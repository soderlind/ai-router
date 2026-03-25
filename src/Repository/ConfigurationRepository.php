<?php
/**
 * Configuration Repository.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter\Repository;

use AIRouter\DTO\Configuration;

/**
 * Repository for CRUD operations on configurations via Options API.
 */
final class ConfigurationRepository implements ConfigurationRepositoryInterface {

	/**
	 * Option key for storing configurations.
	 */
	private const OPTION_KEY = 'ai_router_configurations';

	/**
	 * Option key for default configuration ID.
	 */
	private const DEFAULT_OPTION_KEY = 'ai_router_default_config';

	/**
	 * In-memory cache of configurations.
	 *
	 * @var array<string, Configuration>|null
	 */
	private ?array $cache = null;

	/**
	 * Get all configurations.
	 *
	 * @return array<string, Configuration> Keyed by configuration ID.
	 */
	public function get_all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$raw = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $raw ) ) {
			$raw = [];
		}

		$this->cache = [];
		foreach ( $raw as $data ) {
			if ( is_array( $data ) && isset( $data['id'] ) ) {
				$config                        = Configuration::from_array( $data );
				$this->cache[ $config->get_id() ] = $config;
			}
		}

		return $this->cache;
	}

	/**
	 * Get a configuration by ID.
	 *
	 * @param string $id Configuration ID.
	 * @return Configuration|null
	 */
	public function get( string $id ): ?Configuration {
		$all = $this->get_all();
		return $all[ $id ] ?? null;
	}

	/**
	 * Check if a configuration exists.
	 *
	 * @param string $id Configuration ID.
	 * @return bool
	 */
	public function exists( string $id ): bool {
		return null !== $this->get( $id );
	}

	/**
	 * Save a configuration (create or update).
	 *
	 * @param Configuration $config Configuration to save.
	 * @return bool True on success.
	 */
	public function save( Configuration $config ): bool {
		$all                        = $this->get_all();
		$all[ $config->get_id() ] = $config;

		return $this->persist( $all );
	}

	/**
	 * Delete a configuration.
	 *
	 * @param string $id Configuration ID.
	 * @return bool True on success.
	 */
	public function delete( string $id ): bool {
		$all = $this->get_all();

		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}

		unset( $all[ $id ] );

		// If deleted config was default, clear the default.
		if ( $this->get_default_id() === $id ) {
			$this->set_default_id( '' );
		}

		return $this->persist( $all );
	}

	/**
	 * Get configurations by provider type.
	 *
	 * @param string $provider_type Provider type slug.
	 * @return array<Configuration>
	 */
	public function get_by_provider_type( string $provider_type ): array {
		return array_filter(
			$this->get_all(),
			static fn( Configuration $c ) => $c->get_provider_type() === $provider_type
		);
	}

	/**
	 * Get configurations that support a capability.
	 *
	 * @param string $capability Capability slug.
	 * @return array<Configuration>
	 */
	public function get_by_capability( string $capability ): array {
		return array_filter(
			$this->get_all(),
			static fn( Configuration $c ) => $c->supports_capability( $capability )
		);
	}

	/**
	 * Get the default configuration ID.
	 *
	 * @return string Empty string if not set.
	 */
	public function get_default_id(): string {
		return (string) get_option( self::DEFAULT_OPTION_KEY, '' );
	}

	/**
	 * Set the default configuration ID.
	 *
	 * @param string $id Configuration ID.
	 * @return bool
	 */
	public function set_default_id( string $id ): bool {
		return update_option( self::DEFAULT_OPTION_KEY, $id );
	}

	/**
	 * Get the default configuration.
	 *
	 * @return Configuration|null
	 */
	public function get_default(): ?Configuration {
		$id = $this->get_default_id();
		return $id ? $this->get( $id ) : null;
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
	 * Persist configurations to database.
	 *
	 * @param array<string, Configuration> $configurations Configurations keyed by ID.
	 * @return bool
	 */
	private function persist( array $configurations ): bool {
		$raw = array_map(
			static fn( Configuration $c ) => $c->to_array(),
			$configurations
		);

		// Use array_values to ensure sequential array for JSON storage.
		$result      = update_option( self::OPTION_KEY, array_values( $raw ) );
		$this->cache = $configurations;

		return $result;
	}

	/**
	 * Import configurations from array (for bulk import).
	 *
	 * @param array<array<string, mixed>> $data    Raw configuration data.
	 * @param bool                        $replace Whether to replace existing configs.
	 * @return int Number of configurations imported.
	 */
	public function import( array $data, bool $replace = false ): int {
		$current = $replace ? [] : $this->get_all();
		$count   = 0;

		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$config = Configuration::from_array( $item );
			$errors = $config->validate();

			if ( empty( $errors ) ) {
				$current[ $config->get_id() ] = $config;
				++$count;
			}
		}

		$this->persist( $current );

		return $count;
	}

	/**
	 * Export all configurations as array.
	 *
	 * @param bool $include_secrets Whether to include sensitive data.
	 * @return array<array<string, mixed>>
	 */
	public function export( bool $include_secrets = false ): array {
		$all = $this->get_all();

		if ( $include_secrets ) {
			return array_map(
				static fn( Configuration $c ) => $c->to_array(),
				array_values( $all )
			);
		}

		return array_map(
			static fn( Configuration $c ) => $c->jsonSerialize(),
			array_values( $all )
		);
	}

	/**
	 * Count configurations.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->get_all() );
	}
}
