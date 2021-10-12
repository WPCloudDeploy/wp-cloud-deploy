<?php
/**
 * Template for showing data in the metabox when viewing the APP CPT in wp-admin
 *
 * @package wpcd
 */

?>
<style>
	/*******************************************************************/
	/* CSS for VPN post meta                                           */
	/*******************************************************************/
	.basic_server_meta_field{
		margin-bottom: 10px;
	}
	.basic_server_meta_field label{
		width: 150px;
		display: inline-block;
	}
	.basic_server_meta_field input[type="text"],
	.basic_server_meta_field select{
		width: 60%;
	}
</style>
<?php
// Add nonce for security and authentication.
	wp_nonce_field( 'wpcd_basic_server_app_nonce_meta_action', 'basic_server_meta' );
?>

<div class="basic_server_meta_field">
	<label for="wpcd_basic_serverscripts_version"><?php echo esc_html( __( 'Scripts Version', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_basic_serverscripts_version" id="wpcd_basic_serverscripts_version" value="<?php echo esc_attr( $wpcd_basic_server_app_scripts_version ); ?>" />
</div>
