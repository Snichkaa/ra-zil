( function () {
	'use strict';
	var config = window.razilAnalytics || {};
	var topics = [ 'general', 'price', 'hall', 'visit' ];
	function emit( event, data ) {
		var payload = { event: event, service: data.service || config.service || 'general' };
		if ( data.topic && topics.indexOf( data.topic ) !== -1 ) { payload.topic = data.topic; }
		if ( data.placement ) { payload.placement = data.placement; }
		if ( data.event_id && /^[a-f0-9-]{36}$/i.test( data.event_id ) ) { payload.event_id = data.event_id; }
		// No name, phone, entered text, query string, referrer or cookie is emitted.
		window.dispatchEvent( new CustomEvent( 'razil:analytics', { detail: payload } ) );
		if ( ! config.enabled ) { return; }
		if ( config.metrika && typeof window.ym === 'function' ) { window.ym( Number( config.metrika ), 'reachGoal', event, payload ); }
		if ( /^G-[A-Z0-9]+$/.test( config.ga4 || '' ) && typeof window.gtag === 'function' ) { window.gtag( 'event', event, Object.assign( { send_to: config.ga4 }, payload ) ); }
	}
	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest && e.target.closest( 'a[href^="tel:"]' );
		if ( ! link || ( config.phones || [] ).indexOf( link.getAttribute( 'href' ) ) === -1 ) { return; }
		var placement = link.closest( 'header,.rz-header' ) ? 'header' : link.closest( 'footer,.rz-footer' ) ? 'footer' : link.closest( '.rz-hero' ) ? 'hero' : link.closest( 'dialog' ) ? 'form' : 'content';
		emit( 'phone_click', { placement: placement } );
	} );
	document.querySelectorAll( '.rz-cb-form' ).forEach( function ( form ) {
		var started = false;
		form.addEventListener( 'input', function ( e ) {
			if ( started || e.target.type !== 'tel' || ! e.target.value ) { return; }
			started = true;
			emit( 'callback_start', { topic: form.elements.rz_cb_topic.value, service: ( config.services || {} )[ form.elements.rz_cb_service.value ] || config.service } );
		} );
	} );
	var receipt = config.receipt;
	if ( receipt && receipt.event_id ) {
		var seen = false;
		try { seen = sessionStorage.getItem( 'razil_cb_' + receipt.event_id ) === '1'; if ( ! seen ) { sessionStorage.setItem( 'razil_cb_' + receipt.event_id, '1' ); } } catch ( e ) { /* Server consumption still prevents refresh duplicates. */ }
		if ( ! seen ) { emit( 'callback_saved', receipt ); }
	}
}() );
