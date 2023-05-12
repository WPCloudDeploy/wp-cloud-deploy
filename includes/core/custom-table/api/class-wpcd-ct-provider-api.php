<?php

/**
 * Class Provider API
 */
class WPCD_CT_Provider_API extends WPCD_Custom_Table_API {
	
	/**
	 * Model Name
	 * 
	 * @var string
	 */
	public $model_name = 'provider';
	
	/**
	 * Table Name
	 * 
	 * @var string 
	 */
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