<?php
/**
 * This class handles DNS configurations for each
 * supported DNS provider.
 *
 * @package WPCD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_DNS
 */
class WPCD_DNS extends WPCD_Base {

	/**
	 * Constructor
	 */
	public function __construct() {

		// Setup WordPress hooks.
		$this->hooks();

	}

	/**
	 * Hook into WordPress and other plugins as needed.
	 */
	private function hooks() {

		// Nothing needed.
	}

	/**
	 * Get a unique subdomain string.
	 *
	 * The calling program will need to append it to the root domain (usually by calling the get_active_root_domain() function in this class.
	 *
	 * @param int $length The length of the random subdomain string to return.
	 *
	 * @return string
	 */
	public function get_subdomain( $length = 12 ) {
		$subdomain = apply_filters( 'wpcd_wpapp_subdomain_string', wpcd_random_str( $length, '0123456789abcdefghijklmnopqrstuvwxyz' ) );
		return $subdomain;
	}

	/**
	 * Get the root domain for the active DNS provider.
	 * Since we only support CloudFlare right now, it's easy!
	 *
	 * @return string
	 */
	public function get_active_root_domain() {
		return apply_filters( 'wpcd_wpapp_active_root_domain', wpcd_get_early_option( 'wordpress_app_dns_cf_temp_domain' ) );
	}

	/**
	 * Return a full temp domain of the type xxx.yyy.zzz.
	 *
	 * @param int $subdomain_length The length of the subdomain to return.
	 */
	public function get_full_temp_domain( $subdomain_length = 12 ) {

		$root_domain = $this->get_active_root_domain();

		if ( ! empty( $root_domain ) ) {
			return $this->get_subdomain( $subdomain_length ) . '.' . $root_domain;
		}

		return '';

	}

	/**
	 * Contact the domain provider and update their A records to point their servers to the specified address.
	 *
	 * @param string $domain The subdomain or domain that needs to be added to the DNS provider records.
	 * @param string $ipv4   IPv4 value - DNS provider will update their records to point here.
	 * @param string $ipv6   IPv6 value - DNS provider will update their records to point here.
	 *
	 * @return boolean
	 */
	public function set_dns_for_domain( $domain, $ipv4, $ipv6 = '' ) {

		// if we ever support more than cloudflare, we'll first need to see which DNS provider takes precedence.
		// For now we just pull the cloudflare values and use them.
		if ( wpcd_get_option( 'wordpress_app_dns_cf_enable' ) ) {
			$root_domain = $this->get_active_root_domain();
			if ( strpos( $domain, $root_domain ) ) {
				// Looks like the root domain is part of the $domain string so we can try to add to the cloudflare DNS.
				$zone_id = wpcd_get_option( 'wordpress_app_dns_cf_zone_id' );
				$token   = wpcd_get_option( 'wordpress_app_dns_cf_token' );
				$proxied = ! wpcd_get_option( 'wordpress_app_dns_cf_disable_proxy' );
				// If the option to add an IPV6 AAAA record is not turned on, zero out the $ipv6 var so that we don't attempt to add the AAAA record.
				if ( ! wpcd_get_option( 'wordpress_app_auto_add_aaaa' ) ) {
					$ipv6 = '';
				}
				return $this->cloudflare_add_subdomain( $domain, $zone_id, $token, $ipv4, $proxied, $ipv6 );
			}
		}

		return false;

	}

	/**
	 * Contact the domain provider and delete a domain.
	 *
	 * @TODO: Right now we're checking the domain to see
	 * if it has the same root as that in the configuration.
	 * But maybe that's not needed.  What if they change the
	 * root in the config?  We might still want to attempt
	 * a delete anyway?
	 *
	 * @param string $domain The subdomain or domain that needs to be deleted - expect format is 'xxx.yyy' or 'xxx.yyy.zzz'.
	 * If only the subdomain is passed without a '.xxx' extension this function will fail since we are checking for the
	 * domain root to determine if it is part of the configured dns settings.
	 *
	 * @return boolean
	 */
	public function delete_dns_for_domain( $domain ) {

		// if we ever support more than cloudflare, we'll first need to see which DNS provider takes precedence.
		// For now we just pull the cloudflare values and use them.
		if ( wpcd_get_option( 'wordpress_app_dns_cf_auto_delete' ) ) {
			$root_domain = $this->get_active_root_domain();
			if ( strpos( $domain, '.' . $root_domain ) ) {
				// Looks like the root domain is part of the $domain string so we can try to delete it from the cloudflare DNS.
				$zone_id = wpcd_get_option( 'wordpress_app_dns_cf_zone_id' );
				$token   = wpcd_get_option( 'wordpress_app_dns_cf_token' );
				return $this->cloudflare_delete_subdomain( $domain, $zone_id, $token );
			}
		}

		return false;

	}



	/**
	 * Add a subdomain to cloudflare
	 *
	 * @param string  $domain     subdomain to add to cloudflare.
	 * @param string  $zone_id    the zone id to which to add the subdomain.
	 * @param string  $token      api security token from cloudflare.
	 * @param string  $ip         IP address to associate with the subdomain.
	 * @param boolean $proxied    should new subdomain be proxied?  Default is true.
	 * @param string  $ipv6       IPV6 to be added.
	 *
	 * @return boolean
	 */
	public function cloudflare_add_subdomain( $domain, $zone_id, $token, $ip, $proxied = true, $ipv6 = '' ) {

			$url = 'https://api.cloudflare.com/client/v4/zones/' . $zone_id . '/dns_records';

            // @codingStandardsIgnoreStart
            /*'{"type":"A","name":"'$DOMAIN'","content":"'$SERVERIP'","ttl":120,"priority":10,"proxied":true}'*/
            // @codingStandardsIgnoreEnd

			$data_payload = array(
				'type'     => 'A',
				'name'     => "$domain",
				'content'  => "$ip",
				'ttl'      => 1,
				'priority' => 10,
			);

			$data_payload['proxied'] = $proxied;

			$data = wp_json_encode( $data_payload );

			$options = array(
				'body'        => $data,
				'headers'     => array(
					'Authorization' => "Bearer $token",
					'Content-Type'  => 'application/json',
				),
				'data_format' => 'body',
			);

			$response = wp_remote_post( $url, $options );

			$response_code = wp_remote_retrieve_response_code( $response );

			if ( 200 !== $response_code ) {

				/* Translators: 1. %s - $domain is the subdomain that we are attempting to add to cloudflare. */
				$message = sprintf( __( 'The automatic DNS setup for %s in Cloudflare returned an error when adding the A record. You will need to add this site manually.', 'wpcd' ), $domain );
				do_action( 'wpcd_log_error', $message, 'error', __FILE__, __LINE__, array(), false );

				return false;
			}

			// Add AAAA record.
			if ( ! empty( $ipv6 ) ) {

				$data_payload = array(
					'type'     => 'AAAA',
					'name'     => "$domain",
					'content'  => "$ipv6",
					'ttl'      => 1,
					'priority' => 10,
				);

				$data_payload['proxied'] = $proxied;

				$data = wp_json_encode( $data_payload );

				$options = array(
					'body'        => $data,
					'headers'     => array(
						'Authorization' => "Bearer $token",
						'Content-Type'  => 'application/json',
					),
					'data_format' => 'body',
				);

				$response = wp_remote_post( $url, $options );

				$response_code = wp_remote_retrieve_response_code( $response );

				if ( 200 !== $response_code ) {

					/* Translators: 1. %s - $domain is the subdomain that we are attempting to add to cloudflare. */
					$message = sprintf( __( 'The automatic DNS setup for %s in Cloudflare returned an error when adding an AAAA record. You will need to add this site manually.', 'wpcd' ), $domain );
					do_action( 'wpcd_log_error', $message, 'error', __FILE__, __LINE__, array(), false );

					return false;
				}
			}

			return true;

	}

	/**
	 * Get the details of a subdomain from cloudflare
	 *
	 * @param string $domain     subdomain to add to cloudflare.
	 * @param string $zone_id    the zone id to which to add the subdomain.
	 * @param string $token      api security token from cloudflare.
	 * @param string $type       The record type, eg 'A" or 'AAAA'.
	 *
	 * @return boolean
	 */
	public function cloudflare_get_subdomain_details( $domain, $zone_id, $token, $type = 'A' ) {

			$url = 'https://api.cloudflare.com/client/v4/zones/' . $zone_id . '/dns_records';

			// Modify get url to search for name that matches $domain...
			$url .= "?type=$type&name=$domain";

			// Additional parms to get request.
			$options = array(
				'headers'     => array(
					'Authorization' => "Bearer $token",
					'Content-Type'  => 'application/json',
				),
				'data_format' => 'body',
			);

			// Get the data.
			$response = wp_remote_get( $url, $options );

			// Decode the data.
			try {
				$json = json_decode( $response['body'] );
			} catch ( Exception $ex ) {
				$json = null;
			}

			$response_code = wp_remote_retrieve_response_code( $response );

			if ( 200 !== $response_code ) {

				/* Translators: 1. %s is the subdomain whose details we are attempting to get from cloudflare. 2. %s is the response code we got from cloudflare */
				$message = sprintf( __( 'We could not obtain details for domain %1$s from Cloudflare - an eror was returned - %2$s.', 'wpcd' ), $domain, $response_code );
				do_action( 'wpcd_log_error', $message, 'error', __FILE__, __LINE__, array(), false );

				return false;
			}

			return $json;

	}

	/**
	 * Get the cloudflare ID of a subdomain
	 *
	 * @param string $domain     subdomain to add to cloudflare.
	 * @param string $zone_id    the zone id to which to add the subdomain.
	 * @param string $token      api security token from cloudflare.
	 * @param string $type       The record type, eg 'A" or 'AAAA'.
	 *
	 * @return boolean
	 */
	public function cloudflare_get_subdomain_id( $domain, $zone_id, $token, $type = 'A' ) {
		$details = $this->cloudflare_get_subdomain_details( $domain, $zone_id, $token, $type );

		if ( $details ) {
			if ( $details->result_info->count > 0 ) {
				return $details->result[0]->id;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Delete a subdomain from cloudflare
	 *
	 * @param string $domain     subdomain to add to cloudflare.
	 * @param string $zone_id    the zone id to which to add the subdomain.
	 * @param string $token      api security token from cloudflare.
	 *
	 * @return boolean
	 */
	public function cloudflare_delete_subdomain( $domain, $zone_id, $token ) {

		// Get the domain id for "A" record the passed domain.
		$cf_domain_id = $this->cloudflare_get_subdomain_id( $domain, $zone_id, $token, 'A' );

			// If no id, just exit.
		if ( ! $cf_domain_id ) {
			return false;
		}

		// Cloudflare API base url.
		$url = 'https://api.cloudflare.com/client/v4/zones/' . $zone_id . '/dns_records';

		// Add our request to the url.
		$url .= "/$cf_domain_id";

		$options = array(
			'method'      => 'DELETE',
			'headers'     => array(
				'Authorization' => "Bearer $token",
				'Content-Type'  => 'application/json',
			),
			'data_format' => 'body',
		);

		// Send the delete request.
		$response = wp_remote_request( $url, $options );

		// Examine the response code from the request to make sure its ok.
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $response_code ) {

			/* Translators: 1. %s is the subdomain whose details we are attempting to get from cloudflare. 2. %s is the response code we got from cloudflare */
			$message = sprintf( __( 'We were unable to delete the A record for domain %1$s in Cloudflare - an eror was returned: %2$s. You will need to add this site manually.', 'wpcd' ), $domain, $response_code );
			do_action( 'wpcd_log_error', $message, 'error', __FILE__, __LINE__, array(), false );

			return false;
		}

		// Get the domain id for "AAAA" record the passed domain so we can delete it too if it exists.
		// If it exists and the delete fail, we're going to log an error but fall through to return TRUE.
		// This is because we're still assuming that the "A" record is the main thing we have to deal with for now (which we've already done above and exited if it failed).
		$cf_domain_id = $this->cloudflare_get_subdomain_id( $domain, $zone_id, $token, 'AAAA' );

		if ( $cf_domain_id ) {

			// Cloudflare API base url.
			$url = 'https://api.cloudflare.com/client/v4/zones/' . $zone_id . '/dns_records';

			// Add our request to the url.
			$url .= "/$cf_domain_id";

			$options = array(
				'method'      => 'DELETE',
				'headers'     => array(
					'Authorization' => "Bearer $token",
					'Content-Type'  => 'application/json',
				),
				'data_format' => 'body',
			);

			// Send the delete request.
			$response = wp_remote_request( $url, $options );

			// Examine the response code from the request to make sure its ok.
			$response_code = wp_remote_retrieve_response_code( $response );

			if ( 200 !== $response_code ) {

				/* Translators: 1. %s is the subdomain whose details we are attempting to get from cloudflare. 2. %s is the response code we got from cloudflare */
				$message = sprintf( __( 'We were unable to delete the AAAA record for domain %1$s in Cloudflare - an eror was returned: %2$s. You will need to add this site manually.', 'wpcd' ), $domain, $response_code );
				do_action( 'wpcd_log_error', $message, 'error', __FILE__, __LINE__, array(), false );

			}
		}

		// If got here, everything ok so return true.
		return true;

	}

}
