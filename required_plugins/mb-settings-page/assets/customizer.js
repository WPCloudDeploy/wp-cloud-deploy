( function( $, api, rwmb ) {
	// Add nonce to list of inputs for validation.
	var inputSelectors = 'input[name*="nonce"], ' + rwmb.inputSelectors;

	$.extend( FormSerializer.patterns, {
		validate: /^[a-z][a-z0-9_-]*(?:\[(?:\d*|[a-z0-9_-]+)\])*$/i,
		key:      /[a-z0-9_-]+|(?=\[\])/gi,
		named:    /^[a-z0-9_-]+$/i
	} );

	// Transform { "mbb_0": "first", "mbb_1": "second" } to ["first", "second"] recursively.
	const transformObject = obj => {
		if ( typeof obj !== 'object' ) {
			return obj;
		}
		if ( Array.isArray( obj ) ) {
			return obj.map( transformObject );
		}

		// Make sure all keys are 'mbb_*'.
		const keys = Object.keys( obj );
		const match = keys.reduce( ( check, key ) => check && /^mbb_\d+$/.test( key ), true );
		if ( match ) {
			return Object.values( obj ).map( transformObject );
		}

		keys.forEach( key => obj[key] = transformObject( obj[key] ) );

		return obj;
	}

	api.controlConstructor[ 'meta_box' ] = api.Control.extend( {
		ready: function() {
			var setting = this.setting,
				$container = $( this.container );

			function setValue() {
				var data = $container.find( inputSelectors ).mbSerializeObject();
				data = transformObject( data );
				setting.set( JSON.stringify( data ) );
			}

			$container.on( 'change keyup input mb_change', inputSelectors, _.debounce( setValue, 200 ) );
			setValue();

			rwmb.$document.trigger( 'mb_init_editors' );
		}
	} );
} )( jQuery, wp.customize, rwmb );