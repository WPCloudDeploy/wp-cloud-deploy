<?php
/**
 * VPN SSH
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require wpcd_path . 'vendor/autoload.php';

use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\PublicKeyLoader;

/**
 * Class VPN_SSH
 */
class VPN_SSH extends WPCD_SSH {

	/**
	 * VPN_SSH constructor.
	 */
	public function __construct() {
		// empty.
	}

	/**
	 * Download a file using SFTP.
	 *
	 * @param string $ip The IP of the instance.
	 * @param string $remote_file The remote file to download.
	 * @param string $local_file The local file to download (if empty, the file contents will be sent as a string).
	 * @param array  $key_attributes The SSH key.
	 * @param string $user The user to login with.
	 * @param bool   $error_type error_type.
	 *
	 * @return mixed
	 */
	public function download( $ip, $remote_file, $local_file, array $key_attributes, $user = 'root', $error_type = 1 ) {
		do_action( 'wpcd_log_error', "trying to download $remote_file from $ip", 'debug', __FILE__, __LINE__ );

		/* Use a filter to force an override of the user name if necessary - this can be useful when servers such as aws don't use the 'root' user as the default login user */
		$user = apply_filters( 'wpcd_download_ssh_user', $user, $key_attributes );

		$ssh = new SFTP( $ip );

		$key = PublicKeyLoader::load( WPCD()->decrypt( $key_attributes['key'] ), $key_attributes['passwd'] );

		if ( ! $ssh->login( $user, $key ) ) {
			do_action( 'wpcd_log_error', "Unable to login to server at ip $ip", 'error', __FILE__, __LINE__ );
			return new \WP_Error( $this->construct_error( __( 'Unfortunately it looks like we are unable to connect to the server at this time. This generally occurs when the server is initializing for the first time or is being restarted. <br /><br />Please wait a few minutes and try again. If the problem continues, please contact our support team.', 'wpcd' ) ) );
		}
		if ( empty( $local_file ) ) {
			$contents = $ssh->get( $remote_file );
			if ( empty( $contents ) ) {
				do_action( 'wpcd_log_error', "Unable to find file $remote_file", 'error', __FILE__, __LINE__ );
				return new \WP_Error( $this->construct_error( __( 'We are not able to locate the file we are looking for. Please wait a few minutes and try again. If the problem continues, please contact our support team.', 'wpcd' ) ) );
			}
			ob_start();
			echo $contents;
			return ob_get_clean();
		} else {
			$ssh->get( $remote_file, $local_file );
		}
	}


	/**
	 * Take an error message STRING and turn it into a nicely formatted
	 * html error suitable for display in the magnific popup.
	 *
	 * @param string $message The message to reformat and display.
	 *
	 * @return string $new_message The reformatted message.
	 */
	public function construct_error( $message ) {
		$new_message     = '<div class="wpcd-vpn-error-message-wrap">';
			$new_message = $new_message . '<div class="wpcd-vpn-error-message-title">' . __( 'Oops - An Error Has Occurred', 'wpcd' ) . '</div>';
			$new_message = $new_message . '<div class="wpcd-vpn-error-message">' . $message;
				// Add text to tell user how to dismiss the message..
				$new_message = $new_message . '<div class="wpcd-vpn-error-dismiss-message">' . __( 'To dismiss this message click on the X in the upper right or anywhere on the page outside of this box.', 'wpcd' ) . '</div>';
			$new_message    .= '</div>';

			// add in a link to the help screen if a link was set up in settings...
			$help_url = wpcd_get_option( 'vpn_general_help_url' );
		if ( ! empty( $help_url ) ) {
			$new_message         .= '<hr />';
			$new_message         .= '<div class="wpcd-vpn-error-message-help-footer">';
				$new_message     .= '<div class="wpcd-vpn-error-message-help-title">' . 'Help Resources' . '</div>';
				$new_message     .= '<div class="wpcd-vpn-error-message-help-text">';
					$new_message .= sprintf( __( 'For more information and additional help please check out our <a href="%s"> help pages.</a>', 'wpcd' ), $help_url );
				$new_message     .= '</div> <!-- error-message-help-text -->';
			$new_message         .= '</div>';
		}

		$new_message .= '</div> <!-- error-message-wrap -->';

		return $new_message;
	}

}
