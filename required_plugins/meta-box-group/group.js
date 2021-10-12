( function( $, _, document, window, rwmb, i18n ) {
	'use strict';

	var group = {
		toggle: {}, // Toggle module for handling collapsible/expandable groups.
		clone: {}   // Clone module for handling clone groups.
	};

	/**
	 * Handles a click on either the group title or the group collapsible/expandable icon.
	 * Expects `this` to equal the clicked element.
	 *
	 * @param event Click event.
	 */
	group.toggle.handle = function( event ) {
		event.preventDefault();
		event.stopPropagation();

		var $group = $( this ).closest( '.rwmb-group-clone, .rwmb-group-non-cloneable' ),
			state = $group.hasClass( 'rwmb-group-collapsed' ) ? 'expanded' : 'collapsed';

		group.toggle.updateState( $group, state );

		// Refresh maps to make them visible.
		$( window ).trigger( 'rwmb_map_refresh' );
	};

	/**
	 * Update the group expanded/collapsed state.
	 *
	 * @param $group Group element.
	 * @param state  Force group to have a state.
	 */
	group.toggle.updateState = function( $group, state ) {
		var $input = $group.find( '.rwmb-group-state' ).last().find( 'input' );
		if ( ! $input.length && ! state ) {
			return;
		}
		if ( state ) {
			$input.val( state );
		} else {
			state = $input.val();
		}
		// Store current state. Will be preserved when cloning.
		$input.attr( 'data-current', state );
		$input.trigger( 'change' );

		$group.toggleClass( 'rwmb-group-collapsed', 'collapsed' === state )
			  .find( '.rwmb-group-toggle-handle' ).first().attr( 'aria-expanded', 'collapsed' !== state );
	};

	/**
	 * Update group title.
	 *
	 * @param index   Group clone index.
	 * @param element Group element.
	 */
	group.toggle.updateTitle = function ( index, element ) {
		var $group = $( element ),
			$title = $group.find( '> .rwmb-group-title-wrapper > .rwmb-group-title, > .rwmb-input > .rwmb-group-title-wrapper > .rwmb-group-title' ),
			options = $title.data( 'options' );

		if ( 'undefined' === typeof options ) {
			return;
		}

		var content = options.content || '',
			fields = options.fields || [];

		function processField( field ) {
			if ( -1 === content.indexOf( '{' + field + '}' ) ) {
				return;
			}

			var selectors = 'input[name*="[' + field + ']"], textarea[name*="[' + field + ']"], select[name*="[' + field + ']"], button[name*="[' + field + ']"]',
				$field = $group.find( selectors );

			if ( ! $field.length ) {
				return;
			}

			var fieldValue = $field.val() || '';
			if ( $field.is( 'select' ) && fieldValue ) {
				fieldValue = $field.find( 'option:selected' ).text();
			}
			content = content.replace( '{' + field + '}', fieldValue );

			// Update title when field's value is changed.
			if ( ! $field.data( 'update-group-title' ) ) {
				$field.on( 'keyup change', _.debounce( function () {
					group.toggle.updateTitle( index, element );
				}, 250 ) ).data( 'update-group-title', true );
			}
		}

		content = content.replace( '{#}', index );
		fields.forEach( processField );

		$title.text( content );
	};

	/**
	 * Initialize the title on load or when new clone is added.
	 *
	 * @param $container Wrapper (on load) or group element (when new clone is added)
	 */
	group.toggle.initTitle = function ( $container ) {
		$container.find( '.rwmb-group-collapsible' ).each( function () {
			// Update group title for non-cloneable groups.
			var $this = $( this );
			if ( $this.hasClass( 'rwmb-group-non-cloneable' ) ) {
				group.toggle.updateTitle( 1, this );
				group.toggle.updateState( $this );
				return;
			}

			$this.children( '.rwmb-input' ).each( function () {
				var $input = $( this );

				// Update group title.
				$input.children( '.rwmb-group-clone' ).each( function ( index, clone ) {
					group.toggle.updateTitle( index + 1, clone );
					group.toggle.updateState( $( clone ) );
				} );

				// Drag and drop clones via group title.
				if ( $input.data( 'ui-sortable' ) ) { // If sortable is initialized.
					$input.sortable( 'option', 'handle', '.rwmb-clone-icon + .rwmb-group-title-wrapper' );
				} else { // If not.
					$input.on( 'sortcreate', function () {
						$input.sortable( 'option', 'handle', '.rwmb-clone-icon + .rwmb-group-title-wrapper' );
					} );
				}
			} );
		} );
	};

	/**
	 * Initialize the collapsible state when first loaded.
	 * Add class 'rwmb-group-collapsed' to group clones.
	 * Non-cloneable groups have that class already - added via PHP.
	 */
	group.toggle.initState = function () {
		$( '.rwmb-group-collapsible.rwmb-group-collapsed' ).each( function () {
			var $this = $( this );
			if ( ! $this.hasClass( 'rwmb-group-non-cloneable' ) ) {
				$this.children( '.rwmb-input' ).children( '.rwmb-group-clone' ).addClass( 'rwmb-group-collapsed' );
			}
		} );
	};

	/**
	 * Update group index for inputs
	 */
	group.clone.updateGroupIndex = function () {
		var that = this,
			$clones = $( this ).parents( '.rwmb-group-clone' ),
			totalLevel = $clones.length;
		$clones.each( function ( i, clone ) {
			var index = parseInt( $( clone ).parent().data( 'next-index' ) ) - 1,
				level = totalLevel - i;

			group.clone.replaceName.call( that, level, index );

			// Stop each() loop immediately when reach the new clone group.
			if ( $( clone ).data( 'clone-group-new' ) ) {
				return false;
			}
		} );
	};

	group.clone.updateIndex = function() {
		// debugger;
		var $this = $( this );

		// Update index only for sub fields in a group
		if ( ! $this.closest( '.rwmb-group-clone' ).length ) {
			return;
		}

		// Do not update index if field is not cloned
		if ( ! $this.closest( '.rwmb-input' ).children( '.rwmb-clone' ).length ) {
			return;
		}

		var index = parseInt( $this.closest( '.rwmb-input' ).data( 'next-index' ) ) - 1,
			level = $this.parents( '.rwmb-clone' ).length;

		group.clone.replaceName.call( this, level, index );

		// Stop propagation.
		return false;
	};

	/**
	 * Helper function to replace the level-nth [\d] with the new index.
	 * @param level
	 * @param index
	 */
	group.clone.replaceName = function ( level, index ) {
		var $input = $( this ),
			name = $input.attr( 'name' );
		if ( ! name ) {
			return;
		}

		var regex = new RegExp( '((?:\\[\\d+\\].*?){' + ( level - 1 ) + '}.*?)(\\[\\d+\\])' ),
			newValue = '$1' + '[' + index + ']';

		name = name.replace( regex, newValue );
		$input.attr( 'name', name );
	};

	/**
	 * Helper function to replace the level-nth [\d] with the new index.
	 * @param level
	 * @param index
	 */
	group.clone.replaceId = function ( level, index ) {
		var $input = $( this ),
			id = $input.attr( 'id' );
		if ( ! id ) {
			return;
		}

		var regex = new RegExp( '_(\\d*)$' ),
			newValue = '_' + rwmb.uniqid();

		if ( regex.test( id ) ) {
			id = id.replace( regex, newValue );
		} else {
			id += newValue;
		}

		$input.attr( 'id', id );
	};

	/**
	 * When clone a group:
	 * 1) Remove sub fields' clones and keep only their first clone
	 * 2) Reset sub fields' [data-next-index] to 1
	 * 3) Set [name] for sub fields (which is done when 'clone' event is fired
	 * 4) Repeat steps 1)-3) for sub groups
	 * 5) Set the group title
	 *
	 * @param event The clone_instance custom event
	 * @param index The group clone index
	 */
	group.clone.processGroup = function ( event, index ) {
		var $group = $( this );
		if ( ! $group.hasClass( 'rwmb-group-clone' ) ) {
			return false; // Do not bubble up.
		}
		// Do not trigger clone on parents.
		event.stopPropagation();

		$group
			// Add new [data-clone-group-new] to detect which group is cloned. This data is used to update sub inputs' group index
			.data( 'clone-group-new', true )
			// Remove clones, and keep only their first clone. Reset [data-next-index] to 1
			.find( '.rwmb-input' ).each( function () {
				$( this ).data( 'next-index', 1 ).children( '.rwmb-clone:gt(0)' ).remove();
			} );

		// Update [group index] for inputs
		$group.find( rwmb.inputSelectors ).each( function () {
			group.clone.updateGroupIndex.call( this );
		} );

		// Preserve the state (via [data-current]).
		$group.find( '[name*="[_state]"]' ).each( function() {
			$( this ).val( $( this ).data( 'current' ) );
		} );

		// Update group title for the new clone and set it expanded by default.
		if ( $group.closest( '.rwmb-group-collapsible' ).length ) {
			group.toggle.updateTitle( index + 1, $group );
			group.toggle.updateState( $group );
		}
		// Sub groups: reset titles, but preserve the state.
		group.toggle.initTitle( $group );

		rwmb.$document.trigger( 'clone_completed', [$group] );
	};

	/**
	 * Remove a group clone
	 * @param event The click event.
	 */
	group.clone.remove = function( event ) {
		event.preventDefault();
		event.stopPropagation();
		var ok = confirm( i18n.confirmRemove );
		if ( ! ok ) {
			return;
		}
		$( this ).parent().siblings( '.remove-clone' ).trigger( 'click' );
	}

	function init() {
		group.toggle.initState();
		group.toggle.initTitle( rwmb.$document );

		// Refresh maps to make them visible.
		$( window ).trigger( 'rwmb_map_refresh' );
	}

	rwmb.$document
		.on( 'mb_ready', init )
		.on( 'click', '.rwmb-group-title-wrapper, .rwmb-group-toggle-handle', group.toggle.handle )
		.on( 'clone_instance', '.rwmb-clone', group.clone.processGroup )
		.on( 'update_index', rwmb.inputSelectors, group.clone.replaceId )
		.on( 'update_index', rwmb.inputSelectors, group.clone.updateIndex )
		.on( 'click', '.rwmb-group-remove', group.clone.remove );
} )( jQuery, _, document, window, rwmb, RWMB_Group );
