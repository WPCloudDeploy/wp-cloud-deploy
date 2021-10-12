<?php
/**
 * Plugin Name: Meta Box Include Exclude
 * Plugin URI:  https://metabox.io/plugins/meta-box-include-exclude/
 * Description: Easily show/hide meta boxes by ID, page template, taxonomy or custom defined function.
 * Version:     1.0.11
 * Author:      MetaBox.io
 * Author URI:  https://metabox.io
 * License:     GPL2+
 */

if ( defined( 'ABSPATH' ) && ! class_exists( 'MB_Include_Exclude' ) ) {
	require __DIR__ . '/class-mb-include-exclude.php';
	add_filter( 'rwmb_show', array( 'MB_Include_Exclude', 'check' ), 10, 2 );
}
