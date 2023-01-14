<?php

/**
 * Class DNS Record API
 */
class WPCD_CT_DNS_Zone_Record_API extends WPCD_Custom_Table_API {
	
	/**
	 * Model Name
	 * 
	 * @var string
	 */
	public $model_name = 'dns_zone_record';
	
	/**
	 * Table Name
	 * 
	 * @var string 
	 */
	public $table_name = 'dns_zone_records';
	
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
	 * Return joins query to get items with permission
	 * 
	 * @global object $wpdb
	 * 
	 * @param int|null $user_id
	 * 
	 * @return string
	 */
	public function permission_query_join( $user_id = null, $item_id = '' ) {
		global $wpdb;
		
		$dns_provider_api = WPCD_Custom_Table_API::get('dns_provider');
		$meta_table = $this->get_meta_table_name();
		
		$q = ' INNER JOIN ' . $dns_provider_api->get_table_name() . ' p ON p.ID = zone.parent_id 
			   LEFT JOIN '.$meta_table.' m ON m.item_id = p.ID AND m.model = %s';

		return $wpdb->prepare( $q, $dns_provider_api->get_model_name() );
	}
	

	/**
	 * Get dns zone records by dns zone with user permission
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
		
		if( !$parent_id ) {
			return array();
		}
		
		$args = $this->prepare_listing_args( $args );
		
		$table = $this->get_table_name();
		$q = 'SELECT t1.* FROM '.$table.' t1';
		
		$should_add = $this->should_add_permission_query( $user_id );
		
		$joins = '';
		
		if( 3 === $should_add ) {
			return array();
		} elseif ( 2 == $should_add ) {
			
			$dns_zone_table = WPCD_MB_Custom_Table::get('dns_zone')->get_table_name();
			$joins = $wpdb->prepare( " INNER JOIN {$dns_zone_table} as zone on t1.parent_id = zone.id and zone.id = %d", $parent_id );
			
			$joins .= $this->permission_query_join();
			$where = ' AND (' . $this->permission_where_clause( $user_id, true ) . ')';
		}
		
		$where =  $wpdb->prepare( ' WHERE t1.parent_id = %d ', $parent_id ) . $where . ' GROUP BY t1.ID';
		$limit = '';

		return $this->get_listing_results( compact( 'q' , 'joins' , 'where' , 'limit' ), $args );
	}
	
}