<?php
/**
 * Tests for ConfigurationRepository.
 *
 * @package AIRouter\Tests\Unit
 */

declare(strict_types=1);

namespace AIRouter\Tests\Unit;

use AIRouter\DTO\Configuration;
use AIRouter\Repository\ConfigurationRepository;
use AIRouter\Tests\TestCase;
use Brain\Monkey\Functions;

/**
 * ConfigurationRepository test class.
 */
class ConfigurationRepositoryTest extends TestCase {

	/**
	 * Test get_all returns empty array when no configurations.
	 */
	public function test_get_all_returns_empty_array(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_configurations', [] )
			->andReturn( [] );

		$repository = new ConfigurationRepository();
		$result     = $repository->get_all();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_all returns configurations from option.
	 */
	public function test_get_all_returns_configurations(): void {
		$stored = [
			[
				'id'            => 'config-1',
				'name'          => 'Config 1',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [ 'text_generation' ],
				'is_default'    => false,
			],
			[
				'id'            => 'config-2',
				'name'          => 'Config 2',
				'provider_type' => 'azure-openai',
				'settings'      => [ 'endpoint' => 'https://test.openai.azure.com' ],
				'capabilities'  => [ 'image_generation' ],
				'is_default'    => true,
			],
		];

		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_configurations', [] )
			->andReturn( $stored );

		$repository = new ConfigurationRepository();
		$result     = $repository->get_all();

		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'config-1', $result );
		$this->assertArrayHasKey( 'config-2', $result );
		$this->assertInstanceOf( Configuration::class, $result[ 'config-1' ] );
	}

	/**
	 * Test get returns configuration by ID.
	 */
	public function test_get_returns_configuration_by_id(): void {
		$stored = [
			[
				'id'            => 'target-config',
				'name'          => 'Target',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [],
				'is_default'    => false,
			],
		];

		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_configurations', [] )
			->andReturn( $stored );

		$repository = new ConfigurationRepository();
		$config     = $repository->get( 'target-config' );

		$this->assertInstanceOf( Configuration::class, $config );
		$this->assertSame( 'Target', $config->get_name() );
	}

	/**
	 * Test get returns null for nonexistent ID.
	 */
	public function test_get_returns_null_for_nonexistent(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_configurations', [] )
			->andReturn( [] );

		$repository = new ConfigurationRepository();
		$config     = $repository->get( 'nonexistent' );

		$this->assertNull( $config );
	}

	/**
	 * Test exists returns true when configuration exists.
	 */
	public function test_exists_returns_true(): void {
		$stored = [
			[
				'id'            => 'existing',
				'name'          => 'Existing',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [],
				'is_default'    => false,
			],
		];

		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_configurations', [] )
			->andReturn( $stored );

		$repository = new ConfigurationRepository();

		$this->assertTrue( $repository->exists( 'existing' ) );
	}

	/**
	 * Test save persists configuration.
	 */
	public function test_save_persists_configuration(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_configurations', [] )
			->andReturn( [] );

		Functions\expect( 'update_option' )
			->once()
			->with( 'ai_router_configurations', \Mockery::any() )
			->andReturnUsing(
				function ( $key, $value ) {
					$this->assertSame( 'ai_router_configurations', $key );
					$this->assertCount( 1, $value );
					$this->assertSame( 'new-config', $value[ 0 ][ 'id' ] );
					return true;
				}
			);

		$config = Configuration::from_array(
			[
				'id'            => 'new-config',
				'name'          => 'New Config',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [],
				'is_default'    => false,
			]
		);

		$repository = new ConfigurationRepository();
		$result     = $repository->save( $config );

		$this->assertTrue( $result );
	}

	/**
	 * Test save syncs API key to connector option.
	 */
	public function test_save_syncs_api_key_to_connector_option(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_configurations', [] )
			->andReturn( [] );

		Functions\expect( 'update_option' )
			->once()
			->with( 'ai_router_configurations', \Mockery::any() )
			->andReturn( true );

		Functions\expect( 'update_option' )
			->once()
			->with( 'connectors_ai_openai_api_key', 'sk-test-key' )
			->andReturn( true );

		$config = Configuration::from_array(
			[
				'id'            => 'api-config',
				'name'          => 'API Config',
				'provider_type' => 'openai',
				'settings'      => [ 'api_key' => 'sk-test-key' ],
				'capabilities'  => [ 'text_generation' ],
				'is_default'    => false,
			]
		);

		$repository = new ConfigurationRepository();
		$result     = $repository->save( $config );

		$this->assertTrue( $result );
	}

	/**
	 * Test save syncs Azure OpenAI API key to openai connector option.
	 *
	 * Azure OpenAI unregisters from wp_get_connectors(), so we sync to
	 * the openai option which is always in the registry.
	 */
	public function test_save_syncs_azure_api_key_to_connector_option(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_configurations', [] )
			->andReturn( [] );

		Functions\expect( 'update_option' )
			->once()
			->with( 'ai_router_configurations', \Mockery::any() )
			->andReturn( true );

		// Azure syncs to openai option (azure unregisters from connector registry).
		Functions\expect( 'update_option' )
			->once()
			->with( 'connectors_ai_openai_api_key', 'azure-test-key' )
			->andReturn( true );

		$config = Configuration::from_array(
			[
				'id'            => 'azure-config',
				'name'          => 'Azure Config',
				'provider_type' => 'azure-openai',
				'settings'      => [ 'api_key' => 'azure-test-key' ],
				'capabilities'  => [ 'text_generation' ],
				'is_default'    => false,
			]
		);

		$repository = new ConfigurationRepository();
		$result     = $repository->save( $config );

		$this->assertTrue( $result );
	}

	/**
	 * Test delete removes configuration.
	 */
	public function test_delete_removes_configuration(): void {
		$stored = [
			[
				'id'            => 'to-delete',
				'name'          => 'Delete Me',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [],
				'is_default'    => false,
			],
		];

		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_configurations', [] )
			->andReturn( $stored );

		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_default_config', '' )
			->andReturn( '' );

		Functions\expect( 'update_option' )
			->once()
			->andReturnUsing(
				function ( $key, $value ) {
					$this->assertSame( 'ai_router_configurations', $key );
					$this->assertEmpty( $value );
					return true;
				}
			);

		$repository = new ConfigurationRepository();
		$result     = $repository->delete( 'to-delete' );

		$this->assertTrue( $result );
	}

	/**
	 * Test delete returns false for nonexistent configuration.
	 */
	public function test_delete_returns_false_for_nonexistent(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_configurations', [] )
			->andReturn( [] );

		$repository = new ConfigurationRepository();
		$result     = $repository->delete( 'nonexistent' );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_by_provider_type filters correctly.
	 */
	public function test_get_by_provider_type(): void {
		$stored = [
			[
				'id'            => 'openai-1',
				'name'          => 'OpenAI 1',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [],
				'is_default'    => false,
			],
			[
				'id'            => 'azure-1',
				'name'          => 'Azure 1',
				'provider_type' => 'azure-openai',
				'settings'      => [],
				'capabilities'  => [],
				'is_default'    => false,
			],
			[
				'id'            => 'openai-2',
				'name'          => 'OpenAI 2',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [],
				'is_default'    => false,
			],
		];

		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_configurations', [] )
			->andReturn( $stored );

		$repository = new ConfigurationRepository();
		$result     = $repository->get_by_provider_type( 'openai' );

		$this->assertCount( 2, $result );
	}

	/**
	 * Test get_by_capability filters correctly.
	 */
	public function test_get_by_capability(): void {
		$stored = [
			[
				'id'            => 'text-config',
				'name'          => 'Text Config',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [ 'text_generation' ],
				'is_default'    => false,
			],
			[
				'id'            => 'image-config',
				'name'          => 'Image Config',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [ 'image_generation' ],
				'is_default'    => false,
			],
		];

		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_configurations', [] )
			->andReturn( $stored );

		$repository = new ConfigurationRepository();
		$result     = $repository->get_by_capability( 'text_generation' );

		$this->assertCount( 1, $result );
		$this->assertArrayHasKey( 'text-config', $result );
	}

	/**
	 * Test get_default_id returns stored value.
	 */
	public function test_get_default_id(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_default_config', '' )
			->andReturn( 'my-default' );

		$repository = new ConfigurationRepository();
		$result     = $repository->get_default_id();

		$this->assertSame( 'my-default', $result );
	}

	/**
	 * Test set_default_id updates option.
	 */
	public function test_set_default_id(): void {
		Functions\expect( 'update_option' )
			->once()
			->with( 'ai_router_default_config', 'new-default' )
			->andReturn( true );

		$repository = new ConfigurationRepository();
		$result     = $repository->set_default_id( 'new-default' );

		$this->assertTrue( $result );
	}

	/**
	 * Test cache is cleared properly.
	 */
	public function test_clear_cache(): void {
		$stored = [
			[
				'id'            => 'cached',
				'name'          => 'Cached',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [],
				'is_default'    => false,
			],
		];

		// First call loads from option.
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_configurations', [] )
			->andReturn( $stored );

		$repository = new ConfigurationRepository();
		$repository->get_all(); // Populates cache.

		// Second call returns cached.
		$repository->get_all(); // Should not call get_option again.

		// Clear cache.
		$repository->clear_cache();

		// Now it should call get_option again.
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_configurations', [] )
			->andReturn( [] );

		$result = $repository->get_all();
		$this->assertEmpty( $result );
	}
}
