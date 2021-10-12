<?php
/**
 * Trait:
 * Contains some of the metabox related code for the server and apps cpt screens.
 * This particular trait handles the code for labels, descriptions and notes.
 * Used only by the class-wpcd-posts-app-server.php and class-wpcd-posts-app.php files which define the WPCD_POSTS_APP_SERVER and WPCD_POSTS_APP classes respectively.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_metaboxes_for_labels_notes_for_servers_and_apps.
 */
trait wpcd_metaboxes_for_labels_notes_for_servers_and_apps {

	/**
	 * Initialization hook to bootstrap the other hooks need.
	 * This function needs to be called by the class using this
	 * trait.
	 */
	public function init_hooks_for_labels_notes_for_servers_and_apps() {

		// Filter hook to add metabox for team.
		add_filter( 'rwmb_meta_boxes', array( $this, 'wpcd_register_labels_notes_metaboxes' ) );

	}

	/**
	 * Register the labels and notes metaboxes - these are metabox.io metaboxes, not standard WP metaboxes.
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * @param array $metaboxes Array of existing metaboxes.
	 *
	 * @return array new array of metaboxes
	 */
	public function wpcd_register_labels_notes_metaboxes( $metaboxes ) {

		$post_id   = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
		$post_type = $this->get_post_type( $post_id );

		$prefix = 'wpcd_';

		// Get the count of all tabs.
		$wpcd_note_count            = '';
		$wpcd_labels_count          = '';
		$wpcd_links_count           = '';
		$wpcd_admin_only_note_count = '';

		if ( 'wpcd_app' === $post_type || 'wpcd_app_server' === $post_type ) {
			$wpcd_note   = get_post_meta( $post_id, 'wpcd_note', true );
			$wpcd_labels = get_post_meta( $post_id, 'wpcd_labels', true );
			$wpcd_links  = get_post_meta( $post_id, 'wpcd_links', true );

			if ( ! empty( $wpcd_note ) && count( $wpcd_note ) > 0 ) {
				$wpcd_note_count = ' (' . count( $wpcd_note ) . ')';
			}

			if ( ! empty( $wpcd_labels ) && count( $wpcd_labels ) > 0 ) {
				$wpcd_labels_count = ' (' . count( $wpcd_labels ) . ')';
			}

			if ( ! empty( $wpcd_links ) && count( $wpcd_links ) > 0 ) {
				$wpcd_links_count = ' (' . count( $wpcd_links ) . ')';
			}

			// Check if admin user.
			if ( wpcd_is_admin() ) {
				$wpcd_admin_only_note = get_post_meta( $post_id, 'wpcd_admin_only_note', true );
				if ( ! empty( $wpcd_admin_only_note ) && count( $wpcd_admin_only_note ) > 0 ) {
					$wpcd_admin_only_note_count = ' (' . count( $wpcd_admin_only_note ) . ')';
				}
			}
		}

		// Tabs.
		$tabs = array(
			'wpcd_descriptions' => array(
				'label' => 'Descriptions',
				'icon'  => 'dashicons-text-page',
			),
			'wpcd_notes'        => array(
				'label' => sprintf( __( 'Notes %s', 'wpcd' ), $wpcd_note_count ),
				'icon'  => 'dashicons-analytics',
			),
			'wpcd_labels'       => array(
				'label' => sprintf( __( 'Labels %s', 'wpcd' ), $wpcd_labels_count ),
				'icon'  => 'dashicons-format-quote',
			),
			'wpcd_links'        => array(
				'label' => sprintf( __( 'Links %s', 'wpcd' ), $wpcd_links_count ),
				'icon'  => 'dashicons-admin-links',
			),
		);

		// If is admin, add in a tab for admin-only notes.
		if ( wpcd_is_admin() ) {
			$tabs['wpcd_admin_only_notes'] = array(
				'label' => sprintf( __( 'Notes - Admin Eyes Only %s', 'wpcd' ), $wpcd_admin_only_note_count ),
				'icon'  => 'dashicons-analytics',
			);
		}

		// Fields for tabs.
		$fields = array(
			array(
				'name' => '',
				'id'   => $prefix . 'labels_notes_intro_header',
				'type' => 'custom_html',
				'std'  => __( 'Click the update button in the upper right when you are finished to save your changes.', 'wpcd' ),
			),

			array(
				'name'        => __( 'Short Description', 'wpcd' ),
				'id'          => $prefix . 'short_description',
				'type'        => 'text',
				'placeholder' => __( 'Enter a short description', 'wpcd' ),
				'size'        => 60,
				'desc'        => __( 'This description can sometimes appear in the server or app lists', 'wpcd' ),
				'tab'         => 'wpcd_descriptions',
			),
			array(
				'name' => __( 'Long Description', 'wpcd' ),
				'id'   => $prefix . 'long_description',
				'type' => 'textarea',
				'cols' => 120,
				'rows' => 10,
				'tab'  => 'wpcd_descriptions',
			),
			// Notes tab.
				array(
					'name'        => '',
					'id'          => $prefix . 'note',
					'type'        => 'textarea',
					'placeholder' => __( 'Write your note here', 'wpcd' ),
					'cols'        => 60,
					'rows'        => 5,
					'clone'       => true,
					'sort_clone'  => true,
					'add_button'  => __( 'Add another note', 'wpcd' ),
					'tab'         => 'wpcd_notes',
				),
			// Labels tab.
				array(
					'name' => '',
					'id'   => $prefix . 'labels_header',
					'type' => 'custom_html',
					'std'  => __( 'These labels appear to the right of the server/app/post-title in the server or app list. Use sparingly to avoid cluttering the server/app/post-title column.', 'wpcd' ),
					'tab'  => 'wpcd_labels',
				),
			array(
				'name'       => __( 'Label(s)', 'wpcd' ),
				'id'         => $prefix . 'labels',
				'type'       => 'text',
				'size'       => 60,
				'clone'      => true,
				'sort_clone' => true,
				'add_button' => __( 'Add another label', 'wpcd' ),
				'tab'        => 'wpcd_labels',
			),
			// links tab.
				array(
					'name'       => '',
					'id'         => $prefix . 'links',
					'type'       => 'group',
					'clone'      => true,
					'sort_clone' => true,
					'add_button' => __( 'Add another link', 'wpcd' ),
					'tab'        => 'wpcd_links',
					'fields'     => array(
						array(
							'name' => __( 'Link Label', 'wpcd' ),
							'id'   => 'wpcd_link_label',
							'type' => 'text',
							'size' => 120,
						),
						array(
							'name' => __( 'URL', 'wpcd' ),
							'id'   => 'wpcd_link_url',
							'type' => 'url',
							'size' => 120,
						),
						array(
							'name' => __( 'Description', 'wpcd' ),
							'id'   => 'wpcd_link_desc',
							'type' => 'text',
							'size' => 120,
						),
					),
				),

		);

		// If is admin, add in fields for the admin-only notes.
		if ( wpcd_is_admin() ) {
			$fields[] = array(
				'name'        => '',
				'id'          => $prefix . 'admin_only_note',
				'type'        => 'textarea',
				'placeholder' => __( 'Write your note here', 'wpcd' ),
				'cols'        => 60,
				'rows'        => 5,
				'clone'       => true,
				'sort_clone'  => true,
				'add_button'  => __( 'Add another note', 'wpcd' ),
				'tab'         => 'wpcd_admin_only_notes',
			);
		}

		// Register the metabox for labels, notes and descriptions.
		$metaboxes[] = array(
			'id'       => $prefix . 'labels_notes',
			'title'    => __( 'Descriptions, Notes & Labels', 'wpcd' ),
			'pages'    => array( $post_type ),
			'priority' => 'default',
			'tabs'     => $tabs,
			'fields'   => $fields,
		);

		return $metaboxes;

	}

}
