<?php
/**
 * AI Router.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter;

use AIRouter\DTO\Configuration;
use AIRouter\Repository\ConfigurationRepositoryInterface;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;

/**
 * Core routing logic for AI requests.
 */
final class Router {

	/**
	 * Provider class map.
	 */
	private const PROVIDER_CLASSES = [
		'openai'       => 'WordPress\\OpenAiAiProvider\\Provider\\OpenAiProvider',
		'azure-openai' => 'WordPress\\AzureOpenAiAiProvider\\Provider\\AzureOpenAiProvider',
		'ollama'       => 'Fueled\\AiProviderForOllama\\Provider\\OllamaProvider',
	];

	/**
	 * Current capability being processed.
	 *
	 * @var string|null
	 */
	private ?string $current_capability = null;

	/**
	 * Constructor.
	 *
	 * @param ConfigurationRepositoryInterface $repository     Configuration repository.
	 * @param CapabilityMap                    $capability_map Capability map.
	 */
	public function __construct(
		private readonly ConfigurationRepositoryInterface $repository,
		private readonly CapabilityMap $capability_map,
	) {}

	/**
	 * Initialize router hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register AI Router as a connector for credential detection.
		add_action( 'wp_connectors_init', [ $this, 'register_connector' ] );

		// Register providers early so AI Client knows about them.
		add_action( 'init', [ $this, 'register_configured_providers' ], 5 );

		// Hook before AI generation to inject routing.
		add_action( 'wp_ai_client_before_generate_result', [ $this, 'before_generate' ], 5 );

		// Set up authentication for configured providers on init.
		add_action( 'init', [ $this, 'setup_provider_authentication' ], 25 );

		// Tell AI plugin we have valid credentials when we have configured providers.
		add_filter( 'wpai_pre_has_valid_credentials_check', [ $this, 'filter_has_valid_credentials' ] );
	}

	/**
	 * Register AI Router as a connector.
	 *
	 * This makes has_ai_credentials() return true when AI Router has configurations,
	 * without making any provider (like OpenAI) think it's configured.
	 *
	 * @param \WP_Connector_Registry $registry Connector registry.
	 * @return void
	 */
	public function register_connector( \WP_Connector_Registry $registry ): void {
		$registry->register(
			'ai_router',
			[
				'name'           => 'AI Router',
				'description'    => __( 'Capability-based AI provider routing.', 'ai-router' ),
				'type'           => 'ai_provider',
				'authentication' => [
					'method'       => 'api_key',
					'setting_name' => 'ai_router_credential_sentinel',
				],
			]
		);
	}

	/**
	 * Filter AI plugin's credential check.
	 *
	 * Returns true if AI Router has at least one configuration with an API key,
	 * allowing the AI plugin to function without separately configured connectors.
	 *
	 * @param bool|null $has_valid Whether credentials are valid (null = use default check).
	 * @return bool|null
	 */
	public function filter_has_valid_credentials( ?bool $has_valid ): ?bool {
		// If already validated by something else, don't override.
		if ( true === $has_valid ) {
			return $has_valid;
		}

		// Check if we have any configuration with an API key.
		foreach ( $this->repository->get_all() as $config ) {
			$api_key = $config->get_setting( 'api_key', '' );
			if ( ! empty( $api_key ) ) {
				return true;
			}
		}

		// Fall back to default check.
		return $has_valid;
	}

	/**
	 * Register providers based on which provider plugins are installed.
	 *
	 * This ensures providers are available in the AI Client registry
	 * when the provider plugin is installed. Configuration (API keys, etc.)
	 * is handled separately by AI Router.
	 *
	 * @return void
	 */
	public function register_configured_providers(): void {
		if ( ! class_exists( AiClient::class) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		foreach ( self::PROVIDER_CLASSES as $provider_type => $provider_class ) {
			// Skip if provider plugin is not installed.
			if ( ! class_exists( $provider_class ) ) {
				continue;
			}

			// Get provider ID for this type.
			$provider_id = $this->get_provider_id( $provider_type );

			if ( ! $provider_id ) {
				continue;
			}

			// Register if not already registered.
			if ( ! $registry->hasProvider( $provider_id ) ) {
				try {
					$registry->registerProvider( $provider_class );

					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( sprintf( 'AI Router: Registered provider %s', $provider_id ) );
					}
				} catch (\Throwable $e) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( sprintf( 'AI Router: Failed to register provider %s: %s', $provider_id, $e->getMessage() ) );
					}
				}
			}
		}
	}

	/**
	 * Hook called before AI generation.
	 *
	 * Receives a BeforeGenerateResultEvent from the AI Client SDK.
	 * At this point the model is already selected, so we just track
	 * the capability for potential use in other hooks.
	 *
	 * @param object $event The BeforeGenerateResultEvent instance.
	 * @return void
	 */
	public function before_generate( object $event ): void {
		// Extract capability from the event.
		$capability = null;
		if ( is_callable( [ $event, 'getCapability' ] ) ) {
			$cap_enum = $event->getCapability();
			if ( null !== $cap_enum ) {
				// CapabilityEnum has a ->value property with the string slug.
				$capability = $cap_enum->value ?? (string) $cap_enum;
			}
		}

		if ( null === $capability ) {
			return;
		}

		$this->current_capability = $capability;

		$config = $this->get_configuration_for_capability( $capability );

		if ( null === $config ) {
			return;
		}

		/**
		 * Fires when AI Router processes a request.
		 *
		 * @param Configuration $config     The configuration matched.
		 * @param string        $capability The capability being used.
		 * @param object        $event      The BeforeGenerateResultEvent.
		 */
		do_action( 'ai_router_routed', $config, $capability, $event );
	}

	/**
	 * Get configuration for a capability with fallback.
	 *
	 * @param string $capability Capability slug.
	 * @return Configuration|null
	 */
	public function get_configuration_for_capability( string $capability ): ?Configuration {
		/**
		 * Filters the configuration for a capability.
		 *
		 * @param Configuration|null $config     The configuration (null to use default logic).
		 * @param string             $capability The capability slug.
		 */
		$config = apply_filters( 'ai_router_get_configuration', null, $capability );

		if ( $config instanceof Configuration ) {
			return $config;
		}

		// Check explicit capability mapping.
		$config = $this->capability_map->get_config_for_capability( $capability );

		if ( $config && $this->is_config_available( $config ) ) {
			return $config;
		}

		// Fallback to default configuration.
		$default = $this->repository->get_default();

		if ( $default && $default->supports_capability( $capability ) && $this->is_config_available( $default ) ) {
			return $default;
		}

		// Last resort: find any config that supports the capability.
		$configs = $this->repository->get_by_capability( $capability );

		foreach ( $configs as $fallback ) {
			if ( $this->is_config_available( $fallback ) ) {
				/**
				 * Filters the fallback configuration.
				 *
				 * @param Configuration $fallback   The fallback configuration.
				 * @param string        $capability The capability slug.
				 */
				return apply_filters( 'ai_router_fallback_config', $fallback, $capability );
			}
		}

		return null;
	}

	/**
	 * Set up authentication for all configured providers.
	 *
	 * @return void
	 */
	public function setup_provider_authentication(): void {
		if ( ! class_exists( AiClient::class) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();
		$configs  = $this->repository->get_all();

		foreach ( $configs as $config ) {
			$provider_id = $this->get_provider_id_for_config( $config );
			$api_key     = $config->get_setting( 'api_key', '' );

			if ( ! $provider_id || empty( $api_key ) ) {
				continue;
			}

			if ( ! $registry->hasProvider( $provider_id ) ) {
				continue;
			}

			// For Azure, need custom auth class.
			if ( 'azure-openai' === $config->get_provider_type() ) {
				$this->setup_azure_authentication( $config, $registry );
			} else {
				// Standard API key auth.
				try {
					$auth = new ApiKeyRequestAuthentication( $api_key );
					$registry->setProviderRequestAuthentication( $provider_id, $auth );
				} catch (\Throwable $e) {
					// Log but don't break.
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( sprintf( 'AI Router: Failed to set auth for %s: %s', $provider_id, $e->getMessage() ) );
					}
				}
			}
		}
	}

	/**
	 * Set up Azure-specific authentication.
	 *
	 * @param Configuration $config   Configuration.
	 * @param object        $registry Provider registry.
	 * @return void
	 */
	private function setup_azure_authentication( Configuration $config, object $registry ): void {
		$provider_id = 'azure_openai';
		$api_key     = $config->get_setting( 'api_key', '' );

		if ( empty( $api_key ) ) {
			return;
		}

		// Check if Azure provider's custom auth class exists.
		$auth_class = 'WordPress\\AzureOpenAiAiProvider\\Http\\AzureApiKeyRequestAuthentication';

		if ( class_exists( $auth_class ) ) {
			try {
				// AzureApiKeyRequestAuthentication only takes API key.
				// Endpoint/deployment/api_version are synced to connector options
				// by ConfigurationRepository::sync_connector_option().
				$auth = new $auth_class( $api_key );
				$registry->setProviderRequestAuthentication( $provider_id, $auth );
			} catch (\Throwable $e) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( sprintf( 'AI Router: Failed to set Azure auth: %s', $e->getMessage() ) );
				}
			}
		}
	}

	/**
	 * Get the provider ID for a provider type.
	 *
	 * @param string $provider_type Provider type (e.g., 'openai', 'azure-openai').
	 * @return string|null
	 */
	private function get_provider_id( string $provider_type ): ?string {
		$map = [
			'openai'       => 'openai',
			'azure-openai' => 'azure_openai',
			'ollama'       => 'ollama',
		];

		return $map[ $provider_type ] ?? null;
	}

	/**
	 * Get the provider ID for a configuration.
	 *
	 * @param Configuration $config Configuration.
	 * @return string|null
	 */
	private function get_provider_id_for_config( Configuration $config ): ?string {
		return $this->get_provider_id( $config->get_provider_type() );
	}

	/**
	 * Check if a configuration is available (provider registered and configured).
	 *
	 * @param Configuration $config Configuration to check.
	 * @return bool
	 */
	private function is_config_available( Configuration $config ): bool {
		$api_key = $config->get_setting( 'api_key', '' );

		if ( empty( $api_key ) ) {
			return false;
		}

		// For Azure, also need endpoint.
		if ( 'azure-openai' === $config->get_provider_type() ) {
			$endpoint = $config->get_setting( 'endpoint', '' );
			if ( empty( $endpoint ) ) {
				return false;
			}
		}

		// Check if underlying provider is registered.
		if ( class_exists( AiClient::class) ) {
			$registry    = AiClient::defaultRegistry();
			$provider_id = $this->get_provider_id_for_config( $config );

			if ( $provider_id && ! $registry->hasProvider( $provider_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Convert string capability to CapabilityEnum.
	 *
	 * @param string $capability Capability slug.
	 * @return CapabilityEnum|null
	 */
	public static function string_to_capability_enum( string $capability ): ?CapabilityEnum {
		$map = [
			'text_generation'           => 'textGeneration',
			'chat_history'              => 'chatHistory',
			'image_generation'          => 'imageGeneration',
			'embedding_generation'      => 'embeddingGeneration',
			'text_to_speech_conversion' => 'textToSpeechConversion',
			'speech_generation'         => 'speechGeneration',
			'music_generation'          => 'musicGeneration',
			'video_generation'          => 'videoGeneration',
		];

		$method = $map[ $capability ] ?? null;

		if ( $method && method_exists( CapabilityEnum::class, $method ) ) {
			return CapabilityEnum::$method();
		}

		return null;
	}

	/**
	 * Get human-readable capability name.
	 *
	 * @param string $capability Capability slug.
	 * @return string
	 */
	public static function get_capability_label( string $capability ): string {
		$labels = [
			'text_generation'           => __( 'Text Generation', 'ai-router' ),
			'chat_history'              => __( 'Chat History', 'ai-router' ),
			'image_generation'          => __( 'Image Generation', 'ai-router' ),
			'embedding_generation'      => __( 'Embedding Generation', 'ai-router' ),
			'text_to_speech_conversion' => __( 'Text to Speech', 'ai-router' ),
			'speech_generation'         => __( 'Speech Generation', 'ai-router' ),
			'music_generation'          => __( 'Music Generation', 'ai-router' ),
			'video_generation'          => __( 'Video Generation', 'ai-router' ),
		];

		return $labels[ $capability ] ?? $capability;
	}
}
