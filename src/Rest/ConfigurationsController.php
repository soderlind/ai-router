<?php
/**
 * REST API Controller for Configurations.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter\Rest;

use AIRouter\DTO\Configuration;
use AIRouter\ProviderDiscovery;
use AIRouter\Service\ConfigurationNotFoundException;
use AIRouter\Service\ConfigurationService;
use AIRouter\Service\ConfigurationValidationException;
use AIRouter\Vocabulary;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API endpoints for managing configurations.
 */
final class ConfigurationsController extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ai-router/v1';

	/**
	 * Resource name.
	 *
	 * @var string
	 */
	protected $rest_base = 'configurations';

	/**
	 * Constructor.
	 *
	 * @param ConfigurationService $service Configuration service.
	 */
	public function __construct(
		private readonly ConfigurationService $service,
	) {}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET/POST /configurations.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args'                => $this->get_create_args(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// GET/PUT/DELETE /configurations/{id}.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9-]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
					'args'                => $this->get_update_args(),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'delete_item_permissions_check' ],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// POST /capability-map.
		register_rest_route(
			$this->namespace,
			'/capability-map',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_capability_map' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_capability_map' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
				],
			]
		);

		// POST /default.
		register_rest_route(
			$this->namespace,
			'/default',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_default' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_default' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
				],
			]
		);

		// GET /providers - installed AI providers from WP AI Client SDK.
		register_rest_route(
			$this->namespace,
			'/providers',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_providers' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
			]
		);
	}

	/**
	 * Check if user can read configurations.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can read a configuration.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Check if user can create configurations.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can update configurations.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can delete configurations.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get all configurations.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		$configurations = $this->service->list();

		$data = array_map(
			fn( Configuration $c ) => $this->prepare_item_for_response( $c, $request )->get_data(),
			$configurations
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Get a single configuration.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$config = $this->service->get( $request[ 'id' ] );

		if ( null === $config ) {
			return new WP_Error(
				'not_found',
				__( 'Configuration not found.', 'ai-router' ),
				[ 'status' => 404 ]
			);
		}

		return $this->prepare_item_for_response( $config, $request );
	}

	/**
	 * Create a configuration.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		try {
			$config = $this->service->create( [
				'name'          => sanitize_text_field( $request[ 'name' ] ),
				'provider_type' => sanitize_key( $request[ 'provider_type' ] ),
				'settings'      => $this->sanitize_settings( $request[ 'settings' ] ?? [] ),
				'capabilities'  => array_map( 'sanitize_key', $request[ 'capabilities' ] ?? [] ),
				'is_default'    => (bool) ( $request[ 'is_default' ] ?? false ),
			] );

			return $this->prepare_item_for_response( $config, $request );
		} catch ( ConfigurationValidationException $e ) {
			return new WP_Error(
				'validation_error',
				$e->getMessage(),
				[ 'status' => 400 ]
			);
		}
	}

	/**
	 * Update a configuration.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		try {
			$updates = [];

			if ( isset( $request[ 'name' ] ) ) {
				$updates[ 'name' ] = sanitize_text_field( $request[ 'name' ] );
			}

			if ( isset( $request[ 'provider_type' ] ) ) {
				$updates[ 'provider_type' ] = sanitize_key( $request[ 'provider_type' ] );
			}

			if ( isset( $request[ 'settings' ] ) ) {
				$updates[ 'settings' ] = $this->sanitize_settings( $request[ 'settings' ] );
			}

			if ( isset( $request[ 'capabilities' ] ) ) {
				$updates[ 'capabilities' ] = array_map( 'sanitize_key', $request[ 'capabilities' ] );
			}

			if ( isset( $request[ 'is_default' ] ) ) {
				$updates[ 'is_default' ] = (bool) $request[ 'is_default' ];
			}

			$config = $this->service->update( $request[ 'id' ], $updates );

			return $this->prepare_item_for_response( $config, $request );
		} catch ( ConfigurationNotFoundException $e ) {
			return new WP_Error(
				'not_found',
				__( 'Configuration not found.', 'ai-router' ),
				[ 'status' => 404 ]
			);
		} catch ( ConfigurationValidationException $e ) {
			return new WP_Error(
				'validation_error',
				$e->getMessage(),
				[ 'status' => 400 ]
			);
		}
	}

	/**
	 * Delete a configuration.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		try {
			$this->service->delete( $request[ 'id' ] );

			return rest_ensure_response( [ 'deleted' => true ] );
		} catch ( ConfigurationNotFoundException $e ) {
			return new WP_Error(
				'not_found',
				__( 'Configuration not found.', 'ai-router' ),
				[ 'status' => 404 ]
			);
		}
	}

	/**
	 * Get capability map.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_capability_map( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( $this->service->get_capability_map() );
	}

	/**
	 * Update capability map.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_capability_map( WP_REST_Request $request ) {
		$mappings = $request->get_json_params();

		if ( ! is_array( $mappings ) ) {
			return new WP_Error(
				'invalid_data',
				__( 'Invalid capability map data.', 'ai-router' ),
				[ 'status' => 400 ]
			);
		}

		// Sanitize mappings.
		$sanitized = [];
		foreach ( $mappings as $capability => $config_id ) {
			$sanitized[ sanitize_key( $capability ) ] = sanitize_text_field( $config_id );
		}

		$result = $this->service->update_capability_map( $sanitized );

		return rest_ensure_response( $result );
	}

	/**
	 * Get default configuration.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_default( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( [ 'default_id' => $this->service->get_default_id() ] );
	}

	/**
	 * Set default configuration.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function set_default( WP_REST_Request $request ) {
		try {
			$config_id = sanitize_text_field( $request[ 'config_id' ] ?? '' );

			$this->service->set_default( $config_id );

			return rest_ensure_response( [ 'default_id' => $config_id ] );
		} catch ( ConfigurationNotFoundException $e ) {
			return new WP_Error(
				'not_found',
				__( 'Configuration not found.', 'ai-router' ),
				[ 'status' => 404 ]
			);
		}
	}

	/**
	 * Prepare item for response.
	 *
	 * @param Configuration   $config  Configuration.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $config, $request ): WP_REST_Response {
		$data = $config->jsonSerialize();

		// Add mapped capabilities info.
		$data[ 'mapped_capabilities' ] = $this->service->get_capabilities_for_config( $config->get_id() );

		return rest_ensure_response( $data );
	}

	/**
	 * Get create endpoint arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_create_args(): array {
		return [
			'name'          => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'provider_type' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			],
			'settings'      => [
				'type'    => 'object',
				'default' => [],
			],
			'capabilities'  => [
				'type'    => 'array',
				'items'   => [ 'type' => 'string' ],
				'default' => [],
			],
			'is_default'    => [
				'type'    => 'boolean',
				'default' => false,
			],
		];
	}

	/**
	 * Get update endpoint arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_update_args(): array {
		$args = $this->get_create_args();

		// All fields optional for update.
		foreach ( $args as $key => $arg ) {
			$args[ $key ][ 'required' ] = false;
		}

		return $args;
	}

	/**
	 * Sanitize settings array.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array<string, mixed>
	 */
	private function sanitize_settings( array $settings ): array {
		$sanitized = [];

		foreach ( $settings as $key => $value ) {
			$key = sanitize_key( $key );

			if ( is_string( $value ) ) {
				// Don't sanitize API keys too aggressively.
				if ( str_contains( $key, 'key' ) || str_contains( $key, 'token' ) ) {
					$sanitized[ $key ] = trim( $value );
				} else {
					$sanitized[ $key ] = sanitize_text_field( $value );
				}
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_settings( $value );
			} else {
				$sanitized[ $key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Get item schema.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ai-router-configuration',
			'type'       => 'object',
			'properties' => [
				'id'            => [
					'description' => __( 'Unique identifier.', 'ai-router' ),
					'type'        => 'string',
					'readonly'    => true,
				],
				'name'          => [
					'description' => __( 'Configuration name.', 'ai-router' ),
					'type'        => 'string',
				],
				'provider_type' => [
					'description' => __( 'Provider type.', 'ai-router' ),
					'type'        => 'string',
				],
				'settings'      => [
					'description' => __( 'Provider settings.', 'ai-router' ),
					'type'        => 'object',
				],
				'capabilities'  => [
					'description' => __( 'Supported capabilities.', 'ai-router' ),
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
				],
				'is_default'    => [
					'description' => __( 'Whether this is the default configuration.', 'ai-router' ),
					'type'        => 'boolean',
				],
			],
		];
	}

	/**
	 * Get installed AI providers from the WP AI Client SDK.
	 *
	 * Returns providers with their capabilities, fields, and configuration status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_providers( WP_REST_Request $request ): WP_REST_Response {
		$discovery = new ProviderDiscovery();

		return rest_ensure_response( [
			'providers'    => $discovery->get_providers(),
			'capabilities' => $discovery->get_all_capabilities(),
		] );
	}
}
