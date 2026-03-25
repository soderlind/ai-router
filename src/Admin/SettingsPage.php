<?php
/**
 * Admin Settings Page.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter\Admin;

use AIRouter\CapabilityMap;
use AIRouter\DTO\Configuration;
use AIRouter\Repository\ConfigurationRepository;
use AIRouter\Router;

/**
 * Settings page for AI Router configuration.
 */
final class SettingsPage {

	/**
	 * Page slug.
	 */
	private const PAGE_SLUG = 'ai-router';

	/**
	 * Script handle.
	 */
	private const SCRIPT_HANDLE = 'ai-router-admin';

	/**
	 * Constructor.
	 *
	 * @param ConfigurationRepository $repository     Configuration repository.
	 * @param CapabilityMap           $capability_map Capability map.
	 */
	public function __construct(
		private readonly ConfigurationRepository $repository,
		private readonly CapabilityMap $capability_map,
	) {}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Add settings page to menu.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_options_page(
			__( 'AI Router', 'ai-router' ),
			__( 'AI Router', 'ai-router' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue_scripts( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$asset_file = AI_ROUTER_DIR . 'build/admin.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
		} else {
			$asset = [
				'dependencies' => [ 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ],
				'version'      => AI_ROUTER_VERSION,
			];
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			AI_ROUTER_URL . 'build/admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			self::SCRIPT_HANDLE,
			AI_ROUTER_URL . 'build/admin.css',
			[ 'wp-components' ],
			$asset['version']
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'aiRouterAdmin',
			$this->get_script_data()
		);

		wp_set_script_translations( self::SCRIPT_HANDLE, 'ai-router' );
	}

	/**
	 * Get data for JavaScript.
	 *
	 * @return array<string, mixed>
	 */
	private function get_script_data(): array {
		$configurations = $this->repository->get_all();

		return [
			'restUrl'        => rest_url( 'ai-router/v1/' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'configurations' => array_values(
				array_map(
					static fn( Configuration $c ) => $c->jsonSerialize(),
					$configurations
				)
			),
			'capabilityMap'  => $this->capability_map->get_map(),
			'defaultConfig'  => $this->repository->get_default_id(),
			'providerTypes'  => Configuration::PROVIDER_TYPES,
			'capabilities'   => $this->get_capabilities_with_labels(),
		];
	}

	/**
	 * Get capabilities with human-readable labels.
	 *
	 * @return array<array{slug: string, label: string}>
	 */
	private function get_capabilities_with_labels(): array {
		$capabilities = [];

		foreach ( Configuration::CAPABILITIES as $capability ) {
			$capabilities[] = [
				'slug'  => $capability,
				'label' => Router::get_capability_label( $capability ),
			];
		}

		return $capabilities;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Configure multiple AI provider configurations and route capabilities to specific providers.', 'ai-router' ); ?>
			</p>
			<div id="ai-router-admin"></div>
			<noscript>
				<p class="notice notice-error">
					<?php esc_html_e( 'JavaScript is required to manage AI Router settings.', 'ai-router' ); ?>
				</p>
			</noscript>
		</div>
		<?php
	}
}
