<?php
/**
 * Header Popup
 *
 * @package wpcd
 */

$no_logo = (bool) wpcd_get_option( 'wordpress_app_noshow_logo' );
if ( ( ( ! defined( 'WPCD_WPAPP_SKIP_POPUP_HEADER' ) ) || ( defined( 'WPCD_WPAPP_SKIP_POPUP_HEADER' ) && ! WPCD_WPAPP_SKIP_POPUP_HEADER ) ) && ! $no_logo ) {
	?>	
	<div class="wpcd-header">
		<div class="wpcd-header-wrap">
			<div class="wpcd-header-title"><img src=" <?php echo apply_filters( 'wpcd_popup_header_logo', wpcd_url . '/assets/images/wpcd-logo-june2020-1.png' ); ?>" alt="WPCloud Deploy Logo" width="300"></div>
		</div>
	</div>
	<?php
}
?>
