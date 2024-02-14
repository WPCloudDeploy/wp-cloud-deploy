<?php
/**
 * This file contains general or helper functions for WPCD
 * when handling metabox related things.
 *
 * It primarily helps with creating CARD wrappers in tabs.
 *
 * Looking for other metabox related functions?
 * check out core/apps/wordpres-app/traits/traits-for-class-wordpress-app/metaboxes-app.php
 * and core/apps/wordpres-app/traits/traits-for-class-wordpress-app/metaboxes-server.php
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return an ARRAY suitable for a metabox field
 * that has a div wrapper with certain classes.
 *
 * @since 5.7.
 *
 * @param string $tab The tab on which the field will go.
 */
function wpcd_start_full_card( $tab ) {

	$field = array(
		'tab'               => $tab,
		'type'              => 'wpcd_card_container',
		'column_row_before' => '<div class="wpcd-card-group wpcd-card-full">',
	);

	return $field;

}

/**
 * Return an ARRAY suitable for a metabox field
 * that has a div wrapper with certain classes.
 *
 * @since 5.7.
 *
 * @param string $tab The tab on which the field will go.
 */
function wpcd_start_full_card_no_border( $tab ) {

	$field = array(
		'tab'               => $tab,
		'type'              => 'wpcd_card_container',
		'column_row_before' => '<div class="wpcd-card-group wpcd-card-full-no-border">',
	);

	return $field;

}

/**
 * Return an ARRAY suitable for a metabox field
 * that has a div wrapper with certain classes.
 *
 * @since 5.7.
 *
 * @param string $tab The tab on which the field will go.
 */
function wpcd_start_full_card_page_heading( $tab ) {

	$field = array(
		'tab'               => $tab,
		'type'              => 'wpcd_card_container',
		'column_row_before' => '<div class="wpcd-card-group wpcd-card-full-no-border wpcd-card-full-page-heading">',
	);

	return $field;

}


/**
 * Return an ARRAY suitable for a metabox field
 * that has a div wrapper with certain classes.
 *
 * @since 5.7.
 *
 * @param string $tab The tab on which the field will go.
 */
function wpcd_start_half_card( $tab ) {

	$field = array(
		'tab'               => $tab,
		'type'              => 'wpcd_card_container',
		'column_row_before' => '<div class="wpcd-card-group wpcd-card-half">',
	);

	return $field;

}

/**
 * Return an ARRAY suitable for a metabox field
 * that has a div wrapper with certain classes.
 *
 * @since 5.7.
 *
 * @param string $tab The tab on which the field will go.
 */
function wpcd_start_one_third_card( $tab ) {

	$field = array(
		'tab'               => $tab,
		'type'              => 'wpcd_card_container',
		'column_row_before' => '<div class="wpcd-card-group wpcd-card-third">',
	);

	return $field;

}

/**
 * Return an ARRAY suitable for a metabox field
 * that has a div wrapper with certain classes.
 *
 * @since 5.7.
 *
 * @param string $tab The tab on which the field will go.
 */
function wpcd_start_two_thirds_card( $tab ) {

	$field = array(
		'tab'               => $tab,
		'type'              => 'wpcd_card_container',
		'column_row_before' => '<div class="wpcd-card-group wpcd-card-two-thirds">',
	);

	return $field;

}

/**
 * Return the closing div for a card.
 *
 * @since 5.7.
 *
 * @param string $tab The tab on which the field will go.
 */
function wpcd_end_card( $tab ) {

	$field = array(
		'tab'               => $tab,
		'type'              => 'wpcd_card_container',
		'column_row_before' => '</div><!-- .wpcd-card-group -->',
	);

	return $field;

}


