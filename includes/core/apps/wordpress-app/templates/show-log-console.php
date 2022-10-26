<?php
/**
 * Used to show just the log console screen when the user select "view logs" under the server title in the server list screen
 *
 * @package wpcd
 */

?>

<div class="wpcd-install-app-container-show-log-console">
	<?php require wpcd_path . 'includes/core/apps/wordpress-app/templates/header-popup.php'; ?>
	<style>
	/* This is needed to shift the "console" from the right column to the left since the default styling is to place the console on the right column */
	.wpcd-create-popup-fields-wrap {
		display: none !important;
	}
	.wpcd-create-popup-console-wrap {
		float: none !important;
	}
	</style>

	<div class="wpcd-install-app-container wpcd-popup">
		<div class="wpcd-action-title-wrap">
			<div class="wpcd-action-title"><?php echo esc_html( __( 'Viewing Logs for the Selected Server' ) ); ?></div>
		</div>
		<div class="wpcd-create-popup-grid-wrap">
			<div class = "wpcd-create-popup-console-wrap">
				<?php require wpcd_path . 'includes/core/apps/wordpress-app/templates/log-console.php'; ?>
			</div>
		</div>
	</div>
</div>