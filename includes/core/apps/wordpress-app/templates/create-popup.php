<?php
/**
 * Create Popup
 *
 * @package wpcd
 */

?>
<div class="wpcd-install-popup-container">
<!-- Before Logo/header action hook -->
<?php do_action( "wpcd_{$this->get_app_name()}_create_popup_before_header", $providers, $oslist ); ?>				

<?php require wpcd_path . 'includes/core/apps/wordpress-app/templates/header-popup.php'; ?>

<!-- After Logo/header action hook -->
<?php do_action( "wpcd_{$this->get_app_name()}_create_popup_after_header", $providers, $oslist ); ?>				


<div class="wpcd-pre-install wpcd-popup">

	<?php
	/* Do not show screen if server licenses exceeded */
	if ( WPCD_License::show_license_tab() ) {
		if ( ! WPCD_License::check_server_limit() ) {
			?>
			<div class="wpcd-no-providers"><?php echo esc_html( __( 'Oops! Unfortunately you have either reached or exceeded the number of servers allowed by your license!', 'wpcd' ) ); ?></div>
			<?php
			return;
		}
	}
	/* End do not show screen if server licenses exceeded */
	?>

	<?php
	/* Show error message if there are no providers! */
	if ( empty( $providers ) ) {
		?>
		<div class="wpcd-no-providers"><?php echo esc_html( __( 'Oops! Unfortunately it seems as if you have not configured a server provider yet. You need at least one server provider before you can deploy a server. You can do this on the settings screen.', 'wpcd' ) ); ?></div>
		<?php
	} else {
		?>

		<!-- Before title action hook -->
		<?php do_action( "wpcd_{$this->get_app_name()}_create_popup_before_title", $providers, $oslist ); ?>

		<div class="wpcd-action-title-wrap">
			<div class="wpcd-action-title"><?php echo esc_html( __( 'Deploy A New WordPress Server', 'wpcd' ) ); ?></div>
		</div>

		<!-- After title action hook -->
		<?php do_action( "wpcd_{$this->get_app_name()}_create_popup_after_title", $providers, $oslist ); ?>

		<div class="wpcd-create-popup-grid-wrap">
			<div class="wpcd-create-popup-fields-wrap">

				<!-- Before form open action hook -->
				<?php do_action( "wpcd_{$this->get_app_name()}_create_popup_before_form_open", $providers, $oslist ); ?>

				<form id="wpcd-install">				
					<!-- After form open action hook -->
					<?php do_action( "wpcd_{$this->get_app_name()}_create_popup_after_form_open", $providers, $oslist ); ?>

					<!-- Webserver type dropdown -->
					<?php if ( DEFINED( 'WPCD_WPAPP_SHOW_WEBSERVER_OPTIONS' ) && WPCD_WPAPP_SHOW_WEBSERVER_OPTIONS ) { ?>
					<div class="wpcd-create-popup-label-wrap"><label class="wpcd-create-popup-label" for="webserver-type"> <?php echo esc_html( __( 'Web Server', 'wpcd' ) ); ?>  </label></div>
					<div class="wpcd-create-popup-input-wrap">
						<select name="webserver-type" class="wpcd_app_webserver">
						<?php
						$default_web_server = wpcd_get_option( 'wordpress_app_default_webserver' );
						if ( empty( $default_web_server ) ) {
							$default_web_server = 'nginx';
						}
						foreach ( $webserver_list as $webserver => $webserver_name ) {
							$is_selected_webserver = '';
							if ( $webserver === $default_web_server ) {
								$is_selected_webserver = 'selected';
							}
							?>
								<option value="<?php echo esc_attr( $webserver ); ?>" <?php echo esc_attr( $is_selected_webserver ); ?> ><?php echo esc_html( $webserver_name ); ?></option>
								<?php
						}
						?>
						</select>
					</div>
					<?php } ?>

					<!-- OS dropdown -->
					<div class="wpcd-create-popup-label-wrap"><label class="wpcd-create-popup-label" for="os"> <?php echo esc_html( __( 'OS', 'wpcd' ) ); ?>  </label></div>
					<div class="wpcd-create-popup-input-wrap">
						<select name="os" class="wpcd_app_os">
						<?php
						$default_os = wpcd_get_option( 'wordpress_app_default_os' );
						if ( empty( $default_os ) ) {
							$default_os = 'ubuntu2004lts';
						}
						foreach ( $oslist as $os => $osname ) {
							$is_selected_os = '';
							if ( $os == $default_os ) {
								$is_selected_os = 'selected';
							}
							?>
								<option value="<?php echo esc_attr( $os ); ?>" <?php echo esc_attr( $is_selected_os ); ?> ><?php echo esc_html( $osname ); ?></option>
								<?php
						}
						?>
						</select>
					</div>

					<!-- Provider dropdown -->
					<div class="wpcd-create-popup-label-wrap"><label class="wpcd-create-popup-label" for="provider"> <?php echo esc_html( __( 'Provider', 'wpcd' ) ); ?>  </label></div>
					<div class="wpcd-create-popup-input-wrap">
						<select name="provider" class="wpcd_app_provider">
						<?php
						foreach ( $provider_regions['providers'] as $slug => $name ) {
							?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
							<?php
						}
						?>
						</select>
					</div>

					<!-- Region dropdown -->
					<div class="wpcd-create-popup-label-wrap"><label class="wpcd-create-popup-label" for="region"> <?php echo esc_html( __( 'Region', 'wpcd' ) ); ?>  </label></div>
					<div class="wpcd-create-popup-input-wrap">
						<select name="region" class="wpcd_app_region">
						<?php
						foreach ( $provider_regions['regions'] as $slug => $name ) {
							?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
							<?php
						}
						?>
						</select>
					</div>

					<!-- Server Size dropdown -->
					<div class="wpcd-create-popup-label-wrap"><label class="wpcd-create-popup-label" for="size"> <?php echo esc_html( __( 'Size', 'wpcd' ) ); ?>  </label></div>
					<div class="wpcd-create-popup-input-wrap">
						<select name="size" class="wpcd_app_size">
						<?php
						foreach ( $provider_regions['sizes'] as $slug => $name ) {
							?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
							<?php
						}
						?>
						</select>
					</div>

					<div class="wpcd-create-popup-label-wrap wpcd-create-popup-label-wrap-for-server-type"><label class="wpcd-create-popup-label" for="server_type"> <?php echo esc_html( __( 'Server Type', 'wpcd' ) ); ?>  </label></div>
					<div class="wpcd-create-popup-input-wrap">
						<select name="server_type" class="server_type">
							<option value="wordpress-app">WordPress</option>
							<option value="something-else">Something Else</option>
						</select>		
					</div>

					<div class="wpcd-create-popup-label-wrap"><label class="wpcd-create-popup-label" for="script_version"> <?php echo esc_html( __( 'Script Version', 'wpcd' ) ); ?>  </label></div>			
					<div class="wpcd-create-popup-input-wrap">			
						<select name="script_version" class="wpcd_app_script_version">
						<?php
						foreach ( $dir_list as $dir_name ) {
							?>
							<option value="<?php echo $dir_name; ?>" <?php selected( $scripts_version, $dir_name, true ); ?>><?php echo esc_html( $dir_name ); ?></option>
							<?php
						}
						?>
						</select>
					</div>

					<div class="wpcd-create-popup-label-wrap"><label class="wpcd-create-popup-label" for="name"> <?php echo esc_html( __( 'Name of Server', 'wpcd' ) ); ?>  </label></div>			
					<div class="wpcd-create-popup-input-wrap">			
						<input type="text" name="name" placeholder="<?php echo esc_html( __( 'Name of server', 'wpcd' ) ); ?>" class="wpcd_server_name">
						<div class='wp_field_error wp_server_name_error_msg'><?php _e( 'The following characters are invalid in all fields:  \' " ; \ | < > ` @ $ & ( ) / and spaces, carriage returns, linefeeds.', 'wpcd' ); ?></div>
					</div>

					<input type="hidden" id="wp_user_id" name="wp_user_id" value="<?php echo esc_attr( get_current_user_id() ); ?>" >

					<!-- Before install button action hook -->
					<?php do_action( "wpcd_{$this->get_app_name()}_create_popup_before_install_button", $providers, $oslist ); ?>

					<div class="wpcd-create-popup-input-wrap">
						<button class="wpcd-install-button wpcd-install-server"><?php echo esc_html( __( 'Deploy', 'wpcd' ) ); ?></button>
					</div>

					<!-- Before form close action hook -->
					<?php do_action( "wpcd_{$this->get_app_name()}_create_popup_before_form_close", $providers, $oslist ); ?>

				</form>

				<!-- After form close action hook -->
				<?php do_action( "wpcd_{$this->get_app_name()}_create_popup_after_form_close", $providers, $oslist ); ?>

				<div class="wpcd-action-instructions wpcd_custom_tooltip">
					<?php echo esc_html( __( 'Instructions', 'wpcd' ) ); ?><span class="dashicons dashicons-info"></span>
					<span class="wpcd_custom_tooltiptext">
						<?php echo esc_html( __( 'Fill out the fields above and then click the DEPLOY button to start the provisioning the server.', 'wpcd' ) ); ?><br />
						<?php echo esc_html( __( 'After this step is complete and the server has been deployed you will be able to install one or more WordPress sites from the ALL CLOUD SERVERS list.', 'wpcd' ) ); ?><br /><br />
						<?php echo esc_html( __( 'Instructions and notes:', 'wpcd' ) ); ?><br />
						<?php echo esc_html( __( '1. Do not change the version number of the scripts unless advised to by support. The default version of scripts is v1.', 'wpcd' ) ); ?><br />
						<?php echo esc_html( __( '2. If you leave the server name blank, a random numeric name will be generated for you.', 'wpcd' ) ); ?><br /><br />
						<?php echo esc_html( __( 'When the server has been deployed and all software installed you will see a message in the black terminal window that states "Installation Completed". You will also get a popup confirmation.', 'wpcd' ) ); ?>
					</span>
				</div>	
			</div>

			<!-- Before console wrap action hook -->
			<?php do_action( "wpcd_{$this->get_app_name()}_create_popup_before_console_wrap", $providers, $oslist ); ?>				

			<div class = "wpcd-create-popup-console-wrap">
				<?php include wpcd_path . 'includes/core/apps/wordpress-app/templates/log-console.php'; ?>		
			</div>

			<!-- After console wrap action hook -->
			<?php do_action( "wpcd_{$this->get_app_name()}_create_popup_after_console_wrap", $providers, $oslist ); ?>

		</div>
		<?php
	}
	?>
</div>
</div>