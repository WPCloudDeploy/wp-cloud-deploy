<?php
/**
 * This class is used as a base class for any class
 * that creates and manages post types.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_Posts_Base
 */
class WPCD_Posts_Base {

	/**
	 * WPCD_Posts_Base constructor.
	 */
	public function __construct() {
		// nothing.
	}

	/**
	 * Remove PRIVATE state label from certain custom post types.
	 * Add in any labels defined in the descriptions/notes/labels metabox.
	 *
	 * This function is called from filter hooks defined in descendant classes.
	 *
	 * Filter Hook: display_post_states
	 *
	 * @param array  $states The current states for the CPT record.
	 * @param object $post The post object.
	 *
	 * @return array $states
	 */
	public function display_post_states( $states, $post ) {

		/* Remove PRIVATE state label from certain custom post types */
		if ( 'wpcd_app' === get_post_type( $post ) || 'wpcd_app_server' === get_post_type( $post ) ) {
			if ( ! boolval( wpcd_get_option( 'wpcd_show_private_state' ) ) ) {
				if ( isset( $states['private'] ) ) {
					unset( $states['private'] );
				}
			}
		}

		/* Add in any labels defined in the descriptions/notes/labels metabox. */
		if ( 'wpcd_app' === get_post_type( $post ) || 'wpcd_app_server' === get_post_type( $post ) ) {

			$labels = get_post_meta( $post->ID, 'wpcd_labels', true );

			if ( ! empty( $labels ) ) {
				$counter = 0;
				foreach ( $labels as $label ) {
					$counter++;
					$index            = 'wpcd_labels_' . (string) $counter; // create a valid array index for the $states array.
					$cssclass         = 'wpcd_' . preg_replace( '/\W+/', '', strtolower( wp_strip_all_tags( $label ) ) );  // create a valid css classname to be used with the label.
					$states[ $index ] = '<span class="wpcd_custom_state_label ' . $cssclass . '">' . $label . '</span>';
				}
			}
		}

		/* Add in labels if delete protection is on */
		if ( 'wpcd_app_server' === get_post_type( $post ) ) {

			$server_delete_protection_status = get_post_meta( $post->ID, 'wpcd_server_delete_protection', true );
			if ( ! empty( $server_delete_protection_status ) && '1' === $server_delete_protection_status ) {
				$states['server_delete_protected'] = __( 'Delete Protected', 'wpcd' );
			}
		}
		if ( 'wpcd_app' === get_post_type( $post ) ) {
			$app_delete_protection_status = get_post_meta( $post->ID, 'wpcd_app_delete_protection', true );
			if ( ! empty( $app_delete_protection_status ) && '1' === $app_delete_protection_status ) {
				$states['app_delete_protected'] = __( 'Delete Protected', 'wpcd' );
			}
		}

		return $states;

	}

}
