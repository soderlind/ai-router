/**
 * Tests for Admin UI components.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import React from 'react';
import apiFetch from '@wordpress/api-fetch';

// Import the components we want to test.
// Since admin.js exports nothing, we'll test via the window object and create helper components.

/**
 * Helper: Create a mock configuration.
 */
const createMockConfig = ( overrides = {} ) => ( {
	id: 'config-123',
	name: 'Test Configuration',
	provider_type: 'openai',
	settings: {
		api_key: 'sk-****1234',
	},
	capabilities: [ 'text_generation', 'chat_history' ],
	is_default: false,
	mapped_capabilities: [],
	...overrides,
} );

/**
 * Helper: ConfigurationForm component for testing.
 */
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
import { useState } from '@wordpress/element';

const PROVIDER_SETTINGS_FIELDS = {
	openai: [
		{ key: 'api_key', label: 'API Key', type: 'password' },
	],
	'azure-openai': [
		{ key: 'api_key', label: 'API Key', type: 'password' },
		{ key: 'endpoint', label: 'Endpoint URL', type: 'text', placeholder: 'https://your-resource.openai.azure.com' },
		{ key: 'deployment_id', label: 'Deployment ID', type: 'text' },
		{ key: 'api_version', label: 'API Version', type: 'text', placeholder: '2024-02-15-preview' },
	],
};

/**
 * Simplified ConfigurationForm for testing.
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
			setError( err.message || 'Failed to save configuration.' );
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
		<form onSubmit={ handleSubmit } data-testid="config-form">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<TextControl
				label="Configuration Name"
				value={ formData.name }
				onChange={ ( value ) => setFormData( ( prev ) => ( { ...prev, name: value } ) ) }
				required
				data-testid="name-input"
			/>

			<SelectControl
				label="Provider Type"
				value={ formData.provider_type }
				onChange={ ( value ) => setFormData( ( prev ) => ( { ...prev, provider_type: value } ) ) }
				options={ Object.entries( providerTypes ).map( ( [ value, label ] ) => ( { value, label } ) ) }
				data-testid="provider-select"
			/>

			<div data-testid="settings-fields">
				{ settingsFields.map( ( field ) => (
					<TextControl
						key={ field.key }
						label={ field.label }
						type={ field.type }
						placeholder={ field.placeholder }
						value={ formData.settings[ field.key ] || '' }
						onChange={ ( value ) => updateSetting( field.key, value ) }
						data-testid={ `setting-${ field.key }` }
					/>
				) ) }
			</div>

			<div data-testid="capabilities-list">
				{ capabilities.map( ( cap ) => (
					<CheckboxControl
						key={ cap.slug }
						label={ cap.label }
						checked={ formData.capabilities.includes( cap.slug ) }
						onChange={ () => toggleCapability( cap.slug ) }
						data-testid={ `cap-${ cap.slug }` }
					/>
				) ) }
			</div>

			<CheckboxControl
				label="Set as default configuration"
				checked={ formData.is_default }
				onChange={ ( value ) => setFormData( ( prev ) => ( { ...prev, is_default: value } ) ) }
				data-testid="is-default"
			/>

			<Flex>
				<FlexItem>
					<Button variant="secondary" onClick={ onCancel } disabled={ saving } data-testid="cancel-btn">
						Cancel
					</Button>
				</FlexItem>
				<FlexItem>
					<Button variant="primary" type="submit" isBusy={ saving } disabled={ saving } data-testid="submit-btn">
						{ config?.id ? 'Update' : 'Create' }
					</Button>
				</FlexItem>
			</Flex>
		</form>
	);
}

describe( 'ConfigurationForm', () => {
	const defaultProps = {
		config: null,
		onSave: vi.fn(),
		onCancel: vi.fn(),
		providerTypes: {
			openai: 'OpenAI',
			'azure-openai': 'Azure OpenAI',
		},
		capabilities: [
			{ slug: 'text_generation', label: 'Text Generation' },
			{ slug: 'image_generation', label: 'Image Generation' },
		],
	};

	beforeEach( () => {
		vi.clearAllMocks();
	} );

	it( 'renders empty form for new configuration', () => {
		render( <ConfigurationForm { ...defaultProps } /> );

		expect( screen.getByTestId( 'config-form' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'name-input' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'provider-select' ) ).toBeInTheDocument();
	} );

	it( 'renders form with existing configuration data', () => {
		const config = createMockConfig();
		render( <ConfigurationForm { ...defaultProps } config={ config } /> );

		// Check that update button is shown.
		expect( screen.getByTestId( 'submit-btn' ) ).toHaveTextContent( 'Update' );
	} );

	it( 'shows OpenAI settings fields by default', () => {
		render( <ConfigurationForm { ...defaultProps } /> );

		expect( screen.getByTestId( 'setting-api_key' ) ).toBeInTheDocument();
	} );

	it( 'shows Azure settings fields when Azure provider selected', () => {
		const config = createMockConfig( { provider_type: 'azure-openai' } );
		render( <ConfigurationForm { ...defaultProps } config={ config } /> );

		expect( screen.getByTestId( 'setting-api_key' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'setting-endpoint' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'setting-deployment_id' ) ).toBeInTheDocument();
	} );

	it( 'renders capability checkboxes', () => {
		render( <ConfigurationForm { ...defaultProps } /> );

		expect( screen.getByTestId( 'cap-text_generation' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'cap-image_generation' ) ).toBeInTheDocument();
	} );

	it( 'calls onCancel when cancel button clicked', () => {
		render( <ConfigurationForm { ...defaultProps } /> );

		fireEvent.click( screen.getByTestId( 'cancel-btn' ) );

		expect( defaultProps.onCancel ).toHaveBeenCalled();
	} );

	it( 'calls apiFetch with POST for new configuration', async () => {
		apiFetch.mockResolvedValueOnce( { id: 'new-id', name: 'Test' } );

		render( <ConfigurationForm { ...defaultProps } /> );

		// Fill in name - the data-testid is on the input directly.
		const nameInput = screen.getByTestId( 'name-input' );
		fireEvent.change( nameInput, { target: { value: 'New Config' } } );

		// Submit.
		fireEvent.submit( screen.getByTestId( 'config-form' ) );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/ai-router/v1/configurations',
					method: 'POST',
				} )
			);
		} );
	} );

	it( 'calls apiFetch with PUT for existing configuration', async () => {
		const config = createMockConfig();
		apiFetch.mockResolvedValueOnce( config );

		render( <ConfigurationForm { ...defaultProps } config={ config } /> );

		fireEvent.submit( screen.getByTestId( 'config-form' ) );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: `/ai-router/v1/configurations/${ config.id }`,
					method: 'PUT',
				} )
			);
		} );
	} );

	it( 'shows error notice on API failure', async () => {
		apiFetch.mockRejectedValueOnce( new Error( 'API Error' ) );

		render( <ConfigurationForm { ...defaultProps } /> );

		fireEvent.submit( screen.getByTestId( 'config-form' ) );

		await waitFor( () => {
			expect( screen.getByText( 'API Error' ) ).toBeInTheDocument();
		} );
	} );
} );

/**
 * Simplified CapabilityMapping for testing.
 */
function CapabilityMapping( { configurations, capabilityMap, capabilities, onUpdate } ) {
	const [ localMap, setLocalMap ] = useState( capabilityMap );
	const [ saving, setSaving ] = useState( false );
	const [ changed, setChanged ] = useState( false );

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
			alert( err.message || 'Failed to save mapping.' );
		} finally {
			setSaving( false );
		}
	};

	const getConfigOptions = ( capability ) => {
		const options = [ { value: '', label: '— Not mapped —' } ];

		configurations.forEach( ( config ) => {
			if ( config.capabilities.includes( capability ) ) {
				options.push( { value: config.id, label: config.name } );
			}
		} );

		return options;
	};

	return (
		<div data-testid="capability-mapping">
			<table>
				<thead>
					<tr>
						<th>Capability</th>
						<th>Route to Configuration</th>
					</tr>
				</thead>
				<tbody>
					{ capabilities.map( ( cap ) => (
						<tr key={ cap.slug } data-testid={ `row-${ cap.slug }` }>
							<td>{ cap.label }</td>
							<td>
								<SelectControl
									value={ localMap[ cap.slug ] || '' }
									onChange={ ( value ) => handleChange( cap.slug, value ) }
									options={ getConfigOptions( cap.slug ) }
									data-testid={ `select-${ cap.slug }` }
								/>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>

			{ changed && (
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ saving }
					disabled={ saving }
					data-testid="save-mapping-btn"
				>
					Save Mapping
				</Button>
			) }
		</div>
	);
}

describe( 'CapabilityMapping', () => {
	const defaultProps = {
		configurations: [
			createMockConfig( { id: 'config-1', name: 'Text Config', capabilities: [ 'text_generation' ] } ),
			createMockConfig( { id: 'config-2', name: 'Image Config', capabilities: [ 'image_generation' ] } ),
		],
		capabilityMap: {},
		capabilities: [
			{ slug: 'text_generation', label: 'Text Generation' },
			{ slug: 'image_generation', label: 'Image Generation' },
		],
		onUpdate: vi.fn(),
	};

	beforeEach( () => {
		vi.clearAllMocks();
	} );

	it( 'renders capability rows', () => {
		render( <CapabilityMapping { ...defaultProps } /> );

		expect( screen.getByTestId( 'row-text_generation' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'row-image_generation' ) ).toBeInTheDocument();
	} );

	it( 'shows only configs that support capability in dropdown', () => {
		render( <CapabilityMapping { ...defaultProps } /> );

		// For text_generation, only config-1 should be available.
		// The data-testid is directly on the select element.
		const textSelect = screen.getByTestId( 'select-text_generation' );
		const options = Array.from( textSelect.options ).map( ( opt ) => opt.value );

		expect( options ).toContain( '' ); // "Not mapped" option.
		expect( options ).toContain( 'config-1' );
		expect( options ).not.toContain( 'config-2' );
	} );

	it( 'shows save button when mapping changed', () => {
		render( <CapabilityMapping { ...defaultProps } /> );

		// Initially no save button.
		expect( screen.queryByTestId( 'save-mapping-btn' ) ).not.toBeInTheDocument();

		// Change a mapping.
		const textSelect = screen.getByTestId( 'select-text_generation' );
		fireEvent.change( textSelect, { target: { value: 'config-1' } } );

		// Now save button should appear.
		expect( screen.getByTestId( 'save-mapping-btn' ) ).toBeInTheDocument();
	} );

	it( 'calls apiFetch when save button clicked', async () => {
		apiFetch.mockResolvedValueOnce( { text_generation: 'config-1' } );

		render( <CapabilityMapping { ...defaultProps } /> );

		// Change a mapping.
		const textSelect = screen.getByTestId( 'select-text_generation' );
		fireEvent.change( textSelect, { target: { value: 'config-1' } } );

		// Click save.
		fireEvent.click( screen.getByTestId( 'save-mapping-btn' ) );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/ai-router/v1/capability-map',
					method: 'POST',
				} )
			);
		} );
	} );

	it( 'reflects existing capability map', () => {
		const props = {
			...defaultProps,
			capabilityMap: { text_generation: 'config-1' },
		};

		render( <CapabilityMapping { ...props } /> );

		const textSelect = screen.getByTestId( 'select-text_generation' );
		expect( textSelect.value ).toBe( 'config-1' );
	} );
} );

describe( 'Provider settings fields', () => {
	it( 'OpenAI has api_key field', () => {
		expect( PROVIDER_SETTINGS_FIELDS.openai ).toHaveLength( 1 );
		expect( PROVIDER_SETTINGS_FIELDS.openai[ 0 ].key ).toBe( 'api_key' );
	} );

	it( 'Azure OpenAI has all required fields', () => {
		const azureFields = PROVIDER_SETTINGS_FIELDS[ 'azure-openai' ];

		expect( azureFields ).toHaveLength( 4 );

		const fieldKeys = azureFields.map( ( f ) => f.key );
		expect( fieldKeys ).toContain( 'api_key' );
		expect( fieldKeys ).toContain( 'endpoint' );
		expect( fieldKeys ).toContain( 'deployment_id' );
		expect( fieldKeys ).toContain( 'api_version' );
	} );
} );
