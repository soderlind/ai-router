<?php
/**
 * Unit tests for ConnectorSync.
 *
 * @package AIRouter\Tests\Unit
 */

declare(strict_types=1);

namespace AIRouter\Tests\Unit;

use AIRouter\ConnectorSync;
use AIRouter\DTO\Configuration;
use AIRouter\Tests\TestCase;
use Brain\Monkey\Functions;

/**
 * Test ConnectorSync.
 *
 * @covers \AIRouter\ConnectorSync
 */
class ConnectorSyncTest extends TestCase {

	private ConnectorSync $sync;

	protected function setUp(): void {
		parent::setUp();
		$this->sync = new ConnectorSync();
	}

	/**
	 * Test sync_connector_option sets sentinel option.
	 */
	public function test_sync_connector_option_sets_sentinel(): void {
		$config = Configuration::from_array( [
			'id'            => 'test-id',
			'name'          => 'Test Config',
			'provider_type' => 'openai',
			'settings'      => [ 'api_key' => 'sk-test-key' ],
		] );

		Functions\expect( 'update_option' )
			->once()
			->with( 'connectors_ai_ai_router_api_key', '1' );

		$this->sync->sync_connector_option( $config );
	}

	/**
	 * Test sync_connector_option skips when no API key.
	 */
	public function test_sync_connector_option_skips_without_api_key(): void {
		$config = Configuration::from_array( [
			'id'            => 'test-id',
			'name'          => 'Test Config',
			'provider_type' => 'openai',
			'settings'      => [],
		] );

		Functions\expect( 'update_option' )->never();

		$this->sync->sync_connector_option( $config );
	}

	/**
	 * Test sync_connector_option syncs Azure endpoint.
	 */
	public function test_sync_connector_option_syncs_azure_endpoint(): void {
		$config = Configuration::from_array( [
			'id'            => 'test-id',
			'name'          => 'Azure Config',
			'provider_type' => 'azure-openai',
			'settings'      => [
				'api_key'  => 'azure-key',
				'endpoint' => 'https://test.openai.azure.com',
			],
		] );

		Functions\expect( 'update_option' )
			->once()
			->with( 'connectors_ai_ai_router_api_key', '1' );

		Functions\expect( 'update_option' )
			->once()
			->with( 'connectors_ai_azure_openai_api_key', 'azure-key' );

		Functions\expect( 'update_option' )
			->once()
			->with( 'connectors_ai_azure_openai_endpoint', 'https://test.openai.azure.com' );

		$this->sync->sync_connector_option( $config );
	}

	/**
	 * Test sync_request_options sets deployment_id for Azure.
	 */
	public function test_sync_request_options_sets_deployment_id(): void {
		$config = Configuration::from_array( [
			'id'            => 'test-id',
			'name'          => 'Azure Config',
			'provider_type' => 'azure-openai',
			'settings'      => [
				'deployment_id' => 'gpt-4-deployment',
				'api_version'   => '2024-01-01',
			],
		] );

		Functions\expect( 'update_option' )
			->once()
			->with( 'connectors_ai_azure_openai_deployment_id', 'gpt-4-deployment' );

		Functions\expect( 'update_option' )
			->once()
			->with( 'connectors_ai_azure_openai_api_version', '2024-01-01' );

		$this->sync->sync_request_options( $config );
	}

	/**
	 * Test sync_request_options skips non-Azure providers.
	 */
	public function test_sync_request_options_skips_non_azure(): void {
		$config = Configuration::from_array( [
			'id'            => 'test-id',
			'name'          => 'OpenAI Config',
			'provider_type' => 'openai',
			'settings'      => [
				'api_key' => 'sk-test',
			],
		] );

		Functions\expect( 'update_option' )->never();

		$this->sync->sync_request_options( $config );
	}

	/**
	 * Test clear_sentinel deletes sentinel option.
	 */
	public function test_clear_sentinel_deletes_option(): void {
		Functions\expect( 'delete_option' )
			->once()
			->with( 'connectors_ai_ai_router_api_key' );

		$this->sync->clear_sentinel();
	}
}
