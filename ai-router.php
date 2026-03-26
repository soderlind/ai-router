<?php
/**
 * Plugin Name: AI Router
 * Plugin URI: https://github.com/soderlind/ai-router
 * Description: Route AI requests to different provider configurations based on capability. Allows multiple configurations of the same AI provider with different LLMs.
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Version: 0.2.0
 * Author: Per Søderlind
 * Author URI: https://soderlind.no
 * License: GPL-2.0-or-later
 * Text Domain: ai-router
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'AI_ROUTER_VERSION', '0.2.0' );
define( 'AI_ROUTER_FILE', __FILE__ );
define( 'AI_ROUTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'AI_ROUTER_DIR', AI_ROUTER_PATH ); // Alias for backward compat.
define( 'AI_ROUTER_URL', plugin_dir_url( __FILE__ ) );

// Autoloader.
require_once AI_ROUTER_DIR . 'src/autoload.php';

// GitHub plugin updater.
require_once AI_ROUTER_DIR . 'src/class-github-plugin-updater.php';

\AIRouter\Updater\GitHubUpdater::init(
	github_url: 'https://github.com/soderlind/ai-router',
	plugin_file: __FILE__,
	plugin_slug: 'ai-router',
	name_regex: '/ai-router\.zip/',
	branch: 'main',
);

/**
 * Bootstrap the plugin.
 *
 * @return void
 */
function bootstrap(): void {
	// Check WP 7 AI support.
	if ( ! function_exists( 'wp_supports_ai' ) || ! wp_supports_ai() ) {
		add_action(
			'admin_notices',
			static function (): void {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html__( 'AI Router requires WordPress 7.0+ with AI support enabled.', 'ai-router' )
				);
			}
		);
		return;
	}

	// Initialize components.
	$repository     = new Repository\ConfigurationRepository();
	$capability_map = new CapabilityMap( $repository );
	$router         = new Router( $repository, $capability_map );

	// Register admin integration with WP 7 Connectors page.
	if ( is_admin() ) {
		$connectors = new Admin\ConnectorsIntegration();
		$connectors->init();
	}

	// Register REST API.
	add_action(
		'rest_api_init',
		static function () use ($repository, $capability_map): void {
			$controller = new Rest\ConfigurationsController( $repository, $capability_map );
			$controller->register_routes();
		}
	);

	// Initialize router hooks.
	$router->init();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\bootstrap' );

/**
 * Activation hook.
 *
 * @return void
 */
function activate(): void {
	// Create default options if they don't exist.
	if ( false === get_option( 'ai_router_configurations' ) ) {
		add_option( 'ai_router_configurations', [], '', false );
	}
	if ( false === get_option( 'ai_router_capability_map' ) ) {
		add_option( 'ai_router_capability_map', [], '', false );
	}
	if ( false === get_option( 'ai_router_default_config' ) ) {
		add_option( 'ai_router_default_config', '', '', false );
	}
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );

/**
 * Uninstall hook registered via uninstall.php.
 *
 * @return void
 */
function uninstall(): void {
	delete_option( 'ai_router_configurations' );
	delete_option( 'ai_router_capability_map' );
	delete_option( 'ai_router_default_config' );
}
