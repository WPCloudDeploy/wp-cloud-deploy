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
	 * User posts count
	 * 
	 * @var int
	 */
	protected $user_posts_count;
	
	/**
	 * Current status for posts listing
	 * 
	 * @var string
	 */
	protected $current_post_status = 'all';
	/**
	 * Constructor
	 * 
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		
		global $wpdb;
		
		$this->base_url = isset( $args['base_url'] ) ? $args['base_url'] : '';
		
		parent:: __construct( array(
			'singular' => 'table example',
			'plural'   => 'table examples',
			'ajax'     => false,
			'screen' => isset( $args['screen'] ) ? $args['screen'] : null
		) );
		
		$_status = filter_input( INPUT_GET, 'post_status', FILTER_SANITIZE_STRING );
		$this->current_post_status = $_status ? $_status : 'all';
		$post_type        = $this->post_type;
		
		$post_type_object = get_post_type_object( $post_type );
		$exclude_states = get_post_stati(
			array(
				'show_in_admin_all_list' => false,
			)
		);
		
		$this->user_posts_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( 1 )
				FROM $wpdb->posts
				WHERE post_type = %s
				AND post_status NOT IN ( '" . implode( "','", $exclude_states ) . "' )
				AND post_author = %d",
				$post_type,
				get_current_user_id()
			)
		);
		
		
		if ( $this->user_posts_count
			&& ! current_user_can( $post_type_object->cap->edit_others_posts )
			&& empty( $_REQUEST['post_status'] ) && empty( $_REQUEST['all_posts'] )
			&& empty( $_REQUEST['author'] ) && empty( $_REQUEST['show_sticky'] )
		) {
			$_GET['author'] = get_current_user_id();
		}
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
		if( isset( $_REQUEST['_page'] ) ) {
			$pagenum = filter_input( INPUT_GET, '_page', FILTER_SANITIZE_NUMBER_INT );
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
	 * Return posts per page number
	 * 
	 * @return int
	 */
	private function posts_per_page() {
		$posts_per_page = (int) get_user_option( "edit_{$this->post_type}_per_page" );
		if ( empty( $posts_per_page ) || $posts_per_page < 1 ) {
			$posts_per_page = 20;
		}
		
		$posts_per_page = apply_filters( "edit_{$this->post_type}_per_page", $posts_per_page );
		$posts_per_page = apply_filters( 'edit_posts_per_page', $posts_per_page, $this->post_type );
		
		return $posts_per_page;
	}
	
	
	/**
	 * Return results query
	 * 
	 * @param boolean|array $q
	 * 
	 * @return \WP_Query
	 */
	public function posts_query( $q = false ) {
		
		if ( false === $q ) {
			$q = $_GET;
		}
		
		$post_type = $this->post_type;
		$post_status      = '';
		$perm             = '';
		
		$post_stati = get_post_stati();
		
		if ( isset( $q['post_status'] ) && in_array( $q['post_status'], $post_stati, true ) ) {
			$post_status = $q['post_status'];
			$perm        = 'readable';
		}
		
		$posts_per_page = $this->posts_per_page();
		$paged = $this->get_pagenum();
		
		$query = compact( 'post_type', 'post_status', 'perm', 'posts_per_page', 'paged' );
		$query[ $this->front_id() ] = true;
		
		return new WP_Query( $query );
	}
	
	/**
	 * Prepare results
	 * 
	 * @global array $avail_post_stati
	 */
	public function prepare_items() {
		global  $avail_post_stati;

		$wp_query = $this->posts_query();
		
		$post_type = $this->post_type;
		
		$avail_post_stati = get_available_post_statuses( $post_type );

		$per_page = $this->posts_per_page();

		if ( $wp_query->found_posts || $this->get_pagenum() === 1 ) {
			$total_items = $wp_query->found_posts;
		} else {
			$post_counts = (array) wp_count_posts( $post_type, 'readable' );

			if ( isset( $_REQUEST['post_status'] ) && in_array( $_REQUEST['post_status'], $avail_post_stati, true ) ) {
				$total_items = $post_counts[ $_REQUEST['post_status'] ];
			} elseif ( isset( $_GET['author'] ) && get_current_user_id() === (int) $_GET['author'] ) {
				$total_items = $this->user_posts_count;
			} else {
				$total_items = array_sum( $post_counts );

				// Subtract post types that are not included in the admin all list.
				foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
					$total_items -= $post_counts[ $state ];
				}
			}
		}

		$this->is_trash = isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'];

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);
		
		$this->items = $wp_query->posts;
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
	 * Print table views
	 * 
	 * @return void
	 */
	public function views() {
		$views = $this->get_views();
		
		$views = apply_filters( "wpcd_public_table_views_{$this->post_type}", $views );

		if ( empty( $views ) ) {
			return;
		}

		echo "<ul class='subsubsub'>\n";
		foreach ( $views as $class => $view ) {
			$views[ $class ] = "\t<li class='$class'>$view";
		}
		echo implode( " |</li>\n", $views ) . "</li>\n";
		echo '</ul>';
	}
	
	
	/**
	 * Return table views
	 * 
	 * @global array $avail_post_stati
	 * 
	 * @return array
	 */
	protected function get_views() {
		global $avail_post_stati;

		$post_type = $this->post_type;

		$status_links = array();
		$num_posts    = wp_count_posts( $post_type, 'readable' );
		
		$total_posts  = array_sum( (array) $num_posts );
		$class        = '';

		$current_user_id = get_current_user_id();
		$all_args        = array();
		$mine            = '';
		
		// Subtract post types that are not included in the admin all list.
		foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
			$total_posts -= $num_posts->$state;
		}

		if ( $this->user_posts_count && $this->user_posts_count !== $total_posts ) {
			if ( isset( $_GET['author'] ) && ( $current_user_id === (int) $_GET['author'] ) ) {
				$class = 'current';
			}

			$mine_args = array('author'    => $current_user_id);

			$mine_inner_html = sprintf(
				/* translators: %s: Number of posts. */
				_nx(
					'Mine <span class="count">(%s)</span>',
					'Mine <span class="count">(%s)</span>',
					$this->user_posts_count,
					'posts'
				),
				number_format_i18n( $this->user_posts_count )
			);

			$mine = $this->get_status_link( $mine_args, $mine_inner_html , $class );
			
			$all_args['all_posts'] = 1;
			$class                 = '';
		}
		
		if ( empty( $class ) && ( $this->is_base_request() || $this->current_post_status == 'all' ) ) {
			$class = 'current';
		}

		$all_inner_html = sprintf(
			/* translators: %s: Number of posts. */
			_nx(
				'All <span class="count">(%s)</span>',
				'All <span class="count">(%s)</span>',
				$total_posts,
				'posts'
			),
			number_format_i18n( $total_posts )
		);

		$status_links['all'] = $this->get_status_link( $all_args, $all_inner_html , $class );
		
		if ( $mine ) {
			$status_links['mine'] = $mine;
		}

		foreach ( get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' ) as $status ) {
			$class = '';
			$status_name = $status->name;
			
			if ( ! in_array( $status_name, $avail_post_stati, true ) || empty( $num_posts->$status_name ) ) {
				continue;
			}

			if ( isset( $_REQUEST['post_status'] ) && $status_name === $_REQUEST['post_status'] ) {
				$class = 'current';
			}

			$status_args = array('post_status' => $status_name);

			$status_label = sprintf(
				translate_nooped_plural( $status->label_count, $num_posts->$status_name ),
				number_format_i18n( $num_posts->$status_name )
			);
			
			$status_links[ $status_name ] = $this->get_status_link( $status_args, $status_label , $class );
		}
		
		return $status_links;
	}
	
	/**
	 * Update paged query var from pagination links
	 */
	public function update_table_pagination_js() {
		?>
		<script type="text/javascript">
		( function($){

			$( function() {
				$('#posts-filter .tablenav-pages .pagination-links a').each( function() {
					var url = jQuery(this).attr('href');
					url = url.replace("paged=", "_page=")
					$(this).attr('href', url);
				});
			});
			
			$('#posts-filter .tablenav-pages .paging-input input.current-page[name=paged]').attr('name', '_page');

		})(jQuery);
		
		</script>
		<?php
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
		<div class="actions">
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