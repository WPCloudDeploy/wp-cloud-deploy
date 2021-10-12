<?php
/**
 * Trait:
 * Contains code for getter and setter functions to set the post type being managed by the including class.
 * Used only by the class-wpcd-posts-app-server.php and class-wpcd-posts-app.php files which define the WPCD_POSTS_APP_SERVER and WPCD_POSTS_APP classes respectively.
 *
 * @package wpcd
 */

/**
 * Trait wpcd_get_set_post_type.
 */
trait wpcd_get_set_post_type {

	/**
	 * The primary post type being handled by the including class..
	 * This will typically be set by using the
	 * getter and setting functions defined later.
	 *
	 * @var $post_type post type.
	 */
	private $post_type = '';

	/**
	 * Sets the primary post type handled by the including class.
	 * It will be used by some traits to avoid
	 * code duplication.
	 *
	 * @param string $type the post type name.
	 *
	 * @return void
	 */
	public function set_post_type( $type ) {
		$this->post_type = $type;
	}

	/**
	 * Gets the post type handled by the including class.
	 *
	 * @return string The post type name handled by the instance of this class
	 */
	public function get_post_type() {
		return $this->post_type;
	}

}
