<?php
/**
 * Custom Server.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLOUD_PROVIDER_API_CustomServer
 */
class CLOUD_PROVIDER_API_CustomServer extends CLOUD_PROVIDER_API_CustomServer_Parent {

	/**
	 * VPN_API_Customserver constructor.
	 *
	 * @param array $args args.
	 */
	public function __construct( $args = array() ) {

		parent::__construct();

		/* Set provider name */
		if ( isset( $args['provider_name'] ) && ( ! empty( $args['provider_name'] ) ) ) {
			$this->set_provider( $args['provider_name'] );
		} else {
			$this->set_provider( 'Custom Server' );
		}

		/* Set provider slug*/
		if ( isset( $args['provider_slug'] ) && ( ! empty( $args['provider_slug'] ) ) ) {
			$this->set_provider_slug( $args['provider_slug'] );
		} else {
			$this->set_provider_slug( 'custom-server' );
		}

		/* Set default link to cloud provider's user dashboard */
		if ( isset( $args['provider_dashboard_link'] ) && ( ! empty( $args['provider_dashboard_link'] ) ) ) {
			$this->set_provider_dashboard_link( $args['provider_dashboard_link'] );
		} else {
			$this->set_provider_dashboard_link( 'https://wpclouddeploy.com/documentation/custom-server-provider/' );
		}

		/* Sometimes need to set a region prefix so regions are visibly different to users */
		if ( isset( $args['provider_region_prefix'] ) && ( ! empty( $args['provider_region_prefix'] ) ) ) {
			$this->set_region_prefix( $args['provider_region_prefix'] );
		} else {
			$this->set_region_prefix( '' );
		}

		/* Set filter to add link to the dashboard */
		$provider = $this->get_provider_slug();
		add_filter( "wpcd_cloud_provider_settings_api_key_label_desc_{$provider}", array( $this, 'set_link_to_provider_dashboard' ) );

		/* Set the API key - pulling from settings */
		$this->set_api_key( WPCD()->decrypt( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_apikey' ) ) );
		$this->set_ipv4( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_ipv4' ) );

		/* This provider needs some unique settings */
		add_filter( "wpcd_cloud_provider_settings_{$provider}", array( &$this, 'settings' ), 10, 2 );
		add_filter( "wpcd_cloud_provider_settings_after_api_{$provider}", array( &$this, 'settings_after_api' ), 10, 2 );
		add_filter( "wpcd_cloud_provider_settings_after_part1_{$provider}", array( &$this, 'settings_after_part1' ), 10, 2 );

		/* This provider needs special instructions for the API KEY field because it's not used */
		add_filter( 'wpcd_cloud_provider_settings_api_key_desc_custom-server', array( $this, 'set_desc_for_api_field' ) );

		/* This provider MIGHT not use 'root' as their default admin user */
		if ( ! empty( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_custom_root_user' ) ) ) {
			$this->set_root_user( wpcd_get_early_option( 'vpn_' . $this->get_provider_slug() . '_custom_root_user' ) );
		}

	}

}
