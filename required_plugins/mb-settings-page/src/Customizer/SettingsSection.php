<?php
namespace MBSP\Customizer;

use MBSP\Storage;
use MBSP\Factory;

class SettingsSection extends \RW_Meta_Box {
	private $panels = [];

	public function __construct( $args ) {
		$args['settings_pages'] = (array) $args['settings_pages'];
		$this->panels           = Factory::get( $args['settings_pages'], 'customizer' );
		$this->object_type      = 'setting';

		parent::__construct( $args );
	}

	protected function object_hooks() {
		add_action( 'customize_register', array( $this, 'register' ) );
	}

	public function register( $wp_customize ) {
		foreach ( $this->panels as $panel ) {
			$wp_customize->add_section( $this->id, array(
				'title'          => $this->title,
				'panel'          => $panel->id,
				'capability'     => $panel->capability,
				'priority'       => $this->priority,
				'theme_supports' => $this->theme_supports,
			) );
		}
	}

	public function is_edit_screen( $screen = null ) {
		return is_customize_preview();
	}

	public function get_storage() {
		return (new Storage);
	}

	public function register_fields() {
		$registry = rwmb_get_registry( 'field' );
		foreach ( $this->panels as $panel ) {
			foreach ( $this->fields as $field ) {
				$registry->add( $field, $panel->option_name, $this->object_type );
			}
		}
	}
}
