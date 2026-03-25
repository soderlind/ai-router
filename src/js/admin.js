/**
 * AI Router Admin UI
 *
 * @package AIRouter
 */

import { createRoot, useState, useEffect, useCallback } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Flex,
	FlexItem,
	Notice,
	Panel,
	PanelBody,
	SelectControl,
	Spinner,
	TextControl,
	CheckboxControl,
	__experimentalHeading as Heading,
	__experimentalText as Text,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import './admin.css';

/**
 * Provider settings fields by type.
 */
const PROVIDER_SETTINGS_FIELDS = {
	openai: [
		{ key: 'api_key', label: __( 'API Key', 'ai-router' ), type: 'password' },
	],
	'azure-openai': [
		{ key: 'api_key', label: __( 'API Key', 'ai-router' ), type: 'password' },
		{ key: 'endpoint', label: __( 'Endpoint URL', 'ai-router' ), type: 'text', placeholder: 'https://your-resource.openai.azure.com' },
		{ key: 'deployment_id', label: __( 'Deployment ID', 'ai-router' ), type: 'text' },
		{ key: 'api_version', label: __( 'API Version', 'ai-router' ), type: 'text', placeholder: '2024-02-15-preview' },
	],
};

/**
 * Configuration Form Component.
 */
function ConfigurationForm( { config, onSave, onCancel, providerTypes, capabilities } ) {
	const [ formData, setFormData ] = useState( {
		name: config?.name || '',
		provider_type: config?.provider_type || 'openai',
		settings: config?.settings || {},
		capabilities: config?.capabilities || [],
		is_default: config?.is_default || false,
	} );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	const handleSubmit = async ( e ) => {
		e.preventDefault();
		setSaving( true );
		setError( null );

		try {
			const endpoint = config?.id
				? `/ai-router/v1/configurations/${ config.id }`
				: '/ai-router/v1/configurations';

			const method = config?.id ? 'PUT' : 'POST';

			const result = await apiFetch( {
				path: endpoint,
				method,
				data: formData,
			} );

			onSave( result );
		} catch ( err ) {
			setError( err.message || __( 'Failed to save configuration.', 'ai-router' ) );
		} finally {
			setSaving( false );
		}
	};

	const updateSetting = ( key, value ) => {
		setFormData( ( prev ) => ( {
			...prev,
			settings: { ...prev.settings, [ key ]: value },
		} ) );
	};

	const toggleCapability = ( capability ) => {
		setFormData( ( prev ) => {
			const caps = prev.capabilities.includes( capability )
				? prev.capabilities.filter( ( c ) => c !== capability )
				: [ ...prev.capabilities, capability ];
			return { ...prev, capabilities: caps };
		} );
	};

	const settingsFields = PROVIDER_SETTINGS_FIELDS[ formData.provider_type ] || [];

	return (
		<form onSubmit={ handleSubmit }>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<TextControl
				label={ __( 'Configuration Name', 'ai-router' ) }
				value={ formData.name }
				onChange={ ( value ) => setFormData( ( prev ) => ( { ...prev, name: value } ) ) }
				required
			/>

			<SelectControl
				label={ __( 'Provider Type', 'ai-router' ) }
				value={ formData.provider_type }
				onChange={ ( value ) => setFormData( ( prev ) => ( { ...prev, provider_type: value } ) ) }
				options={ Object.entries( providerTypes ).map( ( [ value, label ] ) => ( { value, label } ) ) }
			/>

			<Heading level={ 4 }>{ __( 'Provider Settings', 'ai-router' ) }</Heading>
			{ settingsFields.map( ( field ) => (
				<TextControl
					key={ field.key }
					label={ field.label }
					type={ field.type }
					placeholder={ field.placeholder }
					value={ formData.settings[ field.key ] || '' }
					onChange={ ( value ) => updateSetting( field.key, value ) }
				/>
			) ) }

			<Heading level={ 4 }>{ __( 'Capabilities', 'ai-router' ) }</Heading>
			<Text>{ __( 'Select the capabilities this configuration supports:', 'ai-router' ) }</Text>
			<div className="ai-router-capabilities">
				{ capabilities.map( ( cap ) => (
					<CheckboxControl
						key={ cap.slug }
						label={ cap.label }
						checked={ formData.capabilities.includes( cap.slug ) }
						onChange={ () => toggleCapability( cap.slug ) }
					/>
				) ) }
			</div>

			<CheckboxControl
				label={ __( 'Set as default configuration', 'ai-router' ) }
				help={ __( 'Used as fallback when no specific mapping exists.', 'ai-router' ) }
				checked={ formData.is_default }
				onChange={ ( value ) => setFormData( ( prev ) => ( { ...prev, is_default: value } ) ) }
			/>

			<Flex justify="flex-end" style={ { marginTop: '16px' } }>
				<FlexItem>
					<Button variant="secondary" onClick={ onCancel } disabled={ saving }>
						{ __( 'Cancel', 'ai-router' ) }
					</Button>
				</FlexItem>
				<FlexItem>
					<Button variant="primary" type="submit" isBusy={ saving } disabled={ saving }>
						{ config?.id ? __( 'Update', 'ai-router' ) : __( 'Create', 'ai-router' ) }
					</Button>
				</FlexItem>
			</Flex>
		</form>
	);
}

/**
 * Configuration Card Component.
 */
function ConfigurationCard( { config, onEdit, onDelete, providerTypes } ) {
	const [ deleting, setDeleting ] = useState( false );

	const handleDelete = async () => {
		if ( ! window.confirm( __( 'Are you sure you want to delete this configuration?', 'ai-router' ) ) ) {
			return;
		}

		setDeleting( true );
		try {
			await apiFetch( {
				path: `/ai-router/v1/configurations/${ config.id }`,
				method: 'DELETE',
			} );
			onDelete( config.id );
		} catch ( err ) {
			alert( err.message || __( 'Failed to delete configuration.', 'ai-router' ) );
		} finally {
			setDeleting( false );
		}
	};

	return (
		<Card className="ai-router-config-card">
			<CardHeader>
				<Flex>
					<FlexItem>
						<Heading level={ 4 }>{ config.name }</Heading>
						<Text>{ providerTypes[ config.provider_type ] || config.provider_type }</Text>
					</FlexItem>
					<FlexItem>
						{ config.is_default && (
							<span className="ai-router-default-badge">
								{ __( 'Default', 'ai-router' ) }
							</span>
						) }
					</FlexItem>
				</Flex>
			</CardHeader>
			<CardBody>
				<Text>
					<strong>{ __( 'Capabilities:', 'ai-router' ) }</strong>{ ' ' }
					{ config.capabilities.length > 0
						? config.capabilities.join( ', ' )
						: __( 'None', 'ai-router' ) }
				</Text>
				{ config.mapped_capabilities?.length > 0 && (
					<Text className="ai-router-mapped-info">
						<strong>{ __( 'Mapped to:', 'ai-router' ) }</strong>{ ' ' }
						{ config.mapped_capabilities.join( ', ' ) }
					</Text>
				) }
				<Flex justify="flex-end" style={ { marginTop: '12px' } }>
					<FlexItem>
						<Button variant="secondary" onClick={ () => onEdit( config ) } size="small">
							{ __( 'Edit', 'ai-router' ) }
						</Button>
					</FlexItem>
					<FlexItem>
						<Button
							variant="secondary"
							isDestructive
							onClick={ handleDelete }
							isBusy={ deleting }
							disabled={ deleting }
							size="small"
						>
							{ __( 'Delete', 'ai-router' ) }
						</Button>
					</FlexItem>
				</Flex>
			</CardBody>
		</Card>
	);
}

/**
 * Capability Mapping Component.
 */
function CapabilityMapping( { configurations, capabilityMap, capabilities, onUpdate } ) {
	const [ localMap, setLocalMap ] = useState( capabilityMap );
	const [ saving, setSaving ] = useState( false );
	const [ changed, setChanged ] = useState( false );

	useEffect( () => {
		setLocalMap( capabilityMap );
		setChanged( false );
	}, [ capabilityMap ] );

	const handleChange = ( capability, configId ) => {
		setLocalMap( ( prev ) => ( {
			...prev,
			[ capability ]: configId,
		} ) );
		setChanged( true );
	};

	const handleSave = async () => {
		setSaving( true );
		try {
			const result = await apiFetch( {
				path: '/ai-router/v1/capability-map',
				method: 'POST',
				data: localMap,
			} );
			onUpdate( result );
			setChanged( false );
		} catch ( err ) {
			alert( err.message || __( 'Failed to save mapping.', 'ai-router' ) );
		} finally {
			setSaving( false );
		}
	};

	const getConfigOptions = ( capability ) => {
		const options = [ { value: '', label: __( '— Not mapped —', 'ai-router' ) } ];

		configurations.forEach( ( config ) => {
			if ( config.capabilities.includes( capability ) ) {
				options.push( { value: config.id, label: config.name } );
			}
		} );

		return options;
	};

	return (
		<div className="ai-router-capability-mapping">
			<table className="widefat">
				<thead>
					<tr>
						<th>{ __( 'Capability', 'ai-router' ) }</th>
						<th>{ __( 'Route to Configuration', 'ai-router' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ capabilities.map( ( cap ) => (
						<tr key={ cap.slug }>
							<td>{ cap.label }</td>
							<td>
								<SelectControl
									value={ localMap[ cap.slug ] || '' }
									onChange={ ( value ) => handleChange( cap.slug, value ) }
									options={ getConfigOptions( cap.slug ) }
									__nextHasNoMarginBottom
								/>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>

			{ changed && (
				<Flex justify="flex-end" style={ { marginTop: '16px' } }>
					<FlexItem>
						<Button
							variant="primary"
							onClick={ handleSave }
							isBusy={ saving }
							disabled={ saving }
						>
							{ __( 'Save Mapping', 'ai-router' ) }
						</Button>
					</FlexItem>
				</Flex>
			) }
		</div>
	);
}

/**
 * Main Admin App Component.
 */
function AdminApp() {
	const [ configurations, setConfigurations ] = useState( window.aiRouterAdmin?.configurations || [] );
	const [ capabilityMap, setCapabilityMap ] = useState( window.aiRouterAdmin?.capabilityMap || {} );
	const [ editingConfig, setEditingConfig ] = useState( null );
	const [ showForm, setShowForm ] = useState( false );
	const [ loading, setLoading ] = useState( false );

	const providerTypes = window.aiRouterAdmin?.providerTypes || {};
	const capabilities = window.aiRouterAdmin?.capabilities || [];

	const refreshConfigurations = useCallback( async () => {
		setLoading( true );
		try {
			const [ configs, mapping ] = await Promise.all( [
				apiFetch( { path: '/ai-router/v1/configurations' } ),
				apiFetch( { path: '/ai-router/v1/capability-map' } ),
			] );
			setConfigurations( configs );
			setCapabilityMap( mapping );
		} catch ( err ) {
			console.error( 'Failed to refresh:', err );
		} finally {
			setLoading( false );
		}
	}, [] );

	const handleSave = ( config ) => {
		setConfigurations( ( prev ) => {
			const exists = prev.find( ( c ) => c.id === config.id );
			if ( exists ) {
				return prev.map( ( c ) => ( c.id === config.id ? config : c ) );
			}
			return [ ...prev, config ];
		} );
		setShowForm( false );
		setEditingConfig( null );
		refreshConfigurations();
	};

	const handleDelete = ( id ) => {
		setConfigurations( ( prev ) => prev.filter( ( c ) => c.id !== id ) );
		refreshConfigurations();
	};

	const handleEdit = ( config ) => {
		setEditingConfig( config );
		setShowForm( true );
	};

	const handleCancel = () => {
		setShowForm( false );
		setEditingConfig( null );
	};

	if ( loading ) {
		return (
			<div className="ai-router-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="ai-router-admin">
			<Panel>
				<PanelBody title={ __( 'Provider Configurations', 'ai-router' ) } initialOpen>
					{ showForm ? (
						<ConfigurationForm
							config={ editingConfig }
							onSave={ handleSave }
							onCancel={ handleCancel }
							providerTypes={ providerTypes }
							capabilities={ capabilities }
						/>
					) : (
						<>
							<div className="ai-router-configs-grid">
								{ configurations.map( ( config ) => (
									<ConfigurationCard
										key={ config.id }
										config={ config }
										onEdit={ handleEdit }
										onDelete={ handleDelete }
										providerTypes={ providerTypes }
									/>
								) ) }
							</div>

							{ configurations.length === 0 && (
								<Notice status="info" isDismissible={ false }>
									{ __( 'No configurations yet. Create your first one!', 'ai-router' ) }
								</Notice>
							) }

							<Button
								variant="primary"
								onClick={ () => setShowForm( true ) }
								style={ { marginTop: '16px' } }
							>
								{ __( 'Add Configuration', 'ai-router' ) }
							</Button>
						</>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Capability Routing', 'ai-router' ) } initialOpen>
					<Text style={ { marginBottom: '16px', display: 'block' } }>
						{ __( 'Map each AI capability to a specific provider configuration. When WordPress requests that capability, it will be routed to the selected configuration.', 'ai-router' ) }
					</Text>

					{ configurations.length === 0 ? (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Create at least one configuration to set up routing.', 'ai-router' ) }
						</Notice>
					) : (
						<CapabilityMapping
							configurations={ configurations }
							capabilityMap={ capabilityMap }
							capabilities={ capabilities }
							onUpdate={ setCapabilityMap }
						/>
					) }
				</PanelBody>
			</Panel>
		</div>
	);
}

// Mount the app.
document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'ai-router-admin' );
	if ( container ) {
		const root = createRoot( container );
		root.render( <AdminApp /> );
	}
} );
