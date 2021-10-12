<?php
class RWMB_Backup_Field extends RWMB_Textarea_Field {
	public static function html( $meta, $field ) {
		$storage_class = get_class( $field['storage'] );
		$func          = false !== strpos( $storage_class, 'Network' ) ? 'get_site_option' : 'get_option';

		$field['field_name'] = "{$field['option_name']}_backup";

		$meta = $func( $field['option_name'] );
		$meta = $meta ? wp_json_encode( $meta ) : '';

		return parent::html( $meta, $field );
	}

	public static function save( $new, $old, $post_id, $field ) {}

	public static function normalize( $field ) {
		$field = wp_parse_args( $field, [
			'rows'       => 5,
			'desc'       => __( 'To export settings, copy the content of this field and save to a file. To import settings, paste the content of the backup file here.', 'mb-settings-page' ),
			'attributes' => [
				'onclick' => 'this.select()',
			],
		] );
		$field = parent::normalize( $field );

		return $field;
	}
}