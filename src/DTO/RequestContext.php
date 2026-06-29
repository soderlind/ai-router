<?php
/**
 * Request Context DTO.
 *
 * Immutable value object representing per-request state during AI routing.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter\DTO;

/**
 * Encapsulates all per-request state during AI generation.
 *
 * This immutable object is created at the start of each AI request
 * and contains all the context needed for routing and configuration.
 * It replaces mutable instance fields on the Router.
 */
final readonly class RequestContext {

	/**
	 * Constructor.
	 *
	 * @param string        $capability      The capability being requested.
	 * @param Configuration $configuration   The matched configuration.
	 * @param string|null   $deployment_id   Azure deployment ID override (if Azure).
	 * @param string|null   $api_version     Azure API version override (if Azure).
	 * @param array<string> $capabilities    Capabilities list override (if Azure).
	 */
	public function __construct(
		public string $capability,
		public Configuration $configuration,
		public ?string $deployment_id = null,
		public ?string $api_version = null,
		public array $capabilities = [],
	) {}

	/**
	 * Create a RequestContext from a Configuration.
	 *
	 * Extracts Azure-specific settings if the provider is Azure.
	 *
	 * @param string        $capability    The capability being requested.
	 * @param Configuration $configuration The matched configuration.
	 * @return self
	 */
	public static function from_configuration( string $capability, Configuration $configuration ): self {
		$deployment_id = null;
		$api_version   = null;
		$capabilities  = [];

		// Extract Azure-specific settings.
		if ( in_array( $configuration->get_provider_type(), [ 'azure-openai', 'azure_openai' ], true ) ) {
			$deployment_id = $configuration->get_setting( 'deployment_id', '' ) ?: null;
			$api_version   = $configuration->get_setting( 'api_version', '' ) ?: null;
			$capabilities  = $configuration->get_capabilities();
		}

		return new self(
			capability: $capability,
			configuration: $configuration,
			deployment_id: $deployment_id,
			api_version: $api_version,
			capabilities: $capabilities,
		);
	}

	/**
	 * Check if this context has Azure overrides.
	 *
	 * @return bool
	 */
	public function has_azure_overrides(): bool {
		return null !== $this->deployment_id || null !== $this->api_version;
	}

	/**
	 * Get the provider type.
	 *
	 * @return string
	 */
	public function get_provider_type(): string {
		return $this->configuration->get_provider_type();
	}

	/**
	 * Get the configuration ID.
	 *
	 * @return string
	 */
	public function get_configuration_id(): string {
		return $this->configuration->get_id();
	}

	/**
	 * Get a setting from the configuration.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( string $key, mixed $default = null ): mixed {
		return $this->configuration->get_setting( $key, $default );
	}
}
