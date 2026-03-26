<?php
/**
 * Configuration DTO.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter\DTO;

use JsonSerializable;

/**
 * Immutable configuration object representing a provider configuration.
 */
final class Configuration implements JsonSerializable {

	/**
	 * Supported provider types.
	 */
	public const PROVIDER_TYPES = [
		'openai'       => 'OpenAI',
		'azure-openai' => 'Azure OpenAI',
	];

	/**
	 * All available capabilities from WP 7 CapabilityEnum.
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
	 * Constructor.
	 *
	 * @param string               $id            Unique configuration ID.
	 * @param string               $name          Human-readable name.
	 * @param string               $provider_type Provider type slug (openai, azure-openai).
	 * @param array<string, mixed> $settings      Provider-specific settings (api_key, endpoint, deployment_id, etc.).
	 * @param array<string>        $capabilities  List of capability slugs this config supports.
	 * @param bool                 $is_default    Whether this is the default fallback config.
	 */
	public function __construct(
		private readonly string $id,
		private readonly string $name,
		private readonly string $provider_type,
		private readonly array $settings = [],
		private readonly array $capabilities = [],
		private readonly bool $is_default = false,
	) {}

	/**
	 * Create from array (e.g., from database).
	 *
	 * @param array<string, mixed> $data Raw data array.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self(
			id: $data[ 'id' ] ?? wp_generate_uuid4(),
			name: $data[ 'name' ] ?? '',
			provider_type: $data[ 'provider_type' ] ?? 'openai',
			settings: $data[ 'settings' ] ?? [],
			capabilities: $data[ 'capabilities' ] ?? [],
			is_default: $data[ 'is_default' ] ?? false,
		);
	}

	/**
	 * Get configuration ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Get configuration name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get provider type.
	 *
	 * @return string
	 */
	public function get_provider_type(): string {
		return $this->provider_type;
	}

	/**
	 * Get provider settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings(): array {
		return $this->settings;
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not set.
	 * @return mixed
	 */
	public function get_setting( string $key, mixed $default = null ): mixed {
		return $this->settings[ $key ] ?? $default;
	}

	/**
	 * Get supported capabilities.
	 *
	 * @return array<string>
	 */
	public function get_capabilities(): array {
		return $this->capabilities;
	}

	/**
	 * Check if this config supports a capability.
	 *
	 * @param string $capability Capability slug.
	 * @return bool
	 */
	public function supports_capability( string $capability ): bool {
		return in_array( $capability, $this->capabilities, true );
	}

	/**
	 * Check if this is the default config.
	 *
	 * @return bool
	 */
	public function is_default(): bool {
		return $this->is_default;
	}

	/**
	 * Create a copy with updated values.
	 *
	 * @param array<string, mixed> $updates Values to update.
	 * @return self
	 */
	public function with( array $updates ): self {
		return new self(
			id: $updates[ 'id' ] ?? $this->id,
			name: $updates[ 'name' ] ?? $this->name,
			provider_type: $updates[ 'provider_type' ] ?? $this->provider_type,
			settings: $updates[ 'settings' ] ?? $this->settings,
			capabilities: $updates[ 'capabilities' ] ?? $this->capabilities,
			is_default: $updates[ 'is_default' ] ?? $this->is_default,
		);
	}

	/**
	 * Convert to array for storage.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'id'            => $this->id,
			'name'          => $this->name,
			'provider_type' => $this->provider_type,
			'settings'      => $this->settings,
			'capabilities'  => $this->capabilities,
			'is_default'    => $this->is_default,
		];
	}

	/**
	 * Convert to array for JSON serialization (masks sensitive data).
	 *
	 * @return array<string, mixed>
	 */
	public function jsonSerialize(): array {
		$data               = $this->to_array();
		$data[ 'settings' ] = $this->mask_sensitive_settings( $data[ 'settings' ] );
		return $data;
	}

	/**
	 * Mask sensitive settings for output.
	 *
	 * @param array<string, mixed> $settings Settings array.
	 * @return array<string, mixed>
	 */
	private function mask_sensitive_settings( array $settings ): array {
		$sensitive_keys = [ 'api_key', 'secret', 'password', 'token' ];

		foreach ( $settings as $key => $value ) {
			if ( is_string( $value ) && $this->is_sensitive_key( $key, $sensitive_keys ) ) {
				$len              = strlen( $value );
				$settings[ $key ] = $len > 8
					? substr( $value, 0, 4 ) . str_repeat( '*', $len - 8 ) . substr( $value, -4 )
					: str_repeat( '*', $len );
			}
		}

		return $settings;
	}

	/**
	 * Check if a key is sensitive.
	 *
	 * @param string        $key            Key to check.
	 * @param array<string> $sensitive_keys List of sensitive key patterns.
	 * @return bool
	 */
	private function is_sensitive_key( string $key, array $sensitive_keys ): bool {
		$key_lower = strtolower( $key );
		foreach ( $sensitive_keys as $pattern ) {
			if ( str_contains( $key_lower, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Validate the configuration.
	 *
	 * @return array<string> List of validation errors (empty if valid).
	 */
	public function validate(): array {
		$errors = [];

		if ( empty( $this->name ) ) {
			$errors[] = __( 'Configuration name is required.', 'ai-router' );
		}

		if ( empty( $this->provider_type ) ) {
			$errors[] = __( 'Provider type is required.', 'ai-router' );
		}

		// Provider-specific validation.
		if ( 'azure-openai' === $this->provider_type ) {
			if ( empty( $this->get_setting( 'endpoint' ) ) ) {
				$errors[] = __( 'Azure OpenAI endpoint is required.', 'ai-router' );
			}
		}

		// Validate capabilities.
		foreach ( $this->capabilities as $capability ) {
			if ( ! in_array( $capability, self::CAPABILITIES, true ) ) {
				$errors[] = sprintf(
					/* translators: %s: capability name */
					__( 'Invalid capability: %s', 'ai-router' ),
					$capability
				);
			}
		}

		return $errors;
	}
}
