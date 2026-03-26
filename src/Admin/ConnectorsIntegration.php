<?php
/**
 * Connectors Page Integration.
 *
 * Adds AI Router to the WP 7 Settings → Connectors page using the
 * experimental registerConnector API.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter\Admin;

/**
 * Integrates AI Router into the Connectors admin page.
 */
class ConnectorsIntegration {

	/**
	 * Script module handle.
	 *
	 * @var string
	 */
	private const MODULE_HANDLE = 'ai-router-connectors';

	/**
	 * Initialize the integration.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register the script module on init (like Azure OpenAI provider).
		add_action( 'init', [ $this, 'register_script_module' ] );

		// Enqueue on both possible connectors page hooks (Beta 3 + RC1 compat).
		add_action( 'options-connectors-wp-admin_init', [ $this, 'enqueue_script_module' ] );
		add_action( 'connectors-wp-admin_init', [ $this, 'enqueue_script_module' ] );
	}

	/**
	 * Register the script module.
	 *
	 * @return void
	 */
	public function register_script_module(): void {
		if ( ! function_exists( 'wp_register_script_module' ) ) {
			return;
		}

		$asset_file = AI_ROUTER_PATH . 'build/connectors.asset.php';
		$version    = AI_ROUTER_VERSION;

		if ( file_exists( $asset_file ) ) {
			$asset   = require $asset_file;
			$version = $asset[ 'version' ] ?? $version;
		}

		// Register as a script module with @wordpress/connectors dependency.
		// Use dynamic import to match Azure OpenAI pattern.
		wp_register_script_module(
			self::MODULE_HANDLE,
			plugins_url( 'build/connectors.js', AI_ROUTER_FILE ),
			[
				[
					'id'     => '@wordpress/connectors',
					'import' => 'dynamic',
				],
			],
			$version
		);
	}

	/**
	 * Enqueue the script module on the Connectors page.
	 *
	 * @return void
	 */
	public function enqueue_script_module(): void {
		wp_enqueue_script_module( self::MODULE_HANDLE );
	}
}
