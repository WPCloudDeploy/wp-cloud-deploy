<?php

/**
 * Class to handle main page table view
 */
class WPCD_CT_List_Table extends \MetaBox\CustomTable\Model\ListTable {
	
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
	 * Init class params
	 * 
	 * @param array $args
	 */
	public function __construct( $args ) {
		parent::__construct( $args );
		
		$this->model    = $args['model'];
		$this->base_url = admin_url( "admin-ajax.php?page=model-{$this->model->name}" );
	}
	
	/**
	 * Prepare table items
	 */
	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();
		
		$custom_table = WPCD_MB_Custom_Table::get( $this->model->name );
		$per_page = $this->get_items_per_page( "{$this->model->name}_per_page", 20 );
		
		$page     = $this->get_pagenum();
		
		$results = $custom_table->get_items(array(
			'limit' => $per_page,
			'page'	=> $page,
			'orderby' => rwmb_request()->get('orderby'),
			'order' => rwmb_request()->get('order'),
		));
		
		$this->items = $results['items'];
		
		
		$this->set_pagination_args( [
			'total_items' => $results['items_count'],
			'per_page'    => $per_page,
		] );
	}
	
	/**
	 * Return item page link
	 * 
	 * @param array $item
	 * 
	 * @return string
	 */
	public function item_link( $item ) {
		$custom_table = WPCD_MB_Custom_Table::get( $this->model->name );
		return 
		add_query_arg( [
			'model-action' => 'edit',
			'model-id'     => $item['ID'],
		], $custom_table->get_listing_page_url( $custom_table->get_view() ) );
		
	}
	
	/**
	 * Return ID column content
	 * 
	 * @param array $item
	 * 
	 * @return string
	 */
	public function column_id( $item ) {
		return sprintf( '<a href="%s">#%d</a>', $this->item_link( $item ) , $item['ID'] );
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination.
	 * 
	 * @param string $which
	 * 
	 * @return string
	 */
	protected function extra_tablenav( $which ) {
		return '';
	}

	/**
	 * Row actions
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
		
		$actions = apply_filters( "mbct_{$this->model->name}_row_actions", array(), $item );

		return $this->row_actions( $actions );
	}
	
	/**
	 * Retrieves the list of bulk actions available for this table.
	 * 
	 * @return array
	 */
	public function get_bulk_actions() {
		return array();
	}
	
	/**
	 * Displays the bulk actions dropdown.
	 *
	 * @since 3.1.0
	 *
	 * @param string $which The location of the bulk actions: 'top' or 'bottom'.
	 *                      This is designated as optional for backward compatibility.
	 */
	protected function bulk_actions( $which = '' ) { }
	
	/**
	 * Displays the search box.
	 * 
	 * @param string $text
	 * @param string $input_id
	 */
	public function search_box( $text, $input_id ) { }

	
}
