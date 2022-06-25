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
			'general_setup'            => array(
				'name'    => __( 'General', 'wpcd' ),
				'view'    => array( $this, 'wpcd_general_setup' ),
				'handler' => array( $this, 'wpcd_general_setup_save' ),
			),
			'connect_to_provider'      => array(
				'name'    => __( 'Connect To Provider', 'wpcd' ),
				'view'    => array( $this, 'wpcd_connect_to_provider' ),
				'handler' => array( $this, 'wpcd_connect_to_provider_save' ),
			),
			'my_ticket_page'           => array(
				'name'    => __( 'My ticket Page', 'wpcd' ),
				'view'    => array( $this, 'as_setup_my_ticket_page' ),
				'handler' => array( $this, 'as_setup_my_ticket_page_save' ),
			),
			'priorities'               => array(
				'name'    => __( 'Priorities', 'wpcd' ),
				'view'    => array( $this, 'as_setup_priorities' ),
				'handler' => array( $this, 'as_setup_priorities_save' ),
			),
			'departments'              => array(
				'name'    => __( 'Departments', 'wpcd' ),
				'view'    => array( $this, 'as_setup_departments' ),
				'handler' => array( $this, 'as_setup_departments_save' ),
			),
			'ticket_submit_user_roles' => array(
				'name'    => __( 'Existing Users', 'wpcd' ),
				'view'    => array( $this, 'as_setup_ticket_submit_user_roles' ),
				'handler' => array( $this, 'as_setup_ticket_submit_user_roles_save' ),
			),
			'lets_go'                  => array(
				'name'    => __( "Let's Go", 'wpcd' ),
				'view'    => array( $this, 'as_setup_lets_go' ),
				'handler' => array( $this, 'as_setup_lets_go_save' ),
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
			<?php wp_print_scripts( 'as-setup' ); ?>
			<?php do_action( 'admin_print_styles' ); ?>
			<?php do_action( 'admin_head' ); ?>
		</head>
		<body class="as-setup wp-core-ui">
			<div class="as-setup-wizard">
			<h1 id="as-logo"><a href="https://wpclouddeploy.com/">WPCloudDeploy</a></h1>			
		<?php
	}


	/**
	 * Output the steps.
	 */
	public function setup_wizard_steps() {
		$output_steps = $this->steps;
		?>
		<ol class="as-setup-steps">
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
		echo '<div class="as-setup-content">';
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
		printf(
			'<p class="sub-heading">%s</p>',
			esc_html_e( 'Welcome to WPCloudDeploy! This setup wizard will help you get the basics configured and connected to your DigitalOcean account.', 'wpcd' )
		);

		?>
		<form method="post">
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
				<p><?php esc_html_e( 'Click the CONTINUE button', 'wpcd' ); ?> </p>
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
			<p><?php esc_html_e( 'There you can click the GENERATE NEW TOKEN button and follow the instructions.  Please make sure that you assign WRITE permissions and that you select a long-running expiration date so that your access is not pre-maturely cut-off.', 'wpcd' ); ?></p>
			<label for="digital-ocean-api-token"><?php esc_html_e( 'Enter Your DigitalOcean API Key:', 'wpcd' ); ?></label>
			<input type="text" name="digital-ocean-api-token" />
			<?php
			echo '<input type="submit" name="save_step" value="Continue">';
			// Extract error message from url - this could be set by the save function for this step.
			if ( $this->get_error_message_from_url() ) {
				$error_msg = $this->get_error_message_from_url();
				?>
				<p style="color:red;"><?php esc_html_e( $error_msg ); ?></p>
				<?php
			}

			wp_nonce_field( 'wpas-setup-connect-to-provider' );
			?>
		</form>
		<?php
	}

	/**
	 * Connect To Provider setup on save.
	 */
	public function wpcd_connect_to_provider_save() {
		check_admin_referer( 'wpas-setup-connect-to-provider' );

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
		if ( ! is_wp_error( $connection_status ) && ! empty( $connection_status['test_status'] ) && true === $connection_status['test_status'] ) {
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
	 * Awesome Support my tickets page setup view.
	 */
	public function as_setup_my_ticket_page() {
		?>
		<form method="post">
			<p><b><?php _e( 'Which menu would you like to add the MY TICKETS page to?', 'wpcd' ); ?> </b></p>
			<p><?php _e( 'We have created a new page that users can access to view their existing tickets.  This step allows you to add that page to one of your existing menus so users can easily access it.', 'wpcd' ); ?></p>
			<p><?php _e( 'Note: If you change your mind later you can remove the page from your menu or add it to a new menu via APPEARANCE->MENUS.', 'wpcd' ); ?></p>
			<?php
			$menu_lists = wp_get_nav_menus();
			echo '<select name="wpas_ticket_list_menu">';
			foreach ( $menu_lists as $key => $menu ) {
				echo '<option value="' . $menu->term_id . '">' . $menu->name . '</option>';
			}
			echo '<select>';
			?>
			<input type="submit" name="save_step" value="Continue">
			<?php wp_nonce_field( 'as-setup' ); ?>
		</form>
		<?php
	}

	/**
	 * Awesome Support my ticket page setup on save.
	 */
	public function as_setup_my_ticket_page_save() {
		check_admin_referer( 'as-setup' );
		$ticket_list           = wpas_get_option( 'ticket_list' );
		$wpas_ticket_list_menu = ( isset( $_POST['wpas_ticket_list_menu'] ) && ! empty( $_POST['wpas_ticket_list_menu'] ) ) ? intval( $_POST['wpas_ticket_list_menu'] ) : 0;
		if ( ! empty( $ticket_list ) && ! is_array( $ticket_list ) ) {
			wp_update_nav_menu_item(
				$wpas_ticket_list_menu,
				0,
				array(
					'menu-item-db-id'     => $ticket_list,
					'menu-item-object-id' => $ticket_list,
					'menu-item-object'    => 'page',
					'menu-item-title'     => wp_strip_all_tags( __( 'My Tickets', 'wpcd' ) ),
					'menu-item-status'    => 'publish',
					'menu-item-type'      => 'post_type',
				)
			);
		}
		wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
	}

		/**
		 * Awesome Support priorities setup view.
		 */
	public function as_setup_priorities() {
		$support_priority = wpas_get_option( 'support_priority' );
		?>
		<form method="post">
			<p><b><?php _e( 'Would you like to use the priority field in your tickets?', 'wpcd' ); ?> </b></p>
			<p><?php _e( 'Turn this option on if you would like to assign priorities to your tickets.', 'wpcd' ); ?> </p>
			<p><?php _e( 'After you have finished with the wizard you can configure your priority levels under TICKETS->PRIORITIES.', 'wpcd' ); ?> </p>
			<p><?php _e( 'You can also tweak how priorities work by changing settings under the TICKETS->SETTINGS->FIELDS tab.', 'wpcd' ); ?> </p>
			<label for='property_field_yes'>Yes</label>
			<input type="radio" name="property_field" id='property_field_yes' value="yes" checked />
			<label for='property_field_no'>No</label>
			<input type="radio" name="property_field" id='property_field_no' value="no"/>
			<input type="submit" name="save_step" value="Continue">
			<?php wp_nonce_field( 'as-setup' ); ?>
		</form>
		<?php
	}

	/**
	 * Awesome Support priorities setup on save.
	 */
	public function as_setup_priorities_save() {
		check_admin_referer( 'as-setup' );
		$property_field = ( isset( $_POST['property_field'] ) ) ? sanitize_text_field( $_POST['property_field'] ) : '';
		$options        = unserialize( get_option( 'wpas_options', array() ) );
		if ( ! empty( $property_field ) && 'yes' === $property_field ) {
			$options['support_priority'] = '1';
		} else {
			$options['support_priority'] = 0;
		}
		update_option( 'wpas_options', serialize( $options ) );
		wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
	}

	/**
	 * Awesome Support ticket submit allowed roles setup view.
	 */
	public function as_setup_ticket_submit_user_roles() {

		?>
		<form method="post">
			
			<h2><?php _e( 'Important! How do you want to handle your existing users?', 'wpcd' ); ?></h2>
			<p><em><?php _e( 'By default, none of your existing users will be allowed to submit ticket. However, you can adjust this based on your existing user roles.', 'wpcd' ); ?></em></p>
			<p><b><?php _e( 'Any of the user roles you check below will automatically be allowed to submit tickets.', 'wpcd' ); ?></b>
			<span><em><?php _e( ' If you do not choose any roles then only new users will be allowed to submit tickets!  If this is a new installation of WordPress with no existing users then you can just skip to the next step by clicking the CONTINUE button. ', 'wpcd' ); ?></em></span>
			</p>
			
			<?php

			$all_roles = get_editable_roles();

			$skip_roles = array(
				'wpas_manager',
				'wpas_support_manager',
				'wpas_agent',
				'wpas_user',
			);

			foreach ( $all_roles as $r_name => $r ) {
				if ( ! in_array( $r_name, $skip_roles ) ) {
					printf( '<label><input type="checkbox" name="roles[]" value="%s" /> %s</label><br />', $r_name, $r['name'] );
				}
			}

			?>
			<br />
			<input type="submit" name="save_step" value="Continue">
			<?php wp_nonce_field( 'as-setup' ); ?>
		</form>
		<?php
	}

	/**
	 * Awesome Support ticket submit allowed roles setup on save.
	 */
	public function as_setup_ticket_submit_user_roles_save() {

		check_admin_referer( 'as-setup' );

		$selected_roles = filter_input( INPUT_POST, 'roles', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		$selected_roles = $selected_roles && is_array( $selected_roles ) ? $selected_roles : array();

		$capabilities = array(
			'view_ticket',
			'create_ticket',
			'close_ticket',
			'reply_ticket',
			'attach_files',
		);

		foreach ( $selected_roles as $role_name ) {
			$role = get_role( $role_name );

			foreach ( $capabilities as $capability ) {
				$role->add_cap( $capability );
			}
		}

		// Don't show setup wizard link on plug-in activation.
		update_option( 'wpas_plugin_setup', 'done' );

		wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
	}



		/**
		 * Awesome Support departments setup view.
		 */
	public function as_setup_departments() {
		$departments = wpas_get_option( 'departments' );
		?>
		<form method="post">
			<p><b><?php _e( 'Do you want to enable Departments?', 'wpcd' ); ?> </b></p>
			<p><?php _e( 'Turn this option on if you would like to assign departments to your tickets.', 'wpcd' ); ?> </p>
			<p><?php _e( 'Once enabled, you can configure your list of departments by going to TICKETS->DEPARTMENTS.', 'wpcd' ); ?> </p>
			<p><?php _e( 'You can turn this off later if you change your mind by going to the TICKETS->SETTINGS->FIELDS tab.', 'wpcd' ); ?> </p>			
			<label for='departments_field_yes'>Yes</label>
			<input type="radio" name="departments_field" id='departments_field_yes' value="yes" checked />
			<label for='departments_field_no'>No</label>
			<input type="radio" name="departments_field" id='departments_field_no' value="no"/>
			<input type="submit" name="save_step" value="Continue">
			<?php wp_nonce_field( 'as-setup' ); ?>
		</form>
		<?php
	}

	/**
	 * Awesome Support departments setup on save.
	 */
	public function as_setup_departments_save() {
		check_admin_referer( 'as-setup' );
		$departments_field = ( isset( $_POST['departments_field'] ) ) ? sanitize_text_field( $_POST['departments_field'] ) : '';
		$options           = unserialize( get_option( 'wpas_options', array() ) );
		if ( ! empty( $departments_field ) && 'yes' === $departments_field ) {
			$options['departments'] = '1';
		} else {
			$options['departments'] = '0';
		}
		update_option( 'wpas_options', serialize( $options ) );
		// Don't show setup wizard link on plug-in activation.
		update_option( 'wpas_plugin_setup', 'done' );
		wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
	}

	/**
	 * Lets Go page view for think you message and all.
	 */
	public function as_setup_lets_go() {
		?>
		<form method="post">
			<p><b><?php _e( 'Your new support system is all set up and ready to go!', 'wpcd' ); ?></b></p>
			<p><?php _e( 'If your menus are active in your theme your users will now able to register for an account and submit tickets.', 'wpcd' ); ?></p>
			<p><b><?php _e( 'Do you have existing users in your WordPress System?', 'wpcd' ); ?></b></p>
			<p>
			<?php
			echo sprintf( __( 'If so, you will want to read <b><u><a %s>this article</a></b></u> on our website.', 'wpcd' ), 'href="https://getawesomesupport.com/documentation/awesome-support/admin-handling-existing-users-after-installation/" target="_blank" ' );
			?>
			</p>
			<p><b><?php _e( 'Where are my support tickets?', 'wpcd' ); ?></b></p>
			<p><?php _e( 'You can now access your support tickets and other support options under the new TICKETS menu option.', 'wpcd' ); ?></p>
			<input type="submit" name="save_step" value="Let's Go">
			<?php wp_nonce_field( 'as-setup' ); ?>
		</form>
		<?php
	}

	/**
	 * Lets Go button click
	 */
	public function as_setup_lets_go_save() {
		wp_redirect(
			add_query_arg(
				array(
					'post_type' => 'ticket',
					'page'      => 'wpas-about',
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
		$msg = sanitize_text_field( FILTER_INPUT( INPUT_GET, 'error_msg', FILTER_DEFAULT) );
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
