<?php
/**
 * Capability Map Interface.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter;

/**
 * Interface for capability to configuration mapping.
 */
interface CapabilityMapInterface {
	/**
	 * Get the full capability map.
	 *
	 * @return array<string, string> Capability => Configuration ID.
	 */
	public function get_map(): array;

	/**
	 * Get the configuration ID for a capability.
	 *
	 * @param string $capability Capability slug.
	 * @return string|null Configuration ID or null if not mapped.
	 */
	public function get_config_id_for_capability( string $capability ): ?string;

	/**
	 * Set multiple mappings at once.
	 *
	 * @param array<string, string> $mappings Capability => config_id mappings.
	 * @return bool True if successful.
	 */
	public function set_bulk( array $mappings ): bool;

	/**
	 * Get capabilities mapped to a specific configuration.
	 *
	 * @param string $config_id Configuration ID.
	 * @return array<string>
	 */
	public function get_capabilities_for_config( string $config_id ): array;

	/**
	 * Remove all mappings for a configuration.
	 *
	 * @param string $config_id Configuration ID.
	 * @return bool True if successful.
	 */
	public function remove_config( string $config_id ): bool;
}
