<?php
namespace MetaBox\CustomTable\Model;

class Factory {
	private static $data = [];

	public static function make( $name, $args ) {
		$model = new Model( $name, $args );
		self::add( $name, $model );

		$admin = new Admin( $model );

		return $model;
	}

	public static function get( $key ) {
		return self::$data[ $key ] ?? null;
	}

	public static function add( $key, $value ) {
		self::$data[ $key ] = $value;
	}
}