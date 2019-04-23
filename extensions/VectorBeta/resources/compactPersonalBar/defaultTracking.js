( function( mw, $ ) {
	'use strict';

	function trackClick( el, name ) {
		mw.beta.trackClick( el, 'PersonalBar', {
			action: 'link-click',
			link: name,
			version: 'default',
			userId: mw.user.getId()
		} );
	}

	$( function() {
		trackClick( '#pt-userpage', 'user-page' );
		trackClick( '#pt-mycontris', 'contributions' );
		trackClick( '#pt-notifications', 'notifications' );
		trackClick( '#pt-mytalk', 'talk' );
		trackClick( '#pt-watchlist', 'watchlist' );
		trackClick( '#pt-preferences', 'preferences' );
		trackClick( '#pt-betafeatures', 'beta' );
		trackClick( '#pt-logout', 'logout' );
		trackClick( '#pt-uls', 'language' );
		trackClick( '#footer-places-privacy', 'privacy' );
		trackClick( '#n-help', 'help' );
	} );
}( mediaWiki, jQuery ) );
