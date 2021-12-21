<?php
/**
 * Trait:
 * Contains functions related to running commands and retrieving
 * logs to display to the admin.
 * Used only by the class-wordpress.php file which defines the
 * WPCD_WORDPRESS_APP class.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_wpapp_commands_and_logs
 */
trait wpcd_wpapp_commands_and_logs {

	/**
	 * Run a previously constructed command asynchronously.
	 * This one is called 'type 2' because its a newer async method.
	 * Check out the write up about the different aysnc methods we use
	 * here: https://wpclouddeploy.com/documentation/wpcloud-deploy-dev-notes/ssh-execution-models/
	 *
	 * @param string $id       app post id.
	 * @param string $command  The command reference that is used as a sort of a handle to the async operation.
	 * @param string $run_cmd  The actual command to run on the server.
	 * @param array  $instance An array with server and app instance data such as the provider and other important things.
	 * @param string $action   The reference string used by ajax to trigger the command eg: 'backup-run-manual' or 'restore-from-backup'.
	 *
	 * @return array | boolean Return false if no other data can be provided about the current command.
	 *                          Return array letting the calling program pass it through to the ajax call.
	 *                          to let ajax know we're still doing an async command.
	 */
	public function run_async_command_type_2( $id, $command, $run_cmd, $instance, $action ) {

		// Now that we have the command constructed, either run it or check to see if it is already running...
		if ( ! $this->is_command_done( $id, $command ) && ! $this->is_command_running( $id, $command ) ) {
			do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );
			$result = $this->execute_ssh(
				'generic',
				$instance,
				array( 'commands' => $run_cmd ),
				function() use ( $instance, $action, $command, $id ) {
					if ( ! $this->is_command_running( $id, $command ) ) {
						$this->set_command_started( $id, $command );
						// if this is a long running command, let's add a deferred action that will fetch the logs using cron.
						// instead of being polled by the client.
						// this takes care of the scenario where a long running command fails when the user is on another screen.
						// the intermediate logs are available because the cron job fetched the logs, not the polling client.
						$post_type = 'wpcd_app';  // Assume an app cpt.
						if ( ! empty( $id ) ) {
							$post_type = get_post_type( $id );
						}
						switch ( $post_type ) {
							case 'wpcd_app':
								update_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_status", 'in-progress' );
								update_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action", 'fetch-logs-from-server' );
								update_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_args", array( 'command' => $command ) );
								break;
							case 'wpcd_app_server':
								update_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action_status", 'in-progress' );
								update_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action", 'fetch-logs-from-server' );
								update_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action_args", array( 'command' => $command ) );
								break;							
						}
					}
				}
			);
			if ( is_wp_error( $result ) ) {
				do_action( 'wpcd_log_error', sprintf( 'Unable to run command %s on %s ', $run_cmd, print_r( $instance, true ) ), 'error', __FILE__, __LINE__, $instance, false );

				// if something went wrong, remove all the 'temporary' metas so that another attempt will run.
				delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_status" );  // Should only be on an app record.
				delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action" );   // Should only be on an app record.
				delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_args" );   // Should only be on an app record.
				delete_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action_status" );   // Should only be on an server record.
				delete_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action" );   // Should only be on an server record.
				delete_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action_args" );   // Should only be on an server record.

				// Let the user know that something bad happened.
				$result = array(
					'msg'     => __( 'The command seemed to have errored. If you continue to receive this message, you should check the various logs for clues and/or contact WPCloud Deploy Support for assistance!', 'wpcd' ),
					'refresh' => 'yes',
				);
				return $result;

			} else {
				return array(
					'command' => $command,
					'async'   => 'yes',
				);
			}
		}

		return false;

	}


	/**
	 * Called when a command completes.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_completed
	 *
	 * @param int    $id     The postID of the SERVER cpt.
	 * @param string $name   The name of the command.
	 */
	public function command_completed( $id, $name ) {
		// get the app that is in-progress.
		$posts = get_posts(
			array(
				'post_type'   => 'wpcd_app',
				'post_status' => 'private',
				'numberposts' => 300,
				'meta_query'  => array(
					array(
						'key'   => 'wpcd_app_action_status',
						'value' => 'in-progress',
					),
					array(
						'key'   => 'parent_post_id',
						'value' => $id,
					),
				),
				'fields'      => 'ids',
			)
		);

		// loop through and clean up metas on the app records.
		if ( $posts ) {
			foreach ( $posts as $app_id ) {
				$app_post_id = $app_id;
				// remove the 'temporary' meta.
				delete_post_meta( $app_post_id, 'wpcd_app_action_status' );
				delete_post_meta( $app_post_id, 'wpcd_install_wp_command_name' );
				delete_post_meta( $app_post_id, 'wpcd_app_action_args' );

				// Fire some hooks...
				$base_command = $this->get_command_base_name( $name ); // First, we need to extract out the base command from $name.
				if ( ! empty( $base_command ) ) {
					do_action( "wpcd_command_{$this->get_app_name()}_{$base_command}_completed_after_cleanup", $id, $app_id, $name, $base_command );  // Hook Name Example: wpcd_command_wordpress-app_install_wp_completed_after_cleanup.
					do_action( "wpcd_command_{$this->get_app_name()}_completed_after_cleanup", $id, $app_id, $name, $base_command );
				}
			}
		}

		// remove server level 'temporary' metas as well.
		delete_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action" );
		delete_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action_status" );

	}


	/**
	 * Fetches the logs for a particular app ID and command from the database.
	 *
	 * @param int    $id id.
	 * @param string $name name.
	 */
	public function get_app_command_logs( $id, $name ) {
		$logs   = null;
		$log_id = get_post_meta( $id, 'wpcd_temp_log_id', true );

		// either the logs have not yet started or the command is done.
		if ( empty( $log_id ) && $this->is_command_done( $id, $name ) ) {
			$log_id = WPCD_COMMAND_LOG::get_command_log_id( $id, $name );
		}

		if ( ! empty( $log_id ) ) {
			$logs = WPCD_COMMAND_LOG::get_command_log( $log_id );
		}

		return $logs;
	}


	/**
	 * Runs commands after server is created
	 *
	 * If this function is being called, the assumption is that the
	 * server is in an "active" state, ready for commands.
	 *
	 * @param array $instance Array of attributes for the custom post type.
	 */
	private function run_after_server_create_commands( $instance ) {

		/* If we're here, server is up and running, which means we have an IP address. Make sure it gets added to the server record! */
		if ( $instance['post_id'] && isset( $instance['ip'] ) && $instance['ip'] ) {
			WPCD_SERVER()->add_ipv4_address( $instance['post_id'], $instance['ip'] );
		}

		/* With the IP address housekeeping finished, we can get the commands to run */
		$run_cmd = $this->get_after_server_create_commands( $instance );

		do_action( 'wpcd_log_error', sprintf( 'attempting to run after server create commands for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		if ( ! empty( $run_cmd ) ) {
			// replace the starting dashes.
			$run_cmd = str_replace( ' - ', '', $run_cmd );

			/*
			NOTE:
			This hangs and does not return if the command contains an & to run in the background.
			If this command is run manually on the linux shell, it returns to the shell prompt correctly.
			Nothing of the following works:
			- adding a '\n' after the command
			- echo "done" && {...commands..} > /dev/null &
			- echo "done" && {...commands..} > /dev/null 2>&1 &
			- echo "done" && {...commands..} &
			- commands in different lines separated by ; and an exit 0 to force exit

			That is why we have to provide a callback.
			*/
			$result = $this->execute_ssh(
				'generic',
				$instance,
				array( 'commands' => $run_cmd ),
				function() use ( $instance ) {
					if ( ! $this->is_command_running( $instance['post_id'], 'prepare_server' ) ) {
						$this->set_command_started( $instance['post_id'], 'prepare_server' );
					}
				}
			);

			if ( is_wp_error( $result ) ) {
				do_action( 'wpcd_log_error', sprintf( 'Unable to run command %s on %s ', $run_cmd, print_r( $instance, true ) ), 'error', __FILE__, __LINE__, $instance, false );
			} else {
				return true;
			}
		}

		return false;
	}


	/**
	 * Get script file contents to run for servers that are being provisioned...
	 *
	 * Generally, the file contents is a combination of three components:
	 *  1. The run commands passed into the cloud provider api.
	 *  2. The main bash script that we want to run upon start up.
	 *  3. Parameters to the bash script.
	 *
	 * Filter Hook: wpcd_cloud_provider_run_cmd
	 *
	 * @param array $attributes Attributes of the server and app being provisioned.
	 *
	 * @return string $run_cmd
	 */
	public function get_after_server_create_commands( $attributes ) {
		return $this->turn_script_into_command( $attributes, 'after-server-create-run-commands.txt', $attributes );
	}

	/**
	 * Performs an SSH command on a WordPress Server instance.
	 *
	 * @param string $action Action to perform.
	 * @param array  $attributes Attributes of the Server instance.
	 * @param array  $additional Additional data that may be required for the action. It can use the following keys (non-exhaustive list):
	 *  commands: The command(s) to execute.
	 *  remote: The remote file (for uploads).
	 *  local: The local file (for uploads).
	 * @param string $callback callback.
	 *
	 * @return mixed
	 */
	public function execute_ssh( $action, $attributes, $additional, $callback = null ) {

		// Get an array with ssh login data.
		$key = $this->ssh()->get_ssh_key_details( $attributes );
		if ( empty( $key ) ) {
			do_action( 'wpcd_log_error', print_r( $key, true ), 'error', __FILE__, __LINE__ );
			return new \WP_Error( __( 'We are unable to connect to the server at this time because we cannot get a key or instance for the server - please try again later or contact technical support.', 'wpcd' ) );
		}

		// Initialize some variables...
		$post_id                   = $attributes['post_id'];
		$root_user                 = $key['root_user'];
		$ssh_private_key_encrypted = $key['key'];
		$ssh_private_key_password  = $key['passwd'];
		$ip                        = $key['ip'];

		// Run the command we were given.
		$result = null;
		switch ( $action ) {
			case 'generic':
				$commands = $additional['commands'];
				$result   = $this->ssh()->exec( $ip, $commands, $key, $action, $post_id, $root_user, $callback );
				break;
			case 'upload':
				$result = $this->ssh()->upload( $ip, $additional['remote'], $additional['local'], $key, $root_user );
				break;
		}

		do_action( 'wpcd_log_error', 'execute_ssh: result = ' . print_r( $result, true ), 'trace', __FILE__, __LINE__, null, false );

		if ( is_wp_error( $result ) ) {
			do_action( 'wpcd_log_error', print_r( $result, true ), 'error', __FILE__, __LINE__ );
			return $result;
		}

		if ( in_array( $action, array( 'connected', 'disconnect', 'generic', 'upload' ) ) ) {
			return $result;
		}

		WPCD_SERVER()->add_action_to_history( $action, $attributes );
		return true;
	}


	/**
	 * Returns the attributes the SSH commands need.
	 *
	 * @param int $id id.
	 */
	private function get_ssh_attributes( $id ) {
		// if this post is not a server, let's get that because all SSH related details are inside there.
		if ( get_post_type( $id ) !== 'wpcd_app_server' ) {
			$server_post_id = get_post_meta( $id, 'parent_post_id', true );
			if ( get_post_type( $server_post_id ) !== 'wpcd_app_server' ) {
				// what the hell? the parent is not a server??
				return \WP_Error( sprintf( 'Parent of %d (%s) is %d (%s)', $id, get_post_type( $id ), $server_post_id, get_post_type( $server_post_id ) ) );
			}
			$id = $server_post_id;
		}

		$attributes = array(
			'post_id' => $id,
		);

		/* Get data from server post */
		$all_meta = get_post_meta( $id );
		foreach ( $all_meta as $key => $value ) {
			if ( 'wpcd_server_app_post_id' == $key ) {
				continue;  // this key, if present, should not be added to the array since it shouldn't even be in the server cpt in the first place. But it might get there accidentally on certain operations.
			}

			if ( strpos( $key, 'wpcd_server_' ) === 0 ) {
				$value = wpcd_maybe_unserialize( $value );
				$attributes[ str_replace( 'wpcd_server_', '', $key ) ] = is_array( $value ) && count( $value ) === 1 ? $value[0] : $value;
			}
		}

		// Get an array with ssh login data.
		$key = $this->ssh()->get_ssh_key_details( $attributes );
		if ( empty( $key ) ) {
			do_action( 'wpcd_log_error', print_r( $key, true ), 'error', __FILE__, __LINE__ );
			return new \WP_Error( __( 'We are unable to connect to the server at this time because we cannot get a key or instance for the server - please try again later or contact technical support.', 'wpcd' ) );
		}
		
		return $key;
	}


	/**
	 * Gets the .logs.done.
	 *
	 * @param string $logs logs.
	 * @param int    $id id.
	 * @param string $name name.
	 */
	public function get_logs_done( $logs, $id, $name ) {
		$attributes = $this->get_ssh_attributes( $id );

		// Make sure the $attributes var is valid otherwise return false.
		if ( is_wp_error( $attributes ) || empty( $attributes ) ) {
			return false;
		}

		$return     = $this->ssh()->download( $attributes['ip'], "{$this->get_app_name()}_{$name}.log.done", '', $attributes, $attributes['root_user'], 2 );

		switch ( $return ) {
			case 102:
				// file was not found so return an appropriate message.
				return __( 'The operation seems to be complete but we were unable to find the final log file that we were looking for on the server. You should try this operaration again and contact our tech support team if this continues to occur.', 'wpcd' );
				break;
			case 101:
				// unable to login.
				return __( 'Unable to login to the server to download the final set of log files. This is usually a temporary issue and we will retry the operation in a bit. Please just wait a little longer...', 'wpcd' );
				break;
			default:
				return $return;
				break;
		}

	}

	/**
	 * Gets the .logs.intermed.
	 *
	 * @param string $logs logs.
	 * @param int    $id id.
	 * @param string $name name.
	 */
	public function get_logs_intermed( $logs, $id, $name ) {
		$attributes = $this->get_ssh_attributes( $id );

		// Make sure the $attributes var is valid otherwise return false.
		if ( is_wp_error( $attributes ) || empty( $attributes ) ) {
			return false;
		}

		$return     = $this->ssh()->download( $attributes['ip'], "{$this->get_app_name()}_{$name}.log.intermed", '', $attributes, $attributes['root_user'], 2 );

		switch ( $return ) {
			case 102:
				// file was not found so try finding a done log...
				$return = $return = $this->ssh()->download( $attributes['ip'], "{$this->get_app_name()}_{$name}.log.done", '', $attributes, $attributes['root_user'], 2 );
				switch ( $return ) {
					case 102:
						return __( 'We were unable to find an intermediate log file that we were looking for. We tried to find the final one as well but could not do so. This is usually a temporary issue and we will retry the operation in a bit. Please just wait a little longer...', 'wpcd' );
						break;
					case 101:
						// unable to login.
						return __( 'Unable to login to the server to download intermediate log files. This is usually a temporary issue and we will retry the operation in a bit. Please just wait a little longer...', 'wpcd' );
						break;
					default:
						$return .= __( 'It appears that this command has been completed but the WordPress server has NOT yet finished notifications to all related components. This is usually a temporary issue.  Or it could be a permanent firewall issue that blocks us from receiving notifications from the WordPress server.  Please wait a bit to see if the process completes properly - when it does this message will be removed and you will see the log results of the command.', 'wpcd' );
						return $return;
						break;
				}
			case 101:
				// unable to login.
				return __( 'Unable to login to the server to download intermediate log files. This is usually a temporary issue and we will retry the operation in a bit. Please just wait a little longer...', 'wpcd' );
				break;
			default:
				return $return;
				break;
		}
	}

	/**
	 * Perform all deferred or background actions for a server.
	 *
	 * It searches for all 'wpcd_app_server' CPTs that have "wpcd_server_{$this->get_app_name()}_action_status" as 'in-progress'
	 * and calls the action wpcd_server_{$this->get_app_name()}_action on each post.
	 *
	 * Each server might have a task running on it that needs to be called.  This is specified in the
	 * meta wpcd_server_{$this->get_app_name()}_action.
	 * After processing each server's action that way, it will then call the PENDING TASKS function
	 * to see if any new processes can be started on servers that don't already have tasks running on them.
	 */
	public function do_deferred_actions_for_server() {

		// server actions.
		$posts = get_posts(
			array(
				'post_type'   => 'wpcd_app_server',
				'post_status' => 'private',
				'numberposts' => -1,
				'meta_query'  => array(
					array(
						'key'   => "wpcd_server_{$this->get_app_name()}_action_status",
						'value' => 'in-progress',
					),
				),
				'fields'      => 'ids',
			)
		);

		if ( $posts ) {
			foreach ( $posts as $id ) {
				$action = get_post_meta( $id, "wpcd_server_{$this->get_app_name()}_action", true );
				do_action( 'wpcd_log_error', "calling deferred SERVER action $action for $id", 'debug', __FILE__, __LINE__ );
				do_action( "wpcd_server_{$this->get_app_name()}_action", $id, $action );
			}
		}

		// See if other background tasks for servers need to be started.
		WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks();

		set_transient( 'wpcd_do_deferred_actions_for_server_is_active', 1, wpcd_get_long_running_command_timeout() * MINUTE_IN_SECONDS );
	}


	/**
	 * Perform all actions that need a polling mechanism.
	 *
	 * It searches for all 'wpcd_app' CPTs that have "wpcd_app_{$this->get_app_name()}_action_status" as 'in-progress'
	 * and calls the action wpcd_app_{$this->get_app_name()}_action on each post.
	 */
	public function do_deferred_actions_for_app() {
		// app actions.
		$posts = get_posts(
			array(
				'post_type'   => 'wpcd_app',
				'post_status' => 'private',
				'numberposts' => -1,
				'meta_query'  => array(
					array(
						'key'   => "wpcd_app_{$this->get_app_name()}_action_status",
						'value' => 'in-progress',
					),
				),
				'fields'      => 'ids',
			)
		);

		if ( $posts ) {
			foreach ( $posts as $id ) {
				$action = get_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action", true );
				do_action( 'wpcd_log_error', "calling repeated action $action for $id", 'debug', __FILE__, __LINE__ );
				do_action( "wpcd_app_{$this->get_app_name()}_action", $id, $action );
			}
		}

		set_transient( 'wpcd_do_deferred_actions_for_app_is_active', 1, wpcd_get_long_running_command_timeout() * MINUTE_IN_SECONDS );
	}

	/**
	 * Performs an action on an app instance.
	 *
	 * @param int    $app_post_id Post ID.
	 * @param string $action Action to perform.
	 * @param array  $additional Additional data that may be required for the action.
	 *
	 * @return mixed
	 */
	public function do_app_action( $app_post_id, $action, $additional = array() ) {

		/* Check that we're on the right post type */
		if ( get_post_type( $app_post_id ) !== 'wpcd_app' || empty( $action ) ) {
			return;
		}

		/* Check to make sure that we're in the right app !*/
		if ( $this->get_app_name() <> get_post_meta( $app_post_id, 'app_type', true ) ) {
			return;
		}

		// Get the server data related to this app.
		$attributes  = null;
		$server_post = $this->get_server_by_app_id( $app_post_id );
		if ( $server_post ) {
			/* Get data from server post */
			$all_meta = get_post_meta( $server_post->ID );

			foreach ( $all_meta as $key => $value ) {
				if ( strpos( $key, 'wpcd_server_' ) === 0 ) {
					$value = wpcd_maybe_unserialize( $value );
					$attributes[ str_replace( 'wpcd_server_', '', $key ) ] = is_array( $value ) && count( $value ) === 1 ? $value[0] : $value;
				}
			}

			$attributes = array(
				'post_id' => $server_post->ID,
			);
		}

		/* Add some ids to our new attributes array */
		$attributes = array(
			'app_post_id' => $app_post_id,
		);

		/* What's the status of our app? */
		$current_status = get_post_meta( $app_post_id, "wpcd_app_{$this->get_app_name()}_action_status", true );
		if ( empty( $current_status ) ) {
			$current_status = '';
		}

		/* Log some stuff and clean up errors */
		do_action( 'wpcd_log_error', "performing $action for $app_post_id on $current_status with " . print_r( $attributes, true ) . ', additional ' . print_r( $additional, true ), 'debug', __FILE__, __LINE__ );
		delete_post_meta( $app_post_id, 'wpcd_app_error' );
		$this->add_deferred_action_history( $app_post_id, $this->get_app_name(), 'wpcd_app_last_deferred_action_source' );

		/** Commenting this code out for performance reasons - shouldn't be needed for what we are using this function for currently
			$details = WPCD()->get_provider_api($attributes['provider'])->call( 'details', array( 'id' => $attributes['provider_instance_id'] ) );
			// problem fetching details. Maybe instance was deleted?
			// except for delete, bail!
			if ( is_wp_error( $details ) && 'delete' !== $action ) {
				do_action( 'wpcd_log_error', "Unable to find instance on " . $attributes['provider'] . ". Aborting action $action.", "warn", __FILE__, __LINE__ );
				return $details;
			}

			// Merge post ids and server details into a single array
			$attributes = array_merge( $attributes, $details );
		*/

		/* Finally, handle the action */
		switch ( $action ) {
			case 'fetch-logs-from-server':
				$args = get_post_meta( $app_post_id, "wpcd_app_{$this->get_app_name()}_action_args", true );
				$name = $args['command'];
				if ( $this->is_command_done( $app_post_id, $name ) ) {
					// this should never happen!
					do_action( 'wpcd_log_error', "Expecting to fetch logs for $name for $app_post_id after it has finished?  This seemed to have failed", 'error', __FILE__, __LINE__ );
					return;
				}
				$logs = $this->get_command_logs( $app_post_id, $name );
				break;
			default:
				do_action( "wpcd_app_{$this->get_app_name()}_action_{$action}", $app_post_id, $additional );
		}
	}


	/**
	 * Performs an action on a server instance.
	 *
	 * This is closely related to the do_instance_frontend_action_request function.
	 * So changes here should require an examination of that function as well.
	 *
	 * @param int    $server_post_id Post ID.
	 * @param string $action Action to perform.
	 * @param array  $additional Additional data that may be required for the action.
	 *
	 * @return mixed
	 */
	public function do_instance_action( $server_post_id, $action, $additional = array() ) {
		if ( get_post_type( $server_post_id ) !== 'wpcd_app_server' || empty( $action ) ) {
			return;
		}

		$attributes = array(
			'post_id' => $server_post_id,
		);

		/**
		 * Get list of custom fields for the server.
		 */

		/* @TODO: We're running this through a filter and requesting a specific location but not sure that is the right thing to do. */
		$custom_fields = apply_filters( "wpcd_{$this->get_app_name()}_create_wp_server_parms_custom_fields", WPCD_CUSTOM_FIELDS()->get_fields_for_location( 'wordpress-app-new-server-popup' ), $attributes, $attributes );

		/* Get data from server post */
		$all_meta = get_post_meta( $server_post_id );
		foreach ( $all_meta as $key => $value ) {
			if ( 'wpcd_server_app_post_id' == $key ) {
				continue;  // this key, if present, should not be added to the array since it shouldn't even be in the server cpt in the first place. But it might get there accidentally on certain operations.
			}

			// Any field that starts with "wpcd_server_" goes into the array.
			if ( strpos( $key, 'wpcd_server_' ) === 0 ) {
				$value = wpcd_maybe_unserialize( $value );
				$attributes[ str_replace( 'wpcd_server_', '', $key ) ] = is_array( $value ) && count( $value ) === 1 ? $value[0] : $value;
			}

			// Any custom field with data goes into the array regardless of it's name prefix.
			if ( isset( $custom_fields[ $key ] ) ) {
				$attributes[ $key ] = is_array( $value ) && count( $value ) === 1 ? $value[0] : $value;
			}
		}

		$current_status = get_post_meta( $server_post_id, "wpcd_server_{$this->get_app_name()}_action_status", true );
		if ( empty( $current_status ) ) {
			$current_status = '';
		}

		do_action( 'wpcd_log_error', "performing $action for $server_post_id on $current_status with " . print_r( $attributes, true ) . ', additional ' . print_r( $additional, true ), 'debug', __FILE__, __LINE__ );

		delete_post_meta( $server_post_id, 'wpcd_server_error' );
		WPCD_SERVER()->add_deferred_action_history( $server_post_id, $this->get_app_name() );

		$details = WPCD()->get_provider_api( $attributes['provider'] )->call( 'details', array( 'id' => $attributes['provider_instance_id'] ) );
		// problem fetching details. Maybe instance was deleted?
		// except for delete, bail!
		if ( is_wp_error( $details ) && 'delete' !== $action ) {
			do_action( 'wpcd_log_error', 'Unable to find instance on ' . $attributes['provider'] . ". Aborting action $action.", 'warn', __FILE__, __LINE__ );
			return $details;
		}

		// Merge post ids and server details into a single array.
		$attributes = array_merge( $attributes, $details );

		$result = true;
		switch ( $action ) {

			case 'after-server-create-commands':
				$state = $details['status'];
				// run commands only when the server is 'active' and the command is not currently running.
				if ( 'active' === $state ) {
					/* Action hook if this is the first time in here - wpcd_server_wordpress-app_server_created */
					if ( get_post_meta( $attributes['post_id'], "wpcd_server_{$this->get_app_name()}_server_created_action_hook_fired", true ) <> 1 ) {
						update_post_meta( $attributes['post_id'], "wpcd_server_{$this->get_app_name()}_server_created_action_hook_fired", 1 );
						do_action( "wpcd_server_{$this->get_app_name()}_server_created", $attributes );
					}

					/* Check if after-server-create-commands are finished or if they need to be started */
					if ( $this->is_command_done( $attributes['post_id'], 'prepare_server' ) ) {
						// We used to use this area to schedule emails to be sent but no longer.
						// Emails will be now sent from action hooks after commands are complete.
						// For now we do nothing here.
						// update_post_meta( $attributes['post_id'], "wpcd_server_{$this->get_app_name()}_action", 'email' );
						// WPCD_SERVER()->add_deferred_action_history( $attributes['post_id'], $this->get_app_name() );.
					} elseif ( ! $this->is_command_running( $attributes['post_id'], 'prepare_server' ) ) {
						$this->run_after_server_create_commands( $attributes );
					}
				}
				break;
				
			case 'fetch-logs-from-server':
				$args = get_post_meta( $server_post_id, "wpcd_server_{$this->get_app_name()}_action_args", true );
				$name = $args['command'];
				if ( $this->is_command_done( $server_post_id, $name ) ) {
					// this should never happen!
					do_action( 'wpcd_log_error', "Expecting to fetch logs for $name for $server_post_id after it has finished?  This seemed to have failed", 'error', __FILE__, __LINE__ );
					return;
				}
				$logs = $this->get_command_logs( $server_post_id, $name );
				break;				

			case 'email':
				// *** NOT USED ***.
				$state = $details['status'];
				// send email only when 'active'.
				if ( 'active' === $state ) {
					// Deleting these three items means that sending this email is the last thing in the deferred action sequence and no more deferred actions will occur for this server.
					delete_post_meta( $attributes['post_id'], "wpcd_server_{$this->get_app_name()}_action_status" );
					delete_post_meta( $attributes['post_id'], "wpcd_server_{$this->get_app_name()}_action" );
					delete_post_meta( $attributes['post_id'], 'wpcd_server_init', '1' );
					// Send the email.
					$this->send_email( $attributes );
					WPCD_SERVER()->add_deferred_action_history( $attributes['post_id'], $this->get_app_name() );
				}
				break;

			case 'install-wordpress':
				$state = $details['status'];
				if ( 'active' === $state ) {
					if ( ! $this->install_wp( $attributes ) ) {
						// something went wrong. what should we do here?
					}
				}
				break;

			case 'reboot':
				$result = WPCD_SERVER()->reboot_server( $attributes );
				break;

			case 'off':
				$result = WPCD_SERVER()->turn_off_server( $attributes );
				break;

			case 'on':
				$result = WPCD_SERVER()->turn_on_server( $attributes );
				break;

			case 'delete':
				$result = WPCD_SERVER()->delete_server( $attributes );
		}

	}

	/**
	 * Add tasks to pending log for:
	 *  - Installing Callbacks
	 *  - Setup standard backups
	 *  - Setup critical files backups (Local server configuration backups)
	 *  - Run services status
	 *  - Delete protect servers
	 * 
	 *
	 * Action hook: wpcd_command_{$this->get_app_name()}_{$base_command}_{$status} || wpcd_wordpress-app_prepare_server_completed
	 *
	 * @param int    $server_id      The post id of the server record.
	 * @param string $command_name   The full name of the command that triggered this action.
	 */
	public function wpcd_wpapp_prepare_server_completed( $server_id, $command_name ) {

		$server_post = get_post( $server_id );

		// Bail if not a post object.
		if ( ! $server_post || is_wp_error( $server_post ) ) {
			return;
		}

		// Bail if not a WordPress app.
		if ( 'wordpress-app' <> WPCD_WORDPRESS_APP()->get_server_type( $server_id ) ) {
			return;
		}

		// Get server instance array.
		$instance = WPCD_WORDPRESS_APP()->get_instance_details( $server_id );

		if ( 'wpcd_app_server' === get_post_type( $server_id ) ) {

			// If the app install was done via a background pending tasks process then get that pending task post data here.
			// We do that by checking the pending tasks table for a record where the key=domain and type='rest_api_install_wp' and state='in-process'.
			$pending_task_posts = WPCD_POSTS_PENDING_TASKS_LOG()->get_tasks_by_key_state_type( $server_id, 'in-process', 'rest_api_install_server' );
			if ( $pending_task_posts ) {
				/* Now update the log entry to market is as complete. */
				$data_to_save = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $pending_task_posts[0]->ID );
				WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $pending_task_posts[0]->ID, $data_to_save, 'complete' );
			}
		}

	}	


}
