<?php

/**
 *
 * @author Tahir Nazir
 */

if( !class_exists( 'WPCD_Public_List_Table' ) ) {
	require_once wpcd_path . 'includes/core/apps/wordpress-app/public/class-public-list-table.php';
}

class WPCD_Server_Apps_List_Table extends WPCD_Public_List_Table {
	protected $order;
	protected $orderby;
	protected $posts_per_page = 10;

	public function __construct( $args ) {
		
		$this->post_type = 'wpcd-app';
		
		parent::__construct( $args );
		$this->prepare_items();
	}


	public function get_instance(){
	  return $this;
	}
	
	
	protected function get_sql_results() {
	
		return get_posts(array(
			'post_type' => 'wpcd_app',
			'post_status' => ['publish', 'private', 'draft', 'trash', 'pending', 'future'],
			'wpcd_app_front' => true
		));
		
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
		_e( 'No app found.' );
	}

	/**
	 * @see WP_List_Table::get_columns()
	 */
	public function get_columns() {

		$columns = array(
			'title' => __('Title', 'wpcd'),
			'date' => __('Date', 'wpcd')
		);
		
		$columns = apply_filters( 'manage_wpcd_app_posts_columns', $columns );
		
		return $columns;
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
				$actions['wpcd_public_app_restore'] = sprintf(
					'<a class="wpcd_public_row_del_item_action" data-wpcd-action="restore" data-wpcd-type="app" data-wpcd-id="%d" data-wpcd-nonce="%s" href="#">%s</a>',
					$post->ID,
					wp_create_nonce( 'wpcd_public_app_restore_' . $post->ID ),
					esc_html( __( 'Restore', 'wpcd' ) )
				);
				
				
			} elseif ( EMPTY_TRASH_DAYS ) {
				
				$actions['wpcd_public_app_trash'] = sprintf(
					'<a class="wpcd_public_row_del_item_action" data-wpcd-action="trash" data-wpcd-type="app" data-wpcd-id="%d" data-wpcd-nonce="%s" href="#">%s</a>',
					$post->ID,
					wp_create_nonce( 'wpcd_public_app_trash_' . $post->ID ),
					_x( 'Trash', 'wpcd' )
				);
				
			}

			if ( 'trash' === $post->post_status || ! EMPTY_TRASH_DAYS ) {
//				
				$actions['wpcd_public_app_delete'] = sprintf(
					'<a class="wpcd_public_row_del_item_action" data-wpcd-action="delete" data-wpcd-type="app" data-wpcd-id="%d" data-wpcd-nonce="%s" href="#">%s</a>',
					$post->ID,
					wp_create_nonce( 'wpcd_public_app_delete_' . $post->ID ),
					esc_html( __( 'Delete Permanently', 'wpcd' ) )
				);
			}
		}

		
		
		$actions = apply_filters( 'post_row_actions', $actions, $post );
		
		return $this->row_actions( $actions );
	}

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
				
				
				do_action( 'manage_wpcd_app_posts_custom_column', $column_name, $item->ID ); 
				return ob_get_clean();
				break;
		}
		
		
	}

}