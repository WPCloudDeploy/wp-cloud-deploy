<?php
/**
 * This class handle the sync process
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_SYNC
 */
class WPCD_SYNC {

	/**
	 * WPCD_SYNC instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_Sync constructor.
	 */
	public function __construct() {

		// Action hook to check if settings changed or updated.
		add_action( 'rwmb_before_save_post', array( $this, 'check_for_settings_values_changed' ), 10, 1 );

		// Action hook to delete the received file.
		add_action( 'wp_ajax_wpcd_delete_received_file', array( $this, 'wpcd_delete_received_file' ) );

		// Action hook to restore the received file.
		add_action( 'wp_ajax_wpcd_restore_received_file', array( $this, 'wpcd_restore_received_file' ) );

		// Action hook to fire on new site created on WP Multisite.
		add_action( 'wp_initialize_site', array( $this, 'export_schedule_events_for_new_site' ), 10, 2 );

		// Action hook to push data to another site.
		add_action( 'wp_ajax_wpcd_sync_push', array( $this, 'wpcd_sync_push' ) );

		// Action hook to save the encryption key.
		add_action( 'wp_ajax_wpcd_encryption_key_save', array( $this, 'wpcd_encryption_key_save' ) );

		// Action hook to export data on selected time.
		add_action( 'wpcd_export_data_actions', array( $this, 'get_common_code_for_export_data' ), 1, 6 );
	}


	/**
	 * Function for update the cron when cron settings changed
	 *
	 * @param string $object_id object id.
	 */
	public function check_for_settings_values_changed( $object_id ) {
		if ( true === is_admin() && 'wpcd_settings' === $object_id ) {

			$wpcd_sync_target_site    = filter_input( INPUT_POST, 'wpcd_sync_target_site', FILTER_SANITIZE_STRING );
			$wpcd_sync_enc_key        = filter_input( INPUT_POST, 'wpcd_sync_enc_key', FILTER_SANITIZE_STRING );
			$wpcd_sync_user_id        = filter_input( INPUT_POST, 'wpcd_sync_user_id', FILTER_SANITIZE_STRING );
			$wpcd_sync_password       = filter_input( INPUT_POST, 'wpcd_sync_password', FILTER_SANITIZE_STRING );
			$wpcd_sync_auto_export    = filter_input( INPUT_POST, 'wpcd_sync_auto_export', FILTER_SANITIZE_NUMBER_INT );
			$wpcd_sync_set_cron       = filter_input( INPUT_POST, 'wpcd_sync_set_cron', FILTER_SANITIZE_STRING );
			$wpcd_export_all_settings = filter_input( INPUT_POST, 'wpcd_export_all_settings', FILTER_SANITIZE_NUMBER_INT );
			$ajax                     = 0;

			$saved_sync_target_site    = wpcd_get_option( 'wpcd_sync_target_site' );
			$saved_sync_enc_key        = wpcd_get_option( 'wpcd_sync_enc_key' );
			$saved_sync_user_id        = wpcd_get_option( 'wpcd_sync_user_id' );
			$saved_sync_password       = wpcd_get_option( 'wpcd_sync_password' );
			$saved_sync_auto_export    = wpcd_get_option( 'wpcd_sync_auto_export' );
			$saved_sync_set_cron       = wpcd_get_option( 'wpcd_sync_set_cron' );
			$saved_export_all_settings = wpcd_get_option( 'wpcd_export_all_settings' );
			$output_matched            = 1;
			if ( $wpcd_sync_target_site != $saved_sync_target_site ||
				$wpcd_sync_enc_key != $saved_sync_enc_key ||
				$wpcd_sync_user_id != $saved_sync_user_id ||
				$wpcd_sync_password != $saved_sync_password ||
				$wpcd_sync_auto_export != $saved_sync_auto_export ||
				$wpcd_sync_set_cron != $saved_sync_set_cron ||
				$wpcd_export_all_settings != $saved_export_all_settings
			) {
				$output_matched = 0;
			}

			if ( ! empty( $wpcd_export_all_settings ) && $wpcd_export_all_settings == 1 ) {
				$wpcd_export_all_settings = 1;
			} else {
				$wpcd_export_all_settings = 0;
			}

			if ( ! empty( $saved_export_all_settings ) && $saved_export_all_settings == 1 ) {
				$saved_export_all_settings = 1;
			} else {
				$saved_export_all_settings = 0;
			}

			$wpcd_sync_target_site = WPCD()->encrypt( $wpcd_sync_target_site );
			$wpcd_sync_enc_key     = WPCD()->encrypt( $wpcd_sync_enc_key );
			$wpcd_sync_user_id     = WPCD()->encrypt( $wpcd_sync_user_id );
			$wpcd_sync_password    = WPCD()->encrypt( $wpcd_sync_password );

			$schedule_args = array( $wpcd_sync_target_site, $wpcd_sync_enc_key, $wpcd_sync_user_id, $wpcd_sync_password, $wpcd_export_all_settings, $ajax );

			$saved_sync_target_site = WPCD()->encrypt( $saved_sync_target_site );
			$saved_sync_enc_key     = WPCD()->encrypt( $saved_sync_enc_key );
			$saved_sync_user_id     = WPCD()->encrypt( $saved_sync_user_id );
			$saved_sync_password    = WPCD()->encrypt( $saved_sync_password );

			$old_schedule_args = array( $saved_sync_target_site, $saved_sync_enc_key, $saved_sync_user_id, $saved_sync_password, $saved_export_all_settings, $ajax );

			// remove cron if auto export disable.
			if ( $wpcd_sync_auto_export == 0 || empty( $wpcd_sync_auto_export ) ) {

				if ( is_multisite() && is_plugin_active_for_network( wpcd_plugin ) ) {
					// Get all blogs in the network.
					$blog_ids = get_sites( array( 'fields' => 'ids' ) );
					foreach ( $blog_ids as $blog_id ) {
						switch_to_blog( $blog_id );
						wp_unschedule_hook( 'wpcd_export_data_actions' );
						wp_clear_scheduled_hook( 'wpcd_export_data_actions', $old_schedule_args );
						wp_clear_scheduled_hook( 'wpcd_export_data_actions', $schedule_args );
						restore_current_blog();
					}
				} else {
					wp_unschedule_hook( 'wpcd_export_data_actions' );
					wp_clear_scheduled_hook( 'wpcd_export_data_actions', $old_schedule_args );
					wp_clear_scheduled_hook( 'wpcd_export_data_actions', $schedule_args );
				}
			}

			// set cron if export settings changed.
			if ( $output_matched == 0 ) {

				if ( is_multisite() && is_plugin_active_for_network( wpcd_plugin ) ) {
					// Get all blogs in the network.
					$blog_ids = get_sites( array( 'fields' => 'ids' ) );
					foreach ( $blog_ids as $blog_id ) {
						switch_to_blog( $blog_id );
						wp_unschedule_hook( 'wpcd_export_data_actions' );
						wp_clear_scheduled_hook( 'wpcd_export_data_actions', $old_schedule_args );
						restore_current_blog();
					}
				} else {
					wp_unschedule_hook( 'wpcd_export_data_actions' );
					wp_clear_scheduled_hook( 'wpcd_export_data_actions', $old_schedule_args );
				}

				if ( $wpcd_sync_auto_export == 1 ) {
					if ( isset( $wpcd_sync_set_cron ) && ! empty( $wpcd_sync_set_cron ) ) {

						if ( is_multisite() && is_plugin_active_for_network( wpcd_plugin ) ) {
							// Get all blogs in the network.
							$blog_ids = get_sites( array( 'fields' => 'ids' ) );
							foreach ( $blog_ids as $blog_id ) {
								switch_to_blog( $blog_id );
								wp_schedule_event( time(), $wpcd_sync_set_cron, 'wpcd_export_data_actions', $schedule_args );
								restore_current_blog();
							}
						} else {
							wp_schedule_event( time(), $wpcd_sync_set_cron, 'wpcd_export_data_actions', $schedule_args );
						}
					}
				}
			}
		}
	}


	/**
	 * Deletes received files
	 */
	public function wpcd_delete_received_file() {
		// nonce check.
		check_ajax_referer( 'wpcd-settings', 'nonce' );

		// Permissions check.
		if ( ! wpcd_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		global $wpdb;

		$file_name  = filter_input( INPUT_POST, 'file_name', FILTER_SANITIZE_STRING );
		$restore_id = filter_input( INPUT_POST, 'restore_id', FILTER_SANITIZE_STRING );

		$user_id = get_current_user_id();

		// Check before deleting if file exists.
		$table_name    = $wpdb->prefix . 'wpcd_restore_files';
		$get_files_sql = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE restore_id = %d AND file_name = %d", array( $restore_id, $file_name ) );
		$files_results = $wpdb->get_results( $get_files_sql );

		if ( count( $files_results ) != 0 ) {

			$delete_result = $wpdb->delete( $table_name, array( 'restore_id' => $restore_id ) );

			if ( ! $delete_result ) {
				// if unable to delete the file, show a message.
				$error_msg = array( 'msg' => __( 'There is a problem while deleting the file.', 'wpcd' ) );
				wp_send_json_error( $error_msg );
				wp_die();
			}

			$success_msg = array( 'msg' => __( 'File deleted successfully.', 'wpcd' ) );
			wp_send_json_success( $success_msg );
			wp_die();

		} else {
			$error_msg = array( 'msg' => __( 'File does not exist on the Server.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

	}

	/**
	 * Restore received files
	 */
	public function wpcd_restore_received_file() {
		// nonce check.
		check_ajax_referer( 'wpcd-settings', 'nonce' );

		// Permissions check.
		if ( ! wpcd_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		global $wpdb;

		$file_name       = filter_input( INPUT_POST, 'file_name', FILTER_SANITIZE_STRING );
		$restore_id      = filter_input( INPUT_POST, 'restore_id', FILTER_SANITIZE_STRING );
		$delete_existing = filter_input( INPUT_POST, 'delete_existing', FILTER_VALIDATE_BOOLEAN );
		$key             = filter_input( INPUT_POST, 'decryption_key_to_restore', FILTER_SANITIZE_STRING );

		$user_id = get_current_user_id();

		// Check before restoring if file exists.
		$table_name    = $wpdb->prefix . 'wpcd_restore_files';
		$get_files_sql = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE restore_id = %d AND file_name = %d", array( $restore_id, $file_name ) );
		$files_results = $wpdb->get_results( $get_files_sql );

		if ( count( $files_results ) != 0 ) {

			$encrypted_json_data = $files_results[0]->file_data;
			$decrypted_json_data = WPCD()->decrypt( $encrypted_json_data, $key );

			/*
			$secretKey = md5($key);
			$iv = substr( hash( 'sha256', "aaaabbbbcccccddddeweee" ), 0, 16 );
			openssl_decrypt(base64_decode($encrypted_json_data), 'AES-128-CBC', $secretKey, OPENSSL_RAW_DATA, $iv);
			*/

			$decoded_json_data = json_decode( $decrypted_json_data, true );

			if ( empty( $decrypted_json_data ) || empty( $decoded_json_data ) ) {
				$error_msg = array( 'msg' => __( 'The file can not be restored. Please enter a valid decryption key.', 'wpcd' ) );
				wp_send_json_error( $error_msg );
				wp_die();
			}

			$exported_users = $decoded_json_data['users'];

			$new_user_ids = array();

			// Users to be imported.
			foreach ( $exported_users as $exported_user ) {
				$user_email = $exported_user['user_email'];
				$user       = get_user_by( 'email', $user_email );

				// If user exists, get the user id and store it in the array.
				if ( $user ) {
					$new_user_ids[ $exported_user['user_id'] ] = $user->ID;
				} else {
					$user_name       = $exported_user['user_login'];
					$random_password = wp_generate_password();
					$user_created    = wp_create_user( $user_name, $random_password, $user_email );

					// If user is created, assign a role to the user.
					if ( ! is_wp_error( $user_created ) ) {
						$user_roles = $exported_user['user_roles'];
						foreach ( $user_roles as $role ) {
							$user = new WP_User( $user_created );
							$user->add_role( $role );
						}

						$new_user_ids[ $exported_user['user_id'] ] = $user_created;
					}
				}
			}

			$teams = $decoded_json_data['teams'];

			$new_team_ids = array();

			if ( count( $teams ) ) {

				// Teams to be imported.
				foreach ( $teams as $key => $team ) {
					$post_data = array();

					$args = array(
						'name'        => $team['post_name'],
						'post_type'   => $team['post_type'],
						'post_status' => $team['post_status'],
						'numberposts' => 1,
					);

					$posts = get_posts( $args );

					// If the same post exists.
					if ( count( $posts ) ) {
						// If delete_existing is true then it will delete the existing team post.
						if ( $delete_existing ) {
							$post_id = $posts[0]->ID;
							// This will delete the post forcefully.
							wp_delete_post( $post_id, true );
						} else {
							$new_team_ids[ $team['post_id'] ] = $posts[0]->ID;
							continue;
						}
					}

					$post_title = str_replace( '-', ' ', $team['post_name'] );
					$post_title = ucwords( $post_title );

					$post_data['post_title']  = $post_title;
					$post_data['post_type']   = $team['post_type'];
					$post_data['post_status'] = $team['post_status'];
					$post_data['post_author'] = get_current_user_id();

					// insert the team post.
					$post_id = wp_insert_post( $post_data );

					// if the team post inserted, add the meta related to it.
					if ( ( ! empty( $post_id ) ) && ( ! is_wp_error( $post_id ) ) ) {
						$wpcd_permission_rule = $team['post_meta']['wpcd_permission_rule'];

						// if the permission rule isnt empty.
						if ( $wpcd_permission_rule ) {
							$new_wpcd_permission_rule = array();
							foreach ( $wpcd_permission_rule as $rule ) {
								$rule['wpcd_team_member'] = $new_user_ids[ $rule['wpcd_team_member'] ];

								$server_permissions = $rule['wpcd_server_permissions'];

								if ( $server_permissions ) {
									foreach ( $server_permissions as $key => $server_permission ) {

										// Get the ID of permission post by permission name.
										$posts = get_posts(
											array(
												'post_type' => 'wpcd_permission_type',
												'post_status' => 'private',
												'meta_key' => 'wpcd_permission_name',
												'meta_value' => $server_permission,
												'fields'   => 'ids',
												'posts_per_page' => 1,
											)
										);

										$server_permissions[ $key ] = $posts ? $posts[0] : '';
									}
									// update the server permission ids on Target Site.
									$rule['wpcd_server_permissions'] = $server_permissions;
								}

								$app_permissions = $rule['wpcd_app_permissions'];
								if ( $app_permissions ) {
									foreach ( $app_permissions as $key => $app_permission ) {

										// Get the ID of permission post by permission name.
										$posts = get_posts(
											array(
												'post_type' => 'wpcd_permission_type',
												'post_status' => 'private',
												'meta_key' => 'wpcd_permission_name',
												'meta_value' => $app_permission,
												'fields'   => 'ids',
												'posts_per_page' => 1,
											)
										);

										// update the app permission ids on Target Site.
										$app_permissions[ $key ] = $posts ? $posts[0] : '';
									}
									$rule['wpcd_app_permissions'] = $app_permissions;
								}

								// Assign the updated rule to a new array.
								$new_wpcd_permission_rule[] = $rule;
							}

							update_post_meta( $post_id, 'wpcd_permission_rule', $new_wpcd_permission_rule );
						}

						$new_team_ids[ $team['post_id'] ] = $post_id;
					}
				}
			}

			$server_posts = $decoded_json_data['server_posts'];
			$app_posts    = $decoded_json_data['app_posts'];

			$parent_post_ids = array();

			// Server posts to be imported.
			foreach ( $server_posts as $server_post ) {
				$post_data = array();

				$args = array(
					'name'        => $server_post['post_title'],
					'post_type'   => $server_post['post_type'],
					'post_status' => $server_post['post_status'],
					'numberposts' => 1,
				);

				$posts = get_posts( $args );

				// If the same post exists.
				if ( count( $posts ) ) {
					// If delete_existing is true then it will delete the existing server post.
					if ( $delete_existing ) {
						$post_id = $posts[0]->ID;
						// This will delete the post forcefully and not fire certain hooks.
						wp_delete_post( $post_id, true );
					} else {
						continue;
					}
				}

				$user_email = $server_post['user_info']['user_email'];
				$user       = get_user_by( 'email', $user_email );

				if ( $user ) {
					$post_author = $user->ID;
				} else {
					$user_name       = $server_post['user_info']['user_login'];
					$random_password = wp_generate_password();
					$post_author     = wp_create_user( $user_name, $random_password, $user_email );

					// If user is created, assign a role to the user.
					if ( ! is_wp_error( $post_author ) ) {
						$user_roles = $server_post['user_info']['user_roles'];
						foreach ( $user_roles as $role ) {
							$user = new WP_User( $post_author );
							$user->add_role( $role );
						}
					}
				}

				// check if $post_author doesnt result in WP Error.
				if ( is_wp_error( $post_author ) ) {
					$post_author = get_current_user_id();
				}

				$post_data['post_title']  = $server_post['post_title'];
				$post_data['post_type']   = $server_post['post_type'];
				$post_data['post_status'] = $server_post['post_status'];
				$post_data['post_author'] = $post_author;

				// insert the server post.
				$post_id = wp_insert_post( $post_data );

				// if the server post inserted, add the meta related to it.
				if ( ( ! empty( $post_id ) ) && ( ! is_wp_error( $post_id ) ) ) {
					foreach ( $server_post['post_meta'] as $key => $value ) {

						if ( 'wpcd_assigned_teams' === $key ) {
							$wpcd_assigned_teams = $value;

							if ( $wpcd_assigned_teams ) {
								foreach ( $wpcd_assigned_teams as $team_id ) {
									add_post_meta( $post_id, 'wpcd_assigned_teams', $new_team_ids[ $team_id ], false );
								}
								continue;
							}
						}

						if ( 'wpcd_server_post_id' === $key ) {
							$parent_post_ids[ $value ] = $post_id;
							$value                     = $post_id;
						}

						// meta keys that contains serialized value.
						$values_to_unserialize_keys = array(
							'wpcd_server_actions',
							'wpcd_server_last_deferred_action_source',
							'wpcd_server_status_push',
							'wpcd_server_status_push_history',
						);

						if ( in_array( $key, $values_to_unserialize_keys ) ) {
							$value = unserialize( $value );
						}

						update_post_meta( $post_id, $key, $value );
					}
				}
			}

			// App posts to be imported.
			foreach ( $app_posts as $app_post ) {
				$post_data = array();

				$args = array(
					'name'        => $app_post['post_title'],
					'post_type'   => $app_post['post_type'],
					'post_status' => $app_post['post_status'],
					'numberposts' => 1,
				);

				$posts = get_posts( $args );

				// If the same post exists.
				if ( count( $posts ) ) {
					// If delete_existing is true then it will delete the existing app post.
					if ( $delete_existing ) {
						$post_id = $posts[0]->ID;
						// This will delete the post forcefully and not fire certain hooks.
						wp_delete_post( $post_id, true );
					} else {
						continue;
					}
				}

				$user_email = $app_post['user_info']['user_email'];
				$user       = get_user_by( 'email', $user_email );

				if ( $user ) {
					$post_author = $user->ID;
				} else {
					$user_name       = $app_post['user_info']['user_login'];
					$random_password = wp_generate_password();
					$post_author     = wp_create_user( $user_name, $random_password, $user_email );

					// If user is created, assign a role to the user.
					if ( ! is_wp_error( $post_author ) ) {
						$user_roles = $app_post['user_info']['user_roles'];
						foreach ( $user_roles as $role ) {
							$user = new WP_User( $post_author );
							$user->add_role( $role );
						}
					}
				}

				// check if $post_author doesnt result in WP Error.
				if ( is_wp_error( $post_author ) ) {
					$post_author = get_current_user_id();
				}

				$post_data['post_title']  = $app_post['post_title'];
				$post_data['post_type']   = $app_post['post_type'];
				$post_data['post_status'] = $app_post['post_status'];
				$post_data['post_author'] = $post_author;

				// insert the server post.
				$post_id = wp_insert_post( $post_data );

				// if the server post inserted, add the meta related to it.
				if ( ( ! empty( $post_id ) ) && ( ! is_wp_error( $post_id ) ) ) {
					foreach ( $app_post['post_meta'] as $key => $value ) {

						if ( 'wpcd_assigned_teams' === $key ) {
							$wpcd_assigned_teams = $value;

							if ( $wpcd_assigned_teams ) {
								foreach ( $wpcd_assigned_teams as $team_id ) {
									add_post_meta( $post_id, 'wpcd_assigned_teams', $new_team_ids[ $team_id ], false );
								}
								continue;
							}
						}

						if ( 'parent_post_id' == $key ) {
							$value = $parent_post_ids[ $value ];
						}

						// meta keys that contains serialized value.
						$values_to_unserialize_keys = array( 'wpcd_app_action_args' );

						if ( in_array( $key, $values_to_unserialize_keys ) ) {
							$value = unserialize( $value );
						}

						update_post_meta( $post_id, $key, $value );
					}
				}
			}

			// Check if settings data is available or not.
			if ( isset( $decoded_json_data['wpcd_settings'] ) ) {
				// Settings to be imported.
				$settings_to_be_imported = $decoded_json_data['wpcd_settings'];

				// Check if export/import settings is enabled or not.
				if ( isset( $settings_to_be_imported['wpcd_export_all_settings'] ) ) {
					$wpcd_export_all_settings_enable = $settings_to_be_imported['wpcd_export_all_settings'];
					if ( ! empty( $wpcd_export_all_settings_enable ) && $wpcd_export_all_settings_enable == 1 ) {

						// Don't update the sync tab settings.
						$settings_to_be_imported['wpcd_sync_target_site']    = wpcd_get_option( 'wpcd_sync_target_site' );
						$settings_to_be_imported['wpcd_sync_enc_key']        = wpcd_get_option( 'wpcd_sync_enc_key' );
						$settings_to_be_imported['wpcd_sync_user_id']        = wpcd_get_option( 'wpcd_sync_user_id' );
						$settings_to_be_imported['wpcd_sync_password']       = wpcd_get_option( 'wpcd_sync_password' );
						$settings_to_be_imported['wpcd_sync_auto_export']    = wpcd_get_option( 'wpcd_sync_auto_export' );
						$settings_to_be_imported['wpcd_sync_set_cron']       = wpcd_get_option( 'wpcd_sync_set_cron' );
						$settings_to_be_imported['wpcd_export_all_settings'] = wpcd_get_option( 'wpcd_export_all_settings' );
						$settings_to_be_imported['wpcd_encryption_key_v2']   = wpcd_get_option( 'wpcd_encryption_key_v2' );

						// update the settings options.
						update_option( 'wpcd_settings', $settings_to_be_imported );
					}
				}
			}

			$success_msg = array( 'msg' => __( 'File data imported successfully.', 'wpcd' ) );
			wp_send_json_success( $success_msg );
			wp_die();
		} else {
			$error_msg = array( 'msg' => __( 'File does not exist on the Server.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}
	}

	/**
	 * Fires on activation of plugin.
	 *
	 * @param  boolean $network_wide    True if plugin is activated network-wide.
	 *
	 * @return void
	 */
	public static function activate( $network_wide ) {

		if ( is_multisite() && $network_wide ) {
			// Get all blogs in the network.
			$blog_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::export_schedule_events();
				self::wpcd_create_restore_file_table();
				restore_current_blog();
			}
		} else {
			self::export_schedule_events();
			self::wpcd_create_restore_file_table();
		}

	}

	/**
	 * Creates the custom table for restore files
	 * This will happen on Plugin Activation.
	 *
	 * @return void
	 */
	public static function wpcd_create_restore_file_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'wpcd_restore_files';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE $table_name (
			restore_id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			recieved_from varchar(250) NOT NULL,
			file_name varchar(250) NOT NULL,
			file_data LONGTEXT NOT NULL,
			date_time DATETIME NOT NULL,
			PRIMARY KEY (restore_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		add_option( 'wpcd_db_version', wpcd_db_version );
	}

	/**
	 * Deletes the custom table for restore files
	 * This will happen on Plugin Uninstallation.
	 *
	 * @return void
	 */
	public static function wpcd_delete_restore_files_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpcd_restore_files';
		$sql        = "DROP TABLE IF EXISTS $table_name";
		$wpdb->query( $sql );
		delete_option( 'wpcd_db_version' );
	}

	/**
	 * Unschedule existing export events on Activation of the plugin.
	 *
	 * @return void
	 */
	public static function export_schedule_events() {
		wp_unschedule_hook( 'wpcd_export_data_actions' );
	}

	/**
	 * Fires on deactivation of plugin.
	 *
	 * @param  boolean $network_wide    True if plugin is deactivated network-wide.
	 *
	 * @return void
	 */
	public static function deactivate( $network_wide ) {

		if ( is_multisite() && $network_wide ) {
			// Get all blogs in the network.
			$blog_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::export_clear_scheduled_events();
				restore_current_blog();
			}
		} else {
			self::export_clear_scheduled_events();
		}

	}

	/**
	 * Clears scheduled events on Deactivation of the plugin.
	 *
	 * @return void
	 */
	public static function export_clear_scheduled_events() {
		$settings = get_option( 'wpcd_settings' );

		$wpcd_sync_target_site    = wpcd_get_option( 'wpcd_sync_target_site' );
		$wpcd_sync_enc_key        = wpcd_get_option( 'wpcd_sync_enc_key' );
		$wpcd_sync_user_id        = wpcd_get_option( 'wpcd_sync_user_id' );
		$wpcd_sync_password       = wpcd_get_option( 'wpcd_sync_password' );
		$wpcd_export_all_settings = wpcd_get_option( 'wpcd_export_all_settings' );
		$ajax                     = 0;

		if ( ! empty( $wpcd_export_all_settings ) && $wpcd_export_all_settings == 1 ) {
			$wpcd_export_all_settings = 1;
		} else {
			$wpcd_export_all_settings = 0;
		}

		$wpcd_sync_target_site = WPCD()->encrypt( $wpcd_sync_target_site );
		$wpcd_sync_enc_key     = WPCD()->encrypt( $wpcd_sync_enc_key );
		$wpcd_sync_user_id     = WPCD()->encrypt( $wpcd_sync_user_id );
		$wpcd_sync_password    = WPCD()->encrypt( $wpcd_sync_password );

		$schedule_args = array( $wpcd_sync_target_site, $wpcd_sync_enc_key, $wpcd_sync_user_id, $wpcd_sync_password, $wpcd_export_all_settings, $ajax );

		$settings['wpcd_sync_auto_export'] = 0;
		update_option( 'wpcd_settings', $settings );

		wp_unschedule_hook( 'wpcd_export_data_actions' );
		wp_clear_scheduled_hook( 'wpcd_export_data_actions', $schedule_args );
	}

	/**
	 * To schedule events for newly created site on WP Multisite and
	 * To create custom table for newly created site on WP Mulisite.
	 *
	 * Action hook: wp_initialize_site
	 *
	 * @param  object $new_site new site.
	 * @param  array  $args args.
	 * @return void
	 */
	public function export_schedule_events_for_new_site( $new_site, $args ) {

		$plugin_name = wpcd_plugin;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {

			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			self::export_clear_scheduled_events();
			self::wpcd_create_restore_file_table();
			restore_current_blog();
		}

	}


	/**
	 * Pushes data to the target site
	 */
	public function wpcd_sync_push() {
		// nonce check.
		check_ajax_referer( 'wpcd-sync-push', 'nonce' );

		// Permissions check.
		if ( ! wpcd_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		$wpcd_sync_target_site    = filter_input( INPUT_POST, 'wpcd_sync_target_site', FILTER_SANITIZE_STRING );
		$wpcd_sync_enc_key        = filter_input( INPUT_POST, 'wpcd_sync_enc_key', FILTER_SANITIZE_STRING );
		$wpcd_sync_user_id        = filter_input( INPUT_POST, 'wpcd_sync_user_id', FILTER_SANITIZE_STRING );
		$wpcd_sync_password       = filter_input( INPUT_POST, 'wpcd_sync_password', FILTER_SANITIZE_STRING );
		$wpcd_export_all_settings = filter_input( INPUT_POST, 'wpcd_export_all_settings', FILTER_SANITIZE_NUMBER_INT );

		$wpcd_sync_target_site = WPCD()->encrypt( $wpcd_sync_target_site );
		$wpcd_sync_enc_key     = WPCD()->encrypt( $wpcd_sync_enc_key );
		$wpcd_sync_user_id     = WPCD()->encrypt( $wpcd_sync_user_id );
		$wpcd_sync_password    = WPCD()->encrypt( $wpcd_sync_password );

		$ajax = 1;
		echo $this->get_common_code_for_export_data( $wpcd_sync_target_site, $wpcd_sync_enc_key, $wpcd_sync_user_id, $wpcd_sync_password, $wpcd_export_all_settings, $ajax );

	}

	/**
	 * Gets the common code for export data
	 *
	 * @param string $wpcd_sync_target_site wpcd_sync_target_site.
	 * @param string $wpcd_sync_enc_key wpcd_sync_enc_key.
	 * @param int    $wpcd_sync_user_id wpcd_sync_user_id.
	 * @param string $wpcd_sync_password wpcd_sync_password.
	 * @param array  $wpcd_export_all_settings wpcd_export_all_settings.
	 * @param int    $ajax ajax.
	 */
	public function get_common_code_for_export_data( $wpcd_sync_target_site, $wpcd_sync_enc_key, $wpcd_sync_user_id, $wpcd_sync_password, $wpcd_export_all_settings, $ajax ) {

		$source_site_url = site_url();

		$wpcd_sync_target_site = WPCD()->decrypt( $wpcd_sync_target_site );
		$wpcd_sync_enc_key     = WPCD()->decrypt( $wpcd_sync_enc_key );
		$wpcd_sync_user_id     = WPCD()->decrypt( $wpcd_sync_user_id );
		$wpcd_sync_password    = WPCD()->decrypt( $wpcd_sync_password );

		// Get SERVER and APP posts.
		$args = array(
			'post_type'   => array( 'wpcd_app_server', 'wpcd_app' ),
			'post_status' => 'private',
			'numberposts' => -1,
		);

		$posts = get_posts( $args );

		if ( count( $posts ) ) {
			$server_posts = array();
			$app_posts    = array();

			foreach ( $posts as $post ) {

				switch ( get_post_type( $post ) ) {
					case 'wpcd_app_server':
						$server_posts[ $post->ID ]['post_id']     = $post->ID;
						$server_posts[ $post->ID ]['post_title']  = $post->post_title;
						$server_posts[ $post->ID ]['post_type']   = $post->post_type;
						$server_posts[ $post->ID ]['post_status'] = $post->post_status;

						// Add user info to export.
						$user_info = get_userdata( $post->post_author );

						$server_posts[ $post->ID ]['user_info']['user_login'] = $user_info->user_login;
						$server_posts[ $post->ID ]['user_info']['user_email'] = $user_info->user_email;
						$server_posts[ $post->ID ]['user_info']['user_roles'] = $user_info->roles;

						// Get all the server meta.
						$server_all_meta = get_post_meta( $post->ID, '', true );

						foreach ( $server_all_meta as $key => $value ) {
							if ( 'wpcd_assigned_teams' === $key ) {
								continue;
							}
							// $key is meta key name and $value[0] contains the value for that meta key.
							$server_posts[ $post->ID ]['post_meta'][ $key ] = $value[0];
						}

						$wpcd_assigned_teams = get_post_meta( $post->ID, 'wpcd_assigned_teams', false );
						if ( $wpcd_assigned_teams ) {
							$server_posts[ $post->ID ]['post_meta']['wpcd_assigned_teams'] = $wpcd_assigned_teams;
						}

						break;

					case 'wpcd_app':
						$app_posts[ $post->ID ]['post_id']     = $post->ID;
						$app_posts[ $post->ID ]['post_title']  = $post->post_title;
						$app_posts[ $post->ID ]['post_type']   = $post->post_type;
						$app_posts[ $post->ID ]['post_status'] = $post->post_status;

						// Add user info to export.
						$user_info = get_userdata( $post->post_author );

						$app_posts[ $post->ID ]['user_info']['user_login'] = $user_info->user_login;
						$app_posts[ $post->ID ]['user_info']['user_email'] = $user_info->user_email;
						$app_posts[ $post->ID ]['user_info']['user_roles'] = $user_info->roles;

						// Get all the app meta.
						$app_all_meta = get_post_meta( $post->ID, '', true );

						foreach ( $app_all_meta as $key => $value ) {
							if ( 'wpcd_assigned_teams' === $key ) {
								continue;
							}
							// $key is meta key name and $value[0] contains the value for that meta key.
							$app_posts[ $post->ID ]['post_meta'][ $key ] = $value[0];
						}

						$wpcd_assigned_teams = get_post_meta( $post->ID, 'wpcd_assigned_teams', false );
						if ( $wpcd_assigned_teams ) {
							$app_posts[ $post->ID ]['post_meta']['wpcd_assigned_teams'] = $wpcd_assigned_teams;
						}

						break;
				}
			}

			$raw_data = array();

			$raw_data['server_posts'] = $server_posts; // Contains all the server posts and its metadata.
			$raw_data['app_posts']    = $app_posts; // Contains all the app posts and its metadata.

			// Get the team data.
			$teams = get_posts(
				array(
					'post_type'   => 'wpcd_team',
					'post_status' => 'private',
					'numberposts' => -1,
				)
			);

			$team_posts     = array();
			$exported_users = array();
			if ( count( $teams ) ) {
				foreach ( $teams as $team ) {
					$team_posts[ $team->ID ]['post_id']     = $team->ID;
					$team_posts[ $team->ID ]['post_name']   = $team->post_name;
					$team_posts[ $team->ID ]['post_type']   = $team->post_type;
					$team_posts[ $team->ID ]['post_status'] = $team->post_status;

					// Get all the team meta.
					$wpcd_permission_rule = get_post_meta( $team->ID, 'wpcd_permission_rule', true );

					if ( $wpcd_permission_rule ) {
						$new_wpcd_permission_rule = array();
						foreach ( $wpcd_permission_rule as $rule ) {
							$server_permissions = $rule['wpcd_server_permissions'];

							if ( $server_permissions ) {
								foreach ( $server_permissions as $key => $server_permission ) {
									$server_permissions[ $key ] = get_post_meta( $server_permission, 'wpcd_permission_name', true );
								}
								$rule['wpcd_server_permissions'] = $server_permissions;
							}

							$app_permissions = $rule['wpcd_app_permissions'];
							if ( $app_permissions ) {
								foreach ( $app_permissions as $key => $app_permission ) {
									$app_permissions[ $key ] = get_post_meta( $app_permission, 'wpcd_permission_name', true );
								}
								$rule['wpcd_app_permissions'] = $app_permissions;
							}
							$new_wpcd_permission_rule[] = $rule;
						}

						$team_posts[ $team->ID ]['post_meta']['wpcd_permission_rule'] = $new_wpcd_permission_rule;
					}

					foreach ( $wpcd_permission_rule as $rule ) {
						$team_member = $rule['wpcd_team_member'];

						// Get user by ID.
						$user = new WP_User( $team_member );

						// if user id exists then continue.
						if ( array_key_exists( $team_member, $exported_users ) ) {
							continue;
						}

						// user data to be exported.
						$exported_users[ $team_member ]['user_id']    = $team_member;
						$exported_users[ $team_member ]['user_login'] = $user->data->user_login;
						$exported_users[ $team_member ]['user_email'] = $user->data->user_email;
						$exported_users[ $team_member ]['user_roles'] = $user->roles;
					}
				}

				$raw_data['teams'] = $team_posts; // Contains all the team posts and its metadata.
				$raw_data['users'] = $exported_users; // Contains the users related to teams.

			}

			// Get all the Settings fields.

			if ( ! empty( $wpcd_export_all_settings ) && $wpcd_export_all_settings == 1 ) {
				$settings                             = get_option( 'wpcd_settings' );
				$settings['wpcd_export_all_settings'] = 1;
				$raw_data['wpcd_settings']            = $settings;
			}

			// Encode the data into json.
			$json_data = wp_json_encode( $raw_data );

			// Encrypt the JSON data.
			$encrypted_json_data = WPCD()->encrypt( $json_data, $wpcd_sync_enc_key );
			/*
			$secretKey = md5($wpcd_sync_enc_key);
			$iv = substr( hash( 'sha256', "aaaabbbbcccccddddeweee" ), 0, 16 );
			$encrypted_json_data = base64_encode(openssl_encrypt($json_data, 'AES-128-CBC', $secretKey, OPENSSL_RAW_DATA, $iv));
			*/

			// Init a cUrl request for pushing data to target site.
			$ch = curl_init();

			$target_site = trailingslashit( $wpcd_sync_target_site );
			$url         = $target_site . 'wp-json/wpcd/v1/receivedata';

			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_POST, 1 );

			// Set the parameters.
			curl_setopt(
				$ch,
				CURLOPT_POSTFIELDS,
				http_build_query(
					array(
						'data'            => $encrypted_json_data,
						'key'             => $wpcd_sync_enc_key,
						'username'        => $wpcd_sync_user_id,
						'password'        => $wpcd_sync_password,
						'source_site_url' => $source_site_url,
					)
				)
			);

			// Receive server response.
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

			$response = curl_exec( $ch );

			curl_close( $ch );

			$response_arr = json_decode( $response, true );

			// if data not store at the target site.
			if ( $response_arr['status'] == false ) {
				if ( $ajax == 0 ) {
					do_action( 'wpcd_log_error', $response_arr['message'], 'debug', __FILE__, __LINE__ );
				} else {
					do_action( 'wpcd_log_error', $response_arr['message'], 'debug', __FILE__, __LINE__ );
					$error_msg = array( 'msg' => $response_arr['message'] );
					wp_send_json_error( $error_msg );
					wp_die();
				}
			}

			if ( $ajax == 1 ) {
				$success_msg = array( 'msg' => __( 'SERVER and APP post data have been exported and is stored in a JSON file on the destination site.', 'wpcd' ) );
				wp_send_json_success( $success_msg );
				wp_die();
			}
		} else {
			if ( $ajax == 0 ) {
				do_action( 'wpcd_log_error', 'No posts found for SERVER and APP.', 'debug', __FILE__, __LINE__ );
			} else {
				do_action( 'wpcd_log_error', 'No posts found for SERVER and APP.', 'debug', __FILE__, __LINE__ );
				// if no server or app post found.
				$error_msg = array( 'msg' => __( 'No posts found for SERVER and APP.', 'wpcd' ) );
				wp_send_json_error( $error_msg );
				wp_die();
			}
		}
	}


	/**
	 * Save encryption key in the settings
	 */
	public function wpcd_encryption_key_save() {
		// nonce check.
		check_ajax_referer( 'wpcd-encryption-key-save', 'nonce' );

		// Permissions check.
		if ( ! wpcd_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		$wpcd_encryption_key_v2 = filter_input( INPUT_POST, 'wpcd_encryption_key_v2', FILTER_SANITIZE_STRING );

		update_option( 'wpcd_encryption_key_v2', $wpcd_encryption_key_v2 );

		$success_msg = array( 'msg' => __( 'Encryption key saved successfully.', 'wpcd' ) );
		wp_send_json_success( $success_msg );
		wp_die();
	}
}
