<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class handle DNS Zone Record functionality
 */
class WPCD_CT_DNS_Zone_Record extends WPCD_MB_Custom_Table {
	
	/**
	 * Model slug
	 * 
	 * @var string
	 */
	public $model_name = 'dns_zone_record';
	
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
		
		add_action('wp_ajax_wpcd_ct_zone_records_table_view', array( $this, 'ajax_child_items_table_view' ));
		add_action( "rwmb_after_{$this->inline_edit_metabox()}", array( $this, 'inline_edit_metabox_add_nonce_field' ) );
	}	
	
	/**
	 * Register custom table model
	 */
	public function register_model() {
		
		mb_register_model( $this->get_model_name(), [
			'table'  => $this->get_table_name(),
			'labels' => [
				'name'          => 'DNS Zones Records',
				'singular_name' => 'DNS Zone Record',
			],
			'parent'      => 'edit.php?post_type=wpcd_app_server',
			'position'    => 80,
			'capability'  => 'wpcd_manage_settings',
			'show_in_menu'	=> false
		] );
	}
	
	/**
	 * Create db table for model
	 */
	public function create_table() {
		
		$columns = array(
			'parent_id'			=> 'int(11) NOT NULL',
			'type'				=> 'varchar(200) NOT NULL',
			'name'				=> 'varchar(200) DEFAULT NULL',
			'content'			=> 'text DEFAULT NULL',
			'ttl'				=> 'varchar(200) DEFAULT NULL',
			'notes'				=> 'text NULL'
		);
		
		MB_Custom_Table_API::create( $this->get_table_name(), $columns, [], true );
	}
	
	
	/**
	 * Return empty container for zone records view
	 * 
	 * @return string
	 */
	function zone_records_table_view_container() {
		ob_start();
		
		$action = "wpcd_ct_zone_records_table_view";
		$view = $this->get_view();
		
		$provider_id = rwmb_request()->get('model-id');
		$id = "wpcd_ct_zone_records_table_container";
		printf( 
				'<div class="wpcd_ct_table_container" id="%s" data-action="%s" data-provider_id="%s" data-view="%s" data-nonce="%s">', 
				$id, 
				$action, 
				$provider_id, 
				$view,
				$this->get_view_nonce( $view, 'dns_zone_records' )
				);
		
		$this->add_item_link();
		printf('<div class="wpcd-ct-notices"></div>' );
		
		printf('<div class="wpcd_ct_items_table_view_container"></div>' );
		echo '</div>';
		return ob_get_clean();
	}
	
	/**
	 * Return table view content
	 */
	public function ajax_child_items_table_view() {
		
		$view_type = rwmb_request()->get('view');
		$provider_id = rwmb_request()->get('provider_id');
		$zone = rwmb_request()->get('zone');
		$nonce = rwmb_request()->get('nonce');
		
		
		$can_add_item = false;
        $error = '';
        $items = '';
		
		$dns_provider = WPCD_MB_Custom_Table::get('dns_provider');
		if( !$this->verify_view_nonce( $view_type, 'dns_zone_records', $nonce ) ) {
			$error = 'Error getting zone records, try again later.';
		} elseif( !$dns_provider->user_can_edit( $provider_id ) ) {
			$error = 'You are not allowed to view zone records';
		}
		
		if( !$error ) {
			if( $view_type == 'public' ) {
				$items = $this->display_public_child_items_table( $zone );
			} else {
				$items = $this->display_child_items_table( $zone );
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
		
		$action = "wpcd_{$this->get_model_name()}_inline_add";
		$view = $this->get_view();
		printf(
				'<a href="#" class="mp_edit_inline wpcd-button wpcd-ct-add-item-link" data-action="%s" data-view="%s" data-nonce="%s">%s</a>', 
				$action, 
				$view,
				$this->get_view_nonce( $view, 'add_dns_zone_record' ),
				'Add Zone Record' 
				);
	}
	
	
	/**
	 * show parent id metabox field while adding/editing zone record
	 * 
	 * @return string
	 */
	function parent_id_mb_fields() {
		$dns_zone = WPCD_MB_Custom_Table::get( 'dns_zone' );
		$dns_zone_id = rwmb_request()->get('parent-id');
		$selected_zone = $dns_zone->api->get_item_display_name( $dns_zone_id );
		$label = __( 'DNS Zone', 'wpcd' );
		
		
		return 
		array(
			array(
				'name' => $label,
				'id'   => 'parent_id',
				'type' => 'hidden',
				'std' => $dns_zone_id,
				'admin_columns' => [
					'position' => 'after id',
				],
			),
			
			array(
				'type' => 'custom_html',
				'std'	=> sprintf('<div class="rwmb-field rwmb-select-wrapper"><div class="rwmb-label"><label for="parent_id">%s</label></div><div class="rwmb-input">%s</div></div>', $label,  $selected_zone )
			)
		);
	}
	
	
	/**
	 * Return list of zone record types
	 * 
	 * @return array
	 */
	public function types_list() {
		
		$types = array(
			'A'		=> 'A',
			'AAAA'  => 'AAAA',
			'CNAME' => 'CNAME',
			'TXT'	=> 'TXT',
			'NS'	=> 'NS'
		);
		
		return apply_filters('wpcd_zone_record_types', $types );
	}
	
	/**
	 * Metabox id for main form
	 * 
	 * @return string
	 */
	function inline_edit_metabox() {
		return 'dns_provider_zone_record_mb';
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
				'name' => __( 'Type', 'wpcd' ),
				'id'   => 'type',
				'type' => 'select',
				'options' => $this->types_list(),
				'desc' => __('Select record type', 'wpcd' ),
				'admin_columns' => [
					'position' => 'after parent_id',
				],
				'required' => true
			),
			array(
				'name' => __( 'name', 'wpcd' ),
				'id'   => 'name',
				'type' => 'text',
				'desc' => __('Enter name (e.g. example.com)', 'wpcd' ),
				'admin_columns' => [
					'position' => 'after type',
				],
				'required' => true
			),

			array(
				'name' => __( 'Contents', 'wpcd' ),
				'id'   => 'content',
				'type' => 'text',
				'desc' => __('Enter record content', 'wpcd' ),
				'required' => true
			),
			array(
				'name' => __( 'TTL', 'wpcd' ),
				'id'   => 'ttl',
				'type' => 'text',
				'desc' => __('Enter TTL in seconds', 'wpcd' ),
				'admin_columns' => [
					'position' => 'after name',
				],
				'required' => true
			),
			array(
				'name' => __( 'Notes', 'wpcd' ),
				'id'   => 'notes',
				'type' => 'textarea',
				'class'			=> '',
				'desc' => __('Enter notes', 'wpcd' ),
			)
		);
		
		$fields = array_merge( $this->parent_id_mb_fields() , $fields );
		
		 
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
	 * Check permission for add/edit item form
	 * 
	 * @param string $action
	 * @param int|null $zone_id
	 * @param int|null $zone_record_id
	 * 
	 * @return array
	 */
	function add_edit_form_permissions( $action, $zone_id = null, $zone_record_id = null ) {
		
		
		$has_access = false;
		$message = '';
		
		if( $action == 'edit' || $action == 'delete' ) {
			$item = $this->api->get_by_id( $zone_record_id );
			if( $item ) {
				$zone_id = $item->parent_id;
			}
		}
		
		
		$dns_zone = WPCD_MB_Custom_Table::get('dns_zone');
		$dns_provider = WPCD_MB_Custom_Table::get('dns_provider');
		
		
		$zone_item = null;
		$provider_id = null;
		
		if( $zone_id ) {
			$zone_item = $dns_zone->api->get_by_id( $zone_id );
		}
		
		if( $zone_item ) {
			$provider_id = $zone_item->parent_id;
			if( $provider_id && $dns_provider->user_can_edit( $provider_id ) ) {
				$has_access = true;
			}
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
		
		$zone_id		= filter_input( INPUT_POST, 'parent_id',  FILTER_SANITIZE_NUMBER_INT );
		$zone_record_id	= filter_input( INPUT_POST, 'model-id', FILTER_SANITIZE_NUMBER_INT );
		
		$type	  = filter_input( INPUT_POST, 'type',		FILTER_SANITIZE_STRING );
		$name	  = filter_input( INPUT_POST, 'name',		FILTER_SANITIZE_STRING );
		$content  = filter_input( INPUT_POST, 'content',	FILTER_SANITIZE_STRING );
		$ttl	  = filter_input( INPUT_POST, 'ttl',		FILTER_SANITIZE_STRING );
		
		$error = '';
		
		$result = array( 'success' => true, 'error' => '' );
		
		$permissions = $this->add_edit_form_permissions( $action, $zone_id, $zone_record_id );
		
		if( !$this->verify_nonce( $action ) ) {
			$error = 'Error while saving zone record.';
		} 
		elseif( !$permissions['has_access'] ) {
			$error = $permissions['message'];
		}
		
		elseif( empty( $type ) || !$type ) {
			$error = "Type is required";
		} elseif( empty( $name ) || !$name ) {
			$error = "Name is required";
		} elseif( empty( $content ) || !$content ) {
			$error = "Content is required";
		} elseif( empty( $ttl ) || !$ttl ) {
			$error = "TTL is required";
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
		
		$action = 'delete';
		$zone_record_id = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT );
		$permissions = $this->add_edit_form_permissions( $action, null, $zone_record_id );
		
		$error = '';
		if( !$this->verify_nonce( $action ) ) {
			$error = 'Error while deleting zone record.';
		} 
		elseif( !$permissions['has_access'] ) {
			$error = $permissions['message'];
		} else {
			$this->api->delete_item( $zone_record_id );
		}
		
		if( !empty( $error ) ) {
			$result = array( 'success' => false, 'error' => sprintf('<div class="notice notice-error"><p>%s</p></div>', $error ) );
		} else {
			$result = array( 'success' => true, 'error' => '' );
		}
		
		wp_send_json( $result );
		die();
	}
	
	/**
	 * Return display name of parent item
	 * 
	 * @param int $parent_id
	 * 
	 * @return string
	 */
	public function get_parent_name( $parent_id ) {
		$dns_zone = WPCD_MB_Custom_Table::get( 'dns_zone' );
		return $dns_zone->api->get_item_display_name( $parent_id );
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
	
}
