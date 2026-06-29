<?php
/**
 * Unit tests for ConfigurationService.
 *
 * @package AIRouter\Tests\Unit
 */

declare(strict_types=1);

namespace AIRouter\Tests\Unit;

use AIRouter\CapabilityMapInterface;
use AIRouter\DTO\Configuration;
use AIRouter\Repository\ConfigurationRepositoryInterface;
use AIRouter\Service\ConfigurationNotFoundException;
use AIRouter\Service\ConfigurationService;
use AIRouter\Service\ConfigurationValidationException;
use AIRouter\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\MockInterface;

/**
 * Test ConfigurationService.
 *
 * @covers \AIRouter\Service\ConfigurationService
 */
class ConfigurationServiceTest extends TestCase {

	/** @var ConfigurationRepositoryInterface&MockInterface */
	private ConfigurationRepositoryInterface $repository;

	/** @var CapabilityMapInterface&MockInterface */
	private CapabilityMapInterface $capability_map;

	private ConfigurationService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->repository     = Mockery::mock( ConfigurationRepositoryInterface::class );
		$this->capability_map = Mockery::mock( CapabilityMapInterface::class );
		$this->service        = new ConfigurationService( $this->repository, $this->capability_map );
	}

	/**
	 * Test list returns all configurations.
	 */
	public function test_list_returns_all_configurations(): void {
		$configs = [
			'id1' => Configuration::from_array( [
				'id'            => 'id1',
				'name'          => 'Config 1',
				'provider_type' => 'openai',
				'settings'      => [],
			] ),
			'id2' => Configuration::from_array( [
				'id'            => 'id2',
				'name'          => 'Config 2',
				'provider_type' => 'anthropic',
				'settings'      => [],
			] ),
		];

		$this->repository->allows( 'get_all' )->andReturn( $configs );

		$result = $this->service->list();

		$this->assertCount( 2, $result );
		$this->assertSame( 'id1', $result[0]->get_id() );
		$this->assertSame( 'id2', $result[1]->get_id() );
	}

	/**
	 * Test get returns configuration by ID.
	 */
	public function test_get_returns_configuration_by_id(): void {
		$config = Configuration::from_array( [
			'id'            => 'test-id',
			'name'          => 'Test Config',
			'provider_type' => 'openai',
			'settings'      => [],
		] );

		$this->repository->allows( 'get' )->with( 'test-id' )->andReturn( $config );

		$result = $this->service->get( 'test-id' );

		$this->assertSame( $config, $result );
	}

	/**
	 * Test get returns null for unknown ID.
	 */
	public function test_get_returns_null_for_unknown_id(): void {
		$this->repository->allows( 'get' )->with( 'unknown-id' )->andReturn( null );

		$result = $this->service->get( 'unknown-id' );

		$this->assertNull( $result );
	}

	/**
	 * Test create creates and saves configuration.
	 */
	public function test_create_creates_and_saves_configuration(): void {
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'generated-uuid' );

		$this->repository->expects( 'save' )->once()->with( Mockery::on( function ( Configuration $c ) {
			return $c->get_id() === 'generated-uuid'
				&& $c->get_name() === 'My Config'
				&& $c->get_provider_type() === 'openai';
		} ) );

		$this->repository->expects( 'set_default_id' )->never();

		$result = $this->service->create( [
			'name'          => 'My Config',
			'provider_type' => 'openai',
			'settings'      => [ 'api_key' => 'sk-test' ],
			'capabilities'  => [],
			'is_default'    => false,
		] );

		$this->assertSame( 'generated-uuid', $result->get_id() );
		$this->assertSame( 'My Config', $result->get_name() );
		$this->assertSame( 'openai', $result->get_provider_type() );
	}

	/**
	 * Test create normalizes provider type.
	 */
	public function test_create_normalizes_provider_type(): void {
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'generated-uuid' );

		$this->repository->expects( 'save' )->once()->with( Mockery::on( function ( Configuration $c ) {
			return $c->get_provider_type() === 'azure-openai';
		} ) );

		$result = $this->service->create( [
			'name'          => 'My Config',
			'provider_type' => 'azure_openai', // Underscore variant.
			'settings'      => [ 'endpoint' => 'https://test.openai.azure.com' ],
			'capabilities'  => [],
			'is_default'    => false,
		] );

		$this->assertSame( 'azure-openai', $result->get_provider_type() );
	}

	/**
	 * Test create sets default when is_default is true.
	 */
	public function test_create_sets_default_when_is_default_is_true(): void {
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'default-uuid' );

		$this->repository->expects( 'save' )->once();
		$this->repository->expects( 'set_default_id' )->once()->with( 'default-uuid' );

		$this->service->create( [
			'name'          => 'Default Config',
			'provider_type' => 'openai',
			'settings'      => [],
			'capabilities'  => [],
			'is_default'    => true,
		] );
	}

	/**
	 * Test create throws on validation error.
	 */
	public function test_create_throws_on_validation_error(): void {
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'test-uuid' );

		$this->expectException( ConfigurationValidationException::class );

		$this->service->create( [
			'name'          => '', // Empty name is invalid.
			'provider_type' => 'openai',
			'settings'      => [],
			'capabilities'  => [],
			'is_default'    => false,
		] );
	}

	/**
	 * Test update updates and saves configuration.
	 */
	public function test_update_updates_and_saves_configuration(): void {
		$existing = Configuration::from_array( [
			'id'            => 'existing-id',
			'name'          => 'Old Name',
			'provider_type' => 'openai',
			'settings'      => [ 'model' => 'gpt-3.5' ],
		] );

		$this->repository->allows( 'get' )->with( 'existing-id' )->andReturn( $existing );
		$this->repository->expects( 'save' )->once()->with( Mockery::on( function ( Configuration $c ) {
			return $c->get_id() === 'existing-id'
				&& $c->get_name() === 'New Name'
				&& $c->get_setting( 'model' ) === 'gpt-4';
		} ) );
		$this->repository->allows( 'get_default_id' )->andReturn( '' );

		$result = $this->service->update( 'existing-id', [
			'name'     => 'New Name',
			'settings' => [ 'model' => 'gpt-4' ],
		] );

		$this->assertSame( 'New Name', $result->get_name() );
	}

	/**
	 * Test update throws for unknown configuration.
	 */
	public function test_update_throws_for_unknown_configuration(): void {
		$this->repository->allows( 'get' )->with( 'unknown-id' )->andReturn( null );

		$this->expectException( ConfigurationNotFoundException::class );

		$this->service->update( 'unknown-id', [ 'name' => 'New Name' ] );
	}

	/**
	 * Test update handles default flag changes.
	 */
	public function test_update_handles_default_flag_changes(): void {
		$existing = Configuration::from_array( [
			'id'            => 'existing-id',
			'name'          => 'Test',
			'provider_type' => 'openai',
			'settings'      => [],
			'is_default'    => false,
		] );

		$this->repository->allows( 'get' )->with( 'existing-id' )->andReturn( $existing );
		$this->repository->expects( 'save' )->once();
		$this->repository->expects( 'set_default_id' )->once()->with( 'existing-id' );
		$this->repository->allows( 'get_default_id' )->andReturn( '' );

		$this->service->update( 'existing-id', [ 'is_default' => true ] );
	}

	/**
	 * Test update clears default when is_default becomes false.
	 */
	public function test_update_clears_default_when_is_default_becomes_false(): void {
		$existing = Configuration::from_array( [
			'id'            => 'existing-id',
			'name'          => 'Test',
			'provider_type' => 'openai',
			'settings'      => [],
			'is_default'    => true,
		] );

		$this->repository->allows( 'get' )->with( 'existing-id' )->andReturn( $existing );
		$this->repository->expects( 'save' )->once();
		$this->repository->allows( 'get_default_id' )->andReturn( 'existing-id' );
		$this->repository->expects( 'set_default_id' )->once()->with( '' );

		$this->service->update( 'existing-id', [ 'is_default' => false ] );
	}

	/**
	 * Test delete removes configuration.
	 */
	public function test_delete_removes_configuration(): void {
		$existing = Configuration::from_array( [
			'id'            => 'delete-id',
			'name'          => 'To Delete',
			'provider_type' => 'openai',
			'settings'      => [],
		] );

		$this->repository->allows( 'get' )->with( 'delete-id' )->andReturn( $existing );
		$this->capability_map->expects( 'remove_config' )->once()->with( 'delete-id' );
		$this->repository->expects( 'delete' )->once()->with( 'delete-id' )->andReturn( true );

		$result = $this->service->delete( 'delete-id' );

		$this->assertTrue( $result );
	}

	/**
	 * Test delete throws for unknown configuration.
	 */
	public function test_delete_throws_for_unknown_configuration(): void {
		$this->repository->allows( 'get' )->with( 'unknown-id' )->andReturn( null );

		$this->expectException( ConfigurationNotFoundException::class );

		$this->service->delete( 'unknown-id' );
	}

	/**
	 * Test get_default_id returns default ID.
	 */
	public function test_get_default_id_returns_default_id(): void {
		$this->repository->allows( 'get_default_id' )->andReturn( 'default-id' );

		$result = $this->service->get_default_id();

		$this->assertSame( 'default-id', $result );
	}

	/**
	 * Test set_default sets default configuration.
	 */
	public function test_set_default_sets_default_configuration(): void {
		$this->repository->allows( 'exists' )->with( 'new-default' )->andReturn( true );
		$this->repository->expects( 'set_default_id' )->once()->with( 'new-default' );

		$this->service->set_default( 'new-default' );
	}

	/**
	 * Test set_default with empty clears default.
	 */
	public function test_set_default_with_empty_clears_default(): void {
		$this->repository->expects( 'set_default_id' )->once()->with( '' );

		$this->service->set_default( '' );
	}

	/**
	 * Test set_default throws for unknown configuration.
	 */
	public function test_set_default_throws_for_unknown_configuration(): void {
		$this->repository->allows( 'exists' )->with( 'unknown-id' )->andReturn( false );

		$this->expectException( ConfigurationNotFoundException::class );

		$this->service->set_default( 'unknown-id' );
	}

	/**
	 * Test get_capability_map returns map.
	 */
	public function test_get_capability_map_returns_map(): void {
		$map = [ 'text_generation' => 'config-id', 'image_generation' => 'other-id' ];

		$this->capability_map->allows( 'get_map' )->andReturn( $map );

		$result = $this->service->get_capability_map();

		$this->assertSame( $map, $result );
	}

	/**
	 * Test update_capability_map updates mappings.
	 */
	public function test_update_capability_map_updates_mappings(): void {
		$mappings = [ 'text_generation' => 'new-config', 'image_generation' => 'other-config' ];

		$this->capability_map->expects( 'set_bulk' )->once()->with( $mappings );
		$this->capability_map->allows( 'get_map' )->andReturn( $mappings );

		$result = $this->service->update_capability_map( $mappings );

		$this->assertSame( $mappings, $result );
	}

	/**
	 * Test get_capabilities_for_config returns capabilities.
	 */
	public function test_get_capabilities_for_config_returns_capabilities(): void {
		$capabilities = [ 'text_generation', 'image_generation' ];

		$this->capability_map->allows( 'get_capabilities_for_config' )->with( 'config-id' )->andReturn( $capabilities );

		$result = $this->service->get_capabilities_for_config( 'config-id' );

		$this->assertSame( $capabilities, $result );
	}

	/**
	 * Test exists returns true for existing configuration.
	 */
	public function test_exists_returns_true_for_existing_configuration(): void {
		$this->repository->allows( 'exists' )->with( 'existing-id' )->andReturn( true );

		$result = $this->service->exists( 'existing-id' );

		$this->assertTrue( $result );
	}

	/**
	 * Test exists returns false for non-existing configuration.
	 */
	public function test_exists_returns_false_for_non_existing_configuration(): void {
		$this->repository->allows( 'exists' )->with( 'unknown-id' )->andReturn( false );

		$result = $this->service->exists( 'unknown-id' );

		$this->assertFalse( $result );
	}
}
