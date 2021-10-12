<style>
	/*******************************************************************/
	/* CSS for Error Log post meta                                           */
	/*******************************************************************/
	.error_log_meta_field{
		margin-bottom: 10px;
	}
	.error_log_meta_field label{
		width: 150px;
		display: inline-block;
		font-weight: bold;
	}
	.error_log_meta_field input[type="text"],
	.error_log_meta_field select{
		width: 60%;
	}
</style>
<?php
/**
 * Add nonce for security and authentication.
 *
 * @package wpcd
 */

wp_nonce_field( 'wpcd_app_nonce_meta_action', 'app_meta' );
?>
<div class="error_log_meta_field">
	<label for="error_type"><?php echo esc_html( __( 'Error Type', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $error_type ); ?></p>
</div>
<hr />
<div class="error_log_meta_field">
	<label for="error_msg"><?php echo esc_html( __( 'Error Message', 'wpcd' ) ); ?></label>
	<p><?php echo wpautop( esc_html( $error_msg ) ); ?></p>
</div>
<hr />
<div class="error_log_meta_field">
	<label for="error_file"><?php echo esc_html( __( 'Error File', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $error_file ); ?></p>
</div>
<div class="error_log_meta_field">
	<label for="error_line"><?php echo esc_html( __( 'Error Line', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $error_line ); ?></p>
</div>
<div class="error_log_meta_field">
	<label for="error_data"><?php echo esc_html( __( 'Additional Data', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $error_data ); ?></p>
</div>
