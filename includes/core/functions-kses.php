<?php
/**
 * This file contains general or helper functions for WPCD
 * that return data filtered for various KSES parameters.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a string with array appropriate for filtering SELECT input fields.
 *
 * @since 5.7.
 */
function wpcd_kses_allowed_select() {

	$allowed_html['select'] = array(
		'class'    => true,
		'id'       => true,
		'name'     => true,
		'multiple' => true,
		'size'     => true,
		'disabled' => true,
		'readonly' => true,
		'required' => true,
	);
	$allowed_html['option'] = array(
		'value'    => true,
		'selected' => true,
		'disabled' => true,
		'label'    => true,
	);

	return $allowed_html;

}

/**
 * Returns a string filtered by wp_kses for allowed SELECT input fields.
 *
 * This is generally used by routines that paint the filter bars.
 *
 * @since 5.7.
 *
 * @param string $data String to filter.
 */
function wpcd_kses_select( $data ) {

	return wp_kses( $data, wpcd_kses_allowed_select() );

}
