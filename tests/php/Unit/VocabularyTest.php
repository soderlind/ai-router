<?php
/**
 * Vocabulary tests.
 *
 * @package AIRouter\Tests
 */

declare(strict_types=1);

namespace AIRouter\Tests\Unit;

use AIRouter\Tests\TestCase;
use AIRouter\Vocabulary;

/**
 * Tests for the Vocabulary class.
 */
class VocabularyTest extends TestCase {

	/**
	 * Test capabilities returns all capability slugs.
	 */
	public function test_capabilities_returns_all_slugs(): void {
		$capabilities = Vocabulary::capabilities();

		$this->assertIsArray( $capabilities );
		$this->assertContains( 'text_generation', $capabilities );
		$this->assertContains( 'chat_history', $capabilities );
		$this->assertContains( 'image_generation', $capabilities );
		$this->assertContains( 'embedding_generation', $capabilities );
		$this->assertContains( 'text_to_speech_conversion', $capabilities );
		$this->assertContains( 'speech_generation', $capabilities );
		$this->assertContains( 'music_generation', $capabilities );
		$this->assertContains( 'video_generation', $capabilities );
		$this->assertCount( 8, $capabilities );
	}

	/**
	 * Test capabilities_with_labels returns slug and label pairs.
	 */
	public function test_capabilities_with_labels(): void {
		$capabilities = Vocabulary::capabilities_with_labels();

		$this->assertIsArray( $capabilities );
		$this->assertCount( 8, $capabilities );

		$first = $capabilities[0];
		$this->assertArrayHasKey( 'slug', $first );
		$this->assertArrayHasKey( 'label', $first );
		$this->assertSame( 'text_generation', $first['slug'] );
		$this->assertSame( 'Text Generation', $first['label'] );
	}

	/**
	 * Test get_capability_label returns known labels.
	 */
	public function test_get_capability_label_known(): void {
		$this->assertSame( 'Text Generation', Vocabulary::get_capability_label( 'text_generation' ) );
		$this->assertSame( 'Image Generation', Vocabulary::get_capability_label( 'image_generation' ) );
		$this->assertSame( 'Chat History', Vocabulary::get_capability_label( 'chat_history' ) );
		$this->assertSame( 'Text to Speech', Vocabulary::get_capability_label( 'text_to_speech_conversion' ) );
	}

	/**
	 * Test get_capability_label returns formatted label for unknown.
	 */
	public function test_get_capability_label_unknown(): void {
		$this->assertSame( 'Unknown Capability', Vocabulary::get_capability_label( 'unknown_capability' ) );
		$this->assertSame( 'Some New Thing', Vocabulary::get_capability_label( 'some_new_thing' ) );
	}

	/**
	 * Test is_valid_capability returns true for valid capabilities.
	 */
	public function test_is_valid_capability_valid(): void {
		$this->assertTrue( Vocabulary::is_valid_capability( 'text_generation' ) );
		$this->assertTrue( Vocabulary::is_valid_capability( 'image_generation' ) );
		$this->assertTrue( Vocabulary::is_valid_capability( 'chat_history' ) );
	}

	/**
	 * Test is_valid_capability returns false for invalid capabilities.
	 */
	public function test_is_valid_capability_invalid(): void {
		$this->assertFalse( Vocabulary::is_valid_capability( 'unknown_capability' ) );
		$this->assertFalse( Vocabulary::is_valid_capability( '' ) );
		$this->assertFalse( Vocabulary::is_valid_capability( 'text-generation' ) ); // Wrong format
	}

	/**
	 * Test provider_types returns all provider types.
	 */
	public function test_provider_types(): void {
		$types = Vocabulary::provider_types();

		$this->assertIsArray( $types );
		$this->assertArrayHasKey( 'openai', $types );
		$this->assertArrayHasKey( 'azure-openai', $types );
		$this->assertSame( 'OpenAI', $types['openai'] );
		$this->assertSame( 'Azure OpenAI', $types['azure-openai'] );
	}

	/**
	 * Test is_valid_provider returns true for valid providers.
	 */
	public function test_is_valid_provider_valid(): void {
		$this->assertTrue( Vocabulary::is_valid_provider( 'openai' ) );
		$this->assertTrue( Vocabulary::is_valid_provider( 'azure-openai' ) );
	}

	/**
	 * Test is_valid_provider returns false for invalid providers.
	 */
	public function test_is_valid_provider_invalid(): void {
		$this->assertFalse( Vocabulary::is_valid_provider( 'unknown' ) );
		$this->assertFalse( Vocabulary::is_valid_provider( 'azure_openai' ) ); // Underscore variant
		$this->assertFalse( Vocabulary::is_valid_provider( '' ) );
	}

	/**
	 * Test normalize_provider_type normalizes aliases.
	 */
	public function test_normalize_provider_type(): void {
		$this->assertSame( 'azure-openai', Vocabulary::normalize_provider_type( 'azure_openai' ) );
		$this->assertSame( 'openai', Vocabulary::normalize_provider_type( 'openai' ) );
		$this->assertSame( 'unknown', Vocabulary::normalize_provider_type( 'unknown' ) );
	}

	/**
	 * Test get_provider_class returns class names.
	 */
	public function test_get_provider_class(): void {
		$this->assertSame(
			'WordPress\\OpenAiAiProvider\\Provider\\OpenAiProvider',
			Vocabulary::get_provider_class( 'openai' )
		);
		$this->assertSame(
			'WordPress\\AzureOpenAiAiProvider\\Provider\\AzureOpenAiProvider',
			Vocabulary::get_provider_class( 'azure-openai' )
		);
		$this->assertNull( Vocabulary::get_provider_class( 'unknown' ) );
	}

	/**
	 * Test get_provider_id returns provider IDs.
	 */
	public function test_get_provider_id(): void {
		$this->assertSame( 'openai', Vocabulary::get_provider_id( 'openai' ) );
		$this->assertSame( 'azure_openai', Vocabulary::get_provider_id( 'azure-openai' ) );
		$this->assertSame( 'ollama', Vocabulary::get_provider_id( 'ollama' ) );
		$this->assertNull( Vocabulary::get_provider_id( 'unknown' ) );
	}

	/**
	 * Test provider_classes returns all provider class mappings.
	 */
	public function test_provider_classes(): void {
		$classes = Vocabulary::provider_classes();

		$this->assertIsArray( $classes );
		$this->assertArrayHasKey( 'openai', $classes );
		$this->assertArrayHasKey( 'azure-openai', $classes );
		$this->assertArrayHasKey( 'ollama', $classes );
	}

	/**
	 * Test CAPABILITIES constant matches capabilities() method.
	 */
	public function test_capabilities_constant_matches_method(): void {
		$this->assertSame( Vocabulary::CAPABILITIES, Vocabulary::capabilities() );
	}

	/**
	 * Test PROVIDER_TYPES constant matches provider_types() method.
	 */
	public function test_provider_types_constant_matches_method(): void {
		$this->assertSame( Vocabulary::PROVIDER_TYPES, Vocabulary::provider_types() );
	}

	/**
	 * Test PROVIDER_CLASSES constant matches provider_classes() method.
	 */
	public function test_provider_classes_constant_matches_method(): void {
		$this->assertSame( Vocabulary::PROVIDER_CLASSES, Vocabulary::provider_classes() );
	}
}
