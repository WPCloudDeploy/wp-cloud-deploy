<?php
/**
 * Digital Ocean Parent.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLOUD_PROVIDER_API_DigitalOcean_Parent
 */
class CLOUD_PROVIDER_API_DigitalOcean_Parent extends CLOUD_PROVIDER_API {

	/**
	 * A text string with the url to the base REST API call
	 */
	const _URL = 'https://api.digitalocean.com/v2/';

	/**
	 * The provider's id for the default image used to fire up a server.
	 * If no other logic is provided to choose an image, this will
	 * generally be the one choosen.
	 */
	const _IMAGE = 'ubuntu-20-04-x64';

	/**
	 * A text string with the cloud provider's api key.
	 *
	 * @var $api_key api key.
	 */
	private $api_key;

	/**
	 * CLOUD_PROVIDER_API_DigitalOcean constructor.
	 */
	public function __construct() {

		parent::__construct();

		/* Set provider name and slug */
		$this->set_provider( 'Digital Ocean' );
		$this->set_provider_slug( 'digital-ocean' );

		/* Set baseline provider name and slug - this will not change even on custom servers */
		$this->set_base_provider_name( 'Digital Ocean' );
		$this->set_base_provider_slug( 'digital-ocean' );

		/* Set link to cloud provider's user dashboard */
		$this->set_provider_dashboard_link( 'https://cloud.digitalocean.com/account/api/tokens' );

		/* Set flag to indicate that this provider supports creating ssh keys */
		$this->set_feature_flag( 'ssh_create', true );

		/* Set flag to indicate that this provider supports testing connections to it. */
		$this->set_feature_flag( 'test_connection', true );

		/* Set flag that indicates we will support snapshots */
		$this->set_feature_flag( 'snapshots', true );
		$this->set_feature_flag( 'snapshot-delete', false );  // We can't support this in DigitalOcean because the create snapshot api or subsequent endpoints do not actually return the snapshot ID.
		$this->set_feature_flag( 'snapshot-list', true );

		/* Set flag that indicates we will support provider level backups on server creation */
		$this->set_feature_flag( 'enable_backups_on_server_create', true );

		/* Set flag that indicates we will support provider level dynamic tags on server creation */
		$this->set_feature_flag( 'enable_dynamic_tags_on_server_create', true );

		/* Set the flag that indicates we support resize operations */
		$this->set_feature_flag( 'resize', true );

		/* Set the API key - pulling from settings */
		$this->set_api_key( WPCD()->decrypt( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_apikey' ) ) );

		/* Set filter to add link to the digital ocean api dashboard */
		$provider = $this->get_provider_slug();
		add_filter( "wpcd_cloud_provider_settings_api_key_label_desc_{$provider}", array( $this, 'set_link_to_provider_dashboard' ) );

		/* Run cron to auto start server after resize */
		add_action( 'wpcd_' . $this->get_provider_slug() . '_auto_start_after_resize_cron', array( $this, 'doAutoStartServer' ), 10 );

	}

	/**
	 * Set function for api key.
	 *
	 * @param string $the_key the key.
	 */
	public function set_api_key( $the_key ) {
		$this->api_key = $the_key;
	}

	/**
	 * Getter function for api key
	 */
	public function get_api_key() {
		return $this->api_key;
	}

	/**
	 * Get the os image to be used.
	 *
	 * This function is generally called by
	 * the CALL() function.
	 *
	 * @param array $attributes - attributes of the server being provisioned or used.
	 */
	public function get_image_name( $attributes ) {
		if ( isset( $attributes['initial_os'] ) ) {
			switch ( $attributes['initial_os'] ) {
				case 'ubuntu1804lts':
					return 'ubuntu-18-04-x64';  // no break statement needed after this since we're returning out of the function.
				case 'ubuntu2004lts':
					return 'ubuntu-20-04-x64';  // no break statement needed after this since we're returning out of the function.
				case 'ubuntu2204lts':
					return 'ubuntu-22-04-x64';  // no break statement needed after this since we're returning out of the function.
				default:
					return self::_IMAGE;
			}
		} else {
			return self::_IMAGE;
		}
	}

	/**
	 * Call the API.
	 *
	 * @param string $method The method to invoke.
	 * @param array  $attributes The attributes for the action.
	 *
	 *  The Attributes array can contain the following information:
	 *
	 *  [app_post_id]            / number / eg: 1048 / available on reinstall and relocate
	 *  [initial_app_name]       / string / eg: vpn  / the initial application that was added to the server as it was initially provisioned.
	 *  [region]                 / string / eg: nyc1
	 *  [size]                   / string / eg: small
	 *  [name]                   / string / eg: shana-mcmahon-2020-02-03-163452-1046-1
	 *  [wc_order_id]            / number / eg: 1045
	 *  [wc_subscription]        / serialized array or array / eg: a:1:{i:0;i:1046;}
	 *  [wc_user_id]             / number / eg: 13
	 *  [provider]               / string / eg: digital-ocean
	 *  [provider_instance_id]   / number / eg: 178565950
	 *  [created]                / date / eg:2020-02-03T16:34:53Z
	 *  [actions]                / serialized array / eg: a:2:{s:7:"created";i:1580747693;s:8:"add-user";i:1580748191;}
	 *  [max_clients]            / number / eg: 5
	 *  [dns]                    / number / eg: 1
	 *  [protocol]               / number / eg: 1
	 *  [port]                   / number / eg: 1194
	 *  [client]                 / string / eg:client1
	 *  [parent_post_id]         / number / eg: 1047
	 *  [id]                     / number / eg: 178565950 / added after being passed into this function - see code below
	 *
	 *  Note that not all elements in the array is available for all methods/actions or for all apps.
	 *  For example, dns, protocol, port, client and clients are all available only when passed in via the VPN app.
	 *
	 * @return mixed
	 */
	public function call( $method, $attributes = array() ) {
		// phpcs:ignore
		do_action( 'wpcd_log_error', "executing do request $method with " . print_r( $attributes, true ), 'debug', __FILE__, __LINE__ ); //PHPcs warning normally issued because of print_r

		/* Can the requested data be retrieved from the cache? */
		$cache_key = $this->get_provider_slug() . $this->get_cache_key_prefix() . hash( 'sha256', $this->api_key ) . $method;

		$cache = get_transient( $cache_key );
		if ( false !== $cache ) {
			return $cache;
		}
		/* end pull from cache */

		if ( empty( $this->api_key ) ) {
			return false;
		}

		/* Make sure that our attributes array contains an id element. */
		if ( isset( $attributes['provider_instance_id'] ) && ! isset( $attributes['id'] ) ) {
			$attributes['id'] = $attributes['provider_instance_id'];
		}
		$return   = null;
		$action   = 'GET';
		$body     = '';
		$endpoint = $method;
		$run_cmd  = '';
		switch ( $method ) {
			case 'sizes':
				break;
			case 'keys':
				$endpoint = 'account/keys';
				break;
			case 'regions':
				break;
			case 'create':
				$endpoint             = 'droplets';
				$action               = 'POST';
				$attributes['option'] = '0';
				$image                = $this->get_image_name( $attributes );
				$size                 = '';
				if ( isset( $attributes['size'] ) ) {
					$size = wpcd_get_option( 'vpn_' . $this->get_provider_slug() . '_' . $attributes['size'] );
				}
				// size_raw is the raw size as expected by the provider.
				if ( empty( $size ) && isset( $attributes['size_raw'] ) ) {
					$size = $attributes['size_raw'];
				}
				$region = $attributes['region'];
				$name   = $attributes['name'];

				$backups = (bool) wpcd_get_option( 'vpn_' . $this->get_provider_slug() . '_enable_provider_backups_on_server_create' );
				$backups = empty( $backups ) ? 'false' : 'true';

				$tags = wpcd_get_option( 'vpn_' . $this->get_provider_slug() . '_tags_on_server_create' );
				if ( empty( $tags ) ) {
					$tags = 'wpcd';
				}

				/* Get run commands - these are automatically executed upon server creation */
				$run_cmd = apply_filters( 'wpcd_cloud_provider_run_cmd', $run_cmd, $attributes );  // Someone else needs to tell us what to run upon server start up otherwise only a basic server install will be done.
				$run_cmd = apply_filters( 'wpcd_cloud_provider_run_cmd_' . $this->get_provider_slug(), $run_cmd, $attributes ); // just in case running a command on startup is dependent on the provider.

				/* The body of the data being submitted to the cloud provider to create a server */
				$body = '{
"name": "' . $name . '",
"region": "' . $region . '",
"size": "' . $size . '",
"image": "' . $image . '",
"ssh_keys": [' . wpcd_get_option( 'vpn_' . $this->get_provider_slug() . '_sshkey_id' ) . '],
"backups":"' . $backups . '",
"tags": "' . $tags . '",
"ipv6": true,
"monitoring": true,
"user_data": "
#cloud-config
runcmd:
' . $run_cmd . '
  "
		}';
				break;
			case 'snapshot':
				$endpoint = 'droplets/' . $attributes['id'] . '/actions';
				$action   = 'POST';
				$name     = sprintf( 'WPCD_%d_%s', $attributes['id'], get_gmt_from_date( '' ) );
				$body     = '{
"type": "snapshot",
"name": "' . $name . '"
}';
				break;
			case 'delete_snapshot':
				/**
				 * Not used because DO doesn't give us the snapshot ID
				 * after a snapshot operation.
				 * Without the ID, we can't delete anything.
				 */
				$endpoint = 'snapshots/' . $attributes['snapshot_id'];
				$action   = 'DELETE';
				break;
			case 'pending_snapshot_status':
				/**
				 * When a snapshot is requested, DO does not return the id right away.
				 * So need to query the ACTIONS endpoint to get the final id.
				 * Or so we thought.
				 * It turns out DO doesn't provide the final snapshot ID via the API at all!
				 * That makes this call useless but we're keeping it in here anyway for
				 * potential future use since we already wrote the code.
				 */
				$endpoint = 'actions/' . $attributes['pending_snapshot_id'];
				break;
			case 'list_all_snapshots':
				$endpoint = 'snapshots' . '?page=1&per_page=9999';
				break;
			case 'reboot':
				$endpoint = 'droplets/' . $attributes['id'] . '/actions';
				$action   = 'POST';
				$body     = '{
					"type": "reboot"
				}';
				break;
			case 'off':
				$endpoint = 'droplets/' . $attributes['id'] . '/actions';
				$action   = 'POST';
				$body     = '{
					"type": "power_off"
				}';
				break;
			case 'on':
				$endpoint = 'droplets/' . $attributes['id'] . '/actions';
				$action   = 'POST';
				$body     = '{
					"type": "power_on"
				}';
				break;
			case 'status':
				$endpoint = 'droplets/' . $attributes['id'] . '/actions/' . $attributes['action_id'];
				break;
			case 'delete':
				$endpoint = 'droplets/' . $attributes['id'];
				$action   = 'DELETE';
				break;
			case 'details':
				$endpoint = 'droplets/' . $attributes['id'];
				break;
			case 'action':
				/* The action endpoint is used to get the result of an operation that usually takes a long time.  We are not using this right now. */
				$endpoint = 'actions/' . $attributes['action_id'];
				break;
			case 'resize':
				$endpoint = 'droplets/' . $attributes['id'] . '/actions';
				$action   = 'POST';
				$body     = '{
					"type": "resize", "size":"' . $attributes['new_size'] . '"
				}';
				break;
			case 'ssh_create':
				$endpoint       = 'account/keys';
				$action         = 'POST';
				$ssh_key_name   = $attributes['public_key_name'];
				$new_public_key = $attributes['public_key'];
				$body           = '{
					"public_key": "' . $new_public_key . '", 
					"name": "' . $ssh_key_name . '"
				}';
				break;
			case 'test_connection':
				$endpoint = 'regions'; // If we can get a set of regions we've probably got a good connection.
				break;
			default:
				return new WP_Error( 'not supported' );
		}

		/* Execute the method */
		$response = wp_remote_request(
			self::_URL . $endpoint,
			array(
				'method'  => $action,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				),
				'body'    => $body,
			)
		);

		// phpcs:ignore
		do_action( 'wpcd_log_error', "$method with " . print_r( $attributes, true ) . " with $body", 'debug', __FILE__, __LINE__ ); //PHPcs warning normally issued because of print_r

		/* Check for errors after execution */
		if ( is_wp_error( $response ) || ( ! in_array( intval( $response['response']['code'] ), array( 200, 201, 202, 204, 302, 301 ) ) ) ) {
			// phpcs:ignore
			do_action( 'wpcd_log_error', "$method with " . print_r( $attributes, true ) . ' gives error response = ' . print_r( $response, true ), 'error', __FILE__, __LINE__ ); //PHPcs warning normally issued because of print_r

			$body = wp_remote_retrieve_body( $response );
			$body = json_decode( $body );
			if ( is_object( $body ) ) {
				return new \WP_Error( $body->id, $body->message );
			} else {
				return new \WP_Error( 'error', $body );
			}
		}

		/* If no execution errors, get body of response */
		$body   = wp_remote_retrieve_body( $response );
		$body   = json_decode( $body );
		$return = array();

		/* Return different data depending on the method / action executed */
		switch ( $method ) {
			case 'sizes':
				foreach ( $body->sizes as $size ) {
					if ( 1 === (int) $size->available ) {
						/* translators: %1$s is the digital ocean slug/plan description. Hopefully the remaining replacements are self-explanatory - they all refer to various aspects of the digital ocean plan. */
						$return[ $size->slug ] = sprintf( __( '%1$s (%2$d CPUs, %3$d MB RAM, %4$d GB SSD, $%5$d per month USD, %6$d TB data transfer/month)', 'wpcd' ), $size->slug, $size->vcpus, $size->memory, $size->disk, $size->price_monthly, $size->transfer );
					}
				}
				break;
			case 'keys':
				foreach ( $body->ssh_keys as $key ) {
					$return[ $key->id ] = $key->name;
				}
				break;
			case 'regions':
				foreach ( $body->regions as $region ) {
					if ( $region->available ) {
						if ( ! empty( $this->get_region_prefix() ) ) {
							$return[ $region->slug ] = $this->get_region_prefix() . ': ' . $region->name;
						} else {
							$return[ $region->slug ] = $region->name;
						}
					}
				}
				break;
			case 'create':
				$return['provider_instance_id'] = $body->droplet->id;
				$return['name']                 = $body->droplet->name;
				$return['created']              = $body->droplet->created_at;
				$return['actions']              = array( 'created' => time() );
				$return['run_cmd']              = $run_cmd;
				break;
			case 'snapshot':
				if ( ! empty( $body->action->id ) ) {
					$return['id']               = $attributes['id'];
					$return['snapshot-id']      = $body->action->id;   // Unfortunately, this is NOT the id of the droplet.  Might be the ID of a background process for it and we'll have to use it to query later?
					$return['snapshot-id-type'] = 'intermediate';  // Two possible values - 'final' if this is the final id for the snapshot or 'intermediate' if we have to wait for the snapshot to finish and then use this id to get the final snapshot id.
					$return['provider_status']  = $body->action->status;
					$return['status']           = 'success';
				} else {
					$return['status'] = 'fail';
				}
				break;
			case 'delete_snapshot':
				/* Not used. */
				if ( ! empty( $body->id ) ) {
					$return['status'] = 'success';
				} else {
					$return['status'] = 'fail';
				}
				break;
			case 'pending_snapshot_status':
				/* Not used. */
				if ( ! empty( $body->action ) ) {
					if ( 'completed' === $body->action->status ) {
						$return['status'] = 'complete';
					} else {
						$return['status'] = $body->action->status;
					}
					$return['snapshot_id'] = $body->action->id;  // This should be the snapshot id but its NOT because DO doesn't actually give it to you anywhere making this action completely useless.
				} else {
					$return['status'] = 'fail';
				}

				break;
			case 'list_all_snapshots':
				if ( ! empty( $body->snapshots ) ) {
					$snapshot_count = 0;
					foreach ( $body->snapshots as $snapshot ) {
						$return_snapshot['id']            = $snapshot->id;
						$return_snapshot['name']          = $snapshot->name;
						$return_snapshot['resource_id']   = $snapshot->resource_id; // The droplet id to which this belongs.
						$return_snapshot['resource_size'] = $snapshot->size_gigabytes;
						$return_snapshot['resource_type'] = $snapshot->resource_type; // This might be unique to digital-ocean.
						$return_snapshot['tags']          = $snapshot->tags;
						$return['snapshots'][]            = $return_snapshot;

						$snapshot_count++;
					}
					$return['snapshot_count'] = $snapshot_count;
				} else {
					$return['status'] = 'fail';
				}
				break;
			case 'reboot':
			case 'off':
			case 'on':
				// "in-progress", "completed", or "errored".
				$return['status']    = $body->action->status;
				$return['action_id'] = $body->action->id;
				break;
			case 'resize':
				$return['status']    = $body->action->status;
				$return['action_id'] = $body->action->id;

				$this->cacheAutoStartServer( $attributes['id'], $body->action->id );
				wp_clear_scheduled_hook( 'wpcd_' . $this->get_provider_slug() . '_auto_start_after_resize_cron' );
				wp_schedule_event( time(), 'every_minute', 'wpcd_' . $this->get_provider_slug() . '_auto_start_after_resize_cron' );

				break;
			case 'status':
				$return['status'] = $body->action->status;
				$return['ip']     = $body->action->status;
				break;
			case 'delete':
				$return['status'] = 'done';
				break;
			case 'details':
				$return['os']     = sprintf( '%s %s', $body->droplet->image->distribution, $body->droplet->image->name );
				$return['ip']     = $this->get_ipv4_from_body( $body );
				$return['ipv6']   = $this->get_ipv6_from_body( $body );
				$return['name']   = $body->droplet->name;
				$return['status'] = $body->droplet->status;
				break;
			case 'ssh_create':
				$return['ssh_key_id'] = $body->ssh_key->id;
				break;
			case 'test_connection':
				if ( ! empty( $body->regions ) ) {
					$return['test_status'] = true;
				} else {
					$return['test_status'] = false;
				}
				break;
			case 'action':
				/* We are not using this endpoint right now. */
				break;
		}

		/* Cache some things if necessary */
		$this->store_cache( $cache_key, $return, $method );

		/* Return data if all good - if errors, we've already exited this function way above here. */
		return $return;

	}

	/**
	 * Extract the IPv4 address from the array that DO returns.
	 *
	 * @param object $body Digital Ocean response object to a request for server details.
	 *
	 * @return: string IPv4
	 */
	public function get_ipv4_from_body( $body ) {

		if ( 'public' == $return['ip'] = $body->droplet->networks->v4[0]->type ) {
			return $body->droplet->networks->v4[0]->ip_address;
		}

		if ( 'public' == $return['ip'] = $body->droplet->networks->v4[1]->type ) {
			return $body->droplet->networks->v4[1]->ip_address;
		}

		return 'error-no-ip-found';

	}

	/**
	 * Extract the IPv6 address from the array that DO returns.
	 *
	 * @param object $body Digital Ocean response object to a request for server details.
	 *
	 * @return: string IPv6 if it exists, error string otherwise.
	 */
	public function get_ipv6_from_body( $body ) {

		if ( ! empty( $body->droplet->networks->v6[0] ) ) {
			return $body->droplet->networks->v6[0]->ip_address;
		}

		return 'error-no-ipv6-found';

	}

	/**
	 * Return servers for auto start
	 *
	 * @return array
	 */
	public function getAutoStartServers() {
		return get_option( 'wpcd_' . $this->get_provider_slug() . '_auto_start_servers_cron', array() );
	}

	/**
	 * Add server to auto start cache list
	 *
	 * @param string $server_id  The server id.
	 * @param string $action_id  The action id.
	 *
	 * @return void
	 */
	public function cacheAutoStartServer( $server_id, $action_id ) {
		$all_servers               = $this->getAutoStartServers();
		$all_servers[ $server_id ] = $action_id;
		update_option( 'wpcd_' . $this->get_provider_slug() . '_auto_start_servers_cron', $all_servers );
	}


	/**
	 * Cron action to restart servers after resize
	 *
	 * @return void
	 */
	public function doAutoStartServer() {

		// Returns the option that holds an array of servers that need to be restarted for this provider.
		$all_servers = $this->getAutoStartServers();

		// Bail if no servers are in the array.
		if ( empty( $all_servers ) ) {
			wp_clear_scheduled_hook( 'wpcd_' . $this->get_provider_slug() . '_auto_start_after_resize_cron' );
			return;
		}

		// Loop through the array of servers, get their operation status and restart if the operation is complete.
		// Note that the $server_id variable here is the providers' INSTANCE ID, not the post id for the server!
		foreach ( $all_servers as $server_id => $action_id ) {

			$endpoint = 'droplets/' . $server_id . '/actions?page=1&per_page=20';

			$response = wp_remote_request(
				self::_URL . $endpoint,
				array(
					'method'  => 'GET',
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $this->api_key,
					),
				)
			);

			$body = wp_remote_retrieve_body( $response );
			$body = json_decode( $body );

			foreach ( $body->actions as $action ) {

				if ( $action->id == $action_id ) {
					if ( 'completed' === $action->status || 'errored' === $action->status ) {

						// Update server record with new size.
						if ( 'completed' === $action->status ) {
							$server_post_id = WPCD_SERVER()->get_server_id_by_instance_id( $server_id );
							WPCD_SERVER()->finalize_server_size( $server_post_id );
						}

						// restart the server.
						$this->call( 'on', array( 'id' => $server_id ) );

						// remove server from restart array.
						unset( $all_servers[ $server_id ] );
					}

					break;

				}
			}
		}

		// Update option with new list of servers.
		update_option( 'wpcd_' . $this->get_provider_slug() . '_auto_start_servers_cron', $all_servers );

		// Clear out the cron if all servers have been processed.
		if ( empty( $all_servers ) ) {
			wp_clear_scheduled_hook( 'wpcd_' . $this->get_provider_slug() . '_auto_start_after_resize_cron' );
		}

	}
}
