<?php
/**
 * Tweaks Tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_TWEAKS
 */
class WPCD_WORDPRESS_TABS_TWEAKS extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );
	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'tweaks';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_tweaks_tab';
	}

	/**
	 * Populates the tab name.
	 *
	 * @param array $tabs The default value.
	 * @param int   $id   The post ID of the server.
	 *
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs, $id ) {
		if ( $this->get_tab_security( $id ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'Tweaks', 'wpcd' ),
				'icon'  => 'fad fa-car-tilt',
			);
		}
		return $tabs;
	}

	/**
	 * Checks whether or not the user can view the current tab.
	 *
	 * @param int $id The post ID of the site.
	 *
	 * @return boolean
	 */
	public function get_tab_security( $id ) {
		return ( true === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) );
	}

	/**
	 * Gets the fields to be shown in the TWEAKS tab.
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_get_tabs
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_tab_fields( array $fields, $id ) {

		// If user is not allowed to access the tab then don't paint the fields.
		if ( ! $this->get_tab_security( $id ) ) {
			return $fields;
		}

		return $this->get_fields_for_tab( $fields, $id, $this->get_tab_slug() );

	}

	/**
	 * Called when an action needs to be performed on the tab.
	 *
	 * @param mixed  $result The default value of the result.
	 * @param string $action The action to be performed.
	 * @param int    $id The post ID of the app.
	 *
	 * @return mixed    $result The default value of the result.
	 */
	public function tab_action( $result, $action, $id ) {

		/* Verify that the user is even allowed to view the app before proceeding to do anything else */
		if ( ! $this->wpcd_user_can_view_wp_app( $id ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}
		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'tweaks-toggle-xmlrpc', 'tweaks-toggle-xfo-sameorigin', 'tweaks-toggle-xfo-deny', 'tweaks-toggle-csp-default', 'tweaks-toggle-hsts', 'tweaks-toggle-xss', 'tweaks-toggle-default-referrer-policy', 'tweaks-toggle-default-permission-policy', 'tweaks-toggle-restapi', 'tweaks-toggle-gzip-domain', 'tweaks-toggle-browser-cache', 'tweaks-toggle-fail2ban-domain', 'tweaks-change-file-upload-size-action' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				/* We prefer not to use a 'default' statement in switches just in case someone pushes in bad data from the front-end and bypasses the security check above. In that case no code will execute without a default block.*/
				case 'tweaks-toggle-xmlrpc':
				case 'tweaks-toggle-xfo-sameorigin':
				case 'tweaks-toggle-xfo-deny':
				case 'tweaks-toggle-csp-default':
				case 'tweaks-toggle-hsts':
				case 'tweaks-toggle-xss':
				case 'tweaks-toggle-default-referrer-policy':
				case 'tweaks-toggle-default-permission-policy':
				case 'tweaks-toggle-restapi':
				case 'tweaks-toggle-gzip-domain':
				case 'tweaks-toggle-browser-cache':
					$result = $this->tweaks_toggle_a_thing( $id, $action );
					if ( ! is_wp_error( $result ) ) {
						$result = array( 'refresh' => 'yes' );
					}
					break;
				case 'tweaks-toggle-fail2ban-domain':
					$result = $this->tweaks_toggle_fail2ban( $id, $action );
					if ( ! is_wp_error( $result ) ) {
						$result = array( 'refresh' => 'yes' );
					}
				case 'tweaks-change-file-upload-size-action':
					$result = $this->tweaks_change_upload_file_size( $id, $action );
					if ( ! is_wp_error( $result ) ) {
						$result = array( 'refresh' => 'yes' );
					}
			}
		}
		return $result;
	}

	/**
	 * Gets the actions to be shown in the PHP tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		if ( ! $this->is_460_or_later( $id ) ) {
			return $this->pre_460_warning_header( $id );
		}

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return $this->get_disabled_header_field();
		}

		// Basic checks passed, ok to proceed.
		return array_merge(
			$this->get_tweak_header_security_fields( $id ),
			$this->get_security_fields( $id ),
			$this->get_fail2ban_fields( $id ),
			$this->get_tweak_header_performance_fields( $id ),
			$this->get_performance_fields( $id ),
			$this->get_increase_fileupload_size_fields( $id ),
		);

	}

	/**
	 * Gets the main header fields for the security section of tweaks
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_tweak_header_security_fields( $id ) {

		$actions = array();

		$actions['tweaks-security-header'] = array(
			'label'          => __( 'Security Tweaks', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Tweaks that could help improve the security of your site.', 'wpcd' ),
			),
		);

		return $actions;

	}

	/**
	 * Gets the main header fields for the performance section of tweaks
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_tweak_header_performance_fields( $id ) {

		$actions = array();

		$actions['tweaks-performance-header'] = array(
			'label'          => __( 'Performance Tweaks', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Tweaks that could help improve the performance of your site.', 'wpcd' ),
			),
		);

		return $actions;

	}

	/**
	 * Returns header fields that warns the user the tweaks screen is not available if the
	 * server is not a 460 server.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function pre_460_warning_header( $id ) {

		$actions = array();

		$actions['tweaks-not-available-warning'] = array(
			'label'          => __( 'Feature Not Available', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'You must upgrade your server to WPCD 4.6.0 or later before the features on this tab will be available. You can do that under the server UPGRADE tab for the server on which this site is located.', 'wpcd' ),
			),
		);

		return $actions;

	}

	/**
	 * Gets the fields for the security section on the TWEAKS tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_security_fields( $id ) {

		$actions = array();

		/* XMLRPC */
		$xmlrpc_status = $this->get_meta_value( $id, 'wpcd_wpapp_xmlrpc_status', 'on', 'on' );

		/* Set the text of the confirmation prompt */
		$xmlrpc_confirmation_prompt = 'on' === $xmlrpc_status ? __( 'Are you sure you would like to disable XMLRPC for this site?', 'wpcd' ) : __( 'Are you sure you would like to enable XMLRPC for this site?', 'wpcd' );

		$actions['tweaks-toggle-xmlrpc'] = array(
			'label'          => __( 'XMLRPC', 'wpcd' ),
			'raw_attributes' => array(
				'on_label'            => __( 'Enabled', 'wpcd' ),
				'off_label'           => __( 'Disabled', 'wpcd' ),
				'std'                 => $xmlrpc_status === 'on',
				'confirmation_prompt' => $xmlrpc_confirmation_prompt,
				'desc'                => __( 'Enable or Disable XMLRPC for this site.', 'wpcd' ),
				'columns'             => 6,
			),
			'type'           => 'switch',
		);

		/**
		 * XFRAME OPTIONS - SAMEORIGIN
		 */
		$xfo_sameorigin_status = $this->get_meta_value( $id, 'wpcd_wpapp_xfo_sameorigin_status', 'on', 'off' );

		/* Set the text of the confirmation prompt */
		$xfo_sameorigin_confirmation_prompt = 'on' === $xfo_sameorigin_status ? __( 'Are you sure you would like to disable X-FRAME-OPTIONS: SAME ORIGIN for this site?', 'wpcd' ) : __( 'Are you sure you would like to enable X-FRAME-OPTIONS: SAME ORIGIN for this site?', 'wpcd' );

		$actions['tweaks-toggle-xfo-sameorigin'] = array(
			'label'          => __( 'X-Frame-Options: SAMEORIGIN', 'wpcd' ),
			'raw_attributes' => array(
				'on_label'            => __( 'Enabled', 'wpcd' ),
				'off_label'           => __( 'Disabled', 'wpcd' ),
				'std'                 => $xfo_sameorigin_status === 'on',
				'confirmation_prompt' => $xfo_sameorigin_confirmation_prompt,
				'desc'                => __( 'Enable or Disable X-FRAME-OPTIONS: SAME ORIGIN for this site.', 'wpcd' ),
				'columns'             => 6,
			),
			'type'           => 'switch',
		);

		/**
		 * Default CSP
		 */
		$csp_default_status = $this->get_meta_value( $id, 'wpcd_wpapp_csp_default_status', 'off', 'off' );

		/* Set the text of the confirmation prompt */
		$xfo_csp_default_confirmation_prompt = 'on' === $csp_default_status ? __( 'Are you sure you would like to disable the default Content Security Policy for this site?', 'wpcd' ) : __( 'Are you sure you would like to enable the default Content Security Policy for this site?', 'wpcd' );

		$actions['tweaks-toggle-csp-default'] = array(
			'label'          => __( 'Default Content Security Policy', 'wpcd' ),
			'raw_attributes' => array(
				'on_label'            => __( 'Enabled', 'wpcd' ),
				'off_label'           => __( 'Disabled', 'wpcd' ),
				'std'                 => $csp_default_status === 'on',
				'confirmation_prompt' => $xfo_csp_default_confirmation_prompt,
				'desc'                => __( 'Enable or Disable the default Content Security Policy for this site.', 'wpcd' ),
				'columns'             => 6,
				'tooltip'             => sprintf( __( 'The default content security policy is: %s', 'wpcd' ), "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; connect-src 'self'; img-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self' data: 'always';" ),
			),
			'type'           => 'switch',
		);

		/**
		 * XFRAME OPTIONS - DENY
		 */
		$xfo_deny_status = $this->get_meta_value( $id, 'wpcd_wpapp_xfo_deny_status', 'off', 'off' );

		/* Set the text of the confirmation prompt */
		$xfo_deny_confirmation_prompt = 'on' === $xfo_deny_status ? __( 'Are you sure you would like to disable X-FRAME-OPTIONS: DENY for this site?', 'wpcd' ) : __( 'Are you sure you would like to enable X-FRAME-OPTIONS: DENY for this site?', 'wpcd' );

		$actions['tweaks-toggle-xfo-deny'] = array(
			'label'          => __( 'X-Frame-Options: DENY', 'wpcd' ),
			'raw_attributes' => array(
				'on_label'            => __( 'Enabled', 'wpcd' ),
				'off_label'           => __( 'Disabled', 'wpcd' ),
				'std'                 => $xfo_deny_status === 'on',
				'confirmation_prompt' => $xfo_deny_confirmation_prompt,
				'desc'                => __( 'Enable or Disable X-FRAME-OPTIONS - DENY for this site.', 'wpcd' ),
				'columns'             => 6,
			),
			'type'           => 'switch',
		);

		/**
		 * HSTS
		 */
		$hsts_status = $this->get_meta_value( $id, 'wpcd_wpapp_hsts_status', 'on', 'off' );

		/* Set the text of the confirmation prompt */
		$hsts_confirmation_prompt = 'on' === $hsts_status ? __( 'Are you sure you would like to disable HSTS for this site?', 'wpcd' ) : __( 'Are you sure you would like to enable HSTS for this site?', 'wpcd' );

		$actions['tweaks-toggle-hsts'] = array(
			'label'          => __( 'HSTS', 'wpcd' ),
			'raw_attributes' => array(
				'on_label'            => __( 'Enabled', 'wpcd' ),
				'off_label'           => __( 'Disabled', 'wpcd' ),
				'std'                 => $hsts_status === 'on',
				'confirmation_prompt' => $hsts_confirmation_prompt,
				'desc'                => __( 'Enable or Disable HSTS for this site.', 'wpcd' ),
				'tooltip'             => __( 'HSTS = HTTP Strict Transport Security', 'wpcd' ),
				'columns'             => 6,
			),
			'type'           => 'switch',
		);

		/**
		 * XSS
		 */
		$xss_status = $this->get_meta_value( $id, 'wpcd_wpapp_xss_status', 'on', 'off' );

		/* Set the text of the confirmation prompt */
		$xss_confirmation_prompt = 'on' === $xss_status ? __( 'Are you sure you would like to disable XSS protection for this site?', 'wpcd' ) : __( 'Are you sure you would like to enable XSS protection for this site?', 'wpcd' );

		$actions['tweaks-toggle-xss'] = array(
			'label'          => __( 'XSS', 'wpcd' ),
			'raw_attributes' => array(
				'on_label'            => __( 'Enabled', 'wpcd' ),
				'off_label'           => __( 'Disabled', 'wpcd' ),
				'std'                 => $xss_status === 'on',
				'confirmation_prompt' => $xss_confirmation_prompt,
				'desc'                => __( 'Enable or Disable XSS protection for this site.', 'wpcd' ),
				'tooltip'             => __( 'XSS = Cross Site Scripting. Protection is enabled via the X-XSS-Protection header.  It is useful for older browsers or if you have not enabled the appropriate Content Security header directives that would provide similar protection.', 'wpcd' ),
				'columns'             => 6,
			),
			'type'           => 'switch',
		);

		/**
		 * REFERRER Policy
		 */
		$default_referrer_policy_status = $this->get_meta_value( $id, 'wpcd_wpapp_default_referrer_policy_status', 'on', 'off' );

		/* Set the text of the confirmation prompt */
		$default_referrer_policy_confirmation_prompt = 'on' === $default_referrer_policy_status ? __( 'Are you sure you would like to disable the default REFERRER Policy protection for this site?', 'wpcd' ) : __( 'Are you sure you would like to enable the default REFERRER Policy protection for this site?', 'wpcd' );

		$actions['tweaks-toggle-default-referrer-policy'] = array(
			'label'          => __( 'Default Referrer Policy', 'wpcd' ),
			'raw_attributes' => array(
				'on_label'            => __( 'Enabled', 'wpcd' ),
				'off_label'           => __( 'Disabled', 'wpcd' ),
				'std'                 => $default_referrer_policy_status === 'on',
				'confirmation_prompt' => $default_referrer_policy_confirmation_prompt,
				'desc'                => __( 'Enable or Disable the default Referrer Policy protection for this site.', 'wpcd' ),
				'tooltip'             => __( 'Adds the REFERRER POLICY "no-referrer, strict-origin-when-cross-origin" header when enabled.', 'wpcd' ),
				'columns'             => 6,
			),
			'type'           => 'switch',
		);

		/**
		 * Default Permission Policy
		 */
		$default_permission_policy_status = $this->get_meta_value( $id, 'wpcd_wpapp_default_permission_policy_status', 'off', 'off' );

		/* Set the text of the confirmation prompt */
		$default_permission_policy_confirmation_prompt = 'on' === $default_permission_policy_status ? __( 'Are you sure you would like to disable the default PERMISSIONS POLICY protection for this site?', 'wpcd' ) : __( 'Are you sure you would like to enable the default PERMISSIONS POLICY protection for this site?', 'wpcd' );

		$actions['tweaks-toggle-default-permission-policy'] = array(
			'label'          => __( 'Default Permissions Policy', 'wpcd' ),
			'raw_attributes' => array(
				'on_label'            => __( 'Enabled', 'wpcd' ),
				'off_label'           => __( 'Disabled', 'wpcd' ),
				'std'                 => $default_permission_policy_status === 'on',
				'confirmation_prompt' => $default_permission_policy_confirmation_prompt,
				'desc'                => __( 'Enable or Disable the default Permissions Policy protection for this site.', 'wpcd' ),
				'tooltip'             => sprintf( __( 'The default Permissions policy is: %s', 'wpcd' ), 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()' ),
				'columns'             => 6,
			),
			'type'           => 'switch',
		);

		/**
		 * REST API
		 */
		$restapi_status = $this->get_meta_value( $id, 'wpcd_wpapp_restapi_status', 'off', 'off' );

		/* Set the text of the confirmation prompt */
		$restapi_confirmation_prompt = 'on' === $restapi_status ? __( 'Are you sure you would like to disable the WP REST API for this site?', 'wpcd' ) : __( 'Are you sure you would like to enable the WP REST API for this site?', 'wpcd' );

		$actions['tweaks-toggle-restapi'] = array(
			'label'          => __( 'Rest API', 'wpcd' ),
			'raw_attributes' => array(
				'on_label'            => __( 'Blocked', 'wpcd' ),
				'off_label'           => __( 'Not Blocked', 'wpcd' ),
				'std'                 => $restapi_status === 'on',
				'confirmation_prompt' => $restapi_confirmation_prompt,
				'desc'                => __( 'Enable or Disable the REST API for this site. Turning off the RESTAPI can break some things so test your site fully after turning on this option!', 'wpcd' ),
				'tooltip'             => __( 'We will be installing a 3rd party plugin named DISABLE-JSON-API to enable this feature.  Therefore this is not a fully supported function in WPCD!', 'wpcd' ),
				'columns'             => 6,
			),
			'type'           => 'switch',
		);

		return $actions;

	}

	/**
	 * Gets the fields for the performance section on the TWEAKS tab.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_performance_fields( $id ) {

		$actions = array();

		/**
		 * GZIP
		 */
		$gzip_status = $this->get_meta_value( $id, 'wpcd_wpapp_gzip_domain_status', 'on', 'off' );

		/* Set the text of the confirmation prompt */
		$gzip_confirmation_prompt = 'on' === $gzip_status ? __( 'Are you sure you would like to disable GZIP for this site?', 'wpcd' ) : __( 'Are you sure you would like to enable GZIP for this site?', 'wpcd' );

		$actions['tweaks-toggle-gzip-domain'] = array(
			'label'          => __( 'Gzip', 'wpcd' ),
			'raw_attributes' => array(
				'on_label'            => __( 'Enabled', 'wpcd' ),
				'off_label'           => __( 'Disabled', 'wpcd' ),
				'std'                 => $gzip_status === 'on',
				'confirmation_prompt' => $gzip_confirmation_prompt,
				'desc'                => __( 'Enable or Disable Gzip for this site.', 'wpcd' ),
				'tooltip'             => __( 'When enabled, the list of file types that are compressed is located in the /etc/nginx/common/gzip.conf file on your server.', 'wpcd' ),
				'columns'             => 6,
			),
			'type'           => 'switch',
		);

		/**
		 * BROWSER CACHING FOR IMAGES, SCRIPTS & STYLES
		 */
		$browser_cache_status = $this->get_meta_value( $id, 'wpcd_wpapp_browser_cache_status', 'on', 'off' );

		/* Set the text of the confirmation prompt */
		$browser_cache_confirmation_prompt = 'on' === $browser_cache_status ? __( 'Are you sure you would like to disable caching of static resources such as images, scripts & styles in browsers?', 'wpcd' ) : __( 'Are you sure you would like to enable caching of static resources such as images, scripts & styles in browsers?', 'wpcd' );

		$actions['tweaks-toggle-browser-cache'] = array(
			'label'          => __( 'Browser Cache', 'wpcd' ),
			'raw_attributes' => array(
				'on_label'            => __( 'Enabled', 'wpcd' ),
				'off_label'           => __( 'Disabled', 'wpcd' ),
				'std'                 => $browser_cache_status === 'on',
				'confirmation_prompt' => $browser_cache_confirmation_prompt,
				'desc'                => __( 'Enable or Disable caching static resources such as images, js scripts and css stylesheets in the user browser.', 'wpcd' ),
				'tooltip'             => __( 'When enabled, the list of file types that are cached in the browser is located in the /etc/nginx/common/browsercache.conf file on your server.', 'wpcd' ),
				'columns'             => 6,
			),
			'type'           => 'switch',
		);

		return $actions;
	}

	/**
	 * Gets the fields for the Fail2Ban TWEAKS section.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_fail2ban_fields( $id ) {

		$actions = array();

		// What's the server id?
		$server_id = $this->get_server_id_by_app_id( $id );
		if ( ! $server_id ) {
			return $actions;
		}

		// Is fail2ban installed on the server?
		$f2b_installed = get_post_meta( $server_id, 'wpcd_wpapp_fail2ban_installed', true );

		// Return a single header in the array if fail2ban is not installed on the server.
		if ( 'yes' !== $f2b_installed ) {
			$actions['tweaks-fail2ban-header-not-installed'] = array(
				'label'          => __( 'Fail2Ban', 'wpcd' ),
				'type'           => 'heading',
				'raw_attributes' => array(
					'desc' => __( 'Fail2ban is not installed on the server so no options are available for it at this time for this site.', 'wpcd' ),
				),
			);
			return $actions;
		}

		// Fail2ban is installed - ok to show options related to it.
		$actions['tweaks-fail2ban-header'] = array(
			'label'          => __( 'Fail2Ban', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Add or remove the Fail2ban WP plugin for this site.', 'wpcd' ),
			),
		);

		$fail2ban_status = $this->get_meta_value( $id, 'wpcd_wpapp_fail2ban_domain_status', 'off', 'off' );

		/* Set the text of the confirmation prompt */
		$fail2ban_confirmation_prompt = 'on' === $fail2ban_status ? __( 'Are you sure you would like to remove the fail2ban plugin for this site?', 'wpcd' ) : __( 'Are you sure you would like to install the fail2ban plugin for this site?', 'wpcd' );

		$actions['tweaks-toggle-fail2ban-domain'] = array(
			'label'          => __( 'Fail2ban', 'wpcd' ),
			'raw_attributes' => array(
				'on_label'            => __( 'Enabled', 'wpcd' ),
				'off_label'           => __( 'Disabled', 'wpcd' ),
				'std'                 => $fail2ban_status === 'on',
				'confirmation_prompt' => $fail2ban_confirmation_prompt,
				'desc'                => __( 'Enable or Disable the Fail2ban plugin for this site.', 'wpcd' ),
				'columns'             => 6,
			),
			'type'           => 'switch',
		);

		return $actions;
	}

	/**
	 * Gets the fields for the File Upload TWEAKS section.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_increase_fileupload_size_fields( $id ) {

		$actions = array();

		// What's the server id?
		$server_id = $this->get_server_id_by_app_id( $id );
		if ( ! $server_id ) {
			return $actions;
		}

		// Did we set a size before?
		$existing_size = get_post_meta( $id, 'wpcd_wpapp_file_upload_size', true );
		if ( empty( $existing_size ) ) {
			$existing_size = 25;
		}

		// Header.
		$actions['tweaks-change-fileupload-size-header'] = array(
			'label'          => __( 'File Upload Size', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => __( 'Change the maximum allowed size for file uploads in PHP and NGINX configuration files.', 'wpcd' ),
			),
		);

		$actions['tweaks-change-fileupload-size'] = array(
			'label'          => __( 'Size (MB)', 'wpcd' ),
			'raw_attributes' => array(
				'placeholder'    => __( 'New Upload Size', 'wpcd' ),
				'std'            => $existing_size,
				'size'           => 20,
				// the key of the field (the key goes in the request).
				'data-wpcd-name' => 'file_upload_size',
			),
			'type'           => 'number',
		);

		/* Set the text of the confirmation prompt */
		$uploadsize_confirmation_prompt = __( 'Are you sure you would like to change the maximum upload size for this site?', 'wpcd' );

		$actions['tweaks-change-file-upload-size-action'] = array(
			'label'          => '',
			'raw_attributes' => array(
				'std'                 => __( 'Change', 'wpcd' ),
				'confirmation_prompt' => $uploadsize_confirmation_prompt,
				// fields that contribute data for this action.
				'data-wpcd-fields'    => json_encode( array( '#wpcd_app_action_tweaks-change-fileupload-size' ) ),

			),
			'type'           => 'button',
		);

		return $actions;
	}

	/**
	 * Get the current value of an on/off meta value from the server field.
	 *
	 * This is just a get_post_meta but sets a default value if nothing exists.
	 * The default can be different based on whether the server was installed before
	 * V 4.6.0 or after.
	 *
	 * @param int    $id                     postid of server record.
	 * @param string $meta_name              name of meta value to get.
	 * @param string $default_460_value      default value if meta isn't set and current version of app is 4.6.0 or greater.
	 * @param string $default_pre_460_value  default value if meta isn't set and current version of app is less than 4.6.0.
	 *
	 * @return mixed|string
	 */
	private function get_meta_value( $id, $meta_name, $default_460_value, $default_pre_460_value ) {

		$status = get_post_meta( $id, $meta_name, true );
		if ( empty( $status ) ) {
			if ( true === $this->is_460_or_later( $id ) ) {
				$status = $default_460_value;
			} else {
				$status = $default_pre_460_value;
			}
		}

		return $status;

	}

	/**
	 * Toggle the status for certain items in a site's .conf file.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function tweaks_toggle_a_thing( $id, $action ) {
		
		// What type of web server are we running?
		$webserver_type      = $this->get_web_server_type( $id );
		$webserver_type_name = $this->get_web_server_description_by_id( $id );

		switch ( $webserver_type ) {
			case 'ols':
			case 'ols-enterprise':
				$bridge_file = 'ols_options.txt';
				break;

			case 'nginx':
			default:
				$bridge_file = 'nginx_options.txt';
				break;

		}		

		// Action name that we'll be sending to the server.
		$server_action = '';

		switch ( $action ) {
			case 'tweaks-toggle-xmlrpc':
				// What is the current XMLRPC status?
				$xmlrpc_status = $this->get_meta_value( $id, 'wpcd_wpapp_xmlrpc_status', 'on', 'on' );

				// Figure out the proper action to send to the server script.
				if ( 'on' === $xmlrpc_status ) {
					// currently on so turn it off.
					$server_action = 'disable_xmlrpc';
				} else {
					$server_action = 'enable_xmlrpc';
				}
				break;
			case 'tweaks-toggle-xfo-sameorigin':
				$xfo_sameorigin_status = $this->get_meta_value( $id, 'wpcd_wpapp_xfo_sameorigin_status', 'on', 'off' );

				// Figure out the proper action to send to the server script.
				if ( 'on' === $xfo_sameorigin_status ) {
					// currently on so turn it off.
					$server_action = 'disable_xfo';
				} else {
					$server_action = 'enable_xfo_sameorigin';
				}
				break;
			case 'tweaks-toggle-xfo-deny':
				$xfo_deny_status = $this->get_meta_value( $id, 'wpcd_wpapp_xfo_deny_status', 'off', 'off' );

				// Figure out the proper action to send to the server script.
				if ( 'on' === $xfo_deny_status ) {
					// currently on so turn it off.
					$server_action = 'disable_xfo';
				} else {
					$server_action = 'enable_xfo_deny';
				}
				break;
			case 'tweaks-toggle-csp-default':
				$csp_default_status = $this->get_meta_value( $id, 'wpcd_wpapp_csp_default_status', 'off', 'off' );

				// Figure out the proper action to send to the server script.
				if ( 'on' === $csp_default_status ) {
					// currently on so turn it off.
					$server_action = 'disable_csp';
				} else {
					$server_action = 'enable_default_csp';
				}
				break;
			case 'tweaks-toggle-hsts':
				$hsts_status = $this->get_meta_value( $id, 'wpcd_wpapp_hsts_status', 'on', 'off' );

				// Figure out the proper action to send to the server script.
				if ( 'on' === $hsts_status ) {
					// currently on so turn it off.
					$server_action = 'disable_hsts';
				} else {
					$server_action = 'enable_hsts';
				}
				break;
			case 'tweaks-toggle-xss':
				$xss_status = $this->get_meta_value( $id, 'wpcd_wpapp_xss_status', 'on', 'off' );

				// Figure out the proper action to send to the server script.
				if ( 'on' === $xss_status ) {
					// currently on so turn it off.
					$server_action = 'disable_xss';
				} else {
					$server_action = 'enable_xss';
				}
				break;
			case 'tweaks-toggle-default-referrer-policy':
				$default_referrer_policy_status = $this->get_meta_value( $id, 'wpcd_wpapp_default_referrer_policy_status', 'on', 'off' );

				// Figure out the proper action to send to the server script.
				if ( 'on' === $default_referrer_policy_status ) {
					// currently on so turn it off.
					$server_action = 'disable_rp';
				} else {
					$server_action = 'enable_rp';
				}
				break;
			case 'tweaks-toggle-default-permission-policy':
				$default_permission_policy_status = $this->get_meta_value( $id, 'wpcd_wpapp_default_permission_policy_status', 'off', 'off' );

				// Figure out the proper action to send to the server script.
				if ( 'on' === $default_permission_policy_status ) {
					// currently on so turn it off.
					$server_action = 'disable_pp';
				} else {
					$server_action = 'enable_default_pp';
				}
				break;
			case 'tweaks-toggle-restapi':
				$restapi_status = $this->get_meta_value( $id, 'wpcd_wpapp_restapi_status', 'off', 'off' );

				// Figure out the proper action to send to the server script.
				if ( 'off' === $restapi_status ) {
					// Currently not blocked so block it.
					$server_action = 'disable_restapi';
				} else {
					// Currently blocked so unblock it.
					$server_action = 'enable_restapi';
				}
				break;

			/**
			 * Performance options section
			 */

			case 'tweaks-toggle-gzip-domain':
				$gzip_status = $this->get_meta_value( $id, 'wpcd_wpapp_gzip_domain_status', 'on', 'off' );

				// Figure out the proper action to send to the server script.
				if ( 'on' === $gzip_status ) {
					// currently on so turn it off.
					$server_action = 'disable_gzip_domain';
				} else {
					$server_action = 'enable_gzip_domain';
				}
				break;
			case 'tweaks-toggle-browser-cache':
				$browser_cache_status = $this->get_meta_value( $id, 'wpcd_wpapp_browser_cache_status', 'on', 'off' );

				// Figure out the proper action to send to the server script.
				if ( 'on' === $browser_cache_status ) {
					// currently on so turn it off.
					$server_action = 'disable_cache_include';
				} else {
					$server_action = 'enable_cache_include';
				}
				break;
		}

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			$bridge_file,
			array(
				'action' => $server_action,
				'domain' => get_post_meta(
					$id,
					'wpapp_domain',
					true
				),
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// Run the command.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, $bridge_file );

		// Check for success.
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s for site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// Update metas...
		switch ( $action ) {
			case 'tweaks-toggle-xmlrpc':
				if ( 'on' === $xmlrpc_status ) {
					// currently on so turn it off.
					update_post_meta( $id, 'wpcd_wpapp_xmlrpc_status', 'off' );
				} else {
					update_post_meta( $id, 'wpcd_wpapp_xmlrpc_status', 'on' );
				}
				break;
			case 'tweaks-toggle-xfo-sameorigin':
				if ( 'on' === $xfo_sameorigin_status ) {
					// currently on so turn it off.
					update_post_meta( $id, 'wpcd_wpapp_xfo_sameorigin_status', 'off' );
					update_post_meta( $id, 'wpcd_wpapp_xfo_deny_status', 'off' );
				} else {
					update_post_meta( $id, 'wpcd_wpapp_xfo_sameorigin_status', 'on' );
					update_post_meta( $id, 'wpcd_wpapp_xfo_deny_status', 'off' );
				}
				break;
			case 'tweaks-toggle-xfo-deny':
				if ( 'on' === $xfo_deny_status ) {
					// currently on so turn it off.
					update_post_meta( $id, 'wpcd_wpapp_xfo_deny_status', 'off' );
					update_post_meta( $id, 'wpcd_wpapp_xfo_sameorigin_status', 'off' );
				} else {
					update_post_meta( $id, 'wpcd_wpapp_xfo_deny_status', 'on' );
					update_post_meta( $id, 'wpcd_wpapp_xfo_sameorigin_status', 'off' );
				}
				break;
			case 'tweaks-toggle-csp-default':
				if ( 'on' === $csp_default_status ) {
					// currently on so turn it off.
					update_post_meta( $id, 'wpcd_wpapp_csp_default_status', 'off' );
					update_post_meta( $id, 'wpcd_wpapp_csp_custom_status', 'off' );
				} else {
					update_post_meta( $id, 'wpcd_wpapp_csp_default_status', 'on' );
					update_post_meta( $id, 'wpcd_wpapp_csp_custom_status', 'off' );
				}
				break;
			case 'tweaks-toggle-hsts':
				if ( 'on' === $hsts_status ) {
					// currently on so turn it off.
					update_post_meta( $id, 'wpcd_wpapp_hsts_status', 'off' );
				} else {
					update_post_meta( $id, 'wpcd_wpapp_hsts_status', 'on' );
				}
				break;
			case 'tweaks-toggle-xss':
				if ( 'on' === $xss_status ) {
					// currently on so turn it off.
					update_post_meta( $id, 'wpcd_wpapp_xss_status', 'off' );
				} else {
					update_post_meta( $id, 'wpcd_wpapp_xss_status', 'on' );
				}
				break;
			case 'tweaks-toggle-default-referrer-policy':
				if ( 'on' === $default_referrer_policy_status ) {
					// currently on so turn it off.
					update_post_meta( $id, 'wpcd_wpapp_default_referrer_policy_status', 'off' );
				} else {
					update_post_meta( $id, 'wpcd_wpapp_default_referrer_policy_status', 'on' );
				}
				break;
			case 'tweaks-toggle-default-permission-policy':
				if ( 'on' === $default_permission_policy_status ) {
					// currently on so turn it off.
					update_post_meta( $id, 'wpcd_wpapp_default_permission_policy_status', 'off' );
					update_post_meta( $id, 'wpcd_wpapp_custom_permission_policy_status', 'off' );
				} else {
					update_post_meta( $id, 'wpcd_wpapp_default_permission_policy_status', 'on' );
					update_post_meta( $id, 'wpcd_wpapp_custom_permission_policy_status', 'off' );
				}
				break;
			case 'tweaks-toggle-restapi':
				if ( 'on' === $restapi_status ) {
					// currently on so turn it off.
					update_post_meta( $id, 'wpcd_wpapp_restapi_status', 'off' );
				} else {
					update_post_meta( $id, 'wpcd_wpapp_restapi_status', 'on' );
				}
				break;

			/**
			 * Performance options section
			 */

			case 'tweaks-toggle-gzip-domain':
				if ( 'on' === $gzip_status ) {
					// currently on so turn it off.
					update_post_meta( $id, 'wpcd_wpapp_gzip_domain_status', 'off' );
				} else {
					update_post_meta( $id, 'wpcd_wpapp_gzip_domain_status', 'on' );
				}
				break;
			case 'tweaks-toggle-browser-cache':
				if ( 'on' === $browser_cache_status ) {
					// currently on so turn it off.
					update_post_meta( $id, 'wpcd_wpapp_browser_cache_status', 'off' );
				} else {
					update_post_meta( $id, 'wpcd_wpapp_browser_cache_status', 'on' );
				}
				break;

		}

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( __( 'The status for the selected item has been toggled.', 'wpcd' ) );

	}

	/**
	 * Add or remove the wp fail2ban plugin from the site.
	 *
	 * @param int    $id         The postID of the server cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function tweaks_toggle_fail2ban( $id, $action ) {

		// Action name that we'll be sending to the server.
		$server_action = '';

		// Figure out if we're adding or removing the plugin.
		$fail2ban_status = $this->get_meta_value( $id, 'wpcd_wpapp_fail2ban_domain_status', 'off', 'off' );
		// Figure out the proper action to send to the server script.
		if ( 'on' === $fail2ban_status ) {
			// currently on so turn it off.
			$server_action = 'fail2ban_wp_remove';
		} else {
			$server_action = 'fail2ban_wp_install';
		}

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'fail2ban_site.txt',
			array(
				'action' => $server_action,
				'domain' => get_post_meta(
					$id,
					'wpapp_domain',
					true
				),
			)
		);

		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// Run the command.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'fail2ban_site.txt' );

		// Check for success.
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to %1$s for site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// Update metas...
		if ( 'on' === $fail2ban_status ) {
			// currently on so turn it off.
			update_post_meta( $id, 'wpcd_wpapp_fail2ban_domain_status', 'off' );
		} else {
			update_post_meta( $id, 'wpcd_wpapp_fail2ban_domain_status', 'on' );
		}

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( __( 'The status for the selected item has been toggled.', 'wpcd' ) );
	}

	/**
	 * Change the maximum file size for uploads
	 *
	 * @param int    $id         The postID of the site cpt.
	 * @param string $action     The action to be performed (this matches the string required in the bash scripts if bash scripts are used ).
	 *
	 * @return boolean success/failure/other
	 */
	private function tweaks_change_upload_file_size( $id, $action ) {

		// Action name that we'll be sending to the server.
		$server_action = 'change_upload_limits';

		// Get details of server instance.
		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the field values from the front-end.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		if ( empty( $args['file_upload_size'] ) ) {
			/* translators: %s is replaced with the internal action name. */
			return new \WP_Error( sprintf( __( 'The upload file size must not be blank for action %s', 'wpcd' ), $action ) );
		} else {
			$upload_limit = $args['file_upload_size'];
		}

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'change_file_upload_size.txt',
			array(
				'action'       => $server_action,
				'domain'       => get_post_meta(
					$id,
					'wpapp_domain',
					true
				),
				'upload_limit' => $upload_limit,
			)
		);

		// log.
		// @codingStandardsIgnoreLine - added to ignore the print_r in the line below when linting with PHPcs.
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// Run the command.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'change_file_upload_size.txt' );

		// Check for success.
		if ( ! $success ) {
			/* Translators: %1$s is an internal action name. %2$s is an error message. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s for site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// Update metas...
			update_post_meta( $id, 'wpcd_wpapp_file_upload_size', $upload_limit );

		// Return the data as an error so it can be shown in a dialog box.
		return new \WP_Error( __( 'The maximum allowed file upload size has been updated.', 'wpcd' ) );
	}

}

new WPCD_WORDPRESS_TABS_TWEAKS();
