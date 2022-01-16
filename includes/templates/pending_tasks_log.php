<style>
	/*******************************************************************/
	/* CSS for PENDING TASKS LOG post meta                             */
	/*******************************************************************/
	.pending_tasks_log_meta_field{
		margin-bottom: 10px;
	}
	.pending_tasks_log_meta_field label{
		width: 150px;
		display: inline-block;
		font-weight: bold;
	}
	.pending_tasks_log_meta_field input[type="text"],
	.pending_tasks_log_meta_field select{
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
<div class="pending_tasks_log_meta_field">
	<label for="pending_task_type"><?php echo esc_html( __( 'Pending Task Type', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $pending_task_type ); ?></p>
</div>
<hr />
<div class="pending_tasks_log_meta_field">
	<label for="pending_task_key"><?php echo esc_html( __( 'Key', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $pending_task_key ); ?></p>
</div>
<hr />
<div class="pending_tasks_log_meta_field">
	<label for="pending_task_state"><?php echo esc_html( __( 'State', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $pending_task_state ); ?></p>
</div>
<hr />
<div class="pending_tasks_log_meta_field">
	<label for="pending_task_attempts"><?php echo esc_html( __( 'Attempts To Complete', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $pending_task_attempts ); ?></p>
</div>
<hr />
<div class="pending_tasks_log_meta_field">
	<label for="pending_task_reference"><?php echo esc_html( __( 'Reference', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $pending_task_reference ); ?></p>
</div>
<hr />
<div class="pending_tasks_log_meta_field">
	<label for="pending_task_comment"><?php echo esc_html( __( 'Comment', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $pending_task_comment ); ?></p>
</div>
<hr />
<div class="pending_tasks_log_meta_field">
	<label for="pending_task_history"><?php echo esc_html( __( 'History / Last error Message', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $pending_task_history ); ?></p>
</div>
<div class="pending_tasks_log_meta_field">
	<label for="pending_task_messages"><?php echo esc_html( __( 'Messages', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $pending_task_messages ); ?></p>
</div>
<hr />
<div class="pending_tasks_log_meta_field">
	<label for="pending_task_start_date"><?php echo esc_html( __( 'Date Started', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $pending_task_start_date ); ?></p>
</div>
<hr />
<div class="pending_tasks_log_meta_field">
	<label for="pending_task_complete_date"><?php echo esc_html( __( 'Date Completed', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $pending_task_complete_date ); ?></p>
</div>
<hr />
<div class="pending_tasks_log_meta_field">
	<label for="pending_task_details"><?php echo esc_html( __( 'Related Data', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( print_r( $pending_task_details, true ) ); ?></p>
</div>
<hr />
<div class="pending_tasks_log_meta_field">
	<label for="parent_post_id"><?php echo esc_html( __( 'Log Owner or Parent ID', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $parent_post_id ); ?></p>
</div>
<div class="pending_tasks_log_meta_field">
	<label for="pending_task_parent_post_type"><?php echo esc_html( __( 'Parent Post Type', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $pending_task_parent_post_type ); ?></p>
</div>
<div class="pending_tasks_log_meta_field">
	<label for="pending_task_associated_server_id"><?php echo esc_html( __( 'Associated Server ID', 'wpcd' ) ); ?></label>
	<p><?php echo esc_html( $pending_task_associated_server_id ); ?></p>
</div>
