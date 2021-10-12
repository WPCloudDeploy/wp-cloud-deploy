<?php
namespace MBSP\Network;

use MBSP\Factory;
use MBSP\MetaBox as BaseMetaBox;

class MetaBox extends BaseMetaBox {
	protected function setup( $args ) {
		$this->pages       = Factory::get( $args['settings_pages'], 'network' );
		$this->object_type = 'network_setting';
	}

	public function get_storage() {
		return (new Storage);
	}

	public function is_edit_screen( $screen = null ) {
		if ( ! ( $screen instanceof \WP_Screen ) ) {
			$screen = get_current_screen();
		}

		$page_hooks = wp_list_pluck( $this->pages, 'page_hook' );
		$page_hooks = array_map( function( $page_hook ) {
			return "$page_hook-network";
		}, $page_hooks );

		return in_array( $screen->id, $page_hooks, true );
	}
}
