( function ( wp ) {
	'use strict';

	var cfg = window.swishAcEditor || { postTypes: [], tagsUrl: '' };

	var el        = wp.element.createElement;
	var Fragment  = wp.element.Fragment;
	var useState  = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var registerBlockType = wp.blocks.registerBlockType;
	var __        = wp.i18n.__;

	var blockEditor         = wp.blockEditor || wp.editor;
	var InnerBlocks         = blockEditor.InnerBlocks;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps;
	var InspectorControls   = blockEditor.InspectorControls;
	var useBlockProps       = blockEditor.useBlockProps;
	var MediaUpload         = blockEditor.MediaUpload;
	var MediaUploadCheck    = blockEditor.MediaUploadCheck;

	var PanelBody        = wp.components.PanelBody;
	var TextControl      = wp.components.TextControl;
	var TextareaControl  = wp.components.TextareaControl;
	var SelectControl    = wp.components.SelectControl;
	var RangeControl     = wp.components.RangeControl;
	var ColorPalette     = wp.components.ColorPalette;
	var Button           = wp.components.Button;
	var CheckboxControl  = wp.components.CheckboxControl;
	var ToggleControl    = wp.components.ToggleControl;
	var FormTokenField   = wp.components.FormTokenField;
	var Notice           = wp.components.Notice;
	var FocalPointPicker = wp.components.FocalPointPicker;
	var BoxControl       = wp.components.BoxControl || wp.components.__experimentalBoxControl;

	var useSelect   = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var apiFetch    = wp.apiFetch;

	var ALLOWED = [
		'core/columns',
		'core/column',
		'core/heading',
		'core/paragraph',
		'core/image',
		'swish/ac-form'
	];

	var TEMPLATE = [
		[ 'core/heading',   { level: 2, content: 'Get our newsletter' } ],
		[ 'core/paragraph', { content: 'Sign up to hear about new trips.' } ],
		[ 'swish/ac-form' ]
	];

	// Format the padding attribute (object | number | string) into a CSS shorthand value.
	function paddingToCss( p ) {
		if ( p == null || p === '' ) return null;
		if ( typeof p === 'number' ) return p + 'px';
		if ( typeof p === 'string' ) return p;
		if ( typeof p === 'object' ) {
			var sides = [ 'top', 'right', 'bottom', 'left' ];
			var any   = false;
			var parts = sides.map( function ( s ) {
				var v = p[ s ];
				if ( v != null && v !== '' ) { any = true; return v; }
				return '0';
			} );
			return any ? parts.join( ' ' ) : null;
		}
		return null;
	}

	// Read/write a post meta key via core/editor.
	function useMeta( key ) {
		var value = useSelect( function ( select ) {
			var meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
			return meta ? meta[ key ] : undefined;
		}, [ key ] );
		var editPost = useDispatch( 'core/editor' ).editPost;
		function setValue( v ) {
			var update = {};
			update[ key ] = v;
			editPost( { meta: update } );
		}
		return [ value, setValue ];
	}

	function TargetingPanel() {
		var modeState = useMeta( '_swish_targeting_mode' );
		var urlsState = useMeta( '_swish_targeting_urls' );
		var ptState   = useMeta( '_swish_targeting_post_types' );
		var authState = useMeta( '_swish_targeting_auth' );

		var mode = modeState[0] || 'all';
		var urls = Array.isArray( urlsState[0] ) ? urlsState[0] : [];
		var pts  = Array.isArray( ptState[0] ) ? ptState[0] : [];
		var auth = authState[0] || 'any';

		return el( PanelBody, { title: __( 'Where to show', 'swish-active-campaign' ), initialOpen: false },
			el( SelectControl, {
				label: __( 'Targeting', 'swish-active-campaign' ),
				value: mode,
				options: [
					{ value: 'all',        label: __( 'All pages', 'swish-active-campaign' ) },
					{ value: 'urls',       label: __( 'Match URL patterns', 'swish-active-campaign' ) },
					{ value: 'post_types', label: __( 'Specific post types', 'swish-active-campaign' ) },
					{ value: 'exclude',    label: __( 'All pages except URL patterns', 'swish-active-campaign' ) }
				],
				onChange: modeState[1]
			} ),
			( mode === 'urls' || mode === 'exclude' ) && el( TextareaControl, {
				label: __( 'URL patterns (one per line, * wildcard)', 'swish-active-campaign' ),
				value: urls.join( '\n' ),
				onChange: function ( v ) {
					urlsState[1]( v.split( /\r?\n/ ).map( function ( s ) { return s.trim(); } ).filter( Boolean ) );
				},
				help: __( 'Example: /trips/*  or  /blog/welcome', 'swish-active-campaign' )
			} ),
			mode === 'post_types' && el( 'div', null,
				( cfg.postTypes || [] ).map( function ( pt ) {
					return el( CheckboxControl, {
						key: pt.value,
						label: pt.label,
						checked: pts.indexOf( pt.value ) !== -1,
						onChange: function ( on ) {
							var next = pts.slice();
							var i = next.indexOf( pt.value );
							if ( on && i === -1 ) next.push( pt.value );
							if ( ! on && i !== -1 ) next.splice( i, 1 );
							ptState[1]( next );
						}
					} );
				} )
			),
			el( SelectControl, {
				label: __( 'Show to', 'swish-active-campaign' ),
				value: auth,
				options: [
					{ value: 'any',        label: __( 'Everyone', 'swish-active-campaign' ) },
					{ value: 'logged_in',  label: __( 'Logged-in users only', 'swish-active-campaign' ) },
					{ value: 'logged_out', label: __( 'Logged-out users only', 'swish-active-campaign' ) }
				],
				onChange: authState[1]
			} )
		);
	}

	function TriggerPanel() {
		var typeState   = useMeta( '_swish_trigger_type' );
		var timeState   = useMeta( '_swish_trigger_time_seconds' );
		var scrollState = useMeta( '_swish_trigger_scroll_percent' );
		var clickState  = useMeta( '_swish_trigger_click_selector' );

		var type = typeState[0] || 'time';

		return el( PanelBody, { title: __( 'When to show', 'swish-active-campaign' ), initialOpen: false },
			el( SelectControl, {
				label: __( 'Trigger', 'swish-active-campaign' ),
				value: type,
				options: [
					{ value: 'time',   label: __( 'Time delay', 'swish-active-campaign' ) },
					{ value: 'scroll', label: __( 'Scroll depth', 'swish-active-campaign' ) },
					{ value: 'exit',   label: __( 'Exit intent', 'swish-active-campaign' ) },
					{ value: 'click',  label: __( 'Click on element', 'swish-active-campaign' ) }
				],
				onChange: typeState[1]
			} ),
			type === 'time' && el( TextControl, {
				type: 'number',
				label: __( 'Delay (seconds)', 'swish-active-campaign' ),
				value: timeState[0] != null ? timeState[0] : 5,
				onChange: function ( v ) { timeState[1]( parseInt( v, 10 ) || 0 ); }
			} ),
			type === 'scroll' && el( TextControl, {
				type: 'number',
				label: __( 'Scroll past (%)', 'swish-active-campaign' ),
				value: scrollState[0] != null ? scrollState[0] : 50,
				onChange: function ( v ) { scrollState[1]( parseInt( v, 10 ) || 0 ); }
			} ),
			type === 'click' && el( TextControl, {
				label: __( 'CSS selector', 'swish-active-campaign' ),
				value: clickState[0] || '',
				onChange: clickState[1],
				help: __( 'e.g. .lead-magnet-link, #signup-btn', 'swish-active-campaign' )
			} )
		);
	}

	function FrequencyPanel() {
		var daysState  = useMeta( '_swish_freq_dismiss_days' );
		var afterState = useMeta( '_swish_freq_hide_after_submit' );

		return el( PanelBody, { title: __( 'Frequency cap', 'swish-active-campaign' ), initialOpen: false },
			el( TextControl, {
				type: 'number',
				label: __( 'Hide for N days after dismissal', 'swish-active-campaign' ),
				value: daysState[0] != null ? daysState[0] : 7,
				onChange: function ( v ) { daysState[1]( parseInt( v, 10 ) || 0 ); }
			} ),
			el( ToggleControl, {
				label: __( 'Hide forever after submission', 'swish-active-campaign' ),
				checked: afterState[0] !== false,
				onChange: afterState[1]
			} )
		);
	}

	function ACPanel() {
		var listState = useMeta( '_swish_ac_list_id' );
		var tagsState = useMeta( '_swish_ac_tags' );
		var tagsValue = Array.isArray( tagsState[0] ) ? tagsState[0] : [];

		var s = useState( { loadingTags: false, loadingLists: false, tags: [], lists: [], error: '' } );
		var state = s[0], setState = s[1];

		function loadTags( refresh ) {
			setState( Object.assign( {}, state, { loadingTags: true } ) );
			apiFetch( { path: '/swish-ac/v1/ac-tags' + ( refresh ? '?refresh=1' : '' ) } )
				.then( function ( res ) {
					if ( res && res.ok ) {
						setState( function ( prev ) { return Object.assign( {}, prev, { loadingTags: false, tags: res.tags || [], error: '' } ); } );
					} else {
						setState( function ( prev ) { return Object.assign( {}, prev, { loadingTags: false, error: ( res && res.message ) || 'Tag fetch failed' } ); } );
					}
				} )
				.catch( function ( e ) {
					setState( function ( prev ) { return Object.assign( {}, prev, { loadingTags: false, error: ( e && e.message ) || 'Tag fetch failed' } ); } );
				} );
		}

		function loadLists( refresh ) {
			setState( function ( prev ) { return Object.assign( {}, prev, { loadingLists: true } ); } );
			apiFetch( { path: '/swish-ac/v1/ac-lists' + ( refresh ? '?refresh=1' : '' ) } )
				.then( function ( res ) {
					if ( res && res.ok ) {
						setState( function ( prev ) { return Object.assign( {}, prev, { loadingLists: false, lists: res.lists || [], error: '' } ); } );
					} else {
						setState( function ( prev ) { return Object.assign( {}, prev, { loadingLists: false, error: ( res && res.message ) || 'List fetch failed' } ); } );
					}
				} )
				.catch( function ( e ) {
					setState( function ( prev ) { return Object.assign( {}, prev, { loadingLists: false, error: ( e && e.message ) || 'List fetch failed' } ); } );
				} );
		}

		useEffect( function () { loadTags( false ); loadLists( false ); }, [] );

		var listOptions = [ { value: '', label: __( '— Use plugin default —', 'swish-active-campaign' ) } ];
		state.lists.forEach( function ( l ) {
			listOptions.push( { value: l.id, label: l.name + ' (#' + l.id + ')' } );
		} );
		// Preserve a saved list id that's no longer in the fetched set.
		if ( listState[0] && ! state.lists.some( function ( l ) { return l.id === listState[0]; } ) && state.lists.length ) {
			listOptions.push( { value: listState[0], label: __( 'Saved id: ', 'swish-active-campaign' ) + listState[0] } );
		}

		return el( PanelBody, { title: __( 'ActiveCampaign', 'swish-active-campaign' ), initialOpen: false },
			el( SelectControl, {
				label: __( 'List', 'swish-active-campaign' ),
				value: listState[0] || '',
				options: listOptions,
				onChange: listState[1],
				help: __( 'Choose a list from ActiveCampaign, or use the plugin default.', 'swish-active-campaign' )
			} ),
			state.error && el( Notice, { status: 'warning', isDismissible: false }, state.error ),
			el( FormTokenField, {
				label: __( 'Tags', 'swish-active-campaign' ),
				value: tagsValue,
				suggestions: state.tags,
				onChange: function ( v ) { tagsState[1]( v.map( String ) ); },
				__experimentalExpandOnFocus: true
			} ),
			el( Button, {
				variant: 'secondary',
				isBusy: state.loadingTags || state.loadingLists,
				onClick: function () { loadTags( true ); loadLists( true ); }
			}, __( 'Refresh from ActiveCampaign', 'swish-active-campaign' ) )
		);
	}

	// Deprecation v0: swish/popup before image/layout/imageOpacity were added.
	// Old saved content had only accentColor + successMessage. save() was unchanged
	// (InnerBlocks.Content), so we only need to migrate attributes forward.
	var deprecatedV0 = {
		attributes: {
			accentColor:    { type: 'string', default: '#ffba00' },
			successMessage: { type: 'string', default: 'Thanks! Check your inbox.' }
		},
		supports: { html: false, multiple: false, reusable: false, inserter: true },
		save: function () { return el( InnerBlocks.Content ); },
		migrate: function ( attrs, inner ) {
			return [
				Object.assign( {
					imageId: 0, imageUrl: '', imageAlt: '',
					layout: 'stack', imageOpacity: 0.4, width: 460,
					padding: { top: '28px', right: '28px', bottom: '28px', left: '28px' },
					imageHeight: 0, focalPoint: { x: 0.5, y: 0.5 }
				}, attrs ),
				inner
			];
		}
	};

	registerBlockType( 'swish/popup', {
		deprecated: [ deprecatedV0 ],
		edit: function ( props ) {
			var a   = props.attributes;
			var set = props.setAttributes;

			var fp = a.focalPoint || { x: 0.5, y: 0.5 };
			var fpStr = Math.round( fp.x * 100 ) + '% ' + Math.round( fp.y * 100 ) + '%';
			var paddingCss = paddingToCss( a.padding );
			var wrapperStyle = {
				'--swish-accent': a.accentColor,
				'--swish-popup-width':   ( a.width   || 460 ) + 'px',
				'--swish-popup-padding': paddingCss || '28px',
				'--swish-img-pos': fpStr,
				'--swish-img-height': a.imageHeight > 0 ? a.imageHeight + 'px' : 'auto'
			};
			if ( a.layout === 'background' && a.imageUrl ) {
				wrapperStyle[ '--swish-bg-image' ]   = 'url("' + a.imageUrl + '")';
				wrapperStyle[ '--swish-bg-opacity' ] = a.imageOpacity;
			}

			var blockProps = useBlockProps( {
				className: 'swish-ac-popup swish-ac-popup--layout-' + a.layout,
				style: wrapperStyle
			} );

			var innerBlocksProps = useInnerBlocksProps(
				{ className: 'swish-ac-popup__content' },
				{ allowedBlocks: ALLOWED, template: TEMPLATE, templateLock: false }
			);

			function imageControls() {
				return el( MediaUploadCheck, null,
					el( MediaUpload, {
						onSelect: function ( media ) {
							set( { imageId: media.id, imageUrl: media.url, imageAlt: media.alt || '' } );
						},
						allowedTypes: [ 'image' ],
						value: a.imageId,
						render: function ( o ) {
							var hasImage = !! a.imageId;
							return el( Fragment, null,
								el( Button, { variant: 'secondary', onClick: o.open },
									hasImage ? __( 'Replace image', 'swish-active-campaign' ) : __( 'Select image', 'swish-active-campaign' )
								),
								hasImage ? el( Button, {
									variant: 'tertiary',
									isDestructive: true,
									onClick: function () { set( { imageId: 0, imageUrl: '', imageAlt: '' } ); }
								}, __( 'Remove', 'swish-active-campaign' ) ) : null
							);
						}
					} )
				);
			}

			var inspector = el( InspectorControls, null,
				el( PanelBody, { title: __( 'Image', 'swish-active-campaign' ), initialOpen: true },
					imageControls(),
					a.imageUrl ? el( TextControl, {
						label: __( 'Alt text', 'swish-active-campaign' ),
						value: a.imageAlt,
						onChange: function ( v ) { set( { imageAlt: v } ); }
					} ) : null,
					a.imageUrl ? el( RangeControl, {
						label: __( 'Image height (px, 0 = auto)', 'swish-active-campaign' ),
						value: a.imageHeight || 0,
						onChange: function ( v ) { set( { imageHeight: parseInt( v, 10 ) || 0 } ); },
						min: 0, max: 500, step: 10,
						help: __( 'Set a height to crop the image; uses the focal point below.', 'swish-active-campaign' )
					} ) : null,
					a.imageUrl && FocalPointPicker ? el( FocalPointPicker, {
						label: __( 'Focal point', 'swish-active-campaign' ),
						url: a.imageUrl,
						value: a.focalPoint || { x: 0.5, y: 0.5 },
						onChange: function ( v ) { set( { focalPoint: v } ); }
					} ) : null
				),
				el( PanelBody, { title: __( 'Layout', 'swish-active-campaign' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Style', 'swish-active-campaign' ),
						value: a.layout,
						options: [
							{ value: 'stack',      label: __( 'Image on top', 'swish-active-campaign' ) },
							{ value: 'two-column', label: __( 'Two column (image left)', 'swish-active-campaign' ) },
							{ value: 'background', label: __( 'Image as background', 'swish-active-campaign' ) }
						],
						onChange: function ( v ) { set( { layout: v } ); }
					} ),
					a.layout === 'background' && el( RangeControl, {
						label: __( 'Image opacity', 'swish-active-campaign' ),
						value: a.imageOpacity,
						onChange: function ( v ) { set( { imageOpacity: v } ); },
						min: 0, max: 1, step: 0.05
					} ),
					el( RangeControl, {
						label: __( 'Width (px)', 'swish-active-campaign' ),
						value: a.width || 460,
						onChange: function ( v ) { set( { width: parseInt( v, 10 ) || 460 } ); },
						min: 320, max: 900, step: 20
					} ),
					BoxControl ? el( BoxControl, {
						label: __( 'Padding', 'swish-active-campaign' ),
						values: ( a.padding && typeof a.padding === 'object' )
							? a.padding
							: { top: '28px', right: '28px', bottom: '28px', left: '28px' },
						onChange: function ( next ) { set( { padding: next } ); },
						allowReset: true,
						units: [
							{ value: 'px', label: 'px' },
							{ value: '%',  label: '%' },
							{ value: 'em', label: 'em' },
							{ value: 'rem', label: 'rem' }
						]
					} ) : null
				),
				el( PanelBody, { title: __( 'Popup style', 'swish-active-campaign' ), initialOpen: false },
					el( 'label', { className: 'components-base-control__label' },
						__( 'Accent color', 'swish-active-campaign' )
					),
					el( ColorPalette, {
						value: a.accentColor,
						onChange: function ( v ) { set( { accentColor: v || '#ffba00' } ); }
					} ),
					el( TextControl, {
						label: __( 'Success message', 'swish-active-campaign' ),
						value: a.successMessage,
						onChange: function ( v ) { set( { successMessage: v } ); }
					} )
				),
				el( TargetingPanel ),
				el( TriggerPanel ),
				el( FrequencyPanel ),
				el( ACPanel )
			);

			var image = a.imageUrl
				? el( 'img', { className: 'swish-ac-popup__image', src: a.imageUrl, alt: a.imageAlt } )
				: null;

			var body;
			if ( a.layout === 'two-column' ) {
				body = el( 'div', blockProps,
					el( 'div', { className: 'swish-ac-popup__media' }, image ),
					el( 'div', innerBlocksProps )
				);
			} else if ( a.layout === 'background' ) {
				body = el( 'div', blockProps,
					el( 'div', innerBlocksProps )
				);
			} else {
				body = el( 'div', blockProps,
					image,
					el( 'div', innerBlocksProps )
				);
			}

			return el( Fragment, null, inspector, body );
		},
		save: function () {
			return el( InnerBlocks.Content );
		}
	} );
} )( window.wp );
