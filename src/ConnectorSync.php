<?php
/**
 * Connector Sync.
 *
 * Handles syncing AI Router configurations to WordPress connector options
 * so has_ai_credentials() returns true and Azure/OpenAI providers work correctly.
 *
 * @package AIRouter
 */

declare(strict_types=1);

namespace AIRouter;

use AIRouter\DTO\Configuration;

/**
 * Syncs configuration settings to connector options.
 *
 * WordPress's AI plugin uses has_ai_credentials() to check if any AI provider
 * is configured. This class ensures AI Router configurations are visible to
 * that check without making individual providers appear configured (which
 * would cause wrong provider selection).
 *
 * @see https://developer.wordpress.org/reference/functions/has_ai_credentials/
 */
final class ConnectorSync implements ConnectorSyncInterface {

	/**
	 * Sync configuration to standard connector options.
	 *
	 * Sets the connectors_ai_ai_router_api_key option so has_ai_credentials() returns
	 * true via the AI Router connector. Does NOT set connectors_ai_openai_api_key
	 * to avoid making the OpenAI provider think it's configured.
	 *
	 * For Azure OpenAI, syncs api_key and endpoint only. Does NOT sync
	 * deployment_id or capabilities at save time.
	 *
	 * @param Configuration $config Configuration to sync.
	 * @return void
	 */
	public function sync_connector_option( Configuration $config ): void {
		$api_key = $config->get_setting( 'api_key', '' );
		if ( empty( $api_key ) ) {
			return;
		}

		// Set sentinel for AI Router connector - makes has_ai_credentials() return true.
		// Uses the auto-generated option name from WP_Connector_Registry.
		update_option( 'connectors_ai_ai_router_api_key', '1' );

		// For Azure OpenAI, sync only api_key and endpoint.
		// deployment_id and capabilities are intentionally NOT synced here
		// so the Azure provider's model metadata directory discovers all
		// available model types (text, image, embedding, etc.).
		if ( in_array( $config->get_provider_type(), [ 'azure-openai', 'azure_openai' ], true ) ) {
			update_option( 'connectors_ai_azure_openai_api_key', $api_key );

			$endpoint = $config->get_setting( 'endpoint', '' );
			if ( ! empty( $endpoint ) ) {
				update_option( 'connectors_ai_azure_openai_endpoint', $endpoint );
			}
		}
	}

	/**
	 * Sync request-time options for a specific capability.
	 *
	 * Called just before an AI request is executed. Sets deployment_id and
	 * api_version so the Azure provider model classes use the correct values
	 * for this specific request.
	 *
	 * @param Configuration $config Configuration matched for this request.
	 * @return void
	 */
	public function sync_request_options( Configuration $config ): void {
		if ( ! in_array( $config->get_provider_type(), [ 'azure-openai', 'azure_openai' ], true ) ) {
			return;
		}

		$deployment_id = $config->get_setting( 'deployment_id', '' );
		if ( ! empty( $deployment_id ) ) {
			update_option( 'connectors_ai_azure_openai_deployment_id', $deployment_id );
		}

		$api_version = $config->get_setting( 'api_version', '' );
		if ( ! empty( $api_version ) ) {
			update_option( 'connectors_ai_azure_openai_api_version', $api_version );
		}
	}

	/**
	 * Clear connector sentinel option.
	 *
	 * Called when all configurations are deleted to ensure has_ai_credentials()
	 * returns false.
	 *
	 * @return void
	 */
	public function clear_sentinel(): void {
		delete_option( 'connectors_ai_ai_router_api_key' );
	}
}
