<?php
namespace MBAC;

class Model extends Base {
	protected function init() {
		$priority = 20;
		add_filter( "mbct_{$this->object_type}_columns", array( $this, 'columns' ), $priority );
		add_filter( "mbct_{$this->object_type}_sortable_columns", array( $this, 'sortable_columns' ), $priority );
		add_filter( "mbct_{$this->object_type}_column_output", array( $this, 'show' ), $priority, 4 );

		add_action( "mbct_{$this->object_type}_list_table_load", array( $this, 'execute' ) );
	}

	public function execute() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );

		if ( $this->searchable_field_ids ) {
			add_filter( "mbct_{$this->object_type}_query_where", [ $this, 'search' ], 0 );
		}

		add_filter( "mbct_{$this->object_type}_query_order", [ $this, 'sort' ], 0 );
	}

	/**
	 * Show column content.
	 *
	 * @param string $output Column output.
	 * @param string $column Column name.
	 * @param array  $item   Item values.
	 * @param object $model  Model object.
	 */
	public function show( $output, $column, $item, $model ) {
		$field = $this->find_field( $column );
		if ( false === $field ) {
			return $output;
		}

		$config = array(
			'before' => '',
			'after'  => '',
		);
		if ( is_array( $field['admin_columns'] ) ) {
			$config = wp_parse_args( $field['admin_columns'], $config );
		}

		$args = [
			'object_type' => 'model',
			'type'        => $model->name,
		];

		return sprintf(
			'<div class="mb-admin-columns mb-admin-columns-%s" id="mb-admin-columns-%s">%s%s%s</div>',
			esc_attr( $field['type'] ),
			esc_attr( $field['id'] ),
			$config['before'],
			rwmb_the_value( $field['id'], $args, $item['ID'], false ),
			$config['after']
		);
	}

	public function sort( $order ) {
		if ( $order || empty( $_REQUEST['orderby'] ) ) {
			return '';
		}
		$order  = 'ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
		$order .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		return $order;
	}

	public function search( $where ) {
		$s = (string) filter_input( INPUT_GET, 's' );
		if ( ! $s ) {
			return '';
		}

		$conditions = [];
		foreach ( $this->searchable_field_ids as $field_id ) {
			$conditions[] = "({$field_id} LIKE N'%" . esc_sql( $s ) . "%')";
		}
		$conditions = $conditions ? '(' . implode( ' OR ', $conditions ) . ')' : '';

		$where = $where ? "$where AND $conditions" : "WHERE $conditions";
		return $where;
	}
}
