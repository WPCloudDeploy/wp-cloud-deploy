<?php
/**
 * Trait:
 * Contains support code for WooCommerce functionality that cannot go directly in the class-wordpress-woocomerce.php file.
 * Used only by the class-wordpress.php file which defines the
 * WPCD_WORDPRESS_APP class.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_wpapp_woocommerce_support
 */
trait wpcd_wpapp_woocommerce_support {

	/**
	 * Do not allow WooCommerce to forcibly redirect to their account page
	 *
	 * Filter Hook: woocommerce_prevent_admin_access
	 *
	 * @param string $prevent_access prevent_access.
	 */
	public function wc_subscriber_admin_access( $prevent_access ) {
		if ( true == boolval( wpcd_get_early_option( 'wordpress_app_wc_prevent_redirect' ) ) ) {
			return false;
		} else {
			return $prevent_access;
		}
	}

}
