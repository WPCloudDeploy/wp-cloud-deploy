/*
* This JS file is loaded for the user notification shortcode screen
*/

(function ($, wpcdusernotify) {

    var interval;

    $(document).ready(function () {
        init();
    });

    // For selecting the all or none options of the dropdowns
    function initSelectAllNoneOptions() {
        // Triggered from the select all or none text
        $('body').on('click', '.wpcd-select-all-none a', function (e) {
            e.preventDefault();

            var $this = $(this),
                $select = $this.parent().siblings('select');

            if ('none' === $this.data('type')) {
                $select.val([]).trigger('change');
                return;
            }
            var selected = [];
            $select.find('option').each(function (index, option) {
                selected.push(option.value);
            });
            $select.val(selected).trigger('change');
        });
    }

    // For saving or updating the user notification data
    function initUserNotificationDataSubmit() {

        // If send to zapier field checked then show the zapier webhook field
        jQuery('body').on('click', '#wpcd_notify_send_to_zapier', function (e) {
            if (jQuery('#wpcd_notify_send_to_zapier').is(":checked")) {
                jQuery('.wpcd_zapier_webhook_section').show();
            } else {
                jQuery('.wpcd_zapier_webhook_section').hide();
            }
        });

        // To open the user notification alert form - popup
        $('body').on('click', '.wpcd_add_notify_alert_btn,.wpcd-edit-notify-alert', function (e) {
            e.preventDefault();
            var enable_btn = $(this);
            var action = $(this).data('action');
            var nonce = $(this).data('nonce');
            var post_id = $(this).data('post_id');

            // Disable the button
            jQuery(this).attr('disabled', 'disabled');
            jQuery('.wpcd_user_notify_list_sec').addClass('wpcd_all_user_notify_disable');
            jQuery('#wpcd_user_notify_popup_sec').remove();

            $.ajax({
                url: wpcdusernotify.admin_ajax,
                method: 'POST',
                data: {
                    action: action,
                    nonce: nonce,
                    post_id: post_id
                },
                success: function (html) {
                    enable_btn.removeAttr('disabled');
                    jQuery('.wpcd_user_notify_list_sec').removeClass('wpcd_all_user_notify_disable');
                    jQuery('body').append(html);
                },
                error: function (error) {
                    enable_btn.removeAttr('disabled');
                    jQuery('.wpcd_user_notify_list_sec').removeClass('wpcd_all_user_notify_disable');
                }
            });

        });

        // To close the user notification alert form - popup
        jQuery('body').on('click', '.wpcd_user_notify_close', function (e) {
            e.preventDefault();
            jQuery('.wpcd_user_notify_list_sec').removeClass('wpcd_all_user_notify_disable');
            jQuery('#wpcd_user_notify_popup_sec').hide();
            jQuery('#wpcd_user_notify_popup_sec').remove();
        });

        // For submit/update the data of user notification
        $('body').on('click', '.wpcd_user_notify_submit', function (e) {
            e.preventDefault();

            var enable_btn = jQuery(this);
            jQuery('.wpcd_user_notify_error').hide();

            // Check validation of all the fields
            var validated = initNotifyFieldsValidation();

            if (!validated) {
                // Focus to the error message of the fields if invalid
                jQuery(".wpcd_user_notify_error:visible").each(function () {
                    var error_field = jQuery(this);
                    jQuery('.wpcd_user_notify_modal').animate({
                        scrollTop: error_field.offset().top - 100
                    }, 100);
                    return false; // breaks
                });
            } else {
                var action = $(this).data('action');
                var nonce = $(this).data('nonce');
                var post_id = $(this).data('post_id');

                var profile_name = jQuery('#wpcd_notify_profile_name').val();
                var email_addresses = jQuery('#wpcd_notify_email_addresses').val();
                var slack_webhooks = jQuery('#wpcd_notify_slack_webhooks').val();
                var zapier_webhooks = jQuery('#wpcd_notify_zapier_webhooks').val();

                var send_to_zapier = 0;
                if (jQuery('#wpcd_notify_send_to_zapier').is(":checked")) {
                    var send_to_zapier = 1;
                }

                var servers = jQuery('#wpcd_notify_user_servers').val();
                var sites = jQuery('#wpcd_notify_sites').val();
                var types = jQuery('#wpcd_notify_types').val();
                var references = jQuery('#wpcd_notify_references').val();

                // If all notification methods are empty then also return
                if (email_addresses == '' && slack_webhooks == '' &&
                    !jQuery('#wpcd_notify_send_to_zapier').is(":checked")) {
                    alert(wpcdusernotify.i10n.wpcd_notify_method_empty);
                    return false;
                }

                jQuery(".user_notify_wait_msg").show();
                jQuery(this).attr('disabled', 'disabled');

                $.ajax({
                    url: wpcdusernotify.admin_ajax,
                    method: 'POST',
                    data: {
                        action: action,
                        nonce: nonce,
                        post_id: post_id,
                        profile_name: profile_name,
                        email_addresses: email_addresses,
                        slack_webhooks: slack_webhooks,
                        send_to_zapier: send_to_zapier,
                        zapier_webhooks: zapier_webhooks,
                        servers: servers,
                        sites: sites,
                        types: types,
                        references: references
                    },
                    success: function (data) {
                        alert(data.data.msg);
                        location.reload();
                        jQuery('#wpcd_user_notify_popup_sec').hide();
                        jQuery(".user_notify_wait_msg").hide();
                    },
                    error: function (error) {
                        alert(error);
                        location.reload();
                        jQuery('#wpcd_user_notify_popup_sec').hide();
                        jQuery(".user_notify_wait_msg").hide();
                    }
                });
            }

        });
    }

    // For deleting the user notification data
    function initUserNotificationDataDelete() {
        // For delete the data of user notification
        $('body').on('click', '.wpcd-delete-notify-alert', function (e) {
            e.preventDefault();

            var action = $(this).data('action');
            var nonce = $(this).data('nonce');
            var post_id = $(this).data('post_id');

            // Confirmation for deleting the existing entry
            var confirm_delete = confirm(wpcdusernotify.i10n.wpcd_notify_alert_delete);

            if (confirm_delete) {

                jQuery(this).attr('disabled', 'disabled');
                jQuery('.wpcd_user_notify_list_sec').addClass('wpcd_all_user_notify_disable');

                $.ajax({
                    url: wpcdusernotify.admin_ajax,
                    method: 'POST',
                    data: {
                        action: action,
                        nonce: nonce,
                        post_id: post_id,
                    },
                    success: function (data) {
                        alert(data.data.msg);
                        location.reload();
                    },
                    error: function (error) {
                        jQuery('.wpcd_user_notify_list_sec').removeClass('wpcd_all_user_notify_disable');
                        alert(error);
                        location.reload();
                    }
                });
            }

        });
    }

    // For test zapier webhook.
    function initZapierWebhookTest() {
        // For test zapier webhook with dummy test data.
        $('body').on('click', '.wpcd_zapier_webhook_test', function (e) {
            e.preventDefault();

            var enable_btn = jQuery(this);
            jQuery('.wpcd_user_notify_error').hide();

            var zapier_webhooks = jQuery('#wpcd_notify_zapier_webhooks').val();

            // Check validation for zapier field
            var valid = true;
            if (zapier_webhooks != '' && jQuery('#wpcd_notify_send_to_zapier').is(":checked")) {
                var validated = initCheckZapierValidation(zapier_webhooks, valid);
            } else {
                jQuery('#wpcd_notify_zapier_webhooks').parent().find('.wpcd_user_notify_error').show();
                return false;
            }

            if (!validated) {
                // Focus to the error message of the fields if invalid
                jQuery(".wpcd_user_notify_error:visible").each(function () {
                    var error_field = jQuery(this);
                    jQuery('.wpcd_user_notify_modal').animate({
                        scrollTop: error_field.offset().top - 100
                    }, 100);
                    return false; // breaks
                });
            } else {
                var action = $(this).data('action');
                var nonce = $(this).data('nonce');

                jQuery(".zapier_test_wait_msg").show();
                jQuery(this).attr('disabled', 'disabled');

                $.ajax({
                    url: wpcdusernotify.admin_ajax,
                    method: 'POST',
                    data: {
                        action: action,
                        nonce: nonce,
                        zapier_webhooks: zapier_webhooks
                    },
                    success: function (data) {
                        alert(data.data.msg);
                        enable_btn.removeAttr('disabled');
                        jQuery(".zapier_test_wait_msg").hide();
                    },
                    error: function (error) {
                        alert(error);
                        enable_btn.removeAttr('disabled');
                        jQuery(".zapier_test_wait_msg").hide();
                    }
                });
            }

        });
    }

    // For validating the fields on the user notification form
    function initNotifyFieldsValidation() {

        // Get fields to check for validation
        var wpcd_notify_email_addresses = $('#wpcd_notify_email_addresses').val();
        var wpcd_notify_slack_webhooks = $('#wpcd_notify_slack_webhooks').val();
        var wpcd_notify_send_to_zapier = $('#wpcd_notify_send_to_zapier').val();
        var wpcd_notify_zapier_webhooks = $('#wpcd_notify_zapier_webhooks').val();

        var valid = true;


        // SHOW ERROR MESSAGES IF FIELDS ARE NOT SELECTED

        /*
        * For validate the email address
        * Split by comma if multiple emails are there and create an array of those emails 
        to validate each email
        */

        // Remove extra spaces from the email string
        var all_emails = wpcd_notify_email_addresses.replace(/ /g, '');

        if (wpcd_notify_email_addresses != '') {

            /* 
            * Regex for email validation
            * Email should be something like : example@gmail.com
            */
            var regex = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;

            // Split the string and create an emails array
            var emails_result = all_emails.split(",");
            // Filter the emails array if empty values
            var filter_emails = emails_result.filter(function (el) {
                return el != null && el != "";
            });

            // Check filtered array is empty or not
            if (filter_emails == '' || filter_emails == undefined) {
                jQuery('#wpcd_notify_email_addresses').val('');
            } else {
                // Check validation for the each email address
                jQuery('#wpcd_notify_email_addresses').val(filter_emails.join(","));
                $.each(filter_emails, function (index, value) {
                    if (!regex.test(value)) {
                        valid = false;
                        $('#wpcd_notify_email_addresses').parent().find('.wpcd_user_notify_error').show();
                    }
                });
            }
        }


        /*
        * For validating the slack webhook URLs.
        * Split the string by comma if there are multiple slack webhooks. Then
        * create an array of those slack webhooks so we can validate each 
        * individually.
        */

        // Remove extra spaces from the slack string
        var all_slack_webhooks = wpcd_notify_slack_webhooks.replace(/ /g, '');

        if (wpcd_notify_slack_webhooks != '') {

            /* 
            * Regex for slack webhoook URL validation
            * Slack webhook URL should be something like : 
            * https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX
            */
            var slack_validate = /^(http|https)?:\/\/[a-zA-Z0-9-\.]+\.[a-z]{2,6}/;

            // Split the string and create an webhooks array
            var webhooks_result = all_slack_webhooks.split(",");
            // Filter the webhooks array if empty values
            var filter_webhooks = webhooks_result.filter(function (el) {
                return el != null && el != "";
            });

            // Check filtered array is empty or not
            if (filter_webhooks == '' || filter_webhooks == undefined) {
                jQuery('#wpcd_notify_slack_webhooks').val('');
            } else {
                // Check validation for the each webhook url
                jQuery('#wpcd_notify_slack_webhooks').val(filter_webhooks.join(","));
                $.each(filter_webhooks, function (index, value) {
                    var word_count = value.split('://').length - 1;
                    if (!slack_validate.test(value) || word_count > 1) {
                        valid = false;
                        $('#wpcd_notify_slack_webhooks').parent().find('.wpcd_user_notify_error').show();
                    }
                });
            }
        }


        /*
        * For validating the zapier webhook URL.
        * Split the string by comma if there are multiple zapier webhooks. Then 
        * create an array of those zapier webhooks so we can validate each one
        * individually.
        */

        var valid_check = initCheckZapierValidation(wpcd_notify_zapier_webhooks, valid);

        if (valid_check) {
            valid = true;
        } else {
            valid = false;
        }

        // Invalid - If send to zapier is enabled and zapier webhook field is empty
        if (jQuery('#wpcd_notify_zapier_webhooks').val() == '' && jQuery('#wpcd_notify_send_to_zapier').is(":checked")) {
            valid = false;
            $('#wpcd_notify_zapier_webhooks').parent().find('.wpcd_user_notify_error').show();
        }

        return valid;
    }

    // Check zapier validation code.
    function initCheckZapierValidation(wpcd_notify_zapier_webhooks, valid) {
        // Remove extra spaces from the zapier string
        var all_zapier_webhooks = wpcd_notify_zapier_webhooks.replace(/ /g, '');

        if (wpcd_notify_zapier_webhooks != '' && jQuery('#wpcd_notify_send_to_zapier').is(":checked")) {

            /* 
            * Regex for zapier webhoook URL validation
            * zapier webhook URL should be something like : 
            * https://hooks.zapier.com/hooks/catch/123456/abcdef/
            */
            var zapier_validate = /^(http|https)?:\/\/[a-zA-Z0-9-\.]+\.[a-z]{2,6}/;

            // Split the string and create an webhooks array
            var webhooks_result = all_zapier_webhooks.split(",");
            // Filter the webhooks array if empty values
            var filter_webhooks = webhooks_result.filter(function (el) {
                return el != null && el != "";
            });

            // Check filtered array is empty or not
            if (filter_webhooks == '' || filter_webhooks == undefined) {
                jQuery('#wpcd_notify_zapier_webhooks').val('');
            } else {
                // Check validation for the each webhook url
                jQuery('#wpcd_notify_zapier_webhooks').val(filter_webhooks.join(","));
                $.each(filter_webhooks, function (index, value) {
                    var word_count = value.split('://').length - 1;
                    if (!zapier_validate.test(value) || word_count > 1) {
                        valid = false;
                        $('#wpcd_notify_zapier_webhooks').parent().find('.wpcd_user_notify_error').show();
                    }
                });
            }
        }

        return valid;
    }

    /**
     * Instantiate JS functions
     */
    function init() {
        initUserNotificationDataSubmit();
        initUserNotificationDataDelete();
        initSelectAllNoneOptions();
        initZapierWebhookTest();
    }

})(jQuery, wpcdusernotify);