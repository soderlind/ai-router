<?php
/**
 * Connector Sync Interface.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter;

use AIRouter\DTO\Configuration;

/**
 * Interface for syncing configurations to connector options.
 */
interface ConnectorSyncInterface {
	/**
	 * Sync configuration to standard connector options.
	 *
	 * @param Configuration $config Configuration to sync.
	 * @return void
	 */
	public function sync_connector_option( Configuration $config ): void;

	/**
	 * Sync request-time options for a specific capability.
	 *
	 * @param Configuration $config Configuration matched for this request.
	 * @return void
	 */
	public function sync_request_options( Configuration $config ): void;

	/**
	 * Clear connector sentinel option.
	 *
	 * @return void
	 */
	public function clear_sentinel(): void;
}
