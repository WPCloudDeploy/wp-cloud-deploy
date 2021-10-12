<?php
namespace MBSP\Customizer;

use MBSP\Storage;

class NormalSection extends \RW_Meta_Box {
	public function __construct( $args ) {
		parent::__construct( $args );
		$this->object_type = 'setting';

		$this->object_id = isset( $args['option_name'] ) ? $args['option_name'] : 'theme_mods_' . get_stylesheet();
	}

	protected function object_hooks() {
		add_action( 'customize_register', array( $this, 'register' ) );
	}

	public function register( $wp_customize ) {
		$wp_customize->add_section( $this->id, array(
			'title'          => $this->title,
			'panel'          => $this->panel,
			'capability'     => $this->capability,
			'priority'       => $this->priority,
			'theme_supports' => $this->theme_supports,
		) );
	}

	public function is_edit_screen( $screen = null ) {
		return is_customize_preview();
	}

	public function get_storage() {
		return (new Storage);
	}

	public function register_fields() {
		$registry = rwmb_get_registry( 'field' );
		foreach ( $this->fields as $field ) {
			$registry->add( $field, $this->object_id, $this->object_type );
		}
	}
}
