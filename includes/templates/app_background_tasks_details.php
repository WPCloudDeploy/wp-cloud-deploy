<style>
	/* No style defined here - styles should already be loaded by the main application template - see templates/app_details.php */
</style>

<?php
/**
 * Add nonce for security and authentication.
 *
 * @package wpcd
 */

wp_nonce_field( 'wpcd_app_nonce_meta_action', 'app_meta' );
?>
<div class="wpcd_app_meta_warning">
	<?php echo esc_html( __( 'Do not change the data below unless instructed by technical support or you know what you are doing! Incorrect data in these fields can break the link to your server and applications at your cloud provider!', 'wpcd' ) ); ?>
</div>
<div class="wpcd_app_metabox_app_name">
	<?php
		/* translators: %s app name */
		echo sprintf( esc_html( __( 'APP Name: %s', 'wpcd' ) ), esc_html( $this->get_app_name() ) );
	?>
	<br />	
</div>

<div class="wpcd_app_meta_field">
	<label for="wpcd_app_action_status"><?php echo esc_html( __( 'Action Status', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_app_action_status" id="wpcd_app_action_status" value="<?php echo esc_html( $wpcd_app_action_status ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'If a background action is in process for this app, this will not be blank. Generally set to in-progress if something is being processed for this app.', 'wpcd' ) ); ?></small>
</div>
<div class="wpcd_app_meta_field">
	<label for="wpcd_app_action"><?php echo esc_html( __( 'Action', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_app_action" id="wpcd_app_action" value="<?php echo esc_html( $wpcd_app_action ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'If a background action is in process for this app, this will not be blank. It will indicate what action needs to be processed next - eg: fetch-logs-from-server', 'wpcd' ) ); ?></small>	
</div>
<div class="wpcd_app_meta_field">
	<label for="wpcd_app_action_args"><?php echo esc_html( __( 'Action Arguments', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_app_action_args" id="wpcd_app_action_args" value="<?php echo esc_html( $wpcd_app_action_args ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'If a background action is in process for this app, this will not be blank. It will show the arguments for the action - this is a serialized field so if you update it for some reason, separate the elements with commas.', 'wpcd' ) ); ?></small>	
</div>
<div class="wpcd_app_meta_field">
	<label for="wpcd_app_command_mutex"><?php echo esc_html( __( 'Mutex', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_app_command_mutex" id="wpcd_app_command_mutex" value="<?php echo esc_html( $wpcd_app_command_mutex ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'If a background action is in process for this app, this will not be blank. It is used to make sure other background tasks are not initiated while one is being currently processed.  Example of data here is <em>backup-run-manual:cf08.wpvix.com:873</em>', 'wpcd' ) ); ?></small>	
</div>
<div class="wpcd_app_meta_field">
	<label for="wpcd_temp_log_id"><?php echo esc_html( __( 'Temp Log Id', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_temp_log_id" id="wpcd_temp_log_id" value="<?php echo esc_html( $wpcd_temp_log_id ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'For some background processes we retrieve the log files from the server in peices. This field helps to keep that straight.', 'wpcd' ) ); ?></small>	
</div>
