/**
 * MDS Licensing — Frontend Sync Catalog filtering & Quote Request dialog controller.
 */
( function () {
	'use strict';

	function initCatalog( root ) {
		const searchInput = root.querySelector( '.mds-lic-catalog__search' );
		const oneStopCb = root.querySelector( '.mds-lic-catalog__onestop' );
		const stemsCb = root.querySelector( '.mds-lic-catalog__stems' );
		const cards = Array.from( root.querySelectorAll( '.mds-lic-catalog__card' ) );

		function applyFilter() {
			const q = ( ( searchInput && searchInput.value ) || '' ).trim().toLowerCase();
			const onlyOneStop = Boolean( oneStopCb && oneStopCb.checked );
			const onlyStems = Boolean( stemsCb && stemsCb.checked );
			cards.forEach( ( card ) => {
				const hay = card.dataset.search || '';
				const matchQ = ! q || hay.indexOf( q ) !== -1;
				const matchOneStop = ! onlyOneStop || card.dataset.onestop === '1';
				const matchStems = ! onlyStems || card.dataset.instrumental === '1';
				card.hidden = ! ( matchQ && matchOneStop && matchStems );
			} );
		}

		if ( searchInput ) {
			searchInput.addEventListener( 'input', applyFilter );
		}
		if ( oneStopCb ) {
			oneStopCb.addEventListener( 'change', applyFilter );
		}
		if ( stemsCb ) {
			stemsCb.addEventListener( 'change', applyFilter );
		}

		const dialog = root.querySelector( '.mds-lic-dialog' );
		const form = root.querySelector( '.mds-lic-quote-form' );
		const targetEl = root.querySelector( '.mds-lic-quote-target' );
		const statusEl = root.querySelector( '.mds-lic-quote-status' );
		const closeBtn = root.querySelector( '.mds-lic-dialog__close' );
		const endpoint = root.dataset.quoteEndpoint || '/wp-json/mds-lic/v1/sync-quote';

		if ( ! dialog || ! form ) {
			return;
		}

		root.querySelectorAll( '.mds-lic-catalog__quote-btn[data-release-id]' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const idInput = form.querySelector( 'input[name="release_id"]' );
				if ( idInput ) {
					idInput.value = btn.dataset.releaseId || '0';
				}
				if ( targetEl ) {
					targetEl.textContent = btn.dataset.releaseTitle || '';
				}
				if ( statusEl ) {
					statusEl.textContent = '';
				}
				if ( typeof dialog.showModal === 'function' ) {
					dialog.showModal();
				}
			} );
		} );

		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', () => dialog.close() );
		}

		form.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			const data = {};
			new FormData( form ).forEach( ( val, key ) => {
				data[ key ] = String( val );
			} );
			if ( statusEl ) {
				statusEl.textContent = 'Sending…';
			}
			fetch( endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( data ),
			} )
				.then( ( r ) => r.json().then( ( body ) => ( { ok: r.ok, body } ) ) )
				.then( ( res ) => {
					if ( ! res.ok ) {
						throw new Error( ( res.body && res.body.message ) || 'Submission failed.' );
					}
					if ( statusEl ) {
						statusEl.textContent = ( res.body && res.body.message ) || 'Quote request sent!';
					}
					form.reset();
				} )
				.catch( ( err ) => {
					if ( statusEl ) {
						statusEl.textContent = err.message || 'Submission failed.';
					}
				} );
		} );
	}

	function boot() {
		document.querySelectorAll( '.mds-lic-catalog:not([data-mds-lic-init])' ).forEach( ( root ) => {
			root.dataset.mdsLicInit = '1';
			initCatalog( root );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
