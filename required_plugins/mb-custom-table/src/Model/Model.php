<?php
namespace MetaBox\CustomTable\Model;

class Model {
	public $name;
	private $args;

	public function __construct( $name, $args ) {
		$this->name = $name;
		$this->args = $this->normalize( $args );
	}

	public function __get( $name ) {
		return $this->args[ $name ] ?? null;
	}

	public function __set( $name, $value ) {
		$this->args[ $name ] = $value;
	}

	/**
	 * @see WP_Post_Type::set_props()
	 */
	private function normalize( $args ) {
		$defaults = [
			// Mimic post type arguments.
			'labels'        => [],
			'show_in_menu'  => true,
			'menu_position' => null,
			'menu_icon'     => 'dashicons-admin-post',
			'parent'        => '',

			// New.
			'capability' => 'edit_posts',
		];
		$args = array_merge( $defaults, $args );

		$args['name']   = $this->name;
		$args['labels'] = $this->get_labels( $args['labels'] );

		return $args;
	}

	/**
	 * @see get_post_type_labels()
	 */
	private function get_labels( $labels ) {
		// Mimic post type labels.
		$defaults = [
			'name'          => _x( 'Models', 'model general name', 'mb-custom-table' ),
			'menu_name'     => _x( 'Models', 'model general name', 'mb-custom-table' ),
			'singular_name' => _x( 'Model', 'model singular name', 'mb-custom-table' ),
			'add_new'       => _x( 'Add New', 'model', 'mb-custom-table' ),
			'add_new_item'  => __( 'Add New Item', 'mb-custom-table' ),
			'edit_item'     => __( 'Edit Item', 'mb-custom-table' ),
			'search_items'  => __( 'Search Items', 'mb-custom-table' ),
			'not_found'     => __( 'No items found.', 'mb-custom-table' ),
			'all_items'     => __( 'All Items', 'mb-custom-table' ),
			'item_updated'  => __( 'Item updated.', 'mb-custom-table' ),

			// New.
			'item_added'   => __( 'Item added.', 'mb-custom-table' ),
			'item_deleted' => __( 'Item deleted.', 'mb-custom-table' ),
		];

		if ( ! isset( $labels['singular_name'] ) && isset( $labels['name'] ) ) {
			$labels['singular_name'] = $labels['name'];
		}
		if ( ! isset( $labels['menu_name'] ) && isset( $labels['name'] ) ) {
			$labels['menu_name'] = $labels['name'];
		}
		if ( ! isset( $labels['all_items'] ) && isset( $labels['menu_name'] ) ) {
			$labels['all_items'] = $labels['menu_name'];
		}

		$labels = array_merge( $defaults, $labels );

		return $labels;
	}
}