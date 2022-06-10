<?php
/**
 * Trait:
 * Contains all the metabox related code for the app server cpt screens.
 * Used only by the class-wordpress.php file which defines the
 * WPCD_WORDPRESS_APP class.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_wpapp_metaboxes_server
 */
trait wpcd_wpapp_metaboxes_server {

	/**
	 * Registers a stub metabox for servers with a filter
	 * that can be used by child apps or other parts of the plugins
	 * to paint their own metaboxes.
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * Note that this is a METABOX.IO metabox hook,
	 * not a core WP hook.
	 *
	 * @param array $metaboxes Array of metaboxes.
	 *
	 * @return array Array of metaboxes to paint.
	 */
	public function register_server_metaboxes( $metaboxes ) {

		$metaboxes = apply_filters( "wpcd_server_{$this->get_app_name()}_metaboxes", $metaboxes );

		return $metaboxes;

	}

	/**
	 * Add metaboxes for the WordPress app into the SERVER details CTP screen.
	 *
	 * Filter hook: wpcd_server_{$this->get_app_name()}_metaboxes
	 *
	 * Note that this is a METABOX.IO metabox hook,
	 * not a core WP hook.
	 *
	 * @param array $meta_boxes Array of existing metaboxes.
	 *
	 * @return array Array of new metaboxes
	 */
	public function add_meta_boxes_server( $meta_boxes ) {

		/* Get the ID for the post */
		$id = wpcd_get_current_page_server_id();

		/* If empty id, not a post so return */
		if ( empty( $id ) ) {
			return $meta_boxes;
		}

		/* Make sure that we are on a server post  */
		if ( get_post_type( $id ) <> 'wpcd_app_server' ) {
			return $meta_boxes;
		}

		/* Make sure that we're on a wordpress-app server */
		if ( get_post_meta( $id, 'wpcd_server_server-type', true ) <> 'wordpress-app' ) {
			return $meta_boxes;
		}

		/* Where should the server tabs go? */
		$tab_style = $this->get_tab_style_server();

		/* Initial array that will hold field list */
		$fields = array();

		/* Paint fields at the top of the screen */
		switch ( $tab_style ) {
			case 'left':
				/* If we are painting the tabs vertically, we need to add a new metabox at the top of the screen to show the core details of the site. */
				$meta_boxes[] = array(
					'id'         => "wpcd_server_{$this->get_app_name()}_tab_top_of_server_details",
					'title'      => __( 'WordPress Server', 'wpcd' ),
					'class'      => 'wpcd-wpapp-actions',
					'post_types' => 'wpcd_app_server',
					'fields'     => $this->get_general_fields_server( $fields, $id ),
					'class'      => "wpcd_server_{$this->get_app_name()}_tab_top_of_server_details",
					'style'      => 'seamless',
				);
				break;
			default:
				/* Show fields at the top of the screen if painting tabs horizontally. */
				$fields = $this->get_general_fields_server( $fields, $id );  // get fields to show at the top of the metabox.
				break;
		}

		/* Get tabs and fields - these filters are implemented throughout the wpapp code */
		$tabs   = apply_filters( "wpcd_server_{$this->get_app_name()}_get_tabnames", array(), $id );
		$fields = apply_filters( "wpcd_server_{$this->get_app_name()}_get_tabs", $fields, $id );

		/* Give each tab a default icon */
		foreach ( $tabs as $key => $tab ) {
			if ( empty( $tab['icon'] ) ) {
				$tabs[ $key ]['icon'] = wpcd_get_random_fa_class();
			}
		}

		$meta_boxes[] = array(
			'id'          => "wpcd_server_{$this->get_app_name()}_tab3",
			'title'       => __( 'WordPress Server', 'wpcd' ),
			'class'       => 'wpcd-wpapp-actions',
			'tabs'        => $tabs,
			'tab_style'   => $tab_style,
			'tab_wrapper' => true,
			'style'       => $tab_style === 'left' ? 'seamless' : '',
			'post_types'  => 'wpcd_app_server',
			'fields'      => $fields,
		);

		return $meta_boxes;
	}

	/**
	 * To add custom metabox on server details screen.
	 * Multiple metaboxes created for:
	 * 1. Site limits
	 * 2. (comming soon)
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * @param  array $metaboxes metaboxes.
	 *
	 * @return array
	 */
	public function register_server_metaboxes_misc( $metaboxes ) {

		// What's the post id we're looking at?
		$post_id = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );

		// Register a metabox to hold site limits (and possibly other limits in the future).
		$metaboxes[] = array(
			'id'       => 'wpcd_server_site_quota',
			'title'    => __( 'Quotas and Limits', 'wpcd' ),
			'pages'    => array( 'wpcd_app_server' ), // displays on wpcd_app post type only.
			'context'  => 'side',
			'priority' => 'low',
			'fields'   => array(

				// Field to hold the max sites allowed on the server.
				array(
					'name'    => __( 'Max Sites', 'wpcd' ),
					'id'      => 'wpcd_server_max_sites',
					'type'    => 'number',
					'std'     => 0,
					'tooltip' => __( 'This is the maximum number of sites a customer will be allowed to place on this server. Admins will not be limited by this number.', 'wpcd' ),
				),

			),
		);

		return $metaboxes;

	}

}
