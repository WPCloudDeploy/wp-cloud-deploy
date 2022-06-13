<?php
/**
 * Basic server woocommerce
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BASIC_SERVER_WooCommerce
 */
class BASIC_SERVER_WooCommerce extends WPCD_WOOCOMMERCE {

	/**
	 * Available server sizes when ordering
	 *
	 * @var $sizes sizes.
	 */
	public static $sizes;

	/**
	 * VPN_Admin constructor.
	 */
	public function __construct() {
		self::$sizes = array(
			'small'  => __( 'Small', 'wpcd' ),
			'medium' => __( 'Medium', 'wpcd' ),
			'large'  => __( 'Large', 'wpcd' ),
		);

		// WooCommerce related hooks.
		add_action( 'woocommerce_product_data_panels', array( &$this, 'wc_basic_server_options' ) );
		add_filter( 'woocommerce_product_data_tabs', array( &$this, 'wc_basic_server_options_tabs' ) );
		add_action( 'woocommerce_process_product_meta', array( &$this, 'wc_basic_server_save' ), 10, 2 );
		add_action( 'woocommerce_payment_complete', array( &$this, 'wc_spinup_basic_server' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( &$this, 'wc_order_completed' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_cancelled', array( &$this, 'wc_kill_basic_server' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_expired', array( &$this, 'wc_kill_basic_server' ), 10, 1 );
		add_action( 'woocommerce_before_add_to_cart_button', array( &$this, 'wc_add_misc_attributes' ), 10 );
		add_filter( 'woocommerce_add_cart_item_data', array( &$this, 'wc_save_misc_attributes' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( &$this, 'wc_show_misc_attributes' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( &$this, 'wc_checkout_create_order_line_item' ), 10, 4 );
		add_filter( 'woocommerce_display_item_meta', array( &$this, 'wc_display_misc_attributes' ), 10, 3 );
		add_filter( 'woocommerce_thankyou_order_received_text', array( &$this, 'wc_thankyou_order_received_text' ), 10, 3 );
	}

	/**
	 * Show the Server attributes when item is added to the cart.
	 *
	 * @param string $html html.
	 * @param object $item item.
	 * @param array  $args args.
	 */
	public function wc_display_misc_attributes( $html, $item, $args ) {
		$strings = array();
		$html    = '';
		$args    = wp_parse_args(
			$args,
			array(
				'before'    => '<ul class="wc-item-meta"><li>',
				'after'     => '</li></ul>',
				'separator' => '</li><li>',
				'echo'      => true,
				'autop'     => false,
			)
		);

		foreach ( $item->get_formatted_meta_data() as $meta_id => $meta ) {
			$value = $args['autop'] ? wp_kses_post( $meta->display_value ) : wp_kses_post( make_clickable( trim( $meta->display_value ) ) );
			$key   = wp_kses_post( $meta->display_key );
			switch ( $key ) {
				case 'wpcd_app_basic_server_provider':
					$key       = __( 'Provider', 'wpcd' );
					$providers = WPCD_BASIC_SERVER_APP()->get_active_providers();
					$value     = $providers[ $meta->value ];
					break;
				case 'wpcd_app_basic_server_region':
					$provider = wc_get_order_item_meta( $item->get_id(), 'wpcd_app_basic_server_provider', true );
					if ( ! empty( WPCD_BASIC_SERVER_APP()->api( $provider ) ) ) {
						$regions = WPCD_BASIC_SERVER_APP()->api( $provider )->call( 'regions' );
					} else {
						$regions = null;
					}
					$key = __( 'Region', 'wpcd' );
					if ( ! empty( $regions ) ) {
						$value = $regions[ $meta->value ];
					} else {
						$value = __( 'missing', 'wpcd' );
					}
					break;
			}
			$value     = $args['autop'] ? $value : wp_kses_post( make_clickable( trim( $value ) ) );
			$strings[] = '<strong class="wc-item-meta-label">' . $key . ':</strong> ' . $value;
		}

		if ( $strings ) {
			$html = $args['before'] . implode( $args['separator'], $strings ) . $args['after'];
		}
		return $html;
	}

	/**
	 * Show the Server attributes when item is added to the cart.
	 *
	 * @param object $item item.
	 * @param string $cart_item_key cart_item_key.
	 * @param array  $values values.
	 * @param object $order order.
	 */
	public function wc_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
		do_action( 'wpcd_log_error', 'wc_checkout_create_order_line_item called for order ' . $order->get_id() . ' with values ' . print_r( $values, true ), 'debug', __FILE__, __LINE__ );

		if ( isset( $values['wpcd_app_basic_server_provider'] ) ) {
			$item->add_meta_data(
				'wpcd_app_basic_server_provider',
				$values['wpcd_app_basic_server_provider'],
				true
			);
		}
		if ( isset( $values['wpcd_app_basic_server_region'] ) ) {
			$item->add_meta_data(
				'wpcd_app_basic_server_region',
				$values['wpcd_app_basic_server_region'],
				true
			);
		}

	}

	/**
	 * Show the Server attributes when item is added to the cart.
	 *
	 * @param array $item_data item data.
	 * @param array $cart_item_data cart_item_data.
	 */
	public function wc_show_misc_attributes( $item_data, $cart_item_data ) {
		if ( isset( $cart_item_data['wpcd_app_basic_server_provider'] ) ) {
			$providers   = WPCD_BASIC_SERVER_APP()->get_active_providers();
			$item_data[] = array(
				'key'   => __( 'Provider', 'wpcd' ),
				'value' => wc_clean( $providers[ $cart_item_data['wpcd_app_basic_server_provider'] ] ),
			);
		}
		if ( isset( $cart_item_data['wpcd_app_basic_server_region'] ) ) {
			$regions     = WPCD_BASIC_SERVER_APP()->api( $cart_item_data['wpcd_app_basic_server_provider'] )->call( 'regions' );
			$item_data[] = array(
				'key'   => __( 'Region', 'wpcd' ),
				'value' => wc_clean( $regions[ $cart_item_data['wpcd_app_basic_server_region'] ] ),
			);
		}
		return $item_data;
	}

	/**
	 * Save the Server attributes when item is added to the cart.
	 *
	 * @param array $cart_item_data cart_item_data.
	 * @param int   $product_id product_id.
	 * @param int   $variation_id variation_id.
	 */
	public function wc_save_misc_attributes( $cart_item_data, $product_id, $variation_id ) {
		foreach ( $_POST as $param => $value ) {
			if ( strpos( $param, 'wpcd_app_basic_server' ) !== false ) {
				$cart_item_data[ $param ] = sanitize_text_field( $value );
			}
		}
		return $cart_item_data;
	}

	/**
	 * Add the Server attributes to the product detail page.
	 */
	public function wc_add_misc_attributes() {
		global $product;

		$is_vpn = get_post_meta( $product->get_id(), 'wpcd_app_basic_server_product', true );
		if ( 'yes' !== $is_vpn ) {
			return;
		}

		$provider_regions = array();
		$clouds           = WPCD_BASIC_SERVER_APP()->get_active_providers();
		$regions          = array();
		$providers        = array();
		foreach ( $clouds as $provider => $name ) {
			$locs = WPCD_BASIC_SERVER_APP()->api( $provider )->call( 'regions' );
			if ( is_wp_error( $locs ) ) {
				continue;
			}
			if ( empty( $regions ) ) {
				$regions = $locs;
			}
			$providers[ $provider ] = $name;
			$locations              = array();
			foreach ( $locs as $slug => $loc ) {
				$locations[] = array(
					'slug' => $slug,
					'name' => $loc,
				);
			}
			$provider_regions[ $provider ] = $locations;
		}

		wp_enqueue_script( 'wpcd-basic-server-wc', wpcd_url . 'includes/core/apps/basic-server/assets/js/wpcd-basic-server-wc.js', array( 'jquery', 'select2', 'wp-util' ), wpcd_scripts_version, true );
		wp_localize_script( 'wpcd-basic-server-wc', 'attributes', array( 'provider_regions' => $provider_regions ) );

		echo '<div class="wpcd-basic-server-custom-fields">';

		woocommerce_form_field(
			'wpcd_app_basic_server_provider',
			array(
				'type'     => 'select',
				'class'    => array( 'form-row-wide', 'wpcd-basic-server-provider' ),
				'label'    => __( 'Provider', 'wpcd' ),
				'options'  => $providers,
				'required' => true,
			)
		);

		woocommerce_form_field(
			'wpcd_app_basic_server_region',
			array(
				'type'     => 'select',
				'class'    => array( 'form-row-wide', 'wpcd-basic-server-region' ),
				'label'    => __( 'Region', 'wpcd' ),
				'options'  => $regions,
				'required' => true,
			)
		);

		echo '</div>
		<br clear="all">';
	}

	/**
	 * Kill the Server when subscription expires.
	 *
	 * @param object $subscription subscription.
	 */
	public function wc_kill_basic_server( \WC_Subscription $subscription ) {
		$orders = $subscription->get_related_orders();
		if ( ! $orders ) {
			return;
		}

		foreach ( $orders as $order_id ) {
			$instances = get_posts(
				array(
					'post_type'   => 'wpcd_app_server',
					'post_status' => 'private',
					'numberposts' => 300,
					'meta_query'  => array(
						'relation' => 'AND',
						array(
							'key'   => 'wpcd_server_wc_order_id',
							'value' => $order_id,
						),
						array(
							'key'   => 'wpcd_server_initial_app_name',
							'value' => 'basic-server',
						),						
					),
					'fields'      => 'ids',
				)
			);

			do_action( 'wpcd_log_error', 'Canceling ' . count( $instances ) . " instances in WC order ($order_id)", 'debug', __FILE__, __LINE__ );

			if ( $instances ) {
				foreach ( $instances as $id ) {
					do_action( 'wpcd_log_error', "Canceling Server $id as part of WC order ($order_id)", 'debug', __FILE__, __LINE__ );
					do_action( 'wpcd_basic_server_app_action', $id, 'delete' );
				}
			}
		}
	}

	/**
	 * Handle order completion status
	 *
	 * Action Hook: woocommerce_order_status_completed
	 *
	 * @param int $order_id order id.
	 */
	public function wc_order_completed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( 'cheque' === $order->get_payment_method() ) {
			// Check payments need to fire the server initialization process when
			// the status goes to completion...
			$this->wc_spinup_basic_server( $order_id );
		}
	}

	/**
	 * Spin up the Server when payment suceeds.
	 * Generally, this should happen only when
	 * the status goes to "processing".
	 * But, for certain gateways such as "Checks",
	 * this has to be done for "completed".
	 * In that case, this function will be called by
	 * the wc_order_completed() function from above.
	 *
	 * Action Hook: woocommerce_payment_complete
	 *
	 * @param int $order_id order id.
	 */
	public function wc_spinup_basic_server( $order_id ) {

		/* Do not spin up instances for renewal orders... */
		if ( function_exists( 'wcs_order_contains_renewal' ) ) {
			if ( true == wcs_order_contains_renewal( $order_id ) ) {
				return;
			}
		}

		/* This is a new order so lets do the thing... */
		$order = wc_get_order( $order_id );

		$user  = $order->get_user();
		$items = $order->get_items();
		foreach ( $items as $item ) {
			$product_id = $item->get_product_id();
			$is_vpn     = get_post_meta( $product_id, 'wpcd_app_basic_server_product', true );
			if ( 'yes' !== $is_vpn ) {
				continue;
			}

			$provider = wc_get_order_item_meta( $item->get_id(), 'wpcd_app_basic_server_provider', true );
			$region   = wc_get_order_item_meta( $item->get_id(), 'wpcd_app_basic_server_region', true );

			do_action( 'wpcd_log_error', "got VPN order ($order_id), payment method " . $order->get_payment_method() . " with provider/region = $provider/$region", 'debug', __FILE__, __LINE__ );

			if ( $provider ) {
				$subscription  = array();
				$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'product_id' => $item->get_product_id() ) );
				foreach ( $subscriptions as $subscription_id => $subscription_obj ) {
					$subscription[] = $subscription_id;
				}
				$subscription = array_filter( array_unique( $subscription ) );

				for ( $x = 0; $x < $item->get_quantity(); $x++ ) {
					$name            = sanitize_title( sprintf( '%s-%s-%s-%d', $user->user_nicename, get_gmt_from_date( '' ), implode( '-', $subscription ), ( $x + 1 ) ) );
					$scripts_version = wpcd_get_option( 'basic_server_script_version' );
					if ( ! empty( get_post_meta( $product_id, 'wpcd_app_basic_server_scripts_version', true ) ) ) {
						$scripts_version = get_post_meta( $product_id, 'wpcd_app_basic_server_scripts_version', true );
					}
					$attributes = array(
						'initial_app_name' => WPCD_BASIC_SERVER_APP()->get_app_name(),
						'scripts_version'  => $scripts_version,
						'region'           => $region,
						'size'             => get_post_meta( $product_id, 'wpcd_app_basic_server_size', true ),
						'name'             => $name,
						'wc_order_id'      => $order_id,
						'wc_subscription'  => $subscription,
						'wc_user_id'       => $user->ID,
						'provider'         => $provider,
						'init'             => true,
					);

					/* Create server */
					$instance = WPCD_SERVER()->create_server( 'create', $attributes );  // fire up a new server server.

					/* Install App on server */
					$instance = WPCD_BASIC_SERVER_APP()->add_app( $instance );

				}
			}
		}
	}

	/**
	 * Save the product details.
	 *
	 * @param int    $id id.
	 * @param object $post post.
	 */
	public function wc_basic_server_save( $id, $post ) {

		// Assume that this isn't a basic server product.
		delete_post_meta( $id, 'wpcd_app_basic_server_product' );

		// Check to see if it's a basic server product.
		if ( isset( $_POST['wpcd_app_basic_server_product'] ) ) {
			$product = sanitize_text_field( $_POST['wpcd_app_basic_server_product'] );
			update_post_meta( $id, 'wpcd_app_basic_server_product', $product );
		} else {
			$product = '';
		}

		// Remove existing BASIC SERVER data.
		delete_post_meta( $id, 'wpcd_app_basic_server_size' );
		delete_post_meta( $id, 'wpcd_app_basic_server_scripts_version' );

		// Add in new data.
		if ( 'yes' === $product ) {
			update_post_meta( $id, 'wpcd_app_basic_server_size', sanitize_text_field( $_POST['wpcd_app_basic_server_size'] ) );
			update_post_meta( $id, 'wpcd_app_basic_server_scripts_version', sanitize_text_field( $_POST['wpcd_app_basic_server_scripts_version'] ) );
		}
	}

	/**
	 * Add the BASIC SERVER tab to the product add/modify page.
	 *
	 * @param array $tabs tabs.
	 */
	public function wc_basic_server_options_tabs( $tabs ) {

		$tabs['wpcd_app_basic_server'] = array(
			'label'    => __( 'Basic Server', 'wpcd' ),
			'target'   => 'wpcd_app_basic_server_product_data',
			'class'    => array( 'show_if_subscription', 'show_if_variable' ),
			'priority' => 22,
		);
		return $tabs;

	}

	/**
	 * Add the contents to the Server tab of the product add/modify page.
	 */
	public function wc_basic_server_options() {

		echo '<div id="wpcd_app_basic_server_product_data" class="panel woocommerce_options_panel hidden">';

			woocommerce_wp_checkbox(
				array(
					'id'          => 'wpcd_app_basic_server_product',
					'value'       => get_post_meta( get_the_ID(), 'wpcd_app_basic_server_product', true ),
					'label'       => __( 'This is a Server', 'wpcd' ),
					'desc_tip'    => true,
					'description' => __( 'This is a Server', 'wpcd' ),
				)
			);

			woocommerce_wp_select(
				array(
					'id'      => 'wpcd_app_basic_server_size',
					'value'   => get_post_meta( get_the_ID(), 'wpcd_app_basic_server_size', true ),
					'label'   => __( 'Subscription size', 'wpcd' ),
					'options' => self::$sizes,
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => 'wpcd_app_basic_server_scripts_version',
					'value'       => get_post_meta( get_the_ID(), 'wpcd_app_basic_server_scripts_version', true ),
					'label'       => __( 'Version of Scripts', 'wpcd' ),
					'description' => __( 'Default version will be pulled from settings if nothing is entered here', 'wpcd' ),
					'type'        => 'text',
				)
			);

		echo '</div>';
	}

	/**
	 * Add text to the to of the WC thank you page.
	 *
	 * Filter Hook: woocommerce_thankyou_order_received_text
	 *
	 * @since 1.0.0
	 *
	 * @param string $str The current text of the thank you page.
	 * @param array  $order The woocommerce order object array.
	 *
	 * @return string The text to show on the WC thank you page.
	 */
	public function wc_thankyou_order_received_text( $str, $order ) {
		if ( $this->does_order_contain_item_of_type( $order, 'basic_server' ) ) {
			$str = $this->get_thank_you_text( $str, $order, 'basic_server' );
		}

		return $str;
	}

}
