<?php
/**
 * WPCD APP Class
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_APP
 */
class WPCD_APP extends WPCD_Base {

	/**
	 * Absolute path to the main scripts folder - needs to be sent by the app inheriting this class.
	 *
	 * @var $scripts_folder
	 */
	private $scripts_folder;

	/**
	 * Relative path to the main scripts folder.
	 * The path is relative to the WPCD URL.
	 * So, if the scripts folder is under
	 * 'includes\core\apps\vpn\scripts' then
	 * that is what must be specified here.
	 *
	 * @var $scripts_folder_relative
	 */
	private $scripts_folder_relative;


	/**
	 * App name
	 *
	 * @var $app_name
	 */
	private $app_name;

	/**
	 * App description
	 *
	 * @var $app_desc
	 */
	private $app_desc;

	/**
	 * Holds a reference to a list of cloud providers
	 *
	 * @var $providers
	 */
	private static $providers;

	/**
	 * Class constructor.
	 */
	public function __construct() {

		// Instantiate some variables.
		self::$providers = WPCD()->get_cloud_providers();

		// Fire hooks.
		$this->hooks();
	}

	/**
	 * Hooks function.
	 */
	private function hooks() {

		// Register our ssh callback function for long running ssh commands.
		add_action( 'rest_api_init', array( $this, 'register_rest_endpoint' ) );

		// Hide certain actions on the custom post type list page.
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );

		// Make sure WordPress loads up our css and js scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 1 );

		// Register application metabox stub with filter. Note that this is a METABOX.IO filter, not a core WP filter.
		add_filter( 'rwmb_meta_boxes', array( $this, 'register_app_metaboxes' ), 10, 1 );

		// Meta box display callback on the APP CPT screen.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes_background_task_details' ) );

		// Save Meta Values in metaboxes on the APP CPT screen.
		add_action( 'save_post', array( $this, 'save_meta_values_for_background_task_details' ), 10, 2 );

		// Show some app details in the wp-admin list of apps.
		add_filter( 'wpcd_app_admin_list_summary_column', array( &$this, 'app_admin_list_summary_status_column' ), 10, 2 );

	}

	/**
	 * Returns all the providers
	 *
	 * Right now this is effectively the same as WPCD()->get_cloud_providers()
	 * which is ALL Providers.
	 * But, we're including this function in the event that this VPN app
	 * needs to modify the list before returning it to the caller.
	 *
	 * @return array
	 */
	public function get_providers() {
		return self::$providers;
	}

	/**
	 *
	 * Returns only the providers that have an API key set.
	 */
	public function get_active_providers() {
		return WPCD()->get_active_cloud_providers();
	}

	/**
	 * Register the scripts for custom post types for all apps.
	 *
	 * @param string $hook The page name hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( in_array( $hook, array( 'post.php' ) ) ) {
			$screen = get_current_screen();
			if ( is_object( $screen ) && 'wpcd_app' == $screen->post_type ) {
				wp_register_script( 'wpcd-magnific', wpcd_url . 'assets/js/jquery.magnific-popup.min.js', array( 'jquery' ), wpcd_scripts_version, true );

				wp_enqueue_style( 'wpcd-magnific', wpcd_url . 'assets/css/magnific-popup.css', array(), wpcd_scripts_version );
				wp_enqueue_style( 'wpcd-app-admin', wpcd_url . 'assets/css/wpcd-app.css', array( 'wpcd-magnific' ), wpcd_scripts_version );
			}
		}
	}

	/**
	 *
	 * Hide the QUICK EDIT link that shows up when you hover over a post link in a CPT list.
	 *
	 * @param array   $actions The list of actions.
	 * @param WP_Post $post The post object.
	 *
	 * @return array    Array of actions.
	 */
	public function row_actions( $actions, $post ) {
		if ( 'wpcd_app' === $post->post_type ) {
			// hide Quick Edit.
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	/**
	 * Registers a stub metabox for apps with a filter
	 * that can be used by child apps to paint their own
	 * metaboxes.
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * Note that this is a METABOX.IO metabox hook,
	 * not a core WP hook.
	 *
	 * @param array $metaboxes Array of metaboxes.
	 *
	 * @return array Array of metaboxes to paint.
	 */
	public function register_app_metaboxes( $metaboxes ) {

		return apply_filters( "wpcd_app_{$this->get_app_name()}_metaboxes", $metaboxes );

	}

	/**
	 * Child classes need to make sure there is a way to add an app to a server
	 *
	 * This method should be abstract but it causes complications in the children
	 * that inherits it.
	 *
	 * @param object $server_instance server_instance.
	 */
	public function add_app( &$server_instance ) {}


	/**
	 * Setting function for app_name
	 *
	 * @param string $name name.
	 */
	public function set_app_name( $name ) {
		$this->app_name = $name;
	}

	/**
	 * Getter function for app_name
	 */
	public function get_app_name() {
		return $this->app_name;
	}

	/**
	 * Setting function for app description
	 *
	 * @param string $desc description.
	 */
	public function set_app_description( $desc ) {
		$this->app_desc = $desc;
	}

	/**
	 * Getter function for app description
	 */
	public function get_app_description() {
		return $this->app_desc;
	}

	/**
	 * Setting function for scripts_folder
	 *
	 * @param string $folder folder.
	 */
	public function set_scripts_folder( $folder ) {
		$this->scripts_folder = $folder;
	}

	/**
	 * Getter function for scripts_folder
	 */
	public function get_scripts_folder() {
		return $this->scripts_folder;
	}

	/**
	 * Setting function for scripts_folder_relative
	 *
	 * @param string $folder folder.
	 */
	public function set_scripts_folder_relative( $folder ) {
		$this->scripts_folder_relative = $folder;
	}

	/**
	 * Getter function for scripts_folder_relative
	 */
	public function get_scripts_folder_relative() {
		return $this->scripts_folder_relative;
	}


	/**
	 * Returns a list of apps (posts) for a given user.
	 *
	 * @param int $user_id  The user to get the apps for.
	 *
	 * @return array|boolean Array of app posts or false or error message
	 */
	public function get_apps_by_user_id( $user_id ) {

		// return nothing if no user id is provided.
		if ( $user_id <= 0 ) {
			return false;
		}

		$posts = get_posts(
			array(
				'post_type'   => 'wpcd_app',
				'post_status' => 'private',
				'numberposts' => -1,
				'meta_query'  => array(),
				'author'      => $user_id,
			)
		);

		return $posts;

	}

	/**
	 * Returns a list of SERVERS (posts) for a given user.
	 *
	 * @param int $user_id  The user to get the servers for.
	 *
	 * @return array|boolean Array of app posts or false or error message
	 *
	 * @TODO - this might be better off in the WPCD_Server class?
	 * If so, we can always add a stub function here to replace it
	 * and move it there if necessary later.
	 */
	public function get_servers_by_user_id( $user_id ) {

		// return nothing if no user id is provided.
		if ( $user_id <= 0 ) {
			return false;
		}

		$posts = get_posts(
			array(
				'post_type'   => 'wpcd_app_server',
				'post_status' => 'private',
				'numberposts' => -1,
				'meta_query'  => array(),
				'author'      => $user_id,
			)
		);

		return $posts;

	}

	/**
	 * Just adding a note here among all these other get_by_id functions
	 * that there is a WordPress specific function in the file
	 * class-wordpress-app.php.
	 * It is called get_app_id_by_server_id_and_domain.
	 *
	 * Now, on to the next function below.
	 */

	/**
	 * Returns a server post object using the postid of a server.
	 *
	 * It is effectively just a wrapper around get_post.
	 *
	 * @param int $server_id  The app for which to locate the server post.
	 *
	 * @return array|boolean Server post or false or error message
	 */
	public function get_server_by_server_id( $server_id ) {

		// Get the app post.
		return get_post( $server_id );

	}

	/**
	 * Returns an apps post for a given app_id (post_id).
	 *
	 * @param int $app_id  The app_id to get the apps for.
	 *
	 * @return WP_Post|null Array of app posts or false or error message
	 */
	public function get_app_by_app_id( $app_id ) {

		// Get the app post.
		$app_post = get_post( $app_id );

		return $app_post;

	}

	/**
	 * Returns a server post object using the postid of an app.
	 *
	 * @param int $app_id  The app for which to locate the server post.
	 *
	 * @return array|boolean Server post or false or error message
	 */
	public function get_server_by_app_id( $app_id ) {

		// If for some reason the $app_id is actually a server id return the server data right away.
		if ( 'wpcd_app_server' == get_post_type( $app_id ) ) {
			return get_post( $app_id );
		}

		// Get the app post.
		$app_post = get_post( $app_id );

		if ( ! empty( $app_post ) && ! is_wp_error( $app_post ) ) {

			$server_post = get_post( get_post_meta( $app_post->ID, 'parent_post_id', true ) );

			return $server_post;

		} else {

			return false;

		}

		return false;
	}

	/**
	 * Returns a server ID using the postid of an app.
	 *
	 * @param int $app_id  The app for which to locate the server post.
	 *
	 * @return array|boolean Server post or false or error message
	 */
	public function get_server_id_by_app_id( $app_id ) {

		$server_post = $this->get_server_by_app_id( $app_id );

		if ( ! empty( $server_post ) && ! is_wp_error( $server_post ) ) {

			return $server_post->ID;

		} else {

			return false;

		}

	}

	/**
	 * Returns the app_type for a given app_id (post_id).
	 *
	 * @param int $app_id  The app_id to get the app_type for.
	 *
	 * @return sring|boolean
	 */
	public function get_app_type( $app_id ) {

		// Get the app post.
		$app_type = get_post_meta( $app_id, 'app_type', true );

		return $app_type;

	}

	/**
	 * Returns the server_type for a given post_id.
	 *
	 * @param int $server_id  The server_id to get the app_type for.
	 *
	 * @return sring|boolean
	 */
	public function get_server_type( $server_id ) {

		// Get the app post.
		$server_type = get_post_meta( $server_id, 'wpcd_server_initial_app_name', true );

		return $server_type;

	}

	/**
	 * Get the IPv4 address on the server record
	 * given an app post id.
	 *
	 * @param int $app_id app_id of app record.
	 *
	 * @return string|boolean the ipv4 address or false if we can't get one.
	 */
	public function get_ipv4_address( $app_id ) {

		// if for some reason the $app_id is actually a server id return the server data right away.
		if ( 'wpcd_app_server' === get_post_type( $app_id ) ) {
			return WPCD_SERVER()->get_ipv4_address( $app_id );
		}

		// ok, we probably have an app id so work with that.
		$server_post = $this->get_server_by_app_id( $app_id );
		if ( $server_post ) {
			return WPCD_SERVER()->get_ipv4_address( $server_post->ID );
		} else {
			return false;
		}

		return false;
	}

	/**
	 * Get the IPv6 address on the server record
	 * given an app post id.
	 *
	 * @param int $app_id app_id of app record.
	 *
	 * @return string|boolean The ipv6 address or false if we can't get one.
	 */
	public function get_ipv6_address( $app_id ) {

		// if for some reason the $app_id is actually a server id return the server data right away.
		if ( 'wpcd_app_server' === get_post_type( $app_id ) ) {
			return WPCD_SERVER()->get_ipv6_address( $app_id );
		}

		// ok, we probably have an app id so work with that.
		$server_post = $this->get_server_by_app_id( $app_id );
		if ( $server_post ) {
			return WPCD_SERVER()->get_ipv6_address( $server_post->ID );
		} else {
			return false;
		}

		return false;
	}

	/**
	 * Get the a combination of the IPv4 and IPv6 address for display.
	 *
	 * @param int $app_id post id of app record.
	 *
	 * @return string the ipv addresses.
	 */
	public function get_all_ip_addresses_for_display( $app_id ) {

		$ip   = '';
		$ipv4 = $this->get_ipv4_address( $app_id );
		if ( ! empty( $ipv4 ) ) {
			$ip = $ipv4;
		}
		if ( wpcd_get_early_option( 'wpcd_show_ipv6' ) ) {
			$ipv6 = $this->get_ipv6_address( $app_id );
			if ( ! empty( $ipv6 ) ) {
				if ( ! empty( $ip ) ) {
					$ip .= '<br />' . $ipv6;
				} else {
					$ip = $ipv6;
				}
			}
		}

		return $ip;

	}

	/**
	 * Get the server name given an app post id.
	 *
	 * @param int $app_id post id of app record.
	 *
	 * @return string the server name.
	 */
	public function get_server_name( $app_id ) {

		// if for some reason the $app_id is actually a server id return the server data right away.
		if ( 'wpcd_app_server' == get_post_type( $app_id ) ) {
			return WPCD_SERVER()->get_server_name( $app_id );
		}

		// ok, we probably have an app id so work with that.
		$server_post = $this->get_server_by_app_id( $app_id );
		if ( $server_post ) {
			return WPCD_SERVER()->get_server_name( $server_post->ID );
		} else {
			return false;
		}
	}

	/**
	 * Get the server region given an app post id.
	 *
	 * @param int $app_id post id of app record.
	 *
	 * @return string the server name.
	 */
	public function get_server_region( $app_id ) {
		$server_post = $this->get_server_by_app_id( $app_id );
		if ( $server_post ) {
			return WPCD_SERVER()->get_server_region( $server_post->ID );
		} else {
			return false;
		}
	}

	/**
	 * Get the server provider given an app post id.
	 *
	 * @param int $app_id post id of app record.
	 *
	 * @return string the server name.
	 */
	public function get_server_provider( $app_id ) {
		$server_post = $this->get_server_by_app_id( $app_id );
		if ( $server_post ) {
			return WPCD_SERVER()->get_server_provider( $server_post->ID );
		} else {
			return false;
		}
	}


	/**
	 * Get a server meta given an app id.
	 *
	 * @param int     $app_id post id of server record.
	 * @param string  $key meta key to retrieve.
	 * @param boolean $single whether to retrieve one value or an array of values (if more than one value exists).
	 *
	 * @return mixed    The meta data retrieved from the server record.
	 */
	public function get_server_meta_by_app_id( $app_id, $key, $single = false ) {

		// If for some reason the $app_id is actually a server id return the server data right away.
		if ( 'wpcd_app_server' == get_post_type( $app_id ) ) {
			return get_post_meta( $app_id, $key, $single );
		}

		// Get server post object.
		$server_post = $this->get_server_by_app_id( $app_id );

		if ( $server_post ) {
			$server_id = $server_post->ID;
			return get_post_meta( $server_id, $key, $single );
		} else {
			return false;
		}
	}

	/**
	 * Returns the text contents of a script file
	 *
	 * @param string $file The file name and path to get contents for.
	 *
	 * @return string|boolean|wp_error $script The contents of the requested file or false or an error
	 */
	public function get_script_file_contents( $file ) {
		if ( file_exists( $file ) ) {
			$script = file_get_contents( $file );
		} else {
			$script = '';
		}

		return $script;
	}

	/**
	 * Takes a script file and inserts any custom field tokens into it.
	 *
	 * The tokens will be inserted into the line that starts with EXPORT.
	 * For example, if the EXPORT line in the file is:
	 *      export domain=##DOMAIN## wp_user=##WP_USER## wp_password=##WP_PASSWORD## wp_email=##WP_EMAIL## wp_version=##WP_VERSION## wp_locale=##WP_LOCALE## &&
	 * we will modify it to include custom fields at beginning..
	 *      export custom_field_one = ##CUSTOM_FIELD_ONE## custom_field_two = ##CUSTOM_FIELD_TWO## domain=##DOMAIN## wp_user=##WP_USER## wp_password=##WP_PASSWORD## wp_email=##WP_EMAIL## wp_version=##WP_VERSION## wp_locale=##WP_LOCALE## &&
	 *
	 * @param string $script_name script name.
	 * @param string $script_contents script name.
	 */
	public function add_custom_field_tokens_to_script( $script_name, $script_contents ) {

		// Get the list of custom fields applicable for the $script_name - make sure to run it through a filter so that devs can modify it if necessary.
		$custom_fields = apply_filters( "wpcd_{$this->get_app_name()}_add_custom_field_tokens_to_script", WPCD_CUSTOM_FIELDS()->get_fields_for_script( $script_name ), $script_name, $script_contents );

		if ( ! empty( $custom_fields ) ) {
			$custom_fields_string = '';
			foreach ( $custom_fields as $field ) {
				$custom_fields_string = $field['name'] . '=' . '##' . strtoupper( $field['name'] ) . '##';
			}

			$script_contents = str_replace( 'export ', 'export ' . $custom_fields_string . ' ', $script_contents );
		}

		return $script_contents;

	}

	/**
	 * Replaces placeholder tokens in a script.
	 *
	 * A script is usually a server provisioning startup script
	 * Tokens are of the format ##TOKEN## and it is expected that
	 * the 'TOKEN' is uppercase.
	 *
	 * As of Version 4.2.5 of WPCD, this function also handles
	 * replacing similar tokens in EMAIL templates.
	 *
	 * @param string $script The full text of the script contents.
	 * @param array  $tokens Key-value array of tokens to replace.
	 *
	 * @return $string The updated script contents
	 */
	public function replace_script_tokens( $script, $tokens ) {
		$updated_script = $script;

		foreach ( $tokens as $name => $value ) {
			if ( is_array( $value ) ) {
				continue;
			}

			$updated_script = str_replace( '##' . strtoupper( $name ) . '##', $value, $updated_script );
		}

		return $updated_script;
	}

	/**
	 * Get script temp folder - create it if not already created
	 *
	 * Certain providers such as digital ocean allow you to execute
	 * a script from a certain path by loading it up with wget.
	 * So, we place our scripts in a temp folder and pass that foldername
	 * and script name to the provider.
	 *
	 * @return $string $temp_path temporary path based on the name of the app (which must be set by the descendent class).
	 */
	public function get_script_temp_path() {

		$dir = wp_get_upload_dir();

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		if ( ! $wp_filesystem->is_dir( trailingslashit( $dir['basedir'] ) . 'wpcd_app_' . $this->get_app_name() . '/scripts' ) ) {
			// $wp_filesystem->mkdir( trailingslashit( $dir['basedir'] ) . 'wpcd_app_' . $this->get_app_name()  . '/scripts' );
			wp_mkdir_p( trailingslashit( $dir['basedir'] ) . 'wpcd_app_' . $this->get_app_name() . '/scripts' );
		}

		$temp_path = trailingslashit( $dir['basedir'] ) . 'wpcd_app_' . $this->get_app_name() . '/scripts';

		return $temp_path;
	}

	/**
	 * Returns corresponding URI path to the script temp path from the above function
	 */
	public function get_script_temp_path_uri() {
		$dir = wp_get_upload_dir();
		return trailingslashit( $dir['baseurl'] ) . 'wpcd_app_' . $this->get_app_name() . '/scripts';
	}

	/**
	 * Deletes scripts in our scripts temporary folder if they are more than 10 minutes old.
	 * Generally, the scripts temp folder is used to place files uploaded to the server
	 * for execution as its being instantiated. 10 minutes is more than enough time for that to happen.
	 */
	public function file_watcher_delete_temp_files() {

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		set_transient( 'wpcd_file_watcher_delete_temp_files_is_active', 1, 15 * MINUTE_IN_SECONDS );

		// Set transient to check WordPress logs delete cron scheduled and loaded.
		set_transient( 'wpcd_wordpress_file_watcher_delete_temp_files_is_active', 1, 15 * MINUTE_IN_SECONDS );

		$dir = $this->get_script_temp_path();

		if ( ! $wp_filesystem->is_dir( $dir ) ) {
			return;
		}

		$files = $wp_filesystem->dirlist( $dir );
		if ( ! $files ) {
			return;
		}

		foreach ( $files as $name => $atts ) {
			if ( time() - $atts['lastmodunix'] > 10 * MINUTE_IN_SECONDS ) {
				$wp_filesystem->delete( trailingslashit( $dir ) . $atts['name'] );
			}
		}
	}

	/**
	 * Registers the ssh callback endpoint.
	 *
	 * When we send a long running command to the server via ssh
	 * we include a callback to let us know the command is complete.
	 * This registers the callback url.
	 *
	 * Examples:
	 * 1. When the server install completes
	 * Route: /wp-json/wordpress-app/v1/command/123/prepare_server/completed/123142341/ where
	 * - 123: post_id of the server
	 * - prepare_server: command name
	 * - completed: command status
	 * - 123142341: timestamp of when the callback was created
	 *
	 * 2. When an install WP completes:
	 * Route: /wp-json/wordpress-app/v1/command/123/install_wp_bcedc450f8481e89b1445069acdc3dd9/completed/123142341/ where
	 * - 123: post_id of the server
	 * - install_wp_bcedc450f8481e89b1445069acdc3dd9: command name (including the MD5 hash of the domain)
	 * - completed: command status
	 * - 123142341: timestamp of when the callback was created
	 */
	public function register_rest_endpoint() {
		register_rest_route(
			$this->get_app_name() . '/v' . wpcd_rest_version,
			'/command/(?P<id>\d+)/(?P<name>.+)/(?P<status>.+)/(?P<cmdid>\d+)/',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => array( $this, 'perform_command' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * This should be called to prep the command before it is fired (and before it updates its status with REST).
	 *
	 * @param int    $id id.
	 * @param string $name name.
	 * @param string $status status.
	 * @param array  $attributes attributes.
	 */
	protected function get_command_url( $id, $name, $status, $attributes = array() ) {
		$time = time();
		return apply_filters( 'wpcd_rest_url', rest_url( "{$this->get_app_name()}/v" . wpcd_rest_version . "/command/$id/$name/$status/$time/" ), $id, $name, $status, $attributes );
	}

	/**
	 * Checks if a command that was prepped above is valid or not and, if valid, sends back the attributes.
	 *
	 * @param int    $id id.
	 * @param string $name name.
	 */
	protected function get_command_attributes( $id, $name ) {
		$attributes = get_transient( "wpcd_command_{$this->get_app_name()}_{$id}_{$name}" );
		if ( $attributes !== false ) {
			return $attributes;
		}
		return false;
	}

	/**
	 * Checks if a command is done.
	 *
	 * This function seems to be triggered only by ajax
	 * or other explicit calls.  So using the action hooks
	 * in here is not useful for anything being done in the
	 * background - eg: via woocommerce.
	 * Alternative hooks to use are in the UPDATE_COMMAND_STATUS
	 * function.
	 *
	 * @param int    $id id.
	 * @param string $name name.
	 *
	 * Doneness is indicated by the transient having the string value of 'done'.
	 */
	protected function is_command_done( $id, $name ) {
		$attributes = get_transient( "wpcd_command_{$this->get_app_name()}_{$id}_{$name}" );
		$done       = $attributes && is_string( $attributes ) && $attributes === 'done';
		do_action( 'wpcd_log_error', "is_command_done( $id, $name ) = " . ( $done ? 'yes' : 'no' ), 'trace', __FILE__, __LINE__, null, false );

		/* Allow things to hook in when a command is completed. */
		if ( 1 === (int) $done ) {
			// Action hook: wpcd_server_wordpress-app_command_done.
			// This will allow things to hook into any action that is marked as done.
			do_action( "wpcd_server_{$this->get_app_name()}_command_done", $id, $name );

			// Action hook: example - wpcd_server_wordpress-app_prepare_server_command_done.
			// This will allow things to hook into a specific action name marked as done.
			do_action( "wpcd_server_{$this->get_app_name()}_{$name}_command_done", $id );
		}

		return $done;
	}

	/**
	 * Checks if a command is still running.
	 *
	 * If the transient has an array value, the command is deemed to be running.
	 *
	 * @param int    $id id.
	 * @param string $name name.
	 */
	protected function is_command_running( $id, $name ) {
		$attributes = get_transient( "wpcd_command_{$this->get_app_name()}_{$id}_{$name}" );
		$running    = $attributes && is_array( $attributes );
		do_action( 'wpcd_log_error', "is_command_running( $id, $name ) = " . ( $running ? 'yes' : 'no' ), 'debug', __FILE__, __LINE__, false );
		return $running;
	}

	/**
	 * Get the logs for a particular command.
	 *
	 * @param int    $id id.
	 * @param string $name name.
	 * @param bool   $persist_in_db persist_in_db.
	 * @param string $ref reference.
	 */
	protected function get_command_logs( $id, $name, $persist_in_db = true, $ref = '' ) {
		// check the status of the command.
		$attributes = $this->get_command_attributes( $id, $name );

		if ( $attributes === false ) {
			// invalid command, bail!
			return new \WP_Error( __( 'Invalid Command or Command has expired', 'wpcd' ) );
		}

		$logs = '';

		// we need to save logs in 2 parts. This first part is to quickly create the wpcd_temp_log_id with an empty log.
		if ( $persist_in_db ) {
			$log_id = get_post_meta( $id, 'wpcd_temp_log_id', true );
			if ( empty( $log_id ) ) {
				$log_id = WPCD_POSTS_COMMAND_LOG()->add_command_log_entry( $id, $name, $logs, $ref, null );
				update_post_meta( $id, 'wpcd_temp_log_id', $log_id );
			}
		}

		// if completed, then get .log.done.
		if ( is_string( $attributes ) && $attributes === 'done' ) {
			// get the log done contents.
			$logs = apply_filters( "wpcd_command_{$this->get_app_name()}_logs_done", '', $id, $name, $attributes );
		} else {
			// otherwise get .log.intermed.
			$logs = apply_filters( "wpcd_command_{$this->get_app_name()}_logs_intermed", '', $id, $name, $attributes );
		}

		// we need to save logs in 2 parts. This second part is to update the log id from wpcd_temp_log_id with the actual logs.
		if ( $persist_in_db ) {
			$log_id = get_post_meta( $id, 'wpcd_temp_log_id', true );
			if ( ! empty( $log_id ) ) {
				$log_id = WPCD_POSTS_COMMAND_LOG()->add_command_log_entry( $id, $name, $logs, $ref, $log_id );
			} else {
				// this can only happen when the polling requests have very short intervals.
				// Intervals should be increased to avoid this occurrence.
				do_action( 'wpcd_log_error', "Unable to find a log id in wpcd_temp_log_id for post $id. Increase the polling interval", 'error', __FILE__, __LINE__, false );
			}
		}

		if ( $log_id ) {
			// We need to re-retrieve the logs from the database because the process of saving them might have added data that the user needs to see on the screen.
			$logs = apply_filters( "wpcd_command_{$this->get_app_name()}_logs", WPCD_POSTS_COMMAND_LOG()->get_command_log( $log_id ) );
		}

		return $logs;

	}

	/**
	 * Get a log file.
	 *
	 * @param int    $id the id of the object or item for which we need a log file - eg: a server or app post id.
	 * @param string $name an identifier containing both the command log id and the command type separated by a '-'. We only need the ID portion.
	 */
	protected function get_old_command_logs( $id, $name ) {
		if ( ! empty( $id ) && ! empty( $name ) ) {
			$parts = explode( '-', $name );
			if ( isset( $parts[0] ) && isset( $parts[1] ) ) {
				return get_post_meta( $parts[0], 'command_result', true );
			}
		}
		return false;
	}


	/**
	 * The endpoint called when a command's status is relayed to us.
	 *
	 * @param array $params params.
	 */
	public function perform_command( WP_REST_Request $params ) {
		$data       = null;
		$name       = sanitize_text_field( $params['name'] );
		$status     = sanitize_text_field( $params['status'] );
		$command_id = filter_var( sanitize_text_field( $params['cmdid'] ), FILTER_VALIDATE_INT );
		$id         = filter_var( sanitize_text_field( $params['id'] ), FILTER_VALIDATE_INT );

		if ( ! $id ) {
			return new WP_REST_Response( array( 'error' => new WP_Error( __( 'Invalid instance', 'wpcd' ) ) ) );
		}
		if ( ! $name ) {
			return new WP_REST_Response( array( 'error' => new WP_Error( __( 'Invalid name: %s', 'wpcd' ) ) ) );
		}
		// if status is not in a limited set.
		if ( ! $status || ! in_array( $status, apply_filters( 'wpcd_command_statuses', array( 'started', 'completed', 'errored' ), $name ), true ) ) {
			return new WP_REST_Response( array( 'error' => new WP_Error( sprintf( __( 'Invalid status: %s', 'wpcd' ), $status ) ) ) );
		}
		if ( ! $command_id ) {
			return new WP_REST_Response( array( 'error' => new WP_Error( __( 'Invalid command ID', 'wpcd' ) ) ) );
		}

		$data = apply_filters( "wpcd_{$this->get_app_name()}_command_status", $data, $id, $name, $status, $command_id );
		// short-circuit the action if a non-null is returned.
		if ( ! is_null( $data ) ) {
			// if an error is raised, send it back as an error.
			if ( is_wp_error( $data ) ) {
				return new WP_REST_Response( array( 'error' => $data ) );
			}
			// otherwise send it back as a response.
			return new WP_REST_Response( array( 'data' => $data ) );
		}

		do_action( "wpcd_{$this->get_app_name()}_command_status_action", $id, $name, $status, $command_id );
		do_action( "wpcd_{$this->get_app_name()}_command_{$name}_{$status}", $id, $command_id, $name, $status );

		$this->update_command_status( $id, $name, $status );

		return new WP_REST_Response( array( 'data' => $data ) );

	}

	/**
	 * Mark the command with the status.
	 *
	 * @param int    $id id.
	 * @param string $name name.
	 * @param string $status status.
	 */
	public function update_command_status( $id, $name, $status ) {
		// check the status of the command.
		$attributes = $this->get_command_attributes( $id, $name );
		if ( $attributes === false ) {
			// invalid command, bail!
			return null;
		}

		// if the status is completed, we will mark the command as done.
		if ( $status === 'completed' ) {
			// set transient (no expiry) with a string, not an array.
			set_transient( "wpcd_command_{$this->get_app_name()}_{$id}_{$name}", 'done' );
			$this->unlock_command_mutex( $id, $name );
		} else {
			$attributes['_status'] = $status;
			// set the status and again extend the time.
			// The expiration is updated every time the command status is updated.
			// This means if a command goes through 5 status updates, it gives each status update 15 minutes to run (the function wpcd_get_long_running_command_timeout() defaults to 15 minutes but can be changed in the setttings screen).
			set_transient( "wpcd_command_{$this->get_app_name()}_{$id}_{$name}", $attributes, wpcd_get_long_running_command_timeout() * MINUTE_IN_SECONDS );
		}

		/**
		 * Trigger some action hooks
		 */
		// First, we need to extract out the base command from $name.
		$base_command = $this->get_command_base_name( $name );
		if ( ! empty( $base_command ) ) {
			// fire action hooks.
			do_action( "wpcd_command_{$this->get_app_name()}_{$base_command}_{$status}", $id, $name );  // Hook Name Example: wpcd_command_wordpress-app_install_wp_completed.
		}

		/* Fire this hook no matter what */
		do_action( "wpcd_command_{$this->get_app_name()}_{$status}", $id, $name, $base_command );  // Hook Name Example: wpcd_command_wordpress-app_completed.
	}

	/**
	 * Get Command Base Name.
	 *
	 * Given a command name of any of the following two formats, extract the base name and return it.
	 *  Format Example #1: install_wp_1608639174
	 *  Format Example #2: replace_domain---badvix05.wpvix.com---547
	 *
	 * @param string $name name.
	 */
	public function get_command_base_name( $name ) {

		if ( 'prepare_server' === $name ) {
			// sigh..why is there always an exception?  This particular command does not match format #1 or #2 so intercept and return right away!
			return $name;
		}

		if ( false === strpos( $name, '---' ) ) {
			// command is likely format #1.
			$start_of_numbers = wpcd_locate_first_number_in_string( $name );
			if ( $start_of_numbers >= 1 ) {
				// make sure the character preceeding the number is an "_".
				if ( '_' == substr( $name, $start_of_numbers - 1, 1 ) ) {
					// get the base command string.
					$base_name = substr( $name, 0, $start_of_numbers - 1 );
					return $base_name;
				}
			}
		} else {
			// command is likely format #2.
			$end_of_name_location = strpos( $name, '---' );
			if ( false === $end_of_name_location ) {
				return false;  // we really don't know what format the command is in...
			} else {
				$base_name = substr( $name, 0, $end_of_name_location );
				return $base_name;
			}
		}

		return false;

	}

	/**
	 * Mark the command as started.
	 *
	 * @param int    $id id.
	 * @param string $name name.
	 * @param bool   $lock lock.
	 */
	public function set_command_started( $id, $name, $lock = true ) {
		$attributes = array(
			'_id'     => $id,
			'_status' => 'started',
		);
		set_transient( "wpcd_command_{$this->app_name}_{$id}_{$name}", $attributes, wpcd_get_long_running_command_timeout() * MINUTE_IN_SECONDS );

		if ( $lock ) {
			$this->get_command_mutex( $id, $name );
		}
	}


	/**
	 * Encrypt a string.
	 *
	 * @param string $plainText The string to encrypt.
	 *
	 * @return string
	 */
	public static function encrypt( $plainText ) {
		return WPCD()->encrypt( $plainText );
	}

	/**
	 * Decrypt a string.
	 *
	 * @param string $encryptedText The string to decrypt.
	 *
	 * @return string
	 */
	public static function decrypt( $encryptedText ) {
		return WPCD()->decrypt( $encryptedText );
	}


	/**
	 * Adds the scripts for provider/region/size support.
	 *
	 * @TODO Anyone who wants to add the drop down support should use this, including VPN.
	 *
	 * @param bool   $get_regions get_regions.
	 * @param bool   $get_sizes get_sizes.
	 * @param string $get_provider get_provider.
	 *
	 * @return array
	 */
	public function add_provider_support( $get_regions = true, $get_sizes = true, $get_provider = '' ) {
		$provider_regions = array();
		$provider_sizes   = array();
		$clouds           = $this->get_active_providers();
		$regions          = array();
		$providers        = array();
		$sizes            = array();
		foreach ( $clouds as $provider => $name ) {
			$providers[ $provider ] = $name;

			if ( $get_regions ) {
				$locs = $this->api( $provider )->call( 'regions' );

				// if api key not provided or an error occurs, bail!
				if ( ! $locs || is_wp_error( $locs ) ) {
					continue;
				}

				// This regions array will be returned as part of our return statement.
				// It will be for either the first provider or the one specified.
				// in the parms to this-function.
				if ( ( ! empty( $get_provider ) ) ) {
					if ( $provider == $get_provider ) {
						$regions = $locs;
					}
				} else {
					if ( empty( $regions ) ) {
						$regions = $locs;
					}
				}

				$locations = array();
				foreach ( $locs as $slug => $loc ) {
					$locations[] = array(
						'slug' => $slug,
						'name' => $loc,
					);
				}
				$provider_regions[ $provider ] = $locations;
			}

			if ( $get_sizes ) {
				$capacity = $this->api( $provider )->call( 'sizes' );

				// if api key not provided or an error occurs, bail!
				if ( ! $capacity || is_wp_error( $capacity ) ) {
					continue;
				}

				// This sizes array will be returned as part of our return statement.
				// It will be for either the first provider or the one specified.
				// in the parms to this-function.
				if ( ( ! empty( $get_provider ) ) ) {
					if ( $provider == $get_provider ) {
						$sizes = $capacity;
					}
				} else {
					if ( empty( $sizes ) ) {
						$sizes = $capacity;
					}
				}
				$boxes = array();
				foreach ( $capacity as $slug => $size ) {
					$boxes[] = array(
						'slug' => $slug,
						'name' => $size,
					);
				}
				$provider_sizes[ $provider ] = $boxes;
			}
		}

		wp_register_script( 'wpcd-provider-regions-sizes', wpcd_url . 'assets/js/wpcd-provider-regions-sizes.js', array( 'jquery', 'wp-util' ), wpcd_scripts_version, true );
		wp_localize_script(
			'wpcd-provider-regions-sizes',
			'attributes',
			array(
				'provider_regions' => $provider_regions,
				'provider_sizes'   => $provider_sizes,
			)
		);
		wp_enqueue_script( 'wpcd-provider-regions-sizes' );

		return array(
			'providers' => $providers,
			'regions'   => $regions,
			'sizes'     => $sizes,
		);
	}

	/**
	 * This unlocks the instance for a command so that other commands can execute.
	 *
	 * @param int    $id id.
	 * @param string $name name.
	 */
	protected function unlock_command_mutex( $id, $name ) {
		$this->get_command_logs( $id, $name );
		delete_post_meta( $id, 'wpcd_command_mutex' );

		// delete the temp log id when the command completes.
		delete_post_meta( $id, 'wpcd_temp_log_id' );
	}

	/**
	 * This locks the instance for a command so that no other commands can execute.
	 * This also checks the instance if it is locked for a particular command.
	 *
	 * @param int    $id id.
	 * @param string $command command.
	 */
	protected function get_command_mutex( $id, $command ) {
		$mutex = get_post_meta( $id, 'wpcd_command_mutex', true );
		// no command is running; set our command.
		if ( ! $mutex ) {
			add_post_meta( $id, 'wpcd_command_mutex', $command );
			$mutex = $command;
		}

		// return if the command running is the same as the input command.
		return $mutex === $command;
	}

	/**
	 * This returns if a log button needs to be shown and if yes, for what command.
	 * Maybe the user has gone to another page and then comes back to a page where they may want to see the logs.
	 *
	 * @param int  $id id.
	 * @param bool $running_only running_only.
	 */
	public static function show_log_button_for( $id, $running_only = true ) {
		if ( $running_only ) {
			// get the mutex to find out what command is currently running, if any.
			$mutex = get_post_meta( $id, 'wpcd_command_mutex', true );
			if ( ! empty( $mutex ) ) {
				return $mutex;
			}
		} else {
			// let's see all logs that have been captured...
			$logs    = wpcd_get_child_posts( 'wpcd_command_log', $id );
			$buttons = array();
			if ( $logs ) {
				foreach ( $logs as $log ) {
					$buttons[] = $log->ID . '-' . get_post_meta( $log->ID, 'command_type', true ) . ':' . strtotime( $log->post_date );
				}
				return $buttons;
			}
		}

		return false;
	}

	/**
	 * Creates the final command that can be executed on SSH.
	 *
	 * @param array  $attributes attributes.
	 * @param string $script_name script_name.
	 * @param array  $additional additional.
	 */
	protected function turn_script_into_command( $attributes, $script_name, $additional = array() ) {
		// Set initial return command - blank.
		$run_cmd = '';

		/* first check that we are handling a server app / server - use attributes array */
		$ok_to_run = false;
		if ( isset( $attributes['app_post_id'] ) ) {
			$app_type = $this->get_app_type( $attributes['app_post_id'] );
			if ( $this->get_app_name() == $app_type ) {
				$ok_to_run = true;
			}
		}

		if ( ! $ok_to_run ) {
			if ( isset( $attributes['initial_app_name'] ) ) {
				if ( $this->get_app_name() == $attributes['initial_app_name'] ) {
					$ok_to_run = true;
				}
			}
		}

		if ( ! $ok_to_run ) {
			/* this does not belong to us so just quit now */
			return $run_cmd;
		}
		/* End first check that we are handling a basic server app / server - use attributes array */

		/* What version of the scripts are we getting? */
		$script_version = '';
		if ( isset( $attributes['scripts_version'] ) && ( ! empty( $attributes['scripts_version'] ) ) ) {
			$script_version = $attributes['scripts_version'];
		} else {
			$script_version = wpcd_get_option( "{$this->get_app_name()}_script_version" );
		}
		if ( empty( $script_version ) ) {
			$script_version = 'v1';
		}
		/* End what version of the scripts are we getting */

		/**
		 * Grab the contents of the run commands template - the text in this template is updated to replace certain ##TOKENS##
		 * and then passed into the cloud provider upon start up.  In the case of Digital Ocean, it uses the run_cmd attribute
		 * of the API to pass in these commands.
		 *
		 * We first check to see if the filename exists with the providers prefix.  If not then we try to grab one without the prefix.
		 */
		$command = $this->get_script_file_contents( apply_filters( 'wpcd_script_file_name', $script_name, $attributes ) ); // let developers override...
		if ( empty( $command ) ) {
			$command = $this->get_script_file_contents( $this->get_scripts_folder() . $script_version . '/' . $attributes['provider'] . '-' . $script_name );
		}
		if ( empty( $command ) ) {
			$command = $this->get_script_file_contents( $this->get_scripts_folder() . $script_version . '/' . $script_name );
		}
		$command = $this->add_custom_field_tokens_to_script( $script_name, $command ); // Add custom fields to command script file.
		$command = apply_filters( 'wpcd_script_file_contents', $command, $script_name ); // let developers override again...

		/* Construct an array of placeholder tokens for the run command file  */
		$place_holders = apply_filters( "wpcd_script_placeholders_{$this->get_app_name()}", array(), $script_name, $script_version, $attributes, $command, $additional );

		/* Replace the startup run command contents */
		$command = $this->replace_script_tokens( $command, $place_holders );
		if ( apply_filters( 'wpcd_dos2unix', true, $command ) ) {
			$command = dos2unix_strings( $command ); // make sure that we only have unix line endings...
		}
		return $command;
	}

	/**
	 * Register meta box(es).
	 */
	public function add_meta_boxes_background_task_details() {

		/* Only render for true admins! */
		if ( ! wpcd_is_admin() ) {
			return;
		}

		/* Only render if the settings option is turned on. */
		if ( ! (bool) wpcd_get_option( 'show-advanced-metaboxes' ) ) {
			return;
		}

		// Add an APP detail meta box into APP custom post type.
		// This will be used to show data on the background actions/tasks.
		add_meta_box(
			'app_background_action',
			__( 'Application Details: Scheduled and Background Actions Status', 'wpcd' ),
			array( $this, 'render_app_details_for_background_tasks_meta_box' ),
			'wpcd_app',
			'advanced',
			'default'
		);
	}

	/**
	 * Render the APPs detail meta box to show the background task status and details.
	 *
	 * @param object $post Current post object.
	 *
	 * @print HTML $html HTML of the meta box
	 */
	public function render_app_details_for_background_tasks_meta_box( $post ) {

		$html = '';

		$wpcd_app_action_status = get_post_meta( $post->ID, "wpcd_app_{$this->get_app_name()}_action_status", true );
		$wpcd_app_action        = get_post_meta( $post->ID, "wpcd_app_{$this->get_app_name()}_action", true );
		$wpcd_app_action_args   = wpcd_maybe_unserialize( get_post_meta( $post->ID, "wpcd_app_{$this->get_app_name()}_action_args", true ) );
		// Notice that this field does not have the word "app" in it.
		$wpcd_app_command_mutex = get_post_meta( $post->ID, 'wpcd_command_mutex', true );
		$wpcd_temp_log_id       = get_post_meta( $post->ID, 'wpcd_temp_log_id', true );

		// Convert action args array into "," separated string.
		if ( ! empty( $wpcd_app_action_args ) && is_array( $wpcd_app_action_args ) ) {
			$wpcd_app_action_args = implode( ',', $wpcd_app_action_args );
		}

		ob_start();
		require wpcd_path . 'includes/templates/app_background_tasks_details.php';
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;

	}

	/**
	 * Handles saving the meta box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return null
	 */
	public function save_meta_values_for_background_task_details( $post_id, $post ) {
		// Add nonce for security and authentication.
		$nonce_name   = filter_input( INPUT_POST, 'app_meta', FILTER_SANITIZE_STRING );
		$nonce_action = 'wpcd_app_nonce_meta_action';

		// Check if nonce is valid.
		if ( ! wp_verify_nonce( $nonce_name, $nonce_action ) ) {
			return;
		}

		// Check if user has permissions to save data.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if not an autosave.
		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Check if not a revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Make sure post type is wpcd_app.
		if ( 'wpcd_app' !== $post->post_type ) {
			return;
		}

		$wpcd_app_action_status = filter_input( INPUT_POST, 'wpcd_app_action_status', FILTER_SANITIZE_STRING );
		$wpcd_app_action        = filter_input( INPUT_POST, 'wpcd_app_action', FILTER_SANITIZE_STRING );
		$wpcd_app_action_args   = filter_input( INPUT_POST, 'wpcd_app_action_args', FILTER_SANITIZE_STRING );
		$wpcd_app_command_mutex = filter_input( INPUT_POST, 'wpcd_app_command_mutex', FILTER_SANITIZE_STRING );
		$wpcd_temp_log_id       = filter_input( INPUT_POST, 'wpcd_temp_log_id', FILTER_SANITIZE_NUMBER_INT );

		// save action arguments as array.
		if ( ! empty( $wpcd_app_action_args ) ) {
			$wpcd_app_action_args = explode( ',', $wpcd_app_action_args );
		}

		update_post_meta( $post_id, "wpcd_app_{$this->get_app_name()}_action_status", $wpcd_app_action_status );
		update_post_meta( $post_id, "wpcd_app_{$this->get_app_name()}_action", $wpcd_app_action );
		update_post_meta( $post_id, "wpcd_app_{$this->get_app_name()}_action_args", $wpcd_app_action_args );
		// Notice that this field does not have the word "app" in it.
		update_post_meta( $post_id, 'wpcd_command_mutex', $wpcd_app_command_mutex );
		update_post_meta( $post_id, 'wpcd_temp_log_id', $wpcd_temp_log_id );
	}

	/**
	 * Add content to the app summary column that shows up in app admin list.
	 * Show the application status metas
	 *
	 * Filter Hook: wpcd_app_admin_list_summary_column
	 *
	 * @param string $column_data Data to show in the column.
	 * @param int    $post_id Id of app post being displayed.
	 *
	 * @return: string $column_data
	 */
	public function app_admin_list_summary_status_column( $column_data, $post_id ) {

		/* Bail out if the app being evaluated isn't a wp app. */
		if ( $this->get_app_name() <> get_post_meta( $post_id, 'app_type', true ) ) {
			return $column_data;
		}

		/* Get app status */
		$action_status = get_post_meta( $post_id, "wpcd_app_{$this->get_app_name()}_action_status", true );
		$action        = get_post_meta( $post_id, "wpcd_app_{$this->get_app_name()}_action", true );

		/* Add it to the column data */
		if ( ! empty( $action_status ) ) {

			$our_data = '';

			$our_data = $our_data . '<b>' . __( 'Action Status: ', 'wpcd' ) . $action_status . '</b>' . '<br />';

			if ( ! empty( $action ) ) {
				$our_data = $our_data . '<em>' . __( 'Action: ', 'wpcd' ) . $action . '</em>' . '<br /><br />';
			}

			// Is our data already in the column?  If not, add it!
			if ( false === strpos( $column_data, $our_data ) ) {

				/* Put a line break to separate our data section from others if the column already contains data */
				if ( ! empty( $column_data ) ) {
					$column_data = $column_data . '<br />';
				}

				$column_data = $column_data . $our_data;

			}
		}

		return $column_data;

	}


	/**
	 * Returns a id of app for a given domain name.
	 *
	 * @param string $domain_name domain name to get the app id.
	 *
	 * @return int id of app
	 */
	public function get_app_id_by_domain_name( $domain_name ) {

		// return nothing if no domain name is provided.
		if ( ! isset( $domain_name ) || empty( $domain_name ) ) {
			return false;
		}

		// find domain name in app records.
		$app_posts = get_posts(
			array(
				'post_type'   => 'wpcd_app',
				'post_status' => 'private',
				'numberposts' => 1,
				'meta_query'  => array(
					array(
						'key'     => 'wpapp_domain',
						'value'   => $domain_name,
						'compare' => '=',
					),
				),
			)
		);

		// get app id from found result.
		$app_id = '';
		if ( ! empty( $app_posts ) ) {
			foreach ( $app_posts as $app_post ) {
				$app_id = $app_post->ID;
			}
		}

		return $app_id;

	}

	/**
	 * Get an array of replaceable fields for a user.
	 *
	 * This array is used to replace placeholder values
	 * when constructing certain emails to send to a user.
	 *
	 * @param int $user_id user id.
	 *
	 * @return array key-value array of fields with values from the user_id.
	 */
	public function get_std_email_fields_for_user( $user_id ) {

		$return = array();

		$user_info = get_userdata( $user_id );

		$return['FIRST_NAME'] = $user_info->first_name;
		$return['LAST_NAME']  = $user_info->last_name;
		$return['NICE_NAME']  = $user_info->user_nicename;
		$return['EMAIL']      = $user_info->user_email;

		return $return;

	}

}
