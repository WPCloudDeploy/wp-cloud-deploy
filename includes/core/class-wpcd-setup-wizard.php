<?php
/**
 * WPCD Setup Wizard Class
 *
 * Takes new users through some basic steps to setup their support.
 *
 * @package   WPCLoudDeploy/Includes/Core/WPCD_Admin_Setup_Wizard
 * @author    WPCloudDeploy <contact@wpclouddeploy.com>
 * @license   GPL-2.0+
 * @link      https://wpclouddeploy.com
 * @copyright 2019-2022 wpclouddeploy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPCD_Admin_Setup_Wizard class.
 */
class WPCD_Admin_Setup_Wizard {

	/**
	 * Current step
	 *
	 * @var string
	 */
	private $step = '';

	/**
	 * Steps for the setup wizard
	 *
	 * @var array
	 */
	private $steps = array();

	/**
	 * Hook in tabs.
	 */
	public function __construct() {

		add_action( 'admin_menu', array( $this, 'admin_menus' ) );
		add_action( 'admin_init', array( $this, 'maybe_ask_setup_wizard' ) );
		add_action( 'admin_init', array( $this, 'setup_wizard' ) );

		/* AJAX Function for skipping setup wizard. */
		add_action( 'wp_ajax_wpcd_skip_wizard_setup', array( $this, 'wpcd_skip_wizard_setup' ) );
	}

	/**
	 * Add admin menus/screens.
	 */
	public function admin_menus() {
		add_dashboard_page( '', '', 'manage_options', 'wpcd-setup', '' );
	}

	/**
	 * Maybe show prompt at top of screen to setup wizard.
	 *
	 * Action Hook: admin_init.
	 */
	public function maybe_ask_setup_wizard() {

		$test_mode = true;

		/**
		 * Proceed only if both options 'wpcd_plugin_setup' & 'wpcd_skip_wizard_setup' = false
		 * 'wpas_plugin_setup' will be added at the end of wizard steps
		 * 'wpas_skip_wizard_setup' will be set to true if user choose to skip wizrd from admin notice
		 */
		if ( ( ! get_option( 'wpcd_plugin_setup', false ) && ! get_option( 'wpcd_skip_wizard_setup', false ) ) || ( true === $test_mode ) ) {

			/**
			 * If we already have at least one server setup, do not show wizard prompt.
			 */
			$count_servers = wp_count_posts( 'wpcd_app_server' )->private;
			if ( $count_servers > 0 ) {
				add_option( 'wpcd_skip_wizard_setup', true );
				return;
			}

			/**
			 * If METABOX is not already installed, skip since we can't do anything until that is installed.
			 */
			if ( ! class_exists( 'RWMB_Core' ) ) {
				return;
			}

			// Show Wizard prompt.
			add_action( 'admin_notices', array( $this, 'wpcd_ask_setup_wizard' ), 1 );
			add_action( 'admin_enqueue_scripts', array( $this, 'wpcd_setup_wizard_scripts' ), 10, 1 );
		}

	}

	/**
	 * Paint a button at the top of the admin area asking the user if they'd like to setup using the wizard.
	 *
	 * Action Hook: admin_notices.
	 */
	public function wpcd_ask_setup_wizard() {

		if ( wpcd_is_admin() ) {

			/* Product name that will be shown at various locations in the wizard. */
			$product_name = wpcd_get_short_product_name()

			?>
			<div class="updated wpcd-wizard-notice">
				<h1 class="wizard-main-heading"><?php _e( sprintf( __( '%s: First Time Install', 'wpcd' ), $product_name ) ); ?></h1>
				<p class="wizard-first-line"><?php _e( sprintf( __( 'Thank you for installing %s. Please choose an option below to get started.', 'wpcd' ), $product_name ) ); ?></p>
				<p class="wizard-normal wizard-second-line"><?php _e( sprintf( __( 'If this is not the first time you are using %s or you would like to manually configure your initial settings, then you should choose to skip this process. Otherwise proceed by clicking the button.', 'wpcd' ), $product_name ) ); ?></p>
				<p><span class="wpcd-button-wizard-primary"><?php _e( '<a href="' . admin_url( 'index.php?page=wpcd-setup' ) . '">Click here To Get Started Now</a>', 'wpcd' ); ?></span>		
					<span class="wpcd-button-wizard-skip"><?php _e( '<a href="#" id="wpcd-skip-wizard">Or skip this process</a>', 'wpcd' ); ?>
				</p>		
			</div>	
			<?php
		}

	}

	/**
	 * Load Scripts to be used in the setup wizard.
	 *
	 * Action Hook: admin_enqueue_scripts.
	 */
	public function wpcd_setup_wizard_scripts() {
		wp_enqueue_style( 'wpcd-admin-wizard-notice', wpcd_url . 'assets/css/wpcd-setup-wizard-notice.css', array(), wpcd_scripts_version );
		wp_enqueue_script( 'wpcd-admin-wizard-script', wpcd_url . 'assets/js/wpcd-setup-wizard.js', array( 'jquery' ), wpcd_scripts_version, true );
		wp_localize_script(
			'wpcd-admin-wizard-script',
			'WPCD_Wizard',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'about_page' => admin_url( 'edit.php?post_type=wpcd_app_server' ),
			)
		);
	}

	/**
	 * Skip Setup Wizard
	 *
	 * @since 5.0
	 *
	 * @return void.
	 */
	public function wpcd_skip_wizard_setup() {
		add_option( 'wpcd_skip_wizard_setup', true );
		wp_die();
	}


	/**
	 * Show the setup wizard.
	 */
	public function setup_wizard() {
		if ( empty( $_GET['page'] ) || 'wpcd-setup' !== $_GET['page'] ) { // WPCS: CSRF ok, input var ok.
			return;
		}
		$default_steps = array(
			'general_setup'       => array(
				'name'    => __( 'General', 'wpcd' ),
				'view'    => array( $this, 'wpcd_general_setup' ),
				'handler' => array( $this, 'wpcd_general_setup_save' ),
			),
			'select_provier'      => array(
				'name'    => __( 'Select Your Server Provider', 'wpcd' ),
				'view'    => array( $this, 'wpcd_select_provider' ),
				'handler' => array( $this, 'wpcd_select_provider_save' ),
			),
			'connect_to_provider' => array(
				'name'    => __( 'Connect To Provider', 'wpcd' ),
				'view'    => array( $this, 'wpcd_connect_to_provider' ),
				'handler' => array( $this, 'wpcd_connect_to_provider_save' ),
			),
			'create_ssh_keys'     => array(
				'name'    => __( 'Create SSH Keys', 'wpcd' ),
				'view'    => array( $this, 'wpcd_create_ssh_keys' ),
				'handler' => array( $this, 'wpcd_create_ssh_keys_save' ),
			),
			'lets_go'             => array(
				'name'    => __( 'Ready', 'wpcd' ),
				'view'    => array( $this, 'wpcd_setup_lets_go' ),
				'handler' => array( $this, 'wpcd_setup_lets_go_save' ),
			),
		);

		// Load styles and scripts.
		wp_enqueue_style( 'wpcd-common-admin', wpcd_url . 'assets/css/wpcd-setup-wizard.css', array(), wpcd_scripts_version );
		wp_enqueue_script( 'wpcd-admin-script', wpcd_url . 'assets/js/wpcd-setup-wizard-support.js', array( 'jquery' ), wpcd_scripts_version, true );
		wp_enqueue_script( 'wpcd-setup', wpcd_url . 'assets/js/wpcd-setup-wizard-support.js', array( 'jquery', 'wp-util' ), wpcd_scripts_version, true );

		// What is the next step?
		$this->steps = apply_filters( 'as_setup_wizard_steps', $default_steps );
		$this->step  = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : current( array_keys( $this->steps ) ); // WPCS: CSRF ok, input var ok.

		if ( ! empty( $_POST['save_step'] ) && isset( $this->steps[ $this->step ]['handler'] ) ) {
			call_user_func( $this->steps[ $this->step ]['handler'], $this );
		}

		ob_start();
		// call setup view functions here.
		$this->setup_wizard_header();
		$this->setup_wizard_steps();
		$this->setup_wizard_content();
		$this->setup_wizard_footer();
		exit;
	}

	/**
	 * Setup Wizard Header.
	 */
	public function setup_wizard_header() {
		$product_name = wpcd_get_short_product_name();
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta name="viewport" content="width=device-width" />
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			<title><?php esc_html_e( sprintf( __( '%s &rsaquo; Setup Wizard', 'wpcd' ), $product_name ) ); ?></title>
			<?php wp_print_scripts( 'wpcd-setup' ); ?>
			<?php do_action( 'admin_print_styles' ); ?>
			<?php do_action( 'admin_head' ); ?>
		</head>
		<body class="wpcd-setup wp-core-ui">
			<div class="wpcd-setup-wizard">
			<h1 id="wpcd-logo"><a href="https://wpclouddeploy.com/"><?php esc_html_e( sprintf( __( '%s', 'wpcd' ), $product_name ) ); ?></a></h1>
		<?php
	}


	/**
	 * Output the steps.
	 */
	public function setup_wizard_steps() {
		$output_steps = $this->steps;
		?>
		<ol class="wpcd-setup-steps">
			<?php foreach ( $output_steps as $step_key => $step ) : ?>
				<?php
					/**
					 * Determine step_class
					 * On each steps done, add .done while .active
					 * for the current step state.
					 */
				if ( $step_key === $this->step ) {
					$step_class = 'active';
				} elseif ( array_search( $this->step, array_keys( $this->steps ), true ) > array_search( $step_key, array_keys( $this->steps ), true ) ) {
					$step_class = 'done';
				} else {
					$step_class = '';
				}
				?>
				<li class="<?php echo $step_class; ?>"><div class="hint">
					<?php echo esc_html( $step['name'] ); ?>
				</div></li>
			<?php endforeach; ?>
		</ol>
		<?php
	}

	/**
	 * Output the content for the current step.
	 */
	public function setup_wizard_content() {
		echo '<div class="wpcd-setup-content">';
		/*
		printf(
			'<p class="sub-heading">%s</p>',
			__( 'Welcome to Awesome Support! This setup wizard will help you to quickly configure your new support system so that you can start processing customer requests right away.  So lets get started with our first question!', 'wpcd' )
		);
		*/
		if ( ! empty( $this->steps[ $this->step ]['view'] ) ) {
			call_user_func( $this->steps[ $this->step ]['view'], $this );

		}
		echo '</div>';
	}


	/**
	 * Setup Wizard Footer.
	 */
	public function setup_wizard_footer() {
		$about_us_link = add_query_arg(
			array(
				'post_type' => 'wpcd_app_server',
				'page'      => 'wpcd_settings#tab-cloud-provider',
			),
			admin_url( 'edit.php' )
		)
		?>
		<?php if ( 'lets_go' !== $this->step ) : ?>
			<a class="not-now" href="<?php echo esc_url( $about_us_link ); ?>"><?php esc_html_e( 'Not right now', 'wpcd' ); ?></a>
		<?php endif; ?>
				</div><!-- .setup-wizard -->
			</body>
		</html>
		<?php
	}

	/**
	 * General Setup
	 */
	public function wpcd_general_setup() {
		// Heading.
		/*
		printf(
			'<p class="sub-heading">%s</p>',
			esc_html_e( 'Welcome to WPCloudDeploy! This setup wizard will help you get the basics configured and connected to your DigitalOcean account.', 'wpcd' )
		);
		*/

		$product_name = wpcd_get_short_product_name();

		?>
		<form method="post">
			<p><b><?php esc_html_e( sprintf( __( 'Welcome to %s!', 'wpcd' ), $product_name ) ); ?> </b></p>	
			<p><?php esc_html_e( 'This setup wizard will help you get the basics configured and connected to your Cloud Server account', 'wpcd' ); ?> </p>	
			<p><b><?php esc_html_e( 'Encryption Key', 'wpcd' ); ?> </b></p>			
			<?php
			// See if wpcd encryption key is defined.
			if ( ! DEFINED( 'WPCD_ENCRYPTION_KEY' ) ) {
				?>
				<p><?php esc_html_e( 'Setting up an encryption key in your wp-config.php file helps to protect your sensitive data such as your API keys and SSH keys.', 'wpcd' ); ?> </p>
				<p><?php esc_html_e( 'We strongly recommend that you set this up now. If you set it up later, you will need to re-enter your sensitive data (api keys etc.)', 'wpcd' ); ?> </p>
				<p><b><?php esc_html_e( 'Please use your sFTP client to add the WPCD_ENCRYPTION_KEY constant into your wp-config.php file.', 'wpcd' ); ?> </b></p>
				<p><pre><?php esc_html_e( "define( 'WPCD_ENCRYPTION_KEY', 'your very long encryption key goes here' );", 'wpcd' ); ?> </pre></p>
				<p><?php esc_html_e( 'Click the continue button when this task is completed. You will not be able to move on to the next step until this task is completed.', 'wpcd' ); ?> </p>
				<?php

			} else {
				?>
				<p><?php esc_html_e( 'Sweet! Your WPCD_ENCRYPTION_KEY constant is defined. You can continue to the next step.', 'wpcd' ); ?> </p>
				<p><?php esc_html_e( 'Please click the CONTINUE button below.', 'wpcd' ); ?> </p>
				<?php
			}
			?>

			<input type="submit" name="save_step" value="Continue">
			<?php wp_nonce_field( 'wpcd-setup' ); ?>
		</form>
		<?php
	}

	/**
	 * General Setup Save
	 */
	public function wpcd_general_setup_save() {
		check_admin_referer( 'wpcd-setup' );
		if ( ! DEFINED( 'WPCD_ENCRYPTION_KEY' ) ) {
			// Do not move on from the wizard since the encryption key is not defined in wp-config.php.
			wp_safe_redirect( esc_url_raw( $this->get_this_step_link() ) );
			exit;
		} else {
			wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
			exit;
		}
	}

	/**
	 * Select Provider setup view.
	 */
	public function wpcd_select_provider() {

		// Generate list of providers.
		$providers['digital-ocean'] = __( 'Digital Ocean', 'wpcd' );
		if ( class_exists( 'CLOUD_PROVIDER_API_Linode' ) ) {
			$providers['linode'] = __( 'Linode', 'wpcd' );
		}
		if ( class_exists( 'CLOUD_PROVIDER_API_VultrV2' ) ) {
			$providers['vultr-v2'] = __( 'Vultr', 'wpcd' );
		}
		if ( class_exists( 'CLOUD_PROVIDER_API_VultrV2Baremetal' ) ) {
			$providers['vultr-v2-baremetal'] = __( 'Vultr Baremetal', 'wpcd' );
		}
		if ( class_exists( 'CLOUD_PROVIDER_API_Hetzner' ) ) {
			$providers['hetzner'] = __( 'Hetzner', 'wpcd' );
		}
		if ( class_exists( 'CLOUD_PROVIDER_API_Upcloud' ) ) {
			$providers['upcloud'] = __( 'UpCloud', 'wpcd' );
		}

		?>
		<form method="post">
			<p><b><?php esc_html_e( 'Please Select Your Cloud Server Provider', 'wpcd' ); ?> </b></p>
			<p><?php esc_html_e( 'If you do not see your Cloud Server provider in the list below, please make sure you have uploaded and/or activated the provider plugin.', 'wpcd' ); ?> </p>
			<p><?php esc_html_e( 'Providers other than Digital Ocean may require a premium purchase.', 'wpcd' ); ?> </p>
			<label for="selected-provider"><?php esc_html_e( 'Choose a provider:', 'wpcd' ); ?></label>
			<select style="min-width: 200px;" type="text" name="selected-provider">
			<?php
			foreach ( $providers as $provider => $name ) {
				echo '<option value="' . $provider . '">' . $name . '</option>';
			}
			?>
			</select>
			<br /><br />
			<?php
			echo '<input type="submit" name="save_step" value="Continue">';
			// Extract error message from url - this could be set by the save function for this step.
			if ( $this->get_error_message_from_url() ) {
				$error_msg = $this->get_error_message_from_url();
				?>
				<p style="color:red;"><?php esc_html_e( $error_msg ); ?></p>
				<?php
			}

			wp_nonce_field( 'wpcd-select-provider' );
			?>
		</form>
		<?php
	}

	/**
	 * Save Selected Provider.
	 */
	public function wpcd_select_provider_save() {
		check_admin_referer( 'wpcd-select-provider' );

		// Extract the token from the _POST global var.
		$selected_provider = sanitize_text_field( FILTER_INPUT( INPUT_POST, 'selected-provider', FILTER_DEFAULT ) );

		// Empty key?  Stay on the current step.
		if ( empty( $selected_provider ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'Please select a provider.', 'wpcd' ) ), $this->get_this_step_link() ) ) );
			exit;
		}

		// Otherwise, save the selected provider in a transient for later use.
		update_option( 'wpcd_setup_wizard_selected_provider', $selected_provider );

		// Go to next step.
		wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
		exit;

	}

	/**
	 * Connect To Provider setup view.
	 */
	public function wpcd_connect_to_provider() {

		// Get provider from saved option.
		$provider = $this->wpcd_get_selected_provider();

		// If no provider, exit - the error message would have been displayed by the call to wpcd_get_selected_provider().
		if ( empty( $provider ) ) {
			exit;
		}

		?>
		<form method="post">
			<?php $this->wpcd_api_token_instructions( $provider ); ?>

			<?php // Ask for the api key only if the provider is not upcloud - UPCLOUD only needs a user id and password. ?>
			<?php if ( 'upcloud' !== $provider ) { ?>
				<b><label for="api-token"><?php esc_html_e( 'Enter Your API Key or Token:', 'wpcd' ); ?></label></b>
				<input type="text" name="api-token" size="100" />
				<br /><br />
			<?php } ?>

			<?php $this->wpcd_get_other_provider_fields( $provider ); ?>

			<?php
			echo '<input type="submit" name="save_step" value="Continue">';
			// Extract error message from url - this could be set by the save function for this step.
			if ( $this->get_error_message_from_url() ) {
				$error_msg = $this->get_error_message_from_url();
				?>
				<p style="color:red;"><?php esc_html_e( $error_msg ); ?></p>
				<?php
			}

			wp_nonce_field( 'wpcd-setup-connect-to-provider' );
			?>
		</form>
		<?php
	}

	/**
	 * Return the provider that was selected by the user and stored in options.
	 *
	 * Echo an error to the screen if there's a problem.
	 */
	public function wpcd_get_selected_provider() {

		// Get provider from saved option.
		$provider = get_option( 'wpcd_setup_wizard_selected_provider' );

		// If not provider, throw error and exit.
		if ( empty( $provider ) ) {
			$error_msg = __( 'We are unable to read the provider from the database. Please exit this wizard and contact our technical support team.', 'wpcd' );
			?>
			<p style="color:red;"><?php esc_html_e( $error_msg ); ?></p>
			<?php
		}

		return $provider;

	}

	/**
	 * Echo instructions for getting api tokens to the screen.
	 *
	 * @param string $provider The provider slug.
	 */
	public function wpcd_api_token_instructions( $provider ) {

		switch ( $provider ) {
			case 'digital-ocean':
				?>
				<p><b><?php esc_html_e( 'Connect To Your Digital Ocean Account', 'wpcd' ); ?> </b></p>
				<p><?php esc_html_e( 'Create an API TOKEN at Digital Ocean and enter it below.', 'wpcd' ); ?> </p>
				<p><?php esc_html_e( 'You can create a token by navigating to: https://cloud.digitalocean.com/account/api.', 'wpcd' ); ?></p>
				<p><?php esc_html_e( 'There you can click the GENERATE NEW TOKEN button and follow the instructions.', 'wpcd' ); ?></p>
				<p><?php esc_html_e( 'Please make sure that you assign WRITE permissions and that you select a long-running expiration date.', 'wpcd' ); ?></p>			
				<?php
				break;

			case 'linode':
				?>
				<p><b><?php esc_html_e( 'Connect To Your Linode Account', 'wpcd' ); ?> </b></p>
				<p><?php esc_html_e( 'Create an API TOKEN at LINODE and enter it below.', 'wpcd' ); ?> </p>
				<p><?php esc_html_e( 'You can create a token by navigating to: https://https://cloud.linode.com/profile/tokens.', 'wpcd' ); ?></p>
				<p><?php esc_html_e( 'There you can click the CREATE A PERSONAL ACCESS TOKEN button and follow the instructions.', 'wpcd' ); ?></p>
				<p><?php esc_html_e( 'Please make sure that you assign all READ/WRITE permissions and select a long-running expiration date.', 'wpcd' ); ?></p>			
				<?php
				break;

			case 'hetzner':
				?>
				<p><b><?php esc_html_e( 'Connect To Your Hetzner Account', 'wpcd' ); ?> </b></p>
				<p><?php esc_html_e( 'Get your API token from your Hetzner console and enter it below.', 'wpcd' ); ?> </p>
				<p><?php esc_html_e( 'You can get the api token by navigating to: https://console.hetzner.cloud/projects/<<YOURPROJECTID>>/security/tokens.', 'wpcd' ); ?></p>
				<p><?php esc_html_e( '(Replace the <<YOURPROJECT>> placeholder in the URL above with your Hetzner project id.).', 'wpcd' ); ?></p>
				<p><?php esc_html_e( 'There you can click the GENERATE API TOKEN button and follow the instructions.', 'wpcd' ); ?></p>
				<p><?php esc_html_e( 'Please make sure that you assign both READ & WRITE permissions!', 'wpcd' ); ?></p>			
				<?php
				break;

			case 'upcloud':
				?>
				<p><b><?php esc_html_e( 'Connect To Your UpCloud Account', 'wpcd' ); ?> </b></p>
				<?php
				break;

			case 'vultr-v2':
			case 'vultr-v2-baremetal':
				?>
				<p><b><?php esc_html_e( 'Connect To Your Vultr Account', 'wpcd' ); ?> </b></p>
				<p><?php esc_html_e( 'Get your API token from your VULTR console and enter it below.', 'wpcd' ); ?> </p>
				<p><?php esc_html_e( 'You can get the api token by navigating to: https://my.vultr.com/settings/#settingsapi.', 'wpcd' ); ?></p>
				<p><?php esc_html_e( 'Be sure to modify the ACCESS CONTROL section to allow your WPCD Server IP.  Otherwise, creating Vultr servers will fail.', 'wpcd' ); ?></p>
				<p><?php esc_html_e( 'The easiest thing to do is to allow ANY IPv4 and ANY IPv6. You can tighten them down later.', 'wpcd' ); ?></p>			
				<?php
				break;

		}

	}

	/**
	 * Get any other provider fields that might be needed.
	 * For example, LINODE needs a user name.
	 *
	 * @param string $provider The provider slug.
	 */
	public function wpcd_get_other_provider_fields( $provider ) {

		switch ( $provider ) {
			case 'digital-ocean':
				// Nothing needed.
				break;

			case 'linode':
				?>
				<b><label for="user-name"><?php esc_html_e( 'Enter Your Linode User Name:', 'wpcd' ); ?></label></b>
				<input type="text" name="user-name" size="100" />
				<br /><br />				
				<?php
				break;

			case 'hetzner':
				// Nothing needed.
				break;

			case 'upcloud':
				?>
				<b><label for="user-name"><?php esc_html_e( 'Enter Your Upcloud User Name:', 'wpcd' ); ?></label></b>
				<input type="text" name="user-name" size="100" />
				<br /><br />				
				<b><label for="user-name"><?php esc_html_e( 'Enter Your Upcloud Password:', 'wpcd' ); ?></label></b>
				<input type="text" name="user-password" size="100" />
				<br /><br />								
				<?php
				break;

			case 'vultr-v2':
			case 'vultr-v2-baremetal':
				// Nothing needed.
				break;

		}

	}

	/**
	 * Save other provider fields.  For example Linode needs to get and save the user name.
	 *
	 * @param string $provider The provider slug.
	 */
	public function wpcd_save_other_provider_fields( $provider ) {

		switch ( $provider ) {
			case 'digital-ocean':
				// Nothing needed.
				break;

			case 'linode':
				// Extract the user name from the _POST global var.
				$user_name = sanitize_text_field( FILTER_INPUT( INPUT_POST, 'user-name', FILTER_DEFAULT ) );

				// Empty key?  Stay on the current step.
				if ( empty( $user_name ) ) {
					wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'Please provide the Linode user name.', 'wpcd' ) ), $this->get_this_step_link() ) ) );
					exit;
				}

				// Otherwise, save it.
				wpcd_set_option( "vpn_{$provider}_user_name", WPCD()->encrypt( $user_name ) );

				break;

			case 'upcloud':
				// Extract the user name from the _POST global var.
				$user_name = sanitize_text_field( FILTER_INPUT( INPUT_POST, 'user-name', FILTER_DEFAULT ) );

				// Empty key?  Stay on the current step.
				if ( empty( $user_name ) ) {
					wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'Please provide your UpCloud user name.', 'wpcd' ) ), $this->get_this_step_link() ) ) );
					exit;
				}

				// Otherwise, save it.
				wpcd_set_option( "vpn_{$provider}_user_name", WPCD()->encrypt( $user_name ) );

				// Extract the password from the _POST global var.
				$password = sanitize_text_field( FILTER_INPUT( INPUT_POST, 'user-password', FILTER_DEFAULT ) );

				// Empty password?  Stay on the current step.
				if ( empty( $password ) ) {
					wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'Please provide your UpCloud Password.', 'wpcd' ) ), $this->get_this_step_link() ) ) );
					exit;
				}

				// Otherwise, save it.
				wpcd_set_option( "vpn_{$provider}_user_password", WPCD()->encrypt( $password ) );

				break;

			case 'hetzner':
				break;

			case 'vultr-v2':
			case 'vultr-v2-baremetal':
				// Nothing needed.
				break;

		}

	}

	/**
	 * Connect To Provider setup on save.
	 *
	 * @NOTE: Portions of this code is a duplicate of what's in the wpcd_provider_test_provider_connection() function in file includes/core/class-wpcd-settings.php.
	 */
	public function wpcd_connect_to_provider_save() {
		check_admin_referer( 'wpcd-setup-connect-to-provider' );

		// Get previously selected provider.
		$provider = $this->wpcd_get_selected_provider();

		// Extract the token from the _POST global var.
		$api_key = sanitize_text_field( FILTER_INPUT( INPUT_POST, 'api-token', FILTER_DEFAULT ) );

		// If the provider is upcloud, this value is going to be blank so set it to something random.
		if ( 'upcloud' === $provider ) {
			$api_key = 'no_value_needed';
		}

		// Empty key?  Stay on the current step.
		if ( empty( $api_key ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'Please provide the API Key/Token.', 'wpcd' ) ), $this->get_this_step_link() ) ) );
			exit;
		}

		// If no provider, exit - the error message would have been displayed by the call to wpcd_get_selected_provider().
		if ( empty( $provider ) ) {
			exit;
		}

		// Save api key/token.
		wpcd_set_option( "vpn_{$provider}_apikey", WPCD()->encrypt( $api_key ) );

		// Get and save any other fields that are unique to the provider.
		$this->wpcd_save_other_provider_fields( $provider );

		// Now test connection.
		// This code is a duplicate of what's in the wpcd_provider_test_provider_connection() function in file includes/core/class-wpcd-settings.php.
		$transient_key = 'wpcd_provider_connection_test_success_flag_' . $provider . hash( 'sha256', $api_key );
		delete_transient( $transient_key );

		// Call the test_connection function.
		$attributes        = array();
		$connection_status = WPCD()->get_provider_api( $provider )->call( 'test_connection', $attributes );
		if ( ! is_wp_error( $connection_status ) && ! empty( $connection_status['test_status'] ) && true === (bool) $connection_status['test_status'] ) {
			// Go to next step.
			wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
			exit;
		} else {
			// Stay on this step.
			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'We were unable to connect to your server provider with this API key/token. Please re-enter it or try a different one.', 'wpcd' ) ), $this->get_this_step_link() ) ) );
			exit;
		}

	}

	/**
	 * Create SSH keys at cloud provider view.
	 */
	public function wpcd_create_ssh_keys() {
		?>
		<form method="post">
			<p><b><?php esc_html_e( 'Create SSH Keys', 'wpcd' ); ?> </b></p>
			<p><?php esc_html_e( 'We use SSH keys, not passwords, to connect to your server.', 'wpcd' ); ?></p>
			<p><?php esc_html_e( 'We will create a new ssh key-pair for you and submit the public portion to your server provider\'s account.', 'wpcd' ); ?></p>
			<p><?php esc_html_e( 'Click the CONTINUE button below to do this now.', 'wpcd' ); ?></p>
			<p><?php esc_html_e( 'If you prefer to use your own keys, you can cancel this assistant using the NOT RIGHT NOW button and enter your own keys in the SETTINGS area.', 'wpcd' ); ?></p>
			<input type="submit" name="save_step" value="Continue">
			<?php wp_nonce_field( 'wpcd-ssh-keys' ); ?>
		</form>
		<?php
	}

	/**
	 * Create SSH keys at cloud provider on save.
	 *
	 * @NOTE: Portions of this code is a duplicate of what's in the wpcd_provider_auto_create_ssh_key() function in file includes/core/class-wpcd-settings.php.
	 */
	public function wpcd_create_ssh_keys_save() {
		check_admin_referer( 'wpcd-ssh-keys' );

		// Get previously selected provider.
		$provider = $this->wpcd_get_selected_provider();

		// If no provider, exit - the error message would have been displayed by the call to wpcd_get_selected_provider().
		if ( empty( $provider ) ) {
			exit;
		}

		// Create key.
		$key_pair                      = WPCD_WORDPRESS_APP()->ssh()->create_key_pair();
		$attributes                    = array();
		$attributes['public_key']      = $key_pair['public'];
		$attributes['public_key_name'] = 'WPCD_AUTO_CREATE_' . wpcd_random_str( 10, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' );

		// Call the ssh_create function.
		$key_id = WPCD()->get_provider_api( $provider )->call( 'ssh_create', $attributes );

		if ( is_array( $key_id ) && ( ! is_wp_error( $key_id ) ) && ( $key_id ) && ( ! empty( $key_id['ssh_key_id'] ) ) ) {

			// Ok, we got this far. Save to our options array.
			wpcd_set_option( "vpn_{$provider}_sshkey_id", $key_id['ssh_key_id'] );
			wpcd_set_option( "vpn_{$provider}_sshkey", WPCD()->encrypt( $key_pair['private'] ) );
			wpcd_set_option( "vpn_{$provider}_public_sshkey", $key_pair['public'] );
			wpcd_set_option( "vpn_{$provider}_sshkeynotes", $attributes['public_key_name'] . ': ' . __( 'This key was automatically created.', 'wpcd' ) );

			wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
			exit;

		} else {
			// Stay on this step.
			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'It looks like we were unable to create the key for you. You can try again or cancel this wizard and setup your own pair in the settings area.', 'wpcd' ) ), $this->get_this_step_link() ) ) );
			exit;
		}
	}

	/**
	 * Lets Go page view for think you message and all.
	 */
	public function wpcd_setup_lets_go() {
		$product_name = wpcd_get_short_product_name();
		?>
		<form method="post">
			<p><b><?php esc_html_e( sprintf( __( '%s is all set up and ready to go!', 'wpcd' ), $product_name ) ); ?></b></p>
			<p><?php esc_html_e( 'You should now be able to create your first server at your provider.', 'wpcd' ); ?></p>
			<p>
			<?php
			echo sprintf( __( 'You can go to the server list to create your first server or <b><u><a %s>View The Documentation</a></b></u>.', 'wpcd' ), 'href="https://wpclouddeploy.com/doc-landing/" target="_blank" ' );
			?>
			</p>
			<input type="submit" name="save_step" value="Create First Server">
			<?php wp_nonce_field( 'wpcd-setup-complete' ); ?>
		</form>
		<?php
	}

	/**
	 * Lets Go button click
	 */
	public function wpcd_setup_lets_go_save() {
		check_admin_referer( 'wpcd-setup-complete' );
		add_option( 'wpcd_plugin_setup', true );  // Prevents the wizard from showing on the screen.
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'wpcd_app_server',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Extract and return an error message from the URL.
	 */
	public function get_error_message_from_url() {
		$msg = sanitize_text_field( FILTER_INPUT( INPUT_GET, 'error_msg', FILTER_DEFAULT ) );
		return $msg;
	}

	/**
	 * Get the URL for the next step's screen.
	 *
	 * @param string $step  slug (default: current step).
	 * @return string       URL for next step if a next step exists.
	 *                      Admin URL if it's the last step.
	 *                      Empty string on failure.
	 */
	public function get_next_step_link( $step = '' ) {
		if ( ! $step ) {
			$step = $this->step;
		}

		$keys = array_keys( $this->steps );
		if ( end( $keys ) === $step ) {
			return admin_url();
		}

		$step_index = array_search( $step, $keys, true );
		if ( false === $step_index ) {
			return '';
		}

		return remove_query_arg( 'error_msg', add_query_arg( 'step', $keys[ $step_index + 1 ], remove_query_arg( 'activate_error' ) ) );
	}

	/**
	 * Get the URL for the current step's screen.
	 *
	 * @param string $step  slug (default: current step).
	 * @return string       URL for current step.
	 */
	public function get_this_step_link( $step = '' ) {
		if ( ! $step ) {
			$step = $this->step;
		}

		$keys = array_keys( $this->steps );
		if ( end( $keys ) === $step ) {
			return admin_url();
		}

		$step_index = array_search( $step, $keys, true );
		if ( false === $step_index ) {
			return '';
		}

		return add_query_arg( 'step', $keys[ $step_index ], remove_query_arg( 'activate_error' ) );
	}

}
new WPCD_Admin_Setup_Wizard();
