<?php
namespace MetaBox\CustomTable;

class API {
	/**
	 * Create table, use dbDelta() function.
	 *
	 * @param string $table_name Table name without prefix.
	 * @param array  $columns    Table columns, is an array with key is column name
	 *                           and value is column structure.
	 * @param array  $keys       Table keys, is a numeric array contain key name and
	 *                           column. Example: post_name (post_name).
	 */
	public static function create( $table_name, $columns, $keys = [], $auto_increment = false ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name = str_replace( '-', '_', $table_name );

		$sql = self::get_table_schema( $table_name, $columns, $keys, $auto_increment );
		dbDelta( $sql );
	}

	/**
	 * Get table schema.
	 *
	 * @param string $table_name Table name.
	 * @param array  $columns    Table columns, is an array with key is column name
	 *                           and value is column structure.
	 * @param array  $keys       Table keys, is a numeric array contain key name and
	 *                           column. Example: post_name (post_name).
	 *
	 * @return string
	 */
	private static function get_table_schema( $table_name, $columns, $keys = [], $auto_increment = false ) {
		if ( ! $columns ) {
			return '';
		}

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$lines   = [];
		$lines[] = '`ID` bigint(20) unsigned NOT NULL' . ( $auto_increment ? ' AUTO_INCREMENT' : '' );
		foreach ( $columns as $name => $value ) {
			$lines[] = "`$name` $value";
		}

		$lines[] = 'PRIMARY KEY  (`ID`)';
		foreach ( $keys as $key ) {
			$lines[] = "KEY `$key` (`$key`)";
		}

		$lines = implode( ",\n", $lines );

		$sql = "
			CREATE TABLE $table_name (
				$lines
			) $charset_collate;
		";

		return $sql;
	}

	public static function exists( $object_id, $table ) {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE `ID` = %d", $object_id ) );

		return $count > 0;
	}

	public static function get( $object_id, $table ) {
		$row = Cache::get( $object_id, $table );
		return array_map( 'maybe_unserialize', $row );
	}

	/**
	 * Set $object_id to null for auto-increment table (for models).
	 */
	public static function add( $object_id, $table, $row ) {
		Cache::set( $object_id, $table, $row );

		global $wpdb;
		$row['ID'] = $object_id;
		$row = array_map( 'self::maybe_serialize', $row );
		do_action( 'mbct_before_add', $object_id, $table, $row );
		$output = $wpdb->insert( $table, $row );
		do_action( 'mbct_after_add', $object_id, $table, $row );
		return $output;
	}

	public static function update( $object_id, $table, $row ) {
		if ( empty( $row ) ) {
			self::delete( $object_id, $table );
			return false;
		}

		Cache::set( $object_id, $table, $row );

		global $wpdb;
		$row = array_map( 'self::maybe_serialize', $row );
		do_action( 'mbct_before_update', $object_id, $table, $row );
		$output = $wpdb->update( $table, (array) $row, ['ID' => $object_id] );
		do_action( 'mbct_after_update', $object_id, $table, $row );
		return $output;
	}

	public static function delete( $object_id, $table ) {
		Cache::set( $object_id, $table, [] );

		global $wpdb;
		do_action( 'mbct_before_delete', $object_id, $table );
		$output = $wpdb->delete( $table, ['ID' => $object_id] );
		do_action( 'mbct_after_delete', $object_id, $table );
		return $output;
	}

	/**
	 * Don't use WordPress's maybe_serialize() because it double-serializes if the data is already serialized.
	 */
	private static function maybe_serialize( $data ) {
		return is_array( $data ) ? serialize( $data ) : $data;
	}

	public static function get_value( $field_id, $object_id, $table ) {
		$row = self::get( $object_id, $table );
		return isset( $row[ $field_id ] ) ? $row[ $field_id ] : null;
	}
}
