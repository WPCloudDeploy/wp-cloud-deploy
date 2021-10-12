<?php
namespace MBSP\Network;

use MBSP\Storage as BaseStorage;

/**
 * Storage for multisite install.
 * Save all settings in a single option. $object_id is the option name.
 */
class Storage extends BaseStorage {
	protected function get_option( $name ) {
		return (array) get_site_option( $name, array() );
	}

	protected function update_option( $name, $value ) {
		return update_site_option( $name, $value );
	}
}