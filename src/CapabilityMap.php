<?php
/**
 * Capability Map.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter;

use AIRouter\DTO\Configuration;
use AIRouter\Repository\ConfigurationRepositoryInterface;

/**
 * Manages capability-to-configuration mappings.
 */
class CapabilityMap {

	/**
	 * Option key for storing capability map.
	 */
	private const OPTION_KEY = 'ai_router_capability_map';

	/**
	 * In-memory cache.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $cache = null;

	/**
	 * Constructor.
	 *
	 * @param ConfigurationRepositoryInterface $repository Configuration repository.
	 */
	public function __construct(
		private readonly ConfigurationRepositoryInterface $repository,
	) {}

	/**
	 * Get the full capability map.
	 *
	 * @return array<string, string> Capability => Configuration ID.
	 */
	public function get_map(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$raw = get_option( self::OPTION_KEY, [] );

		$this->cache = is_array( $raw ) ? $raw : [];

		return $this->cache;
	}

	/**
	 * Get configuration ID for a capability.
	 *
	 * @param string $capability Capability slug.
	 * @return string|null Configuration ID or null if not mapped.
	 */
	public function get_config_id_for_capability( string $capability ): ?string {
		$map = $this->get_map();
		return $map[ $capability ] ?? null;
	}

	/**
	 * Get configuration for a capability.
	 *
	 * @param string $capability Capability slug.
	 * @return Configuration|null
	 */
	public function get_config_for_capability( string $capability ): ?Configuration {
		$config_id = $this->get_config_id_for_capability( $capability );

		if ( null === $config_id ) {
			return null;
		}

		return $this->repository->get( $config_id );
	}

	/**
	 * Set the configuration for a capability.
	 *
	 * @param string $capability Capability slug.
	 * @param string $config_id  Configuration ID (empty to unset).
	 * @return bool
	 */
	public function set( string $capability, string $config_id ): bool {
		$map = $this->get_map();

		if ( empty( $config_id ) ) {
			unset( $map[ $capability ] );
		} else {
			// Validate config exists and supports the capability.
			$config = $this->repository->get( $config_id );
			if ( null === $config ) {
				return false;
			}

			if ( ! $config->supports_capability( $capability ) ) {
				return false;
			}

			$map[ $capability ] = $config_id;
		}

		return $this->persist( $map );
	}

	/**
	 * Set multiple capability mappings at once.
	 *
	 * @param array<string, string> $mappings Capability => Configuration ID.
	 * @return bool
	 */
	public function set_bulk( array $mappings ): bool {
		$map = $this->get_map();

		foreach ( $mappings as $capability => $config_id ) {
			if ( ! in_array( $capability, Configuration::CAPABILITIES, true ) ) {
				continue;
			}

			if ( empty( $config_id ) ) {
				unset( $map[ $capability ] );
				continue;
			}

			$config = $this->repository->get( $config_id );
			if ( null === $config || ! $config->supports_capability( $capability ) ) {
				continue;
			}

			$map[ $capability ] = $config_id;
		}

		return $this->persist( $map );
	}

	/**
	 * Remove mapping for a capability.
	 *
	 * @param string $capability Capability slug.
	 * @return bool
	 */
	public function remove( string $capability ): bool {
		return $this->set( $capability, '' );
	}

	/**
	 * Remove all mappings for a configuration (e.g., when deleting a config).
	 *
	 * @param string $config_id Configuration ID.
	 * @return bool
	 */
	public function remove_config( string $config_id ): bool {
		$map     = $this->get_map();
		$changed = false;

		foreach ( $map as $capability => $mapped_id ) {
			if ( $mapped_id === $config_id ) {
				unset( $map[ $capability ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			return $this->persist( $map );
		}

		return true;
	}

	/**
	 * Get capabilities mapped to a specific configuration.
	 *
	 * @param string $config_id Configuration ID.
	 * @return array<string>
	 */
	public function get_capabilities_for_config( string $config_id ): array {
		$map          = $this->get_map();
		$capabilities = [];

		foreach ( $map as $capability => $mapped_id ) {
			if ( $mapped_id === $config_id ) {
				$capabilities[] = $capability;
			}
		}

		return $capabilities;
	}

	/**
	 * Get unmapped capabilities.
	 *
	 * @return array<string>
	 */
	public function get_unmapped_capabilities(): array {
		$map       = $this->get_map();
		$unmapped  = [];

		foreach ( Configuration::CAPABILITIES as $capability ) {
			if ( ! isset( $map[ $capability ] ) ) {
				$unmapped[] = $capability;
			}
		}

		return $unmapped;
	}

	/**
	 * Check if a capability is mapped.
	 *
	 * @param string $capability Capability slug.
	 * @return bool
	 */
	public function is_mapped( string $capability ): bool {
		$map = $this->get_map();
		return isset( $map[ $capability ] );
	}

	/**
	 * Clear the in-memory cache.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->cache = null;
	}

	/**
	 * Persist the capability map.
	 *
	 * @param array<string, string> $map Capability map.
	 * @return bool
	 */
	private function persist( array $map ): bool {
		$result      = update_option( self::OPTION_KEY, $map );
		$this->cache = $map;

		return $result;
	}
}
