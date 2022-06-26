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

		/**
		 * Ask for setup plugin using Setup wizard.
		 * Proceed only if both options 'wpcd_plugin_setup' & 'wpcd_skip_wizard_setup' = false
		 * 'wpas_plugin_setup' will be added at the end of wizard steps
		 * 'wpas_skip_wizard_setup' will be set to true if user choose to skip wizrd from admin notice
		 */
		if ( ! get_option( 'wpcd_plugin_setup', false ) && ! get_option( 'wpcd_skip_wizard_setup', false ) ) {
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
			?>
			<div class="updated wpcd-wizard-notice">
				<h1 class="wizard-main-heading"><?php _e( 'WPCloudDeploy: First Time Install', 'wpcd' ); ?></h1>
				<p class="wizard-first-line"><?php _e( 'Thank you for installing WPCloudDeploy. Please choose an option below to get started.', 'wpcd' ); ?></p>
				<p class="wizard-normal wizard-second-line"><?php _e( 'If this is not the first time you are using WPCLoudDeploy or you would like to manually configure your initial settings, then you should choose to skip this process. Otherwise proceed by clicking the button.', 'wpcd' ); ?></p>		
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
		wp_enqueue_style( 'wpcd-common-admin', wpcd_url . 'assets/css/wpcd-setup-wizard-notice.css', array(), wpcd_scripts_version );
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
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta name="viewport" content="width=device-width" />
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			<title><?php esc_html_e( 'WPCloudDeploy &rsaquo; Setup Wizard', 'wpcd' ); ?></title>
			<?php wp_print_scripts( 'wpcd-setup' ); ?>
			<?php do_action( 'admin_print_styles' ); ?>
			<?php do_action( 'admin_head' ); ?>
		</head>
		<body class="wpcd-setup wp-core-ui">
			<div class="wpcd-setup-wizard">
			<h1 id="wpcd-logo"><a href="https://wpclouddeploy.com/">WPCloudDeploy</a></h1>			
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

		?>
		<form method="post">
			<p><b><?php esc_html_e( 'Welcome to WPCloudDeploy!', 'wpcd' ); ?> </b></p>	
			<p><?php esc_html_e( 'This setup wizard will help you get the basics configured and connected to your DigitalOcean account', 'wpcd' ); ?> </p>	
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
	 * Connect To Provider setup view.
	 */
	public function wpcd_connect_to_provider() {
		?>
		<form method="post">
			<p><b><?php esc_html_e( 'Connect To Your DigitalOcean Account', 'wpcd' ); ?> </b></p>
			<p><?php esc_html_e( 'Create an API TOKEN at DigitalOcean and enter it below.', 'wpcd' ); ?> </p>
			<p><?php esc_html_e( 'You can create a token by navigating to: https://cloud.digitalocean.com/account/api.', 'wpcd' ); ?></p>
			<p><?php esc_html_e( 'There you can click the GENERATE NEW TOKEN button and follow the instructions.', 'wpcd' ); ?></p>
			<p><?php esc_html_e( 'Please make sure that you assign WRITE permissions and that you select a long-running expiration date.', 'wpcd' ); ?></p>
			<b><label for="digital-ocean-api-token"><?php esc_html_e( 'Enter Your DigitalOcean API Key:', 'wpcd' ); ?></label></b>
			<input type="text" name="digital-ocean-api-token" size="100" />
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

			wp_nonce_field( 'wpcd-setup-connect-to-provider' );
			?>
		</form>
		<?php
	}

	/**
	 * Connect To Provider setup on save.
	 *
	 * @NOTE: Portions of this code is a duplicate of what's in the wpcd_provider_test_provider_connection() function in file includes/core/class-wpcd-settings.php.
	 */
	public function wpcd_connect_to_provider_save() {
		check_admin_referer( 'wpcd-setup-connect-to-provider' );

		// Extract the token from the _POST global var.
		$api_key = sanitize_text_field( FILTER_INPUT( INPUT_POST, 'digital-ocean-api-token', FILTER_DEFAULT ) );

		// Empty key?  Stay on the current step.
		if ( empty( $api_key ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'Please provide the DigitalOcean API Key/Token.', 'wpcd' ) ), $this->get_this_step_link() ) ) );
			exit;
		}

		// Otherwise, update the digital ocean option.
		$provider = 'digital-ocean';
		wpcd_set_option( "vpn_{$provider}_apikey", WPCD()->encrypt( $api_key ) );

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
			wp_safe_redirect( esc_url_raw( add_query_arg( array( 'error_msg' => __( 'We were unable to connect to DigitalOcean with this API key/token. Please try a different one.', 'wpcd' ) ), $this->get_this_step_link() ) ) );
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
			<p><?php esc_html_e( 'We will create a new ssh key-pair for you and submit the public portion to your DigitalOcean account.', 'wpcd' ); ?></p>
			<p><?php esc_html_e( 'Click the CONTINUE button below to do this now.', 'wpcd' ); ?></p>
			<p><?php esc_html_e( 'If you prefer to use your own keys, you can cancel this assistant using the NOT RIGHT NOW button.', 'wpcd' ); ?></p>
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

		// Create key.
		$provider                      = 'digital-ocean';
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
		?>
		<form method="post">
			<p><b><?php esc_html_e( 'WPCloudDeploy is all set up and ready to go!', 'wpcd' ); ?></b></p>
			<p><?php esc_html_e( 'You should now be able to create your first server at DigitalOcean.', 'wpcd' ); ?></p>
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
