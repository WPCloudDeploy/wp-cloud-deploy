<style>
	/*******************************************************************/
	/* CSS for VPN post meta                                           */
	/*******************************************************************/
	.command_log_meta_field{
		margin-bottom: 10px;
	}
	.command_log_meta_field label{
		width: 150px;
		display: inline-block;
		font-weight: bold;
	}
	.command_log_meta_field input[type="text"],
	.command_log_meta_field select{
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
<div class="command_log_meta_field">
	<label for="command_type"><?php echo esc_html( __( 'Command Type', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $command_type ); ?></p>
</div>
<hr />
<div class="command_log_meta_field">
	<label for="command_reference"><?php echo esc_html( __( 'Command Reference', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $command_reference ); ?></p>
</div>
<hr />
<div class="command_log_meta_field">
	<label for="command_result"><?php echo esc_html( __( 'Command Result', 'wpcd' ) ); ?></label>
	<p><?php echo wpautop( esc_html( $command_result ) ); ?></p>
</div>
<hr />
<div class="command_log_meta_field">
	<label for="parent_post_id"><?php echo esc_html( __( 'Log Owner or Parent ID', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $parent_post_id ); ?></p>
</div>
