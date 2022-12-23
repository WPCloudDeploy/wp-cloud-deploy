<?php
/**
 * This file contains general or helper functions for WPCD
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get all post and cpt records whose parent_post_id
 * matches the passed post_id.
 *
 * Note: This function is looking for a meta_field value,
 * NOT the built-in WordPress parent ids.
 *
 * The $post_type parameter is needed because WP does not like the 'any' parameter for post_type in this context for some reason.
 *
 * @param string $post_type Post type to search for child records.
 * @param int    $post_id Parent post id to search for.
 *
 * @returns array|false $posts array of posts or boolean false
 */
function wpcd_get_child_posts( $post_type, $post_id ) {

	// exit immediately if nothing provided..
	if ( empty( $post_id ) || empty( $post_type ) ) {
		return false;
	}

	$args = array(
		'post_type'      => $post_type,
		'meta_key'       => 'parent_post_id',
		'meta_value'     => $post_id,
		'posts_per_page' => 9999,
		'post_status'    => 'any',
	);

	$posts = get_posts( $args );

	return $posts;
}


/**
 * Delete all post and cpt records whose parent_post_id
 * matches the passed post_id.
 *
 * Note: This function is looking for a meta_field value,
 * NOT the built-in WordPress parent ids.
 *
 * The $post_type parameter is needed because WP
 * does not like the 'any' parameter for post_type
 * in this context for some reason.
 *
 * @param string $post_type Post type to search for child records.
 * @param int    $post_id Parent post id to search for.
 *
 * @returns boolean true always
 */
function wpcd_delete_child_posts( $post_type, $post_id ) {

	$posts_to_delete = wpcd_get_child_posts( $post_type, $post_id );

	// Delete all the posts based on custom meta value.
	do_action( 'wpcd_get_child_posts_for_delete', $posts_to_delete, $post_type );

	if ( ! empty( $posts_to_delete ) ) {
		foreach ( $posts_to_delete as $post ) {
			wp_delete_post( $post->ID, true ); // Note the TRUE parm - we're NOT sending to trash but deleting right away.
		}
	}

	return true;

}

/**
 * Our own function for unserializeing a field...
 *
 * @param array $data data.
 */
function wpcd_maybe_unserialize( $data ) {
	$output = $data;
	if ( is_serialized( $data ) && ! is_array( $data ) ) {
		$output = unserialize( $data );
	}

	/**
	 * @BUG, @TODO Its weird but sometimes you have to check it three because the first couple of unserialize calls are failing.
	 */

	/**
	 * Maybe this just needs to be a recursive function until is_serialized returns false.
	*/
	if ( is_serialized( $output ) && ! is_array( $output ) ) {
		$output = unserialize( $output );
	}
	if ( is_serialized( $output ) && ! is_array( $output ) ) {
		$output = unserialize( $output );
	}
	if ( is_serialized( $output ) && ! is_array( $output ) ) {
		$output = unserialize( $output );
	}

	return $output;
}

/**
 * Get the value of an option.
 *
 * @param string $option_id The option whose value you want to retrieve.
 * @param string $domain The metabox.io settings page that holds the option - optional and will default to 'wpcd_settings.
 *
 * $domain is a way of grouping options.  Apps can create their own options
 * pages if they want.  So this routine needs to allow retrieval from those
 * pages.
 *
 * @returns boolean true always
 */
function wpcd_get_option( $option_id, $domain = 'wpcd_settings' ) {

	if ( function_exists( 'rwmb_meta' ) ) {
		$value = rwmb_meta( $option_id, array( 'object_type' => 'setting' ), $domain );
		return $value;
	} else {
		return false;
	}

}

/**
 * This is the same as the wpcd_get_option function above.  But it
 * goes directly to the WordPress options function instead.
 * sometimes you need this because the metabox.io stuff isn't loaded yet.
 *
 * @param int    $option_id option_id.
 * @param string $domain domain.
 */
function wpcd_get_early_option( $option_id, $domain = 'wpcd_settings' ) {
	$options = get_option( $domain );

	// if the value does not exist, return false.
	$value = isset( $options[ $option_id ] ) ? $options[ $option_id ] : false;

	return $value;
}

/**
 * Directly write to our metabox option array.
 *
 * This function should almost never be used!
 * We only use this in one place right now - when automatically create
 * and setting ssh keys for a provider
 *
 * @param int    $option_id option_id.
 * @param string $option_value The value to write to the option element in our options array.
 * @param string $domain domain.
 *
 * @return bool
 */
function wpcd_set_option( $option_id, $option_value, $domain = 'wpcd_settings' ) {

	// Retrieve our options array.
	$options = get_option( $domain );

	// Write to it.
	$options[ $option_id ] = $option_value;

	// Save it and return result of the wp option save operation.
	return update_option( $domain, $options );

}

/**
 * Get short product name that will be used for white labelling.
 *
 * The WPCD_SHORT_NAME constant for the product name can be defined in wp-config.php.
 */
function wpcd_get_short_product_name() {
	$product_name = 'WPCloudDeploy';
	if ( defined( 'WPCD_SHORT_NAME' ) ) {
		$product_name = WPCD_SHORT_NAME;
	}

	return $product_name;
}

/**
 * Get longer product name that will be used for white labelling.
 *
 * The WPCD_LONG_NAME constant for the product name can be defined in wp-config.php.
 */
function wpcd_get_long_product_name() {
	$product_name = 'WPCloudDeploy';
	if ( defined( 'WPCD_LONG_NAME' ) ) {
		$product_name = WPCD_LONG_NAME;
	}

	return $product_name;
}

/**
 * Return whether or not WPCD is being called via it's better cron process.
 *
 * @since 5.0
 *
 * @return bool
 */
function wpcd_is_doing_cron() {

	if ( defined( 'WPCD_DOING_CORE_CRON' ) && WPCD_DOING_CORE_CRON ) {
		return true;
	}

	return false;
}

/**
 * Returns the timeout for long running commands.
 * Defaults to 25 minutes if not already set.
 *
 * Default changed from 15 min to 25 min in WPCD 5.0.
 */
function wpcd_get_long_running_command_timeout() {
	$timeout = wpcd_get_option( 'long-command-timeout' );
	if ( empty( $timeout ) ) {
		$timeout = 25;  // default timeout is 25 minutes.
	}
	if ( (int) $timeout < 25 ) {
		$timeout = 25;
	}
	return $timeout;
}

/**
 * Nonce checking...
 *
 * @param bool $action action.
 *
 * @TODO - this might be one of the functions that
 * need to be split into its own admin functions class.
 */
function check_ajax_admin_nonce( $action = false ) {
	$nonce  = isset( $_REQUEST['nonce'] ) ? $_REQUEST['nonce'] : '';
	$action = empty( $action ) ? 'wpcd-admin-nonce' : $action;

	if ( ! wp_verify_nonce( $nonce, $action ) ) {
		wp_send_json_error( esc_js( __( 'Wrong Nonce', 'wpcd' ) ) );
	}
}

/**
 * Nonce checking...
 *
 * @param bool $action action.
 */
function check_ajax_front_end_nonce( $action = false ) {
	$nonce  = isset( $_REQUEST['nonce'] ) ? $_REQUEST['nonce'] : '';
	$action = empty( $action ) ? 'wpcd-frontend-nonce' : $action;

	if ( ! wp_verify_nonce( $nonce, $action ) ) {
		wp_send_json_error( esc_js( __( 'Wrong Nonce', 'wpcd' ) ) );
	}
}

/**
 * Convert dos line endings to unix.
 *
 * @param string $s string data.
 *
 * @return string $s normalized line endings.
 */
function dos2unix_strings( $s ) {
	// Normalize line endings.
	// Convert all line-endings to UNIX format.
	$s = str_replace( "\r\n", "\n", $s );
	$s = str_replace( "\r", "\n", $s );
	// Don't allow out-of-control blank lines.
	$s = preg_replace( "/\n{2,}/", "\n\n", $s );
	return $s;
}

/**
 * Returns the last N lines from a string that
 * has line breaks.
 *
 * @param string $s string  data.
 * @param int    $n         number of lines to return from end of file.
 * @param string $breaktype "\n" or "<br />" etc.
 *
 * @return string $s normalized line endings.
 */
function wpcd_get_last_lines_from_string( $s, $n = 1, $breaktype = "\n" ) {
	$lines = explode( "\n", $s );

	$lines = array_slice( $lines, -$n );

	return implode( $breaktype, $lines );
}

/**
 * Takes a string and remove all items between two specified strings
 * including the specified strings itself.
 *
 * @credit: https://stackoverflow.com/questions/13031250/php-function-to-delete-all-between-certain-characters-in-string
 *
 * @param string $beginning the start of data to delete.
 * @param string $end the ending of data to delete.
 * @param string $string string to search.
 *
 * @return string
 */
function wpcd_delete_all_between( $beginning, $end, $string ) {
	$beginning_pos = strpos( $string, $beginning );
	$end_pos       = strpos( $string, $end );
	if ( $beginning_pos === false || $end_pos === false ) {
		return $string;
	}

	$text_to_delete = substr( $string, $beginning_pos, ( $end_pos + strlen( $end ) ) - $beginning_pos );

	return wpcd_delete_all_between( $beginning, $end, str_replace( $text_to_delete, '', $string ) ); // recursion to ensure all occurrences are replaced.
}

/**
 * Takes a string and remove all items before and after two specified strings.
 *
 * @credit: https://stackoverflow.com/questions/5696412/how-to-get-a-substring-between-two-strings-in-php
 *
 * @param string $string string to search.
 * @param string $start the start of data to keep.
 * @param string $end the ending of data to keep.
 * @return string
 */
function wpcd_get_string_between( $string, $start, $end ) {
	$string = ' ' . $string;
	$ini    = strpos( $string, $start );
	if ( $ini == 0 ) {
		return '';
	}
	$ini += strlen( $start );
	$len  = strpos( $string, $end, $ini ) - $ini;
	return substr( $string, $ini, $len );
}

/**
 * Takes a string that has multiple lines on it and breaks each line into an array.
 *
 * @credit: https://stackoverflow.com/questions/1483497/how-can-i-put-strings-in-an-array-split-by-new-line
 *
 * @param string $string string to search break.
 *
 * @return array of strings broken by line endings.
 */
function wpcd_split_lines_into_array( $string ) {
	return preg_split( "/\r\n|\n|\r/", $string );
}

/**
 * Takes a string and finds matching key-value pairs, replacing the value with a new value..
 *
 * The keys and values in a pair in the string must not be separated by spaces.
 * Thus something like this is valid: export action=backup domain=test088.wpvix.com site=test088.wpvix.com.
 * In this example the keys are 'action=', 'domain=' and 'site='.
 * The values are 'backup', 'test088.wpvix.com and 'test088.wpvix.com' respectively.
 * notice that there is no space between the key= and the value.
 *
 * Note: Only the first occurrence will be replaced.
 *
 * @param array  $pairs key-value array eg: array( 'wp_password=' => '(***private***)', 'aws_access_key_id=' => '(***private***)', 'aws_secret_access_key=' => '(***private***)'  ).
 * @param string $ihaystack The haystack to search for the key-value pairs.
 *
 * @return string
 */
function wpcd_replace_key_value_paired_strings( $pairs, $ihaystack ) {
	$haystack = $ihaystack;
	foreach ( $pairs as $key => $value ) {

		// Search for the string.
		$pos = strpos( $haystack, $key );
		if ( $pos !== false ) {

			// we have a match.
			$startpos = $pos + strlen( $key );  // Starting position of character after the match.
			// find the position of the next space.
			$spaceloc = strpos( $haystack, ' ', $startpos );

			// if the difference in positions between the space and the start position of the string we're searching for is > 0 .
			if ( ( $spaceloc - $startpos - 1 ) > 0 ) {
				$haystack = substr_replace( $haystack, $value, $startpos, ( $spaceloc - $startpos ) );
			}
		}
	}
	return $haystack;
}

/**
 * Generate a random string, using a cryptographically secure
 * pseudorandom number generator (random_int)
 *
 * This function uses type hints now (PHP 7+ only), but it was originally
 * written for PHP 5 as well.
 *
 * Credit: https://stackoverflow.com/questions/4356289/php-random-string-generator/31107425#31107425
 *
 * For PHP 7, random_int is a PHP core function
 * For PHP 5.x, depends on https://github.com/paragonie/random_compat
 *
 * @param int    $length      How many characters do we want?.
 * @param string $keyspace A string of all possible characters to select from.
 *
 * @return string
 * @throws \RangeException RangeException.
 */
function wpcd_random_str( int $length = 64, string $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ#!$-[]{}' ) : string {
	if ( $length < 1 ) {
		throw new \RangeException( 'Length must be a positive integer' );
	}
	$pieces = array();
	$max    = mb_strlen( $keyspace, '8bit' ) - 1;
	for ( $i = 0; $i < $length; ++$i ) {
		$pieces [] = $keyspace[ random_int( 0, $max ) ];
	}
	return implode( '', $pieces );
}

/**
 * Find the LOCATION of the first number in a string...
 *
 * Credit: https://stackoverflow.com/a/7495681
 *
 * @param string $text Text string to search.
 */
function wpcd_locate_first_number_in_string( $text ) {
	preg_match( '/\d/', $text, $m, PREG_OFFSET_CAPTURE );
	if ( sizeof( $m ) ) {
		return $m[0][1]; // 24 in your example.
	}

	// return false when there's no numbers in the string.
	return false;
}

/**
 * Given a filename/path of the format:
 * "/var/log/nginx/access.log"
 * return just the "access" word.
 *
 * @param string $file_name file name.
 */
function wpcd_get_log_file_without_extension( $file_name ) {
	$return_name = substr( strrchr( $file_name, '/' ), 1 );
	$return_name = str_replace( '.log', '', $return_name );
	return $return_name;
}

/**
 * Function for getting list of directories within specified directory
 *
 * @param  string $directory directory path.
 *
 * @return array or null
 */
function wpcd_get_dir_list( $directory ) {

	if ( empty( $directory ) ) {
		return;
	}

	$dirlist           = array();
	$scanned_directory = array_diff( scandir( $directory ), array( '..', '.' ) );
	$dirlist           = array_values( $scanned_directory );

	return $dirlist;
}

/**
 * Given a number such as 7.4 or 74, return the
 * OLS php service name such as lsphp74.
 *
 * @param string $version PHP version to handle.
 *
 * @return string
 */
function wpcd_convert_php_version_to_ols_service( $version ) {
	// Strip periods.
	$version = str_replace( '.', '', $version );
	return 'lsphp' . $version;
}

/**
 * Given an ols php service such as lsphp74, return 7.4
 *
 * @param string $phpservice OLS PHP Service name- eg: lsphp81.
 *
 * @return string.
 */
function wpcd_convert_ols_phpservice_to_php_version( $phpservice ) {

	// Get last two characters of phpservice.
	$version = mb_substr( $phpservice, -2 );
	$version = mb_substr( $version, 0, 1 ) . '.' . mb_substr( $version, 1, 1 );  // Reminder: First character position is 0, not 1.
	return $version;

}

/**
 * Get The Post ID from a form submission.
 * This is usually used when the post id isn't explicitly passed into a function or filter or action hook.
 *
 * @return int post_id.
 */
function wpcd_get_form_submission_post_id() {
	$post_id = null;
	if ( isset( $_GET['post'] ) ) {
		$post_id = intval( $_GET['post'] );
	} elseif ( isset( $_POST['post_ID'] ) ) {
		$post_id = intval( $_POST['post_ID'] );
	}
	return $post_id;
}

/**
 * Get The Author ID from a form submission.
 * Note that this assumes that the post has already been saved!
 *
 * @return int author_id
 */
function wpcd_get_form_submission_post_author() {

	$author_id = 0;

	$post_id = wpcd_get_form_submission_post_id();

	return wpcd_get_post_author( $post_id );

}

/**
 * Get The Author ID from a post
 * Note that this assumes that the post has already been saved!
 *
 * @param int $post_id post id.
 *
 * @return int author_id.
 */
function wpcd_get_post_author( $post_id ) {

	$author_id = 0;

	if ( $post_id ) {
		$post = get_post( $post_id );
		if ( $post ) {
			$author_id = $post->post_author;
		}
	}

	return $author_id;

}

/**
 * Get a list of all roles in WordPress and return them as an array of ids.
 *
 * @return array of roles.
 */
function wpcd_get_roles_ids() {
	global $wp_roles;
	foreach ( $wp_roles->roles as $key => $role ) {
		$roles[] = $key;
	}
	return $roles;
}

/**
 * Get a list of all roles in WordPress and return them as an array internal wp role names..
 *
 * @return array of roles.
 */
function wpcd_get_roles() {
	global $wp_roles;
	$roles = array();
	foreach ( $wp_roles->roles as $key => $role ) {
		$roles[ $key ] = $role['name'];
	}
	return $roles;
}

/**
 * Check if the user has permission to perform a specific action.
 *
 * @param  int    $user_id user id.
 * @param  string $permission_name permission name.
 * @param  int    $post_id    Server or App post ID.
 *
 * @return boolean
 */
function wpcd_user_can( $user_id, $permission_name, $post_id ) {

	global $wpdb;

	if ( wpcd_is_admin( $user_id ) ) {
		return true;
	}

	if ( empty( $user_id ) || empty( $permission_name ) || empty( $post_id ) ) {
		return false;
	}

	$permission_id = wpcd_get_permission_id_from_name( $permission_name );

	if ( empty( $permission_id ) ) {
		return false;
	}

	$teams = get_post_meta( $post_id, 'wpcd_assigned_teams', false );

	if ( count( $teams ) ) {
		$table_name = $wpdb->prefix . 'permission_assignments';

		$teams_placeholder = implode( ', ', array_fill( 0, count( $teams ), '%d' ) );
		$query_fields      = array_merge( $teams, array( $user_id, $permission_id, 1 ) );

		$sql          = $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE team_id IN(" . $teams_placeholder . ') AND user_id = %d AND permission_type_id = %d AND granted = %d', $query_fields );
		$results      = $wpdb->get_var( $sql );
		$result_count = intval( $results );

		if ( $result_count > 0 ) {
			return true;
		} else {
			return false;
		}
	} else {
		return false;
	}
}

/**
 * To get permision id by permission name for wpcd_permission_type post type
 *
 * @param  string $permission_name permission name.
 *
 * @return int
 */
function wpcd_get_permission_id_from_name( $permission_name ) {
	if ( empty( $permission_name ) ) {
		return 0;
	}

	$args      = array(
		'post_type'   => 'wpcd_permission_type',
		'post_status' => 'private',
		'fields'      => 'ids',
		'meta_query'  => array(
			array(
				'key'     => 'wpcd_permission_name',
				'value'   => $permission_name,
				'compare' => '=',
			),
		),
	);
	$postslist = get_posts( $args );

	if ( count( $postslist ) == 1 ) {
		$permission_id = $postslist[0];
		return $permission_id;
	} else {
		return 0;
	}
}

/**
 * For Filtering server/app list by permission name
 *
 * @param  string $permission_name permission name.
 * @param  string $post_type post type.
 * @param  string $post_status post status.
 * @param int    $user_id user id.
 *
 * @return array post ids or blank array
 */
function wpcd_get_posts_by_permission( $permission_name, $post_type, $post_status = 'private', $user_id = '' ) {

	// Pick up current user id - we'll need it later.
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	// array need to be return.
	$post__in = array();

	if ( wpcd_is_admin( $user_id ) ) {
		// get all posts since user is an admin!
		$posts = get_posts(
			array(
				'posts_per_page' => -1,
				'post_type'      => $post_type,
				'post_status'    => $post_status,
			)
		);
	} else {

		global $wpdb;

		if ( empty( $permission_name ) || empty( $post_type ) ) {
			return array();
		}

		$permission_id = wpcd_get_permission_id_from_name( $permission_name );
		$table_name    = $wpdb->prefix . 'permission_assignments';

		$get_teams_sql = $wpdb->prepare( "SELECT team_id FROM {$table_name} WHERE user_id = %d AND permission_type_id = %d AND granted = %d", array( $user_id, $permission_id, 1 ) );
		$results       = $wpdb->get_results( $get_teams_sql );

		$meta_key = 'wpcd_assigned_teams';

		$post_status = ( $post_status == 'all' ) ? 'private' : $post_status;

		// To check if the user is in any team.
		if ( count( $results ) ) {
			$teams = array();
			foreach ( $results as $result ) {
				$teams[] = $result->team_id;
			}

			$teams_placeholder = implode( ', ', array_fill( 0, count( $teams ), '%d' ) );
			$query_fields      = array_merge( array( $post_type, $post_status, $user_id, $post_type, $post_status, $meta_key ), $teams );

			$get_posts_sql = $wpdb->prepare( "SELECT DISTINCT P.ID, P.post_author FROM {$wpdb->posts} AS P LEFT JOIN {$wpdb->postmeta} AS PM on PM.post_id = P.ID WHERE P.post_type = %s AND P.post_status = %s AND P.post_author = %d OR P.post_type = %s AND P.post_status = %s AND ( PM.meta_key = %s AND PM.meta_value IN (" . $teams_placeholder . ') )', $query_fields );
			$posts         = $wpdb->get_results( $get_posts_sql );

		} else {

			// To check if the user is a post author or server/app doesnt have any teams assigned.
			$get_posts_sql = $wpdb->prepare( "SELECT DISTINCT P.ID, P.post_author FROM {$wpdb->posts} AS P LEFT JOIN {$wpdb->postmeta} AS PM on PM.post_id = P.ID WHERE P.post_type = %s AND P.post_status = %s AND P.post_author = %d", array( $post_type, $post_status, $user_id ) );
			$posts         = $wpdb->get_results( $get_posts_sql );
		}
	}

	if ( count( $posts ) ) {
		foreach ( $posts as $post ) {
			$post_id     = $post->ID;
			$post_author = $post->post_author;

			if ( $user_id == $post_author ) {
				$post__in[] = $post_id;
			}

			if ( wpcd_user_can( $user_id, $permission_name, $post_id ) ) {
				$post__in[] = $post_id;
			}
		}

		if ( count( $post__in ) ) {
			$post__in = array_unique( $post__in );
		}
	}

	return $post__in;
}

/**
 * Takes a SERVER feature name and checks our options array to see if
 * the current user or passed in user id is the author and if authors
 * are allowed to access that feature.
 *
 * We're pulling from the items in the APP:WordPress - Security tab in SETTINGS, subtab FEATURES - SERVERS.
 *
 * There is a related function named wpcd_can_author_view_server_tab in /traits/traits-for-class-wordpress-app/tabs-security.php.
 * It handles similar security for server tabs.  Changes and fixes here should probably be considered for that function as well.
 *
 * There is also a very similar to the wpcd_can_author_view_site_feature function
 * below and changes made here might need to be made there as well.
 *
 * @param int    $id             The id of the cpt record for the server.
 * @param string $feature_name   The name of the feature we're checking. eg: email_metabox.
 * @param int    $user_id        The userid to check.
 */
function wpcd_can_author_view_server_feature( $id, $feature_name, $user_id = 0 ) {

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
	if ( $post->post_type != 'wpcd_app_server' ) {
		return true;
	}

	// If the user id we're checking is not the author of the post then just return true.
	if ( $post_author != $user_id ) {
		return true;
	}

	// Check the true/false items in the APP:WordPress - Security tab, subtab FEATURES - SERVERS in SETTINGS.
	// These items let us know which features should be turned off even if the user is the owner/author of the server.
	if ( true === (bool) get_post_meta( $id, 'wpcd_wpapp_is_staging', true ) ) {
		// This is a staging server but we're not really setting or using this so nothing to do.
		// The concept of a staging server is for future use.
	} else {
		// We have a very long settings key so we construct it in pieces.
		$wpcd_id_prefix       = 'wpcd_wpapp_server_security_exception';
		$context_tab_short_id = 'live-servers';
		$owner_key            = 'server-owner';
		$feature_key          = $feature_name;
		$setting_key          = "{$wpcd_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$feature_key}";  // See the class-wordpress-app-settings.php file for how this is key is constructed and used. Function: all_server_features_security_fields.

		// Get option value.
		$exception_flag = (bool) wpcd_get_early_option( $setting_key );
		if ( true === $exception_flag ) {
			return false;
		}
	}

	// And...still here so check the roles in the APP:WordPress - Security tab, subtab FEATURES - SERVERS in SETTINGS.
	// These items let us know which features should be turned off based on roles, even if the user is the owner/author of the server.
	if ( true === (bool) get_post_meta( $id, 'wpcd_wpapp_is_staging', true ) ) {
		// This is a staging server but we're not really setting or using this concept.  So there is nothing to do here.
		// The concept of a staging server is for future use.
	} else {
		// We have a very long settings key so we construct it in pieces.
		$prefix               = 'wpcd_wpapp_server_security_exception';
		$context_tab_short_id = 'live-servers';
		$feature_key          = $feature_name;
		$setting_key          = "{$wpcd_id_prefix}_{$context_tab_short_id}_{$feature_key}_roles"; // See the class-wordpress-app-settings.php file for how this is key is constructed and used. Function: all_server_features_security_fields.

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
 * Takes a feature id and checks to see if it is excluded from being
 * used by the the author of the ID passed into this function.
 *
 * There is a related function named wpcd_can_author_view_site_tab in /traits/traits-for-class-wordpress-app/tabs-security.php.
 * It handles similar security for site tabs.  Changes and fixes here should probably be considered for that function as well.
 *
 * This is also very similar to the wpcd_can_author_view_server_feature function
 * above and changes made here might need to be made there as well.
 *
 * @param int    $id             The id of the cpt record for the server.
 * @param string $feature_name   The name of the tab we're checking.
 * @param int    $user_id        The userid to check.
 */
function wpcd_can_author_view_site_feature( $id, $feature_name, $user_id = 0 ) {

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

	// If we're not checking an app post return true.
	if ( $post->post_type != 'wpcd_app' ) {
		return true;
	}

	// If the user id we're checking is not the author of the post then just return true.
	if ( $post_author != $user_id ) {
		return true;
	}

	// We now know the user is the author of the site post.  Are they also the author of the server post?
	$both        = false;
	$server_post = WPCD()->get_server_by_app_id( $id );
	if ( $server_post ) {
		if ( $user_id === (int) $server_post->post_author ) {
			$both = true;
		}
	}

	// Staging or live site?
	if ( true === WPCD()->is_staging_site( $id ) ) {
		$context_tab_short_id = 'staging-sites';
	} else {
		$context_tab_short_id = 'live-sites';
	}

	// We have a very long settings key so we construct it in pieces.
	$wpcd_id_prefix = 'wpcd_wpapp_site_security_exception';
	$feature_key    = $feature_name;
	if ( true === $both ) {
		$owner_key = 'site-and-server-owner';
	} else {
		$owner_key = 'site-owner';
	}
	$setting_key = "{$wpcd_id_prefix}_{$context_tab_short_id}_{$owner_key}_{$feature_key}";  // See the class-wordpress-app-settings.php file for how this is key is constructed and used. Function: all_server_features_security_fields.

	// Get option value.
	$exception_flag = (bool) wpcd_get_early_option( $setting_key );
	if ( true === $exception_flag ) {
		return false;
	}

	// And...still here so check the roles in the APP:WordPress - Security tab in SETTINGS.
	// These items let us know which tabs should be turned off based on roles, even if the user is the owner/author of the server.
	// Just as before, we have a very long settings key so we construct it in pieces.
	$prefix      = 'wpcd_wpapp_site_security_exception';
	$feature_key = $feature_name;
	$setting_key = "{$wpcd_id_prefix}_{$context_tab_short_id}_{$feature_key}_roles"; // See the class-wordpress-app-settings.php file for how this is key is constructed and used. Function: all_server_features_security_fields.

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

/**
 * Given an array of post_ids, return a key-value array with post ids and post titles.
 *
 * @param array $post_ids Array of integer post ids.
 *
 * @return array key-value array of postid=>post_title
 */
function wpcd_post_ids_to_key_value_array( $post_ids ) {

	$return = array();

	foreach ( $post_ids as $post_id ) {
		$post = get_post( $post_id );
		if ( $post && ( ! is_wp_error( $post ) ) ) {
			$return[ $post_id ] = $post->post_title;
		}
	}

	return $return;

}

/**
 * Whether we're going to allow the data sync options
 * to be enabled on a site.
 */
function wpcd_data_sync_allowed() {
	$allow = false;

	// It's completely disabled in the wp-config.php file.
	if ( defined( 'WPCD_DISABLE_DATA_SYNC' ) && WPCD_DISABLE_DATA_SYNC ) {
		return false;
	}

	// If we get here then all good.
	return true;
}

/**
 * Whether we're going to allow the manual email notifications options
 * to be enabled on a site.
 */
function wpcd_email_notifications_allowed() {
	$allow = false;

	// It's completely disabled in the wp-config.php file.
	if ( defined( 'WPCD_DISABLE_EMAIL_NOTIFICATIONS' ) && WPCD_DISABLE_EMAIL_NOTIFICATIONS ) {
		return false;
	}

	// If we get here then all good.
	return true;
}

/**
 * To filter numeric values passed in an array
 *
 * @param  array $array array.
 * @return array
 */
function wpcd_filter_input_numeric_array( array &$array ) {
	array_walk_recursive(
		$array,
		function ( &$value ) {
			$value = filter_var( trim( $value ), FILTER_SANITIZE_NUMBER_INT );
		}
	);

	return $array;
}

/**
 * For checking the user is team manager or not
 *
 * @param  int $user_id user id.
 * @param  int $team_id team id.
 *
 * @return boolean
 */
function wpcd_check_user_is_team_manager( $user_id, $team_id = 0 ) {

	if ( empty( $user_id ) ) {
		return false;
	}

	// return true if passed $user_id is a team manager in passed $team_id.
	if ( ! empty( $team_id ) ) {
		$wpcd_permission_rule = get_post_meta( $team_id, 'wpcd_permission_rule', true );

		foreach ( $wpcd_permission_rule as $rule ) {
			if ( array_key_exists( 'wpcd_team_manager', $rule ) && $rule['wpcd_team_member'] == $user_id ) {
				return true;
			}
		}
	} else {
		$args  = array(
			'post_type'   => 'wpcd_team',
			'post_status' => 'private',
			'numberposts' => -1,
			'fields'      => 'ids',
		);
		$teams = get_posts( $args );

		if ( $teams ) {
			foreach ( $teams as $team ) {
				$wpcd_permission_rule[] = get_post_meta( $team, 'wpcd_permission_rule', true );
			}

			if ( $wpcd_permission_rule ) {
				foreach ( $wpcd_permission_rule as $rules ) {
					if ( $rules ) {
						foreach ( $rules as $rule ) {
							if ( array_key_exists( 'wpcd_team_manager', $rule ) && $rule['wpcd_team_member'] == $user_id ) {
								return true;
							}
						}
					}
				}
			}
		}
	}

	return false;

}

/**
 * To fetch the list of teams where passed $user_id is a team manager
 *
 * @param  int    $user_id user id.
 * @param string $post_status post status.
 *
 * @return array
 */
function wpcd_get_team_manager_posts( $user_id, $post_status = 'private' ) {
	$post_status = ( $post_status == 'all' ) ? 'private' : $post_status;
	$args        = array(
		'post_type'   => 'wpcd_team',
		'post_status' => $post_status,
		'numberposts' => -1,
		'fields'      => 'ids',
	);
	$teams       = get_posts( $args );

	$post__in = array();
	if ( $teams ) {

		foreach ( $teams as $team ) {
			if ( get_post( $team )->post_author == $user_id ) {
				$post__in[] = $team;
			}

			$wpcd_permission_rule[ $team ] = get_post_meta( $team, 'wpcd_permission_rule', true );
		}

		if ( $wpcd_permission_rule ) {
			foreach ( $wpcd_permission_rule as $team_id => $rules ) {
				if ( $rules ) {
					foreach ( $rules as $rule ) {
						if ( array_key_exists( 'wpcd_team_manager', $rule ) && $rule['wpcd_team_member'] == $user_id ) {

							if ( in_array( $team_id, $post__in ) ) {
								continue;
							}

							$post__in[] = $team_id;
						}
					}
				}
			}

			if ( $post__in ) {
				return $post__in;
			}
		}
	}
	return $post__in;

}

/**
 * Checks that the current user is admin or network admin
 *
 * @param  integer $user_id user id.
 * @return boolean
 */
function wpcd_is_admin( $user_id = 0 ) {

	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	if ( is_super_admin( $user_id ) || user_can( $user_id, 'manage_options' ) ) {
		return true;
	}
	return false;
}

/**
 * Helper function to determine if the current user can delete an app.
 *
 * @param int $post_id the id of the app post.
 *
 * @return boolean
 */
function wpcd_can_current_user_delete_app( $post_id ) {

	// Short circuit: If admin, then just return true.
	if ( wpcd_is_admin() ) {
		return true;
	}

	// get state of delete protect flag.
	$wpcd_app_delete_protection = get_post_meta( $post_id, 'wpcd_app_delete_protection', true );

	// get user id and post object.
	$user_id = get_current_user_id();
	$post    = get_post( $post_id );

	// do checks.
	if ( ( $post->post_type == 'wpcd_app' && ! wpcd_user_can( $user_id, 'delete_app_record', $post->ID ) && $post->post_author != $user_id ) || ( $post->post_type == 'wpcd_app' && ! empty( $wpcd_app_delete_protection ) ) ) {
		return false;
	} else {
		return true;
	}

}

/**
 * Helper function to determine if deletion protection is turned on for an app.
 *
 * @param int $post_id the id of the app post.
 *
 * @return boolean
 */
function wpcd_is_app_delete_protected( $post_id ) {

	$wpcd_app_delete_protection = get_post_meta( $post_id, 'wpcd_app_delete_protection', true );
	if ( empty( $wpcd_app_delete_protection ) ) {
		return false;
	} else {
		return true;
	}

}

/**
 * Removes https/http/www. to make the domain a consistent NAME.TLD
 *
 * @param  string $domain domain.
 * @return string
 */
function wpcd_clean_domain( $domain ) {
	if ( empty( $domain ) ) {
		return '';
	}

	$domain = preg_replace( '#^https?:?/?/?#', '', $domain );
	$domain = preg_replace( '#^www\.#', '', $domain );

	return $domain;
}

/**
 * Takes a string and removes everything except alphanumeric
 * characters and dashes.
 *
 * @param  string $instr String to clean.
 *
 * @return string
 */
function wpcd_clean_alpha_numeric_dashes( $instr ) {

	if ( empty( $instr ) ) {
		return '';
	}

	$instr = preg_replace( '/[^A-Za-z0-9-]/', '', $instr );

	return $instr;
}

/**
 * Takes a string and removes everything except alphanumeric
 * characters, dashes, periods and underscores.
 *
 * @param  string $instr String to clean.
 *
 * @return string
 */
function wpcd_clean_alpha_numeric_dashes_periods_underscores( $instr ) {

	if ( empty( $instr ) ) {
		return '';
	}

	$instr = preg_replace( '/[^A-Za-z0-9-._]/', '', $instr );

	return $instr;
}

/**
 * Get the list of users that are in the assigned teams
 *
 * @param int $post_id Post ID of server or app type post.
 *
 * @return array List of user ids
 */
function wpcd_get_users_in_team( $post_id ) {

	if ( empty( $post_id ) ) {
		return array();
	}

	$users = array();

	$teams = get_post_meta( $post_id, 'wpcd_assigned_teams', false );
	$users = array();
	if ( $teams ) {
		foreach ( $teams as $team ) {
			$rules = get_post_meta( $team, 'wpcd_permission_rule', true );
			if ( $rules ) {
				foreach ( $rules as $rule ) {
					if ( in_array( $rule['wpcd_team_member'], $users ) ) {
						continue;
					}

					$users[] = $rule['wpcd_team_member'];
				}
			}
		}
	}

	return $users;
}

/**
 * Return the svg code for a loading icon.
 *
 * $type = 1: Large loading icon with three vertical colored bars on a black background.
 *
 * @param int $type which icon to return.
 *
 * @return string the icon code.
 */
function wpcd_get_loading_svg_code( $type = 1 ) {

	$code = '';

	switch ( $type ) {
		case 1:
			$code = '<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" style="margin: auto; background: rgb(0, 0, 0) none repeat scroll 0% 0%; display: block; shape-rendering: auto;" width="200px" height="200px" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid">
<rect x="17.5" y="30" width="15" height="40" fill="#ef6f90">
  <animate attributeName="y" repeatCount="indefinite" dur="1s" calcMode="spline" keyTimes="0;0.5;1" values="18;30;30" keySplines="0 0.5 0.5 1;0 0.5 0.5 1" begin="-0.2s"></animate>
  <animate attributeName="height" repeatCount="indefinite" dur="1s" calcMode="spline" keyTimes="0;0.5;1" values="64;40;40" keySplines="0 0.5 0.5 1;0 0.5 0.5 1" begin="-0.2s"></animate>
</rect>
<rect x="42.5" y="30" width="15" height="40" fill="#f8b26a">
  <animate attributeName="y" repeatCount="indefinite" dur="1s" calcMode="spline" keyTimes="0;0.5;1" values="20.999999999999996;30;30" keySplines="0 0.5 0.5 1;0 0.5 0.5 1" begin="-0.1s"></animate>
  <animate attributeName="height" repeatCount="indefinite" dur="1s" calcMode="spline" keyTimes="0;0.5;1" values="58.00000000000001;40;40" keySplines="0 0.5 0.5 1;0 0.5 0.5 1" begin="-0.1s"></animate>
</rect>
<rect x="67.5" y="30" width="15" height="40" fill="#abbd81">
  <animate attributeName="y" repeatCount="indefinite" dur="1s" calcMode="spline" keyTimes="0;0.5;1" values="20.999999999999996;30;30" keySplines="0 0.5 0.5 1;0 0.5 0.5 1"></animate>
  <animate attributeName="height" repeatCount="indefinite" dur="1s" calcMode="spline" keyTimes="0;0.5;1" values="58.00000000000001;40;40" keySplines="0 0.5 0.5 1;0 0.5 0.5 1"></animate>
</rect>
<!-- [ldio] generated by https://loading.io/ --></svg>';
			break;
	}

	$code = apply_filters( 'wpcd_get_loading_svg_code', $code, $type );

	return $code;

}


if ( ! function_exists( 'wpcd_is_woocommerce_activated' ) ) {
	/**
	 * Check if WooCommerce is activated
	 */
	function wpcd_is_woocommerce_activated() {
		if ( class_exists( 'woocommerce' ) ) {
			return true;
		} else {
			return false;
		}
	}
}

/**
 * Get number of minutes left for transient to expire
 *
 * @param string $key key.
 *
 * @return int | false
 */
function wpcd_get_transient_remaining_time_in_mins( $key ) {
	if ( true === (bool) wp_using_ext_object_cache() ) {
		// External object cache in use - we cannot get expiration data so return false.
		return false;
	} else {
		return round( ( ( (int) get_option( "_transient_timeout_$key", 0 ) - time() ) / 60 ), 0 );
	}
}

/**
 * Return an array of font-awesome pro classes.
 */
function wpcd_get_some_fa_classes() {
	return array(
		'fad fa-alicorn',
		'fad fa-badger-honey',
		'fad fa-bat',
		'fad fa-cat',
		'fad fa-crow',
		'fad fa-deer',
		'fad fa-dove',
		'fad fa-dragon',
		'fad fa-duck',
		'fad fa-elephant',
		'fad fa-feather',
		'fad fa-horse',
		'fad fa-monkey',
		'fad fa-narwhal',
		'fad fa-pegasus',
		'fad fa-pig',
		'fad fa-rabbit',
		'fad fa-squirrel',
		'fad fa-turtle',
		'fad fa-unicorn',
		'fad fa-whale',

		'fad fa-cat-space',
		'fad fa-cow',
		'fad fa-deer-rudolph',
		'fad fa-dog',
		'fad fa-dog-unleashed',
		'fad fa-feather=alt',
		'fad fa-fish',
		'fad fa-frog',
		'fad fa-hippo',
		'fad fa-horse-head',
		'fad fa-horse-saddle',
		'fad fa-kiwi-bird',
		'fad fa-otter',
		'fad fa-paw',
		'fad fa-paw-alt',
		'fad fa-paw-claws',
		'fad fa-rabbit-fast',
		'fad fa-ram',
		'fad fa-sheep',
		'fad fa-skull-cow',
		'fad fa-snake',
		'fad fa-spider',
		'fad fa-spider-black-widow',

		'fad fa-key',
		'fad fa-chess',
		'fad fa-chess-rook-alt',
		'fad fa-chess-rook',
		'fad fa-chess-queen-alt',
		'fad fa-chess-queen',
		'fad fa-chess-pawn-alt',
		'fad fa-chess-pawn',
		'fad fa-chess-knight-alt',
		'fad fa-chess-knight',
		'fad fa-chess-king-alt',
		'fad fa-chess-king',
		'fad fa-chess-board',
		'fad fa-chess-bishop-alt',
		'fad fa-chess-bishop',
	);
}

/**
 * Returns a random font-awesome pro class from a pre-defined array of classes.
 */
function wpcd_get_random_fa_class() {
	return wpcd_get_some_fa_classes()[ array_rand( wpcd_get_some_fa_classes() ) ];
}

/**
 * Take a string (usually a password) and place backslashes in front of all non-alpha-numeric chars.
 *
 * This is usually used to escape passwords for the bash command line.
 *
 * @param string $thestring the string.
 */
function wpcd_escape_for_bash( $thestring ) {
	return preg_replace( '/([^A-Za-z0-9\s])/', '\\\\$1', $thestring );
}

/**
 * Get a documentation link from our options array.
 *
 * We will take in an option key and a default value.
 * If the option key returns a value we'll use that.
 * Otherwise we'll return the default value.
 *
 * @param string $link_option_key The option key that will contain a documentation link.
 * @param string $default_link    The default documentation link if the option key does not contain a link value.
 *
 * @return string
 */
function wpcd_get_documentation_link( $link_option_key, $default_link ) {
	$link = wpcd_get_early_option( $link_option_key );
	if ( ! empty( $link ) ) {
		return $link;
	} else {
		return $default_link;
	}
}


/**
 * Check if wp_query is for front-end servers listing page
 *
 * @param object $query WordPress Query Object.
 *
 * @return boolean
 */
function wpcd_is_public_servers_list_query( $query ) {
	return isset( $query->query['wpcd_app_server_front'] ) && $query->query['wpcd_app_server_front'];
}

/**
 * Check if wp_query is for front-end apps listing page
 *
 * @param object $query WordPress Query Object.
 *
 * @return boolean
 */
function wpcd_is_public_apps_list_query( $query ) {
	return isset( $query->query['wpcd_app_front'] ) && $query->query['wpcd_app_front'];
}

/**
 * Check if a user can edit a server or app.
 *
 * @global object $post
 *
 * @param null|int $server_id The post id of the server.
 * @param null|int $user_id The user id.
 * @param string   $type Should be 'server' or 'app'.
 *
 * @return boolean
 */
function wpcd_user_can_edit_app_server( $server_id = null, $user_id = null, $type = 'server' ) {

	if ( null === $server_id ) {
		global $post;
		$server_id = $post->ID;
	}

	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $server_id || ! $user_id ) {
		return false;
	}

	if ( wpcd_is_admin() ) {
		return true;
	}

	$post_author = get_post( $server_id )->post_author;

	return ! ( ! wpcd_user_can( $user_id, 'view_' . $type, $server_id ) && $post_author != $user_id );
}

/**
 * Return server id from current page url for front-end or from query var on backend
 *
 * @global object $post
 *
 * @return string|int
 */
function wpcd_get_current_page_server_id() {

	$id = '';
	if ( is_admin() ) {
		$id = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
	} else {
		global $post;

		$_server_name = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : parse_url( home_url( '/' ), PHP_URL_HOST );

		if ( ! $post ) {
			$id = url_to_postid( 'http://' . $_server_name . $_SERVER['REQUEST_URI'] );
		} else {
			$id = $post->ID;
		}
	}

	return $id;
}

/**
 * Takes a string and wraps it with a span and a class.
 *
 * For example, if we get a string such as "Domain:" we might
 * return <span class="wpcd-column-label-domain">Domain:</span>.
 *
 * @param string $string The string to wrap.
 * @param string $hint A portion of the class name eg: "Domain".
 * @param string $prefix The classname prefix eg: 'column-label'.
 *
 * @return string
 */
function wpcd_wrap_string_with_span_and_class( $string, $hint, $prefix ) {

	$class = "wpcd-{$prefix}-{$hint}";
	return '<span class="' . $class . '">' . $string . '</span>';

}

/**
 * Takes a string and wraps it with a div and a class.
 *
 * For example, if we get a string such as "Domain:" we might
 * return <div class="wpcd-column-label-domain">Domain:</div>.
 *
 * @param string $string The string to wrap.
 * @param string $hint A portion of the class name eg: "Domain".
 * @param string $prefix The classname prefix eg: 'column-label'.
 *
 * @return string
 */
function wpcd_wrap_string_with_div_and_class( $string, $hint, $prefix ) {

	$class = "wpcd-{$prefix}-{$hint}";
	return '<div class="' . $class . '">' . $string . '</div>';

}

/**
 * Return the post id when viewing a post and the
 * id is not otherwise available.
 *
 * This is very useful when using the metabox filters
 * to setup a metabox.
 *
 * @since 5.0
 *
 * @see: https://support.metabox.io/topic/get-post-metabox-data-inside-of-mb-block-default-value/
 *
 * @return int.
 */
function wpcd_get_post_id_from_global() {
	$post_id = null;
	if ( isset( $_GET['post'] ) ) {
		$post_id = intval( $_GET['post'] );
	} elseif ( isset( $_POST['post_ID'] ) ) {
		$post_id = intval( $_POST['post_ID'] );
	}
	return $post_id;
}

/**
 * Takes a text string such as an IP address and
 * wraps it with special a div/span CSS.
 * The div/span is used to trigger a JS function
 * to copy the wrapped data to the clipboard.
 *
 * @since 5.0
 *
 * @param string $data_string The string to wrap.
 * @param bool   $break True = wrap with a div False = wrap with a span.
 *
 * @return string
 */
function wpcd_wrap_clipboard_copy( $data_string, $break = true, $br_tag = false ) {

	if ( true === $break ) {
		$return = '<div class="wpcd-click-to-copy">';
	} else {
		$return = '<span class="wpcd-click-to-copy">';
	}
	$return .= '<span class="wpcd-click-to-copy-text">' . $data_string . '</span>';
	if ( true === $br_tag ) {
		$return .= '<br/>';
	}
	$return .= '<span data-label="' . __( 'Copied', 'wpcd' ) . '" class="wpcd-click-to-copy-label wpcd-copy-hidden">' . __( 'Click to copy', 'wpcd' ) . '</span>';

	if ( true === $break ) {
		$return .= '</div>';
	} else {
		$return .= '</span>';
	}

	return $return;
}

/**
 * Generate and return a unique uuid.
 *
 * @credit: https://www.uuidgenerator.net/dev-corner/php
 *
 * @since 5.0
 */
function wpcd_generate_uuid() {

	// Generate 16 bytes (128 bits) of random data or use the data passed into the function.
	$data = $data ?? random_bytes( 16 );
	assert( strlen( $data ) == 16 );

	// Set version to 0100.
	$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );

	// Set bits 6-7 to 10.
	$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );

	// Output the 36 character UUID.
	return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );

}

/**
 * Return whether GIT is enabled for WPCD.
 */
function wpcd_is_git_enabled() {
	if ( class_exists( 'WPCD_GitControl_Init' ) ) {
		return true;
	} else {
		return false;
	}
}

/*
// Functions in this section are for testing only.
function wpcd_test_01( $attributes ) {
	error_log( 'wpcd_server_wordpress-app_server_created action_hook has fired' );
}
add_action( 'wpcd_server_wordpress-app_server_created', 'wpcd_test_01', 10, 1 );

function wpcd_test_02( $attributes ) {
	error_log( 'wpcd_server_wordpress-app_prepare_server_command_done hook as fired' );
}
add_action( 'wpcd_server_wordpress-app_prepare_server_command_done', 'wpcd_test_02', 10, 1 );

function wpcd_test_initial_server_attributes( $attributes, $args ) {
	$attributes['test_attribute'] = 'test';
	return $attributes;
}
add_filter( "wpcd_wordpress-app_initial_server_attributes", 'wpcd_test_initial_server_attributes', 10, 2 );

function wpcd_test_create_popup_after_form_option() {

	return ;

	?>

	<div class="wpcd-create-popup-label-wrap"><label class="wpcd-create-popup-label" for="namex"> <?php echo __('Name of Serverx', 'wpcd') ?>  </label></div>
	<div class="wpcd-create-popup-input-wrap">
		<input type="text" name="namex" placeholder="<?php _e('Name of serverx', 'wpcd' ); ?>" class="wpcd_server_namex">
	</div>

	<?php
}
add_action( "wpcd_wordpress-app_create_popup_after_form_open", 'wpcd_test_create_popup_after_form_option', 10 );
*/


/**
 * Check if user has a role
 * 
 * @param int $user_id
 * @param string $role_name
 * 
 * @return boolean
 */
function wpcd_user_has_role( $user_id, $role_name ) {
    $user_meta = get_userdata( $user_id );
    $user_roles = $user_meta->roles;
    return in_array( $role_name, $user_roles );
}


/**
 * Get all provider based on user permission
 * 
 * @param int|null $user_id
 * 
 * @return array
 */
function wpcd_get_all_providers_by_permission( $user_id = null ) {
	$provider = WPCD_Custom_Table_API::get('provider');
	$result = $provider->get_items_by_permission( array(), $user_id );
	return isset( $result['items'] ) ? $result['items'] : array();
}

/**
 * Get all dns provider based on user permission
 * 
 * @param int|null $user_id
 * 
 * @return array
 */
function wpcd_get_all_dns_providers_by_permission( $user_id = null ) {
	$dns_provider = WPCD_Custom_Table_API::get('dns_provider');
	$result = $dns_provider->get_items_by_permission( array(), $user_id );
	return isset( $result['items'] ) ? $result['items'] : array();
}

/**
 * Get all dns zones based on user permission
 * 
 * @param int|null $user_id
 * 
 * @return array
 */
function wpcd_get_all_user_dns_zones( $user_id = null ) {
	$dns_zone = WPCD_Custom_Table_API::get('dns_zone');
	$result = $dns_zone->get_items_by_permission( array(), $user_id );
	return $result;
}

/**
 * Get all dns zone records based on zone id
 * 
 * @param int $zone_id
 * 
 * @return array
 */
function wpcd_get_zone_records_by_zone_id( $zone_id ) {
	
	$results = array();
	if( $zone_id ) {
		
		$zone_record = WPCD_Custom_Table_API::get('dns_zone_record');
		$results = $zone_record->get_items_by_parent_id( $zone_id, ARRAY_A );
	}
	return ( is_array( $results ) ) ? $results : array();
}

/**
 * Return default dns zone
 * 
 * @return array
 */
function wpcd_get_default_dns_zone() {
	
	$dns_zone = WPCD_Custom_Table_API::get('dns_zone');
	$result = $dns_zone->get_default();
	
	return $result;
	
}

/**
 * Get dns provider based on zone id
 * 
 * @param int $zone_id
 * 
 * @return array
 */
function wpcd_get_dns_provider_by_zone_id( $zone_id = null ) {
	
	$dns_provider_item = array();
	$dns_zone = WPCD_Custom_Table_API::get('dns_zone');
	$zone = $dns_zone->get_by_id( $zone_id );
	
	if( $zone && $zone->parent_id ) {
		$dns_provider = WPCD_Custom_Table_API::get('dns_provider');
		$dns_provider_item = (array) $dns_provider->get_by_id( $zone->parent_id );
	}
	
	return $dns_provider_item;
}