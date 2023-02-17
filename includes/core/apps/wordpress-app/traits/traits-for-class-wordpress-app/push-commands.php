<?php
/**
 * Trait:
 * Contains functions related to running commands that
 * are pushed from the server without being called us.
 *
 * Examples of these commands are auto-backup notices,
 * server status notices and more.
 *
 * Used only by the class-wordpress.php file which defines the
 * WPCD_WORDPRESS_APP class.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_wpapp_push_commands
 */
trait wpcd_wpapp_push_commands {

	/**
	 * Handles server status pushes from bash script #24 - part 1 - server status.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_command_server_status_completed || wpcd_{$this->get_app_name()}_command_{$name}_{$status}
	 *
	 * @param int    $id server post id.
	 * @param int    $command_id an id that is given to the bash script at the time it's first run. Doesn't do anything for us in this context so it's not used here.
	 * @param string $name name.
	 * @param string $status status.
	 *
	 * @return void.
	 */
	public function push_command_server_status_completed( $id, $command_id, $name, $status ) {

		// Set variable to status, in this case it is always "completed" - will be used in action hooks later.
		$status = 'completed';

		// set variable to name, in this case it is always "server_status" - will be used in action hooks later.
		$name = 'server_status';

		// Potentially break out into a new function if apt_already_running_warning var is set on the incoming hook. See bash script #29.
		$apt_already_running_warning = sanitize_text_field( filter_input( INPUT_GET, 'apt_already_running_warning', FILTER_UNSAFE_RAW ) );
		if ( 'yes' === $apt_already_running_warning ) {
			// Later we can probably call a new function so we can throw hooks and filters and such but for now, just a warning in the error log will suffice.
			do_action( 'wpcd_log_error', __( 'An update request was made while another instance of APT was alrady running.  The request was ignored.', 'wpcd' ), 'warning', __FILE__, __LINE__ );
			return;
		}

		// Create an array to hold items taken from the $_request object.
		$server_status_items = array();

		// get restart status item.
		$server_status_items['restart'] = sanitize_text_field( filter_input( INPUT_GET, 'restart', FILTER_UNSAFE_RAW ) );
		if ( ! in_array( $server_status_items['restart'], array( 'yes', 'no' ) ) ) {
			$server_status_items['restart'] = 'unknown';
		}

		// get numeric elements.
		$server_status_items['total_updates']           = filter_input( INPUT_GET, 'total_updates', FILTER_SANITIZE_NUMBER_INT );
		$server_status_items['security_updates']        = filter_input( INPUT_GET, 'security_updates', FILTER_SANITIZE_NUMBER_INT );
		$server_status_items['unattended_package_num']  = filter_input( INPUT_GET, 'unattended_package_num', FILTER_SANITIZE_NUMBER_INT );
		$server_status_items['free_disk_space']         = filter_input( INPUT_GET, 'free_disk', FILTER_SANITIZE_NUMBER_INT );
		$server_status_items['free_disk_space_percent'] = filter_input( INPUT_GET, 'free_disk_percentage', FILTER_SANITIZE_NUMBER_INT );
		$server_status_items['total_memory']            = filter_input( INPUT_GET, 'Total_mem', FILTER_SANITIZE_NUMBER_INT );
		$server_status_items['used_memory']             = filter_input( INPUT_GET, 'Used_mem', FILTER_SANITIZE_NUMBER_INT );
		$server_status_items['used_memory_percent']     = filter_input( INPUT_GET, 'Used_mem_percentage', FILTER_SANITIZE_NUMBER_INT );
		$server_status_items['database_size']           = filter_input( INPUT_GET, 'Database_size', FILTER_SANITIZE_NUMBER_INT );
		$server_status_items['database_size_largest']   = filter_input( INPUT_GET, 'Largest_DB_Size', FILTER_SANITIZE_NUMBER_INT );
		$server_status_items['up_time']                 = filter_input( INPUT_GET, 'uptime', FILTER_SANITIZE_NUMBER_INT );
		$server_status_items['cpu_now']                 = filter_input( INPUT_GET, 'cpu_now', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
		$server_status_items['cpu_since_reboot']        = filter_input( INPUT_GET, 'cpu_since_reboot', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
		$server_status_items['apt_check_error']         = filter_input( INPUT_GET, 'Apt_Check_Error', FILTER_SANITIZE_NUMBER_INT );

		// get largest database name.
		$server_status_items['largest_database'] = wp_kses( filter_input( INPUT_GET, 'Largest_DB_Name', FILTER_UNSAFE_RAW ), array(), array() );

		// get list of packages to be updated - it's a comma-delimited string.
		$package_list                            = sanitize_text_field( filter_input( INPUT_GET, 'list_of_packages', FILTER_UNSAFE_RAW ) );
		$package_list                            = wp_kses( $package_list, array(), array() ); // no html allowed - just in case we get a string from someone we don't expect.
		$package_list                            = explode( ',', $package_list );
		$server_status_items['list_of_packages'] = $package_list;

		// get list of unattended packages to be updated or that requires attention - it's a comma-delimited string.
		$unattended_package_list                        = sanitize_text_field( filter_input( INPUT_GET, 'unattended_package_list', FILTER_UNSAFE_RAW ) );
		$unattended_package_list                        = wp_kses( $unattended_package_list, array(), array() ); // no html allowed - just in case we get a string from someone we don't expect.
		$unattended_package_list                        = explode( ',', $unattended_package_list );
		$server_status_items['unattended_package_list'] = $unattended_package_list;

		// get list of websites and diskspace used by each one.
		$website_diskspace                        = wp_kses( filter_input( INPUT_GET, 'website_disk', FILTER_UNSAFE_RAW ), array(), array() );
		$website_diskspace                        = explode( ',', $website_diskspace );
		$server_status_items['website_diskspace'] = $website_diskspace;

		// server time zone.
		$server_time_zone                        = sanitize_text_field( filter_input( INPUT_GET, 'Timezone', FILTER_UNSAFE_RAW ) );
		$server_time_zone                        = wp_kses( $server_time_zone, array(), array(), array() ); // no html allowed - just in case we get a string from someone we don't expect.
		$server_status_items['server_time_zone'] = $server_time_zone;

		// default php version (will store only the first two digits eg: 7.4 or 8.1).
		$server_default_php_version                 = sanitize_text_field( filter_input( INPUT_GET, 'phpversion', FILTER_UNSAFE_RAW ) );
		$server_default_php_version                 = wp_kses( $server_default_php_version, array(), array() ); // no html allowed - just in case we get a string from someone we don't expect.
		$server_status_items['default_php_version'] = $server_default_php_version;

		// default php full version (will store all three sections of the version - eg: 7.4.26).
		$server_default_php_version_full                 = sanitize_text_field( filter_input( INPUT_GET, 'phpfullversion', FILTER_UNSAFE_RAW ) );
		$server_default_php_version_full                 = wp_kses( $server_default_php_version_full, array(), array() ); // no html allowed - just in case we get a string from someone we don't expect.
		$server_status_items['default_php_version_full'] = $server_default_php_version_full;

		// Finally, add the time reported to the array.
		$server_status_items['reporting_time']       = time();
		$server_status_items['reporting_time_human'] = date( 'Y-m-d H:i:s', time() );

		// Stamp the server record with the array.
		if ( 'wpcd_app_server' === get_post_type( $id ) ) {

			// update the meta that holds the current data..
			update_post_meta( $id, 'wpcd_server_status_push', $server_status_items );

			// add to history meta as well.
			$history = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_server_status_push_history', true ) );
			if ( empty( $history ) ) {
				$history = array();
			}

			$history[ ' ' . (string) time() ] = $server_status_items; // we have to force the time element to be a string by putting a space in front of it otherwise manipulating the array as a key-value pair is a big problem if we want to purge just part of the array later.

			if ( count( $history ) >= 10 ) {
				// take the last element off to prevent history from getting too big.
				$removed = array_shift( $history );
			}

			update_post_meta( $id, 'wpcd_server_status_push_history', $history );

			// Add a special meta to indicate that the server might need to be restarted.  We'll use this to allow the server list to be filtered to show only servers needing to be restarted.
			if ( 'yes' === $server_status_items['restart'] ) {
				update_post_meta( $id, 'wpcd_server_restart_needed', 'yes' );
			} else {
				delete_post_meta( $id, 'wpcd_server_restart_needed' );
			}

			// Add a user friendly notification record for certain things...
			if ( 'yes' === $server_status_items['restart'] ) {
				do_action( 'wpcd_log_notification', $id, 'alert', __( 'This server needs to be restarted for security updates to take effect.', 'wpcd' ), 'updates', null );
			}
			if ( ! in_array( $server_status_items['default_php_version'], array( '7.4', '8.0', '8.1' ) ) ) {
				/* Translators: %s is the incorrect PHP version. */
				do_action( 'wpcd_log_notification', $id, 'alert', sprintf( __( 'The default PHP version on this server is incorrect - it should be 7.4, 8.0 or 8.1 but is currently set to %s.', 'wpcd' ), $server_status_items['default_php_version'] ), 'server-config', null );
			}
			if ( empty( $server_status_items['default_php_version'] ) ) {
				/* Translators: %s is the incorrect PHP version. */
				do_action( 'wpcd_log_notification', $id, 'notice', __( 'The default PHP version on this server is being reported as an empty string - it is likely that you need to update the callbacks on it.', 'wpcd' ), 'server-config', null );
			}

			// Let other plugins react to the new good data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_good", $server_status_items, $id );

		} else {

			do_action( 'wpcd_log_error', 'Data received for server that does not exist - received server id ' . (string) $id . '<br /> The first 5000 characters of the received data is shown below after being sanitized with WP_KSES:<br /> ' . substr( wp_kses( print_r( $_REQUEST, true ), array() ), 0, 5000 ), 'security', __FILE__, __LINE__ );

			// Let other plugins react to the new bad data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_bad", $server_status_items, $id );

		}

		// Let other plugins react to the new data (regardless of it's good or bad) with an action hook.
		do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed", $server_status_items, $id );

	}

	/**
	 * Handles server status pushes from bash script #24 - part 2 - sites status.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_command_sites_status_completed || wpcd_{$this->get_app_name()}_command_{$name}_{$status}
	 *
	 * @param int    $id server post id.
	 * @param int    $command_id an id that is given to the bash script at the time it's first run. Doesn't do anything for us in this context so it's not used here.
	 * @param string $name name.
	 * @param string $status status.
	 *
	 * @return void.
	 */
	public function push_command_sites_status_completed( $id, $command_id, $name, $status ) {

		// Set variable to status, in this case it is always "completed" - will be used in action hooks later.
		$status = 'completed';

		// set variable to name, in this case it is always "sites_status" - will be used in action hooks later.
		$name = 'sites_status';

		// Create an array to hold items taken from the $_request object.
		$sites_status_items = array();

		// Get domain name.
		$sites_status_items['domain']           = wp_kses( filter_input( INPUT_GET, 'domain', FILTER_UNSAFE_RAW ), array(), array() );
		$sites_status_items['public_ip']        = wp_kses( filter_input( INPUT_GET, 'publicip', FILTER_UNSAFE_RAW ), array(), array() );
		$sites_status_items['wp_update_needed'] = wp_kses( filter_input( INPUT_GET, 'wpupdate', FILTER_UNSAFE_RAW ), array(), array() );

		// Get numeric elements.
		$sites_status_items['domain_file_size']     = filter_input( INPUT_GET, 'domain_file_usage', FILTER_SANITIZE_NUMBER_INT );
		$sites_status_items['domain_db_size']       = filter_input( INPUT_GET, 'domain_db_size', FILTER_SANITIZE_NUMBER_INT );
		$sites_status_items['domain_backup_size']   = filter_input( INPUT_GET, 'domain_backup_size', FILTER_SANITIZE_NUMBER_INT );
		$sites_status_items['plugin_updates_count'] = filter_input( INPUT_GET, 'pluginupdate', FILTER_SANITIZE_NUMBER_INT );
		$sites_status_items['theme_updates_count']  = filter_input( INPUT_GET, 'themeupdate', FILTER_SANITIZE_NUMBER_INT );

		// Get WP Version.
		$sites_status_items['wp_version'] = wp_kses( filter_input( INPUT_GET, 'wpversion', FILTER_UNSAFE_RAW ), array(), array() );

		// WP_DEBUG flag.
		$sites_status_items['wp_debug'] = filter_input( INPUT_GET, 'wpdebug', FILTER_SANITIZE_NUMBER_INT );

		// Finally, add the time reported to the array.
		$sites_status_items['reporting_time']       = time();
		$sites_status_items['reporting_time_human'] = date( 'Y-m-d H:i:s', time() );

		// Locate the site post id(appid) based on a combination of the domain name and the server ip address.
		$app_id = $this->get_app_id_by_server_id_and_domain( $id, $sites_status_items['domain'] );

		// Stamp the site/app record with the array.
		if ( 'wpcd_app' === get_post_type( $app_id ) ) {

			// update wp version meta.
			if ( ! empty( $sites_status_items['wp_version'] ) ) {
				update_post_meta( $app_id, 'wpapp_current_version', $sites_status_items['wp_version'] );
			}

			// update wpdebug meta.
			if ( ! empty( $sites_status_items['wp_debug'] ) ) {
				update_post_meta( $app_id, 'wpapp_wp_debug', (int) $sites_status_items['wp_debug'] );
			} else {
				update_post_meta( $app_id, 'wpapp_wp_debug', 0 );
			}

			// update the meta that holds the current data..
			update_post_meta( $app_id, 'wpcd_site_status_push', $sites_status_items );

			// add to history meta as well.
			$history = wpcd_maybe_unserialize( get_post_meta( $app_id, 'wpcd_site_status_push_history', true ) );
			if ( empty( $history ) ) {
				$history = array();
			}

			$history[ ' ' . (string) time() ] = $sites_status_items; // we have to force the time element to be a string by putting a space in front of it otherwise manipulating the array as a key-value pair is a big problem if we want to purge just part of the array later.

			if ( count( $history ) >= 10 ) {
				// take the last element off to prevent history from getting too big.
				$removed = array_shift( $history );
			}

			update_post_meta( $app_id, 'wpcd_site_status_push_history', $history );

			// Set the flag to yes if site needs any of the updates.
			$update_needed = 'no';

			// Add a user friendly notification record for certain things...
			if ( $sites_status_items['plugin_updates_count'] > 0 ) {
				$update_needed = 'yes';
				/* translators: %s is replaced with the number of plugin updates pending for the site. */
				do_action( 'wpcd_log_notification', $app_id, 'alert', sprintf( __( 'This site has %s plugin updates pending.', 'wpcd' ), $sites_status_items['plugin_updates_count'] ), 'site-updates', null );
			}
			if ( $sites_status_items['theme_updates_count'] > 0 ) {
				$update_needed = 'yes';
				/* translators: %s is replaced with the number of theme updates pending for the site. */
				do_action( 'wpcd_log_notification', $app_id, 'alert', sprintf( __( 'This site has %s theme updates pending.', 'wpcd' ), $sites_status_items['theme_updates_count'] ), 'site-updates', null );
			}
			if ( 'yes' === $sites_status_items['wp_update_needed'] ) {
				$update_needed = 'yes';
				do_action( 'wpcd_log_notification', $app_id, 'alert', __( 'This site has a core WordPress update pending.', 'wpcd' ), 'site-updates', null );
			}

			// update the meta that holds the sites needs update check.
			update_post_meta( $app_id, 'wpcd_site_needs_updates', $update_needed );

			// Check site quotas.
			$this->check_site_quotas( $app_id );

			// Let other plugins react to the new good data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_good", $sites_status_items, $app_id, $id );

		} else {

			do_action( 'wpcd_log_error', 'Data received for server that does not exist - received server id ' . (string) $id . '<br /> The first 5000 characters of the received data is shown below after being sanitized with WP_KSES:<br /> ' . substr( wp_kses( print_r( $_REQUEST, true ), array() ), 0, 5000 ), 'security', __FILE__, __LINE__ );

			// Let other plugins react to the new bad data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_bad", $sites_status_items, $app_id, $id );

		}

		// Let other plugins react to the new data (regardless of it's good or bad) with an action hook.
		do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed", $sites_status_items, $app_id, $id );

	}

	/**
	 * Handles server status pushes from bash script #24 - aptget status.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_command_aptget_status_completed || wpcd_{$this->get_app_name()}_command_{$name}_{$status}
	 *
	 * @param int    $id server post id.
	 * @param int    $command_id an id that is given to the bash script at the time it's first run. Doesn't do anything for us in this context so it's not used here.
	 * @param string $name name.
	 * @param string $status status.
	 *
	 * @return void.
	 */
	public function push_command_aptget_status_completed( $id, $command_id, $name, $status ) {

		// Set variable to status, in this case it is always "completed" - will be used in action hooks later.
		$status = 'completed';

		// set variable to name, in this case it is always "aptget_status" - will be used in action hooks later.
		$name = 'aptget_status';

		// Create an array to hold items taken from the $_request object.
		$aptget_status_items = array();

		// get restart status item.
		$aptget_status_items['aptget_status'] = sanitize_text_field( filter_input( INPUT_GET, 'aptget_status', FILTER_UNSAFE_RAW ) );
		if ( ! in_array( $aptget_status_items['aptget_status'], array( 'running' ) ) ) {
			$aptget_status_items['aptget_status'] = 'unknown';
		}

		// Finally, add the time reported to the array.
		$aptget_status_items['reporting_time']       = time();
		$aptget_status_items['reporting_time_human'] = date( 'Y-m-d H:i:s', time() );

		// Stamp the server record with the array.
		if ( 'wpcd_app_server' === get_post_type( $id ) ) {

			// update the meta that holds the current data..
			update_post_meta( $id, 'wpcd_server_aptget_status_push', $aptget_status_items );

			// add to history meta as well.
			$history = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_server_aptget_status_push_history', true ) );
			if ( empty( $history ) ) {
				$history = array();
			}

			$history[ ' ' . (string) time() ] = $aptget_status_items; // we have to force the time element to be a string by putting a space in front of it otherwise manipulating the array as a key-value pair is a big problem if we want to purge just part of the array later.

			if ( count( $history ) >= 10 ) {
				// take the last element off to prevent history from getting too big.
				$removed = array_shift( $history );
			}

			update_post_meta( $id, 'wpcd_server_aptget_status_push_history', $history );

			// Now, set transient with server id that tags this server as having aptget running.
			// Transient should expire after 3 minutes.
			if ( 'running' === $aptget_status_items['aptget_status'] ) {
				$transient_name = $id . 'wpcd_server_aptget_status';
				set_transient( $transient_name, 'running', 180 );
			}

			// Let other plugins react to the new good data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_good", $aptget_status_items, $id );

		} else {

			do_action( 'wpcd_log_error', 'Data received for server that does not exist - received server id ' . (string) $id . '<br /> The first 5000 characters of the received data is shown below after being sanitized with WP_KSES:<br /> ' . substr( wp_kses( print_r( $_REQUEST, true ), array() ), 0, 5000 ), 'security', __FILE__, __LINE__ );

			// Let other plugins react to the new bad data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_bad", $aptget_status_items, $id );

		}

		// Let other plugins react to the new data (regardless of it's good or bad) with an action hook.
		do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed", $aptget_status_items, $id );

	}


	/**
	 * Checks the limits defined for the site against the newly received data.
	 *
	 * Currently only checks disk space limits since that is all that we can do right now.
	 * There's no way to measure bandwidth or cpu usage.
	 *
	 * @param int $app_id The post id of the app we're working with.
	 *
	 * @return void.
	 */
	public function check_site_quotas( $app_id ) {

		// How much diskspace is allowed?
		$disk_space_quota = $this->get_site_disk_quota( $app_id );

		// How much disk space was used?
		$used_disk = $this->get_total_disk_used( $app_id );

		// if disk limit is exceeded add notification and maybe disable and lock site.
		if ( $used_disk > $disk_space_quota && $disk_space_quota > 0 ) {
			/* translators: %s is replaced with the number of plugin updates pending for the site. */
			do_action( 'wpcd_log_notification', $app_id, 'alert', sprintf( __( 'This site has exceeded its disk quota: Allowed quota: %1$d MB, Currently used: %2$d MB.', 'wpcd' ), $disk_space_quota, $used_disk ), 'quotas', null );

			// Maybe disable the site. But only do it if the site has not already in that state.
			if ( boolval( wpcd_get_option( 'wordpress_app_sites_disk_quota_disable_site' ) ) ) {
				if ( 'on' === $this->site_status( $app_id ) ) {
					do_action( 'wpcd_wordpress-app_do_toggle_site_status', $app_id, 'site-status', 'off' );
					do_action( 'wpcd_log_notification', $app_id, 'alert', __( 'This site is being disabled because the disk quota has been exceeded.', 'wpcd' ), 'quotas', null );
				}
			}

			// Maybe apply an admin lock to the site. But only do it if the site has not already in that state.
			if ( boolval( wpcd_get_option( 'wordpress_app_sites_disk_quota_admin_lock_site' ) ) ) {
				if ( ! $this->get_admin_lock_status( $app_id ) ) {
					WPCD_WORDPRESS_APP()->set_admin_lock_status( $app_id, 'on' );
					do_action( 'wpcd_log_notification', $app_id, 'alert', __( 'This site has had it\'s admin lock applied because the disk quota has been exceeded.', 'wpcd' ), 'quotas', null );
				}
			}
		}

	}

	/**
	 * Handles the results of the callback when a malware scan completes.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_command_maldet_scan_completed || wpcd_{$this->get_app_name()}_command_{$name}_{$status}
	 *
	 * @param int    $id server post id.
	 * @param int    $command_id an id that is given to the bash script at the time it's first run. Doesn't do anything for us in this context so it's not used here.
	 * @param string $name name.
	 * @param string $status status.
	 *
	 * @return void.
	 */
	public function push_command_maldet_scan_completed( $id, $command_id, $name, $status ) {

		// Set variable to status, in this case it is always "completed" - will be used in action hooks later.
		$status = 'completed';

		// set variable to name, in this case it is always "maldet_scan" - will be used in action hooks later.
		$name = 'maldet_scan';

		// Create an array to hold items taken from the $_request object.
		$maldet_scan_items = array();

		// Get numeric items from the request object.
		$maldet_scan_items['total_files']   = filter_input( INPUT_GET, 'totalfiles', FILTER_SANITIZE_NUMBER_INT );
		$maldet_scan_items['total_hits']    = filter_input( INPUT_GET, 'totalhits', FILTER_SANITIZE_NUMBER_INT );
		$maldet_scan_items['total_cleaned'] = filter_input( INPUT_GET, 'totalcleaned', FILTER_SANITIZE_NUMBER_INT );

		// Add the time reported to the array.
		$maldet_scan_items['reporting_time']       = time();
		$maldet_scan_items['reporting_time_human'] = date( 'Y-m-d H:i:s', time() );

		// Stamp the server record with the array.
		if ( 'wpcd_app_server' === get_post_type( $id ) ) {

			// update the meta that holds the current data..
			update_post_meta( $id, 'wpcd_maldet_scan_push', $maldet_scan_items );

			// add to history meta as well.
			$history = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_maldet_scan_push_history', true ) );

			if ( count( $history ) >= 10 ) {
				// take the last element off to prevent history from getting too big.
				$removed = array_shift( $history );
			}

			if ( empty( $history ) ) {
				$history = array();
			}
			$history[ ' ' . (string) time() ] = $maldet_scan_items; // we have to force the time element to be a string by putting a space in front of it otherwise manipulating the array as a key-value pair is a big problem if we want to purge just part of the array later.
			update_post_meta( $id, 'wpcd_maldet_scan_push_history', $history );

			// Add a user friendly notification record.
			if ( (int) $maldet_scan_items['total_hits'] > 0 ) {
				do_action( 'wpcd_log_notification', $id, 'alert', __( 'Last malware scan detected one or more malicious items.', 'wpcd' ), 'malware', null );
			} else {
				do_action( 'wpcd_log_notification', $id, 'notice', __( 'Last malware scan detected no malicious items.', 'wpcd' ), 'malware', null );
			}

			// Let other plugins react to the new good data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_good", $maldet_scan_items, $id );

		} else {

			do_action( 'wpcd_log_error', 'Data received for server that does not exist - received server id ' . (string) $id . '<br /> The first 5000 characters of the received data is shown below after being sanitized with WP_KSES:<br /> ' . substr( wp_kses( print_r( $_REQUEST, true ), array() ), 0, 5000 ), 'security', __FILE__, __LINE__ );

			// Let other plugins react to the new bad data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_bad", $maldet_scan_items, $id );

		}

		// Let other plugins react to the new data (regardless of it's good or bad) with an action hook.
		do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed", $maldet_scan_items, $id );

	}

	/**
	 * Handles the results of the callback when a server starts up or shutsdown gracefully
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_command_server_restart_completed || wpcd_{$this->get_app_name()}_command_{$name}_{$status}
	 *
	 * @param int    $id server post id.
	 * @param int    $command_id an id that is given to the bash script at the time it's first run. Doesn't do anything for us in this context so it's not used here.
	 * @param string $name name.
	 * @param string $status status.
	 *
	 * @return void.
	 */
	public function push_command_server_restart_completed( $id, $command_id, $name, $status ) {

		// Set variable to status, in this case it is always "completed" - will be used in action hooks later.
		$status = 'completed';

		// set variable to name, in this case it is always "server_restart" - will be used in action hooks later.
		$name = 'server_restart';

		// Create an array to hold items taken from the $_request object.
		$server_restart_items = array();

		// Get event from request object...
		$server_restart_items['event'] = wp_kses( filter_input( INPUT_GET, 'event', FILTER_UNSAFE_RAW ), array(), array() );

		// Add the time reported to the array.
		$server_restart_items['reporting_time'] = time();

		// Stamp the server record with the array.
		if ( 'wpcd_app_server' === get_post_type( $id ) ) {

			// Get server instance details that the action history function needs.
			$instance_attributes = $this->get_server_instance_details( $id );

			WPCD_SERVER()->add_action_to_history( $server_restart_items['event'], $instance_attributes );

			// Stamp the server remote status meta based on whether we're starting up or shutting down.
			if ( 'shutting_down' === $server_restart_items['event'] ) {
				update_post_meta( $id, 'wpcd_server_current_state', 'off' );  // @TODO: 'off' should be a constant from the main PROVIDER ancestor class.

				// Add a user friendly notification record.
				do_action( 'wpcd_log_notification', $id, 'warning', __( 'The server is shutting down.', 'wpcd' ), 'power', null );
			}
			if ( 'started_up' === $server_restart_items['event'] ) {
				update_post_meta( $id, 'wpcd_server_current_state', 'active' );  // @TODO: 'active' should be a constant from the main PROVIDER ancestor class.

				// Add a user friendly notification record.
				do_action( 'wpcd_log_notification', $id, 'warning', __( 'The server started up.', 'wpcd' ), 'power', null );

				// Update some meta item that indicates if a power restart is needed.
				$server_status_items = get_post_meta( $id, 'wpcd_server_status_push', true );
				if ( ! empty( $server_status_items ) ) {
					if ( isset( $server_status_items['restart'] ) ) {
						$server_status_items['restart'] = 'no';
						update_post_meta( $id, 'wpcd_server_status_push', $server_status_items );
					}
				}
			}

			// Let other plugins react to the new good data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_good", $server_restart_items, $id );

		} else {

			do_action( 'wpcd_log_error', 'Data received for server that does not exist - received server id ' . (string) $id . '<br /> The first 5000 characters of the received data is shown below after being sanitized with WP_KSES:<br /> ' . substr( wp_kses( print_r( $_REQUEST, true ), array() ), 0, 5000 ), 'security', __FILE__, __LINE__ );

			// Let other plugins react to the new bad data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_bad", $server_restart_items, $id );

		}

		// Let other plugins react to the new data (regardless of it's good or bad) with an action hook.
		do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed", $server_restart_items, $id );

	}

	/**
	 * Handles the results of the callback when a monit triggers
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_command_monit_log_completed || wpcd_{$this->get_app_name()}_command_{$name}_{$status}
	 *
	 * @param int    $id server post id.
	 * @param int    $command_id an id that is given to the bash script at the time it's first run. Doesn't do anything for us in this context so it's not used here.
	 * @param string $name name.
	 * @param string $status status.
	 *
	 * @return void.
	 */
	public function push_command_monit_log_completed( $id, $command_id, $name, $status ) {

		// Set variable to status, in this case it is always "completed" - will be used in action hooks later.
		$status = 'completed';

		// set variable to name, in this case it is always "monit_log" - will be used in action hooks later.
		$name = 'monit_log';

		// Create an array to hold items taken from the $_request object.
		$monit_log_items = array();

		// Get string items from the request object.
		$monit_log_items['monit_status'] = wp_kses( filter_input( INPUT_GET, 'monit_status', FILTER_UNSAFE_RAW ), array(), array() );

		// Add the time reported to the array.
		$monit_log_items['reporting_time']       = time();
		$monit_log_items['reporting_time_human'] = date( 'Y-m-d H:i:s', time() );

		// Stamp the server record with the array.
		if ( 'wpcd_app_server' === get_post_type( $id ) ) {

			// update the meta that holds the current data..
			update_post_meta( $id, 'wpcd_monit_log_push', $monit_log_items );

			// add to history meta as well.
			$history = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_monit_log_push_history', true ) );

			if ( empty( $history ) ) {
				$history = array();
			}

			if ( count( $history ) >= 10 ) {
				// take the last element off to prevent history from getting too big.
				$removed = array_shift( $history );
			}

			$history[ ' ' . (string) time() ] = $monit_log_items; // we have to force the time element to be a string by putting a space in front of it otherwise manipulating the array as a key-value pair is a big problem if we want to purge just part of the array later.
			update_post_meta( $id, 'wpcd_monit_log_push_history', $history );

			// Add a user friendly notification record.
			do_action( 'wpcd_log_notification', $id, 'notice', $monit_log_items['monit_status'], 'monit', null );

			// Let other plugins react to the new good data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_good", $monit_log_items, $id );

		} else {

			do_action( 'wpcd_log_error', 'Data received for server that does not exist - received server id ' . (string) $id . '<br /> The first 5000 characters of the received data is shown below after being sanitized with WP_KSES:<br /> ' . substr( wp_kses( print_r( $_REQUEST, true ), array() ), 0, 5000 ), 'security', __FILE__, __LINE__ );

			// Let other plugins react to the new bad data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_bad", $monit_log_items, $id );

		}

		// Let other plugins react to the new data (regardless of it's good or bad) with an action hook.
		do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed", $monit_log_items, $id );

	}

	/**
	 * Handles the results of the callback when a backup for a domain has started
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_command_start_domain_backup_completed || wpcd_{$this->get_app_name()}_command_{$name}_{$status}
	 *
	 * @param int    $id app post id.
	 * @param int    $command_id an id that is given to the bash script at the time it's first run. Doesn't do anything for us in this context so it's not used here.
	 * @param string $name name.
	 * @param string $status status.
	 *
	 * @return void.
	 */
	public function push_command_domain_backup_v1_started( $id, $command_id, $name, $status ) {

		// Set variable to status, in this case it is always "completed" - will be used in action hooks later.
		$status = 'completed';

		// set variable to name, in this case it is always "start_domain_backup_v1" - will be used in action hooks later.
		$name = 'start_domain_backup_v1';

		// Create an array to hold items taken from the $_request object.
		$backup_items = array();

		// Get domain from request object...
		$backup_items['domain'] = wp_kses( filter_input( INPUT_GET, 'domain', FILTER_UNSAFE_RAW ), array(), array() );

		// Get the post id from the domain...
		if ( ! empty( $backup_items['domain'] ) ) {
			$id = $this->get_app_id_by_domain_name( $backup_items['domain'] );
		}

		// Add the time reported to the array.
		$backup_items['reporting_time'] = time();

		// Because of the way the backup callbacks work, $ID is zero and we don't know the exact ID of the app record.
		// Best we can do is setup a generic notification record.
		if ( ! empty( $backup_items['domain'] ) ) {

			// Add a user friendly notification record.
			do_action( 'wpcd_log_notification', $id, 'notice', sprintf( __( 'An attempt to backup domain %s has started.', 'wpcd' ), $backup_items['domain'] ), 'backup', null );

			// Let other plugins react to the new good data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_good", $backup_items, $id );

		} else {

			do_action( 'wpcd_log_error', 'Data received for server or app that does not exist - received server or app id ' . (string) $id . '<br /> The first 5000 characters of the received data is shown below after being sanitized with WP_KSES:<br /> ' . substr( wp_kses( print_r( $_REQUEST, true ), array() ), 0, 5000 ), 'security', __FILE__, __LINE__ );

			// Let other plugins react to the new bad data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_bad", $backup_items, $id );

		}

		// Let other plugins react to the new data (regardless of it's good or bad) with an action hook.
		do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed", $backup_items, $id );

	}

	/**
	 * Handles the results of the callback when a backup for a domain has ended
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_command_end_domain_backup_completed || wpcd_{$this->get_app_name()}_command_{$name}_{$status}
	 *
	 * @param int    $id app post id.
	 * @param int    $command_id an id that is given to the bash script at the time it's first run. Doesn't do anything for us in this context so it's not used here.
	 * @param string $name name.
	 * @param string $status status.
	 *
	 * @return void.
	 */
	public function push_command_domain_backup_v1_completed( $id, $command_id, $name, $status ) {

		// Set variable to status, in this case it is always "completed" - will be used in action hooks later.
		$status = 'completed';

		// set variable to name, in this case it is always "end_domain_backup_v1" - will be used in action hooks later.
		$name = 'end_domain_backup_v1';

		// Create an array to hold items taken from the $_request object.
		$backup_items = array();

		// Get domain from request object...
		$backup_items['domain'] = wp_kses( filter_input( INPUT_GET, 'domain', FILTER_UNSAFE_RAW ), array(), array() );

		// Get the post id from the domain...
		if ( ! empty( $backup_items['domain'] ) ) {
			$id = $this->get_app_id_by_domain_name( $backup_items['domain'] );
		}

		// Add the time reported to the array.
		$backup_items['reporting_time'] = time();

		// Because of the way the backup callbacks work, $ID is zero and we don't know the exact ID of the app record.
		// Best we can do is setup a generic notification record.
		if ( ! empty( $backup_items['domain'] ) ) {

			// Add a user friendly notification record.
			do_action( 'wpcd_log_notification', $id, 'notice', sprintf( __( 'The attempt to backup domain %s has ended.', 'wpcd' ), $backup_items['domain'] ), 'backup', null );

			// Let other plugins react to the new good data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_good", $backup_items, $id );

		} else {

			do_action( 'wpcd_log_error', 'Data received for server or app that does not exist - received server or app id ' . (string) $id . '<br /> The first 5000 characters of the received data is shown below after being sanitized with WP_KSES:<br /> ' . substr( wp_kses( print_r( $_REQUEST, true ), array() ), 0, 5000 ), 'security', __FILE__, __LINE__ );

			// Let other plugins react to the new bad data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_bad", $backup_items, $id );

		}

		// Let other plugins react to the new data (regardless of it's good or bad) with an action hook.
		do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed", $backup_items, $id );

	}

	/**
	 * Handles the results of the callback when a backup for server configuration has started or ended.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_command_server_config_backup_completed || wpcd_{$this->get_app_name()}_command_{$name}_{$status}
	 *
	 * @param int    $id app post id.
	 * @param int    $command_id an id that is given to the bash script at the time it's first run. Doesn't do anything for us in this context so it's not used here.
	 * @param string $name name.
	 * @param string $status status.
	 *
	 * @return void.
	 */
	public function push_command_server_config_backup( $id, $command_id, $name, $status ) {

		// Set variable to status, in this case it is always "completed" - will be used in action hooks later.
		$status = 'completed';

		// set variable to name, in this case it is always "start_domain_backup_v1" - will be used in action hooks later.
		$name = 'server_config_backup';

		// Create an array to hold items taken from the $_request object.
		$backup_items = array();

		// Get domain from request object...
		$backup_items['backup'] = wp_kses( filter_input( INPUT_GET, 'backup', FILTER_UNSAFE_RAW ), array(), array() );

		// Add the time reported to the array.
		$backup_items['reporting_time'] = time();

		if ( 'wpcd_app_server' === get_post_type( $id ) && in_array( $backup_items['backup'], array( 'started', 'successful', 'failed' ) ) ) {

			// Add a user friendly notification record.
			switch ( $backup_items['backup'] ) {

				case 'started':
					do_action( 'wpcd_log_notification', $id, 'notice', __( 'Configuration backup has started.', 'wpcd' ), 'backup-config', null );
					break;

				case 'successful':
					do_action( 'wpcd_log_notification', $id, 'notice', __( 'Configuration backup has completed successfully.', 'wpcd' ), 'backup-config', null );
					break;

				case 'failed':
					do_action( 'wpcd_log_notification', $id, 'error', __( 'Configuration backup has failed.', 'wpcd' ), 'backup-config', null );
					break;

			}

			// Let other plugins react to the new good data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_good", $backup_items, $id );

		} else {

			do_action( 'wpcd_log_error', 'Data received for server or app that does not exist - received server or app id ' . (string) $id . '<br /> The first 5000 characters of the received data is shown below after being sanitized with WP_KSES:<br /> ' . substr( wp_kses( print_r( $_REQUEST, true ), array() ), 0, 5000 ), 'security', __FILE__, __LINE__ );

			// Let other plugins react to the new bad data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_bad", $backup_items, $id );

		}

		// Let other plugins react to the new data (regardless of it's good or bad) with an action hook.
		do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed", $backup_items, $id );

	}

	/**
	 * Handles the results of the callback when a scheduled site sync has started or ended.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_command_schedule_site_sync_completed || wpcd_{$this->get_app_name()}_command_{$name}_{$status}
	 *
	 * @param int    $id app post id.
	 * @param int    $command_id an id that is given to the bash script at the time it's first run. Doesn't do anything for us in this context so it's not used here.
	 * @param string $name name.
	 * @param string $status status.
	 *
	 * @return void.
	 */
	public function push_command_schedule_site_sync( $id, $command_id, $name, $status ) {

		// Set variable to status, in this case it is always "completed" - will be used in action hooks later.
		$status = 'completed';

		// set variable to name, in this case it is always "schedule_site_sync" - will be used in action hooks later.
		$name = 'schedule_site_sync';

		// Create an array to hold items taken from the $_request object.
		$sync_items = array();

		// Get domain from request object...
		$sync_items['status'] = wp_kses( filter_input( INPUT_GET, 'syncstatus', FILTER_UNSAFE_RAW ), array(), array() );

		// Add the time reported to the array.
		$sync_items['reporting_time'] = time();

		if ( 'wpcd_app' === get_post_type( $id ) && in_array( $sync_items['status'], array( 'start', 'end', 'failed' ) ) ) {

			// Add a user friendly notification record.
			switch ( $sync_items['status'] ) {

				case 'start':
					do_action( 'wpcd_log_notification', $id, 'notice', __( 'A sitesync has started.', 'wpcd' ), 'site-sync', null );
					break;

				case 'end':
					do_action( 'wpcd_log_notification', $id, 'notice', __( 'A sitesync has ended.', 'wpcd' ), 'site-sync', null );
					break;

				case 'failed':
					do_action( 'wpcd_log_notification', $id, 'error', __( 'A sitesync has failed.', 'wpcd' ), 'site-sync', null );
					break;

			}

			// Let other plugins react to the new good data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_good", $sync_items, $id );

		} else {

			do_action( 'wpcd_log_error', 'Data received for server or app that does not exist - received server or app id ' . (string) $id . '<br /> The first 5000 characters of the received data is shown below after being sanitized with WP_KSES:<br /> ' . substr( wp_kses( print_r( $_REQUEST, true ), array() ), 0, 5000 ), 'security', __FILE__, __LINE__ );

			// Let other plugins react to the new bad data with an action hook.
			do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed_bad", $sync_items, $id );

		}

		// Let other plugins react to the new data (regardless of it's good or bad) with an action hook.
		do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}_processed", $sync_items, $id );

	}

	/**
	 * Handles the results of the a test rest api call.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_command_test_rest_api_completed || wpcd_{$this->get_app_name()}_command_{$name}_{$status}
	 *
	 * @param int    $id app post id.
	 * @param int    $command_id an id that is given to the bash script at the time it's first run. Doesn't do anything for us in this context so it's not used here.
	 * @param string $name name.
	 * @param string $status status.
	 *
	 * @return void.
	 */
	public function push_command_test_rest_api_completed( $id, $command_id, $name, $status ) {

		do_action( 'wpcd_log_notification', $id, 'notice', __( 'The test rest api call has succeeded.', 'wpcd' ), 'misc', null );

	}

}
