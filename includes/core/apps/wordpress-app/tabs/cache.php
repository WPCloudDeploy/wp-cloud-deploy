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
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 1 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );
		/* add_filter( 'wpcd_is_ssh_successful', array( $this, 'was_ssh_successful' ), 10, 5 ); */

		add_action( "wpcd_command_{$this->get_app_name()}_completed", array( $this, 'command_completed' ), 10, 2 );

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
	 * Populates the tab name.
	 *
	 * @param array $tabs The default value.
	 *
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs ) {
		$tabs['cache'] = array(
			'label' => __( 'Cache', 'wpcd' ),
			'icon'  => 'fad fa-bookmark',
		);
		return $tabs;
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
		$pc_status = get_post_meta( $id, 'wpapp_nginx_pagecache_status', true );
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
			update_post_meta( $id, 'wpapp_nginx_pagecache_status', 'on' );
		} elseif ( 'disable_page_cache' === $action ) {
			update_post_meta( $id, 'wpapp_nginx_pagecache_status', 'off' );
		}

		// Success message and force refresh.
		if ( ! is_wp_error( $result ) ) {
			if ( 'enable_page_cache' === $action ) {
				$success_msg = __( 'NGINX pagecache has been enabled for this site.', 'wpcd' );
			} else {
				$success_msg = __( 'NGINX pagecache has been disabled for this site.', 'wpcd' );
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

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

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
			$success_msg = __( 'NGINX pagecache has been cleared for this site.', 'wpcd' );
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

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return array_merge( $fields, $this->get_disabled_header_field( 'cache' ) );
		}

		// Pick up server id for this app - we'll need it later.
		$server_id             = $this->get_server_by_app_id( $id );
		$server_edit_post_link = get_edit_post_link( $server_id );

		/* PAGE CACHE with CACHE ENABLER PLUGIN */
		$fields[] = array(
			'name' => __( 'Page Cache', 'wpcd' ),
			'tab'  => 'cache',
			'type' => 'heading',
			'desc' => __( 'Enable or disable the NGINX page cache.<br />', 'wpcd' ),
		);

		// What is the status of the page cache?
		$pc_status = get_post_meta( $id, 'wpapp_nginx_pagecache_status', true );
		if ( empty( $pc_status ) ) {
			$pc_status = 'off';
		}

		/* Set the confirmation prompt based on the the current status of this flag */
		$confirmation_prompt = '';
		if ( 'on' === $pc_status ) {
			$confirmation_prompt = __( 'Are you sure you would like to disable the NGINX page cache for this site?', 'wpcd' );
		} else {
			$confirmation_prompt = __( 'Are you sure you would like to enable NGINX page cache for this site?', 'wpcd' );
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
			'desc' => __( 'Clear the NGINX based page cache for this site', 'wpcd' ),
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
				'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to clear the NGINX page cache for this site?', 'wpcd' ),
			),
			'class'      => 'wpcd_app_action',
			'save_field' => false,
		);

		/* MEMCACHED */
		$fields[] = array(
			'name' => __( 'Memcached', 'wpcd' ),
			'tab'  => 'cache',
			'type' => 'heading',
			'desc' => __( 'Enable or disable Memcached on your site.<br />', 'wpcd' ),
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
				'desc'       => __( 'Enable or disable Memcached for this site. <br /> Note that all transients will be deleted when this cache is enabled - your plugins and themes should automatically re-add them as needed.', 'wcpcd' ),
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
				'std'        => __( 'Cleanup: Toggle Local Value for Memcached', 'wcpcd' ),
				'desc'       => __( 'If the Memcached status toggle above is not correct, click this button to correct it. This can happen if there is an error on the server or you or another admin manually toggle the memcached service on the server outside of this plugin.', 'wcpcd' ),
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
			$memcached_read_link = '<a href="https://medium.com/@Alibaba_Cloud/redis-vs-memcached-in-memory-data-storage-systems-3395279b0941">' . __( 'Memcached and Redis Object Caches', 'wpcd' ) . '</a>';
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

		return $fields;

	}

}

new WPCD_WORDPRESS_TABS_CACHE();
