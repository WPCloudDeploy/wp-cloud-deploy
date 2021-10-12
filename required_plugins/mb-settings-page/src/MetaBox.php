<?php
namespace MBSP;

class MetaBox extends \RW_Meta_Box {
	protected $pages = [];

	public function __construct( $args ) {
		$args['settings_pages'] = (array) $args['settings_pages'];
		$this->setup( $args );
		parent::__construct( $args );
	}

	protected function setup( $args ) {
		$this->pages       = Factory::get( $args['settings_pages'], 'normal' );
		$this->object_type = 'setting';
	}

	protected function object_hooks() {
		add_action( 'mb_settings_page_load', array( $this, 'load' ) );

		if ( $this->tab ) {
 			add_action( "rwmb_before_{$this->id}", array( $this, 'show_tab' ) );
 		}
	}

	public function load( $page_args ) {
		static $message_shown = false;

		if ( ! in_array( $page_args['id'], $this->settings_pages ) ) {
			return;
		}

		$screen = get_current_screen();
		add_filter( "postbox_classes_{$screen->id}_{$this->id}", array( $this, 'postbox_classes' ) );

		$object_id = $page_args['option_name'];
		$this->set_object_id( $object_id );

		// Add meta boxes.
		add_meta_box(
			$this->id,
			$this->title,
			array( $this, 'show' ),
			null, // Current page.
			$this->context,
			$this->priority
		);

		// Save options.
		if ( empty( $_POST['submit'] ) || $page_args['is_imported'] ) {
			return;
		}

		$this->save_post( $object_id );

		// Compatible with old hook.
		$data = get_option( $object_id, array() );
		$data = apply_filters( 'mb_settings_pages_data', $data, $object_id );
		update_option( $object_id, $data );

		// Prevent duplicate messages.
		if ( ! $message_shown ) {
			add_settings_error( '', 'saved', $page_args['message'], 'updated' );
			$message_shown = true;
		}
	}

	public function is_edit_screen( $screen = null ) {
		if ( ! ( $screen instanceof \WP_Screen ) ) {
			$screen = get_current_screen();
		}

		return in_array( $screen->id, wp_list_pluck( $this->pages, 'page_hook' ), true );
	}

	public function show_tab() {
		echo '<script type="text/html" class="rwmb-settings-tab" data-tab="', esc_attr( $this->tab ), '"></script>';
	}

	public function get_storage() {
		return (new Storage);
	}

	public function register_fields() {
		$registry = rwmb_get_registry( 'field' );
		foreach ( $this->pages as $page ) {
			foreach ( $this->meta_box['fields'] as &$field ) {
				$registry->add( $field, $page->option_name, $this->object_type );

				if ( isset( $field['type'] ) && 'backup' === $field['type'] ) {
					$field['option_name'] = $page->option_name;
				}
			}
		}
	}
}
