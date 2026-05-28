( function ( wp ) {
	'use strict';
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var __ = wp.i18n.__;

	var blockEditor = wp.blockEditor || wp.editor;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps     = blockEditor.useBlockProps;

	var PanelBody     = wp.components.PanelBody;
	var TextControl   = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;

	// Deprecation v0: swish/ac-form before buttonAlign was added.
	// save() was null then and is null now, so this just migrates attrs.
	var deprecatedV0 = {
		attributes: {
			showName:     { type: 'boolean', default: true },
			nameLabel:    { type: 'string',  default: 'Name' },
			nameRequired: { type: 'boolean', default: false },
			emailLabel:   { type: 'string',  default: 'Email' },
			submitLabel:  { type: 'string',  default: 'Subscribe' }
		},
		supports: { html: false, multiple: false, reusable: false, inserter: true },
		save: function () { return null; },
		migrate: function ( attrs, inner ) {
			return [ Object.assign( { buttonAlign: 'left' }, attrs ), inner ];
		}
	};

	registerBlockType( 'swish/ac-form', {
		deprecated: [ deprecatedV0 ],
		edit: function ( props ) {
			var a   = props.attributes;
			var set = props.setAttributes;
			var blockProps = useBlockProps( { className: 'swish-ac-form swish-ac-form--preview' } );

			var alignClass = 'swish-ac-form__submit-wrap--align-' + ( a.buttonAlign || 'left' );

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Name field', 'swish-active-campaign' ), initialOpen: true },
						el( ToggleControl, {
							label: __( 'Show name field', 'swish-active-campaign' ),
							checked: !! a.showName,
							onChange: function ( v ) { set( { showName: v } ); }
						} ),
						a.showName && el( TextControl, {
							label: __( 'Name label', 'swish-active-campaign' ),
							value: a.nameLabel,
							onChange: function ( v ) { set( { nameLabel: v } ); }
						} ),
						a.showName && el( ToggleControl, {
							label: __( 'Required', 'swish-active-campaign' ),
							checked: !! a.nameRequired,
							onChange: function ( v ) { set( { nameRequired: v } ); }
						} )
					),
					el( PanelBody, { title: __( 'Email field', 'swish-active-campaign' ), initialOpen: false },
						el( TextControl, {
							label: __( 'Email label', 'swish-active-campaign' ),
							value: a.emailLabel,
							onChange: function ( v ) { set( { emailLabel: v } ); }
						} )
					),
					el( PanelBody, { title: __( 'Submit button', 'swish-active-campaign' ), initialOpen: false },
						el( TextControl, {
							label: __( 'Button label', 'swish-active-campaign' ),
							value: a.submitLabel,
							onChange: function ( v ) { set( { submitLabel: v } ); }
						} ),
						el( SelectControl, {
							label: __( 'Button alignment', 'swish-active-campaign' ),
							value: a.buttonAlign || 'left',
							options: [
								{ value: 'left',   label: __( 'Left', 'swish-active-campaign' ) },
								{ value: 'center', label: __( 'Center', 'swish-active-campaign' ) },
								{ value: 'right',  label: __( 'Right', 'swish-active-campaign' ) },
								{ value: 'full',   label: __( 'Full width', 'swish-active-campaign' ) }
							],
							onChange: function ( v ) { set( { buttonAlign: v } ); }
						} )
					)
				),
				el( 'div', blockProps,
					a.showName && el( 'div', { className: 'swish-ac-form__field' },
						el( 'label', null, a.nameLabel + ( a.nameRequired ? ' *' : '' ) ),
						el( 'input', { type: 'text', disabled: true, placeholder: a.nameLabel } )
					),
					el( 'div', { className: 'swish-ac-form__field' },
						el( 'label', null, a.emailLabel + ' *' ),
						el( 'input', { type: 'email', disabled: true, placeholder: a.emailLabel } )
					),
					el( 'div', { className: 'swish-ac-form__submit-wrap ' + alignClass },
						el( 'button', { type: 'button', className: 'swish-ac-form__submit', disabled: true }, a.submitLabel )
					)
				)
			);
		},
		save: function () { return null; }
	} );
} )( window.wp );
