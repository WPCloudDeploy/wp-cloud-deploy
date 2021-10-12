<?php
namespace MBSP;

class Loader {
	public function __construct() {
		$this->register_settings_pages();

		add_filter( 'rwmb_meta_box_class_name', array( $this, 'meta_box_class_name' ), 10, 2 );

		add_filter( 'rwmb_meta_type', array( $this, 'filter_meta_type' ), 10, 3 );
	}

	private function register_settings_pages() {
		$settings_pages = apply_filters( 'mb_settings_pages', array() );

		if ( empty( $settings_pages ) || ! is_array( $settings_pages ) ) {
			return;
		}

		array_walk( $settings_pages, [Factory::class, 'make'] );
	}

	/**
	 * Filter meta box class name.
	 *
	 * @param  string $class_name Meta box class name.
	 * @param  array  $args       Meta box settings.
	 * @return string
	 */
	public function meta_box_class_name( $class_name, $args ) {
		if ( isset( $args['panel'] ) ) {
			return __NAMESPACE__ . '\Customizer\NormalSection';
		}

		if ( empty( $args['settings_pages'] ) ) {
			return $class_name;
		}
		if ( Factory::get( $args['settings_pages'], 'network' ) ) {
			return __NAMESPACE__ . '\Network\MetaBox';
		}

		return __NAMESPACE__ . '\MetaBox';
	}

	/**
	 * Filter meta type from object type and object id.
	 *
	 * @param string     $type        Meta type get from object type and object id.
	 *                                Assert 'setting' if object id is a string.
	 * @param string     $object_type Object type.
	 * @param string|int $object_id   Object id. Should be the option name.
	 *
	 * @return string
	 */
	public function filter_meta_type( $type, $object_type, $object_id ) {
		return in_array( $object_type, ['setting', 'network_setting'] ) ? $object_id : $type;
	}
}
