jQuery( function( $ ) {
	// Delete an item.
	$( document ).on( 'click', '.row-actions .delete a', function( e ) {
		e.preventDefault();

		if ( ! confirm( MbctListTable.confirm ) ) {
			return;
		}

		const $this = $( this );

		$.post( ajaxurl, {
			action: 'mbct_delete_items',
			ids: [ parseInt( $this.data( 'id' ), 10 ) ],
			model: $( 'input[name="model"]' ).val(),
			_ajax_nonce: MbctListTable.nonceDelete,
		}, response => {
			if ( response.success ) {
				$this.closest( 'tr' ).css( 'background', '#ff8383' ).hide( 'slow', function() {
					$( this ).remove();
				} );
			} else {
				alert( response.data );
			}
		} );
	} );

	// Bulk delete.
	$( '#doaction' ).on( 'click', function( e ) {
		e.preventDefault();

		const $items = $( 'input[name="items[]"]' ).filter( ':checked' );
		if ( $items.length === 0 ) {
			return;
		}

		if ( ! confirm( MbctListTable.confirm ) ) {
			return;
		}

		let ids = [];
		$items.each( function() {
			ids.push( parseInt( $( this ).val(), 10 ) );
		} );

		$.post( ajaxurl, {
			action: 'mbct_delete_items',
			ids,
			model: $( 'input[name="model"]' ).val(),
			_ajax_nonce: MbctListTable.nonceDelete,
		}, response => {
			if ( response.success ) {
				$items.closest( 'tr' ).css( 'background', '#ff8383' ).hide( 'slow', function() {
					$( this ).remove();
				} );
			} else {
				alert( response.data );
			}
		} );
	} );
} );