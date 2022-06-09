<?php
namespace MetaBox\CustomTable\Model;

class Ajax {
	public function __construct() {
		add_action( 'wp_ajax_mbct_delete_items', [ $this, 'delete' ] );
	}

	public function delete() {
		check_ajax_referer( 'delete-items' );

		$request    = rwmb_request();
		$ids        = array_map( 'absint', $request->post( 'ids' ) );
		$model_name = sanitize_text_field( $request->post( 'model' ) );
		$model      = Factory::get( $model_name );

		if ( ! $ids || ! $model_name || ! $model ) {
			wp_send_json_error( __( 'Invalid request', 'mb-custom-table' ) );
		}

		foreach ( $ids as $id ) {
			$result = $this->delete_item( $id, $model->table );
			if ( $result === false ) {
				wp_send_json_error( __( 'Something wrong deleting the items from the database, please try again later.', 'mb-custom-table' ) );
			}
		}
		wp_send_json_success();
	}

	private function delete_item( $id, $table ) {
		global $wpdb;

		do_action( 'mbct_before_delete', $id, $table );
		return $wpdb->delete(
			$table,
			[ 'id' => $id ],
			[ '%d' ]
		);
		do_action( 'mbct_after_delete', $id, $table );
	}
}