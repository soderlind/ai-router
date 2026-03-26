<?php
/**
 * Tests for Router.
 *
 * @package AIRouter\Tests\Unit
 */

declare(strict_types=1);

namespace AIRouter\Tests\Unit;

use AIRouter\CapabilityMap;
use AIRouter\DTO\Configuration;
use AIRouter\Repository\ConfigurationRepositoryInterface;
use AIRouter\Router;
use AIRouter\Tests\TestCase;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Router test class.
 */
class RouterTest extends TestCase {

	/**
	 * Mock repository.
	 *
	 * @var ConfigurationRepositoryInterface&\Mockery\MockInterface
	 */
	private $repository;

	/**
	 * Mock capability map.
	 *
	 * @var CapabilityMap&\Mockery\MockInterface
	 */
	private $capability_map;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->repository     = Mockery::mock( ConfigurationRepositoryInterface::class);
		$this->capability_map = Mockery::mock( CapabilityMap::class);
	}

	/**
	 * Test init registers hooks.
	 */
	public function test_init_registers_hooks(): void {
		Actions\expectAdded( 'wp_ai_client_before_generate_result' )
			->once();

		// Two init hooks: register_configured_providers (priority 5) and setup_provider_authentication (priority 25).
		Actions\expectAdded( 'init' )
			->twice();

		$router = new Router( $this->repository, $this->capability_map );
		$router->init();
	}

	/**
	 * Test get_configuration_for_capability returns mapped config.
	 */
	public function test_get_configuration_for_capability_returns_mapped(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'mapped-config',
				'name'          => 'Mapped Config',
				'provider_type' => 'openai',
				'settings'      => [ 'api_key' => 'test-key' ],
				'capabilities'  => [ 'text_generation' ],
				'is_default'    => false,
			]
		);

		Filters\expectApplied( 'ai_router_get_configuration' )
			->once()
			->with( null, 'text_generation' )
			->andReturn( null );

		$this->capability_map
			->shouldReceive( 'get_config_for_capability' )
			->once()
			->with( 'text_generation' )
			->andReturn( $config );

		$router = new Router( $this->repository, $this->capability_map );
		$result = $router->get_configuration_for_capability( 'text_generation' );

		$this->assertSame( $config, $result );
	}

	/**
	 * Test get_configuration_for_capability respects filter.
	 */
	public function test_get_configuration_for_capability_respects_filter(): void {
		$filter_config = Configuration::from_array(
			[
				'id'            => 'filter-config',
				'name'          => 'Filter Config',
				'provider_type' => 'openai',
				'settings'      => [],
				'capabilities'  => [],
				'is_default'    => false,
			]
		);

		Filters\expectApplied( 'ai_router_get_configuration' )
			->once()
			->with( null, 'text_generation' )
			->andReturn( $filter_config );

		$router = new Router( $this->repository, $this->capability_map );
		$result = $router->get_configuration_for_capability( 'text_generation' );

		$this->assertSame( $filter_config, $result );
	}

	/**
	 * Test get_configuration_for_capability falls back to default.
	 */
	public function test_get_configuration_for_capability_falls_back_to_default(): void {
		$default = Configuration::from_array(
			[
				'id'            => 'default-config',
				'name'          => 'Default Config',
				'provider_type' => 'openai',
				'settings'      => [ 'api_key' => 'key' ],
				'capabilities'  => [ 'text_generation' ],
				'is_default'    => true,
			]
		);

		Filters\expectApplied( 'ai_router_get_configuration' )
			->once()
			->andReturn( null );

		$this->capability_map
			->shouldReceive( 'get_config_for_capability' )
			->once()
			->andReturn( null );

		$this->repository
			->shouldReceive( 'get_default' )
			->once()
			->andReturn( $default );

		$router = new Router( $this->repository, $this->capability_map );
		$result = $router->get_configuration_for_capability( 'text_generation' );

		$this->assertSame( $default, $result );
	}

	/**
	 * Test get_configuration_for_capability returns null when nothing available.
	 */
	public function test_get_configuration_for_capability_returns_null(): void {
		Filters\expectApplied( 'ai_router_get_configuration' )
			->once()
			->andReturn( null );

		$this->capability_map
			->shouldReceive( 'get_config_for_capability' )
			->once()
			->andReturn( null );

		$this->repository
			->shouldReceive( 'get_default' )
			->once()
			->andReturn( null );

		$this->repository
			->shouldReceive( 'get_by_capability' )
			->once()
			->with( 'text_generation' )
			->andReturn( [] );

		$router = new Router( $this->repository, $this->capability_map );
		$result = $router->get_configuration_for_capability( 'text_generation' );

		$this->assertNull( $result );
	}

	/**
	 * Test get_configuration_for_capability finds fallback from any config.
	 */
	public function test_get_configuration_for_capability_finds_fallback(): void {
		$fallback = Configuration::from_array(
			[
				'id'            => 'fallback-config',
				'name'          => 'Fallback',
				'provider_type' => 'openai',
				'settings'      => [ 'api_key' => 'key' ],
				'capabilities'  => [ 'text_generation' ],
				'is_default'    => false,
			]
		);

		Filters\expectApplied( 'ai_router_get_configuration' )
			->once()
			->andReturn( null );

		$this->capability_map
			->shouldReceive( 'get_config_for_capability' )
			->once()
			->andReturn( null );

		$this->repository
			->shouldReceive( 'get_default' )
			->once()
			->andReturn( null );

		$this->repository
			->shouldReceive( 'get_by_capability' )
			->once()
			->with( 'text_generation' )
			->andReturn( [ 'fallback-config' => $fallback ] );

		Filters\expectApplied( 'ai_router_fallback_config' )
			->once()
			->with( $fallback, 'text_generation' )
			->andReturn( $fallback );

		$router = new Router( $this->repository, $this->capability_map );
		$result = $router->get_configuration_for_capability( 'text_generation' );

		$this->assertSame( $fallback, $result );
	}

	/**
	 * Test get_capability_label returns human-readable label.
	 */
	public function test_get_capability_label(): void {
		$this->assertSame( 'Text Generation', Router::get_capability_label( 'text_generation' ) );
		$this->assertSame( 'Image Generation', Router::get_capability_label( 'image_generation' ) );
		$this->assertSame( 'Chat History', Router::get_capability_label( 'chat_history' ) );
		$this->assertSame( 'Text to Speech', Router::get_capability_label( 'text_to_speech_conversion' ) );
	}

	/**
	 * Test get_capability_label returns slug for unknown capability.
	 */
	public function test_get_capability_label_returns_slug_for_unknown(): void {
		$this->assertSame( 'unknown_capability', Router::get_capability_label( 'unknown_capability' ) );
	}

	/**
	 * Test before_generate fires routed action.
	 */
	public function test_before_generate_fires_action(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'test-config',
				'name'          => 'Test',
				'provider_type' => 'openai',
				'settings'      => [ 'api_key' => 'key' ],
				'capabilities'  => [ 'text_generation' ],
				'is_default'    => false,
			]
		);

		// Mock the event object with getCapability() method.
		$cap_enum        = new \stdClass();
		$cap_enum->value = 'text_generation';

		$event = Mockery::mock( 'BeforeGenerateResultEvent' );
		$event->shouldReceive( 'getCapability' )->andReturn( $cap_enum );

		Filters\expectApplied( 'ai_router_get_configuration' )
			->once()
			->with( null, 'text_generation' )
			->andReturn( null );

		$this->capability_map
			->shouldReceive( 'get_config_for_capability' )
			->once()
			->with( 'text_generation' )
			->andReturn( $config );

		Actions\expectDone( 'ai_router_routed' )
			->once()
			->with( $config, 'text_generation', $event );

		$router = new Router( $this->repository, $this->capability_map );
		$router->before_generate( $event );
	}

	/**
	 * Test before_generate does nothing when no config found.
	 */
	public function test_before_generate_does_nothing_when_no_config(): void {
		// Mock the event object with getCapability() method.
		$cap_enum        = new \stdClass();
		$cap_enum->value = 'text_generation';

		$event = Mockery::mock( 'BeforeGenerateResultEvent' );
		$event->shouldReceive( 'getCapability' )->andReturn( $cap_enum );

		Filters\expectApplied( 'ai_router_get_configuration' )
			->once()
			->with( null, 'text_generation' )
			->andReturn( null );

		$this->capability_map
			->shouldReceive( 'get_config_for_capability' )
			->once()
			->with( 'text_generation' )
			->andReturn( null );

		$this->repository
			->shouldReceive( 'get_default' )
			->once()
			->andReturn( null );

		$this->repository
			->shouldReceive( 'get_by_capability' )
			->once()
			->with( 'text_generation' )
			->andReturn( [] );

		// ai_router_routed should NOT be called.
		Actions\expectDone( 'ai_router_routed' )
			->never();

		$router = new Router( $this->repository, $this->capability_map );
		$router->before_generate( $event );
	}
}
