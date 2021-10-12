<?php
namespace MBSP\Customizer;

use MBSP\Factory;

class Manager {
	public function __construct() {
		// Priority 30 ensures it fires after meta boxes are registered.
		add_action( 'init', [$this, 'init'], 30 );
	}

	public function init() {
		$meta_boxes = rwmb_get_registry( 'meta_box' )->all();

		// Meta box that has a settings page.
		$settings_sections = array_filter( $meta_boxes, function( $meta_box ) {
			return $meta_box->settings_pages && Factory::get( $meta_box->settings_pages, 'customizer' );
		} );
		array_walk( $settings_sections, [$this, 'register_settings_section'] );

		// Meta box that doesn't have a settings page.
		$normal_sections = array_filter( $meta_boxes, function( $meta_box ) {
			return isset( $meta_box->meta_box['panel'] );
		} );
		array_walk( $normal_sections, [$this, 'register_normal_section'] );
	}

	private function register_settings_section( $meta_box ) {
		$panels   = Factory::get( $meta_box->settings_pages, 'customizer' );
		$meta_box = new SettingsSection( $meta_box->meta_box );
		$meta_box->register_fields();
		foreach ( $panels as $panel ) {
			$meta_box->object_id = $panel->option_name;
			new Setting( $meta_box );
		}
	}

	private function register_normal_section( $meta_box ) {
		new Setting( $meta_box );
	}
}
