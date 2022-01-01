<?php
/**
 * This class is used for posts log.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Parent class for all logging classes - ssh, command, error and others */

/**
 * Class WPCD_POSTS_LOG
 */
class WPCD_POSTS_LOG extends WPCD_Posts_Base {

	/**
	 * The primary post type being handled by this class.
	 * This will typically be set by descendant classes
	 * using the getter and setting functions defined
	 * below.
	 *
	 * @var $post_type
	 */
	private $post_type = '';

	/**
	 * The list of post_meta fields that will
	 * be searched when the user runs a search
	 * from the log list screen.
	 *
	 * @var $post_search_fields
	 */
	private $post_search_fields = array();

	/**
	 * WPCD_POSTS_LOG instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_POSTS_LOG constructor.
	 */
	public function __construct() {
		$this->log_hooks(); // register hooks to make things happen.
	}

	/**
	 * Setup WordPress and other plugin/theme hooks.
	 *
	 * @return void
	 */
	public function log_hooks() {

		// Remove edit and quick-edit from log-list rows.
		add_filter( 'post_row_actions', array( $this, 'wpcd_post_row_actions' ), 10, 2 );

		// Get rid of publish metabox on the log detail cpt screen.
		add_action( 'admin_menu', array( $this, 'wpcd_modify_admin_menu' ) );

		// Action hook to remove all log.
		add_action( 'wp_ajax_purge_log', array( $this, 'remove_all_logs' ) );

		// Action hook to remove all log except unsent.
		add_action( 'wp_ajax_purge_unsent_log', array( $this, 'remove_all_log_except_unsent_logs' ) );

		// Load up css and js scripts used for managing this cpt data screens.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 1 );

		// Action hook to extend admin search.
		add_action( 'pre_get_posts', array( $this, 'wpcd_logs_extend_admin_search' ), 10, 1 );
		add_action( 'pre_get_posts', array( $this, 'wpcd_logs_meta_or_title_search' ), 10, 1 );

		// Filter hook to modify where clause.
		add_filter( 'posts_where', array( $this, 'wpcd_logs_posts_where' ), 10, 2 );

		// Action hook to check if user has capability to access add new, edit or listing screen for wpcd_posts_log cpt.
		add_action( 'load-edit.php', array( $this, 'wpcd_posts_log_load' ) );
		add_action( 'load-post-new.php', array( $this, 'wpcd_posts_log_load' ) );
		add_action( 'load-post.php', array( $this, 'wpcd_posts_log_load' ) );

		// Action hook to add custom back to list button.
		add_action( 'admin_footer-post.php', array( $this, 'wpcd_logs_backtolist_btn' ) );
	}

	/**
	 * Sets the post type handled by this or descendant class
	 *
	 * @param string $type the post type name.
	 *
	 * @return void
	 */
	public function set_post_type( $type ) {
		$this->post_type = $type;
	}

	/**
	 * Gets the post type handled by this or descendant class
	 *
	 * @return string The post type name handled by the instance of this class
	 */
	public function get_post_type() {
		return $this->post_type;
	}

	/**
	 * Sets the list of fields to be searched by this or descendant class
	 *
	 * @param string $search_fields search_fields.
	 *
	 * @return void
	 */
	public function set_post_search_fields( $search_fields ) {
		$this->post_search_fields = $search_fields;
	}

	/**
	 * Gets the post search fields handled by this or descendant class
	 *
	 * @return string The post search field names handled by the instance of this class
	 */
	public function get_post_search_fields() {
		return $this->post_search_fields;
	}

	/**
	 * Register the scripts for the custom post type.
	 *
	 * @param string $hook hook name.
	 */
	public function enqueue_scripts( $hook ) {
		if ( in_array( $hook, array( 'edit.php' ), true ) ) {
			$exclude_posttypes = array( 'wpcd_notify_user' );
			$screen            = get_current_screen();
			if ( ! in_array( $screen->post_type, $exclude_posttypes, true ) ) {
				if ( is_object( $screen ) && $screen->post_type === $this->get_post_type() ) {

					if ( 'wpcd_notify_log' === $screen->post_type ) {
						$purge_title = __( 'Purge All', 'wpcd' );
					} else {
						$purge_title = __( 'Purge', 'wpcd' );
					}

					wp_enqueue_script( 'wpcd-logs-common', wpcd_url . 'assets/js/wpcd-logs-common.js', array( 'jquery' ), wpcd_scripts_version, true );
					wp_localize_script(
						'wpcd-logs-common',
						'params',
						array(
							'action'          => 'purge_log',
							'unsent_action'   => 'purge_unsent_log',
							'clean_up_action' => 'clean_up_pending_logs_action',
							'post_type'       => $screen->post_type,
							'nonce'           => wp_create_nonce( 'wpcd-log' ),
							'unsent_nonce'    => wp_create_nonce( 'wpcd-unsent-log' ),
							'clean_up_nonce'  => wp_create_nonce( 'wpcd-clean-up-pending-log' ),
							'l10n'            => array(
								'prompt'             => __( 'Are you sure? This will delete all the logs.', 'wpcd' ),
								'unsent_prompt'      => __( 'Are you sure? This will delete all the sent logs.', 'wpcd' ),
								'clean_up_prompt'    => __( 'Are you sure? This will clean up all the pending logs.', 'wpcd' ),
								'wait_msg'           => __( 'Please wait...', 'wpcd' ),
								'purge_title'        => $purge_title,
								'purge_unsent_title' => __(
									'Purge All Except Unsent',
									'wpcd'
								),
								'clean_up_title'     => __( 'Clean Up', 'wpcd' ),
							),
						)
					);

				}
			}
		}
	}

	/**
	 * Take a string and remove some common strings that
	 * could be confusing to the reader.
	 * This is generally used to clean up log messages
	 *
	 * @param string $str string.
	 */
	public function remove_common_strings( $str ) {
		if ( ! is_wp_error( $str ) ) {
			$str = str_replace( "'unknown': I need something more specific.", '', $str );
			$str = str_replace( 'mesg: ttyname failed: Inappropriate ioctl for device', '', $str );
			$str = str_replace( 'sudo: dos2unix: command not found', 'sudo: dos2unix: command not found. Unfortunately the server provisioning process has failed. Please delete this server and restart! Sorry about that!', $str );
		}
		return $str;
	}

	/**
	 * Make sure that logs don't grow too large!
	 *
	 * Usually called from descendant classes
	 * during maintenance operations
	 *
	 * Note: Assumption is that all log posts are "private".
	 *
	 * Issues:  If you change the AUTO TRIM item in settings
	 * it will delete all log records.  This is because
	 * wp_count_posts does not return the new count
	 * after a delete has occurred. This is probably
	 * a database transaction issue where this function
	 * is being called in the middle of a transaction that
	 * is incomplete so the data being returned is "old".
	 * Using WP_Query can solve the issue but then
	 * you would have to re-architect this whole
	 * routine to avoid constantly returning the
	 * entire set of log posts.
	 * For now we'll live with the issue.
	 *
	 * @param string $post_type post type to clean up.
	 *
	 * @return void
	 */
	public function clean_up_old_log_entries( $post_type ) {

		$count_posts       = wp_count_posts( $post_type );
		$error_log_entries = 0;
		if ( $count_posts ) {
			$error_log_entries = $count_posts->private + $count_posts->publish;
		}

		if ( 'wpcd_notify_log' === $post_type ) {
			$auto_trim_log_limit = (int) wpcd_get_early_option( 'auto_trim_notification_log_limit' );
		}
		if ( 'wpcd_notify_sent' === $post_type ) {
			$auto_trim_log_limit = (int) wpcd_get_early_option( 'auto_trim_notification_sent_log_limit' );
		}
		if ( 'wpcd_ssh_log' === $post_type ) {
			$auto_trim_log_limit = (int) wpcd_get_early_option( 'auto_trim_ssh_log_limit' );
		}
		if ( 'wpcd_command_log' === $post_type ) {
			$auto_trim_log_limit = (int) wpcd_get_early_option( 'auto_trim_command_log_limit' );
		}
		if ( 'wpcd_error_log' === $post_type ) {
			$auto_trim_log_limit = (int) wpcd_get_early_option( 'auto_trim_error_log_limit' );
		}

		if ( defined( 'WPCD_AUTO_TRIM_LOG_LIMIT' ) ) {
			$auto_trim_log_limit = WPCD_AUTO_TRIM_LOG_LIMIT;
		}
		if ( empty( $auto_trim_log_limit ) || $auto_trim_log_limit <= 0 ) {
			$auto_trim_log_limit = 999;
		}

		if ( $error_log_entries >= $auto_trim_log_limit && $error_log_entries > 0 && $auto_trim_log_limit > 0 ) {

			// Figure out maximum number of records to delete.
			$delete_at_most = wpcd_get_early_option( 'most_items_to_delete' );
			if ( empty( $delete_at_most ) ) {
				$delete_at_most = 100;
			}
			$max_posts_to_delete = min( $error_log_entries - $auto_trim_log_limit, $delete_at_most );

			if ( $max_posts_to_delete > 0 ) {

				// Get posts sorted by date in descending order and limit to the max setting above.
				$posts_to_delete = get_posts(
					array(
						'orderby'        => 'date',
						'order'          => 'ASC',
						'post_type'      => $post_type,
						'posts_per_page' => $max_posts_to_delete,
						'post_status'    => 'private',
					)
				);

				// Now do the delete.
				$counter = 0;
				if ( $max_posts_to_delete > 0 ) {
					foreach ( $posts_to_delete as $post_to_delete ) {
						$counter++;  // We're going to use a counter just in case $posts_to_delete contains more posts than our intended max (which shouldn't happen under normal circumstances).
						wp_delete_post( $post_to_delete->ID, true );
						if ( $counter >= $max_posts_to_delete ) {
							break;
						}
					}
				}
			}
		}

	}

	/**
	 * Removes edit option from bulk action on command log/ssh log/error log listing screen
	 *
	 * Filter hook: bulk_actions-edit-{cpt-name}
	 *
	 * @param  array $actions actions.
	 *
	 * @return array
	 */
	public function wpcd_log_bulk_actions( $actions ) {
		unset( $actions['edit'] );
		return $actions;
	}

	/**
	 * This filter modifies post rows for "wpcd_command_log", "wpcd_ssh_log" and "wpcd_error_log".
	 * post rows such as "Edit", "Quick Edit".
	 *
	 * Action hook: post_row_actions
	 *
	 * @param  array  $actions actions.
	 * @param  object $post post object.
	 *
	 * @return array
	 */
	public function wpcd_post_row_actions( $actions, $post ) {

		if ( $post->post_type === $this->get_post_type() ) {
			// Removes "Edit".
			unset( $actions['edit'] );
			// Removes "Quick Edit".
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	/**
	 * Remove Publish meta box from all log detail screens.
	 *
	 * Action hook: admin_menu
	 */
	public function wpcd_modify_admin_menu() {
		remove_meta_box( 'submitdiv', $this->get_post_type(), 'side' );
	}

	/**
	 * Removes all log entries.
	 */
	public function remove_all_logs() {

		// Nonce check.
		if ( ! check_ajax_referer( 'wpcd-log', 'nonce', false ) ) {
			wp_send_json_error( array( 'msg' => __( 'Invalid request.', 'wpcd' ) ) );
		}

		// Access check.
		if ( ! wpcd_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action - purge logs.', 'wpcd' ) ) );
		}

		$post_type   = sanitize_text_field( $_POST['params']['post_type'] );
		$count_posts = wp_count_posts( $post_type );

		if ( $count_posts ) {
			$log_entries = $count_posts->private + $count_posts->publish;
		}

		$max_posts_to_delete = $log_entries;

		if ( $max_posts_to_delete > 0 ) {
			// Get posts sorted by date in descending order and limit to the max setting above.
			$posts_to_delete = get_posts(
				array(
					'orderby'        => 'date',
					'order'          => 'ASC',
					'post_type'      => $post_type,
					'posts_per_page' => -1,
					'post_status'    => 'any',
				)
			);

			foreach ( $posts_to_delete as $post_to_delete ) {
				wp_delete_post( $post_to_delete->ID, true );
			}

			$msg = __( 'All log entries have been removed.', 'wpcd' );
			wp_send_json_success( array( 'msg' => $msg ) );

		}

		wp_send_json_error( array( 'msg' => __( 'No Entries for deletion.', 'wpcd' ) ) );
	}


	/**
	 * Removes all log except unsent log entries.
	 */
	public function remove_all_log_except_unsent_logs() {

		// Nonce check.
		if ( ! check_ajax_referer( 'wpcd-unsent-log', 'nonce', false ) ) {
			wp_send_json_error( array( 'msg' => __( 'Invalid request.', 'wpcd' ) ) );
		}

		// Access check.
		if ( ! wpcd_is_admin() ) {
			wp_send_json_error( array( 'msg' => __( 'You are not authorized to perform this action - purge logs.', 'wpcd' ) ) );
		}

		$post_type   = sanitize_text_field( $_POST['params']['post_type'] );
		$count_posts = wp_count_posts( $post_type );

		if ( $count_posts ) {
			$log_entries = $count_posts->private + $count_posts->publish;
		}

		$max_posts_to_delete = $log_entries;

		if ( $max_posts_to_delete > 0 ) {
			// Get posts sorted by date in descending order and limit to the max setting above.
			$posts_to_delete = get_posts(
				array(
					'orderby'        => 'date',
					'order'          => 'ASC',
					'post_type'      => $post_type,
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'meta_query'     => array(
						array(
							'key'     => 'notification_sent',
							'value'   => 1,
							'compare' => '=',
						),
					),
				)
			);

			foreach ( $posts_to_delete as $post_to_delete ) {
				wp_delete_post( $post_to_delete->ID, true );
			}

			$msg = __( 'All sent log entries have been removed.', 'wpcd' );

			wp_send_json_success( array( 'msg' => $msg ) );
		}

		wp_send_json_error( array( 'msg' => __( 'No Entries for deletion.', 'wpcd' ) ) );
	}

	/**
	 * Enhance search for logs listing screen
	 *
	 * Action hook: pre_get_posts
	 *
	 * @param  object $query query.
	 *
	 * @return null
	 */
	public function wpcd_logs_extend_admin_search( $query ) {

		global $typenow;

		// use your post type.
		$post_type = $this->get_post_type();

		if ( is_admin() && $typenow === $post_type && $query->is_search() ) {

			// Use your Custom fields/column name to search for.
			$search_fields = $this->get_post_search_fields();

			$search_term = $query->query_vars['s'];

			// $query->query_vars['s'] = '';

			if ( '' !== $search_term ) {
				$meta_query = array( 'relation' => 'OR' );

				foreach ( $search_fields as $search_field ) {
					array_push(
						$meta_query,
						array(
							'key'     => $search_field,
							'value'   => $search_term,
							'compare' => 'LIKE',
						)
					);
				}

				// Use an 'OR' comparison for each additional custom meta field.
				if ( count( $meta_query ) > 1 ) {
					$meta_query['relation'] = 'OR';
				}
				// Set the meta_query parameter.
				$query->set( 'meta_query', $meta_query );

				// To allow the search to also return "OR" results on the post_title.
				$query->set( '_meta_or_title', $search_term );

			};
		} else {
			return;
		}

	}

	/**
	 * Meta or title search for wpcd_app_server post type
	 *
	 * Action hook: pre_get_posts
	 *
	 * @param  object $query query.
	 */
	public function wpcd_logs_meta_or_title_search( $query ) {

		global $typenow;

		$post_type = $this->get_post_type();
		$title     = $query->get( '_meta_or_title' );
		if ( is_admin() && $typenow === $post_type && $query->is_search() && $title ) {

			add_filter(
				'get_meta_sql',
				function( $sql ) use ( $title, $post_type ) {
					global $wpdb;

					// Only run once.
					static $nr = 0;
					if ( 0 !== $nr++ ) {
						return $sql;
					}

					// Modified WHERE.
					$sql['where'] = sprintf(
						' AND ( (%s) OR (%s) ) ',
						$wpdb->prepare( "{$wpdb->posts}.post_title LIKE '%%%s%%'", $title ),
						mb_substr( $sql['where'], 5, mb_strlen( $sql['where'] ) )
					);

					// Only run if post type is wpcd_command_log or wpcd_ssh_log.
					if ( in_array( $post_type, array( 'wpcd_command_log', 'wpcd_ssh_log' ), true ) ) {
						$server_meta_search = " OR ({$wpdb->postmeta}.meta_key = 'parent_post_id' AND {$wpdb->postmeta}.meta_value IN ( SELECT P.ID FROM {$wpdb->posts} AS P LEFT JOIN {$wpdb->postmeta} AS PM on PM.post_id = P.ID WHERE P.post_type = 'wpcd_app_server' and P.post_status = 'private' and ( ( PM.meta_key = 'wpcd_server_name' AND PM.meta_value LIKE '" . esc_sql( '%' . $wpdb->esc_like( $title ) . '%' ) . "' ) ) ) ) ";

						$sql['where'] .= $server_meta_search;
					}

					return $sql;
				}
			);
		}
	}

	/**
	 * Change where clause for sql query.
	 *
	 * Action hook: posts_where
	 *
	 * @param  string $where where string.
	 * @param  object $wp_query wp_query.
	 *
	 * @return string
	 */
	public function wpcd_logs_posts_where( $where, $wp_query ) {
		global $wpdb, $typenow;

		$post_type = $this->get_post_type();

		if ( is_admin() && $typenow === $post_type && $wp_query->is_search() ) {

			if ( isset( $wp_query->query_vars['_meta_or_title'] ) && '' !== $wp_query->query_vars['_meta_or_title'] ) {

				$_meta_or_title = $wp_query->query_vars['_meta_or_title'];

				$search = $this->get_find_query( $_meta_or_title );

				$find    = $search;
				$replace = '';
				$where   = str_replace( $find, $replace, $where );

			}
		}
		return $where;
	}

	/**
	 * Parse the search terms
	 *
	 * @param  array $terms terms.
	 *
	 * @return array
	 */
	protected function parse_search_terms( $terms ) {
		$strtolower = function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower';
		$checked    = array();

		$words = explode(
			',',
			_x(
				'about,an,are,as,at,be,by,com,for,from,how,in,is,it,of,on,or,that,the,this,to,was,what,when,where,who,will,with,www',
				'Comma-separated list of search stopwords in your language'
			)
		);

		$stopwords = array();

		foreach ( $words as $word ) {
			$word = trim( $word, "\r\n\t " );
			if ( $word ) {
				$stopwords[] = $word;
			}
		}

		foreach ( $terms as $term ) {
			// keep before/after spaces when term is for exact match.
			if ( preg_match( '/^".+"$/', $term ) ) {
				$term = trim( $term, "\"'" );
			} else {
				$term = trim( $term, "\"' " );
			}

			// Avoid single A-Z and single dashes.
			if ( ! $term || ( 1 === strlen( $term ) && preg_match( '/^[a-z\-]$/i', $term ) ) ) {
				continue;
			}

			if ( in_array( call_user_func( $strtolower, $term ), $stopwords, true ) ) {
				continue;
			}

			$checked[] = $term;
		}

		return $checked;
	}

	/**
	 * Prepare the find query to replace in where clause
	 *
	 * @param  string $q query string.
	 *
	 * @return string
	 */
	protected function get_find_query( $q ) {
		global $wpdb;

		if ( preg_match_all( '/".*?("|$)|((?<=[\t ",+])|^)[^\t ",+]+/', $q, $matches ) ) {
			$search_term_count = count( $matches[0] );
			$search_terms      = $this->parse_search_terms( $matches[0] );
			// if the search string has only short terms or stopwords, or is 10+ terms long, match it as sentence.
			if ( empty( $q ) || count( $search_terms ) > 9 ) {
				$search_terms = array( $q );
			}
		} else {
			$search_terms = array( $q );
		}

		$n         = '%';
		$search    = '';
		$searchand = '';

		$exclusion_prefix = apply_filters( 'wp_query_search_exclusion_prefix', '-' );

		foreach ( $search_terms as $term ) {
			// If there is an $exclusion_prefix, terms prefixed with it should be excluded.
			$substr  = substr( $term, 0, 1 );
			$exclude = $exclusion_prefix && ( $exclusion_prefix === $substr );
			if ( $exclude ) {
				$like_op  = 'NOT LIKE';
				$andor_op = 'AND';
				$term     = substr( $term, 1 );
			} else {
				$like_op  = 'LIKE';
				$andor_op = 'OR';
			}

			$like      = $n . $wpdb->esc_like( $term ) . $n;
			$search   .= $wpdb->prepare( "{$searchand}(({$wpdb->posts}.post_title $like_op %s) $andor_op ({$wpdb->posts}.post_excerpt $like_op %s) $andor_op ({$wpdb->posts}.post_content $like_op %s))", $like, $like, $like );
			$searchand = ' AND ';

		}

		$search = ' AND (' . $search . ')';

		return $search;
	}

	/**
	 * Checks if current user has the capability to access add new, edit or listing page for logs.
	 *
	 * Action hook: load-edit.php, load-post-new.php, load-post.php
	 *
	 * @return void
	 */
	public function wpcd_posts_log_load() {
		$screen = get_current_screen();

		if ( $screen->post_type === $this->get_post_type() ) {
			if ( ! current_user_can( 'wpcd_manage_logs' ) ) {
				wp_die( esc_html( __( 'You don\'t have access to this page.', 'wpcd' ) ) );
			}
		}
	}

	/**
	 * Adds custom back to list button for all type of logs
	 *
	 * @return void
	 */
	public function wpcd_logs_backtolist_btn() {
		$screen    = get_current_screen();
		$post_type = $this->get_post_type();

		if ( $screen->id === $post_type ) {
			$query          = sprintf( 'edit.php?post_type=%s', $post_type );
			$backtolist_url = admin_url( $query );
			$backtolist_txt = __( 'Back To List', 'wpcd' );
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$('.wp-heading-inline').append('<a href="<?php echo esc_attr( $backtolist_url ); ?>" class="page-title-action"><?php echo esc_html( $backtolist_txt ); ?></a>');
				});
			</script>
			<?php
		}
	}

	/**
	 * Return an array of sensitive values
	 * and what to replace them with.
	 *
	 * Called by certain logging functions in the descendant class.
	 *
	 * @return array
	 */
	public function wpcd_get_pw_terms_to_clean() {

		$terms = WPCD()->wpcd_get_pw_terms_to_clean();

		return $terms;

	}

}
