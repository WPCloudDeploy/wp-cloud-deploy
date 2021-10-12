( function( $, document, i18n ) {
	"use strict";

	function clearInputs() {
		// TinyMCE.
		if ( typeof tinyMCE !== 'undefined' ) {
			tinyMCE.activeEditor.setContent( '' );
		}

		$( '.rwmb-meta-box :input:visible' ).val( '' );

		// Range.
		$( '.rwmb-range + .rwmb-output' ).text( '' );

		// Media field.
		$( '.rwmb-image_advanced' ).trigger( 'media:reset' );

		// File upload.
		$( '.rwmb-media-list' ).html( '' );

		// Color picker field.
		$( '.rwmb-color' ).val( '' );
		$( '.rwmb-input .wp-color-result' ).css( 'background-color', '' );

		// Checkbox and radio.
		$( '.rwmb-meta-box :input:checkbox, .rwmb-meta-box :input:radio' ).prop( 'checked', false );

		// Image select.
		$( '.rwmb-image-select' ).removeClass( 'rwmb-active' );

		// Clone field.
		$( '.rwmb-clone:not(:first-of-type)' ).remove();
	}

	function showSuccessMessage() {
		$( '#addtag p.submit' ).before( '<div id="mb-term-meta-message" class="notice notice-success"><p><strong>' + i18n.addedMessage + '</strong></p></div>' );

		setTimeout( function () {
			$( '#mb-term-meta-message' ).fadeOut();
		}, 2000 );
	}

	function makeEditorsSave() {
		if ( typeof tinyMCE === 'undefined' ) {
			return;
		}
		var editors = tinyMCE.editors;

		for ( var i in editors ) {
			editors[i].on( 'change', editors[i].save );
		}
	}

	$( document ).on( 'ajaxSuccess', function( e, request, settings ) {
		if ( settings.data.indexOf( 'action=add-tag' ) < 0 ) {
			return;
		}

		clearInputs();
		showSuccessMessage();
	} );

	$( function() {
		setTimeout( makeEditorsSave, 500 );
	} );
} )( jQuery, document, MBTermMeta );
