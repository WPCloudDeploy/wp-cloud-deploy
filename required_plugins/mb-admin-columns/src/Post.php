<?php
namespace MBAC;

class Post extends Base {
	protected function init() {
		// Actions to show post columns can be executed via normal page request or via Ajax when quick edit.
		// Priority 20 allows us to overwrite WooCommerce settings.
		$priority = 20;
		add_filter( "manage_{$this->object_type}_posts_columns", array( $this, 'columns' ), $priority );
		add_action( "manage_{$this->object_type}_posts_custom_column", array( $this, 'show' ), $priority, 2 );
		add_filter( "manage_edit-{$this->object_type}_sortable_columns", array( $this, 'sortable_columns' ), $priority );

		// Other actions need to run only in Management page.
		add_action( 'load-edit.php', array( $this, 'execute' ) );
	}

	/**
	 * Actions need to run only in Management page.
	 */
	public function execute() {
		if ( ! $this->is_screen() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );

		if ( $this->searchable_field_ids ) {
			add_action( 'pre_get_posts', [ $this, 'search' ] );
		}

		add_action( 'pre_get_posts', [ $this, 'sort' ], 20 );
		add_action( 'restrict_manage_posts', [ $this, 'output_filters' ] );
	}

	/**
	 * Show column content.
	 *
	 * @param string $column  Column ID.
	 * @param int    $post_id Post ID.
	 */
	public function show( $column, $post_id ) {
		if ( false === ( $field = $this->find_field( $column ) ) ) {
			return;
		}

		$value = rwmb_meta( $field['id'], '', $post_id );
		if ( in_array( $value, [ '', [] ], true ) ) {
			return;
		}

		$config = array(
			'before' => '',
			'after'  => '',
		);
		if ( is_array( $field['admin_columns'] ) ) {
			$config = wp_parse_args( $field['admin_columns'], $config );
		}
		printf(
			'<div class="mb-admin-columns mb-admin-columns-%s" id="mb-admin-columns-%s">%s</div>',
			esc_attr( $field['type'] ),
			esc_attr( $field['id'] ),
			$config['before'] . rwmb_the_value( $field['id'], '', $post_id, false ) . $config['after']
		);
	}

	/**
	 * Sort by meta value.
	 *
	 * @param WP_Query $query The query.
	 */
	public function sort( $query ) {
		if ( ! $query->is_main_query() ) {
			return;
		}
		$orderby = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING );
		if ( ! $orderby || false === ( $field = $this->find_field( $orderby ) ) ) {
			return;
		}

		if ( $this->table ) {
			$this->sort_by_custom_table( $orderby );
		} else {
			$query->set( 'orderby', in_array( $field['type'], array( 'number', 'slider', 'range' ), true ) ? 'meta_value_num' : 'meta_value' );
			$query->set( 'meta_key', $orderby );
		}
	}

	private function sort_by_custom_table( $orderby ) {
		$order = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING );

		add_filter( 'posts_join', function( $join, $query ) {
			global $wpdb;
			$join .= "LEFT JOIN {$this->table} ON {$this->table}.ID = {$wpdb->posts}.ID";
			return $join;
		}, 10, 2 );

		add_filter( 'posts_orderby', function() use ( $orderby, $order ) {
			global $wpdb;
			$orderby = "{$this->table}.{$orderby} {$order}";
			return $orderby;
		}, 10, 2 );
	}

	public function search( $query ) {
		if ( ! $query->is_main_query() ) {
			return;
		}
		$s = filter_input( INPUT_GET, 's', FILTER_SANITIZE_STRING );
		if ( ! $s ) {
			return;
		}

		$by_title = $this->search_post_ids_by_title();
		$post_ids = $this->table ? $this->search_post_ids_by_tables() : $this->search_post_ids_by_metas();

		$post__in = $query->get( 'post__in' );
		$post__in = array_unique( array_filter( array_merge( $post__in, $by_title, $post_ids ) ) );
		rsort( $post__in );

		$query->set( 'post__in', $post__in );

		// Disable default search.
		$query->set( 's', '' );

		// But filter to show the search query in the admin page.
		add_filter( 'get_search_query', function() use ( $s ) {
			return $s;
		} );
	}

	private function search_post_ids_by_title() {
		global $wpdb;

		$s        = filter_input( INPUT_GET, 's', FILTER_SANITIZE_STRING );
		$sql      = "SELECT ID FROM $wpdb->posts WHERE post_title LIKE N'%" . esc_sql( $s ) . "%'";
		$post_ids = $wpdb->get_col( $sql );

		return $post_ids;
	}

	private function search_post_ids_by_metas() {
		global $wpdb;
		$s = filter_input( INPUT_GET, 's', FILTER_SANITIZE_STRING );

		$keys     = "'" . implode( "', '", $this->searchable_field_ids ) . "'";
		$sql      = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key IN ($keys) AND meta_value LIKE N'%" . esc_sql( $s ) . "%'";
		$post_ids = $wpdb->get_col( $sql );

		return $post_ids;
	}

	private function search_post_ids_by_tables() {
		if ( ! class_exists( 'MB_Custom_Table_API' ) ) {
			return array();
		}

		global $wpdb;

		$s = filter_input( INPUT_GET, 's', FILTER_SANITIZE_STRING );

		$where = array();
		foreach ( $this->searchable_field_ids as $field_id ) {
			$where[] = "{$field_id} LIKE N'%" . esc_sql( $s ) . "%'";
		}
		$where = implode( ' OR ', $where );

		$sql      = "SELECT ID FROM {$this->table} WHERE {$where}";
		$post_ids = $wpdb->get_col( $sql );

		return $post_ids;
	}

	/**
	 * Output filters in the All Posts screen.
	 *
	 * @param string $post_type The current post types.
	 */
	public function output_filters( $post_type ) {
		if ( $post_type !== $this->object_type ) {
			return;
		}
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		$taxonomies = array_filter( $taxonomies, array( $this, 'is_filterable' ) );
		array_walk( $taxonomies, array( $this, 'output_filter_for' ) );
	}

	/**
	 * Check if we have some taxonomies to filter.
	 *
	 * @param \WP_Taxonomy $taxonomy The taxonomy object.
	 *
	 * @return bool
	 */
	protected function is_filterable( $taxonomy ) {
		// Post category is filterable by default.
		if ( 'post' === $this->object_type && 'category' === $taxonomy->name ) {
			return false;
		}

		foreach ( $this->fields as $field ) {
			if ( ! is_array( $field['admin_columns'] ) || empty( $field['admin_columns']['filterable'] ) ) {
				continue;
			}
			if ( 'taxonomy' === $field['type'] && in_array( $taxonomy->name, $field['taxonomy'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Output filter for a taxonomy.
	 *
	 * @param \WP_Taxonomy $taxonomy The taxonomy object.
	 */
	protected function output_filter_for( $taxonomy ) {
		wp_dropdown_categories( array(
			'show_option_all' => sprintf( __( 'All %s', 'mb-admin-columns' ), $taxonomy->label ),
			'orderby'         => 'name',
			'order'           => 'ASC',
			'hide_empty'      => false,
			'hide_if_empty'   => true,
			'selected'        => filter_input( INPUT_GET, $taxonomy->query_var, FILTER_SANITIZE_STRING ),
			'hierarchical'    => true,
			'name'            => $taxonomy->query_var,
			'taxonomy'        => $taxonomy->name,
			'value_field'     => 'slug',
		) );
	}

	/**
	 * Check if we in right page in admin area.
	 *
	 * @return bool
	 */
	private function is_screen() {
		return get_current_screen()->post_type === $this->object_type;
	}
}