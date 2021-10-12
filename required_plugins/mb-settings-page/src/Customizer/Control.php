<?php
namespace MBSP\Customizer;

class Control extends \WP_Customize_Control {
	public $type = 'meta_box';
	public $meta_box;

	public function render_content() {
		$this->meta_box->show();
		?>
		<input type="hidden" <?php $this->link(); ?>>
		<?php
	}

	public function enqueue() {
		$this->meta_box->enqueue();

		wp_enqueue_style( 'mbsp-customizer', MBSP_URL . 'assets/customizer.css' );

		wp_register_script( 'mb-jquery-serialize-object', MBSP_URL . 'assets/jquery.serialize-object.js', ['jquery'], '2.5.0', true );
		wp_enqueue_script( 'mbsp-customizer', MBSP_URL . 'assets/customizer.js', ['customize-controls', 'mb-jquery-serialize-object', 'rwmb', 'underscore'], '', true );
	}
}