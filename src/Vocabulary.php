<?php
/**
 * Vocabulary - Single source of truth for capabilities and providers.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter;

use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;

/**
 * Centralizes all capability and provider vocabulary.
 *
 * This is the ONLY place where capability slugs, provider types, and
 * their mappings are defined. All other modules import from here.
 */
final class Vocabulary {

	/**
	 * All available capability slugs from WP 7 CapabilityEnum.
	 *
	 * @var list<string>
	 */
	public const CAPABILITIES = [
		'text_generation',
		'chat_history',
		'image_generation',
		'embedding_generation',
		'text_to_speech_conversion',
		'speech_generation',
		'music_generation',
		'video_generation',
	];

	/**
	 * Capability slug to label mapping.
	 *
	 * @var array<string, string>
	 */
	private const CAPABILITY_LABELS = [
		'text_generation'           => 'Text Generation',
		'chat_history'              => 'Chat History',
		'image_generation'          => 'Image Generation',
		'embedding_generation'      => 'Embedding Generation',
		'text_to_speech_conversion' => 'Text to Speech',
		'speech_generation'         => 'Speech Generation',
		'music_generation'          => 'Music Generation',
		'video_generation'          => 'Video Generation',
	];

	/**
	 * Capability slug to CapabilityEnum method name mapping.
	 *
	 * @var array<string, string>
	 */
	private const CAPABILITY_ENUM_METHODS = [
		'text_generation'           => 'textGeneration',
		'chat_history'              => 'chatHistory',
		'image_generation'          => 'imageGeneration',
		'embedding_generation'      => 'embeddingGeneration',
		'text_to_speech_conversion' => 'textToSpeechConversion',
		'speech_generation'         => 'speechGeneration',
		'music_generation'          => 'musicGeneration',
		'video_generation'          => 'videoGeneration',
	];

	/**
	 * SDK capability values to our canonical slugs.
	 *
	 * The SDK uses both camelCase and snake_case for capability values.
	 *
	 * @var array<string, string>
	 */
	private const CAPABILITY_VALUE_MAP = [
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

	/**
	 * Supported provider types with display names.
	 *
	 * Keys are canonical slugs (hyphenated).
	 *
	 * @var array<string, string>
	 */
	public const PROVIDER_TYPES = [
		'openai'       => 'OpenAI',
		'azure-openai' => 'Azure OpenAI',
	];

	/**
	 * Provider type to provider class mapping.
	 *
	 * @var array<string, string>
	 */
	public const PROVIDER_CLASSES = [
		'openai'       => 'WordPress\\OpenAiAiProvider\\Provider\\OpenAiProvider',
		'azure-openai' => 'WordPress\\AzureOpenAiAiProvider\\Provider\\AzureOpenAiProvider',
		'ollama'       => 'Fueled\\AiProviderForOllama\\Provider\\OllamaProvider',
	];

	/**
	 * Provider type to provider ID mapping (for WP AI Client registry).
	 *
	 * @var array<string, string>
	 */
	private const PROVIDER_IDS = [
		'openai'       => 'openai',
		'azure-openai' => 'azure_openai',
		'ollama'       => 'ollama',
	];

	/**
	 * Aliases for provider type normalization.
	 *
	 * Maps non-canonical forms to canonical hyphenated slugs.
	 *
	 * @var array<string, string>
	 */
	private const PROVIDER_ALIASES = [
		'azure_openai' => 'azure-openai',
	];

	/**
	 * Get all capability slugs.
	 *
	 * @return list<string>
	 */
	public static function capabilities(): array {
		return self::CAPABILITIES;
	}

	/**
	 * Get all capabilities with labels.
	 *
	 * @return list<array{slug: string, label: string}>
	 */
	public static function capabilities_with_labels(): array {
		return array_map(
			static fn( string $slug ): array => [
				'slug'  => $slug,
				'label' => self::get_capability_label( $slug ),
			],
			self::CAPABILITIES
		);
	}

	/**
	 * Get human-readable label for a capability.
	 *
	 * @param string $slug Capability slug.
	 * @return string
	 */
	public static function get_capability_label( string $slug ): string {
		if ( isset( self::CAPABILITY_LABELS[ $slug ] ) ) {
			return __( self::CAPABILITY_LABELS[ $slug ], 'ai-router' );
		}
		return ucwords( str_replace( '_', ' ', $slug ) );
	}

	/**
	 * Validate that a capability slug is known.
	 *
	 * @param string $slug Capability slug.
	 * @return bool
	 */
	public static function is_valid_capability( string $slug ): bool {
		return in_array( $slug, self::CAPABILITIES, true );
	}

	/**
	 * Convert a CapabilityEnum to our canonical slug.
	 *
	 * @param CapabilityEnum $capability The capability enum.
	 * @return string|null The canonical slug or null if unknown.
	 */
	public static function capability_enum_to_slug( CapabilityEnum $capability ): ?string {
		$value = (string) $capability;
		return self::CAPABILITY_VALUE_MAP[ $value ] ?? $value;
	}

	/**
	 * Convert a capability slug to CapabilityEnum.
	 *
	 * @param string $slug Capability slug.
	 * @return CapabilityEnum|null
	 */
	public static function slug_to_capability_enum( string $slug ): ?CapabilityEnum {
		$method = self::CAPABILITY_ENUM_METHODS[ $slug ] ?? null;

		if ( $method && class_exists( CapabilityEnum::class ) && method_exists( CapabilityEnum::class, $method ) ) {
			return CapabilityEnum::$method();
		}

		return null;
	}

	/**
	 * Get all provider types.
	 *
	 * @return array<string, string> Slug => Display name.
	 */
	public static function provider_types(): array {
		return self::PROVIDER_TYPES;
	}

	/**
	 * Validate that a provider type is known.
	 *
	 * @param string $type Provider type slug.
	 * @return bool
	 */
	public static function is_valid_provider( string $type ): bool {
		return isset( self::PROVIDER_TYPES[ $type ] );
	}

	/**
	 * Normalize provider type to canonical form.
	 *
	 * Converts aliases (e.g., azure_openai) to canonical slug (azure-openai).
	 *
	 * @param string $type Raw provider type.
	 * @return string Canonical provider type.
	 */
	public static function normalize_provider_type( string $type ): string {
		return self::PROVIDER_ALIASES[ $type ] ?? $type;
	}

	/**
	 * Get provider class for a provider type.
	 *
	 * @param string $type Provider type slug.
	 * @return string|null Provider class name or null if unknown.
	 */
	public static function get_provider_class( string $type ): ?string {
		return self::PROVIDER_CLASSES[ $type ] ?? null;
	}

	/**
	 * Get provider ID for a provider type (for WP AI Client registry).
	 *
	 * @param string $type Provider type slug.
	 * @return string|null Provider ID or null if unknown.
	 */
	public static function get_provider_id( string $type ): ?string {
		return self::PROVIDER_IDS[ $type ] ?? null;
	}

	/**
	 * Get all provider classes.
	 *
	 * @return array<string, string> Type => Class name.
	 */
	public static function provider_classes(): array {
		return self::PROVIDER_CLASSES;
	}
}
