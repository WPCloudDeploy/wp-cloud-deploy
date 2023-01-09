<?php

/**
 * Display table for child model
 */
class WPCD_CT_Childs_List_Table extends WPCD_CT_List_Table {
	
	/**
	 * Base url for table page
	 * 
	 * @var string
	 */
	protected $base_url;
	
	/**
	 * Model name
	 * 
	 * @var string
	 */
	private $model;
	
	
	/**
	 * Parent item id
	 * @var int
	 */
	private $model_id = '';
	
	/**
	 * Init class params
	 * 
	 * @param array $args
	 */
	public function __construct( $args ) {
		
		parent::__construct( $args );
		
		$this->model    = $args['model'];
		$this->base_url = admin_url( "admin-ajax.php?page=model-{$this->model->name}" );
		
		$this->model_id = isset( $args['model_id'] ) ? $args['model_id'] : '';
	}
	
	/**
	 * Return columns
	 * 
	 * @return array
	 */
	public function get_columns() {
		$columns = [
			'id' => __( 'ID', 'mb-custom-table' ),
		];
		return apply_filters( "mbct_{$this->model->name}_columns", $columns );
	}
	
	
	public function item_link( $item ) {
		
		$custom_table = WPCD_MB_Custom_Table::get($this->model->name);
		$view = $custom_table->get_view();
		
		return add_query_arg( [
					'action' =>  "wpcd_{$this->model->name}_inline_edit",
					'model-id'     => apply_filters("wpcd_ct_{$this->model->name}_child_table_item_id", $item['ID'], $item ),
					'parent-id'		=> isset( $item['parent_id'] ) ?  $item['parent_id'] : '',
					'nonce'			=> $custom_table->get_view_nonce( $view, "edit_{$this->model->name}" ),
					'view'			=> $view,
				], admin_url( "admin-ajax.php" ) );
		
	}
	/**
	 * ID column content
	 * 
	 * @param array $item
	 * 
	 * @return string
	 */
	public function column_id( $item ) {
		return sprintf( '<a href="%s" class="mp_edit_inline">#%d</a>', $this->item_link( $item ) , $item['ID'] );
	}
	
	/**
	 * Prepare table items
	 */
	public function prepare_items() {
		
		$this->_column_headers = $this->get_column_info();
		
		$custom_table = WPCD_MB_Custom_Table::get( $this->model->name );
		
		$per_page = $this->get_items_per_page( "{$this->model->name}_per_page", 20 );
		$page     = $this->get_pagenum();

		$results = $custom_table->get_items_by_parent( $this->model_id, array(
			'limit' => $per_page,
			'page'	=> $page,
			'orderby' => rwmb_request()->get('orderby'),
			'order' => rwmb_request()->get('order'),
		) );
		
		$this->items = $results['items'];
		
		$this->set_pagination_args( [
			'total_items' => $results['items_count'],
			'per_page'    => $per_page,
		] );
	}
	
	/**
	 * Gets a list of all, hidden, and sortable columns, with filter applied.
	 *
	 * @since 3.1.0
	 *
	 * @return array
	 */
	protected function get_column_info() {
		// $_column_headers is already set / cached.
		if ( isset( $this->_column_headers ) && is_array( $this->_column_headers ) ) {
			/*
			 * Backward compatibility for `$_column_headers` format prior to WordPress 4.3.
			 *
			 * In WordPress 4.3 the primary column name was added as a fourth item in the
			 * column headers property. This ensures the primary column name is included
			 * in plugins setting the property directly in the three item format.
			 */
			$column_headers = array( array(), array(), array(), $this->get_primary_column_name() );
			foreach ( $this->_column_headers as $key => $value ) {
				$column_headers[ $key ] = $value;
			}

			return $column_headers;
		}

		$columns = $this->get_columns();
		$hidden  = get_hidden_columns( $this->screen );

		$sortable_columns = $this->get_sortable_columns();
		/**
		 * Filters the list table sortable columns for a specific screen.
		 *
		 * The dynamic portion of the hook name, `$this->screen->id`, refers
		 * to the ID of the current screen.
		 *
		 * @since 3.1.0
		 *
		 * @param array $sortable_columns An array of sortable columns.
		 */
		$_sortable = apply_filters( "manage_{$this->screen->id}_sortable_columns", $sortable_columns );

		$sortable = array();
		foreach ( $_sortable as $id => $data ) {
			if ( empty( $data ) ) {
				continue;
			}

			$data = (array) $data;
			if ( ! isset( $data[1] ) ) {
				$data[1] = false;
			}

			$sortable[ $id ] = $data;
		}

		$primary               = $this->get_primary_column_name();
		$this->_column_headers = array( $columns, $hidden, $sortable, $primary );

		return $this->_column_headers;
	}
	
	/**
	 * Row action for child table
	 * 
	 * @param array $item
	 * @param string $column_name
	 * @param string $primary
	 * 
	 * @return string
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}
		
		$custom_table = WPCD_MB_Custom_Table::get($this->model->name);
		
		$actions = [
			'wpcd-ct-edit' => sprintf(
				'<a href="%s" class="wpcd-ct-edit-child-item mp_edit_inline">' . esc_html__( 'Edit', 'mb-custom-table' ) . '</a>',
				$this->item_link( $item )
			),
			'wpcd-ct-delete' => sprintf(
				'<a href="#" data-id="%d" data-model="%s" data-nonce="%s" class="wpcd-ct-delete-child-item">' . esc_html__( 'Delete', 'mb-custom-table' ) . '</a>',
				$item['ID'],
				$this->model->name,
				$custom_table->get_nonce('delete')
			)
		];

		$actions = apply_filters( "wpcd_mbct_{$this->model->name}_child_table_row_actions", $actions, $item );
		
		return $this->row_actions( $actions );
	}
}
