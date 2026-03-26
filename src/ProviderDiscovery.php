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
		$value = $capability->getValue();

		// Map SDK capability values to our slugs.
		$map = [
			'text_generation'           => 'text_generation',
			'textGeneration'            => 'text_generation',
			'chat_history'              => 'chat_history',
			'chatHistory'               => 'chat_history',
			'image_generation'          => 'image_generation',
			'imageGeneration'           => 'image_generation',
			'embedding_generation'      => 'embedding_generation',
			'embeddingGeneration'       => 'embedding_generation',
			'text_to_speech_conversion' => 'text_to_speech_conversion',
			'textToSpeechConversion'    => 'text_to_speech_conversion',
			'speech_generation'         => 'speech_generation',
			'speechGeneration'          => 'speech_generation',
			'music_generation'          => 'music_generation',
			'musicGeneration'           => 'music_generation',
			'video_generation'          => 'video_generation',
			'videoGeneration'           => 'video_generation',
		];

		return $map[ $value ] ?? $value;
	}

	/**
	 * Get provider-specific configuration fields.
	 *
	 * @param string $provider_id Provider ID.
	 * @param object $metadata    Provider metadata.
	 * @return list<array{key: string, label: string, type: string, placeholder?: string}>
	 */
	private function get_provider_fields( string $provider_id, object $metadata ): array {
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
		return [
			[ 'slug' => 'text_generation', 'label' => __( 'Text Generation', 'ai-router' ) ],
			[ 'slug' => 'chat_history', 'label' => __( 'Chat History', 'ai-router' ) ],
			[ 'slug' => 'image_generation', 'label' => __( 'Image Generation', 'ai-router' ) ],
			[ 'slug' => 'embedding_generation', 'label' => __( 'Embedding Generation', 'ai-router' ) ],
			[ 'slug' => 'text_to_speech_conversion', 'label' => __( 'Text to Speech', 'ai-router' ) ],
			[ 'slug' => 'speech_generation', 'label' => __( 'Speech Generation', 'ai-router' ) ],
			[ 'slug' => 'music_generation', 'label' => __( 'Music Generation', 'ai-router' ) ],
			[ 'slug' => 'video_generation', 'label' => __( 'Video Generation', 'ai-router' ) ],
		];
	}

	/**
	 * Get capability label by slug.
	 *
	 * @param string $slug Capability slug.
	 * @return string Capability label.
	 */
	public function get_capability_label( string $slug ): string {
		foreach ( $this->get_all_capabilities() as $cap ) {
			if ( $cap[ 'slug' ] === $slug ) {
				return $cap[ 'label' ];
			}
		}
		return ucwords( str_replace( '_', ' ', $slug ) );
	}
}
