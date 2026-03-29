/**
 * Mock for @wordpress/components.
 */

import React from 'react';

// Simple mock components that render children or nothing.
export const Button = ( { children, onClick, ...props } ) => (
	<button onClick={ onClick } { ...props }>
		{ children }
	</button>
);

export const Card = ( { children, className, ...props } ) => (
	<div className={ `components-card ${ className || '' }` } { ...props }>
		{ children }
	</div>
);

export const CardBody = ( { children, ...props } ) => (
	<div className="components-card__body" { ...props }>
		{ children }
	</div>
);

export const CardHeader = ( { children, ...props } ) => (
	<div className="components-card__header" { ...props }>
		{ children }
	</div>
);

export const Flex = ( { children, ...props } ) => (
	<div className="components-flex" { ...props }>
		{ children }
	</div>
);

export const FlexItem = ( { children, ...props } ) => (
	<div className="components-flex-item" { ...props }>
		{ children }
	</div>
);

export const Notice = ( { children, status, isDismissible, ...props } ) => (
	<div className={ `components-notice is-${ status }` } { ...props }>
		{ children }
	</div>
);

export const Panel = ( { children, ...props } ) => (
	<div className="components-panel" { ...props }>
		{ children }
	</div>
);

export const PanelBody = ( { children, title, initialOpen, ...props } ) => (
	<div className="components-panel__body" { ...props }>
		{ title && <h2>{ title }</h2> }
		{ children }
	</div>
);

export const SelectControl = ( {
	label,
	value,
	onChange,
	options,
	...props
} ) => (
	<div className="components-select-control">
		{ label && <label>{ label }</label> }
		<select
			value={ value }
			onChange={ ( e ) => onChange( e.target.value ) }
			{ ...props }
		>
			{ options?.map( ( opt ) => (
				<option key={ opt.value } value={ opt.value }>
					{ opt.label }
				</option>
			) ) }
		</select>
	</div>
);

export const Modal = ( { title, onRequestClose, children, ...props } ) => (
	<div className="components-modal__screen-overlay" role="dialog" aria-label={ title } { ...props }>
		<div className="components-modal__frame">
			<div className="components-modal__header">
				<h1>{ title }</h1>
				<button onClick={ onRequestClose } aria-label="Close">×</button>
			</div>
			<div className="components-modal__content">{ children }</div>
		</div>
	</div>
);

export const Spinner = () => (
	<div className="components-spinner" data-testid="spinner" />
);

export const TextControl = ( {
	label,
	value,
	onChange,
	type = 'text',
	...props
} ) => (
	<div className="components-text-control">
		{ label && <label>{ label }</label> }
		<input
			type={ type }
			value={ value || '' }
			onChange={ ( e ) => onChange( e.target.value ) }
			{ ...props }
		/>
	</div>
);

export const CheckboxControl = ( {
	label,
	checked,
	onChange,
	help,
	...props
} ) => (
	<div className="components-checkbox-control">
		<input
			type="checkbox"
			checked={ checked }
			onChange={ ( e ) => onChange( e.target.checked ) }
			{ ...props }
		/>
		{ label && <label>{ label }</label> }
		{ help && (
			<p className="components-checkbox-control__help">{ help }</p>
		) }
	</div>
);

// Experimental components.
export const __experimentalVStack = ( { children, spacing, ...props } ) => (
	<div className="components-v-stack" { ...props }>
		{ children }
	</div>
);

export const __experimentalHStack = ( { children, spacing, ...props } ) => (
	<div className="components-h-stack" { ...props }>
		{ children }
	</div>
);

export const __experimentalHeading = ( { level, children, ...props } ) => {
	const Tag = `h${ level || 2 }`;
	return <Tag { ...props }>{ children }</Tag>;
};

export const __experimentalText = ( { children, ...props } ) => (
	<span { ...props }>{ children }</span>
);

export const __experimentalConfirmDialog = ( {
	isOpen,
	onConfirm,
	onCancel,
	confirmButtonText,
	cancelButtonText,
	children,
	...props
} ) =>
	isOpen ? (
		<div className="components-confirm-dialog" role="dialog" { ...props }>
			<p>{ children }</p>
			<button onClick={ onConfirm }>{ confirmButtonText || 'OK' }</button>
			<button onClick={ onCancel }>
				{ cancelButtonText || 'Cancel' }
			</button>
		</div>
	) : null;
