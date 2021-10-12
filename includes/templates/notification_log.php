<style>
	/*******************************************************************/
	/* CSS for VPN post meta                                           */
	/*******************************************************************/
	.notify_log_meta_field{
		margin-bottom: 10px;
	}
	.notify_log_meta_field label{
		width: 150px;
		display: inline-block;
		font-weight: bold;
	}
	.notify_log_meta_field input[type="text"],
	.notify_log_meta_field select{
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
<div class="notify_log_meta_field">
	<label for="command_type"><?php echo esc_html( __( 'Notification Type', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $notification_type ); ?></p>
</div>
<hr />
<div class="notify_log_meta_field">
	<label for="command_reference"><?php echo esc_html( __( 'Notification Reference', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $notification_reference ); ?></p>
</div>
<hr />
<div class="notify_log_meta_field">
	<label for="command_result"><?php echo esc_html( __( 'Notification Message', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $notification_message ); ?></p>
</div>
<hr />
<div class="notify_log_meta_field">
	<label for="parent_post_id"><?php echo esc_html( __( 'Notification Owner or Parent ID', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $parent_post_id ); ?></p>
</div>
