<?php
/**
 * Custom table caching before saving data
 *
 * @package    Meta Box
 * @subpackage MB Custom Table
 */

namespace MetaBox\CustomTable;

class Cache {
	/**
	 * Get data from cache.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $table     Table name.
	 *
	 * @return array
	 */
	public static function get( $object_id, $table ) {
		global $wpdb;

		$row = wp_cache_get( $object_id, self::get_cache_group( $table ) );
		if ( false === $row ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE `ID` = %d",
					$object_id
				),
				ARRAY_A
			);
			$row = is_array( $row ) ? $row : array();
			self::set( $object_id, $table, $row );
		}
		return is_array( $row ) ? $row : array();
	}

	/**
	 * Set a row to cache.
	 *
	 * @param int    $object_id Object ID.
	 * @param string $table     Table name.
	 * @param array  $row       Row data.
	 */
	public static function set( $object_id, $table, $row ) {
		wp_cache_set( $object_id, $row, self::get_cache_group( $table ) );
	}

	/**
	 * Get cache group name from table name.
	 *
	 * @param string $table Table name.
	 *
	 * @return string
	 */
	protected static function get_cache_group( $table ) {
		return "rwmb_{$table}_table_data";
	}
}
