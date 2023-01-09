<?php


abstract class WPCD_Custom_Table_API {
	
	
	public $model_name = '';
	
	
	public $child_model_name = '';
	public $parent_model_name = '';
	
	const META_TABLE_NAME = 'wpcd_ct_model_meta';


	public function __construct() {}
	
	
	
	public static function get( $model_name ) {
		
		$class_name_prefix = 'WPCD_CT_';
		$class_name = $class_name_prefix . ucwords( $model_name, '_' ) . '_API';
		
		if( class_exists( $class_name ) ) {
			return $class_name::instance();
		}
		
		return null;
	}
	
	public function get_model_name() {
		return $this->model_name;
	}
	
	
	public function get_table_name() {
		global $wpdb;
		return "{$wpdb->prefix}wpcd_ct_{$this->table_name}";
	}
	
	
	public static function get_meta_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::META_TABLE_NAME;
	}
	
	
	public function get_by_id( $item_id ) {
		global $wpdb;
		
		$q = 'SELECT * FROM ' . $this->get_table_name() . ' WHERE ID=%d';
		return $wpdb->get_row( $wpdb->prepare( $q, $item_id ) );
	}
	
	function get_items_by_parent_id( $parent_id, $output = OBJECT ) {
		global $wpdb;
		
		$q = 'SELECT * FROM ' . $this->get_table_name() . ' WHERE parent_id=%d';
		$results = $wpdb->get_results( $wpdb->prepare( $q, $parent_id ), $output );
		
		return $results;
	}
	
	
	
	public function delete_item( $item_id ) {
		global $wpdb;
		$wpdb->delete( $this->get_table_name(), array( 'ID' => $item_id ), array( '%d' ) );
	}
	
	
	function delete_items_by_parent( $parent_id ) {
		global $wpdb;
		$wpdb->delete( $this->get_table_name(), [ 'parent_id' => $parent_id ], ['%d'] );
	}
	
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
	
	
	
	
	public function add_meta_value( $item_id, $key, $value ) {
		global $wpdb;
		
		$table = $this->get_meta_table_name();
		
		$data = array( 'item_id' => $item_id, 'meta_key' => $key, 'meta_value' => $value, 'model' => $this->get_model_name() );
		$format = array( '%d', '%s', '%s', '%s' );
		
		$wpdb->insert( $table, $data, $format );
	}
	
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
	
	
	public function delete_meta_by_value( $item_id, $key, $value ) {
		global $wpdb;
		
		$table = $this->get_meta_table_name();
		
		$wpdb->delete( $table, array( 'item_id' => $item_id, 'meta_key' => $key, 'meta_value' => $value ), array( '%d', '%s', '%s' ) );
	}
	
	
	public function update_owner( $item_id, $user_id = null ) {
		
		global $wpdb;
		
		if( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		
		$wpdb->update( $this->get_table_name(), array( 'owner' => $user_id ), array( 'ID' => $item_id ), array('%d'), array('%d') );
	}
	
	
	function prepare_permission_user( $user_id = null ) {
		
		if( null === $user_id || !$user_id ) {
			$user_id = get_current_user_id();
		}

		if( !$user_id ) {
			return '';
		}
	}
	
	
	
	function prepare_query_limit( $limit, $page ) {
		global $wpdb;
		
		$limit_query = '';
		if( $limit !== -1 && $limit > 0 ) {
			$page = $page && is_numeric( $page ) && $page > 0 ? $page : 1;
			
			$limit_query = $wpdb->prepare(' LIMIT %d, %d', ( $limit * $page ) - $limit, $limit );
		}
		
		return $limit_query;
	}
	
	
	function prepare_listing_args( $args = array() ) {
		
		$defaults = [
			'limit'   => -1,
			'page'    => 1
		];
		
		
		$args = wp_parse_args( $args, $defaults );
		return $args;
	}
	
	//1 - user is wpcd_is_admin
	//2 - add query
	//3 - user not found
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
	

	
	
	function permission_inner_join_clause( $user_id = null ) {
		global $wpdb;


		if( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$user_meta = get_userdata( $user_id );
		$user_roles = $user_meta->roles;

		$q = 'AND m.model = %s AND 
				( 
					( m.meta_key=\'allowed_roles\' AND m.meta_value IN(\''. implode("','", $user_roles ).'\') ) OR 
					( m.meta_key=\'allowed_users\' AND m.meta_value = %d )  
				) 
				';


		return $wpdb->prepare( $q, $this->get_model_name(), $user_id );
	}
	
	
	public function permission_inner_join( $user_id = null ) {
		
		$meta_table = $this->get_meta_table_name();
		$q = ' INNER JOIN '.$meta_table.' m ON m.item_id = t1.ID ';
		return $q .  $this->permission_inner_join_clause( $user_id ) ;
	}
	
	
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
			$joins = $this->permission_inner_join( $user_id );
		}
		
		$where .= ' GROUP BY t1.ID';
		$limit = $this->prepare_query_limit( $args['limit'], $args['page'] );
		
		return $this->get_listing_results( compact( 'q' , 'joins' , 'where' , 'limit' ), $args );
	}
	
	
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