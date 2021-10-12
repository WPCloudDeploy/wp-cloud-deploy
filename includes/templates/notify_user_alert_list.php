<?php
/**
 * This template file used for list notify alert html
 *
 * @package wpcd
 */

// Check if user logged in or not.
if ( is_user_logged_in() ) {

	// Get current logged in user id.
	$current_user_id = get_current_user_id();

	// Get user notification alerts data.
	$user_notify_posts = get_posts(
		array(
			'post_type'   => 'wpcd_notify_user',
			'post_status' => 'private',
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'author'      => $current_user_id,
		)
	);

	$add_new_color = wpcd_get_option( 'wordpress_app_add_new_button_color' );
	$add_new_color = empty( $add_new_color ) ? '#0d6efd' : $add_new_color;
	?>

<div class="wpcd_user_notify_list_sec">
	<div class="wpcd_notify_add_sec">
		<button data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpcd-user-notify-form-display' ) ); ?>" data-action="wpcd_user_notification_display_form_popup" data-post_id="0" class="wpcd_add_notify_alert_btn btn btn-primary" style="border-color:<?php echo esc_html( $add_new_color ); ?>; background-color:<?php echo esc_html( $add_new_color ); ?>;"><?php echo esc_html( __( 'Add New Alert', 'wpcd' ) ); ?></button>        
	</div>
	<div class="wpcd_notify_table_sec">
		<table class="wpcd_user_notify_table">
			<thead>
				<tr>
					<th><?php echo esc_html( __( 'Name / Description', 'wpcd' ) ); ?></th>
					<th><?php echo esc_html( __( 'Email Addresses', 'wpcd' ) ); ?></th>
					<th><?php echo esc_html( __( 'Slack Webhooks', 'wpcd' ) ); ?></th>
					<th><?php echo esc_html( __( 'Send to Zapier', 'wpcd' ) ); ?></th>
					<th><?php echo esc_html( __( 'Created Date', 'wpcd' ) ); ?></th>
					<th><?php echo esc_html( __( 'Edit', 'wpcd' ) ); ?></th>
					<th><?php echo esc_html( __( 'Delete', 'wpcd' ) ); ?></th>
				</tr> 
			</thead>
			<tbody>
		<?php
		if ( ! empty( $user_notify_posts ) ) {
			foreach ( $user_notify_posts as $notify_data ) {
				$notify_id    = $notify_data->ID;
				$created_date = $notify_data->post_date;

				$profile_name  = get_post_meta( $notify_id, 'wpcd_notify_user_profile_name', true );
				$notify_emails = get_post_meta( $notify_id, 'wpcd_notify_user_email_addresses', true );
				$notify_slack  = get_post_meta( $notify_id, 'wpcd_notify_user_slack_webhooks', true );
				$notify_zapier = get_post_meta( $notify_id, 'wpcd_notify_user_zapier_send', true );

				$profile_name  = ( ! empty( $profile_name ) ) ? $profile_name : __( '-', 'wpcd' );
				$notify_emails = ( ! empty( $notify_emails ) ) ? $notify_emails : __( '-', 'wpcd' );
				$notify_slack  = ( ! empty( $notify_slack ) ) ? $notify_slack : __( '-', 'wpcd' );
				$notify_zapier = ( '1' === $notify_zapier ) ? __( 'Yes', 'wpcd' ) : __( 'No', 'wpcd' );

				echo '<tr>';
				echo '<td>' . esc_html( $profile_name ) . '</td>';
				echo '<td>' . esc_html( $notify_emails ) . '</td>';
				echo '<td>' . esc_html( $notify_slack ) . '</td>';
				echo '<td>' . esc_html( $notify_zapier ) . '</td>';
				echo '<td>' . esc_html( $created_date ) . '</td>';
				echo '<td><a data-post_id="' . esc_attr( $notify_id ) . '" data-action="wpcd_user_notification_display_form_popup" data-nonce="' . esc_html( wp_create_nonce( 'wpcd-user-notify-form-display' ) ) . '" class="wpcd-edit-notify-alert">' . esc_html( __( 'Edit', 'wpcd' ) ) . '</a></td>';
				echo '<td><a data-post_id="' . esc_attr( $notify_id ) . '" data-action="wpcd_user_notification_data_delete" data-nonce="' . esc_html( wp_create_nonce( 'wpcd-user-notify-delete' ) ) . '" class="wpcd-delete-notify-alert">' . esc_html( __( 'Delete', 'wpcd' ) ) . '</a></td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="7">' . esc_html( __( 'No alerts found.', 'wpcd' ) ) . '</td></tr>';
		}
		?>
			</tbody>
		</table>
	</div>
</div>

<?php } else { ?>
	<p><?php echo esc_html( __( 'Sorry, you are not allowed to access this form. Please login to the system to access it.', 'wpcd' ) ); ?></p>
<?php } ?>
