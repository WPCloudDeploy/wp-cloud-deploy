( function ( document, $, i18n ) {
	'use strict';

	var $tabs, $boxes;

	function toggleMetaBox() {
		$( '.if-js-closed' ).removeClass( 'if-js-closed' ).addClass( 'closed' );
		postboxes.add_postbox_toggles( i18n.pageHook );
	}

	function switchTab() {
		$boxes.each( function () {
			var $this = $( this );
			this.dataset.tab = '#tab-' + $this.find( '.rwmb-settings-tab' ).data( 'tab' );
		} );
		$( '.nav-tab-wrapper' ).on( 'click', 'a', ( e ) => showTab( e.target.getAttribute( 'href' ) ) );
	}

	function detectActiveTab() {
		$tabs.first().trigger( 'click' );
		showTab( location.hash );
	}

	function showValidateErrorFields() {
		var inputSelectors = 'input[class*="rwmb-error"], textarea[class*="rwmb-error"], select[class*="rwmb-error"], button[class*="rwmb-error"]';
		$( document ).on( 'after_validate', 'form', ( e ) => showTab( $( e.target ).find( inputSelectors ).closest( '.postbox' ).data( 'tab' ) ) );
	}

	function showTab( tab ) {
		if ( ! tab ) {
			return;
		}
		$tabs.removeClass( 'nav-tab-active' ).filter( '[href="' + tab + '"]' ).addClass( 'nav-tab-active' );
		$boxes.hide().filter( ( index, element ) => element.dataset.tab === tab ).show();

		rwmb.$document.trigger( 'mb_init_editors' );
	}

	$( function() {
		$boxes = $( '.wrap .postbox' );
		$tabs = $( '.nav-tab' );

		toggleMetaBox();
		switchTab();
		detectActiveTab();
		showValidateErrorFields();
	} );
} )( document, jQuery, MBSettingsPage );
