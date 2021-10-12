<?php
/**
 * Digital Ocean Alternate.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLOUD_PROVIDER_API_DigitalOceanAlternate
 */
class CLOUD_PROVIDER_API_DigitalOceanAlternate extends CLOUD_PROVIDER_API_DigitalOcean_Parent {

	/**
	 * VPN_API_DigitalOcean constructor.
	 */
	public function __construct() {

		parent::__construct();

		/* Set provider name and slug */
		$this->set_provider( 'Digital Ocean Alternate' );
		$this->set_provider_slug( 'digital-ocean-alternate' );

		/* Set link to cloud provider's user dashboard */
		$this->set_provider_dashboard_link( 'https://cloud.digitalocean.com/account/api/tokens' );

		/* Need a region prefix so regions are visibly different to users (since this is a clone of the DO provider)  */
		$this->set_region_prefix( 'DO-ALT' );

		/* Set the API key - pulling from settings */
		$this->set_api_key( WPCD()->decrypt( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_apikey' ) ) );

		/* Set filter to add link to the digital ocean api dashboard */
		$provider = $this->get_provider_slug();
		add_filter( "wpcd_cloud_provider_settings_api_key_label_desc_{$provider}", array( $this, 'set_link_to_provider_dashboard' ) );

	}


}
