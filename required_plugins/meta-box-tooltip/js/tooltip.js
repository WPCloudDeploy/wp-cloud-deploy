( function ( $, rwmb ) {
	function init() {
		tippy( '.mb-tooltip', {
			animation: 'fade',
			arrow: true,
			interactive: true
		} );
	}

	rwmb.$document
		.on( 'mb_ready', init )
		.on( 'clone', init );
} )( jQuery, rwmb );
