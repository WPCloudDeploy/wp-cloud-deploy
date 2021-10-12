<?php
/**
 * Basic server ssh.
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
 * Class BASIC_SERVER_SSH
 */
class BASIC_SERVER_SSH extends WPCD_SSH {

	/**
	 * BASIC_SERVER_SSH constructor.
	 */
	public function __construct() {
		// empty.
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
		$new_message     = '<div class="wpcd-basic-server-error-message-wrap">';
			$new_message = $new_message . '<div class="wpcd-basic-server-error-message-title">' . __( 'Oops - An Error Has Occurred', 'wpcd' ) . '</div>';
			$new_message = $new_message . '<div class="wpcd-basic-server-error-message">' . $message;
				// Add text to tell user how to dismiss the message..
				$new_message = $new_message . '<div class="wpcd-basic-server-error-dismiss-message">' . __( 'To dismiss this message click on the X in the upper right or anywhere on the page outside of this box.', 'wpcd' ) . '</div>';
			$new_message    .= '</div>';

			// add in a link to the help screen if a link was set up in settings...
			$help_url = wpcd_get_option( 'basic_server_general_help_url' );
		if ( ! empty( $help_url ) ) {
			$new_message         .= '<hr />';
			$new_message         .= '<div class="wpcd-basic-server-error-message-help-footer">';
				$new_message     .= '<div class="wpcd-basic-server-error-message-help-title">' . 'Help Resources' . '</div>';
				$new_message     .= '<div class="wpcd-basic-server-error-message-help-text">';
					$new_message .= sprintf( __( 'For more information and additional help please check out our <a href="%s"> help pages.</a>', 'wpcd' ), $help_url );
				$new_message     .= '</div> <!-- error-message-help-text -->';
			$new_message         .= '</div>';
		}

		$new_message .= '</div> <!-- error-message-wrap -->';

		return $new_message;
	}

}
