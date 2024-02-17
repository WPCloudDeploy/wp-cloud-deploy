<?php
/**
 * This class handles functions related to expiring sites.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup functions related to expiring sites.
 *
 * @package wpcd
 * @version 1.0.0 / wpcd
 * @since 5.7.0
 */
class WPCD_App_Expiration {

	/**
	 * Constructor function.
	 */
	public function __construct() {

		// Filter hook to add custom meta boxes.
		add_filter( 'rwmb_meta_boxes', array( $this, 'wpcd_app_register_meta_boxes' ), 10, 1 );

		// Add state to show if an app is expired.
		add_filter( 'display_post_states', array( $this, 'display_post_states' ), 20, 2 );
	}

	/**
	 * Add custom metabox for expiration date on app details screen.
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * @param  array $metaboxes metaboxes.
	 *
	 * @return array
	 */
	public function wpcd_app_register_meta_boxes( $metaboxes ) {

		// Register the metabox for site expiration.
		$metaboxes[] = array(
			'id'       => 'wpcd_app_site_expiration_metabox',
			'title'    => __( 'Site Expiration (UTC +0)', 'wpcd' ),
			'pages'    => array( 'wpcd_app' ), // displays on wpcd_app post type only.
			'context'  => 'side',
			'priority' => 'low',
			'fields'   => array(

				// Explantion field.
				array(
					'type' => 'custom_html',
					'std'  => __( 'You can control what happens when a site expires in SETTINGS.', 'wpcd' ),
				),
				// add a date-time field for site expiration.
				array(
					'desc'       => __( 'When does this site expire?', 'wpcd' ),
					'id'         => 'wpcd_app_expires',
					'type'       => 'datetime',
					'js_options' => array(
						'stepMinute'      => 1,
						'showTimepicker'  => true,
						'controlType'     => 'select',
						'showButtonPanel' => false,
						'oneLine'         => true,
					),
					'inline'     => false,
					'timestamp'  => true,
				),

				// Divider.
				array(
					'type' => 'custom_html',
					'std'  => '<hr/>',
				),

				// Checkbox for if site has expired.
				array(
					'name' => __( 'Expired?', 'wpcd' ),
					'desc' => __( 'Has site already expired?', 'wpcd' ),
					'id'   => 'wpcd_app_expired_status',
					'type' => 'checkbox',
				),

			),
		);

		return $metaboxes;
	}


	/**
	 * Add any apps that are expired to the pending logs tables.
	 *
	 * This is generally called from a CRON process.
	 */
	public function do_tasks() {

		/**
		 * Find all apps with an expiration less than the current time.
		 *
		 * Note: In a future version we should also search for apps
		 * that are not already marked as 'expired'.
		 * We're not doing that now because it complicates the metaquery
		 * since we'd have to search for both empty value and a value of
		 * '0' or false.
		 */
		$current_linux_epoc = time();
		$args               = array(
			'post_type'      => 'wpcd_app',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => 'wpcd_app_expires',
					'value'   => $current_linux_epoc,
					'type'    => 'NUMERIC',
					'compare' => '<',
				),
			),
		);

		$apps = get_posts( $args );

		// If we have apps, do stuff.
		if ( $apps ) {

			foreach ( $apps as $app ) {

				// Is the app already marked as expired?  If so, skip it!
				$is_expired = $this->get_expired_status( $app->ID );

				// Mark the app as expired.
				if ( false === $is_expired ) {
					$this->set_as_expired( $app->ID );
				}

				// Now do other stuff based on what's in settings.

			}
		}

	}

	/**
	 * Set an app as expired.
	 *
	 * @param int $app_id The app id we're querying.
	 */
	public function set_as_expired( $app_id ) {
		update_post_meta( $app_id, 'wpcd_app_expired_status', 1 );
	}

	/**
	 * Is an app expired?  Return true or false.
	 *
	 * @param int $app_id The app id we're querying.
	 */
	public function get_expired_status( $app_id ) {

		$status = get_post_meta( $app_id, 'wpcd_app_expired_status', true );
		if ( 1 === (int) $status ) {
			return true;
		} else {
			return false;
		}

		return false;

	}


	/**
	 * Set the post state display expired status in the app list.
	 *
	 * Filter Hook: display_post_states
	 *
	 * @param array  $states The current states for the CPT record.
	 * @param object $post The post object.
	 *
	 * @return array $states.
	 */
	public function display_post_states( $states, $post ) {

		if ( 'wpcd_app' === get_post_type( $post ) ) {

			if ( true === $this->get_expired_status( $post->ID ) ) {
				$states['wpcd-app-expired-flag'] = __( 'Expired', 'wpcd' );
			}
		}

		return $states;

	}

}
