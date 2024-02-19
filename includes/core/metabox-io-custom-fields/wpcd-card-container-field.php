<?php
defined( 'ABSPATH' ) || die;

/**
 * Pseudo field to be used when rendering cards inside containers.
 * It actually renders nothing - it's only used so that
 * our custom metabox column can inject wrapper divs around it.
 *
 * While not strictly necessary, we think it's better to have
 * the custom field than try to do something with one of the
 * regular fields that might change markup in the future.
 */
if ( class_exists( 'RWMB_Field' ) ) {
	class RWMB_Wpcd_Card_Container_Field extends RWMB_Field {
		/**
		 * Get field Card Container.
		 *
		 * @param mixed $meta  Meta value.
		 * @param array $field Field parameters.
		 *
		 * @return string
		 */
		public static function html( $meta, $field ) {
			return '<!-- card-container -->';
		}
	}
}
