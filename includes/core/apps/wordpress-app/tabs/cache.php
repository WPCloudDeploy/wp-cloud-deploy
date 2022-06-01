<?php
/**
 * Cache tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_CACHE
 */
class WPCD_WORDPRESS_TABS_CACHE extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_BACKUP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );
		/* add_filter( 'wpcd_is_ssh_successful', array( $this, 'was_ssh_successful' ), 10, 5 ); */

		add_action( "wpcd_command_{$this->get_app_name()}_completed", array( $this, 'command_completed' ), 10, 2 );

		/* Pending Logs Background Task: Install our page cache on a site */
		add_action( 'wpcd_pending_log_toggle_page_cache', array( $this, 'toggle_page_cache' ), 10, 3 );

	}

	/**
	 * Called when a command completes.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_completed
	 *
	 * @param int    $id     The postID of the server cpt.
	 * @param string $name   The name of the command.
	 */
	public function command_completed( $id, $name ) {

		// remove the 'temporary' meta so that another attempt will run if necessary.
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_status" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_args" );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'cache';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_cache_tab';
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
				'label' => __( 'Cache', 'wpcd' ),
				'icon'  => 'fad fa-bookmark',
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

		/* Verify that the user is even allowed to view the app before proceeding to do anything else */
		if ( ! $this->wpcd_user_can_view_wp_app( $id ) ) {
			/* translators: %1s is replaced with an internal action name; %2$s is replaced with the file name; %3$s is replaced with the post id being acted on. %4$s is the user id running this action. */
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'site-toggle-memcached', 'site-toggle-memcached-local-value', 'site-toggle-pagecache', 'site-clear-pagecache' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}
		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'site-toggle-memcached':
					// What is the status of memcached?
					$mc_status = get_post_meta( $id, 'wpapp_memcached_status', true );
					if ( empty( $mc_status ) ) {
						$mc_status = 'off';
					}
					// toggle it.
					$result = $this->enable_disable_memcached( 'on' === $mc_status ? 'disable' : 'enable', $id );
					break;
				case 'site-toggle-memcached-local-value':
					$result = $this->toggle_local_status_memcached( $id );
					break;
				case 'site-toggle-pagecache':
					$result = $this->enable_disable_pagecache( $action, $id );
					break;
				case 'site-clear-pagecache':
					$result = $this->clear_pagecache( $action, $id );
					break;

			}
		}

		return $result;

	}

	/**
	 * Enable/Disable the NGINX based page cache
	 *
	 * @param string $action The action key to send to the bash script.  This is actually the key of the drop-down select.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function enable_disable_pagecache( $action, $id ) {

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is an internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// Figure out the action to used based on the current status in our database.
		$pc_status = $this->get_page_cache_status( $id );
		if ( empty( $pc_status ) ) {
			$pc_status = 'off';
		}

		$action = 'off' === $pc_status ? 'enable_page_cache' : 'disable_page_cache';

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'manage_nginx_pagecache.txt',
			array(
				'action' => $action,
				'domain' => $domain,
			)
		);

		// log.
		// @codingStandardsIgnoreLine - added to ignore the print_r in the line below when linting with PHPcs.
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'manage_nginx_pagecache.txt' );
		if ( ! $success ) {
			/* Translators: %1$s is an internal action name. %2$s is an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// Now that we know we're successful, lets update a meta indicating the status of page cache on the site.
		if ( 'enable_page_cache' === $action ) {
			$this->set_page_cache_status( $id, 'on' );
		} elseif ( 'disable_page_cache' === $action ) {
			$this->set_page_cache_status( $id, 'off' );
		}

		// Success message and force refresh.
		if ( ! is_wp_error( $result ) ) {
			if ( 'enable_page_cache' === $action ) {
				$success_msg = __( 'The page cache has been enabled for this site.', 'wpcd' );
			} else {
				$success_msg = __( 'The page cache has been disabled for this site.', 'wpcd' );
			}
			$result = array(
				'msg'     => $success_msg,
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Clear the NGINX based page cache
	 *
	 * @param string $action The action key to send to the bash script.  This is actually the key of the drop-down select.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function clear_pagecache( $action, $id ) {

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is an internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// What type of web server are we running?
		$webserver_type      = $this->get_web_server_type( $id );
		$webserver_type_name = $this->get_web_server_description_by_id( $id );		

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// Action expected by bash scripts.
		$action = 'clear_page_cache';

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'manage_nginx_pagecache.txt',
			array(
				'action' => $action,
				'domain' => $domain,
			)
		);

		// log.
		// @codingStandardsIgnoreLine - added to ignore the print_r in the line below when linting with PHPcs.
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'manage_nginx_pagecache.txt' );
		if ( ! $success ) {
			/* Translators: %1$s is an internal action name. %2$s is an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// Success message and force refresh.
		if ( ! is_wp_error( $result ) ) {
			$success_msg = sprintf( __( 'The %s pagecache has been cleared for this site.', 'wpcd' ), $webserver_type_name );
			$result      = array(
				'msg'     => $success_msg,
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Enable/Disable memcached
	 *
	 * @param string $action The action key to send to the bash script.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function enable_disable_memcached( $action, $id ) {

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is an internal action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'manage_memcached.txt',
			array(
				'action' => $action,
				'domain' => $domain,
			)
		);

		// log.
		// @codingStandardsIgnoreLine - added to ignore the print_r in the line below when linting with PHPcs.
		do_action( 'wpcd_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'manage_memcached.txt' );
		if ( ! $success ) {
			/* Translators: %1$s is an internal action name. %2$s is an error message. */
			return new \WP_Error( sprintf( __( 'Unable to perform action %1$s for site: %2$s', 'wpcd' ), $action, $result ) );
		}

		// Now that we know we're successful, lets update a meta indicating the status of memcached on the site.
		if ( 'enable' === $action ) {
			update_post_meta( $id, 'wpapp_memcached_status', 'on' );
		} elseif ( 'disable' === $action ) {
			update_post_meta( $id, 'wpapp_memcached_status', 'off' );
		}

		// Success message and force refresh.
		if ( ! is_wp_error( $result ) ) {
			if ( 'enable' === $action ) {
				$success_msg = __( 'Memcached has been enabled for this site.', 'wpcd' );
			} else {
				$success_msg = __( 'Memcached has been disabled for this site.', 'wpcd' );
			}
			$result = array(
				'msg'     => $success_msg,
				'refresh' => 'yes',
			);
		}

		return $result;

	}

	/**
	 * Toggle local status for memcached
	 *
	 * @param int $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function toggle_local_status_memcached( $id ) {

		// get current local memcached status.
		$mc_status = get_post_meta( $id, 'wpapp_memcached_status', true );
		if ( empty( $mc_status ) ) {
			$mc_status = 'off';
		}

		// whats the new status going to be?
		if ( 'on' === $mc_status ) {
			$new_mc_status = 'off';
		} else {
			$new_mc_status = 'on';
		}

		// update it.
		update_post_meta( $id, 'wpapp_memcached_status', $new_mc_status );

		// Force refresh?
		if ( ! is_wp_error( $result ) ) {
			$result = array(
				'msg'     => __( 'The local Memcached status has been toggled.', 'wpcd' ),
				'refresh' => 'yes',
			);
		} else {
			$result = false;
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
			return array_merge( $fields, $this->get_disabled_header_field( 'cache' ) );
		}

		// What type of web server are we running?
		$webserver_type      = $this->get_web_server_type( $id );
		$webserver_type_name = $this->get_web_server_description_by_id( $id );

		// Pick up server id for this app - we'll need it later.
		$server_id             = $this->get_server_by_app_id( $id );
		$server_edit_post_link = get_edit_post_link( $server_id );

		/* PAGE CACHE with CACHE ENABLER PLUGIN */
		$fields[] = array(
			'name' => __( 'Page Cache', 'wpcd' ),
			'tab'  => 'cache',
			'type' => 'heading',
		);

		// What is the status of the page cache?
		$pc_status = $this->get_page_cache_status( $id );
		if ( empty( $pc_status ) ) {
			$pc_status = 'off';
		}

		/* Set the confirmation prompt based on the the current status of this flag */
		$confirmation_prompt = '';
		if ( 'on' === $pc_status ) {
			$confirmation_prompt = sprintf( __( 'Are you sure you would like to disable the %s page cache for this site?', 'wpcd' ), $webserver_type_name );
		} else {
			$confirmation_prompt = sprintf( __( 'Are you sure you would like to enable %s page cache for this site?', 'wpcd' ), $webserver_type_name );
		}

		$fields[] = array(
			'id'         => 'toggle-pagecache',
			'name'       => '',
			'tab'        => 'cache',
			'type'       => 'switch',
			'on_label'   => __( 'Enabled', 'wpcd' ),
			'off_label'  => __( 'Disabled', 'wpcd' ),
			'std'        => 'on' === $pc_status,
			'desc'       => '',
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'site-toggle-pagecache',
				// the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => $confirmation_prompt,
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/**
		 * Clear the page cache
		 */
		$fields[] = array(
			'name' => __( 'Clear Page Cache', 'wpcd' ),
			'tab'  => 'cache',
			'type' => 'heading',
		);
		$fields[] = array(
			'id'         => 'clear-pagecache',
			'name'       => '',
			'tab'        => 'cache',
			'type'       => 'button',
			'std'        => __( 'Clear', 'wpcd' ),
			'desc'       => '',
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-wpcd-action'              => 'site-clear-pagecache',
				// the id.
				'data-wpcd-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-wpcd-confirmation-prompt' => sprintf( __( 'Are you sure you would like to clear the %s page cache for this site?', 'wpcd' ), $webserver_type_name ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/* MEMCACHED */
		$fields[] = array(
			'name' => __( 'Memcached', 'wpcd' ),
			'tab'  => 'cache',
			'type' => 'heading',
		);

		if ( 'installed' === $this->get_server_installed_service_status( $id, 'memcached' ) ) {

			// What is the status of memcached?
			$mc_status = get_post_meta( $id, 'wpapp_memcached_status', true );
			if ( empty( $mc_status ) ) {
				$mc_status = 'off';
			}

			/* Set the confirmation prompt based on the the current status of this flag */
			$confirmation_prompt = '';
			if ( 'on' === $mc_status ) {
				$confirmation_prompt = __( 'Are you sure you would like to disable Memcached for this site?', 'wpcd' );
			} else {
				$confirmation_prompt = __( 'Are you sure you would like to enable Memcached for this site?', 'wpcd' );
			}

			$fields[] = array(
				'id'         => 'toggle-memcached',
				'name'       => '',
				'tab'        => 'cache',
				'type'       => 'switch',
				'on_label'   => __( 'Enabled', 'wpcd' ),
				'off_label'  => __( 'Disabled', 'wpcd' ),
				'std'        => 'on' === $mc_status,
				'desc'       => __( 'Enable or disable Memcached for this site. <br /> Note that all transients will be deleted when this cache is enabled - your plugins and themes should automatically re-add them as needed.', 'wpcd' ),
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'site-toggle-memcached',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => $confirmation_prompt,
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			$fields[] = array(
				'id'         => 'toggle-memcached-local-value',
				'name'       => '',
				'tab'        => 'cache',
				'type'       => 'button',
				'std'        => __( 'Cleanup: Toggle Local Value for Memcached', 'wpcd' ),
				'desc'       => __( 'If the Memcached status toggle above is not correct, click this button to correct it. This can happen if there is an error on the server or you or another admin manually toggle the memcached service on the server outside of this plugin.', 'wpcd' ),
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'site-toggle-memcached-local-value',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to toggle the local value for Memcached? This will have no effect on the server status', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

		} else {

			// Construct a nice message showing more resources and a link to the server page.
			$server_edit_link    = '<a href=' . '"' . $server_edit_post_link . '"' . '>' . __( 'server', 'wpcd' ) . '</a>';
			$memcached_read_url  = 'https://medium.com/@Alibaba_Cloud/redis-vs-memcached-in-memory-data-storage-systems-3395279b0941';
			$memcached_read_url  = wpcd_get_documentation_link( 'wordpress-app-doc-link-memcached-info', apply_filters( 'wpcd_documentation_links', $memcached_read_url ) );
			$memcached_read_link = '<a href="' . $memcached_read_url . '">' . __( 'Memcached and Redis Object Caches', 'wpcd' ) . '</a>';
			/* Translators: %1$s is a readmore link for memcached. %2$s is a link to the server where memcached is installed. */
			$mc_not_enabled = sprintf( __( 'Memcached is not enabled on this server. Learn more about %1$s. Go to %2$s.', 'wpcd' ), $memcached_read_link, $server_edit_link );
			$fields[]       = array(
				'tab'  => 'cache',
				'type' => 'custom_html',
				'std'  => $mc_not_enabled,
			);

		}

		// Allow plugins and addons to hook into the field definitions just before adding notes.
		$fields = apply_filters( "wpcd_app_{$this->get_app_name()}_tabs_cache_before_notes", $fields, $id );

		if ( ! (bool) wpcd_get_early_option( 'wordpress_app_hide_about_caches_text' ) ) {

			/* About caches and caching */
			$desc  = __( 'On a WordPress system there are FIVE possible levels of caching.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( '&nbsp;&nbsp;&nbsp;&nbsp;1. CDN Caching such as that done by Cloudflare', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( '&nbsp;&nbsp;&nbsp;&nbsp;2. OpCode Caching which stores compiled PHP code so it does not have to be compiled every time a page executes', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( '&nbsp;&nbsp;&nbsp;&nbsp;3. Object caching which caches database queries', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( '&nbsp;&nbsp;&nbsp;&nbsp;4. Page caching which caches full web pages after they have been generated by WordPress and', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( '&nbsp;&nbsp;&nbsp;&nbsp;5. Browser caching', 'wpcd' );
			$desc .= '<br />';
			$desc .= '<br />';
			$desc .= __( 'This page will help you manage caching at levels 3 and 4 - object caching and page caching.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( 'For object caching you can use Memcached or Redis along with a WordPress plugin to manage the cache.', 'wpcd' );
			$desc .= '<br />';
			$desc .= __( 'For page caching you can use an NGINX cache in combination with a WordPress plugin to manage the cache.', 'wpcd' );

			$fields[] = array(
				'name' => __( 'About Caches and Caching', 'wpcd' ),
				'tab'  => 'cache',
				'type' => 'heading',
				'desc' => $desc,
			);

			/* About clearing caches */
			$desc     = __( 'When you are clearing caches, you must make sure that all five caching levels are cleared/purged.', 'wpcd' );
			$desc    .= '<br />';
			$desc    .= __( 'When troubleshooting an issue, it is very easy to forget about one of the levels which might make a problem seem unsolved  - when the real issue is that an old page or data is stuck in a cache somewhere along the route.', 'wpcd' );
			$desc    .= '<br />';
			$desc    .= __( 'When technical support of any kind - plugin author, theme authors etc. ask you to purge your cache, they are really asking about purging all five cache leves - if you have them all enabled.', 'wpcd' );
			$fields[] = array(
				'name' => __( 'About Clearing Caches', 'wpcd' ),
				'tab'  => 'cache',
				'type' => 'heading',
				'desc' => $desc,
			);
		}

		// Cache Documentation Link to WPCloudDeploy Site.
		$doc_link = 'https://wpclouddeploy.com/documentation/wpcloud-deploy-user-guide/page-cache/';
		$desc     = __( 'Read more about caching in our documentation.', 'wpcd' );
		$desc    .= '<br />';
		$desc    .= '<br />';
		$desc    .= sprintf( '<a href="%s">%s</a>', wpcd_get_documentation_link( 'wordpress-app-doc-link-page-cache', apply_filters( 'wpcd_documentation_links', $doc_link ) ), __( 'View Cache Documentation', 'wpcd' ) );

		$fields[] = array(
			'name' => __( 'Cache Documentation', 'wpcd' ),
			'tab'  => 'cache',
			'type' => 'heading',
			'desc' => $desc,
		);

		return $fields;

	}

	/**
	 * Toggle the page cache.
	 *
	 * Called from an action hook from the pending logs background process - WPCD_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: wpcd_pending_log_toggle_page_cache
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $site_id    Id of site involved in this action.
	 * @param array $args       All the data needed to handle this action.
	 */
	public function toggle_page_cache( $task_id, $site_id, $args ) {

		// Grab our data array from pending tasks record...
		$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		/* Toggle The Page Cache */
		$result = $this->enable_disable_pagecache( 'site-toggle-pagecache', $site_id );

		$task_status = 'complete';  // Assume success.
		if ( is_array( $result ) ) {
			// We'll get an array with a success message from the enable_disable_pagecache() function.  So nothing to do here.
			// We'll just reset the $task_status to complete (which is the value it was initialized with) to avoid complaints by PHPcs about an empty if statement.
			$task_status = 'complete';
		} else {
			if ( false === (bool) $result || is_wp_error( $result ) ) {
				$task_status = 'failed';
			}
		}
		WPCD_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, $task_status );

	}

}

new WPCD_WORDPRESS_TABS_CACHE();
