/**
 * Mock for @wordpress/element.
 */

import * as React from 'react';

export const createRoot = ( container ) => ( {
	render: ( element ) => {
		// Simple mock - just return element for testing.
	},
} );

export const useState = React.useState;
export const useEffect = React.useEffect;
export const useCallback = React.useCallback;
export const useMemo = React.useMemo;
export const useRef = React.useRef;
export const useContext = React.useContext;
export const createContext = React.createContext;
export const createElement = React.createElement;
export const Fragment = React.Fragment;
export const Component = React.Component;
export const cloneElement = React.cloneElement;
export const Children = React.Children;
export const isValidElement = React.isValidElement;

export default React;
