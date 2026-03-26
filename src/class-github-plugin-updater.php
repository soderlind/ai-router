<?php
/**
 * GitHub Plugin Updater.
 *
 * @package AIRouter
 */

namespace AIRouter\Updater;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Generic WordPress Plugin GitHub Updater.
 *
 * A reusable class for handling WordPress plugin updates from
 * GitHub repositories using the plugin-update-checker library.
 *
 * @package Soderlind\WordPress
 * @link    https://github.com/soderlind/wordpress-plugin-github-updater
 * @version 1.0.0
 * @author  Per Soderlind
 * @license GPL-2.0+
 */
class GitHubUpdater {

	/**
	 * Initialize the GitHub update checker.
	 *
	 * @param string $github_url  Full GitHub repository URL.
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @param string $plugin_slug Plugin slug used by WordPress.
	 * @param string $name_regex  Optional regex to filter release assets.
	 * @param string $branch      Branch to track.
	 * @return void
	 */
	public static function init(
		string $github_url,
		string $plugin_file,
		string $plugin_slug,
		string $name_regex = '',
		string $branch = 'main'
	): void {
		add_action(
			'init',
			static function () use ( $github_url, $plugin_file, $plugin_slug, $name_regex, $branch ): void {
				try {
					if ( ! class_exists( PucFactory::class ) ) {
						throw new \RuntimeException( 'Missing dependency yahnis-elsts/plugin-update-checker. Run composer install --no-dev.' );
					}

					$checker = PucFactory::buildUpdateChecker( $github_url, $plugin_file, $plugin_slug );
					$checker->setBranch( $branch );

					if ( '' !== $name_regex ) {
						$checker->getVcsApi()->enableReleaseAssets( $name_regex );
					}
				} catch ( \Throwable $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( 'GitHubUpdater (' . $plugin_slug . '): ' . $e->getMessage() );
					}
				}
			}
		);
	}
}

/**
 * Backwards-compatible wrapper around GitHubUpdater::init().
 */
class GitHub_Plugin_Updater {

	/**
	 * GitHub repository URL.
	 *
	 * @var string
	 */
	private $github_url;

	/**
	 * Branch to check for updates.
	 *
	 * @var string
	 */
	private $branch;

	/**
	 * Regex pattern to match the plugin zip file name.
	 *
	 * @var string
	 */
	private $name_regex;

	/**
	 * The plugin slug.
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * The main plugin file path.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Whether to enable release assets.
	 *
	 * @var bool
	 */
	private $enable_release_assets;

	/**
	 * Constructor.
	 *
	 * @param array $config Configuration array with the following keys:
	 *                      - github_url: GitHub repository URL (required)
	 *                      - plugin_file: Main plugin file path (required)
	 *                      - plugin_slug: Plugin slug for updates (required)
	 *                      - branch: Branch to check for updates (default: 'main')
	 *                      - name_regex: Regex pattern for zip file name (optional)
	 *                      - enable_release_assets: Whether to enable release assets (default: true if name_regex provided)
	 * @throws \InvalidArgumentException If required parameters are missing.
	 */
	public function __construct( $config = array() ) {
		// Validate required parameters.
		$required = array( 'github_url', 'plugin_file', 'plugin_slug' );
		foreach ( $required as $key ) {
			if ( empty( $config[ $key ] ) ) {
				throw new \InvalidArgumentException( "Required parameter '{$key}' is missing or empty." );
			}
		}

		$this->github_url            = (string) $config['github_url'];
		$this->plugin_file           = (string) $config['plugin_file'];
		$this->plugin_slug           = (string) $config['plugin_slug'];
		$this->branch                = isset( $config['branch'] ) ? (string) $config['branch'] : 'main';
		$this->name_regex            = isset( $config['name_regex'] ) ? (string) $config['name_regex'] : '';
		$this->enable_release_assets = isset( $config['enable_release_assets'] )
			? (bool) $config['enable_release_assets']
			: ! empty( $this->name_regex );

		if ( ! $this->enable_release_assets ) {
			$this->name_regex = '';
		}

		self::init(
			$this->github_url,
			$this->plugin_file,
			$this->plugin_slug,
			$this->name_regex,
			$this->branch
		);
	}

	/**
	 * Initialize the update checker via the canonical static API.
	 *
	 * @param string $github_url  Full GitHub repository URL.
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @param string $plugin_slug Plugin slug used by WordPress.
	 * @param string $name_regex  Optional regex to filter release assets.
	 * @param string $branch      Branch to track.
	 * @return void
	 */
	public static function init( $github_url, $plugin_file, $plugin_slug, $name_regex = '', $branch = 'main' ) {
		GitHubUpdater::init(
			(string) $github_url,
			(string) $plugin_file,
			(string) $plugin_slug,
			(string) $name_regex,
			(string) $branch
		);
	}

	/**
	 * Create updater instance with minimal configuration.
	 *
	 * @param string $github_url  GitHub repository URL.
	 * @param string $plugin_file Main plugin file path.
	 * @param string $plugin_slug Plugin slug.
	 * @param string $branch      Branch name (default: 'main').
	 * @return GitHub_Plugin_Updater
	 */
	public static function create( $github_url, $plugin_file, $plugin_slug, $branch = 'main' ) {
		return new self(
			array(
				'github_url'  => $github_url,
				'plugin_file' => $plugin_file,
				'plugin_slug' => $plugin_slug,
				'branch'      => $branch,
			)
		);
	}

	/**
	 * Create updater instance for plugins with release assets.
	 *
	 * @param string $github_url  GitHub repository URL.
	 * @param string $plugin_file Main plugin file path.
	 * @param string $plugin_slug Plugin slug.
	 * @param string $name_regex  Regex pattern for release assets.
	 * @param string $branch      Branch name (default: 'main').
	 * @return GitHub_Plugin_Updater
	 */
	public static function create_with_assets( $github_url, $plugin_file, $plugin_slug, $name_regex, $branch = 'main' ) {
		return new self(
			array(
				'github_url'  => $github_url,
				'plugin_file' => $plugin_file,
				'plugin_slug' => $plugin_slug,
				'branch'      => $branch,
				'name_regex'  => $name_regex,
			)
		);
	}
}
