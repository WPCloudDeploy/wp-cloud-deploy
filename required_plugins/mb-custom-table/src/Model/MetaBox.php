<?php
namespace MetaBox\CustomTable\Model;

class MetaBox extends \RW_Meta_Box {
	public function __construct( $args ) {
		$args['models']    = (array) $args['models'];
		$this->object_type = 'model';
		parent::__construct( $args );
	}

	protected function object_hooks() {
		add_action( 'mbct_model_edit_load', [ $this, 'load' ] );
		add_action( 'mbct_model_edit_load', [ $this, 'save_model' ] );

		// Hide meta box if it's set 'default_hidden'.
		add_filter( 'default_hidden_meta_boxes', array( $this, 'hide' ), 10, 2 );
	}

	public function enqueue() {
		if ( ! $this->is_edit_screen() ) {
			return;
		}

		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );

		parent::enqueue();
	}

	public function load() {
		if ( ! $this->is_edit_screen() ) {
			return;
		}

		$screen = get_current_screen();
		add_filter( "postbox_classes_{$screen->id}_{$this->id}", [ $this, 'postbox_classes' ] );

		// Add meta boxes.
		add_meta_box(
			$this->id,
			$this->title,
			[ $this, 'show' ],
			null, // Current page.
			$this->context,
			$this->priority
		);
	}

	public function save_model() {
		// Save.
		if ( empty( $_POST['submit'] ) ) {
			return;
		}

		// Get the correct inserted ID when add new model.
		$object_id = rwmb_request()->filter_get( 'model-id', FILTER_SANITIZE_NUMBER_INT );
		if ( 'add' === rwmb_request()->get( 'model-action' ) ) {
			$object_id = -1; // A fake ID to store data in the cache.
		}
		$this->save_post( $object_id );
	}

	public function get_current_object_id() {
		return rwmb_request()->filter_get( 'model-id', FILTER_SANITIZE_NUMBER_INT );
	}

	public function is_edit_screen( $screen = null ) {
		$page = rwmb_request()->get( 'page' );
		if ( strpos( $page, 'model-' ) !== 0 ) {
			return false;
		}
		$model = substr( $page, 6 );

		$action = rwmb_request()->get( 'model-action' );

		return in_array( $model, $this->models, true ) && in_array( $action, ['add', 'edit'] );
	}

	public function register_fields() {
		$registry = rwmb_get_registry( 'field' );

		foreach ( $this->models as $model ) {
			foreach ( $this->meta_box['fields'] as &$field ) {
				$registry->add( $field, $model, $this->object_type );
			}
		}
	}
}
