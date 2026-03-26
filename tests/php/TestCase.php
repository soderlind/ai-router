<?php
/**
 * Base test case with Brain Monkey setup.
 *
 * @package AIRouter\Tests
 */

declare(strict_types=1);

namespace AIRouter\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Abstract base test case.
 */
abstract class TestCase extends PHPUnitTestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock common WordPress functions.
		$this->mockWordPressFunctions();
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Mock common WordPress functions used throughout the plugin.
	 */
	protected function mockWordPressFunctions(): void {
		// Translation functions - return first argument.
		Functions\stubs(
			[
				'__'                  => static fn( $text ) => $text,
				'esc_html__'          => static fn( $text ) => $text,
				'esc_attr__'          => static fn( $text ) => $text,
				'esc_html'            => static fn( $text ) => $text,
				'esc_attr'            => static fn( $text ) => $text,
				'sanitize_key'        => static fn( $key ) => strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) ),
				'sanitize_text_field' => static fn( $str ) => trim( strip_tags( $str ) ),
				'wp_generate_uuid4'   => static fn() => sprintf(
					'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
					mt_rand( 0, 0xffff ),
					mt_rand( 0, 0xffff ),
					mt_rand( 0, 0xffff ),
					mt_rand( 0, 0x0fff ) | 0x4000,
					mt_rand( 0, 0x3fff ) | 0x8000,
					mt_rand( 0, 0xffff ),
					mt_rand( 0, 0xffff ),
					mt_rand( 0, 0xffff )
				),
			]
		);
	}
}
