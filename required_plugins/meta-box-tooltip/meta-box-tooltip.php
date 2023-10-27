<?php
/**
 * Plugin Name: Meta Box Tooltip
 * Plugin URI:  https://metabox.io/plugins/meta-box-tooltip/
 * Description: Add tooltip for meta fields
 * Version:     1.1.7
 * Author:      MetaBox.io
 * Author URI:  https://metabox.io
 * License:     GPL2+
 */

// Prevent loading this file directly.
defined( 'ABSPATH' ) || die;

if ( ! class_exists( 'MB_Tooltip' ) ) {

	class MB_Tooltip {
		public function __construct() {
			add_action( 'rwmb_enqueue_scripts', [ $this, 'enqueue' ] );
			add_filter( 'rwmb_begin_html', [ $this, 'add_label_tooltip' ], 10, 2 );
			add_filter( 'rwmb_outer_html', [ $this, 'add_input_tooltip' ], 10, 2 );
		}

		public function enqueue() {
			list( , $url ) = RWMB_Loader::get_path( dirname( __FILE__ ) );
			wp_enqueue_style( 'mb-tooltip', $url . 'css/tooltip.css', '', '1.1.7' );

			wp_register_script( 'popper', $url . 'js/popper.js', [], '2.11.6', true );
			wp_register_script( 'tippy', $url . 'js/tippy.js', [ 'popper' ], '6.3.7', true );

			wp_enqueue_script( 'mb-tooltip', $url . 'js/tooltip.js', [ 'jquery', 'tippy' ], '1.1.7', true );
		}

		public function add_label_tooltip( string $html, array $field ) : string {
			if ( empty( $field['tooltip'] ) ) {
				return $html;
			}

			$tooltip      = $this->get_tooltip_data( $field['tooltip'] );
			$tooltip_html = $this->get_tooltip_html( $tooltip );

			$html = str_replace( '</label>', $tooltip_html . '</label>', $html );

			return $html;
		}

		public function add_input_tooltip( string $html, array $field ) : string {
			if ( empty( $field['tooltip_input'] ) ) {
				return $html;
			}

			$tooltip      = $this->get_tooltip_data( $field['tooltip_input'] );
			$tooltip_html = $this->get_tooltip_html( $tooltip );

			$input_fields = [
				'date',
				'datetime',
				'email',
				'number',
				'password',
				'text',
				'time',
				'url',
			];

			if ( in_array( $field['type'], $input_fields, true ) ) {
				$find    = '/<input.(.*).>/U';
				$replace = '$0' . $tooltip_html;
				$html    = preg_replace( $find, $replace, $html );
			}

			$select_fields = [ 'select', 'select_advanced' ];

			if ( in_array( $field['type'], $select_fields, true ) ) {
				$find    = '/<select.(.*).>.(.*).<\/select>/U';
				$replace = '$0' . $tooltip_html;
				$html    = preg_replace( $find, $replace, $html );
			}

			return $html;
		}

		/**
		 * Get tooltip data.
		 *
		 * @param  string|array $tooltip Field tooltip.
		 * @return array
		 */
		private function get_tooltip_data( $tooltip ) : array {
			// Add tooltip to field label, in one of following formats
			// 1) 'tooltip' => 'Tooltip Content'
			// 2) 'tooltip' => array( 'icon' => 'info', 'content' => 'Tooltip Content', 'position' => 'top' )
			// 3) 'tooltip' => array( 'icon' => 'http://url-to-icon-image.png', 'content' => 'Tooltip Content', 'position' => 'top' )
			//
			// In 1st format, icon will be 'info' by default
			// In 2nd format, icon can be 'info' (default), 'help'
			// In 3rd format, icon can be URL to custom icon image
			//
			// 'position' is optional. Value can be 'top' (default), 'bottom', 'left', 'right'.
			$data = [
				'content'    => 'tooltip',
				'icon'       => 'info',
				'position'   => 'top',
				'allow_html' => true,
			];

			if ( is_string( $tooltip ) ) {
				$data['content'] = $tooltip;
				return $data;
			}

			$data = array_merge( $data, array_filter( $tooltip ) );

			return $data;
		}

		/**
		 * Get tooltip html from tooltip data.
		 */
		private function get_tooltip_html( array $data ) : string {
			// If icon is an URL to custom image.
			if ( filter_var( $data['icon'], FILTER_VALIDATE_URL ) ) {
				$icon_html = '<img src="' . esc_url( $data['icon'] ) . '">';
			} else {
				$icons     = array(
					'info' => 'dashicons dashicons-info',
					'help' => 'dashicons dashicons-editor-help',
				);
				$class     = isset( $icons[ $data['icon'] ] ) ? $icons[ $data['icon'] ] : 'dashicons ' . $data['icon'];
				$icon_html = '<span class="' . esc_attr( $class ) . '"></span>';
			}
			$tooltip_html = sprintf(
				'<span class="mb-tooltip" data-tippy-allowHTML="%s" data-tippy-placement="%s" data-tippy-content="%s">%s</span>',
				$data['allow_html'] ? 'true' : 'false',
				esc_attr( $data['position'] ),
				esc_attr( $data['content'] ),
				$icon_html
			);

			return $tooltip_html;
		}
	}

	new MB_Tooltip;
}
