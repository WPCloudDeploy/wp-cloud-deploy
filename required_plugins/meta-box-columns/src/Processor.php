<?php
/**
 * The markup processor that adds column markup to a meta box.
 */

namespace MetaBox\Columns;

/**
 * Markup processor class.
 */
class Processor {
	/**
	 * Meta box settings.
	 *
	 * @var array
	 */
	protected $meta_box;

	/**
	 * Store the meta box's columns data.
	 *
	 * @var array
	 */
	protected $columns = array();

	/**
	 * Detect if a meta box has columns.
	 *
	 * @var bool
	 */
	protected $has_columns = false;

	/**
	 * Constructor.
	 *
	 * @param array $meta_box Meta box settings.
	 */
	public function __construct( $meta_box ) {
		$this->meta_box = $meta_box;
		$this->parse_columns();
		$this->parse_fields( $this->meta_box['fields'] );
	}

	/**
	 * Process all fields in the meta box.
	 */
	public function process() {
		if ( ! $this->has_columns ) {
			return;
		}
		$row = new Row( $this->columns, $this->meta_box['fields'] );
		$row->process();
		$this->meta_box['fields']  = $row->get_fields();
		$this->meta_box['columns'] = $this->columns;
	}

	/**
	 * Get meta box settings.
	 *
	 * @return array
	 */
	public function get_meta_box() {
		return $this->meta_box;
	}

	/**
	 * Fetch and store column data from meta box.
	 */
	protected function parse_columns() {
		if ( empty( $this->meta_box['columns'] ) || ! is_array( $this->meta_box['columns'] ) ) {
			return;
		}
		foreach ( $this->meta_box['columns'] as $key => $column ) {
			$this->columns[ sanitize_key( $key ) ] = $this->parse_column( $column );
		}
		$this->has_columns = true;
	}

	/**
	 * Parse column data.
	 *
	 * @param  array|int $column Column data.
	 *
	 * @return array
	 */
	protected function parse_column( $column ) {
		if ( is_array( $column ) ) {
			return wp_parse_args( $column, array(
				'size'  => 12,
				'class' => '',
			) );
		}

		return array(
			'size'  => intval( $column ),
			'class' => '',
		);
	}

	/**
	 * Parse meta box fields.
	 *
	 * @param array $fields List of fields.
	 */
	protected function parse_fields( &$fields ) {
		foreach ( $fields as &$field ) {
			if ( isset( $field['column'] ) || isset( $field['columns'] ) ) {
				$this->has_columns = true;
			}

			// If field doesn't have column settings, set default to 12 columns.
			if ( ! isset( $field['columns'] ) && ! isset( $field['column'] ) ) {
				$field['columns'] = 12;
			}
			if ( isset( $field['columns'] ) ) {
				$this->parse_field( $field );
			}

			if ( isset( $field['fields'] ) ) {
				$this->parse_fields( $field['fields'] );
			}
		}
	}

	/**
	 * Parse field column using "columns" setting.
	 *
	 * @param array $field Field settings.
	 */
	protected function parse_field( &$field ) {
		$column_id       = 'column-' . uniqid();
		$field['column'] = $column_id;

		$this->columns[ $column_id ] = array(
			'size'  => $field['columns'],
			'class' => '',
		);
		unset( $field['columns'] );
	}
}
