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
	public function get(string $id): ?Configuration;

	/**
	 * Save a configuration.
	 *
	 * @param Configuration $config Configuration to save.
	 * @return bool
	 */
	public function save(Configuration $config): bool;

	/**
	 * Delete a configuration.
	 *
	 * @param string $id Configuration ID.
	 * @return bool
	 */
	public function delete(string $id): bool;

	/**
	 * Check if a configuration exists.
	 *
	 * @param string $id Configuration ID.
	 * @return bool
	 */
	public function exists(string $id): bool;

	/**
	 * Get configurations by provider type.
	 *
	 * @param string $provider_type Provider type.
	 * @return array<string, Configuration>
	 */
	public function get_by_provider_type(string $provider_type): array;

	/**
	 * Get configurations that support a capability.
	 *
	 * @param string $capability Capability name.
	 * @return array<string, Configuration>
	 */
	public function get_by_capability(string $capability): array;

	/**
	 * Count configurations.
	 *
	 * @return int
	 */
	public function count(): int;
}
