<?php
/**
 * WordPress App WPCD_WORDPRESS_APP.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_APP
 */

class WPCD_WORDPRESS_APP_PUBLIC {

/**
	 * Holds a reference to this class
	 *
	 * @var $instance instance.
	 */
	private static $instance;


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
	
	public function __construct() {
		
		
		if( !is_admin() ) {
			$this->public_hooks();
		}
	}
	
	
	private function public_hooks() {
		
		
		
		
		//add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 1 ); // class-wpcd-posts-app-server.php
		// Make sure WordPress loads up our css and js scripts on frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 1 );
		
		
		add_filter('the_content', array( $this, 'public_single_server_content' ) );
		add_filter( 'rwmb_meta_box_class_name', array( $this, 'rwmb_meta_box_class_name' ), 10, 2 );
		
		
		add_shortcode( 'wpcd_cloud_servers', array( $this, 'servers_shortcode' ) );
		add_shortcode( 'wpcd_deploy_server', array( $this, 'wpcd_deploy_server' ) );
		add_filter('pre_get_posts', array( $this, 'pre_get_posts' ) );
	}
	
	
	function wpcd_deploy_server() {
		
		ob_start();
		WPCD_WORDPRESS_APP()->ajax_server_handle_create_popup('public');
		$content = ob_get_clean();
		
		return '<div id="wpcd_public_wrapper">' . $content . '</div>';
	}
	
	
	/**
	 * Register the scripts for custom post types for the wp app.
	 *
	 * @param string $hook The page name hook.
	 */
	public function enqueue_scripts() {
		
		
		if( !self::is_public_page() ) {
			return;
		}
		
		
			
		WPCD_POSTS_APP_SERVER()->enqueue_server_post_common_scripts();
			
		
		if ( self::is_server_edit_page() ) {
			
			
//			wp_enqueue_style( 'rwmb', RWMB_CSS_URL . 'style.css', array(), RWMB_VER );
//			if ( is_rtl() ) {
//				wp_enqueue_style( 'rwmb-rtl', RWMB_CSS_URL . 'style-rtl.css', array(), RWMB_VER );
//			}
//
//			wp_enqueue_script( 'rwmb', RWMB_JS_URL . 'script.js', array( 'jquery' ), RWMB_VER, true );



			// Auto save.
//			if ( $this->autosave ) {
//				wp_enqueue_script( 'rwmb-autosave', RWMB_JS_URL . 'autosave.js', array( 'jquery' ), RWMB_VER, true );
//			}
			
			WPCD_POSTS_APP_SERVER()->enqueue_server_post_chart_scripts();
			
			
			
		}
		
		
		
		wp_enqueue_script( 'wpcd-wpapp-public-common', wpcd_url . 'includes/core/apps/wordpress-app/assets/js/wpcd-wpapp-public-common.js', array( 'jquery', 'wpcd-magnific' ), wpcd_scripts_version, true );
		wp_localize_script(
			'wpcd-wpapp-public-common',
			'wpcd_wpapp_params',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'is_public' => !is_admin(),
				'servers_list_page_url' => get_permalink( self::get_servers_list_page_id() )
			]
		);
		
		wp_enqueue_style( 'wpcd-public-common', wpcd_url . 'includes/core/apps/wordpress-app/assets/css/wpcd-public-common.css', wpcd_scripts_version, true );

	}
	
	public function rwmb_meta_box_class_name( $name, $setting ) {
		
		if( 'RW_Meta_Box' === $name ) {
			$name = 'RW_Meta_Box_Public';
			
			require_once wpcd_path . 'includes/core/apps/wordpress-app/public/rw_meta_box_public.php';
		}
		
		return $name;
	}
	
	
	function public_single_server_content($content) {
		
		
		if( self::is_server_edit_page() ) {
			
			if( wpcd_user_can_edit_server() ) {
				
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
		}
		
		return '<div id="wpcd_public_wrapper"><div class="wpcd_public_container">' . $content . '</div></div>';
	}
	
	
	
	function pre_get_posts( $query ) {
		
		if ( ! is_admin() && $query->is_main_query() && is_single() && isset( $query->query['post_type'] ) && $query->query['post_type'] == 'wpcd_app_server' ) {
			$query->set( 'post_status', 'private' ); 
		}
		
		return $query;
	}
	
	
	
	public function servers_shortcode() {
		
		
		if( !get_current_user_id() ) {
			return '<div id="wpcd_public_wrapper"><div class="wpcd_public_container">' . esc_html( __( 'You don\'t have permission to servers list.', 'wpcd' ) ) . '</div></div>';
		}
		
		
		
		require_once wpcd_path . 'includes/core/apps/wordpress-app/public/class-servers-list-table.php';
		
		
		$table = new Servers_List_Table();
		
		
		ob_start();
		
		?>
		
		<div id="wpcd_public_wrapper">
			<div id="wpcd_public_servers_container">
					
				<?php printf( '<a class="button deploy_button" href="%s">%s</a>', get_permalink( self::get_server_deploy_page_id() ) , __( 'Deploy A New WordPress Server', 'wpcd' ) ) ?>

				<form id="posts-filter" method="get">
					<input type="hidden" name="post_status" class="post_status_page" value="<?php echo ! empty( $_REQUEST['post_status'] ) ? esc_attr( $_REQUEST['post_status'] ) : 'all'; ?>" />
					<?php $table->display(); ?>
				</form>
		
			</div>
		</div>
				
		<?php
		return ob_get_clean();
	}
	
	
	
	public static function is_server_edit_page() {
		
		global $post;
		
		return $post && $post->post_type == 'wpcd_app_server';
	
//		if( is_admin() ) {
//			$screen = get_current_screen();
//			return is_object( $screen ) && 'wpcd_app_server' == $screen->post_type;
//		}
//
//		return is_singular('wpcd_app_server');
	}
	
	
	public static function is_servers_list_page() {
		global $post;
		
		return $post && $post->ID == self::get_servers_list_page_id();
	}
	
	
	public static function is_public_page() {
		global $post;
		
		
		if( !$post ) {
			return;
		}
		
		
		return self::is_server_edit_page() || $post->ID == self::get_servers_list_page_id() || $post->ID == self::get_server_deploy_page_id();
	}
	
	public static function get_servers_list_page_id() {
		return get_option( 'wpcd_public_servers_list_page_id' );
	}
	
	public static function get_server_deploy_page_id() {
		return get_option( 'wpcd_public_deploy_server_page_id' );
	}




	public static function page_exists( $name , $check_exists = false ) {
		
		$page_id = false;
		
		switch( $name ) {
			case 'servers_list':
				$page_id = self::get_servers_list_page_id();
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
	
	
	public static function deploy_server_page_exists( $check_exists = false ) {
		
		return self::page_exists('deploy_server', $check_exists);
	}
	

	public static function servers_list_page_exists( $check_exists = false ) {
		
		return self::page_exists('servers_list', $check_exists);
		
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