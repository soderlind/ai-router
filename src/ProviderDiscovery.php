<?php
/**
 * Provider Discovery.
 *
 * Discovers installed AI providers from the WordPress AI Client SDK.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;

/**
 * Discovers and extracts information from installed AI providers.
 */
class ProviderDiscovery {

	/**
	 * Provider registry instance.
	 *
	 * @var ProviderRegistry|null
	 */
	private ?ProviderRegistry $registry = null;

	/**
	 * Get the provider registry.
	 *
	 * @return ProviderRegistry|null
	 */
	private function get_registry(): ?ProviderRegistry {
		if ( null === $this->registry ) {
			if ( ! class_exists( AiClient::class) ) {
				return null;
			}
			$this->registry = AiClient::defaultRegistry();
		}
		return $this->registry;
	}

	/**
	 * Get all installed providers with their metadata.
	 *
	 * @return array<string, array{
	 *     id: string,
	 *     name: string,
	 *     description: string,
	 *     capabilities: list<string>,
	 *     is_configured: bool,
	 *     credentials_url: string|null,
	 *     fields: list<array{key: string, label: string, type: string, placeholder?: string}>
	 * }>
	 */
	public function get_providers(): array {
		$registry = $this->get_registry();
		if ( null === $registry ) {
			return [];
		}

		$providers = [];

		foreach ( $registry->getRegisteredProviderIds() as $provider_id ) {
			try {
				$class_name = $registry->getProviderClassName( $provider_id );
				$metadata   = $class_name::metadata();

				// Extract capabilities from all models.
				$capabilities = $this->extract_provider_capabilities( $class_name );

				// Check if provider is configured (has valid credentials).
				$is_configured = $registry->isProviderConfigured( $provider_id );

				// Get credentials URL if available.
				$credentials_url = $metadata->getCredentialsUrl();

				// Get provider-specific fields based on provider type.
				$fields = $this->get_provider_fields( $provider_id, $metadata );

				$providers[ $provider_id ] = [
					'id'              => $provider_id,
					'name'            => $metadata->getName() ?: ucwords( str_replace( [ '-', '_' ], ' ', $provider_id ) ),
					'description'     => $metadata->getDescription() ?: '',
					'capabilities'    => $capabilities,
					'is_configured'   => $is_configured,
					'credentials_url' => $credentials_url,
					'fields'          => $fields,
				];
			} catch (\Throwable $e) {
				// Skip providers that fail to load.
				continue;
			}
		}

		// Merge in fallback providers that aren't discovered.
		$providers = array_merge( $this->get_fallback_providers(), $providers );

		return $providers;
	}

	/**
	 * Get fallback providers for common AI services.
	 *
	 * These are always available even if not registered in the AI Client SDK.
	 *
	 * @return array<string, array>
	 */
	private function get_fallback_providers(): array {
		return [
			'openai'    => [
				'id'              => 'openai',
				'name'            => __( 'OpenAI', 'ai-router' ),
				'description'     => __( 'GPT models via OpenAI API.', 'ai-router' ),
				'capabilities'    => [],
				'is_configured'   => false,
				'credentials_url' => 'https://platform.openai.com/api-keys',
				'fields'          => [
					[
						'key'   => 'api_key',
						'label' => __( 'API Key', 'ai-router' ),
						'type'  => 'password',
					],
				],
			],
			'anthropic' => [
				'id'              => 'anthropic',
				'name'            => __( 'Anthropic', 'ai-router' ),
				'description'     => __( 'Claude models via Anthropic API.', 'ai-router' ),
				'capabilities'    => [],
				'is_configured'   => false,
				'credentials_url' => 'https://console.anthropic.com/api-keys',
				'fields'          => [
					[
						'key'   => 'api_key',
						'label' => __( 'API Key', 'ai-router' ),
						'type'  => 'password',
					],
				],
			],
			'google'    => [
				'id'              => 'google',
				'name'            => __( 'Google (Gemini)', 'ai-router' ),
				'description'     => __( 'Gemini models via Google AI.', 'ai-router' ),
				'capabilities'    => [],
				'is_configured'   => false,
				'credentials_url' => 'https://aistudio.google.com/app/apikey',
				'fields'          => [
					[
						'key'   => 'api_key',
						'label' => __( 'API Key', 'ai-router' ),
						'type'  => 'password',
					],
				],
			],
			'ollama'    => [
				'id'              => 'ollama',
				'name'            => __( 'Ollama (Local)', 'ai-router' ),
				'description'     => __( 'Local models via Ollama.', 'ai-router' ),
				'capabilities'    => [],
				'is_configured'   => false,
				'credentials_url' => null,
				'fields'          => [
					[
						'key'         => 'endpoint',
						'label'       => __( 'Server URL', 'ai-router' ),
						'type'        => 'text',
						'placeholder' => 'http://localhost:11434',
					],
					[
						'key'         => 'model',
						'label'       => __( 'Model Name', 'ai-router' ),
						'type'        => 'text',
						'placeholder' => 'llama2',
					],
				],
			],
		];
	}

	/**
	 * Extract unique capabilities from a provider's models.
	 *
	 * @param class-string $class_name Provider class name.
	 * @return list<string> List of capability slugs.
	 */
	private function extract_provider_capabilities( string $class_name ): array {
		$capabilities = [];

		try {
			$model_directory = $class_name::modelMetadataDirectory();
			$models          = $model_directory->listModelMetadata();

			foreach ( $models as $model_metadata ) {
				foreach ( $model_metadata->getSupportedCapabilities() as $capability ) {
					$slug = $this->capability_to_slug( $capability );
					if ( $slug && ! in_array( $slug, $capabilities, true ) ) {
						$capabilities[] = $slug;
					}
				}
			}
		} catch (\Throwable $e) {
			// Return empty if model metadata fails.
		}

		return $capabilities;
	}

	/**
	 * Convert a CapabilityEnum to our slug format.
	 *
	 * @param CapabilityEnum $capability The capability enum.
	 * @return string|null The slug or null if unknown.
	 */
	private function capability_to_slug( CapabilityEnum $capability ): ?string {
		return Vocabulary::capability_enum_to_slug( $capability );
	}

	/**
	 * Discover provider settings from WordPress registered settings.
	 *
	 * Looks for settings registered under the 'connectors' group with the pattern
	 * connectors_ai_{provider_id}_*.
	 *
	 * @param string $provider_id Provider ID (e.g. 'azure-ai-foundry').
	 * @return list<array{key: string, label: string, type: string, description?: string}>
	 */
	private function discover_connector_settings( string $provider_id ): array {
		if ( ! function_exists( 'get_registered_settings' ) ) {
			return [];
		}

		$all_settings = get_registered_settings();
		$fields       = [];

		// Normalize provider ID: convert hyphens to underscores for option name matching.
		$provider_slug = str_replace( '-', '_', $provider_id );
		$prefix        = 'connectors_ai_' . $provider_slug . '_';

		// Keys to skip:
		// - Internal sentinels: status_api_key, status, sentinel
		// - Auto-detected by providers: capabilities, model_name
		$skip_keys = [ 'status_api_key', 'status', 'sentinel', 'capabilities', 'model_name' ];

		foreach ( $all_settings as $option_name => $setting ) {
			// Check if this setting belongs to our provider.
			if ( strpos( $option_name, $prefix ) !== 0 ) {
				continue;
			}

			// Check if it's in the connectors group.
			if ( ! isset( $setting['group'] ) || 'connectors' !== $setting['group'] ) {
				continue;
			}

			// Extract the field key (part after the prefix).
			$key = substr( $option_name, strlen( $prefix ) );
			if ( empty( $key ) ) {
				continue;
			}

			// Skip internal sentinel/status settings.
			if ( in_array( $key, $skip_keys, true ) ) {
				continue;
			}

			// Determine field type based on setting type and key name.
			$type = 'text';
			if ( 'boolean' === ( $setting['type'] ?? '' ) ) {
				$type = 'checkbox';
			} elseif ( 'array' === ( $setting['type'] ?? '' ) ) {
				$type = 'multiselect';
			} elseif ( strpos( $key, 'api_key' ) !== false || strpos( $key, 'password' ) !== false ) {
				$type = 'password';
			}

			$field = [
				'key'   => $key,
				'label' => $setting['label'] ?? ucwords( str_replace( '_', ' ', $key ) ),
				'type'  => $type,
			];

			if ( ! empty( $setting['description'] ) ) {
				$field['description'] = $setting['description'];
			}

			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * Get provider-specific configuration fields.
	 *
	 * First attempts dynamic discovery from WordPress registered settings,
	 * then falls back to hardcoded fields for known providers.
	 *
	 * @param string $provider_id Provider ID.
	 * @param object $metadata    Provider metadata.
	 * @return list<array{key: string, label: string, type: string, placeholder?: string}>
	 */
	private function get_provider_fields( string $provider_id, object $metadata ): array {
		// Try dynamic discovery first.
		$discovered = $this->discover_connector_settings( $provider_id );
		if ( ! empty( $discovered ) ) {
			return $discovered;
		}

		// Fall back to hardcoded fields.

		// Base field: API key (most providers need this).
		$fields = [
			[
				'key'   => 'api_key',
				'label' => __( 'API Key', 'ai-router' ),
				'type'  => 'password',
			],
		];

		// Normalize provider ID for matching (handle both underscore and hyphen variants).
		$normalized = str_replace( [ '-', '_' ], '', strtolower( $provider_id ) );

		// Provider-specific additional fields.
		if ( in_array( $normalized, [ 'azureopenai', 'azure_openai' ], true ) ) {
			$fields = array_merge( $fields, [
				[
					'key'         => 'endpoint',
					'label'       => __( 'Endpoint URL', 'ai-router' ),
					'type'        => 'text',
					'placeholder' => 'https://your-resource.openai.azure.com',
				],
				[
					'key'   => 'deployment_id',
					'label' => __( 'Deployment ID', 'ai-router' ),
					'type'  => 'text',
				],
				[
					'key'         => 'api_version',
					'label'       => __( 'API Version', 'ai-router' ),
					'type'        => 'text',
					'placeholder' => '2024-02-15-preview',
				],
			] );
		} elseif ( $normalized === 'ollama' ) {
			$fields = [
				[
					'key'         => 'endpoint',
					'label'       => __( 'Ollama Server URL', 'ai-router' ),
					'type'        => 'text',
					'placeholder' => 'http://localhost:11434',
				],
				[
					'key'         => 'model',
					'label'       => __( 'Model Name', 'ai-router' ),
					'type'        => 'text',
					'placeholder' => 'llama2',
				],
			];
		}
		// OpenAI, Anthropic, Google just need API key (base fields are enough).

		return $fields;
	}

	/**
	 * Get all available capabilities with labels.
	 *
	 * @return list<array{slug: string, label: string}>
	 */
	public function get_all_capabilities(): array {
		return Vocabulary::capabilities_with_labels();
	}

	/**
	 * Get capability label by slug.
	 *
	 * @param string $slug Capability slug.
	 * @return string Capability label.
	 */
	public function get_capability_label( string $slug ): string {
		return Vocabulary::get_capability_label( $slug );
	}
}
