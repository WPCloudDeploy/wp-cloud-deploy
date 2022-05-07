<?php

/**
 * Class to display servers table on front-end
 * 
 * @author Tahir Nazir
 */

if( !class_exists( 'WPCD_Public_List_Table' ) ) {
	require_once wpcd_path . 'includes/core/apps/wordpress-app/public/class-public-list-table.php';
}

class Servers_List_Table extends WPCD_Public_List_Table {
	
	/**
	 * Constructor
	 * 
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		
		$this->post_type = 'wpcd_app_server';
		
		parent::__construct( $args );
		$this->prepare_items();
	}
	
	/**
	 * Id for front end listing query identification
	 * 
	 * @return string
	 */
	protected function front_id() {
		return 'wpcd_app_server_front';
	}

	/**
	 * @see WP_List_Table::ajax_user_can()
	 */
	public function ajax_user_can()
	{
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Print no item message
	 */
	public function no_items()
	{
		_e( 'No server found.' );
	}

	/**
	 * Return table columns
	 * 
	 * @return array
	 */
	public function get_columns() {

		$columns = array(
			'title' => __('Title', 'wpcd'),
			'date' => __('Date', 'wpcd')
		);
		
		$columns = apply_filters( 'manage_wpcd_app_server_posts_columns', $columns );
		
		return $columns;
	}

	/**
	 * Row action, view|trash|delete etc
	 * 
	 * @param object $post
	 * @param string $column_name
	 * @param string $primary
	 * 
	 * @return string|array
	 */
	protected function handle_row_actions($post, $column_name, $primary) {
		if ( 'title' !== $column_name ) {
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
				$actions['wpcd_public_server_restore'] = sprintf(
					'<a class="wpcd_public_row_del_item_action" data-wpcd-action="restore" data-wpcd-type="server" data-wpcd-id="%d" data-wpcd-nonce="%s" href="#">%s</a>',
					$post->ID,
					wp_create_nonce( 'wpcd_public_server_restore_' . $post->ID ),
					esc_html( __( 'Restore', 'wpcd' ) )
				);
			} elseif ( EMPTY_TRASH_DAYS ) {
				
				$actions['wpcd_public_server_trash'] = sprintf(
					'<a class="wpcd_public_row_del_item_action" data-wpcd-action="trash" data-wpcd-type="server" data-wpcd-id="%d" data-wpcd-nonce="%s" href="#">%s</a>',
					$post->ID,
					wp_create_nonce( 'wpcd_public_server_trash_' . $post->ID ),
					_x( 'Trash', 'wpcd' )
				);
			}

			if ( 'trash' === $post->post_status || ! EMPTY_TRASH_DAYS ) {
				$actions['wpcd_public_server_delete'] = sprintf(
					'<a class="wpcd_public_row_del_item_action" data-wpcd-action="delete" data-wpcd-type="server" data-wpcd-id="%d" data-wpcd-nonce="%s" href="#">%s</a>',
					$post->ID,
					wp_create_nonce( 'wpcd_public_server_delete_' . $post->ID ),
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
				return $this->getTitleColumn( $item );
				break;
			default :
				
				ob_start();
				do_action( 'manage_wpcd_app_server_posts_custom_column', $column_name, $item->ID ); 
				return ob_get_clean();
				break;
		}
		
		
	}
	
}