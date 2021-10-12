<?php
namespace MBUM;

class Loader {
	public function __construct() {
		add_filter( 'rwmb_meta_box_class_name', [ $this, 'change_meta_box_class_name' ], 10, 2 );

		add_filter( 'rwmb_meta_type', [ $this, 'change_meta_type' ], 10, 2 );

		add_action( 'user_edit_form_tag', [ $this, 'output_form_upload_attributes' ] );
	}

	/**
	 * Filter meta box class name.
	 *
	 * @param  string $class_name Meta box class name.
	 * @param  array  $meta_box   Meta box data.
	 * @return string
	 */
	public function change_meta_box_class_name( $class_name, $meta_box ) {
		return isset( $meta_box['type'] ) && 'user' === $meta_box['type'] ? __NAMESPACE__ . '\MetaBox' : $class_name;
	}

	/**
	 * Filter meta type from object type and object id.
	 *
	 * @param string $type        Meta type get from object type and object id.
	 * @param string $object_type Object type.
	 *
	 * @return string
	 */
	public function change_meta_type( $type, $object_type ) {
		return 'user' === $object_type ? 'user' : $type;
	}

	public function output_form_upload_attributes() {
		echo ' enctype="multipart/form-data"';
	}
}
