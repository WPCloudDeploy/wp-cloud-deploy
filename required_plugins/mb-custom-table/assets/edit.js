// Confirm when delete.
document.querySelector( '#mbct-delete' ).addEventListener( 'click', e => !confirm( Mbct.confirm ) && e.preventDefault() );