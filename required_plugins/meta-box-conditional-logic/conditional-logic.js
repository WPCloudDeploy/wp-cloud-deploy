( function( $, rwmb ) {
	////////// SELECTOR CACHE //////////

	/**
	 * Selector cache.
	 * @link https://ttmm.io/tech/selector-caching-jquery/
	 */
	class SelectorCache {
		constructor( $scope ) {
			this.collection = {};
			this.$scope = $scope;
		}
		get( selector ) {
			if ( undefined === this.collection[ selector ] ) {
				this.collection[ selector ] = this.$scope ? this.$scope.find( selector ) : $( selector );
			}

			return this.collection[ selector ];
		}
	}

	const globalSelectorCache = new SelectorCache();
	const getSelectorCache = $scope => $scope ? new SelectorCache( $scope ) : globalSelectorCache;


	////////// GUTENBERG-RELATED FUNCTIONS //////////

	const wpElements = {
		page_template: '#page_template',
		post_format: 'input[name="post_format"]',
		parent_id: '#parent_id',
		post_ID: '#post_ID'
	};
	const wpGutenbergMap = {
		page_template: 'template',
		post_format: 'format',
		parent_id: 'parent',
		post_ID: 'id',
		post_category: 'categories',
		tags: 'tags'
	};

	const isWpElement = element => rwmb.isGutenberg ? wpGutenbergMap.hasOwnProperty( element ) : wpElements.hasOwnProperty( element );
	const isGutenbergElement = element => rwmb.isGutenberg ? wpGutenbergMap.hasOwnProperty( element ) : false;
	const getWpSelector = element => !rwmb.isGutenberg && isWpElement( element ) ? wpElements[ element ] : null;

	function getWpElementValue( element ) {
		if ( rwmb.isGutenberg ) {
			return wp.data.select( 'core/editor' ).getEditedPostAttribute( wpGutenbergMap[ element ] );
		}
		let $element = globalSelectorCache.get( getWpSelector( element ) );
		return 'post_format' === element ? $element.filter( ':checked' ).val() : $element.val();
	}


	////////// COMPARISON HELPER FUNCTIONS //////////

	/**
	 * Check if an array contains a value using soft comparison.
	 * Used when users set post_category = [1, 2] or ['1', '2']. Both should work.
	 * Note: Array.indexOf(), Array.includes(), _.contains() use strict comparison.
	 */
	function contains( list, value ) {
		let i = list.length;
		while ( i-- ) {
			if ( list[ i ] == value ) {
				return true;
			}
		}
		return false;
	}

	function compare( needle, haystack, operator ) {
		if ( needle === null || typeof needle === 'undefined' ) {
			needle = '';
		}

		switch ( operator ) {
			case '=':
				if ( !Array.isArray( needle ) || !Array.isArray( haystack ) ) {
					return needle == haystack;
				}
				// Simple comparison for 2 arrays.
				let ok1 = needle.every( function( value ) {
					return contains( haystack, value );
				} );
				let ok2 = haystack.every( function( value ) {
					return contains( needle, value );
				} );
				return ok1 && ok2;

			case '>=':
				return needle >= haystack;

			case '>':
				return needle > haystack;

			case '<=':
				return needle <= haystack;

			case '<':
				return needle < haystack;

			case 'contains':
				if ( Array.isArray( needle ) ) {
					return contains( needle, haystack );
				} else if ( typeof needle === 'string' ) {
					return needle.indexOf( haystack ) !== -1;
				}
				return needle == haystack;

			case 'in':
				if ( !Array.isArray( needle ) ) {
					return needle == haystack || contains( haystack, needle );
				}
				// If needle is an array, 'in' means if any of needle's value in haystack.
				let found = false;
				needle.forEach( function( value ) {
					if ( value == haystack || contains( haystack, value ) ) {
						found = true;
					}
				} );
				return found;

			case 'start_with':
			case 'starts with':
				return needle.indexOf( haystack ) === 0;

			case 'end_with':
			case 'ends with':
				haystack = new RegExp( haystack + '$' );
				return haystack.test( needle );

			case 'match':
				haystack = new RegExp( haystack );
				return haystack.test( needle );

			case 'between':
				if ( Array.isArray( haystack ) && typeof haystack[ 0 ] !== 'undefined' && typeof haystack[ 1 ] !== 'undefined' ) {
					return needle >= haystack[ 0 ] && needle <= haystack[ 1 ];
				}
		}
	}


	////////// RUN CONDITIONS //////////

	function runConditionalLogic( $scope ) {
		// Log run time for performance tracking.
		// console.time( 'Run Conditional Logic' );

		// Run only for the new cloned group (when click add clone button) if possible.
		let selectorCache = getSelectorCache( $scope ),
			$conditions = selectorCache.get( '.mbc-conditions' );

		$conditions.each( function() {
			let $this = $( this ),
				conditions = $this.data( 'conditions' ),
				action = typeof conditions[ 'hidden' ] !== 'undefined' ? 'hidden' : 'visible',
				logic = conditions[ action ],
				logicApply = isLogicCorrect( logic, $this ),
				$element = $this.parent();

			if ( !$element.hasClass( 'rwmb-field' ) && $element.closest( '.postbox' ).length ) {
				$element = $element.closest( '.postbox' );
			}

			toggle( $element, logicApply, action );
		} );

		// Show run time.
		// Test 001-visibility-broken: 20 clones < 300ms.
		// console.timeEnd( 'Run Conditional Logic' );

		// Outside conditions.
		_.each( conditions, function( logics, field ) {
			_.each( logics, function( logic, action ) {
				if ( typeof logic.when === 'undefined' ) {
					return;
				}

				let selector = getSelector( field, globalSelectorCache ),
					$element = globalSelectorCache.get( selector ),
					logicApply = isLogicCorrect( logic, '' );

				toggle( $element, logicApply, action );
			} );
		} );
	}

	/**
	 * Check if logics attached to fields is correct or not.
	 * If a field is hidden by Conditional Logic, then all dependent fields are hidden also.
	 *
	 * @param  logics Array of logic applied to field.
	 * @param  $field Current field (input) element (jQuery object).
	 * @return boolean
	 */
	function isLogicCorrect( logics, $field ) {
		let relation = typeof logics.relation !== 'undefined' ? logics.relation.toLowerCase() : 'and',
			success = relation === 'and';

		logics.when.forEach( function( logic ) {
			// Skip check if we already know the result.
			if ( relation === 'and' && !success ) {
				return;
			}
			if ( relation === 'or' && success ) {
				return;
			}

			// Get scope of current field. Scope is only applied for Group field.
			// A scope is a group or whole meta box which contains event source and current field.
			let $scope = getScope( $field ),
				selectorCache = getSelectorCache( $scope ),
				dependentFieldSelector = getSelector( logic[ 0 ], selectorCache );

			// Try broader scope if field is in a cloneable group.
			if ( !isGutenbergElement( logic[ 0 ] ) && !dependentFieldSelector && $scope && $scope.hasClass( 'rwmb-group-clone' ) ) {
				$scope = getScope( $field, true );
				selectorCache = getSelectorCache( $scope ),
					dependentFieldSelector = getSelector( logic[ 0 ], selectorCache );
			}

			// console.log( 'Selector', logic[0], dependentFieldSelector );

			if ( !isGutenbergElement( logic[ 0 ] ) && !dependentFieldSelector ) {
				return;
			}

			let $dependentField = selectorCache.get( dependentFieldSelector ),
				isDependentFieldVisible = $dependentField.closest( '.rwmb-field' ).attr( 'data-visible' );

			if ( 'hidden' === isDependentFieldVisible ) {
				success = 'hidden';
				return;
			}

			let dependentValue = getValue( logic[ 0 ], selectorCache ),
				operator = logic[ 1 ],
				value = logic[ 2 ],
				negative = false;

			// Cast to string if array has 1 element and its a string
			if ( Array.isArray( dependentValue ) && dependentValue.length === 1 ) {
				dependentValue = dependentValue[ 0 ];
			}

			// Allows user using NOT statement.
			if ( compare( operator, 'not', 'contains' ) || compare( operator, '!', 'contains' ) ) {
				negative = true;
				operator = operator.replace( 'not', '' );
				operator = operator.replace( '!', '' );
			}

			operator = operator.trim();

			if ( $.isNumeric( dependentValue ) ) {
				dependentValue = parseInt( dependentValue );
			}

			let result = compare( dependentValue, value, operator );

			if ( negative ) {
				result = !result;
			}

			// console.log( 'Logic Compare',  logic[0], dependentValue, value, operator, result );

			success = relation === 'and' ? success && result : success || result;
		} );

		return success;
	}


	////////// GET FIELD VALUE / SELECTOR //////////

	function getValue( fieldName, selectorCache ) {
		if ( isWpElement( fieldName ) ) {
			return getWpElementValue( fieldName );
		}
		if ( rwmb.isGutenberg && compare( fieldName, 'tax_input', 'contains' ) ) {
			let match = fieldName.match( /tax_input\[(.*?)\]/ );
			return wp.data.select( 'core/editor' ).getEditedPostAttribute( match[ 1 ] );
		}

		// Allows user define conditional logic by callback
		if ( compare( fieldName, '(', 'contains' ) ) {
			return eval( fieldName );
		}

		// Search by ID.
		let $field = compare( fieldName, '#', 'start_with' ) ? selectorCache.get( fieldName ) : selectorCache.get( '#' + fieldName ),
			value = $field.val();

		// Non-checkbox field with ID.
		if ( $field.length && $field.attr( 'type' ) !== 'checkbox' && typeof value !== 'undefined' && value != null ) {
			return value;
		}

		// Single checkbox field.
		if ( $field.length && $field.attr( 'type' ) === 'checkbox' ) {
			return $field.is( ':checked' );
		}

		// Checkbox list, radio, select tree, e.g. no ID.
		let selector = null,
			isMultiple = false;

		// Try to find the element via [name] attribute.
		if ( selectorCache.get( '[name="' + fieldName + '"]' ).length ) {
			selector = '[name="' + fieldName + '"]';
		} else if ( selectorCache.get( '[name*="' + fieldName + '"]' ).length ) {
			selector = '[name*="' + fieldName + '"]';
		} else if ( selectorCache.get( '[name*="' + fieldName + '[]"]' ).length ) {
			selector = '[name*="' + fieldName + '[]"]';
			isMultiple = true;
		}

		if ( null === selector ) {
			return 0;
		}

		let $selector = selectorCache.get( selector ),
			selectorType = $selector.attr( 'type' );
		selectorType = selectorType ? selectorType : $selector.prop( 'tagName' );

		let isSelectTree = 'SELECT' === selectorType && isMultiple;

		if ( [ 'checkbox', 'radio', 'hidden' ].indexOf( selectorType ) === -1 && !isSelectTree ) {
			return $selector.val();
		}

		// If user selected a checkbox, radio, or select tree, return array of selected fields, or value of singular field.
		let values = [],
			$elements = [];

		if ( selectorType === 'hidden' && fieldName !== 'post_category' && !compare( selector, 'tax_input', 'contains' ) ) {
			$elements = $selector;
		} else if ( isSelectTree ) {
			$elements = $selector;
		} else {
			$elements = $selector.filter( ':checked' );
		}

		$elements.each( function() {
			values.push( this.value );
		} );

		return values.length > 1 ? values : values.pop();
	}

	function getScope( $field, ignoreGroupClone ) {
		// $field is empty when checking logic of outside conditions.
		if ( !$field ) {
			return '';
		}

		// If the current field is in a group clone, then all the logics must be within this group.
		if ( !ignoreGroupClone ) {
			let $groupClone = $field.closest( '.rwmb-group-clone' );
			if ( $groupClone.length ) {
				return $groupClone;
			}
		}

		// If Gutenberg is active.
		if ( rwmb.isGutenberg ) {
			return $( '#editor' );
		}

		// Global scope. Should be the closest 'form', since in the frontend, users can insert the same meta box in multiple forms.
		// In the backend, edit 'form' wraps almost everything. So it should be okay.
		let $form = $field.closest( 'form' );
		return $form.length ? $form : '';
	}

	function getSelector( name, selectorCache ) {
		if ( isWpElement( name ) ) {
			return getWpSelector( name );
		}

		if ( compare( name, '(', 'contains' ) ) {
			return null;
		}
		if ( !selectorCache ) {
			selectorCache = globalSelectorCache;
		}

		if ( isUserDefinedSelector( name ) ) {
			return name;
		}

		let selectors = [
			name,
			'#' + name,
			'[name="' + name + '"]',
			'[name^="' + name + '"]',
			'[name*="' + name + '"]'
		];
		let selector = _.find( selectors, function( pattern ) {
			return selectorCache.get( pattern ).length > 0;
		} );

		return selector ? selector : null;
	}

	function isUserDefinedSelector( name ) {
		return compare( name, '.', 'starts with' ) ||
			compare( name, '#', 'starts with' ) ||
			compare( name, '[name', 'contains' ) ||
			compare( name, '>', 'contains' ) ||
			compare( name, '*', 'contains' ) ||
			compare( name, '~', 'contains' );
	}

	////////// HANDLE TOGGLING //////////

	function toggle( $element, logic, action ) {
		if ( logic === true ) {
			action === 'visible' ? applyVisible( $element ) : applyHidden( $element );
		} else if ( logic === false ) {
			action === 'visible' ? applyHidden( $element ) : applyVisible( $element );
		} else if ( logic === 'hidden' ) {
			applyHidden( $element );
		}
	}

	function applyVisible( $element ) {
		// If element is a field, get the field wrapper.
		let $field = $element.closest( '.rwmb-field' );
		if ( $field.length ) {
			$element = $field;
		}

		let toggleType = getToggleType( $element ),
			func = {
				display: 'show',
				slide: 'slideDown',
				fade: 'fadeIn'
			};
		if ( func.hasOwnProperty( toggleType ) ) {
			$element[ func[ toggleType ] ]();
		} else {
			$element.css( 'visibility', 'visible' );
		}

		$element.attr( 'data-visible', 'visible' );

		// Reset the required attribute for inputs.
		$element.find( rwmb.inputSelectors ).each( function() {
			let $this = $( this ),
				$field = $this.closest( '.rwmb-field.required' );
			if ( $field.length ) {
				$this.prop( 'required', true );
			}
		} );
	}

	function applyHidden( $element ) {
		// If element is a field, get the field wrapper.
		let $field = $element.closest( '.rwmb-field' );
		if ( $field.length ) {
			$element = $field;
		}

		let toggleType = getToggleType( $element ),
			func = {
				display: 'hide',
				slide: 'slideUp',
				fade: 'fadeOut'
			};
		if ( func.hasOwnProperty( toggleType ) ) {
			$element[ func[ toggleType ] ]();
		} else {
			$element.css( 'visibility', 'hidden' );
		}

		$element.attr( 'data-visible', 'hidden' );

		// Remove required attribute for inputs and trigger a custom event.
		$element.find( rwmb.inputSelectors ).each( function() {
			let $this = $( this ),
				required = $this.attr( 'required' );
			if ( required ) {
				$this.prop( 'required', false );
			}
			$this.trigger( 'cl_hide' );
		} );
	}

	function getToggleType( $element ) {
		let $type = $element.closest( '.rwmb-meta-box' ).children( '.mbc-toggle-type' );
		return $type.length ? $type.data( 'toggle_type' ) : 'display';
	}

	////////// EVENTS //////////

	let watchedElements;

	function getWatchedElements() {
		watchedElements = [];

		$( '.mbc-conditions' ).each( function() {
			let fieldConditions = $( this ).data( 'conditions' ),
				action = typeof fieldConditions[ 'hidden' ] !== 'undefined' ? 'hidden' : 'visible',
				logic = fieldConditions[ action ];

			logic.when.forEach( addWatchedElement, this );
		} );

		// Outside conditions.
		_.each( conditions, function( logics ) {
			_.each( logics, function( logic ) {
				if ( typeof logic.when === 'undefined' ) {
					return;
				}
				logic.when.forEach( addWatchedElement, null );
			} );
		} );

		// Removed duplicated and empty selectors.
		watchedElements = _.uniq( watchedElements ).filter( Boolean ).join();
	}

	function addWatchedElement( logic ) {
		if ( compare( logic[ 0 ], '(', 'contains' ) ) {
			return;
		}

		// Find selector within correct scope to speed up.
		let $scope = null;
		if ( null !== this ) {
			$scope = getScope( $( this ) );
		}
		let selectorCache = getSelectorCache( $scope ),
			selector = getSelector( logic[ 0 ], selectorCache );

		if ( !selector ) {
			selector = '#' + logic[ 0 ];
		}

		watchedElements.push( selector );
	}


	////////// MAIN CODE //////////

	function watch() {
		getWatchedElements();

		// In Gutenberg, simply subscribe to all changes.
		if ( rwmb.isGutenberg ) {
			wp.data.subscribe( runConditionalLogic );
		}

		// Listening eventSource apply conditional logic when eventSource is change.
		if ( watchedElements.length > 1 ) {
			rwmb.$document
				.off( 'change keyup', watchedElements )
				.on( 'change keyup', watchedElements, function() {
					runConditionalLogic( getScope( $( this ) ) );
				} );
		}

		// Featured image replaces HTML, thus the event listening above doesn't work.
		// We have to detect DOM change.
		if ( -1 !== watchedElements.indexOf( '_thumbnail_id' ) ) {
			$( '#postimagediv' ).on( 'DOMSubtreeModified', runConditionalLogic );
		}
	}

	function init() {
		runConditionalLogic();
		watch();

		// When a block switches to edit mode, get watched elements and watch again.
		rwmb.$document.on( 'mb-blocks-edit-ready', function( e ) {
			watch();
			runConditionalLogic( $( e.target ) );
		} );

		// For groups.
		rwmb.$document.on( 'clone_completed', ( event, $group ) => runConditionalLogic( $group ) );
	}

	// Export the runConditionalLogic to global scope to use in other scripts.
	rwmb.runConditionalLogic = runConditionalLogic;

	// Run when page finishes loading to improve performance.
	// https://github.com/wpmetabox/meta-box/issues/1195.
	setTimeout( init, 100 );
} )( jQuery, rwmb );
