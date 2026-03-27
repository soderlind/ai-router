/**
 * Mock for @wordpress/connectors.
 */

import { vi } from 'vitest';

export const __experimentalRegisterConnector = vi.fn();

export const __experimentalConnectorItem = ( {
	children,
	logo,
	name,
	description,
	actionArea,
} ) => {
	const el = require( 'react' ).createElement;
	return el(
		'div',
		{ 'data-testid': 'connector-item', 'data-name': name },
		actionArea,
		children
	);
};
