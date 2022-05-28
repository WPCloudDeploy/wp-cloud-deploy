<?php
/**
 * Mimic add new page.
 * @see wp-admin/post-new.php
 * @see wp-admin/edit-form-advanced.php
 */
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?= esc_html( $this->model->labels['add_new_item'] ) ?></h1>
	<hr class="wp-header-end">

	<form method="post" action="" enctype="multipart/form-data" id="post" class="rwmb-model-form">
		<?php
		wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );

		do_action( 'edit_form_top', null );
		?>
		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<div id="postbox-container-1" class="postbox-container">
					<?php do_meta_boxes( null, 'side', null ) ?>
				</div>
				<div id="postbox-container-2" class="postbox-container">
					<?php do_meta_boxes( null, 'normal', null ) ?>
					<?php do_meta_boxes( null, 'advanced', null ) ?>
				</div>
			</div>

			<br class="clear">
		</div>
	</form>
</div>