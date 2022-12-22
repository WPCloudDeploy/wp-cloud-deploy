<?php
/**
 * Mimic edit page.
 * @see wp-admin/edit.php
 * @see wp-admin/edit-form-advanced.php
 */

$message = rwmb_request()->get( 'model-message' );
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?= esc_html( $this->model->labels['edit_item'] ) ?> #<?= esc_html( rwmb_request()->get( 'model-id' ) ) ?></h1>
	<hr class="wp-header-end">

	<?php $message = rwmb_request()->get( 'model-message' ) ?>
	<?php if ( $message && isset( $this->model->labels["item_$message"] ) ) : ?>
		<div id="message" class="updated notice notice-success is-dismissible"><p><?= esc_html( $this->model->labels["item_$message"] ) ?></p></div>
	<?php endif ?>

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