<?php
/**
 * Digital Ocean.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLOUD_PROVIDER_API_DigitalOcean
 */
class CLOUD_PROVIDER_API_DigitalOcean extends CLOUD_PROVIDER_API_DigitalOcean_Parent {

	/**
	 * VPN_API_DigitalOcean constructor.
	 *
	 * @param array $args args.
	 */
	public function __construct( $args = array() ) {

		parent::__construct();

		/* Set provider name */
		if ( isset( $args['provider_name'] ) && ( ! empty( $args['provider_name'] ) ) ) {
			$this->set_provider( $args['provider_name'] );
		} else {
			$this->set_provider( 'Digital Ocean' );
		}

		/* Set provider slug*/
		if ( isset( $args['provider_slug'] ) && ( ! empty( $args['provider_slug'] ) ) ) {
			$this->set_provider_slug( $args['provider_slug'] );
		} else {
			$this->set_provider_slug( 'digital-ocean' );
		}

		/* Set default link to cloud provider's user dashboard */
		if ( isset( $args['provider_dashboard_link'] ) && ( ! empty( $args['provider_dashboard_link'] ) ) ) {
			$this->set_provider_dashboard_link( $args['provider_dashboard_link'] );
		} else {
			$this->set_provider_dashboard_link( 'https://cloud.digitalocean.com/account/api/tokens' );
		}

		/* Sometimes need to set a region prefix so regions are visibly different to users */
		if ( isset( $args['provider_region_prefix'] ) && ( ! empty( $args['provider_region_prefix'] ) ) ) {
			$this->set_region_prefix( $args['provider_region_prefix'] );
		} else {
			$this->set_region_prefix( '' );
		}

		/* Set the API key - pulling from settings */
		$this->set_api_key( WPCD()->decrypt( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_apikey' ) ) );

		/* Set filter to add link to the digital ocean api dashboard */
		$provider = $this->get_provider_slug();
		add_filter( "wpcd_cloud_provider_settings_api_key_label_desc_{$provider}", array( $this, 'set_link_to_provider_dashboard' ) );

	}

}
