<?php
/**
 * Class that manage taxonomy admin columns.
 *
 * @package    Meta Box
 * @subpackage MB Admin Columns
 */

/**
 * Taxonomy admin columns class.
 */
class MB_Admin_Columns_Taxonomy extends MB_Admin_Columns_Base {
	/**
	 * Initialization.
	 */
	public function init() {
		$priority = 20;
		add_filter( "manage_edit-{$this->object_type}_columns", array( $this, 'columns' ), $priority );
		add_filter( "manage_{$this->object_type}_custom_column", array( $this, 'show' ), $priority, 3 );
		add_filter( "manage_edit-{$this->object_type}_sortable_columns", array( $this, 'sortable_columns' ), $priority );

		// Other actions need to run only in Management page.
		add_action( 'load-edit-tags.php', array( $this, 'execute' ) );
	}

	/**
	 * Actions need to run only in Management page.
	 */
	public function execute() {
		if ( ! $this->is_screen() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		// Sorting by meta value works unexpectedly.
		// @codingStandardsIgnoreLine
		// add_filter( 'get_terms_args', array( $this, 'sort' ) );
	}

	/**
	 * Show column content.
	 *
	 * @param string $output  Output of the custom column.
	 * @param string $column  Column ID.
	 * @param int    $term_id Term ID.
	 *
	 * @return string
	 */
	public function show( $output, $column, $term_id ) {
		if ( false === ( $field = $this->find_field( $column ) ) ) {
			return $output;
		}

		$config = array(
			'before' => '',
			'after'  => '',
		);
		if ( is_array( $field['admin_columns'] ) ) {
			$config = wp_parse_args( $field['admin_columns'], $config );
		}

		return sprintf(
			'<div class="mb-admin-columns mb-admin-columns-%s" id="mb-admin-columns-%s">%s%s%s</div>',
			$field['type'],
			$field['id'],
			$config['before'],
			rwmb_the_value( $field['id'], array( 'object_type' => 'term' ), $term_id, false ),
			$config['after']
		);
	}

	/**
	 * Sort by meta value.
	 *
	 * @param array $args Query parameters.
	 *
	 * @return array
	 */
	public function sort( $args ) {
		$field_id = (string) filter_input( INPUT_GET, 'orderby' );

		if ( ! $field_id || false === ( $field = $this->find_field( $field_id ) ) ) {
			return $args;
		}

		$args['orderby']  = in_array( $field['type'], array( 'number', 'slider', 'range' ), true ) ? 'meta_value_num' : 'meta_value';
		// @codingStandardsIgnoreLine
		$args['meta_key'] = $field_id;

		return $args;
	}

	/**
	 * Check if we in right page in admin area.
	 *
	 * @return bool
	 */
	private function is_screen() {
		return get_current_screen()->taxonomy === $this->object_type;
	}
}
