<?php
/**
 * Trait:
 * Contains some of the metabox related code for the server and apps cpt screens.
 * Used only by the class-wpcd-posts-app-server.php and class-wpcd-posts-app.php files which define the WPCD_POSTS_APP_SERVER and WPCD_POSTS_APP classes respectively.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_metaboxes_for_taxonomies_for_servers_and_apps
 */
trait wpcd_metaboxes_for_taxonomies_for_servers_and_apps {

	/**
	 * Group taxonomy name in use by the class.
	 * Set via getter and setter functions
	 *
	 * @var $taxonomy taxonomy.
	 */
	private $taxonomy = '';

	/**
	 * Sort_group_column_key
	 *
	 * @var $sort_group_column_key sort_group_column_key.
	 */
	private $sort_group_column_key = '';

	/**
	 * Initialization hook to bootstrap the other hooks need.
	 * This function needs to be called by the class using this
	 * trait.
	 */
	public function init_hooks_for_taxonomies_for_servers_and_apps() {

		// Filter hook to add metabox for custom taxonomy.
		add_filter( 'rwmb_meta_boxes', array( $this, 'wpcd_register_taxonomy_metaboxes' ) );

		// Load up css and js scripts used for managing this cpt data screen.
		add_action( 'admin_enqueue_scripts', array( $this, 'wpcd_enqueue_scripts_for_taxonomies_for_servers_and_apps' ), 10, 1 );

		// Filter hook to sort by server group or app group.
		add_filter( 'posts_clauses', array( $this, 'wpcd_sort_group_column' ), 10, 2 );

		// Action hook to add custom back to list button.
		add_action( 'admin_footer-post.php', array( $this, 'wpcd_backtolist_btn' ) );
		add_action( 'admin_footer-term.php', array( $this, 'wpcd_backtolist_btn' ) );
	}

	/**
	 * Register the taxonomy metaboxes - these are metabox.io metaboxes, not standard WP metaboxes.
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * @param array $metaboxes Array of existing metaboxes.
	 *
	 * @return array new array of metaboxes
	 */
	public function wpcd_register_taxonomy_metaboxes( $metaboxes ) {

		$roles = wp_roles()->get_names();

		$metaboxes[] = array(
			'taxonomies' => $this->get_post_taxonomy(), // List of taxonomies. Array or string.
			'title'      => '',
			'fields'     => array(
				array(
					'name' => __( 'Color', 'wpcd' ),
					'id'   => 'wpcd_group_color',
					'type' => 'color',
				),
				array(
					'name' => __( 'Visible to Admins Only', 'wpcd' ),
					'id'   => 'wpcd_only_admins',
					'type' => 'checkbox',
				),
				array(
					'name'            => __( 'Visible to Allowed Roles Only', 'wpcd' ),
					'id'              => 'wpcd_roles_allowed',
					'type'            => 'select',
					'multiple'        => true,
					'placeholder'     => __( 'Select User Roles', 'wpcd' ),
					'select_all_none' => true,
					'options'         => $roles,
				),
			),
		);
		return $metaboxes;
	}

	/**
	 * Sets the post taxonomy handled by including class
	 *
	 * @param string $taxonomy the post taxonomy name.
	 *
	 * @return void
	 */
	public function set_post_taxonomy( $taxonomy ) {
		$this->taxonomy = $taxonomy;
	}

	/**
	 * Gets the post taxonomy handled by including class
	 *
	 * @return string the post taxonomy name.
	 */
	public function get_post_taxonomy() {
		return $this->taxonomy;
	}

	/**
	 * Sets the group column key for sorting handled by including class
	 *
	 * @param string $group_column_key the group column key.
	 *
	 * @return void
	 */
	public function set_group_key( $group_column_key ) {
		$this->sort_group_column_key = $group_column_key;
	}

	/**
	 * Gets the group column key for sorting handled by including class
	 *
	 * @return string the group column key.
	 */
	public function get_group_key() {
		return $this->sort_group_column_key;
	}


	/**
	 * Enqueue css files.
	 *
	 * Action hook: admin_enqueue_scripts
	 *
	 * @see https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
	 *
	 * @param string $hook hook.
	 *
	 * @return void
	 */
	public function wpcd_enqueue_scripts_for_taxonomies_for_servers_and_apps( $hook ) {
		/**
		 * Code that used to be in this screen was removed and placed in the wp-cloud-deploy.php file
		 * because the css file was transformed to be used on more screens throughout WPCD.
		 */
	}

	/**
	 * To add custom filtering options based on meta fields.
	 * This filter will be added on server and app listing screen at the backend
	 *
	 * @param  string $post_type post type.
	 * @param  string $field_key field key.
	 * @param  string $first_option first option.
	 *
	 * @return string
	 */
	public function generate_meta_dropdown( $post_type, $field_key, $first_option ) {
		global $wpdb;

		$post_status = 'private';

		if ( 'wpcd_app_server' === $post_type ) {
			$permission = 'view_server';
		} elseif ( 'wpcd_app' === $post_type ) {
			$permission = 'view_app';
		}

		$posts = wpcd_get_posts_by_permission( $permission, $post_type, $post_status );

		if ( ! $posts || empty( $posts ) ) {
			return;
		}

		if ( count( $posts ) == 0 ) {
			return '';
		}

		$posts_placeholder = implode( ', ', array_fill( 0, count( $posts ), '%d' ) );
		$query_fields      = array_merge( array( $field_key ), $posts );
		$sql               = $wpdb->prepare( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value IS NOT NULL AND post_id IN ( " . $posts_placeholder . ' ) ORDER BY meta_value', $query_fields );
		$result            = $wpdb->get_results( $sql );
		if ( count( $result ) == 0 ) {
			return '';
		}
		$html          = '';
		$html         .= sprintf( '<select name="%s" id="filter-by-%s">', $field_key, $field_key );
		$html         .= sprintf( '<option value="">%s</option>', $first_option );
		$get_field_key = filter_input( INPUT_GET, $field_key, FILTER_SANITIZE_STRING );
		foreach ( $result as $row ) {
			if ( empty( $row->meta_value ) ) {
				continue;
			}
			$meta_value = $row->meta_value;

			$label = $meta_value;

			// Special handlng for the wpcd_server_provider field / dropdown.
			// Need to show alternate provider text from settings if that's set.
			if ( 'wpcd_server_provider' === $field_key ) {
				$label = wpcd_get_early_option( "vpn_{$meta_value}_alt_desc" );
				if ( empty( $label ) ) {
					$label = $meta_value;
				}
			}

			$selected = selected( $get_field_key, $meta_value, false );
			$html    .= sprintf( '<option value="%s" %s>%s</option>', $meta_value, $selected, $label );
		}
		$html .= '</select>';
		return $html;
	}

	/**
	 * To add custom filtering options based on custom taxonomy.
	 * This filter will be added on server and app listing screen at the backend
	 *
	 * @param  string $taxonomy taxonomy.
	 * @param  string $first_option first option.
	 *
	 * @return string
	 */
	public function generate_term_dropdown( $taxonomy, $first_option ) {

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		if ( count( $terms ) == 0 ) {
			return '';
		}
		$html          = '';
		$html         .= sprintf( '<select name="%s" id="filter-by-%s">', $taxonomy, $taxonomy );
		$html         .= sprintf( '<option value="">%s</option>', $first_option );
		$get_field_key = filter_input( INPUT_GET, $taxonomy, FILTER_SANITIZE_STRING );
		foreach ( $terms as $term ) {
			$term_id   = $term->term_id;
			$term_name = $term->name;
			$selected  = selected( $get_field_key, $term_id, false );
			$html     .= sprintf( '<option value="%d" %s>%s</option>', $term_id, $selected, $term_name );
		}
		$html .= '</select>';
		return $html;
	}

	/**
	 * To add custom filtering options based on servers.
	 * This filter will be added on app listing screen at the backend
	 *
	 * @param  string $first_option first option.
	 *
	 * @return string
	 */
	public function generate_server_dropdown( $first_option ) {

		global $wpdb;

		$post_type   = 'wpcd_app_server';
		$post_status = 'private';

		$servers = wpcd_get_posts_by_permission( 'view_server', $post_type, $post_status );

		if ( count( $servers ) == 0 ) {
			return '';
		}

		$servers_placeholder = implode( ', ', array_fill( 0, count( $servers ), '%d' ) );
		$query_fields        = array_merge( array( $post_type, $post_status ), $servers );
		$sql                 = $wpdb->prepare( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s  AND ID IN ( " . $servers_placeholder . ' ) ORDER BY post_title', $query_fields );
		$posts               = $wpdb->get_results( $sql );

		if ( count( $posts ) == 0 ) {
			return '';
		}

		$html          = '';
		$html         .= sprintf( '<select name="%s" id="filter-by-%s">', $post_type, $post_type );
		$html         .= sprintf( '<option value="">%s</option>', $first_option );
		$get_field_key = filter_input( INPUT_GET, $post_type, FILTER_SANITIZE_STRING );

		foreach ( $posts as $p ) {
			$post_id    = $p->ID;
			$post_title = $p->post_title;
			$selected   = selected( $get_field_key, $post_id, false );
			$html      .= sprintf( '<option value="%d" %s>%s</option>', $post_id, $selected, $post_title );
		}
		$html .= '</select>';
		return $html;
	}

	/**
	 * To add custom filtering options based on post author.
	 * This filter will be added on server and app listing screen at the backend
	 *
	 * @param string $post_type post type.
	 * @param string $field_key field key.
	 * @param string $first_option first option.
	 *
	 * @return string
	 */
	public function generate_owner_dropdown( $post_type, $field_key, $first_option ) {

		global $wpdb;

		$post_status = 'private';

		if ( 'wpcd_app_server' === $post_type ) {
			$permission = 'view_server';
		} elseif ( 'wpcd_app' === $post_type ) {
			$permission = 'view_app';
		}

		$posts = wpcd_get_posts_by_permission( $permission, $post_type, $post_status );

		if ( ! $posts || empty( $posts ) ) {
			return;
		}

		if ( count( $posts ) == 0 ) {
			return '';
		}

		$posts_placeholder = implode( ', ', array_fill( 0, count( $posts ), '%d' ) );
		$query_fields      = array_merge( array( $post_type, $post_status ), $posts );

		$sql   = $wpdb->prepare( "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s  AND ID IN ( " . $posts_placeholder . ' ) ORDER BY post_author', $query_fields );
		$posts = $wpdb->get_results( $sql );

		if ( count( $posts ) == 0 ) {
			return '';
		}

		$html          = '';
		$html         .= sprintf( '<select name="%s" id="filter-by-%s">', $field_key, $field_key );
		$html         .= sprintf( '<option value="">%s</option>', $first_option );
		$get_field_key = filter_input( INPUT_GET, $field_key, FILTER_SANITIZE_STRING );
		$owners        = array();

		foreach ( $posts as $p ) {
			if ( in_array( $p->post_author, $owners ) ) {
				continue;
			}
			$owners[]         = $p->post_author;
			$post_author_id   = $p->post_author;
			$post_author_name = empty( $post_author_id ) ? __( 'No Author or Owner provided.', 'wpcd') : esc_html( get_user_by( 'ID', $post_author_id )->user_login );
			$selected         = selected( $get_field_key, $post_author_id, false );
			$html            .= sprintf( '<option value="%d" %s>%s</option>', $post_author_id, $selected, $post_author_name );
		}
		$html .= '</select>';
		return $html;
	}

	/**
	 * To add custom filtering options based on meta key and value.
	 * This filter will be added on app listing screen at the backend
	 * Gets the array of server ids for specified meta key and value
	 *
	 * @param  string $field_key field key.
	 * @param  string $field_value field value.
	 *
	 * @return array
	 */
	public function get_app_server_ids( $field_key, $field_value ) {
		$parents = get_posts(
			array(
				'posts_per_page' => -1,
				'post_type'      => 'wpcd_app_server',
				'post_status'    => 'private',
				'fields'         => 'ids', // Just get IDs, not objects.
				'meta_query'     => array(
					array(
						'field'   => $field_key,
						'value'   => $field_value,
						'compare' => '=',
					),
				),
			)
		);

		if ( count( $parents ) ) {
			return $parents;
		} else {
			return array();
		}

	}

	/**
	 * Sorts server/app listing by server/app group column name and value
	 *
	 * Filter hook: posts_clauses
	 *
	 * @param  string $clauses clauses.
	 * @param  object $wp_query wp_query.
	 *
	 * @return string
	 */
	public function wpcd_sort_group_column( $clauses, $wp_query ) {
		global $wpdb;

		if ( isset( $wp_query->query['orderby'] ) && $wp_query->query['orderby'] == $this->get_group_key() ) {
			$clauses['join'] .= "LEFT OUTER JOIN {$wpdb->term_relationships} ON {$wpdb->posts}.ID={$wpdb->term_relationships}.object_id
				LEFT OUTER JOIN {$wpdb->term_taxonomy} USING (term_taxonomy_id)
				LEFT OUTER JOIN {$wpdb->terms} USING (term_id)";

			$clauses['where']  .= "AND (taxonomy = '" . $this->get_post_taxonomy() . "' OR taxonomy IS NULL)";
			$clauses['groupby'] = 'object_id';
			$clauses['orderby'] = "GROUP_CONCAT({$wpdb->terms}.name ORDER BY name ASC)";
			if ( strtoupper( $wp_query->get( 'order' ) ) == 'ASC' ) {
				$clauses['orderby'] .= 'ASC';
			} else {
				$clauses['orderby'] .= 'DESC';
			}
		}
		return $clauses;
	}

	/**
	 * Manipulate post counts by post status for server and app listing screen
	 *
	 * @param  string $post_type post type.
	 * @param  array  $views Array of table view links keyed by status slug.
	 * @param  string $permission Name of the permission.
	 *
	 * @return array
	 */
	public function wpcd_app_manipulate_views( $post_type, $views, $permission ) {

		if ( ! is_admin() && ! in_array( $post_type, array( 'wpcd_app_server', 'wpcd_app' ) ) ) {
			return $views;
		}

		$private_posts = wpcd_get_posts_by_permission( $permission, $post_type, 'private' );
		$private       = count( $private_posts );

		$trash_posts = wpcd_get_posts_by_permission( $permission, $post_type, 'trash' );
		$trash       = count( $trash_posts );

		$total = $private;

		$views['all'] = preg_replace( '/\(.+\)/U', '(' . $total . ')', $views['all'] );
		if ( array_key_exists( 'private', $views ) ) {
			if ( $private > 0 ) {
				$views['private'] = preg_replace( '/\(.+\)/U', '(' . $private . ')', $views['private'] );
			} else {
				unset( $views['private'] );
			}
		}

		if ( array_key_exists( 'trash', $views ) ) {
			if ( $trash > 0 ) {
				$views['trash'] = preg_replace( '/\(.+\)/U', '(' . $trash . ')', $views['trash'] );
			} else {
				unset( $views['trash'] );
			}
		}

		return $views;
	}

	/**
	 * Adds custom back to list button for server and app post types and post taxonomies
	 *
	 * @return void
	 */
	public function wpcd_backtolist_btn() {
		$screen         = get_current_screen();
		$post_taxonomy  = $this->get_post_taxonomy();
		$post_type      = str_replace( '_group', '', $post_taxonomy );
		$backtolist_txt = __( 'Back To List', 'wpcd' );

		if ( $screen->id == $post_type ) {
			$query          = sprintf( 'edit.php?post_type=%s', $post_type );
			$backtolist_url = admin_url( $query );
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$('.wp-heading-inline').append('<a href="<?php echo esc_html( $backtolist_url ); ?>" class="page-title-action"><?php echo esc_html( $backtolist_txt ); ?></a>');					
				});
			</script>
			<?php
		}

		if ( $screen->taxonomy === $post_taxonomy ) {
			$query          = sprintf( 'edit-tags.php?taxonomy=%s&post_type=%s', $post_taxonomy, $post_type );
			$backtolist_url = admin_url( $query );
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$('.wrap > h1').append('<a href="<?php echo esc_html( $backtolist_url ); ?>" class="page-title-action"><?php echo esc_html( $backtolist_txt ); ?></a>');					
				});
			</script>
			<?php
		}
	}

	/**
	 * Common code for exclude the server/app group terms.
	 *
	 * @param  string $wpcd_group_taxonomy wpcd_group_taxonomy.
	 *
	 * @return array
	 */
	public function wpcd_common_code_server_app_group_metabox( $wpcd_group_taxonomy ) {

		$wpcd_exclude_group_arr = array();

		$terms_arr = get_terms(
			$wpcd_group_taxonomy,
			array(
				'hide_empty' => false,
			)
		);

		if ( ! empty( $terms_arr ) ) {
			foreach ( $terms_arr as $key => $value ) {
				$term_id            = $value->term_id;
				$wpcd_only_admins   = get_term_meta( $term_id, 'wpcd_only_admins' );
				$wpcd_roles_allowed = get_term_meta( $term_id, 'wpcd_roles_allowed' );

				if ( ! empty( $wpcd_only_admins ) && empty( $wpcd_roles_allowed ) ) {
					if ( $wpcd_only_admins[0] == 1 ) {
						if ( ! wpcd_is_admin() ) {
							$wpcd_exclude_group_arr[] = $term_id;
						}
					}
				} elseif ( ! empty( $wpcd_roles_allowed ) ) {
					$current_user_id = get_current_user_id();
					$user_obj        = get_user_by( 'id', $current_user_id );
					$user_roles      = $user_obj->roles;

					$role_found = 0;
					if ( ! empty( $user_roles ) ) {
						foreach ( $user_roles as $key_role => $value_role ) {
							if ( in_array( $value_role, $wpcd_roles_allowed, true ) ) {
								$role_found = 1;
								break;
							}
						}
					}

					if ( ! wpcd_is_admin() && $role_found == 0 ) {
						$wpcd_exclude_group_arr[] = $term_id;
					}
				}
			}
		}

		return $wpcd_exclude_group_arr;

	}


	/**
	 * Exclude terms ids based on the global variables.
	 *
	 * @param  array $args args.
	 * @param  array $wpcd_exclude_group wpcd_exclude_group.
	 *
	 * @return array
	 */
	public function wpcd_exclude_term_ids_for_server_app_group( $args, $wpcd_exclude_group ) {

		if ( ! empty( $wpcd_exclude_group ) ) {
			$args['exclude'] = $wpcd_exclude_group;
			$args['orderby'] = 'id';
			$args['order']   = 'ASC';
		}

		return $args;
	}

}
