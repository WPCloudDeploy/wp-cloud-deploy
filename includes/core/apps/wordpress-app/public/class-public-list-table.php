<?php

/**
 * 
 * @author Tahir Nazir
 */

if( !class_exists( 'WP_Posts_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
}

class WPCD_Public_List_Table extends WP_List_Table {
	protected $order;
	protected $orderby;
	protected $posts_per_page = 10;
	protected $post_type;
	protected $base_url;
	protected $status_post_counts = array();



	public function __construct( $args = array() ) {
		
		$this->post_type = isset( $args['post_type'] ) ? $args['post_type'] : '';
		$this->base_url = isset( $args['base_url'] ) ? $args['base_url'] : '';
		
		parent:: __construct( array(
			'singular' => 'table example',
			'plural'   => 'table examples',
			'ajax'     => false
		) );
		
		$this->set_order();
		$this->set_orderby();
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
		
		$this->status_post_counts = array();
		
		foreach( $posts as $p ) {
			$this->status_post_counts[$p->post_status] = isset( $this->status_post_counts[$p->post_status] ) ? $this->status_post_counts[$p->post_status]+1 : 1;
		}
		
		
		$items = array();
		
		$_status = isset( $_REQUEST['post_status'] ) ? $_REQUEST['post_status'] : '';
		
		if( $_status ) {
			
			foreach( $posts as $p ) {
				
				if( $_status == $p->post_status ) {
					$items[]= $p;
				}
			}
			
			$posts = $items;
		}
		
		
		
		
		
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
		}
		$this->items = $posts_array;
	}
	
	protected function get_status_link( $args, $label, $class = '' ) {
		
		$url = add_query_arg( $args, $this->base_url );

		$class_html   = '';
		$aria_current = '';

		if ( ! empty( $class ) ) {
			$class_html = sprintf(
				' class="%s"',
				esc_attr( $class )
			);

			if ( 'current' === $class ) {
				$aria_current = ' aria-current="page"';
			}
		}

		return sprintf(
			'<a href="%s"%s%s>%s</a>',
			esc_url( $url ),
			$class_html,
			$aria_current,
			$label
		);
	}




	protected function get_views() {
		
		$class = '';
		if ( !isset( $_REQUEST['post_status'] ) ) {
			$class = 'current';
		}		
		
		$label = sprintf('%s <span class="count">(%s)</span>', __('All', 'wpcd'), array_sum( $this->status_post_counts ) );
		$status_links['all'] = $this->get_status_link( [], $label, $class );
		
		foreach( $this->status_post_counts as $status => $count ) {
			
			$class = empty( $class ) && isset( $_REQUEST['post_status'] ) && $status === $_REQUEST['post_status'] ? 'current' : '';
			$label = sprintf('%s <span class="count">(%s)</span>', ucfirst($status), $count );
			$status_links[$status] = $this->get_status_link( [ 'post_status' => $status ], $label , $class );
		}
		
		return $status_links;
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

}