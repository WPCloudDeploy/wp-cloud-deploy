<?php


abstract class WPCD_Custom_Table_API {
	
	/**
	 * Model Name
	 * 
	 * @var string
	 */
	public $model_name = '';
	
	
	public $child_model_name = '';
	public $parent_model_name = '';
	
	const META_TABLE_NAME = 'wpcd_ct_model_meta';

	/**
	 * Constructor method
	 */
	public function __construct() {}
	
	
	/**
	 * Get instance of api class by model name
	 * 
	 * @param string $model_name
	 * 
	 * @return object
	 */
	public static function get( $model_name ) {
		
		$class_name_prefix = 'WPCD_CT_';
		$class_name = $class_name_prefix . ucwords( $model_name, '_' ) . '_API';
		
		if( class_exists( $class_name ) ) {
			return $class_name::instance();
		}
		return null;
	}
	
	/**
	 * Return model name
	 * 
	 * @return string
	 */
	public function get_model_name() {
		return $this->model_name;
	}
	
	/**
	 * Return table name
	 * 
	 * @global object $wpdb
	 * 
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return "{$wpdb->prefix}wpcd_ct_{$this->table_name}";
	}
	
	/**
	 * Return meta table name
	 * 
	 * @global object $wpdb
	 * 
	 * @return string
	 */
	public static function get_meta_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::META_TABLE_NAME;
	}
	
	/**
	 * Get item by item id
	 * 
	 * @global object $wpdb
	 * 
	 * @param int $item_id
	 * 
	 * @return array
	 */
	public function get_by_id( $item_id ) {
		global $wpdb;
		
		$q = 'SELECT * FROM ' . $this->get_table_name() . ' WHERE ID=%d';
		return $wpdb->get_row( $wpdb->prepare( $q, $item_id ) );
	}
	
	/**
	 * Get items by parent id
	 * 
	 * @global object $wpdb
	 * 
	 * @param int $parent_id
	 * @param string $output
	 * 
	 * @return array
	 */
	function get_items_by_parent_id( $parent_id, $output = OBJECT ) {
		global $wpdb;
		
		$q = 'SELECT * FROM ' . $this->get_table_name() . ' WHERE parent_id=%d';
		$results = $wpdb->get_results( $wpdb->prepare( $q, $parent_id ), $output );
		
		return $results;
	}
	
	/**
	 * Delete an item by item id
	 * 
	 * @global object $wpdb
	 * 
	 * @param int $item_id
	 */
	public function delete_item( $item_id ) {
		global $wpdb;
		$wpdb->delete( $this->get_table_name(), array( 'ID' => $item_id ), array( '%d' ) );
	}
	
	/**
	 * Delete child items by parent id;
	 * 
	 * @global object $wpdb
	 * 
	 * @param int $parent_id
	 */
	function delete_items_by_parent( $parent_id ) {
		global $wpdb;
		$wpdb->delete( $this->get_table_name(), [ 'parent_id' => $parent_id ], ['%d'] );
	}
	
	/**
	 * Return meta values as array from meta table
	 * 
	 * @global object $wpdb
	 * 
	 * @param int $item_id
	 * @param string $key
	 * 
	 * @return array
	 */
	public function get_meta_values( $item_id, $key ) {
		global $wpdb;
		
		$table = $this->get_meta_table_name();
		
		$q = "SELECT * FROM $table WHERE item_id=%d AND model=%s AND meta_key=%s";
		$results = $wpdb->get_results( $wpdb->prepare( $q, $item_id, $this->get_model_name(), $key ) );
		
		$values = array();
		foreach( $results as $res ) {
			$values[] = $res->meta_value;
		}
		return $values;
	}
	
	/**
	 * Save meta value for an item
	 * 
	 * @global object $wpdb
	 * 
	 * @param int $item_id
	 * @param string $key
	 * @param string $value
	 */
	public function add_meta_value( $item_id, $key, $value ) {
		global $wpdb;
		
		$table = $this->get_meta_table_name();
		$data = array( 'item_id' => $item_id, 'meta_key' => $key, 'meta_value' => $value, 'model' => $this->get_model_name() );
		$format = array( '%d', '%s', '%s', '%s' );
		
		$wpdb->insert( $table, $data, $format );
	}
	
	/**
	 * Update meta values with new list
	 * 
	 * @param int $item_id
	 * @param string $key
	 * @param array $data
	 */
	public function update_meta_values( $item_id, $key, $data ) {
		
		$new_data = is_array($data) ? $data : array();
		$existing	= $this->get_meta_values( $item_id, $key );
		$new		= array_diff( $new_data, $existing );
		$deletable  = array_diff( $existing, $new_data );
		
		foreach( $deletable as $deletable_item ) {
			$this->delete_meta_by_value( $item_id, $key, $deletable_item );
		}
		
		foreach( $new as $new_item ) {
			$this->add_meta_value( $item_id, $key, $new_item );
		}
	}
	
	/**
	 * Delete meta item by value, key and item id
	 * 
	 * @global object $wpdb
	 * 
	 * @param int $item_id
	 * @param string $key
	 * @param string $value
	 */
	public function delete_meta_by_value( $item_id, $key, $value ) {
		global $wpdb;
		
		$table = $this->get_meta_table_name();
		
		$wpdb->delete( $table, array( 'item_id' => $item_id, 'meta_key' => $key, 'meta_value' => $value ), array( '%d', '%s', '%s' ) );
	}
	
	/**
	 * Update item owner
	 * 
	 * @global object $wpdb
	 * @param int $item_id
	 * @param int $user_id
	 */
	public function update_owner( $item_id, $user_id = null ) {
		global $wpdb;
		
		if( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		$wpdb->update( $this->get_table_name(), array( 'owner' => $user_id ), array( 'ID' => $item_id ), array('%d'), array('%d') );
	}
	
	
	/**
	 * Prepare limit query for listing
	 * 
	 * @global object $wpdb
	 * 
	 * @param int $limit
	 * @param int $page
	 * 
	 * @return string
	 */
	function prepare_query_limit( $limit, $page ) {
		global $wpdb;
		
		$limit_query = '';
		if( $limit !== -1 && $limit > 0 ) {
			$page = $page && is_numeric( $page ) && $page > 0 ? $page : 1;
			
			$limit_query = $wpdb->prepare(' LIMIT %d, %d', ( $limit * $page ) - $limit, $limit );
		}
		
		return $limit_query;
	}
	
	/**
	 * Prepare args for listing
	 * 
	 * @param array $args
	 * 
	 * @return array
	 */
	function prepare_listing_args( $args = array() ) {
		
		$defaults = [
			'limit'   => -1,
			'page'    => 1
		];
		
		$args = wp_parse_args( $args, $defaults );
		return $args;
	}
	
	/**
	 * Check if we need to add permission query
	 * 
	 * @param int $user_id
	 * 
	 * @return int 
	 *				1 = user is wpcd_is_admin, 
	 *				2 = add query, 
	 *				3 = user not found
	 */
	function should_add_permission_query( $user_id = null ) {
		$should_add = 2;
		
		if( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		if( !$user_id ) {
			$should_add = 3;
		} else if ( wpcd_is_admin( $user_id ) ) {
			$should_add = 1;
		}
		
		return $should_add;
	}
	
	/**
	 * Return where clause for query require user permission
	 * 
	 * @global object $wpdb
	 * 
	 * @param int $user_id
	 * @param boolean $by_parent
	 * 
	 * @return string
	 */
	function permission_where_clause( $user_id = null, $by_parent = false ) {
		global $wpdb;
		
		if( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$user_meta = get_userdata( $user_id );
		$user_roles = $user_meta->roles;

		$q = ( $by_parent ? 'p' : 't1' ) . '.owner = %d OR 
				( 
					( m.meta_key=\'allowed_roles\' AND m.meta_value IN(\''. implode("','", $user_roles ).'\') ) OR 
					( m.meta_key=\'allowed_users\' AND m.meta_value = %d )
				)';


		return $wpdb->prepare( $q, $user_id, $user_id );
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
		$meta_table = $this->get_meta_table_name();
		$q = ' LEFT JOIN '.$meta_table.' m ON m.item_id = t1.ID AND m.model = %s';
		
		return $wpdb->prepare( $q, $this->get_model_name() );
	}
	
	/**
	 * Get items all with user permission
	 * 
	 * @param array $args
	 * 
	 * @param int|null $user_id
	 * 
	 * @return array
	 */
	function get_items_by_permission( $args = array(), $user_id = null ) {
		
		$args = $this->prepare_listing_args( $args );
		$table = $this->get_table_name();
		$q = 'SELECT t1.* FROM '.$table.' t1';
		
		$should_add = $this->should_add_permission_query( $user_id );
		
		$where = '';
		$joins = '';
		
		if( 3 === $should_add ) {
			return array();
		} elseif ( 2 == $should_add ) {
			$joins = $this->permission_query_join( $user_id );
			$where .= ' WHERE ' . $this->permission_where_clause( $user_id );
		}
		
		$where .= ' GROUP BY t1.ID';
		$limit = $this->prepare_query_limit( $args['limit'], $args['page'] );
		
		return $this->get_listing_results( compact( 'q' , 'joins' , 'where' , 'limit' ), $args );
	}
	
	/**
	 * Run query and return results
	 * 
	 * @global object $wpdb
	 * 
	 * @param array $query
	 * @param array $args
	 * 
	 * @return array
	 */
	function get_listing_results( $query, $args ) {
		
		global $wpdb;
		
		extract( $query );
		
		$query = $q . $joins . $where . $limit;
		$query_count = 'SELECT count(*) FROM (' . $q . $joins . $where . ') as res';
		
		$items = $wpdb->get_results( $query, 'ARRAY_A' );
		if( $args['limit'] > 0 ) {
			$total_items = $wpdb->get_var( $query_count );
		} else {
			$total_items = count( $items );
		}
		
		return array(
			'items_count'   => $total_items,
			'items'			=> $items
		);
		
	}
	
}