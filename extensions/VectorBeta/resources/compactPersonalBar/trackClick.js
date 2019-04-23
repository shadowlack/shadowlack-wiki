( function( mw, $ ) {
	'use strict';

	mw.beta = mw.beta || {};
	mw.beta.trackClick = $.noop;

	function logSavedClick() {
		var schemaName, data;

		try {
			schemaName = localStorage.getItem( 'trackClickSchemaName' );
			data = localStorage.getItem( 'trackClickData' );
			if ( schemaName ) {
				mw.eventLog.logEvent( schemaName, JSON.parse( data ) );
				localStorage.removeItem( 'trackClickSchemaName' );
				localStorage.removeItem( 'trackClickData' );
			}
		} catch ( e ) {}
	}

	if ( mw.eventLog ) {

		mw.beta.trackClick = function( el, schemaName, data ) {
			$( el ).on( 'click', function() {
				// schedule sending the event if the click target was not a link,
				// a link that was prevented or a link that only changed the anchor
				try {
					localStorage.setItem( 'trackClickSchemaName', schemaName );
					localStorage.setItem( 'trackClickData', JSON.stringify( data ) );
				} catch ( e ) {
					return;
				}
				setTimeout( logSavedClick, 2000 );
			} );
		};

		// send an event stored by a click on a link on previous page (if present)
		logSavedClick();

	}
}( mediaWiki, jQuery ) );
