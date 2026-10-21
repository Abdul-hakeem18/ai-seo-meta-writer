/* global aismw */
( function () {
	'use strict';

	const box = document.querySelector( '.aismw' );
	if ( ! box ) {
		return;
	}

	const postId = box.dataset.post;
	const text = box.querySelector( '#aismw-text' );
	const keyword = box.querySelector( '#aismw-keyword' );
	const count = box.querySelector( '.aismw-count' );
	const status = box.querySelector( '.aismw-status' );
	const generateBtn = box.querySelector( '#aismw-generate' );
	const saveBtn = box.querySelector( '#aismw-save' );

	function updateCount() {
		const n = text.value.length;
		count.textContent = n + ' / ' + aismw.max;
		count.classList.toggle( 'is-long', n > aismw.max );
	}

	function setBusy( busy ) {
		generateBtn.disabled = busy;
		saveBtn.disabled = busy;
	}

	async function send( action, data ) {
		const body = new URLSearchParams( Object.assign( { action: action, nonce: aismw.nonce, post_id: postId }, data ) );
		const res = await fetch( aismw.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } );
		const json = await res.json().catch( () => null );
		if ( ! json || ! json.success ) {
			throw new Error( ( json && json.data && json.data.message ) || aismw.i18n.error );
		}
		return json.data;
	}

	generateBtn.addEventListener( 'click', async function () {
		setBusy( true );
		status.textContent = aismw.i18n.working;
		try {
			const data = await send( 'aismw_generate', { keyword: keyword.value } );
			text.value = data.description;
			updateCount();
			status.textContent = '';
		} catch ( e ) {
			status.textContent = e.message;
		}
		setBusy( false );
	} );

	saveBtn.addEventListener( 'click', async function () {
		setBusy( true );
		try {
			await send( 'aismw_save', { description: text.value } );
			status.textContent = aismw.i18n.saved;
		} catch ( e ) {
			status.textContent = e.message;
		}
		setBusy( false );
	} );

	text.addEventListener( 'input', updateCount );
	updateCount();
}() );
