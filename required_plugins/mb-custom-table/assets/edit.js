// Confirm when delete.
let mbctDelete = document.querySelector( '#mbct-delete' );
if ( mbctDelete ) {
	mbctDelete.addEventListener( 'click', e => !confirm( Mbct.confirm ) && e.preventDefault() );
}