<?php
/**
 * ProviderDiscovery unit tests.
 *
 * @package AIRouter\Tests\Unit
 */

declare(strict_types=1);

namespace AIRouter\Tests\Unit;

use AIRouter\ProviderDiscovery;
use AIRouter\Tests\TestCase;
use Brain\Monkey\Functions;
use ReflectionMethod;

/**
 * Tests for ProviderDiscovery.
 */
class ProviderDiscoveryTest extends TestCase {

	/**
	 * Test discover_connector_settings returns empty when function doesn't exist.
	 */
	public function test_discover_connector_settings_returns_empty_when_function_missing(): void {
		// By default, get_registered_settings doesn't exist in test environment.
		$discovery = new ProviderDiscovery();

		$method = new ReflectionMethod( ProviderDiscovery::class, 'discover_connector_settings' );
		$method->setAccessible( true );

		$result = $method->invoke( $discovery, 'azure-ai-foundry' );

		$this->assertSame( [], $result );
	}

	/**
	 * Test discover_connector_settings extracts fields from registered settings.
	 */
	public function test_discover_connector_settings_extracts_provider_fields(): void {
		// Mock get_registered_settings.
		Functions\when( 'get_registered_settings' )->justReturn( [
			'connectors_ai_azure_ai_foundry_api_key'  => [
				'group'       => 'connectors',
				'type'        => 'string',
				'label'       => 'API Key',
				'description' => 'Azure AI Foundry API key.',
			],
			'connectors_ai_azure_ai_foundry_endpoint' => [
				'group'       => 'connectors',
				'type'        => 'string',
				'label'       => 'Endpoint URL',
				'description' => 'Azure AI resource URL.',
			],
			'some_other_option'                       => [
				'group' => 'general',
				'type'  => 'string',
				'label' => 'Other',
			],
		] );

		$discovery = new ProviderDiscovery();

		$method = new ReflectionMethod( ProviderDiscovery::class, 'discover_connector_settings' );
		$method->setAccessible( true );

		$result = $method->invoke( $discovery, 'azure-ai-foundry' );

		$this->assertCount( 2, $result );

		// Check first field (api_key).
		$this->assertSame( 'api_key', $result[0]['key'] );
		$this->assertSame( 'password', $result[0]['type'] ); // api_key gets password type.
		$this->assertSame( 'API Key', $result[0]['label'] );

		// Check second field (endpoint).
		$this->assertSame( 'endpoint', $result[1]['key'] );
		$this->assertSame( 'text', $result[1]['type'] );
		$this->assertSame( 'Endpoint URL', $result[1]['label'] );
	}

	/**
	 * Test discover_connector_settings ignores settings from other groups.
	 */
	public function test_discover_connector_settings_ignores_other_groups(): void {
		Functions\when( 'get_registered_settings' )->justReturn( [
			'connectors_ai_azure_ai_foundry_api_key' => [
				'group' => 'general', // Wrong group.
				'type'  => 'string',
				'label' => 'API Key',
			],
		] );

		$discovery = new ProviderDiscovery();

		$method = new ReflectionMethod( ProviderDiscovery::class, 'discover_connector_settings' );
		$method->setAccessible( true );

		$result = $method->invoke( $discovery, 'azure-ai-foundry' );

		$this->assertSame( [], $result );
	}

	/**
	 * Test discover_connector_settings ignores settings from other providers.
	 */
	public function test_discover_connector_settings_ignores_other_providers(): void {
		Functions\when( 'get_registered_settings' )->justReturn( [
			'connectors_ai_openai_api_key' => [
				'group' => 'connectors',
				'type'  => 'string',
				'label' => 'API Key',
			],
		] );

		$discovery = new ProviderDiscovery();

		$method = new ReflectionMethod( ProviderDiscovery::class, 'discover_connector_settings' );
		$method->setAccessible( true );

		$result = $method->invoke( $discovery, 'azure-ai-foundry' );

		$this->assertSame( [], $result );
	}

	/**
	 * Test discover_connector_settings handles boolean type.
	 */
	public function test_discover_connector_settings_handles_boolean_type(): void {
		Functions\when( 'get_registered_settings' )->justReturn( [
			'connectors_ai_test_provider_enabled' => [
				'group' => 'connectors',
				'type'  => 'boolean',
				'label' => 'Enabled',
			],
		] );

		$discovery = new ProviderDiscovery();

		$method = new ReflectionMethod( ProviderDiscovery::class, 'discover_connector_settings' );
		$method->setAccessible( true );

		$result = $method->invoke( $discovery, 'test-provider' );

		$this->assertCount( 1, $result );
		$this->assertSame( 'checkbox', $result[0]['type'] );
	}

	/**
	 * Test discover_connector_settings detects array type as multiselect.
	 */
	public function test_discover_connector_settings_detects_array_as_multiselect(): void {
		Functions\when( 'get_registered_settings' )->justReturn( [
			'connectors_ai_test_provider_allowed_models' => [
				'group' => 'connectors',
				'type'  => 'array',
				'label' => 'Allowed Models',
			],
		] );

		$discovery = new ProviderDiscovery();

		$method = new ReflectionMethod( ProviderDiscovery::class, 'discover_connector_settings' );
		$method->setAccessible( true );

		$result = $method->invoke( $discovery, 'test-provider' );

		$this->assertCount( 1, $result );
		$this->assertSame( 'multiselect', $result[0]['type'] );
	}

	/**
	 * Test discover_connector_settings generates label from key when missing.
	 */
	public function test_discover_connector_settings_generates_label_from_key(): void {
		Functions\when( 'get_registered_settings' )->justReturn( [
			'connectors_ai_test_provider_custom_field' => [
				'group' => 'connectors',
				'type'  => 'string',
				// No label provided.
			],
		] );

		$discovery = new ProviderDiscovery();

		$method = new ReflectionMethod( ProviderDiscovery::class, 'discover_connector_settings' );
		$method->setAccessible( true );

		$result = $method->invoke( $discovery, 'test-provider' );

		$this->assertCount( 1, $result );
		$this->assertSame( 'Custom Field', $result[0]['label'] );
	}

	/**
	 * Test discover_connector_settings skips internal and auto-detected settings.
	 */
	public function test_discover_connector_settings_skips_internal_and_auto_detected(): void {
		Functions\when( 'get_registered_settings' )->justReturn( [
			'connectors_ai_azure_ai_foundry_api_key'        => [
				'group' => 'connectors',
				'type'  => 'string',
				'label' => 'API Key',
			],
			'connectors_ai_azure_ai_foundry_status_api_key' => [
				'group' => 'connectors',
				'type'  => 'string',
				'label' => 'Azure AI Foundry Status API Key', // Internal sentinel.
			],
			'connectors_ai_azure_ai_foundry_capabilities'   => [
				'group' => 'connectors',
				'type'  => 'array',
				'label' => 'Capabilities', // Auto-detected by provider.
			],
			'connectors_ai_azure_ai_foundry_model_name'     => [
				'group' => 'connectors',
				'type'  => 'string',
				'label' => 'Model Name', // Auto-detected by provider.
			],
			'connectors_ai_azure_ai_foundry_endpoint'       => [
				'group' => 'connectors',
				'type'  => 'string',
				'label' => 'Endpoint URL',
			],
		] );

		$discovery = new ProviderDiscovery();

		$method = new ReflectionMethod( ProviderDiscovery::class, 'discover_connector_settings' );
		$method->setAccessible( true );

		$result = $method->invoke( $discovery, 'azure-ai-foundry' );

		// Should have 2 fields only (api_key, endpoint).
		// Filtered: status_api_key, capabilities, model_name.
		$this->assertCount( 2, $result );

		$keys = array_column( $result, 'key' );
		$this->assertContains( 'api_key', $keys );
		$this->assertContains( 'endpoint', $keys );
		$this->assertNotContains( 'status_api_key', $keys );
		$this->assertNotContains( 'capabilities', $keys );
		$this->assertNotContains( 'model_name', $keys );
	}
}
