<?php

/**
 * Parent class to display servers or apps table on front-end
 * 
 * @author Tahir Nazir
 */

if( !class_exists( 'WP_Posts_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
}

class WPCD_Public_List_Table extends WP_List_Table {
	
	/**
	 * Items per page
	 * 
	 * @var int 
	 */
	protected $posts_per_page = 10;
	
	/**
	 * Post type
	 * 
	 * @var string 
	 */
	protected $post_type;
	
	/**
	 * Base url of displaying page on front-end
	 * 
	 * @var string
	 */
	protected $base_url;
	
	/**
	 * Posts count based on status
	 * 
	 * @var array
	 */
	protected $status_post_counts = array();


	/**
	 * Constructor
	 * 
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		
		$this->base_url = isset( $args['base_url'] ) ? $args['base_url'] : '';
		
		parent:: __construct( array(
			'singular' => 'table example',
			'plural'   => 'table examples',
			'ajax'     => false
		) );
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
			$pagenum = filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT );
		} elseif( get_query_var('paged') ) {
			$pagenum = get_query_var('paged');
		}
		
		if ( isset( $this->_pagination_args['total_pages'] ) && $pagenum > $this->_pagination_args['total_pages'] ) {
			$pagenum = $this->_pagination_args['total_pages'];
		}

		return max( 1, $pagenum );
	}

	/**
	 * @see WP_List_Table::ajax_user_can()
	 */
	public function ajax_user_can()
	{
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Return item title with hyper link if item is viewable
	 * 
	 * @param type $item
	 * 
	 * @return string
	 */
	protected function getTitleColumn( $item ) {
		$post_type_object = get_post_type_object( $item->post_type );
		if ( is_post_type_viewable( $post_type_object ) && 'trash' !== $item->post_status ) {
			return sprintf( '<a href="%s">%s</a>', get_permalink( $item->ID ), $item->post_title );
		}
		return $item->post_title;
	}

	/**
	 * Prepare data for display
	 * 
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
		
		
		$posts_by_status = array();
		$items = array();
		
		foreach( $posts as $p ) {
			$posts_by_status[$p->post_status][] = $p;
		}
		
		$_status = filter_input( INPUT_GET, 'post_status', FILTER_SANITIZE_STRING );
		if( $_status ) {
			$items = isset( $posts_by_status[$_status] ) ? $posts_by_status[$_status] : array();
		} else {
			
			$all_statuses = get_post_stati( array( 'show_in_admin_all_list' => true ) );
			
			foreach( $posts as $p ) {
				if( !in_array($p->post_status, $all_statuses ) ) {
					continue;
				}
				$items[] = $p;
			}
		}
		
		$posts = $items;
		
		
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
	
	/**
	 * Return link for status view item
	 * 
	 * @param array $args
	 * @param string $label
	 * @param string $class
	 * 
	 * @return string
	 */
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

	/**
	 * Return status view links
	 * 
	 * @return array
	 */
	protected function get_views() {
		
		$class = '';
		$_status = filter_input( INPUT_GET, 'post_status', FILTER_SANITIZE_STRING );
		
		if ( empty( $_status ) ) {
			$class = 'current';
		}		
		
		$num_posts    = wp_count_posts( $this->post_type, 'readable' );
		
		$total_posts  = array_sum( (array) $num_posts );
		
		// Subtract post types that are not included in the admin all list.
		foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
			$total_posts -= $num_posts->$state;
		}
		
		$label = sprintf('%s <span class="count">(%s)</span>', __('All', 'wpcd'), $total_posts );
		$status_links['all'] = $this->get_status_link( [], $label, $class );
		
		foreach( $this->status_post_counts as $status => $count ) {
			
			$class = empty( $class ) && !empty( $_status ) && $status === $_status ? 'current' : '';
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