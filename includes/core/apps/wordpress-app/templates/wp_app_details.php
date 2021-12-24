<?php
/**
 * Template for showing data in the metabox when viewing the APP CPT in wp-admin
 *
 * @package wpcd
 */

?>
<style>
	/*******************************************************************/
	/* CSS for WPAPP post meta                                           */
	/*******************************************************************/
	.wpapp_meta_field{
		margin-bottom: 10px;
	}
	.wpapp_meta_field label{
		width: 150px;
		display: inline-block;
	}
	.wpapp_meta_field input[type="text"],
	.wpapp_meta_field select{
		width: 60%;
	}
</style>
<?php
// Add nonce for security and authentication.
wp_nonce_field( 'wpcd_wp_app_nonce_meta_action', 'wpapp_meta' );
?>
<div class="wpcd_app_meta_warning">
	<?php echo esc_html( __( 'The data shown here is the details of the site at the time it was initially installed. Anything you change here does NOT have any effect on the site. However, it is a good idea to update these values if they have changed on your site.', 'wpcd' ) ); ?>
</div>
<div class="wpapp_meta_field">
	<label for="wpcd_wpapp_domain"><?php echo esc_html( __( 'Domain', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_wpapp_domain" id="wpcd_wpapp_domain" value="<?php echo esc_attr( $wpcd_wpapp_domain ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'Be very very careful about changing this - please consider using our built-in change-domain function instead!  If you do change this here, you migh also need to update the post title field in the APPLICATIONS DETAIL metabox to avoid confusion.', 'wpcd' ) ); ?></small>	
</div>
<div class="wpapp_meta_field">
	<label for="wpcd_wpapp_userid"><?php echo esc_html( __( 'Initial Admin User ID', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_wpapp_userid" id="wpcd_wpapp_userid" value="<?php echo esc_attr( $wpcd_wpapp_userid ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'You can change this to match the value you set after the initial install.', 'wpcd' ) ); ?></small>
</div>
<div class="wpapp_meta_field">
	<label for="wpcd_wpapp_email"><?php echo esc_html( __( 'Initial Email Address', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_wpapp_email" id="wpcd_wpapp_email" value="<?php echo esc_attr( $wpcd_wpapp_email ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'You can change this to match the value you set after the initial install.', 'wpcd' ) ); ?></small>
</div>
<div class="wpapp_meta_field">
	<label for="wpcd_wpapp_password"><?php echo esc_html( __( 'Initial Password', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_wpapp_password" id="wpcd_wpapp_password" value="<?php echo esc_attr( $wpcd_wpapp_password ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'You can change this to match the value you set after the initial install.', 'wpcd' ) ); ?></small>
</div>
<div class="wpapp_meta_field">
	<label for="wpcd_wpapp_initial_version"><?php echo esc_html( __( 'Initial Installed Version', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_wpapp_initial_version" id="wpcd_wpapp_initial_version" value="<?php echo esc_attr( $wpcd_wpapp_initial_version ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'This is the initial version of WordPress that was installed - it might have been upgraded after that. You can set the current version here to match your installed version.', 'wpcd' ) ); ?></small>
</div>
<div class="wpapp_meta_field">
	<label for="wpcd_wpapp_staging_domain"><?php echo esc_html( __( 'Companion Staging Domain', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_wpapp_staging_domain" id="wpcd_wpapp_staging_domain" value="<?php echo esc_attr( $wpcd_wpapp_staging_domain ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'If a staging domain was created for this site, this is the domain name for it.', 'wpcd' ) ); ?></small>
</div>
<div class="wpapp_meta_field">
	<label for="wpcd_wpapp_staging_domain_id"><?php echo esc_html( __( 'Companion Staging Domain Post ID', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_wpapp_staging_domain_id" id="wpcd_wpapp_staging_domain_id" value="<?php echo esc_attr( $wpcd_wpapp_staging_domain_id ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'If a staging domain was created for this site, this is the post ID where its located.', 'wpcd' ) ); ?></small>
</div>
<div class="wpapp_meta_field">
	<label for="wpcd_wpapp_wc_order_id"><?php echo esc_html( __( 'WooCommerce Order ID', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_wpapp_wc_order_id" id="wpcd_wpapp_wc_order_id" value="<?php echo esc_attr( $wpcd_wpapp_wc_order_id ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'If this site was purchased and created by the WooCommerce store then this field will show the WooCommerce order id.', 'wpcd' ) ); ?></small>
</div>
<div class="wpapp_meta_field">
	<label for="wpcd_wpapp_wc_subscription_id"><?php echo esc_html( __( 'WooCommerce Subscription ID', 'wpcd' ) ); ?></label>
	<input type="text" name="wpcd_wpapp_wc_subscription_id" id="wpcd_wpapp_wc_subscription_id" value="<?php echo esc_attr( $wpcd_wpapp_wc_subscription_id ); ?>" />
	<br />
	<small><?php echo esc_html( __( 'If this site was purchased and created by the WooCommerce store then this field will show the WooCommerce subscription id.', 'wpcd' ) ); ?></small>
</div>
