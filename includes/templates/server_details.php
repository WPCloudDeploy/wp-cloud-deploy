<style>
	/*******************************************************************/
	/* CSS for VPN post meta                                           */
	/*******************************************************************/
	.wpcd_server_meta_warning {
		padding-top: 1em;
		padding-bottom: 1em;
		font-weight: bold;
		font-size: 1.2 em;
		color: #d50000;
	}
	.wpcd_server_meta_field {
		margin-bottom: 10px;
	}
	.wpcd_server_meta_field label {
		width: 25%;
		display: inline-block;
	}
	label.wpcd_server_meta_field_readonly_field_title {
		width: 100%;
		font-weight: bold;
	}
	.wpcd_server_meta_field_readonly label {
		margin-top: 0.2em;
	}
	.wpcd_server_meta_field input[type="text"],
	.wpcd_server_meta_field select{
		width: 60%;
	}
</style>
<?php
/**
 * Add nonce for security and authentication.
 *
 * @package wpcd
 */

wp_nonce_field( 'wpcd_server_nonce_meta_action', 'vpn_meta' );
?>
<div class="wpcd_server_meta_warning">
	<?php echo esc_html( __( 'Do not change the data below unless instructed by technical support or you know what you are doing! Incorrect data in these fields can break the link to your server at your cloud provider!', 'wpcd' ) ); ?>
</div>

<div class="wpcd_server_meta_field">
	<label for="wpcd_server_name"><?php echo esc_html( __( 'Name', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_name" id="wpcd_server_name" value="<?php echo esc_html( $wpcd_server_name ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_title"><?php echo esc_html( __( 'Post Title', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_title" id="wpcd_server_title" value="<?php echo esc_html( $wpcd_server_title ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_provider"><?php echo esc_html( __( 'Provider', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_provider" id="wpcd_server_provider" value="<?php echo esc_html( $wpcd_server_provider ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_provider_instance_id"><?php echo esc_html( __( 'Provider Instance ID', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_provider_instance_id" id="wpcd_server_provider_instance_id" value="<?php echo esc_html( $wpcd_server_provider_instance_id ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_region"><?php echo esc_html( __( 'Region', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_region" id="wpcd_server_region" value="<?php echo esc_html( $wpcd_server_region ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_size"><?php echo esc_html( __( 'Size', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_size" id="wpcd_server_size" value="<?php echo esc_html( $wpcd_server_size ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_size_raw"><?php echo esc_html( __( 'Size (Raw or Provider Internal Size ID)', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_size_raw" id="wpcd_server_size_raw" value="<?php echo esc_html( $wpcd_server_size_raw ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_ipv4"><?php echo esc_html( __( 'IPv4 Address', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_ipv4" id="wpcd_server_ipv4" value="<?php echo esc_html( $wpcd_server_ipv4 ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_scripts_version"><?php echo esc_html( __( 'Version of app scripts used for this server', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_scripts_version" id="wpcd_server_scripts_version" value="<?php echo esc_html( $wpcd_server_scripts_version ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_init"><?php echo esc_html( __( 'Server init flag', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_init" id="wpcd_server_init" value="<?php echo esc_html( $wpcd_server_init ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_initial_app_name"><?php echo esc_html( __( 'Initial App Name', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_initial_app_name" id="wpcd_server_initial_app_name" value="<?php echo esc_html( $wpcd_server_initial_app_name ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_plugin_initial_version"><?php echo esc_html( __( 'Initial Plugin Version', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_plugin_initial_version" id="wpcd_server_plugin_initial_version" value="<?php echo esc_html( $wpcd_server_plugin_initial_version ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_plugin_updated_version"><?php echo esc_html( __( 'Last Updated Plugin Version', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_plugin_updated_version" id="wpcd_server_plugin_updated_version" value="<?php echo esc_html( $wpcd_server_plugin_updated_version ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_server-type"><?php echo esc_html( __( 'Server Type', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_server-type" id="wpcd_server_server-type" value="<?php echo esc_html( $wpcd_server_server_type ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_wc_order_id"><?php echo esc_html( __( 'Woocommerce Order ID', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_wc_order_id" id="wpcd_server_wc_order_id" value="<?php echo esc_html( $wpcd_server_wc_order_id ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_created"><?php echo esc_html( __( 'Create Date', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_created" id="wpcd_server_created" value="<?php echo esc_html( $wpcd_server_created ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_parent_post_id"><?php echo esc_html( __( 'Parent ID', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_parent_post_id" id="wpcd_server_parent_post_id" value="<?php echo esc_html( $wpcd_server_parent_post_id ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_after_create_action_app_id"><?php echo esc_html( __( 'After Server Create Action APP ID', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_after_create_action_app_id" id="wpcd_server_after_create_action_app_id" value="<?php echo esc_html( $wpcd_server_after_create_action_app_id ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_action_status"><?php echo esc_html( __( 'Action Status', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_action_status" id="wpcd_server_action_status" value="<?php echo esc_html( $wpcd_server_action_status ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_command_mutex"><?php echo esc_html( __( 'Command Mutex', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_command_mutex" id="wpcd_server_command_mutex" value="<?php echo esc_html( $wpcd_server_command_mutex ); ?>" />
</div>
<div class="wpcd_server_meta_field">
	<label for="wpcd_server_last_upgrade_done"><?php echo esc_html( __( 'Last Upgrade Version', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_server_last_upgrade_done" id="wpcd_server_last_upgrade_done" value="<?php echo esc_html( $wpcd_server_last_upgrade_done ); ?>" />
</div>

<hr />

<!-- These fields are array/serialized read-only fields -->
<!-- Do not wrap them in esc_html and esc_url since they contain valid html and sanitization was done earlier. -->
<div class="wpcd_server_meta_field wpcd_server_meta_field_readonly">
	<label class='wpcd_server_meta_field_readonly_field_title'><?php echo esc_html( __( 'Last Deferred Action Source', 'wpcd' ) ); ?></label>
	<br />
	<label>
	<?php
	$allowed_html = array(
		'br' => array(),
	);
	echo wp_kses( $wpcd_server_last_deferred_action_source, $allowed_html );
	?>
	</label>
</div>
<div class="wpcd_server_meta_field wpcd_server_meta_field_readonly">
	<label class='wpcd_server_meta_field_readonly_field_title'><?php echo esc_html( __( 'Server Actions', 'wpcd' ) ); ?></label>
	<br />
	<label><?php echo wp_kses( $wpcd_server_actions, $allowed_html ); ?></label>
</div>
<!-- End read-only fields -->
