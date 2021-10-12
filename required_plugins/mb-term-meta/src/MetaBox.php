<?php
namespace MBTM;

use RWMB_Loader;

class MetaBox extends \RW_Meta_Box {
	public function __construct( $meta_box ) {
		$meta_box['taxonomies'] = (array) $meta_box['taxonomies'];
		$this->object_type      = 'term';
		parent::__construct( $meta_box );
	}

	protected function object_hooks() {
		// Add meta fields to edit term page.
		add_action( 'load-edit-tags.php', array( $this, 'add' ) );
		add_action( 'load-term.php', array( $this, 'add' ) );

		// Save term meta.
		foreach ( $this->meta_box['taxonomies'] as $taxonomy ) {
			add_action( "edited_$taxonomy", array( $this, 'save_post' ) );
			add_action( "created_$taxonomy", array( $this, 'save_post' ) );
		}

		add_action( "rwmb_before_{$this->meta_box['id']}", array( $this, 'show_heading' ) );
	}

	public function show_heading() {
		if ( $this->meta_box['title'] ) {
			echo '<h2>', esc_html( $this->meta_box['title'] ), '</h2>';
		}
	}

	public function add() {
		if ( ! $this->is_edit_screen() ) {
			return;
		}

		// Add meta box.
		foreach ( $this->meta_box['taxonomies'] as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form", array( $this, 'show' ), 10, 2 );
			add_action( "{$taxonomy}_add_form_fields", array( $this, 'show' ), 10, 2 );

			add_action( "{$taxonomy}_term_edit_form_tag", array( 'RWMB_File_Field', 'post_edit_form_tag' ) );
		}
	}

	public function enqueue() {
		if ( ! $this->is_edit_screen() ) {
			return;
		}

		parent::enqueue();

		list( , $url ) = RWMB_Loader::get_path( dirname( __DIR__ ) );
		wp_enqueue_style( 'mb-term-meta', $url . 'assets/term-meta.css', '', '1.2.8' );

		// Only load these scripts on add term page.
		$screen = get_current_screen();
		if ( 'edit-tags' !== $screen->base ) {
			return;
		}

		wp_enqueue_script( 'mb-term-meta', $url . 'assets/term-meta.js', array( 'jquery' ), '1.2.8', true );
		wp_localize_script( 'mb-term-meta', 'MBTermMeta', array(
			'addedMessage' => __( 'Term added.', 'mb-term-meta' ),
		) );
	}

	public function get_current_object_id() {
		return filter_input( INPUT_GET, 'tag_ID', FILTER_SANITIZE_NUMBER_INT );
	}

	public function is_edit_screen( $screen = null ) {
		$screen = get_current_screen();

		return
			( 'edit-tags' === $screen->base || 'term' === $screen->base )
			&& in_array( $screen->taxonomy, $this->meta_box['taxonomies'], true );
	}

	public function register_fields() {
		$field_registry = rwmb_get_registry( 'field' );

		foreach ( $this->taxonomies as $taxonomy ) {
			foreach ( $this->fields as $field ) {
				$field_registry->add( $field, $taxonomy, 'term' );
			}
		}
	}
}
