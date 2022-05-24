<?php
/**
 * Handle all features related to front-end
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_APP_PUBLIC
 */

class WPCD_WORDPRESS_APP_PUBLIC {

/**
	 * Holds a reference to this class
	 *
	 * @var $instance instance.
	 */
	private static $instance;
	
	/**
	 * is it server edit page
	 * 
	 * @var null|boolean 
	 */
	public $is_server_edit_page = null;
	
	/**
	 * is it app listing page
	 * 
	 * @var null|boolean  
	 */
	public $is_apps_listing_page = null;


	/**
	 * Static function that can initialize the class
	 * and return an instance of itself.
	 *
	 * @TODO: This just seems to duplicate the constructor
	 * and is probably only needed when called by
	 * SPMM()->set_class()
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	public function __construct() {
		
		if( !is_admin() ) {
			$this->public_hooks();
		} elseif ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			add_action( "wp_ajax_wpcd_public_wordpress-app", array( $this, 'ajax_public' ) );
		}
	}
	
	/**
	 * Register hooks for front-end
	 */
	private function public_hooks() {
		
		// Make sure WordPress loads up our css and js scripts on frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 1 );
		
		
		add_filter('the_content', array( $this, 'public_single_server_content' ) );
		add_filter('the_content', array( $this, 'public_single_app_content' ) );
		add_filter( 'the_title', array( $this, 'server_app_title'), 11, 2  );

		add_filter( 'rwmb_meta_box_class_name', array( $this, 'rwmb_meta_box_class_name' ), 10, 2 );
				
		add_shortcode( 'wpcd_cloud_servers', array( $this, 'servers_shortcode' ) );
		add_shortcode( 'wpcd_cloud_server_apps', array( $this, 'server_apps_shortcode' ) );
		
		add_shortcode( 'wpcd_deploy_server', array( $this, 'wpcd_deploy_server' ) );
		add_filter('pre_get_posts', array( $this, 'pre_get_posts' ) );
		
		add_action( 'wp_head', array( $this, 'inline_style' ) );
		
		add_filter( "wpcd_public_table_views_wpcd_app_server", array( $this, 'wpcd_app_server_table_views' ), 11, 1 );
		add_filter( "wpcd_public_table_views_wpcd_app", array( $this, 'wpcd_app_table_views' ), 11, 1 );
		
		add_filter( 'removable_query_args', array( $this, 'removable_query_args') );
	}
	
	/**
	 * Remove _page query var to avoid duplication in pagination url
	 * 
	 * @param array $args
	 * 
	 * @return array
	 */
	function removable_query_args( $args ) {
		
		$args[] = '_page';
		return $args;
	}
	
	/**
	 * manipulate views for servers table
	 * 
	 * @param array $views
	 * 
	 * @return array
	 */
	function wpcd_app_server_table_views( $views ) {
		return WPCD_POSTS_APP_SERVER()->wpcd_app_manipulate_views( 'wpcd_app_server', $views, 'view_server', true );
	}
	
	/**
	 * manipulate views for apps table
	 * 
	 * @param array $views
	 * 
	 * @return array
	 */
	function wpcd_app_table_views( $views ) {
		return WPCD_POSTS_APP()->wpcd_app_manipulate_views( 'wpcd_app', $views, 'view_server', true );
	}
	
	/**
	 * Single entry point for all public ajax actions.
	 */
	public function ajax_public() {
		
		/* Get action requested */
		$action = filter_input( INPUT_POST, '_action', FILTER_SANITIZE_STRING );
		$id		= filter_input( INPUT_POST, 'id', FILTER_VALIDATE_INT );
		$nonce  = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
		
		
		$app_actions = array(
			'wpcd_public_app_trash',
			'wpcd_public_app_delete',
			'wpcd_public_app_restore',
		);
		
		$server_actions = array(
			'wpcd_public_server_trash',
			'wpcd_public_server_delete',
			'wpcd_public_server_restore'
		);
		
		$all_actions = array_merge( $app_actions, $server_actions );
		
		if( !in_array( $action, $all_actions ) || empty( $id ) ) {
			wp_send_json_error( array( 'msg' => __( 'You are not allowed to perform this operation on this app.', 'wpcd' ) ) );
			die();
		}
		
		
		if( !wp_verify_nonce( $nonce, "{$action}_{$id}" ) ) {
			
			wp_send_json_error( array( 'msg' => __( 'You are not allowed to perform this operation on this app.', 'wpcd' ) ) );
			die();
		}
		
		remove_action( 'wp_trash_post', array( WPCD_POSTS_APP(), 'wpcd_app_delete_post' ), 10, 1 );
		remove_action( 'before_delete_post', array( WPCD_POSTS_APP(), 'wpcd_app_delete_post' ), 10, 1 );
		
		remove_action( 'wp_trash_post', array( WPCD_POSTS_APP_SERVER(), 'wpcd_app_server_delete_post' ), 10, 1 );
		remove_action( 'before_delete_post', array( WPCD_POSTS_APP_SERVER(), 'wpcd_app_server_delete_post' ), 10, 1 );
		
		$success = false;
		$success_reload = true;
		$message = "";
		switch ( $action ) {
			
			case 'wpcd_public_app_trash':
				
				if ( !current_user_can( 'delete_post', $id ) ||  ! WPCD_POSTS_APP()->wpcd_app_delete_post( $id, true ) ) {
					$message = __( 'Sorry, you are not allowed to move this item to the Trash.', 'wpcd' );
				} elseif ( !wp_trash_post( $id ) ) {
					$message = __( 'Error in moving the item to Trash.', 'wpcd' );
				} else {
					$success = true;
					$message = __( 'App moved to Trash.', 'wpcd' );
				}
				
				break;
			case 'wpcd_public_app_delete':
				
				if ( !current_user_can( 'delete_post', $id ) ||  ! WPCD_POSTS_APP()->wpcd_app_delete_post( $id, true ) ) {
					$message = __( 'Sorry, you are not allowed to delete this item.', 'wpcd' );
				} elseif ( !wp_delete_post( $id , true ) ) {
					$message = __( 'Error while deleting this item.', 'wpcd' );
				} else {
					$success = true;
					$message = __( 'App deleted.', 'wpcd' );
				}
				
				break;
			case 'wpcd_public_app_restore':
				
				if ( !current_user_can( 'delete_post', $id ) ||  ! WPCD_POSTS_APP()->wpcd_app_delete_post( $id, true ) ) {
					$message = __( 'Sorry, you are not allowed to restore this item from the Trash.', 'wpcd' );
				} elseif ( !wp_untrash_post( $id ) ) {
					$message = __( 'Error while restoring the item from trash.', 'wpcd' );
				} else {
					$success = true;
					$message = __( 'App restored.', 'wpcd' );
				}
				break;
			case 'wpcd_public_server_trash':
				if ( !current_user_can( 'delete_post', $id ) ||  ! WPCD_POSTS_APP_SERVER()->wpcd_app_server_delete_post( $id, true ) ) {
					$message = __( 'Sorry, you are not allowed to move this item to the Trash.', 'wpcd' );
				} elseif ( !wp_trash_post( $id ) ) {
					$message = __( 'Error in moving the item to Trash.', 'wpcd' );
				} else {
					$success = true;
					$message = __( 'Server moved to Trash.', 'wpcd' );
				}
				break;
			case 'wpcd_public_server_delete':
				
				if ( !current_user_can( 'delete_post', $id ) ||  ! WPCD_POSTS_APP_SERVER()->wpcd_app_server_delete_post( $id, true ) ) {
					$message = __( 'Sorry, you are not allowed to delete this item.', 'wpcd' );
				} elseif ( !wp_delete_post( $id , true ) ) {
					$message = __( 'Error while deleting this item.', 'wpcd' );
				} else {
					$success = true;
					$message = __( 'Server deleted.', 'wpcd' );
				}
				break;
			case 'wpcd_public_server_restore':
				if ( !current_user_can( 'delete_post', $id ) ||  ! WPCD_POSTS_APP_SERVER()->wpcd_app_server_delete_post( $id, true ) ) {
					$message = __( 'Sorry, you are not allowed to restore this item from the Trash.', 'wpcd' );
				} elseif ( !wp_untrash_post( $id ) ) {
					$message = __( 'Error while restoring the item from trash.', 'wpcd' );
				} else {
					$success = true;
					$message = __( 'Server restored.', 'wpcd' );
				}
				break;
		}
		
		add_action( 'wp_trash_post', array( WPCD_POSTS_APP_SERVER(), 'wpcd_app_server_delete_post' ), 10, 1 );
		add_action( 'before_delete_post', array( WPCD_POSTS_APP_SERVER(), 'wpcd_app_server_delete_post' ), 10, 1 );
		add_action( 'wp_trash_post', array( WPCD_POSTS_APP(), 'wpcd_app_delete_post' ), 10, 1 );
		add_action( 'before_delete_post', array( WPCD_POSTS_APP(), 'wpcd_app_delete_post' ), 10, 1 );
		
		$result_data['msg'] = $message;
		$result_data['success_reload'] = $success_reload;
		
		$result = $result_data;
		
		if( $success ) {
			wp_send_json_success($result);
		}
		
		wp_send_json_error($result);
		die();
	}
	
	/**
	 * Return deploy server view
	 * 
	 * @return string
	 */
	function wpcd_deploy_server() {
		
		ob_start();
		WPCD_WORDPRESS_APP()->ajax_server_handle_create_popup('public');
		$content = ob_get_clean();
		
		return '<div id="wpcd_public_wrapper">' . $content . '</div>';
	}
	
	/**
	 * Inline style to handle width issue
	 * 
	 * @return string
	 */
	public function inline_style() {
		
		if( !self::is_public_page() || !class_exists( 'RW_Meta_Box' ) ) {
			return;
		}
		?>
		<style>
		.entry-content > *
		{
			max-width: 100% !important;
		}
		</style>
		<?php
	}
	
	/**
	 * Register the scripts for front-end.
	 * 
	 * @return void
	 */
	public function enqueue_scripts() {
		
		if( !self::is_public_page() || !class_exists( 'RW_Meta_Box' ) ) {
			return;
		}
		
		if( self::is_app_edit_page() || self::is_server_edit_page() ) {
			$hook = 'post.php';
		} else {
			$hook = 'edit.php';
		}
		
		WPCD()->wpcd_admin_scripts($hook);
		WPCD_WORDPRESS_APP()->wpapp_enqueue_scripts($hook);
		WPCD_POSTS_APP_SERVER()->enqueue_server_post_common_scripts();
		
		if ( self::is_server_edit_page() ) {
			WPCD_POSTS_APP_SERVER()->enqueue_server_post_chart_scripts();
		}
		
		wp_enqueue_script( 'wpcd-wpapp-public-common', wpcd_url . 'includes/core/apps/wordpress-app/assets/js/wpcd-wpapp-public-common.js', array( 'jquery', 'wpcd-magnific' ), wpcd_scripts_version, true );
		wp_localize_script(
			'wpcd-wpapp-public-common',
			'wpcd_wpapp_params',
			[
				'action' => 'wpcd_public_wordpress-app',
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'is_public' => !is_admin(),
				'servers_list_page_url' => get_permalink( self::get_servers_list_page_id() ),
				'apps_list_page_url' => get_permalink( self::get_apps_list_page_id() ),
				'app_delete_messages' => WPCD_POSTS_APP()->wpcd_app_trash_prompt_messages(),
				'server_delete_messages' => WPCD_POSTS_APP_SERVER()->wpcd_app_trash_prompt_messages()
			]
		);
		
		
		if ( self::is_server_edit_page() || self::is_app_edit_page() ) {
			wp_enqueue_script( 'wpcd-mbio-tabs-fix.', wpcd_url . 'assets/js/wpcd-mbio-tabs-fix.js', array( 'jquery', 'rwmb-tabs' ), wpcd_scripts_version, true );
		}
		
		wp_enqueue_style( 'wpcd-public-common', wpcd_url . 'includes/core/apps/wordpress-app/assets/css/wpcd-public-common.css', wpcd_scripts_version, true );
	}
	
	/**
	 * Register custom metabox class name
	 * 
	 * @param string $name
	 * @param array $setting
	 * 
	 * @return string
	 */
	public function rwmb_meta_box_class_name( $name, $setting ) {
		
		if( 'RW_Meta_Box' === $name && ( self::is_server_edit_page() || self::is_app_edit_page() ) ) {
			$name = 'RW_Meta_Box_Public';
			require_once wpcd_path . 'includes/core/apps/wordpress-app/public/rw_meta_box_public.php';
		}
		return $name;
	}
	
	/**
	 * Remove 'Private:' from apps and servers single posts
	 * 
	 * @param string $title
	 * @param int $id
	 * 
	 * @return string
	 */
	public function server_app_title( $title, $id ) {
		if( self::is_app_edit_page() || self::is_server_edit_page() ) {
			$post = get_post( $id );
			$title = isset( $post->post_title ) ? $post->post_title : '';
		}
		
		return $title;
	}
	
	/**
	 * Return single server content
	 * 
	 * @param string $content
	 * 
	 * @return string
	 */
	function public_single_server_content( $content ) {
		
		if( self::is_server_edit_page() ) {
			if( wpcd_user_can_edit_app_server() && class_exists( 'RW_Meta_Box' ) ) {
				ob_start();

				$metaboxes = array(
					'wpcd_server_wordpress-app_tab_top_of_server_details',
					'wpcd_server_wordpress-app_tab3'
				);
								
				echo '<div><a class="button wpcd-back_link" href="' . get_permalink( self::get_servers_list_page_id() ) . '">' . __( 'Cloud Servers', 'wpcd' ) . '</a></div>';

				foreach( $metaboxes as $mb ) {
					echo '<div id="'.$mb.'">';
					rwmb_get_registry( 'meta_box' )->get($mb)->show();
					echo '</div>';
				}

				$content = ob_get_clean();
			} else {
				$content = esc_html( __( 'You don\'t have permission to edit this post.', 'wpcd' ) );
			}
			
			$content = '<div id="wpcd_public_wrapper"><div class="wpcd_public_container">' . $content . '</div></div>';
		}
		
		return $content;
	}
	
	/**
	 * Return single server app content
	 * 
	 * @param string $content
	 * 
	 * @return string
	 */
	function public_single_app_content( $content ) {
		
		if( self::is_app_edit_page() ) {
			if( wpcd_user_can_edit_app_server(null, null, 'app') && class_exists( 'RW_Meta_Box' ) ) {
				
				ob_start();
				$metaboxes = array(
					'wpcd_wordpress-app_tab_top_of_site_details',
					'wpcd_wordpress-app_tab2'
				);
				
				echo '<div><a class="button wpcd-back_link" href="' . get_permalink( self::get_servers_list_page_id() ) . '">' . __( 'Cloud Servers', 'wpcd' ) . '</a></div>';

				foreach( $metaboxes as $mb ) {
					echo '<div id="'.$mb.'">';
					rwmb_get_registry( 'meta_box' )->get($mb)->show();
					echo '</div>';
				}

				$content = ob_get_clean();
			} else {
				$content = esc_html( __( 'You don\'t have permission to edit this post.', 'wpcd' ) );
			}
			
			$content = '<div id="wpcd_public_wrapper"><div class="wpcd_public_container">' . $content . '</div></div>';
		}
		
		return $content;
	}
	
	
	/**
	 * Customize query for front-end pages
	 * 
	 * @param object $query
	 * 
	 * @return object
	 */
	function pre_get_posts( $query ) {
		
		if( is_admin() || !$query->is_main_query() || !is_single() ) {
			return $query;
		}
		
		if( self::is_apps_list_page() || self::is_servers_list_page() ) {
			return $query;
		}
		
		if ( isset( $query->query['post_type'] ) && $query->query['post_type'] == 'wpcd_app_server' ) {
			$query->set( 'post_status', 'private' ); 
		}
		
		if ( isset( $query->query['post_type'] ) && $query->query['post_type'] == 'wpcd_app' ) {
			$query->set( 'post_status', 'private' ); 
		}
		
		return $query;
	}
	
	/**
	 * Display server apps table
	 * 
	 * @return string
	 */
	public function server_apps_shortcode() {
		
		if( !get_current_user_id() ) {
			return '<div id="wpcd_public_wrapper"><div class="wpcd_public_container">' . esc_html( __( 'You don\'t have permission to servers list.', 'wpcd' ) ) . '</div></div>';
		}
		
		if ( !class_exists( 'RW_Meta_Box' ) ) {
			return '';
		}
		
		require_once wpcd_path . 'includes/core/apps/wordpress-app/public/class-server-apps-table.php';
		
		$table = new WPCD_Server_Apps_List_Table(array(
			'post_type' => 'wpcd-app',
			'base_url' => get_permalink( self::get_apps_list_page_id() )
		));
		
		$post_status = filter_input( INPUT_GET, 'post_status', FILTER_SANITIZE_STRING );
		ob_start();
		?>
		
		<div id="wpcd_public_wrapper">
			<div id="wpcd_public_servers_container">
				<?php printf( '<a class="button deploy_button" href="%s">%s</a>', get_permalink( self::get_servers_list_page_id() ) , __( 'Install WordPress', 'wpcd' ) ) ?>
				<?php $table->views(); ?>
				<form id="posts-filter" method="get">
					<input type="hidden" name="post_status" class="post_status_page" value="<?php echo !empty( $post_status ) ? esc_attr( $post_status ) : 'all'; ?>" />
					<?php $table->display(); ?>
				</form>
		
			</div>
			<?php $table->update_table_pagination_js(); ?>
		</div>
				
		<?php
		return ob_get_clean();
	}
	
	
	/**
	 * Display servers table
	 * 
	 * @return string
	 */
	public function servers_shortcode() {
		
		if( !get_current_user_id() ) {
			return '<div id="wpcd_public_wrapper"><div class="wpcd_public_container">' . esc_html( __( 'You don\'t have permission to servers list.', 'wpcd' ) ) . '</div></div>';
		}
		
		if ( !class_exists( 'RW_Meta_Box' ) ) {
			return '';
		}
		
		require_once wpcd_path . 'includes/core/apps/wordpress-app/public/class-servers-list-table.php';
		
		$table = new Servers_List_Table(array(
			'post_type' => 'wpcd_app_server',
			'base_url' => get_permalink( self::get_servers_list_page_id() )
		));
		
		$post_status = filter_input( INPUT_GET, 'post_status', FILTER_SANITIZE_STRING );
		ob_start();
		?>
		<div id="wpcd_public_wrapper">
			<div id="wpcd_public_servers_container">
					
				<?php printf( '<a class="button deploy_button" href="%s">%s</a>', get_permalink( self::get_server_deploy_page_id() ) , __( 'Deploy A New WordPress Server', 'wpcd' ) ) ?>
				<?php $table->views(); ?>
				<form id="posts-filter" method="get">
					<input type="hidden" name="post_status" class="post_status_page" value="<?php echo !empty( $post_status ) ? esc_attr( $post_status ) : 'all'; ?>" />
					<?php $table->display(); ?>
				</form>
			</div>
			<?php $table->update_table_pagination_js(); ?>
		</div>
				
		<?php
		return ob_get_clean();
	}
	
	
	/**
	 * Is it server edit/view page
	 * 
	 * @global object $post
	 * 
	 * @return boolean
	 */
	public static function is_server_edit_page() {
		global $post;
		
		$object = self::instance();
		if( $object->is_server_edit_page === null ) {
			$server_id = wpcd_get_current_page_server_id();
			$post = get_post( $server_id );
			
			$object->is_server_edit_page = $post && $post->post_type == 'wpcd_app_server' ? true : false;
		}
		
		return $object->is_server_edit_page;
	}
	
	/**
	 * Is it app edit/view page
	 * 
	 * @global object $post
	 * 
	 * @return boolean
	 */
	public static function is_app_edit_page() {
		global $post;
		return $post && $post->post_type == 'wpcd_app';
	}
	
	/**
	 * Is it servers listing page
	 * 
	 * @global object $post
	 * 
	 * @return boolean
	 */
	public static function is_servers_list_page() {
		global $post;
		return $post && $post->ID == self::get_servers_list_page_id();
	}
	
	/**
	 * Is it server apps listing page
	 * 
	 * @global object $post
	 * 
	 * @return boolean
	 */
	public static function is_apps_list_page() {
		global $post;
		
		$object = self::instance();
		
		if( $object->is_apps_listing_page === null ) {
			$url = "http://".$_SERVER['SERVER_NAME']. explode( '?', $_SERVER['REQUEST_URI'] )[0];
			$post_id =  url_to_postid( $url );
			$object->is_apps_listing_page = $post_id &&  $post_id == self::get_apps_list_page_id() ? true : false;
		}
		
		return $object->is_apps_listing_page;
	}
	
	
	/**
	 * Is it a wpcd public view page
	 * 
	 * @global object $post
	 * 
	 * @return boolean
	 */
	public static function is_public_page() {
		global $post;
		
		if( !$post ) {
			return;
		}
		return self::is_server_edit_page() || self::is_servers_list_page() || self::is_apps_list_page() || self::is_app_edit_page() || $post->ID == self::get_server_deploy_page_id();
	}
	
	/**
	 * Return servers listing page id
	 * 
	 * @return int
	 */
	public static function get_servers_list_page_id() {
		return get_option( 'wpcd_public_servers_list_page_id' );
	}
	
	/**
	 * Return apps listing page id
	 * 
	 * @return int
	 */
	public static function get_apps_list_page_id() {
		return get_option( 'wpcd_public_apps_list_page_id' );
	}
	
	/**
	 * Return server deploy page id
	 * 
	 * @return int
	 */
	public static function get_server_deploy_page_id() {
		return get_option( 'wpcd_public_deploy_server_page_id' );
	}
	
	/**
	 * Check if a public page exists
	 * 
	 * @param string $name
	 * @param boolean $check_exists
	 * 
	 * @return boolean
	 */
	public static function page_exists( $name , $check_exists = false ) {
		
		$page_id = false;
		
		switch( $name ) {
			case 'servers_list':
				$page_id = self::get_servers_list_page_id();
				break;
			case 'servers_list':
				$page_id = self::get_apps_list_page_id();
				break;
			case 'deploy_server':
				$page_id = self::get_server_deploy_page_id();
				break;
		}
		
		
		if( $page_id  && $check_exists ) {
			$page = get_post( $page_id );

			if( !$page ) {
				$page_id = false;
			} 
		}

		return $page_id;
	}
	
	/**
	 * Check if deploy server page exists
	 * 
	 * @param boolean $check_exists
	 * 
	 * @return boolean
	 */
	public static function deploy_server_page_exists( $check_exists = false ) {
		return self::page_exists('deploy_server', $check_exists);
	}
	
	/**
	 * Check if servers listing page exists
	 * 
	 * @param boolean $check_exists
	 * 
	 * @return boolean
	 */
	public static function servers_list_page_exists( $check_exists = false ) {
		return self::page_exists('servers_list', $check_exists);
	}
	
	/**
	 * Check if server apps page exists
	 * 
	 * @param boolean $check_exists
	 * 
	 * @return boolean
	 */
	public static function apps_list_page_exists( $check_exists = false ) {
		return self::page_exists('appss_list', $check_exists);
	}
	
	/**
	 * Fires on activation of plugin.
	 *
	 * @param  boolean $network_wide    True if plugin is activated network-wide.
	 *
	 * @return void
	 */
	public static function activate( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			// Get all blogs in the network.
			$blog_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::create_pages();
				restore_current_blog();
			}
		} else {
			self::create_pages();
		}
	}
	
	/**
	 * Create pages on plugin activation
	 */
	public static function create_pages() {
		
		if( !self::servers_list_page_exists( true ) ) {
			
			$page_id = wp_insert_post( array(
				'post_title'    => __( 'Cloud Servers', 'wpcd' ),
				'post_content'  => '[wpcd_cloud_servers]',
				'post_status'   => 'publish',
				'post_author'   => get_current_user_id(),
				'post_type'     => 'page',
			) );

			add_option( 'wpcd_public_servers_list_page_id', $page_id );
		}
		
		if( !self::apps_list_page_exists( true ) ) {
			
			$page_id = wp_insert_post( array(
				'post_title'    => __( 'Cloud Apps', 'wpcd' ),
				'post_content'  => '[wpcd_cloud_server_apps]',
				'post_status'   => 'publish',
				'post_author'   => get_current_user_id(),
				'post_type'     => 'page',
			) );

			add_option( 'wpcd_public_apps_list_page_id', $page_id );
		}
		
		
		if( !self::deploy_server_page_exists( true ) ) {
			
			$page_id = wp_insert_post( array(
				'post_title'    => __( 'Deploy Server', 'wpcd' ),
				'post_content'  => '[wpcd_deploy_server]',
				'post_status'   => 'publish',
				'post_author'   => get_current_user_id(),
				'post_type'     => 'page',
			) );

			add_option( 'wpcd_public_deploy_server_page_id', $page_id );
		}
	}

}

?>