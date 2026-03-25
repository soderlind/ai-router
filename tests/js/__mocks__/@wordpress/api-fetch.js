/**
 * Mock for @wordpress/api-fetch.
 */

import { vi } from 'vitest';

const apiFetch = vi.fn( async ( options ) => {
	// Default mock implementation - return empty response.
	return {};
} );

export default apiFetch;
