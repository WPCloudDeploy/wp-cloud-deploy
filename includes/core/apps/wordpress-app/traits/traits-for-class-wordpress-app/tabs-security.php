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
	 * @param int    $id         The id of the cpt record for the app.
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
	 * @param int    $id         The id of the cpt record for the server.
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
	 * Checks to see if the user can change PHP options for the specified app.
	 *
	 * @param int $id the id of the cpt record for the app.
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
	 * @param int $id the id of the cpt record for the app.
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
	 * @param int $id the id of the cpt record for the app.
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
	 * @param int $id the id of the cpt record for the app.
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

		// Setup some variables.
		$user_id     = get_current_user_id();
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
	 * @param int $id the id of the cpt record for the server.
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

		// Setup some variables.
		$user_id     = get_current_user_id();
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
	 * Takes a tabname and checks a WP-CONFIG.PHP constant (WPCD_SERVER_HIDE_AUTHOR_TABS)
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

		/* Admin then, 'return' true. */
		if ( wpcd_is_admin( $user_id ) ) {
			return true;
		}

		// If nothing is defined in wp-config, then just return TRUE.
		if ( ( ! defined( 'WPCD_SERVER_HIDE_TABS_WHEN_AUTHOR' ) ) || ( defined( 'WPCD_SERVER_HIDE_TABS_WHEN_AUTHOR' ) && empty( WPCD_SERVER_HIDE_TABS_WHEN_AUTHOR ) ) ) {
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
		if ( $post->post_type != 'wpcd_app_server' ) {
			return true;
		}

		// If the user id we're checking is not the author of the post then just return true.
		if ( $post_author != $user_id ) {
			return true;
		}

		// Explode the command delimited string from the wp-config.php entry.
		$excluded_tabs = explode( ',', WPCD_SERVER_HIDE_TABS_WHEN_AUTHOR );

		// At this point we know the user id is the author of the post.
		// So we need to check if the specified tab is off-limits to
		// Authors of servers.
		if ( in_array( $tab_name, $excluded_tabs ) ) {
			// author should not be allowed to see this tab.
			return false;
		}
		
		// We're still here, so check the items in the APP:WordPress - Security tab in SETTINGS.
		

		// Got here?  default to true.
		return true;

	}

}
