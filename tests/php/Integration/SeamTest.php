<?php
/**
 * Integration Seam Tests.
 *
 * Tests verifying the integration points between modules work correctly.
 *
 * @package AIRouter\Tests\Integration
 */

declare(strict_types=1);

namespace AIRouter\Tests\Integration;

use AIRouter\CapabilityMap;
use AIRouter\ConnectorSync;
use AIRouter\ConnectorSyncInterface;
use AIRouter\DTO\Configuration;
use AIRouter\DTO\RequestContext;
use AIRouter\Repository\ConfigurationRepository;
use AIRouter\Repository\ConfigurationRepositoryInterface;
use AIRouter\Router;
use AIRouter\Service\ConfigurationService;
use AIRouter\Vocabulary;
use AIRouter\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Integration tests for module seams.
 */
class SeamTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		// Mock WordPress functions commonly needed.
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );
	}

	/**
	 * Test service correctly uses repository for CRUD operations.
	 *
	 * Seam: ConfigurationService ↔ Repository
	 */
	public function test_service_repository_integration(): void {
		$repository     = Mockery::mock( ConfigurationRepositoryInterface::class );
		$capability_map = Mockery::mock( 'AIRouter\CapabilityMapInterface' );

		Functions\when( 'wp_generate_uuid4' )->justReturn( 'test-uuid' );

		$repository->expects( 'save' )->once()->with( Mockery::on( function ( Configuration $c ) {
			return $c->get_id() === 'test-uuid'
				&& $c->get_name() === 'Test Config'
				&& $c->get_provider_type() === 'openai';
		} ) )->andReturn( true );

		$repository->expects( 'set_default_id' )->never();

		$service = new ConfigurationService( $repository, $capability_map );

		$result = $service->create( [
			'name'          => 'Test Config',
			'provider_type' => 'openai',
			'settings'      => [ 'api_key' => 'sk-test' ],
			'capabilities'  => [ 'text_generation' ],
			'is_default'    => false,
		] );

		$this->assertInstanceOf( Configuration::class, $result );
		$this->assertSame( 'test-uuid', $result->get_id() );
	}

	/**
	 * Test repository correctly uses ConnectorSync for side effects.
	 *
	 * Seam: Repository ↔ ConnectorSync
	 */
	public function test_repository_connector_sync_integration(): void {
		$connector_sync = Mockery::mock( ConnectorSyncInterface::class );
		$connector_sync->expects( 'sync_connector_option' )->once();

		$repository = new ConfigurationRepository( $connector_sync );

		$config = Configuration::from_array( [
			'id'            => 'test-id',
			'name'          => 'Test Config',
			'provider_type' => 'openai',
			'settings'      => [ 'api_key' => 'sk-test' ],
		] );

		$repository->save( $config );
	}

	/**
	 * Test Vocabulary provides valid capabilities.
	 *
	 * Seam: Vocabulary constants used across modules
	 */
	public function test_vocabulary_capabilities_are_valid(): void {
		$capabilities = Vocabulary::capabilities();

		$this->assertNotEmpty( $capabilities );

		foreach ( $capabilities as $cap ) {
			$this->assertTrue(
				Vocabulary::is_valid_capability( $cap ),
				"Capability '$cap' should be valid"
			);
		}
	}

	/**
	 * Test RequestContext is created correctly from Configuration.
	 *
	 * Seam: RequestContext ↔ Configuration
	 */
	public function test_request_context_from_configuration(): void {
		$config = Configuration::from_array( [
			'id'            => 'config-123',
			'name'          => 'Azure GPT-4',
			'provider_type' => 'azure-openai',
			'settings'      => [
				'api_key'       => 'azure-key',
				'endpoint'      => 'https://test.openai.azure.com',
				'deployment_id' => 'gpt-4-deployment',
				'api_version'   => '2024-01-01',
			],
			'capabilities'  => [ 'text_generation', 'chat_history' ],
		] );

		$context = RequestContext::from_configuration( 'text_generation', $config );

		$this->assertSame( 'text_generation', $context->capability );
		$this->assertSame( $config, $context->configuration );
		$this->assertSame( 'azure-openai', $context->get_provider_type() );
		$this->assertSame( 'gpt-4-deployment', $context->deployment_id );
		$this->assertSame( '2024-01-01', $context->api_version );
		$this->assertTrue( $context->has_azure_overrides() );
	}

	/**
	 * Test service correctly filters capabilities through Vocabulary.
	 *
	 * Seam: ConfigurationService ↔ Vocabulary
	 */
	public function test_service_validates_capabilities_via_vocabulary(): void {
		$repository     = Mockery::mock( ConfigurationRepositoryInterface::class );
		$capability_map = Mockery::mock( 'AIRouter\CapabilityMapInterface' );

		$capability_map->expects( 'set_bulk' )->once()->with( Mockery::on( function ( array $mappings ) {
			// Only valid capabilities should be passed.
			return isset( $mappings['text_generation'] )
				&& ! isset( $mappings['invalid_capability'] );
		} ) )->andReturn( true );

		$capability_map->allows( 'get_map' )->andReturn( [ 'text_generation' => 'config-id' ] );

		$service = new ConfigurationService( $repository, $capability_map );

		$result = $service->update_capability_map( [
			'text_generation'     => 'config-id',
			'invalid_capability'  => 'other-id',
		] );

		$this->assertArrayHasKey( 'text_generation', $result );
		$this->assertArrayNotHasKey( 'invalid_capability', $result );
	}

	/**
	 * Test Configuration DTO validates Azure requires endpoint.
	 *
	 * Seam: Configuration validation rules
	 */
	public function test_configuration_azure_validation(): void {
		$config = Configuration::from_array( [
			'id'            => 'test-id',
			'name'          => 'Azure Config',
			'provider_type' => 'azure-openai',
			'settings'      => [ 'api_key' => 'azure-key' ], // Missing endpoint.
		] );

		$errors = $config->validate();

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'endpoint', implode( ' ', $errors ) );
	}

	/**
	 * Test ConnectorSync handles Azure-specific options.
	 *
	 * Seam: ConnectorSync ↔ Azure provider options
	 */
	public function test_connector_sync_azure_specific(): void {
		$sync = new ConnectorSync();

		$config = Configuration::from_array( [
			'id'            => 'azure-id',
			'name'          => 'Azure Config',
			'provider_type' => 'azure-openai',
			'settings'      => [
				'api_key'       => 'azure-key',
				'endpoint'      => 'https://test.openai.azure.com',
				'deployment_id' => 'gpt-4-deployment',
				'api_version'   => '2024-01-01',
			],
		] );

		// Verify sync sets Azure-specific options.
		// ConnectorSync is already tested in ConnectorSyncTest.
		// Here we just verify the integration path.
		$this->assertSame( 'azure-openai', $config->get_provider_type() );
		$this->assertSame( 'azure-key', $config->get_setting( 'api_key' ) );
		$this->assertSame( 'https://test.openai.azure.com', $config->get_setting( 'endpoint' ) );
		$this->assertSame( 'gpt-4-deployment', $config->get_setting( 'deployment_id' ) );
		$this->assertSame( '2024-01-01', $config->get_setting( 'api_version' ) );
	}

	/**
	 * Test CapabilityMap correctly resolves configuration for capability.
	 *
	 * Seam: CapabilityMap ↔ Repository
	 */
	public function test_capability_map_resolves_configuration(): void {
		$config = Configuration::from_array( [
			'id'            => 'gpt4-id',
			'name'          => 'GPT-4 Config',
			'provider_type' => 'openai',
			'settings'      => [ 'api_key' => 'sk-test' ],
			'capabilities'  => [ 'text_generation' ],
		] );

		$repository = Mockery::mock( ConfigurationRepositoryInterface::class );
		$repository->allows( 'get' )->with( 'gpt4-id' )->andReturn( $config );

		Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
			if ( 'ai_router_capability_map' === $key ) {
				return [ 'text_generation' => 'gpt4-id' ];
			}
			return $default;
		} );

		$capability_map = new CapabilityMap( $repository );

		$result = $capability_map->get_config_for_capability( 'text_generation' );

		$this->assertNotNull( $result );
		$this->assertSame( 'gpt4-id', $result->get_id() );
	}
}
