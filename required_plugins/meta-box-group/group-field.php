<?php
/**
 * Group field class.
 *
 * @package    Meta Box
 * @subpackage Meta Box Group
 */

/**
 * Class for group field.
 *
 * @package    Meta Box
 * @subpackage Meta Box Group
 */
class RWMB_Group_Field extends RWMB_Field {
	/**
	 * Queue to store the group fields' meta(s). Used to get child field meta.
	 *
	 * @var array
	 */
	protected static $meta_queue = array();

	/**
	 * Add hooks for sub-fields.
	 */
	public static function add_actions() {
		// Group field is the 1st param.
		$args = func_get_args();
		foreach ( $args[0]['fields'] as $field ) {
			RWMB_Field::call( $field, 'add_actions' );
		}
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public static function admin_enqueue_scripts() {
		// Group field is the 1st param.
		$args   = func_get_args();
		$fields = $args[0]['fields'];

		// Load clone script conditionally.
		foreach ( $fields as $field ) {
			if ( $field['clone'] ) {
				wp_enqueue_script( 'rwmb-clone', RWMB_JS_URL . 'clone.js', array( 'jquery-ui-sortable' ), RWMB_VER, true );
				break;
			}
		}

		// Enqueue sub-fields scripts and styles.
		foreach ( $fields as $field ) {
			RWMB_Field::call( $field, 'admin_enqueue_scripts' );
		}

		// Use helper function to get correct URL to current folder, which can be used in themes/plugins.
		list( , $url ) = RWMB_Loader::get_path( dirname( __FILE__ ) );
		wp_enqueue_style( 'rwmb-group', $url . 'group.css', '', '1.3.14' );
		wp_enqueue_script( 'rwmb-group', $url . 'group.js', array( 'jquery', 'underscore' ), '1.3.14', true );
		wp_localize_script(
			'rwmb-group',
			'RWMB_Group',
			array(
				'confirmRemove' => __( 'Are you sure you want to remove this group?', 'meta-box-group' ),
			)
		);
	}

	/**
	 * Get group field HTML.
	 *
	 * @param mixed $meta  Meta value.
	 * @param array $field Field parameters.
	 *
	 * @return string
	 */
	public static function html( $meta, $field ) {
		ob_start();

		self::output_collapsible_elements( $field, $meta );

		// Add filter to child field meta value, make sure it's added only once.
		if ( empty( self::$meta_queue ) ) {
			add_filter( 'rwmb_raw_meta', array( __CLASS__, 'child_field_meta' ), 10, 3 );
		}

		// Add group value to the queue.
		array_unshift( self::$meta_queue, $meta );

		foreach ( $field['fields'] as $child_field ) {
			$child_field['field_name']       = self::child_field_name( $field['field_name'], $child_field['field_name'] );
			$child_field['attributes']['id'] = self::child_field_id( $field, $child_field );
			$child_field['std']              = self::child_field_std( $field, $child_field, $meta );

			self::child_field_clone_default( $field, $child_field );

			if ( in_array( $child_field['type'], array( 'file', 'image' ) ) ) {
				$child_field['input_name'] = '_file_' . uniqid();
				$child_field['index_name'] = self::child_field_name( $field['field_name'], $child_field['index_name'] );
			}

			self::call( 'show', $child_field, RWMB_Group::$saved );
		}

		// Remove group value from the queue.
		array_shift( self::$meta_queue );

		// Remove filter to child field meta value and reset class's parent field's meta.
		if ( empty( self::$meta_queue ) ) {
			remove_filter( 'rwmb_raw_meta', array( __CLASS__, 'child_field_meta' ) );
		}

		return ob_get_clean();
	}

	/**
	 * Output collapsible elements for groups.
	 *
	 * @param array $field Group field parameters.
	 */
	protected static function output_collapsible_elements( $field, $meta ) {
		if ( ! $field['collapsible'] ) {
			return;
		}

		// Group title.
		$title_attributes = array(
			'class'        => 'rwmb-group-title',
			'data-options' => $field['group_title'],
		);

		$title = self::normalize_group_title( $field['group_title'] );
		$title_attributes['data-options'] = array(
			'type'    => 'text',
			'content' => $title,
			'fields'  => self::get_child_field_ids( $field ),
		);

		echo '<div class="rwmb-group-title-wrapper">';
		echo '<h4 ', self::render_attributes( $title_attributes ), '>', $title, '</h4>';
		if ( $field['clone'] ) {
			echo '<a href="javascript:;" class="rwmb-group-remove">', esc_html__( 'Remove', 'meta-box-group' ), '</a>';
		}
		echo '</div>';

		// Collapse/expand icon.
		$default_state = ( isset( $field['default_state'] ) && $field['default_state'] == 'expanded' ) ? 'true' : 'false';
		echo '<button aria-expanded="' . esc_attr( $default_state ) . '" class="rwmb-group-toggle-handle button-link"><span class="rwmb-group-toggle-indicator" aria-hidden="true"></span></button>';
	}

	private static function normalize_group_title( $group_title ) {
		if ( is_string( $group_title ) ) {
			return $group_title;
		}
		$fields = array_filter( array_map( 'trim', explode( ',', $group_title['field'] . ',' ) ) );
		$fields = array_map( function( $field ) {
			return '{' . $field . '}';
		}, $fields );

		$separator = isset( $group_title['separator'] ) ? $group_title['separator'] : ' ';

		return implode( $separator, $fields );
	}

	/**
	 * Change the way we get meta value for child fields.
	 *
	 * @param mixed $meta        Meta value.
	 * @param array $child_field Child field.
	 * @param bool  $saved       Has the meta box been saved.
	 *
	 * @return mixed
	 */
	public static function child_field_meta( $meta, $child_field, $saved ) {
		$group_meta = reset( self::$meta_queue );
		$child_id   = $child_field['id'];
		if ( isset( $group_meta[ $child_id ] ) ) {
			$meta = $group_meta[ $child_id ];
		}

		// Fix value for date time timestamp.
		if (
			in_array( $child_field['type'], ['date', 'datetime', 'time'], true )
			&& ! empty( $child_field['timestamp'] )
			&& isset( $meta['timestamp'] )
		) {
			$meta = $meta['timestamp'];
		}

		return $meta;
	}

	/**
	 * Get meta value, make sure value is an array (of arrays if field is cloneable).
	 * Don't escape value.
	 *
	 * @param int   $post_id Post ID.
	 * @param bool  $saved   Is the meta box saved.
	 * @param array $field   Field parameters.
	 *
	 * @return mixed
	 */
	public static function meta( $post_id, $saved, $field ) {
		$meta = self::raw_meta( $post_id, $field );

		// Use $field['std'] only when the meta box hasn't been saved (i.e. the first time we run).
		$meta = ! $saved ? $field['std'] : $meta;

		// Make sure returned value is an array.
		if ( empty( $meta ) ) {
			$meta = array();
		}

		// If cloneable, make sure each sub-value is an array.
		if ( $field['clone'] ) {
			// Make sure there's at least 1 sub-value.
			if ( empty( $meta ) ) {
				$meta[0] = array();
			}

			foreach ( $meta as $k => $v ) {
				$meta[ $k ] = (array) $v;
			}
		}

		return $meta;
	}

	/**
	 * Set value of meta before saving into database.
	 *
	 * @param mixed $new     The submitted meta value.
	 * @param mixed $old     The existing meta value.
	 * @param int   $post_id The post ID.
	 * @param array $field   The field parameters.
	 *
	 * @return array
	 */
	public static function value( $new, $old, $post_id, $field ) {
		if ( empty( $field['fields'] ) || ! is_array( $field['fields'] ) ) {
			return array();
		}
		if ( ! $new || ! is_array( $new ) ) {
			$new = array();
		}
		$new = self::get_sub_values( $field['fields'], $new, $post_id );

		return self::sanitize( $new, $old, $post_id, $field );
	}

	/**
	 * Recursively get values for sub-fields and sub-groups.
	 *
	 * @param  array $fields  List of group fields.
	 * @param  array $new     Group value.
	 * @param  int   $post_id Post ID.
	 * @return array
	 */
	protected static function get_sub_values( $fields, $new, $post_id ) {
		$fields = array_filter( $fields, function( $field ) {
			return in_array( $field['type'], array( 'file', 'image', 'group' ) );
		} );

		foreach ( $fields as $field ) {
			$value = isset( $new[ $field['id'] ] ) ? $new[ $field['id'] ] : array();

			if ( 'group' === $field['type'] ) {
				$value               = $field['clone'] ? RWMB_Clone::value( $value, array(), $post_id, $field ) : self::get_sub_values( $field['fields'], $value, $post_id );
				$new[ $field['id'] ] = $value;
				continue;
			}

			// File uploads.
			if ( $field['clone'] ) {
				$value = RWMB_File_Field::clone_value( $value, array(), $post_id, $field, $new );
			} else {
				$index          = isset( $new[ "_index_{$field['id']}" ] ) ? $new[ "_index_{$field['id']}" ] : null;
				$field['index'] = $index;
				$value          = RWMB_File_Field::value( $value, '', $post_id, $field );
			}

			$new[ $field['id'] ] = $value;
		}
		return $new;
	}

	/**
	 * Sanitize value of meta before saving into database.
	 *
	 * @param mixed $new     The submitted meta value.
	 * @param mixed $old     The existing meta value.
	 * @param int   $post_id The post ID.
	 * @param array $field   The field parameters.
	 *
	 * @return array
	 */
	public static function sanitize( $new, $old, $post_id, $field ) {
		$sanitized = array();
		if ( ! $new || ! is_array( $new ) ) {
			return $sanitized;
		}

		foreach ( $new as $key => $value ) {
			if ( is_array( $value ) && ! empty( $value ) ) {
				$value = self::sanitize( $value, '', '', array() );
			}
			if ( '' !== $value && array() !== $value ) {
				if ( is_int( $key ) ) {
					$sanitized[] = $value;
				} else {
					$sanitized[ $key ] = $value;
				}
			}
		}

		return $sanitized;
	}

	public static function normalize( $field ) {
		$field           = parent::normalize( $field );
		$field['fields'] = empty( $field['fields'] ) ? [] : RW_Meta_Box::normalize_fields( $field['fields'] );

		$field = wp_parse_args( $field, [
			'collapsible'   => false,
			'save_state'    => false,
			'group_title'   => $field['clone'] ? __( 'Entry {#}', 'meta-box-group' ) : __( 'Entry', 'meta-box-group' ),
			'default_state' => 'expanded',
		] );

		if ( $field['collapsible'] ) {
			$field['class'] .= ' rwmb-group-collapsible';

			if ( 'collapsed' === $field['default_state'] ) {
				$field['class'] .= ' rwmb-group-collapsed';
			}
		}
		// Add a new hidden field to save the collapse/expand state.
		if ( $field['save_state'] ) {
			$field['fields'][] = RWMB_Input_Field::normalize(
				array(
					'type'       => 'hidden',
					'id'         => '_state',
					'std'        => $field['default_state'],
					'class'      => 'rwmb-group-state',
					'attributes' => array(
						'data-current' => $field['default_state'],
					),
				)
			);
		}
		if ( ! $field['clone'] ) {
			$field['class'] .= ' rwmb-group-non-cloneable';
		}

		return $field;
	}

	/**
	 * Change child field name from 'child' to 'parent[child]'.
	 *
	 * @param string $parent Parent field's name.
	 * @param string $child  Child field's name.
	 *
	 * @return string
	 */
	protected static function child_field_name( $parent, $child ) {
		$pos  = strpos( $child, '[' );
		$pos  = false === $pos ? strlen( $child ) : $pos;
		$name = $parent . '[' . substr( $child, 0, $pos ) . ']' . substr( $child, $pos );

		return $name;
	}

	/**
	 * Change child field attribute id to from 'id' to 'parent_id'.
	 *
	 * @param array $parent      Parent field.
	 * @param array $child       Child field.
	 *
	 * @return string
	 */
	protected static function child_field_id( $parent, $child ) {
		if ( isset( $child['attributes']['id'] ) && false === $child['attributes']['id'] ) {
			return false;
		}
		$parent = isset( $parent['attributes']['id'] ) ? $parent['attributes']['id'] : $parent['id'];
		$child  = isset( $child['attributes']['id'] ) ? $child['attributes']['id'] : $child['id'];
		return "{$parent}_{$child}";
	}

	/**
	 * Change child field std.
	 *
	 * @param array $parent Parent field settings.
	 * @param array $child  Child field settings.
	 * @param array $meta   The value of the parent field. When meta box is not saved, it's the parent's std value.
	 *
	 * @return string
	 */
	protected static function child_field_std( $parent, $child, $meta ) {
	    // Respect 'std' value set in child field.
	    if ( ! empty( $child['std'] ) ) {
	        return $child['std'];
	    }

	    // $meta contains $parent['std'] or clone's std.
	    $std = isset( $meta[ $child['id'] ] ) ? $meta[ $child['id'] ] : '';
	    return $std;
	}

	protected static function get_child_field_ids( $field ) {
		$ids = array();
		foreach ( $field['fields'] as $sub_field ) {
			if ( ! isset( $sub_field['id'] ) ) {
				continue;
			}
			$sub_ids = isset( $sub_field['fields'] ) ? self::get_child_field_ids( $sub_field ) : array( $sub_field['id'] );
			$ids     = array_merge( $ids, $sub_ids );
		}
		return $ids;
	}

	/**
	 * Setup clone_default for sub-fields.
	 * Test cases: https://docs.google.com/spreadsheets/d/10jQ70ygXH42qdaDpwIk52wYqYhKK3TiqaJvOxEYo5bQ/edit?usp=sharing
	 */
	protected static function child_field_clone_default( $parent, &$child ) {
		$clone_default = $child['clone_default'];
		if ( $parent['clone'] && $parent['clone_default'] && ! $child['clone'] ) {
			$clone_default = true;
		}
		$child['clone_default'] = $clone_default;
		if ( ! $clone_default ) {
			return;
		}
		$child['attributes'] = wp_parse_args( $child['attributes'], [
			'data-default'       => $child['std'],
			'data-clone-default' => 'true',
		] );
	}
}
