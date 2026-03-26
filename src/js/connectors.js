/**
 * AI Router — Connector for the WP 7 Connectors page.
 *
 * This module registers AI Router as a connector on Settings → Connectors.
 * AI Router is a "meta-connector" that routes AI requests to different
 * provider configurations based on capability.
 *
 * @package
 */

// ── Script module import (real ES module) ────────────────────────
import {
	__experimentalRegisterConnector as registerConnector,
	__experimentalConnectorItem as ConnectorItem,
} from '@wordpress/connectors';

// ── Classic scripts — accessed via window globals ────────────────
const apiFetch = window.wp.apiFetch;
const { useState, useEffect, useCallback, createElement } = window.wp.element;
const { __ } = window.wp.i18n;
const {
	Button,
	SelectControl,
	TextControl,
	CheckboxControl,
	Notice,
	Spinner,
	__experimentalVStack: VStack,
	__experimentalHStack: HStack,
} = window.wp.components;

const el = createElement;

/**
 * All supported AI capabilities (hardcoded, matches PHP ProviderDiscovery).
 */
const CAPABILITY_OPTIONS = [
	{ slug: 'text_generation', label: __( 'Text Generation', 'ai-router' ) },
	{ slug: 'chat_history', label: __( 'Chat History', 'ai-router' ) },
	{ slug: 'image_generation', label: __( 'Image Generation', 'ai-router' ) },
	{
		slug: 'embedding_generation',
		label: __( 'Embedding Generation', 'ai-router' ),
	},
	{
		slug: 'text_to_speech_conversion',
		label: __( 'Text to Speech', 'ai-router' ),
	},
	{
		slug: 'speech_generation',
		label: __( 'Speech Generation', 'ai-router' ),
	},
	{ slug: 'music_generation', label: __( 'Music Generation', 'ai-router' ) },
	{ slug: 'video_generation', label: __( 'Video Generation', 'ai-router' ) },
];

/**
 * Provider types (hardcoded, matches PHP ProviderDiscovery fallback providers).
 */
const PROVIDER_TYPES = {
	openai: __( 'OpenAI', 'ai-router' ),
	anthropic: __( 'Anthropic', 'ai-router' ),
	google: __( 'Google (Gemini)', 'ai-router' ),
	ollama: __( 'Ollama (Local)', 'ai-router' ),
	azure_openai: __( 'Azure OpenAI', 'ai-router' ),
};

/**
 * Capabilities supported by each provider type.
 */
const PROVIDER_CAPABILITIES = {
	openai: [
		'text_generation',
		'chat_history',
		'image_generation',
		'embedding_generation',
		'text_to_speech_conversion',
	],
	anthropic: [ 'text_generation', 'chat_history' ],
	google: [
		'text_generation',
		'chat_history',
		'image_generation',
		'embedding_generation',
	],
	ollama: [ 'text_generation', 'chat_history', 'embedding_generation' ],
	azure_openai: [
		'text_generation',
		'chat_history',
		'image_generation',
		'embedding_generation',
		'text_to_speech_conversion',
	],
};

/**
 * Get capabilities for a provider type.
 * @param providerType
 */
function getProviderCapabilities( providerType ) {
	return PROVIDER_CAPABILITIES[ providerType ] || [];
}

/**
 * Get display name for a configuration (prefixed with provider name).
 * @param {Object} config Configuration object with name and provider_type.
 * @return {string} Display name like "OpenAI: My Config"
 */
function getConfigDisplayName( config ) {
	const providerLabel = PROVIDER_TYPES[ config.provider_type ] || config.provider_type;
	return `${ providerLabel }: ${ config.name }`;
}

/**
 * Provider settings fields.
 */
const PROVIDER_FIELDS = {
	openai: [
		{
			key: 'api_key',
			label: __( 'API Key', 'ai-router' ),
			type: 'password',
		},
	],
	azure_openai: [
		{
			key: 'api_key',
			label: __( 'API Key', 'ai-router' ),
			type: 'password',
		},
		{
			key: 'endpoint',
			label: __( 'Endpoint URL', 'ai-router' ),
			type: 'text',
			placeholder: 'https://your-resource.openai.azure.com',
		},
		{
			key: 'deployment_id',
			label: __( 'Deployment ID', 'ai-router' ),
			type: 'text',
		},
		{
			key: 'api_version',
			label: __( 'API Version', 'ai-router' ),
			type: 'text',
			placeholder: '2024-02-15-preview',
		},
	],
	anthropic: [
		{
			key: 'api_key',
			label: __( 'API Key', 'ai-router' ),
			type: 'password',
		},
	],
	google: [
		{
			key: 'api_key',
			label: __( 'API Key', 'ai-router' ),
			type: 'password',
		},
	],
	ollama: [
		{
			key: 'endpoint',
			label: __( 'Server URL', 'ai-router' ),
			type: 'text',
			placeholder: 'http://localhost:11434',
		},
		{
			key: 'model',
			label: __( 'Model Name', 'ai-router' ),
			type: 'text',
			placeholder: 'llama2',
		},
	],
};

/**
 * Get provider fields for a provider type.
 * @param providerType
 */
function getProviderFields( providerType ) {
	return PROVIDER_FIELDS[ providerType ] || PROVIDER_FIELDS.openai;
}

/**
 * Hook: load configurations and capability map from REST API.
 */
function useAIRouterData() {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ configurations, setConfigurations ] = useState( [] );
	const [ capabilityMap, setCapabilityMap ] = useState( {} );
	const [ defaultConfig, setDefaultConfig ] = useState( '' );

	// Load data from REST API.
	const loadData = useCallback( async () => {
		setIsLoading( true );
		try {
			const [ configs, mapping, defaultId ] = await Promise.all( [
				apiFetch( { path: 'ai-router/v1/configurations' } ),
				apiFetch( { path: 'ai-router/v1/capability-map' } ),
				apiFetch( { path: 'ai-router/v1/default' } ),
			] );
			setConfigurations( configs );
			setCapabilityMap( mapping );
			setDefaultConfig( defaultId?.id || '' );
		} catch ( e ) {
			console.error( 'Failed to load AI Router data:', e );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		loadData();
	}, [ loadData ] );

	return {
		isLoading,
		configurations,
		capabilityMap,
		defaultConfig,
		setConfigurations,
		setCapabilityMap,
		setDefaultConfig,
		refresh: loadData,
	};
}

/**
 * Configuration Form Component (inline).
 * @param root0
 * @param root0.config
 * @param root0.onSave
 * @param root0.onCancel
 */
function ConfigurationForm( { config, onSave, onCancel } ) {
	const initialProvider = config?.provider_type || 'openai';
	const [ formData, setFormData ] = useState( {
		name: config?.name || '',
		provider_type: initialProvider,
		settings: config?.settings || {},
		capabilities: getProviderCapabilities( initialProvider ),
		is_default: config?.is_default || false,
	} );

	// Update capabilities when provider changes.
	const handleProviderChange = ( newProvider ) => {
		setFormData( ( p ) => ( {
			...p,
			provider_type: newProvider,
			settings: {},
			capabilities: getProviderCapabilities( newProvider ),
		} ) );
	};
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	const handleSubmit = async () => {
		setSaving( true );
		setError( null );
		try {
			const endpoint = config?.id
				? `ai-router/v1/configurations/${ config.id }`
				: 'ai-router/v1/configurations';
			const method = config?.id ? 'PUT' : 'POST';
			const result = await apiFetch( {
				path: endpoint,
				method,
				data: formData,
			} );
			onSave( result );
		} catch ( err ) {
			setError( err.message || __( 'Failed to save.', 'ai-router' ) );
		} finally {
			setSaving( false );
		}
	};

	const settingsFields = getProviderFields( formData.provider_type );

	return el(
		'div',
		{ className: 'ai-router-form' },
		error && el( Notice, { status: 'error', isDismissible: false }, error ),
		el(
			VStack,
			{ spacing: 3 },
			el( TextControl, {
				label: __( 'Configuration Name', 'ai-router' ),
				value: formData.name,
				onChange: ( v ) =>
					setFormData( ( p ) => ( { ...p, name: v } ) ),
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
			} ),
			el( SelectControl, {
				label: __( 'Provider Type', 'ai-router' ),
				value: formData.provider_type,
				onChange: handleProviderChange,
				options: Object.entries( PROVIDER_TYPES ).map(
					( [ value, label ] ) => ( { value, label } )
				),
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
			} ),
			...settingsFields.map( ( field ) =>
				el( TextControl, {
					key: field.key,
					label: field.label,
					type: field.type,
					placeholder: field.placeholder,
					value: formData.settings[ field.key ] || '',
					onChange: ( v ) =>
						setFormData( ( p ) => ( {
							...p,
							settings: { ...p.settings, [ field.key ]: v },
						} ) ),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} )
			),
			el(
				'fieldset',
				null,
				el(
					'legend',
					null,
					__( 'Supported Capabilities', 'ai-router' )
				),
				el(
					'p',
					{ className: 'description', style: { marginTop: 0 } },
					__(
						'Based on provider type. Assign in Capability Routing below.',
						'ai-router'
					)
				),
				el(
					'ul',
					{
						className: 'ai-router-capability-list',
						style: { margin: '8px 0', paddingLeft: '20px' },
					},
					...formData.capabilities.map( ( slug ) => {
						const cap = CAPABILITY_OPTIONS.find(
							( c ) => c.slug === slug
						);
						return el( 'li', { key: slug }, cap?.label || slug );
					} )
				),
				formData.capabilities.length === 0 &&
					el(
						'p',
						{ style: { fontStyle: 'italic', color: '#757575' } },
						__(
							'No capabilities defined for this provider.',
							'ai-router'
						)
					)
			),
			el( CheckboxControl, {
				label: __( 'Set as default', 'ai-router' ),
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
					},
					config?.id
						? __( 'Update', 'ai-router' )
						: __( 'Create', 'ai-router' )
				)
			)
		)
	);
}

/**
 * Capability Routing Component.
 * @param root0
 * @param root0.configurations
 * @param root0.capabilityMap
 * @param root0.onUpdate
 */
function CapabilityRouting( { configurations, capabilityMap, onUpdate } ) {
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
			const result = await apiFetch( {
				path: 'ai-router/v1/capability-map',
				method: 'POST',
				data: localMap,
			} );
			onUpdate( result );
			setChanged( false );
		} catch ( e ) {
			alert( e.message || __( 'Failed to save.', 'ai-router' ) );
		} finally {
			setSaving( false );
		}
	};

	const getConfigsForCapability = ( capSlug ) =>
		configurations.filter( ( c ) =>
			getProviderCapabilities( c.provider_type ).includes( capSlug )
		);

	return el(
		'div',
		{ className: 'ai-router-routing' },
		el( 'h4', null, __( 'Capability Routing', 'ai-router' ) ),
		el(
			'p',
			{ className: 'description' },
			__(
				'Assign each AI capability to a configuration. Each capability can only be assigned once.',
				'ai-router'
			)
		),
		el(
			VStack,
			{ spacing: 2 },
			...CAPABILITY_OPTIONS.map( ( cap ) => {
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
						HStack,
						{ spacing: 2, alignment: 'center' },
						el( 'span', null, cap.label ),
						isAssigned &&
							el(
								'span',
								{
									style: {
										background: '#00a32a',
										color: '#fff',
										borderRadius: '3px',
										padding: '2px 6px',
										fontSize: '11px',
										fontWeight: 500,
									},
								},
								__( 'Assigned', 'ai-router' )
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
							label: getConfigDisplayName( c ),
						} ) ),
					],
					disabled: available.length === 0,
					help:
						available.length === 0
							? __(
									'No configurations support this capability',
									'ai-router'
							  )
							: isAssigned
							? `→ ${ getConfigDisplayName( assignedConfig ) }`
							: '',
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} );
			} ),
			changed &&
				el(
					Button,
					{ variant: 'primary', onClick: handleSave, isBusy: saving },
					__( 'Save Routing', 'ai-router' )
				)
		)
	);
}

/**
 * Main AI Router Connector Component.
 * @param root0
 * @param root0.slug
 * @param root0.name
 * @param root0.description
 * @param root0.logo
 */
function AIRouterConnector( { slug, name, description, logo } ) {
	const {
		isLoading,
		configurations,
		capabilityMap,
		defaultConfig,
		setConfigurations,
		setCapabilityMap,
		setDefaultConfig,
		refresh,
	} = useAIRouterData();

	const [ isExpanded, setIsExpanded ] = useState( false );
	const [ editingConfig, setEditingConfig ] = useState( null );
	const [ creatingConfig, setCreatingConfig ] = useState( false );

	// Calculate status.
	const configCount = configurations.length;
	const mappedCount = Object.keys( capabilityMap ).length;
	const isConfigured = configCount > 0;

	// Loading state.
	if ( isLoading ) {
		return el( ConnectorItem, {
			logo: logo || el( AIRouterIcon ),
			name,
			description,
			actionArea: el( Spinner ),
		} );
	}

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
	};

	const handleDeleteConfig = async ( id ) => {
		if (
			! window.confirm( __( 'Delete this configuration?', 'ai-router' ) )
		) {
			return;
		}
		try {
			await apiFetch( {
				path: `ai-router/v1/configurations/${ id }`,
				method: 'DELETE',
			} );
			setConfigurations( ( prev ) =>
				prev.filter( ( c ) => c.id !== id )
			);
			if ( defaultConfig === id ) {
				setDefaultConfig( '' );
			}
			setCapabilityMap( ( prev ) => {
				const updated = { ...prev };
				Object.keys( updated ).forEach( ( k ) => {
					if ( updated[ k ] === id ) {
						delete updated[ k ];
					}
				} );
				return updated;
			} );
		} catch ( e ) {
			alert( e.message || __( 'Failed to delete.', 'ai-router' ) );
		}
	};

	// Action button.
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

	// Status text.
	const statusText = isConfigured
		? `${ configCount } config${
				configCount !== 1 ? 's' : ''
		  }, ${ mappedCount } mapped`
		: null;

	// Expanded panel content.
	let panelContent = null;
	if ( isExpanded ) {
		if ( creatingConfig || editingConfig ) {
			panelContent = el( ConfigurationForm, {
				config: editingConfig,
				onSave: handleSaveConfig,
				onCancel: () => {
					setEditingConfig( null );
					setCreatingConfig( false );
				},
			} );
		} else {
			panelContent = el(
				VStack,
				{ spacing: 4 },
				// Configuration list.
				el(
					'div',
					null,
					el(
						HStack,
						{ justify: 'space-between' },
						el( 'h4', null, __( 'Configurations', 'ai-router' ) ),
						el(
							Button,
							{
								variant: 'secondary',
								size: 'compact',
								onClick: () => setCreatingConfig( true ),
							},
							__( 'Add', 'ai-router' )
						)
					),
					configurations.length === 0
						? el(
								'p',
								{ className: 'description' },
								__( 'No configurations yet.', 'ai-router' )
						  )
						: el(
								'ul',
								{ className: 'ai-router-config-list' },
								...configurations.map( ( config ) =>
									el(
										'li',
										{ key: config.id },
										el(
											HStack,
											{ justify: 'space-between' },
											el(
												'span',
												null,
												config.name,
												defaultConfig === config.id &&
													el(
														'em',
														null,
														` (${ __(
															'default',
															'ai-router'
														) })`
													)
											),
											el(
												HStack,
												{ spacing: 1 },
												el(
													Button,
													{
														variant: 'link',
														size: 'small',
														onClick: () =>
															setEditingConfig(
																config
															),
													},
													__( 'Edit', 'ai-router' )
												),
												el(
													Button,
													{
														variant: 'link',
														size: 'small',
														isDestructive: true,
														onClick: () =>
															handleDeleteConfig(
																config.id
															),
													},
													__( 'Delete', 'ai-router' )
												)
											)
										)
									)
								)
						  )
				),
				// Capability routing (only show if configs exist).
				configurations.length > 0 &&
					el( CapabilityRouting, {
						configurations,
						capabilityMap,
						onUpdate: setCapabilityMap,
					} )
			);
		}
	}

	return el(
		ConnectorItem,
		{
			logo: logo || el( AIRouterIcon ),
			name,
			description: statusText || description,
			actionArea: actionButton,
		},
		panelContent
	);
}

/**
 * AI Router icon (40 × 40).
 */
function AIRouterIcon() {
	return el(
		'svg',
		{
			width: 40,
			height: 40,
			viewBox: '0 0 24 24',
			xmlns: 'http://www.w3.org/2000/svg',
			'aria-hidden': 'true',
		},
		// Router/network icon.
		el( 'path', {
			d: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z',
			fill: 'currentColor',
		} )
	);
}

// ── Register the connector ────────────────────────────────────────
// AI Router appears under "AI Providers" section as a routing layer.
// Using 'ai_provider' type to group with other AI connectors.
registerConnector( 'ai_provider/ai-router', {
	name: __( 'AI Router', 'ai-router' ),
	description: __(
		'Route AI requests to different providers by capability.',
		'ai-router'
	),
	render: AIRouterConnector,
} );
