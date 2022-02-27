<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class RW_Meta_Box_Public extends RW_Meta_Box {
	
	

	
	protected function global_hooks() {
		// Enqueue common styles and scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		// Add additional actions for fields.
		foreach ( $this->fields as $field ) {
			RWMB_Field::call( $field, 'add_actions' );
		}
	}
	
	
	
}