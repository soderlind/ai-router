/**
 * Vitest setup file.
 */

import { vi } from 'vitest';
import '@testing-library/jest-dom/vitest';
import * as React from 'react';
import apiFetch from '@wordpress/api-fetch';
import * as wpI18n from '@wordpress/i18n';
import * as wpComponents from '@wordpress/components';

// Set up window.wp globals that connectors.js reads at module level.
window.wp = {
	apiFetch,
	element: {
		useState: React.useState,
		useEffect: React.useEffect,
		useCallback: React.useCallback,
		useMemo: React.useMemo,
		useRef: React.useRef,
		createElement: React.createElement,
	},
	i18n: wpI18n,
	components: {
		Button: wpComponents.Button,
		Modal: wpComponents.Modal,
		SelectControl: wpComponents.SelectControl,
		TextControl: wpComponents.TextControl,
		CheckboxControl: wpComponents.CheckboxControl,
		Notice: wpComponents.Notice,
		Spinner: wpComponents.Spinner,
		__experimentalVStack: wpComponents.__experimentalVStack,
		__experimentalHStack: wpComponents.__experimentalHStack,
		__experimentalConfirmDialog:
			wpComponents.__experimentalConfirmDialog,
	},
};

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
