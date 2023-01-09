<?php $table->prepare_items(); ?>
<div class="wrap">
	<a href="<?php echo esc_url( add_query_arg( 'model-action', 'add' ) ) ?>" class="page-title-action wpcd-button"><?php echo esc_html( $this->model->labels['add_new'] ) ?></a>

	<?php if ( rwmb_request()->get( 'model-message' ) === 'deleted' ) : ?>
		<div id="message" class="updated notice notice-success is-dismissible"><p><?php echo esc_html( $this->model->labels['item_deleted'] ) ?></p></div>
	<?php endif ?>

	<form id="posts-filter" method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( "model-{$this->model->name}" ) ?>">
		<input type="hidden" name="model" value="<?php echo esc_attr( $this->model->name ) ?>">
		<?php
		$table->views();
		$table->display();
		?>
	</form>
</div>
