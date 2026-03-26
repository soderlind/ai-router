/**
 * Mock for @wordpress/i18n.
 */

export const __ = ( text ) => text;
export const _x = ( text ) => text;
export const _n = ( single, plural, number ) =>
	number === 1 ? single : plural;
export const _nx = ( single, plural, number ) =>
	number === 1 ? single : plural;
export const sprintf = ( format, ...args ) => {
	let i = 0;
	return format.replace( /%[sd]/g, () => args[ i++ ] ?? '' );
};
export const setLocaleData = () => {};
export const getLocaleData = () => ( {} );
