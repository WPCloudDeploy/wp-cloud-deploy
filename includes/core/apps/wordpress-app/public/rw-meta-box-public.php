<?php

/**
 * Custom metabox class for public view
 */
class RW_Meta_Box_Public extends RW_Meta_Box {

	/**
	 * Enqueue some scripts.
	 */
	protected function global_hooks() {
		// Enqueue common styles and scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		// Add additional actions for fields.
		foreach ( $this->fields as $field ) {
			RWMB_Field::call( $field, 'add_actions' );
		}
	}
}
