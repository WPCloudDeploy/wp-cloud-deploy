<?php
namespace MetaBox\CustomTable;

class Loader {
	public function __construct() {
		add_filter( 'rwmb_meta_box_class_name', [ $this, 'meta_box_class_name' ], 10, 2 );

		add_filter( 'rwmb_get_storage', [ $this, 'get_storage' ], 10, 3 );
		add_action( 'rwmb_after_save_post', [ $this, 'update_object_data' ] );
		add_action( 'delete_post', [ $this, 'delete_object_data' ] );
		add_action( 'deleted_user', [ $this, 'delete_object_data' ] );
		add_action( 'delete_term', [ $this, 'delete_term_data' ], 10, 3 );
		add_action( 'rwmb_flush_data', [ $this, 'flush_data' ], 10, 3 );
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
			$storage        = new Storage;
			$storage->table = $meta_box->table;
		}

		return $storage;
	}

	/**
	 * This function is called each time a meta box saves data.
	 * To avoid updating multiple times, we need to run only when the last meta box saves data.
	 */
	public function update_object_data( $object_id ) {
		static $processed = [
			'id'    => [],
			'table' => [],
		];
		static $count     = 0;

		$is_rest     = defined( 'REST_REQUEST' ) && REST_REQUEST;
		$object_type = $this->get_saved_object_type( $is_rest );
		$meta_boxes  = $this->get_meta_boxes_for( $object_type, $object_id );

		// Remove un-validated meta box (like not included in the front end), which don't trigger `rwmb_after_save_post` hook.
		if ( ! $is_rest ) {
			$meta_boxes = array_filter( $meta_boxes, [ $this, 'validate' ] );
		}

		// Only update data when the last meta box saves data.
		$count++;
		if ( $count < count( $meta_boxes ) && ! $is_rest ) {
			return;
		}

		foreach ( $meta_boxes as $meta_box ) {
			// Avoid updating data multiple times if many meta boxes use the same table.
			$table = $meta_box->table;
			if ( ! $table || in_array( $object_id, $processed['id'], true ) && in_array( $table, $processed['table'], true ) ) {
				continue;
			}
			$processed['id'][]    = $object_id;
			$processed['table'][] = $table;

			$storage = $meta_box->get_storage();
			$row     = Cache::get( $object_id, $table );
			$row     = array_map( [ $this, 'maybe_serialize' ], $row );

			// Delete.
			if ( ! $this->has_data( $row ) ) {
				$storage->delete_row( $object_id );
				continue;
			}

			// Update.
			if ( $storage->row_exists( $object_id ) ) {
				$storage->update_row( $object_id, $row );
				continue;
			}

			// Add.
			if ( $object_type === 'model' ) {
				unset( $row['ID'] );
			} else {
				$row['ID'] = $object_id; // Must set to connect to existing WP objects.
			}
			$storage->insert_row( $row );
		}
	}

	/**
	 * This function is called by rwmb_set_meta hook.
	 * To save data in cache to database.
	 */
	public function flush_data( $object_id, $field, $args = [] ) {
		if ( empty( $field['id'] ) || ! $field['save_field'] ) {
			return;
		}

		$storage = $field['storage'];
		if ( Storage::class !== get_class( $storage ) ) {
			return;
		}

		$row = Cache::get( $object_id, $storage->table );
		$row = array_map( [ $this, 'maybe_serialize' ], $row );

		// Delete
		if ( ! $this->has_data( $row ) ) {
			$storage->delete_row( $object_id );
			return;
		}

		// Update.
		if ( $storage->row_exists( $object_id ) ) {
			$storage->update_row( $object_id, $row );
			return;
		}

		// Add.
		$object_type = $args['object_type'] ?? 'post';
		if ( $object_type === 'model' ) {
			unset( $row['ID'] );
		} else {
			$row['ID'] = $object_id; // Must set to connect to existing WP objects.
		}
		$storage->insert_row( $row );
	}

	/**
	 * Validate if a meta box is submitted properly.
	 * Used to check if a meta box is not included on the front end.
	 */
	private function validate( $meta_box ) {
		$nonce = rwmb_request()->filter_post( "nonce_{$meta_box->id}" );
		return wp_verify_nonce( $nonce, "rwmb-save-{$meta_box->id}" );
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
			if ( ! $this->uses_custom_table( $meta_box ) ) {
				continue;
			}
			$storage = $meta_box->get_storage();
			$storage->delete( $object_id ); // Delete from cache.
			$storage->delete_row( $object_id ); // Delete from DB.
		}
	}

	public function delete_term_data( int $object_id, int $tt_id, string $taxonomy ) {
		$meta_boxes = rwmb_get_registry( 'meta_box' )->get_by( [
			'object_type' => 'term',
		] );

		foreach ( $meta_boxes as $meta_box ) {
			if ( ! in_array( $taxonomy, $meta_box->taxonomies, true ) ) {
				continue;
			}
			if ( ! $this->uses_custom_table( $meta_box ) ) {
				continue;
			}
			$storage = $meta_box->get_storage();
			$storage->delete( $object_id ); // Delete from cache.
			$storage->delete_row( $object_id ); // Delete from DB.
		}
	}

	private function uses_custom_table( $meta_box ): bool {
		return 'custom_table' === $meta_box->storage_type && $meta_box->table;
	}

	/**
	 * Get meta boxes for the specific object by type and ID.
	 * This includes meta boxes that don't use custom tables.
	 */
	private function get_meta_boxes_for( $object_type, $object_id ): array {
		$meta_boxes = rwmb_get_registry( 'meta_box' )->get_by( [
			'object_type' => $object_type,
		] );
		if ( 'user' === $object_type ) {
			return $meta_boxes;
		}

		array_walk( $meta_boxes, [ $this, 'check_type' ], [ $object_type, $object_id ] );
		$meta_boxes = array_filter( $meta_boxes );

		return $meta_boxes;
	}

	private function get_saved_object_type( bool $is_rest ): string {
		$object_type = rwmb_request()->post( 'object_type' );
		if ( $is_rest && $object_type ) {
			return $object_type;
		}

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

	private function get_deleted_object_type(): string {
		return str_replace( [ 'delete_', 'deleted_' ], '', current_filter() );
	}

	private function check_type( &$meta_box, $key, $object_data ) {
		list( $object_type, $object_id ) = $object_data;

		$type = null;
		$prop = null;
		switch ( $object_type ) {
			case 'post':
				// Custom Gutenberg blocks are always available for posts.
				if ( $meta_box->type === 'block' ) {
					return;
				}
				$type = get_post_type( $object_id );
				if ( 'revision' === $type ) {
					return;
				}
				$prop = 'post_types';
				break;
			case 'term':
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

	private function has_data( $row ): bool {
		if ( ! $row ) {
			return false;
		}

		unset( $row['ID'] );

		foreach ( $row as $value ) {
			if ( ! in_array( $value, [ '', null, [] ], true ) ) {
				return true;
			}
		}

		return false;
	}
}
