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
			$is_type    = get_post_meta( $product_id, "wpcd_app_{$item_type}_product", true );
			if ( 'yes' === $is_type ) {
				$found = true;
				break;
			}
		}

		return $found;
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
			if ( 'yes' == $is_type ) {
				$found = true;
				break;
			}
		}

		return $found;

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
	protected function is_product_type( $product_id, $item_type ) {

		$is_type = get_post_meta( $product_id, "wpcd_app_{$item_type}_product", true );

		if ( 'yes' == $is_type ) {
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

		$return = false;
		if ( ! is_null( WC()->cart ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( ! empty( $cart_item['subscription_switch'] ) ) {
					$return = true;
				}
			}
		}

		return $return;

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
	 *
	 * @return string
	 */
	protected function get_thank_you_text( $str, $order, $item_type ) {
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
		return apply_filters( 'wpcd_thank_you_text', $str, $order, $item_type );
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

		echo $value;

	}
}
