<?php
/**
 * This class handles server specific actions such as
 * creation, destroying, moving and so on.
 *
 * @TODO:  Right now it depends on an instance of the VPN APP
 * to do certain things.  We need to divorce that out.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_Server
 */
class WPCD_Server extends WPCD_Base {

	/**
	 * WPCD_Server constructer.
	 */
	public function __construct() {

		// setup WordPress hooks.
		$this->hooks();

	}

	/**
	 * Hook into WordPress and other plugins as needed.
	 */
	private function hooks() {

		// This action hook might actually be unused - not sure where it's being called from right now.
		add_action( 'wpcd_app_server_action', array( &$this, 'do_instance_action' ), 10, 3 );  // Invoke a server action via an action call.

		add_action( 'wp_trash_post', array( &$this, 'trash_server_post' ), 10, 1 );  // Attempt to delete server at the provider when server post is deleted.

	}

	/**
	 * Performs an action on a server instance.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $action Action to perform.
	 * @param array  $additional Additional data that may be required for the action.
	 *
	 * @return mixed
	 *
	 * @TODO: check to see if this is being called anywhere.  Its a duplicate
	 * of the create_server() function below.
	 * It might be getting called via an action hook somehow.
	 * If not it can be removed.
	 */
	public function do_instance_action( $post_id, $action, $additional = array() ) {

		// Create a server...
		// For now it will unfortunately install a VPN as well because server creation is not yet decoupled from
		// app creation...
		if ( is_null( $post_id ) && 'create' === $action ) {
			$instance = WPCD()->get_provider_api( $additional['provider'] )->call( 'create', $additional );
			$instance = array_merge( $additional, $instance );
			$this->update_instance( $instance );
			return;
		}

	}

	/**
	 * Performs an action on a server instance.
	 *
	 * @param string $action Action to perform.
	 * @param array  $additional Additional data that may be required for the action.
	 *
	 * @return mixed $instance An array representing the instance of the server just spun up.
	 * The returned array is a merge of the $additional array and the data generated from
	 * the server creation call on the provider.
	 *
	 * @TODO: The $action parameter is redundant and should be removed.
	 * For now all calls to this will set it to 'create'.
	 */
	public function create_server( $action, $additional = array() ) {

		// Create a server...
		if ( 'create' === $action ) {

			// Create the instance via the api.
			$instance = WPCD()->get_provider_api( $additional['provider'] )->call( 'create', $additional );

			// Make sure it's not a wp_error.
			if ( is_wp_error( $instance ) ) {
				return $instance;
			}

			// We should have a valid instance array here.
			if ( $instance ) {
				$instance = array_merge( $additional, $instance );

				// Allow apps to hook in and do things.
				$instance = apply_filters( 'wpcd_after_server_create', $instance );

				// Add a record of the instance to the appropriate custom post type.
				$post_id = $this->create_server_post( $instance );

				// Add the post id to the instance array so it can be returned...
				$instance['post_id'] = $post_id;

				// Allow apps to hook in again.
				$instance = apply_filters( 'wpcd_after_server_post_create', $instance );

				// return a reference to the instance.
				return $instance;
			}
		}

		return false;

	}

	/**
	 * Creates the record for the server in the custom post type...
	 *
	 * @param array|object $instance An object representing the instance of the server just spun up.
	 *
	 * @return mixed
	 */
	public function create_server_post( $instance ) {

		/* variable to hold post author */
		$post_author = 0;

		/**
		 * If 'wc_user_id' is set in the instance use it as the post author.
		 */
		if ( ( isset( $instance['wc_user_id'] ) ) ) {
			$post_author = $instance['wc_user_id'];
		}

		/**
		 * If we still don't have a post author, then check to see if a 'user_id' element is set and use that.
		 */
		if ( empty( $post_author ) ) {
			if ( isset( $instance['user_id'] ) ) {
				$post_author = $instance['user_id'];
			}
		}

		/**
		 * Still don't have a post author?  Set to current user
		 */
		if ( empty( $post_author ) ) {
			$post_author = get_current_user_id();
		}

		/* Insert the post */
		$post_id = wp_insert_post(
			array(
				'ID'          => array_key_exists( 'post_id', $instance ) ? $instance['post_id'] : '',
				'post_type'   => 'wpcd_app_server',
				'post_status' => 'private',
				'post_title'  => $instance['name'],
				'post_author' => isset( $post_author ) ? $post_author : 1,
			)
		);

		/* If we have a valid post id, update some metas on the server record */
		if ( ( ! empty( $post_id ) ) && ( ! is_wp_error( $post_id ) ) ) {
			// Set plugin version so we know what plugin version created the server.
			update_post_meta( $post_id, 'wpcd_server_plugin_initial_version', wpcd_version );
			update_post_meta( $post_id, 'wpcd_server_plugin_updated_version', wpcd_version );
		}

		do_action( 'wpcd_log_error', "Created CPT with ID $post_id " . print_r( $instance, true ), 'debug', __FILE__, __LINE__ );

		return $post_id;

	}

	/**
	 * Turn on the server
	 *
	 * @param array $attributes Attributes of the server instance.
	 *
	 * @return mixed
	 */
	public function turn_on_server( $attributes ) {
		$action = 'on';
		$result = $this->handle_async_action( $action, $attributes );
		return $result;
	}

	/**
	 * Turn off the server
	 *
	 * @param array $attributes Attributes of the server instance.
	 *
	 * @return mixed
	 */
	public function turn_off_server( $attributes ) {
		$action = 'off';
		$result = $this->handle_async_action( $action, $attributes );
		return $result;
	}

	/**
	 * Delete the server
	 *
	 * @param array $attributes Attributes of the server instance.
	 *
	 * @return mixed
	 */
	public function delete_server( $attributes ) {
		$action   = 'delete';
		$instance = WPCD()->get_provider_api( $attributes['provider'] )->call( $action, $attributes );
		// if the instance is missing from the provider or if the destroy method succeeds.
		if ( is_wp_error( $instance ) || 'done' === $instance['status'] ) {
			wp_trash_post( $attributes['post_id'] );
			wpcd_delete_child_posts( 'wpcd_app', $attributes['post_id'] ); // delete all app child posts associated with the server...
			$this->add_action_to_history( $action, $attributes );
		}
	}

	/**
	 * Restart server
	 *
	 * @param array $attributes Attributes of the server instance.
	 *
	 * @return mixed
	 */
	public function reboot_server( $attributes ) {
		$action = 'reboot';
		$result = $this->handle_async_action( $action, $attributes );
		return $result;
	}

	/**
	 * Reinstall the server (which is a delete and an add)
	 *
	 * @param array $old_attributes Attributes of the server being deleted.
	 * @param array $new_attributes Attributes of the server being added.
	 *
	 * @return array $instance New instance data
	 */
	public function reinstall_server( $old_attributes, $new_attributes ) {
		// Create the new server.
		$instance = $this->create_server( 'create', $new_attributes );

		// Delete the old one...
		$this->delete_server( $old_attributes );

		return $instance;
	}

	/**
	 * Relocate the server (which is a delete and an add)
	 *
	 * @param array $old_attributes Attributes of the server being deleted.
	 * @param array $new_attributes Attributes of the server being added.
	 *
	 * @return array $instance New instance data
	 */
	public function relocate_server( $old_attributes, $new_attributes ) {
		// Create the new server.
		$instance = $this->create_server( 'create', $new_attributes );

		// Delete the old one...
		$this->delete_server( $old_attributes );

		return $instance;
	}

	/**
	 * Delete the server if the admin is deleting the server post
	 * or some other function is attempting to delete the server post.
	 *
	 * Ok, so here is some VERY important information.  This class
	 * already has a delete_server function in it.  If we call it
	 * from this function we will likely end up in an infinite loop.
	 * This is because that delete_server function then goes ahead
	 * and deletes the post record which will trigger this hook
	 * again.  And again. And again.
	 * That delete_server function is intended to be called from
	 * front-end routines.  This one is for back-end wp-admin
	 * routines and will likely duplicate a lot of the delete_server
	 * function code - it would just pulling the data from different
	 * locations. (Yes, duplicate code, I know, YUCK!)
	 *
	 * Action hook: trashed_post
	 *
	 * @param string $post_id the post_id being deleted...
	 *
	 * @return mixed
	 */
	public function trash_server_post( $post_id ) {

		/* Bail out if we're not doing this from inside the wp-admin screen */
		if ( ! is_admin() ) {
			return;
		}

		/* Bail out if not our post type */
		if ( 'wpcd_app_server' != get_post_type( $post_id ) ) {
			return;
		}

		/* Ok, we're good - lets fill out the attributes array that all the provider api calls expect */
		$attributes['post_id']              = $post_id;
		$attributes['provider']             = get_post_meta( $post_id, 'wpcd_server_provider', true );
		$attributes['provider_instance_id'] = get_post_meta( $post_id, 'wpcd_server_provider_instance_id', true );

		/*Run the delete api function call */
		$action = 'delete';
		if ( ! empty( WPCD()->get_provider_api( $attributes['provider'] ) ) ) {
			$instance = WPCD()->get_provider_api( $attributes['provider'] )->call( $action, $attributes );
		}

		// Check results - if the instance is missing from the provider or if the destroy method succeeds.
		if ( is_wp_error( $instance ) || 'done' === $instance['status'] ) {
			wpcd_delete_child_posts( 'wpcd_app', $attributes['post_id'] ); // delete all app child posts associated with the server...
			$this->add_action_to_history( $action, $attributes );
		}

	}

	/**
	 * Update the IP address on the server record
	 *
	 * @param int    $post_id post id of server record.
	 * @param string $ip ip address.
	 *
	 * @return void
	 */
	public function add_ipv4_address( $post_id, $ip ) {
		update_post_meta( $post_id, 'wpcd_server_ipv4', $ip );
	}

	/**
	 * Get the IPv4 address on the server record
	 *
	 * @param int $post_id post id of server record.
	 *
	 * @return string the ipv4 address
	 */
	public function get_ipv4_address( $post_id ) {
		return get_post_meta( $post_id, 'wpcd_server_ipv4', true );
	}

	/**
	 * Get the server os on the server record.
	 *
	 * This is our string, not the providers' string.
	 * If no OS is on the server record, return ubuntu 18.04 LTS as the server.
	 *
	 * @param int $post_id post id of server record.
	 *
	 * @return string the os
	 */
	public function get_server_os( $post_id ) {
		$os = get_post_meta( $post_id, 'wpcd_server_initial_os', true );
		if ( empty( $os ) ) {
			$os = 'ubuntu1804lts';
		}
		return $os;
	}

	/**
	 * Get the server name on the server record
	 *
	 * @TODO: The server name is the post title
	 * or in a post meta field.  Right now it
	 * might be inconsistent which is which.
	 * For now, we're going to return the
	 * post title.
	 *
	 * @param int $post_id post id of server record.
	 *
	 * @return string the server name
	 */
	public function get_server_name( $post_id ) {
		$post = get_post( $post_id );
		if ( $post ) {
			return $post->post_title;
		} else {
			return false;
		}
	}

	/**
	 * Get the server region on the server record
	 *
	 * @param int $post_id post id of server record.
	 *
	 * @return string the server name
	 */
	public function get_server_region( $post_id ) {
		return get_post_meta( $post_id, 'wpcd_server_region', true );
	}

	/**
	 * Get the server provider on the server record
	 *
	 * @param int $post_id post id of server record.
	 *
	 * @return string the server name
	 */
	public function get_server_provider( $post_id ) {
		return get_post_meta( $post_id, 'wpcd_server_provider', true );
	}

	/**
	 * Get the server size on the server record
	 *
	 * @TODO: There is a field called 'wpcd_server_size' on the server record
	 * that needs to be converted to a 'raw' size and returned if it exists
	 * and 'wpcd_server_size_raw' does not exist.
	 *
	 * @param int $post_id post id of server record.
	 *
	 * @return string the server name
	 */
	public function get_server_size( $post_id ) {
		return get_post_meta( $post_id, 'wpcd_server_size_raw', true );
	}
	
	/**
	 * Set the server size on the server record
	 *
	 * @TODO: There is a field called 'wpcd_server_size' on the server record
	 * that needs to be set if it already exists with a value.
	 *
	 * @param int $post_id post id of server record.
	 *
	 * @return string the server name
	 */
	public function set_server_size( $post_id, $new_size ) {
		return update_post_meta( $post_id, 'wpcd_server_size_raw', $new_size );
	}

	/**
	 * Set a pending server size on the server record.
	 * Usually used when resizing a server.
	 *
	 * @param int $post_id post id of server record.
	 *
	 * @return string the server name
	 */
	public function set_pending_server_size( $post_id, $new_size ) {
		return update_post_meta( $post_id, 'wpcd_server_pending_size_raw', $new_size );
	}
	
	/**
	 * Convert the pending server size to the final server size.
	 * Usually used when resizing a server.
	 *
	 * @param int $post_id post id of server record.
	 *
	 * @return string the server name
	 */
	public function finalize_server_size( $post_id ) {
		$return = $this->set_server_size( $post_id, get_post_meta( $post_id, 'wpcd_server_pending_size_raw', true ) );
		delete_post_meta( $post_id, 'wpcd_server_pending_size_raw' );
		return $return;
	}	

	/**
	 * Get the server instance id on the server record
	 *
	 * @param int $post_id post id of server record.
	 *
	 * @return string the server name
	 */
	public function get_server_provider_instance_id( $post_id ) {
		return get_post_meta( $post_id, 'wpcd_server_provider_instance_id', true );
	}
	
	/**
	 * Returns an server ID using the instance of a server.
	 *
	 * @param int    $instance_id  The server instance id used to locate the server post id.
	 *
	 * @return int|boolean app post id or false or error message
	 */
	public function get_server_id_by_instance_id( $instance_id ) {

		$posts = get_posts(
			array(
				'post_type'   => 'wpcd_app_server',
				'post_status' => 'private',
				'numberposts' => -1,
				'meta_query'  => array(
					array(
						'key'   => 'wpcd_server_provider_instance_id',
						'value' => $instance_id,
					),
				),
			),
		);
		
		// Too many posts?  Bail out.
		if ( count( $posts ) <> 1 ) {
			return false;
		}

		if ( $posts ) {
			return $posts[0]->ID;
		} else {
			return false;
		}

	}	

	/**
	 * Get the number of apps on the server
	 *
	 * @param int $post_id post id of server record.
	 *
	 * @return int number of apps on server
	 */
	public function get_app_count( $post_id ) {

		$args = array(
			'post_type'      => 'wpcd_app',
			'post_status'    => 'private',
			'posts_per_page' => 9999,
			'meta_query'     => array(
				array(
					'key'   => 'parent_post_id',
					'value' => $post_id,
				),
			),
		);

		$posts = get_posts( $args );

		$cnt_posts = 0;

		if ( ! empty( $posts ) ) {
			$cnt_posts = count( $posts );
		}

		return $cnt_posts;

	}

	/**
	 * Get the root user login name for the server.
	 *
	 * @param int $id The id of the server post.
	 *
	 * @return string   The root user for the server provider or that is stored on the server post record.
	 */
	public function get_root_user_name( $id ) {

		// Is a root user specified on the server post?  If so, return it.
		$server_root_user = get_post_meta( $id, 'wpcd_server_ssh_root_user', true );
		if ( ! empty( $server_root_user ) ) {
			return $server_root_user;
		}

		// Get the default root user from the provider.
		$provider  = get_post_meta( $id, 'wpcd_server_provider', true );
		$root_user = WPCD()->get_provider_api( $provider )->get_root_user();
		return $root_user;

	}

	/**
	 * Adds action to the history of a server instance.
	 *
	 * @param string $action Action to perform.
	 * @param array  $attributes Attributes of the server instance.
	 *
	 * @return bool
	 */
	public function add_action_to_history( $action, $attributes ) {
		$post_id = $attributes['post_id'];

		// Is this post_id a valid post?.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// Valid post id so grab meta and append to it.
		$history = get_post_meta( $post_id, 'wpcd_server_actions', true );
		if ( ! $history ) {
			$history = array();
		}
		// $history[ $action ] = time();
		$history[ time() ] = $action;  // Note: Starting in WPCD V4.0.0, we switched the array key from action to the time in order to handle duplicate actions.
		update_post_meta( $post_id, 'wpcd_server_actions', $history );
	}

	/**
	 * Perform a multi-step action (that goes through multiple statuses) on a server instance.
	 * Examples are: turn off server, turn on server, reboot server
	 *
	 * @param string $action Action to perform.
	 * @param array  $attributes Attributes of the server instance.
	 *
	 * @return bool
	 */
	public function handle_async_action( $action, $attributes ) {
		$post_id = $attributes['post_id'];

		$current_status = get_post_meta( $post_id, 'wpcd_server_action_status', true );
		if ( empty( $current_status ) ) {
			$current_status = '';  // Make sure this variable is a string, not boolean or integer.
		}

		if ( empty( $current_status ) ) {

			$instance = WPCD()->get_provider_api( $attributes['provider'] )->call( $action, $attributes );

			WPCD_SERVER()->add_action_to_history( $action . ' (pre)', $attributes );

			do_action( 'wpcd_log_error', "Async action $action called with result = " . print_r( $instance, true ), 'debug', __FILE__, __LINE__ );

			if ( is_wp_error( $instance ) ) {
				update_post_meta( $post_id, 'wpcd_server_error', $instance->get_error_message() );
				return false;
			} elseif ( 'in-progress' === $instance['status'] ) {
				update_post_meta( $post_id, 'wpcd_server_current_state', 'performing ' . $action );
				update_post_meta( $post_id, 'wpcd_server_action_status', $instance['status'] );
				update_post_meta( $post_id, 'wpcd_server_action', $action );
				update_post_meta( $post_id, 'wpcd_server_action_id', $instance['action_id'] );
				$this->add_deferred_action_history( $post_id, 'class-wpcd-server' );
			}
		} else {
			$instance = WPCD()->get_provider_api( $attributes['provider'] )->call( 'status', $attributes );

			if ( 'completed' === $instance['status'] ) {
				delete_post_meta( $post_id, 'wpcd_server_current_state' );
				delete_post_meta( $post_id, 'wpcd_server_action_status' );
				delete_post_meta( $post_id, 'wpcd_server_action' );
				delete_post_meta( $post_id, 'wpcd_server_action_id' );
				$this->add_deferred_action_history( $post_id, 'class-wpcd-server' );

				if ( 'off' === $action ) {
					update_post_meta( $post_id, 'wpcd_server_current_state', 'off' );
				}
				WPCD_SERVER()->add_action_to_history( $action, $attributes );

			}
		}
		return true;
	}

	/**
	 * This function checks to see if commands can be run
	 * on the server.
	 *
	 * It does this by checking a variety of metas that
	 * variously indicates something is going on with the server.
	 *
	 * @param int $server_id  Server id to check.
	 *
	 * @return boolean
	 */
	public function is_server_available_for_commands( $server_id ) {

		$current_state = get_post_meta( $server_id, 'wpcd_server_current_state', true );

		// Check state variable first.
		if ( $current_state <> 'active' and ! empty( $current_state ) ) {
			return false;
		}

		$return = true;

		$return = apply_filters( 'wpcd_is_server_available_for_commands', $return, $server_id );

		return $return;

	}

}
