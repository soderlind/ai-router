<?php
/**
 * PSR-4 autoloader for AIRouter namespace.
 *
 * @package AIRouter
 */

declare(strict_types=1);

spl_autoload_register(
	static function ( string $class ): void {
		$prefix   = 'AIRouter\\';
		$base_dir = __DIR__ . '/';
		$len      = strlen( $prefix );

		if ( strncmp( $class, $prefix, $len ) !== 0 ) {
			return;
		}

		$relative = substr( $class, $len );
		$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);
