<?php $this->list_table->prepare_items(); ?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?= esc_html( $this->model->labels['all_items'] ) ?></h1>
	<a href="<?= esc_url( add_query_arg( 'model-action', 'add' ) ) ?>" class="page-title-action"><?= esc_html( $this->model->labels['add_new'] ) ?></a>
	<hr class="wp-header-end">

	<?php $message = rwmb_request()->get( 'model-message' ) ?>
	<?php if ( $message && isset( $this->model->labels["item_$message"] ) ) : ?>
		<div id="message" class="updated notice notice-success is-dismissible"><p><?= esc_html( $this->model->labels["item_$message"] ) ?></p></div>
	<?php endif ?>

	<form id="posts-filter" method="get">
		<input type="hidden" name="page" value="<?= esc_attr( "model-{$this->model->name}" ) ?>">
		<input type="hidden" name="model" value="<?= esc_attr( $this->model->name ) ?>">
		<?php
		$this->list_table->views();
		$this->list_table->search_box( $this->model->labels['search_items'], $this->model->name );
		$this->list_table->display();
		?>
	</form>
</div>
