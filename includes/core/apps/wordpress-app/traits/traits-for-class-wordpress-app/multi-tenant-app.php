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

	/**
	 * Gets the default version (production version) for a template site.
	 *
	 * @since 5.3
	 *
	 * @param int $app_id The post id of the site we're working with.
	 *
	 * @return string
	 */
	public function get_mt_default_version( $app_id ) {

		return get_post_meta( $app_id, 'wpcd_app_mt_default_version', true );

	}

	/**
	 * Sets the default version (production version) for a template site.
	 *
	 * @since 5.3
	 *
	 * @param int    $app_id The post id of the site we're working with.
	 * @param string $version The version to set as the default.
	 *
	 * @return string
	 */
	public function set_mt_default_version( $app_id, $version ) {

		return update_post_meta( $app_id, 'wpcd_app_mt_default_version', $version );

	}

	/**
	 * Gets the mt version stamped on a site.
	 *
	 * The site can be a 'mt version' site or a tenant site.
	 *
	 * @since 5.3
	 *
	 * @param int $app_id The post id of the site we're working with.
	 *
	 * @return string
	 */
	public function get_mt_version( $app_id ) {

		return get_post_meta( $app_id, 'wpcd_app_mt_version', true );

	}

	/**
	 * Sets the version on a site.
	 *
	 * The site can be a 'mt version' site or a tenant site.
	 *
	 * @since 5.3
	 *
	 * @param int    $app_id The post id of the site we're working with.
	 * @param string $version The version to set as the default.
	 *
	 * @return string
	 */
	public function set_mt_version( $app_id, $version ) {

		return update_post_meta( $app_id, 'wpcd_app_mt_version', $version );

	}

	/**
	 * Gets the parent of a mt site
	 *
	 * The site can be a 'mt version' site or a tenant site or a 'mt version clone' site.
	 *
	 * @since 5.3
	 *
	 * @param int $app_id The post id of the site we're working with.
	 *
	 * @return string
	 */
	public function get_mt_parent( $app_id ) {

		return get_post_meta( $app_id, 'wpcd_app_mt_parent', true );

	}

	/**
	 * Sets the parent of a site that is related to a template/product.
	 *
	 * The site can be a 'mt version' site or a tenant site or a 'mt version clone' site.
	 *
	 * @since 5.3
	 *
	 * @param int $app_id The post id of the site we're working with.
	 * @param int $parent_id The post id of the parent site (should be a template site).
	 *
	 * @return string
	 */
	public function set_mt_parent( $app_id, $parent_id ) {

		return update_post_meta( $app_id, 'wpcd_app_mt_parent', $parent_id );

	}

	/**
	 * Sets the site type for an mt related site.  Can be one of the following:
	 *
	 *  'mt_version'
	 *  'mt_version_clone'
	 *  'mt_tenant'
	 *  'mt_template_clone'
	 *
	 * @since 5.3
	 *
	 * @param int    $app_id The post id of the site we're working with.
	 * @param string $site_type The type of site (see above for possible options).
	 *
	 * @return string
	 */
	public function set_mt_site_type( $app_id, $site_type ) {

		return update_post_meta( $app_id, 'wpcd_app_mt_site_type', $site_type );

	}

	/**
	 * Get the site type.
	 *
	 * We'll return one of the following values:
	 *  'standard'
	 *  'template'
	 *  'mt_version'
	 *  'mt_version_clone'
	 *  'mt_tenant'
	 *  'mt_template_clone'
	 *
	 * @param int $app_id The post id of the site we're working with.
	 *
	 * @return string
	 */
	public function get_mt_site_type( $app_id ) {

		$return = 'standard';  // Default is a standard site.

		// Check if it's a template site.
		if ( $this->wpcd_is_template_site( $app_id ) ) {
			$return = 'template';
		}

		// Check if there's a meta on the site with a special mt related value.
		// This check should return nothing or one of the following four values:
		// 'mt_version'.
		// 'mt_version_clone'.
		// 'mt_tenant'.
		// 'mt_template_clone'.
		$mt_site_type = get_post_meta( $app_id, 'wpcd_app_mt_site_type', true );
		if ( ! empty( $mt_site_type ) ) {
			$return = $mt_site_type;
		}

		return $return;

	}

}
