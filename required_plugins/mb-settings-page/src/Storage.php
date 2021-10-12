<?php
namespace MBSP;

/**
 * Save all settings in a single option. $object_id is the option name.
 */
class Storage {
	public function get( $object_id, $name, $args = array() ) {
		$single = is_array( $args ) ? ! empty( $args['single'] ) : (bool) $args;
		$option = $this->get_option( $object_id );

		return isset( $option[ $name ] ) ? $option[ $name ] : ( $single ? '' : array() );
	}

	public function add( $object_id, $meta_key, $meta_value, $unique = false ) {
		if ( $unique ) {
			return $this->update( $object_id, $meta_key, $meta_value );
		}

		$setting = (array) $this->get( $object_id, $meta_key, array(
			'std' => array(),
		) );

		$meta_value = wp_unslash( $meta_value );
		$setting[]  = $meta_value;

		return $this->update( $object_id, $meta_key, $setting );
	}

	public function update( $object_id, $meta_key, $meta_value, $prev_value = '' ) {
		$option              = $this->get_option( $object_id );
		$option[ $meta_key ] = wp_unslash( $meta_value );

		return $this->update_option( $object_id, $option );
	}

	public function delete( $object_id, $meta_key, $meta_value = '', $delete_all = false ) {
		$option = $this->get_option( $object_id );
		if ( ! isset( $option[ $meta_key ] ) ) {
			return true;
		}

		if ( $delete_all || ! $meta_value || $option[ $meta_key ] === $meta_value ) {
			unset( $option[ $meta_key ] );
			return $this->update_option( $object_id, $option );
		}

		if ( ! is_array( $option[ $meta_key ] ) ) {
			return true;
		}

		// For field with multiple values.
		foreach ( $option[ $meta_key ] as $key => $value ) {
			if ( $value === $meta_value ) {
				unset( $option[ $meta_key ][ $key ] );
			}
		}
		return $this->update_option( $object_id, $option );
	}

	protected function get_option( $name ) {
		return (array) get_option( $name, array() );
	}

	protected function update_option( $name, $value ) {
		return update_option( $name, $value );
	}
}