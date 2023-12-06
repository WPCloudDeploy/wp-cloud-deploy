<?php
/**
 * Class WPCD_WOOCOMMERCE
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parent class for all woocommerce function classes
 */
class WPCD_WOOCOMMERCE {

	/**
	 * WPCD_WOOCOMMERCE constructor.
	 */
	public function __construct() {

		// Action hook to add the WooCommerce Order ID and Subscription ID into the Reference Column of the Pending Tasks table.
		add_action( 'manage_wpcd_pending_log_posts_custom_column', array( $this, 'pending_tasks_log_table_content' ), 10, 2 );

		// Filter hook to potentially inject some css classes into the rows of the public/front-end views.
		add_filter( 'wpcd_public_table_single_row', array( $this, 'wpcd_public_table_single_row' ), 10, 2 );

		// Remove the resubscribe button from the WC account area.
		add_filter( 'wcs_can_user_resubscribe_to_subscription', array( $this, 'wpcd_remove_wc_resubscribe_button' ), 100 );

	}

	/**
	 * Checks if the order contains an item of the given type.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $order The woocommerce order object array.
	 * @param string $item_type The type of item that should be in the order.
	 *
	 * @return bool
	 */
	protected function does_order_contain_item_of_type( $order, $item_type ) {
		$found = false;
		$items = $order->get_items();
		foreach ( $items as $item ) {
			$product_id = $item->get_product_id();
			$product_id = apply_filters( 'wpcd_does_order_contain_item_of_type_product_id', $product_id );
			$is_type    = get_post_meta( $product_id, "wpcd_app_{$item_type}_product", true );
			if ( 'yes' === $is_type ) {
				$found = true;
				break;
			}
		}

		return apply_filters( 'wpcd_does_order_contain_item_of_type', $found );
	}

	/**
	 * Checks whether all items of a particular type on an order has or has not suppressed thank you notices.
	 * If at least one item of the specified type on the order  has not suppressed the
	 * thank you notice, return false.
	 *
	 * If all items of the specified type on the order has suppressed the thank you notice return true.
	 *
	 * It is expected that the item meta name follows a particular convention:
	 *     wpcd_app_{$item_type}_no_global_thankyou_notice
	 *
	 * @since 5.0.0
	 *
	 * @param array  $order The woocommerce order object array.
	 * @param string $item_type The type of item that should be in the order.
	 *
	 * @return bool
	 */
	protected function does_order_suppress_thank_you_notice( $order, $item_type ) {
		$return = true;
		$items  = $order->get_items();
		foreach ( $items as $item ) {
			$product_id = $item->get_product_id();
			$product_id = apply_filters( 'wpcd_does_order_suppress_thank_you_notice_product_id', $product_id );
			$is_type    = get_post_meta( $product_id, "wpcd_app_{$item_type}_product", true );
			if ( 'yes' === $is_type ) {
				$suppress = get_post_meta( $product_id, "wpcd_app_{$item_type}_no_global_thankyou_notice", true );
				if ( 'yes' !== $suppress ) {
					$return = false;
					break;
				}
			}
		}

		return apply_filters( 'wpcd_does_order_suppress_thank_you_notice', $return );
	}

	/**
	 * Checks if the WC cart contains an item of the given type.
	 *
	 * @since 4.5.0
	 *
	 * @param string $item_type The type of item that should be in the cart.
	 *
	 * @return bool
	 */
	protected function does_cart_contain_item_of_type( $item_type ) {
		$found = false;
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			// Is this a WP Sites Product?  If not get out.
			$is_type = get_post_meta( $cart_item['product_id'], "wpcd_app_{$item_type}_product", true );
			if ( 'yes' === $is_type ) {
				$found = true;
				break;
			}
		}

		return $found;

	}

	/**
	 * Given a WOOCOMMERCE order, return a one dimensional array with just
	 * the unique product ids on it.
	 *
	 * @since 5.0
	 *
	 * @param object $order The order we're extracting product ids from.
	 *
	 * @return array
	 */
	protected function get_unique_products_on_order( $order ) {

		$return = array();

		$items = $order->get_items();
		foreach ( $items as $item ) {
			$product_id = $item->get_product_id();
			$product_id = apply_filters( 'wpcd_get_unique_products_on_order_product_id', $product_id );
			if ( ! in_array( $product_id, $return, true ) ) {
				array_push( $return, $product_id );
			}
		}

		return apply_filters( 'get_unique_products_on_order', $return );

	}

	/**
	 * Checks if a product id is a particular type of product
	 *
	 * @since 4.5.0
	 *
	 * @param int    $product_id The type of item we're checking for.
	 * @param string $item_type item type.
	 *
	 * @return bool
	 */
	public function is_product_type( $product_id, $item_type ) {

		$is_type = get_post_meta( $product_id, "wpcd_app_{$item_type}_product", true );

		if ( 'yes' === $is_type ) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Does the cart contain a renewal order?
	 *
	 * @param object $order  WC Order Object.
	 */
	public function is_cart_renewal( $order = null ) {

		$is_renewal = wcs_cart_contains_renewal();

		if ( $is_renewal && ! is_wp_error( $is_renewal ) ) {
			return true;
		}

		if ( $order ) {
			if ( true === wcs_order_contains_renewal( $order ) ) {
				return true;
			}
		}

		return false;

	}

	/**
	 * Does the cart contain a subscription switch object?
	 *
	 * @return boolean True - cart has at least one subscription switch object in it, false otherwise.
	 */
	public function is_cart_subscription_switch() {

		$has_subscriptions_switch = false;
		if ( ! empty( WC()->cart ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( wcs_get_cart_item_switch_type( $cart_item ) ) {
					$has_subscriptions_switch = true;
				}
			}
		}
		return $has_subscriptions_switch;

	}

	/**
	 * Does the order contain a subscription switch?
	 *
	 * @param object|int $order  WC Order Object or WC order id.
	 */
	public function is_order_subscription_switch( $order ) {

		if ( $order ) {
			if ( true === wcs_order_contains_switch( $order ) ) {
				return true;
			}
		}

		return false;

	}

	/**
	 * Returns the thank you text to show.
	 *
	 * @since 1.0.0
	 *
	 * @param string $str The current text of the thank you page.
	 * @param array  $order The woocommerce order object array.
	 * @param string $item_type The type of item that should be in the order.
	 * @param bool   $global Whether to return the global thank you text from settings or the one(s) stored in products on the order.
	 *
	 * @return string
	 */
	protected function get_thank_you_text( $str, $order, $item_type, $global = true ) {

		if ( true === $global ) {
			return $this->get_global_thank_you_text( $str, $order, $item_type );
		}

		if ( ! $global ) {
			return $this->get_product_thank_you_text( $str, $order, $item_type );
		}

	}

	/**
	 * Returns the thank you text to show.
	 * It pulls the thank-you text from the global settings.
	 *
	 * @since 5.0.0
	 *
	 * @param string $str The current text of the thank you page.
	 * @param array  $order The woocommerce order object array.
	 * @param string $item_type The type of item that should be in the order.
	 *
	 * @return string
	 */
	protected function get_global_thank_you_text( $str, $order, $item_type ) {
		// Get the text to show at the top of the thank you page.
		$addl_text         = wpcd_get_option( "{$item_type}_general_wc_thank_you_text_before" );
		$addl_text_to_show = '';  // Temporary holding variable for text that will be concatenated to the incoming $str variable.

		// If there is text to show, then add it to the temporary holding var.
		if ( ! empty( $addl_text ) ) {
			$addl_text_to_show = $addl_text;
		}

		// Maybe add a link to the vpn account page as well....
		// Only if the token ##VPNACCOUNTPAGE## exists in the $addl_text to show option/variable.
		$acct_option = boolval( wpcd_get_option( "{$item_type}_general_wc_show_acct_link_ty_page" ) );
		$acct_url    = wpcd_get_option( "{$item_type}_general_wc_ty_acct_link_url" );
		$acct_text   = wpcd_get_option( "{$item_type}_general_wc_ty_acct_link_text" );

		if ( ! empty( $acct_text ) && ! empty( $acct_url ) && true == $acct_option ) {
			$type              = str_replace( '_', '', strtoupper( $item_type ) );
			$accnt_link        = '<div class="wpcd-vpn-wc-thank-you-acct-page-link-wrap">' . '<a href=' . '"' . $acct_url . '"' . '>' . $acct_text . '</a>' . '</div>';
			$addl_text_to_show = str_replace( "##{$type}ACCOUNTPAGE##", $accnt_link, $addl_text_to_show );
		}

		// Now create the entire text string to be returned to the action hook.
		if ( ! empty( $addl_text_to_show ) ) {
			$str = $addl_text_to_show . $str;
		}

		// Allow apps to hook in and modify the string further.
		return apply_filters( 'wpcd_global_thank_you_text', $str, $order, $item_type );
	}

	/**
	 * Returns the thank you text to show.
	 * It pulls the thank-you text from the individual products.
	 *
	 * @since 5.0.0
	 *
	 * @param string $str The current text of the thank you page.
	 * @param array  $order The woocommerce order object array.
	 * @param string $product_item_type The type of item that should be in the order.
	 * @param string $settings_item_type A substring that helps construct the option value to pull certain things from settings.
	 *
	 * @return string
	 */
	protected function get_product_thank_you_text( $str, $order, $product_item_type, $settings_item_type ) {

		$products = $this->get_unique_products_on_order( $order );

		foreach ( $products as $product_id ) {
			// Get text from product.
			$addl_text_to_show = get_post_meta( $product_id, "wpcd_app_{$product_item_type}_product_thankyou_notice", true );

			if ( ! empty( $addl_text_to_show ) ) {

				// Maybe add a link to the vpn account page as well....
				// Only if the token ##VPNACCOUNTPAGE## exists in the $addl_text to show option/variable.
				$acct_option = boolval( wpcd_get_option( "{$settings_item_type}_general_wc_show_acct_link_ty_page" ) );
				$acct_url    = wpcd_get_option( "{$settings_item_type}_general_wc_ty_acct_link_url" );
				$acct_text   = wpcd_get_option( "{$settings_item_type}_general_wc_ty_acct_link_text" );

				if ( ! empty( $acct_text ) && ! empty( $acct_url ) && true == $acct_option ) {
					$type              = str_replace( '_', '', strtoupper( $settings_item_type ) );
					$accnt_link        = '<div class="wpcd-vpn-wc-thank-you-acct-page-link-wrap">' . '<a href=' . '"' . $acct_url . '"' . '>' . $acct_text . '</a>' . '</div>';
					$addl_text_to_show = str_replace( "##{$type}ACCOUNTPAGE##", $accnt_link, $addl_text_to_show );
				}

				// Now create the entire text string to be returned to the action hook.
				if ( ! empty( $addl_text_to_show ) ) {
					$str = apply_filters( 'wpcd_product_thank_you_text', $addl_text_to_show, $str, $order, $product_id, $product_item_type, $settings_item_type ) . $str;
				}
			}
		}

		// Allow apps to hook in and modify the string further.
		return apply_filters( 'wpcd_all_product_thank_you_text', $str, $order, $product_item_type, $settings_item_type );
	}

	/**
	 * Return a list of sizes of servers that
	 * can be sold through WC.
	 */
	public function get_wc_size_options() {

		$default_server_sizes = wpcd_get_early_option( 'wpcd_default_server_sizes' );

		if ( empty( $default_server_sizes ) || ( isset( $default_server_sizes[0] ) && empty( $default_server_sizes[0]['size'] ) ) ) {

			$size_options = array(
				'small'  => __( 'Small Server', 'wpcd' ),
				'medium' => __( 'Medium Server', 'wpcd' ),
				'large'  => __( 'Large Server', 'wpcd' ),
			);

		} else {

			foreach ( $default_server_sizes as $size ) {
				$size_options[ $size['size'] ] = $size['size_desc'];
			}
		}

		return $size_options;
	}

	/**
	 * Add the WooCommerce Order ID and Subscription ID into the Reference Column of the Pending Tasks table.
	 *
	 * Action Hook: manage_wpcd_pending_log_posts_custom_column
	 *
	 * @param string $column_name column name.
	 * @param int    $post_id post id.
	 *
	 *    print column value.
	 */
	public function pending_tasks_log_table_content( $column_name, $post_id ) {

		$value = '';

		switch ( $column_name ) {

			case 'wpcd_pending_task_reference':
				$data = WPCD_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $post_id );

				$wc_data_string = '';
				if ( isset( $data['wp_wc_order_id'] ) ) {
					$wc_data_string .= '<br .>' . __( 'WC Order Id: ', 'wpcd' ) . '<a href=' . get_edit_post_link( $data['wp_wc_order_id'] ) . '>' . $data['wp_wc_order_id'] . '</a>';
				}

				if ( isset( $data['wp_wc_subscription_id'] ) ) {
					$wc_data_string .= '<br .>' . __( 'WC Subs Id: ', 'wpcd' ) . '<a href=' . get_edit_post_link( $data['wp_wc_subscription_id'] ) . '>' . $data['wp_wc_subscription_id'] . '</a>';
				}

				if ( ! empty( $wc_data_string ) ) {
					$value .= $wc_data_string;
				}
				break;

			default:
				break;
		}

		echo wp_kses_post( $value );

	}

	/**
	 * Inject css classes into individual rows on the front-end.
	 *
	 * @since 5.0
	 *
	 * @param array   $css_classes Array of existing classes.
	 * @param WP_POST $item Post object representing the post being handled.
	 *
	 * @return array.
	 */
	public function wpcd_public_table_single_row( $css_classes, $item ) {

		$post_id = $item->ID;

		if ( 'wpcd_app' === $item->post_type ) {
			// Check to see if we have a wc product id on the app post.
			if ( ! empty( $post_id ) ) {
				$product_id = get_post_meta( $post_id, 'wpapp_wc_product_id', true );
				if ( ! empty( $product_id ) ) {
					/* Add the product ID to the classes. */
					$css_classes[] = 'wpcd_wc_product_id_' . (string) $product_id;

					/* Add categories to the classes. */
					$product_categories = $this->get_wc_product_categories( $product_id );
					foreach ( $product_categories as $key => $category ) {
						$css_classes[] = 'wpcd_wc_product_cat_slug_' . (string) $category->slug;
						$css_classes[] = 'wpcd_wc_product_cat_id_' . (string) $category->term_id;
					}
				}
			}
		}

		if ( 'wpcd_app_server' === $item->post_type ) {
			// Check to see if we have a wc product id on the server post.
			if ( ! empty( $post_id ) ) {
				$product_id = get_post_meta( $post_id, 'wpcd_server_wc_product_id', true );
				if ( ! empty( $product_id ) ) {
					/* Add the product ID to the classes. */
					$css_classes[] = 'wpcd_wc_product_id_' . (string) $product_id;

					/* Add categories to the classes. */
					$product_categories = $this->get_wc_product_categories( $product_id );
					foreach ( $product_categories as $key => $category ) {
						$css_classes[] = 'wpcd_wc_product_cat_slug_' . (string) $category->slug;
						$css_classes[] = 'wpcd_wc_product_cat_id_' . (string) $category->term_id;
					}
				}
			}
		}

		return $css_classes;

	}

	/**
	 * Get a list of product categories for woocommerce products.
	 *
	 * Only returns the top level categories, not the child categories.
	 *
	 * @since 5.0.
	 *
	 * @return array
	 */
	public function get_all_wc_product_categories() {
		$orderby    = 'name';
		$order      = 'asc';
		$hide_empty = false;
		$cat_args   = array(
			'orderby'    => $orderby,
			'order'      => $order,
			'hide_empty' => $hide_empty,
		);

		$product_categories = get_terms( 'product_cat', $cat_args );

		return $product_categories;
	}

	/**
	 * Get a list of product categories for a single woocommerce product.
	 *
	 * Only returns the top level categories, not the child categories.
	 *
	 * @since 5.0.
	 *
	 * @param int $product_id Product id for the wc product we need to get.
	 *
	 * @return array
	 */
	public function get_wc_product_categories( $product_id ) {

		return get_the_terms( $product_id, 'product_cat' );

	}

	/**
	 * Remove the resubscribe button from the WC account area.
	 *
	 * Note that this affects ALL user subscriptions including non-wpcd subscriptions.
	 * Unfortunately the WC hook does not provide access to the subscription object.
	 * So there's no way to filter the action to only our products.
	 *
	 * Filter Hook: wcs_can_user_resubscribe_to_subscription
	 *
	 * @since 5.0.
	 *
	 * @return boolean
	 */
	public function wpcd_remove_wc_resubscribe_button() {

		return false;

	}
}
