<?php

/**
 * Class DNS Zone API
 */
class WPCD_CT_DNS_Zone_API extends WPCD_Custom_Table_API {
	
	/**
	 * Model Name
	 * 
	 * @var string
	 */
	public $model_name = 'dns_zone';
	
	/**
	 * Table Name
	 * 
	 * @var string 
	 */
	public $table_name = 'dns_zones';
	
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
	 * Return default DNS Zone
	 * 
	 * @global object $wpdb
	 * 
	 * @return array
	 */
	function get_default() {
		global $wpdb;
		
		$q = 'SELECT * FROM ' . $this->get_table_name() . ' WHERE is_system_default=1';
		$results = $wpdb->get_row( $q, 'ARRAY_A' );
		
		return $results;
	}
	
	/**
	 * Clear default zone mark
	 * 
	 * @global object $wpdb
	 * 
	 * @param int $item_id
	 */
	function maybe_mark_default( $item_id ) {
		$zone = $this->get_by_id( $item_id );

		if( $zone && $zone->is_system_default ) {
			global $wpdb;
			$q = 'UPDATE ' . $this->get_table_name() . ' SET is_system_default = 0 WHERE ID != %d';
			$wpdb->query( $wpdb->prepare( $q, $item_id ) );
		}
	}
	
	/**
	 * Return domain name
	 * 
	 * @param int $item_id
	 * 
	 * @return string
	 */
	public function get_item_display_name( $item_id ) {
		$item = $this->get_by_id( $item_id );
		$name = '';
		if( $item ) {
			$name = $item->domain;
		}
		
		return $name;
	}
	
	/**
	 * Return joins query to get items with permission
	 * 
	 * @global object $wpdb
	 * 
	 * @param int|null $user_id
	 * 
	 * @return string
	 */
	public function permission_query_join( $user_id = null ) {
		global $wpdb;
		
		$dns_provider_api = WPCD_Custom_Table_API::get('dns_provider');
		$meta_table = $this->get_meta_table_name();
		
		$q .= ' INNER JOIN wp_wpcd_ct_dns_providers p ON p.ID = t1.parent_id 
			    LEFT JOIN '.$meta_table.' m ON m.item_id = t1.parent_id AND m.model = %s';
		
		return $wpdb->prepare( $q, $dns_provider_api->get_model_name() );
	}
	
	/**
	 * Get all dns zones with user permission
	 * 
	 * @global object $wpdb
	 * 
	 * @param array $args
	 * 
	 * @param int|null $user_id
	 * 
	 * @return array
	 */
	public function get_items_by_permission( $args = array(), $user_id = null ) {
		global $wpdb;
		
		$table = $this->get_table_name();
		$q = 'SELECT t1.* FROM '.$table.' t1';
		
		$should_add = $this->should_add_permission_query( $user_id );
		
		$joins = '';
		$where = '';
		
		if( 3 === $should_add ) {
			return array();
		} elseif ( 2 == $should_add ) {
			$joins = $this->permission_query_join( $user_id );
			$where = 'WHERE ' . $this->permission_where_clause( $user_id );
		}
		
		$group_by =   ' GROUP BY t1.ID';
		
		return $wpdb->get_results( $q . $joins . $where . $group_by, 'ARRAY_A' );
	}
	
	/**
	 * Get dns zone by dns provider with user permission
	 * 
	 * @global object $wpdb
	 * 
	 * @param int $parent_id
	 * @param array $args
	 * @param int|null $user_id
	 * 
	 * @return array
	 */
	public function get_child_items_by_permission( $parent_id, $args = array(), $user_id = null ) {
		global $wpdb;
		
		$args = $this->prepare_listing_args( $args );
		$table = $this->get_table_name();
		$q = 'SELECT t1.* FROM '.$table.' t1';
		$should_add = $this->should_add_permission_query( $user_id );
		
		$joins = '';
		$where = '';
		
		if( 3 === $should_add ) {
			return array();
		} elseif ( 2 == $should_add ) {
			$joins = $this->permission_query_join( $user_id );
			$where = ' AND (' . $this->permission_where_clause( $user_id, true ) . ')';
		}
		$where =  $wpdb->prepare( ' WHERE t1.parent_id = %d ', $parent_id ) . $where . ' GROUP BY t1.ID';
		$limit = $this->prepare_query_limit( $args['limit'], $args['page'] );
		
		return $this->get_listing_results( compact( 'q' , 'joins' , 'where' , 'limit' ), $args );
	}
	
}