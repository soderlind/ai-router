<?php
/**
 * Configuration Validation Exception.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter\Service;

use Exception;

/**
 * Thrown when configuration validation fails.
 */
class ConfigurationValidationException extends Exception {

	/**
	 * Validation errors.
	 *
	 * @var array<string>
	 */
	private array $errors;

	/**
	 * Constructor.
	 *
	 * @param array<string> $errors Validation error messages.
	 */
	public function __construct( array $errors ) {
		$this->errors = $errors;
		parent::__construct( implode( ' ', $errors ) );
	}

	/**
	 * Get validation errors.
	 *
	 * @return array<string>
	 */
	public function get_errors(): array {
		return $this->errors;
	}
}
