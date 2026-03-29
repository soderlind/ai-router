import * as __WEBPACK_EXTERNAL_MODULE__wordpress_connectors_a8be66d0__ from "@wordpress/connectors";
/******/ var __webpack_modules__ = ({

/***/ "@wordpress/connectors"
/*!****************************************!*\
  !*** external "@wordpress/connectors" ***!
  \****************************************/
(module) {

module.exports = __WEBPACK_EXTERNAL_MODULE__wordpress_connectors_a8be66d0__;

/***/ }

/******/ });
/************************************************************************/
/******/ // The module cache
/******/ var __webpack_module_cache__ = {};
/******/ 
/******/ // The require function
/******/ function __webpack_require__(moduleId) {
/******/ 	// Check if module is in cache
/******/ 	var cachedModule = __webpack_module_cache__[moduleId];
/******/ 	if (cachedModule !== undefined) {
/******/ 		return cachedModule.exports;
/******/ 	}
/******/ 	// Create a new module (and put it into the cache)
/******/ 	var module = __webpack_module_cache__[moduleId] = {
/******/ 		// no module.id needed
/******/ 		// no module.loaded needed
/******/ 		exports: {}
/******/ 	};
/******/ 
/******/ 	// Execute the module function
/******/ 	if (!(moduleId in __webpack_modules__)) {
/******/ 		delete __webpack_module_cache__[moduleId];
/******/ 		var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 		e.code = 'MODULE_NOT_FOUND';
/******/ 		throw e;
/******/ 	}
/******/ 	__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 
/******/ 	// Return the exports of the module
/******/ 	return module.exports;
/******/ }
/******/ 
/************************************************************************/
/******/ /* webpack/runtime/make namespace object */
/******/ (() => {
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = (exports) => {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/ })();
/******/ 
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!******************************!*\
  !*** ./src/js/connectors.js ***!
  \******************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_connectors__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/connectors */ "@wordpress/connectors");
/**
 * AI Router — Connector for the WP 7 Connectors page.
 *
 * Registers AI Router as a connector on Settings → Connectors.
 * Uses the providers REST endpoint as the single source of truth
 * for provider types, capabilities, and field definitions.
 *
 * @package AIRouter
 */

// ── Script module import (real ES module) ────────────────────────


// ── Classic scripts — accessed via window globals ────────────────
const apiFetch = window.wp.apiFetch;
const {
	useState,
	useEffect,
	useCallback,
	useMemo,
	useRef,
	createElement,
} = window.wp.element;
const { __, sprintf } = window.wp.i18n;
const {
	Button,
	Icon,
	Modal,
	SelectControl,
	TextControl,
	CheckboxControl,
	Notice,
	Spinner,
	__experimentalVStack: VStack,
	__experimentalHStack: HStack,
	__experimentalConfirmDialog: ConfirmDialog,
} = window.wp.components;

const el = createElement;

// ── Sentinel value for unchanged secret fields ───────────────────
const SECRET_UNCHANGED = '__AI_ROUTER_UNCHANGED__';

// ── CSS tokens (inline — avoids separate stylesheet) ─────────────
const TOKENS = {
	radius: '6px',
	space: {
		xs: '4px',
		sm: '8px',
		md: '16px',
		lg: '24px',
	},
	color: {
		success: '#008a20',
		successBg: '#edfaef',
		muted: '#757575',
		border: '#c3c4c7',
		surface: '#f6f7f7',
		destructive: '#cc1818',
	},
	fontSize: {
		xs: '11px',
		sm: '12px',
		base: '13px',
	},
};

// ── API adapter ──────────────────────────────────────────────────

/**
 * Centralized REST API adapter with response normalization and error shaping.
 */
const api = {
	async fetchConfigurations() {
		return apiFetch( { path: 'ai-router/v1/configurations' } );
	},
	async fetchCapabilityMap() {
		return apiFetch( { path: 'ai-router/v1/capability-map' } );
	},
	async fetchDefault() {
		const res = await apiFetch( { path: 'ai-router/v1/default' } );
		return res?.default_id || '';
	},
	async fetchProviders() {
		return apiFetch( { path: 'ai-router/v1/providers' } );
	},
	async saveConfiguration( id, data ) {
		const path = id
			? `ai-router/v1/configurations/${ id }`
			: 'ai-router/v1/configurations';
		return apiFetch( { path, method: id ? 'PUT' : 'POST', data } );
	},
	async deleteConfiguration( id ) {
		return apiFetch( {
			path: `ai-router/v1/configurations/${ id }`,
			method: 'DELETE',
		} );
	},
	async saveCapabilityMap( mapping ) {
		return apiFetch( {
			path: 'ai-router/v1/capability-map',
			method: 'POST',
			data: mapping,
		} );
	},
};

/**
 * Extract a user-facing message from an API error.
 *
 * @param {Error|Object} err
 * @param {string}       fallback
 * @return {string}
 */
function errorMessage( err, fallback ) {
	return err?.message || fallback;
}

// ── Provider metadata helpers ────────────────────────────────────

/**
 * Normalize a provider ID to its canonical form.
 *
 * The backend stores provider_type with hyphens (azure-openai) but
 * the providers endpoint may return IDs with underscores (azure_openai).
 * This function ensures consistent lookup regardless of format.
 *
 * @param {string} id Provider ID.
 * @return {string} Canonical ID.
 */
function normalizeProviderId( id ) {
	return ( id || '' ).replace( /-/g, '_' );
}

/**
 * Get the display label for a provider type.
 *
 * @param {string} providerType Provider type slug.
 * @param {Object} providers    Providers map keyed by normalized ID.
 * @return {string}
 */
function getProviderLabel( providerType, providers ) {
	const norm = normalizeProviderId( providerType );
	return providers[ norm ]?.name || providerType;
}

/**
 * Get display name for a configuration: "ProviderLabel: ConfigName".
 *
 * @param {Object} config    Configuration object.
 * @param {Object} providers Providers map.
 * @return {string}
 */
function getConfigDisplayName( config, providers ) {
	return `${ getProviderLabel( config.provider_type, providers ) }: ${ config.name }`;
}

/**
 * Get capabilities supported by a provider type.
 *
 * @param {string} providerType Provider type slug.
 * @param {Object} providers    Providers map.
 * @return {string[]}
 */
function getProviderCapabilities( providerType, providers ) {
	const norm = normalizeProviderId( providerType );
	return providers[ norm ]?.capabilities || [];
}

/**
 * Get settings fields for a provider type.
 *
 * @param {string} providerType Provider type slug.
 * @param {Object} providers    Providers map.
 * @return {Array}
 */
function getProviderFields( providerType, providers ) {
	const norm = normalizeProviderId( providerType );
	return providers[ norm ]?.fields || [
		{ key: 'api_key', label: __( 'API Key', 'ai-router' ), type: 'password' },
	];
}

/**
 * Check whether a field is a secret field.
 *
 * @param {Object} field Field definition.
 * @return {boolean}
 */
function isSecretField( field ) {
	return field.type === 'password' || /key|token|secret/i.test( field.key );
}

// ── Data hook ────────────────────────────────────────────────────

/**
 * Hook: load all AI Router data from REST API.
 *
 * Returns loading/error states, provider metadata, configurations,
 * capability map, and default config ID along with setters and refresh.
 */
function useAIRouterData() {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState( null );
	const [ configurations, setConfigurations ] = useState( [] );
	const [ capabilityMap, setCapabilityMap ] = useState( {} );
	const [ defaultConfig, setDefaultConfig ] = useState( '' );
	const [ providers, setProviders ] = useState( {} );
	const [ capabilities, setCapabilities ] = useState( [] );

	const loadData = useCallback( async () => {
		setIsLoading( true );
		setLoadError( null );
		try {
			const [ configs, mapping, defaultId, providerData ] =
				await Promise.all( [
					api.fetchConfigurations(),
					api.fetchCapabilityMap(),
					api.fetchDefault(),
					api.fetchProviders(),
				] );
			setConfigurations( configs );
			setCapabilityMap( mapping );
			setDefaultConfig( defaultId );

			// Build a normalized providers map keyed by normalized ID.
			const pMap = {};
			if ( providerData?.providers ) {
				Object.values( providerData.providers ).forEach( ( p ) => {
					pMap[ normalizeProviderId( p.id ) ] = p;
				} );
			}
			setProviders( pMap );
			setCapabilities( providerData?.capabilities || [] );
		} catch ( e ) {
			setLoadError(
				errorMessage(
					e,
					__( 'Failed to load AI Router data.', 'ai-router' )
				)
			);
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		loadData();
	}, [ loadData ] );

	return {
		isLoading,
		loadError,
		configurations,
		capabilityMap,
		defaultConfig,
		providers,
		capabilities,
		setConfigurations,
		setCapabilityMap,
		setDefaultConfig,
		refresh: loadData,
	};
}

// ── Notice manager ───────────────────────────────────────────────

/**
 * Hook: manage a notice queue.
 *
 * @return {Object} { notice, showNotice, clearNotice }
 */
function useNotice() {
	const [ notice, setNotice ] = useState( null );
	const timerRef = useRef( null );

	const clearNotice = useCallback( () => {
		setNotice( null );
		if ( timerRef.current ) {
			clearTimeout( timerRef.current );
			timerRef.current = null;
		}
	}, [] );

	const showNotice = useCallback(
		( message, status = 'info', autoDismiss = 5000 ) => {
			if ( timerRef.current ) {
				clearTimeout( timerRef.current );
			}
			setNotice( { message, status } );
			if ( autoDismiss > 0 ) {
				timerRef.current = setTimeout(
					() => setNotice( null ),
					autoDismiss
				);
			}
		},
		[]
	);

	return { notice, showNotice, clearNotice };
}

// ── Status Chip ──────────────────────────────────────────────────

function StatusChip( { label, variant } ) {
	const styles = {
		assigned: {
			background: TOKENS.color.successBg,
			color: '#0a5c1a',
		},
		default: {
			background: '#e7f1fa',
			color: '#135e96',
		},
		empty: {
			background: TOKENS.color.surface,
			color: TOKENS.color.muted,
		},
	};
	const style = {
		...( styles[ variant ] || styles.empty ),
		borderRadius: '10px',
		padding: '0 10px',
		fontSize: TOKENS.fontSize.xs,
		fontWeight: 500,
		lineHeight: '20px',
		height: '20px',
		boxSizing: 'border-box',
		display: 'inline-flex',
		alignItems: 'center',
		justifyContent: 'center',
		whiteSpace: 'nowrap',
		textTransform: 'none',
		letterSpacing: '0.01em',
	};
	return el( 'span', { style, 'aria-label': label }, label );
}

// ── Capability Chip (green with checkmark) ───────────────────────

function CapabilityChip( { label, active } ) {
	const style = active
		? {
				background: TOKENS.color.successBg,
				color: '#0a5c1a',
				border: '1px solid #b8e6c8',
		  }
		: {
				background: TOKENS.color.surface,
				color: TOKENS.color.muted,
				border: `1px solid ${ TOKENS.color.border }`,
		  };
	return el(
		'span',
		{
			style: {
				...style,
				borderRadius: '12px',
				padding: '2px 10px',
				fontSize: TOKENS.fontSize.sm,
				fontWeight: 500,
				lineHeight: '20px',
				display: 'inline-flex',
				alignItems: 'center',
				gap: '4px',
				whiteSpace: 'nowrap',
			},
		},
		label,
		active && el( 'span', { style: { fontSize: '11px' } }, ' \u2713' )
	);
}

// ── Provider First Workflow Banner ───────────────────────────────

function WorkflowBanner() {
	return el(
		'div',
		{
			style: {
				background: '#f0f6ff',
				border: '1px solid #c5d9f0',
				borderRadius: TOKENS.radius,
				padding: `${ TOKENS.space.md } ${ TOKENS.space.lg }`,
				marginBottom: TOKENS.space.lg,
				display: 'flex',
				alignItems: 'flex-start',
				gap: TOKENS.space.sm,
			},
		},
		el(
			'span',
			{
				style: {
					color: '#2271b1',
					fontSize: '20px',
					lineHeight: '1',
					flexShrink: 0,
				},
			},
			'\u24D8' // circled info
		),
		el(
			'div',
			null,
			el(
				'strong',
				{ style: { display: 'block', marginBottom: '4px' } },
				__( 'Provider First Workflow', 'ai-router' )
			),
			el(
				'span',
				{ style: { fontSize: TOKENS.fontSize.base, color: '#1e1e1e' } },
				el( 'strong', { style: { color: '#2271b1' } }, __( 'Step 1:', 'ai-router' ) ),
				' ',
				__( 'Configure your AI providers with credentials and settings below.', 'ai-router' ),
				' ',
				el( 'strong', { style: { color: '#2271b1' } }, __( 'Step 2:', 'ai-router' ) ),
				' ',
				__( 'Map each capability to a configuration in the Capability Routing section.', 'ai-router' )
			)
		)
	);
}

// ── Configuration Form ───────────────────────────────────────────

function ConfigurationForm( { config, providers, capabilities, onSave, onCancel } ) {
	const isEditing = !! config?.id;
	const initialProvider = config?.provider_type || 'openai';

	// Track original settings to detect which secrets were changed.
	const originalSettings = useRef( config?.settings || {} );

	const [ formData, setFormData ] = useState( () => {
		// When editing, use the config's own stored capabilities;
		// when creating, derive from provider metadata.
		const caps = isEditing && config.capabilities?.length
			? config.capabilities
			: getProviderCapabilities( initialProvider, providers );
		// For editing: initialize secret fields with sentinel value.
		const settings = {};
		if ( isEditing ) {
			const fields = getProviderFields( initialProvider, providers );
			fields.forEach( ( f ) => {
				if ( isSecretField( f ) ) {
					settings[ f.key ] = SECRET_UNCHANGED;
				} else {
					settings[ f.key ] = config?.settings?.[ f.key ] || '';
				}
			} );
		}
		return {
			name: config?.name || '',
			provider_type: normalizeProviderId( initialProvider ),
			settings: isEditing ? settings : {},
			capabilities: caps,
			is_default: config?.is_default || false,
		};
	} );

	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	const handleProviderChange = ( newProvider ) => {
		setFormData( ( p ) => ( {
			...p,
			provider_type: newProvider,
			settings: {},
			capabilities: getProviderCapabilities( newProvider, providers ),
		} ) );
	};

	const handleSubmit = async () => {
		if ( ! formData.name.trim() ) {
			setError( __( 'Configuration name is required.', 'ai-router' ) );
			return;
		}
		setSaving( true );
		setError( null );
		try {
			// Build settings payload: omit secrets that haven't been changed.
			const settingsPayload = {};
			const fields = getProviderFields(
				formData.provider_type,
				providers
			);
			fields.forEach( ( f ) => {
				const val = formData.settings[ f.key ];
				if ( isSecretField( f ) && val === SECRET_UNCHANGED ) {
					// Don't send unchanged secrets.
					return;
				}
				settingsPayload[ f.key ] = val || '';
			} );

			const result = await api.saveConfiguration( config?.id, {
				...formData,
				settings: settingsPayload,
			} );
			onSave( result );
		} catch ( err ) {
			setError(
				errorMessage(
					err,
					__( 'Failed to save configuration.', 'ai-router' )
				)
			);
		} finally {
			setSaving( false );
		}
	};

	const settingsFields = getProviderFields(
		formData.provider_type,
		providers
	);

	// Build provider select options from the dynamically loaded providers map.
	const providerOptions = useMemo(
		() =>
			Object.values( providers ).map( ( p ) => ( {
				value: normalizeProviderId( p.id ),
				label: p.name,
			} ) ),
		[ providers ]
	);

	const formTitle = isEditing
		? __( 'Edit Configuration', 'ai-router' )
		: __( 'New Configuration', 'ai-router' );

	return el(
		Modal,
		{
			title: formTitle,
			onRequestClose: onCancel,
			size: 'medium',
			className: 'ai-router-form-modal',
		},
		error &&
			el(
				Notice,
				{
					status: 'error',
					isDismissible: true,
					onDismiss: () => setError( null ),
				},
				error
			),
		el(
			VStack,
			{ spacing: 3 },
			el( TextControl, {
				label: __( 'Configuration Name', 'ai-router' ),
				value: formData.name,
				onChange: ( v ) =>
					setFormData( ( p ) => ( { ...p, name: v } ) ),
				required: true,
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
			} ),
			el( SelectControl, {
				label: __( 'Provider', 'ai-router' ),
				value: formData.provider_type,
				onChange: handleProviderChange,
				options: providerOptions,
				disabled: isEditing,
				help: isEditing
					? __(
							'Provider cannot be changed after creation.',
							'ai-router'
					  )
					: undefined,
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
			} ),
			// Provider-specific fields.
			...settingsFields.map( ( field ) => {
				const isSecret = isSecretField( field );
				const currentVal = formData.settings[ field.key ] || '';
				const isMasked = isSecret && currentVal === SECRET_UNCHANGED;
				return el( TextControl, {
					key: field.key,
					label: field.label,
					type: isSecret ? 'password' : field.type || 'text',
					placeholder: isMasked
						? __( '(unchanged)', 'ai-router' )
						: field.placeholder || '',
					value: isMasked ? '' : currentVal,
					onChange: ( v ) =>
						setFormData( ( p ) => ( {
							...p,
							settings: { ...p.settings, [ field.key ]: v },
						} ) ),
					onFocus: () => {
						// Clear sentinel on first focus so user types fresh.
						if ( isMasked ) {
							setFormData( ( p ) => ( {
								...p,
								settings: {
									...p.settings,
									[ field.key ]: '',
								},
							} ) );
						}
					},
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} );
			} ),
			// Capabilities (read-only, derived from provider).
			el(
				'fieldset',
				null,
				el(
					'legend',
					{
						style: {
							fontWeight: 500,
							fontSize: TOKENS.fontSize.base,
							marginBottom: TOKENS.space.xs,
						},
					},
					__( 'Supported Capabilities', 'ai-router' )
				),
				el(
					'p',
					{
						className: 'description',
						style: {
							marginTop: 0,
							marginBottom: TOKENS.space.sm,
						},
					},
					__(
						'Determined by provider. Assign to this configuration in Capability Routing.',
						'ai-router'
					)
				),
				formData.capabilities.length > 0
					? el(
							'div',
							{
								style: {
									display: 'flex',
									flexWrap: 'wrap',
									gap: '6px',
								},
							},
							...formData.capabilities.map( ( slug ) => {
								const cap = capabilities.find(
									( c ) => c.slug === slug
								);
								return el( StatusChip, {
									key: slug,
									label: cap?.label || slug,
									variant: 'empty',
								} );
							} )
					  )
					: el(
							'p',
							{
								style: {
									fontStyle: 'italic',
									color: TOKENS.color.muted,
								},
							},
							__(
								'No capabilities for this provider.',
								'ai-router'
							)
					  )
			),
			el( CheckboxControl, {
				label: __( 'Set as default fallback', 'ai-router' ),
				help: __(
					'Used when no specific capability routing applies.',
					'ai-router'
				),
				checked: formData.is_default,
				onChange: ( v ) =>
					setFormData( ( p ) => ( { ...p, is_default: v } ) ),
				__nextHasNoMarginBottom: true,
			} ),
			el(
				HStack,
				{ spacing: 2, justify: 'flex-end' },
				el(
					Button,
					{
						variant: 'tertiary',
						onClick: onCancel,
						disabled: saving,
					},
					__( 'Cancel', 'ai-router' )
				),
				el(
					Button,
					{
						variant: 'primary',
						onClick: handleSubmit,
						isBusy: saving,
						disabled: saving,
						'aria-disabled': saving,
					},
					isEditing
						? __( 'Save Changes', 'ai-router' )
						: __( 'Create Configuration', 'ai-router' )
				)
			)
		)
	);
}

// ── Configuration List Row ───────────────────────────────────────

function ConfigRow( {
	config,
	isDefault,
	mappedCapabilities,
	providers,
	capabilityLabels,
	onEdit,
	onDelete,
} ) {
	const providerLabel = getProviderLabel( config.provider_type, providers );
	const mappedText =
		mappedCapabilities.length > 0
			? sprintf(
					/* translators: %d: number of mapped capabilities */
					__( '%d capability mapped', 'ai-router' ),
					mappedCapabilities.length
			  )
			: '';
	const metaParts = [ providerLabel, mappedText ]
		.filter( Boolean )
		.join( ' · ' );

	return el(
		'div',
		{
			style: {
				display: 'grid',
				gridTemplateColumns: '1fr auto auto',
				alignItems: 'center',
				gap: TOKENS.space.md,
				padding: `${ TOKENS.space.sm } 0`,
				borderBottom: `1px solid ${ TOKENS.color.border }`,
			},
		},
		// Column 1: name + metadata stacked.
		el(
			'div',
			{ style: { minWidth: 0 } },
			el(
				'div',
				{
					style: {
						display: 'flex',
						alignItems: 'center',
						gap: TOKENS.space.sm,
						flexWrap: 'wrap',
					},
				},
				el(
					'strong',
					{
						style: {
							fontSize: '14px',
							whiteSpace: 'nowrap',
							overflow: 'hidden',
							textOverflow: 'ellipsis',
						},
					},
					config.name
				),
				isDefault &&
					el( StatusChip, {
						label: __( 'Default', 'ai-router' ),
						variant: 'default',
					} )
			),
			el(
				'div',
				{
					style: {
						color: TOKENS.color.muted,
						fontSize: TOKENS.fontSize.sm,
						marginTop: '2px',
						whiteSpace: 'nowrap',
						overflow: 'hidden',
						textOverflow: 'ellipsis',
					},
				},
				metaParts
			)
		),
		// Column 2: Edit.
		el(
			Button,
			{
				variant: 'tertiary',
				size: 'compact',
				onClick: () => onEdit( config ),
				'aria-label': sprintf(
					/* translators: %s: configuration name */
					__( 'Edit %s', 'ai-router' ),
					config.name
				),
			},
			__( 'Edit', 'ai-router' )
		),
		// Column 3: Delete.
		el(
			Button,
			{
				variant: 'tertiary',
				size: 'compact',
				isDestructive: true,
				onClick: () => onDelete( config ),
				'aria-label': sprintf(
					/* translators: %s: configuration name */
					__( 'Delete %s', 'ai-router' ),
					config.name
				),
			},
			__( 'Delete', 'ai-router' )
		)
	);
}

// ── Capability Routing ───────────────────────────────────────────

function CapabilityRouting( {
	configurations,
	capabilityMap,
	capabilities,
	providers,
	onUpdate,
	showNotice,
} ) {
	const [ localMap, setLocalMap ] = useState( capabilityMap );
	const [ saving, setSaving ] = useState( false );
	const [ changed, setChanged ] = useState( false );

	useEffect( () => {
		setLocalMap( capabilityMap );
		setChanged( false );
	}, [ capabilityMap ] );

	const handleSave = async () => {
		setSaving( true );
		try {
			const result = await api.saveCapabilityMap( localMap );
			onUpdate( result );
			setChanged( false );
			showNotice(
				__( 'Capability routing saved.', 'ai-router' ),
				'success'
			);
		} catch ( e ) {
			showNotice(
				errorMessage(
					e,
					__( 'Failed to save routing.', 'ai-router' )
				),
				'error',
				0
			);
		} finally {
			setSaving( false );
		}
	};

	const getConfigsForCapability = ( capSlug ) =>
		configurations.filter( ( c ) =>
			( c.capabilities || [] ).includes( capSlug )
		);

	const assignedCount = Object.keys( localMap ).filter(
		( k ) => localMap[ k ]
	).length;
	const totalCount = capabilities.length;

	return el(
		'section',
		{
			'aria-labelledby': 'ai-router-routing-heading',
			style: { marginTop: TOKENS.space.md },
		},
		el(
			HStack,
			{
				justify: 'space-between',
				alignment: 'center',
				style: { marginBottom: TOKENS.space.sm },
			},
			el(
				HStack,
				{ spacing: 2, alignment: 'center' },
				el(
					'h4',
					{
						id: 'ai-router-routing-heading',
						style: { margin: 0, fontSize: '14px', fontWeight: 600 },
					},
					__( 'Capability Routing', 'ai-router' )
				),
				el( StatusChip, {
					label: sprintf(
						/* translators: 1: assigned count, 2: total count */
						__( '%1$d / %2$d assigned', 'ai-router' ),
						assignedCount,
						totalCount
					),
					variant: assignedCount === totalCount ? 'assigned' : 'empty',
				} )
			),
			changed &&
				el(
					Button,
					{
						variant: 'primary',
						size: 'compact',
						onClick: handleSave,
						isBusy: saving,
						disabled: saving,
					},
					__( 'Save Routing', 'ai-router' )
				)
		),
		el(
			'p',
			{
				className: 'description',
				style: { marginTop: 0, marginBottom: TOKENS.space.md },
			},
			__(
				'Assign each AI capability to a configuration. Priority: explicit assignment → default (if supports capability) → first available match.',
				'ai-router'
			)
		),
		el(
			VStack,
			{ spacing: 2 },
			...capabilities.map( ( cap ) => {
				const available = getConfigsForCapability( cap.slug );
				const isAssigned = !! localMap[ cap.slug ];
				const assignedConfig = isAssigned
					? configurations.find(
							( c ) => c.id === localMap[ cap.slug ]
					  )
					: null;

				return el( SelectControl, {
					key: cap.slug,
					label: el(
						'span',
						{
							style: {
								display: 'flex',
								alignItems: 'baseline',
								gap: TOKENS.space.sm,
							},
						},
						el(
							'span',
							{
								style: {
									color: undefined,
								},
							},
							cap.label
						),
						isAssigned &&
							assignedConfig &&
							el(
								'span',
								{
									style: {
										fontSize: TOKENS.fontSize.sm,
										fontWeight: 400,
										color: TOKENS.color.muted,
										textTransform: 'none',
									},
								},
								'→ ',
								getConfigDisplayName(
									assignedConfig,
									providers
								)
							)
					),
					value: localMap[ cap.slug ] || '',
					onChange: ( v ) => {
						setLocalMap( ( p ) => {
							const updated = { ...p };
							if ( v ) {
								updated[ cap.slug ] = v;
							} else {
								delete updated[ cap.slug ];
							}
							return updated;
						} );
						setChanged( true );
					},
					options: [
						{
							value: '',
							label: __( '— Not assigned —', 'ai-router' ),
						},
						...available.map( ( c ) => ( {
							value: c.id,
							label: getConfigDisplayName( c, providers ),
						} ) ),
					],
					disabled: available.length === 0,
					help:
						available.length === 0
							? __(
									'No configurations support this capability.',
									'ai-router'
							  )
							: '',
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} );
			} )
		)
	);
}

// ── Main Connector Component ─────────────────────────────────────

function AIRouterConnector( { slug, name, description, logo } ) {
	const {
		isLoading,
		loadError,
		configurations,
		capabilityMap,
		defaultConfig,
		providers,
		capabilities,
		setConfigurations,
		setCapabilityMap,
		setDefaultConfig,
		refresh,
	} = useAIRouterData();

	const { notice, showNotice, clearNotice } = useNotice();

	const [ isExpanded, setIsExpanded ] = useState( false );
	const [ editingConfig, setEditingConfig ] = useState( null );
	const [ creatingConfig, setCreatingConfig ] = useState( false );
	const [ confirmDelete, setConfirmDelete ] = useState( null );

	// Derived state.
	const configCount = configurations.length;
	const mappedCount = Object.keys( capabilityMap ).length;
	const totalCapabilities = capabilities.length;
	const isConfigured = configCount > 0;

	// Capability label lookup helper.
	const capabilityLabels = useMemo( () => {
		const map = {};
		capabilities.forEach( ( c ) => {
			map[ c.slug ] = c.label;
		} );
		return map;
	}, [ capabilities ] );

	// ── Loading state ────────────────────────────────────────────
	if ( isLoading ) {
		return el( _wordpress_connectors__WEBPACK_IMPORTED_MODULE_0__.__experimentalConnectorItem, {
			logo: logo || el( AIRouterIcon ),
			name,
			description,
			actionArea: el( Spinner ),
		} );
	}

	// ── Handlers ─────────────────────────────────────────────────
	const handleSaveConfig = ( saved ) => {
		setConfigurations( ( prev ) => {
			const idx = prev.findIndex( ( c ) => c.id === saved.id );
			if ( idx >= 0 ) {
				const updated = [ ...prev ];
				updated[ idx ] = saved;
				return updated;
			}
			return [ ...prev, saved ];
		} );
		if ( saved.is_default ) {
			setDefaultConfig( saved.id );
		}
		setEditingConfig( null );
		setCreatingConfig( false );
		showNotice(
			editingConfig
				? __( 'Configuration updated.', 'ai-router' )
				: __( 'Configuration created.', 'ai-router' ),
			'success'
		);
	};

	const executeDelete = async ( config ) => {
		try {
			await api.deleteConfiguration( config.id );
			setConfigurations( ( prev ) =>
				prev.filter( ( c ) => c.id !== config.id )
			);
			if ( defaultConfig === config.id ) {
				setDefaultConfig( '' );
			}
			setCapabilityMap( ( prev ) => {
				const updated = { ...prev };
				Object.keys( updated ).forEach( ( k ) => {
					if ( updated[ k ] === config.id ) {
						delete updated[ k ];
					}
				} );
				return updated;
			} );
			showNotice(
				sprintf(
					/* translators: %s: configuration name */
					__( '"%s" deleted.', 'ai-router' ),
					config.name
				),
				'success'
			);
		} catch ( e ) {
			showNotice(
				errorMessage(
					e,
					__( 'Failed to delete configuration.', 'ai-router' )
				),
				'error',
				0
			);
		}
	};

	const handleDeleteConfig = ( config ) => {
		setConfirmDelete( config );
	};

	// ── Action button ────────────────────────────────────────────
	const buttonLabel = isConfigured
		? __( 'Manage', 'ai-router' )
		: __( 'Set Up', 'ai-router' );

	const actionButton = el(
		Button,
		{
			variant: isConfigured ? 'tertiary' : 'secondary',
			size: isConfigured ? undefined : 'compact',
			onClick: () => setIsExpanded( ! isExpanded ),
			'aria-expanded': isExpanded,
		},
		buttonLabel
	);

	// ── Status summary ───────────────────────────────────────────
	const statusText = isConfigured
		? sprintf(
				/* translators: 1: config count, 2: mapped count */
				__( '%1$d configs, %2$d mapped', 'ai-router' ),
				configCount,
				mappedCount
		  )
		: null;

	// ── Panel content ────────────────────────────────────────────
	let panelContent = null;

	// Modal for create/edit (renders as overlay, panel stays visible).
	const formModal = ( creatingConfig || editingConfig ) &&
		el( ConfigurationForm, {
			config: editingConfig,
			providers,
			capabilities,
			onSave: handleSaveConfig,
			onCancel: () => {
				setEditingConfig( null );
				setCreatingConfig( false );
			},
		} );

	if ( isExpanded ) {
		// Global notice bar.
		const noticeBar =
			notice &&
			el(
				Notice,
				{
					status: notice.status,
					isDismissible: true,
					onDismiss: clearNotice,
					politeness: 'assertive',
				},
				notice.message
			);

		// Load error state.
		const errorBar =
			loadError &&
			el(
				Notice,
				{ status: 'error', isDismissible: false },
				loadError,
				' ',
				el(
					Button,
					{ variant: 'link', onClick: refresh },
					__( 'Retry', 'ai-router' )
				)
			);

		// Delete confirmation dialog.
		const deleteDialog =
			confirmDelete &&
			( ConfirmDialog
				? el( ConfirmDialog, {
						isOpen: true,
						onConfirm: () => {
							executeDelete( confirmDelete );
							setConfirmDelete( null );
						},
						onCancel: () => setConfirmDelete( null ),
						confirmButtonText: __( 'Delete', 'ai-router' ),
						cancelButtonText: __( 'Cancel', 'ai-router' ),
						size: 'small',
				  },
				  sprintf(
						/* translators: %s: configuration name */
						__( 'Delete "%s"? This cannot be undone.', 'ai-router' ),
						confirmDelete.name
				  ) )
				: // Fallback if ConfirmDialog is unavailable.
				  ( () => {
						if (
							window.confirm(
								sprintf(
									__( 'Delete "%s"? This cannot be undone.', 'ai-router' ),
									confirmDelete.name
								)
							)
						) {
							executeDelete( confirmDelete );
						}
						setConfirmDelete( null );
						return null;
				  } )() );

		if ( creatingConfig || editingConfig ) {
			// Show panel content behind the modal.
		}

		panelContent = el(
			'div',
			null,
			formModal,
			el(
				VStack,
				{ spacing: 4, style: { padding: `${ TOKENS.space.md } 0` } },
				noticeBar,
				errorBar,
				deleteDialog,

				// ── Section: Configurations ──────────────────────
				el(
					'section',
					{ 'aria-labelledby': 'ai-router-configs-heading' },
					el(
						HStack,
						{
							justify: 'space-between',
							alignment: 'center',
							style: { marginBottom: TOKENS.space.sm },
						},
						el(
							HStack,
							{ spacing: 2, alignment: 'center' },
							el(
								'h4',
								{
									id: 'ai-router-configs-heading',
									style: {
										margin: 0,
										fontSize: '14px',
										fontWeight: 600,
									},
								},
								__( 'Configurations', 'ai-router' )
							),
							el( StatusChip, {
								label: String( configCount ),
								variant: configCount > 0 ? 'assigned' : 'empty',
							} )
						),
						el(
							Button,
							{
								variant: 'primary',
								size: 'compact',
								onClick: () => setCreatingConfig( true ),
							},
							'+ ',
							__( 'Add', 'ai-router' )
						)
					),
					// Config list or empty state.
					configurations.length === 0
						? el(
								'div',
								{
									style: {
										textAlign: 'center',
										padding: `${ TOKENS.space.lg } ${ TOKENS.space.md }`,
										color: TOKENS.color.muted,
										background: TOKENS.color.surface,
										borderRadius: TOKENS.radius,
									},
								},
								el(
									'p',
									{
										style: {
											margin: `0 0 ${ TOKENS.space.sm }`,
											fontSize: '14px',
										},
									},
									__(
										'No configurations yet.',
										'ai-router'
									)
								),
								el(
									'p',
									{
										style: {
											margin: 0,
											fontSize: TOKENS.fontSize.sm,
										},
									},
									__(
										'Add a provider configuration to start routing AI requests.',
										'ai-router'
									)
								)
						  )
						: el(
								'div',
								{
									role: 'list',
									'aria-label': __(
										'Provider configurations',
										'ai-router'
									),
								},
								...configurations.map( ( config ) => {
									const mapped =
										Object.entries( capabilityMap )
											.filter(
												( [ , cid ] ) =>
													cid === config.id
											)
											.map( ( [ cap ] ) => cap );
									return el(
										'div',
										{ key: config.id, role: 'listitem' },
										el( ConfigRow, {
											config,
											isDefault:
												defaultConfig === config.id,
											mappedCapabilities: mapped,
											providers,
											capabilityLabels,
											onEdit: setEditingConfig,
											onDelete: handleDeleteConfig,
										} )
									);
								} )
						  )
				),

				// ── Section: Capability Routing ──────────────────
				configurations.length > 0 &&
					el( CapabilityRouting, {
						configurations,
						capabilityMap,
						capabilities,
						providers,
						onUpdate: setCapabilityMap,
						showNotice,
					} )
			)
		);
	}
	return el(
		_wordpress_connectors__WEBPACK_IMPORTED_MODULE_0__.__experimentalConnectorItem,
		{
			logo: logo || el( AIRouterIcon ),
			name,
			description: statusText || description,
			actionArea: actionButton,
		},
		panelContent
	);
}

// ── Icon ─────────────────────────────────────────────────────────

function AIRouterIcon() {
	return el(
		'svg',
		{
			width: 40,
			height: 40,
			viewBox: '0 0 24 24',
			xmlns: 'http://www.w3.org/2000/svg',
			'aria-hidden': 'true',
			focusable: 'false',
		},
		el( 'path', {
			d: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z',
			fill: 'currentColor',
		} )
	);
}

// ── Register ─────────────────────────────────────────────────────

(0,_wordpress_connectors__WEBPACK_IMPORTED_MODULE_0__.__experimentalRegisterConnector)( 'ai_provider/ai-router', {
	name: __( 'AI Router', 'ai-router' ),
	description: __(
		'Route AI requests to different providers by capability.',
		'ai-router'
	),
	render: AIRouterConnector,
} );

})();


//# sourceMappingURL=connectors.js.map