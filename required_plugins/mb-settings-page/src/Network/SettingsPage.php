<?php
namespace MBSP\Network;

use MBSP\SettingsPage as BaseSettingsPage;

class SettingsPage extends BaseSettingsPage {
	protected $type = 'network';

	protected function register_hooks() {
		add_action( 'network_admin_menu', array( $this, 'register_admin_menu' ) );
	}

	public function add_admin_notice_hook() {
		add_action( 'network_admin_notices', 'settings_errors' );
	}
}
