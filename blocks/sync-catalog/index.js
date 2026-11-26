/**
 * Block editor registration for `mds-lic/sync-catalog`.
 */
( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor || {};
	const { PanelBody, TextControl, ToggleControl } = wp.components || {};
	const { __ } = wp.i18n;

	registerBlockType( 'mds-lic/sync-catalog', {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps ? useBlockProps() : {};
			return el(
				Fragment,
				null,
				InspectorControls && el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Sync Catalog Settings', 'mds-licensing' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Max Releases', 'mds-licensing' ),
							type: 'number',
							value: String( attributes.limit || 24 ),
							onChange: ( v ) => setAttributes( { limit: Math.max( 1, parseInt( v, 10 ) || 24 ) } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show One-Stop Releases Only', 'mds-licensing' ),
							checked: Boolean( attributes.oneStopOnly ),
							onChange: ( v ) => setAttributes( { oneStopOnly: v } ),
						} ),
						el( ToggleControl, {
							label: __( 'Enable Sync Quote Request Button', 'mds-licensing' ),
							checked: attributes.showQuote !== false,
							onChange: ( v ) => setAttributes( { showQuote: v } ),
						} )
					)
				),
				el(
					'div',
					Object.assign( {}, blockProps, {
						style: { border: '1px dashed #8c8f94', borderRadius: '6px', padding: '16px', background: '#f6f7f7' },
					} ),
					el( 'strong', null, __( 'Sync Licensing Catalog & Quote Request', 'mds-licensing' ) ),
					el( 'p', { style: { margin: '6px 0 0', fontSize: '13px', color: '#50575e' } }, __( 'Renders sync-cleared releases with live One-Stop/Mood/Stems filters and a Sync Quote Request modal.', 'mds-licensing' ) )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
