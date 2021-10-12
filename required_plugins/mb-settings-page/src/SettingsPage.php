<?php
namespace MBSP;

class SettingsPage {
	private $args;
	public $page_hook;
	protected $type;

	public function __construct( $args = [] ) {
		$this->args = $args;
		$this->register_hooks();
	}

	protected function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
	}

	public function register_admin_menu() {
		// Add top level menu.
		if ( ! $this->parent ) {
			$this->page_hook = add_menu_page(
				$this->page_title,
				$this->menu_title,
				$this->capability,
				$this->id,
				array( $this, 'show' ),
				$this->icon_url,
				$this->position
			);

			// If this menu has a default sub-menu.
			if ( $this->submenu_title ) {
				add_submenu_page(
					$this->id,
					$this->page_title,
					$this->submenu_title,
					$this->capability,
					$this->id,
					array( $this, 'show' )
				);
			}
		} // Add sub-menu.
		else {
			$this->page_hook = add_submenu_page(
				$this->parent,
				$this->page_title,
				$this->menu_title,
				$this->capability,
				$this->id,
				array( $this, 'show' )
			);
		}

		// Enqueue scripts and styles.
		add_action( "admin_print_styles-{$this->page_hook}", array( $this, 'enqueue' ) );

		// Load action.
		add_action( "load-{$this->page_hook}", array( $this, 'load' ) );
		add_action( "load-{$this->page_hook}", array( $this, 'add_help_tabs' ) );
		add_action( "load-{$this->page_hook}", array( $this, 'add_admin_notice_hook' ) );
	}

	public function show() {
		$class = trim( "wrap {$this->class}" );
		$class .= " rwmb-settings-{$this->style}";
		if ( $this->tabs ) {
			$class .= " rwmb-settings-tabs-{$this->tab_style}";
		}
		?>
		<div class="<?= esc_attr( $class ) ?>">
			<h1><?= esc_html( get_admin_page_title() ) ?></h1>

			<div class="rwmb-settings-wrap">
				<?php $this->output_tab_nav() ?>

				<div class="rwmb-settings-form-wrap">
					<form method="post" action="" enctype="multipart/form-data" id="post" class="rwmb-settings-form">
						<div id="poststuff">
							<?php
							// Nonce for saving meta boxes status (collapsed/expanded) and order.
							wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
							wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
							?>
							<div id="post-body" class="metabox-holder columns-<?= intval( $this->columns ); ?>">
								<?php if ( $this->columns > 1 ) : ?>
									<div id="postbox-container-1" class="postbox-container">
										<?php do_meta_boxes( null, 'side', null ); ?>
									</div>
								<?php endif; ?>
								<div id="postbox-container-2" class="postbox-container">
									<?php do_meta_boxes( null, 'normal', null ); ?>
									<?php do_meta_boxes( null, 'advanced', null ); ?>
								</div>
							</div>
							<br class="clear">
							<p class="submit">
								<?php submit_button( esc_html( $this->submit_button ), 'primary', 'submit', false ); ?>
								<?php do_action( 'mb_settings_page_submit_buttons' ); ?>
							</p>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	private function output_tab_nav() {
		if ( ! $this->tabs ) {
			return;
		}
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $this->tabs as $id => $tab ) {
			if ( is_string( $tab ) ) {
				$tab = ['label' => $tab];
			}
			$tab = wp_parse_args( $tab, [
				'icon'  => '',
				'label' => '',
			] );

			if ( filter_var( $tab['icon'], FILTER_VALIDATE_URL ) ) { // If icon is an URL.
				$icon = '<img src="' . esc_url( $tab['icon'] ) . '">';
			} else { // If icon is icon font.
				// If icon is dashicons, auto add class 'dashicons' for users.
				if ( false !== strpos( $tab['icon'], 'dashicons' ) ) {
					$tab['icon'] .= ' dashicons';
				}
				// Remove duplicate classes.
				$tab['icon'] = array_filter( array_map( 'trim', explode( ' ', $tab['icon'] ) ) );
				$tab['icon'] = implode( ' ', array_unique( $tab['icon'] ) );

				$icon = $tab['icon'] ? '<i class="' . esc_attr( $tab['icon'] ) . '"></i>' : '';
			}

			printf( '<a href="#tab-%s" class="nav-tab">%s%s</a>', esc_attr( $id ), $icon, esc_html( $tab['label'] ) );
		}
		echo '</h2>';
	}

	public function enqueue() {
		wp_enqueue_style( 'mb-settings-page', MBSP_URL . 'assets/settings.css', '', '1.3' );

		// For meta boxes.
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );

		// Enqueue settings page script and style.
		wp_enqueue_script( 'mb-settings-page', MBSP_URL . 'assets/settings.js', array( 'jquery' ), '1.1.2', true );
		wp_localize_script( 'mb-settings-page', 'MBSettingsPage', array(
			'pageHook' => $this->page_hook,
			'tabs'     => array_keys( $this->tabs ),
		) );
	}

	public function load() {
		$this->args['is_imported'] = $this->import();

		/**
		 * Custom hook runs when current page loads. Use this to add meta boxes and filters.
		 *
		 * @param array $page_args The page arguments
		 */
		do_action( 'mb_settings_page_load', $this->args );
	}

	public function add_admin_notice_hook() {
		if ( ! $this->parent || 'options-general.php' !== $this->parent ) {
			add_action( 'admin_notices', 'settings_errors' );
		}
	}

	public function add_help_tabs() {
		if ( ! $this->help_tabs || ! is_array( $this->help_tabs ) ) {
			return;
		}
		$screen = get_current_screen();
		foreach ( $this->help_tabs as $k => $help_tab ) {
			// Auto generate help tab ID if missed.
			if ( empty( $help_tab['id'] ) ) {
				$help_tab['id'] = "{$this->id}-help-tab-$k";
			}
			$screen->add_help_tab( $help_tab );
		}
	}

	protected function import() {
		$get_func    = 'network' === $this->type ? 'get_site_option' : 'get_option';
		$update_func = 'network' === $this->type ? 'update_site_option' : 'update_option';

		$option_name = $this->args['option_name'];

		$new = rwmb_request()->post( "{$option_name}_backup" );
		$new = wp_unslash( $new );
		$old = $get_func( $option_name );
		$old = json_encode( $old );
		if ( ! $new || $old === $new ) {
			return false;
		}
		$option = json_decode( $new, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$update_func( $option_name, $option );
		}
		return true;
	}

	public function __get( $name ) {
		return isset( $this->args[ $name ] ) ? $this->args[ $name ] : null;
	}
}
