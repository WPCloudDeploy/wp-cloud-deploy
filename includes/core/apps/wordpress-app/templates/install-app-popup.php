<?php
/**
 * Install App Popup
 *
 * @package wpcd
 */

?>

<!-- Before logo/header action hook -->
<?php do_action( "wpcd_{$this->get_app_name()}_install_app_popup_before_header", $server_id, $user_id ); ?>

<?php require wpcd_path . 'includes/core/apps/wordpress-app/templates/header-popup.php'; ?>

<!-- After logo/header action hook -->
<?php do_action( "wpcd_{$this->get_app_name()}_install_app_popup_after_header", $server_id, $user_id ); ?>


<?php
/* Do not show screen if wpsite licenses exceeded */
if ( WPCD_License::show_license_tab() ) {
	if ( ! WPCD_License::check_wpsite_limit() ) {
		?>
		<div class="wpcd-install-app-container wpcd-popup">
			<div class="wpcd-no-install-wp-permission">
			<?php echo esc_html( __( 'Oops! Unfortunately you have either reached or exceeded the number of WordPress sites allowed by your license!', 'wpcd' ) ); ?></div>
			</div>
		</div>			
		<?php
		return;
	}
}
/* End do not show screen if wpsite licenses exceeded */
?>

<?php
	$user_id     = get_current_user_id();
	$post_author = get_post( $server_id )->post_author;

if ( ! wpcd_user_can( $user_id, 'add_app_wpapp', $server_id ) && $post_author != $user_id && ! wpcd_is_admin() ) {
	?>
	<div class="wpcd-install-app-container wpcd-popup">
		<div class="wpcd-no-install-wp-permission">
		<?php echo esc_html( __( 'You don\'t have permission to Install WordPress on this server.', 'wpcd' ) ); ?>
		</div>
	</div>
	<?php
} else {
	?>

<div class="wpcd-install-app-container wpcd-popup">

	<!-- Before title action hook -->
	<?php do_action( "wpcd_{$this->get_app_name()}_install_app_popup_before_title", $server_id, $user_id ); ?>

	<div class="wpcd-action-title-wrap">
		<div class="wpcd-action-title"><?php echo esc_html( sprintf( __( 'Install A New WordPress Site On Server %1$s / %2$s @ %3$s', 'wpcd' ), $server_name, $ipv4, $server_provider ) ); ?></div>
	</div>

	<!-- After title action hook -->
	<?php do_action( "wpcd_{$this->get_app_name()}_install_app_popup_before_title", $server_id, $user_id ); ?>

	<div class="wpcd-create-popup-grid-wrap">
		<div class="wpcd-create-popup-fields-wrap">

		<!-- Before form open action hook -->
		<?php do_action( "wpcd_{$this->get_app_name()}_install_app_popup_before_form_open", $server_id, $user_id ); ?>		

			<form id="wpcd-install">

			<!-- After form open action hook -->
			<?php do_action( "wpcd_{$this->get_app_name()}_install_app_popup_after_form_open", $server_id, $user_id ); ?>

				<input type="hidden" name="wpcd_app_type" value="wordpress">

			<?php $other_fields_error = __( 'The following characters are invalid in all field:  \' " ; \ | < > ` @ $ ( ) / and spaces, carriage returns, linefeeds.', 'wpcd' ); ?>

				<div class="wpcd-create-popup-label-wrap"><label class="wpcd-create-popup-label" for="wp_domain"> <?php echo esc_html( __( 'Domain', 'wpcd' ) ); ?>  </label></div>
				<div class="wpcd-create-popup-input-wrap">
				<?php if ( empty( $temp_sub_domain ) ) { ?>
						<input type="text" maxlength="32" name="wp_domain" placeholder="<?php echo esc_html( __( 'Domain without www or http - eg: mydomain.com', 'wpcd' ) ); ?>" size="50">
					<?php } else { ?>
						<input type="text" maxlength="32" name="wp_domain" value=<?php echo esc_html( "$temp_sub_domain" ); ?> placeholder="<?php echo esc_attr( __( 'Domain without www or http - eg: mydomain.com', 'wpcd' ) ); ?>" size="50">
					<?php } ?>
					<span class="error wpcd-domain-error"><?php echo esc_html( __( 'Please enter a valid domain name or IP Address.', 'wpcd' ) ); ?></span>
					<div class='wp_field_error wp_domain_error_msg'><?php echo esc_html( $other_fields_error ); ?></div>
				</div>

				<div class="wpcd-create-popup-label-wrap"><label class="wpcd-create-popup-label" for="wp_locale"> <?php echo esc_html( __( 'Language', 'wpcd' ) ); ?>  </label></div>
				<div class="wpcd-create-popup-input-wrap">
				<?php
				wp_dropdown_languages(
					array(
						'name'     => 'wp_locale',
						'id'       => 'wp_locale',
						'echo'     => true,
						'selected' => 'en_US',
					)
				);
				?>
				</div>

				<div class="wpcd-create-popup-label-wrap"><label class="wpcd-create-popup-label" for="wp_user"> <?php echo esc_html( __( 'Admin Username', 'wpcd' ) ); ?>  </label></div>
				<div class="wpcd-create-popup-input-wrap">
					<input type="text" name="wp_user" placeholder="<?php echo esc_html( __( 'Admin Username', 'wpcd' ) ); ?>">
					<div class='wp_field_error wp_username_error_msg'><?php echo esc_html( $other_fields_error ); ?></div>
				</div>

				<div class="wpcd-create-popup-label-wrap"><label class="wpcd-create-popup-label" for="wp_password"> <?php echo esc_html( __( 'Admin Password', 'wpcd' ) ); ?>  </label></div>
				<div class="wpcd-create-popup-input-wrap">
					<input type="password" name="wp_password" placeholder="<?php esc_html( __( 'Admin Password', 'wpcd' ) ); ?>">
					<div class='wp_field_error wp_password_error_msg'><?php echo esc_html( __( 'The following characters are invalid in the password field:  \' " ; \ | < > ` (single-quote, double-quote, semi-colon, backslash, pipe, angled-brackets, backtics, spaces, carriage returns, linefeeds.)', 'wpcd' ) ); ?></div>
				</div>

				<div class="wpcd-create-popup-label-wrap"><label class="wpcd-create-popup-label" for="wp_email"> <?php echo esc_html( __( 'Admin Email Address', 'wpcd' ) ); ?>  </label></div>
				<div class="wpcd-create-popup-input-wrap">
					<input type="email" name="wp_email" placeholder="<?php echo esc_attr( __( 'Email', 'wpcd' ) ); ?>" >
					<div class='wp_field_error wp_check_email_error_msg'><?php echo esc_html( __( 'Please enter a valid email address.', 'wpcd' ) ); ?></div>
					<div class='wp_field_error wp_email_error_msg'><?php echo esc_html( __( 'The following characters are invalid in email field:  \' " ; \ | < > ` and spaces, carriage returns, linefeeds.', 'wpcd' ) ); ?></div>
				</div>				
				<!-- <input type="text" name="wp_version" placeholder="<?php echo esc_attr( __( 'Version', 'wpcd' ) ); ?>" value="latest"> -->
				<div class="wpcd-create-popup-label-wrap"><label class="wpcd-create-popup-label" for="wp_version"> <?php echo esc_html( __( 'WordPress Version', 'wpcd' ) ); ?>  </label></div>
				<div class="wpcd-create-popup-input-wrap wpcd-create-popup-input-wp-version-select2-wrap">
				<?php
					$version_options = array( 'latest', '5.8.2', '5.7.3', '5.6.5', '5.5.6', '5.4.7', '5.3.8', '5.2.12', '5.1.10', '5.0.13', '4.9.18', '4.8.17', '4.7.21' );
				?>
					<select name="wp_version" id="wpcd-wp-version" style="width: 150px;">
					<?php
					foreach ( $version_options as $version_option ) {
						?>
								<option value="<?php echo esc_attr( $version_option ); ?>"><?php echo esc_html( $version_option ); ?></option>
							<?php
					}
					?>
					</select>
				</div>

				<!-- Before install button action hook -->
				<?php do_action( "wpcd_{$this->get_app_name()}_install_app_popup_before_install_button", $server_id, $user_id ); ?>	

				<div class="wpcd-create-popup-input-wrap">
					<button class="wpcd-install-button wpcd-install-app"><?php echo esc_html( __( 'Install', 'wpcd' ) ); ?></button>
				</div>

				<!-- Before form close action hook -->
				<?php do_action( "wpcd_{$this->get_app_name()}_install_app_popup_before_form_close", $server_id, $user_id ); ?>

			</form>

			<!-- After form close action hook -->
			<?php do_action( "wpcd_{$this->get_app_name()}_install_app_popup_after_form_close", $server_id, $user_id ); ?>			

			<div class="wpcd-action-instructions"><?php echo esc_html( __( 'Fill out the fields above and then click the INSTALL button to start creating a WordPress site on your server.', 'wpcd' ) ); ?></div>
		</div>

		<!-- Before console wrap action hook -->
		<?php do_action( "wpcd_{$this->get_app_name()}_install_app_popup_before_console_wrap", $server_id, $user_id ); ?>

		<div class = "wpcd-create-popup-console-wrap">
			<?php include wpcd_path . 'includes/core/apps/wordpress-app/templates/log-console.php'; ?>
		</div>

		<!-- After console wrap action hook -->
		<?php do_action( "wpcd_{$this->get_app_name()}_install_app_popup_after_console_wrap", $server_id, $user_id ); ?>

	</div>
</div>
<?php } ?>
