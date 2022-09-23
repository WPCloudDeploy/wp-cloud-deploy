<?php
/**
 * Trait:
 * Contains functions common to backups at the server and site/domain lelvel
 * Used only by the class-wordpress.php file which defines the
 * WPCD_WORDPRESS_APP class.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_wpapp_backup_functions.
 */
trait wpcd_wpapp_backup_functions {

	/**
	 * Return global credentials and buckets if the ones on this id are blank.
	 *
	 * @param int $id id.
	 */
	public function get_s3_credentials_for_backup( $id ) {
		$creds['aws_access_key_id']     = 'unknown';
		$creds['aws_secret_access_key'] = 'unknown';
		$creds['aws_bucket_name']       = '';
		$creds['aws_region']            = '';
		$creds['s3_endpoint']            = '';

		// Get data from the current server.
		$key    = $this->get_server_meta_by_app_id( $id, 'wpcd_wpapp_backup_aws_key', true );
		$secret = self::decrypt( $this->get_server_meta_by_app_id( $id, 'wpcd_wpapp_backup_aws_secret', true ) );
		$bucket = $this->get_server_meta_by_app_id( $id, 'wpcd_wpapp_backup_aws_bucket', true );
		$region = $this->get_server_meta_by_app_id( $id, 'wpcd_wpapp_backup_aws_region', true );
		$endpoint = $this->get_server_meta_by_app_id( $id, 'wpcd_wpapp_backup_s3_endpoint', true );

		// If keys are empty on the app, use global keys.
		if ( empty( $key ) ) {
			// get the global creds.
			$key    = wpcd_get_option( 'wordpress_app_aws_access_key' );
			$secret = self::decrypt( wpcd_get_option( 'wordpress_app_aws_secret_key' ) );
		}
		// If bucket is empty on the app, use global bucket.
		if ( empty( $bucket ) ) {
			$bucket = wpcd_get_option( 'wordpress_app_aws_bucket' );
		}
		// If region is empty on the app, use global region.
		if ( empty( $region ) ) {
			$region = wpcd_get_option( 'wordpress_app_aws_region' );
		}
		// if endpoint is empty on the app, use global endpoint.
		if ( empty( $endpoint ) ) {
			$endpoint = wpcd_get_option( 'wordpress_app_s3_endpoint' );
		}
		// If region is still empty default to us-east-1, unless endpoint is set.
		if ( empty( $region ) ) {
			$region = 'us-east-1';
		}

		// fill in the array and return.
		$creds['aws_access_key_id']     = escapeshellarg( $key );
		$creds['aws_secret_access_key'] = escapeshellarg( $secret );
		$creds['aws_bucket_name']       = escapeshellarg( $bucket );
		$creds['aws_region']            = escapeshellarg( $region );
		$creds['s3_endpoint']            = escapeshellarg( $endpoint );

		$creds['aws_access_key_id_noesc']     = $key;
		$creds['aws_secret_access_key_noesc'] = $secret;
		$creds['aws_bucket_name_noesc']       = $bucket;
		$creds['aws_region_noesc']            = $region;
		$creds['s3_endpoint_noesc']            = $endpoint;

		return $creds;

	}

}
