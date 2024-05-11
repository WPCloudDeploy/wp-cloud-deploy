<?php
/**
 * Class WP Cloud Delpoy
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_CLOUD_DEPLOY
 */
class WP_CLOUD_DEPLOY {

	/**
	 * Holds a reference to this class.
	 *
	 * @var $instance
	 */
	private static $instance;

	/**
	 * Array of providers.
	 * Add more providers here and ensure a class called class-<provider>.php exists in providers folder.
	 * Or, if adding providers via another plugin, use the filter 'wpcd_get_cloud_providers'
	 * that's defined in the getter function later in this class.
	 *
	 * @var $providers
	 */
	private static $providers = array( 'digital-ocean' => 'Digital Ocean' );

	/**
	 * Array of applications in the format array( 'appid' => 'App Desc1', 'appid2' => 'App Desc2' );
	 * This will initially be blank until an app-specific routine sets it upon initialization of its class.
	 *
	 * @var $app_list
	 */
	private $app_list = array();

	/**
	 * Holds a list of classes..
	 *
	 * @var $classes
	 */
	public $classes = array();


	/**
	 * Default encryption key if one is not set in wp-config.php
	 */
	const ENCRYPTION_KEY = '*274C07F32B66FBE3A8CD06718CD6297ADDC156A7';

	/**
	 * Create and return an instance of this class.
	 */
	public static function instance() {
		self::$instance = new self();
		return self::$instance;
	}

	/**
	 * WP_CLOUD_DEPLOY constructor.
	 */
	public function __construct() {
		// Global for backwards compatibility.
		$GLOBALS['WP_CLOUD_DEPLOY'] = $this;

		// Setup custom post types.
		$this->setup_post_types();

		// Load roles and capabilities.
		$this->load_roles_capabilities();

		// Setup WordPress hooks.
		$this->hooks();
	}

	/**
	 * Return an instance of self.
	 *
	 * @return WP_CLOUD_DEPLOY
	 */
	public function get_this() {
		return $this;
	}

	/**
	 * Hook into WordPress and other plugins as needed.
	 */
	private function hooks() {

		/* Add main menu page */
		add_action( 'admin_menu', array( &$this, 'add_main_menu_page' ) );

		// Handle proper admin menu subpages highlighting (current menu).
		add_filter( 'parent_file', array( &$this, 'handle_admin_current_main_menu' ) );
		add_filter( 'submenu_file', array( &$this, 'handle_admin_current_submenu_item' ), 10, 2 );

		// Handle main menu submenus ordering.
		add_action( 'admin_init', array( &$this, 'ensure_submenu_items_order' ), 100 );

		/* Add a global error log handler */
		add_action( 'wpcd_log_error', array( &$this, 'log_error' ), 10, 6 );

		/* Add a global notifications handler */
		add_action( 'wpcd_log_notification', array( &$this, 'log_notification' ), 10, 5 );

		/* Load admin scripts */
		add_action( 'admin_enqueue_scripts', array( &$this, 'wpcd_admin_scripts' ), 10, 1 );

		/* Add backup digital ocean provider if enabled on wp-config */
		add_filter( 'wpcd_get_cloud_providers', array( &$this, 'add_backup_digital_ocean_provider' ), 10, 1 );

		/* Remove digital ocean provider if so configured in settings */
		add_filter( 'wpcd_get_cloud_providers', array( &$this, 'remove_digital_ocean_provider' ), 10, 1 );

		/* Replace the provider descriptions with white label descriptions */
		add_filter( 'wpcd_get_cloud_providers', array( &$this, 'wpcd_get_cloud_providers_white_label_desc' ), 10, 1 );

		/* Add some license nag notices */
		add_action( 'admin_notices', array( $this, 'license_notices_server_limit' ) );
		add_action( 'admin_notices', array( $this, 'license_notices_wpsite_limit' ) );

		// Set some options that the Wisdom plugin will pick up.
		add_action( 'wpcd_wisdom_custom_options', array( $this, 'set_wisdom_custom_options' ) );

		// Capture wisdom data weekly instead of monthly.
		add_filter( 'wisdom_filter_schedule_wpcd', array( $this, 'set_wisdom_schedule' ), 10, 1 );

		// Action hook to fire on new site created on WP Multisite.
		add_action( 'wp_initialize_site', array( $this, 'wpcd_wisdom_custom_options_events' ), 10, 2 );
	}

	/**
	 * Set some Wisdom options.
	 *
	 * The option names are set at the bottom of the wpcd.php file.
	 * When Wisdom fires its data collection functions it scans for those option names.
	 * We'll set the value of those options here.
	 */
	public function set_wisdom_custom_options() {
		if ( ! get_option( 'wisdom_opt_out' ) ) {
			// User has not opted out so set options.
			$count_servers = wp_count_posts( 'wpcd_app_server' )->private;
			$count_apps    = wp_count_posts( 'wpcd_app' )->private;
			update_option(
				'wisdom_wpcd_server_count',
				array(
					'wisdom_registered_setting' => 1,
					'wisdom_wpcd_server_count'  => $count_servers,
				)
			);
			update_option(
				'wisdom_wpcd_app_count',
				array(
					'wisdom_registered_setting' => 1,
					'wisdom_wpcd_app_count'     => $count_apps,
				)
			);
		}

		// Setting a 24 hour transient but not sure that it's really necessary since it's not being used anywhere.  Might be useful later though.
		set_transient( 'wpcd_wisdom_custom_options', 1, 1440 * MINUTE_IN_SECONDS );
	}

	/**
	 * Set the wisdom collection schedule to weekly.
	 *
	 * Filter Hook: wisdom_filter_schedule_wpcd
	 *
	 * @param string $schedule Current schedule.
	 *
	 * @return string new schedule i.e.: 'weekly.
	 */
	public function set_wisdom_schedule( $schedule ) {
		return 'weekly';
	}

	/**
	 * Show license notice if number of servers exceed the amount allowed by the current license.
	 */
	public function license_notices_server_limit() {
		if ( WPCD_License::show_license_tab() ) {
			if ( ! WPCD_License::check_server_limit() ) {
				$class   = 'notice notice-error';
				$message = __( 'WPCloudDeploy: You have reached or exceeded the number of servers allowed with your license.', 'wpcd' );
				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
			}
		}
	}

	/**
	 * Show license notice if number of wpsites exceed the amount allowed by the current license.
	 */
	public function license_notices_wpsite_limit() {
		if ( WPCD_License::show_license_tab() ) {
			if ( ! WPCD_License::check_wpsite_limit() ) {
				$class   = 'notice notice-error';
				$message = __( 'WPCloudDeploy: You have reached or exceeded the number of WordPress sites allowed with your license.', 'wpcd' );
				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
			}
		}
	}

	/**
	 * Since WordPress doesn't properly handle position ordering of post type admin menu entries located in submenus,
	 * we need to manually order them.
	 *
	 * We will hook in after all the core logic has been applied to the submenu items.
	 *
	 * Note: This function use the $submenu global variable directly and therefore might break in a future version of WP.
	 *
	 * @return void
	 */
	public function ensure_submenu_items_order() {
		global $submenu;

		// We are only interested in this top-level menu item.
		if ( empty( $submenu['edit.php?post_type=wpcd_app_server'] ) || ! is_array( $submenu['edit.php?post_type=wpcd_app_server'] ) ) {
			return;
		}

		// This is the order we are after for these submenu items.
		// Any other items that are present will be left at the end, in their existing relative order.
		$submenus_order = apply_filters(
			'wpcd_main_menu_submenus_order',
			array(
				'edit.php?post_type=wpcd_app_server'     => 10,
				'edit-tags.php?taxonomy=wpcd_app_server_group&amp;post_type=wpcd_app_server' => 20,
				'edit.php?post_type=wpcd_app'            => 30,
				'edit-tags.php?taxonomy=wpcd_app_group&post_type=wpcd_app' => 40,
				'edit.php?s&post_status=all&post_type=wpcd_app&app_type=wordpress-app&filter_action=' . 'Filter' => 50,
				'edit.php?post_type=wpcd_cloud_provider' => 60,
				'edit.php?post_type=wpcd_team'           => 70,
				'edit.php?post_type=wpcd_ssh_log'        => 80,
				'edit.php?post_type=wpcd_command_log'    => 90,
				'edit.php?post_type=wpcd_pending_log'    => 100,
				'edit.php?post_type=wpcd_error_log'      => 110,
			)
		);

		// Sort the submenus by their order value, ascending.
		asort( $submenus_order, SORT_NUMERIC );

		$temp_submenu       = $submenu['edit.php?post_type=wpcd_app_server'];
		$temp_submenu_pages = array_column( $temp_submenu, 2 );
		$new_submenu        = array();
		foreach ( $submenus_order as $item_page => $item_order ) {
			$found_key = array_search( $item_page, $temp_submenu_pages );
			if ( false !== $found_key && (int) $found_key >= 0 ) {
				if ( ! empty( $temp_submenu[ $found_key ] ) ) {
					$new_submenu[] = $temp_submenu[ $found_key ];
					unset( $temp_submenu[ $found_key ] );
				}
			}
		}

		// Append any leftovers.
		if ( ! empty( $temp_submenu ) ) {
			$new_submenu = array_merge( $new_submenu, $temp_submenu );
		}

		// Overwrite the global variable.
		$submenu['edit.php?post_type=wpcd_app_server'] = $new_submenu;
	}

	/**
	 * Main Menu Item: Make the wpcd_app_server post type the main menu item
	 * by adding another option below it
	 */
	public function add_main_menu_page() {

		// If a user can manage apps but cannot manage servers, we need to make the parent menu something other than the server CPT.
		if ( current_user_can( 'wpcd_manage_apps' ) && ( ! current_user_can( 'wpcd_manage_servers' ) ) ) {
			$parent_page = 'edit.php?post_type=wpcd_app';
		} else {
			$parent_page = 'edit.php?post_type=wpcd_app_server';
		}

		if ( ( ! defined( 'WPCD_HIDE_WPAPP_MENU' ) ) || ( defined( 'WPCD_HIDE_WPAPP_MENU' ) && ! WPCD_HIDE_WPAPP_MENU ) ) {
			add_submenu_page(
				$parent_page,
				( defined( 'WPCD_WPAPP_MENU_NAME' ) ? WPCD_WPAPP_MENU_NAME : __( 'WordPress Sites', 'wpcd' ) ),
				( defined( 'WPCD_WPAPP_MENU_NAME' ) ? WPCD_WPAPP_MENU_NAME : __( 'WordPress Sites', 'wpcd' ) ),
				'wpcd_manage_apps',
				'edit.php?s&post_status=all&post_type=wpcd_app&app_type=wordpress-app&filter_action=' . 'Filter',
				'',
				3
			);
		}

		add_submenu_page(
			'edit.php?post_type=wpcd_app_server',
			__( 'App Groups', 'wpcd' ),
			__( 'App Groups', 'wpcd' ),
			'wpcd_manage_groups',
			'edit-tags.php?taxonomy=wpcd_app_group&post_type=wpcd_app',
			'',
			4
		);

		if ( ! defined( 'WPCD_HIDE_HELP_TAB' ) || ( defined( 'WPCD_HIDE_HELP_TAB' ) && ! WPCD_HIDE_HELP_TAB ) ) {
			add_submenu_page(
				'edit.php?post_type=wpcd_app_server',
				__( 'FAQ & Help', 'wpcd' ),
				__( 'FAQ & Help', 'wpcd' ),
				'manage_options',
				'wpcd_faq_and_help',
				array( $this, 'wpcd_get_faq_and_help_text_page_callback' ),
				20
			);
		}

		// Sub-menu entries will be populated by each log CPT via their 'show_in_menu' config entry.
		add_menu_page(
			__( 'Server Alerts', 'wpcd' ),
			__( 'Server Alerts', 'wpcd' ),
			'wpcd_manage_logs',
			'edit.php?post_type=wpcd_notify_log',
			'',
			'dashicons-bell',
			51
		);
	}

	/**
	 * Handle proper highlighting of current menu item.
	 *
	 * Filter Hook: parent_file
	 *
	 * @param string $parent_file The parent file.
	 *
	 * @return string The new parent file.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/parent_file/.
	 */
	public function handle_admin_current_main_menu( $parent_file ) {
		global $current_screen;

		// If we are looking at something related to the wpcd_app CPT,
		// the parent should be the wpcd_app_server menu item since we've added submenu item under it.
		if ( 'wpcd_app' === $current_screen->post_type ) {
			$parent_file = 'edit.php?post_type=wpcd_app_server';
		}

		return $parent_file;
	}

	/**
	 * Handle proper highlighting of current menu item.
	 *
	 * Filter Hook: submenu_file
	 *
	 * @param string $submenu_file The submenu file.
	 * @param string $parent_file  The submenu item's parent file.
	 *
	 * @return string The new submenu file.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/submenu_file/.
	 */
	public function handle_admin_current_submenu_item( $submenu_file, $parent_file ) {
		global $submenu_file, $current_screen;

		// If we are looking at something in under the wpcd_app_server CPT top-level menu item,
		// we have work to do.
		if ( 'edit.php?post_type=wpcd_app_server' === $parent_file ) {
			if ( 'wpcd_app_group' == $current_screen->taxonomy ) {
				// If we are dealing with the wpcd_app_group taxonomy,
				// set as the current submenu item the 'App Groups' page.
				$submenu_file = 'edit-tags.php?taxonomy=wpcd_app_group&post_type=wpcd_app';
			} elseif ( 'wpcd_app' === $current_screen->post_type
				&& 'edit' === $current_screen->base
				&& ! empty( $_GET['app_type'] ) && ! empty( $_GET['filter_action'] )
				&& 'wordpress-app' === $_GET['app_type'] ) {

				// Set as the current submenu item the WordPress Sites filter menu item.
				$submenu_file = 'edit.php?s&post_status=all&post_type=wpcd_app&app_type=wordpress-app&filter_action=' . 'Filter';
			}
		}

		return $submenu_file;
	}

	/**
	 * Construct the faq and help text to show on the FAQ & Help menu page.
	 */
	public function wpcd_get_faq_and_help_text_page_callback() {
		$help = '<div class="wpcd_faq_help_sec"><h1 class="wp-heading-inline">FAQ & Help</h1>';

		$help .= __( 'Links to commonly requested documentation.', 'wpcd' );
		$help .= '<br />';

		$help     .= '<div class="wpcd_settings_help_text_wrapper">';
			$help .= '<div class="wpcd_settings_help_text_left_column">';

				$help .= '<h2>' . __( 'Getting Started', 'wpcd' ) . '</h2>';
				$help .= '<a href="https://wpclouddeploy.com/doc-landing/">' . __( 'Popular articles', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy/introduction-to-wpcloud-deploy/">' . __( 'Quick start', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/category/release-notes/">' . __( 'Release notes', 'wpcd' ) . '</a>';

				$help .= '<h2>' . __( 'Tasks', 'wpcd' ) . '</h2>';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-user-guide/deploy-a-server/">' . __( 'Deploy a new server', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-user-guide/add-a-new-wordpress-site/">' . __( 'Deploy a new WordPress site', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-user-guide/enable-or-disable-ssl/">' . __( 'SSL Certificates', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/backups-with-aws-s3/">' . __( 'Backups with AWS S3', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/cloning-sites/">' . __( 'Cloning sites', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/sftp/">' . __( 'sFTP', 'wpcd' ) . '</a>';
				$help .= '<br />';

				$help .= '<h2>' . __( 'Troubleshooting', 'wpcd' ) . '</h2>';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy/reasons-servers-fail-to-deploy/">' . __( 'Common server deployment issues', 'wpcd' ) . '</a>';
				$help .= '<br />';

				$help .= '<h2>' . __( 'Reading', 'wpcd' ) . '</h2>';
				$help .= '<a href="https://wpclouddeploy.com/how-to-build-a-wordpress-saas-video-course-free/">' . __( 'How To Build A WordPress SaaS - Video Course', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/a-wordpress-server-sizing-guide/">' . __( 'WordPress Server Sizing Guide', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/the-five-levels-of-caching-in-wordpress/">' . __( 'Understanding WordPress Caching', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/page-cache/">' . __( 'Our NGINX Page Cache', 'wpcd' ) . '</a>';

			$help .= '</div>';

			$help     .= '<div class="wpcd_settings_help_text_middle_column">';
				$help .= '<h2>' . __( 'Teams', 'wpcd' ) . '</h2>';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-teams/introduction-to-teams/">' . __( 'Introduction', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-teams/preparing-users-for-a-team/">' . __( 'Preparing users for a team', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-teams/creating-teams/">' . __( 'Creating teams', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-teams/assigning-teams/">' . __( 'Assigning teams', 'wpcd' ) . '</a>';

				$help .= '<h2>' . __( 'Components', 'wpcd' ) . '</h2>';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-addons-and-upgrades/multisite-introduction/">' . __( 'Multisite', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/server-sync-introduction/">' . __( 'Server Sync', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/custom-servers-bring-your-own-server/">' . __( 'Custom Servers (Bring Your Own Server)', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/virtual-cloud-providers/">' . __( 'Virtual Providers', 'wpcd' ) . '</a>';

				$help .= '<h2>' . __( 'Developers', 'wpcd' ) . '</h2>';
				$help .= '<a href="https://wpclouddeploy.com/how-to-add-custom-functionality-to-wpcd-part-1/">' . __( 'Adding custom functionality part 1', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/tutorial-add-custom-functionality-to-wpcd-part-2/">' . __( 'Adding custom functionality part 2', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/tutorial-add-custom-functionality-to-wpcd-part-3/">' . __( 'Adding custom functionality part 3', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/tutorial-add-custom-functionality-to-wpcd-part-4/">' . __( 'Adding custom functionality part 4', 'wpcd' ) . '</a>';
				$help .= '<br />';
			$help     .= '</div>';

			$help     .= '<div class="wpcd_settings_help_text_right_column">';
				$help .= '<h2>' . __( 'Videos', 'wpcd' ) . '</h2>';
				$help .= '<a href="https://www.youtube.com/channel/UC-OM3lYLHMWYqkGLLy4eYFA" class="wpcd_help_button wpcd_help_button_videos">' . __( 'Videos', 'wpcd' ) . '</a>';
				$help .= '<br />';

				$help .= '<br />';
				$help .= '<h2>' . __( 'More documentation', 'wpcd' ) . '</h2>';

				$help .= '<a href="https://wpclouddeploy.com/documentation/wpcloud-deploy/introduction-to-wpcloud-deploy/" class="wpcd_help_button wpcd_help_button_all_docs">' . __( 'View All Documentation', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<br />';

				$help .= '<h2>' . __( 'Additional Resources', 'wpcd' ) . '</h2>';
				$help .= '<a href="https://www.facebook.com/groups/wp.linux.support">' . __( 'Join the private facebook group', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://twitter.com/wpclouddeploy">' . __( 'Subscribe to our Twitter feed', 'wpcd' ) . '</a>';
				$help .= '<br />';
				$help .= '<a href="https://wpclouddeploy.com/mailpoet-group/basic-subscription-form/">' . __( 'Get Email Updates', 'wpcd' ) . '</a>';

				$help .= '<br />';
				$help .= '<h2>' . __( 'Support', 'wpcd' ) . '</h2>';

				$help .= '<a href="https://wpclouddeploy.com/support/" class="wpcd_help_button wpcd_help_button_all_support">' . __( 'All Support Options', 'wpcd' ) . '</a>';
				$help .= '<br />';

			$help .= '</div>';

		$help .= '</div>';
		$help .= '</div>';

		echo apply_filters( 'wpcd_settings_help_tab_text', $help );
	}


	/**
	 * Set up post types...
	 */
	public function setup_post_types() {
		if ( empty( WPCD()->classes['wpcd_posts_app_server'] ) ) {
			WPCD()->classes['wpcd_posts_app_server'] = new WPCD_POSTS_APP_SERVER();
		}

		if ( empty( WPCD()->classes['wpcd_posts_app'] ) ) {
			WPCD()->classes['wpcd_posts_app'] = new WPCD_POSTS_APP();
		}

		if ( empty( WPCD()->classes['wpcd_posts_cloud_provider'] ) ) {
			WPCD()->classes['wpcd_posts_cloud_provider'] = new WPCD_POSTS_CLOUD_PROVIDER();
		}

		if ( empty( WPCD()->classes['wpcd_posts_ssh_log'] ) ) {
			WPCD()->classes['wpcd_posts_ssh_log'] = new WPCD_SSH_LOG();
		}

		if ( empty( WPCD()->classes['wpcd_posts_command_log'] ) ) {
			WPCD()->classes['wpcd_posts_command_log'] = new WPCD_COMMAND_LOG();
		}

		if ( empty( WPCD()->classes['wpcd_posts_pending_tasks_log'] ) ) {
			WPCD()->classes['wpcd_posts_pending_tasks_log'] = new WPCD_PENDING_TASKS_LOG();
		}

		if ( empty( WPCD()->classes['wpcd_posts_notify_log'] ) ) {
			WPCD()->classes['wpcd_posts_notify_log'] = new WPCD_NOTIFY_LOG();
		}

		if ( empty( WPCD()->classes['wpcd_posts_notify_user'] ) ) {
			WPCD()->classes['wpcd_posts_notify_user'] = new WPCD_NOTIFY_USER();
		}

		if ( empty( WPCD()->classes['wpcd_posts_notify_sent'] ) ) {
			WPCD()->classes['wpcd_posts_notify_sent'] = new WPCD_NOTIFY_SENT();
		}

		if ( empty( WPCD()->classes['wpcd_posts_error_log'] ) ) {
			WPCD()->classes['wpcd_posts_error_log'] = new WPCD_ERROR_LOG();
		}

		if ( empty( WPCD()->classes['wpcd_posts_team'] ) ) {
			WPCD()->classes['wpcd_posts_team'] = new WPCD_POSTS_TEAM();
		}

		if ( empty( WPCD()->classes['wpcd_posts_permission_type'] ) ) {
			WPCD()->classes['wpcd_posts_permission_type'] = new WPCD_POSTS_PERMISSION_TYPE();
		}
	}

	/**
	 * Load css and js scripts
	 *
	 * Action hook: admin_enqueue_scripts
	 *
	 * Note: Even though this is loaded on an admin hook, the PUBLIC class
	 * will manually call this function to get these scripts loaded up on the
	 * frontend as well!
	 *
	 * @param string $hook hook.
	 */
	public function wpcd_admin_scripts( $hook ) {

		// What screen are we on?
		$screen = get_current_screen();

		/* Inject brand colors into global style sheet. */
		$this->wpcd_inject_brand_color_styles();

		/* Inject custom css. */
		$this->wpcd_inject_custom_css();

		/* Do not allow auto-saves on any wpcd-screen. */
		if ( is_object( $screen ) && wpcd_str_starts_with( $screen->post_type, 'wpcd_' ) ) {
			wp_dequeue_script( 'autosave' );
		}

		/* Cloud Providers Screen - Uses the settings screen style sheet for now. */
		if ( is_object( $screen ) && in_array( $screen->post_type, array( 'wpcd_cloud_provider' ) ) ) {
			wp_enqueue_style( 'wpcd-admin-settings', wpcd_url . 'assets/css/wpcd-admin-settings.css', array(), wpcd_scripts_version );
		}

		/* CSS common to server and app screens. */
		if ( is_object( $screen ) && in_array( $screen->post_type, array( 'wpcd_app_server', 'wpcd_app' ) ) ) {
			wp_enqueue_style( 'wpcd-common-admin', wpcd_url . 'assets/css/wpcd-common-admin.css', array(), wpcd_scripts_version );
		}

		/* Style sheet for the settings screen. */
		if ( 'wpcd_app_server_page_wpcd_settings' === $hook ||
			'wpcd_app_server_page_wpcd_faq_and_help' === $hook ) {
			wp_enqueue_style( 'wpcd-admin-settings', wpcd_url . 'assets/css/wpcd-admin-settings.css', array(), wpcd_scripts_version );
			if ( defined( 'WPCD_SKIP_SERVER_SIZES_SETTING' ) && WPCD_SKIP_SERVER_SIZES_SETTING ) {
				wp_enqueue_style( 'wpcd-admin-settings-server-sizes', wpcd_url . 'assets/css/wpcd-admin-settings-server-sizes.css', array(), wpcd_scripts_version );
			}
		}

	}

	/**
	 * Add the wp-admin and front-end brand color styles
	 * as inline styles to the global style sheet.
	 *
	 * Called from function wpcd_admin_scripts which is hooked into action admin_enqueue_scripts
	 */
	public function wpcd_inject_brand_color_styles() {

		// Get brand color settings for the wp-admin area.
		$primary_brand_color = wpcd_get_option( 'wordpress_app_primary_brand_color' );
		$primary_brand_color = empty( $primary_brand_color ) ? WPCD_PRIMARY_BRAND_COLOR : $primary_brand_color;

		$secondary_brand_color = wpcd_get_option( 'wordpress_app_secondary_brand_color' );
		$secondary_brand_color = empty( $secondary_brand_color ) ? WPCD_SECONDARY_BRAND_COLOR : $secondary_brand_color;

		$tertiary_brand_color = wpcd_get_option( 'wordpress_app_tertiary_brand_color' );
		$tertiary_brand_color = empty( $tertiary_brand_color ) ? WPCD_TERTIARY_BRAND_COLOR : $tertiary_brand_color;

		$accent_bg_color = wpcd_get_option( 'wordpress_app_accent_background_color' );
		$accent_bg_color = empty( $accent_bg_color ) ? WPCD_ACCENT_BG_COLOR : $accent_bg_color;

		$medium_accent_bg_color = wpcd_get_option( 'wordpress_app_medium_accent_background_color' );
		$medium_accent_bg_color = empty( $medium_accent_bg_color ) ? WPCD_MEDIUM_ACCENT_BG_COLOR : $medium_accent_bg_color;

		$medium_bg_color = wpcd_get_option( 'wordpress_app_medium_background_color' );
		$medium_bg_color = empty( $medium_bg_color ) ? WPCD_MEDIUM_BG_COLOR : $medium_bg_color;

		$light_bg_color = wpcd_get_option( 'wordpress_app_light_background_color' );
		$light_bg_color = empty( $light_bg_color ) ? WPCD_LIGHT_BG_COLOR : $light_bg_color;

		$alternate_accent_bg_color = wpcd_get_option( 'wordpress_app_alternate_accent_background_color' );
		$alternate_accent_bg_color = empty( $alternate_accent_bg_color ) ? WPCD_ALTERNATE_ACCENT_BG_COLOR : $alternate_accent_bg_color;

		$positive_color = wpcd_get_option( 'wordpress_app_positive_color' );
		$positive_color = empty( $positive_color ) ? WPCD_POSITIVE_COLOR : $positive_color;

		$negative_color = wpcd_get_option( 'wordpress_app_negative_color' );
		$negative_color = empty( $negative_color ) ? WPCD_NEGATIVE_COLOR : $negative_color;

		$alt_negative_color = wpcd_get_option( 'wordpress_app_alt_negative_color' );
		$alt_negative_color = empty( $alt_negative_color ) ? WPCD_ALT_NEGATIVE_COLOR : $alt_negative_color;

		$white_color = wpcd_get_option( 'wordpress_app_white_color' );
		$white_color = empty( $white_color ) ? WPCD_WHITE_COLOR : $white_color;

		$terminal_bg_color = wpcd_get_option( 'wordpress_app_terminal_background_color' );
		$terminal_bg_color = empty( $terminal_bg_color ) ? WPCD_TERMINAL_BG_COLOR : $terminal_bg_color;

		$terminal_fg_color = wpcd_get_option( 'wordpress_app_terminal_foreground_color' );
		$terminal_fg_color = empty( $terminal_fg_color ) ? WPCD_TERMINAL_FG_COLOR : $terminal_fg_color;

		// Get brand color settings for the front-end.
		$primary_brand_color_fe = wpcd_get_option( 'wordpress_app_fe_primary_brand_color' );
		$primary_brand_color_fe = empty( $primary_brand_color_fe ) ? WPCD_FE_PRIMARY_BRAND_COLOR : $primary_brand_color_fe;

		$secondary_brand_color_fe = wpcd_get_option( 'wordpress_app_fe_secondary_brand_color' );
		$secondary_brand_color_fe = empty( $secondary_brand_color_fe ) ? WPCD_FE_SECONDARY_BRAND_COLOR : $secondary_brand_color_fe;

		$tertiary_brand_color_fe = wpcd_get_option( 'wordpress_app_fe_tertiary_brand_color' );
		$tertiary_brand_color_fe = empty( $tertiary_brand_color_fe ) ? WPCD_FE_TERTIARY_BRAND_COLOR : $tertiary_brand_color_fe;

		$accent_bg_color_fe = wpcd_get_option( 'wordpress_app_fe_accent_background_color' );
		$accent_bg_color_fe = empty( $accent_bg_color_fe ) ? WPCD_FE_ACCENT_BG_COLOR : $accent_bg_color_fe;

		$medium_accent_bg_color_fe = wpcd_get_option( 'wordpress_app_fe_medium_accent_background_color' );
		$medium_accent_bg_color_fe = empty( $medium_accent_bg_color_fe ) ? WPCD_FE_MEDIUM_ACCENT_BG_COLOR : $medium_accent_bg_color_fe;

		$medium_bg_color_fe = wpcd_get_option( 'wordpress_app_fe_medium_background_color' );
		$medium_bg_color_fe = empty( $medium_bg_color_fe ) ? WPCD_FE_MEDIUM_BG_COLOR : $medium_bg_color_fe;

		$light_bg_color_fe = wpcd_get_option( 'wordpress_app_fe_light_background_color' );
		$light_bg_color_fe = empty( $light_bg_color_fe ) ? WPCD_FE_LIGHT_BG_COLOR : $light_bg_color_fe;

		$alternate_accent_bg_color_fe = wpcd_get_option( 'wordpress_app_fe_alternate_accent_background_color' );
		$alternate_accent_bg_color_fe = empty( $alternate_accent_bg_color_fe ) ? WPCD_FE_ALTERNATE_ACCENT_BG_COLOR : $alternate_accent_bg_color_fe;

		$positive_color_fe = wpcd_get_option( 'wordpress_app_fe_positive_color' );
		$positive_color_fe = empty( $positive_color_fe ) ? WPCD_FE_POSITIVE_COLOR : $positive_color_fe;

		$negative_color_fe = wpcd_get_option( 'wordpress_app_fe_negative_color' );
		$negative_color_fe = empty( $negative_color_fe ) ? WPCD_FE_NEGATIVE_COLOR : $negative_color_fe;

		$alt_negative_color_fe = wpcd_get_option( 'wordpress_app_fe_alt_negative_color' );
		$alt_negative_color_fe = empty( $alt_negative_color_fe ) ? WPCD_FE_ALT_NEGATIVE_COLOR : $alt_negative_color_fe;

		$white_color_fe = wpcd_get_option( 'wordpress_app_fe_white_color' );
		$white_color_fe = empty( $white_color_fe ) ? WPCD_FE_WHITE_COLOR : $white_color_fe;

		/* Global style sheet. */
		wp_enqueue_style( 'wpcd-global-css', wpcd_url . 'assets/css/wpcd-global.css', array(), wpcd_scripts_version );

		/* Define brand colors */
		$global_css = ":root {
			--wpcd-primary-brand-color: {$primary_brand_color};
			--wpcd-secondary-brand-color: {$secondary_brand_color};
			--wpcd-tertiary-brand-color: {$tertiary_brand_color};
			--wpcd-accent-background-color: {$accent_bg_color};
			--wpcd-medium-accent-background-color: {$medium_accent_bg_color};
			--wpcd-medium-background-color: {$medium_bg_color};
			--wpcd-light-background-color: {$light_bg_color};
			--wpcd-alternate-accent-background-color: {$alternate_accent_bg_color};
			--wpcd-positive-color: {$positive_color};			
			--wpcd-negative-color: {$negative_color};
			--wpcd-alt-negative-color: {$alt_negative_color};
			--wpcd-white-color: {$white_color};
			--wpcd-terminal-background-color: {$terminal_bg_color};			
			--wpcd-terminal-foreground-color: {$terminal_fg_color};						

			--wpcd-front-end-primary-brand-color: {$primary_brand_color_fe};
			--wpcd-front-end-secondary-brand-color: {$secondary_brand_color_fe};
			--wpcd-front-end-tertiary-brand-color: {$tertiary_brand_color_fe};
			--wpcd-front-end-medium-accent-background-color: {$medium_accent_bg_color_fe};
			--wpcd-front-end-accent-background-color: {$accent_bg_color_fe};
			--wpcd-front-end-medium-background-color: {$medium_bg_color_fe};
			--wpcd-front-end-light-background-color: {$light_bg_color_fe};
			--wpcd-front-end-alternate-accent-background-color: {$alternate_accent_bg_color_fe};
			--wpcd-front-end-positive-color: {$positive_color_fe};
			--wpcd-front-end-negative-color: {$negative_color_fe};
			--wpcd-front-end-alt-negative-color: {$alt_negative_color_fe};
			--wpcd-front-end-white-color: {$white_color_fe};
		}";

		/* Add some global css. */
		wp_add_inline_style( 'wpcd-global-css', $global_css );

	}

	/**
	 * Add custom css defined in settings
	 * as inline styles to the global style sheet.
	 *
	 * Called from function wpcd_admin_scripts which is hooked into action admin_enqueue_scripts
	 */
	public function wpcd_inject_custom_css() {

		/* Global style sheet. */
		wp_enqueue_style( 'wpcd-global-css', wpcd_url . 'assets/css/wpcd-global.css', array(), wpcd_scripts_version );

		$global_css = wpcd_get_early_option( 'wordpress-app-custom-css-override' );

		/* Add to global css. */
		if ( ! empty( $global_css ) ) {
			wp_add_inline_style( 'wpcd-global-css', $global_css );
		}
	}


	/**
	 * Get the list of operating systems that we can install on a server.
	 *
	 * @since 4.2.5
	 *
	 * @return array
	 */
	public static function get_os_list() {
		$oslist = array(
			'ubuntu2204lts' => __( 'Ubuntu 22.04 LTS', 'wpcd' ),
			'ubuntu2004lts' => __( 'Ubuntu 20.04 LTS', 'wpcd' ),
			'ubuntu2404lts' => __( 'Ubuntu 24.04 LTS (Important Restrictions - See Docs!)', 'wpcd' ),
		);

		// Remove some entries based on settings.
		if ( (bool) wpcd_get_option( 'wordpress_app_disable_ubuntu_lts_2004' ) ) {
			unset( $oslist['ubuntu2004lts'] );
		}
		if ( (bool) wpcd_get_option( 'wordpress_app_disable_ubuntu_lts_2204' ) ) {
			unset( $oslist['ubuntu2204lts'] );
		}

		if ( (bool) wpcd_get_option( 'wordpress_app_disable_ubuntu_lts_2404' ) ) {
			unset( $oslist['ubuntu2404lts'] );
		}

		// Add in some optional entries if necessary.
		if ( (bool) wpcd_get_option( 'wordpress_app_enable_ubuntu_lts_1804' ) ) {
			$oslist['ubuntu1804lts'] = __( 'Ubuntu 18.04 LTS (Deprecated, EOL April 2023)', 'wpcd' );
		}

		// Return filtered array.
		return apply_filters( 'wpcd_os_list', $oslist );
	}

	/**
	 * Get the list of web servers that we can install on a server.
	 *
	 * @since 4.8.2
	 *
	 * @return array
	 */
	public static function get_webserver_list() {
		$webserver_list = array(
			'nginx' => __( 'NGINX', 'wpcd' ),
			'ols'   => __( 'OpenLiteSpeed (Beta)', 'wpcd' ),
			// 'ols-enterprise' => __( 'LiteSpeed Enterprise (Beta)', 'wpcd' ),
		);
		return apply_filters( 'wpcd_webserver_list', $webserver_list );
	}


	/**
	 * Get the operating system name (full name) initially installed on a server
	 *
	 * @since 4.2.5
	 *
	 * @param string $os The os id / key.
	 *
	 * @return string
	 */
	public static function get_os_description( $os ) {

		$return = $os;

		switch ( $os ) {
			case 'ubuntu1804lts':
				$return = __( 'Ubuntu 18.04 LTS', 'wpcd' );
				break;

			case 'ubuntu2004lts':
				$return = __( 'Ubuntu 20.04 LTS', 'wpcd' );
				break;

			case 'ubuntu2204lts':
				$return = __( 'Ubuntu 22.04 LTS', 'wpcd' );
				break;

			case 'ubuntu2404lts':
				$return = __( 'Ubuntu 24.04 LTS', 'wpcd' );
				break;

		}

		return $return;

	}


	/**
	 * Encrypt a string.
	 *
	 * @since 4.1.0
	 *
	 * @param string $plainText The string to encrypt.
	 * @param string $inkey A key to use to encrypt the incoming text.  If none is supplied use the pre-stored key in wpconfig or the database.
	 *
	 * @return string
	 */
	public static function encrypt( $plainText, $inkey = '' ) {
		/* Get the encryption key */
		$key = $inkey;
		if ( empty( $key ) ) {
			// No key passed into the function so see if one was defined globally - usually via wp-config.php.
			if ( defined( 'WPCD_ENCRYPTION_KEY' ) ) {
				$key = WPCD_ENCRYPTION_KEY;
			}
		}
		if ( empty( $key ) ) {
			// still no key so check for option value...
			$key = get_option( 'wpcd_encryption_key_v2' );
		}
		if ( empty( $key ) ) {
			// ok, we don't have a key at all anywhere.
			// so generate one and add it to wp options.
			$len = openssl_cipher_iv_length( $cipher = 'AES-128-CBC' );
			$key = $key = wpcd_random_str( $len );  // Do NOT use openssl_random_pseudo_bytes($len) since WordPress is not retrieving this value from options properly! We store it but WP retrieves blank so do not use it.
			update_option( 'wpcd_encryption_key_v2', $key );
		}

		/* Get an initialization vector */
		$ivlen = openssl_cipher_iv_length( $cipher = 'AES-128-CBC' );
		$iv    = openssl_random_pseudo_bytes( $ivlen );

		/* Generate the raw cipher text */
		$secret_key     = md5( $key );
		$ciphertext_raw = openssl_encrypt( $plainText, 'AES-128-CBC', $secret_key, OPENSSL_RAW_DATA, $iv );

		/* Add hmac */
		$hmac = hash_hmac( 'sha256', $ciphertext_raw, $secret_key, $as_binary = true );

		/* final return value - this value concatenates the encrypted text with the iv and the hmac */
		$ciphertext = 'wpcd-encrypt-v2:' . base64_encode( $iv . $hmac . $ciphertext_raw );

		return $ciphertext;
	}

	/**
	 * Encrypt a string - version 1
	 *
	 * This was the original version before we changed it
	 * to use a random initialization vector.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plainText The string to encrypt.
	 *
	 * @return string
	 */
	public static function encrypt_v1( $plainText ) {
		$key = self::ENCRYPTION_KEY;
		if ( defined( 'WPCD_ENCRYPTION_KEY' ) ) {
			$key = WPCD_ENCRYPTION_KEY;
		}
		$secret_key = md5( $key );
		$iv         = substr( hash( 'sha256', 'aaaabbbbcccccddddeweee' ), 0, 16 );
		return base64_encode( openssl_encrypt( $plainText, 'AES-128-CBC', $secret_key, OPENSSL_RAW_DATA, $iv ) );
	}

	/**
	 * Decrypt a string.
	 *
	 * @since 4.1.0
	 *
	 * @param string $encryptedText The string to decrypt.
	 * @param string $inkey A key to use to encrypt the incoming text.  If none is supplied use the pre-stored key in wpconfig or the database.
	 *
	 * @return string
	 */
	public static function decrypt( $encryptedText, $inkey = '' ) {

		// Make sure we have something to encrypt.
		if ( empty( $encryptedText ) ) {
			return $encryptedText;
		}

		// Check to see if encryption used is v1 or v2.
		if ( strpos( $encryptedText, 'wpcd-encrypt-v2:' ) !== false ) {
			$version = 'v2';
		} else {
			$version = 'v1';
		}

		switch ( $version ) {
			case 'v1':
				return self::decrypt_v1( $encryptedText );
				break;
			case 'v2':
				return self::decrypt_v2( $encryptedText, $inkey );
				break;
			default:
				return '';
		}

		return '';

	}

	/**
	 * Decrypt a string - v1
	 *
	 * Decrypt a string using the Version 1 algorithm with a fixed initialization vector.
	 *
	 * @since 1.0.0
	 *
	 * @param string $encryptedText The string to decrypt.
	 *
	 * @return string
	 */
	public static function decrypt_v1( $encryptedText ) {
		$key = self::ENCRYPTION_KEY;
		if ( defined( 'WPCD_ENCRYPTION_KEY' ) ) {
			$key = WPCD_ENCRYPTION_KEY;
		}

		$secret_key = md5( $key );
		$iv         = substr( hash( 'sha256', 'aaaabbbbcccccddddeweee' ), 0, 16 );
		return openssl_decrypt( base64_decode( $encryptedText ), 'AES-128-CBC', $secret_key, OPENSSL_RAW_DATA, $iv );
	}

	/**
	 * Decrypt a string - v2
	 *
	 * Decrypt a string using the Version 2 algorithm with a variable initialization vector.
	 *
	 * @since 4.1.0
	 *
	 * @param string $encryptedText The string to decrypt.
	 * @param string $inkey A key to use to encrypt the incoming text.  If none is supplied use the pre-stored key in wpconfig or the database.
	 *
	 * @return string
	 */
	public static function decrypt_v2( $encryptedText, $inkey = '' ) {

		/* Get the encryption key */
		$key = $inkey;
		if ( empty( $key ) ) {
			// No key passed into the function so see if one was defined globally - usually via wp-config.php.
			if ( defined( 'WPCD_ENCRYPTION_KEY' ) ) {
				$key = WPCD_ENCRYPTION_KEY;
			}
		}
		if ( empty( $key ) ) {
			// Still no key so check for option value.
			$key = get_option( 'wpcd_encryption_key_v2' );
		}

		/* Remove the v2 prefix from the encrypted string */
		$encryptedText = str_replace( 'wpcd-encrypt-v2:', '', $encryptedText );

		/* get the raw cipher after removing iv and such  */
		$c              = base64_decode( $encryptedText );
		$ivlen          = openssl_cipher_iv_length( $cipher = 'AES-128-CBC' );
		$iv             = substr( $c, 0, $ivlen );
		$hmac           = substr( $c, $ivlen, $sha2len = 32 );
		$ciphertext_raw = substr( $c, $ivlen + $sha2len );

		/* Decrypt the text */
		$secret_key         = md5( $key );
		$original_plaintext = openssl_decrypt( $ciphertext_raw, $cipher, $secret_key, $options = OPENSSL_RAW_DATA, $iv );

		/* Check hmac */
		$calcmac = hash_hmac( 'sha256', $ciphertext_raw, $secret_key, $as_binary = true );
		if ( hash_equals( $hmac, $calcmac ) ) {
			return $original_plaintext;
		} else {
			return '';
		}

	}

	/**
	 * Return an array of sensitive values
	 * and what to replace them with.
	 *
	 * @return array
	 */
	public function wpcd_get_pw_terms_to_clean() {

		$terms = apply_filters(
			'wpcd_get_pw_terms_to_clean',
			array(
				'wp_password='                => '(***private***)',
				'wps_new_password='           => '(***private***)',
				'aws_access_key_id='          => '(***private***)',
				'aws_secret_access_key='      => '(***private***)',
				'--admin_password='           => '(***private***)',
				'pass='                       => '(***private***)',
				'remote_dbpass='              => '(***private***)',
				'local_dbpass='               => '(***private***)',

				'dns_cloudflare_api_token='   => '(***private***)',
				'dns_cloudflare_api_key='     => '(***private***)',
				'secret_key_manager_api_key=' => '(***private***)',
				'git_token='                  => '(***private***)',

				'dns_cloudflare_api_token'    => '(***private***)',
				'dns_cloudflare_api_key'      => '(***private***)',
				'secret_key_manager_api_key'  => '(***private***)',
				'git_token'                   => '(***private***)',

				'logtivity_teams_api_key='    => '(***private***)',
				'ubuntu_pro_token='           => '(***private***)',
				"Updated the constant 'DB_PASSWORD' in the 'wp-config.php' file with the value " => '(***private***)' . PHP_EOL,

			)
		);

		return $terms;

	}

	/**
	 * Write errors out to the error log
	 *
	 * @param string $msg message to write.
	 * @param string $type type of message.
	 * @param string $file filename generating message.
	 * @param string $line linenumber related to the message being written.
	 * @param string $data optional data that will be logged to a separate field when writing to the database.
	 * @param bool   $force If this is true, it will log ALWAYS. Useful for temporary debugging.
	 */
	public function log_error( $msg, $type, $file, $line, $data = '', $force = false ) {

		// Remove known password strings.
		$pwarray = $this->wpcd_get_pw_terms_to_clean();
		$msg     = wpcd_replace_key_value_paired_strings( $pwarray, $msg );

		// Log to debug.log file.
		$debug_flag            = defined( 'WPCD_DEBUG' ) ? WPCD_DEBUG : false;
		$log_options_debug_log = wpcd_get_early_option( 'logging_and_tracing_types_debug_log' );
		if ( $force || ( in_array( $type, array( 'debug', 'trace' ), true ) && $debug_flag ) || in_array( $type, array( 'error', 'warn' ), true ) || ( $log_options_debug_log && in_array( $type, $log_options_debug_log ) ) ) {
			error_log( sprintf( '%s:%d:(%s) - %s', basename( $file ), $line, $type, $msg ) );
		}

		// Log to database?  Note that you can log to the database and NOT log to the error log file.  They are independent!
		$log_options = wpcd_get_early_option( 'logging_and_tracing_types' );
		if ( $log_options && in_array( $type, $log_options ) ) {
			WPCD_POSTS_ERROR_LOG()->add_error_log_entry( $msg, $type, $file, $line, $data );
		}

	}

	/**
	 * Add a new Notification Log record
	 *
	 * @param int    $parent_post_id The post id that represents the item this log is being done against.
	 * @param string $notification_type The type of notification.
	 * @param string $message The notification message itself.
	 * @param string $notification_reference any additional or third party reference.
	 * @param int    $post_id The ID of an existing log, if it exists.
	 */
	public function log_notification( $parent_post_id, $notification_type, $message, $notification_reference, $post_id = null ) {
		WPCD_POSTS_NOTIFY_LOG()->add_notify_log_entry( $parent_post_id, $notification_type, $message, $notification_reference, $post_id );
	}


	/**
	 * Return a requested provider object
	 *
	 * @param string $provider name of provider.
	 *
	 * @return VPN_API_Provider_{provider}()
	 *
	 * This will allow additional providers to be added via plugins.
	 */
	public function get_provider_api( $provider ) {
		if ( empty( $provider ) ) {
			return null;
		}

		/* Folders of array of locations to search for an api class */
		$provider_paths = apply_filters( 'wpcd_cloud_provider_class_path', array( wpcd_path . 'includes/core/providers/' ) );

		if ( empty( WPCD()->classes[ 'wpcd_vpn_api_provider_' . $provider ] ) ) {

			/* Search through folders looking for a provider class file */
			foreach ( $provider_paths as $provider_path ) {
				$provider_file = $provider_path . 'class-' . $provider . '.php';
				if ( file_exists( $provider_file ) ) {
					require_once $provider_file;
				}
			}
			$class = 'CLOUD_PROVIDER_API_' . str_replace( ' ', '', ucwords( str_replace( array( '-', '_' ), ' ', $provider ) ) );
			if ( class_exists( $class ) ) {
				WPCD()->classes[ 'wpcd_vpn_api_provider_' . $provider ] = new $class();
			} else {
				// This might be a virtual cloud provider stored in the wpcd_cloud_provider custom post type...
				if ( ! $this->setup_virtual_cloud_provider( $provider, $provider_paths ) ) {
					return null;
				}
			}
		}

		return WPCD()->classes[ 'wpcd_vpn_api_provider_' . $provider ];

	}

	/**
	 * Given a provider and a list of paths to search, see if the provider
	 * exists in the wpcd_cloud_provider post type.  If it does, grab
	 * its real api data (aka "cloud provider type") and instantiate a
	 * new instance of the class with data that matches the data in the
	 * wpcd_cloud_provider post type.
	 *
	 * @param string $provider name of provider (slug).
	 * @param string $provider_paths provider_paths.
	 *
	 * @return boolean true if class could be instantiated for the provider, false otherwise.
	 */
	public function setup_virtual_cloud_provider( $provider, $provider_paths ) {

		$args = array(
			'post_type'      => 'wpcd_cloud_provider',
			'posts_per_page' => -1,
			'meta_key'       => 'wpcd_cloud_provider_slug',
			'meta_value'     => $provider,
		);

		$post = get_posts( $args );

		// if we have a match to the provider...
		if ( $post ) {

			// grab some data off the post record...
			$provider_type = get_post_meta( $post[0]->ID, 'wpcd_cloud_provider_type', true );
			$inactive      = boolval( get_post_meta( $post[0]->ID, 'wpcd_cloud_provider_inactive', true ) );

			// $args is used to pass the newly instantiated class some data into its constructor.
			$args = array(
				'provider_name'           => $post[0]->post_title,
				'provider_slug'           => get_post_meta( $post[0]->ID, 'wpcd_cloud_provider_slug', true ),
				'provider_region_prefix'  => get_post_meta( $post[0]->ID, 'wpcd_cloud_provider_region_prefix', true ),
				'provider_dashboard_link' => get_post_meta( $post[0]->ID, 'wpcd_cloud_provider_dashboard_link', true ),
			);

			// See if we can find a class with the provider type - logic is very very similar to that of the get_provider_api function.
			/* Search through folders looking for a provider class file */
			foreach ( $provider_paths as $provider_path ) {
				$provider_file = $provider_path . 'class-' . $provider_type . '.php';
				if ( file_exists( $provider_file ) ) {
					require_once $provider_file;
				}
			}

			$class = 'CLOUD_PROVIDER_API_' . str_replace( ' ', '', ucwords( str_replace( array( '-', '_' ), ' ', $provider_type ) ) );
			if ( class_exists( $class ) ) {
				WPCD()->classes[ 'wpcd_vpn_api_provider_' . $provider ] = new $class( $args );
				return true;
			}
		}
		return false;
	}

	/**
	 * Return the array of providers
	 */
	public function get_cloud_providers() {
		return apply_filters( 'wpcd_get_cloud_providers', self::$providers );
	}

	/**
	 * Remove digital ocean from array of providers.
	 *
	 * Filter hook: wpcd_get_cloud_providers
	 *
	 * @param array $providers_list providers_list.
	 */
	public function remove_digital_ocean_provider( $providers_list ) {

		if ( wpcd_get_early_option( 'hide-do-provider' ) ) {
			unset( $providers_list['digital-ocean'] );
		}

		return $providers_list;

	}


	/**
	 * Add backup digital ocean provider
	 *
	 * Filter hook: wpcd_get_cloud_providers
	 *
	 * @param array $providers_list providers_list.
	 */
	public function add_backup_digital_ocean_provider( $providers_list ) {

		if ( defined( 'WPCD_LOAD_BACKUP_DO_PROVIDER' ) && ( true === WPCD_LOAD_BACKUP_DO_PROVIDER ) ) {
			$providers_list['digital-ocean-alternate'] = __( 'Digital Ocean Backup', 'wpcd' );
		}

		return $providers_list;

	}

	/**
	 * Replace the provider descriptions with white label descriptions
	 *
	 * Filter hook: wpcd_get_cloud_providers
	 *
	 * @param array $providers_list Key-value array of providers.
	 *
	 * @return array
	 */
	public function wpcd_get_cloud_providers_white_label_desc( $providers_list ) {

		foreach ( $providers_list as $provider => $desc ) {
			$label = wpcd_get_early_option( "vpn_{$provider}_alt_desc" );
			if ( ! empty( $label ) ) {
				$providers_list[ $provider ] = $label;
			}
		}

		return $providers_list;

	}

	/**
	 * Return the description for a provider.
	 * It takes into account any white-label settings.
	 *
	 * @param string $provider   Provider id.
	 *
	 * @return string
	 */
	public function wpcd_get_cloud_provider_desc( $provider ) {

		$providers = WPCD()->get_cloud_providers();

		if ( isset( $providers[ $provider ] ) ) {
			$provider_desc = $providers[ $provider ];
			if ( empty( $provider_desc ) ) {
				$provider_desc = $provider;
			}
		} else {
			$provider_desc = $provider;
		}

		return $provider_desc;

	}

	/**
	 * Return the array of ACTIVE providers
	 *
	 * An active provider is one whose credentials have been entered.
	 * Later we might be more strict with the definition.
	 */
	public function get_active_cloud_providers() {

		$providers = $this->get_cloud_providers();

		foreach ( $providers as $provider => $provider_name ) {
			// Can you get a valid api?  If not unset.
			if ( empty( $this->get_provider_api( $provider ) ) ) {
				unset( $providers[ $provider ] );
				continue;
			}

			// If we got here, we have a valid api so check to see if credentials provided.
			if ( ! $this->get_provider_api( $provider )->credentials_available() ) {
				unset( $providers[ $provider ] );
			}
		}

		return apply_filters( 'wpcd_get_active_cloud_providers', $providers );

	}

	/**
	 * Return default encryption key
	 */
	public function get_default_encryption_key() {
		return self::ENCRYPTION_KEY;
	}

	/**
	 * Adds an app id and description to the application list var.
	 *
	 * Array format: array( 'appid' => 'App Desc1', 'appid2' => 'App Desc2' )
	 *
	 * @param array $app_id keyed array of app id and a description.
	 */
	public function set_app_id( $app_id ) {
		$this->app_list[] = $app_id;
	}

	/**
	 * Return a list of applications
	 * This list is actually filled out by other app-specific routines
	 */
	public function get_app_list() {
		return apply_filters( 'wpcd_app_list', $this->app_list );
	}

	/**
	 * Loads roles and capabilites
	 *
	 * @return void
	 */
	public function load_roles_capabilities() {
		if ( empty( WPCD()->classes['wpcd_roles_capabilities'] ) ) {
			WPCD()->classes['wpcd_roles_capabilities'] = new WPCD_ROLES_CAPABILITIES();
		}
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
	 * Returns whether a site is a staging site.
	 *
	 * Note: We're commingling some things here because
	 * only the wordpres-app has the concept of "staging".
	 * So this should be in that app class but putting
	 * it here because we need it in some global functions.
	 * And there is always the possibility that a future app
	 * will have both staging and production types.
	 *
	 * @param int $app_id ID of app being interrogated.
	 *
	 * @return boolean
	 */
	public function is_staging_site( $app_id ) {

		$is_staging = (int) get_post_meta( $app_id, 'wpapp_is_staging', true );

		if ( 1 === $is_staging ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * To schedule events for newly created site on WP Multisite.
	 *
	 * Action hook: wp_initialize_site
	 *
	 * @param  object $new_site new site.
	 * @param  array  $args args.
	 * @return void
	 */
	public function wpcd_wisdom_custom_options_events( $new_site, $args ) {

		$plugin_name = wpcd_plugin;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {

			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			if ( ! wp_next_scheduled( 'wpcd_wisdom_custom_options' ) ) {
				wp_schedule_event( time(), 'twicedaily', 'wpcd_wisdom_custom_options' );
			}
			restore_current_blog();
		}

	}
}
