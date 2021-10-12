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
	.vpn_meta_field{
		margin-bottom: 10px;
	}
	.vpn_meta_field label{
		width: 150px;
		display: inline-block;
	}
	.vpn_meta_field input[type="text"],
	.vpn_meta_field select{
		width: 60%;
	}
</style>
<?php
// Add nonce for security and authentication.
wp_nonce_field( 'wpcd_vpn_app_nonce_meta_action', 'vpn_meta' );
?>
<div class="vpn_meta_field">
	<label for="wpcd_vpn_dns"><?php echo esc_html( __( 'VPN DNS', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_vpn_dns" id="wpcd_vpn_dns" value="<?php echo esc_attr( $wpcd_vpn_app_dns ); ?>" />
</div>
<div class="vpn_meta_field">
	<label for="wpcd_vpn_protocol"><?php echo esc_html( __( 'VPN Protocol', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_vpn_protocol" id="wpcd_vpn_protocol" value="<?php echo esc_attr( $wpcd_vpn_app_protocol ); ?>" />
</div>
<div class="vpn_meta_field">
	<label for="wpcd_vpn_port"><?php echo esc_html( __( 'VPN Port', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_vpn_port" id="wpcd_vpn_port" value="<?php echo esc_attr( $wpcd_vpn_app_port ); ?>" />
</div>
<div class="vpn_meta_field">
	<label for="wpcd_vpn_clients"><?php echo esc_html( __( 'VPN Clients', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_vpn_clients" id="wpcd_vpn_clients" value="<?php echo esc_attr( $wpcd_vpn_app_clients ); ?>" />
	<label><?php echo esc_html( __( 'Enter clients separated by ","', 'wpcd' ) ); ?></label>
</div>
<div class="vpn_meta_field">
	<label for="wpcd_vpn_scripts_version"><?php echo esc_html( __( 'Scripts Version', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_vpn_scripts_version" id="wpcd_vpn_scripts_version" value="<?php echo esc_attr( $wpcd_vpn_app_scripts_version ); ?>" />
</div>
<div class="vpn_meta_field">
	<label for="wpcd_vpn_max_clients"><?php echo esc_html( __( 'VPN Max Clients', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_vpn_max_clients" id="wpcd_vpn_max_clients" value="<?php echo esc_attr( $wpcd_vpn_app_max_clients ); ?>" />
</div>
