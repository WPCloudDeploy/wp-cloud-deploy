<?php
/**
 * Trait:
 * Contains helper functions related to checking users security for certain actions.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_wpapp_tabs_security
 */
trait wpcd_wpapp_tabs_security {

	/**
	 * Checks to see if the user can perform a generic action for the specified app.
	 *
	 * @param string $permission The Permission name.
	 * @param int    $id         The post id of the cpt record for the app.
	 *
	 * @return boolean
	 */
	public function wpcd_wpapp_user_can( $permission, $id ) {

		$user_id     = get_current_user_id();
		$post        = get_post( $id );
		$post_author = $post->post_author;
		$app_type    = get_post_meta( $id, 'app_type', true );
		if ( 'wpcd_app' === $post->post_type && 'wordpress-app' === $app_type && ( wpcd_user_can( $user_id, $permission, $id ) || $post_author == $user_id ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Checks to see if the user can perform a generic action for the specified server.
	 *
	 * @param string $permission The Permission name.
	 * @param int    $id         The post id of the cpt record for the server.
	 *
	 * @return boolean
	 */
	public function wpcd_wpapp_server_user_can( $permission, $id ) {

		$user_id     = get_current_user_id();
		$post        = get_post( $id );
		$post_author = $post->post_author;
		$server_type = get_post_meta( $id, 'wpcd_server_server-type', true );

		if ( 'wpcd_app_server' === $post->post_type && 'wordpress-app' === $server_type && ( wpcd_user_can( $user_id, $permission, $id ) || $post_author == $user_id ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Checks to see if the user can perform a generic action for the specified site.
	 *
	 * @param string $permission The Permission name.
	 * @param int    $id         The post id of the cpt record for the site.
	 *
	 * @return boolean
	 */
	public function wpcd_wpapp_site_user_can( $permission, $id ) {

		$user_id     = get_current_user_id();
		$post        = get_post( $id );
		$post_author = $post->post_author;

		if ( 'wpcd_app' === $post->post_type && ( wpcd_user_can( $user_id, $permission, $id ) || $post_author == $user_id ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Checks to see if the user can change PHP options for the specified app.
	 *
	 * @param int $id The post id of the cpt record for the app.
	 *
	 * @return boolean
	 */
	public function wpcd_wpapp_user_can_change_php_options( $id ) {

		$user_id     = get_current_user_id();
		$post        = get_post( $id );
		$post_author = $post->post_author;
		$app_type    = get_post_meta( $id, 'app_type', true );
		if ( 'wpcd_app' === $post->post_type && 'wordpress-app' === $app_type && ( wpcd_user_can( $user_id, 'wpapp_update_site_php_options', $id ) || $post_author == $user_id ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Checks to see if the user can change PHP ADVANCED options for the specified app.
	 *
	 * @param int $id The post id of the cpt record for the app.
	 *
	 * @return boolean
	 */
	public function wpcd_wpapp_user_can_change_php_advanced_options( $id ) {

		// Only admins can do this for now.
		// We need to add a new security capability for this later.
		return wpcd_is_admin();

	}

	/**
	 * Checks to see if the user can remove a WordPress site.
	 *
	 * @param int $id The post id of the cpt record for the app.
	 *
	 * @return boolean
	 */
	public function wpcd_user_can_remove_wp_site( $id ) {

		$user_id     = get_current_user_id();
		$post        = get_post( $id );
		$post_author = $post->post_author;
		$app_type    = get_post_meta( $id, 'app_type', true );
		if ( 'wpcd_app' === $post->post_type && 'wordpress-app' === $app_type && ( wpcd_user_can( $user_id, 'wpapp_remove_site', $id ) || $post_author == $user_id ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Checks to see if the user can view a WordPress app.
	 *
	 * @param int $id The post id of the cpt record for the app.
	 *
	 * @return boolean
	 */
	public function wpcd_user_can_view_wp_app( $id ) {

		// See if we have a valid post id.
		// If we're not on a valid post type return true since we really don't care.
		// Note that returning false will break some things based on how this function is used.
		// If we want to return FALSE, then we would need to check the actions in each tab that
		// uses this function.
		$post = get_post( $id );
		if ( ( ! $post ) || ( is_wp_error( $post ) ) ) {
			return true;
		}

		/* If user is admin then, return true. */
		$user_id = get_current_user_id();
		if ( wpcd_is_admin( $user_id ) ) {
			return true;
		}

		// Setup some variables.
		$post_author = $post->post_author;
		$app_type    = get_post_meta( $id, 'app_type', true );

		// If we're not on a wpcd_app post type return true since we really don't care.
		// Note that returning false will break some things based on how this function is used.
		// If we want to return FALSE, then we would need to check the actions in each tab that.
		// uses this function.
		if ( $post->post_type != 'wpcd_app' ) {
			return true;
		}

		if ( 'wpcd_app' === $post->post_type && 'wordpress-app' === $app_type && ( wpcd_user_can( $user_id, 'view_app', $id ) || $post_author == $user_id ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Checks to see if the user can view a WordPress server
	 *
	 * @param int $id The post id of the cpt record for the server.
	 *
	 * @return boolean
	 */
	public function wpcd_user_can_view_wp_server( $id ) {

		// See if we have a valid post id.
		// If we're not on a valid post type return true since we really don't care.
		// Note that returning false will break some things based on how this function is used.
		// If we want to return FALSE, then we would need to check the actions in each tab that
		// uses this function.
		$post = get_post( $id );
		if ( ( ! $post ) || ( is_wp_error( $post ) ) ) {
			return true;
		}

		/* If user is admin then, return true. */
		$user_id = get_current_user_id();
		if ( wpcd_is_admin( $user_id ) ) {
			return true;
		}

		// Setup some variables.
		$post_author = $post->post_author;
		$server_type = get_post_meta( $id, 'wpcd_server_server-type', true );

		// If we're not on a wpcd_app_server post type return true since we really don't care.
		// Note that returning false will break some things based on how this function is used.
		// If we want to return FALSE, then we would need to check the actions in each tab that.
		// uses this function.
		if ( $post->post_type != 'wpcd_app_server' ) {
			return true;
		}

		if ( 'wpcd_app_server' === $post->post_type && 'wordpress-app' === $server_type && ( wpcd_user_can( $user_id, 'view_server', $id ) || $post_author == $user_id ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Takes a SERVER tabname and checks a WP-CONFIG.PHP constant (WPCD_SERVER_HIDE_AUTHOR_TABS)
	 * to see if it is excluded AND if the current user is the author of the passed in
	 * post_id of the server.
	 *
	 * This is a useful check when running an SaaS style service and you
	 * don't want users who purchased a server to be able to use certain
	 * server tabs.
	 *
	 * @param int    $id         The id of the cpt record for the server.
	 * @param string $tab_name   The name of the tab we're checking.
	 * @param int    $user_id    The userid to check.
	 */
	public function wpcd_can_author_view_server_tab( $id, $tab_name, $user_id = 0 ) {

		/* If user is admin then, return true. */
		if ( wpcd_is_admin( $user_id ) ) {
			return true;
		}

		// If nothing is defined in wp-config, then just return TRUE.
		// if ( ( ! defined( 'WPCD_SERVER_HIDE_TABS_WHEN_AUTHOR' ) ) || ( defined( 'WPCD_SERVER_HIDE_TABS_WHEN_AUTHOR' ) && empty( WPCD_SERVER_HIDE_TABS_WHEN_AUTHOR ) ) ) {
		// return true;
		// }

		// If not a post, return true.
		$post = get_post( $id );
		if ( ( ! $post ) || ( is_wp_error( $post ) ) ) {
			return true;
		}

		// Get the user id.
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		// Setup other variables.
		$post_author = $post->post_author;
		$server_type = get_post_meta( $id, 'wpcd_server_server-type', true );

		// If we're not checking a wp-app server post return true.
		if ( $post->post_type != 'wpcd_app_server' ) {
			return true;
		}

		// If the user id we're checking is not the author of the post then just return true.
		if ( $post_author != $user_id ) {
			return true;
		}

		// Time to check entries in wp-config.php. This could probably be deprecated since we have all those new entries in SETTINGS as of V 4.13.0.
		if ( defined( 'WPCD_SERVER_HIDE_TABS_WHEN_AUTHOR' ) && ( ! empty( WPCD_SERVER_HIDE_TABS_WHEN_AUTHOR ) ) ) {
			// Explode the command delimited string from the wp-config.php entry.
			$excluded_tabs = explode( ',', WPCD_SERVER_HIDE_TABS_WHEN_AUTHOR );

			// At this point we know the user id is the author of the post.
			// So we need to check if the specified tab is off-limits to
			// Authors of servers.
			if ( in_array( $tab_name, $excluded_tabs ) ) {
				// author should not be allowed to see this tab.
				return false;
			}
		}

		// We're still here, so check the true/false items in the APP:WordPress - Security tab in SETTINGS.
		// These items let us know which tabs should be turned off even if the user is the owner/author of the server.
		if ( true === (bool) get_post_meta( $id, 'wpcd_wpapp_is_staging', true ) ) {
			// This is a staging server but we're not really setting or using this so nothing to do.
			// The concept of a staging server is for future use.
		} else {
			// We have a very long settings key so we construct it in pieces.
			$wpcd_id_prefix       = 'wpcd_wpapp_server_security_exception';
			$context_tab_short_id = 'live-servers';
			$owner_key            = 'server-owner';
			$tab_key              = $tab_name;
			$setting_key          = "{$wpcd_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$tab_key}";  // See the class-wordpress-app-settings.php file for how this is key is constructed and used. Function: all_server_security_fields.

			// Get option value.
			$exception_flag = (bool) wpcd_get_early_option( $setting_key );
			if ( true === $exception_flag ) {
				return false;
			}
		}

		// And...still here so check the roles in the APP:WordPress - Security tab in SETTINGS.
		// These items let us know which tabs should be turned off based on roles, even if the user is the owner/author of the server.
		if ( true === (bool) get_post_meta( $id, 'wpcd_wpapp_is_staging', true ) ) {
			// This is a staging server but we're not really setting or using this concept.  So there is nothing to do here.
			// The concept of a staging server is for future use.
		} else {
			// We have a very long settings key so we construct it in pieces.
			$prefix               = 'wpcd_wpapp_server_security_exception';
			$context_tab_short_id = 'live-servers';
			$tab_key              = $tab_name;
			$setting_key          = "{$wpcd_id_prefix}_{$context_tab_short_id}_{$tab_key}_roles"; // See the class-wordpress-app-settings.php file for how this is key is constructed and used. Function: all_server_security_fields.

			$banned_roles = wpcd_get_early_option( $setting_key );

			if ( is_array( $banned_roles ) ) {
				$user_data = get_userdata( $user_id );
				if ( array_intersect( $banned_roles, $user_data->roles ) ) {
					return false;
				}
			}
		}

		// Got here?  default to true.
		return true;

	}

	/**
	 * Takes a SITE tabname and checks to see if it is excluded from being
	 * used by t the author of the passed.
	 *
	 * This is a useful check when running an SaaS style service and you
	 * don't want users who purchased a server to be able to use certain
	 * server tabs.
	 *
	 * This is very similar to the wpcd_can_author_view_server_tab function
	 * above and changes made here might need to be made there as well.
	 *
	 * @param int    $id         The id of the cpt record for the server.
	 * @param string $tab_name   The name of the tab we're checking.
	 * @param int    $user_id    The userid to check.
	 */
	public function wpcd_can_author_view_site_tab( $id, $tab_name, $user_id = 0 ) {

		/* If user is admin then, return true. */
		if ( wpcd_is_admin( $user_id ) ) {
			return true;
		}

		// If not a post, return true.
		$post = get_post( $id );
		if ( ( ! $post ) || ( is_wp_error( $post ) ) ) {
			return true;
		}

		// Get the user id.
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		// Setup other variables.
		$post_author = $post->post_author;
		$server_type = get_post_meta( $id, 'wpcd_server_server-type', true );

		// If we're not checking a wp-app server post return true.
		if ( $post->post_type != 'wpcd_app' ) {
			return true;
		}

		// If the user id we're checking is not the author of the post then just return true.
		if ( $post_author != $user_id ) {
			return true;
		}

		// We now know the user is the author of the site post.  Are they also the author of the server post?
		$both        = false;
		$server_post = $this->get_server_by_app_id( $id );
		if ( $server_post ) {
			if ( $user_id === (int) $server_post->post_author ) {
				$both = true;
			}
		}

		// Staging or live site?
		if ( true === $this->is_staging_site( $id ) ) {
			$context_tab_short_id = 'staging-sites';
		} else {
			$context_tab_short_id = 'live-sites';
		}

		// We have a very long settings key so we construct it in pieces.
		$wpcd_id_prefix = 'wpcd_wpapp_site_security_exception';
		$tab_key        = $tab_name;
		if ( true === $both ) {
			$owner_key = 'site-and-server-owner';
		} else {
			$owner_key = 'site-owner';
		}
		$setting_key = "{$wpcd_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$tab_key}";  // See the class-wordpress-app-settings.php file for how this is key is constructed and used. Function: all_server_security_fields.

		// Get option value.
		$exception_flag = (bool) wpcd_get_early_option( $setting_key );
		if ( true === $exception_flag ) {
			return false;
		}

		// And...still here so check the roles in the APP:WordPress - Security tab in SETTINGS.
		// These items let us know which tabs should be turned off based on roles, even if the user is the owner/author of the server.
		// Just as before, we have a very long settings key so we construct it in pieces.
		$prefix      = 'wpcd_wpapp_site_security_exception';
		$tab_key     = $tab_name;
		$setting_key = "{$wpcd_id_prefix}_{$context_tab_short_id}_{$tab_key}_roles"; // See the class-wordpress-app-settings.php file for how this is key is constructed and used. Function: all_server_security_fields.

		$banned_roles = wpcd_get_early_option( $setting_key );

		if ( is_array( $banned_roles ) ) {
			$user_data = get_userdata( $user_id );
			if ( array_intersect( $banned_roles, $user_data->roles ) ) {
				return false;
			}
		}

		// Got here?  default to true.
		return true;

	}

}
