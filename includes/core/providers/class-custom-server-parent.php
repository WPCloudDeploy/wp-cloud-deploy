<?php
/**
 * Custom Server Parent.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLOUD_PROVIDER_API_CustomServer_Parent
 */
class CLOUD_PROVIDER_API_CustomServer_Parent extends CLOUD_PROVIDER_API {

	/**
	 * A text string with the url to the base REST API call
	 */
	const _URL = '';

	/**
	 * The provider's id for the image used to fire up a server
	 */
	const _IMAGE = '';

	/**
	 * A text string with the cloud provider's name.
	 *
	 * @var $_PROVIDER PROVIDER.
	 */
	private $_PROVIDER;

	/**
	 * A text string representing a unique internal code for the cloud provider.
	 *
	 * @var $_PROVIDER_SLUG PROVIDER_SLUG.
	 */
	private $_PROVIDER_SLUG;

	/**
	 * A text string with the cloud provider's api key
	 *
	 * @var $api_key api_key.
	 */
	private $api_key;

	/**
	 * A text string IPV4 address for the custom server.
	 *
	 * @var $custom_ipv4 custom_ipv4.
	 */
	private $custom_ipv4;

	/**
	 * A text string IPV6 address for the custom server.
	 *
	 * @var $custom_ipv6 custom_ipv6.
	 */
	private $custom_ipv6;

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct();

		/* Set provider name and slug */
		$this->set_provider( 'Custom Server #1' );
		$this->set_provider_slug( 'custom-server' );

		/* Set link to cloud provider's user dashboard */
		$this->set_provider_dashboard_link( 'https://wpclouddeploy.com/documentation/custom-server-provider/' );

		/* Set filter to add the link to our documentation - we're using the provider dashboard link for this */
		$provider = $this->get_provider_slug();
		add_filter( "wpcd_cloud_provider_settings_api_key_label_desc_{$provider}", array( $this, 'set_link_to_provider_dashboard' ) );

		/* Set the API key - pulling from settings */
		$this->set_api_key( WPCD()->decrypt( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_apikey' ) ) );
		$this->set_ipv4( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_ipv4' ) );
		$this->set_ipv6( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_ipv6' ) );

		/* This provider needs some unique settings */
		add_filter( "wpcd_cloud_provider_settings_{$provider}", array( &$this, 'settings' ), 10, 2 );
		add_filter( "wpcd_cloud_provider_settings_after_api_{$provider}", array( &$this, 'settings_after_api' ), 10, 2 );
		add_filter( "wpcd_cloud_provider_settings_after_part1_{$provider}", array( &$this, 'settings_after_part1' ), 10, 2 );

		/* This provider needs special instructions for the API KEY field because it's not used */
		add_filter( 'wpcd_cloud_provider_settings_api_key_desc_custom-server', array( $this, 'set_desc_for_api_field' ) );

		/* This provider MIGHT not use 'root' as their default admin user */
		if ( ! empty( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_custom_root_user' ) ) ) {
			$this->set_root_user( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_custom_root_user' ) );
		}

	}

	/**
	 * Set function for api key.
	 *
	 * @param string $the_key the_key.
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
	 * Set function for IPV4 for the custom server.
	 *
	 * @param string $ipv4 ipv4.
	 */
	public function set_ipv4( $ipv4 ) {
		$this->custom_ipv4 = $ipv4;
	}

	/**
	 * Getter function for IPVv4 for the custom server
	 */
	public function get_ipv4() {
		return $this->custom_ipv4;
	}

	/**
	 * Set function for IPV6 for the custom server.
	 *
	 * @param string $ipv6 ipv6.
	 */
	public function set_ipv6( $ipv6 ) {
		$this->custom_ipv6 = $ipv6;
	}

	/**
	 * Getter function for IPVv6 for the custom server
	 */
	public function get_ipv6() {
		return $this->custom_ipv6;
	}

	/**
	 * Implement the credentials_available function
	 * since its criteria is different from the
	 * default ancestor function.
	 *
	 * @return boolean
	 */
	public function credentials_available() {

		$ok_to_continue = parent::credentials_available();

		if ( $ok_to_continue ) {
			if ( empty( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_ipv4' ) ) ) {
				$ok_to_continue = false;
			}
		}

		return $ok_to_continue;

	}

	/**
	 * Set description for API Key field since the field is not used.
	 *
	 * @param string $desc desc.
	 */
	public function set_desc_for_api_field( $desc ) {
		return __( 'Enter any value without spaces in this field but do not leave it blank.  And it should be UNIQUE among all custom servers (if you are using multiple custom server providers.) ', 'wpcd' );
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

		do_action( 'wpcd_log_error', "executing do request $method with " . print_r( $attributes, true ), 'debug', __FILE__, __LINE__ );

		if ( empty( $this->get_ipv4() ) ) {
			return false;
		}

		/* Make sure that our attributes array contains an id element. */
		if ( isset( $attributes['provider_instance_id'] ) && ! isset( $attributes['id'] ) ) {
			$attributes['id'] = $attributes['provider_instance_id'];
		}
		$return     = null;
		$action     = 'GET';
		$body       = '';
		$endpoint   = $method;
		$servername = null; // used by some actions.
		switch ( $method ) {
			case 'sizes':
				$body = $this->get_types();
				break;
			case 'keys':
				$body = $this->get_ssh_keys();
				break;
			case 'regions':
				$body = $this->get_regions();
				break;
			case 'create':
				$body = $this->create_server( $attributes );
				break;
			case 'snapshot':
				/* Not used */
				break;
			case 'reboot':
				$body = $this->server_restart( $attributes['id'] );
				break;
			case 'off':
				$body = $this->server_off( $attributes['id'] );
				break;
			case 'on':
				$body = $this->server_on( $attributes['id'] );
				break;
			case 'status':
				$body = $this->server_details( $attributes['id'] );
				break;
			case 'delete':
				$body = $this->server_delete( $attributes['id'] );
				break;
			case 'details':
				$body = $this->server_details( $attributes['id'] );
				break;
			default:
				return new WP_Error( 'not supported' );
		}

		/* Variable to be returned */
		$return = array();

		/* Return different data depending on the method / action executed */
		switch ( $method ) {
			case 'sizes':
				return $body;
				break;

			case 'keys':
				return $body;
				break;

			case 'regions':
				return $body;
				break;

			case 'create':
				return $body;
				break;

			case 'snapshot':
				break;

			case 'off':
				return $body;
				break;

			case 'reboot':
				return $body;
				break;
			case 'on':
				return $body;
				break;

			case 'status':
				return $body['status'];
				break;

			case 'delete':
				$return['status'] = 'done';  // @TODO: Are we always returning 'done' regardless of the state of the operation?
				break;

			case 'details':
				return $body;
				break;
		}

		/* Return data if all good - if errors, we've already exited this function way above here. */
		return $return;

	}

	/**
	 * Tweak the settings screen to add in the IPv4 address of the server.
	 *
	 * Filter: wpcd_cloud_provider_settings_custom-server || wpcd_cloud_provider_settings_{$provider}
	 *
	 * @param array  $provider_fields array of settings.
	 * @param string $tab_id the ID of the tab being handled when this filter is executed.
	 */
	public function settings( $provider_fields, $tab_id ) {

		$return_fields = $provider_fields;
		$provider      = $this->get_provider_slug();

		if ( ! empty( $provider ) ) {

			$fields = array(
				array(
					'id'   => "vpn_{$provider}_ipv4",
					'type' => 'text',
					'name' => __( 'IPv4 Address For Your Server', 'wpcd' ),
					'size' => '60',
					'desc' => __( 'Enter a properly formatted IPv4 address for your server.', 'wpcd' ),
					'tab'  => $tab_id,
				),
				array(
					'id'   => "vpn_{$provider}_ipv6",
					'type' => 'text',
					'name' => __( 'IPv6 Address For Your Server (Optional)', 'wpcd' ),
					'desc' => __( 'Enter a properly formatted IPv6 address for your server.', 'wpcd' ),
					'tab'  => $tab_id,
				),
			);

			$return_fields = array_merge( $fields, $provider_fields );

		}

		return $return_fields;

	}

	/**
	 * Tweak the settings screen to remove the divider that is at the end of the first set of settings fields.
	 *
	 * Filter: wpcd_cloud_provider_settings_after_api_custom-server || wpcd_cloud_provider_settings_after_api_{$provider}
	 *
	 * @param array  $provider_fields array of settings.
	 * @param string $tab_id the ID of the tab being handled when this filter is executed.
	 */
	public function settings_after_api( $provider_fields, $tab_id ) {

		$provider = $this->get_provider_slug();

		if ( ! empty( $provider ) ) {

			$return_fields =
						array(
							'id'   => "vpn_{$provider}_custom_root_user",
							'type' => 'text',
							'name' => __( 'Root User Name', 'wpcd' ),
							'size' => '60',
							'std'  => 'root',
							'desc' => __( 'The user name that will be used to log into the server - usually "root". We strongly recommend that you DO NOT LEAVE THIS BLANK!', 'wpcd' ),
							'tab'  => $tab_id,
						);

			return $return_fields;

		}

		// Blank provider - just wipe out the divider.
		return array(
			'type' => 'hidden',
			'tab'  => $tab_id,
		);

	}

	/**
	 * Tweak the settings screen to add in some additional fields after the API data is entered.
	 *
	 * Filter: wpcd_cloud_provider_settings_after_part1_custom-server || wpcd_cloud_provider_settings_after_part1_{$provider}
	 *
	 * @param array  $provider_fields array of settings.
	 * @param string $tab_id the ID of the tab being handled when this filter is executed.
	 */
	public function settings_after_part1( $provider_fields, $tab_id ) {

		$return_fields = $provider_fields;
		$provider      = $this->get_provider_slug();

		if ( ! empty( $provider ) ) {

			$fields = array(
				array(
					'id'   => "vpn_{$provider}_heading_customprov_fields",
					'type' => 'heading',
					'name' => __( 'Descriptive Text', 'wpcd' ),
					'desc' => __( 'Enter some data about the servers being deployed with this provider.  Since we are not connecting directly to a provider you can provide some descriptive text for items we normally get from a standard cloud provider.', 'wpcd' ),
					'tab'  => $tab_id,
				),
				array(
					'id'   => "vpn_{$provider}_region_name",
					'type' => 'text',
					'name' => __( 'Region Name', 'wpcd' ),
					'size' => '60',
					'desc' => __( 'Enter the region name that should be displayed when this custom server provider is used.', 'wpcd' ),
					'tab'  => $tab_id,
				),
				array(
					'id'   => "vpn_{$provider}_custom_size",
					'type' => 'text',
					'name' => __( 'Server Size', 'wpcd' ),
					'size' => '60',
					'desc' => __( 'Enter the server size that should be displayed when this custom server provider is used.', 'wpcd' ),
					'tab'  => $tab_id,
				),
				array(
					'id'   => "vpn_{$provider}_custom_public_ssh_key_name",
					'type' => 'text',
					'name' => __( 'Public SSH Key Name', 'wpcd' ),
					'size' => '60',
					'desc' => __( 'Enter the name of the public key that should be displayed when this custom server provider is used.', 'wpcd' ),
					'tab'  => $tab_id,
				),
			);

			$return_fields = array_merge( $provider_fields, $fields );

		}

		return $return_fields;

	}

	/**
	 * Get the list of regions.
	 */
	public function get_regions() {

		$server_region = wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_region_name' );

		if ( empty( $server_region ) ) {
			$return['custom-server-region'] = 'Custom Server';
		} else {
			$return[ sanitize_title( $server_region ) ] = $server_region;
		}

		return $return;

	}


	/**
	 * Get the list of ssh keys
	 */
	public function get_ssh_keys() {

		$ssh_key = wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_custom_public_ssh_key_name' );
		if ( empty( $ssh_key ) ) {
			$return['custom-server-key'] = 'Custom Server Key';
		} else {
			$return[ sanitize_title( $ssh_key ) ] = $ssh_key;
		}

		return $return;

	}

	/**
	 * Get the list of instance sizes.
	 */
	public function get_types() {

		$server_size = wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_custom_size' );

		if ( empty( $server_size ) ) {
			$return['custom-server'] = 'Custom Server Size';
		} else {
			$return[ sanitize_title( $server_size ) ] = $server_size;
		}

		return $return;

	}

	/**
	 * Create a server.
	 *
	 * @param array $attributes attributes.
	 */
	public function create_server( $attributes ) {

		/* Collect some data */
		$size = '';
		if ( isset( $attributes['size'] ) ) {
			$size = wpcd_get_option( 'vpn_' . $this->get_provider_slug() . '_' . $attributes['size'] );
		}
		// size_raw is the raw size as expected by the provider.
		if ( empty( $size ) && isset( $attributes['size_raw'] ) ) {
			$size = $attributes['size_raw'];
		}
		$region     = $attributes['region'];
		$servername = $attributes['name'];

		// Construct a return array.
		$return                         = null;
		$server_id                      = $this->get_rand_str( 10 );
		$return['provider_instance_id'] = $server_id;
		$return['server_name']          = $servername;
		$return['created']              = (string) current_time( 'mysql' );
		$return['actions']              = array( 'created' => time() );
		$return['ip']                   = $this->get_ipv4();
		$return['ipv6']                 = $this->get_ipv6();

		return $return;

	}

	/**
	 * Delete.
	 *
	 * @param int $server_id server id.
	 */
	public function server_delete( $server_id ) {

		$return['status'] = $this->_STATE_OFF;

		return $return;

	}

	/**
	 * Get server details for a server.
	 *
	 * @param int $server_id server id.
	 */
	public function server_details( $server_id ) {

		/* Default return in case of error */
		$return           = array();
		$return['status'] = $this->_STATE_ERRORED;

		$return['action_id'] = ''; /* Not sure what this is supposed to be - hold over from the digital ocean template we used for this... */

		// Get some data from the server post record since there isn't a real server provider to connect to...
		$post = get_post( $server_id );
		if ( $post ) {
			$name = $post->post_title;
		} else {
			$name = __( 'Unknown Name', 'wpcd' );
		}

		$return['os']     = __( 'unknown', 'wpcd' );
		$return['name']   = $name;
		$return['status'] = $this->_STATE_ACTIVE;
		$return['ip']     = $this->get_ipv4();

		return $return;

	}

	/**
	 * Turn off the server.
	 *
	 * @param int $server_id server id.
	 */
	public function server_off( $server_id ) {

		$return           = array();
		$return['status'] = $this->_STATE_ERRORED;

		$return['action_id'] = ''; /* Not sure what this is supposed to be - hold over from the digital ocean template we used for this... */

		$return['status'] = $this->_STATE_OFF;

		return $return;

	}

	/**
	 * Turn on the server.
	 *
	 * @param int $server_id server id.
	 */
	public function server_on( $server_id ) {

		$return           = array();
		$return['status'] = $this->_STATE_ERRORED;

		$return['action_id'] = ''; /* Not sure what this is supposed to be - hold over from the digital ocean template we used for this... */

		$return['status'] = $this->_STATE_ON;

	}

	/**
	 * Reboot the server
	 *
	 * @param int $server_id server id.
	 */
	public function server_restart( $server_id ) {

		$return           = array();
		$return['status'] = $this->_STATE_ERRORED;

		$return['action_id'] = ''; /* Not sure what this is supposed to be - hold over from the digital ocean template we used for this... */

		return $return;
	}

	/**
	 * A function to return a random string of a particular length.
	 *
	 * We need this because linode doesn't allow duplicate labels in its server names
	 * which causes an issue when we're relocating servers because we don't delete
	 * the old one until the new one is up and running.
	 * So we use this function to add a random string to the end of our server name.
	 *
	 * @param int $length - length of string to return.
	 *
	 * @return string
	 */
	public function get_rand_str( $length ) {
		$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$return          = substr( str_shuffle( $permitted_chars ), 0, $length - 1 );
		return $return;
	}

}
