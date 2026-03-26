<?php
/**
 * Tests for CapabilityMap.
 *
 * @package AIRouter\Tests\Unit
 */

declare(strict_types=1);

namespace AIRouter\Tests\Unit;

use AIRouter\CapabilityMap;
use AIRouter\DTO\Configuration;
use AIRouter\Repository\ConfigurationRepositoryInterface;
use AIRouter\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

/**
 * CapabilityMap test class.
 */
class CapabilityMapTest extends TestCase {

	/**
	 * Mock repository.
	 *
	 * @var ConfigurationRepositoryInterface&\Mockery\MockInterface
	 */
	private $repository;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->repository = Mockery::mock( ConfigurationRepositoryInterface::class);
	}

	/**
	 * Test get_map returns stored map.
	 */
	public function test_get_map_returns_stored_map(): void {
		$stored = [
			'text_generation'  => 'config-1',
			'image_generation' => 'config-2',
		];

		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_capability_map', [] )
			->andReturn( $stored );

		$map    = new CapabilityMap( $this->repository );
		$result = $map->get_map();

		$this->assertEquals( $stored, $result );
	}

	/**
	 * Test get_map returns empty array when no map stored.
	 */
	public function test_get_map_returns_empty_array(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_capability_map', [] )
			->andReturn( [] );

		$map    = new CapabilityMap( $this->repository );
		$result = $map->get_map();

		$this->assertEmpty( $result );
	}

	/**
	 * Test get_config_id_for_capability returns mapped ID.
	 */
	public function test_get_config_id_for_capability(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_capability_map', [] )
			->andReturn( [ 'text_generation' => 'config-123' ] );

		$map    = new CapabilityMap( $this->repository );
		$result = $map->get_config_id_for_capability( 'text_generation' );

		$this->assertSame( 'config-123', $result );
	}

	/**
	 * Test get_config_id_for_capability returns null for unmapped.
	 */
	public function test_get_config_id_for_capability_returns_null(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_capability_map', [] )
			->andReturn( [] );

		$map    = new CapabilityMap( $this->repository );
		$result = $map->get_config_id_for_capability( 'text_generation' );

		$this->assertNull( $result );
	}

	/**
	 * Test get_config_for_capability returns configuration.
	 */
	public function test_get_config_for_capability(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'config-123',
				'name'          => 'Test Config',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [ 'text_generation' ],
				'is_default'    => false,
			]
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_capability_map', [] )
			->andReturn( [ 'text_generation' => 'config-123' ] );

		$this->repository
			->shouldReceive( 'get' )
			->once()
			->with( 'config-123' )
			->andReturn( $config );

		$map    = new CapabilityMap( $this->repository );
		$result = $map->get_config_for_capability( 'text_generation' );

		$this->assertSame( $config, $result );
	}

	/**
	 * Test set maps capability to configuration.
	 */
	public function test_set_maps_capability(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'config-new',
				'name'          => 'New Config',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [ 'text_generation' ],
				'is_default'    => false,
			]
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_capability_map', [] )
			->andReturn( [] );

		$this->repository
			->shouldReceive( 'get' )
			->once()
			->with( 'config-new' )
			->andReturn( $config );

		Functions\expect( 'update_option' )
			->once()
			->andReturnUsing(
				function ( $key, $value ) {
					$this->assertSame( 'ai_router_capability_map', $key );
					$this->assertSame( 'config-new', $value[ 'text_generation' ] );
					return true;
				}
			);

		$map    = new CapabilityMap( $this->repository );
		$result = $map->set( 'text_generation', 'config-new' );

		$this->assertTrue( $result );
	}

	/**
	 * Test set returns false if config doesn't exist.
	 */
	public function test_set_returns_false_for_nonexistent_config(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_capability_map', [] )
			->andReturn( [] );

		$this->repository
			->shouldReceive( 'get' )
			->once()
			->with( 'nonexistent' )
			->andReturn( null );

		$map    = new CapabilityMap( $this->repository );
		$result = $map->set( 'text_generation', 'nonexistent' );

		$this->assertFalse( $result );
	}

	/**
	 * Test set returns false if config doesn't support capability.
	 */
	public function test_set_returns_false_if_capability_not_supported(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'config-image',
				'name'          => 'Image Config',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [ 'image_generation' ], // Not text_generation!
				'is_default'    => false,
			]
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_capability_map', [] )
			->andReturn( [] );

		$this->repository
			->shouldReceive( 'get' )
			->once()
			->with( 'config-image' )
			->andReturn( $config );

		$map    = new CapabilityMap( $this->repository );
		$result = $map->set( 'text_generation', 'config-image' );

		$this->assertFalse( $result );
	}

	/**
	 * Test remove unmaps capability.
	 */
	public function test_remove_unmaps_capability(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_capability_map', [] )
			->andReturn( [ 'text_generation' => 'config-123' ] );

		Functions\expect( 'update_option' )
			->once()
			->andReturnUsing(
				function ( $key, $value ) {
					$this->assertSame( 'ai_router_capability_map', $key );
					$this->assertArrayNotHasKey( 'text_generation', $value );
					return true;
				}
			);

		$map    = new CapabilityMap( $this->repository );
		$result = $map->remove( 'text_generation' );

		$this->assertTrue( $result );
	}

	/**
	 * Test remove_config removes all mappings for a config.
	 */
	public function test_remove_config_removes_all_mappings(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_capability_map', [] )
			->andReturn(
				[
					'text_generation'  => 'config-to-remove',
					'image_generation' => 'config-keep',
					'chat_history'     => 'config-to-remove',
				]
			);

		Functions\expect( 'update_option' )
			->once()
			->andReturnUsing(
				function ( $key, $value ) {
					$this->assertSame( 'ai_router_capability_map', $key );
					$this->assertCount( 1, $value );
					$this->assertSame( 'config-keep', $value[ 'image_generation' ] );
					return true;
				}
			);

		$map    = new CapabilityMap( $this->repository );
		$result = $map->remove_config( 'config-to-remove' );

		$this->assertTrue( $result );
	}

	/**
	 * Test get_capabilities_for_config returns mapped capabilities.
	 */
	public function test_get_capabilities_for_config(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_capability_map', [] )
			->andReturn(
				[
					'text_generation'  => 'target-config',
					'image_generation' => 'other-config',
					'chat_history'     => 'target-config',
				]
			);

		$map    = new CapabilityMap( $this->repository );
		$result = $map->get_capabilities_for_config( 'target-config' );

		$this->assertCount( 2, $result );
		$this->assertContains( 'text_generation', $result );
		$this->assertContains( 'chat_history', $result );
	}

	/**
	 * Test get_unmapped_capabilities returns unmapped capabilities.
	 */
	public function test_get_unmapped_capabilities(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_capability_map', [] )
			->andReturn(
				[
					'text_generation' => 'config-1',
				]
			);

		$map    = new CapabilityMap( $this->repository );
		$result = $map->get_unmapped_capabilities();

		$this->assertContains( 'image_generation', $result );
		$this->assertContains( 'embedding_generation', $result );
		$this->assertNotContains( 'text_generation', $result );
	}

	/**
	 * Test is_mapped returns correct boolean.
	 */
	public function test_is_mapped(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_capability_map', [] )
			->andReturn( [ 'text_generation' => 'config-1' ] );

		$map = new CapabilityMap( $this->repository );

		$this->assertTrue( $map->is_mapped( 'text_generation' ) );
		$this->assertFalse( $map->is_mapped( 'image_generation' ) );
	}

	/**
	 * Test set_bulk updates multiple mappings.
	 */
	public function test_set_bulk(): void {
		$text_config = Configuration::from_array(
			[
				'id'            => 'text-config',
				'name'          => 'Text',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [ 'text_generation', 'chat_history' ],
				'is_default'    => false,
			]
		);

		$image_config = Configuration::from_array(
			[
				'id'            => 'image-config',
				'name'          => 'Image',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [ 'image_generation' ],
				'is_default'    => false,
			]
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'ai_router_capability_map', [] )
			->andReturn( [] );

		$this->repository
			->shouldReceive( 'get' )
			->with( 'text-config' )
			->andReturn( $text_config );

		$this->repository
			->shouldReceive( 'get' )
			->with( 'image-config' )
			->andReturn( $image_config );

		Functions\expect( 'update_option' )
			->once()
			->andReturnUsing(
				function ( $key, $value ) {
					$this->assertSame( 'text-config', $value[ 'text_generation' ] );
					$this->assertSame( 'image-config', $value[ 'image_generation' ] );
					return true;
				}
			);

		$map    = new CapabilityMap( $this->repository );
		$result = $map->set_bulk(
			[
				'text_generation'  => 'text-config',
				'image_generation' => 'image-config',
			]
		);

		$this->assertTrue( $result );
	}
}
