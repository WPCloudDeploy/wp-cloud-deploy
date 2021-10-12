<?php
namespace MBSP;

class Factory {
	private static $data = [
		'normal'     => [],
		'network'    => [],
		'customizer' => [],
	];

	public static function make( $args ) {
		$args = self::normalize( $args );

		if ( $args['network'] ) {
			self::$data['network'][ $args['id'] ] = new Network\SettingsPage( $args );
			return;
		}

		if ( $args['customizer'] ) {
			self::$data['customizer'][ $args['id'] ] = new Customizer\Panel( $args );
		}

		if ( ! $args['customizer_only'] ) {
			self::$data['normal'][ $args['id'] ] = new SettingsPage( $args );
		}
	}

	private static function normalize( $args ) {
		$args = wp_parse_args( $args, array(
			'id'              => '', // Page ID. Required. Will be used as slug in URL and option name (if missed).
			'option_name'     => '', // Option name. Optional. Takes 'id' if missed.
			'menu_title'      => '', // Menu title. Optional. Takes 'page_title' if missed.
			'page_title'      => '', // Page title. Optional. Takes 'menu_title' if missed.
			'capability'      => 'edit_theme_options', // Required capability to visit.
			'icon_url'        => '', // Icon URL. @see add_menu_page().
			'position'        => null, // Menu position. @see add_menu_page().
			'parent'          => '', // ID of parent page. Optional.
			'submenu_title'   => '', // Submenu title. Optional.
			'help_tabs'       => array(),
			'style'           => 'boxes',
			'columns'         => 2,
			'tabs'            => array(),
			'tab_style'       => 'default',
			'class'           => '',
			'submit_button'   => __( 'Save Settings', 'mb-settings-page' ),
			'message'         => __( 'Settings saved.', 'mb-settings-page' ),
			'network'         => false,
			'customizer'      => false,
			'customizer_only' => false,
		) );

		// Setup optional parameters.
		if ( ! $args['option_name'] ) {
			$args['option_name'] = $args['id'];
		}
		if ( ! $args['menu_title'] ) {
			$args['menu_title'] = $args['page_title'];
		}
		if ( ! $args['page_title'] ) {
			$args['page_title'] = $args['menu_title'];
		}

		return $args;
	}

	public static function get( $ids, $type = 'normal' ) {
		return array_intersect_key( self::$data[ $type ], array_flip( (array) $ids ) );
	}
}