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

		// If setting to false, we need to check the MT site type.
		// If it's mt_template_clone, we have to remove that as well.
		if ( false === $flag ) {
			if ( 'mt_template_clone' === $this->get_mt_site_type( $app_id ) ) {
				$this->set_mt_site_type( $app_id, '' );
			}
		}
	}

	/**
	 * Returns the wpcd product name set for a template site.
	 *
	 * @param int $id The post id of the site we're working with.
	 *
	 * @return string
	 */
	public function get_product_name( $id ) {
		return get_post_meta( $id, 'wpcd_app_mt_template_product_name', true );
	}

	/**
	 * Sets the wpcd product name for a template site.
	 *
	 * @param int    $id The post id of the site we're working with.
	 * @param string $name The new product name to set for the site.
	 */
	public function set_product_name( $id, $name ) {
		update_post_meta( $id, 'wpcd_app_mt_template_product_name', $name );
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


	/**
	 * Return the mt version history meta value.
	 *
	 * Note: The functions to add and update the verions array
	 * for a site is still in the multi-tenant tab file since
	 * they are not used anywhere else - yet.
	 *
	 * @param int $id Post id of site we're working with.
	 *
	 * @return array.
	 */
	public function get_mt_version_history( $id ) {

		// Get current tag list.
		$versions = wpcd_maybe_unserialize( get_post_meta( $id, 'wpcd_app_mt_version_history', true ) );

		// Make sure we have something in the logs array otherwise create a blank one.
		if ( empty( $versions ) ) {
			$versions = array();
		}
		if ( ! is_array( $versions ) ) {
			$versions = array();
		}

		return $versions;

	}

	/**
	 * Return a list of posts that are version sites with a
	 * particular version.
	 *
	 * There should only be one site but we'll return everything
	 * we get.
	 *
	 * @param string $mt_version The MT version we're inquiring about.
	 *
	 * @return array|object
	 */
	public function get_mt_version_sites_by_version( $mt_version ) {

		$query_args = array(
			'post_type'   => 'wpcd_app',
			'post_status' => 'private',
			'numberposts' => -1,
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'   => 'wpcd_app_mt_site_type',
					'value' => 'mt_version',
				),
				array(
					'key'   => 'wpcd_app_mt_version',
					'value' => $mt_version,
				),
			),
		);

		return get_posts( $query_args );

	}

	/**
	 * Return a list of posts that are version clone sites with a
	 * particular version and located on a specified server.
	 *
	 * There should only be one site but we'll return everything
	 * we get.
	 *
	 * @param string $mt_version The MT version we're inquiring about.
	 * @param int    $server_id The server ID that the version_clone should reside on.
	 *
	 * @return array|object
	 */
	public function get_mt_version_clone_sites_by_version_and_server_id( $mt_version, $server_id ) {

		$query_args = array(
			'post_type'   => 'wpcd_app',
			'post_status' => 'private',
			'numberposts' => -1,
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'   => 'wpcd_app_mt_site_type',
					'value' => 'mt_version_clone',
				),
				array(
					'key'   => 'wpcd_app_mt_version',
					'value' => $mt_version,
				),
				array(
					'key'   => 'parent_post_id',
					'value' => $server_id,
				),
			),
		);

		return get_posts( $query_args );

	}

	/**
	 * Clone MT related metas.
	 *
	 * This is called during cloning, staging and site-sync operations.
	 *
	 * @param int $id Source Site Post Id.
	 * @param int $target_id Cloned site post id.
	 */
	public function clone_mt_metas( $id, $target_id ) {

		// Template flag.
		$is_template = $this->wpcd_is_template_site( $id );
		$this->wpcd_set_template_flag( $target_id, $is_template );

		if ( true === wpcd_is_mt_enabled() ) {
			// MT Version.
			$mt_version = $this->get_mt_version( $id );
			if ( ! empty( $mt_version ) ) {
				$this->set_mt_version( $target_id, $mt_version );
			}

			// MT Site type.
			$mt_site_type = $this->get_mt_site_type( $id );
			if ( 'mt_tenant' === $mt_site_type ) {
				$this->set_mt_site_type( $target_id, $mt_site_type );
			}

			if ( 'mt_version' === $mt_site_type ) {
				$this->set_mt_site_type( $target_id, 'mt_version_clone' );
			}

			if ( 'template' === $mt_site_type ) {
				$this->set_mt_site_type( $target_id, 'mt_template_clone' );
			}

			// MT Parent id.
			$mt_parent_id = $this->get_mt_parent( $id );
			if ( ! empty( $mt_parent_id ) ) {
				$this->set_mt_parent( $target_id, $mt_parent_id );
			} else {
				// The site being cloned doesn't have a parent id.
				// So maybe our new site's parent is the original site.
				// This applies if the original site is a template site or a version site.
				// If the original site is a mt_template_clone or mt_version_clone site then
				// we should already have a parent id and this section would not be invoked.
				if ( in_array( $mt_site_type, array( 'mt_version', 'template' ), true ) ) {
					$this->set_mt_parent( $target_id, $id );
				}
			}
		}
	}

}
