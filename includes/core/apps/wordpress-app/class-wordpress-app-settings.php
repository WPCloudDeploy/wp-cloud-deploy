<?php
/**
 * WordPress app settings.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WORDPRESS_APP_SETTINGS
 */
class WORDPRESS_APP_SETTINGS extends WPCD_APP_SETTINGS {

	/**
	 * Holds a reference to this class
	 *
	 * @var $instance instance.
	 */
	private static $instance;

	/**
	 * Static function that can initialize the class
	 * and return an instance of itself.
	 *
	 * @TODO: This just seems to duplicate the constructor
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wordpress_SERVER_APP_SETTINGS constructor.
	 */
	public function __construct() {

		// setup WordPress and settings hooks.
		$this->hooks();

	}

	/**
	 * Hook into WordPress and other plugins as needed.
	 */
	private function hooks() {

		add_filter( 'wpcd_settings_tabs', array( &$this, 'settings_tabs' ) );  // add a new tab to the settings page.

		add_filter( 'wpcd_settings_metaboxes', array( &$this, 'settings_metaboxes' ) );  // add new metaboxes to our new tab on the settings pages.

		// Enable filter on the private key to encrypt it when its being stored.
		add_filter( 'rwmb_wordpress_app_aws_secret_key_value', array( &$this, 'encrypt_aws_secret_key' ), 10, 3 );

		// Enable filter on the private key field to decrypt it when its being retrieved.
		add_filter( 'rwmb_wordpress_app_aws_secret_key_field_meta', array( &$this, 'decrypt_aws_secret_key' ), 10, 3 );

		// Filter for change the button text.
		add_filter( 'rwmb_media_add_string', array( &$this, 'wpcd_upload_logo_change_add_string' ) );

		// Filter for change the header logo image.
		add_filter( 'wpcd_popup_header_logo', array( &$this, 'wpcd_change_popup_header_logo' ) );

	}

	/**
	 * Change a button text to say "Upload Logo" on the white label settings tab.
	 *
	 * Filter Hook: rwmb_media_add_string
	 *
	 * @See: https://docs.metabox.io/fields/file-advanced/ (scroll down to the FILTERS section.)
	 */
	public function wpcd_upload_logo_change_add_string() {
		return __( 'Upload Logo', 'wpcd' );
	}

	/**
	 * Set the popup header logo image.
	 *
	 * Filter Hook: wpcd_popup_header_logo
	 *
	 * @param string $image_url header logo image url.
	 *
	 * @return string $image_url return the header logo image url.
	 */
	public function wpcd_change_popup_header_logo( $image_url ) {

		$wpcd_options = get_option( 'wpcd_settings' );

		// Check to see if we're not allowed to load up a logo.
		if ( ! empty( $wpcd_options['wordpress_app_noshow_logo'] ) ) {
			return '';
		}

		// We're allowed to use a logo so check to see if one is defined.
		$uploaded_logo = '';
		if ( ! empty( $wpcd_options['wordpress_app_upload_logo'] ) ) {
			$uploaded_logo = $wpcd_options['wordpress_app_upload_logo'];
		}

		// If a logo has been defined, set the variable to it so it can be returned.
		if ( ! empty( $uploaded_logo ) ) {
			$image_url = wp_get_attachment_image_url( $uploaded_logo[0], 'full' );
		}

		return $image_url;
	}

	/**
	 * Encrypt the aws secret key before it is saved in the database.
	 *
	 * @param string $new new.
	 * @param string $field field.
	 * @param string $old old.
	 */
	public function encrypt_aws_secret_key( $new, $field, $old ) {
		if ( ! empty( $new ) ) {
			return WPCD()->encrypt( $new );
		}
		return $new;
	}

	/**
	 * Decrypt the aws secret key before it is shown on the screen.
	 *
	 * @param string $meta meta.
	 * @param string $field field.
	 * @param string $saved saved.
	 */
	public function decrypt_aws_secret_key( $meta, $field, $saved ) {
		if ( ! empty( $meta ) ) {
			return WPCD()->decrypt( $meta );
		}
		return $meta;
	}

	/**
	 * Add a new tab to the settings page
	 *
	 * Filter hook: wpcd_settings_tabs
	 *
	 * @param array $tabs Array of tabs on the settings page.
	 *
	 * @return array $tabs New array of tabs on the settings page
	 */
	public function settings_tabs( $tabs ) {
		$new_tab = array(
			'app-wordpress-app'          => __( 'APP: WordPress - Settings', 'wpcd' ),
			'app-wordpress-app-security' => __( 'APP: WordPress - Security', 'wpcd' ),
		);
		$tabs    = $tabs + $new_tab;
		return $tabs;
	}


	/**
	 * Add a new metaboxes to the settings page
	 *
	 * See the Metabox.IO website for documentation on
	 * the structure of the metabox settings array.
	 * https://docs.metabox.io/extensions/mb-settings-page/
	 *
	 * Filter hook: wpcd_settings_metaboxes
	 *
	 * @param array $metaboxes Array of metaboxes on the settings page.
	 *
	 * @return array $metaboxes New array of metaboxes on the settings page
	 */
	public function settings_metaboxes( $metaboxes ) {

		$metaboxes[] = array(
			'id'             => 'wordpress-app',
			'title'          => __( 'General WordPress App Settings', 'wpcd' ),
			'settings_pages' => 'wpcd_settings',
			'tab'            => 'app-wordpress-app',  // this is the top level tab on the setttings screen, not to be confused with the tabs inside a metabox as we're defining below!
		// List of tabs in the metabox, in one of the following formats:
		// 1) key => label.
		// 2) key => array( 'label' => Tab label, 'icon' => Tab icon ).
			'tabs'           => $this->wordpress_app_metabox_tabs(),
			'tab_style'      => 'left',
			'tab_wrapper'    => true,
			'fields'         => apply_filters( 'wpcd_wordpress-app_settings_fields', $this->all_fields() ),

		);

		$metaboxes[] = array(
			'id'             => 'wordpress-app-security',
			'title'          => __( 'General WordPress App Security', 'wpcd' ),
			'settings_pages' => 'wpcd_settings',
			'tab'            => 'app-wordpress-app-security',  // this is the top level tab on the setttings screen, not to be confused with the tabs inside a metabox as we're defining below!
		// List of tabs in the metabox, in one of the following formats:
		// 1) key => label.
		// 2) key => array( 'label' => Tab label, 'icon' => Tab icon ).
			'tabs'           => $this->wordpress_app_metabox_security_tabs(),
			'tab_style'      => 'left',
			'tab_wrapper'    => true,
			'fields'         => apply_filters( 'wpcd_wordpress-app_security_settings_fields', $this->all_security_fields() ),

		);

		return $metaboxes;
	}

	/**
	 * Return a list of tabs that will go inside the WordPress App metabox.
	 */
	public function wordpress_app_metabox_tabs() {
		$tabs = array(
			'wordpress-app-general-wpadmin'      => array(
				'label' => 'General',
				'icon'  => 'dashicons-text',
			),
			'wordpress-app-servers'              => array(
				'label' => 'Servers',
				'icon'  => 'dashicons-align-full-width',
			),
			'wordpress-app-sites'                => array(
				'label' => 'Sites',
				'icon'  => 'dashicons-admin-multisite',
			),
			'wordpress-app-backup'               => array(
				'label' => 'Backup and Restore',
				'icon'  => 'dashicons-images-alt2',
			),
			'wordpress-app-fields-and-links'     => array(
				'label' => 'Fields & Links',
				'icon'  => 'dashicons-editor-unlink',
			),
			'wordpress-app-plugin-theme-updates' => array(
				'label' => 'Theme & Plugin Updates',
				'icon'  => 'dashicons-admin-plugins',
			),
			'wordpress-app-dns-cloudflare'       => array(
				'label' => 'DNS: Cloudflare',
				'icon'  => 'dashicons-cloud',
			),
			'wordpress-app-email-notify'         => array(
				'label' => 'Email Notifications',
				'icon'  => 'dashicons-email',
			),
			'wordpress-app-slack-notify'         => array(
				'label' => 'Slack Notifications',
				'icon'  => 'dashicons-admin-comments',
			),
			'wordpress-app-zapier-notify'        => array(
				'label' => 'Zapier Notifications',
				'icon'  => 'dashicons-embed-generic',
			),
			'wordpress-app-color-settings'       => array(
				'label' => 'Styles',
				'icon'  => 'dashicons-color-picker',
			),
			'wordpress-app-email-gateway'        => array(
				'label' => 'Email Gateway',
				'icon'  => 'dashicons-email-alt2',
			),
			'wordpress-app-front-end-fields'     => array(
				'label' => 'Front-end Fields',
				'icon'  => 'dashicons-editor-kitchensink',
			),
			'wordpress-app-rest-api'             => array(
				'label' => 'Rest API',
				'icon'  => 'dashicons-rest-api',
			),
			'wordpress-app-white-label'          => array(
				'label' => 'White Label',
				'icon'  => 'dashicons-randomize',
			),
			'wordpress-app-custom-scripts'       => array(
				'label' => 'Custom Scripts',
				'icon'  => 'dashicons-shortcode',
			),
		/*
		/*
		'wordpress-app-scripts' => array(
			'label' => 'Scripts',
			'icon'  => 'dashicons-format-aside',
		),
		*/
		);

		return apply_filters( 'wpcd_wordpress-app_settings_tabs', $tabs );
	}

	/**
	 * Return a list of tabs that will go inside the WordPress App Security metabox.
	 */
	public function wordpress_app_metabox_security_tabs() {
		$tabs = array(
			'wordpress-app-security-live-sites'          => array(
				'label' => 'Tabs - Production Sites',
				'icon'  => 'dashicons-lock',
			),
			'wordpress-app-security-staging-sites'       => array(
				'label' => 'Tabs - Staging Sites',
				'icon'  => 'dashicons-lock',
			),
			'wordpress-app-security-live-servers'        => array(
				'label' => 'Tabs - Servers',
				'icon'  => 'dashicons-lock',
			),
			'wordpress-app-security-features-live-sites' => array(
				'label' => 'Features - Production Sites',
				'icon'  => 'dashicons-lock',
			),
			'wordpress-app-security-features-staging-sites' => array(
				'label' => 'Features - Staging Sites',
				'icon'  => 'dashicons-lock',
			),
			'wordpress-app-security-features-servers'    => array(
				'label' => 'Features - Servers',
				'icon'  => 'dashicons-lock',
			),
		);
		return $tabs;
	}

	/**
	 * Return an array that combines all fields that will go on all tabs.
	 */
	public function all_fields() {
		$general_fields = $this->general_fields();
		// Removing script fields for now since they're not being used.
		/* $script_fields	= $this->scripts_fields(); */
		$server_fields                = $this->server_fields();
		$site_fields                  = $this->site_fields();
		$backup_fields                = $this->backup_fields();
		$fields_and_links             = $this->fields_and_links();
		$theme_and_plugin_updates     = $this->theme_and_plugin_updates();
		$email_notification_fields    = $this->email_notification_fields();
		$slack_notification_fields    = $this->slack_notification_fields();
		$zapier_notification_fields   = $this->zapier_notification_fields();
		$button_color_settings_fields = $this->button_color_settings_fields();
		$email_gateway_load_defaults  = $this->email_gateway_load_defaults();
		$cf_dns_fields                = $this->cf_dns_fields();
		$rest_api_fields              = $this->rest_api_fields();
		$white_label_fields           = $this->white_label_fields();
		$custom_scripts               = $this->custom_script_fields();
		$front_end_fields             = $this->front_end_fields();
		$all_fields                   = array_merge( $general_fields, $server_fields, $site_fields, $backup_fields, $fields_and_links, $theme_and_plugin_updates, $email_notification_fields, $slack_notification_fields, $zapier_notification_fields, $button_color_settings_fields, $email_gateway_load_defaults, $cf_dns_fields, $rest_api_fields, $white_label_fields, $custom_scripts, $front_end_fields );
		return $all_fields;
	}

	/**
	 * Return an array that combines all fields that will go in the WordPress App security tabs.
	 */
	public function all_security_fields() {
		return array_merge( $this->all_site_tabs_security_fields(), $this->all_server_tabs_security_fields(), $this->all_site_features_security_fields(), $this->all_server_features_security_fields() );
	}

	/**
	 * Return an array that combines all fields that will go in the WordPress App security tabs for TABS - PRODUCTION SITES & TABS - STAGING SITES
	 */
	public function all_site_tabs_security_fields() {

		$fields = array();

		// An array of tabs where we'll be creating dynamic settings.  Should be the IDS of existing tabs defined in the 'wordpress_app_metabox_security_tabs' function earlier in this class.
		// We're going to use the second part of the associative array as a short-id because the tab id itself is too long.
		$context_tabs = array(
			'wordpress-app-security-live-sites'    => 'live-sites',
			'wordpress-app-security-staging-sites' => 'staging-sites',
		);

		// The owner types we'll be handling.
		$context_owners = array(
			'site-owner'            => __( 'Site Owners', 'wpcd' ),
			'site-and-server-owner' => __( 'Site & Server Owners', 'wpcd' ),
		);

		// The site tabs we'll be collecting security exceptions for. This must be unique across all items in the APP:WordPress - SECURITY tab!
		$tabs = array(
			'6g_waf'                   => __( '6G Firewall', 'wpcd' ),
			'7g_waf'                   => __( '7G Firewall', 'wpcd' ),
			'backup'                   => __( 'Backup', 'wpcd' ),
			'cache'                    => __( 'Cache', 'wpcd' ),
			'change-domain'            => __( 'Change Domain', 'wpcd' ),
			'clone-site'               => __( 'Clone Site', 'wpcd' ),
			'copy-to-existing-site'    => __( 'Copy To Existing Site', 'wpcd' ),
			'crons'                    => __( 'Crons', 'wpcd' ),
			'general'                  => __( 'General', 'wpcd' ),
			'site-logs'                => __( 'Logs', 'wpcd' ),
			'misc'                     => __( 'Misc', 'wpcd' ),
			'database'                 => __( 'Database', 'wpcd' ),
			'php-options'              => __( 'PHP', 'wpcd' ),
			'redirect-rules'           => __( 'Redirect Rules', 'wpcd' ),
			'sftp'                     => __( 'sFTP', 'wpcd' ),
			'site-sync'                => __( 'Copy To Server', 'wpcd' ),
			'ssl'                      => __( 'SSL', 'wpcd' ),
			'staging'                  => __( 'Staging', 'wpcd' ),
			'statistics'               => __( 'Statistics', 'wpcd' ),
			'theme-and-plugin-updates' => __( 'Site Updates', 'wpcd' ),
			'tools'                    => __( 'Tools', 'wpcd' ),
			'tweaks'                   => __( 'Tweaks', 'wpcd' ),
			'wp-site-users'            => __( 'WP Site Users', 'wpcd' ),
			'wpconfig'                 => __( 'WPConfig', 'wpcd' ),
			'multisite'                => __( 'Multisite', 'wpcd' ),
		);

		// Let developers hook into the array here.
		$tabs = apply_filters( 'wpcd_wordpress-app_site_tab_permissions_list', $tabs );

		// Loop through the settings tabs...
		$wpcd_id_prefix = 'wpcd_wpapp_site_security_exception';
		foreach ( $context_tabs as $context_tab => $context_tab_short_id ) {
			// Heading.
			$desc     = __( 'Which tabs should be hidden from site owners?', 'wpcd' );
			$desc    .= '<br />' . __( 'There are two options: 1. Hide a tab from a site owner.  2. Hide a tab from a site owner who is also the owner of a server.', 'wpcd' );
			$desc    .= '<br />' . __( 'If you only choose an option in the first column but leave the second column disabled then an owner of a site that is also the owner of a server will NOT have the selected tab / option hidden.', 'wpcd' );
			$fields[] = array(
				'name' => __( 'Hide Site Tabs from Site Owners', 'wpcd' ),
				'id'   => "{$wpcd_id_prefix}_{$context_tab_short_id}_site_owner_header",
				'type' => 'heading',
				'std'  => '',
				'desc' => $desc,
				'tab'  => $context_tab,
			);
			// Three columns at the top of each settings tab.
			$fields[] = array(
				'name'    => __( 'Tab', 'wpcd' ),
				'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_tab_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 4,
				'tab'     => $context_tab,
			);
			$fields[] = array(
				'name'    => __( 'Site Owners', 'wpcd' ),
				'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_site_owner_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 4,
				'tab'     => $context_tab,
				'tooltip' => __( 'Disable site owner access to these items.', 'wpcd' ),
			);
			$fields[] = array(
				'name'    => __( 'Site & Server Owners', 'wpcd' ),
				'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_site_server_owner_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 4,
				'tab'     => $context_tab,
				'tooltip' => __( 'Disable site owner access to these items even if they are also the server owner.', 'wpcd' ),
			);
			// Loop through the array of site tabs.
			foreach ( $tabs as $tab_key => $tab_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$tab_desc}",
					'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_column1_{$tab_key}_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 4,
				);
				// The next two columns are for the owner types.
				foreach ( $context_owners as $owner_key => $owner_label ) {
					$fields[] = array(
						'name'      => '',
						'id'        => "{$wpcd_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$tab_key}",
						'type'      => 'switch',
						'on_label'  => __( 'Hide Tab', 'wpcd' ),
						'off_label' => __( 'Show Tab', 'wpcd' ),
						'tab'       => $context_tab,
						'columns'   => 4,
					);
				}
			}

			// Heading - for roles.
			$fields[] = array(
				'name' => __( 'Hide Site Tabs from Roles', 'wpcd' ),
				'id'   => "{$wpcd_id_prefix}_{$context_tab_short_id}_site_owner_header_roles",
				'type' => 'heading',
				'std'  => '',
				'desc' => __( 'Which tabs should be hidden from site owners with these roles?', 'wpcd' ),
				'tab'  => $context_tab,
			);
			// Loop through the array of site tabs, again.
			foreach ( $tabs as $tab_key => $tab_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$tab_desc}",
					'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$tab_key}_role_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 6,
				);
				// Collect the roles.
				$fields[] = array(
					'name'            => '',
					'id'              => "{$wpcd_id_prefix}_{$context_tab_short_id}_{$tab_key}_roles",
					'type'            => 'select_advanced',
					'options'         => wpcd_get_roles(),
					'select_all_none' => true,
					'multiple'        => true,
					'placeholder'     => __( 'Select list of roles that should not see this tab.', 'wpcd' ),
					'tab'             => $context_tab,
					'columns'         => 6,
				);

			}
		}

		$context_tabs = array(
			'wordpress-app-security-live-servers'    => 'live-servers',
			'wordpress-app-security-staging-servers' => 'staging-servers',
		);

		return $fields;

	}

	/**
	 * Return an array that combines all fields that will go in the WordPress App security tabs for TABS - SERVERS.
	 */
	public function all_server_tabs_security_fields() {

		$fields = array();

		// An array of tabs where we'll be creating dynamic settings.  Should be the IDS of existing tabs defined in the 'wordpress_app_metabox_security_tabs' function earlier in this class.
		// We're going to use the second part of the associative array as a short-id because the tab id itself is too long.
		$context_tabs = array(
			'wordpress-app-security-live-servers' => 'live-servers',
		);

		// The owner types we'll be handling.
		$context_owners = array(
			'server-owner' => __( 'Server Owners', 'wpcd' ),
		);

		// The server tabs we'll be collecting security exceptions for. This must be unique across all items in the APP:WordPress - SECURITY tab!
		$tabs = array(
			'server_backup'   => __( 'Backup', 'wpcd' ),
			'callbacks'       => __( 'Callbacks', 'wpcd' ),
			'fail2ban'        => __( 'Fail2ban', 'wpcd' ),
			'goaccess'        => __( 'Goaccess', 'wpcd' ),
			'server-logs'     => __( 'Server Logs', 'wpcd' ),
			'monit-healing'   => __( 'Healing', 'wpcd' ),
			'monitorix'       => __( 'Monitorix', 'wpcd' ),
			'svr_power'       => __( 'Power', 'wpcd' ),
			'services'        => __( 'Services', 'wpcd' ),
			'sites_on_server' => __( 'Sites', 'wpcd' ),
			'ssh_console'     => __( 'SSH Console', 'wpcd' ),
			'server-ssh-keys' => __( 'SSH Keys', 'wpcd' ),
			'svr_statistics'  => __( 'Statistics', 'wpcd' ),
			'svr_tools'       => __( 'Tools', 'wpcd' ),
			'svr_tweaks'      => __( 'Tweaks', 'wpcd' ),
			'firewall'        => __( 'Firewall', 'wpcd' ),
			'server_upgrade'  => __( 'Upgrades', 'wpcd' ),
			'server-users'    => __( 'Users', 'wpcd' ),
			'serversync'      => __( 'Server Sync', 'wpcd' ),
			'resize'          => __( 'Resize Server', 'wpcd' ),
		);

		// Let developers hook into the array here.
		$tabs = apply_filters( 'wpcd_wordpress-app_server_tab_permissions_list', $tabs );

		// Loop through the settings tabs...
		$wpcd_id_prefix = 'wpcd_wpapp_server_security_exception';
		foreach ( $context_tabs as $context_tab => $context_tab_short_id ) {
			// Heading.
			$fields[] = array(
				'name' => __( 'Hide Server Tabs from Server Owners', 'wpcd' ),
				'id'   => "{$wpcd_id_prefix}_{$context_tab_short_id}_server_owner_header",
				'type' => 'heading',
				'std'  => '',
				'desc' => __( 'Which tabs should be hidden from server owners?', 'wpcd' ),
				'tab'  => $context_tab,
			);
			// Two columns at the top of each settings tab.
			$fields[] = array(
				'name'    => __( 'Tab', 'wpcd' ),
				'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_tab_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 6,
				'tab'     => $context_tab,
			);
			$fields[] = array(
				'name'    => __( 'Server Owners', 'wpcd' ),
				'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_server_owner_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 6,
				'tab'     => $context_tab,
				'tooltip' => __( 'Disable server owner access to these items.', 'wpcd' ),
			);

			// Loop through the array of server tabs.
			foreach ( $tabs as $tab_key => $tab_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$tab_desc}",
					'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_column1_{$tab_key}_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 6,
				);
				// The next ONE columns are for the owner types.  Only one element in the array but keeping it as a loop to match the pattern for the sites function.
				foreach ( $context_owners as $owner_key => $owner_label ) {
					$fields[] = array(
						'name'      => '',
						'id'        => "{$wpcd_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$tab_key}",
						'type'      => 'switch',
						'on_label'  => __( 'Hide Tab', 'wpcd' ),
						'off_label' => __( 'Show Tab', 'wpcd' ),
						'tab'       => $context_tab,
						'columns'   => 6,
					);
				}
			}

			// Heading - for roles.
			$fields[] = array(
				'name' => __( 'Hide Server Tabs from Roles', 'wpcd' ),
				'id'   => "{$wpcd_id_prefix}_{$context_tab_short_id}_server_owner_header_roles",
				'type' => 'heading',
				'std'  => '',
				'desc' => __( 'Which tabs should be hidden from server owners with these roles?', 'wpcd' ),
				'tab'  => $context_tab,
			);
			// Loop through the array of server tabs, again.
			foreach ( $tabs as $tab_key => $tab_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$tab_desc}",
					'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$tab_key}_role_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 6,
				);
				// Collect the roles.
				$fields[] = array(
					'name'            => '',
					'id'              => "{$wpcd_id_prefix}_{$context_tab_short_id}_{$tab_key}_roles",
					'type'            => 'select_advanced',
					'options'         => wpcd_get_roles(),
					'select_all_none' => true,
					'multiple'        => true,
					'placeholder'     => __( 'Select list of roles that should not see this tab.', 'wpcd' ),
					'tab'             => $context_tab,
					'columns'         => 6,
				);

			}
		}

		return $fields;

	}

	/**
	 * Return an array that combines all fields that will go in the WordPress App security tabs for subtabs: FEATURES - PRODUCTION SITES & TABS - STAGING SITES
	 */
	public function all_site_features_security_fields() {

		$fields = array();

		// An array of tabs where we'll be creating dynamic settings.  Should be the IDS of existing tabs defined in the 'wordpress_app_metabox_security_tabs' function earlier in this class.
		// We're going to use the second part of the associative array as a short-id because the tab id itself is too long.
		$context_tabs = array(
			'wordpress-app-security-features-live-sites' => 'live-sites',
			'wordpress-app-security-features-staging-sites' => 'staging-sites',
		);

		// The owner types we'll be handling.
		$context_owners = array(
			'site-owner'            => __( 'Site Owners', 'wpcd' ),
			'site-and-server-owner' => __( 'Site & Server Owners', 'wpcd' ),
		);

		// The site features we'll be collecting security exceptions for.  This must be unique across all items in the APP:WordPress - SECURITY tab!
		$site_features = array(
			'email_metabox'      => __( 'Email Metabox', 'wpcd' ),
			'desc_notes_metabox' => __( 'Descriptions Metabox', 'wpcd' ),
		);

		// Let developers hook into the array here.
		$site_features = apply_filters( 'wpcd_wordpress-app_site_features_permissions_list', $site_features );

		// Loop through the settings feature array...
		$wpcd_id_prefix = 'wpcd_wpapp_site_security_exception';
		foreach ( $context_tabs as $context_tab => $context_tab_short_id ) {
			// Heading.
			$desc     = __( 'Which features should be hidden from site owners?', 'wpcd' );
			$desc    .= '<br />' . __( 'There are two options: 1. Hide a feature from a site owner.  2. Hide a feature from a site owner who is also the owner of a server.', 'wpcd' );
			$desc    .= '<br />' . __( 'If you only choose an option in the first column but leave the second column disabled then an owner of a site that is also the owner of a server will NOT have the selected feature hidden.', 'wpcd' );
			$fields[] = array(
				'name' => __( 'Hide Feature from Site Owners', 'wpcd' ),
				'id'   => "{$wpcd_id_prefix}_{$context_tab_short_id}_site_owner_header",
				'type' => 'heading',
				'std'  => '',
				'desc' => $desc,
				'tab'  => $context_tab,
			);
			// Three columns at the top of each settings tab.
			$fields[] = array(
				'name'    => __( 'Feature', 'wpcd' ),
				'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_tab_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 6,
				'tab'     => $context_tab,
			);
			$fields[] = array(
				'name'    => __( 'Site Owners', 'wpcd' ),
				'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_site_owner_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 3,
				'tab'     => $context_tab,
				'tooltip' => __( 'Disable site owner access to these features.', 'wpcd' ),
			);
			$fields[] = array(
				'name'    => __( 'Site & Server Owners', 'wpcd' ),
				'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_site_server_owner_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 3,
				'tab'     => $context_tab,
				'tooltip' => __( 'Disable site owner access to these features even if they are also the server owner.', 'wpcd' ),
			);
			// Loop through the array of site features.
			foreach ( $site_features as $feature_key => $feature_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$feature_desc}",
					'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_column1_{$feature_key}_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 6,
				);
				// The next two columns are for the owner types.
				foreach ( $context_owners as $owner_key => $owner_label ) {
					$fields[] = array(
						'name'      => '',
						'id'        => "{$wpcd_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$feature_key}",
						'type'      => 'switch',
						'on_label'  => __( 'Hide', 'wpcd' ),
						'off_label' => __( 'Show', 'wpcd' ),
						'tab'       => $context_tab,
						'columns'   => 3,
					);
				}
			}

			// Heading - for roles.
			$fields[] = array(
				'name' => __( 'Hide Site Features from Roles', 'wpcd' ),
				'id'   => "{$wpcd_id_prefix}_{$context_tab_short_id}_site_owner_header_roles",
				'type' => 'heading',
				'std'  => '',
				'desc' => __( 'Which features should be hidden from site owners with these roles?', 'wpcd' ),
				'tab'  => $context_tab,
			);
			// Loop through the array of site features, again.
			foreach ( $site_features as $feature_key => $feature_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$feature_desc}",
					'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$feature_key}_role_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 6,
				);
				// Collect the roles.
				$fields[] = array(
					'name'            => '',
					'id'              => "{$wpcd_id_prefix}_{$context_tab_short_id}_{$feature_key}_roles",
					'type'            => 'select_advanced',
					'options'         => wpcd_get_roles(),
					'select_all_none' => true,
					'multiple'        => true,
					'placeholder'     => __( 'Select list of roles that should not see this feature.', 'wpcd' ),
					'tab'             => $context_tab,
					'columns'         => 6,
				);

			}
		}

		$context_tabs = array(
			'wordpress-app-security-live-servers'    => 'live-servers',
			'wordpress-app-security-staging-servers' => 'staging-servers',
		);

		return $fields;

	}

	/**
	 * Return an array that combines all fields that will go in the WordPress App security tabs, subtab: FEATURES - SERVERS.
	 */
	public function all_server_features_security_fields() {

		$fields = array();

		// An array of tabs where we'll be creating dynamic settings.  Should be the IDS of existing tabs defined in the 'wordpress_app_metabox_security_tabs' function earlier in this class.
		// We're going to use the second part of the associative array as a short-id because the tab id itself is too long.
		$context_tabs = array(
			'wordpress-app-security-features-servers' => 'live-servers',
		);

		// The owner types we'll be handling.
		$context_owners = array(
			'server-owner' => __( 'Server Owners', 'wpcd' ),
		);

		// The server features we'll be collecting security exceptions for.  This must be unique across all items in the APP:WordPress - SECURITY tab!
		$svrfeatures = array(
			'email_metabox'      => __( 'Email Metabox', 'wpcd' ),
			'desc_notes_metabox' => __( 'Descriptions Metabox', 'wpcd' ),
		);

		// Let developers hook into the array here.
		$svrfeatures = apply_filters( 'wpcd_wordpress-app_server_features_permissions_list', $svrfeatures );

		// Loop through the settings tabs...
		$wpcd_id_prefix = 'wpcd_wpapp_server_security_exception';
		foreach ( $context_tabs as $context_tab => $context_tab_short_id ) {
			// Heading.
			$fields[] = array(
				'name' => __( 'Hide Certain Features From Server Owners', 'wpcd' ),
				'id'   => "{$wpcd_id_prefix}_{$context_tab_short_id}_server_owner_header",
				'type' => 'heading',
				'std'  => '',
				'desc' => __( 'Which features should be hidden from server owners?', 'wpcd' ),
				'tab'  => $context_tab,
			);
			// Two columns at the top of each settings tab.
			$fields[] = array(
				'name'    => __( 'Feature', 'wpcd' ),
				'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_tab_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 6,
				'tab'     => $context_tab,
			);
			$fields[] = array(
				'name'    => __( 'Server Owners', 'wpcd' ),
				'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_server_owner_header_column",
				'type'    => 'custom_html',
				'std'     => '',
				'columns' => 6,
				'tab'     => $context_tab,
				'tooltip' => __( 'Disable or enable server owner access to these features.', 'wpcd' ),
			);

			// Loop through the array of server features.
			foreach ( $svrfeatures as $feature_key => $feature_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$feature_desc}",
					'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_column1_{$feature_key}_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 6,
				);
				// The next ONE columns are for the owner types.  Only one element in the array but keeping it as a loop to match the pattern for the sites function.
				foreach ( $context_owners as $owner_key => $owner_label ) {
					$fields[] = array(
						'name'      => '',
						'id'        => "{$wpcd_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$feature_key}",
						'type'      => 'switch',
						'on_label'  => __( 'Hide', 'wpcd' ),
						'off_label' => __( 'Show', 'wpcd' ),
						'tab'       => $context_tab,
						'columns'   => 6,
					);
				}
			}

			// Heading - for roles.
			$fields[] = array(
				'name' => __( 'Hide Server Features from Roles', 'wpcd' ),
				'id'   => "{$wpcd_id_prefix}_{$context_tab_short_id}_server_owner_header_roles",
				'type' => 'heading',
				'std'  => '',
				'desc' => __( 'Which features should be hidden from server owners with these roles?', 'wpcd' ),
				'tab'  => $context_tab,
			);
			// Loop through the array of server tabs, again.
			foreach ( $svrfeatures as $feature_key => $feature_desc ) {
				// First column is just the label with the tab name.
				$fields[] = array(
					'name'    => "{$feature_desc}",
					'id'      => "{$wpcd_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$feature_key}_role_label",
					'type'    => 'custom_html',
					'tab'     => $context_tab,
					'columns' => 6,
				);
				// Collect the roles.
				$fields[] = array(
					'name'            => '',
					'id'              => "{$wpcd_id_prefix}_{$context_tab_short_id}_{$feature_key}_roles",
					'type'            => 'select_advanced',
					'options'         => wpcd_get_roles(),
					'select_all_none' => true,
					'multiple'        => true,
					'placeholder'     => __( 'Select list of roles that should not see this feature.', 'wpcd' ),
					'tab'             => $context_tab,
					'columns'         => 6,
				);

			}
		}

		return $fields;

	}

	/**
	 * Return array portion of field settings for use in the script fields tab.
	 */
	public function scripts_fields() {

		$fields = array(
			array(
				'id'   => 'wordpress_app_script_version',
				'type' => 'text',
				'name' => __( 'Version of scripts', 'wpcd' ),
				'desc' => __( '<b>For future use - not yet active</b> <em>You can set the version when you deploy a new site</em> The default is V1.  Updates to plugins that contain new scripts will NOT usually change this value so if you want to use new scripts on plugin updates, you should change this version number.', 'wpcd' ),
				'tab'  => 'wordpress-app-scripts',
			),
			array(
				'id'   => 'wordpress_app_commands_after_server_install',
				'type' => 'textbox',
				'name' => __( 'After provisioning commands', 'wpcd' ),
				'desc' => __( '<b>For future use - not yet active</b> Run these commands after the server has been provisioned.', 'wpcd' ),
				'tab'  => 'wordpress-app-scripts',
			),
		);

		return $fields;

	}

	/**
	 * Return array portion of field settings for use in the general fields tab.
	 */
	public function general_fields() {

		$fields = array(
			array(
				'type' => 'heading',
				'name' => __( 'Server Options', 'wpcd' ),
				'desc' => __( 'Server options specific to the WordPress app.', 'wpcd' ),
				'tab'  => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_default_os',
				'type'    => 'select',
				'name'    => __( 'Default OS', 'wpcd' ),
				'tooltip' => __( 'Select the default OS to be used when deploying a new WordPress server!', 'wpcd' ),
				'tab'     => 'wordpress-app-general-wpadmin',
				'std'     => 'ubuntu2004lts',
				'options' => WPCD()->get_os_list(),
			),
			array(
				'id'      => 'wordpress_app_use_extended_server_name',
				'type'    => 'checkbox',
				'name'    => __( 'Override the server name?', 'wpcd' ),
				'tooltip' => __( 'Set the server name to a system generated name that includes the name entered by the admin. If unchecked, always use just the admin defined server name - admin MUST ensure that the server name is unique for certain cloud providers or server creation will fail!', 'wpcd' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_server_tab_style',
				'type'    => 'select',
				'options' => array(
					'left'    => __( 'Vertical', 'wpcd' ),
					'default' => __( 'Horizontal', 'wpcd' ),
					'box'     => __( 'Boxed', 'wpcd' ),
				),
				'name'    => __( 'Tab Style For Server Detail Screen', 'wpcd' ),
				'tooltip' => __( 'The tabs on the server detail screen are vertical but you can switch them to a horizontal style.', 'wpcd' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_hide_notes_on_server_services_tab',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Notes Column On Services Tab?', 'wpcd' ),
				'tooltip' => __( 'On the services tab we show a notes column.  For experienced admins this is not necessary - check this box to remove the unnecessary text from the screen.', 'wpcd' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_enable_bulk_delete_on_server_when_delete_protected',
				'type'    => 'checkbox',
				'name'    => __( 'Enable Bulk Trash Action for Deleted-protected Servers [Danger]', 'wpcd' ),
				'tooltip' => __( 'Enable the bulk trash option on the server list screen for those items that are delete protected. Usually the checkbox for the server is disabled for delete protected items - which means that other bulk options will not be available for DELETE PROTECTED servers either. Enable to overide this logic and allow bulk actions on DELETE PROTECTED items as well. However, doing so can allow your servers to be inadvertently deleted via the BULK TRASH operation.', 'wpcd' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_disable_bulk_delete_on_full_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Disable Bulk Trash Action', 'wpcd' ),
				'tooltip' => __( 'Disable the bulk delete option for all servers.', 'wpcd' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'type' => 'heading',
				'name' => __( 'App Options', 'wpcd' ),
				'desc' => __( 'Options specific to the WordPress app.', 'wpcd' ),
				'tab'  => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_tab_style',
				'type'    => 'select',
				'options' => array(
					'left'    => __( 'Vertical', 'wpcd' ),
					'default' => __( 'Horizontal', 'wpcd' ),
					'box'     => __( 'Boxed', 'wpcd' ),
				),
				'name'    => __( 'Tab Style For Site Detail Screen', 'wpcd' ),
				'tooltip' => __( 'The tabs on the site detail screen are vertical but you can switch them to a horizontal style.', 'wpcd' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_show_vnstat_in_app',
				'type'    => 'checkbox',
				'name'    => __( 'Show VNSTAT in the app screen?', 'wpcd' ),
				'tooltip' => __( 'VNSTAT provides network statistics data for the entire server. You can choose to show this data in the statistics tab on each site - if you do not have a need to secure sites between users on the same server.', 'wpcd' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_enable_bulk_delete_on_app_when_delete_protected',
				'type'    => 'checkbox',
				'name'    => __( 'Enable Bulk Trash Action for Delete-protected Sites [Danger]', 'wpcd' ),
				'tooltip' => __( 'Enable the bulk trash option on the app list screen for those items that are delete protected. Usually the checkbox for the app is disabled for delete protected items - which means that other bulk options will not be available for DELETE PROTECTED apps either. Enable to overide this logic and allow bulk actions on DELETE PROTECTED items as well. However, doing so can allow your apps to be inadvertently deleted via the BULK TRASH operation.', 'wpcd' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_disable_bulk_delete_on_full_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Disable Bulk Trash Action', 'wpcd' ),
				'tooltip' => __( 'Disable the bulk delete option for all apps.', 'wpcd' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_enable_bulk_site_delete_on_full_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Enable Bulk Site Delete Action', 'wpcd' ),
				'tooltip' => __( 'Enable the bulk site delete option for the sites list. This deletes the selected sites on your servers unlike the TRASH option which just removes the records.', 'wpcd' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'type' => 'heading',
				'name' => __( 'Labels', 'wpcd' ),
				'desc' => __( 'Label options specific to the WordPress app.', 'wpcd' ),
				'tab'  => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_show_label_in_lists',
				'type'    => 'checkbox',
				'name'    => __( 'Show the \'WordPress\' label in server and site lists?', 'wpcd' ),
				'tooltip' => __( 'If you are running multiple apps you will likely want to know which servers and apps are WordPress related.  If so, turn this on.', 'wpcd' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
			array(
				'type' => 'heading',
				'name' => __( 'Misc', 'wpcd' ),
				'desc' => '',
				'tab'  => 'wordpress-app-general-wpadmin',
			),
			array(
				'id'      => 'wordpress_app_ignore_journalctl_xe',
				'type'    => 'checkbox',
				'name'    => __( 'Do not treat Journalctl -xe messages as errors', 'wpcd' ),
				'tooltip' => __( 'Some bash scripts might trigger journal xe warnings.  Check this box to ignore them.', 'wpcd' ),
				'tab'     => 'wordpress-app-general-wpadmin',
			),
		);
		return $fields;

	}

	/**
	 * Array of fields used in the servers.
	 */
	public function server_fields() {

		$fields = array(
			array(
				'type' => 'heading',
				'name' => __( 'Server Setup Options', 'wpcd' ),
				'desc' => __( 'Services and actions to perform immediately after a server has been deployed.', 'wpcd' ),
				'tab'  => 'wordpress-app-servers',
			),
			array(
				'id'      => 'wordpress_app_servers_add_delete_protection',
				'type'    => 'checkbox',
				'name'    => __( 'Delete Protect New Servers?', 'wpcd' ),
				'tooltip' => __( 'Should deletion protection automatically be enabled on new servers?', 'wpcd' ),
				'tab'     => 'wordpress-app-servers',
			),
			array(
				'id'      => 'wordpress_app_servers_activate_callbacks',
				'type'    => 'checkbox',
				'name'    => __( 'Install Callbacks?', 'wpcd' ),
				'tooltip' => __( 'Turn this on to automatically install callbacks on all new servers - this is recommended.', 'wpcd' ),
				'tab'     => 'wordpress-app-servers',
			),
			array(
				'id'      => 'wordpress_app_servers_activate_backups',
				'type'    => 'checkbox',
				'name'    => __( 'Setup Backups?', 'wpcd' ),
				'tooltip' => __( 'Turn this on to automatically setup backups for all sites on new servers - this is recommended if you have configured AWS S3 defaults.', 'wpcd' ),
				'tab'     => 'wordpress-app-servers',
			),
			array(
				'id'      => 'wordpress_app_servers_activate_config_backups',
				'type'    => 'checkbox',
				'name'    => __( 'Setup Local Configuration Backups?', 'wpcd' ),
				'tooltip' => __( 'Turn this on to automatically setup 90 days of local backups for all critical configuration files on new servers - this is recommended.', 'wpcd' ),
				'tab'     => 'wordpress-app-servers',
			),
			array(
				'id'      => 'wordpress_app_servers_refresh_services',
				'type'    => 'checkbox',
				'name'    => __( 'Refresh Services Status?', 'wpcd' ),
				'tooltip' => __( 'Refresh the status of services shown on the SERVICES tab of your new server.', 'wpcd' ),
				'tab'     => 'wordpress-app-servers',
			),
			array(
				'type' => 'heading',
				'name' => __( 'Linux Updates', 'wpcd' ),
				'desc' => __( 'Run updates immediately after server is deployed - NOT RECOMMENDED if any of the options above are also enabled.', 'wpcd' ),
				'tab'  => 'wordpress-app-servers',
			),
			array(
				'id'      => 'wordpress_app_servers_run_all_linux_updates',
				'type'    => 'checkbox',
				'name'    => __( 'Run All Linux Updates?', 'wpcd' ),
				'tooltip' => __( 'Most new servers have a lot of updates that need to be run overnight. You can turn this on to force the updates to run asap.  Note that this will chew up CPU cycles and cause your server to be slow for a bit. If you need to use your servers immediately do not enable this.', 'wpcd' ),
				'tab'     => 'wordpress-app-servers',
			),

		);

		return $fields;
	}

	/**
	 * Array of fields used in the sites tab.
	 */
	public function site_fields() {

		$fields = array(
			array(
				'type' => 'heading',
				'name' => __( 'Site Setup Options', 'wpcd' ),
				'desc' => __( 'Services and actions to perform immediately after a WordPress site has been deployed.', 'wpcd' ),
				'tab'  => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_sites_add_delete_protection',
				'type'    => 'checkbox',
				'name'    => __( 'Delete Protect New Sites?', 'wpcd' ),
				'tooltip' => __( 'Should deletion protection automatically be enabled on new sites?', 'wpcd' ),
				'tab'     => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_sites_install_page_cache',
				'type'    => 'checkbox',
				'name'    => __( 'Install and Enable Page Cache?', 'wpcd' ),
				'tooltip' => __( 'Install and enable the page cache on all new sites?', 'wpcd' ),
				'tab'     => 'wordpress-app-sites',
			),
			array(
				'type' => 'heading',
				'name' => __( 'Misc', 'wpcd' ),
				'desc' => '',
				'tab'  => 'wordpress-app-sites',
			),
			array(
				'id'      => 'wordpress_app_do_not_delete_sftp_users_on_site_delete',
				'type'    => 'checkbox',
				'name'    => __( 'Do not delete sFTP users when a site is removed.', 'wpcd' ),
				'tooltip' => __( 'If checked, sFTP users will remain behind as regular Linux users on the Linux server after a site is deleted.  You will not be able to reuse these user names on new sites', 'wpcd' ),
				'tab'     => 'wordpress-app-sites',
			),
		);

		return $fields;
	}


	/**
	 * Array of fields used in the fields and links tab.
	 */
	public function fields_and_links() {

		$fields = array(
			array(
				'id'   => 'wordpress_fields_and_links_heading_01',
				'type' => 'heading',
				'name' => __( 'Servers', 'wpcd' ),
				'desc' => __( 'Show or hide certain fields in the server list and server screens.', 'wpcd' ),
				'tab'  => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_show_install_wp_link_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Install WP Link', 'wpcd' ),
				'tooltip' => __( 'Show an INSTALL WORDPRESS link under the Title column in the Server List', 'wpcd' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_show_logs_dropdown_in_server_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Logs Drop-down', 'wpcd' ),
				'tooltip' => __( 'Show a dropdown of WordPress installation log attempts under the Title column in the Server List', 'wpcd' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),

			array(
				'id'   => 'wordpress_fields_and_links_heading_02',
				'type' => 'heading',
				'name' => __( 'Sites', 'wpcd' ),
				'desc' => __( 'Show or hide certain fields in the site list and site screens.', 'wpcd' ),
				'tab'  => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_show_staging_column_in_site_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Staging Column', 'wpcd' ),
				'tooltip' => __( 'Show the staging column in the site list', 'wpcd' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),

			array(
				'id'   => 'wordpress_fields_and_links_heading_03',
				'type' => 'heading',
				'name' => __( 'Sites: Compound Fields', 'wpcd' ),
				'desc' => __( 'Show or hide certain fields in the site list and site screens.', 'wpcd' ),
				'tab'  => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_domain_site_summary_column_in_site_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Domain in Site Summary Column', 'wpcd' ),
				'tooltip' => __( 'Hide the domain in the site summary column from non-admins in the site list. This is useful because it is sometimes redundant with the title column.', 'wpcd' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_login_user_site_summary_column_in_site_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Login User in Site Summary Column', 'wpcd' ),
				'tooltip' => __( 'Hide the login user in the site summary column from non-admins in the site list', 'wpcd' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_initial_wp_version_site_summary_column_in_site_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Initial WP Version in Site Summary Column', 'wpcd' ),
				'tooltip' => __( 'Hide the initial WP version data in the site summary column from non-admins in the site list', 'wpcd' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),

			array(
				'id'   => 'wordpress_fields_and_links_heading_04',
				'type' => 'heading',
				'name' => __( 'Sites: Other', 'wpcd' ),
				'desc' => __( 'Show or hide certain information in the site list and site screens.', 'wpcd' ),
				'tab'  => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_about_caches_text',
				'type'    => 'checkbox',
				'name'    => __( 'Hide the About Caches text on the WordPress Site Cache Tab', 'wpcd' ),
				'tooltip' => __( 'Hide the very long explanation about caches on the WordPress Site cache tab', 'wpcd' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_change_domain_explanatory_text',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Explanatory Text on the WordPress Site Change Domain Tab', 'wpcd' ),
				'tooltip' => __( 'Hide the very long explanations on the WordPress Site Change Domain tab.', 'wpcd' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),
			array(
				'id'      => 'wordpress_app_hide_addl_stats_explanatory_text',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Additional Statistics box on the WordPress Site Statistics Tab', 'wpcd' ),
				'tooltip' => __( 'Hide the additional statisics box on the WordPress Site statistics tab from non-admin users.', 'wpcd' ),
				'tab'     => 'wordpress-app-fields-and-links',
			),

		);

		return $fields;
	}

	/**
	 * Array of fields used in the front-end fields tab.
	 */
	public function front_end_fields() {

		$fields = array(
			array(
				'id'   => 'wordpress_front_end_fields_heading_01',
				'type' => 'heading',
				'name' => __( 'Servers', 'wpcd' ),
				'desc' => __( 'Show fields in the server list and server screens when displaying data on the front-end.  These fields are usually HIDDEN by default.', 'wpcd' ),
				'tab'  => 'wordpress-app-front-end-fields',
			),

			array(
				'id'   => 'wordpress_front_end_fields_heading_03',
				'type' => 'heading',
				'name' => __( 'Sites - Show Cards', 'wpcd' ),
				'desc' => __( 'Show cards in the app/site list and related screens when displaying data on the front-end. These cards are usually HIDDEN by default.', 'wpcd' ),
				'tab'  => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_description_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Description', 'wpcd' ),
				'tooltip' => __( 'Show the description field/card in the app list on the front-end.', 'wpcd' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_app_group_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show App Group', 'wpcd' ),
				'tooltip' => __( 'Show the application group field/card in the app list on the front-end.', 'wpcd' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_owner_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Owner', 'wpcd' ),
				'tooltip' => __( 'Show the owner list field/card in the app list on the front-end.', 'wpcd' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_show_teams_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Show Teams', 'wpcd' ),
				'tooltip' => __( 'Show the teams list field/card in the app list on the front-end.', 'wpcd' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),

			array(
				'id'   => 'wordpress_front_end_fields_heading_04',
				'type' => 'heading',
				'name' => __( 'Sites - Hide Cards', 'wpcd' ),
				'desc' => __( 'Hide cards in the app/site list and related screens when displaying data on the front-end. These cards are usually shown by default.', 'wpcd' ),
				'tab'  => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_app_title_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Title', 'wpcd' ),
				'tooltip' => __( 'Hide the title field/card in the app list on the front-end.', 'wpcd' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),				
			array(
				'id'      => 'wordpress_app_fe_hide_app_summary_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide App Summary', 'wpcd' ),
				'tooltip' => __( 'Hide the app summary field/card in the app list on the front-end.', 'wpcd' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),			
			array(
				'id'      => 'wordpress_app_fe_hide_app_health_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide App Health', 'wpcd' ),
				'tooltip' => __( 'Hide the app health field/card in the app list on the front-end.', 'wpcd' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_server_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Server', 'wpcd' ),
				'tooltip' => __( 'Hide the server field/card in the app list on the front-end.', 'wpcd' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_staging_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Staging', 'wpcd' ),
				'tooltip' => __( 'Hide the staging field/card in the app list on the front-end.', 'wpcd' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_cache_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Cache', 'wpcd' ),
				'tooltip' => __( 'Hide the cache field/card in the app list on the front-end.', 'wpcd' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_php_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide php', 'wpcd' ),
				'tooltip' => __( 'Hide the php field/card in the app list on the front-end.', 'wpcd' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),
			array(
				'id'      => 'wordpress_app_fe_hide_ssl_in_app_list',
				'type'    => 'checkbox',
				'name'    => __( 'Hide ssl', 'wpcd' ),
				'tooltip' => __( 'Hide the SSL field/card in the app list on the front-end.', 'wpcd' ),
				'tab'     => 'wordpress-app-front-end-fields',
			),

		);

		return $fields;
	}


	/**
	 * Array of fields used to store the default s3 backup settings
	 * as well as other backup related options.
	 */
	public function backup_fields() {

		$fields = array(
			array(
				'id'   => 'wordpress_app_backup_heading_01',
				'type' => 'heading',
				'name' => __( 'AWS S3 Credentials', 'wpcd' ),
				'desc' => __( 'Sites can be backed up to AWS S3.  These are the AWS credentials that will be used for all sites when backing up and restoring data.  If you need to, you can set different credentials for each server - you can do that in the CLOUD SERVERS screens.', 'wpcd' ),
				'tab'  => 'wordpress-app-backup',
			),
			array(
				'id'      => 'wordpress_app_aws_access_key',
				'type'    => 'text',
				'name'    => __( 'AWS Access Key ID', 'wpcd' ),
				'tooltip' => __( 'AWS Access Key ID', 'wpcd' ),
				'tab'     => 'wordpress-app-backup',
				'std'     => wpcd_get_option( 'wordpress_app_aws_access_key' ),
				'size'    => 60,
			),
			array(
				'id'      => 'wordpress_app_aws_secret_key',
				'type'    => 'text',
				'name'    => __( 'AWS Secret Key', 'wpcd' ),
				'tooltip' => __( 'AWS Secret Key', 'wpcd' ),
				'tab'     => 'wordpress-app-backup',
				'size'    => 60,
			),
			array(
				'id'      => 'wordpress_app_aws_bucket',
				'type'    => 'text',
				'name'    => __( 'AWS Bucket Name', 'wpcd' ),
				'tooltip' => __( 'AWS Bucket Name', 'wpcd' ),
				'tab'     => 'wordpress-app-backup',
				'std'     => wpcd_get_option( 'wordpress_app_aws_bucket' ),
				'size'    => 60,
			),
			array(
				'id'   => 'wordpress_app_backup_warning',
				'type' => 'custom_html',
				'std'  => __( 'Warning! If you are using our SELL SERVERS WITH WOOCOMMERCE premium option, do NOT set these defaults. Otherwise all servers, including your customer servers, will be able to get these. Since your customers might be able to log into their own servers, they will be able to view these credentials. Instead, set them on each server as needed.  See our WOOCOMMERCE documentation for more information or contact our support team with your questions.', 'wpcd' ),
				'tab'  => 'wordpress-app-backup',
			),

			// Fields that will change how the backup & restore options appear on the sites tab.
			array(
				'id'   => 'wordpress_app_backup_heading_02',
				'type' => 'heading',
				'name' => __( 'Simplify Backup Options on Sites', 'wpcd' ),
				'desc' => __( 'For sites, you can remove certain fields to simplify the screen for your users. If you do this, then the value of those fields will default to either the server value or the global values. Please note that admins will always be able to see these fields.', 'wpcd' ),
				'tab'  => 'wordpress-app-backup',
			),
			array(
				'id'      => 'wordpress_app_site_backup_hide_aws_bucket_name',
				'type'    => 'checkbox',
				'name'    => __( 'Hide the AWS S3 bucket name', 'wpcd' ),
				'tooltip' => __( 'Hide the AWS S3 bucket name field on the backup tab on the sites screen.', 'wpcd' ),
				'tab'     => 'wordpress-app-backup',
			),
			array(
				'id'      => 'wordpress_app_site_backup_hide_retention_days',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Retention Days', 'wpcd' ),
				'tooltip' => __( 'Hide the RETENTION DAYS field on the backup tab on the sites screen.', 'wpcd' ),
				'tab'     => 'wordpress-app-backup',
			),
			array(
				'id'      => 'wordpress_app_site_backup_hide_del_remote_backups',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Delete Remote Backups', 'wpcd' ),
				'tooltip' => __( 'Hide the DELETE REMOTE BACKUPS option field on the backup tab on the sites screen.', 'wpcd' ),
				'tab'     => 'wordpress-app-backup',
			),
			array(
				// Note: If we ever decide to create action options on teams or for owners, this conditional should be removed in favor of those.
				'id'      => 'wordpress_app_site_backup_hide_prune_backups_section',
				'type'    => 'checkbox',
				'name'    => __( 'Hide Prune Backups Section', 'wpcd' ),
				'tooltip' => __( 'Hide the entire PRUNE BACKUPS section on the backup tab on the sites screen.', 'wpcd' ),
				'tab'     => 'wordpress-app-backup',
			),

		);

		return $fields;

	}

	/**
	 * Array of fields used to get the theme & plugin update settings.
	 */
	public function theme_and_plugin_updates() {

		$fields = array(
			array(
				'id'   => 'wordpress_app_t_and_p_updates_heading_01',
				'type' => 'heading',
				'name' => __( 'Image Compare Service API', 'wpcd' ),
				'desc' => __( 'We use the https://htmlcsstoimage.com/ service to create and compare images taken of the site before and after the update. This helps us to determine if there were any significant changes to the site after the update is complete.  And, if there were, automatically roll back the changes. Enter your API credentials for the https://htmlcsstoimage.com/ service in this section.', 'wpcd' ),
				'tab'  => 'wordpress-app-plugin-theme-updates',
			),
			array(
				'id'      => 'wordpress_app_hcti_api_user_id',
				'type'    => 'text',
				'name'    => __( 'API User Id', 'wpcd' ),
				'tooltip' => __( 'htmlcsstoimage.com API User Id - this is not your htmlcsstoimage.com login or email address.', 'wpcd' ),
				'tab'     => 'wordpress-app-plugin-theme-updates',
				'std'     => wpcd_get_option( 'wordpress_app_hcti_api_user_id' ),
				'size'    => 60,
			),
			array(
				'id'      => 'wordpress_app_hcti_api_key',
				'type'    => 'text',
				'name'    => __( 'API Key', 'wpcd' ),
				'tooltip' => __( 'htmlcsstoimage.com API Key', 'wpcd' ),
				'tab'     => 'wordpress-app-plugin-theme-updates',
				'std'     => wpcd_get_option( 'wordpress_app_hcti_api_key' ),
				'size'    => 60,
			),
			array(
				'id'      => 'wordpress_app_tandc_updates_pixel_threshold',
				'type'    => 'number',
				'name'    => __( 'Max Pixel Variance', 'wpcd' ),
				'tooltip' => __( 'What is maximum number of pixels that are allowed to be different between before and after images? If the pixel variance exceeds this value the updates will be rolled back. ', 'wpcd' ),
				'tab'     => 'wordpress-app-plugin-theme-updates',
				'std'     => Max( 1, (int) wpcd_get_option( 'wordpress_app_tandc_updates_pixel_threshold' ) ),
				'size'    => 10,
				'min'     => -1,
				'max'     => 1000000,
			),

			array(
				'id'   => 'wordpress_app_t_and_p_updates_heading_02',
				'type' => 'heading',
				'name' => __( 'Excluded Plugins', 'wpcd' ),
				'desc' => __( 'Never update these plugins', 'wpcd' ),
				'tab'  => 'wordpress-app-plugin-theme-updates',
			),
			array(
				'id'      => 'wordpress_app_plugin_updates_excluded_list',
				'type'    => 'text',
				'name'    => __( 'Exclude These Plugins From Updates', 'wpcd' ),
				'desc'    => __( 'Enter the list of plugins separated by commas - eg: akismet,awesome-suppport, woocommerce', 'wpcd' ),
				'tooltip' => __( 'The name is usually the FOLDER in which the plugin is installed - you will not find this at the top of the main plugin file. ', 'wpcd' ),
				'tab'     => 'wordpress-app-plugin-theme-updates',
			),

			array(
				'id'   => 'wordpress_app_t_and_p_updates_heading_03',
				'type' => 'heading',
				'name' => __( 'Excluded Themes', 'wpcd' ),
				'desc' => __( 'Never update these Themes', 'wpcd' ),
				'tab'  => 'wordpress-app-plugin-theme-updates',
			),
			array(
				'id'      => 'wordpress_app_theme_updates_excluded_list',
				'type'    => 'text',
				'name'    => __( 'Exclude These Themes From Updates', 'wpcd' ),
				'desc'    => __( 'Enter the list of themes separated by commas - eg: ocean,beaver-builder,divi', 'wpcd' ),
				'tooltip' => __( 'The name is usually the FOLDER in which the theme is installed - you will not find this at the top of the main theme css file. ', 'wpcd' ),
				'tab'     => 'wordpress-app-plugin-theme-updates',
			),

		);

		return $fields;

	}

	/**
	 * Array of fields used to store the email notification text.
	 */
	public function email_notification_fields() {

		$fields = array(
			array(
				'id'   => 'wordpress_app_email_notify_heading',
				'type' => 'heading',
				'name' => __( 'Email Notification for user', 'wpcd' ),
				'desc' => __( 'This message is sent to a user when the user has configured a notification profile and a notification event matches the profile.', 'wpcd' ),
				'tab'  => 'wordpress-app-email-notify',
			),
			array(
				'id'   => 'wordpress_app_email_notify_subject',
				'type' => 'text',
				'name' => __( 'Subject', 'wpcd' ),
				'tab'  => 'wordpress-app-email-notify',
				'size' => 60,
			),
			array(
				'id'      => 'wordpress_app_email_notify_body',
				'type'    => 'wysiwyg',
				'name'    => __( 'Body', 'wpcd' ),
				'desc'    => __( 'Valid substitutions are: ##USERNAME##, ##FIRST_NAME##, ##LAST_NAME##, ##TYPE##, ##MESSAGE##, ##REFERENCE##, ##SERVERNAME## ##DOMAIN##, ##DATE##, ##TIME##, ##SERVERID##, ##SITEID##, ##IPV4##, ##PROVIDER##.', 'wpcd' ),
				'options' => array(
					'textarea_rows' => 12,
				),
				'tab'     => 'wordpress-app-email-notify',
				'size'    => 60,
			),
		);

		return $fields;

	}

	/**
	 * Array of fields used to store the slack notification text.
	 */
	public function slack_notification_fields() {

		$fields = array(
			array(
				'id'   => 'wordpress_app_slack_notify_heading',
				'type' => 'heading',
				'name' => __( 'Slack Notification for user', 'wpcd' ),
				'desc' => __( 'This message is pushed to a slack channel when the user has configured a notification profile and a notification event matches the profile.', 'wpcd' ),
				'tab'  => 'wordpress-app-slack-notify',
			),
			array(
				'id'      => 'wordpress_app_slack_notify_message',
				'type'    => 'wysiwyg',
				'name'    => __( 'Message', 'wpcd' ),
				'desc'    => __( 'Valid substitutions are: ##USERNAME##, ##FIRST_NAME##, ##LAST_NAME##, ##TYPE##, ##MESSAGE##, ##REFERENCE##, ##SERVERNAME## ##DOMAIN##, ##DATE##, ##TIME##, ##SERVERID##, ##SITEID##, ##IPV4##, ##PROVIDER##.', 'wpcd' ),
				'options' => array(
					'textarea_rows' => 12,
				),
				'tab'     => 'wordpress-app-slack-notify',
				'size'    => 60,
			),
		);

		return $fields;

	}

	/**
	 * Array of fields used to store the zapier notification text.
	 */
	public function zapier_notification_fields() {

		$fields = array(
			array(
				'id'   => 'wordpress_app_zapier_notify_heading',
				'type' => 'heading',
				'name' => __( 'Zapier Notification for user', 'wpcd' ),
				'desc' => __( 'This message is sent to Zapier when the user has configured a notification profile and a notification event matches the profile.', 'wpcd' ),
				'tab'  => 'wordpress-app-zapier-notify',
			),
			array(
				'id'      => 'wordpress_app_zapier_notify_message',
				'type'    => 'wysiwyg',
				'name'    => __( 'Message', 'wpcd' ),
				'desc'    => __( 'Valid substitutions are: ##USERNAME##, ##USERID##, ##USEREMAIL##, ##FIRST_NAME##, ##LAST_NAME##, ##TYPE##, ##MESSAGE##, ##REFERENCE##, ##SERVERNAME##, ##DOMAIN##, ##SERVERID##, ##SITEID#, ##IPV4##, ##PROVIDER##, ##DATE##, ##TIME##.', 'wpcd' ),
				'options' => array(
					'textarea_rows' => 12,
				),
				'tab'     => 'wordpress-app-zapier-notify',
				'size'    => 60,
			),
		);

		return $fields;

	}

	/**
	 * Array of fields used to store the color settings text.
	 */
	public function button_color_settings_fields() {

		$fields = array(
			array(
				'id'   => 'wordpress_app_button_color_heading',
				'type' => 'heading',
				'name' => __( 'Colors for the user notification shortcode', 'wpcd' ),
				'desc' => __( 'These settings are used to manage the color of the buttons shown when the user notification shortcode is used.', 'wpcd' ),
				'tab'  => 'wordpress-app-color-settings',
			),
			array(
				'name'          => 'Add New Button',
				'id'            => 'wordpress_app_add_new_button_color',
				'type'          => 'color',
				'alpha_channel' => true,
				'tab'           => 'wordpress-app-color-settings',
			),
			array(
				'name'          => 'Submit Button',
				'id'            => 'wordpress_app_submit_button_color',
				'type'          => 'color',
				'alpha_channel' => true,
				'tab'           => 'wordpress-app-color-settings',
			),
			array(
				'name'          => 'Update Button',
				'id'            => 'wordpress_app_update_button_color',
				'type'          => 'color',
				'alpha_channel' => true,
				'tab'           => 'wordpress-app-color-settings',
			),
			array(
				'name'          => 'Test Button',
				'id'            => 'wordpress_app_test_button_color',
				'type'          => 'color',
				'alpha_channel' => true,
				'tab'           => 'wordpress-app-color-settings',
			),

		);

		return $fields;

	}

	/**
	 * Array of fields used to store the email gateway load defaults settings.
	 */
	public function email_gateway_load_defaults() {

		/* Email Gateway */
		$eg_desc = __( 'Set default values you can use when setting up server level email gateways.', 'wpcd' );

		$fields = array(
			array(
				'type' => 'heading',
				'name' => __( 'EMAIL GATEWAY', 'wpcd' ),
				'desc' => $eg_desc,
				'tab'  => 'wordpress-app-email-gateway',
			),
			array(
				'id'      => 'wpcd_email_gateway_smtp_server',
				'type'    => 'text',
				'name'    => __( 'SMTP Server & Port', 'wpcd' ),
				'tooltip' => __( 'Enter the url/address for your outgoing email server - usually in the form of a subdomain.domain.com:port - eg: <i>smtp.ionos.com:587</i>.', 'wpcd' ),
				'tab'     => 'wordpress-app-email-gateway',
			),
			array(
				'id'      => 'wpcd_email_gateway_smtp_user',
				'type'    => 'text',
				'name'    => __( 'User Name', 'wpcd' ),
				'tooltip' => __( 'Your user id for connecting to the smtp server', 'wpcd' ),
				'tab'     => 'wordpress-app-email-gateway',
			),
			array(
				'id'      => 'wpcd_email_gateway_smtp_password',
				'type'    => 'text',
				'name'    => __( 'Password', 'wpcd' ),
				'tooltip' => __( 'Your password for connecting to the smtp server', 'wpcd' ),
				'tab'     => 'wordpress-app-email-gateway',
			),
			array(
				'id'      => 'wpcd_email_gateway_smtp_domain',
				'type'    => 'text',
				'name'    => __( 'From Domain', 'wpcd' ),
				'tooltip' => __( 'The default domain for sending messages', 'wpcd' ),
				'tab'     => 'wordpress-app-email-gateway',
			),
			array(
				'id'      => 'wpcd_email_gateway_smtp_hostname',
				'type'    => 'text',
				'name'    => __( 'FQDN Hostname', 'wpcd' ),
				'tooltip' => __( 'FQDN for the server. Some SMTP servers will require this to be a working domain name (example: server1.myblog.com)', 'wpcd' ),
				'tab'     => 'wordpress-app-email-gateway',
			),
			array(
				'id'      => 'wpcd_email_gateway_smtp_note',
				'type'    => 'textarea',
				'name'    => __( 'Brief Note', 'wpcd' ),
				'tooltip' => __( 'Just a note in case you need a reminder about the details of this email gateway setup.', 'wpcd' ),
				'tab'     => 'wordpress-app-email-gateway',
			),
		);

		return $fields;

	}

	/**
	 * Return array portion of field settings for use in the Cloudflare DNS section of the wc sites tab.
	 */
	public function cf_dns_fields() {

		$fields = array(
			array(
				'type' => 'heading',
				'name' => __( 'Automatic DNS via CloudFlare', 'wpcd' ),
				'desc' => __( 'When a site is provisioned it can be automatically assigned a subdomain based on the domain specified below. If this domain is setup in cloudflare, we can automatically point the IP address to the newly created subdomain.', 'wpcd' ),
				'tab'  => 'wordpress-app-dns-cloudflare',
			),
			array(
				'id'         => 'wordpress_app_dns_cf_temp_domain',
				'type'       => 'text',
				'name'       => __( 'Temporary Domain', 'wpcd' ),
				'tooltip'    => __( 'The domain under which a new site\'s temporary sub-domain will be created.', 'wpcd' ),
				'desc'       => __( 'This needs to be a short domain - max 19 chars.' ),
				'size'       => '20',
				'attributes' => array(
					'maxlength' => '19',
				),
				'tab'        => 'wordpress-app-dns-cloudflare',
			),
			array(
				'id'      => 'wordpress_app_dns_cf_enable',
				'type'    => 'checkbox',
				'name'    => __( 'Enable Cloudflare Auto DNS', 'wpcd' ),
				'tooltip' => __( 'Turn this on so that when a new site is being created, the newly created subdomain can be automatically added to your CloudFlare configuration.', 'wpcd' ),
				'tab'     => 'wordpress-app-dns-cloudflare',
			),
			array(
				'id'   => 'wordpress_app_dns_cf_zone_id',
				'type' => 'text',
				'name' => __( 'Zone ID', 'wpcd' ),
				'desc' => __( 'Your zone id can be found in the lower right of the CloudFlare overview page for your domain', 'wpcd' ),
				'size' => 35,
				'tab'  => 'wordpress-app-dns-cloudflare',
			),
			array(
				'id'   => 'wordpress_app_dns_cf_token',
				'type' => 'text',
				'name' => __( 'API Security Token', 'wpcd' ),
				'desc' => __( 'Generate a new token for your zone by using the GET YOUR API TOKEN link located in the lower right of the CloudFlare overview page for your domain.  This should use the EDIT ZONE DNS api token template.', 'wpcd' ),
				'size' => 35,
				'tab'  => 'wordpress-app-dns-cloudflare',
			),
			array(
				'id'      => 'wordpress_app_dns_cf_disable_proxy',
				'type'    => 'checkbox',
				'name'    => __( 'Disable Cloudflare Proxy', 'wpcd' ),
				'tooltip' => __( 'All new subdomains added to CloudFlare will automatically be proxied (orange flag turned on.) Check this box to turn off this behavior.', 'wpcd' ),
				'tab'     => 'wordpress-app-dns-cloudflare',
			),
			array(
				'id'      => 'wordpress_app_dns_cf_auto_delete',
				'type'    => 'checkbox',
				'name'    => __( 'Auto Delete DNS Entry', 'wpcd' ),
				'tooltip' => __( 'Should we attempt to delete the DNS entry for the domain at cloudflare when a site is deleted?', 'wpcd' ),
				'tab'     => 'wordpress-app-dns-cloudflare',
			),          // This one probably should be moved to it's own tab once we get more than on DNS provider.
			array(
				'id'      => 'wordpress_app_auto_issue_ssl',
				'type'    => 'checkbox',
				'name'    => __( 'Automatically Issue SSL', 'wpcd' ),
				'tooltip' => __( 'If DNS was automatically updated after a new site is provisioned, attempt to get an SSL certificate from LETSENCRYPT?', 'wpcd' ),
				'tab'     => 'wordpress-app-dns-cloudflare',
			),
			array(
				'id'      => 'wordpress_app_auto_add_aaaa',
				'type'    => 'checkbox',
				'name'    => __( 'Add AAAA Record for IPv6?', 'wpcd' ),
				'tooltip' => __( 'Add an AAAA DNS entry if the server has an IPv6 address?', 'wpcd' ),
				'tab'     => 'wordpress-app-dns-cloudflare',
			),
		);
		return $fields;
	}

	/**
	 * Return array portion of field settings for use in rest API tab.
	 */
	public function rest_api_fields() {

		$fields = array(
			array(
				'type' => 'heading',
				'name' => __( 'REST API [Beta]', 'wpcd' ),
				'desc' => __( 'Activate the REST API', 'wpcd' ),
				'tab'  => 'wordpress-app-rest-api',
			),
			array(
				'id'   => 'wordpress_app_rest_api_enable',
				'type' => 'checkbox',
				'name' => __( 'Enable the REST API', 'wpcd' ),
				'tab'  => 'wordpress-app-rest-api',
			),
		);
		return $fields;
	}

	/**
	 * Return an array that combines all fields that will go in white label fields tab.
	 */
	public function white_label_fields() {

		$fields = array();

		// Header.
		$fields[] = array(
			'name' => __( 'Overview', 'wpcd' ),
			'id'   => 'wordpress-app-white-label-basics-heading',
			'type' => 'heading',
			'std'  => '',
			'desc' => __( 'We have provided the most popular white label options here.  But, when you want to go further you still have the vast open sea of WP style hooks and filters as well as 3rd party plugins to help you completely customize your WPCD dashboard.', 'wpcd' ),
			'tab'  => 'wordpress-app-white-label',
		);

		// Logo Header.
		$fields[] = array(
			'name' => __( 'Logo', 'wpcd' ),
			'id'   => 'wordpress-app-logo-overides-heading',
			'type' => 'heading',
			'std'  => '',
			'desc' => __( 'Use your own logo on the server and site creation screen.  You can also completely remove the logo with an entry in wp-config - check out the docs on our website for more information about that option.', 'wpcd' ),
			'tab'  => 'wordpress-app-white-label',
		);

		// Checkbox to not show logo.
		$fields[] = array(
			'name'    => 'Do Not Show Logo',
			'id'      => 'wordpress_app_noshow_logo',
			'type'    => 'checkbox',
			'tooltip' => __( 'Remove the WPCD logo from popups and other locations.', 'wpcd' ),
			'tab'     => 'wordpress-app-white-label',
		);

		// Upload Logo.
		$fields[] = array(
			'name'             => 'Upload Logo',
			'id'               => 'wordpress_app_upload_logo',
			'type'             => 'image_advanced',
			'max_file_uploads' => 1,
			'max_status'       => false,
			'image_size'       => 'thumbnail',
			'max_file_size'    => '2mb',
			'tab'              => 'wordpress-app-white-label',
			'hidden'           => array( 'wordpress_app_noshow_logo', '=', '1' ),
		);

		// Brand Colors.
		$fields[] = array(
			'name' => __( 'Brand Colors', 'wpcd' ),
			'id'   => 'wordpress-app-brand-colors-heading',
			'type' => 'heading',
			'std'  => '',
			'desc' => __( 'These settings are used to manage your brand colors.', 'wpcd' ),
			'tab'  => 'wordpress-app-white-label',
		);

		/**
		 * Overrides Brand Colors.
		 */
		// An array of ids and labels for color fields that overide brand colors.
		$brand_colors = array(
			'wordpress_app_primary_brand_color'     => array(
				'label' => __( 'Primary Brand Color', 'wpcd' ),
				'desc'  => '',
				'std'   => '#E91E63',
			),
			'wordpress_app_secondary_brand_color'   => array(
				'label' => __( 'Secondary Brand Color', 'wpcd' ),
				'desc'  => '',
				'std'   => '#FF5722',
			),
			'wordpress_app_tertiary_brand_color'    => array(
				'label' => __( 'Tertiary Brand Color', 'wpcd' ),
				'desc'  => '',
				'std'   => '#03114A',
			),
			'wordpress_app_accent_background_color' => array(
				'label' => __( 'Accent Background Color', 'wpcd' ),
				'desc'  => '',
				'std'   => '#3F4C5F',
			),
			'wordpress_app_medium_background_color' => array(
				'label' => __( 'Medium Background Color', 'wpcd' ),
				'desc'  => '',
				'std'   => '#FAFAFA',
			),
			'wordpress_app_light_background_color'  => array(
				'label' => __( 'Light Background Color', 'wpcd' ),
				'desc'  => '',
				'std'   => '#FDFDFD',
			),
			'wordpress_app_alternate_accent_background_color' => array(
				'label' => __( 'Alternate Accent Background Color', 'wpcd' ),
				'desc'  => '',
				'std'   => '#CFD8DC',
			),
		);

		// Loop through the brand colors array and generate settings fields.
		foreach ( $brand_colors as $brand_key => $brand_value ) {
			// First column is just the label with the tab name.
			$fields[] = array(
				'name'          => "{$brand_value['label']}",
				'id'            => "{$brand_key}",
				'type'          => 'color',
				'alpha_channel' => true,
				'desc'          => "{$brand_value['desc']}",
				'tab'           => 'wordpress-app-white-label',
				'std'           => "{$brand_value['std']}",
			);
		}

		// RESET DEFAULTS BRAND COLORS.
		$fields[] = array(
			'name'       => '',
			'type'       => 'button',
			'std'        => __( 'Reset Defaults', 'wpcd' ),
			'attributes' => array(
				'id'               => 'wordpress_app_reset_brand_colors',
				'data-action'      => 'wpcd_reset_defaults_brand_colors',
				'data-nonce'       => wp_create_nonce( 'wpcd-reset-brand-colors' ),
				'data-loading_msg' => __( 'Please wait...', 'wpcd' ),
				'data-confirm'     => __( 'Are you sure you would like to reset the brand colors with defaults?', 'wpcd' ),
			),
			'tab'        => 'wordpress-app-white-label',
		);

		/**
		 * Documentation Link Overrides Fields
		 */
		// An array of ids and labels for text fields that overide documentation links.
		$doc_links = array(
			'wordpress-app-doc-link-theme-plugin-updates' => array(
				'label' => __( 'Site Updates', 'wpcd' ),
				'desc'  => __( 'The documentation link in the SITE UPDATES tab.', 'wpcd' ),
			),
			'wordpress-app-doc-change-domain'             => array(
				'label' => __( 'Change Domains', 'wpcd' ),
				'desc'  => __( 'The documentation link in the CHANGE DOMAIN tab.', 'wpcd' ),
			),
			'wordpress-app-doc-link-page-cache'           => array(
				'label' => __( 'Cache Information', 'wpcd' ),
				'desc'  => __( 'The documentation link for more information about caching on the CACHE tab.', 'wpcd' ),
			),
			'wordpress-app-doc-link-memcached-info'       => array(
				'label' => __( 'Memcached Information', 'wpcd' ),
				'desc'  => __( 'The documentation link for more information about Memcached on the CACHE tab.', 'wpcd' ),
			),
		);

		// Header.
		$fields[] = array(
			'name' => __( 'Documentation Link Overrides', 'wpcd' ),
			'id'   => 'wordpress-app-doc-link-overides-heading',
			'type' => 'heading',
			'std'  => '',
			'desc' => __( 'Point users to your own documentation instead of the standard WPCloudDeploy documentation.', 'wpcd' ),
			'tab'  => 'wordpress-app-white-label',
		);

		// Loop through the doc links array and generate settings fields.
		foreach ( $doc_links as $doc_key => $doc_link ) {
			// First column is just the label with the tab name.
			$fields[] = array(
				'name' => "{$doc_link['label']}",
				'id'   => "{$doc_key}",
				'type' => 'url',
				'desc' => "{$doc_link['desc']}",
				'tab'  => 'wordpress-app-white-label',
			);
		}

		return $fields;

	}

	/**
	 * Return an array of fields for the custom script fields subtab in the APP:WordPress SETTINGS tab.
	 */
	public function custom_script_fields() {

		$fields = array();

		// The list of scripts we'll be handling.
		$scripts = array(
			'after_server_create' => array(
				'label'   => __( 'After Server Provisioning', 'wpcd' ),
				'tooltip' => __( 'The custom script you would like to run AFTER we successfully complete installing our core stack.', 'wpcd' ),
			),
			'after_site_create'   => array(
				'label'   => __( 'After Site Provisioning', 'wpcd' ),
				'tooltip' => __( 'The custom script you would like to run AFTER we successfully complete installing our a new WordPress site. Note that if you are using our WooCommerce functions, this runs before any template sites are copied over.', 'wpcd' ),
			),
		);

		// Let developers hook into the array here.
		$scripts = apply_filters( 'wpcd_wordpress-app_custom_script_fields_list', $scripts );

		// Field id prefix.
		$wpcd_id_prefix = 'wpcd_wpapp_custom_script';

		// Subtab that the fields will display on.
		$subtab = 'wordpress-app-custom-scripts';

		// Heading.
		$fields[] = array(
			'name' => __( 'Custom Bash Scripts', 'wpcd' ),
			'id'   => $wpcd_id_prefix . '_heading',
			'type' => 'heading',
			'std'  => '',
			'desc' => __( 'Setup the location of BASH custom scripts that are run after certain processes are complete. Using these scripts are easier that writing custom add-ons - but only if you have no need to update data/metas on the WPCD site.', 'wpcd' ),
			'tab'  => $subtab,
		);

		// Loop through the scripts array and render TEXT fields to collect the names of scripts.
		// We'd love to do two columns here like we do on the owner security tab.
		// Unfortunately METABOX.io messes things up on the other subtabs when we try.
		foreach ( $scripts as $script_key => $script_data ) {

			// Pull some data out of the $script_data var.
			$script_label   = $script_data['label'];
			$script_tooltip = $script_data['tooltip'];

			$fields[] = array(
				'name'    => "{$script_label}",
				'id'      => "{$wpcd_id_prefix}_{$script_key}",
				'type'    => 'url',
				'tab'     => $subtab,
				'tooltip' => $script_tooltip,
			);

		}

		// Secrets Manager API Key.
		$secrets_mgr_desc  = __( 'You can enter an api key for a secrets manager here.  It will then be available in your environment for use within your custom scripts.  An example of a secrets manager is doppler.com but you can use any manager that only uses a singlet token.', 'wpcd' );
		$secrets_mgr_desc .= '<br />';
		$secrets_mgr_desc .= __( 'With a secrets manager you can safely pull in other api keys such as private github keys needed to access private resources.', 'wpcd' );

		$fields[] = array(
			'name' => __( 'Secrets Manager API Key', 'wpcd' ),
			'id'   => $wpcd_id_prefix . 'secrets_manager_heading',
			'type' => 'heading',
			'std'  => '',
			'desc' => $secrets_mgr_desc,
			'tab'  => $subtab,
		);
		$fields[] = array(
			'name' => __( 'Secrets Manager API Key', 'wpcd' ),
			'id'   => "{$wpcd_id_prefix}_secrets_manager_api_key",
			'type' => 'text',
			'tab'  => $subtab,
		);

		return $fields;

	}

}
