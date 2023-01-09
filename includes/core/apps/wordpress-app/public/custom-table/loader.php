<?php

use MetaBox\CustomTable\Loader;
use MetaBox\CustomTable\Cache;

class WPCD_CT_Public_Loader extends Loader {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		
		add_action( 'init', 'mb_admin_columns_load', 200 );
		
		add_shortcode( 'wpcd_ct_providers', array( $this, 'wpcd_providers' ) );
		add_shortcode( 'wpcd_ct_dns_providers', array( $this, 'wpcd_dns_providers' ) );
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 10, 1 );
		add_filter( 'wpcd_is_public_page', array( $this, 'is_public_page' ) );
	}
	
	/**
	 * Return metabox class name for custom table
	 * 
	 * @param string $class_name
	 * @param array $args
	 * 
	 * @return string
	 */
	public function meta_box_class_name( $class_name, $args ) {
		
		if ( isset( $args['models'] ) && !empty( $args['models'] ) ) {
			$class_name = 'RW_Meta_Box_Custom_Table_Public';
			require_once wpcd_path . 'includes/core/apps/wordpress-app/public/custom-table/rw-meta-box-custom-table-public.php';
		}
		return $class_name;
	}
	
	/**
	 * Enqueue public resources
	 */
	function enqueue() {
		
		if( $this->check_is_custom_table_public_page() ) {
			wp_enqueue_script( 'wpcd-custom_table', wpcd_url . 'assets/js/custom_table.js', array( 'jquery', 'wpcd-magnific' ), wpcd_scripts_version, true );
		}
	}
	
	/**
	 * Check is public custom table page
	 * 
	 * @return boolean
	 */
	function check_is_custom_table_public_page( ) {
		return $this->is_public_page( false );
	}
	
	/**
	 * Check is public custom table page
	 * 
	 * @param boolean $is_public_page
	 * 
	 * @return boolean
	 */
	function is_public_page( $is_public_page ) {
		
		if( !$is_public_page ) {
		
			$provider = WPCD_MB_Custom_Table::get('provider');
			$dns_provider = WPCD_MB_Custom_Table::get('dns_provider');
			
			$is_public_page = ( !is_admin() && $provider->is_custom_table_page() || $dns_provider->is_custom_table_page() );
		}
		
		return $is_public_page;
	}
	
	
	/**
	 * Return page shortcode content
	 * 
	 * @param string $model
	 * 
	 * @return string
	 */
	public function get_shortcode_page_content( $model ) {
		$custom_table = WPCD_MB_Custom_Table::get( $model );
		
		if( !$custom_table ) {
			return;
		}
		
		$permission = $custom_table->check_main_page_permission();
			
		ob_start();
		
		if( $permission['has_access'] ) {
			
			if( $custom_table->is_item_add_screen() || $custom_table->is_item_edit_screen() ) {
				$custom_table->display_public_edit_screen();
			} else {
				$custom_table->display_public_table();
			}

		} else {
			echo $permission['message'];
		}
		
		$content = ob_get_clean();
		return '<div id="wpcd_public_wrapper">' . $content . '</div>';
	}
	
	/**
	 * Return providers shortcode content
	 * 
	 * @param array $args
	 * 
	 * @return string
	 */
	public function wpcd_providers( $args ) {
		return $this->get_shortcode_page_content('provider');
	}
	
	/**
	 * Return dns providers shortcode content
	 * 
	 * @param array $args
	 * 
	 * @return string
	 */
	public function wpcd_dns_providers( $args ) {
		return $this->get_shortcode_page_content('dns_provider');
	}
	
	/**
	 * 
	 * @global object $wpdb
	 * @param int $object_id
	 */
	public function update_object_data( $object_id ) {
		
		global $wpdb;
		
		$object_type = $this->_get_saved_object_type();
		$meta_boxes  = $this->_get_meta_boxes_for( $object_type, $object_id );
		
		foreach ( $meta_boxes as $meta_box ) {
			$storage = $meta_box->get_storage();
			$row     = Cache::get( $object_id, $meta_box->table );
			$row     = array_map( [ $this, '_maybe_serialize' ], $row );

			$has_data = $this->_has_data( $row );
			if ( ! $has_data ) {
				$storage->delete_row( $object_id );
				continue;
			}

			if ( $storage->row_exists( $object_id ) ) {
				$storage->update_row( $object_id, $row );
				continue;
			}

			if ( $object_type === 'model' ) {
				$storage->insert_row( $row );
				$object_id = $wpdb->insert_id; // Ensure next meta box update the data of the same inserted object.
			} else {
				$row['ID'] = $object_id; // Must set to connect to existing WP objects.
				$storage->insert_row( $row );
			}
		}
		
	}
	
	/**
	 * Return object type
	 * 
	 * @global array $wp_current_filter
	 * 
	 * @return string
	 */
	private function _get_saved_object_type() {
		global $wp_current_filter;

		foreach ( $wp_current_filter as $hook ) {
			if ( 'edit_comment' === $hook ) {
				return 'comment';
			}
			if ( 'profile_update' === $hook || 'user_register' === $hook ) {
				return 'user';
			}
			if ( 0 === strpos( $hook, 'edited_' ) || 0 === strpos( $hook, 'created_' ) ) {
				return 'term';
			}
			if ( 'mbct_model_edit_load' === $hook ) {
				return 'model';
			}
		}
		return 'post';
	}
	
	/**
	 * search metaboxes
	 * 
	 * @param string $object_type
	 * @param string $object_id
	 * 
	 * @return array
	 */
	private function _get_meta_boxes_for( $object_type, $object_id ) {
		$meta_boxes = rwmb_get_registry( 'meta_box' )->get_by( [
			'storage_type' => 'custom_table',
			'object_type'  => $object_type,
		] );
		
		
		if ( 'user' === $object_type ) {
			return $meta_boxes;
		}

		array_walk( $meta_boxes, [ $this, '_check_type' ], [ $object_type, $object_id ] );
		$meta_boxes = array_filter( $meta_boxes );
		return $meta_boxes;
	}
	
	/**
	 * 
	 * @param array $row
	 * 
	 * @return boolean
	 */
	private function _has_data( $row ) {
		if ( ! $row ) {
			return false;
		}

		unset( $row['ID'] );

		foreach ( $row as $value ) {
			if ( ! in_array( $value, ['', null, []] ) ) {
				return true;
			}
		}

		return false;
	}
	
	/**
	 * Check metabox type
	 * 
	 * @global string $wpcd_ct_current_main_page_model
	 * @param array $meta_box
	 * @param string $key
	 * @param array $object_data
	 * 
	 * @return boolean
	 */
	private function _check_type( &$meta_box, $key, $object_data ) {
		global $wpcd_ct_current_main_page_model;
		
		list( $object_type, $object_id ) = $object_data;

		$type = null;
		$prop = null;
		switch ( $object_type ) {
			case 'post':
				$type = get_post_type( $object_id );
				if ( 'revision' === $type ) {
					return;
				}
				$prop = 'post_types';
				break;
			case 'term':
				$type = $object_id;
				$term = get_term( $object_id );
				$type = is_object( $term ) ? $term->taxonomy : null;
				$prop = 'taxonomies';
				break;
			case 'model':
				$type = $wpcd_ct_current_main_page_model;
				$prop = 'models';
				break;
		}
		
		
		if ( ! $type || ! in_array( $type, $meta_box->meta_box[ $prop ], true ) ) {
			$meta_box = false;
		}
	}
	
	/**
	 * Serialize data
	 * 
	 * @param array $data
	 * 
	 * @return string
	 */
	private function _maybe_serialize( $data ) {
		return is_array( $data ) ? serialize( $data ) : $data;
	}
}