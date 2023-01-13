<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class handle DNS Provider functionality
 */
class WPCD_CT_DNS_Provider extends WPCD_MB_Custom_Table {
	
	/**
	 * Model slug
	 * 
	 * @var string
	 */
	public $model_name = 'dns_provider';
	
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
				'name'          => __( 'DNS Providers', 'wpcd' ),
				'singular_name' => __( 'DNS Provider', 'wpcd' ),
			],
			'parent'      => 'edit.php?post_type=wpcd_app_server',
			'position'    => 81,
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
			'dns_name'					=> 'varchar(250) NOT NULL',
			'dns_short_description'		=> 'text',
			'dns_provider'				=> 'varchar(100) NOT NULL',
			'dns_secret_key'			=> 'text',
			'dns_notes'					=> 'text',
			'owner'						=> 'varchar(100) DEFAULT NULL'
		);
		
		MetaBox\CustomTable\API::create( $this->get_table_name(), $columns, [], true );
	}
	
	/**
	 * Create page on plugin activation
	 */
	public function create_public_page() {
		if ( !$this->page_exists( true ) ) {

			$page_id = wp_insert_post(
				array(
					'post_title'   => __( 'DNS Providers', 'wpcd' ),
					'post_content' => '[wpcd_ct_dns_providers]',
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
		return 'dns_provider_mb';
	}
	
	/**
	 * Main function for registering metabox.
	 * 
	 * @param array $meta_boxes
	 * 
	 * @return array
	 */
	public function metaboxes( $meta_boxes ) {
		
		$meta_boxes[] = array(
			'id'				=> $this->form_meta_box_id(),
			'title'				=> __( 'DNS Provider', 'wpcd' ),
			'models'			=> [ $this->get_model_name() ],
			'storage_type'		=> 'custom_table',
			'table'				=> $this->get_table_name(),
			'fields'			=> array(
				
				array(
					'id'   => 'nonce',
					'type' => 'hidden',
					'std'	=> $this->get_nonce( $this->action() ),
					'class'			=> '',
					'desc' => __('', 'wpcd' ),
					'save_field'      => false
				),
				
				array(
					'name' => __( 'DNS Name', 'wpcd' ),
					'id'   => 'dns_name',
					'type' => 'text',
					'desc' => __('Enter DNS provider name', 'wpcd' ),
					'admin_columns' => [
						'position' => 'after id',
						'sort'     => true,
					],
					'required' => true
				),
				array(
					'name' => __( 'Short Description', 'wpcd' ),
					'id'   => 'dns_short_description',
					'type' => 'textarea',
					'desc' => __('Short description about dns provider', 'wpcd' ),
				),
				array(
					'name' => __( 'DNS Provider', 'wpcd' ),
					'id'   => 'dns_provider',
					'type' => 'select',
					'options' => [
						'cloudflare'	=> 'Cloudflare',
						'godaddy' => 'Godaddy',
						'dnsmadeeasy' => 'DNS Made Easy'
					],
					'desc' => __('Select DNS provider', 'wpcd' ),
					'admin_columns' => [
						'position' => 'after dns_name',
						'sort'     => true,
					],
					'required' => true
				),
				array(
					'name' => __( 'DNS Notes', 'wpcd' ),
					'id'   => 'dns_notes',
					'type' => 'textarea',
					'class'			=> '',
					'desc' => __('', 'wpcd' ),
				),
				
				array(
					'name' => __( 'DNS Secret Key', 'wpcd' ),
					'id'   => 'dns_secret_key',
					'type' => 'textarea',
					'class'			=> '',
					'desc' => __('', 'wpcd' ),
				),
				array(
					'type' => 'heading',
					'name' => __( 'Permissions', 'wpcd' )
				),
				array(
					'name' => __( 'Owner', 'wpcd' ),
					'id'   => 'owner',
					'type' => 'user',
					'field_type'  => 'select_advanced',
					'placeholder' => 'Select allowed users',
					'ajax'  => true,
					'multiple'        => false,
					'class'	=> '',
					'desc' => __('', 'wpcd' )
				) ,
				array(
					'name' => __( 'Allowed Roles', 'wpcd' ),
					'id'   => 'allowed_roles',
					'type' => 'select_advanced',
					'options'         => wpcd_get_roles(),
					'select_all_none' => true,
					'multiple'        => true,
					'desc' => __('', 'wpcd' ),
					'placeholder'     => __( 'Select allowed roles.', 'wpcd' ),
					'save_field'      => false
				),
				array(
					'name' => __( 'Allowed Users', 'wpcd' ),
					'id'   => 'allowed_users',
					'type' => 'user',
					'field_type'  => 'select_advanced',
					'placeholder' => 'Select allowed users',
					'ajax'  => true,
					'multiple'        => true,
					'class'			=> '',
					'desc' => __('', 'wpcd' ),
					'save_field'      => false
				)
			)
		);
		
		if( !is_admin() ) {
			
			$meta_boxes[] = array(
				'id'				 => 'dns_provider_mb_submit_box',
				'mb-submit-box'		 => 'yes',
				'models'			 => [ $this->get_model_name() ],
				'storage_type'		 => 'custom_table',
				'table'				 => $this->get_table_name(),
				'fields'			 => array(
					array(
						'type' => 'custom_html',
						'callback' => array( $this, 'submit_box' )
					)
				)
			);
		}
		
		
		if( $this->is_custom_table_page() && $this->is_item_edit_screen() ) {
			
			$meta_boxes[] = array(
				'id'				 => 'dns_provider_mb_zones',
				'child-items-mb'	 => 'yes',
				'title'				 => __( 'Zones', 'wpcd' ),
				'models'			 => [ $this->get_model_name() ],
				'storage_type'		 => 'custom_table',
				'table'				 => $this->get_table_name(),
				'fields'			 => array(
					array(
						'type' => 'custom_html',
						'callback' => array( $this, 'provider_zones' )
					)
				)
			);
		}
		
		
		if( $this->is_custom_table_page() && $this->is_item_edit_screen() ) {
			
			$meta_boxes[] = array(
				'id'				 => 'dns_provider_mb_zone_records',
				'child-items-mb'	 => 'yes',
				'title'				 => __( 'Zone Records', 'wpcd' ),
				'models'			 => [ $this->get_model_name() ],
				'storage_type'		 => 'custom_table',
				'table'				 => $this->get_table_name(),
				'fields'			 => array(
					array(
						'type' => 'custom_html',
						'callback' => array( $this, 'zone_records' )
					)
				)
			);
		}
		
		return $meta_boxes;
	}
	
	
	/**
	 * Return empty container for provider zones
	 * 
	 * @return string
	 */
	public function provider_zones() {
		
		$dns_zone = WPCD_MB_Custom_Table::get( 'dns_zone' );
		return $dns_zone->provider_zones_table_view_container();
	}
	
	
	/**
	 * Return empty container for zone records
	 * 
	 * @return string
	 */
	public function zone_records() {
		
		$dns_zone_record = WPCD_MB_Custom_Table::get( 'dns_zone_record' );
		return $dns_zone_record->zone_records_table_view_container();
	}
	
	/**
	 * Validate data for add, edit action
	 * 
	 * @return array
	 */
	public function validate_data() {
		
		$provider_id  = filter_input( INPUT_GET, 'model-id',	  FILTER_SANITIZE_NUMBER_INT );
		$dns_name	  = sanitize_text_field( filter_input( INPUT_POST, 'dns_name',	  FILTER_UNSAFE_RAW ) );
		$dns_provider = sanitize_text_field( filter_input( INPUT_POST, 'dns_provider', FILTER_UNSAFE_RAW ) );
		
		
		$error = '';
		
		$result = array( 'success' => true, 'error' => '' );
		
		if( $this->action() == 'edit' && !$this->user_can_edit( $provider_id ) ) {
			$error = 'You are not allowed to edit dns provider.';
		} elseif($this->action() == 'add' && !$this->user_can_add() ) {
			$error = 'You are not allowed to add dns provider.';
		} elseif( empty( $dns_name ) || !$dns_name ) {
			$error = "DNS Name is required";
		} elseif( empty( $dns_provider ) || !$dns_provider ) {
			$error = "Select DNS Provider";
		}
		
		
		if( !empty( $error ) ) {
			$result = array( 'success' => false, 'error' => $error );
		}
		
		return $result;
	}
	
	
	/**
	 * Handle delete item action
	 */
	public function ajax_handle_delete_item() {
		
		$model_id = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT );
		
		$view  = sanitize_text_field( filter_input( INPUT_POST, 'view', FILTER_UNSAFE_RAW ) );
		$view = $view ? $view : 'public';
		
		$error = "";
		$action = 'delete';
		
		if( !$this->verify_nonce( $action ) || !$this->check_action_permission( 'delete', $model_id, null, $view ) ) {
			$error = $this->default_permission_error( $action );
		} else {
			
			$dns_zone = WPCD_MB_Custom_Table::get('dns_zone');
			$zone_record = WPCD_MB_Custom_Table::get('dns_zone_record');
			
			$zones = $dns_zone->api->get_items_by_parent_id( $model_id );
			
			foreach( $zones as $zone ) {
				$zone_record->api->delete_items_by_parent( $zone->ID );
			}
			
			$dns_zone->api->delete_items_by_parent( $model_id );
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
	
}
