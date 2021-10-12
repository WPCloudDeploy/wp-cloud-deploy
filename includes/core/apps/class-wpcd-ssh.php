<?php
/**
 * WPCD_SSH Class
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

/* @TODO: All these functions right now seem to be related specifically to the VPN app - what are they doing up here in the parent class??? */

/**
 * Parent class for all app specific SSH classes.
 */
class WPCD_SSH {

	/**
	 * WPCD_SSH constructor.
	 */
	public function __construct() {
		// empty.
	}

	/**
	 * Execute a command using SSH.
	 *
	 * @param string $ip The IP of the instance.
	 * @param string $command The command to execute.
	 * @param array  $key_attributes The SSH key.
	 * @param string $action The action that is executing this command.
	 * @param string $reference_post_id The post ID most affected by this action and which will be used as a log reference (generally the server post).
	 * @param string $user The user to login with.
	 * @param string $callback callback.
	 *
	 * @return mixed
	 */
	public function exec( $ip, $command, array $key_attributes, $action, $reference_post_id, $user = 'root', $callback = null ) {
		do_action( 'wpcd_log_error', "trying to execute $command on $ip", 'debug', __FILE__, __LINE__ );

		/* Use a filter to force an override of the user name if necessary - this can be useful when servers such as aws don't use the 'root' user as the default login user */
		$user = apply_filters( 'wpcd_exec_ssh_user', $user );

		$ssh = new SSH2( $ip );

		$key = PublicKeyLoader::load( WPCD()->decrypt( $key_attributes['key'] ), $key_attributes['passwd'] );

		/* Login */
		if ( ! $ssh->login( $user, $key ) ) {
			do_action( 'wpcd_log_error', "Unable to login to server at ip $ip", 'error', __FILE__, __LINE__ );
			WPCD_POSTS_SSH_LOG()->add_ssh_log_entry( $reference_post_id, 'Login', 'Failed' );
			return new \WP_Error( $this->construct_error( __( 'Unfortunately it looks like we are unable to connect to the server at this time.  This generally occurs when the server is initializing for the first time or is being restarted. <br /><br /> Please wait a few minutes and try again.  If the problem continues, please contact our support team.', 'wpcd' ) ) );
		}

		/* Get ssh timeout and, it none set, use a default */
		$default_ssh_timeout = wpcd_get_early_option( 'ssh_timeout' );
		if ( empty( $default_ssh_timeout ) ) {
			$default_ssh_timeout = 29;
		}

		/* If using a callback we likely want to lower the ssh timeout to get more frequent updates... */
		if ( ! empty( $callback ) ) {
			$ssh_timeout = 15;
		} else {
			// We're not using a callback so we need to allow as much time as possible to let the script run.
			// But there's no point in trying to get the ssh command to run longer than the PHP script is allowed to run.
			$php_max_execution_time = ini_get( 'max_execution_time' );
			if ( ! empty( $php_max_execution_time ) ) {
				// Set the SSH timeout to a few seconds less than the max execution timeout for a php script.
				$ssh_timeout = ( (int) $php_max_execution_time ) - 15;
				// Note that we cannot set the timeout for the webserver.  So if the above timeout value is greater than the server timeouts, then weird behavior will occur.
				// For NGINX set the following values in the nginx config file - we're using 600 seconds as an example here:
				// * fastcgi_read_timeout 600;
				// * client_header_timeout 600;
				// * client_body_timeout 600;
				//
				// For proxied ngninx:
				// * proxy_read_timeout 600s;
				//
				// For APACHE servers (which we don't use but mentioning here for completeness)
				// in .htaccess set the followng:
				// * TimeOut 600.
			} else {
				$ssh_timeout = $default_ssh_timeout;
			}
		}

		/* Execute command - you have 3 mins before it times out. Anything greater than 3 mins should be handled with a callback.  Some proxy servers only give you 1 minute before it times out! */
		$ssh->setTimeout( $ssh_timeout );
		$result = $ssh->exec( $command, $callback );
		do_action( 'wpcd_log_error', "Executing command $command, getting result = $result", 'trace', __FILE__, __LINE__, null, false );

		/* Add log */
		WPCD_POSTS_SSH_LOG()->add_ssh_log_entry( $reference_post_id, $command, $result );

		/* No data coming back is considered an error.  SO, if running a command that results in no feedback pipe follow it up with "&& echo done" or something similar so you get some output. */
		if ( empty( $result ) ) {
			/* All of these actions are for the VPN app.  Non VPN actions are 'generic' actions with no specific switch statement associated with it here, just the default switch. */
			switch ( $action ) {
				case 'remove-user':
				case 'connected':
				case 'disconnect':
					return new \WP_Error( $this->construct_error( __( 'Unfortunately it looks like there are no clients connected to the server at this time.', 'wpcd' ) ) );
				default:
					return new \WP_Error( $this->construct_error( __( 'Oops - an internal error occurred. Please wait a few minutes and try again.  If the problem continues, please contact our support team.', 'wpcd' ) ) );
			}
		}

		/* Evaluate potential errors for certain action types */
		if ( in_array( $action, array( 'remove-user', 'download-file', 'connected', 'disconnect' ) ) ) {

			if ( strpos( $result, 'No such file or directory' ) !== false ) {

				do_action( 'wpcd_log_error', "Error in executing command: $result", 'error', __FILE__, __LINE__ );

				/* All of these actions are for the VPN app.  Non VPN actions are 'generic' actions with no specific switch statement associated with it here, just teh default switch. */
				switch ( $action ) {
					case 'remove-user':
					case 'connected':
					case 'disconnect':
						return new \WP_Error( $this->construct_error( __( 'Unfortunately it looks like there are no clients connected to the server at this time.', 'wpcd' ) ) );
					default:
						return new \WP_Error( $this->construct_error( __( 'Oops - it looks like we cannot find the file or directory that we were searching for. Please wait a few minutes and try again.  If the problem continues, please contact our support team.', 'wpcd' ) ) );
				}
			}
		}

		/* Check for certain errors in generic commands related to this app */
		if ( in_array( $action, array( 'generic' ) ) ) {
			if ( strpos( $result, 'Could not get lock' ) !== false ) {
				do_action( 'wpcd_log_error', "Error in executing command: $result", 'error', __FILE__, __LINE__ );
				return new \WP_Error( $this->construct_error( $result ) );
			}
		}

		/**
		 * Final result
		 *
		 * @TODO: Why are we echoing and then cleaning?  Wouldn't just a plain 'return $result' work?
		 */
		ob_start();
		echo $result;
		return ob_get_clean();
	}

	/**
	 * Download a file using SFTP.
	 *
	 * @param string $ip The IP of the instance.
	 * @param string $remote_file The remote file to download.
	 * @param string $local_file The local file to download (if empty, the file contents will be sent as a string).
	 * @param array  $key_attributes The SSH key.
	 * @param string $user The user to login with.
	 * @param int    $error_type 1=return html formatted string when there are errors  2=return a number 3=return a plain string with a message.
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
			switch ( $error_type ) {
				case 1:
					return new \WP_Error( $this->construct_error( __( 'Unfortunately it looks like we are unable to connect to the server at this time. This generally occurs when the server is initializing for the first time or is being restarted. <br /><br />Please wait a few minutes and try again. If the problem continues, please contact our support team.', 'wpcd' ) ) );
					break;
				case 2:
					return 101;
					break;
				case 3:
					return __( 'Unfortunately it looks like we are unable to connect to the server at this time. This generally occurs when the server is initializing for the first time or is being restarted. Please wait a few minutes and try again. If the problem continues, please contact our support team.', 'wpcd' );
					break;
			}
		}
		if ( empty( $local_file ) ) {
			$contents = $ssh->get( $remote_file );
			if ( empty( $contents ) ) {
				do_action( 'wpcd_log_error', "Unable to find file $remote_file", 'error', __FILE__, __LINE__ );
				switch ( $error_type ) {
					case 1:
						return new \WP_Error( $this->construct_error( __( 'We are not able to locate the file we are looking for. Please wait a few minutes and try again. If the problem continues, please contact our support team.', 'wpcd' ) ) );
						break;
					case 2:
						return 102;
						break;
					case 3:
						return __( 'We are not able to locate the file we are looking for. Please wait a few minutes and try again. If the problem continues, please contact our support team.', 'wpcd' );
						break;
				}
			}
			ob_start();
			echo $contents;
			return ob_get_clean();
		} else {
			$ssh->get( $remote_file, $local_file );
		}
	}

	/**
	 * Uploads a file using SFTP.
	 *
	 * @param string $ip The IP of the instance.
	 * @param string $remote_file The remote file name.
	 * @param string $local_file The local file to upload.
	 * @param array  $key_attributes The SSH key.
	 * @param string $user The user to login with.
	 *
	 * @return mixed
	 */
	public function upload( $ip, $remote_file, $local_file, array $key_attributes, $user = 'root' ) {
		do_action( 'wpcd_log_error', "trying to upload $remote_file to $local_file on $ip", 'debug', __FILE__, __LINE__ );

		/* Use a filter to force an override of the user name if necessary - this can be useful when servers such as aws don't use the 'root' user as the default login user */
		$user = apply_filters( 'wpcd_upload_ssh_user', $user, $key_attributes );

		$ssh = new SFTP( $ip );

		$key = PublicKeyLoader::load( WPCD()->decrypt( $key_attributes['key'] ), $key_attributes['passwd'] );

		if ( ! $ssh->login( $user, $key ) ) {
			do_action( 'wpcd_log_error', "Unable to login to server at ip $ip", 'error', __FILE__, __LINE__ );
			return new \WP_Error( $this->construct_error( __( 'Unfortunately it looks like we are unable to connect to the server at this time. This generally occurs when the server is initializing for the first time or is being restarted. <br /><br />Please wait a few minutes and try again. If the problem continues, please contact our support team.', 'wpcd' ) ) );
		}

		$result = $ssh->put( $remote_file, $local_file, SFTP::SOURCE_LOCAL_FILE );

		do_action( 'wpcd_log_error', "uploaded $remote_file to $local_file on $ip", 'trace', __FILE__, __LINE__, null, false );

		return $result;
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
