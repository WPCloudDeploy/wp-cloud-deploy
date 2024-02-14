<?php
/**
 * This file contains general or helper functions for WPCD
 * to prefix an icon in front of a string.
 *
 * It is used primarily for strings on buttons and section headers.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_restart_icon( $label ) {

	return sprintf( $label, '<i class="fa-sharp fa-solid fa-clock-rotate-left"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_power_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-power-off"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_calendar_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-calendar-days"></i> ' );

}