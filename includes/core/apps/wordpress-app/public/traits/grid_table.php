<?php

trait wpcd_grid_table {

	/**
	 * Displays the table.
	 *
	 * @since 3.1.0
	 */
	public function display() {
		$singular = $this->_args['singular'];

		// Should we show the filter bar and filter view buttons from the admin?
		if ( wpcd_is_admin() && ! boolval( wpcd_get_option( 'wordpress_app_fe_hide_filter_bar_from_admin' ) ) ) {
			$this->display_tablenav( 'top' );
		}

		// Show the show bar to non-admin users?
		if ( ! wpcd_is_admin() && boolval( wpcd_get_option( 'wordpress_app_fe_show_filter_bar' ) ) ) {
			$this->display_tablenav( 'top' );
		}

		/**
		 * The max width var is added to the table output so that a JS script
		 * can dynamically add/remove some css classes to help with responsive
		 * behavior.
		 * Usually, the $max_width var is set by calling the $this->grid_responsive_width()
		 * function.  But because we're not interested in a table display at all,
		 * we're going to hardcode this value to a ridiculously high number.
		 * If in the future we want a true grid table, just set the value to the
		 * $this->grid_responsive_width() function.
		 */
		$max_width = 999999;

		?>
			<div class="wpcd-list-table wpcd-grid-table <?php echo implode( ' ', $this->get_table_classes() ); ?> table-hidden" data-max-width="<?php echo $max_width; ?>">

				<div class="wpcd-grid-table-loader"></div>

				<div class="wpcd-grid-table-columns">
					<?php $this->print_column_headers(); ?>
				</div>

				<?php $this->display_rows_or_placeholder(); ?>

				<div class="wpcd-grid-table-columns">
					<?php $this->print_column_headers( false ); ?>
				</div>

			</div>
		<?php
		$this->display_tablenav( 'bottom' );

		$this->print_style();
	}


	/**
	 * Prints column headers, accounting for hidden and sortable columns.
	 *
	 * @since 3.1.0
	 *
	 * @param bool $with_id Whether to set the ID attribute or not.
	 */
	public function print_column_headers( $with_id = true ) {
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$current_url = remove_query_arg( 'paged', $current_url );

		if ( isset( $_GET['orderby'] ) ) {
			$current_orderby = $_GET['orderby'];
		} else {
			$current_orderby = '';
		}

		if ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) {
			$current_order = 'desc';
		} else {
			$current_order = 'asc';
		}

		if ( ! empty( $columns['cb'] ) ) {
			static $cb_counter = 1;
			$columns['cb']     = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __( 'Select All' ) . '</label>'
				. '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
			$cb_counter++;
		}

		foreach ( $columns as $column_key => $column_display_name ) {
			$class = array( 'wpcd-grid-table-col', 'manage-column', "column-$column_key" );

			if ( in_array( $column_key, $hidden, true ) ) {
				$class[] = 'hidden';
			}

			if ( 'cb' === $column_key ) {
				$class[] = 'check-column';
			} elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ), true ) ) {
				$class[] = 'num';
			}

			if ( $column_key === $primary ) {
				$class[] = 'column-primary';
			}

			if ( isset( $sortable[ $column_key ] ) ) {
				list( $orderby, $desc_first ) = $sortable[ $column_key ];

				if ( $current_orderby === $orderby ) {
					$order = 'asc' === $current_order ? 'desc' : 'asc';

					$class[] = 'sorted';
					$class[] = $current_order;
				} else {
					$order = strtolower( $desc_first );

					if ( ! in_array( $order, array( 'desc', 'asc' ), true ) ) {
						$order = $desc_first ? 'desc' : 'asc';
					}

					$class[] = 'sortable';
					$class[] = 'desc' === $order ? 'asc' : 'desc';
				}

				$column_display_name = sprintf(
					'<a href="%s"><span>%s</span><span class="sorting-indicator"></span></a>',
					esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ),
					$column_display_name
				);
			}

			// $tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
			$tag   = 'div';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id    = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) {
				$class = "class='" . implode( ' ', $class ) . "'";
			}

			echo "<$tag $scope $id $class>$column_display_name</$tag>";
		}
	}


	/**
	 * Generates the tbody element for the list table.
	 *
	 * @since 3.1.0
	 */
	public function display_rows_or_placeholder() {
		if ( $this->has_items() ) {
			echo '<div class="wpcd-grid-table-rows">';
			$this->display_rows();
			echo '</div>';
		} else {
			echo '<div class="no-items">';
			$this->no_items();
			echo '</div>';
		}
	}

	/**
	 * Paint a single row.
	 *
	 * @param array $item Array of columns to paint for the row.
	 */
	public function single_row( $item ) {
		echo '<div class="wpcd-grid-table-row">';
		$this->single_row_columns( $item );
		echo '</div>';
	}


	/**
	 * Generates the columns for a single row of the table.
	 *
	 * @since 3.1.0
	 *
	 * @param object|array $item The current item.
	 */
	protected function single_row_columns( $item ) {
		global $post;

		$global_post = $post;
		$post        = $item;

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {

			echo '<div class="wpcd-grid-table-cell">';
			$classes = "$column_name column-$column_name";
			if ( $primary === $column_name ) {
				$classes .= ' has-row-actions column-primary';
			}

			if ( in_array( $column_name, $hidden, true ) ) {
				$classes .= ' hidden';
			}

			// Comments column uses HTML in the display name with screen reader text.
			// Strip tags to get closer to a user-friendly string.
			$data = 'data-colname="' . esc_attr( wp_strip_all_tags( $column_display_name ) ) . '"';

			$attributes = "class='$classes' $data";

			if ( 'cb' === $column_name ) {
				echo '<th scope="row" class="check-column">';
				echo $this->column_cb( $item );
				echo '</th>';
			} elseif ( method_exists( $this, '_column_' . $column_name ) ) {
				echo call_user_func(
					array( $this, '_column_' . $column_name ),
					$item,
					$classes,
					$data,
					$primary
				);
			} else {
				echo "<div $attributes>";
				echo "<span class=\"row_col_name\">{$column_display_name}</span>";
				if ( method_exists( $this, 'column_' . $column_name ) ) {
					echo call_user_func( array( $this, 'column_' . $column_name ), $item );
				} else {
					echo $this->column_default( $item, $column_name );
				}
				if ( $column_name === $this->getPrimaryColumn() ) {
					echo $this->handle_row_actions( $item, $column_name, $primary );
					do_action( "wpcd_public_{$this->post_type}_table_after_row_actions" );
				}
				echo '</div>';
			}

			echo '</div>';
		}

		$post = $global_post;
	}

	/**
	 * Return width for responsive view
	 *
	 * @return int
	 */
	public function grid_responsive_width() {

		$responsive_width = 0;

		foreach ( $this->grid_template_columns() as $width_template ) {

			$width = 100;
			if ( preg_match( '/^minmax\((.*)px,/', $width_template, $width_match ) ) {
				$width = (int) $width_match[1];
			}
			$responsive_width += $width;
		}

		return $responsive_width;

	}

	/**
	 * Print grid table columns template
	 */
	public function print_style() {

		$template_columns = implode( ' ', $this->grid_template_columns() );
		?>

		<style>

			.wpcd-grid-table-columns, .wpcd-grid-table-rows .wpcd-grid-table-row {
					grid-template-columns : <?php echo $template_columns; ?>
			}	
		</style>

		<?php
	}

	/**
	 * Return grid table columns template for css
	 *
	 * @return string
	 */
	public function grid_template_columns() {
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		$grid_template = array();

		foreach ( $columns as $column_key => $column_display_name ) {
			$class = array( 'wpcd-grid-table-col', 'manage-column', "column-$column_key" );
			$width = 'minmax(100px, 1fr)';

			if ( $column_key == $this->getPrimaryColumn() ) {
				$width = 'minmax(200px, 2fr)';
			}

			$grid_template[ $column_key ] = $width;
		}

		return apply_filters( $this->post_type . '_grid_template_columns', $grid_template );
	}


	/**
	 * Generates the required HTML for a list of row action links.
	 *
	 * @since 3.1.0
	 *
	 * @param string[] $actions        An array of action links.
	 * @param bool     $always_visible Whether the actions should be always visible.
	 * @return string The HTML for the row actions.
	 */
	protected function row_actions( $actions, $always_visible = false ) {
		$action_count = count( $actions );

		if ( ! $action_count ) {
			return '';
		}

		$mode = get_user_setting( 'posts_list_mode', 'list' );

		if ( 'excerpt' === $mode ) {
			$always_visible = true;
		}

		$out = '<div class="' . ( $always_visible ? 'row-actions visible' : 'row-actions' ) . '">';

		$i = 0;

		foreach ( $actions as $action => $link ) {
			++$i;

			$sep = ( $i < $action_count ) ? ' ' : '';

			$out .= "<span class='$action'>$link$sep</span>";
		}

		$out .= '</div>';

		$out .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details' ) . '</span></button>';

		return $out;
	}
}
