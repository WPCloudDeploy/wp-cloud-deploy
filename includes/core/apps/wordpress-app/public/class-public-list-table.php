<?php

/**
 * Description of class-servers-list-table
 *
 * @author Tahir Nazir
 */

if( !class_exists( 'WP_Posts_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
  //require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
//  require_once(ABSPATH . 'wp-admin/includes/screen.php');
//  require_once(ABSPATH . 'wp-admin/includes/class-wp-screen.php');
//  require_once(ABSPATH . 'wp-admin/includes/template.php');
}

class Servers_List_Table extends WP_List_Table {
	private $order;
	private $orderby;
	private $posts_per_page = 10;

	public function __construct() {
		parent:: __construct( array(
			'singular' => 'table example',
			'plural'   => 'table examples',
			'ajax'     => false
		) );

		//add_filter( 'get_list_instance', [$this, 'get_instance']);
		//$this->set_order();
		//$this->set_orderby();
		$this->prepare_items();
	}


	public function get_instance(){
	  return $this;
	}
	
	
	/**
	 * Gets the current page number.
	 *
	 * @since 3.1.0
	 *
	 * @return int
	 */
	public function get_pagenum() {
		
		
		$pagenum = 0;
		if( isset( $_REQUEST['paged'] ) ) {
			$pagenum = absint( $_REQUEST['paged'] );
		} elseif( get_query_var('paged') ) {
			$pagenum = get_query_var('paged');
		}
		
		

		if ( isset( $this->_pagination_args['total_pages'] ) && $pagenum > $this->_pagination_args['total_pages'] ) {
			$pagenum = $this->_pagination_args['total_pages'];
		}

		return max( 1, $pagenum );
	}

	private function get_sql_results() {
		
		
		
		
		return get_posts(array(
			'post_type' => 'wpcd_app_server',
			'post_status' => ['publish', 'private', 'draft'],
			'wpcd_app_server_front' => true,
//			'posts_per_page' => $this->posts_per_page,
//			'paged' => $this->current_page
				
		));
		
	}

	public function set_order() {
		$order = 'DESC';
		if ( isset( $_GET['order'] ) AND $_GET['order'] )
			$order = $_GET['order'];
		$this->order = esc_sql( $order );
	}

	public function set_orderby() {
		$orderby = 'post_date';
		if ( isset( $_GET['orderby'] ) AND $_GET['orderby'] )
			$orderby = $_GET['orderby'];
		$this->orderby = esc_sql( $orderby );
	}

	/**
	 * @see WP_List_Table::ajax_user_can()
	 */
	public function ajax_user_can()
	{
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @see WP_List_Table::no_items()
	 */
	public function no_items()
	{
		_e( 'No server found.' );
	}

	/**
	 * @see WP_List_Table::get_columns()
	 */
	public function get_columns() {

		$columns = array(
			//'cb' => '<input type="checkbox" />',
			'title' => __('Title', 'wpcd'),
			'date' => __('Date', 'wpcd')
		);
		
		$columns = apply_filters( 'manage_wpcd_app_server_posts_columns', $columns );
		
		return $columns;
	}

	/**
	 * @see WP_List_Table::get_sortable_columns()
	 */
	public function get_sortable_columns()
	{
		$sortable = array(
			'ID'         => array( 'ID', true ),
			'post_title' => array( 'post_title', true ),
			'post_date'  => array( 'post_date', true )
		);
		return $sortable;
	}

	/**
	 * Prepare data for display
	 * @see WP_List_Table::prepare_items()
	 */
	public function prepare_items()
	{
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array(
			$columns,
			$hidden,
			$sortable
		);

		// SQL results
		$posts = $this->get_sql_results();
		empty( $posts ) AND $posts = array();

		# >>>> Pagination
		$per_page     = $this->posts_per_page;
		$current_page = $this->get_pagenum();
		$total_items  = count( $posts );
		$this->set_pagination_args( array (
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page )
		) );
		$last_post = $current_page * $per_page;
		$first_post = $last_post - $per_page + 1;
		$last_post > $total_items AND $last_post = $total_items;

		// Setup the range of keys/indizes that contain
		// the posts on the currently displayed page(d).
		// Flip keys with values as the range outputs the range in the values.
		$range = array_flip( range( $first_post - 1, $last_post - 1, 1 ) );

		// Filter out the posts we're not displaying on the current page.
		$posts_array = array_intersect_key( $posts, $range );
		# <<<< Pagination

		// Prepare the data
		$permalink = __( 'Edit:' );
		foreach ( $posts_array as $key => $post )
		{
			$link     = get_edit_post_link( $post->ID );
			$no_title = __( 'No title set' );
			//$title    = ! $post->post_title ? "<em>{$no_title}</em>" : $post->post_title;
			//$posts[ $key ]->post_title = "<a title='{$permalink} {$title}' href='{$link}'>{$title}</a>";
		}
		$this->items = $posts_array;
	}
	
	
	protected function handle_row_actions($post, $column_name, $primary) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$post_type_object = get_post_type_object( $post->post_type );
		$actions          = array();
		$title            = _draft_or_post_title();

		
		if ( is_post_type_viewable( $post_type_object ) && 'trash' !== $post->post_status ) {
			
			$actions['view'] = sprintf(
				'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
				get_permalink( $post->ID ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $title ) ),
				__( 'View' )
			);
		}

		if ( current_user_can( 'delete_post', $post->ID ) ) {
			if ( 'trash' === $post->post_status ) {
				$actions['untrash'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-post_' . $post->ID ),
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'Restore &#8220;%s&#8221; from the Trash' ), $title ) ),
					__( 'Restore' )
				);
			} elseif ( EMPTY_TRASH_DAYS ) {
				$actions['trash'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link( $post->ID ),
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'Move &#8220;%s&#8221; to the Trash' ), $title ) ),
					_x( 'Trash', 'verb' )
				);
			}

			if ( 'trash' === $post->post_status || ! EMPTY_TRASH_DAYS ) {
				$actions['delete'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link( $post->ID, '', true ),
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently' ), $title ) ),
					__( 'Delete Permanently' )
				);
			}
		}

		
		
		$actions = apply_filters( 'post_row_actions', $actions, $post );
		
		return $this->row_actions( $actions );
	}




//
//	public function column_title( $item ) {
//		
//		
//		
//		$actions = array(
//            'edit'      => sprintf('<a href="?page=custom_detail_page&user=%s">Edit</a>',$item->ID),
//            'trash'    => sprintf('<a href="?page=custom_list_page&action=trash&user=%s">Trash</a>',$item->ID),
//            );
//
//			return sprintf('%1$s %2$s', $item->post_title, $this->row_actions($actions) );
//		
//	}
	
	
	/**
	 * A single column
	 */
	public function column_default( $item, $column_name )
	{
		
		switch ( $column_name ) {
			case 'title' :
				return $item->post_title;
				break;
			default :
				
				ob_start();
				do_action( 'manage_wpcd_app_server_posts_custom_column', $column_name, $item->ID ); 
				return ob_get_clean();
				break;
		}
		
		
	}

	/**
	 * Override of table nav to avoid breaking with bulk actions & according nonce field
	 */
	public function display_tablenav( $which ) {
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">
			<!--
			<div class="alignleft actions">
				<?php # $this->bulk_actions( $which ); ?>
			</div>
			 -->
			<?php
			$this->extra_tablenav( $which );
			$this->pagination( $which );
			?>
			<br class="clear" />
		</div>
		<?php
	}

	/**
	 * Disables the views for 'side' context as there's not enough free space in the UI
	 * Only displays them on screen/browser refresh. Else we'd have to do this via an AJAX DB update.
	 *
	 * @see WP_List_Table::extra_tablenav()
	 */
//	public function extra_tablenav( $which )
//	{
//		global $wp_meta_boxes;
//		$views = $this->get_views();
//		if ( empty( $views ) )
//			return;
//
//		$this->views();
//	}
	
	/**
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		
		
		?>
		<div class="alignleft actions">
		<?php
		if ( 'top' === $which ) {
			ob_start();

			$this->months_dropdown( $this->screen->post_type );
			$this->categories_dropdown( $this->screen->post_type );
			$this->formats_dropdown( $this->screen->post_type );

			/**
			 * Fires before the Filter button on the Posts and Pages list tables.
			 *
			 * The Filter button allows sorting by date and/or category on the
			 * Posts list table, and sorting by date on the Pages list table.
			 *
			 * @since 2.1.0
			 * @since 4.4.0 The `$post_type` parameter was added.
			 * @since 4.6.0 The `$which` parameter was added.
			 *
			 * @param string $post_type The post type slug.
			 * @param string $which     The location of the extra table nav markup:
			 *                          'top' or 'bottom' for WP_Posts_List_Table,
			 *                          'bar' for WP_Media_List_Table.
			 */
			do_action( 'restrict_manage_posts', $this->screen->post_type, $which );

			$output = ob_get_clean();

			if ( ! empty( $output ) ) {
				echo $output;
				submit_button( __( 'Filter' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
			}
		}

		if ( $this->is_trash && $this->has_items()
			&& current_user_can( get_post_type_object( $this->screen->post_type )->cap->edit_others_posts )
		) {
			submit_button( __( 'Empty Trash' ), 'apply', 'delete_all', false );
		}
		?>
		</div>
		<?php
		/**
		 * Fires immediately following the closing "actions" div in the tablenav for the posts
		 * list table.
		 *
		 * @since 4.4.0
		 *
		 * @param string $which The location of the extra table nav markup: 'top' or 'bottom'.
		 */
		do_action( 'manage_posts_extra_tablenav', $which );
	}
	
	
	
	
	
	
	
	
} // class

