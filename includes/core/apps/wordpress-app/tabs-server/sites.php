<?php
/**
 * Sites Tab
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_SITES
 */
class WPCD_WORDPRESS_TABS_SITES extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_PHP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_server_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields' ), 10, 2 );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'sites_on_server';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_server_sites_tab';
	}		

	/**
	 * Populates the tab name.
	 *
	 * @param array $tabs The default value.
	 * @param int   $id   The post ID of the server.
	 * 
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs, $id ) {
		if ( true === $this->wpcd_wpapp_server_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_server_tab( $id, $this->get_tab_slug() ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
			'label' => __( 'Sites', 'wpcd' ),
			'icon'  => 'fad fa-browser',
		);
	}
		return $tabs;
	}

	/**
	 * Gets the fields to be shown in the SITES tab.
	 *
	 * Filter hook: wpcd_app_{$this->get_app_name()}_get_tabs
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_tab_fields( array $fields, $id ) {
		return $this->get_fields_for_tab( $fields, $id, $this->get_tab_slug() );

	}

	/**
	 * Gets the actions to be shown in the SITES tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	public function get_actions( $id ) {

		return $this->get_sites_fields( $id );

	}

	/**
	 * Gets the fields for the sites to be shown in the SITES tab in the server details screen.
	 *
	 * @param int $id the post id of the app cpt record.
	 *
	 * @return array Array of actions with key as the action slug and value complying with the structure necessary by metabox.io fields.
	 */
	private function get_sites_fields( $id ) {

		// Set up metabox items.
		$actions = array();

		$actions['sites-header'] = array(
			'label'          => __( 'SITES', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => '',
			),
		);

		$actions['site-title-label'] = array(
			'label'          => __( 'Site Title', 'wpcd' ),
			'raw_attributes' => array(
				'std'     => '',
				'columns' => 3,
			),
			'type'           => 'custom_html',
		);

		$actions['site-status-label'] = array(
			'label'          => __( 'Active status', 'wpcd' ),
			'raw_attributes' => array(
				'std'     => '',
				'columns' => 2,
			),
			'type'           => 'custom_html',
		);

		$actions['site-ssl-label'] = array(
			'label'          => __( 'SSL', 'wpcd' ),
			'raw_attributes' => array(
				'std'     => '',
				'columns' => 1,
			),
			'type'           => 'custom_html',
		);

		$actions['site-wpadmin-label'] = array(
			'label'          => __( 'Link to wp-admin', 'wpcd' ),
			'raw_attributes' => array(
				'std'     => '',
				'columns' => 3,
			),
			'type'           => 'custom_html',
		);

		$actions['site-frontend-label'] = array(
			'label'          => __( 'Link to front-end', 'wpcd' ),
			'raw_attributes' => array(
				'std'     => '',
				'columns' => 3,
			),
			'type'           => 'custom_html',
		);

		$args = array(
			'post_type'      => 'wpcd_app',
			'post_status'    => 'private',
			'posts_per_page' => 9999,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => 'parent_post_id',
					'value' => $id,
				),
			),
		);

		$app_ids = get_posts( $args );

		if ( ! empty( $app_ids ) ) {
			foreach ( $app_ids as $app_id ) {

				if ( wpcd_is_admin() || wpcd_user_can( get_current_user_id(), 'view_app', $app_id ) || get_post_field( 'post_author', $app_id ) == get_current_user_id() ) {

					$site_title = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( admin_url( 'post.php?post=' . $app_id . '&action=edit' ) ), get_the_title( $app_id ) );

				} else {
					$site_title = get_the_title( $app_id );
				}

				$actions[ 'site-title-label_' . $app_id ] = array(
					'label'          => '',
					'raw_attributes' => array(
						'std'     => $site_title,
						'columns' => 3,
					),
					'type'           => 'custom_html',
				);

				$site_status = $this->site_status( $app_id );
				if ( empty( $site_status ) ) {
					$site_status = 'on';
				}

				$site_status = 'on' == $site_status ? __( 'Active', 'wpcd' ) : __( 'Not Active', 'wpcd' );

				$actions[ 'site-status-label_' . $app_id ] = array(
					'label'          => '',
					'raw_attributes' => array(
						'std'     => $site_status,
						'columns' => 2,
					),
					'type'           => 'custom_html',
				);

				$ssl_status = get_post_meta( $app_id, 'wpapp_ssl_status', true );
				if ( empty( $ssl_status ) ) {
					$ssl_status = 'off';
				}

				$actions[ 'site-ssl-label_' . $app_id ] = array(
					'label'          => '',
					'raw_attributes' => array(
						'std'     => $ssl_status,
						'columns' => 1,
					),
					'type'           => 'custom_html',
				);

				$actions[ 'site-wpadmin-label_' . $app_id ] = array(
					'label'          => '',
					'raw_attributes' => array(
						'std'     => $this->get_formatted_wpadmin_link( $app_id ),
						'columns' => 3,
					),
					'type'           => 'custom_html',
				);

				$actions[ 'site-frontend-label_' . $app_id ] = array(
					'label'          => '',
					'raw_attributes' => array(
						'std'     => $this->get_formatted_site_link( $app_id ),
						'columns' => 3,
					),
					'type'           => 'custom_html',
				);

			}
		} else {
			$actions['sites-not-found'] = array(
				'label'          => '',
				'raw_attributes' => array(
					'std' => __( 'No sites found for this server.', 'wpcd' ),
				),
				'type'           => 'custom_html',
			);
		}

		return $actions;

	}

}

new WPCD_WORDPRESS_TABS_SITES();
