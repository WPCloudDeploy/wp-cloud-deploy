<?php
/**
 * 7G firewall tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_7G_FIREWALL
 */
class WPCD_WORDPRESS_TABS_7G_FIREWALL extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_BACKUP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return '7g_waf';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_7gfirewall_tab';
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
				'label' => __( '7G WAF', 'wpcd' ),
				'icon'  => 'fad fa-user-shield',
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
	 * Called when an action needs to be performed on the tab.
	 *
	 * @param mixed  $result The default value of the result.
	 * @param string $action The action to be performed.
	 * @param int    $id The post ID of the app.
	 *
	 * @return mixed    $result The default value of the result.
	 */
	public function tab_action( $result, $action, $id ) {

		/**
		 * Verify that the user is even allowed to view the app before proceeding to do anything else
		 */
		if ( ! $this->wpcd_user_can_view_wp_app( $id ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( '7g_all', '7g_user_agent', '7g_referrer', '7g_query_string', '7g_request_string', '7g_request_method' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {

				case '7g_all':
				case '7g_user_agent':
				case '7g_referrer':
				case '7g_query_string':
				case '7g_request_string':
				case '7g_request_method':
					$result = $this->manage_7g_waf( $id, $action );
					break;

			}
		}

		return $result;

	}

	/**
	 * Manage the 7G Firewall.
	 *
	 * @param int    $id the id of the app post being handled.
	 * @param string $action The action key to send to the bash script.  This is actually the key of the drop-down select.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function manage_7g_waf( $id, $action ) {

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %1$s in file %2$s', 'wpcd' ), $action, __FILE__ ) );
		}

		// Now, we need to figure out from the action request what the bash action should be.
		$bash_action = $this->convert_short_action_to_bash_action( $id, $action );

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'7g_firewall.txt',
			array(
				'action' => $bash_action,
				'domain' => $domain,
			)
		);  // Notice that, unlike other scripts, we're passing a different var for 'action'.

		// log.
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, '7g_firewall.txt' );
		if ( ! $success ) {
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// Now that we know we're successful, lets update a meta indicating the new status on the meta...
		$this->set_7g_status( $id, $action, $this->convert_bash_action_to_value( $bash_action ) );

		// Success message and force refresh.
		if ( ! is_wp_error( $result ) ) {
			$success_msg = __( '7G Rules has been updated for this site.', 'wpcd' );
			$result      = array(
				'msg'     => $success_msg,
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Gets the fields to be shown.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields( array $fields, $id ) {

		if ( ! $id ) {
			// id not found!
			return $fields;
		}

		// If user is not allowed to access the tab then don't paint the fields.
		if ( ! $this->get_tab_security( $id ) ) {
			return $fields;
		}

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return array_merge( $fields, $this->get_disabled_header_field( '7g_waf' ) );
		}

		// Basic checks passed, ok to proceed.
		$desc  = __( 'Manage the 7G WAF.', 'wpcd' );
		$desc .= '<br />';

		$fields[] = array(
			'name' => __( '7G Web Application Firewall / Powered by the Nginx Web Server Engine', 'wpcd' ),
			'tab'  => '7g_waf',
			'type' => 'heading',
			'desc' => $desc,
		);

		/**
		 * FIELDS FOR ENABLING/DISABLING ALL 7G RULES
		 */
			// What is the status of the 7G ALL RULES group?
			$status = $this->get_7g_status( $id, '7g_all' );

			/* Set the confirmation prompt based on the the current status of this flag */
			$confirmation_prompt = '';
		if ( 'on' === $status ) {
			$confirmation_prompt = __( 'Are you sure you would like to turn off this 7G WAF option for this site?', 'wpcd' );
		} else {
			$confirmation_prompt = __( 'Are you sure you would like to turn on this 7G WAF option for this site?', 'wpcd' );
		}

			$fields[] = array(
				'id'         => '7g-all-toggle',
				'name'       => __( 'Toggle ALL 7G Rules', 'wpcd' ),
				'tab'        => '7g_waf',
				'type'       => 'switch',
				'on_label'   => __( 'Enabled', 'wpcd' ),
				'off_label'  => __( 'Disabled', 'wpcd' ),
				'std'        => $status === 'on',
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => '7g_all',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => $confirmation_prompt,
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			$fields[] = array(
				'name' => __( 'Toggle Individual Components', 'wpcd' ),
				'type' => 'heading',
				'tab'  => '7g_waf',
			);

			/* FIELDS FOR ENABLING/DISABLING 7G USER AGENT RULES */

			// What is the status for this group?
			$status = $this->get_7g_status( $id, '7g_user_agent' );

			/* Set the confirmation prompt based on the the current status of this flag */
			$confirmation_prompt = '';
			if ( 'on' === $status ) {
				$confirmation_prompt = __( 'Are you sure you would like to turn off this 7G WAF option for this site?', 'wpcd' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to turn on this 7G WAF option for this site?', 'wpcd' );
			}
			$fields[] = array(
				'id'         => '7g-toggle-user-agent',
				'name'       => __( 'User Agent Rules', 'wpcd' ),
				'tab'        => '7g_waf',
				'type'       => 'switch',
				'on_label'   => __( 'Enabled', 'wpcd' ),
				'off_label'  => __( 'Disabled', 'wpcd' ),
				'std'        => $status === 'on',
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => '7g_user_agent',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => $confirmation_prompt,
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			/* FIELDS FOR ENABLING/DISABLING 7G REFERRER RULES */

			// What is the status for this group?
			$status = $this->get_7g_status( $id, '7g_referrer' );

			/* Set the confirmation prompt based on the the current status of this flag */
			$confirmation_prompt = '';
			if ( 'on' === $status ) {
				$confirmation_prompt = __( 'Are you sure you would like to turn off this 7G WAF option for this site?', 'wpcd' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to turn on this 7G WAF option for this site?', 'wpcd' );
			}
			$fields[] = array(
				'id'         => '7g-all-toggle-referrer',
				'name'       => __( 'Referrer Rules', 'wpcd' ),
				'tab'        => '7g_waf',
				'type'       => 'switch',
				'on_label'   => __( 'Enabled', 'wpcd' ),
				'off_label'  => __( 'Disabled', 'wpcd' ),
				'std'        => $status === 'on',
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => '7g_referrer',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => $confirmation_prompt,
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			/* FIELDS FOR ENABLING/DISABLING 7G QUERY STRING RULES */

			// What is the status for this group?
			$status = $this->get_7g_status( $id, '7g_query_string' );

			/* Set the confirmation prompt based on the the current status of this flag */
			$confirmation_prompt = '';
			if ( 'on' === $status ) {
				$confirmation_prompt = __( 'Are you sure you would like to turn off this 7G WAF option for this site?', 'wpcd' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to turn on this 7G WAF option for this site?', 'wpcd' );
			}
			$fields[] = array(
				'id'         => '7g-all-toggle-query-strings',
				'name'       => __( 'Query String Rules', 'wpcd' ),
				'tab'        => '7g_waf',
				'type'       => 'switch',
				'on_label'   => __( 'Enabled', 'wpcd' ),
				'off_label'  => __( 'Disabled', 'wpcd' ),
				'std'        => $status === 'on',
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => '7g_query_string',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => $confirmation_prompt,
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			/* FIELDS FOR ENABLING/DISABLING 7G REQUEST STRING RULES */

			// What is the status for this group?
			$status = $this->get_7g_status( $id, '7g_request_string' );

			/* Set the confirmation prompt based on the the current status of this flag */
			$confirmation_prompt = '';
			if ( 'on' === $status ) {
				$confirmation_prompt = __( 'Are you sure you would like to turn off this 7G WAF option for this site?', 'wpcd' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to turn on this 7G WAF option for this site?', 'wpcd' );
			}
			$fields[] = array(
				'id'         => '7g-all-toggle-request-string',
				'name'       => __( 'Request String Rules', 'wpcd' ),
				'tab'        => '7g_waf',
				'type'       => 'switch',
				'on_label'   => __( 'Enabled', 'wpcd' ),
				'off_label'  => __( 'Disabled', 'wpcd' ),
				'std'        => $status === 'on',
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => '7g_request_string',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => $confirmation_prompt,
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			/* FIELDS FOR ENABLING/DISABLING 7G REQUEST METHOD RULES */

			// What is the status for this group?
			$status = $this->get_7g_status( $id, '7g_request_method' );

			/* Set the confirmation prompt based on the the current status of this flag */
			$confirmation_prompt = '';
			if ( 'on' === $status ) {
				$confirmation_prompt = __( 'Are you sure you would like to turn off this 7G WAF option for this site?', 'wpcd' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to turn on this 7G WAF option for this site?', 'wpcd' );
			}
			$fields[] = array(
				'id'         => '7g-all-toggle-request-method',
				'name'       => __( 'Request Method Rules', 'wpcd' ),
				'tab'        => '7g_waf',
				'type'       => 'switch',
				'on_label'   => __( 'Enabled', 'wpcd' ),
				'off_label'  => __( 'Disabled', 'wpcd' ),
				'std'        => $status === 'on',
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => '7g_request_method',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => $confirmation_prompt,
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			/* Important Notes */
			$fields[] = array(
				'name' => __( 'Important Notes', 'wpcd' ),
				'tab'  => '7g_waf',
				'type' => 'heading',
				'desc' => __( 'There is no need to activate both the 7G and 6G firewalls. The 7G firewall is an updated version of the 6G firewall.  We are including both because some sites still use the 6G firewall. And the 6G firewall has had more time in the real world.  New sites should start with the 7G firewall and then downgrade to 6G if there are issues.', 'wpcd' ),
			);

			/* About WAFs */
			$desc  = __( 'A WAF is a web application firewall and operates at a higher level than your standard firewall.  It generally has more "smarts" since it sees data the way a web-browser would see the data.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( 'Once traffic has passed through a regular firewall, the 7G firewall takes a look at the content and applies web-specific rules to it to see if the traffic should be allowed.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( 'In this case the firewall is actually a set of very specific NGINX server rules.  Thus, the rules are applied before any traffic even hits your WordPress installation which helps with performance.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( 'The WAF filters out known bad bots and common web application attacks.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( 'However, it is possible that certain types of good traffic could get caught up in the net which is why we allow you to turn on the rules selectively.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( 'Note: If you are using a proxy such as CloudFlare then you probably do not need to turn these on since CloudFlare likely applies a superset of these rules to filter traffic before it hits your site.', 'wpcd' );

			$fields[] = array(
				'name' => __( 'About Web Application Firewalls', 'wpcd' ),
				'tab'  => '7g_waf',
				'type' => 'heading',
				'desc' => $desc,
			);

			/* Credits */
			$fields[] = array(
				'name' => __( 'Credits', 'wpcd' ),
				'tab'  => '7g_waf',
				'type' => 'heading',
				'desc' => __( 'The 6G and 7G firewalls are published by the kind folks over at Perishable Press.', 'wpcd' ),
			);

			return $fields;

	}

	/**
	 * Get the status of a particular 7G firewall group from the app meta
	 *
	 * One of the parameters to this is $action but it is not directly related to a bash action.
	 *
	 * @param int    $id     The id of the app cpt.
	 * @param string $action The action to look up.  This can have one of three values: 7g_all, 7g_user_agent, 7g_referrer, 7g_query_string, 7g_request_string, 7g_request_method.
	 */
	public function get_7g_status( $id, $action ) {

		// Get a full status array for all 7G statuses..
		$status_array = wpcd_maybe_unserialize( get_post_meta( $id, 'wpapp_7g_status', true ) );
		if ( empty( $status_array ) ) {
			$status_array = array();
		}

		// Extract the one we need and return it.  Assume 'off' it it doesn't already exist.
		if ( isset( $status_array[ $action ] ) ) {
			return $status_array[ $action ];
		} else {
			return 'off';
		}

	}

	/**
	 * Set the status of a particular 7G firewall group on the app meta
	 *
	 * One of the parameters to this is $action but it is not directly related to a bash action.
	 *
	 * @param int    $id     The id of the app cpt.
	 * @param string $action The action to look up.  This can have one of these six values: 7g_all, 7g_user_agent, 7g_referrer, 7g_query_string, 7g_request_string, 7g_request_method.
	 * @param string $value value.
	 */
	public function set_7g_status( $id, $action, $value ) {

		// if the action is the 7g_all rules - start with a new array and set everything to the same value.
		if ( in_array( $action, array( '7g_all' ) ) ) {
			$status_array = array();
			foreach ( array( '7g_all', '7g_user_agent', '7g_referrer', '7g_query_string', '7g_request_string', '7g_request_method' ) as $group ) {
				$status_array[ $group ] = $value;
			}
		} else {
			// Get a full current status array for all 7G statuses..
			$status_array = wpcd_maybe_unserialize( get_post_meta( $id, 'wpapp_7g_status', true ) );
			if ( empty( $status_array ) ) {
				$status_array = array();
			}

			// Set a single element in the status array.
			$status_array[ $action ] = $value;
		}

		// And write it back to the database.
		update_post_meta( $id, 'wpapp_7g_status', $status_array );

	}

	/**
	 * Get the real bash action based on a shortended action string.
	 *
	 * One of the parameters to this is $action but it is not directly related to a bash action.
	 *
	 * @param int    $id     The id of the app cpt.
	 * @param string $action The action to look up.  This can have one of these six values: 7g_all, 7g_user_agent, 7g_referrer, 7g_query_string, 7g_request_string, 7g_request_method.
	 */
	public function convert_short_action_to_bash_action( $id, $action ) {

		$status_array = wpcd_maybe_unserialize( get_post_meta( $id, 'wpapp_7g_status', true ) );

		if ( isset( $status_array[ $action ] ) ) {
			$status = $status_array[ $action ];
		} else {
			$status = 'off';
		}

		switch ( $action ) {
			case '7g_all':
				return ( 'off' === $status ? 'enable_all_7g' : 'disable_all_7g' );
				break;
			case '7g_user_agent':
				return ( 'off' === $status ? 'enable_user_agent_7g' : 'disable_user_agent_7g' );
				break;
			case '7g_referrer':
				return ( 'off' === $status ? 'enable_referrer_7g' : 'disable_referrer_7g' );
				break;
			case '7g_query_string':
				return ( 'off' === $status ? 'enable_query_string_7g' : 'disable_query_string_7g' );
				break;
			case '7g_request_string':
				return ( 'off' === $status ? 'enable_request_string_7g' : 'disable_request_string_7g' );
				break;
			case '7g_request_method':
				return ( 'off' === $status ? 'enable_request_method_7g' : 'disable_request_method_7g' );
				break;

		}

		return false;

	}

	/**
	 * Based on a bash action string, determine if we should record 'off' or 'on' in the database.
	 *
	 * @param string $action The action to look up.
	 *                        This can have one of these 12 values:
	 *                        enable_all_7g, disable_all_7g, enable_user_agent_7g, disable_user_agent_7g, enable_referrer_7g, disable_referrer_7g,
	 *                        enable_query_string_7g, enable_query_string_7g, enable_request_string_7g, disable_request_string_7g, enable_request_method_7g, disable_request_method_7g.
	 */
	public function convert_bash_action_to_value( $action ) {
		if ( strpos( $action, 'enable' ) !== false ) {
			return 'on';
		} else {
			return 'off';
		}
	}

}

new WPCD_WORDPRESS_TABS_7G_FIREWALL();
