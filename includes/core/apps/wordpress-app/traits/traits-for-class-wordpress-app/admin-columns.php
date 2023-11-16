<?php
/**
 * Trait:
 * Contains functions related to displaying data on the admin
 * columns for both servers and apps.
 * Used only by the class-wordpress.php file which defines the
 * WPCD_WORDPRESS_APP class.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_wpapp_admin_column_data
 */
trait wpcd_wpapp_admin_column_data {

	/**
	 * Add actions to the server posts screen.
	 *
	 * @param string $actions actions.
	 * @param int    $id id.
	 */
	public function add_post_actions( $actions, $id ) {

		/* If empty id, something is wrong so return. */
		if ( empty( $id ) ) {
			return $actions;
		}

		/* Make sure that we are on a wpcd_app_server post. */
		if ( get_post_type( $id ) <> 'wpcd_app_server' ) {
			return $actions;
		}

		/* Make sure that the server type is a WordPress server! */
		if ( get_post_meta( $id, 'wpcd_server_server-type', true ) <> $this->get_app_name() ) {
			return $actions;
		}

		/* Make sure the status is private - i.e: not deleted or something else. */
		if ( 'private' !== get_post_status( $id ) ) {
			return $actions;
		}

		$new_actions = array();

		// Add link for delete server records.
		$wpcd_server_delete_protection       = get_post_meta( $id, 'wpcd_server_delete_protection', true );
		$wpcd_show_delete_server_record_link = wpcd_get_option( 'wordpress_app_enable_server_delete_record' );
		if ( 'private' === get_post_status( $id ) && wpcd_is_admin() && empty( $wpcd_server_delete_protection ) && $wpcd_show_delete_server_record_link ) {
			/**
			 * We will only get in here if all of the following four conditions are met:
			 *  1: server type is private and
			 *  2. current user is admin and
			 *  3. server does not have delete protection enabled.
			 *  4. the delete server record link option is enabled in settings.
			 */
			$new_actions['wpcd_delete_server_record'] = sprintf(
				'<a class="wpcd_action_delete_server_record" data-wpcd-id="%d" href="">%s</a>',
				$id,
				esc_html( __( 'Delete Record', 'wpcd' ) )
			);
		}
		// Show old-logs drop-down in the server list.
		if ( wpcd_get_option( 'wordpress_app_show_logs_dropdown_in_server_list' ) ) {
			// add the show logs button if a command is currently executing.
			$name = WPCD_APP::show_log_button_for( $id );
			if ( false !== $name ) {
				$new_actions['wpcd_show_logs'] = sprintf(
					'<a class="wpcd_action_show_logs" data-wpcd-id="%d" data-wpcd-name="%s" href="">%s</a>',
					$id,
					$name,
					esc_html( __( 'Show Current Log', 'wpcd' ) )
				);
			}

			// the previous logs, shown as a dropdown to save space.
			$buttons = WPCD_APP::show_log_button_for( $id, false );
			if ( $buttons ) {
				$old_logs   = array();
				$old_logs[] = sprintf( '<option value="">%s</option>', __( 'Show Old Logs', 'wpcd' ) );
				foreach ( $buttons as $slug ) {
					$attr       = explode( ':', $slug );
					$time       = date( get_option( 'date_format' ), intval( $attr[1] ) );
					$old_logs[] = sprintf( '<option value="%s" title="%s" data-wpcd-id="%d" data-wpcd-name="%s">%s</option>', $slug, sprintf( __( 'Executed on %s', 'wpcd' ), $time ), $id, $slug, $attr[0] );
				}
				// add an empty anchor tag which will effectively trigger the pop-up.
				$new_actions['wpcd_show_old_logs'] = sprintf( '<a class="wpcd_action_show_old_logs"></a> <select title="%s" class="wpcd_action_show_old_logs">%s</select>', __( 'Show Logs', 'wpcd' ), implode( '', $old_logs ) );
			}
		}

		// Add the INSTALL WordPress link.
		if ( wpcd_get_option( 'wordpress_app_show_install_wp_link_in_server_list' ) ) {

			/* @TODO: This defined constant in wp-config can probably be removed since we now offer the option in the settings screen */
			if ( ( ! defined( 'WPCD_WPAPP_HIDE_INSTALLWP_HOVER_LINK' ) ) || ( defined( 'WPCD_WPAPP_HIDE_INSTALLWP_HOVER_LINK' ) && ! WPCD_WPAPP_HIDE_INSTALLWP_HOVER_LINK ) ) {

				if ( apply_filters( 'wpcd_wpapp_show_install_wp_link', true, $id ) ) {

					// Check to make sure we're not trying to show this on a deleted post...
					if ( 'private' === get_post_status( $id ) ) {
						// Get the state of the server.
						// @TODO: This section of code should probably be centralized into some sort of function split between class-wpcd-posts-app-server.php and class-wordpress-app
						// linked with FILTERS since we're checking multiple metas scattered across server and app records.
						// Code duplicated around line #192 in function app_server_table_content().
						$state = get_post_meta( $id, 'wpcd_server_current_state', true );
						if ( 'active' == $state || empty( $state ) ) {
							// Now check to make sure that other stuff isn't in progress on the server...
							if ( ( ! empty( get_post_meta( $id, 'wpcd_server_wordpress-app_action', true ) ) ) || ( ! empty( get_post_meta( $id, 'wpcd_server_wordpress-app_action_status', true ) ) ) ) {
								$state = 'in-progress';
							}
						}

						// Only show the link if the server is active.
						if ( 'active' == $state || empty( $state ) ) {
							// check user permissions.
							$user_id     = get_current_user_id();
							$post_author = get_post( $id )->post_author;
							if ( ! wpcd_user_can( $user_id, 'add_app_wpapp', $id ) && $post_author != $user_id ) {
								// No permission, do nothing.
							} else {
								$new_actions['wpcd_install_app'] = sprintf(
									'<a class="wpcd_action_install_app" data-wpcd-id="%d" href="">%s</a>',
									$id,
									esc_html( __( 'Install WordPress', 'wpcd' ) )
								);
							}
						}
					}
				}
			}
		}

		return array_merge( $actions, $new_actions );
	}

	/**
	 * Add content to the app summary column that shows up in app admin list
	 *
	 * Filter Hook: wpcd_app_admin_list_summary_column
	 *
	 * @param string $column_data Data to show in the column.
	 * @param int    $post_id Id of app post being displayed.
	 *
	 * @return string $column_data.
	 */
	public function app_admin_list_summary_column( $column_data, $post_id ) {
		/* Bail out if the app being evaluated isn't a wp app. */
		if ( $this->get_app_name() <> get_post_meta( $post_id, 'app_type', true ) ) {
			return $column_data;
		}

		/* Put a line break to separate out data section from others if the column already contains data */
		if ( ! empty( $colummn_data ) ) {
			$column_data = $column_data . '<br />';
		}

		// Get the count of notes and admin notes.
		$labels_count_arr = $this->get_notes_count_string( $post_id );
		$labels_count_arr = implode( ' ', $labels_count_arr );

		/* Calculate our data element */
		$new_column_data = '';
		if ( true === (bool) wpcd_get_option( 'wordpress_app_hide_domain_site_summary_column_in_site_list' ) && ( ! wpcd_is_admin() ) ) {
			// Do nothing.
		} else {
			$value  = __( 'Domain: ', 'wpcd' );
			$data   = get_post_meta( $post_id, 'wpapp_domain', true );
			$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $value, 'domain', 'left' );
			$value .= WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( wpcd_wrap_clipboard_copy( $data ), 'domain', 'right' );
			$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $value, 'domain' );

			$new_column_data = $new_column_data . $value;
		}
		if ( true === (bool) wpcd_get_option( 'wordpress_app_hide_login_user_site_summary_column_in_site_list' ) && ( ! wpcd_is_admin() ) ) {
			// Do nothing.
		} else {
			$value  = __( 'User: ', 'wpcd' );
			$data   = get_post_meta( $post_id, 'wpapp_user', true );
			$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $value, 'wp_login_user', 'left' );
			$value .= WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( wpcd_wrap_clipboard_copy( $data, false ), 'wp_login_user', 'right' );
			$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $value, 'wp_login_user' );

			$new_column_data = $new_column_data . $value;
		}

		/* Add wp version */
		if ( true === (bool) wpcd_get_option( 'wordpress_app_hide_initial_wp_version_site_summary_column_in_site_list' ) && ( ! wpcd_is_admin() ) ) {
			// Do nothing.
		} else {
			$wp_version = get_post_meta( $post_id, 'wpapp_current_version', true );  // If the the latest version meta was updated by callbacks, use that.
			if ( empty( $wp_version ) ) {
				$wp_version = get_post_meta( $post_id, 'wpapp_version', true );
				if ( 'latest' === $wp_version ) {
					$wp_version = __( 'Latest', 'wpcd' );
				}
				$value        = __( 'Initial WP Version: ', 'wpcd' );
				$column_class = 'initial_wp_version';
			} else {
				$value        = __( 'WP Version: ', 'wpcd' );
				$column_class = 'wp_version';
			}

			$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $value, $column_class, 'left' );
			$value .= WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $wp_version, $column_class, 'right' );
			$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $value, $column_class );

			$new_column_data = $new_column_data . $value;
		}

		/* Add disk quota information */
		if ( true === (bool) wpcd_get_option( 'wordpress_app_hide_disk_quota_in_summary_column_in_site_list' ) && ( ! wpcd_is_admin() ) ) {
			// Do nothing.
		} else {
			$allowed_disk       = $this->get_site_disk_quota( $post_id );
			$current_disk_usage = $this->get_total_disk_used( $post_id );

			/* Translators: %d is the amount of disk space quota allowed for the site. */
			$allowed_disk_display = sprintf( __( '%d MB', 'wpcd' ), $allowed_disk );

			/* Translators: %d is the amount of disk space in use for the site. */
			$current_disk_usage_display = sprintf( __( '%d MB', 'wpcd' ), $current_disk_usage );

			if ( $allowed_disk > 0 ) {
				$value  = __( 'Disk Quota: ', 'wpcd' );
				$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $value, 'disk_quota', 'left' );
				$value .= WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $allowed_disk_display, 'disk_quota', 'right' );
				$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $value, 'disk_quota' );

				$new_column_data = $new_column_data . $value;

				$value  = __( 'Disk Quota Used: ', 'wpcd' );
				$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $value, 'disk_quota_used', 'left' );
				$value .= WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $current_disk_usage_display, 'disk_quota_used', 'right' );
				$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $value, 'disk_quota_used' );

				$new_column_data = $new_column_data . $value;

			}
		}

		// Display multi-tenant parent but only in the wp-admin area - not on the front-end.
		if ( true === wpcd_is_mt_enabled() && true === is_admin() ) {
			$mt_version = $this->get_mt_version( $post_id );
			$mt_parent  = $this->get_mt_parent( $post_id );
			if ( ! empty( $mt_parent ) && ! empty( $mt_version ) ) {
				$mt_parent_domain = $this->get_domain_name( $mt_parent );
				if ( ! empty( $mt_parent_domain ) ) {
					$value  = __( 'MT Parent: ', 'wpcd' );
					$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $value, 'mt_parent_domain', 'left' );
					$value .= WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $mt_parent_domain, 'mt_parent_domain', 'right' );
					$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $value, 'mt_parent_domain' );

					$new_column_data = $new_column_data . $value;
				}
			}
		}

		// Display the count of notes and admin notes.
		if ( ! empty( $labels_count_arr ) ) {
			$new_column_data = $new_column_data . $labels_count_arr . '<br />';
		}

		// wp-admin login and front-end links - but only if site isn't disabled.
		if ( 'off' !== $this->site_status( $post_id ) ) {
			// Display the wp-admin login link.
			$value = $this->get_formatted_wpadmin_link( $post_id );
			$value = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $value, 'wp_admin_link', 'left' );

			// Display a link to the site home page.
			$value2 = $this->get_formatted_site_link( $post_id );
			$value2 = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $value2, 'homepage_link', 'right' );

			if ( is_admin() ) {
				// Stack site links when displayed in the wp-admin area.
				$value           = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $value, 'wp_admin_link' );
				$new_column_data = $new_column_data . $value;
				$value2          = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $value2, 'homepage_link' );
				$new_column_data = $new_column_data . $value2;
			} else {
				// Set links side-by-side shown on the front-end.
				$value           = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $value . $value2, 'site_links' );
				$new_column_data = $new_column_data . $value;
			}
		}

		// Display a passwordless login link.
		if ( true === (bool) wpcd_get_option( 'wordpress_app_hide_passwordless_login_summary_column_in_site_list' ) && ( ! wpcd_is_admin() ) ) {
			// Do nothing.
		} else {
			// Do not show if site is disabled and only if the logged user is an admin.
			if ( wpcd_is_admin() && ( 'off' !== $this->site_status( $post_id ) ) && ( ! wpcd_get_option( 'wordpress_app_disable_passwordless_login' ) ) ) {
				$value           = $this->get_passwordless_login_link_for_display( $post_id, __( 'One-click Login', 'wpcd' ) );
				$value           = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $value, 'wp_passwordless_login_link' );
				$new_column_data = $new_column_data . $value;
			}
		}

		// Display any custom links - only displayed in the wp-admin area.
		if ( is_admin() ) {
			$new_column_data = $new_column_data . $this->get_formatted_custom_links( $post_id );
		}

		// If the site is being synced to another server, include data about that here.  It only displayedin the wp-admin area.
		if ( is_admin() ) {
			$destination_server_id = (int) get_post_meta( $post_id, 'wpcd_wpapp_site_sync_schedule_destination_id', true );
			if ( $destination_server_id > 0 ) {
				// Get the name of the server in a formatted link (with a link if the user is able to edit it otherwise without the link).
				$destination_server_title = wp_kses_post( get_post( $destination_server_id )->post_title );
				$user_id                  = get_current_user_id();
				if ( wpcd_user_can( $user_id, 'view_server', $destination_server_id ) || get_post( $destination_server_id )->post_author === $user_id ) {
					$destination_server_text = sprintf( '<a href="%s">' . $destination_server_title . '</a>', get_edit_post_link( $destination_server_id ) );
				} else {
					$destination_server_text = $destination_server_title;
				}
				$new_column_data = $new_column_data . '<span class="wpcd_destination_site_sync_server wpcd_destination_server">' . __( 'Syncing To:', 'wpcd' ) . '<br .>' . $destination_server_text . '</span>';
			}
		}

		/* Add in some health data about the site. Only show it though if the separate app health column isn't being shown. */
		if ( ! boolval( wpcd_get_option( 'wpcd_show_app_list_health' ) ) ) {

			// Add a horizontal divider if we're on the front-end.
			if ( ! is_admin() ) {
				$new_column_data .= '<hr />';
			}

			$new_column_data .= $this->get_app_health_data( $post_id );

		}

		/* Add data if it's not already in the column */
		if ( strpos( $column_data, $new_column_data ) !== false ) {
			// do nothing.
		} else {
			// add the new data to the column.
			$column_data = $column_data . $new_column_data;
		}

		return $column_data;
	}

	/**
	 * Add content to the app health column that shows up in app admin list
	 *
	 * Filter Hook: wpcd_app_admin_list_app_health_column
	 *
	 * @param string $column_data Data to show in the column.
	 * @param int    $post_id Id of app post being displayed.
	 *
	 * @return string $column_data.
	 */
	public function app_admin_list_health_column( $column_data, $post_id ) {

		/* Bail out if the app being evaluated isn't a wp app. */
		if ( $this->get_app_name() <> get_post_meta( $post_id, 'app_type', true ) ) {
			return $column_data;
		}

		/* Calculate our data element */
		$new_column_data  = '';
		$new_column_data .= $this->get_app_health_data( $post_id );

		/* If no data, add a notification to that effect. */
		if ( empty( $new_column_data ) ) {
			$new_column_data = __( 'There are no health alerts for this app.', 'wpcd' );
		}

		/* Add data if it's not already in the column */
		if ( strpos( $column_data, $new_column_data ) !== false ) {
			// do nothing.
		} else {
			// add the new data to the column.
			$column_data = $column_data . $new_column_data;
		}

		return $column_data;
	}

	/**
	 * Get the app health data formatted as a string.
	 *
	 * @param int $post_id Id of app post being displayed.
	 *
	 * @return string.
	 */
	public function get_app_health_data( $post_id ) {
		$new_column_data = '';

		$wp_update = $this->get_site_status_callback_string( $post_id, 'wp_update_needed' );
		if ( ! empty( $wp_update ) ) {
			$new_column_data .= $wp_update;
		}
		$plugin_updates_count = $this->get_site_status_callback_string( $post_id, 'plugin_updates_count' );
		if ( ! empty( $plugin_updates_count ) ) {
			$new_column_data .= $plugin_updates_count;
		}
		$theme_updates_count = $this->get_site_status_callback_string( $post_id, 'theme_updates_count' );
		if ( ! empty( $theme_updates_count ) ) {
			$new_column_data .= $theme_updates_count;
		}
		if ( ( ! empty( $wp_update ) ) || ( ! empty( $plugin_updates_count ) ) || ( ! empty( $theme_updates_count ) ) ) {
			$data_date = $this->get_site_status_callback_string( $post_id, 'data_date' );
			if ( ! empty( $data_date ) ) {
				if ( is_admin() ) {
					// Show last updated date in short format.
					$new_column_data .= $data_date;
				} else {
					// Show last updated date in long format for front-end.
					$value            = __( 'Last Updated: ', 'wpcd' );
					$value            = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $value, 'health_last_update', 'left' );
					$value           .= WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $data_date, 'health_last_update', 'right' );
					$value            = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $value, 'health_last_update' );
					$new_column_data .= $value;
				}
			}
		}

		// Debug flag.
		$debug_flag = __( 'Disabled', 'wpcd' );
		if ( true === $this->get_site_local_wpdebug_flag( $post_id ) ) {
			$debug_flag = __( 'Enabled', 'wpcd' );
		}
		$value            = __( 'WP_DEBUG: ', 'wpcd' );
		$value            = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $value, 'debugflag', 'left' );
		$value           .= WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $debug_flag, 'debugflag', 'right' );
		$value            = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $value, 'debugflag' );
		$new_column_data .= $value;

		return $new_column_data;
	}

	/**
	 * Get an array of strings that contains the count of the number of notes on server or app post.
	 *
	 * @param int $post_id Id of app post being displayed.
	 *
	 * @return array $labels_count_arr.
	 */
	public function get_notes_count_string( $post_id ) {

		$wpcd_note_count            = '';
		$wpcd_admin_only_note_count = '';
		$labels_count_arr           = array();

		$wpcd_note = get_post_meta( $post_id, 'wpcd_note', true );
		if ( ! empty( $wpcd_note ) && count( $wpcd_note ) > 0 ) {
			$wpcd_note_count = count( $wpcd_note );
			if ( $wpcd_note_count > 1 ) {
				/* Translators: %d is the count of notes for this app. */
				$wpcd_note_count = sprintf( __( '%d Notes', 'wpcd' ), $wpcd_note_count );
			} else {
				/* Translators: %d is the count of notes for this app. */
				$wpcd_note_count = sprintf( __( '%d Note', 'wpcd' ), $wpcd_note_count );
			}
			$labels_count_arr[] = '<span class="wpcd-note-count">' . (string) $wpcd_note_count . '</span>';
		}

		// Check if admin user.
		if ( wpcd_is_admin() ) {
			$wpcd_admin_only_note = get_post_meta( $post_id, 'wpcd_admin_only_note', true );
			if ( ! empty( $wpcd_admin_only_note ) && count( $wpcd_admin_only_note ) > 0 ) {
				$wpcd_admin_only_note_count = count( $wpcd_admin_only_note );
				if ( $wpcd_admin_only_note_count > 1 ) {
					/* Translators: %d is the count of Admin Only notes for this app. */
					$wpcd_admin_only_note_count = sprintf( __( '%d Admin Notes', 'wpcd' ), $wpcd_admin_only_note_count );
				} else {
					/* Translators: %d is the count of Admin Only notes for this app. */
					$wpcd_admin_only_note_count = sprintf( __( '%d Admin Note', 'wpcd' ), $wpcd_admin_only_note_count );
				}
				$labels_count_arr[] = '<span class="wpcd-note-count wpcd-admin-note-count">' . $wpcd_admin_only_note_count . '</span>';
			}
		}

		return $labels_count_arr;

	}

	/**
	 * Add APP SERVER table header values
	 *
	 * @param array $defaults array of default head values.
	 *
	 * @return $defaults modified array with new columns
	 */
	public function app_server_table_head( $defaults ) {

		// Webserver type column.
		$show_it = true;
		if ( ! is_admin() && ( ! boolval( wpcd_get_option( 'wordpress_app_fe_show_web_server_type_column_in_server_list' ) ) ) ) {
			// Do not show it on the front-end.
			$show_it = false;
		}
		if ( is_admin() && ! boolval( wpcd_get_option( 'wordpress_app_show_web_server_type_column_in_server_list' ) ) ) {
			// Not allowed to show it in wp-admin.
			$show_it = false;
		}
		if ( $show_it ) {
			$defaults['wpcd_server_web_server_Type'] = __( 'Web Server', 'wpcd' );
		}

		// Health column.
		$show_it = true;
		if ( ! is_admin() && ( boolval( wpcd_get_option( 'wordpress_app_fe_hide_health_in_server_list' ) ) ) ) {
			$show_it = false;
		}
		if ( $show_it ) {
			$defaults['wpcd_server_health'] = __( 'Health', 'wpcd' );
		}

		return $defaults;
	}

	/**
	 * Show the server status as it resides in the server cpt for this app.
	 *
	 * Filter Hook: wpcd_app_server_admin_list_local_status_column
	 *
	 * @param string $column_data Data to show in the column.
	 * @param int    $post_id Id of app post being displayed.
	 *
	 * @return string $column_data
	 */
	public function app_server_admin_list_local_status_column( $column_data, $post_id ) {

		$local_status = get_post_meta( $post_id, 'wpcd_server_wordpress-app_action_status', true );
		if ( empty( $local_status ) ) {
			return $column_data;
		} else {
			$action = get_post_meta( $post_id, 'wpcd_server_wordpress-app_action', true );
			if ( ! empty( $action ) ) {
				$local_status .= '<br />action: ' . $action;
			}
			if ( empty( $column_data ) ) {
				return $column_data . 'wpapp: ' . $local_status;
			} else {
				// Append the data to the existing column data after first checking to see if the data is already in there.
				$local_status = 'wpapp: ' . $local_status;
				if ( strpos( $column_data, $local_status ) !== false ) {
					// do nothing.
				} else {
					// add the new data to the column.
					$column_data .= '<br />' . $local_status;
				}

				return $column_data;
			}
		}

		return $column_data;

	}

	/**
	 * Add contents to the APP SERVER table columns
	 *
	 * Filter Hook: wpcd_app_server_table_content
	 *
	 * @param string $value existing value of the column.
	 * @param string $column_name column name.
	 * @param int    $post_id post id.
	 *
	 * @return value to print to the server column.
	 */
	public function app_server_table_content( $value, $column_name, $post_id ) {

		switch ( $column_name ) {
			case 'wpcd_server_web_server_Type':
				$value = $this->get_formatted_web_server_type_for_display( $post_id );
				break;

			case 'wpcd_server_actions':
				// Check to make sure we're not trying to show this on a deleted post...
				if ( 'private' <> get_post_status( $post_id ) ) {
					break;
				}

				// Allow devs to hook in.
				$value = apply_filters( "wpcd_{$this->get_app_name()}_server_actions_column_before_install_button", $value, $post_id );

				// Adds an 'install WordPress' button to the server actions column.
				if ( apply_filters( 'wpcd_wpapp_show_install_wp_button', true, $post_id ) ) {

					// Get the state of the server.
					// @TODO: This section of code should probably be centralized into some sort of function split between class-wpcd-posts-app-server.php and class-wordpress-app.
					// linked with FILTERS since we're checking multiple metas scattered across server and app records.
					// Code duplicated around line #63 deep inside the function add_post_actions().
					$state            = get_post_meta( $post_id, 'wpcd_server_current_state', true );
					$pending_size_raw = get_post_meta( $post_id, 'wpcd_server_pending_size_raw', true ); // If server is being resized, this is the target new size.
					if ( 'active' == $state || empty( $state ) ) {
						// Now check to make sure that other stuff isn't in progress on the server...
						if ( ( ! empty( get_post_meta( $post_id, 'wpcd_server_wordpress-app_action', true ) ) ) || ( ! empty( get_post_meta( $post_id, 'wpcd_server_wordpress-app_action_status', true ) ) ) ) {
							$state = 'in-progress';
						}
					}

					// Only show the button if the server is active.
					if ( 'active' == $state || empty( $state ) ) {
						// Button text and link.
						$thebutton = ( get_post_meta( $post_id, 'wpcd_server_server-type', true ) <> 'wordpress-app' ) ? '' : sprintf( '<button class="wpcd_action_install_app button" data-wpcd-id="%d">%s</button>', $post_id, esc_html( __( 'Install WordPress', 'wpcd' ) ) );

						// Check permissions before showing the button.
						$user_id     = get_current_user_id();
						$post_author = get_post( $post_id )->post_author;
						if ( ! wpcd_user_can( $user_id, 'add_app_wpapp', $post_id ) && $post_author != $user_id ) {
							// No permissions, do nothing.
						} else {
							// Make sure this string isn't already in the button column...
							if ( ! empty( $thebutton ) ) {
								if ( strpos( $value, $thebutton ) !== false ) {
									// do nothing.
								} else {
									// add it to the column.
									$value = $thebutton . $value;  // Add the button to the top of the action list.
								}
							}
						}
					} elseif ( ! empty( $pending_size_raw ) ) {
						$value = '<div class="wpcd_server_actions_op_in_progress">' . __( 'An attempt is being made to resize this server. Please check your providers dashboard for the status of this operation. You might need to reboot the server from there if the operation is complete and you continue to see this message.', 'wpcd' ) . '</div>';
					} elseif ( 'off' === $state ) {
						$value = '<div class="wpcd_server_actions_op_in_progress">' . __( 'A shutdown command was sent to this server so it might be turned off.', 'wpcd' ) . '</div>';
					} else {
						$value = '<div class="wpcd_server_actions_op_in_progress">' . __( 'An operation seem to be in progress on this server. Please wait for it to complete!', 'wpcd' ) . '</div>';
					}
				}
				// End adds 'install WordPress' button to the server actions column.

				// Allow devs to hook in.
				$value = apply_filters( "wpcd_{$this->get_app_name()}_server_actions_column_before_web_server_type", $value, $post_id );

				// Add web server type beneath the button or notice.
				$show_web_server_type = true;
				if ( ! is_admin() && ( boolval( wpcd_get_option( 'wordpress_app_fe_hide_web_server_type_element_in_server_list' ) ) ) ) {
					// We're on the front-end - do not show there.
					$show_web_server_type = false;
				}
				if ( is_admin() && ( boolval( wpcd_get_option( 'wordpress_app_hide_web_server_element_in_server_list' ) ) ) ) {
					// We're in wp-admin area but not allowed to show this element.
					$show_web_server_type = false;
				}
				if ( $show_web_server_type && $this->get_app_name() === $this->get_server_type( $post_id ) && ! wpcd_get_option( 'wordpress_app_show_web_server_type_column_in_server_list' ) ) {
					// Show it. Notice that it's only being shown here if the option to show it in a separate column is not enabled.
					$value .= $this->get_formatted_web_server_type_for_display( $post_id, false );
				}

				// Allow devs to hook in.
				$value = apply_filters( "wpcd_{$this->get_app_name()}_server_actions_column_before_custom_links", $value, $post_id );

				// Add custom links below the install WordPress button.
				$value = $value . '<div class = "wpcd_server_actions_custom_links_wrap">' . $this->get_formatted_custom_links( $post_id ) . '</div>';

				// Allow devs to hook in.
				$value = apply_filters( "wpcd_{$this->get_app_name()}_server_actions_column_before_notes_and_labels", $value, $post_id );

				// Display the count of notes and admin notes.
				$labels_count_arr = $this->get_notes_count_string( $post_id );
				$labels_count_arr = implode( ' ', $labels_count_arr );
				$value            = $value . $labels_count_arr;

				// Allow devs to hook in.
				$value = apply_filters( "wpcd_{$this->get_app_name()}_server_actions_column_before_aptget_warning", $value, $post_id );

				// Display warning if the server is running aptget.
				if ( $this->wpcd_is_aptget_running( $post_id ) ) {
					$apt_get_warning_value = '<div class="wpcd_server_actions_aptget_in_progress">' . __( 'It appears that background updates are being run on this server. Certain actions you perform while this is occurring might fail.', 'wpcd' ) . '</div>';
					if ( empty( wpcd_get_option( 'wordpress_app_show_install_button_with_apt_get_running' ) ) ) {
						// Show only the warning and do not display the install wp button.
						$value = $apt_get_warning_value;
					} else {
						// show both the warning and the install wp button.
						$value .= $apt_get_warning_value;
					}
				}

				// Allow devs to hook in.
				$value = apply_filters( "wpcd_{$this->get_app_name()}_server_actions_column", $value, $post_id );

				break;

			case 'wpcd_server_health':
				// Check to make sure we're on a WordPress server...
				if ( 'wordpress-app' <> $this->get_server_type( $post_id ) ) {
					break;
				}

				// Check to make sure we're not trying to show this on a deleted post...
				if ( 'private' <> get_post_status( $post_id ) ) {
					break;
				}

				// String that will be returned/appended to output.
				$health = '';

				$disk_space = $this->get_server_status_callback_string( $post_id, 'free_disk_space_percent' );
				if ( ! empty( $disk_space ) ) {
					$health .= $disk_space;
				}

				$ram_free = $this->get_server_status_callback_string( $post_id, 'free_memory_percent' );
				if ( ! empty( $ram_free ) ) {
					$health .= '<br />' . $ram_free;
				}

				$restart_needed = $this->get_server_status_callback_string( $post_id, 'restart_needed' );
				if ( ! empty( $restart_needed ) ) {
					$health .= '<br />' . $restart_needed;
				}

				$security_updates = $this->get_server_status_callback_string( $post_id, 'security_updates' );
				if ( ! empty( $security_updates ) ) {
					$health .= '<br />' . $security_updates;
				}

				$malware_status = $this->get_server_status_callback_string( $post_id, 'malware_status' );
				if ( ! empty( $malware_status ) ) {
					$health .= '<br />' . $malware_status;
				}

				$default_php_version_status = $this->get_server_status_callback_string( $post_id, 'default_php_version' );
				if ( ! empty( $default_php_version_status ) ) {
					$health .= '<br />' . $default_php_version_status;
				}

				if ( empty( $health ) ) {
					$server_status_callback_status = get_post_meta( $post_id, 'wpcd_wpapp_server_status_callback_installed', true );
					if ( empty( $server_status_callback_status ) ) {
						$health_msg        = __( 'Callbacks are not installed.', 'wpcd' );
						$health_msg       .= '<br /><br />' . __( 'We usually auto-install them after a server has been deployed.', 'wpcd' );
						$health_msg       .= '<br /><br />' . __( 'If you just installed this server, please wait 30 mins and if you still see this message, install them from the CALLBACKS tab.', 'wpcd' );
						$health            = "<div class='wpcd_waiting_for_data_column'>" . $health_msg . '</div>';
						$callback_tab_link = ( is_admin() ? get_edit_post_link( $post_id ) : get_permalink( $post_id ) ) . '#~~callbacks';
						$health           .= "<div class='wpcd_go_to_callbacks_tab_column'>" . "<a href='" . $callback_tab_link . "'>" . __( 'Go To Callbacks Tab', 'wpcd' ) . '</a>' . '</div>';
					} else {
						$health = "<div class='wpcd_waiting_for_data_column'>" . __( 'Waiting For Data From Callback...', 'wpcd' ) . '</div>';
					}
				}

				$value .= $health;

				break;

		}

		return $value;

	}

	/**
	 * Return the web server type description formatted for display.
	 *
	 * @since 5.0
	 *
	 * @param int     $post_id The post id of the server or app.
	 * @param boolean $show_label_in_wpadmin Show the "Web Server:" label when displaying in wp-admin?.
	 *
	 * @return string
	 */
	public function get_formatted_web_server_type_for_display( $post_id, $show_label_in_wpadmin = false ) {
		$value = '';

		$web_server_type = $this->get_web_server_type( $post_id );
		$web_server_desc = $this->get_web_server_description( $web_server_type );

		// Show full string with label?
		$show_full = false;
		if ( is_admin() && true === $show_label_in_wpadmin ) {
			$show_full = true;
		}

		if ( ! is_admin() ) {
			$show_full = true; // Always show the full string on the frontend.
		}

		if ( ! $show_full ) {
			// Show only the value - usually when being displayed in wp-admin in it's own column.
			$value = WPCD_POSTS_APP_SERVER()->wpcd_column_wrap_string_with_span_and_class( $web_server_desc, 'web_server_desc', 'left' );
			$value = WPCD_POSTS_APP_SERVER()->wpcd_column_wrap_string_with_div_and_class( $value, 'web_server_desc' );
		} else {
			// Show both the label and the value.
			$value  = WPCD_POSTS_APP_SERVER()->wpcd_column_wrap_string_with_span_and_class( __( 'Web Server: ', 'wpcd' ), 'web_server_desc', 'left' );
			$value .= WPCD_POSTS_APP_SERVER()->wpcd_column_wrap_string_with_span_and_class( $web_server_desc, 'web_server_desc', 'right' );
			$value  = WPCD_POSTS_APP_SERVER()->wpcd_column_wrap_string_with_div_and_class( $value, 'web_server_desc' );
		}
		return $value;
	}

	/**
	 * Add new columns to the app list
	 *
	 * Filter Hook: manage_wpcd_app_posts_columns
	 *
	 * @param array $defaults column list.
	 *
	 * @return new array of columns
	 */
	public function app_posts_app_table_head( $defaults ) {

		// Webserver type column.
		$show_it = true;
		if ( ! is_admin() && ( ! boolval( wpcd_get_option( 'wordpress_app_fe_show_web_server_type_column_in_app_list' ) ) ) ) {
			// Do not show it on the front-end.
			$show_it = false;
		}
		if ( is_admin() && ! boolval( wpcd_get_option( 'wordpress_app_show_web_server_type_column_in_site_list' ) ) ) {
			// Not allowed to show it in wp-admin.
			$show_it = false;
		}
		if ( $show_it ) {
			$defaults['wpcd_wpapp_web_server_type'] = __( 'Web Server', 'wpcd' );
		}

		// Staging.
		if ( wpcd_get_option( 'wordpress_app_show_staging_column_in_site_list' ) ) {
			$show_it = true;
			if ( ! is_admin() && ( boolval( wpcd_get_option( 'wordpress_app_fe_hide_staging_in_app_list' ) ) ) ) {
				$show_it = false;
			}
			if ( $show_it ) {
				$defaults['wpcd_wpapp_staging'] = __( 'Staging', 'wpcd' );
			}
		}

		// Cache.
		$show_it = true;
		if ( ! is_admin() && ( ! boolval( wpcd_get_option( 'wordpress_app_fe_show_cache_in_app_list' ) ) ) ) {
			$show_it = false;
		}
		if ( $show_it ) {
			$defaults['wpcd_wpapp_cache'] = __( 'Cache', 'wpcd' );
		}

		// PHP.
		$show_it = true;
		if ( ! is_admin() && ( ! boolval( wpcd_get_option( 'wordpress_app_fe_show_php_in_app_list' ) ) ) ) {
			$show_it = false;
		}
		if ( $show_it ) {
			$defaults['wpcd_wpapp_php'] = __( 'PHP', 'wpcd' );
		}

		// SSL.
		$show_it = true;
		if ( ! is_admin() && ( ! boolval( wpcd_get_option( 'wordpress_app_fe_show_ssl_in_app_list' ) ) ) ) {
			$show_it = false;
		}
		if ( $show_it ) {
			$defaults['wpcd_wpapp_ssl'] = __( 'SSL', 'wpcd' );
		}

		// App Features - only applicable to the front-end.
		if ( ! is_admin() ) {
			$show_it = true;
			if ( ! is_admin() && ( boolval( wpcd_get_option( 'wordpress_app_fe_hide_app_features_in_app_list' ) ) ) ) {
				$show_it = false;
			}
			if ( $show_it ) {
				$defaults['wpcd_wpapp_features'] = __( 'App Features', 'wpcd' );
			}
		}

		return $defaults;

	}

	/**
	 * Add contents to the APP POSTS table columns
	 *
	 * Action Hook: manage_wpcd_app_posts_custom_column
	 *
	 * @param string $column_name string column name.
	 * @param int    $post_id int post id.
	 *
	 * @return value to print to the server column.
	 */
	public function app_posts_app_table_content( $column_name, $post_id ) {

		/* Bail out if the app being evaluated isn't a wp app. */
		if ( $this->get_app_name() <> get_post_meta( $post_id, 'app_type', true ) ) {
			return $column_name;
		}

		switch ( $column_name ) {

			case 'wpcd_wpapp_ssl':
				echo wp_kses_post( $this->get_formatted_ssl_for_display( $post_id ) );
				break;

			case 'wpcd_wpapp_php':
				echo wp_kses_post( $this->get_formatted_php_version_for_display( $post_id ) );
				break;

			case 'wpcd_wpapp_cache':
				echo wp_kses_post( $this->get_formatted_cache_for_display( $post_id ) );
				break;

			case 'wpcd_wpapp_web_server_type':
				$value = $this->get_formatted_web_server_type_for_display( $post_id );
				echo $value;
				break;

			case 'wpcd_wpapp_staging':
				if ( ! ( 'wordpress-app' === $this->get_app_name() ) ) {
					break;
				}
				if ( wpcd_get_option( 'wordpress_app_show_staging_column_in_site_list' ) ) {

					$str = '';

					if ( $this->is_staging_site( $post_id ) ) {
						$live_id = $this->get_live_id_for_staging_site( $post_id );
						if ( ! empty( $live_id ) ) {
							$link = '<a href="' . get_edit_post_link( $live_id ) . '" target="_blank">' . $this->get_live_domain_for_staging_site( $post_id ) . '</a>';
							/* Translators: %s: Link to the related live site of a staging site. */
							$str  = sprintf( __( 'Live Site: %s', 'wpcd' ), $link );
							$str .= '<br />';
							$str .= '<b><i>' . $this->get_formatted_wpadmin_link( $live_id ) . '</b></i>';
							$str .= '<br />';
							$str .= '<b><i>' . $this->get_formatted_site_link( $live_id ) . '</b></i>';
						}
					}

					if ( $this->get_companion_staging_site_id( $post_id ) ) {
						$staging_id = $this->get_companion_staging_site_id( $post_id );
						if ( ! empty( $staging_id ) ) {
							$link = '<a href="' . get_edit_post_link( $staging_id ) . '" target="_blank">' . $this->get_companion_staging_site_domain( $post_id ) . '</a>';
							/* Translators: %s: Link to the related live site of a staging site. */
							$str  = sprintf( __( 'Staging Site: %s', 'wpcd' ), $link );
							$str .= '<br />';
							$str .= '<b><i>' . $this->get_formatted_wpadmin_link( $staging_id ) . '</b></i>';
							$str .= '<br />';
							$str .= '<b><i>' . $this->get_formatted_site_link( $staging_id ) . '</b></i>';
						}
					}

					if ( empty( $str ) ) {
						if ( ! is_admin() ) {
							// We're on the front-end so display a message for empty data.
							$str .= __( 'This is not a staging site.', 'wpcd' );
						}
					}

					echo $str;

				}

				break;

			case 'wpcd_wpapp_features':
				/**
				 * This column only shows on the front-end hence the formatting of strings are only for that purpose.
				 */

				// Create a string suitable for displaying the ssl status.
				$ssl    = $this->get_formatted_ssl_for_display( $post_id );
				$value  = __( 'SSL: ', 'wpcd' );
				$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $value, 'site_ssl', 'left' );
				$value .= WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $ssl, 'site_ssl', 'right' );
				$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $value, 'site_ssl' );
				echo wp_kses_post( $value );

				// Create a string suitable for displaying the php version.
				$php    = $this->get_formatted_php_version_for_display( $post_id );
				$value  = __( 'PHP: ', 'wpcd' );
				$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $value, 'site_php', 'left' );
				$value .= WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $php, 'site_php', 'right' );
				$value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $value, 'site_php' );
				echo wp_kses_post( $value );

				// Get a string suitable for displaying the cache information.
				$cache = $this->get_formatted_cache_for_display( $post_id );
				echo wp_kses_post( $cache );

				break;

		}

	}

	/**
	 * Return a formatted string of the SSL status for display.
	 *
	 * @since 5.0
	 *
	 * @param int $post_id The post id of the app we're working with.
	 *
	 * @return string
	 */
	public function get_formatted_ssl_for_display( $post_id ) {

		if ( 'wordpress-app' === $this->get_app_name() ) {
			// add the ssl status.
			if ( true === $this->get_site_local_ssl_status( $post_id ) ) {
				$ssl_status = 'on';

				if ( true === $this->get_site_local_wildcard_ssl_status( $post_id ) ) {
					$ssl_status = 'on [*]';
				}
			} else {
				$ssl_status = 'off';
			}

			if ( 'off' == $ssl_status ) {
				return '<div class="wpcd_ssl_status wpcd_ssl_status_off">' . $ssl_status . '</div>';
			} else {
				return '<div class="wpcd_ssl_status wpcd_ssl_status_on">' . $ssl_status . '</div>';
			}
		}
		return '';
	}

	/**
	 * Return a formatted string of the cache status for display.
	 *
	 * @since 5.0
	 *
	 * @param int $post_id The post id of the app we're working with.
	 *
	 * @return string
	 */
	public function get_formatted_cache_for_display( $post_id ) {

		if ( 'wordpress-app' === $this->get_app_name() ) {
			// get the page cache status.
			$page_cache = WPCD_WORDPRESS_APP()->get_page_cache_status( $post_id );
			if ( empty( $page_cache ) ) {
				$page_cache = 'off';
			}

			// Create a string suitable for displaying the page cache status.
			$pc_value  = __( 'Page Cache: ', 'wpcd' );
			$pc_value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $pc_value, 'page_cache', 'left' );
			$pc_value .= WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $page_cache, 'page_cache', 'right' );
			$pc_value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $pc_value, 'page_cache' );

			// Wrap the string in a div.
			if ( 'off' === $page_cache ) {
				$pc_value = '<div class="wpcd_page_cache_status wpcd_page_cache_off">' . $pc_value . '</div>';
			} else {
				$pc_value = '<div class="wpcd_page_cache_status wpcd_page_cache_on">' . $pc_value . '</div>';
			}

			// get the memcached status...
			$object_cache = wpcd_maybe_unserialize( get_post_meta( $post_id, 'wpapp_memcached_status', true ) );
			if ( empty( $object_cache ) ) {
				$object_cache = 'off';
			} elseif ( 'on' === $object_cache ) {
				$object_cache = 'MemCached';
			}

			// get the redis status.
			if ( 'off' === $object_cache ) {
				$object_cache = wpcd_maybe_unserialize( get_post_meta( $post_id, 'wpapp_redis_status', true ) );
				if ( empty( $object_cache ) ) {
					$object_cache = 'off';
				} elseif ( 'on' === $object_cache ) {
					$object_cache = 'REDIS';
				}
			}

			// Create a string suitable for displaying the object cache status.
			$oc_value  = __( 'Object Cache: ', 'wpcd' );
			$oc_value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $oc_value, 'object_cache', 'left' );
			$oc_value .= WPCD_POSTS_APP()->wpcd_column_wrap_string_with_span_and_class( $object_cache, 'object_cache', 'right' );
			$oc_value  = WPCD_POSTS_APP()->wpcd_column_wrap_string_with_div_and_class( $oc_value, 'object_cache' );

			// Wrap the string in a div.
			if ( 'off' === $object_cache ) {
				$oc_value = '<div class="wpcd_object_cache_status wpcd_object_cache_off">' . $oc_value . '</div>';
			} else {
				$oc_value = '<div class="wpcd_object_cache_status wpcd_object_cache_on">' . $oc_value . '</div>';
			}

			return $pc_value . $oc_value;

		}

		return '';

	}

	/**
	 * Return a formatted string of the php version for display.
	 *
	 * @since 5.0
	 *
	 * @param int $post_id The post id of the app we're working with.
	 *
	 * @return string
	 */
	public function get_formatted_php_version_for_display( $post_id ) {
		if ( 'wordpress-app' === $this->get_app_name() ) {
			// add the php version.
			$php_version = $this->get_php_version_for_app( $post_id );

			// Create a variable that can be used as part of a css class name - periods are not allowed in class names.
			$php_version_class = str_replace( '.', '_', $php_version );

			// Show a link that takes you to a list of apps with the clicked php version.
			if ( is_admin() ) {
				$url = admin_url( 'edit.php?post_type=wpcd_app&wpapp_php_version=' . $php_version );
			} else {
				$url = get_permalink( WPCD_WORDPRESS_APP_PUBLIC::get_apps_list_page_id() ) . '?wpapp_php_version=' . $php_version;
			}

			return "<a href=\"$url\" class=\"wpcd_php_version wpcd_php_version_$php_version_class\">" . $php_version . '</a>';

		}
		return '';
	}

	/**
	 * Add content to the server that shows up in app admin list - just before the APPS ON THIS SERVER link.
	 *
	 * Filter Hook: wpcd_app_admin_list_server_column_before_apps_link
	 *
	 * @param string $column_data Usually empty.
	 * @param int    $post_id Id of app post being displayed.
	 *
	 * @return string
	 */
	public function app_admin_list_server_column_before_apps_link( $column_data, $post_id ) {

		// Maybe show web server information if the app type is wpapp
		if ( 'wordpress-app' === $this->get_app_type( $post_id ) ) {
			$show_web_server_type = true;
			if ( ! is_admin() && ( boolval( wpcd_get_option( 'wordpress_app_fe_hide_web_server_type_element_in_app_list' ) ) ) ) {
				// We're on the front-end - do not show there.
				$show_web_server_type = false;
			}
			if ( is_admin() && ( boolval( wpcd_get_option( 'wordpress_app_hide_web_server_element_in_site_list' ) ) ) ) {
				// We're in wp-admin area but not allowed to show this element.
				$show_web_server_type = false;
			}
			if ( $show_web_server_type ) {
				// Show it.
				return $column_data . $this->get_formatted_web_server_type_for_display( $post_id, true );

			}
		}

		return $column_data;
	}

	/**
	 * Set the post state display
	 *
	 * Filter Hook: display_post_states
	 *
	 * @param array  $states The current states for the CPT record.
	 * @param object $post The post object.
	 *
	 * @return array $states
	 */
	public function display_post_states( $states, $post ) {

		/* Show the app type and site status on the application list */
		if ( 'wpcd_app' === get_post_type( $post ) && 'wordpress-app' === $this->get_app_type( $post->ID ) && boolval( wpcd_get_option( 'wordpress_app_show_label_in_lists' ) ) ) {
			$states['wpcd-app-desc'] = $this->get_app_description();
		}

		/* Show whether the site is enabled or disabled */
		if ( 'wpcd_app' === get_post_type( $post ) && 'wordpress-app' === $this->get_app_type( $post->ID ) ) {
			if ( 'off' === $this->site_status( $post->ID ) ) {
				$states['wpcd-wpapp-status'] = __( 'Disabled', 'wpcd' );
			}
		}

		/* Show whether the site is admin locked */
		if ( 'wpcd_app' === get_post_type( $post ) && 'wordpress-app' === $this->get_app_type( $post->ID ) ) {
			if ( $this->get_admin_lock_status( $post->ID ) ) {
				$states['wpcd-wpapp-admin-locked'] = __( 'Admin Locked', 'wpcd' );
			}
		}

		/* Show whether the site is a staging site */
		if ( 'wpcd_app' === get_post_type( $post ) && 'wordpress-app' === $this->get_app_type( $post->ID ) ) {
			if ( true === $this->is_staging_site( $post->ID ) ) {
				$states['wpcd-wpapp-status'] = __( 'Staging', 'wpcd' );
			}
		}

		/* Show the object server type */
		if ( 'wpcd_app' === get_post_type( $post ) && 'wordpress-app' === $this->get_app_type( $post->ID ) && true === boolval( wpcd_get_option( 'wordpress_app_show_object_server_label_in_lists' ) ) ) {
			$object_server_type = '';
			if ( $this->get_app_is_redis_installed( $post->ID ) ) {
				$object_server_type = 'Redis';
			}
			if ( $this->get_app_is_memcached_installed( $post->ID ) ) {
				$object_server_type = 'Memcached';
			}
			if ( ! empty( $object_server_type ) ) {
				$states['wpcd-wpapp-object-server-type'] = $object_server_type;
			}
		}

		/* Show if the site has a remote database */
		if ( 'wpcd_app' === get_post_type( $post ) && 'wordpress-app' === $this->get_app_type( $post->ID ) ) {
			if ( 'yes' === $this->is_remote_db( $post->ID ) ) {
				$states['wpcd-wpapp-remote-db'] = __( 'RemoteDB', 'wpcd' );
			}
		}

		/* Show if the site is a GIT site */
		if ( 'wpcd_app' === get_post_type( $post ) && 'wordpress-app' === $this->get_app_type( $post->ID ) ) {
			if ( true === $this->get_git_status( $post->ID ) ) {
				$states['wpcd-wpapp-git-status'] = '<span class="wpcd_post_state_git_enabled">' . __( 'Git Enabled', 'wpcd' ) . '</span>';
			}
		}

		/* Show if the site is a template site */
		if ( 'wpcd_app' === get_post_type( $post ) && 'wordpress-app' === $this->get_app_type( $post->ID ) ) {
			// If multi-tenant is not enabled then we'll allow this to go through.
			// If multi-tenant is enabled, it will be handled later below instead.
			if ( ! wpcd_is_mt_enabled() ) {
				if ( true === $this->wpcd_is_template_site( $post->ID ) ) {
					$states['wpcd-wpapp-template-status'] = __( 'Template', 'wpcd' );
				}
			}
		}

		/* Show multi-tenant related site type. */
		if ( 'wpcd_app' === get_post_type( $post ) && 'wordpress-app' === $this->get_app_type( $post->ID ) ) {
			if ( true === wpcd_is_mt_enabled() ) {
				$mt_site_type = $this->get_mt_site_type( $post->ID );
				if ( 'standard' !== $mt_site_type ) {
					$css_class                         = 'wpcd_post_state_' . $mt_site_type;
					$states['wpcd-wpapp-mt-site-type'] = '<span class="' . $css_class . '">' . $mt_site_type . '</span>';
				}
			}
		}

		/* Show multi-tenant related version. */
		if ( 'wpcd_app' === get_post_type( $post ) && 'wordpress-app' === $this->get_app_type( $post->ID ) ) {
			if ( true === wpcd_is_mt_enabled() ) {
				// Show the Multi-tenant site version.
				$mt_version = $this->get_mt_version( $post->ID );
				if ( ! empty( $mt_version ) ) {
					$css_class                            = 'wpcd_post_state wpcd_mt_site_version';
					$states['wpcd-wpapp-mt-site-version'] = '<span class="' . $css_class . '">' . $mt_version . '</span>';
				}
			}
		}

		/* Show the server type on the server list screen */
		if ( 'wpcd_app_server' === get_post_type( $post ) && 'wordpress-app' === $this->get_server_type( $post->ID ) && boolval( wpcd_get_option( 'wordpress_app_show_label_in_lists' ) ) ) {
			$states['wpcd-server-type'] = 'WordPress';  // Unfortunately we don't have a server type description function we can call right now so hardcoding the value here.
		}

		/* Show if the server has a local/custom ssh login */
		if ( 'wpcd_app_server' === get_post_type( $post ) && 'wordpress-app' === $this->get_server_type( $post->ID ) && ! empty( WPCD()->decrypt( get_post_meta( $post->ID, 'wpcd_server_ssh_private_key', true ) ) ) ) {
			$states['wpcd-server-custom-ssh-login'] = __( 'SSH Override', 'wpcd' );
		}

		/* Show the object server type on the server list screen */
		if ( 'wpcd_app_server' === get_post_type( $post ) && 'wordpress-app' === $this->get_server_type( $post->ID ) && true === boolval( wpcd_get_option( 'wordpress_app_show_object_server_label_in_lists' ) ) ) {
			$object_server_type = '';
			if ( $this->is_redis_installed( $post->ID ) ) {
				$object_server_type = 'Redis';
			}
			if ( $this->is_memcached_installed( $post->ID ) ) {
				$object_server_type = 'Memcached';
			}
			if ( ! empty( $object_server_type ) ) {
				$states['wpcd-server-object-server-type'] = $object_server_type;
			}
		}

		return $states;

	}

	/**
	 * Adds link to REMOVE SITE under an APP.
	 *
	 * Filter hook: post_row_actions
	 *
	 * @param  array  $actions actions.
	 * @param  object $post post.
	 *
	 * @return array
	 */
	public function post_row_actions( $actions, $post ) {

		if ( ! wpcd_is_app_delete_protected( $post->ID ) ) {
			if ( wpcd_can_current_user_delete_app( $post->ID ) && $this->wpcd_user_can_remove_wp_site( $post->ID ) ) {
				$actions['wpcd_remove_site'] = sprintf(
					'<a class="wpcd_action_remove_site" data-wpcd-id="%d" href="">%s</a>',
					$post->ID,
					esc_html( __( 'Remove Site', 'wpcd' ) )
				);
			}
		}

		return $actions;
	}

	/**
	 * Returns a formatted string with pieces of information
	 * from the server status callback array stored in post_meta.
	 * The string will be formatted to be suitable for displaying
	 * in a column in the server list.
	 *
	 * The data is pulled from a meta that is updated
	 * by the server_status callback.
	 * See function push_command_server_status_completed()
	 * in file push-commands.php
	 *
	 * @param  int    $post_id post id.
	 * @param  string $item - data item to retrieve.
	 */
	public function get_server_status_callback_string( $post_id, $item ) {

		// String to return.
		$return = '';

		// Get server status data from meta.
		$server_status_items = get_post_meta( $post_id, 'wpcd_server_status_push', true );

		if ( empty( $server_status_items ) ) {
			return $return;
		}

		if ( isset( $server_status_items['total_memory'] ) ) {
			$memory = $server_status_items['total_memory'];
		} else {
			// we don't have any info from the server status callback...
			return $return;
		}

		if ( (int) $memory > 0 ) {
			// We have RAM information which means the server status callback reported at least once and we can safely assume that other status information is available.

			switch ( $item ) {
				case 'restart_needed':
					if ( isset( $server_status_items['restart'] ) && 'yes' === $server_status_items['restart'] ) {

						$return .= __( 'Restart Needed!', 'wpcd' );

						$class = 'wpcd_restart_needed_wrap';

						$return = "<div class='$class'>" . $return . '</div>';

					}
					break;

				case 'default_php_version':
					if ( isset( $server_status_items['default_php_version'] ) && ! empty( $server_status_items['default_php_version'] ) ) {

						// What versions of PHP should we warn for?
						$bad_php_versions = wpcd_get_option( 'wordpress_app_servers_default_php_warn_versions' );
						if ( empty( $bad_php_versions ) ) {
							$bad_php_versions = array( '5.6', '7.1', '7.2', '7.3', '8.2' );
						}

						// Set warning message if the current PHP version is in the array of ones we should warn for.
						if ( in_array( $server_status_items['default_php_version'], $bad_php_versions, true ) ) {

							/* Translators: %s is the incorrect PHP version. */
							$return .= sprintf( __( 'The PHP server CLI version is incorrect. It is currently set to %s.', 'wpcd' ), $server_status_items['default_php_version'] );

							$class = 'wpcd_incorrect_php_default_version';

							$return = "<div class='$class'>" . $return . '</div>';

						}
					}
					break;

				case 'free_disk_space_percent':
					if ( isset( $server_status_items['free_disk_space_percent'] ) ) {
						$freepct = $server_status_items['free_disk_space_percent'];
						$return .= __( 'Disk Free: ', 'wpcd' ) . $freepct . '%';

						if ( isset( $server_status_items['reporting_time'] ) ) {
							$return .= '<br />' . __( 'As of: ', 'wpcd' ) . date( 'Y-m-d @ H:i', $server_status_items['reporting_time'] );
						}

						// Now we want to wrap the value in a class so we can style it.  The class name will vary based on the value of the free disk space.
						if ( (int) $freepct <= 15 ) {
							$class = 'wpcd_disk_free_space_pct_wrap wpcd_disk_free_space_pct_wrap_critical';
						} elseif ( (int) $freepct <= 25 && (int) $freepct > 15 ) {
							$class = 'wpcd_disk_free_space_pct_wrap wpcd_disk_free_space_pct_wrap_warning';
						} else {
							$class = 'wpcd_disk_free_space_pct_wrap wpcd_disk_free_space_pct_wrap_good';
						}

						$return = "<div class='$class'>" . $return . '</div>';
					}
					break;

				case 'free_memory_percent':
					if ( isset( $server_status_items['used_memory_percent'] ) ) {
						$freepct = 100 - ( (int) $server_status_items['used_memory_percent'] ) / 100;   // Used memory is reported weirdly - 1900 = 19%.  Go figure.
						$return .= __( 'RAM Free: ', 'wpcd' ) . $freepct . '%';

						// Now we want to wrap the value in a class so we can style it.  The class name will vary based on the value of the free disk space.
						if ( (int) $freepct <= 15 ) {
							$class = 'wpcd_ram_free_space_pct_wrap wpcd_ram_free_space_pct_wrap_critical';
						} elseif ( (int) $freepct <= 25 && (int) $freepct > 15 ) {
							$class = 'wpcd_ram_free_space_pct_wrap wpcd_ram_free_space_pct_wrap_warning';
						} else {
							$class = 'wpcd_ram_free_space_pct_wrap wpcd_ram_free_space_pct_wrap_good';
						}

						$return = "<div class='$class'>" . $return . '</div>';
					}
					break;

				case 'security_updates':
					if ( isset( $server_status_items['security_updates'] ) && $server_status_items['security_updates'] > 0 ) {
						// We have security updates.
						$secupdates = $server_status_items['security_updates'];
						$return    .= __( 'Sec. Updates: ', 'wpcd' ) . $secupdates;

						if ( isset( $server_status_items['total_updates'] ) ) {
							$return .= __( ' of ', 'wpcd' ) . $server_status_items['total_updates'];
						}

						// Now we want to wrap the value in a class so we can style it.  The class name will vary based on the value of the free disk space.
						$class = '';
						if ( (int) $secupdates > 0 ) {
							$class = 'wpcd_sec_updates_wrap';
						}

						$return = "<div class='$class'>" . $return . '</div>';
					} elseif ( isset( $server_status_items['total_updates'] ) && $server_status_items['total_updates'] > 0 ) {
						// No security updates but we do have other updates.
						$updates = $server_status_items['total_updates'];
						$return .= __( 'Non Sec. Updates: ', 'wpcd' ) . $updates;

						// Now we want to wrap the value in a class so we can style it.  The class name will vary based on the value of the free disk space.
						$class = '';
						if ( (int) $updates > 0 ) {
							$class = 'wpcd_linux_updates_wrap';
						}

						$return = "<div class='$class'>" . $return . '</div>';

					}
					if ( isset( $server_status_items['apt_check_error'] ) && $server_status_items['apt_check_error'] > 0 ) {
						// We have an error with apt so can't get totals for security updates.
						$class       = 'wpcd_sec_updates_wrap';
						$check_error = "<div class='$class'>" . __( 'Updates Check Error Notice: Please run all updates manually!', 'wpcd' ) . '</div>';
						$return     .= $check_error;
					}
					break;

				case 'malware_status':
					$return .= $this->get_malware_status_callback_string( $post_id );
					break;

			}
		} else {
			// we don't have any info from the server status callback...
			return $return;
		}

		return $return;

	}

	/**
	 * Returns a formatted string with MALWARE STATUS information
	 * suitable for displaying in a column in the server list.
	 *
	 * The data is pulled from a meta that is updated
	 * by the maldet_scan_completed callback.
	 * See function push_command_maldet_scan_completed()
	 *  in file push-commands.php
	 *
	 * @param  int $post_id post id.
	 *
	 * @return void
	 */
	public function get_malware_status_callback_string( $post_id ) {

		// String to return.
		$return = '';

		// Get server status data from meta.
		$server_malware_items = get_post_meta( $post_id, 'wpcd_maldet_scan_push', true );

		if ( empty( $server_malware_items ) ) {
			return $return;
		}

		if ( isset( $server_malware_items['total_files'] ) && ( (int) $server_malware_items['total_files'] > 0 ) ) {

			// We have Malware items information which means the server status callback reported at least once and we can safely assume that other status information is available.
			if ( ( isset( $server_malware_items['total_hits'] ) && $server_malware_items['total_hits'] > 0 ) ||
					( isset( $server_malware_items['total_cleaned'] ) && $server_malware_items['total_cleaned'] > 0 )
				) {

				$return .= __( 'Malware: ', 'wpcd' ) . (string) $server_malware_items['total_hits'];
				if ( (int) $server_malware_items['total_cleaned'] > 0 ) {
					$return .= '<br />' . __( 'Cleaned: ', 'wpcd' ) . (string) $server_malware_items['total_cleaned'];
				}

				if ( isset( $server_malware_items['reporting_time'] ) ) {
					$return .= '<br />' . __( 'As of: ', 'wpcd' ) . date( 'Y-m-d', $server_malware_items['reporting_time'] );
				}

				$class = 'wpcd_malware_found_wrap';

				$return = "<div class='$class'>" . $return . '</div>';

			}
		} else {
			// we don't have any info from the server status callback...
			return $return;
		}

		return $return;

	}

	/**
	 * Returns a formatted string with pieces of information
	 * from the site status callback array stored in post_meta.
	 * The string will be formatted to be suitable for displaying
	 * in a column in the site list.
	 *
	 * The data is pulled from a meta that is updated
	 * by the server_status callback.
	 * See function push_command_sites_status_completed()
	 * in file push-commands.php
	 *
	 * @param  int    $post_id post id.
	 * @param  string $item - data item to retrieve.
	 */
	public function get_site_status_callback_string( $post_id, $item ) {

		// String to return.
		$return = '';

		// Get site status data from meta.
		$site_status_items = get_post_meta( $post_id, 'wpcd_site_status_push', true );

		if ( empty( $site_status_items ) ) {
			return $return;
		}

		if ( isset( $site_status_items['domain'] ) ) {
			$domain = $site_status_items['domain'];
		} else {
			// we don't have any info from the site status callback...
			return $return;
		}

		if ( ! empty( $domain ) ) {
			// We have domain information which means the site status callback reported at least once and we can safely assume that other status information is available.

			switch ( $item ) {
				case 'wp_update_needed':
					if ( isset( $site_status_items['wp_update_needed'] ) && 'yes' == $site_status_items['wp_update_needed'] ) {

						$return .= __( 'WP Update Needed', 'wpcd' );

						$class = 'wpcd_site_update_needed wpcd_wp_update_needed';

						$return = "<div class='$class'>" . $return . '</div>';

					}
					break;
				case 'plugin_updates_count':
					if ( isset( $site_status_items['plugin_updates_count'] ) ) {
						$plugin_updates_count = (int) $site_status_items['plugin_updates_count'];
						if ( $plugin_updates_count > 0 ) {
							$return .= __( 'Plugin Updates: ', 'wpcd' ) . (string) $plugin_updates_count;

							// Now we want to wrap the value in a class so we can style it.
							$class = 'wpcd_site_update_needed wpcd_plugin_update_needed';

							$return = "<div class='$class'>" . $return . '</div>';
						}
					}
					break;

				case 'theme_updates_count':
					if ( isset( $site_status_items['theme_updates_count'] ) ) {
						$theme_updates_count = (int) $site_status_items['theme_updates_count'];
						if ( $theme_updates_count > 0 ) {
							$return .= __( 'Theme Updates: ', 'wpcd' ) . (string) $theme_updates_count;

							// Now we want to wrap the value in a class so we can style it.
							$class = 'wpcd_site_update_needed wpcd_theme_update_needed';

							$return = "<div class='$class'>" . $return . '</div>';
						}
					}
					break;

				case 'data_date':
					if ( isset( $site_status_items['reporting_time'] ) ) {
						// $return .= '<br />' . __( 'As of: ', 'wpcd' ) . date( 'Y-m-d @ H:i', $site_status_items['reporting_time'] );
						// $return .= __( 'As of: ', 'wpcd' ) . date( 'Y-m-d @ H:i', $site_status_items['reporting_time'] );
						$return .= date( 'Y-m-d @ H:i', $site_status_items['reporting_time'] );

						// Now we want to wrap the value in a class so we can style it.
						$class = 'wpcd_site_update_reporting_time';

						$return = "<div class='$class'>" . $return . '</div>';
					}
					break;

			}
		} else {
			// we don't have any info from the site status callback...
			return $return;
		}

		return $return;

	}

}
