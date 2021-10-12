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

		// Check to see if 7g firewall upgrades need to be run.
		if ( $this->wpapp_upgrade_must_run_check_462( $post_id ) ) {
			return 462;
		}

		// Check to see if 3.0.0 upgrades were run.
		// This is stub code so you can see the pattern
		// when you're look at this later. ha!

		/*
		if ( $this->wpapp_upgrade_must_run_check_300( $post_id ) )  {
			return 300 ;
		}
		*/

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
			$last_upgrade_done = get_post_meta( $post->ID, 'wpcd_last_upgrade_done', true );
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
			$last_upgrade_done = get_post_meta( $post->ID, 'wpcd_last_upgrade_done', true );
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
			$last_upgrade_done = get_post_meta( $post->ID, 'wpcd_last_upgrade_done', true );
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

		if ( 'wordpress-app' === WPCD_WORDPRESS_APP()->get_server_type( $post_id ) ) {
			if ( $this->wpapp_upgrade_must_run_check( $post_id ) ) {
				$output      = '<span class="wpcd_upgrade_needed_warning">' . __( 'A server upgrade is needed. Please see the upgrades tab.', 'wpcd' ) . '</span>';
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

}
