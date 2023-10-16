<?php
namespace MBAC;
use MetaBox\Support\Arr;

class Post extends Base {
	private $field;

	protected function init() {
		// Actions to show post columns can be executed via normal page request or via Ajax when quick edit.
		// Priority 20 allows us to overwrite WooCommerce settings.
		$priority = 20;
		add_filter( "manage_{$this->object_type}_posts_columns", [ $this, 'columns' ], $priority );
		add_action( "manage_{$this->object_type}_posts_custom_column", [ $this, 'show' ], $priority, 2 );
		add_filter( "manage_edit-{$this->object_type}_sortable_columns", [ $this, 'sortable_columns' ], $priority );

		// Other actions need to run only in Management page.
		add_action( 'load-edit.php', [ $this, 'execute' ] );
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
		$field = $this->find_field( $column );
		if ( false === $field ) {
			return;
		}

		$value = rwmb_meta( $field['id'], '', $post_id );
		if ( in_array( $value, [ '', [] ], true ) ) {
			return;
		}

		$config = [
			'before' => '',
			'after'  => '',
			'link'   => false,
		];
		if ( is_array( $field['admin_columns'] ) ) {
			$config = wp_parse_args( $field['admin_columns'], $config );
		}

		$value = rwmb_the_value( $field['id'], '', $post_id, false );
		if ( $config['link'] === 'view' ) {
			$link  = get_permalink( $post_id );
			$value = '<a href="' . esc_url( $link ) . '">' . $value . '</a>';
		}

		if ( $config['link'] === 'edit' ) {
			$link  = get_edit_post_link( $post_id );
			$value = '<a href="' . esc_url( $link ) . '">' . $value . '</a>';
		}

		printf(
			'<div class="mb-admin-columns mb-admin-columns-%s" id="mb-admin-columns-%s">%s</div>',
			esc_attr( $field['type'] ),
			esc_attr( $field['id'] ),
			$config['before'] . $value . $config['after']
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

		$orderby = (string) filter_input( INPUT_GET, 'orderby' );
		$field   = $this->find_field( $orderby );
		if ( ! $orderby || false === $field ) {
			return;
		}

		if ( Arr::get( $field, 'admin_columns.sort' ) === 'numeric' ) {
			$field['type'] = 'number';
		}

		if ( $this->table ) {
			$this->sort_by_custom_table( $orderby, $field );
		} elseif ( isset( $field['query_args'] ) && array_key_exists( 'taxonomy', $field['query_args'] ) ) {
			$this->sort_by_taxonomy( $field );
		} else {
			$query->set( 'orderby', in_array( $field['type'], [ 'number', 'slider', 'range' ], true ) ? 'meta_value_num' : 'meta_value' );
			$query->set( 'meta_key', $orderby );
		}
	}

	public function sort_by_taxonomy( $field ) {
		$this->field = $field;

		add_filter( 'posts_clauses', function( $clauses, $query ) {
			global $wpdb;

			$taxonomy = $this->field['query_args']['taxonomy'][0];
			$field_id = $this->field['id'];

			if ( empty( $query->query['orderby'] ) || $query->query['orderby'] !== $field_id ) {
				return $clauses;
			}
			$clauses['join']   .= <<<SQL
				LEFT OUTER JOIN {$wpdb->term_relationships} ON {$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id
				LEFT OUTER JOIN {$wpdb->term_taxonomy} USING (term_taxonomy_id)
				LEFT OUTER JOIN {$wpdb->terms} USING (term_id)
SQL;
			$clauses['where']  .= "AND (taxonomy = '$taxonomy' OR taxonomy IS NULL)";
			$clauses['groupby'] = 'object_id';
			$clauses['orderby'] = "GROUP_CONCAT({$wpdb->terms}.name ORDER BY name ASC)";

			if ( strtoupper( $query->get( 'order' ) ) === 'ASC' ) {
				$clauses['orderby'] .= 'ASC';
			} else {
				$clauses['orderby'] .= 'DESC';
			}

			return $clauses;
		}, 10, 2 );
	}

	private function sort_by_custom_table( $orderby, $field ) {
		$order = (string) filter_input( INPUT_GET, 'order' );

		add_filter( 'posts_join', function( $join, $query ) {
			global $wpdb;
			$join .= "LEFT JOIN {$this->table} ON {$this->table}.ID = {$wpdb->posts}.ID";
			return $join;
		}, 10, 2 );

		add_filter( 'posts_orderby', function() use ( $orderby, $order, $field ) {
			if ( in_array( $field['type'], [ 'number', 'slider', 'range' ], true ) ) {
				$orderby = "{$this->table}.{$orderby}+0 {$order}";
			} else {
				$orderby = "{$this->table}.{$orderby} {$order}";
			}
			return $orderby;
		}, 10, 2 );
	}

	public function search( $query ) {
		if ( ! $query->is_main_query() ) {
			return;
		}
		$s = (string) filter_input( INPUT_GET, 's' );
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

		$s        = (string) filter_input( INPUT_GET, 's' );
		$sql      = "SELECT ID FROM $wpdb->posts WHERE post_title LIKE N'%" . esc_sql( $s ) . "%'";
		$post_ids = $wpdb->get_col( $sql );

		return $post_ids;
	}

	private function search_post_ids_by_metas() {
		global $wpdb;
		$s = (string) filter_input( INPUT_GET, 's' );

		$keys     = "'" . implode( "', '", $this->searchable_field_ids ) . "'";
		$sql      = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key IN ($keys) AND meta_value LIKE N'%" . esc_sql( $s ) . "%'";
		$post_ids = $wpdb->get_col( $sql );

		return $post_ids;
	}

	private function search_post_ids_by_tables() {
		if ( ! class_exists( 'MB_Custom_Table_API' ) ) {
			return [];
		}

		global $wpdb;

		$s = (string) filter_input( INPUT_GET, 's' );

		$where = [];
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
		$taxonomies = array_filter( $taxonomies, [ $this, 'is_filterable' ] );
		array_walk( $taxonomies, [ $this, 'output_filter_for' ] );
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
			if ( 'taxonomy' === $field['type'] && in_array( $taxonomy->name, $field['taxonomy'], true ) ) {
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
		wp_dropdown_categories( [
			'show_option_all' => $taxonomy->labels->all_items,
			'orderby'         => 'name',
			'order'           => 'ASC',
			'hide_empty'      => false,
			'hide_if_empty'   => true,
			'selected'        => (string) filter_input( INPUT_GET, $taxonomy->query_var ),
			'hierarchical'    => true,
			'name'            => $taxonomy->query_var,
			'taxonomy'        => $taxonomy->name,
			'value_field'     => 'slug',
		] );
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
