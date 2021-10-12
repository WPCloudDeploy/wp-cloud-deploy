<style>
	/*******************************************************************/
	/* CSS for notifcation sent log meta                                           */
	/*******************************************************************/
	.notify_sent_log_meta_field{
		margin-bottom: 10px;
	}
	.notify_sent_log_meta_field label{
		width: 150px;
		display: inline-block;
		font-weight: bold;
	}
	.notify_sent_log_meta_field input[type="text"],
	.notify_sent_log_meta_field select{
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
<div class="notify_sent_log_meta_field">
	<label for="notify_sent_success"><?php echo esc_html( __( 'Notification Sent Success', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $notify_sent_success ); ?></p>
</div>
<hr />
<div class="notify_sent_log_meta_field">
	<label for="notify_sent_types"><?php echo esc_html( __( 'Notification Sent Type', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $notify_sent_types ); ?></p>
</div>
<hr />
<div class="notify_sent_log_meta_field">
	<label for="notify_sent_references"><?php echo esc_html( __( 'Notification Sent Reference', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $notify_sent_references ); ?></p>
</div>
<hr />
<div class="notify_sent_log_meta_field">
	<label for="notify_sent_message"><?php echo esc_html( __( 'Notification Sent Message', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $notify_sent_message ); ?></p>
</div>
