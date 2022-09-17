<?php
/**
 * Template for showing data in the metabox when viewing the APP CPT in wp-admin
 *
 * @package wpcd
 */

?>
<style>
	/*******************************************************************/
	/* CSS for Stable Diffusion post meta                              */
	/*******************************************************************/
	.stablediff_meta_field{
		margin-bottom: 10px;
	}
	.stablediff_meta_field label{
		width: 150px;
		display: inline-block;
	}
	.stablediff_meta_field input[type="text"],
	.stablediff_meta_field select{
		width: 60%;
	}
</style>
<?php
// Add nonce for security and authentication.
wp_nonce_field( 'wpcd_stablediff_app_nonce_meta_action', 'stablediff_meta' );
?>
<div class="stablediff_meta_field">
	<label for="wpcd_stablediff_scripts_version"><?php echo esc_html( __( 'Scripts Version', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_stablediff_scripts_version" id="wpcd_stablediff_scripts_version" value="<?php echo esc_attr( $wpcd_stablediff_app_scripts_version ); ?>" />
</div>
