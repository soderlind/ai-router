/**
 * Vitest setup file.
 */

import { vi } from 'vitest';
import '@testing-library/jest-dom/vitest';

// Mock window.aiRouterAdmin config.
window.aiRouterAdmin = {
	restUrl: 'https://example.com/wp-json/ai-router/v1/',
	nonce: 'test-nonce-123',
	configurations: [],
	capabilityMap: {},
	defaultConfig: '',
	providerTypes: {
		openai: 'OpenAI',
		'azure-openai': 'Azure OpenAI',
	},
	capabilities: [
		{ slug: 'text_generation', label: 'Text Generation' },
		{ slug: 'chat_history', label: 'Chat History' },
		{ slug: 'image_generation', label: 'Image Generation' },
		{ slug: 'embedding_generation', label: 'Embedding Generation' },
		{ slug: 'text_to_speech_conversion', label: 'Text to Speech' },
		{ slug: 'speech_generation', label: 'Speech Generation' },
		{ slug: 'music_generation', label: 'Music Generation' },
		{ slug: 'video_generation', label: 'Video Generation' },
	],
};

// Mock console.error to reduce test noise.
vi.spyOn( console, 'error' ).mockImplementation( () => {} );
