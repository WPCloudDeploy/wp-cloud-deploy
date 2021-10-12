<?php
/**
 * This class handles data sync rest.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPCD data sync rest.
 *
 * @package wpcd
 * @version 1.0.0 / wpcd
 * @since 4.2.0
 */
class WPCD_DATA_SYNC_REST {

	/**
	 * Instance function.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_DATA_SYNC_REST constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->hooks(); // register hooks.
	}

	/**
	 * Hooks function.
	 */
	private function hooks() {

		if ( wpcd_data_sync_allowed() ) {
			add_filter(
				'rest_pre_serve_request',
				function( $value ) {
					header( 'Access-Control-Allow-Headers: Authorization, X-WP-Nonce,Content-Type, X-Requested-With' );
					header( 'Access-Control-Allow-Origin: *' );
					header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE' );
					header( 'Access-Control-Allow-Credentials: true' );
					return $value;
				},
				11
			);

			// Add custom routes.
			add_action( 'rest_api_init', array( $this, 'wpcd_rest_api_routes' ) );
		}

	}

	/**
	 * Adds Custom routes.
	 */
	public function wpcd_rest_api_routes() {
		register_rest_route(
			'wpcd/v1',
			'/receivedata',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'receivedata' ),
				'permission_callback' => '__return_true',
			)
		);
	}


	/**
	 * Callback function for receiving the data
	 *
	 * @param  object $request request.
	 * @return object
	 */
	public function receivedata( $request ) {
		global $wpdb;
		$params              = $request->get_params();
		$key                 = $params['key'];
		$encrypted_json_data = $params['data'];
		$data                = $params['data'];
		$username            = $params['username'];
		$password            = $params['password'];
		$source_site_url     = $params['source_site_url'];

		// Checks if the data is empty.
		if ( empty( $data ) ) {
			$json['message'] = __( 'Sync data can not be empty.', 'wpcd' );
			$json['status']  = false;
			$error           = new WP_REST_Response( $json );
			$error->set_status( 400 );
			return $error;
		}

		// Checks if the username or password is empty.
		if ( empty( $username ) || empty( $password ) ) {
			$json['message'] = __( 'Empty Username or Password.', 'wpcd' );
			$json['status']  = false;
			$error           = new WP_REST_Response( $json );
			$error->set_status( 400 );
			return $error;
		}

		// It validates the username and password.
		$validate = wp_authenticate( $username, $password );

		// if validation fails.
		if ( is_wp_error( $validate ) ) {
			$json['message'] = wp_strip_all_tags( $validate->get_error_message() );
			$json['status']  = false;
			$error           = new WP_REST_Response( $json );
			$error->set_status( 400 );
			return $error;
		} elseif ( ! wpcd_is_admin( $validate->ID ) ) {
			$json['message'] = __( 'The provided login details are not of admin account. Use admin account details for this action.', 'wpcd' );
			$json['status']  = false;
			$error           = new WP_REST_Response( $json );
			$error->set_status( 400 );
			return $error;
		} else {
			set_transient( 'wpcd_sync_data_to_target_site', 1, 3600 );
		}

		$user_id         = $validate->ID;
		$transient_check = get_transient( 'wpcd_sync_data_to_target_site' );
		if ( false === (bool) $transient_check ) {
			$json['message'] = __( 'Transient not found or has been expired.', 'wpcd' );
			$json['status']  = false;
			$error           = new WP_REST_Response( $json );
			$error->set_status( 400 );
			return $error;
		}

		// check if restore file table is not exists then create new table.
		WPCD_SYNC::wpcd_create_restore_file_table();

		$table_name = $wpdb->prefix . 'wpcd_restore_files';

		$random_number  = wp_rand( 10000000, 99999999 );
		$json_file_name = $random_number . '_' . gmdate( 'Ymdhis' ) . '.json';

		$get_files_sql = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE user_id = %d AND file_name = %d", array( $user_id, $json_file_name ) );
		$results       = $wpdb->get_results( $get_files_sql );

		$date_time = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) );

		if ( count( $results ) === 0 ) {
			// Insert if no such record found.
			$insert_result = $wpdb->insert(
				$table_name,
				array(
					'user_id'       => $user_id,
					'recieved_from' => $source_site_url,
					'file_name'     => $json_file_name,
					'file_data'     => $encrypted_json_data,
					'date_time'     => $date_time,
				)
			);

			if ( ! $insert_result ) {
				$error_msg = array( 'msg' => __( 'Unable to create the json file.', 'wpcd' ) );
				wp_send_json_error( $error_msg );
				wp_die();
			} else {

				// get count of restricted files.
				$restricted_files = wpcd_get_early_option( 'wpcd_restrict_no_files_store' );

				if ( false === (bool) $restricted_files ) {
					$restricted_files = 10;
				}

				// get count of stored files.
				$get_all_files = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY restore_id DESC", array( $user_id ) );
				$files_count   = $wpdb->get_results( $get_all_files );

				// delete old received files.
				if ( 0 !== (int) $restricted_files ) {
					if ( count( $files_count ) > $restricted_files ) {
						$get_old_file      = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY restore_id DESC LIMIT %d, 1", array( $user_id, $restricted_files ) );
						$old_received_file = $wpdb->get_results( $get_old_file );
						if ( count( $old_received_file ) !== 0 ) {
							foreach ( $old_received_file as $key => $value ) {
								$delete_id = $value->restore_id;
							}
							if ( $delete_id ) {
								$delete_query = "DELETE FROM $table_name WHERE restore_id <= $delete_id";
								$wpdb->query( $delete_query );
							}
						}
					}
				}
			}
		} else {
			$error_msg = array( 'msg' => __( 'Unable to create the json file.', 'wpcd' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		$json['message'] = __( 'Data received successfully.', 'wpcd' );

		$json['status'] = true;
		$success        = new WP_REST_Response( $json );
		$success->set_status( 200 );
		return $success;

	}

}
