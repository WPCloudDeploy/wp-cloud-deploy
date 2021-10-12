jQuery( function ( $ ) {
	'use strict';

	// Global variables
	var $parent = $( '#parent_id' );

	// List of selectors for each type of element, for classic editor only.
	var selectors = {
		'template': '#page_template',
		'post_format': 'input[name="post_format"]',
		'parent': '#parent_id',
	};
	var elements = {};

	function initElements() {
		_.forEach( selectors, function( selector, key ) {
			elements[key] = $( selector );
		} );
	}

	function isGutenbergActive() {
		return document.body.classList.contains( 'block-editor-page' );
	}

	// Callback functions to check for each condition.
	var checkCallbacks = {
		template   : function ( templates ) {
			var value = isGutenbergActive() ? wp.data.select( 'core/editor' ).getEditedPostAttribute( 'template' ) : elements.template.val();

			return -1 !== templates.indexOf( value );
		},
		post_format: function ( formats ) {
			// Make sure registered formats in lowercase.
			formats = formats.map( function ( format ) {
				return format.toLowerCase();
			} );

			var value = isGutenbergActive() ? wp.data.select( 'core/editor' ).getEditedPostAttribute( 'format' ) : elements.post_format.filter( ':checked' ).val();
			value = value || 'standard';

			return -1 != formats.indexOf( value );
		},
		taxonomy   : function ( taxonomy, terms ) {
			var values = [];

			if ( isGutenbergActive() ) {
				values = wp.data.select( 'core/editor' ).getEditedPostAttribute( taxonomy );
			} else {
				var $inputs = $( '#' + taxonomy + 'checklist :checked' );
				$inputs.each( function () {
					var $input = $( this ),
						text = $.trim( $input.parent().text() );
					values.push( parseInt( $input.val() ) );
					values.push( text );
				} );
			}

			return _.intersection( values, terms ).length > 0;
		},
		input_value: function ( inputValues, relation ) {
			relation = relation || 'OR';

			for ( var i in inputValues ) {
				var $element = $( i ),
					value = $.trim( $element.val() ),
					checked = null;

				if ( $element.is( ':checkbox' ) ) {
					checked = $element.is( ':checked' ) === !!inputValues[i];
				}

				if ( $element.is( ':radio' ) ) {
					value = $.trim( $element.filter( ':checked' ).val() );
				}

				if ( 'OR' == relation ) {
					if ( ( value == inputValues[i] && checked === null ) || checked === true ) {
						return true;
					}
				} else {
					if ( ( value != inputValues[i] && checked === null ) || checked === false ) {
						return false;
					}
				}
			}
			return relation != 'OR';
		},
		is_child   : function () {
			var value = isGutenbergActive() ? wp.data.select( 'core/editor' ).getEditedPostAttribute( 'parent' ) : elements.parent.val();

			return !! parseInt( value );
		}
	};

	var $document = $( document );

	// Callback functions to addEventListeners for "change" event in each condition.
	var addEventListenersCallbacks = {
		template   : function ( callback ) {
			$document.on( 'change', selectors.template, callback );
		},
		post_format: function ( callback ) {
			$document.on( 'change', selectors.post_format, callback );
		},
		taxonomy   : function ( taxonomy, callback ) {
			// Fire "change" event when click on popular category
			// See wp-admin/js/post.js
			$( '#' + taxonomy + 'checklist-pop' ).on( 'click', 'input', function () {
				var t = $( this ), val = t.val(), id = t.attr( 'id' );
				if ( !val ) {
					return;
				}

				var tax = id.replace( 'in-popular-', '' ).replace( '-' + val, '' );
				$( '#in-' + tax + '-' + val ).trigger( 'change' );
			} );

			$( '#' + taxonomy + 'checklist' ).on( 'change', 'input', callback );
		},
		input_value: function ( callback, selectors ) {
			$document.on( 'change', selectors, callback );
		},
		is_child   : function ( callback ) {
			$document.on( 'change', selectors.parent, callback );
		}
	};

	/**
	 * Add JS addEventListenersers to check conditions to show/hide a meta box
	 * @param type
	 * @param conditions
	 * @param $metaBox
	 */
	function maybeShowHide( type, conditions, $metaBox ) {
		var result = checkAllConditions( conditions );

		if ( 'show' == type ) {
			result ? $metaBox.show() : $metaBox.hide();
		} else {
			result ? $metaBox.hide() : $metaBox.show();
		}
	}

	/**
	 * Check all conditions
	 * @param conditions Array of all conditions
	 *
	 * @return bool
	 */
	function checkAllConditions( conditions ) {
		// Don't change "global" conditions
		var localConditions = $.extend( {}, conditions );

		var relation = localConditions.hasOwnProperty( 'relation' ) ? localConditions['relation'].toUpperCase() : 'OR',
			result;

		// For better loop of checking terms
		if ( localConditions.hasOwnProperty( 'relation' ) ) {
			delete localConditions['relation'];
		}

		function setResult( r ) {
			if ( undefined === result ) {
				result = r;
				return;
			}
			if ( 'OR' === relation ) {
				result = result || r;
			} else {
				result = result && r;
			}
		}

		var criterias = ['template', 'post_format', 'input_value', 'is_child'];
		criterias.forEach( function( criteria ) {
			if ( ! localConditions.hasOwnProperty( criteria ) ) {
				return;
			}

			setResult( checkCallbacks[criteria]( localConditions[criteria], relation ) );
			delete localConditions[criteria];
		} );

		// By taxonomy.
		// Note that we unset all other parameters, so we can safely loop in the localConditions array.
		_.each( localConditions, function( terms, taxonomy ) {
			setResult( checkCallbacks['taxonomy']( taxonomy, terms ) );
		} );

		return result;
	}

	/**
	 * Add event addEventListenersers for "change" event
	 * This will re-check all conditions to show/hide meta box
	 * @param type
	 * @param conditions
	 * @param $metaBox
	 */
	function addEventListeners( type, conditions, $metaBox ) {
		// Don't change "global" conditions
		var localConditions = $.extend( {}, conditions );

		// For better loop of checking terms
		if ( localConditions.hasOwnProperty( 'relation' ) ) {
			delete localConditions['relation'];
		}

		var callback = function () {
			maybeShowHide( type, conditions, $metaBox );
		};

		// Input values.
		if ( localConditions.hasOwnProperty( 'input_value' ) ) {
			var selectors = Object.keys( localConditions.input_value ).join( ',' );
			addEventListenersCallbacks['input_value']( callback, selectors );
			delete localConditions.input_value;
		}

		// In Gutenberg, simply subscribe to all changes.
		if ( isGutenbergActive() ) {
			wp.data.subscribe( callback );
			return;
		}

		// In non-Gutenberg, we need to find elements to listen to changes.
		var criterias = ['template', 'post_format', 'is_child'];
		criterias.forEach( function( criteria ) {
			if ( ! localConditions.hasOwnProperty( criteria ) ) {
				return;
			}
			addEventListenersCallbacks[criteria]( callback );
			delete localConditions[criteria];
		} );

		// By taxonomy, including category.
		// Note that we unset all other parameters, so we can safely loop in the localConditions array
		for ( var taxonomy in localConditions ) {
			if ( ! localConditions.hasOwnProperty( taxonomy ) ) {
				continue;
			}
			addEventListenersCallbacks['taxonomy']( taxonomy, callback );
		}
	}

	function normalizeConditions( conditions ) {
		if ( ! isGutenbergActive() ) {
			return conditions;
		}

		if ( conditions.hasOwnProperty( 'category' ) ) {
			conditions.categories = conditions.category.slice();
			delete conditions.category;
		}
		return conditions;
	}

	function init() {
		$( '.mb-show-hide' ).each( function () {
			var $this = $( this ),
				$metaBox = $this.closest( '.postbox' ),
				conditions;

			// Check for show rules
			if ( $this.data( 'show' ) ) {
				conditions = normalizeConditions( $this.data( 'show' ) );
				maybeShowHide( 'show', conditions, $metaBox );
				addEventListeners( 'show', conditions, $metaBox );
			}

			// Check for hide rules
			if ( $this.data( 'hide' ) ) {
				conditions = normalizeConditions( $this.data( 'hide' ) );
				maybeShowHide( 'hide', conditions, $metaBox );
				addEventListeners( 'hide', conditions, $metaBox );
			}
		} );
	}

	initElements();

	// Run the code after Gutenberg has done rendering.
	// https://stackoverflow.com/a/34999925/371240
	setTimeout( function() {
		window.requestAnimationFrame( init );
	}, 1000 );
} );
