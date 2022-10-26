<?php
if ( ! function_exists( 'mb_register_model' ) ) {
	function mb_register_model( $name, $args ) {
		return MetaBox\CustomTable\Model\Factory::make( $name, $args );
	}
}