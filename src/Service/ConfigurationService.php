<?php
/**
 * Configuration Service.
 *
 * Domain logic for managing AI configurations.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter\Service;

use AIRouter\CapabilityMapInterface;
use AIRouter\DTO\Configuration;
use AIRouter\Repository\ConfigurationRepositoryInterface;
use AIRouter\Vocabulary;

/**
 * Service for managing AI configurations.
 *
 * This service encapsulates domain logic for configuration CRUD operations,
 * separated from HTTP transport concerns. It can be used by REST API, CLI,
 * cron jobs, or any other interface.
 */
class ConfigurationService {

	/**
	 * Constructor.
	 *
	 * @param ConfigurationRepositoryInterface $repository     Configuration repository.
	 * @param CapabilityMapInterface           $capability_map Capability map.
	 */
	public function __construct(
		private readonly ConfigurationRepositoryInterface $repository,
		private readonly CapabilityMapInterface $capability_map,
	) {}

	/**
	 * Get all configurations.
	 *
	 * @return array<Configuration>
	 */
	public function list(): array {
		return array_values( $this->repository->get_all() );
	}

	/**
	 * Get a configuration by ID.
	 *
	 * @param string $id Configuration ID.
	 * @return Configuration|null
	 */
	public function get( string $id ): ?Configuration {
		return $this->repository->get( $id );
	}

	/**
	 * Create a new configuration.
	 *
	 * @param array{
	 *     name: string,
	 *     provider_type: string,
	 *     settings?: array<string, mixed>,
	 *     capabilities?: array<string>,
	 *     is_default?: bool
	 * } $data Configuration data.
	 * @return Configuration The created configuration.
	 * @throws ConfigurationValidationException If validation fails.
	 */
	public function create( array $data ): Configuration {
		$config_data = [
			'id'            => $this->generate_id(),
			'name'          => $data['name'],
			'provider_type' => Vocabulary::normalize_provider_type( $data['provider_type'] ),
			'settings'      => $data['settings'] ?? [],
			'capabilities'  => $this->filter_valid_capabilities( $data['capabilities'] ?? [] ),
			'is_default'    => $data['is_default'] ?? false,
		];

		$config = Configuration::from_array( $config_data );
		$this->validate( $config );

		$this->repository->save( $config );

		if ( $config->is_default() ) {
			$this->repository->set_default_id( $config->get_id() );
		}

		return $config;
	}

	/**
	 * Update an existing configuration.
	 *
	 * @param string                $id      Configuration ID.
	 * @param array<string, mixed>  $updates Fields to update.
	 * @return Configuration The updated configuration.
	 * @throws ConfigurationNotFoundException If configuration not found.
	 * @throws ConfigurationValidationException If validation fails.
	 */
	public function update( string $id, array $updates ): Configuration {
		$existing = $this->repository->get( $id );

		if ( null === $existing ) {
			throw new ConfigurationNotFoundException( $id );
		}

		// Normalize provider type if present.
		if ( isset( $updates['provider_type'] ) ) {
			$updates['provider_type'] = Vocabulary::normalize_provider_type( $updates['provider_type'] );
		}

		// Merge settings if present.
		if ( isset( $updates['settings'] ) ) {
			$updates['settings'] = array_merge(
				$existing->get_settings(),
				$updates['settings']
			);
		}

		// Filter capabilities if present.
		if ( isset( $updates['capabilities'] ) ) {
			$updates['capabilities'] = $this->filter_valid_capabilities( $updates['capabilities'] );
		}

		$config = $existing->with( $updates );
		$this->validate( $config );

		$this->repository->save( $config );

		// Handle default setting changes.
		if ( $config->is_default() ) {
			$this->repository->set_default_id( $config->get_id() );
		} elseif ( $this->repository->get_default_id() === $config->get_id() ) {
			$this->repository->set_default_id( '' );
		}

		return $config;
	}

	/**
	 * Delete a configuration.
	 *
	 * @param string $id Configuration ID.
	 * @return bool True if deleted.
	 * @throws ConfigurationNotFoundException If configuration not found.
	 */
	public function delete( string $id ): bool {
		$config = $this->repository->get( $id );

		if ( null === $config ) {
			throw new ConfigurationNotFoundException( $id );
		}

		// Remove capability mappings for this config.
		$this->capability_map->remove_config( $id );

		return $this->repository->delete( $id );
	}

	/**
	 * Get the default configuration ID.
	 *
	 * @return string
	 */
	public function get_default_id(): string {
		return $this->repository->get_default_id();
	}

	/**
	 * Set the default configuration.
	 *
	 * @param string $id Configuration ID (empty to clear).
	 * @return void
	 * @throws ConfigurationNotFoundException If configuration not found and ID not empty.
	 */
	public function set_default( string $id ): void {
		if ( ! empty( $id ) && ! $this->repository->exists( $id ) ) {
			throw new ConfigurationNotFoundException( $id );
		}

		$this->repository->set_default_id( $id );
	}

	/**
	 * Get the capability map.
	 *
	 * @return array<string, string>
	 */
	public function get_capability_map(): array {
		return $this->capability_map->get_map();
	}

	/**
	 * Update capability mappings.
	 *
	 * @param array<string, string> $mappings Capability => Configuration ID.
	 * @return array<string, string> The updated map.
	 */
	public function update_capability_map( array $mappings ): array {
		// Filter to valid capabilities only.
		$valid_mappings = [];
		foreach ( $mappings as $capability => $config_id ) {
			if ( Vocabulary::is_valid_capability( $capability ) ) {
				$valid_mappings[ $capability ] = $config_id;
			}
		}

		$this->capability_map->set_bulk( $valid_mappings );

		return $this->capability_map->get_map();
	}

	/**
	 * Get capability mappings for a specific configuration.
	 *
	 * @param string $id Configuration ID.
	 * @return array<string>
	 */
	public function get_capabilities_for_config( string $id ): array {
		return $this->capability_map->get_capabilities_for_config( $id );
	}

	/**
	 * Check if a configuration exists.
	 *
	 * @param string $id Configuration ID.
	 * @return bool
	 */
	public function exists( string $id ): bool {
		return $this->repository->exists( $id );
	}

	/**
	 * Validate a configuration.
	 *
	 * @param Configuration $config Configuration to validate.
	 * @return void
	 * @throws ConfigurationValidationException If validation fails.
	 */
	private function validate( Configuration $config ): void {
		$errors = $config->validate();

		if ( ! empty( $errors ) ) {
			throw new ConfigurationValidationException( $errors );
		}
	}

	/**
	 * Generate a unique configuration ID.
	 *
	 * @return string
	 */
	private function generate_id(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		// Fallback for non-WP environments (testing).
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff )
		);
	}

	/**
	 * Filter capabilities to only valid ones.
	 *
	 * @param array<string> $capabilities Raw capabilities.
	 * @return array<string>
	 */
	private function filter_valid_capabilities( array $capabilities ): array {
		return array_values(
			array_filter(
				$capabilities,
				static fn( string $c ) => Vocabulary::is_valid_capability( $c )
			)
		);
	}
}
