<?php


class WPCD_CT_Provider_API extends WPCD_Custom_Table_API {
	
	public $model_name = 'provider';
	public $table_name = 'providers';
	
	
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
	
	
}