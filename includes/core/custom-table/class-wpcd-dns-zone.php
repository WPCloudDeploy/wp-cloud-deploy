<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class handle DNS Zone functionality
 */
class WPCD_CT_DNS_Zone extends WPCD_MB_Custom_Table {
	
	
	/**
	 * Model slug
	 * 
	 * @var string
	 */
	public $model_name = 'dns_zone';
	
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
		
		add_filter( "wpcd_mbct_{$this->model->name}_child_table_row_actions", array( $this, 'row_actions' ), 10, 2 );
		add_action('wp_ajax_wpcd_ct_provider_zones_table_view', array( $this,  'provider_zones_table_view' ) );
		add_action( "rwmb_after_{$this->inline_edit_metabox()}", array( $this, 'inline_edit_metabox_add_nonce_field' ) );
	}
	
	/**
	 * Register custom table model
	 */
	public function register_model() {
		
		mb_register_model( $this->get_model_name(), [
			'table'  => $this->get_table_name(),
			'labels' => [
				'name'          => 'DNS Zones',
				'singular_name' => 'DNS Zone',
			],
			'parent'      => 'edit.php?post_type=wpcd_app_server',
			'position'    => 82,
			'capability'  => 'wpcd_manage_settings',
			'show_in_menu'	=> false
		] );
		
	}
	
	/**
	 * Create db table for model
	 */
	public function create_table() {
		$columns = array(
			'parent_id'				=> 'int(11) NOT NULL',
			'domain'				=> 'varchar(200) NOT NULL',
			'is_system_default'		=> 'varchar(1) DEFAULT NULL',
		);
		
		MB_Custom_Table_API::create( $this->get_table_name(), $columns, [], true );
	}
	
	/**
	 * Metabox id for main form
	 * 
	 * @return string
	 */
	function inline_edit_metabox() {
		return 'dns_provider_zone_mb';
	}
	
	/**
	 * Main function for registering metabox.
	 * 
	 * @param array $meta_boxes
	 * 
	 * @return array
	 */
	public function metaboxes( $meta_boxes ) {
		
		$fields = array(
			array(
				'name' => __( 'Domain', 'wpcd' ),
				'id'   => 'domain',
				'type' => 'text',
				'desc' => __('Enter domain', 'wpcd' ),
				'admin_columns' => [
					'position' => 'after parent_id'
				],
				'required' => true
			),
			array(
				'name' => __( 'Is System Default', 'wpcd' ),
				'id'   => 'is_system_default',
				'type' => 'checkbox',
				'desc' => __('', 'wpcd' ),
				'admin_columns' => [
					'position' => 'after domain'
				],
			)
		);
		
		$fields = array_merge( $this->parent_id_mb_fields(), $fields );
		
		$meta_boxes[] = array(
			'id'				=> $this->inline_edit_metabox(),
			'title'				=> __( 'General', 'wpcd' ),
			'models'			=> [ $this->get_model_name() ],
			'storage_type'		=> 'custom_table',
			'table'				=> $this->get_table_name(),
			'fields'			=> $fields
		);
		
		return $meta_boxes;
	}
	
	/**
	 * show parent id metabox field while adding/editing dns zone
	 * 
	 * @return string
	 */
	function parent_id_mb_fields() {
		
		$dns_provider = WPCD_MB_Custom_Table::get( 'dns_provider' );
		$dns_provider_id = rwmb_request()->get('parent-id');
		$selected_provider_name = $dns_provider->api->get_item_display_name( $dns_provider_id );
		
		$label = __( 'DNS Provider', 'wpcd' );
		
		return
		array(
			
			array(
			
				'name' => $label,
				'id'   => 'parent_id',
				'type' => 'hidden',
				'std' => $dns_provider_id,
				'admin_columns' => [
					'position' => 'after id'
				],
			),
			
			array(
				'type' => 'custom_html',
				'std'	=> sprintf('<div class="rwmb-field rwmb-select-wrapper"><div class="rwmb-label"><label for="parent_id">%s</label></div><div class="rwmb-input">%s</div></div>', $label,  $selected_provider_name )
			)
		);
	}
	
	
	/**
	 * Add records row action on zones table
	 * 
	 * @param array $actions
	 * @param array $item
	 * 
	 * @return array
	 */
	function row_actions( $actions, $item ) {
		$zone = isset( $item['ID'] ) ? $item['ID'] : '';
		$dns_provider = isset( $item['parent_id'] ) ? $item['parent_id'] : '';
		
		$actions['records'] = sprintf('<a href="#" data-zone="%d" data-dns_provider="%s" data-nonce="%s" class="wpcd_ct_load_dns_record_btn">Records</a>', $zone, $dns_provider, $this->get_view_nonce('view', 'records') );
		
		return $actions;
	}
	
	/**
	 * Handle default zone
	 * 
	 * @param int $item_id
	 */
	function after_save_metabox( $item_id ) {
		$this->api->maybe_mark_default( $item_id );
	}
	
	/**
	 * Check permission for add/edit item form
	 * 
	 * @param string $action
	 * @param int|null $provider_id
	 * @param int|null $zone_id
	 * 
	 * @return array
	 */
	function add_edit_form_permissions( $action, $provider_id = null, $zone_id = null ) {
		
		
		$has_access = false;
		$message = '';
		
		if( $action == 'edit'  || $action == 'delete') {
			$item = $this->api->get_by_id( $zone_id );
			if( $item ) {
				$provider_id = $item->parent_id;
			}
			
		}
		
		$dns_provider = WPCD_MB_Custom_Table::get('dns_provider');
		
		if( $provider_id && $dns_provider->user_can_edit( $provider_id ) ) {
			$has_access = true;
		}
		
		if( !$has_access ) {
			$message = $this->default_permission_error( $action );
		}
		
		return array( 'has_access' => $has_access, 'message' => $message );
	}
	
	/**
	 * Validate data for add, edit action
	 * 
	 * @param string $action
	 * 
	 * @return array
	 */
	public function validate_data( $action ) {
		
		
		$provider_id	= filter_input( INPUT_POST, 'parent_id', FILTER_SANITIZE_NUMBER_INT );
		$zone_id		= filter_input( INPUT_POST, 'model-id',  FILTER_SANITIZE_NUMBER_INT );
		$domain			= filter_input( INPUT_POST, 'domain',  FILTER_SANITIZE_STRING );
		
		$error = '';
		$result = array( 'success' => true, 'error' => '' );
		
		$permissions = $this->add_edit_form_permissions( $action, $provider_id, $zone_id );
		
		if( !$this->verify_nonce( $action ) ) {
			$error = 'Error while saving dns zone.';
		} 
		elseif( !$permissions['has_access'] ) {
			$error = $permissions['message'];
		}
		
		elseif( empty( $domain ) || !$domain ) {
			$error = "Domain is required";
		} 
		
		if( !empty( $error ) ) {
			$result = array( 'success' => false, 'error' => $error );
		}
		
		return $result;
	}
	
	/**
	 * Return display name of parent item
	 * 
	 * @param int $parent_id
	 * 
	 * @return string
	 */
	public function get_parent_name( $parent_id ) {
		$dns_provider = WPCD_MB_Custom_Table::get( 'dns_provider' );
		return $dns_provider->api->get_item_display_name( $parent_id );
	}
	
	/**
	 * Get all zone records by zone id
	 * 
	 * @param int $parent_id
	 * @param array $args
	 * @param int|null $user_id
	 * 
	 * @return array
	 */
	public function get_items_by_parent( $parent_id, $args = array(), $user_id = null ) {
		return $this->api->get_child_items_by_permission( $parent_id, $args, $user_id );
	}
	
	
	/**
	 * Return empty container for dns zones view
	 * 
	 * @return string
	 */
	function provider_zones_table_view_container() {
		ob_start();
		
		$action = "wpcd_ct_provider_zones_table_view";
		
		$view = $this->get_view();
		
		$provider_id = rwmb_request()->get('model-id');
		$id = "wpcd_ct_provider_zones_table_container";
		printf( 
				'<div class="wpcd_ct_table_container" id="%s" data-action="%s" data-provider_id="%s" data-view="%s" data-nonce="%s">', 
				$id, 
				$action, 
				$provider_id, 
				$view, 
				$this->get_view_nonce( $view, 'dns_provider_zones' )
				);
		
		printf('<div class="wpcd-ct-notices"></div>' );
		$this->add_item_link();
		printf('<div class="wpcd_ct_items_table_view_container"></div>' );
		
		
		echo '</div>';
		
		return ob_get_clean();
	}
	
	/**
	 * Return table view content
	 */
	function provider_zones_table_view() {
		
		$view_type   = rwmb_request()->get('view');
		$provider_id = rwmb_request()->get('provider_id');
		
		$nonce = rwmb_request()->get('nonce');
		
		$dns_provider = WPCD_MB_Custom_Table::get('dns_provider');
		
		$can_add_item = false;
        $error = '';
        $items = '';
		
		if( !$this->verify_view_nonce( $view_type, 'dns_provider_zones', $nonce ) ) {
			$error = 'Error getting zones, try again later.';
		} elseif( !$dns_provider->user_can_edit( $provider_id ) ) {
			$error = 'You are not allowed to view provider zones';
		}
		
		
		if( !$error ) {
			if( $view_type == 'public' ) {
				$items = $this->display_public_child_items_table( $provider_id );
			} else {
				$items = $this->display_child_items_table( $provider_id );
			}
			$can_add_item = true;
		}
		
		
		$result = array(
			'can_add_item'  => $can_add_item,
            'error'			=> ( !empty( $error ) ? sprintf( '<div class="notice notice-error">%s</div>', $error ) : '' ),
            'items'			=> $items
		);
		
		wp_send_json( $result );
		die();
	}
	
	/**
	 * Print add item tag element
	 */
	public function add_item_link() {
		
		$view = $this->get_view();
		$add_item_url = add_query_arg( [
				'action' =>  "wpcd_{$this->get_model_name()}_inline_add",
				'parent-id'	=> rwmb_request()->get('model-id'),
				'view'=> $view ,
				'nonce'=> $this->get_view_nonce( $view, "add_{$this->get_model_name()}" ),
			], admin_url( "admin-ajax.php" ) );

		printf('<a href="%s" class="mp_edit_inline wpcd-button wpcd-ct-add-item-link">%s</a>', $add_item_url, 'Add new Zone' );
	}
	
	/**
	 * Handle delete item action
	 */
	public function ajax_handle_delete_item() {
		
		$zone_id = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT );
		//$view_type = filter_input( INPUT_POST, 'view', FILTER_SANITIZE_STRING );
			
		$action = 'delete';
		$permissions = $this->add_edit_form_permissions( $action, null, $zone_id );
		
		$error = '';
		if( !$this->verify_nonce( $action ) ) {
			$error = 'Error while deleting dns zone.';
		} 
		elseif( !$permissions['has_access'] ) {
			$error = $permissions['message'];
		} else {
			$zone_record = WPCD_MB_Custom_Table::get( 'dns_zone_record' );
			$zone_record->api->delete_items_by_parent( $zone_id );
			$this->api->delete_item( $zone_id );
		}
		
		
		if( !empty( $error ) ) {
			$result = array( 'success' => false, 'error' => sprintf('<div class="notice notice-error"><p>%s</p></div>', $error ) );
		} else {
			$result = array( 'success' => true, 'error' => '', 'unload_child_table' => '#wpcd_ct_zone_records_table_container' );
		}
		
		wp_send_json( $result );
		die();
	}
	
}
