<?php
namespace MetaBox\CustomTable;

class Cache {
	public static function get( $object_id, $table ): array {
		global $wpdb;

		if ( ! $object_id ) {
			return [];
		}

		$row = wp_cache_get( $object_id, self::get_cache_group( $table ) );
		if ( false !== $row ) {
			return is_array( $row ) ? $row : [];
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE `ID` = %d",
				$object_id
			),
			ARRAY_A
		);
		$row = is_array( $row ) ? $row : [];
		self::set( $object_id, $table, $row );

		return $row;
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

	public static function delete( $object_id, $table ) {
		wp_cache_delete( $object_id, self::get_cache_group( $table ) );
	}

	private static function get_cache_group( string $table ): string {
		return "rwmb_{$table}_table_data";
	}
}
