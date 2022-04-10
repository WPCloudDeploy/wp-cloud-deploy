<?php
class MB_Conditional_Logic {
	private $has_conditions = false;
	private $outside_conditions = null;

	public function __construct() {
		add_action( 'rwmb_before', [ $this, 'insert_meta_box_conditions' ] );
		add_action( 'rwmb_before', [ $this, 'insert_toggle_type' ] );
		add_filter( 'rwmb_wrapper_html', [ $this, 'insert_field_conditions' ], 10, 2 );
		add_action( 'rwmb_after', [ $this, 'enqueue_in_footer' ] );

		add_action( 'rwmb_enqueue_scripts', [ $this, 'enqueue_in_customizer_gutenberg' ] );
	}

	public function insert_meta_box_conditions( $obj ) {
		echo $this->get_conditions_html( $obj->meta_box );
	}

	public function insert_toggle_type( $obj ) {
		if ( $obj->toggle_type ) {
			echo '<template class="mbc-toggle-type" data-toggle_type="' . esc_attr( $obj->toggle_type ) . '"></template>';
		}
	}

	public function insert_field_conditions( $begin, $field ) {
		return $begin . $this->get_conditions_html( $field );
	}

	/**
	 * Get outputted HTML for conditions.
	 *
	 * @param array $settings Meta box/field settings.
	 */
	private function get_conditions_html( $settings ) {
		if ( empty( $settings['visible'] ) && empty( $settings['hidden'] ) ) {
			return '';
		}

		$this->has_conditions = true;

		$conditions = $this->parse_conditions( $settings );
		return '<template class="mbc-conditions" data-conditions="' . esc_attr( wp_json_encode( $conditions ) ) . '"></template>';
	}

	public function enqueue_in_footer() {
		// Bypass if no meta box/field/outside conditions.
		if ( ! $this->has_conditions && ! $this->get_outside_conditions() ) {
			return;
		}

		$this->enqueue();

		// Reset the check for the next meta box.
		$this->has_conditions = false;
	}

	public function enqueue_in_customizer_gutenberg() {
		// In Customizer (for Settings Page extension), meta boxes are loaded via JavaScript.
		// We can't enqueue with "rwmb_after", and must use "rwmb_enqueue_scripts".
		if ( is_customize_preview() ) {
			$this->enqueue();
		}

		// Always enqueue for Gutenberg, to make it work inside dynamic blocks (created with MB Blocks).
		if ( ! is_admin() ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			$this->enqueue();
		}
	}

	public function enqueue() {
		list( , $url ) = RWMB_Loader::get_path( __DIR__ );
		wp_enqueue_script( 'mb-conditional-logic', $url . 'conditional-logic.js', ['underscore', 'rwmb'], filemtime( __DIR__ . '/conditional-logic.js' ), true );
		wp_localize_script( 'mb-conditional-logic', 'conditions', $this->get_outside_conditions() );
	}

	private function get_outside_conditions() {
		if ( null !== $this->outside_conditions ) {
			return $this->outside_conditions;
		}

		$this->outside_conditions = apply_filters( 'rwmb_outside_conditions', [] );
		$this->outside_conditions = array_map( [ $this, 'parse_conditions' ], $this->outside_conditions );

		return $this->outside_conditions;
	}

	private function parse_conditions( $conditions ) {
		$output = [];
		if ( ! empty( $conditions['visible'] ) ) {
			$output['visible'] = $this->parse_condition( $conditions['visible'] );
		}
		if ( ! empty( $conditions['hidden'] ) ) {
			$output['hidden'] = $this->parse_condition( $conditions['hidden'] );
		}
		return $output;
	}

	private function parse_condition( $condition ) {
		if ( ! is_array( $condition ) ) {
			return array(
				'when'     => [],
				'relation' => 'and',
			);
		}

		$relation = isset( $condition['relation'] ) && in_array( $condition['relation'], ['and', 'or'] ) ? $condition['relation'] : 'and';

		$condition_to_normalize = $condition;
		if ( isset( $condition['when'] ) && is_array( $condition['when'] ) ) {
			$condition_to_normalize = $condition['when'];
		}

		$when = $this->get_normalized_criteria( $condition_to_normalize );

		return compact( 'when', 'relation' );
	}

	private function get_normalized_criteria( $condition ) {
		$normalized = [];

		foreach ( $condition as $criteria ) {
			if ( is_array( $criteria ) ) {
				$normalized[] = $this->normalize_criteria( $criteria );
			} else {
				$normalized[] = $this->normalize_criteria( $condition );
				break;
			}
		}

		return $normalized;
	}

	private function normalize_criteria( $criteria ) {
		$criteria_length = count( $criteria );

		if ( 2 === $criteria_length ) {
			$criteria = array( $criteria[0], '=', $criteria[1] );
		}

		// Convert slug to id if conditional logic defined using slug for terms.
		if ( strrpos( $criteria[0], 'slug:', - strlen( $criteria[0] ) ) !== false ) {
			$criteria[0] = ltrim( $criteria[0], 'slug:' );

			$criteria[2] = $this->slug_to_id( $criteria[2] );
		}

		return $criteria;
	}

	private function slug_to_id( $slugs ) {
		global $wpdb;

		$slugs    = (array) $slugs;
		$sql      = "SELECT term_id FROM {$wpdb->terms} WHERE slug IN(" . implode( ', ', array_fill( 0, count( $slugs ), '%s' ) ) . ')';
		$prepared = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $slugs ) );

		return array_map( 'intval', $wpdb->get_col( $prepared ) );
	}
}
