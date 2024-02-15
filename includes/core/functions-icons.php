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

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_phone_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-phone-office"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_trash_icon( $label ) {

	return sprintf( $label, '<i class="fa-solid fa-trash-xmark"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_erase_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-eraser"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_uninstall_icon( $label ) {

	return sprintf( $label, '<i class="fa-solid fa-trash-can-slash"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_install_icon( $label ) {

	return sprintf( $label, '<i class="fa-solid fa-grid-2-plus"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_virus_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-virus-slash"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_updates_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-road-spikes"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_run_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-person-running-fast"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_open_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-lock-open"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_close_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-lock"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_about_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-person-circle-question"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_save_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-floppy-disk"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_email_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-envelopes"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_load_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-truck-ramp-box"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_download_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-download"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_upload_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-download"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_logs_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-warehouse-full"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_warning_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-triangle-exclamation"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_danger_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-skull-crossbones"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_checkmark_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-square-check"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_x_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-square-xmark"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_change_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-swap-arrows"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_change_code_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-code-pull-request"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_php_icon( $label ) {

	return sprintf( $label, '<i class="fa-brands fa-php"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_backup_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-backpack"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_restore_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-trash-can-arrow-up"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_prune_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-broom-wide"></i> ' );
}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_aws_icon( $label ) {

	return sprintf( $label, '<i class="fa-brands fa-aws"></i> ' );
}


/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_ssl_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-key-skeleton-left-right"></i> ' );

}

/**
 * Returns a string with a fontawesome icon inserted.
 *
 * @since 5.7.
 *
 * @param string $label The string where the icon needs to be applied.  It needs to have at least on placeholder in it (eg: '%s Restart' ).
 */
function wpcd_apply_clock_icon( $label ) {

	return sprintf( $label, '<i class="fa-duotone fa-alarm-clock"></i> ' );

}













