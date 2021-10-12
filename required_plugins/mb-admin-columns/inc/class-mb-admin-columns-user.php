<?php
/**
 * Class that manage user admin columns.
 *
 * @package    Meta Box
 * @subpackage MB Admin Columns
 */

/**
 * User admin columns class.
 */
class MB_Admin_Columns_User extends MB_Admin_Columns_Base {
	/**
	 * Initialization.
	 */
	public function init() {
		$priority = 20;
		add_filter( 'manage_users_columns', array( $this, 'columns' ), $priority );
		add_filter( 'manage_users_custom_column', array( $this, 'show' ), $priority, 3 );
		add_filter( 'manage_users_sortable_columns', array( $this, 'sortable_columns' ), $priority );

		// Other actions need to run only in Management page.
		add_action( 'load-users.php', array( $this, 'execute' ) );
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
		// add_action( 'pre_user_query', array( $this, 'sort' ) );
	}

	/**
	 * Show column content.
	 *
	 * @param string $output  Output of the custom column.
	 * @param string $column  Column ID.
	 * @param int    $user_id User ID.
	 *
	 * @return string
	 */
	public function show( $output, $column, $user_id ) {
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
			rwmb_the_value( $field['id'], array( 'object_type' => 'user' ), $user_id, false ),
			$config['after']
		);
	}

	/**
	 * Sort users by meta data.
	 *
	 * @param WP_User_Query $query WP_User_Query object.
	 */
	public function sort( $query ) {
		$field_id = (string) filter_input( INPUT_GET, 'orderby' );

		if ( ! $field_id || false === ( $field = $this->find_field( $field_id ) ) ) {
			return;
		}

		$query->set( 'orderby', in_array( $field['type'], array( 'number', 'slider', 'range' ), true ) ? 'meta_value_num' : 'meta_value' );
		$query->set( 'meta_key', $field_id );
	}

	/**
	 * Check if we in right page in admin area.
	 *
	 * @return bool
	 */
	private function is_screen() {
		return 'users' === get_current_screen()->id;
	}
}
