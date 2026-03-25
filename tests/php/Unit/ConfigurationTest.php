<?php
/**
 * Tests for Configuration DTO.
 *
 * @package AIRouter\Tests\Unit
 */

declare(strict_types=1);

namespace AIRouter\Tests\Unit;

use AIRouter\DTO\Configuration;
use AIRouter\Tests\TestCase;

/**
 * Configuration test class.
 */
class ConfigurationTest extends TestCase {

	/**
	 * Test configuration creation from array.
	 */
	public function test_from_array_creates_configuration(): void {
		$data = [
			'id'            => 'test-id-123',
			'name'          => 'Test Configuration',
			'provider_type' => 'openai',
			'settings'      => [ 'api_key' => 'sk-test123' ],
			'capabilities'  => [ 'text_generation', 'chat_history' ],
			'is_default'    => true,
		];

		$config = Configuration::from_array( $data );

		$this->assertSame( 'test-id-123', $config->get_id() );
		$this->assertSame( 'Test Configuration', $config->get_name() );
		$this->assertSame( 'openai', $config->get_provider_type() );
		$this->assertSame( 'sk-test123', $config->get_setting( 'api_key' ) );
		$this->assertTrue( $config->is_default() );
	}

	/**
	 * Test capabilities support check.
	 */
	public function test_supports_capability(): void {
		$config = Configuration::from_array(
			[
				'id'           => 'test',
				'name'         => 'Test',
				'provider_type' => 'openai',
				'capabilities' => [ 'text_generation', 'image_generation' ],
			]
		);

		$this->assertTrue( $config->supports_capability( 'text_generation' ) );
		$this->assertTrue( $config->supports_capability( 'image_generation' ) );
		$this->assertFalse( $config->supports_capability( 'embedding_generation' ) );
	}

	/**
	 * Test configuration validation with valid data.
	 */
	public function test_validate_returns_empty_for_valid_config(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'valid-config',
				'name'          => 'Valid Config',
				'provider_type' => 'openai',
				'capabilities'  => [ 'text_generation' ],
			]
		);

		$errors = $config->validate();

		$this->assertEmpty( $errors );
	}

	/**
	 * Test configuration validation with missing name.
	 */
	public function test_validate_returns_error_for_missing_name(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'invalid-config',
				'name'          => '',
				'provider_type' => 'openai',
			]
		);

		$errors = $config->validate();

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'name is required', $errors[0] );
	}

	/**
	 * Test configuration validation with invalid provider type.
	 */
	public function test_validate_returns_error_for_invalid_provider_type(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'invalid-config',
				'name'          => 'Test',
				'provider_type' => 'invalid-provider',
			]
		);

		$errors = $config->validate();

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'Invalid provider type', $errors[0] );
	}

	/**
	 * Test Azure OpenAI requires endpoint.
	 */
	public function test_validate_azure_requires_endpoint(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'azure-config',
				'name'          => 'Azure Config',
				'provider_type' => 'azure-openai',
				'settings'      => [ 'api_key' => 'key123' ],
			]
		);

		$errors = $config->validate();

		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( 'endpoint is required', $errors[0] );
	}

	/**
	 * Test configuration with method creates new instance.
	 */
	public function test_with_creates_new_instance(): void {
		$original = Configuration::from_array(
			[
				'id'            => 'original',
				'name'          => 'Original',
				'provider_type' => 'openai',
			]
		);

		$updated = $original->with( [ 'name' => 'Updated' ] );

		$this->assertSame( 'Original', $original->get_name() );
		$this->assertSame( 'Updated', $updated->get_name() );
		$this->assertSame( 'original', $updated->get_id() );
	}

	/**
	 * Test to_array returns all data.
	 */
	public function test_to_array_returns_all_data(): void {
		$data = [
			'id'            => 'test',
			'name'          => 'Test',
			'provider_type' => 'openai',
			'settings'      => [ 'api_key' => 'secret' ],
			'capabilities'  => [ 'text_generation' ],
			'is_default'    => false,
		];

		$config = Configuration::from_array( $data );
		$result = $config->to_array();

		$this->assertEquals( $data, $result );
	}

	/**
	 * Test jsonSerialize masks sensitive data.
	 */
	public function test_json_serialize_masks_sensitive_data(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'test',
				'name'          => 'Test',
				'provider_type' => 'openai',
				'settings'      => [ 'api_key' => 'sk-verylongsecretapikey123' ],
			]
		);

		$json = $config->jsonSerialize();

		$this->assertNotSame( 'sk-verylongsecretapikey123', $json['settings']['api_key'] );
		$this->assertStringStartsWith( 'sk-v', $json['settings']['api_key'] );
		$this->assertStringContainsString( '*', $json['settings']['api_key'] );
	}

	/**
	 * Test get_setting with default value.
	 */
	public function test_get_setting_returns_default(): void {
		$config = Configuration::from_array(
			[
				'id'            => 'test',
				'name'          => 'Test',
				'provider_type' => 'openai',
				'settings'      => [],
			]
		);

		$this->assertNull( $config->get_setting( 'nonexistent' ) );
		$this->assertSame( 'default', $config->get_setting( 'nonexistent', 'default' ) );
	}

	/**
	 * Test CAPABILITIES constant contains expected values.
	 */
	public function test_capabilities_constant(): void {
		$expected = [
			'text_generation',
			'chat_history',
			'image_generation',
			'embedding_generation',
			'text_to_speech_conversion',
			'speech_generation',
			'music_generation',
			'video_generation',
		];

		$this->assertEquals( $expected, Configuration::CAPABILITIES );
	}

	/**
	 * Test PROVIDER_TYPES constant.
	 */
	public function test_provider_types_constant(): void {
		$this->assertArrayHasKey( 'openai', Configuration::PROVIDER_TYPES );
		$this->assertArrayHasKey( 'azure-openai', Configuration::PROVIDER_TYPES );
	}
}
