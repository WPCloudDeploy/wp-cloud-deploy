<?php
namespace MBSP\Customizer;

class Setting {
	private $meta_box;
	private $id;

	public function __construct( $meta_box ) {
		$this->meta_box = $meta_box;
		$this->id       = $meta_box->id;

		add_action( 'customize_register', array( $this, 'register' ) );
	}

	public function register( $wp_customize ) {
		$wp_customize->add_setting( $this->id, array(
			'type' => 'meta_box', // Custom setting type to prevent default WordPress saving process.
		) );
		$wp_customize->add_control( new Control( $wp_customize, $this->id, array(
			'section'  => $this->id,
			'meta_box' => $this->meta_box,
		) ) );

		// Save the setting.
		add_action( 'customize_update_meta_box', [ $this, 'save' ], 10, 2 );

		// Filter the option for previewing.
		add_action( "customize_preview_{$this->id}", [ $this, 'preview' ] );

		if ( ! empty( $_GET['customize_changeset_uuid'] ) ) {
			$this->filter_update_from_customize_changeset();
		}
	}

	public function save( $value, $setting ) {
		if ( $setting->id != $this->id ) {
			return;
		}

		$value = json_decode( $value, true );

		// Populate $_POST.
		rwmb_request()->set_post_data( $value );

		$this->meta_box->save_post( $this->meta_box->object_id );
	}

	public function preview() {
		add_filter( "option_{$this->meta_box->object_id}", [ $this, 'preview_filter' ] );
		add_filter( "default_option_{$this->meta_box->object_id}", [ $this, 'preview_filter' ] );
	}

	public function preview_filter( $original ) {
		if ( ! is_customize_preview() ) {
			return $original;
		}

		$customized = rwmb_request()->post( 'customized', '' );
		$customized = wp_unslash( $customized );
		$customized = json_decode( $customized, true );

		if ( empty( $customized[ $this->id ] ) ) {
			return $this->update_from_customize_changeset( $original );
		}

		$data     = json_decode( $customized[ $this->id ], true );
		$original = empty( $original ) ? [] : $original;

		return array_merge( $original, $data );
	}

	public function filter_update_from_customize_changeset() {
		add_filter( "option_{$this->meta_box->object_id}", [ $this, 'update_from_customize_changeset' ] );
		add_filter( "default_option_{$this->meta_box->object_id}", [ $this, 'update_from_customize_changeset' ] );
	}

	public function update_from_customize_changeset( $original ) {
		if ( empty( $GLOBALS['wp_customize'] ) ) {
			return $original;
		}

		$customized = $GLOBALS['wp_customize']->changeset_data();
		if ( empty( $customized[ $this->id ] ) ) {
			return $original;
		}

		$data     = json_decode( $customized[ $this->id ]['value'], true );
		$original = empty( $original ) ? [] : $original;

		return array_merge( $original, $data );
	}
}
