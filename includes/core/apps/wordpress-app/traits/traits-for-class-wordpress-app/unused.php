<?php
/**
 * Trait:
 * Contains old functions that are likely unused.
 * Used only by the class-wordpress.php file which defines the
 * WPCD_WORDPRESS_APP class.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_wpapp_unused_functions
 */
trait wpcd_wpapp_unused_functions {

	/**
	 * *** NOT USED ***
	 * Sends email to the user.
	 *
	 * @param array $instance Array of attributes for the custom post type.
	 *
	 * An example $instance array would look like this:
			Array
			(
				[post_id] => 4978
				[initial_app_name] => wordpress-app
				[scripts_version] => v1
				[region] => us-central
				[size_raw] => g6-nanode-1
				[name] => spinupvpnwpadmin-test-ema_CX0x
				[provider] => linode
				[server-type] => wordpress-app
				[provider_instance_id] => 19823428
				[server_name] => spinupvpnwpadmin-test-ema_CX0x
				[created] => 2020-03-20 00:58:36
				[actions] => a:1:{s:7:"created";i:1584683916;}
				[wordpress-app_action] => email
				[wordpress-app_action_status] => in-progress
				[last_deferred_action_source] => a:15:{i:1584683916;s:13:"wordpress-app";i:1584683953;s:13:"wordpress-app";i:1584684013;s:13:"wordpress-app";i:1584684073;s:13:"wordpress-app";i:1584684133;s:13:"wordpress-app";i:1584684193;s:13:"wordpress-app";i:1584684253;s:13:"wordpress-app";i:1584684313;s:13:"wordpress-app";i:1584684373;s:13:"wordpress-app";i:1584684433;s:13:"wordpress-app";i:1584684493;s:13:"wordpress-app";i:1584684553;s:13:"wordpress-app";i:1584684613;s:13:"wordpress-app";i:1584684673;s:13:"wordpress-app";i:1584684735;s:13:"wordpress-app";}
				[init] => 1
				[ipv4] => 45.56.75.14
				[status] => active
				[action_id] =>
				[os] => unknown
				[ip] => 45.56.75.14
			).
	 */
	private function send_email( $instance ) {
		do_action( 'wpcd_log_error', 'sending email for ' . print_r( $instance, true ), 'debug', __FILE__, __LINE__ );

		// Get the email body.
		$email_body = $this->get_app_instance_summary( $instance['post_id'], 'email-admin' );

		if ( ! empty( $email_body ) ) {
			wp_mail(
				get_option( 'admin_email' ),
				__( 'Your New WordPress Server Is Ready', 'wpcd' ),
				$email_body,
				array( 'Content-Type: text/html; charset=UTF-8' )
			);
		}

	}

	/**
	 * *** NOT USED ***
	 * Gets the summary of the instance for emails and instructions popup.
	 *
	 * @param int  $server_id POST ID of the server cpt record.
	 * @param bool $type Who is this for?  Possible values are 'email-admin', 'email-user', 'popup'.
	 *
	 * @return string
	 */
	private function get_app_instance_summary( $server_id, $type = 'email-admin' ) {

		// get the server post.
		$server_post = get_post( $server_id );

		// Get provider from server record.
		$provider    = get_post_meta( $server_post->ID, 'wpcd_server_provider', true );
		$instance_id = get_post_meta( $server_post->ID, 'wpcd_server_provider_instance_id', true );
		$details     = WPCD()->get_provider_api( $provider )->call( 'details', array( 'id' => $instance_id ) );

		// Get server size from server record.
		$size     = get_post_meta( $server_post->ID, 'wpcd_server_size', true );
		$raw_size = get_post_meta( $server_post->ID, 'wpcd_server_raw_size', true );
		$region   = get_post_meta( $server_post->ID, 'wpcd_server_region', true );
		$provider = get_post_meta( $server_post->ID, 'wpcd_server_provider', true );

		$template_suffix = 'email-admin';
		switch ( $type ) {
			case 'email-admin':
				$template_suffix = 'email-admin.html';
				break;
			case 'email-user':
				$template_suffix = 'email-user.html';
				break;
			default:
				$template_suffix = 'email-admin.html';
				break;
		}

		$template = file_get_contents( dirname( __FILE__ ) . '/templates/' . $template_suffix );
		return str_replace(
			array( '$NAME', '$PROVIDER', '$IP', '$SIZE', '$URL' ),
			array(
				get_post_meta( $server_post->ID, 'wpcd_server_name', true ),
				$this->get_providers()[ $provider ],
				$details['ip'],
				$size,
				site_url( 'account' ),
			),
			$template
		);
	}

}
