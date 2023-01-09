<?php


class WPCD_CT_DNS_Provider_API extends WPCD_Custom_Table_API {
	
	
	
	public $model_name = 'dns_provider';
	public $table_name = 'dns_providers';
	
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
	
	
	public function get_item_display_name( $item_id ) {
		
		$item = $this->get_by_id( $item_id );
		
		$name = '';
		if( $item ) {
			$name = $item->dns_name;
		}
		
		return $name;
	}
}