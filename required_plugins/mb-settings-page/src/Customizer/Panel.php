<?php
namespace MBSP\Customizer;

class Panel {
	private $args;

	public function __construct( $args = array() ) {
		$this->args = $args;

		add_action( 'customize_register', array( $this, 'register' ) );
	}

	public function register( $wp_customize ) {
		$wp_customize->add_panel( $this->id, array(
			'title'    => $this->menu_title,
			'priority' => $this->priority,
		) );
	}

	public function __get( $name ) {
		return isset( $this->args[ $name ] ) ? $this->args[ $name ] : null;
	}
}
