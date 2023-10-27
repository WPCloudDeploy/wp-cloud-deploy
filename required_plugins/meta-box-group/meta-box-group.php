<?php
/**
 * Plugin Name: Meta Box Group
 * Plugin URI:  https://metabox.io/plugins/meta-box-group/
 * Description: Add-on for meta box plugin, allows you to add field type 'group' which put child fields into 1 group which are displayed/accessed easier and can be cloneable.
 * Version:     1.3.18
 * Author:      MetaBox.io
 * Author URI:  https://metabox.io
 * License:     GPL2+
 */

// Prevent loading this file directly.
defined( 'ABSPATH' ) || die;

if ( ! class_exists( 'RWMB_Group' ) ) {
	class RWMB_Group {
		/**
		 * Indicate that the meta box is saved or not.
		 * This variable is used inside group field to show child fields.
		 *
		 * @var bool
		 */
		public static $saved = false;

		public function __construct() {
			// Hook to 'init' with priority 5 to make sure all actions are registered before Meta Box 4.9.0 runs.
			add_action( 'init', [ $this, 'load_files' ], 5 );

			add_action( 'rwmb_before', [ $this, 'set_saved' ] );
			add_action( 'rwmb_after', [ $this, 'unset_saved' ] );
		}

		public function load_files() {
			if ( class_exists( 'RWMB_Field' ) && ! class_exists( 'RWMB_Group_Field' ) ) {
				require_once __DIR__ . '/group-field.php';
			}
		}

		/**
		 * Check if current meta box is saved.
		 * This variable is used inside group field to show child fields.
		 *
		 * @param object $obj Meta Box object.
		 */
		public function set_saved( $obj ) {
			self::$saved = $obj->is_saved();
		}

		/**
		 * Unset 'saved' variable, to be ready for next meta box.
		 */
		public function unset_saved() {
			self::$saved = false;
		}
	}

	new RWMB_Group;
}
