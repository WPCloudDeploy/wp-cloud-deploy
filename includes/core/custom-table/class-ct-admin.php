<?php

/**
 * Handle admin menu, admin pages and enqueue scripts
 */
class WPCD_CT_Admin extends \MetaBox\CustomTable\Model\Admin {
	
	/**
	 * Model Object
	 * 
	 * @var \MetaBox\CustomTable\Model\Model
	 */
	private $model;
	
	/**
	 * ListTable Object
	 * 
	 * @var Object
	 */
	public $list_table;
	
	/**
	 * 
	 * @param \MetaBox\CustomTable\Model\Model $model
	 */
	public function __construct( \MetaBox\CustomTable\Model\Model $model ) {
		$this->model = $model;
		parent::__construct( $model );
	}
	
	/**
	 * Create menu for admin pages
	 */
	public function add_menu() {
		$action = $this->action();
		$title = $this->model->labels['all_items'];
		if ( $action === 'add' ) {
			$title = $this->model->labels['add_new_item'];
		} elseif ( $action === 'edit' ) {
			$title = $this->model->labels['edit_item'];
		}

		if ( $this->model->parent ) {
			$page = add_submenu_page(
				$this->model->parent,
				$title,
				$this->model->labels['menu_name'],
				$this->model->capability,
				"model-{$this->model->name}",
				[ $this, 'render' ]
			);
		} else {
			$page = add_menu_page(
				$title,
				$this->model->labels['menu_name'],
				$this->model->capability,
				"model-{$this->model->name}",
				[ $this, 'render' ],
				$this->model->menu_icon,
				$this->model->menu_position
			);
		}

		add_action( "load-$page", [ $this, 'load_add_edit' ] );
		add_action( "load-$page", [ $this, 'load_list_table' ] );
		
		add_action( "admin_print_styles-$page", [ $this, 'enqueue' ] );
		
	}

	/**
	 * Render submit box on edit screen
	 */
	public function render_submit_box() {
		$output = $this->template_submit_box();
		echo apply_filters( 'mbct_submit_box', $output, $this->model );
	}

	/**
	 * Return content for submit box
	 * 
	 * @return string
	 */
	public function template_submit_box() {
		$custom_table = WPCD_MB_Custom_Table::get( $this->model->name );
		return $custom_table->submit_box();
	}

	/**
	 * Init table class
	 * 
	 * @return void
	 */
	public function load_list_table() {
		
		if ( !$this->is_screen_list() ) {
			return;
		}

		$this->list_table = new WPCD_CT_List_Table( [
			'model' => $this->model,
		] );
		
		// For admin columns extension to handle actions.
		do_action( "mbct_{$this->model->name}_list_table_load" );
	}
	
	/**
	 * Enqueue resources
	 * 
	 * @return void
	 */
	public function enqueue() {
		
		if ( $this->is_screen_edit() ) {
			wp_enqueue_style( 'mbct-model-edit', MBCT_URL . 'assets/edit.css', [], filemtime( MBCT_DIR . '/assets/edit.css' ) );
			wp_enqueue_script( 'mbct-model-edit', MBCT_URL . 'assets/edit.js', [], filemtime( MBCT_DIR . '/assets/edit.js' ), true );
			wp_localize_script( 'mbct-model-edit', 'Mbct', [
				'confirm' => __( 'Are you sure you want to delete? This action cannot be undone.', 'mb-custom-table' ),
			] );
			return;
		}

		wp_enqueue_style( 'mbct-list-table', MBCT_URL . 'assets/list-table.css', [], filemtime( MBCT_DIR . '/assets/list-table.css' ) );
		wp_enqueue_script( 'mbct-list-table', MBCT_URL . 'assets/list-table.js', ['jquery'], filemtime( MBCT_DIR . '/assets/list-table.js' ), true );
		wp_localize_script( 'mbct-list-table', 'MbctListTable', [
			'nonceDelete' => wp_create_nonce( 'delete-items' ),
			'confirm'     => __( 'Are you sure you want to delete? This action cannot be undone.', 'mb-custom-table' ),
		] );
	}

	/**
	 * Render page
	 * 
	 * @return void
	 */
	public function render() {
		
		$action = $this->action();
		$view = in_array( $action, ['add', 'edit'] ) ? $action : 'list-table';
		
		$custom_table = WPCD_MB_Custom_Table::get( $this->model->name );
		
		if( !$custom_table ) {
			return;
		}
		
		$permission = $custom_table->check_main_page_permission();
		
		if( $permission['has_access'] ) {
			include MBCT_DIR . "/views/$view.php";
		} else {
			echo $permission['message'];
		}
	}
	
	/**
	 * Is edit screen page
	 * 
	 * @return boolean
	 */
	private function is_screen_edit() {
		$custom_table = WPCD_MB_Custom_Table::get( $this->model->name );
		
		return $custom_table->is_custom_table_page() && $custom_table->is_item_add_edit_screen();
	}

	/**
	 * Is list screen page
	 * 
	 * @return boolean
	 */
	private function is_screen_list() {
		$custom_table = WPCD_MB_Custom_Table::get( $this->model->name );
		return $custom_table->is_custom_table_page() && !$custom_table->is_item_add_edit_screen();
	}

	/**
	 * Return current page action
	 * 
	 * @return string
	 */
	private function action() {
		return rwmb_request()->get( 'model-action' );
	}
}
