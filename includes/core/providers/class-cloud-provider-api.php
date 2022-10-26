<?php
/**
 * Cloud Provider API
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLOUD_PROVIDER_API
 */
class CLOUD_PROVIDER_API {

	/**
	 * A text string with the cloud provider's name.
	 *
	 * @var $_PROVIDER provider.
	 */
	private $_PROVIDER;

	/**
	 * A text string representing a unique internal code for the cloud provider.
	 *
	 * @var $_PROVIDER_SLUG provider slug.
	 */
	private $_PROVIDER_SLUG;

	/**
	 * The baseline provider name to be used by certain custom server actions.
	 * This will NOT change even on custom servers.
	 *
	 * @var $_BASE_PROVIDER base provider.
	 */
	private $_BASE_PROVIDER;

	/**
	 * The baseline provider slug to be used by certain custom server actions.
	 * This will NOT change even on custom servers.
	 *
	 * @var $_BASE_PROVIDER_SLUG base provider slug.
	 */
	private $_BASE_PROVIDER_SLUG;

	/**
	 * A text string that holds the link to the providers dashboard.
	 * Set by descendant classes.
	 *
	 * @var $dashboard_link dashboard_link.
	 */
	private $dashboard_link;

	/**
	 * A text string with a prefix that will be used
	 * when displaying regions.
	 * Not really used in this class but by
	 * descendent classes
	 *
	 * @var $region_prefix region_prefix.
	 */
	private $region_prefix;

	/**
	 * A variable to hold a cachekey prefix
	 *
	 * @var $cache_key_prefix cache_key_prefix.
	 */
	private $cache_key_prefix;

	/**
	 * An array variable to hold a set of feature flags
	 *
	 * @var $feature_flags
	 */
	private $feature_flags = array();


	/**
	 * These variables are used to report the state of a server instance.
	 * This helps rationalize the state reporting among various
	 * cloud providers.  This way they all report the same text
	 * to calling functions even though their APIs use different text.
	 *
	 * @var $_STATE_NEW STATE_NEW.
	 */
	public $_STATE_NEW = 'new';

	/**
	 * STATE_NEW variable
	 *
	 * @var $_STATE_NEW STATE_NEW.
	 */
	public $_STATE_ACTIVE = 'active';

	/**
	 * STATE_ERRORED variable
	 *
	 * @var $_STATE_ERRORED STATE_ERRORED.
	 */
	public $_STATE_ERRORED = 'errored';

	/**
	 * STATE_INPROGRESS variable
	 *
	 * @var $_STATE_INPROGRESS STATE_INPROGRESS.
	 */
	public $_STATE_INPROGRESS = 'in-progress';  // not reported by any providers api but is the first state the server is in when we call an API to initiate a build.

	/**
	 * STATE_OFF variable
	 *
	 * @var $_STATE_OFF STATE_OFF.
	 */
	public $_STATE_OFF = 'off';

	/**
	 * STATE_UNKNOWN variable
	 *
	 * @var $_STATE_UNKNOWN STATE_UNKNOWN.
	 */
	public $_STATE_UNKNOWN = 'unknown'; // not reported by any providers api but can be used when we really don't know the state of a server.

	/**
	 * Root user login name.
	 * most providers are 'root' but
	 * some like AWS are different.
	 * We will default to root unless
	 * set differently by a descendent class.
	 *
	 * @var $root_user root_user.
	 */
	private $root_user = 'root';

	/**
	 * Help link to show on the providers' settings screen...
	 *
	 * @var $help_link help_link.
	 */
	private $help_link = 'https://wpclouddeploy.com/documentation/cloud-providers/all-about-cloud-server-providers/';

	/**
	 * Constructor
	 */
	public function __construct() {

		/* Set initial values for feature flags */
		$feature_flags['snapshots']                            = false;  // Does the provider support snapshots?
		$feature_flags['snapshot-delete']                      = false;  // Does the provider support deleting snapshots?
		$feature_flags['snapshot-list']                        = false;  // Does the provider support listing snapshots?
		$feature_flags['enable_backups_on_server_create']      = false;  // Does the provider support automatically enabling backups when a server is initially created?
		$feature_flags['backups']                              = false;  // Does the provider support creating backups?
		$feature_flags['enable_dynamic_tags_on_server_create'] = false;  // Does the provider support enabling random tag(s) when a server is initially created?
		$feature_flags['dynamic_tags']                         = false;  // Does the provider support creating random tags?
		$feature_flags['custom_images']                        = false;  // Does the provider support creating servers from custom images instead of just standard images?
		$feature_flags['resize']                               = false;  // Does the provider support resizing operations?
		$feature_flags['ssh_create']                           = false;  // Does the provider support creating ssh keys?
		$feature_flags['test_connection']                      = false;  // Does the provider support testing a connection to it?

		$this->set_cache_key_prefix();

		/* Set filter to add link to the cloud providers api dashboard */
		$provider = $this->get_provider_slug();
		add_filter( "wpcd_cloud_provider_settings_api_key_label_desc_{$provider}", array( $this, 'set_link_to_provider_dashboard' ) );

	}

	/**
	 * Template function to get api_key.
	 * Implementation is left to the descendent classes.
	 */
	public function get_api_key() {
		return '';
	}

	/**
	 * Template function to get a secret key;
	 * Secret keys are used by providers such as AWS and ALIBABA
	 * Implementation is left to the descendent classes.
	 */
	public function get_secret_key() {
		return '';
	}

	/**
	 * Generate the cache key prefix
	 */
	public function set_cache_key_prefix() {
		if ( is_multisite() ) {
			$this->cache_key_prefix = (string) get_current_blog_id() . '_' . (string) wpcd_version;
		} else {
			$this->cache_key_prefix = (string) wpcd_version;
		}
	}

	/**
	 * Getter function for the cache key prefix
	 */
	public function get_cache_key_prefix() {
		return $this->cache_key_prefix;
	}

	/**
	 * Set function for root user.
	 *
	 * @param int $user user.
	 */
	public function set_root_user( $user ) {
		$this->root_user = $user;
	}

	/**
	 * Getter function for root user.
	 */
	public function get_root_user() {
		return $this->root_user;
	}

	/**
	 * Set function for provider name variable.
	 *
	 * @param int $provider provider.
	 */
	public function set_provider( $provider ) {
		$this->_PROVIDER = $provider;
	}

	/**
	 * Alternate set function for provider name variable.
	 * Its a more obvious name as to what we're setting.
	 *
	 * @param int $provider provider.
	 */
	public function set_provider_friendly_name( $provider ) {
		$this->_PROVIDER = $provider;
	}

	/**
	 * Set function for provider slug.
	 *
	 * @param string $provider_slug provider_slug.
	 */
	public function set_provider_slug( $provider_slug ) {
		$this->_PROVIDER_SLUG = $provider_slug;
	}

	/**
	 * Getter function for provider's name
	 */
	public function get_provider() {
		return $this->_PROVIDER;
	}

	/**
	 * Alternate getter function for provider's name
	 * Its a more obvious name as to what we're returning.
	 */
	public function get_provider_friendly_name() {
		return $this->_PROVIDER;
	}

	/**
	 * Getter function for provider slug
	 */
	public function get_provider_slug() {
		return $this->_PROVIDER_SLUG;
	}

	/**
	 * Set the baseline provider slug to be used by certain custom server actions.
	 * This will NOT change even on custom servers.
	 *
	 * @param string $provider provider.
	 */
	public function set_base_provider_slug( $provider ) {
		$this->_BASE_PROVIDER_SLUG = $provider;
	}

	/**
	 * Get baseline provider slug.
	 */
	public function get_base_provider_slug() {
		return $this->_BASE_PROVIDER_SLUG;
	}

	/**
	 * Set the baseline provider name to be used by certain custom server actions.
	 * This will NOT change even on custom servers.
	 *
	 * @param string $provider_name provider_name.
	 */
	public function set_base_provider_name( $provider_name ) {
		$this->_BASE_PROVIDER = $provider_name;
	}

	/**
	 * Get baseline provider name.
	 */
	public function get_base_provider_name() {
		return $this->_BASE_PROVIDER;
	}

	/**
	 * Set function for region prefix variable.
	 *
	 * @param string $region_prefix region_prefix.
	 */
	public function set_region_prefix( $region_prefix ) {
		$this->region_prefix = $region_prefix;
	}

	/**
	 * Getter function for region prefix
	 */
	public function get_region_prefix() {
		return $this->region_prefix;
	}

	/**
	 * Set function for dashboard link
	 * Needs to be called by descendant classes.
	 *
	 * @param string $link link.
	 */
	public function set_provider_dashboard_link( $link ) {
		$this->dashboard_link = $link;
	}

	/**
	 * Getter function for dashboard link.
	 */
	public function get_provider_dashboard_link() {
		return $this->dashboard_link;
	}

	/**
	 * Set function for help link.
	 *
	 * @param string $help_link help_link.
	 */
	public function set_provider_help_link( $help_link ) {
		$this->help_link = $help_link;
	}

	/**
	 * Getter function for help link.
	 */
	public function get_provider_help_link() {
		return apply_filters( 'wpcd_provider_help_link', $this->help_link );
	}

	/**
	 * Returns a link to the cloud providers dashboard where the user can set their api key.
	 *
	 * Filter Hook: wpcd_cloud_provider_settings_api_key_label_desc_{$provider} aka wpcd_cloud_provider_settings_api_key_label_desc_digital-ocean
	 *
	 * This hook is actually configured in the descendant classes but since the function is the same for every one, its implemented here at the ancestor.
	 *
	 * @param string $link $link.
	 */
	public function set_link_to_provider_dashboard( $link ) {
		/* translators: %s is with a URL link to the cloud providers dashboard. */
		$provider_link = sprintf( '<a href="%s" target="_blank">' . __( 'Go to your %s dashboard', 'wpcd' ) . '</a>', $this->get_provider_dashboard_link(), $this->get_provider_friendly_name() );
		return $link  .= $provider_link;
	}

	/**
	 * Set function for feature flags.
	 *
	 * @param string  $feature The name of the feature to set or remove support for.
	 * @param boolean $flag_value The value to set the flag to.
	 */
	public function set_feature_flag( $feature, $flag_value ) {

		$this->feature_flags[ $feature ] = $flag_value;

	}

	/**
	 * Getter function for feature flags.
	 *
	 * @param string $feature The name of the feature flag to get.
	 */
	public function get_feature_flag( $feature ) {
		if ( isset( $this->feature_flags[ $feature ] ) ) {
			return $this->feature_flags[ $feature ];
		} else {
			return false;
		}
	}

	/**
	 * Translate a state to a human readable string.
	 *
	 * @param string $state The state of the server.
	 *
	 * @return string $return The human-readable text for ordinary human beings.
	 */
	public function get_server_state_text( $state ) {

		$return = 'unknown';

		switch ( $state ) {
			case $this->_STATE_NEW:
				$return = __( 'New Server', 'wpcd' );
				break;
			case $this->_STATE_ACTIVE:
				$return = __( 'Active', 'wpcd' );
				break;
			case $this->_STATE_ERRORED:
				$return = __( 'Unknown State or Errored', 'wpcd' );
				break;
			case $this->_STATE_INPROGRESS:
				$return = __( 'Operation in progress', 'wpcd' );  // not reported by any providers api but is the first state the server is in when we call an API to initiate a build.
				break;
			case $this->_STATE_OFF:
				$return = __( 'Powered-off', 'wpcd' );
				break;
			default:
				$return = __( 'unknown/', 'wpcd' ) . $state;
				break;
		}

		return $return;

	}

	/**
	 * Return the region description given a region code.
	 *
	 * Descendent classes should override this function.
	 * But if they don't we'll just return what's passed in.
	 *
	 * @param string $region Region code.
	 *
	 * @return string.
	 */
	public function get_region_description( $region ) {
		return $region;
	}

	/**
	 * Return the size description given a size code.
	 *
	 * Descendant classes should override this function.
	 * But if they don't we'll just return what's passed in.
	 *
	 * @param string $size Size code.
	 *
	 * @return string.
	 */
	public function get_size_description( $size ) {
		return $size;
	}

	/**
	 * Get a list of methods that will be cached.
	 */
	public function get_cache_methods() {
		return apply_filters( 'wpcd_provider_api_cache_methods', array( 'sizes', 'keys', 'regions', 'other' ) );
	}

	/**
	 * Get a list of all methods that might be cached.
	 */
	public function get_maybe_cache_methods() {
		return apply_filters( 'wpcd_provider_api_maybe_cache_methods', array( 'sizes', 'keys', 'regions', 'create', 'snapshot', 'reboot', 'off', 'on', 'status', 'delete', 'details', 'other' ) );
	}

	/**
	 * Create/Get a key that can be used as a cache key.
	 *
	 * @param string $method The method being called that needs to be cached.  Eg: 'sizes', 'keys', 'regions', 'create', 'snapshot', 'reboot', 'off', 'on', 'status', 'delete', 'details'.
	 *
	 * @return string The cache key.
	 */
	public function get_cache_key( $method ) {

		$cache_key = $this->get_provider_slug() . $this->get_cache_key_prefix() . hash( 'sha256', $this->get_secret_key() ) . $method;

		return $cache_key;

	}

	/**
	 * Clear the cache for a particular provider.
	 */
	public function clear_cache() {
		$methods = $this->get_maybe_cache_methods();
		foreach ( $methods as $method ) {
			$cache_key = $this->get_cache_key( $method );

			if ( get_transient( $cache_key ) ) {
				do_action( 'wpcd_log_error', "Cached item exists: $cache_key. It will be deleted.", 'provider-cache', __FILE__, __LINE__ );
			}

			delete_transient( $cache_key );

			$cache_key = $this->get_provider_slug() . $this->get_cache_key_prefix() . hash( 'sha256', $this->get_api_key() ) . $method;

			if ( get_transient( $cache_key ) ) {
				do_action( 'wpcd_log_error', "Cached item exists: $cache_key. It will be deleted.", 'provider-cache', __FILE__, __LINE__ );
			}

			delete_transient( $cache_key );
		}
	}

	/**
	 * Cache a particular item for a cloud provider.
	 *
	 * We're going to cache each item for the length of time that's set up on the settings screen.
	 *
	 * @param string $cache_key The key to the cache.
	 * @param string $data      The data to be cached and associated with the $cache_key.
	 * @param string $method    The type of data being cached.
	 *
	 * @return void
	 */
	public function store_cache( $cache_key, $data, $method ) {

		// We're only going to be caching certain things.
		if ( ! in_array( $method, $this->get_cache_methods(), true ) ) {
			return;
		}

		// If the $cache_key is empty, generate one.
		if ( empty( $cache_key ) ) {
			$cache_key = $this->get_cache_key( $method );
		}

		// Get the cache time from settings...
		$cache_time = wpcd_get_option( "vpn_{$method}_cache_limit" );
		if ( empty( $cache_time ) ) {
			$cache_time = 15;  // default is 15 minutes.
		};

		set_transient( $cache_key, $data, $cache_time * 60 );

	}

	/**
	 * Get the data held in the cache.
	 *
	 * @param string $method The method being called that needs to be cached.  Eg: 'sizes', 'keys', 'regions', 'create', 'snapshot', 'reboot', 'off', 'on', 'status', 'delete', 'details'.
	 *
	 * @return string|boolean The cached data
	 */
	public function get_cached_data( $method ) {

		$cache_key = $this->get_cache_key( $method );

		$cache = get_transient( $cache_key );

		if ( false !== $cache ) {
			return $cache;
		} else {
			return false;
		}

	}

	/**
	 * Return a formatted list of cached transients.
	 *
	 * @param string $style Can be 'array' or 'string'.
	 *
	 * @return string|array
	 */
	public function get_cached_transient_list( $style = 'string' ) {

		$methods = $this->get_maybe_cache_methods();

		$translist = array();
		foreach ( $methods as $method ) {
			$cache_key = $this->get_provider_slug() . $this->get_cache_key_prefix() . hash( 'sha256', $this->get_secret_key() ) . $method;

			if ( get_transient( $cache_key ) ) {
				$time_to_expire = wpcd_get_transient_remaining_time_in_mins( $cache_key );
				/* translators: %1$s is replaced with the cachekey; %2$d is replaced with the amount of time remaining till the cachekey expires. */
				$translist[] = sprintf( __( ' %1$s will expire in %2$d minutes.', 'wpcd' ), $cache_key, $time_to_expire );
			}
		}
		foreach ( $methods as $method ) {
			$cache_key = $this->get_provider_slug() . $this->get_cache_key_prefix() . hash( 'sha256', $this->get_api_key() ) . $method;

			if ( get_transient( $cache_key ) ) {
				$time_to_expire = wpcd_get_transient_remaining_time_in_mins( $cache_key );
				/* translators: %1$s is replaced with the cachekey; %2$d is replaced with the amount of time remaining till the cachekey expires. */
				$translist[] = sprintf( __( ' %1$s will expire in %2$d minutes.', 'wpcd' ), $cache_key, $time_to_expire );
			}
		}

		/* If user specified to return array do it here */
		if ( 'array' === $style ) {
			return $translist;
		}

		/* Turn array into formatted text string */
		$formatted_text = '';
		foreach ( $translist as $transient ) {
			$formatted_text .= $transient . '<br />';
		}

		return $formatted_text;
	}

	/**
	 * Returns a boolean indicating whether or not the credentials have been entered for a provider.
	 *
	 * This function is intended to be overriden by descendant classes.  This is because
	 * some providers might have a different types of credentials.
	 */
	public function credentials_available() {

		$ok_to_continue = true;

		// check to make sure that a public ssh key is available.
		if ( $ok_to_continue ) {
			if ( empty( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_sshkey_id' ) ) ) {
				$ok_to_continue = false;
			}
		}

		// check to make sure that we have a private key to match the public key.
		if ( $ok_to_continue ) {
			if ( empty( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_sshkey' ) ) ) {
				$ok_to_continue = false;
			}
		}
		if ( $ok_to_continue ) {
			if ( empty( $this->get_api_key() ) ) {
				$ok_to_continue = false;
			}
		}

		return $ok_to_continue;

	}

	/**
	 * Gets the urls of the scripts that are needed to init a Server instance.
	 *
	 * @param array $variables The variables that need to replace the placeholders in the script.
	 *
	 * @return array
	 *
	 * @TODO: This function is no longer being used I don't think.
	 */
	final protected function get_script_url( $variables = array() ) {
		$script = file_get_contents( wpcd_path . 'includes/scripts/params.sh' );
		foreach ( $variables as $name => $value ) {
			if ( is_array( $value ) ) {
				continue;
			}
			$script = str_replace( 'VAR_' . strtoupper( $name ), $value, $script );
		}

		$dir = wp_get_upload_dir();
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		if ( ! $wp_filesystem->is_dir( trailingslashit( $dir['basedir'] ) . 'wpcd_app_vpn' ) ) {
			$wp_filesystem->mkdir( trailingslashit( $dir['basedir'] ) . 'wpcd_app_vpn' );
		}

		$file = 'wpcd_app_vpn/script-' . sprintf( '%s-%s', $variables['wc_order_id'], $variables['region'] ) . '.sh';
		$wp_filesystem->put_contents(
			trailingslashit( $dir['basedir'] ) . $file,
			$script,
			FS_CHMOD_FILE
		);

		/*
		// for testing on local
		return array(
			'main' => 'https://careful-donkey.jurassic.ninja/template.sh',
			'params' => 'https://careful-donkey.jurassic.ninja/script-506-Digital-Ocean---nyc1.sh'
		);
		*/

		return array(
			'main'   => trailingslashit( wpcd_url ) . 'includes/scripts/template.txt',
			'params' => trailingslashit( $dir['baseurl'] ) . str_replace( ' ', '-', $file ),
		);
	}
}
