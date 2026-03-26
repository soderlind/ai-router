<?php
/**
 * Configuration Repository Interface.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter\Repository;

use AIRouter\DTO\Configuration;

/**
 * Interface for configuration repository.
 */
interface ConfigurationRepositoryInterface {
	/**
	 * Get all configurations.
	 *
	 * @return array<string, Configuration>
	 */
	public function get_all(): array;

	/**
	 * Get a configuration by ID.
	 *
	 * @param string $id Configuration ID.
	 * @return Configuration|null
	 */
	public function get( string $id ): ?Configuration;

	/**
	 * Save a configuration.
	 *
	 * @param Configuration $config Configuration to save.
	 * @return bool
	 */
	public function save( Configuration $config ): bool;

	/**
	 * Delete a configuration.
	 *
	 * @param string $id Configuration ID.
	 * @return bool
	 */
	public function delete( string $id ): bool;

	/**
	 * Check if a configuration exists.
	 *
	 * @param string $id Configuration ID.
	 * @return bool
	 */
	public function exists( string $id ): bool;

	/**
	 * Get configurations by provider type.
	 *
	 * @param string $provider_type Provider type.
	 * @return array<string, Configuration>
	 */
	public function get_by_provider_type( string $provider_type ): array;

	/**
	 * Get configurations that support a capability.
	 *
	 * @param string $capability Capability name.
	 * @return array<string, Configuration>
	 */
	public function get_by_capability( string $capability ): array;

	/**
	 * Count configurations.
	 *
	 * @return int
	 */
	public function count(): int;

	/**
	 * Sync configuration settings to connector options.
	 *
	 * For Azure OpenAI, syncs api_key and endpoint only — NOT deployment_id
	 * or capabilities — so the model metadata directory discovers all model types.
	 *
	 * @param Configuration $config Configuration to sync.
	 * @return void
	 */
	public function sync_connector_option( Configuration $config ): void;

	/**
	 * Sync request-time options for a specific capability.
	 *
	 * Called just before an AI request is executed. Sets deployment_id and
	 * api_version for the matched configuration so the provider uses the
	 * correct values for this specific request.
	 *
	 * @param Configuration $config Configuration matched for this request.
	 * @return void
	 */
	public function sync_request_options( Configuration $config ): void;
}
