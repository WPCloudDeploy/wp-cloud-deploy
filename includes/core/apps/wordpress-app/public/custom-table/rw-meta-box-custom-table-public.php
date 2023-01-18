<?php

require_once wpcd_path . 'includes/core/apps/wordpress-app/public/traits/meta-box-public.php';

/**
 * Custom metabox class for custom table public view
 */
class RW_Meta_Box_Custom_Table_Public extends \RW_Meta_Box {

	use Meta_Box_Public;
	
	/**
	 * Create meta box based on given data.
	 *
	 * @param array $args
	 */
	public function __construct( $args ) {
		
		$args['models']    = (array) $args['models'];
		$this->object_type = 'model';
		parent::__construct( $args );
		
	}
	
	/**
	 * Add fields to field registry.
	 */
	public function register_fields() {
		$registry = rwmb_get_registry( 'field' );

		foreach ( $this->models as $model ) {
			foreach ( $this->meta_box['fields'] as &$field ) {
				$registry->add( $field, $model, $this->object_type );
			}
		}
	}
}
