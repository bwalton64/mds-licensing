/**
 * MDS Licensing — Release Tools ▾ Sync, Clearances, Split Sheets & Content ID Modal.
 */
( function ( wp ) {
	'use strict';

	const { createElement: el, Fragment, useEffect, useState } = wp.element;
	const {
		Button,
		CheckboxControl,
		Modal,
		Notice,
		SelectControl,
		Spinner,
		TabPanel,
		TextControl,
		TextareaControl,
		ToggleControl,
	} = wp.components;
	const apiFetch = wp.apiFetch;
	const { __ } = wp.i18n;
	const speak = wp.a11y && typeof wp.a11y.speak === 'function' ? wp.a11y.speak : function () {};

	const boot = window.mdsLicensingEditor || {};
	const restBase = boot.restBase || '/mds-lic/v1';

	const STEM_LABELS = {
		main: 'Main Mix',
		instrumental: 'Instrumental',
		tv_track: 'TV Track (No Lead Vocals)',
		acapella: 'Acapella',
		cutdown_30s: '30s Cutdown',
		cutdown_60s: '60s Cutdown',
		multitrack_stems: 'Full Multitrack Stems',
	};

	function downloadBlob( filename, content, mime ) {
		const blob = new Blob( [ content ], { type: mime } );
		const url = URL.createObjectURL( blob );
		const a = document.createElement( 'a' );
		a.href = url;
		a.download = filename;
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		URL.revokeObjectURL( url );
	}

	function openPrintWindow( html ) {
		const w = window.open( '', '_blank', 'width=920,height=720' );
		if ( ! w ) {
			return;
		}
		w.document.open();
		w.document.write( html );
		w.document.close();
		w.focus();
	}

	function calc2026Mechanical( units, durSec ) {
		const u = Math.max( 0, parseInt( units, 10 ) || 0 );
		const d = Math.max( 0, parseInt( durSec, 10 ) || 0 );
		let rate = 0.131;
		if ( d > 300 ) {
			rate = Math.max( 0.131, Math.ceil( d / 60 ) * 0.0252 );
		}
		return ( u * rate ).toFixed( 2 );
	}

	function LicensingHubApp() {
		const postId = parseInt( boot.postId, 10 ) || 0;

		const [ open, setOpen ] = useState( false );
		const [ initialTab, setInitialTab ] = useState( 'sync' );
		const [ loading, setLoading ] = useState( false );
		const [ saving, setSaving ] = useState( false );
		const [ error, setError ] = useState( '' );
		const [ notice, setNotice ] = useState( '' );

		const [ sync, setSync ] = useState( {} );
		const [ moodsText, setMoodsText ] = useState( '' );
		const [ instrumentsText, setInstrumentsText ] = useState( '' );
		const [ clearances, setClearances ] = useState( { covers: [], samples: [] } );
		const [ allowlist, setAllowlist ] = useState( [] );
		const [ exportsData, setExportsData ] = useState( null );

		const openOnTab = ( tabKey ) => {
			setInitialTab( tabKey || 'sync' );
			setOpen( true );
		};

		useEffect( () => {
			function registerTools() {
				if ( ! window.mdsRmReleaseTools || postId <= 0 ) {
					return;
				}
				const catSync = __( 'Sync & Licensing', 'mds-licensing' );
				window.mdsRmReleaseTools.register( {
					id: 'mds-lic-sync-hub',
					category: catSync,
					icon: 'dashicons dashicons-tickets-alt',
					label: __( 'Sync Profile, DISCO.ac CSV & Cue Sheet', 'mds-licensing' ),
					description: __( 'Manage One-Stop rights, BPM/Key/Moods/Stems & export DISCO.ac CSV and Broadcast Cue Sheets.', 'mds-licensing' ),
					onSelect: () => openOnTab( 'sync' ),
				} );
				window.mdsRmReleaseTools.register( {
					id: 'mds-lic-clearances',
					category: catSync,
					icon: 'dashicons dashicons-clipboard',
					label: __( 'Covers (2026 CRB), Samples & Split Sheet', 'mds-licensing' ),
					description: __( 'Calculate 2026 statutory mechanicals ($0.131), track sample clearances & print Split Sheet Agreements.', 'mds-licensing' ),
					onSelect: () => openOnTab( 'clearances' ),
				} );
				window.mdsRmReleaseTools.register( {
					id: 'mds-lic-content-id',
					category: catSync,
					icon: 'dashicons dashicons-shield',
					label: __( 'YouTube / Meta Content ID Allowlist', 'mds-licensing' ),
					description: __( 'Whitelist licensed client/brand channels from Content ID claims & export CMS CSV.', 'mds-licensing' ),
					onSelect: () => openOnTab( 'allowlist' ),
				} );
			}
			registerTools();
			window.addEventListener( 'mds-rm:release-editor-ready', registerTools );
			return () => window.removeEventListener( 'mds-rm:release-editor-ready', registerTools );
		}, [ postId ] );

		useEffect( () => {
			if ( ! open || postId <= 0 ) {
				return;
			}
			setLoading( true );
			setError( '' );
			setNotice( '' );
			apiFetch( { path: restBase + '/releases/' + postId + '/licensing' } )
				.then( ( res ) => {
					const s = res.sync || {};
					setSync( s );
					setMoodsText( Array.isArray( s.moods ) ? s.moods.join( ', ' ) : '' );
					setInstrumentsText( Array.isArray( s.instruments ) ? s.instruments.join( ', ' ) : '' );
					setClearances( res.clearances || { covers: [], samples: [] } );
					setAllowlist( Array.isArray( res.allowlist ) ? res.allowlist : [] );
					setExportsData( res.exports || null );
				} )
				.catch( ( err ) => setError( ( err && err.message ) || __( 'Failed to load licensing data.', 'mds-licensing' ) ) )
				.finally( () => setLoading( false ) );
		}, [ open, postId ] );

		const handleSaveAll = () => {
			setSaving( true );
			setError( '' );
			setNotice( '' );
			const syncPayload = Object.assign( {}, sync, {
				moods: moodsText.split( ',' ).map( ( x ) => x.trim() ).filter( Boolean ),
				instruments: instrumentsText.split( ',' ).map( ( x ) => x.trim() ).filter( Boolean ),
			} );
			apiFetch( {
				path: restBase + '/releases/' + postId + '/licensing',
				method: 'POST',
				data: {
					sync: syncPayload,
					clearances,
					allowlist,
				},
			} )
				.then( ( res ) => {
					if ( res.sync ) {
						setSync( res.sync );
					}
					if ( res.clearances ) {
						setClearances( res.clearances );
					}
					if ( res.allowlist ) {
						setAllowlist( res.allowlist );
					}
					if ( res.exports ) {
						setExportsData( res.exports );
					}
					const msg = ( res && res.message ) || __( 'Licensing saved.', 'mds-licensing' );
					setNotice( msg );
					speak( msg );
				} )
				.catch( ( err ) => setError( ( err && err.message ) || __( 'Failed to save licensing data.', 'mds-licensing' ) ) )
				.finally( () => setSaving( false ) );
		};

		if ( ! open ) {
			return null;
		}

		const covers = Array.isArray( clearances.covers ) ? clearances.covers : [];
		const samples = Array.isArray( clearances.samples ) ? clearances.samples : [];
		const stems = sync.stems || {};

		return el(
			Modal,
			{
				title: __( 'Sync Licensing, Clearances, Split Sheets & Content ID', 'mds-licensing' ),
				onRequestClose: () => setOpen( false ),
				className: 'mds-lic-modal',
				style: { maxWidth: '900px', width: '95vw' },
			},
			loading
				? el( 'div', { style: { padding: '24px', textAlign: 'center' } }, el( Spinner ) )
				: el(
					Fragment,
					null,
					error && el( Notice, { status: 'error', isDismissible: true, onRemove: () => setError( '' ) }, error ),
					notice && el( Notice, { status: 'success', isDismissible: true, onRemove: () => setNotice( '' ) }, notice ),

					el(
						'div',
						{ style: { display: 'flex', justifyContent: 'flex-end', marginBottom: '10px', gap: '8px', flexWrap: 'wrap' } },
						exportsData && el(
							Button,
							{ variant: 'secondary', onClick: () => openPrintWindow( exportsData.split_sheet_html ) },
							__( 'Print Split Sheet Agreement', 'mds-licensing' )
						),
						el(
							Button,
							{ variant: 'primary', isBusy: saving, disabled: saving, onClick: handleSaveAll },
							saving ? __( 'Saving…', 'mds-licensing' ) : __( 'Save All Licensing Data', 'mds-licensing' )
						)
					),

					el(
						TabPanel,
						{
							initialTabName: initialTab,
							tabs: [
								{ name: 'sync', title: __( 'Sync Profile & DISCO / Cue Sheet', 'mds-licensing' ) },
								{ name: 'clearances', title: __( 'Covers (2026 CRB) & Samples', 'mds-licensing' ) },
								{ name: 'allowlist', title: __( 'Content ID Allowlist', 'mds-licensing' ) },
							],
						},
						( tab ) => {
							if ( tab.name === 'sync' ) {
								return el(
									'div',
									{ style: { paddingTop: '12px' } },
									el(
										'div',
										{ style: { display: 'flex', gap: '18px', flexWrap: 'wrap', marginBottom: '12px' } },
										el( ToggleControl, { label: __( 'Available for Sync Licensing', 'mds-licensing' ), checked: Boolean( sync.sync_available ), onChange: ( v ) => setSync( Object.assign( {}, sync, { sync_available: v } ) ) } ),
										el( ToggleControl, { label: __( 'One-Stop (100% Master + Pub)', 'mds-licensing' ), checked: Boolean( sync.one_stop ), onChange: ( v ) => setSync( Object.assign( {}, sync, { one_stop: v } ) ) } ),
										el( ToggleControl, { label: __( 'Easy-Clear (Pre-Approved Co-Writers)', 'mds-licensing' ), checked: Boolean( sync.easy_clear ), onChange: ( v ) => setSync( Object.assign( {}, sync, { easy_clear: v } ) ) } )
									),
									el(
										'div',
										{ style: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(170px, 1fr))', gap: '10px' } },
										el( TextControl, { label: __( 'Master Ownership %', 'mds-licensing' ), type: 'number', value: String( sync.master_pct ?? 100 ), onChange: ( v ) => setSync( Object.assign( {}, sync, { master_pct: parseFloat( v ) || 0 } ) ) } ),
										el( TextControl, { label: __( 'Publishing Ownership %', 'mds-licensing' ), type: 'number', value: String( sync.publishing_pct ?? 100 ), onChange: ( v ) => setSync( Object.assign( {}, sync, { publishing_pct: parseFloat( v ) || 0 } ) ) } ),
										el( TextControl, { label: __( 'BPM', 'mds-licensing' ), type: 'number', value: String( sync.bpm ?? 0 ), onChange: ( v ) => setSync( Object.assign( {}, sync, { bpm: parseInt( v, 10 ) || 0 } ) ) } ),
										el( TextControl, { label: __( 'Musical Key', 'mds-licensing' ), placeholder: 'Am / C Major', value: sync.musical_key || '', onChange: ( v ) => setSync( Object.assign( {}, sync, { musical_key: v } ) ) } ),
										el( SelectControl, {
											label: __( 'Vocals', 'mds-licensing' ),
											value: sync.vocals || 'vocals',
											options: [
												{ label: __( 'Vocals', 'mds-licensing' ), value: 'vocals' },
												{ label: __( 'Instrumental', 'mds-licensing' ), value: 'instrumental' },
												{ label: __( 'Vocals + Instrumental Available', 'mds-licensing' ), value: 'both' },
											],
											onChange: ( v ) => setSync( Object.assign( {}, sync, { vocals: v } ) ),
										} )
									),
									el(
										'div',
										{ style: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '10px' } },
										el( TextControl, { label: __( 'Clearance Contact Name', 'mds-licensing' ), value: sync.clearance_name || '', onChange: ( v ) => setSync( Object.assign( {}, sync, { clearance_name: v } ) ) } ),
										el( TextControl, { label: __( 'Clearance Contact Email', 'mds-licensing' ), value: sync.clearance_email || '', onChange: ( v ) => setSync( Object.assign( {}, sync, { clearance_email: v } ) ) } ),
										el( TextControl, { label: __( 'Clearance Phone', 'mds-licensing' ), value: sync.clearance_phone || '', onChange: ( v ) => setSync( Object.assign( {}, sync, { clearance_phone: v } ) ) } ),
										el( TextControl, { label: __( 'Moods (comma-separated)', 'mds-licensing' ), placeholder: 'Epic, Tense, Uplifting', value: moodsText, onChange: setMoodsText } ),
										el( TextControl, { label: __( 'Instruments (comma-separated)', 'mds-licensing' ), placeholder: 'Synth, Electric Guitar, Drums', value: instrumentsText, onChange: setInstrumentsText } ),
										el( TextControl, { label: __( 'Sounds Like / FFO', 'mds-licensing' ), value: sync.sounds_like || '', onChange: ( v ) => setSync( Object.assign( {}, sync, { sounds_like: v } ) ) } )
									),
									el( 'h4', { style: { margin: '10px 0 6px' } }, __( 'Stems & Alternate Versions Available', 'mds-licensing' ) ),
									el(
										'div',
										{ style: { display: 'flex', gap: '14px', flexWrap: 'wrap', marginBottom: '10px' } },
										Object.keys( STEM_LABELS ).map( ( key ) => el( CheckboxControl, {
											key,
											label: STEM_LABELS[ key ],
											checked: Boolean( stems[ key ] ),
											onChange: ( v ) => setSync( Object.assign( {}, sync, { stems: Object.assign( {}, stems, { [ key ]: v } ) } ) ),
										} ) )
									),
									el( TextareaControl, { label: __( 'Music Supervisor Pitch Notes', 'mds-licensing' ), rows: 3, value: sync.pitch_notes || '', onChange: ( v ) => setSync( Object.assign( {}, sync, { pitch_notes: v } ) ) } ),
									exportsData && el(
										'div',
										{ style: { display: 'flex', gap: '8px', flexWrap: 'wrap', marginTop: '10px', borderTop: '1px solid #dcdcde', paddingTop: '10px' } },
										el( Button, { variant: 'secondary', onClick: () => downloadBlob( 'release-' + postId + '-disco.csv', exportsData.disco_csv || '', 'text/csv;charset=utf-8' ) }, __( 'Download DISCO.ac CSV', 'mds-licensing' ) ),
										el( Button, { variant: 'secondary', onClick: () => downloadBlob( 'release-' + postId + '-cue-sheet.csv', exportsData.cue_sheet_csv || '', 'text/csv;charset=utf-8' ) }, __( 'Download Broadcast Cue Sheet CSV', 'mds-licensing' ) )
									)
								);
							}

							if ( tab.name === 'clearances' ) {
								return el(
									'div',
									{ style: { paddingTop: '12px' } },
									el( 'h4', { style: { marginTop: 0 } }, __( 'Cover Songs & 2026 US CRB Mechanical Calculator ($0.131/trk or $0.0252/min > 5m)', 'mds-licensing' ) ),
									covers.map( ( c, idx ) => el(
										'div',
										{ key: idx, style: { border: '1px solid #dcdcde', borderRadius: '4px', padding: '10px', marginBottom: '8px', background: '#fff' } },
										el(
											'div',
											{ style: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: '8px' } },
											el( TextControl, { label: __( 'Original Song Title', 'mds-licensing' ), value: c.original_title || '', onChange: ( v ) => { const n = covers.slice(); n[ idx ] = Object.assign( {}, n[ idx ], { original_title: v } ); setClearances( Object.assign( {}, clearances, { covers: n } ) ); } } ),
											el( TextControl, { label: __( 'Original Writer / Publisher', 'mds-licensing' ), value: c.original_writer || '', onChange: ( v ) => { const n = covers.slice(); n[ idx ] = Object.assign( {}, n[ idx ], { original_writer: v } ); setClearances( Object.assign( {}, clearances, { covers: n } ) ); } } ),
											el( TextControl, { label: __( 'Provider (HFA / Easy Song)', 'mds-licensing' ), value: c.provider || 'Easy Song', onChange: ( v ) => { const n = covers.slice(); n[ idx ] = Object.assign( {}, n[ idx ], { provider: v } ); setClearances( Object.assign( {}, clearances, { covers: n } ) ); } } ),
											el( TextControl, { label: __( 'License #', 'mds-licensing' ), value: c.license_number || '', onChange: ( v ) => { const n = covers.slice(); n[ idx ] = Object.assign( {}, n[ idx ], { license_number: v } ); setClearances( Object.assign( {}, clearances, { covers: n } ) ); } } ),
											el( TextControl, { label: __( 'Units (Physical/DL)', 'mds-licensing' ), type: 'number', value: String( c.units_licensed || 0 ), onChange: ( v ) => { const n = covers.slice(); n[ idx ] = Object.assign( {}, n[ idx ], { units_licensed: parseInt( v, 10 ) || 0 } ); setClearances( Object.assign( {}, clearances, { covers: n } ) ); } } ),
											el( TextControl, { label: __( 'Duration (sec)', 'mds-licensing' ), type: 'number', value: String( c.duration_seconds || 0 ), onChange: ( v ) => { const n = covers.slice(); n[ idx ] = Object.assign( {}, n[ idx ], { duration_seconds: parseInt( v, 10 ) || 0 } ); setClearances( Object.assign( {}, clearances, { covers: n } ) ); } } )
										),
										el(
											'div',
											{ style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '4px' } },
											el( 'strong', null, __( 'Est. 2026 Statutory Mechanical: $', 'mds-licensing' ) + calc2026Mechanical( c.units_licensed, c.duration_seconds ) ),
											el( Button, { isDestructive: true, size: 'small', variant: 'link', onClick: () => setClearances( Object.assign( {}, clearances, { covers: covers.filter( ( _, i ) => i !== idx ) } ) ) }, __( 'Remove', 'mds-licensing' ) )
										)
									) ),
									el(
										Button,
										{ variant: 'secondary', onClick: () => setClearances( Object.assign( {}, clearances, { covers: covers.concat( [ { original_title: '', original_writer: '', provider: 'Easy Song', license_number: '', units_licensed: 500, duration_seconds: 210, status: 'pending' } ] ) } ) ) },
										__( '+ Add Cover Mechanical Entry', 'mds-licensing' )
									),

									el( 'h4', { style: { borderTop: '1px solid #dcdcde', paddingTop: '12px', marginTop: '16px' } }, __( 'Sample & Interpolation Clearance Ledger', 'mds-licensing' ) ),
									samples.map( ( s, idx ) => el(
										'div',
										{ key: idx, style: { display: 'grid', gridTemplateColumns: '1.3fr 1.2fr 1fr 0.8fr auto', gap: '6px', marginBottom: '6px', alignItems: 'center' } },
										el( TextControl, { placeholder: __( 'Sampled Work Title', 'mds-licensing' ), value: s.sampled_work || '', onChange: ( v ) => { const n = samples.slice(); n[ idx ] = Object.assign( {}, n[ idx ], { sampled_work: v } ); setClearances( Object.assign( {}, clearances, { samples: n } ) ); } } ),
										el( TextControl, { placeholder: __( 'Original Artist', 'mds-licensing' ), value: s.sampled_artist || '', onChange: ( v ) => { const n = samples.slice(); n[ idx ] = Object.assign( {}, n[ idx ], { sampled_artist: v } ); setClearances( Object.assign( {}, clearances, { samples: n } ) ); } } ),
										el( SelectControl, { value: s.clearance_status || 'pending', options: [ { label: 'Pending', value: 'pending' }, { label: 'Cleared', value: 'cleared' }, { label: 'Buyout', value: 'buyout' }, { label: 'Royalty Share', value: 'royalty_share' } ], onChange: ( v ) => { const n = samples.slice(); n[ idx ] = Object.assign( {}, n[ idx ], { clearance_status: v } ); setClearances( Object.assign( {}, clearances, { samples: n } ) ); } } ),
										el( TextControl, { placeholder: __( 'Share %', 'mds-licensing' ), type: 'number', value: String( s.share_pct || 0 ), onChange: ( v ) => { const n = samples.slice(); n[ idx ] = Object.assign( {}, n[ idx ], { share_pct: parseFloat( v ) || 0 } ); setClearances( Object.assign( {}, clearances, { samples: n } ) ); } } ),
										el( Button, { isDestructive: true, size: 'small', variant: 'tertiary', onClick: () => setClearances( Object.assign( {}, clearances, { samples: samples.filter( ( _, i ) => i !== idx ) } ) ) }, '×' )
									) ),
									el(
										Button,
										{ variant: 'secondary', onClick: () => setClearances( Object.assign( {}, clearances, { samples: samples.concat( [ { sampled_work: '', sampled_artist: '', clearance_status: 'pending', share_pct: 0 } ] ) } ) ) },
										__( '+ Add Sample / Interpolation', 'mds-licensing' )
									)
								);
							}

							return el(
								'div',
								{ style: { paddingTop: '12px' } },
								el( 'p', { style: { marginTop: 0, color: '#50575e' } }, __( 'Record licensed client/creator YouTube channels or video URLs exempted from automated Content ID claims.', 'mds-licensing' ) ),
								allowlist.map( ( row, idx ) => el(
									'div',
									{ key: idx, style: { display: 'grid', gridTemplateColumns: '1fr 1.2fr 1.6fr 0.9fr auto', gap: '6px', marginBottom: '6px', alignItems: 'center' } },
									el( SelectControl, { value: row.platform || 'YouTube Content ID', options: [ { label: 'YouTube Content ID', value: 'YouTube Content ID' }, { label: 'Meta Rights Manager', value: 'Meta Rights Manager' }, { label: 'TikTok', value: 'TikTok' } ], onChange: ( v ) => { const n = allowlist.slice(); n[ idx ] = Object.assign( {}, n[ idx ], { platform: v } ); setAllowlist( n ); } } ),
									el( TextControl, { placeholder: __( 'Client / Brand', 'mds-licensing' ), value: row.client_name || '', onChange: ( v ) => { const n = allowlist.slice(); n[ idx ] = Object.assign( {}, n[ idx ], { client_name: v } ); setAllowlist( n ); } } ),
									el( TextControl, { placeholder: __( 'Channel ID or Video URL', 'mds-licensing' ), value: row.channel_or_video_url || '', onChange: ( v ) => { const n = allowlist.slice(); n[ idx ] = Object.assign( {}, n[ idx ], { channel_or_video_url: v } ); setAllowlist( n ); } } ),
									el( TextControl, { placeholder: __( 'License Ref', 'mds-licensing' ), value: row.license_ref || '', onChange: ( v ) => { const n = allowlist.slice(); n[ idx ] = Object.assign( {}, n[ idx ], { license_ref: v } ); setAllowlist( n ); } } ),
									el( Button, { isDestructive: true, size: 'small', variant: 'tertiary', onClick: () => setAllowlist( allowlist.filter( ( _, i ) => i !== idx ) ) }, '×' )
								) ),
								el(
									'div',
									{ style: { display: 'flex', gap: '8px', marginTop: '10px' } },
									el( Button, { variant: 'secondary', onClick: () => setAllowlist( allowlist.concat( [ { platform: 'YouTube Content ID', client_name: '', channel_or_video_url: '', license_ref: '', active: true } ] ) ) }, __( '+ Add Allowlist Entry', 'mds-licensing' ) ),
									exportsData && el( Button, { variant: 'secondary', onClick: () => downloadBlob( 'release-' + postId + '-content-id-allowlist.csv', exportsData.allowlist_csv || '', 'text/csv;charset=utf-8' ) }, __( 'Download Content ID Allowlist CSV', 'mds-licensing' ) )
								)
							);
						}
					)
				)
		);
	}

	function mountLicensingHub() {
		const root = document.createElement( 'div' );
		root.id = 'mds-lic-hub-root';
		document.body.appendChild( root );
		if ( wp.element.createRoot ) {
			wp.element.createRoot( root ).render( el( LicensingHubApp ) );
		} else if ( wp.element.render ) {
			wp.element.render( el( LicensingHubApp ), root );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', mountLicensingHub );
	} else {
		mountLicensingHub();
	}
} )( window.wp );
