<?php
namespace MetaBox\CustomTable;

class Storage {
	public $table;

	/**
	 * Retrieve metadata for the specified object.
	 *
	 * @param int        $object_id ID of the object metadata is for. In this case, it will be a row's id
	 *                              of table.
	 * @param string     $meta_key  Optional. Metadata key. If not specified, retrieve all metadata for
	 *                              the specified object. In this case, it will be column name.
	 * @param bool|array $args      Optional, default is false.
	 *                              If true, return only the first value of the specified meta_key.
	 *                              If is array, use the `single` element.
	 *                              This parameter has no effect if meta_key is not specified.
	 *
	 * @return mixed Single metadata value, or array of values.
	 */
	public function get( $object_id, $meta_key, $args = false ) {
		if ( is_array( $args ) ) {
			$single = ! empty( $args['single'] );
		} else {
			$single = (bool) $args;
		}
		$default = $single ? '' : array();

		$row = Cache::get( $object_id, $this->table );

		return ! isset( $row[ $meta_key ] ) ? $default : maybe_unserialize( $row[ $meta_key ] );
	}

	/**
	 * Add metadata to cache
	 *
	 * @param int    $object_id  ID of the object metadata is for.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
	 * @param bool   $unique     Optional, default is false.
	 *                           Whether the specified metadata key should be unique for the object.
	 *                           If true, and the object already has a value for the specified metadata key,
	 *                           no change will be made.
	 *
	 * @return bool
	 */
	public function add( $object_id, $meta_key, $meta_value, $unique = false ) {
		if ( $unique ) {
			return $this->update( $object_id, $meta_key, $meta_value );
		}

		$meta_value = wp_unslash( $meta_value );

		$row              = Cache::get( $object_id, $this->table );
		$values           = isset( $row[ $meta_key ] ) ? maybe_unserialize( $row[ $meta_key ] ) : array();
		$values[]         = $meta_value;
		$row[ $meta_key ] = $values;

		Cache::set( $object_id, $this->table, $row );

		return true;
	}

	/**
	 * Update metadata to cache.
	 *
	 * @param int    $object_id  ID of the object metadata is for.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
	 * @param mixed  $prev_value Optional. If specified, only update existing metadata entries with
	 *                           the specified value. Otherwise, update all entries.
	 *
	 * @return bool
	 */
	public function update( $object_id, $meta_key, $meta_value, $prev_value = '' ) {
		if ( empty( $meta_key ) ) {
			return false;
		}
		$meta_value = wp_unslash( $meta_value );
		if ( '' === $meta_value || array() === $meta_value ) {
			$meta_value = null;
		}
		$row              = Cache::get( $object_id, $this->table );
		$row[ $meta_key ] = $meta_value;
		Cache::set( $object_id, $this->table, $row );

		return true;
	}

	/**
	 * Delete metadata.
	 *
	 * @param int    $object_id  ID of the object metadata is for.
	 * @param string $meta_key   Metadata key. If empty, delete row.
	 * @param mixed  $meta_value Optional. Metadata value. Must be serializable if non-scalar. If specified, only delete
	 *                           metadata entries with this value. Otherwise, delete all entries with the specified meta_key.
	 *                           Pass `null, `false`, or an empty string to skip this check. (For backward compatibility,
	 *                           it is not possible to pass an empty string to delete those entries with an empty string
	 *                           for a value).
	 * @param bool   $delete_all Optional, default is false. If true, delete matching metadata entries for all objects,
	 *                           ignoring the specified object_id. Otherwise, only delete matching metadata entries for
	 *                           the specified object_id.
	 *
	 * @return bool True on successful delete, false on failure.
	 */
	public function delete( $object_id, $meta_key = '', $meta_value = '', $delete_all = false ) {
		if ( ! $meta_key ) {
			Cache::set( $object_id, $this->table, null );

			return true;
		}

		// Delete from cache.
		$row = Cache::get( $object_id, $this->table );
		if ( ! isset( $row[ $meta_key ] ) ) {
			return true; // If it is empty, do nothing.
		}

		if ( $delete_all || ! $meta_value || $row[ $meta_key ] === $meta_value ) {
			$row[ $meta_key ] = null;
			Cache::set( $object_id, $this->table, $row );

			return true;
		}

		if ( ! is_array( $row[ $meta_key ] ) ) {
			return true;
		}

		// For field with multiple values.
		foreach ( $row[ $meta_key ] as $key => $value ) {
			if ( $value === $meta_value ) {
				unset( $row[ $meta_key ][ $key ] );
			}
		}

		Cache::set( $object_id, $this->table, $row );

		return true;
	}

	public function row_exists( $object_id ) {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE `ID` = %d", $object_id ) );

		return $count > 0;
	}

	public function update_row( $object_id, $row ) {
		global $wpdb;
		if ( empty( $row ) ) {
			$this->delete_row( $object_id );
			return false;
		}
		$where = array( 'ID' => $object_id );
		do_action( 'mbct_before_update', $object_id, $this->table, $row );
		$output = $wpdb->update( $this->table, (array) $row, $where );
		do_action( 'mbct_after_update', $object_id, $this->table, $row );
		return $output;
	}

	public function insert_row( $row ) {
		global $wpdb;
		$id = isset( $row['ID'] ) ? $row['ID'] : null;
		do_action( 'mbct_before_add', $id, $this->table, $row );
		$output = $wpdb->insert( $this->table, $row );
		do_action( 'mbct_after_add', $wpdb->insert_id, $this->table, $row );
		return $output;
	}

	public function delete_row( $object_id ) {
		global $wpdb;
		$where = array( 'ID' => $object_id );
		do_action( 'mbct_before_delete', $object_id, $this->table );
		$output = $wpdb->delete( $this->table, $where );
		do_action( 'mbct_after_delete', $object_id, $this->table );
		return $output;
	}
}
