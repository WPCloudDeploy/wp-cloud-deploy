<?php
/**
 * Stable Diffusion woocommerce.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class STABLEDIFF_WooCommerce.
 */
class STABLEDIFF_WooCommerce extends WPCD_WOOCOMMERCE {

	/**
	 * Holds a list of protocols - usually just TCP and UDP
	 *
	 * @var $protocol protocol.
	 */
	public $protocol;

	/**
	 * Holds a list of dns providers such as google, opendns and verisign
	 *
	 * @var $dns dns.
	 */
	public $dns;

	/**
	 * Available server sizes when ordering
	 *
	 * @var $sizes sizes.
	 */
	public static $sizes;

	/**
	 * STABLEDIFF_Admin constructor.
	 */
	public function __construct() {
		self::$sizes = array(
			'small'  => __( 'Small', 'wpcd' ),
			'medium' => __( 'Medium', 'wpcd' ),
			'large'  => __( 'Large', 'wpcd' ),
		);

		$this->protocol = WPCD_STABLEDIFF_APP()->get_protocols();
		$this->dns      = WPCD_STABLEDIFF_APP()->get_dns_providers();

		// WooCommerce related hooks.
		add_action( 'woocommerce_product_data_panels', array( &$this, 'wc_stablediff_options' ) );
		add_filter( 'woocommerce_product_data_tabs', array( &$this, 'wc_stablediff_options_tabs' ) );
		add_action( 'woocommerce_process_product_meta', array( &$this, 'wc_stablediff_save' ), 10, 2 );
		add_action( 'woocommerce_payment_complete', array( &$this, 'wc_spinup_stablediff' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( &$this, 'wc_order_completed' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_cancelled', array( &$this, 'wc_kill_stablediff' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_expired', array( &$this, 'wc_kill_stablediff' ), 10, 1 );
		add_action( 'woocommerce_before_add_to_cart_button', array( &$this, 'wc_add_misc_attributes' ), 10 );
		add_filter( 'woocommerce_add_cart_item_data', array( &$this, 'wc_save_misc_attributes' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( &$this, 'wc_show_misc_attributes' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( &$this, 'wc_checkout_create_order_line_item' ), 10, 4 );
		add_filter( 'woocommerce_display_item_meta', array( &$this, 'wc_display_misc_attributes' ), 10, 3 );
		add_filter( 'woocommerce_thankyou_order_received_text', array( &$this, 'wc_thankyou_order_received_text' ), 10, 3 );
	}

	/**
	 * Show the Server attributes on the thank you page under the purchased item.
	 *
	 * @param string $html html.
	 * @param object $item item.
	 * @param array  $args args.
	 */
	public function wc_display_misc_attributes( $html, $item, $args ) {

		/* If not a Stable Diffusion APP Item, exit... */
		$product_id = $item->get_product_id();
		$is_wpapp   = get_post_meta( $product_id, 'wpcd_app_stablediff_product', true );
		if ( 'yes' !== $is_wpapp ) {
			return $html;
		}

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
				case 'wpcd_app_stablediff_provider':
					$key       = __( 'Provider', 'wpcd' );
					$providers = WPCD_STABLEDIFF_APP()->get_active_providers();
					$value     = $providers[ $meta->value ];
					break;
				case 'wpcd_app_stablediff_region':
					$provider = wc_get_order_item_meta( $item->get_id(), 'wpcd_app_stablediff_provider', true );
					if ( ! empty( WPCD_STABLEDIFF_APP()->api( $provider ) ) ) {
						$regions = WPCD_STABLEDIFF_APP()->api( $provider )->call( 'regions' );
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
				case 'wpcd_app_stablediff_protocol':
					$key   = __( 'Protocol', 'wpcd' );
					$value = $this->protocol[ strval( $meta->value ) ];
					break;
				case 'wpcd_app_stablediff_dns':
					$key   = __( 'DNS', 'wpcd' );
					$value = $this->dns[ wp_strip_all_tags( $meta->value ) ];
					break;
				case 'wpcd_app_stablediff_port':
					$key = __( 'Port', 'wpcd' );
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
	 * Show the Stable Diffusion server attributes when item is added to the cart.
	 *
	 * @param object $item item.
	 * @param string $cart_item_key cart_item_key.
	 * @param array  $values values.
	 * @param object $order order.
	 */
	public function wc_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
		do_action( 'wpcd_log_error', 'wc_checkout_create_order_line_item called for order ' . $order->get_id() . ' with values ' . print_r( $values, true ), 'debug', __FILE__, __LINE__ );

		if ( isset( $values['wpcd_app_stablediff_provider'] ) ) {
			$item->add_meta_data(
				'wpcd_app_stablediff_provider',
				$values['wpcd_app_stablediff_provider'],
				true
			);
		}
		if ( isset( $values['wpcd_app_stablediff_region'] ) ) {
			$item->add_meta_data(
				'wpcd_app_stablediff_region',
				$values['wpcd_app_stablediff_region'],
				true
			);
		}
		if ( isset( $values['wpcd_app_stablediff_protocol'] ) ) {
			$item->add_meta_data(
				'wpcd_app_stablediff_protocol',
				$values['wpcd_app_stablediff_protocol'],
				true
			);
		}
		if ( isset( $values['wpcd_app_stablediff_dns'] ) ) {
			$item->add_meta_data(
				'wpcd_app_stablediff_dns',
				$values['wpcd_app_stablediff_dns'],
				true
			);
		}
		if ( isset( $values['wpcd_app_stablediff_port'] ) ) {
			$item->add_meta_data(
				'wpcd_app_stablediff_port',
				$values['wpcd_app_stablediff_port'],
				true
			);
		}
	}

	/**
	 * Show the Stable Diffusion server attributes when item is added to the cart.
	 *
	 * @param array $item_data item_data.
	 * @param array $cart_item_data cart_item_data.
	 */
	public function wc_show_misc_attributes( $item_data, $cart_item_data ) {
		if ( isset( $cart_item_data['wpcd_app_stablediff_provider'] ) ) {
			$providers   = WPCD_STABLEDIFF_APP()->get_active_providers();
			$item_data[] = array(
				'key'   => __( 'Provider', 'wpcd' ),
				'value' => wc_clean( $providers[ $cart_item_data['wpcd_app_stablediff_provider'] ] ),
			);
		}
		if ( isset( $cart_item_data['wpcd_app_stablediff_region'] ) ) {
			$regions     = WPCD_STABLEDIFF_APP()->api( $cart_item_data['wpcd_app_stablediff_provider'] )->call( 'regions' );
			$item_data[] = array(
				'key'   => __( 'Region', 'wpcd' ),
				'value' => wc_clean( $regions[ $cart_item_data['wpcd_app_stablediff_region'] ] ),
			);
		}
		if ( isset( $cart_item_data['wpcd_app_stablediff_protocol'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Protocol', 'wpcd' ),
				'value' => wc_clean( $this->protocol[ $cart_item_data['wpcd_app_stablediff_protocol'] ] ),
			);
		}
		if ( isset( $cart_item_data['wpcd_app_stablediff_dns'] ) ) {
			$item_data[] = array(
				'key'   => __( 'DNS', 'wpcd' ),
				'value' => wc_clean( $this->dns[ $cart_item_data['wpcd_app_stablediff_dns'] ] ),
			);
		}
		if ( isset( $cart_item_data['wpcd_app_stablediff_port'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Port', 'wpcd' ),
				'value' => wc_clean( $cart_item_data['wpcd_app_stablediff_port'] ),
			);
		}
		return $item_data;
	}

	/**
	 * Save the Stable Diffusion server attributes when item is added to the cart.
	 *
	 * @param array $cart_item_data cart_item_data.
	 * @param int   $product_id product_id.
	 * @param int   $variation_id variation_id.
	 */
	public function wc_save_misc_attributes( $cart_item_data, $product_id, $variation_id ) {
		foreach ( $_POST as $param => $value ) {
			if ( strpos( $param, 'wpcd_app_stablediff' ) !== false ) {
				$cart_item_data[ $param ] = sanitize_text_field( $value );
			}
		}
		return $cart_item_data;
	}

	/**
	 * Add the Stable Diffusion server attributes to the product detail page.
	 */
	public function wc_add_misc_attributes() {
		global $product;

		$is_stablediff = get_post_meta( $product->get_id(), 'wpcd_app_stablediff_product', true );
		if ( 'yes' !== $is_stablediff ) {
			return;
		}

		$provider_regions = array();
		$clouds           = WPCD_STABLEDIFF_APP()->get_active_providers();
		$regions          = array();
		$providers        = array();
		foreach ( $clouds as $provider => $name ) {
			$locs = WPCD_STABLEDIFF_APP()->api( $provider )->call( 'regions' );

			// if api key not provided or an error occurs, bail!
			if ( ! $locs || is_wp_error( $locs ) ) {
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

		wp_enqueue_script( 'wpcd-stablediff-wc', wpcd_url . 'includes/core/apps/stable-diffusion/assets/js/wpcd-stablediff-wc.js', array( 'jquery', 'select2', 'wp-util' ), wpcd_scripts_version, true );
		wp_localize_script( 'wpcd-stablediff-wc', 'attributes', array( 'provider_regions' => $provider_regions ) );

		echo '<div class="wpcd-stablediff-custom-fields">';

		woocommerce_form_field(
			'wpcd_app_stablediff_provider',
			array(
				'type'     => 'select',
				'class'    => array( 'form-row-wide', 'wpcd-stablediff-provider' ),
				'label'    => __( 'Provider', 'wpcd' ),
				'options'  => $providers,
				'required' => true,
			)
		);

		woocommerce_form_field(
			'wpcd_app_stablediff_region',
			array(
				'type'     => 'select',
				'class'    => array( 'form-row-wide', 'wpcd-stablediff-region' ),
				'label'    => __( 'Region', 'wpcd' ),
				'options'  => $regions,
				'required' => true,
			)
		);

		woocommerce_form_field(
			'wpcd_app_stablediff_protocol',
			array(
				'type'    => 'select',
				'class'   => array( 'form-row-wide' ),
				'label'   => __( 'Protocol', 'wpcd' ),
				'options' => $this->protocol,
			)
		);

		woocommerce_form_field(
			'wpcd_app_stablediff_port',
			array(
				'type'              => 'number',
				'class'             => array( 'form-row-wide' ),
				'label'             => __( 'Port', 'wpcd' ),
				'default'           => 1194,
				'custom_attributes' => array(
					'min' => 1,
					'max' => 65535,
				),
			)
		);

		woocommerce_form_field(
			'wpcd_app_stablediff_dns',
			array(
				'type'    => 'select',
				'class'   => array( 'form-row-wide' ),
				'label'   => __( 'DNS', 'wpcd' ),
				'options' => $this->dns,
			)
		);

		echo '</div>
		<br clear="all">';
	}

	/**
	 * Kill the Stable Diffusion server when subscription expires.
	 *
	 * @param object $subscription subscription.
	 */
	public function wc_kill_stablediff( \WC_Subscription $subscription ) {
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
							'value' => 'stablediff',
						),
					),
					'fields'      => 'ids',
				)
			);

			do_action( 'wpcd_log_error', 'Canceling ' . count( $instances ) . " instances in WC order ($order_id)", 'debug', __FILE__, __LINE__ );

			if ( $instances ) {
				foreach ( $instances as $id ) {
					do_action( 'wpcd_log_error', "Canceling Stable Diffusion Server $id as part of WC order ($order_id)", 'debug', __FILE__, __LINE__ );
					do_action( 'wpcd_app_action', $id, '', 'delete' );
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
			// Check payments need to fire the stable diffusion server initialization process when.
			// the status goes to completion...
			$this->wc_spinup_stablediff( $order_id );
		}
	}

	/**
	 * Spin up the Stable Diffusion server  when payment succeeds.
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
	public function wc_spinup_stablediff( $order_id ) {

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
			$product_id    = $item->get_product_id();
			$is_stablediff = get_post_meta( $product_id, 'wpcd_app_stablediff_product', true );
			if ( 'yes' !== $is_stablediff ) {
				continue;
			}

			$provider = wc_get_order_item_meta( $item->get_id(), 'wpcd_app_stablediff_provider', true );
			$region   = wc_get_order_item_meta( $item->get_id(), 'wpcd_app_stablediff_region', true );

			do_action( 'wpcd_log_error', "got stable diffusion server order ($order_id), payment method " . $order->get_payment_method() . " with provider/region = $provider/$region", 'debug', __FILE__, __LINE__ );

			if ( $provider ) {
				$subscription  = array();
				$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'product_id' => $item->get_product_id() ) );
				foreach ( $subscriptions as $subscription_id => $subscription_obj ) {
					$subscription[] = $subscription_id;
				}
				$subscription = array_filter( array_unique( $subscription ) );

				for ( $x = 0; $x < $item->get_quantity(); $x++ ) {
					$name            = sanitize_title( sprintf( '%s-%s-%s-%d', $user->user_nicename, get_gmt_from_date( '' ), implode( '-', $subscription ), ( $x + 1 ) ) );
					$scripts_version = wpcd_get_option( 'stablediff_script_version' );
					if ( ! empty( get_post_meta( $product_id, 'wpcd_app_stablediff_scripts_version', true ) ) ) {
						$scripts_version = get_post_meta( $product_id, 'wpcd_app_stablediff_scripts_version', true );
					}
					$attributes = array(
						'initial_app_name' => WPCD_STABLEDIFF_APP()->get_app_name(),
						'scripts_version'  => $scripts_version,
						'region'           => $region,
						'size'             => get_post_meta( $product_id, 'wpcd_app_stablediff_size', true ),
						'max_clients'      => get_post_meta( $product_id, 'wpcd_app_stablediff_max_clients', true ),
						'dns'              => wc_get_order_item_meta( $item->get_id(), 'wpcd_app_stablediff_dns', true ),
						'protocol'         => wc_get_order_item_meta( $item->get_id(), 'wpcd_app_stablediff_protocol', true ),
						'port'             => wc_get_order_item_meta( $item->get_id(), 'wpcd_app_stablediff_port', true ),
						'name'             => $name,
						'client'           => 'client1',
						'wc_order_id'      => $order_id,
						'wc_subscription'  => $subscription,
						'wc_user_id'       => $user->ID,
						'provider'         => $provider,
						'init'             => true,
					);

					/* Create server */
					$instance = WPCD_SERVER()->create_server( 'create', $attributes );  // fire up a new server server.

					/* Install App on server */
					$instance = WPCD_STABLEDIFF_APP()->add_app( $instance );
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
	public function wc_stablediff_save( $id, $post ) {
		// Assume the product is not a Stable Diffusion server product.
		delete_post_meta( $id, 'wpcd_app_stablediff_product' );

		// Check to see if this is a Stable Diffusion server product.
		if ( isset( $_POST['wpcd_app_stablediff_product'] ) ) {
			$product = sanitize_text_field( $_POST['wpcd_app_stablediff_product'] );
			update_post_meta( $id, 'wpcd_app_stablediff_product', $product );
		} else {
			$product = '';
		}

		// Remove existing Stable Diffusion server data.
		delete_post_meta( $id, 'wpcd_app_stablediff_size' );
		delete_post_meta( $id, 'wpcd_app_stablediff_max_users' );
		delete_post_meta( $id, 'wpcd_app_stablediff_max_clients' );
		delete_post_meta( $id, 'wpcd_app_stablediff_max_bandwidth' );
		delete_post_meta( $id, 'wpcd_app_stablediff_scripts_version' );

		// Add new data.
		if ( 'yes' === $product ) {
			update_post_meta( $id, 'wpcd_app_stablediff_size', sanitize_text_field( $_POST['wpcd_app_stablediff_size'] ) );
			update_post_meta( $id, 'wpcd_app_stablediff_max_users', sanitize_text_field( $_POST['wpcd_app_stablediff_max_users'] ) );
			update_post_meta( $id, 'wpcd_app_stablediff_max_clients', sanitize_text_field( $_POST['wpcd_app_stablediff_max_clients'] ) );
			update_post_meta( $id, 'wpcd_app_stablediff_max_bandwidth', sanitize_text_field( $_POST['wpcd_app_stablediff_max_bandwidth'] ) );
			update_post_meta( $id, 'wpcd_app_stablediff_scripts_version', sanitize_text_field( $_POST['wpcd_app_stablediff_scripts_version'] ) );
		}
	}

	/**
	 * Add the Stable Diffusion tab to the product add/modify page.
	 *
	 * @param array $tabs tabs.
	 */
	public function wc_stablediff_options_tabs( $tabs ) {
		$tabs['wpcd_app_stablediff'] = array(
			'label'    => __( 'Stable Diffusion', 'wpcd' ),
			'target'   => 'wpcd_app_stablediff_product_data',
			'class'    => array( 'show_if_subscription', 'show_if_variable' ),
			'priority' => 21,
		);
		return $tabs;

	}

	/**
	 * Add the contents to the Stable Diffusion tab of the product add/modify page.
	 */
	public function wc_stablediff_options() {
		echo '<div id="wpcd_app_stablediff_product_data" class="panel woocommerce_options_panel hidden">';

			woocommerce_wp_checkbox(
				array(
					'id'          => 'wpcd_app_stablediff_product',
					'value'       => get_post_meta( get_the_ID(), 'wpcd_app_stablediff_product', true ),
					'label'       => __( 'This is a Stable Diffusion Server', 'wpcd' ),
					'desc_tip'    => true,
					'description' => __( 'This is a Stable Diffusion Server', 'wpcd' ),
				)
			);

			woocommerce_wp_select(
				array(
					'id'      => 'wpcd_app_stablediff_size',
					'value'   => get_post_meta( get_the_ID(), 'wpcd_app_stablediff_size', true ),
					'label'   => __( 'Subscription size', 'wpcd' ),
					'options' => self::$sizes,
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'                => 'wpcd_app_stablediff_max_users',
					'value'             => get_post_meta( get_the_ID(), 'wpcd_app_stablediff_max_users', true ),
					'label'             => __( 'Number of users allowed', 'wpcd' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'min' => 1,
						'max' => 100,
					),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'                => 'wpcd_app_stablediff_max_clients',
					'value'             => get_post_meta( get_the_ID(), 'wpcd_app_stablediff_max_clients', true ),
					'label'             => __( 'Number of devices allowed', 'wpcd' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'min' => 1,
						'max' => 100,
					),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'                => 'wpcd_app_stablediff_max_bandwidth',
					'value'             => get_post_meta( get_the_ID(), 'wpcd_app_stablediff_max_bandwidth', true ),
					'label'             => __( 'Bandwidth allowed', 'wpcd' ),
					'description'       => __( '(GB)', 'wpcd' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'min' => 1,
						'max' => 100000,
					),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => 'wpcd_app_stablediff_scripts_version',
					'value'       => get_post_meta( get_the_ID(), 'wpcd_app_stablediff_scripts_version', true ),
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
		if ( $this->does_order_contain_item_of_type( $order, 'stablediff' ) ) {
			$str = $this->get_thank_you_text( $str, $order, 'stablediff' );
		}

		return $str;

	}

}
