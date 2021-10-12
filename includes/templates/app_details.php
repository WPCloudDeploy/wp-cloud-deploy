<style>
	/*******************************************************************/
	/* CSS for APP post meta                                           */
	/*******************************************************************/
	.wpcd_app_meta_warning {
		padding-top: 1em;
		padding-bottom: 1em;
		font-weight: bold;
		font-size: 1.2 em;
		color: #d50000;
	}		
	.wpcd_app_meta_field{
		margin-bottom: 10px;
	}
	.wpcd_app_meta_field label{
		width: 150px;
		display: inline-block;
	}
	.wpcd_app_meta_field input[type="text"],
	.wpcd_app_meta_field select{
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
<div class="wpcd_app_meta_warning">
	<?php echo esc_html( __( 'Do not change the data below unless instructed by technical support or you know what you are doing! Incorrect data in these fields can break the link to your server and applications at your cloud provider!', 'wpcd' ) ); ?>
</div>
<div class="wpcd_app_meta_field">
	<label for="app_post_title"><?php echo esc_html( __( 'Post Title', 'wpcd' ) ); ?></label>
	<input type="text" name="app_post_title" id="app_post_title" value="<?php echo esc_html( $app_post_title ); ?>" />
</div>
<div class="wpcd_app_meta_field">
	<label for="app_type"><?php echo esc_html( __( 'App Type', 'wpcd' ) ); ?></label>
	<input type="text" name="app_type" id="app_type" value="<?php echo esc_html( $app_type ); ?>" />
</div>
<div class="wpcd_app_meta_field">
	<label for="server_name"><?php echo esc_html( __( 'Server Name', 'wpcd' ) ); ?></label>
	<?php echo esc_html( $server_name ); ?>
</div>
<div class="wpcd_app_meta_field">
	<label for="server_provider"><?php echo esc_html( __( 'Provider', 'wpcd' ) ); ?></label>
	<?php echo esc_html( $server_provider ); ?>
</div>
<div class="wpcd_app_meta_field">
	<label for="ipv4"><?php echo esc_html( __( 'IPv4', 'wpcd' ) ); ?></label>
	<?php echo esc_html( $ipv4 ); ?>
</div>

<div class="wpcd_app_meta_field">
	<label for="parent_post_id"><?php echo esc_html( __( 'Parent ID', 'wpcd' ) ); ?></label>
	<input type="text" name="parent_post_id" id="parent_post_id" value="<?php echo esc_html( $parent_post_id ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'This is the post id of the server record that identifies where this application is installed. Usually it is a link to the server post data.', 'wpcd' ) ); ?></small>	
</div>
