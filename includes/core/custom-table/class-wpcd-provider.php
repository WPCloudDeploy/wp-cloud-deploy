<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class handle Provider functionality
 */
class WPCD_CT_Provider extends WPCD_MB_Custom_Table {

	/**
	 * Model slug
	 * 
	 * @var string
	 */
	public $model_name = 'provider';
	
	/**
	 * Holds a reference to this class
	 *
	 * @var $instance instance.
	 */
	private static $instance;

	/**
	 * Return instance of self.
	 */
	public static function instance() {
		
		$class = get_called_class();
		
		if ( is_null( self::$instance ) ) {
			self::$instance = new $class();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct();
		
		add_filter( "rwmb_api_secret_key_field_meta", array( $this, 'decrypt' ), 10, 3 );
		add_filter( "rwmb_ssh_private_key_field_meta", array( $this, 'decrypt' ), 10, 3 );
		add_filter( "rwmb_ssh_private_key_password_field_meta", array( $this, 'decrypt' ), 10, 3 );
		add_filter( "rwmb_allowed_users_field_meta", array( $this, 'field_allowed_users' ), 10, 3 );
		add_filter( "rwmb_allowed_roles_field_meta", array( $this, 'field_allowed_roles' ), 10, 3 );
		
		add_action( 'wpcd_after_public_pages_created', array( $this, 'create_public_page') );
	}
	
	/**
	 * Register custom table model
	 */
	public function register_model() {
		
		mb_register_model( $this->get_model_name(), [
			'table'  => $this->get_table_name(),
			'labels' => [
				'name'          => 'Providers',
				'singular_name' => 'Provider',
			],
			'add_new_item'  => __( 'Add New Provider', 'wpcd' ),
			'parent'      => 'edit.php?post_type=wpcd_app_server',
			'position'    => 80,
			'capability'  => 'wpcd_manage_settings',
			'show_in_menu'	=> false,
			'show_in_menu_custom'	=> true,
			
		] );
	}
	
	/**
	 * Create db table for model
	 */
	public function create_table() {
		
		$columns = array(
			'provider_name'		=> 'varchar(250) NOT NULL',
			'description'		=> 'text DEFAULT NULL',
			'base_provider'		=> 'varchar(100) NOT NULL',
			'api_access_key'	=> 'varchar(200) DEFAULT NULL',
			'api_secret_key'	=> 'text DEFAULT NULL',
			'api_key_notes'		=> 'text DEFAULT NULL',
			'ssh_public_key_id' => 'varchar(100) DEFAULT NULL',
			'ssh_public_key'	=> 'text DEFAULT NULL',
			'ssh_private_key'	=> 'text DEFAULT NULL',
			'ssh_private_key_password' => 'varchar(250) DEFAULT NULL',
			'ssh_key_notes'		=> 'text NULL',
			
			'region'				=> 'varchar(100) DEFAULT NULL',
			'azure_tenant'			=> 'varchar(200) DEFAULT NULL',
			'azure_subscription'	=> 'varchar(300) DEFAULT NULL',
			'azure_storage_type'	=> 'varchar(300) DEFAULT NULL',
			'enable_backups'		=> 'varchar(1) DEFAULT NULL',
			'enable_ipv6'			=> 'varchar(1) DEFAULT NULL',
			'tags'					=> 'varchar(250) DEFAULT NULL',
			'server_sizes'			=> 'text DEFAULT NULL',
			'other_notes'			=> 'text DEFAULT NULL',
			'owner'					=> 'varchar(100) DEFAULT NULL',
		);
		
		MB_Custom_Table_API::create( $this->get_table_name(), $columns, [], true );
	}
	
	/**
	 * Create page on plugin activation
	 */
	public function create_public_page() {

		if ( !$this->page_exists( true ) ) {

			$page_id = wp_insert_post(
				array(
					'post_title'   => __( 'Providers', 'wpcd' ),
					'post_content' => '[wpcd_ct_providers]',
					'post_status'  => 'publish',
					'post_author'  => get_current_user_id(),
					'post_type'    => 'page',
				)
			);

			update_option( $this->get_page_id_option_name() , $page_id );
		}
	}

	/**
	 * Metabox id for main form
	 * 
	 * @return string
	 */
	public function form_meta_box_id() {
		return 'provider_general_mb';
	}

	/**
	 * Main function for registering metabox.
	 * 
	 * @param array $meta_boxes
	 * 
	 * @return array
	 */
	public function metaboxes( $meta_boxes ) {

		$base_provider_options = WPCD_POSTS_CLOUD_PROVIDER()->get_provider_types();

		$meta_boxes[] = array(
			'id'             => $this->form_meta_box_id(),
			'title'          => __( 'General', 'wpcd' ),
			'models'       => [ $this->get_model_name() ],
			'storage_type' => 'custom_table',
			'table'        => $this->get_table_name(),
			'fields'         => array(
				array(
					'id'   => 'nonce',
					'type' => 'hidden',
					'std'	=> $this->get_nonce( $this->action() ),
					'class'			=> '',
					'desc' => __('', 'wpcd' ),
					'save_field'      => false
				),


				array(
					'name' => __( 'Name', 'wpcd' ),
					'id'   => 'provider_name',
					'type' => 'text',
					'required' => true,
					'desc' => __('Add provider name', 'wpcd' ),
					'admin_columns' => [
						'position' => 'after id',
						'sort'     => true,
					],
				),
				array(
					'name' => __( 'Description', 'wpcd' ),
					'id'   => 'description',
					'type' => 'textarea',
					'desc' => __('Provider description', 'wpcd' ),
				),
				array(
					'name' => __( 'Base Provider', 'wpcd' ),
					'id'   => 'base_provider',
					'type' => 'select',
					'required' => true,
					'options' => $base_provider_options,
					'desc' => __('', 'wpcd' ),
					'admin_columns' => [
						'position' => 'after provider_name',
						'sort'     => true,
					],
				),
				array(
					'type' => 'heading',
					'name' => __( 'API Keys', 'wpcd' )
				),
				array(
					'name' => __( 'API Access Key', 'wpcd' ),
					'id'   => 'api_access_key',
					'type' => 'text',
					'desc'  => __( 'You can get this key from your providers security or API dashboard. It is encrypted before being stored in the database.', 'wpcd' ),
				),
				array(
					'name' => __( 'API Secret Key', 'wpcd' ),
					'id'   => 'api_secret_key',
					'type' => 'text',
					'class'			=> 'wpcd_settings_pass_toggle',
					'desc' => __( 'You can get this key from your providers security or API dashboard. It is encrypted before being stored in the database.', 'wpcd' ),
				),
				array(
					'name' => __( 'API Key Notes', 'wpcd' ),
					'id'   => 'api_key_notes',
					'type' => 'textarea',
					'desc' => __( 'Your notes about this api key - optional', 'wpcd' ),
				),
				array(
						'type' => 'heading',
						'name' => __( 'SSH Keys', 'wpcd' ),
						'desc' => __( 'For security, we only use public-private key pairs for server management. You must upload at least one public key to provider.', 'wpcd' ),
				),
				array(
					'name' => __( 'SSH Public_key Id', 'wpcd' ),
					'id'   => 'ssh_public_key_id',
					'type' => 'text',
					'desc' => __('', 'wpcd' ),
				),
				array(
					'name' => __( 'SSH Public Key', 'wpcd' ),
					'id'   => 'ssh_public_key',
					'type' => 'textarea',
					'class'			=> 'wpcd_settings_pass_toggle',
					'desc' => __('', 'wpcd' ),
				),
				array(
					'name' => __( 'SSH Private Key', 'wpcd' ),
					'id'   => 'ssh_private_key',
					'type' => 'textarea',
					'class'			=> 'wpcd_settings_pass_toggle',
					'desc' => __('', 'wpcd' ),
				),
				array(
					'name' => __( 'SSH Private Key Password', 'wpcd' ),
					'id'   => 'ssh_private_key_password',
					'type' => 'text',
					'class'			=> 'wpcd_settings_pass_toggle',
					'desc' => __('', 'wpcd' ),
				),
				array( 'type' => 'divider' ),
				array(
					'name' => __( 'Region', 'wpcd' ),
					'id'   => 'region',
					'type' => 'text',
					'class'			=> '',
					'desc' => __('', 'wpcd' ),
				),
				
				array(
					'name' => __( 'Enable Backups', 'wpcd' ),
					'id'   => 'enable_backups',
					'type' => 'checkbox',
					'class'			=> '',
					'desc' => __('', 'wpcd' ),
					'admin_columns' => [
						'position' => 'after base_provider',
						'sort'     => true,
					],
				),
				array(
					'name' => __( 'Enable ipv6', 'wpcd' ),
					'id'   => 'enable_ipv6',
					'type' => 'checkbox',
					'class'			=> '',
					'desc' => __('', 'wpcd' ),
					'admin_columns' => [
						'position' => 'after enable_backups',
						'sort'     => true,
					],
				),
				array(
					'name' => __( 'Tags', 'wpcd' ),
					'id'   => 'tags',
					'type' => 'text',
					'placeholder'     => __( 'Add Tags.', 'wpcd' ),
					'class'			=> '',
					'desc' => __('', 'wpcd' ),
				),

				array(
					'id'            => "server_sizes",
					'type'          => 'key_value',
					'name'			=> __( 'Server Sizes', 'wpcd' )
				),
				array(
					'name' => __( 'Other Notes', 'wpcd' ),
					'id'   => 'other_notes',
					'type' => 'textarea',
					'class'			=> '',
					'desc' => __('', 'wpcd' ),
				),
				
				
				array(
					'type' => 'heading',
					'name' => __( 'Azure', 'wpcd' )
				),
				
				array(
					'name' => __( 'Azure Tenant', 'wpcd' ),
					'id'   => 'azure_tenant',
					'type' => 'text',
					'class'			=> '',
					'desc' => __('', 'wpcd' ),
				),
				array(
					'name' => __( 'Azure Subscription', 'wpcd' ),
					'id'   => 'azure_subscription',
					'type' => 'text',
					'class'			=> '',
					'desc' => __('', 'wpcd' ),
				),
				array(
					'name' => __( 'Azure Storage Type', 'wpcd' ),
					'id'   => 'azure_storage_type',
					'type' => 'text',
					'class'			=> '',
					'desc' => __('', 'wpcd' ),
				),

				array(
					'type' => 'heading',
					'name' => __( 'Permissions', 'wpcd' )
				),
				array(
					'name' => __( 'Allowed Roles', 'wpcd' ),
					'id'   => 'allowed_roles',
					'type' => 'select_advanced',
					'options'         => wpcd_get_roles(),
					'select_all_none' => true,
					'multiple'        => true,
					'save_field'	  => false,
					'desc' => __('', 'wpcd' ),
					'placeholder'     => __( 'Select allowed roles.', 'wpcd' ),
				),
				
				array(
					'name' => __( 'Allowed Users', 'wpcd' ),
					'id'   => 'allowed_users',
					'type' => 'user',
					'field_type'  => 'select_advanced',
					'placeholder' => 'Select allowed users',
					'ajax'  => true,
					'multiple'        => true,
					'save_field'	  => false,
					'class'	=> '',
					'desc' => __('', 'wpcd' )
				)



			),
		);
		
		return $meta_boxes;
	}
	
	/**
	 * Handle delete item action
	 */
	public function ajax_handle_delete_item() {
		
		$model_id = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT );
		
		$view  = filter_input( INPUT_POST, 'view', FILTER_SANITIZE_STRING );
		$view = $view ? $view : 'public';
		
		$error = "";
		$action = 'delete';
		
		if( !$this->verify_nonce( $action ) || !$this->check_action_permission( 'delete', $model_id, null, $view ) ) {
			$error = $this->default_permission_error( $action );
		} else {
			$this->api->delete_item( $model_id );
		}
		
		if( !empty( $error ) ) {
			$result = array( 'success' => false, 'message' => $error );
		} else {
			$message = 'Provider successfully deleted';
			$result = array( 'success' => true, 'message' => $message, 'location' => $this->get_listing_page_url( $view ) );
		}
		
		wp_send_json( $result );
		die();
	}
	
	
	/**
	 * Add filter to encrypt field before saving.
	 */
	function add_encrypt_filters() {
		add_filter( "rwmb_api_secret_key_value", array( $this, 'encrypt' ), 10, 3 );
		add_filter( "rwmb_ssh_private_key_value", array( $this, 'encrypt' ), 10, 3 );
		add_filter( "rwmb_ssh_private_key_password_value", array( $this, 'encrypt' ), 10, 3 );
	}
	
	/**
	 * Remove filter to encrypt field before saving.
	 */
	function remove_encrypt_filters() {
		remove_filter( "rwmb_api_secret_key_value", array( $this, 'encrypt' ), 10, 3 );
		remove_filter( "rwmb_ssh_private_key_value", array( $this, 'encrypt' ), 10, 3 );
		remove_filter( "rwmb_ssh_private_key_password_value", array( $this, 'encrypt' ), 10, 3 );
	}
	
	/**
	 * Validate data for add, edit action
	 * 
	 * @return array
	 */
	public function validate_data() {
		$provider_id	= filter_input( INPUT_GET,  'model-id',		 FILTER_SANITIZE_NUMBER_INT );
		$name			= filter_input( INPUT_POST, 'provider_name', FILTER_SANITIZE_STRING );
		$base_provider  = filter_input( INPUT_POST, 'base_provider', FILTER_SANITIZE_STRING );
		
		$error = '';
		
		$result = array( 'success' => true, 'error' => '' );
		
		if( !$this->verify_nonce( $this->action() ) ) {
			$error = 'Error while saving provider data.';
		} elseif( $this->action() == 'edit' && ( !$provider_id || !$this->user_can_edit( $provider_id ) ) ) {
			$error = 'You are not allowed to edit provider.';
		} elseif($this->action() == 'add' && !$this->user_can_add() ) {
			$error = 'You are not allowed to add provider.';
		} elseif( empty( $name ) || !$name ) {
			$error = "Name is required";
		} elseif( empty( $base_provider ) || !$base_provider ) {
			$error = "Select Base Provider";
		}
		
		
		if( !empty( $error ) ) {
			$result = array( 'success' => false, 'error' => $error );
		}
		
		return $result;
	}
}
