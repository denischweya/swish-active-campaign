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
	var RangeControl  = wp.components.RangeControl;
	var ColorPalette  = wp.components.ColorPalette;

	// Deprecation v0: swish/ac-form before buttonAlign / showLabels were added.
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
			return [
				Object.assign( {
					buttonAlign: 'left', showLabels: true,
					buttonBg: '', buttonText: '',
					buttonBorderWidth: 0, buttonBorderColor: '',
					buttonRadius: 4
				}, attrs ),
				inner
			];
		}
	};

	registerBlockType( 'swish/ac-form', {
		deprecated: [ deprecatedV0 ],
		edit: function ( props ) {
			var a   = props.attributes;
			var set = props.setAttributes;
			var blockProps = useBlockProps( { className: 'swish-ac-form swish-ac-form--preview' } );

			var alignClass = 'swish-ac-form__submit-wrap--align-' + ( a.buttonAlign || 'left' );

			var showLabels = a.showLabels !== false;

			function buttonStyle() {
				var s = {};
				if ( a.buttonBg )   s.background = a.buttonBg;
				if ( a.buttonText ) s.color      = a.buttonText;
				var bw = parseInt( a.buttonBorderWidth, 10 ) || 0;
				if ( bw > 0 ) {
					s.borderStyle = 'solid';
					s.borderWidth = bw + 'px';
					s.borderColor = a.buttonBorderColor || '#000';
				}
				if ( a.buttonRadius != null ) s.borderRadius = a.buttonRadius + 'px';
				return s;
			}

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Labels', 'swish-active-campaign' ), initialOpen: true },
						el( ToggleControl, {
							label: __( 'Show field labels', 'swish-active-campaign' ),
							help: __( 'When off, the placeholder still appears inside the input.', 'swish-active-campaign' ),
							checked: showLabels,
							onChange: function ( v ) { set( { showLabels: v } ); }
						} )
					),
					el( PanelBody, { title: __( 'Name field', 'swish-active-campaign' ), initialOpen: false },
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
					),
					el( PanelBody, { title: __( 'Button style', 'swish-active-campaign' ), initialOpen: false },
						el( 'label', { className: 'components-base-control__label' },
							__( 'Background', 'swish-active-campaign' )
						),
						el( ColorPalette, {
							value: a.buttonBg || '',
							onChange: function ( v ) { set( { buttonBg: v || '' } ); }
						} ),
						el( 'label', { className: 'components-base-control__label' },
							__( 'Text color', 'swish-active-campaign' )
						),
						el( ColorPalette, {
							value: a.buttonText || '',
							onChange: function ( v ) { set( { buttonText: v || '' } ); }
						} ),
						el( RangeControl, {
							label: __( 'Border width (px)', 'swish-active-campaign' ),
							value: a.buttonBorderWidth || 0,
							onChange: function ( v ) { set( { buttonBorderWidth: parseInt( v, 10 ) || 0 } ); },
							min: 0, max: 8, step: 1
						} ),
						( a.buttonBorderWidth || 0 ) > 0 ? el( 'label', { className: 'components-base-control__label' },
							__( 'Border color', 'swish-active-campaign' )
						) : null,
						( a.buttonBorderWidth || 0 ) > 0 ? el( ColorPalette, {
							value: a.buttonBorderColor || '',
							onChange: function ( v ) { set( { buttonBorderColor: v || '' } ); }
						} ) : null,
						el( RangeControl, {
							label: __( 'Border radius (px)', 'swish-active-campaign' ),
							value: a.buttonRadius != null ? a.buttonRadius : 4,
							onChange: function ( v ) { set( { buttonRadius: parseInt( v, 10 ) || 0 } ); },
							min: 0, max: 40, step: 1
						} )
					)
				),
				el( 'div', blockProps,
					a.showName ? el( 'div', { className: 'swish-ac-form__field' },
						showLabels ? el( 'label', null, a.nameLabel + ( a.nameRequired ? ' *' : '' ) ) : null,
						el( 'input', {
							type: 'text', disabled: true,
							placeholder: a.nameLabel,
							'aria-label': showLabels ? null : a.nameLabel
						} )
					) : null,
					el( 'div', { className: 'swish-ac-form__field' },
						showLabels ? el( 'label', null, a.emailLabel + ' *' ) : null,
						el( 'input', {
							type: 'email', disabled: true,
							placeholder: a.emailLabel,
							'aria-label': showLabels ? null : a.emailLabel
						} )
					),
					el( 'div', { className: 'swish-ac-form__submit-wrap ' + alignClass },
						el( 'button', {
							type: 'button',
							className: 'swish-ac-form__submit',
							disabled: true,
							style: buttonStyle()
						}, a.submitLabel )
					)
				)
			);
		},
		save: function () { return null; }
	} );
} )( window.wp );
