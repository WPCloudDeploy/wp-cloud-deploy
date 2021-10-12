<?php
/**
 * WordPress SSH
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
 * Class WORDPRESS_SSH
 */
class WORDPRESS_SSH extends WPCD_SSH {

	/**
	 * This is a helper function to perform a file downwload.
	 * Given an array representing server data and a filename,
	 * download the given file and return it as a string.
	 * to the calling program.
	 *
	 * @param array  $instance The array with details about the server.
	 * @param string $filename the full path and name of the file to download.
	 *
	 * @return string $new_message The reformatted message.
	 */
	public function do_file_download( $instance, $filename ) {

		// Get an array with ssh login data.
		$key = $this->get_ssh_key_details( $instance );
		if ( empty( $key ) ) {
			return new \WP_Error( __( 'We are unable to connect to the server at this time because we cannot get a key or instance for the server - please try again later or contact technical support.', 'wpcd' ) );			
		}

		// Initialize some variables...
		$post_id                   = $instance['post_id'];
		$root_user                 = $key['root_user'];
		$ssh_private_key_encrypted = $key['key'];
		$ssh_private_key_password  = $key['passwd'];
		$ip                        = $key['ip'];

		$result = $this->download( $ip, $filename, '', $key, $root_user, 2 );
		switch ( $result ) {
			case 101:
				return new \WP_Error( __( 'We are unable to connect to the server at this time - please try again later or contact technical support.', 'wpcd' ) );
				break;
			case 102:
				return new \WP_Error( __( 'It looks as if the requested file does not exist or is empty.', 'wpcd' ) );
				break;
			default:
				return $result;
		}

	}

	/**
	 * Given an array of server attributes,
	 * return an array with everything needed to connect
	 * via ssh.
	 *
	 * This function needs to be in the WORDPRESS_SSH class because it utilizes some WordPress app specific things.
	 *
	 * @TODO - Later we can move this back up to the class-wpcd-ssh.php file if we modify the ssh() functions in each
	 * app class to pass itself as an instance to be registered and associated with the ssh class.
	 *
	 * @param array $attributes This must contain at least the post_id and the provider elements.
	 *
	 * @return array
	 */
	public function get_ssh_key_details( $attributes ) {

		// bail out if no server.
		$post_id = $attributes['post_id'];
		if ( empty( $post_id ) ) {
			return false;
		}

		// Initialize some variables...
		$root_user                 = '';
		$ssh_private_key           = '';
		$ssh_private_key_encrypted = '';
		$ssh_private_key_password  = '';

		// Lets check the server to see if it has customized ssh key data...
		$server_post = WPCD_WORDPRESS_APP()->get_server_by_app_id( $post_id );  // get_server_by_app_id will return a server post if given a server post id or an app id so this covers both conditions.
		if ( $server_post ) {
			$ssh_private_key = WPCD()->decrypt( get_post_meta( $post_id, 'wpcd_server_ssh_private_key', true ) );
			if ( ! empty( $ssh_private_key ) ) {
				// it means we have to use the data from the server post for login.
				$ssh_private_key_encrypted = get_post_meta( $post_id, 'wpcd_server_ssh_private_key', true ); // we actually need the encrypted private key for later, not the unencrypted key.
				$ssh_private_key_password  = WPCD()->decrypt( get_post_meta( $post_id, 'wpcd_server_ssh_private_key_password', true ) );  // but we need the unencrypted password because for some reason the ssh() function we call later requires it that way...
				$root_user                 = get_post_meta( $post_id, 'wpcd_server_ssh_root_user', true );
			}
		}

		// If the security data isn't on the server, pull from the provider!
		if ( empty( $ssh_private_key ) ) {
			$root_user = WPCD()->get_provider_api( $attributes['provider'] )->get_root_user();

			$ssh_private_key_password = wpcd_get_option( 'vpn_' . $attributes['provider'] . '_sshkey_passwd' );
			if ( ! empty( $ssh_private_key_password ) ) {
				$ssh_private_key_password = WPCD()->decrypt( $ssh_private_key_password );  // we decrypt the private key password.
			}

			$ssh_private_key_encrypted = wpcd_get_option( 'vpn_' . $attributes['provider'] . '_sshkey' ); // We don't decrypt the private key itself....
		}

		// Get some other data elements...
		$instance = WPCD()->get_provider_api( $attributes['provider'] )->call( 'details', $attributes );

		// Make sure the $instance var is valid otherwise return false.
		if ( is_wp_error( $instance ) || empty( $instance ) ) {
			return false;
		}

		// Grab the ip address.
		$ip       = $instance['ip'];

		// At this point we better have some key data!
		$key = array(
			'passwd'    => $ssh_private_key_password,  // decrypted.
			'key'       => $ssh_private_key_encrypted, // encrypted.
			'ip'        => $ip,
			'root_user' => $root_user,
		);

		return $key;

	}

}
