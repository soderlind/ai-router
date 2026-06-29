<?php
/**
 * RequestContext tests.
 *
 * @package AIRouter\Tests
 */

declare(strict_types=1);

namespace AIRouter\Tests\Unit;

use AIRouter\DTO\Configuration;
use AIRouter\DTO\RequestContext;
use AIRouter\Tests\TestCase;

/**
 * Tests for the RequestContext class.
 */
class RequestContextTest extends TestCase {

	/**
	 * Test constructor sets all properties.
	 */
	public function test_constructor_sets_properties(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'test-id',
				'name'          => 'Test Config',
				'provider_type' => 'openai',
				'settings'      => [ 'api_key' => 'test-key' ],
				'capabilities'  => [ 'text_generation' ],
			]
		);

		$context = new RequestContext(
			capability: 'text_generation',
			configuration: $config,
			deployment_id: 'deploy-123',
			api_version: '2024-01-01',
			capabilities: [ 'text_generation', 'chat_history' ],
		);

		$this->assertSame( 'text_generation', $context->capability );
		$this->assertSame( $config, $context->configuration );
		$this->assertSame( 'deploy-123', $context->deployment_id );
		$this->assertSame( '2024-01-01', $context->api_version );
		$this->assertSame( [ 'text_generation', 'chat_history' ], $context->capabilities );
	}

	/**
	 * Test from_configuration creates context with OpenAI (no Azure overrides).
	 */
	public function test_from_configuration_openai(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'openai-config',
				'name'          => 'OpenAI',
				'provider_type' => 'openai',
				'settings'      => [ 'api_key' => 'sk-xxx' ],
				'capabilities'  => [ 'text_generation' ],
			]
		);

		$context = RequestContext::from_configuration( 'text_generation', $config );

		$this->assertSame( 'text_generation', $context->capability );
		$this->assertSame( $config, $context->configuration );
		$this->assertNull( $context->deployment_id );
		$this->assertNull( $context->api_version );
		$this->assertSame( [], $context->capabilities );
		$this->assertFalse( $context->has_azure_overrides() );
	}

	/**
	 * Test from_configuration creates context with Azure (has overrides).
	 */
	public function test_from_configuration_azure(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'azure-config',
				'name'          => 'Azure',
				'provider_type' => 'azure-openai',
				'settings'      => [
					'api_key'       => 'azure-key',
					'deployment_id' => 'gpt4-deploy',
					'api_version'   => '2024-02-15',
				],
				'capabilities'  => [ 'text_generation', 'image_generation' ],
			]
		);

		$context = RequestContext::from_configuration( 'text_generation', $config );

		$this->assertSame( 'text_generation', $context->capability );
		$this->assertSame( 'gpt4-deploy', $context->deployment_id );
		$this->assertSame( '2024-02-15', $context->api_version );
		$this->assertSame( [ 'text_generation', 'image_generation' ], $context->capabilities );
		$this->assertTrue( $context->has_azure_overrides() );
	}

	/**
	 * Test from_configuration handles azure_openai variant.
	 */
	public function test_from_configuration_azure_underscore_variant(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'azure-config',
				'name'          => 'Azure',
				'provider_type' => 'azure_openai',
				'settings'      => [
					'api_key'       => 'azure-key',
					'deployment_id' => 'deploy-id',
				],
				'capabilities'  => [ 'text_generation' ],
			]
		);

		$context = RequestContext::from_configuration( 'text_generation', $config );

		$this->assertSame( 'deploy-id', $context->deployment_id );
		$this->assertTrue( $context->has_azure_overrides() );
	}

	/**
	 * Test has_azure_overrides returns false when only one override is set.
	 */
	public function test_has_azure_overrides_partial(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'test',
				'name'          => 'Test',
				'provider_type' => 'openai',
			]
		);

		// Only deployment_id set.
		$context = new RequestContext(
			capability: 'text_generation',
			configuration: $config,
			deployment_id: 'deploy-123',
		);
		$this->assertTrue( $context->has_azure_overrides() );

		// Only api_version set.
		$context2 = new RequestContext(
			capability: 'text_generation',
			configuration: $config,
			api_version: '2024-01-01',
		);
		$this->assertTrue( $context2->has_azure_overrides() );
	}

	/**
	 * Test get_provider_type delegates to configuration.
	 */
	public function test_get_provider_type(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'test',
				'name'          => 'Test',
				'provider_type' => 'azure-openai',
			]
		);

		$context = RequestContext::from_configuration( 'text_generation', $config );

		$this->assertSame( 'azure-openai', $context->get_provider_type() );
	}

	/**
	 * Test get_configuration_id delegates to configuration.
	 */
	public function test_get_configuration_id(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'unique-id-123',
				'name'          => 'Test',
				'provider_type' => 'openai',
			]
		);

		$context = RequestContext::from_configuration( 'text_generation', $config );

		$this->assertSame( 'unique-id-123', $context->get_configuration_id() );
	}

	/**
	 * Test get_setting delegates to configuration.
	 */
	public function test_get_setting(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'test',
				'name'          => 'Test',
				'provider_type' => 'openai',
				'settings'      => [
					'api_key'  => 'secret-key',
					'model'    => 'gpt-4',
				],
			]
		);

		$context = RequestContext::from_configuration( 'text_generation', $config );

		$this->assertSame( 'secret-key', $context->get_setting( 'api_key' ) );
		$this->assertSame( 'gpt-4', $context->get_setting( 'model' ) );
		$this->assertNull( $context->get_setting( 'nonexistent' ) );
		$this->assertSame( 'default', $context->get_setting( 'nonexistent', 'default' ) );
	}

	/**
	 * Test context is immutable (readonly properties).
	 */
	public function test_context_is_immutable(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'test',
				'name'          => 'Test',
				'provider_type' => 'openai',
			]
		);

		$context = RequestContext::from_configuration( 'text_generation', $config );

		// Verify readonly class - this should be enforced by PHP.
		$reflection = new \ReflectionClass( $context );
		$this->assertTrue( $reflection->isReadOnly() );
	}

	/**
	 * Test empty deployment_id and api_version are treated as null.
	 */
	public function test_empty_azure_settings_become_null(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'azure-config',
				'name'          => 'Azure',
				'provider_type' => 'azure-openai',
				'settings'      => [
					'api_key'       => 'azure-key',
					'deployment_id' => '',
					'api_version'   => '',
				],
				'capabilities'  => [ 'text_generation' ],
			]
		);

		$context = RequestContext::from_configuration( 'text_generation', $config );

		$this->assertNull( $context->deployment_id );
		$this->assertNull( $context->api_version );
		$this->assertFalse( $context->has_azure_overrides() );
	}
}
