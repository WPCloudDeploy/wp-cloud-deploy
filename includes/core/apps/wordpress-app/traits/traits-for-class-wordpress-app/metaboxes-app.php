<?php
/**
 * Trait:
 * Contains all the metabox related code for the apps cpt screens.
 * Used only by the class-wordpress.php file which defines the
 * WPCD_WORDPRESS_APP class.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_wpapp_metaboxes_app
 */
trait wpcd_wpapp_metaboxes_app {

	/**
	 * Add metaboxes for the WordPress app into the APP details CPT screen.
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_metaboxes.
	 * Note that this is a METABOX.IO metabox hook,
	 * not a core WP hook.
	 *
	 * @param array $meta_boxes Array of existing metaboxes.
	 *
	 * @return array Array of new metaboxes
	 */
	public function add_meta_boxes( $meta_boxes ) {

		/* Get the ID for the post */
		$id = wpcd_get_current_page_server_id();

		/* If empty id, not a post so return */
		if ( empty( $id ) ) {
			return $meta_boxes;
		}

		/* Make sure that we are on a wpapp app post */
		if ( ! ( $this->get_app_name() == get_post_meta( $id, 'app_type', true ) ) ) {
			return $meta_boxes;
		}

		/* Initial array that will hold field list */
		$fields = array();

		/* Paint fields at the top of the screen */
		$tab_style = $this->get_tab_style();
		switch ( $tab_style ) {
			case 'left':
				/* If we are painting the tabs vertically, we need to add a new metabox at the top of the screen to show the core details of the site. */
				$meta_boxes[] = array(
					'id'         => "wpcd_{$this->get_app_name()}_tab_top_of_site_details",
					'title'      => sprintf( __( 'Server: %1$s, Region: %2$s, Provider: %3$s', 'wpcd' ), $this->get_server_name( $id ), $this->get_server_region( $id ), WPCD()->wpcd_get_cloud_provider_desc( $this->get_server_provider( $id ) ) ),
					'class'      => 'wpcd-wpapp-actions',
					'post_types' => 'wpcd_app',
					'fields'     => $this->get_general_fields( $fields, $id ),
					'class'      => "wpcd_{$this->get_app_name()}_tab_top_of_site_details",
					'style'      => 'seamless',
				);
				break;
			default:
				/* Show fields at the top of the screen if painting tabs horizontally. */
				$fields = $this->get_general_fields( $fields, $id );  // get fields to show at the top of the metabox.
				break;
		}

		/* Get tabs and fields - these filters are implemented throughout the wpapp code */
		$tabs   = apply_filters( "wpcd_app_{$this->get_app_name()}_get_tabnames", array(), $id );
		$fields = apply_filters( "wpcd_app_{$this->get_app_name()}_get_tabs", $fields, $id );

		/* Give each tab a default icon */
		$cnt = 0;
		foreach ( $tabs as $key => $tab ) {
			if ( empty( $tab['icon'] ) ) {
				$cnt++;
				$tabs[ $key ]['icon'] = wpcd_get_some_fa_classes()[ $cnt ];
			}
		}

		/* Setup some variables used for printing output */
		$server_post_id = get_post_meta( $id, 'parent_post_id', true );
		$user_id        = get_current_user_id();
		$post_author    = get_post( $server_post_id )->post_author;
		if ( wpcd_user_can( $user_id, 'view_server', $server_post_id ) || $post_author == $user_id ) {
			$url         = admin_url( sprintf( 'post.php?post=%s&action=edit', $server_post_id ) );
			$server_name = sprintf( '<a href="%s" target="_blank" class="wpcd_metabox_title_value wpcd_metabox_title_value_server_name">%s</a>', $url, $this->get_server_name( $id ) );
		} else {
			$server_name = '<span class="wpcd_metabox_title_value wpcd_metabox_title_value_server_name">' . $this->get_server_name( $id ) . '</span>';
		}

		$server_region   = '<span class="wpcd_metabox_title_value wpcd_metabox_title_value_server_region">' . $this->get_server_region( $id ) . '</span>';
		$server_provider = '<span class="wpcd_metabox_title_value wpcd_metabox_title_value_server_provider">' . WPCD()->wpcd_get_cloud_provider_desc( $this->get_server_provider( $id ) ) . '</span>';

		$meta_boxes[] = array(
			'id'          => "wpcd_{$this->get_app_name()}_tab2",
			'title'       => sprintf( __( 'Server: %1$s Region: %2$s Provider: %3$s', 'wpcd' ), $server_name, $server_region, $server_provider ),
			'class'       => 'wpcd-wpapp-actions',
			'tabs'        => $tabs,
			'tab_style'   => $tab_style,
			'tab_wrapper' => true,
			'post_types'  => 'wpcd_app',
			'fields'      => $fields,
		);

		return $meta_boxes;
	}

	/**
	 * Add metabox to the app post detail screen in wp-admin.
	 *
	 * Action Hook: add_meta_box
	 *
	 * @param object $post post object.
	 */
	public function app_admin_add_meta_boxes( $post ) {

		/* Only render for true admins! */
		if ( ! wpcd_is_admin() ) {
			return;
		}

		/* Only render if the settings option is turned on. */
		if ( ! (bool) wpcd_get_option( 'show-advanced-metaboxes' ) ) {
			return;
		}

		/* Only paint metabox when the app-type matches the current app type. */
		if ( ! ( $this->get_app_name() === get_post_meta( $post->ID, 'app_type', true ) ) ) {
			return;
		}

		// Add APP DETAIL meta box into APPS custom post type.
		add_meta_box(
			'wpapp_app_detail',
			__( 'WordPress App', 'wpcd' ),
			array( $this, 'render_wpapp_app_details_meta_box' ),
			'wpcd_app',
			'advanced',
			'high'
		);
	}

	/**
	 * Render the WP APP detail meta box in the app post detail screen in wp-admin
	 *
	 * @param object $post Current post object.
	 *
	 * @print HTML $html HTML of the meta box
	 */
	public function render_wpapp_app_details_meta_box( $post ) {

		/* Only render data in the metabox when the app-type is a wp app. */
		if ( ! ( $this->get_app_name() == get_post_meta( $post->ID, 'app_type', true ) ) ) {
			return;
		}

		$html = '';

		$wpcd_wpapp_domain             = get_post_meta( $post->ID, 'wpapp_domain', true );
		$wpcd_wpapp_userid             = get_post_meta( $post->ID, 'wpapp_user', true );
		$wpcd_wpapp_email              = get_post_meta( $post->ID, 'wpapp_email', true );
		$wpcd_wpapp_password           = $this::decrypt( get_post_meta( $post->ID, 'wpapp_password', true ) );
		$wpcd_wpapp_initial_version    = get_post_meta( $post->ID, 'wpapp_version', true );
		$wpcd_wpapp_staging_domain     = get_post_meta( $post->ID, 'wpapp_staging_domain', true );
		$wpcd_wpapp_staging_domain_id  = get_post_meta( $post->ID, 'wpapp_staging_domain_id', true );
		$wpcd_wpapp_wc_order_id        = get_post_meta( $post->ID, 'wpapp_wc_order_id', true );
		$wpcd_wpapp_wc_subscription_id = get_post_meta( $post->ID, 'wpapp_wc_subscription_id', true );

		ob_start();
		require wpcd_path . 'includes/core/apps/wordpress-app/templates/wp_app_details.php';
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;

	}

	/**
	 * Handles saving the meta box in the app post detail screen in wp-admin.
	 *
	 * @param int    $post_id Post ID.
	 * @param object $post    Post object.
	 * @return null
	 */
	public function app_admin_save_meta_values( $post_id, $post ) {

		/* Only save metabox data when the app-type is a WordPress app. */
		if ( ! ( $this->get_app_name() == get_post_meta( $post->ID, 'app_type', true ) ) ) {
			return;
		}

		// Add nonce for security and authentication.
		$nonce_name   = filter_input( INPUT_POST, 'wpapp_meta', FILTER_SANITIZE_STRING );
		$nonce_action = 'wpcd_wp_app_nonce_meta_action';

		// Check if nonce is valid.
		if ( ! wp_verify_nonce( $nonce_name, $nonce_action ) ) {
			return;
		}

		// Check if user has permissions to save data.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if not an autosave.
		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Check if not a revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Make sure post type is wpcd_app.
		if ( 'wpcd_app' !== $post->post_type ) {
			return;
		}

		/* Get new values */
		$wpcd_wpapp_domain             = filter_input( INPUT_POST, 'wpcd_wpapp_domain', FILTER_SANITIZE_STRING );
		$wpcd_wpapp_userid             = filter_input( INPUT_POST, 'wpcd_wpapp_userid', FILTER_SANITIZE_STRING );
		$wpcd_wpapp_email              = filter_input( INPUT_POST, 'wpcd_wpapp_email', FILTER_SANITIZE_EMAIL );
		$wpcd_wpapp_password           = filter_input( INPUT_POST, 'wpcd_wpapp_password' );  // cannot sanitize passwords unfortunately.
		$wpcd_wpapp_initial_version    = filter_input( INPUT_POST, 'wpcd_wpapp_initial_version', FILTER_SANITIZE_STRING );
		$wpcd_wpapp_staging_domain     = filter_input( INPUT_POST, 'wpcd_wpapp_staging_domain', FILTER_SANITIZE_STRING );
		$wpcd_wpapp_staging_domain_id  = filter_input( INPUT_POST, 'wpcd_wpapp_staging_domain_id', FILTER_SANITIZE_NUMBER_INT );
		$wpcd_wpapp_wc_order_id        = filter_input( INPUT_POST, 'wpcd_wpapp_wc_order_id', FILTER_SANITIZE_NUMBER_INT );
		$wpcd_wpapp_wc_subscription_id = filter_input( INPUT_POST, 'wpcd_wpapp_wc_subscription_id', FILTER_SANITIZE_NUMBER_INT );

		/* Add/Update new values to database */
		update_post_meta( $post_id, 'wpapp_domain', $wpcd_wpapp_domain );
		update_post_meta( $post_id, 'wpapp_userid', $wpcd_wpapp_userid );
		update_post_meta( $post_id, 'wpapp_email', $wpcd_wpapp_email );
		update_post_meta( $post_id, 'wpapp_password', $this::encrypt( $wpcd_wpapp_password ) );
		update_post_meta( $post_id, 'wpapp_version', $wpcd_wpapp_initial_version );
		update_post_meta( $post_id, 'wpapp_staging_domain', $wpcd_wpapp_staging_domain );
		update_post_meta( $post_id, 'wpapp_staging_domain_id', $wpcd_wpapp_staging_domain_id );
		update_post_meta( $post_id, 'wpapp_wc_order_id', $wpcd_wpapp_wc_order_id );
		update_post_meta( $post_id, 'wpapp_wc_subscription_id', $wpcd_wpapp_wc_subscription_id );

	}

	/**
	 * To add custom metabox on app details screen.
	 * Multiple metaboxes created for:
	 * 1. Disk limits
	 * 2. (comming soon)
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * @param  array $metaboxes metaboxes.
	 *
	 * @return array
	 */
	public function add_meta_boxes_misc( $metaboxes ) {

		// What's the post id we're looking at?
		$post_id = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );

		// How much diskspace is allowed?
		// @TODO: this value isn't collected correctly inside this function - something about the way MBIO sets up the metabox prevents this.
		$allowed_disk = (int) get_post_meta( $post_id, 'wpcd_app_max_disk_space_allowed', true );

		// Register a metabox to hold disk limits (and possibly other limits in the future).
		$metaboxes[] = array(
			'id'       => 'wpcd_app_quotas',
			'title'    => __( 'Quotas and Limits', 'wpcd' ),
			'pages'    => array( 'wpcd_app' ), // displays on wpcd_app post type only.
			'context'  => 'side',
			'priority' => 'low',
			'fields'   => array(

				// Field to hold the max diskspace allowed.
				array(
					'name' => __( 'Disk Quota (MB).', 'wpcd' ),
					'id'   => 'wpcd_app_disk_space_quota',
					'type' => 'number',
					'std'  => 500,
				),

				// Field to hold current usage.
				// @TODO: doesn't work because we can't get the $allowed_disk var properly above.
			/*
				array(
					'name'       => __( 'Current Usage.', 'wpcd' ),
					'id'         => 'wpcd_app_current_disk_used',
					'type'       => 'slider',
					'std'        => $this->get_total_disk_used( $post_id ),
					'js_options' => array(
						'min' => 0,
						'max' => $allowed_disk,
					),
					'suffix'     => __( 'MB', 'wpcd' ),
					'save_field' => false,
					'readonly'   => true,
					'disabled'   => true,
				),
			*/

			),
		);

		return $metaboxes;

	}


}
