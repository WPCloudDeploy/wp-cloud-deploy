<?php
/**
 * Prevent developers from adding WordPress-registered user fields in the admin screen.
 * This class only hides fields from profile screen, not disabling saving process (which can be use elsewhere).
 */

namespace MBUM;

class DuplicatedFields {
	private $fields = [
		'rich_editing',
		'syntax_highlighting',
		'admin_color',
		'comment_shortcuts',
		'admin_bar_front',
		'first_name',
		'last_name',
		'nickname',
		'description',
		'locale',
	];

	public function __construct() {
		add_filter( 'rwmb_outer_html', [$this, 'remove_field'], 10, 2 );
	}

	public function remove_field( $html, $field ) {
		if ( ! is_admin() ) {
			return $html;
		}
		$screen = get_current_screen();
		if ( ! is_object( $screen ) || ! in_array( $screen->id, ['profile', 'user-edit', 'profile-network', 'user-edit-network'], true ) ) {
			return $html;
		}
		return in_array( $field['id'], $this->fields, true ) ? '' : $html;
	}
}