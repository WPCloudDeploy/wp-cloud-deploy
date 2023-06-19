<?php
/**
 * Trait:
 * Contains functions related to upgrading the WordPress app.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_wpapp_upgrade_functions
 */
trait wpcd_wpapp_upgrade_functions {

	/**
	 * Run upgrades
	 */
	public function upgrade() {
		// nothing yet.
		return true;
	}

	/**
	 * Check to see if all required upgrades are done.  If not, show notice(s) as required.
	 */
	public function wpapp_upgrades_admin_notice() {

		// Only show messages to admins.
		if ( ! wpcd_is_admin() ) {
			return;
		}

		if ( $this->wpapp_upgrade_must_run_check() > 0 ) {
			$class    = 'notice notice-error';
			$message  = __( '<b>WPCD: Required upgrades are needed on some or all of your servers.</b> Please click on the UPGRADE tab in each of your servers in your server list.  If you see an upgrade button or notice please click it to start the upgrade for that server.', 'wpcd' );
			$message .= '<br />';
			$message .= __( 'Please do not attempt to perform any actions on your servers until you have completed all upgrades and this message disappears.', 'wpcd' );
			$message .= '<br />';
			$message .= __( 'For more information or additional help please contact our technical support team using our contact form or by opening up a support ticket in our support portal.', 'wpcd' );
			$message .= '<br />';
			$message .= __( 'Note that it is possible that multiple upgrades need to be run sequentially.  So this messsage might still appear even though you might have completed some upgrades.', 'wpcd' );

			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}

	}

	/**
	 * Check to see if an upgrade needs to be run.
	 *
	 * This function takes a post id in cases where
	 * we're just checking if an individual server or
	 * app needs an upgrade.
	 * If no id is passed, then we're doing a global check
	 * on all servers/apps
	 *
	 * @param int $post_id Optional post id.
	 */
	public function wpapp_upgrade_must_run_check( $post_id = 0 ) {

		// Check to see if 2.9.0 upgrades were run.
		if ( $this->wpapp_upgrade_must_run_check_290( $post_id ) ) {
			return 290;
		}

		// Check to see if 4.6.0 upgrades were run.
		if ( $this->wpapp_upgrade_must_run_check_460( $post_id ) ) {
			return 460;
		}

		// Check to see if letsencrypt snap upgrades need to be run.
		if ( $this->wpapp_upgrade_must_run_check_461( $post_id ) ) {
			return 461;
		}

		// Check to see if 5.3.0 upgrades were run.
		if ( $this->wpapp_upgrade_must_run_check_530( $post_id ) ) {
			return 530;
		}

		// Check to see if 7g firewall upgrades need to be run.
		if ( $this->wpapp_upgrade_must_run_check_462( $post_id ) ) {
			return 462;
		}

		// If you got here, just return false - no update neede.
		return false;

	}

	/**
	 * Check to see if the V 290 upgrade needs to run.
	 *
	 * This function takes a post id in cases where
	 * we're just checking if an individual server or
	 * app needs an upgrade.
	 * If no id is passed, then we're doing a global check
	 * on all servers.
	 * If the global check was done and everything is kosher
	 * then a global option value will be added that can be
	 * checked at the top of this function.
	 *
	 * @param int $post_id Optional post id that points to a WPCD server post.
	 */
	public function wpapp_upgrade_must_run_check_290( $post_id = 0 ) {

		// Check an option variable to see the last update that was done.
		if ( (int) get_option( 'wpcd_last_upgrade_done' ) >= 290 ) {
			return false;  // no upgrade needed for version 2.9.0.
		}

		// if we have a post id, then we're asking for a check on just one server.
		if ( $post_id > 0 ) {

			/* Check version numbers on server - only versions lower than 460 need to be upgraded */
			$updated_plugin_version = $this->get_server_meta_by_app_id( $post_id, 'wpcd_server_plugin_updated_version', true );
			if ( version_compare( $updated_plugin_version, '2.9.0' ) >= 0 ) {
				return false;
			}

			/* If we got here, we need to check to see if the server needs to be upgraded */
			if ( get_post_meta( $post_id, 'wpcd_last_upgrade_done', true ) < 290 ) {
				return 290;
			} else {
				return false;
			}
		} else {
			// If we got here then we're doing a system-wide upgrade check.
			// So we need to start checking to see if ANY individual servers still need to be upgraded.
			$args = array(
				'post_type'      => 'wpcd_app_server',
				'post_status'    => 'private',
				'posts_per_page' => 99999,
			);
		}

		$posts = get_posts( $args );
		foreach ( $posts as $post ) {

			/* Check version numbers on server - only versions lower than 460 need to be upgraded */
			$updated_plugin_version = $this->get_server_meta_by_app_id( $post->ID, 'wpcd_server_plugin_updated_version', true );
			if ( version_compare( $updated_plugin_version, '2.9.0' ) >= 0 ) {
				continue;
			}

			/* If we got here, we need to check to see if the server needs to be upgraded */
			if ( ( get_post_meta( $post->ID, 'wpcd_last_upgrade_done', true ) < 290 ) && ( 'wordpress-app' === get_post_meta( $post->ID, 'wpcd_server_server-type', true ) ) ) {
				// At least one server still needs to be updated.
				return true;
			}
		}

		// If we get this far, all the servers were updated.
		// Or maybe it's a new install and there are no servers yet.
		// Either way set a system option so that future checks are faster.
		if ( (int) get_option( 'wpcd_last_upgrade_done' ) < 290 ) {
			update_option( 'wpcd_last_upgrade_done', 290 );
		}

		// Still here?
		return false;

	}

	/**
	 * Check to see if the V 460 upgrade needs to run.
	 *
	 * This function takes a post id in cases where
	 * we're just checking if an individual server or
	 * app needs an upgrade.
	 * If no id is passed, then we're doing a global check
	 * on all servers.
	 * If the global check was done and everything is kosher
	 * then a global option value will be added that can be
	 * checked at the top of this function.
	 *
	 * @param int $post_id Optional post id that points to a WPCD server post.
	 */
	public function wpapp_upgrade_must_run_check_460( $post_id = 0 ) {

		// Check a system-wide option variable to see the last update that was done.
		if ( (int) get_option( 'wpcd_last_upgrade_done' ) >= 460 ) {
			return false;  // no upgrade needed for version 4.6.0.
		}

		// if we have a post id, then we're asking for a check on just one server.
		if ( $post_id > 0 ) {

			/* Check version numbers on server - only versions lower than 460 need to be upgraded */
			$updated_plugin_version = $this->get_server_meta_by_app_id( $post_id, 'wpcd_server_plugin_updated_version', true );
			if ( version_compare( $updated_plugin_version, '4.6.0' ) >= 0 ) {
				return false;
			}

			/* If we got here, we need to check to see if the server needs to be upgraded */
			$last_upgrade_done = get_post_meta( $post_id, 'wpcd_last_upgrade_done', true );
			if ( $last_upgrade_done < 460 ) {
				return 460;
			} else {
				return false;
			}
		} else {
			// If we got here then we're doing a system-wide upgrade check.
			// So we need to start checking to see if ANY individual servers still need to be upgraded.
			$args = array(
				'post_type'      => 'wpcd_app_server',
				'post_status'    => 'private',
				'posts_per_page' => 99999,
			);
		}

		$posts = get_posts( $args );
		foreach ( $posts as $post ) {

			/* Check version numbers on server - only versions lower than 460 need to be upgraded */
			$updated_plugin_version = $this->get_server_meta_by_app_id( $post->ID, 'wpcd_server_plugin_updated_version', true );
			if ( version_compare( $updated_plugin_version, '4.6.0' ) >= 0 ) {
				continue;
			}

			/* If we got here, we need to check to see if the server needs to be upgraded */
			$last_upgrade_done = (int) get_post_meta( $post->ID, 'wpcd_last_upgrade_done', true );
			if ( ( $last_upgrade_done < 460 ) && ( 'wordpress-app' == get_post_meta( $post->ID, 'wpcd_server_server-type', true ) ) ) {
				// At least one server still needs to be updated.
				return true;
			}
		}

		// If we get this far, all the servers were updated.
		// Or maybe it's a new install and there are no servers yet.
		// Either way set a system option so that future checks are faster.
		if ( (int) get_option( 'wpcd_last_upgrade_done' ) < 460 ) {
			update_option( 'wpcd_last_upgrade_done', 460 );
		}

		// Still here?
		return false;

	}

	/**
	 * Check to see if we need to run the upgrade conversion of
	 * LETSENCRYPT to SNAP.
	 *
	 * This function takes a post id in cases where
	 * we're just checking if an individual server or
	 * app needs an upgrade.
	 * If no id is passed, then we're doing a global check
	 * on all servers.
	 * If the global check was done and everything is kosher
	 * then a global option value will be added that can be
	 * checked at the top of this function.
	 *
	 * @param int $post_id Optional post id that points to a WPCD server post.
	 */
	public function wpapp_upgrade_must_run_check_461( $post_id = 0 ) {

		// Check a system-wide option variable to see the last update that was done.
		if ( (int) get_option( 'wpcd_last_upgrade_done' ) >= 461 ) {
			return false;  // no upgrade needed for version 4.6.1.
		}

		// if we have a post id, then we're asking for a check on just one server.
		if ( $post_id > 0 ) {

			/* Check version numbers on server - only versions lower than 460 need to be upgraded */
			$updated_plugin_version = $this->get_server_meta_by_app_id( $post_id, 'wpcd_server_plugin_updated_version', true );
			if ( version_compare( $updated_plugin_version, '4.6.1' ) >= 0 ) {
				return false;
			}

			/* If we got here, we need to check to see if the server needs to be upgraded */
			$last_upgrade_done = get_post_meta( $post_id, 'wpcd_last_upgrade_done', true );
			if ( $last_upgrade_done < 461 ) {
				return 461;
			} else {
				return false;
			}
		} else {
			// If we got here then we're doing a system-wide upgrade check.
			// So we need to start checking to see if ANY individual servers still need to be upgraded.
			$args = array(
				'post_type'      => 'wpcd_app_server',
				'post_status'    => 'private',
				'posts_per_page' => 99999,
			);
		}

		$posts = get_posts( $args );
		foreach ( $posts as $post ) {

			/* Check version numbers on server - only versions lower than 461 need to be upgraded */
			$updated_plugin_version = $this->get_server_meta_by_app_id( $post->ID, 'wpcd_server_plugin_updated_version', true );
			if ( version_compare( $updated_plugin_version, '4.6.1' ) >= 0 ) {
				continue;
			}

			/* If we got here, we need to check to see if the server needs to be upgraded */
			$last_upgrade_done = (int) get_post_meta( $post->ID, 'wpcd_last_upgrade_done', true );
			if ( ( $last_upgrade_done < 461 ) && ( 'wordpress-app' === get_post_meta( $post->ID, 'wpcd_server_server-type', true ) ) ) {
				// At least one server still needs to be updated.
				return true;
			}
		}

		// If we get this far, all the servers were updated.
		// Or maybe it's a new install and there are no servers yet.
		// Either way set a system option so that future checks are faster.
		if ( (int) get_option( 'wpcd_last_upgrade_done' ) < 461 ) {
			update_option( 'wpcd_last_upgrade_done', 461 );
		}

		// Still here?
		return false;

	}

	/**
	 * Check to see if we need to run the upgrade to setup the
	 * 7G firewall files.
	 *
	 * This function takes a post id in cases where
	 * we're just checking if an individual server or
	 * app needs an upgrade.
	 * If no id is passed, then we're doing a global check
	 * on all servers.
	 * If the global check was done and everything is kosher
	 * then a global option value will be added that can be
	 * checked at the top of this function.
	 *
	 * @param int $post_id Optional post id that points to a WPCD server post.
	 */
	public function wpapp_upgrade_must_run_check_462( $post_id = 0 ) {

		// Check a system-wide option variable to see the last update that was done.
		if ( (int) get_option( 'wpcd_last_upgrade_done' ) >= 462 ) {
			return false;  // no upgrade needed for version 4.6.2.
		}

		// if we have a post id, then we're asking for a check on just one server.
		if ( $post_id > 0 ) {

			/* Check version numbers on server - only versions lower than 460 need to be upgraded */
			$updated_plugin_version = $this->get_server_meta_by_app_id( $post_id, 'wpcd_server_plugin_updated_version', true );
			if ( version_compare( $updated_plugin_version, '4.6.2' ) >= 0 ) {
				return false;
			}

			/* If we got here, we need to check to see if the server needs to be upgraded */
			$last_upgrade_done = get_post_meta( $post_id, 'wpcd_last_upgrade_done', true );
			if ( $last_upgrade_done < 462 ) {
				return 462;
			} else {
				return false;
			}
		} else {
			// If we got here then we're doing a system-wide upgrade check.
			// So we need to start checking to see if ANY individual servers still need to be upgraded.
			$args = array(
				'post_type'      => 'wpcd_app_server',
				'post_status'    => 'private',
				'posts_per_page' => 99999,
			);
		}

		$posts = get_posts( $args );
		foreach ( $posts as $post ) {

			/* Check version numbers on server - only versions lower than 461 need to be upgraded */
			$updated_plugin_version = $this->get_server_meta_by_app_id( $post->ID, 'wpcd_server_plugin_updated_version', true );
			if ( version_compare( $updated_plugin_version, '4.6.2' ) >= 0 ) {
				continue;
			}

			/* If we got here, we need to check to see if the server needs to be upgraded */
			$last_upgrade_done = (int) get_post_meta( $post->ID, 'wpcd_last_upgrade_done', true );
			if ( ( $last_upgrade_done < 462 ) && ( 'wordpress-app' === get_post_meta( $post->ID, 'wpcd_server_server-type', true ) ) ) {
				// At least one server still needs to be updated.
				return true;
			}
		}

		// If we get this far, all the servers were updated.
		// Or maybe it's a new install and there are no servers yet.
		// Either way set a system option so that future checks are faster.
		if ( (int) get_option( 'wpcd_last_upgrade_done' ) < 462 ) {
			update_option( 'wpcd_last_upgrade_done', 462 );
		}

		// Still here?
		return false;

	}

	/**
	 * Check to see if we need to run the upgrade to fix
	 * the issue with OLS restarting after every three mins.
	 *
	 * This function takes a post id in cases where
	 * we're just checking if an individual server or
	 * app needs an upgrade.
	 * If no id is passed, then we're doing a global check
	 * on all servers.
	 * If the global check was done and everything is kosher
	 * then a global option value will be added that can be
	 * checked at the top of this function.
	 *
	 * @param int $post_id Optional post id that points to a WPCD server post.
	 */
	public function wpapp_upgrade_must_run_check_530( $post_id = 0 ) {

		// Check a system-wide option variable to see the last update that was done.
		if ( (int) get_option( 'wpcd_last_upgrade_done' ) >= 526 ) {
			return false;  // no upgrade needed for version 5.3.0.
		}

		// if we have a post id, then we're asking for a check on just one server.
		if ( $post_id > 0 ) {

			$webserver_type = $this->get_web_server_type( $post_id );

			/* Only webservers of type OLS needs an upgrade for this version. */
			if ( ! in_array( $webserver_type, array( 'ols', 'ols-enterprise' ), true ) ) {
				return false;
			}

			/* Check version numbers on server - only versions lower than 5.2.3 need to be upgraded.  NOTE: V 5.2.6 is the beta version of 5.3.0*/
			$updated_plugin_version = $this->get_server_meta_by_app_id( $post_id, 'wpcd_server_plugin_updated_version', true );
			if ( version_compare( $updated_plugin_version, '5.2.6' ) >= 0 ) {
				return false;
			}

			/* If we got here, we need to check to see if the server needs to be upgraded */
			$last_upgrade_done = (int) get_post_meta( $post_id, 'wpcd_last_upgrade_done', true );
			if ( $last_upgrade_done < 526 ) {
				return 526;
			} else {
				return false;
			}
		} else {
			// If we got here then we're doing a system-wide upgrade check.
			// So we need to start checking to see if ANY individual servers still need to be upgraded.
			$args = array(
				'post_type'      => 'wpcd_app_server',
				'post_status'    => 'private',
				'posts_per_page' => 99999,
			);
		}

		$posts = get_posts( $args );
		foreach ( $posts as $post ) {

			$webserver_type = $this->get_web_server_type( $post->ID );

			/* Only webservers of type OLS needs an upgrade for this version. */
			if ( ! in_array( $webserver_type, array( 'ols', 'ols-enterprise' ), true ) ) {
				continue;
			}

			/* Check version numbers on server - only versions lower than 5.2.3 need to be upgraded.  NOTE: V 5.2.6 is the beta version of 5.3.0*/
			$updated_plugin_version = $this->get_server_meta_by_app_id( $post->ID, 'wpcd_server_plugin_updated_version', true );
			if ( version_compare( $updated_plugin_version, '5.2.6' ) >= 0 ) {
				continue;
			}

			/* If we got here, we need to check to see if the server needs to be upgraded */
			$last_upgrade_done = get_post_meta( $post->ID, 'wpcd_last_upgrade_done', true );
			if ( ( $last_upgrade_done < 526 ) && ( 'wordpress-app' === get_post_meta( $post->ID, 'wpcd_server_server-type', true ) ) ) {
				// At least one server still needs to be updated.
				return true;
			}
		}

		// If we get this far, all the servers were updated.
		// Or maybe it's a new install and there are no servers yet.
		// Either way set a system option so that future checks are faster.
		if ( (int) get_option( 'wpcd_last_upgrade_done' ) < 526 ) {
			update_option( 'wpcd_last_upgrade_done', 526 );
		}

		// Still here?
		return false;

	}

	/**
	 * Show the upgrade status in the LOCAL STATUS column in the server list.
	 *
	 * Filter Hook: wpcd_app_server_admin_list_local_status_column
	 *
	 * @param string $column_data Data to show in the column.
	 * @param int    $post_id Id of server post being displayed.
	 *
	 * @return string $column_data
	 */
	public function app_server_admin_list_upgrade_status( $column_data, $post_id ) {

		$webserver_type = $this->get_web_server_type( $post_id );

		if ( 'wordpress-app' === WPCD_WORDPRESS_APP()->get_server_type( $post_id ) ) {
			if ( $this->wpapp_upgrade_must_run_check( $post_id ) ) {
				$output      = '<span class="wpcd_upgrade_needed_warning">' . __( 'A server upgrade is needed. Please see the upgrades tab.', 'wpcd' ) . '</span>';
				$column_data = $column_data . $output;
			}
			if ( $this->is_cache_enabler_nginx_upgrade_needed( $post_id ) && 'nginx' === $webserver_type ) {
				$output      = '<span class="wpcd_upgrade_needed_warning">' . __( 'A server upgrade is needed to optimize the NGINX cache. Please see the UPGRADE CACHE section on the upgrades tab.', 'wpcd' ) . '</span>';
				$column_data = $column_data . $output;
			}
		}

		return $column_data;

	}

	/**
	 * Show the upgrade status in the APP SUMMARY column in the app list.
	 *
	 * Filter Hook: wpcd_app_admin_list_summary_column
	 *
	 * @param string $column_data Data to show in the column.
	 * @param int    $post_id Id of app post being displayed.
	 *
	 * @return string $column_data column data.
	 */
	public function app_admin_list_upgrade_status( $column_data, $post_id ) {

		if ( 'wordpress-app' === WPCD_WORDPRESS_APP()->get_app_type( $post_id ) ) {
			// Which server is this app on?
			$server_id = $this->get_server_id_by_app_id( $post_id );
			if ( $this->wpapp_upgrade_must_run_check( $server_id ) ) {
				$output      = '<span class="wpcd_upgrade_needed_warning">' . __( 'The server on which this app is installed needs to be upgraded. Please upgrade it before performing actions on this app.', 'wpcd' ) . '</span>';
				$column_data = $column_data . $output;
			}
		}

		return $column_data;

	}

	/**
	 * Run any automatic upgrades that need to be run.
	 *
	 * Action Hook: admin_init
	 */
	public function wpapp_admin_init_app_silent_auto_upgrade() {
		// 5.1 auto upgrades.
		$this->wpcd_510_silent_auto_upgrade();

		// Call future silent auto upgrade functions here.
	}

	/**
	 * Run upgrades for WPCD 5.1.
	 *
	 * For this upgrade, we'll be doing the following:
	 *   1. Make sure that all app records have a php version on them.
	 */
	public function wpcd_510_silent_auto_upgrade() {

		// Check a system-wide option variable to see the last update that was done.
		if ( (int) get_option( 'wpcd_last_silent_auto_upgrade_done' ) >= 510 ) {
			return false;  // no upgrade needed for version 5.1.0.
		}

		/* Get list of app records. */
		$args = array(
			'post_type'      => 'wpcd_app',
			'post_status'    => 'private',
			'posts_per_page' => 99999,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'app_type',
					'value'   => 'wordpress-app',
					'compare' => '=',
				),
			),
		);

		// Grab app records.
		$posts = get_posts( $args );

		// Loop through and stamp.
		foreach ( $posts as $key => $id ) {

			$php_version = wpcd_maybe_unserialize( get_post_meta( $id, 'wpapp_php_version', true ) );
			if ( empty( $php_version ) ) {
				$this->set_php_version_for_app( $id, $this->get_php_version_for_app( $id ) );
			}
		}

		if ( (int) get_option( 'wpcd_last_upgrade_done' ) < 510 ) {
			update_option( 'wpcd_last_silent_auto_upgrade_done', 510 );
		}

	}

	/**
	 * Returns a boolean true/false if we need to install a new version of the cache_enabler script.
	 *
	 * @param int $server_id ID of server being interrogated.
	 *
	 * @return boolean
	 */
	public function is_cache_enabler_nginx_upgrade_needed( $server_id ) {

		$initial_plugin_version = $this->get_server_meta_by_app_id( $server_id, 'wpcd_server_plugin_initial_version', true );  // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.

		if ( version_compare( $initial_plugin_version, '5.2.9' ) > -1 ) {
			// Versions of the plugin after 5.2.9 already has the latest version of cache_enabler.
			return false;
		} else {
			// See if it was manually upgraded - which would leave a meta field value behind on the server CPT record.
			$it_is_installed = (float) $this->get_server_meta_by_app_id( $server_id, 'wpcd_cache_enabler_nginx_upgrade', true );   // This function is smart enough to know if the ID being passed is a server or app id and adjust accordingly.
			if ( $it_is_installed >= 5.11 ) {  // Increase this number to 5.12, 5.13 etc if we need to upgrade again in the future - this is not a version number. It just needs to increase.
				return false;
			} else {
				return true;
			}
		}

		return false;

	}

	/**
	 * Add data to an option that keeps track of wpcd app updates.
	 *
	 * @param int    $id Post id of server being upgraded.
	 * @param string $history_key_type A key to be used to indicate the history being added.
	 * @param string $desc Description of history element being added.
	 */
	public function update_history( $id, $history_key_type, $desc ) {

		// Get history array.
		$history = $this->get_update_history( $id );

		// Add to array.
		$history[] = array(
			'type' => $history_key_type,
			'time' => time(),
			'desc' => $desc,
		);

		// Write it back to post meta.
		update_post_meta( $id, 'wpcd_server_update_history', $history );

	}

	/**
	 * Returns an array with update history items.
	 *
	 * @param int $id Post id of server being handled..
	 */
	public function get_update_history( $id ) {

		$history = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_server_update_history', true ) );

		// Make sure we have a value and that it's an array.
		if ( empty( $history ) || ! is_array( $history ) ) {
			$history = array();
		}

		return $history;

	}

	/**
	 * Returns an HTML formatted string with update history.
	 *
	 * @param int $id Post id of server being handled..
	 */
	public function get_formatted_update_history( $id ) {

		$history = $this->get_update_history( $id );

		// Return right away if we have no history.
		if ( empty( $history ) ) {
			return '';
		}

		// Initialize return value.
		$return = '';

		foreach ( $history as $key => $value ) {

			$return .= '<div class="wpcd_update_history_item">';
			$return .= '<div class="wpcd_update_history_item_datetime">';
			$return .= gmdate( 'Y/m/d g:i A', $value['time'] );
			$return .= '</div>';
			$return .= '<div class="wpcd_update_history_desc">';
			$return .= $value['desc'];
			$return .= '</div>';
			$return .= '</div>';

		}

		$return = '<div class="wpcd_update_history_wrap">' . $return . '</div>';

		return $return;

	}

}
