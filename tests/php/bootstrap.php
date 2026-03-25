<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package AIRouter\Tests
 */

declare(strict_types=1);

// Load Composer autoloader.
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// Load Brain Monkey.
use Brain\Monkey;

// Set up Brain Monkey before tests.
Monkey\setUp();

// Define WordPress constants for testing.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'AI_ROUTER_VERSION' ) ) {
	define( 'AI_ROUTER_VERSION', '1.0.0' );
}

if ( ! defined( 'AI_ROUTER_FILE' ) ) {
	define( 'AI_ROUTER_FILE', dirname( __DIR__, 2 ) . '/ai-router.php' );
}

if ( ! defined( 'AI_ROUTER_DIR' ) ) {
	define( 'AI_ROUTER_DIR', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'AI_ROUTER_URL' ) ) {
	define( 'AI_ROUTER_URL', 'https://example.com/wp-content/plugins/ai-router/' );
}

// Load plugin autoloader.
require_once AI_ROUTER_DIR . 'src/autoload.php';
