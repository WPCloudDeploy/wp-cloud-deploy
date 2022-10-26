<?php
namespace MetaBox\CustomTable;

class Loader {
	public function __construct() {
		add_filter( 'rwmb_meta_box_class_name', [ $this, 'meta_box_class_name' ], 10, 2 );

		add_filter( 'rwmb_get_storage', [ $this, 'get_storage' ], 10, 3 );
		add_action( 'rwmb_after_save_post', [ $this, 'update_object_data' ] );
		add_action( 'delete_post', [ $this, 'delete_object_data' ] );
		add_action( 'delete_term', [ $this, 'delete_object_data' ] );
		add_action( 'deleted_user', [ $this, 'delete_object_data' ] );
	}

	/**
	 * Filter meta box class name for custom model.
	 *
	 * @param  string $class_name Meta box class name.
	 * @param  array  $args       Meta box settings.
	 * @return string
	 */
	public function meta_box_class_name( $class_name, $args ) {
		if ( ! empty( $args['models'] ) ) {
			$class_name = __NAMESPACE__ . '\Model\MetaBox';
		}
		return $class_name;
	}

	public function get_storage( $storage, $object_type, $meta_box ) {
		if ( $meta_box && $this->uses_custom_table( $meta_box ) ) {
			$storage = new Storage;
			$storage->table = $meta_box->table;
		}

		return $storage;
	}

	public function update_object_data( $object_id ) {
		global $wpdb;

		$object_type = $this->get_saved_object_type();
		$meta_boxes  = $this->get_meta_boxes_for( $object_type, $object_id );

		foreach ( $meta_boxes as $meta_box ) {
			$storage = $meta_box->get_storage();
			$row     = Cache::get( $object_id, $meta_box->table );
			$row     = array_map( [ $this, 'maybe_serialize' ], $row );

			$has_data = $this->has_data( $row );
			if ( ! $has_data ) {
				$storage->delete_row( $object_id );
				continue;
			}

			if ( $storage->row_exists( $object_id ) ) {
				$storage->update_row( $object_id, $row );
				continue;
			}

			if ( $object_type === 'model' ) {
				$storage->insert_row( $row );
				$object_id = $wpdb->insert_id; // Ensure next meta box update the data of the same inserted object.
			} else {
				$row['ID'] = $object_id; // Must set to connect to existing WP objects.
				$storage->insert_row( $row );
			}
		}
	}

	/**
	 * Don't use WordPress's maybe_serialize() because it double-serializes if the data is already serialized.
	 */
	private function maybe_serialize( $data ) {
		return is_array( $data ) ? serialize( $data ) : $data;
	}

	public function delete_object_data( $object_id ) {
		$object_type = $this->get_deleted_object_type();
		$meta_boxes  = $this->get_meta_boxes_for( $object_type, $object_id );

		foreach ( $meta_boxes as $meta_box ) {
			$storage = $meta_box->get_storage();
			$storage->delete( $object_id ); // Delete from cache.
			$storage->delete_row( $object_id ); // Delete from DB.
		}
	}

	private function uses_custom_table( $meta_box ) {
		return 'custom_table' === $meta_box->storage_type && $meta_box->table;
	}

	private function get_meta_boxes_for( $object_type, $object_id ) {
		$meta_boxes = rwmb_get_registry( 'meta_box' )->get_by( [
			'storage_type' => 'custom_table',
			'object_type'  => $object_type,
		] );
		if ( 'user' === $object_type ) {
			return $meta_boxes;
		}

		array_walk( $meta_boxes, [ $this, 'check_type' ], [ $object_type, $object_id ] );
		$meta_boxes = array_filter( $meta_boxes );

		return $meta_boxes;
	}

	private function get_saved_object_type() {
		global $wp_current_filter;

		foreach ( $wp_current_filter as $hook ) {
			if ( 'edit_comment' === $hook ) {
				return 'comment';
			}
			if ( 'profile_update' === $hook || 'user_register' === $hook ) {
				return 'user';
			}
			if ( 0 === strpos( $hook, 'edited_' ) || 0 === strpos( $hook, 'created_' ) ) {
				return 'term';
			}
			if ( 'mbct_model_edit_load' === $hook ) {
				return 'model';
			}
		}
		return 'post';
	}

	private function get_deleted_object_type() {
		return str_replace( array( 'delete_', 'deleted_' ), '', current_filter() );
	}

	private function check_type( &$meta_box, $key, $object_data ) {
		list( $object_type, $object_id ) = $object_data;

		$type = null;
		$prop = null;
		switch ( $object_type ) {
			case 'post':
				$type = get_post_type( $object_id );
				if ( 'revision' === $type ) {
					return;
				}
				$prop = 'post_types';
				break;
			case 'term':
				$type = $object_id;
				$term = get_term( $object_id );
				$type = is_object( $term ) ? $term->taxonomy : null;
				$prop = 'taxonomies';
				break;
			case 'model':
				$page = rwmb_request()->get( 'page' );
				if ( strpos( $page, 'model-' ) === 0 ) {
					$type = substr( $page, 6 );
				}
				$prop = 'models';
				break;
		}
		if ( ! $type || ! in_array( $type, $meta_box->meta_box[ $prop ], true ) ) {
			$meta_box = false;
		}
	}

	private function has_data( $row ) {
		if ( ! $row ) {
			return false;
		}

		unset( $row['ID'] );

		foreach ( $row as $value ) {
			if ( ! in_array( $value, ['', null, []] ) ) {
				return true;
			}
		}

		return false;
	}
}
