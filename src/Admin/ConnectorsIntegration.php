<?php
/**
 * Connectors Page Integration.
 *
 * Adds AI Router link to the WP 7 Settings → Connectors page.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter\Admin;

/**
 * Integrates AI Router into the Connectors admin page.
 *
 * Since WP 7 Connectors uses ES modules and the @wordpress/boot system,
 * full integration requires an ESM build. For simplicity, this class
 * adds a menu item that links to the standalone AI Router settings page.
 */
class ConnectorsIntegration {

	/**
	 * Initialize the integration.
	 *
	 * @return void
	 */
	public function init(): void {
		// Add menu item to Connectors page that links to AI Router settings.
		add_action( 'options-connectors-wp-admin_init', [ $this, 'register_connectors_menu_item' ] );

		// Enqueue script to handle the external navigation.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_navigation_script' ] );
	}

	/**
	 * Register the AI Router menu item on the Connectors page.
	 *
	 * @return void
	 */
	public function register_connectors_menu_item(): void {
		if ( ! function_exists( 'wp_register_connectors_wp_admin_menu_item' ) ) {
			return;
		}

		// Register menu item pointing to the external route.
		wp_register_connectors_wp_admin_menu_item(
			'ai-router',
			__( 'AI Router', 'ai-router' ),
			'/router'
		);

		// Register a route that will redirect to the settings page.
		if ( function_exists( 'wp_register_connectors_wp_admin_route' ) ) {
			wp_register_connectors_wp_admin_route( '/router' );
		}
	}

	/**
	 * Enqueue script to handle navigation from Connectors to AI Router settings.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 * @return void
	 */
	public function enqueue_navigation_script( string $hook_suffix ): void {
		// Only on Connectors page.
		$screen = get_current_screen();
		if ( ! $screen || 'connectors' !== $screen->id ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=ai-router' );

		// Add inline script that intercepts navigation to /router and redirects.
		wp_add_inline_script(
			'wp-url', // Dependency that's loaded on Connectors page.
			sprintf(
				'(function() {
					var settingsUrl = %s;
					// Listen for route changes and redirect /router to external page.
					var observer = new MutationObserver(function() {
						if (window.location.hash === "#/router" || window.location.pathname.endsWith("/router")) {
							window.location.href = settingsUrl;
						}
					});
					observer.observe(document.body, { childList: true, subtree: true });
					// Also check on load.
					if (window.location.hash === "#/router") {
						window.location.href = settingsUrl;
					}
				})();',
				wp_json_encode( $settings_url )
			)
		);
	}
}
