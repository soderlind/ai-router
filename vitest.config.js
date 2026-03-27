import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig( {
	plugins: [ react() ],
	test: {
		globals: true,
		environment: 'jsdom',
		setupFiles: [ './tests/js/setup.js' ],
		include: [ 'tests/js/**/*.test.{js,jsx}' ],
		coverage: {
			provider: 'v8',
			reporter: [ 'text', 'html' ],
			include: [ 'src/js/**/*.{js,jsx}' ],
		},
		alias: {
			'@wordpress/element': resolve(
				__dirname,
				'tests/js/__mocks__/@wordpress/element.js'
			),
			'@wordpress/components': resolve(
				__dirname,
				'tests/js/__mocks__/@wordpress/components.jsx'
			),
			'@wordpress/i18n': resolve(
				__dirname,
				'tests/js/__mocks__/@wordpress/i18n.js'
			),
			'@wordpress/api-fetch': resolve(
				__dirname,
				'tests/js/__mocks__/@wordpress/api-fetch.js'
			),
			'@wordpress/connectors': resolve(
				__dirname,
				'tests/js/__mocks__/@wordpress/connectors.js'
			),
		},
	},
} );
