<?php
namespace MetaBox\CustomTable\Model;

class ListTable extends \WP_List_Table {
	private $base_url;
	private $model;
	private $table;

	public function __construct( $args ) {
		$this->model    = $args['model'];
		$this->table    = $this->model->table;
		$this->base_url = admin_url( "admin.php?page=model-{$this->model->name}" );

		parent::__construct( [
			'singular' => $this->model->labels['singular_name'],
			'plural'   => $this->model->labels['name'],
		] );
	}

	public function prepare_items() {
		global $wpdb;

		$this->_column_headers = $this->get_column_info();

		$per_page = $this->get_items_per_page( "{$this->model->name}_per_page", 20 );
		$page     = $this->get_pagenum();

		$where = apply_filters( "mbct_{$this->model->name}_query_where", '' );
		$order = apply_filters( "mbct_{$this->model->name}_query_order", '' );

		$this->set_pagination_args( [
			'total_items' => $this->get_total_items( $where ),
			'per_page'    => $per_page,
		] );

		$limit  = "LIMIT $per_page";
		$offset = ' OFFSET ' . ( $page - 1 ) * $per_page;
		$sql    = "SELECT * FROM $this->table $where $order $limit $offset";

		$this->items = $wpdb->get_results( $sql, 'ARRAY_A' );
	}

	private function get_total_items( $where ) {
		global $wpdb;
		return $wpdb->get_var( "SELECT COUNT(*) FROM $this->table $where" );
	}

	protected function extra_tablenav( $which ) {
		?>
		<div class="alignleft actions">
			<?php
			if ( 'top' === $which ) {
				ob_start();
				do_action( 'mbct_restrict_manage_posts', $this->model->name, $which );
				$output = ob_get_clean();

				if ( ! empty( $output ) ) {
					echo $output;
					submit_button( __( 'Filter', 'mb-custom-table' ), '', 'filter_action', false, [ 'id' => 'post-query-submit' ] );
				}
			}
			?>
		</div>
		<?php
		do_action( 'mbct_manage_posts_extra_tablenav', $which );
	}

	public function get_columns() {
		$columns = [
			'cb' => '<input type="checkbox">',
			'id' => __( 'ID', 'mb-custom-table' ),
		];

		return apply_filters( "mbct_{$this->model->name}_columns", $columns );
	}

	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="items[]" value="%s">',
			intval( $item['ID'] )
		);
	}

	public function column_id( $item ) {
		return sprintf(
			'<a href="%s">#%d</a>',
			add_query_arg( [
				'model-action' => 'edit',
				'model-id'     => $item['ID'],
			], $this->base_url ),
			$item['ID']
		);
	}

	public function column_default( $item, $column_name ) {
		$output = $item[ $column_name ] ?? '';

		return apply_filters( "mbct_{$this->model->name}_column_output", $output, $column_name, $item, $this->model );
	}

	public function get_sortable_columns() {
		return apply_filters( "mbct_{$this->model->name}_sortable_columns", [] );
	}

	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$actions = [
			'edit'   => sprintf(
				'<a href="%s">' . esc_html__( 'Edit', 'mb-custom-table' ) . '</a>',
				add_query_arg( [
					'model-action' => 'edit',
					'model-id'     => $item['ID'],
				], $this->base_url )
			),
			'delete' => sprintf(
				'<a href="#" data-id="%d">' . esc_html__( 'Delete', 'mb-custom-table' ) . '</a>',
				$item['ID'],
				$this->model->name
			),
		];

		$actions = apply_filters( "mbct_{$this->model->name}_row_actions", $actions, $item, $column_name );

		return $this->row_actions( $actions );
	}

	public function get_bulk_actions() {
		$actions = [
			'bulk-delete' => __( 'Delete', 'mb-custom-table' ),
		];

		return apply_filters( "mbct_{$this->model->name}_bulk_actions", $actions );
	}
}
