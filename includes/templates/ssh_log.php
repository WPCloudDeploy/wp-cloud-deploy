<style>
	/*******************************************************************/
	/* CSS for SSH Log post meta                                           */
	/*******************************************************************/
	.ssh_log_meta_field{
		margin-bottom: 10px;
	}
	.ssh_log_meta_field label{
		width: 150px;
		display: inline-block;
		font-weight: bold;
	}
	.ssh_log_meta_field input[type="text"],
	.ssh_log_meta_field select{
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
<div class="ssh_log_meta_field">
	<label for="ssh_cmd"><?php echo esc_html( __( 'SSH Command', 'wpcd' ) ); ?></label>
	<p><?php echo wpautop( esc_html( $ssh_cmd ) ); ?></p>
</div>
<hr />
<div class="ssh_log_meta_field">
	<label for="ssh_cmd_result"><?php echo esc_html( __( 'SSH Result', 'wpcd' ) ); ?></label>
	<p><?php echo wpautop( esc_html( $ssh_cmd_result ) ); ?></p>
</div>
<hr />
<div class="ssh_log_meta_field">
	<label for="parent_post_id"><?php echo esc_html( __( 'Log Parent ID', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $parent_post_id ); ?></p>
</div>
