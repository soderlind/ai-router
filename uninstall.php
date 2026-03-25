<?php
/**
 * Uninstall script for AI Router.
 *
 * @package AIRouter
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ai_router_configurations' );
delete_option( 'ai_router_capability_map' );
delete_option( 'ai_router_default_config' );
