<?php
/**
 * Control the include, exclude condition for meta boxes
 *
 * @link       https://metabox.io/plugins/meta-box-include-exclude/
 * @package    Meta Box
 * @subpackage Meta Box Include Exclude
 */

/**
 * Class MB_Include_Exclude
 */
class MB_Include_Exclude {
	/**
	 * Store the current post ID.
	 *
	 * @var string
	 */
	protected static $post_id;

	/**
	 * Check if meta box is displayed or not.
	 *
	 * @param bool  $show     Show or hide meta box.
	 * @param array $meta_box Meta Box parameters.
	 *
	 * @return bool
	 */
	public static function check( $show, $meta_box ) {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return $show;
		}

		self::$post_id = self::get_current_post_id();

		if ( isset( $meta_box['include'] ) ) {
			$show = self::maybe_exclude_include( 'include', $meta_box );
		}

		if ( isset( $meta_box['exclude'] ) ) {
			$show = ! self::maybe_exclude_include( 'exclude', $meta_box );
		}

		return $show;
	}

	/**
	 * Check if meta box is excluded for current post.
	 *
	 * @param string $type     Accept 'include' or 'exclude'.
	 * @param array  $meta_box Meta box data.
	 *
	 * @return bool
	 */
	protected static function maybe_exclude_include( $type, $meta_box ) {
		$conditions = $meta_box[ $type ];
		$relation   = isset( $conditions['relation'] ) && in_array( strtoupper( $conditions['relation'] ), array( 'AND', 'OR' ), true ) ? strtoupper( $conditions['relation'] ) : 'OR';

		// Initial value.
		$value = 'OR' === $relation ? false : true;

		// For better loop of checking terms.
		unset( $conditions['relation'] );

		$check_by = array( 'ID', 'parent', 'slug', 'template', 'user_role', 'user_id', 'custom', 'is_child', 'edited_user_role', 'edited_user_id', 'capability' );
		foreach ( $check_by as $by ) {
			$func = "check_{$by}";
			if ( ! isset( $conditions[ $by ] ) || ! method_exists( __CLASS__, $func ) ) {
				continue;
			}

			$condition = self::$func( $conditions[ $by ], $meta_box );
			if ( self::combine( $value, $condition, $relation ) ) {
				return $value;
			}

			// For better loop of checking terms.
			unset( $conditions[ $by ] );
		}

		// By parent taxonomy, including category and post_tag.
		// Note that we unset all other parameters, so we can safely loop in the condition array.
		if ( empty( $conditions ) ) {
			return $value;
		}
		// Change 'tag' to correct name 'post_tag'.
		if ( isset( $conditions['parent_tag'] ) ) {
			$conditions['parent_post_tag'] = $conditions['parent_tag'];
			unset( $conditions['parent_tag'] );
		}
		foreach ( $conditions as $key => $terms ) {
			if ( 0 !== strpos( $key, 'parent_' ) ) {
				continue;
			}
			$taxonomy  = substr( $key, 7 );
			$condition = self::check_parent_terms( $taxonomy, $terms );

			if ( self::combine( $value, $condition, $relation ) ) {
				return $value;
			}

			unset( $condition[ $key ] );
		}

		// By taxonomy, including category and post_tag.
		// Note that we unset all other parameters, so we can safely loop in the condition array.
		if ( empty( $conditions ) ) {
			return $value;
		}
		// Change 'tag' to correct name 'post_tag'.
		if ( isset( $conditions['tag'] ) ) {
			$conditions['post_tag'] = $conditions['tag'];
			unset( $conditions['tag'] );
		}

		foreach ( $conditions as $key => $terms ) {
			$condition = self::check_terms( $key, $terms );
			if ( self::combine( $value, $condition, $relation ) ) {
				return $value;
			}
		}

		return $value;
	}

	/**
	 * Check if current post has specific ID
	 *
	 * @param array $ids List of post IDs. Can be array or CSV.
	 *
	 * @return bool
	 */
	protected static function check_ID( $ids ) {
		return in_array( self::$post_id, RWMB_Helpers_Array::from_csv( $ids ) );
	}

	/**
	 * Check if current post has specific parent.
	 *
	 * @param array $ids List post ids.
	 *
	 * @return bool
	 */
	protected static function check_parent( $ids ) {
		$post = get_post( self::$post_id );

		return $post && in_array( $post->post_parent, RWMB_Helpers_Array::from_csv( $ids ) );
	}

	/**
	 * Check if current post has specific slug.
	 *
	 * @param array $slugs List post slugs.
	 *
	 * @return bool
	 */
	protected static function check_slug( $slugs ) {
		$post = get_post( self::$post_id );

		return $post && in_array( $post->post_name, RWMB_Helpers_Array::from_csv( $slugs ) );
	}

	/**
	 * Check if current post has specific template
	 *
	 * @param array $templates List page templates.
	 *
	 * @return bool
	 */
	protected static function check_template( $templates ) {
		$template = get_post_meta( self::$post_id, '_wp_page_template', true );

		return in_array( $template, RWMB_Helpers_Array::from_csv( $templates ) );
	}

	/**
	 * Check if current post has specific term
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $terms    Terms.
	 *
	 * @return bool
	 */
	protected static function check_terms( $taxonomy, $terms ) {
		$terms = RWMB_Helpers_Array::from_csv( $terms );

		$post_terms = wp_get_post_terms( self::$post_id, $taxonomy );
		if ( is_wp_error( $post_terms ) || ! is_array( $post_terms ) || empty( $post_terms ) ) {
			return false;
		}

		foreach ( $post_terms as $post_term ) {
			if ( in_array( $post_term->term_id, $terms ) || in_array( $post_term->name, $terms ) || in_array( $post_term->slug, $terms ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if current post has specific term
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $terms    Terms.
	 *
	 * @return bool
	 */
	protected static function check_parent_terms( $taxonomy, $terms ) {
		$terms = RWMB_Helpers_Array::from_csv( $terms );

		$post_terms = wp_get_post_terms( self::$post_id, $taxonomy );
		if ( is_wp_error( $post_terms ) || ! is_array( $post_terms ) || empty( $post_terms ) ) {
			return false;
		}

		foreach ( $post_terms as $post_term ) {
			if ( empty( $post_term->parent ) ) {
				continue;
			}
			$parent = get_term( $post_term->parent, $taxonomy );
			if ( in_array( $parent->term_id, $terms ) || in_array( $parent->name, $terms ) || in_array( $parent->slug, $terms ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	* Check if current user has one of specified capabilities.
	*
	* @param array|string $capabilities List of user capabilities. Array or CSV.
	* @return bool
	*/
	protected static function check_capability( $capabilities ) {
		$user = wp_get_current_user();
		$capabilities = array_map( 'strtolower', RWMB_Helpers_Array::from_csv( $capabilities ) );

		foreach ( $capabilities as $capability ) {
			if ( $user->has_cap( $capability ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check by current user role.
	 *
	 * @param array|string $roles List of user roles. Array or CSV.
	 *
	 * @return bool
	 */
	protected static function check_user_role( $roles ) {
		$user = wp_get_current_user();
		$roles = array_map( 'strtolower', RWMB_Helpers_Array::from_csv( $roles ) );
		$roles = array_intersect( $user->roles, $roles );
		return ! empty( $roles );
	}

	/**
	 * Check by current user ID.
	 *
	 * @param array|string $user_ids List of user IDs. Array or CSV.
	 *
	 * @return bool
	 */
	protected static function check_user_id( $user_ids ) {
		$user_id = get_current_user_id();
		return in_array( $user_id, RWMB_Helpers_Array::from_csv( $user_ids ) );
	}

	/**
	 * Check by edit user role.
	 *
	 * @param  array|string $roles List of user roles. Array of CSV.
	 * @return boolean
	 */
	protected static function check_edited_user_role( $roles ) {
		/*
		 * If edit another user's profile, get the edited user instead.
		 * This is required for MB User Meta extension.
		 */
		if ( isset( $GLOBALS['pagenow'] ) && 'user-edit.php' === $GLOBALS['pagenow'] ) {
			// If edit other's profile, check edited user.
			$user_id = intval( $_REQUEST['user_id'] );
			$user    = get_userdata( $user_id );

			$roles = array_map( 'strtolower', RWMB_Helpers_Array::from_csv( $roles ) );
			$roles = array_intersect( $user->roles, $roles );

			return ! empty( $roles );
		} elseif ( isset( $GLOBALS['pagenow'] ) && 'profile.php' === $GLOBALS['pagenow'] ) {
			// If edit profile, check current user.
			return self::check_user_role( $roles );
		}

		return true;
	}

	/**
	 * Check by edited user id.
	 *
	 * @param  array|string $user_ids List of user ids. Array of CSV.
	 * @return boolean
	 */
	protected static function check_edited_user_id( $user_ids ) {
		if ( isset( $GLOBALS['pagenow'] ) && 'user-edit.php' === $GLOBALS['pagenow'] ) {
			// If edit other's profile, check edited user.
			$user_id = intval( $_REQUEST['user_id'] );

			return in_array( $user_id, RWMB_Helpers_Array::from_csv( $user_ids ) );
		} elseif ( isset( $GLOBALS['pagenow'] ) && 'profile.php' === $GLOBALS['pagenow'] ) {
			// If edit profile, check current user.
			return self::check_user_id( $user_ids );
		}

		return true;
	}

	/**
	 * Check by custom function
	 *
	 * @param array $func     Callable.
	 * @param array $meta_box Meta box data.
	 *
	 * @return bool
	 */
	protected static function check_custom( $func, $meta_box ) {
		return is_callable( $func ) ? call_user_func( $func, $meta_box ) : false;
	}

	/**
	 * Check the page is child or not
	 *
	 * @param bool $value Boolean value.
	 *
	 * @return bool
	 */
	protected static function check_is_child( $value ) {
		$post     = get_post( self::$post_id );
		$is_child = $post && $post->post_parent ? true : false;

		return $is_child === $value;
	}

	/**
	 * Get current post ID.
	 *
	 * @return int|false Post ID if successful. False on failure.
	 */
	protected static function get_current_post_id() {
		if ( ! is_admin() ) {
			return get_the_ID();
		}
		$post_id = isset( $_GET['post'] ) ? $_GET['post'] : ( isset( $_POST['post_ID'] ) ? $_POST['post_ID'] : false );
		return is_numeric( $post_id ) ? absint( $post_id ) : false;
	}

	/**
	 * Combine 2 logical value.
	 *
	 * @param bool   $value1   First value.
	 * @param bool   $value2   Second value.
	 * @param string $relation 'OR' or 'AND'.
	 *
	 * @return bool Indicator for quick break the check.
	 */
	protected static function combine( &$value1, $value2, $relation ) {
		if ( 'OR' === $relation ) {
			$value1 = $value1 || $value2;

			return $value1;
		}

		$value1 = $value1 && $value2;

		return ! $value1;
	}
}
