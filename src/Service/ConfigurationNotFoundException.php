<?php
/**
 * Configuration Not Found Exception.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter\Service;

use Exception;

/**
 * Thrown when a configuration is not found.
 */
class ConfigurationNotFoundException extends Exception {

	/**
	 * Constructor.
	 *
	 * @param string $id Configuration ID.
	 */
	public function __construct( string $id ) {
		parent::__construct(
			sprintf( 'Configuration not found: %s', $id )
		);
	}
}
