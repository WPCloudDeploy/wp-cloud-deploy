<?php
namespace MBTM;

class Loader {
	public function __construct() {
		add_filter( 'rwmb_meta_box_class_name', array( $this, 'meta_box_class_name' ), 10, 2 );

		add_filter( 'rwmb_meta_type', array( $this, 'filter_meta_type' ), 10, 3 );
	}

	/**
	 * Filter meta box class name.
	 *
	 * @param  string $class_name Meta box class name.
	 * @param  array  $meta_box   Meta box data.
	 * @return string
	 */
	public function meta_box_class_name( $class_name, $meta_box ) {
		return isset( $meta_box['taxonomies'] ) ? __NAMESPACE__ . '\MetaBox' : $class_name;
	}

	/**
	 * Filter meta type from object type and object id.
	 *
	 * @param string $type        Meta type get from object type and object id. Assert taxonomy name if object type is term.
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object id.
	 *
	 * @return string
	 */
	public function filter_meta_type( $type, $object_type, $object_id ) {
		if ( 'term' !== $object_type ) {
			return $type;
		}

		$term = get_term( $object_id );
		return isset( $term->taxonomy ) ? $term->taxonomy : $type;
	}
}
