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
					'name'    => __( 'Expired?', 'wpcd' ),
					'desc'    => __( 'Has site already expired?', 'wpcd' ),
					'tooltip' => __( 'If you change this option you will also need to change the date above to a future date. Otherwise the site will again expire within a few minutes. Also, if you change this option to mark an unexpired site as expired, none of the expiration options in settings will run.', 'wpcd' ),
					'id'      => 'wpcd_app_expired_status',
					'type'    => 'checkbox',
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

					/**
					 * Now do other stuff based on what's in settings.
					 *
					 * Note: if we ever want to handle sites that are marked for deletion but not processed for some reason
					 * (eg: admin manually marked a site as expired) we can move this line outside the IF block.
					 */
					$this->apply_expiration_rules( $app->ID );

				}
			}
		}

	}

	/**
	 * Do actions for sites that have just expired.
	 *
	 * @param int $app_id The ID of the app we're working with.
	 */
	public function apply_expiration_rules( $app_id ) {

		// Verify app to make sure it's valid. It should be but do it again anyway.
		$post = WPCD_WORDPRESS_APP()->get_app_by_app_id( $app_id );
		if ( empty( $post ) ) {
			return;
		}

		// Make sure post type is wpcd_app.
		if ( 'wpcd_app' !== $post->post_type ) {
			return;
		}

		// Is the site enabled?  Can't do certain things if it's now.
		$site_status = WPCD_WORDPRESS_APP()->site_status( $app_id );

		if ( boolval( wpcd_get_option( 'wordpress_app_sites_expired_delete_site' ) ) ) {
			// Schedule site to be deleted.  Since the site is going to be deleted, it does not make since to apply any of the other options beforehand.
			do_action( 'wpcd_log_notification', $app_id, 'alert', __( 'This site is being scheduled for deletion in pending tasks because the it has expired.', 'wpcd' ), 'misc', null );
			$args['action_hook'] = 'wpcd_pending_log_delete_wp_site_and_backups';
			$args['action']      = 'remove_full';
			$task_name           = 'delete-site-and-backups';
			$task_desc           = __( 'Site Expiration: Waiting to delete site and associated backups.', 'wpcd' );
			WPCD_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $app_id, $task_name, $app_id, $args, 'ready', $app_id, $task_desc );
		} else {
			// Maybe disable the site. But only do it if the site has not already in that state.
			if ( boolval( wpcd_get_option( 'wordpress_app_sites_expired_disable_site' ) ) ) {
				if ( 'on' === $site_status ) {
					do_action( 'wpcd_wordpress-app_do_toggle_site_status', $app_id, 'site-status', 'off' );
					do_action( 'wpcd_log_notification', $app_id, 'alert', __( 'This site is being disabled because the it has expired.', 'wpcd' ), 'misc', null );
				}
			}

			// Maybe apply http authentication to the site. But only do it if the site is enabled - this way we don't try to apply config settings to a file that might not exist.
			if ( boolval( wpcd_get_option( 'wordpress_app_sites_expired_enable_http_auth' ) ) ) {
				if ( 'on' === $site_status ) {
					do_action( 'wpcd_wordpress-app_do_site_enable_http_auth', $app_id );
					do_action( 'wpcd_log_notification', $app_id, 'alert', __( 'This site is being password protected because it has expired.', 'wpcd' ), 'misc', null );
				}
			}

			// Maybe apply an admin lock to the site. But only do it if the site has not already in that state.
			if ( boolval( wpcd_get_option( 'wordpress_app_sites_expired_admin_lock_site' ) ) ) {
				if ( ! WPCD_WORDPRESS_APP()->get_admin_lock_status( $app_id ) ) {
					WPCD_WORDPRESS_APP()->set_admin_lock_status( $app_id, 'on' );
					do_action( 'wpcd_log_notification', $app_id, 'alert', __( 'This site has had its admin lock applied because it has expired.', 'wpcd' ), 'misc', null );
				}
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
