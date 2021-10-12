/* global ajaxurl */
/* global params */
(function ($, params) {

	$(document).ready(function () {
		init();
	});

	function init() {

		$('.wp-heading-inline').append($('<a href="" class="wpcd-purge-log page-title-action">' + params.l10n.purge_title + '</a>'));

		if (params.post_type == 'wpcd_notify_log') {
			$('.wp-heading-inline').append($('<a href="" class="wpcd-purge-unsent-log page-title-action">' + params.l10n.purge_unsent_title + '</a>'));
		}

		if (params.post_type == 'wpcd_pending_log') {
			$('.wp-heading-inline').append($('<a href="" class="wpcd-cleanup-pending-logs page-title-action">' + params.l10n.clean_up_title + '</a>'));
		}

		// Purge logs button clicked.
		$('body').on('click', '.wpcd-purge-log', function (e) {
			e.preventDefault();
			var r = confirm(params.l10n.prompt);

			if (r) {
				jQuery('.display_waiting_message').remove();
				jQuery("<span class='display_waiting_message'> " + params.l10n.wait_msg + "</span>").insertAfter('.wp-heading-inline');

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: params.action,
						nonce: params.nonce,
						params: {
							post_type: params.post_type
						}
					},
					success: function (data) {
						jQuery('.display_waiting_message').remove();
						if (data.success) {
							alert(data.data.msg);
							location.reload();
						} else {
							alert(data.data.msg);
						}
					},
					error: function (error) {
						jQuery('.display_waiting_message').remove();
					}
				});
			}

		});

		// Purge all sent notification logs.
		$('body').on('click', '.wpcd-purge-unsent-log', function (e) {
			e.preventDefault();
			var r = confirm(params.l10n.unsent_prompt);

			if (r) {
				jQuery('.display_waiting_message').remove();
				jQuery("<span class='display_waiting_message'> " + params.l10n.wait_msg + "</span>").insertAfter('.wp-heading-inline');

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: params.unsent_action,
						nonce: params.unsent_nonce,
						params: {
							post_type: params.post_type
						}
					},
					success: function (data) {
						jQuery('.display_waiting_message').remove();
						if (data.success) {
							alert(data.data.msg);
							location.reload();
						} else {
							alert(data.data.msg);
						}
					},
					error: function (error) {
						jQuery('.display_waiting_message').remove();
					}
				});
			}

		});

		// Clean up pending logs.
		$('body').on('click', '.wpcd-cleanup-pending-logs', function (e) {
			e.preventDefault();
			var r = confirm(params.l10n.clean_up_prompt);

			if (r) {
				jQuery('.display_waiting_message').remove();
				jQuery("<span class='display_waiting_message'> " + params.l10n.wait_msg + "</span>").insertAfter('.wp-heading-inline');

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: params.clean_up_action,
						nonce: params.clean_up_nonce,
						params: {
							post_type: params.post_type
						}
					},
					success: function (data) {
						jQuery('.display_waiting_message').remove();
						alert(data.message);
						location.reload();
					},
					error: function (error) {
						jQuery('.display_waiting_message').remove();
						alert(error);
						location.reload();
					}
				});
			}

		});

	}

})(jQuery, params);