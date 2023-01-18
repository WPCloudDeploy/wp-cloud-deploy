<?php
/**
 * Mimic edit page.
 * @see wp-admin/edit.php
 * @see wp-admin/edit-form-advanced.php
 */

$message = rwmb_request()->get( 'model-message' );

?>
<div class="wrap">
	<?php
	printf( '<a href="%s" class="wpcd-button">%s</a>', 
			remove_query_arg( array( 'model-id', 'model-action', 'model-message' ) ), 
			'Back to ' . $this->model->labels['name'] 
			);
	
	if( $action == 'add' ) {
		printf( '<h3>%s</h3>', esc_html( $this->model->labels['add_new_item'] ) );
	} else {
		printf( '<h3>%s #%s</h3>', esc_html( $this->model->labels['edit_item'] ), $item_id );
	}
	
	do_action('wpcd_ct_public_notices');

	if ( $message ) : ?>
		<div id="message" class="updated notice notice-success is-dismissible"><p><?= esc_html( $this->model->labels["item_$message"] ) ?></p></div>
	<?php endif ?>
	
	<form method="post" action="" enctype="multipart/form-data" id="post" class="rwmb-model-form">
		<?php
		wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
		
		?>
			
		<input type="hidden" name="nonce" value="<?php echo $this->get_nonce( $action ); ?>" />
		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<div id="postbox-container-2" class="postbox-container">
						<?php
						$meta_boxes = rwmb_get_registry( 'meta_box' )->get_by( array(
							'object_type' => 'model',
							'table'		=> $this->model->table
						) );
						
						$submit_box_rendered = false;
						
						foreach ( $meta_boxes as $mb_id => $mb ) {
							
							$mb->set_object_id( $item_id );
							$id = $mb_id;
							if( isset( $mb->meta_box['mb-submit-box'] ) ) {
								$submit_box_rendered = true;
								$id = 'mb_submit_box';
							}
							
							$classes = '';
							$title = "";
							if( isset( $mb->meta_box['child-items-mb'] ) ) {
								$classes = 'child-items-mb';
								$title = sprintf( '<div class="wpcd-mb-title">%s</div>', $mb->meta_box['title'] );
							}
							
							ob_start();
							$mb->show();
							$mb_content = ob_get_clean();
							
							printf( '<div id="%s" class="%s">%s</div>', $id, $classes, $title . $mb_content );
						}
						
						if( !$submit_box_rendered ) {
							echo $this->submit_box();
						}
						
						?>
				</div>
			</div>

			<br class="clear">
		</div>
	</form>
</div>