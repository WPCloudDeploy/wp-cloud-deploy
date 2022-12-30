<?php
/**
 * Trait:
 * Contains support code for multi-tenant functions.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_wpapp_multi_tenant_app
 */
trait wpcd_wpapp_multi_tenant_app {

	/**
	 * Return whether a site is a template site or not.
	 *
	 * @since 5.3
	 *
	 * @param int $app_id The post id of the site we're working with.
	 *
	 * @return boolean
	 */
	public function wpcd_is_template_site( $app_id ) {
		$is_template_site = (bool) get_post_meta( $app_id, 'wpcd_is_template_site', true );
		return $is_template_site;
	}

	/**
	 * Sets the template flag for a site.
	 *
	 * @since 5.3
	 *
	 * @param int     $app_id The post id of the site we're working with.
	 * @param boolean $flag Whether to set it on or off.
	 */
	public function wpcd_set_template_flag( $app_id, $flag ) {
		if ( true === $flag ) {
			update_post_meta( $app_id, 'wpcd_is_template_site', true );
		} else {
			update_post_meta( $app_id, 'wpcd_is_template_site', false );
		}
	}

}
