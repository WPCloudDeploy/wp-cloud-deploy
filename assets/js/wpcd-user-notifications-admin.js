/*
* This JS file is loaded for cpt wpcd_notify_user details screen only!
*/

/* global notifyparams */
(function ($, notifyparams) {
    init();

    /**
     * Instantiate JS functions
     */
    function init() {

        // If send to zapier field checked then show the zapier webhook field
        initCheckZapierEnabled(); // show or hide when page load
        jQuery('body').on('click', '#wpcd_notify_user_zapier_send', function (e) {
            initCheckZapierEnabled();
        });

        // Change div position.
        var zapier_parent = jQuery('#wpcd_notify_user_zapier_webhooks').parent();
        jQuery('#wpcd-user-zapier-webhook-test').parent().parent().appendTo(zapier_parent);

        // Test zapier webhook function.
        initBackendZapierWebhookTest();

        // Check the validation rules when click on the button
        jQuery('#submitdiv').on('click', '#publish', function (e) {
            var email_addresses = jQuery('#wpcd_notify_user_email_addresses').val();
            var slack_webhooks = jQuery('#wpcd_notify_user_slack_webhooks').val();
            var zapier_webhooks = jQuery('#wpcd_notify_user_zapier_webhooks').val();

            // Check validation of all the fields
            var validated = initNotifyMetaValidate();

            if (!validated) {
                return false;
            } else {
                // If all notification methods are empty then also return
                if (email_addresses == '' && slack_webhooks == '' &&
                    !jQuery('#wpcd_notify_user_zapier_send').is(":checked")) {
                    alert(notifyparams.i10n.empty_methods);
                    return false;
                }
            }
        });

        // Make author dropdown searchable
        $("#post_author_override").select2();

    }

    // For validating the meta fields of user notification
    function initNotifyMetaValidate() {

        // Get fields to check for validation
        var wpcd_notify_email_addresses = $('#wpcd_notify_user_email_addresses').val();
        var wpcd_notify_slack_webhooks = $('#wpcd_notify_user_slack_webhooks').val();
        var wpcd_notify_send_to_zapier = $('#wpcd_notify_user_zapier_send').val();
        var wpcd_notify_zapier_webhooks = $('#wpcd_notify_user_zapier_webhooks').val();

        // SHOW ERROR MESSAGES IF FIELDS ARE NOT SELECTED

        /*
        * For validate the email address
        * Split by comma if multiple emails are there and create an array of those emails 
        * to validate each email
        */

        // Remove extra spaces from the email string
        var all_emails = wpcd_notify_email_addresses.replace(/ /g, '');
        if (wpcd_notify_email_addresses != '') {
            var valid_email = true;

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
                jQuery('#wpcd_notify_user_email_addresses').val('');
            } else {
                // Check validation for the each email address
                jQuery('#wpcd_notify_user_email_addresses').val(filter_emails.join(","));
                $.each(filter_emails, function (index, value) {
                    if (!regex.test(value)) {
                        valid_email = false;
                    }
                });
                if (valid_email == false) {
                    alert(notifyparams.i10n.invalid_emails);
                    return valid_email;
                }
            }
        }


        /*
        * For validate the slack webhook URL
        * Split the string by comma if multiple slack webhooks are there and 
        * create an array of those slack webhooks to validate each webhooks
        */

        // Remove extra spaces from the slack string
        var all_slack_webhooks = wpcd_notify_slack_webhooks.replace(/ /g, '');

        if (wpcd_notify_slack_webhooks != '') {
            var valid_slack = true;

            /* 
            * Regex for slack webhoook URL validation
            * Slack webhook URL should be something like : 
            https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX
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
                jQuery('#wpcd_notify_user_slack_webhooks').val('');
            } else {
                // Check validation for the each webhook url
                jQuery('#wpcd_notify_user_slack_webhooks').val(filter_webhooks.join(","));
                $.each(filter_webhooks, function (index, value) {
                    var word_count = value.split('://').length - 1;
                    if (!slack_validate.test(value) || word_count > 1) {
                        valid_slack = false;
                    }
                });
                if (valid_slack == false) {
                    alert(notifyparams.i10n.invalid_slack);
                    return valid_slack;
                }
            }
        }


        /*
        * For validate the zapier webhook URL
        * Split the string by comma if multiple zapier webhooks are there and 
        * create an array of those zapier webhooks to validate each webhooks
        */

        var valid = true;
        var valid_check = initCheckZapierValidationBackend(wpcd_notify_zapier_webhooks, valid);

        if (!valid_check) {
            alert(notifyparams.i10n.invalid_zapier);
            return false;
        }


        // Invalid - if send to zapier is enabled and zapier webhook field is empty
        if (jQuery('#wpcd_notify_user_zapier_webhooks').val() == '' && jQuery('#wpcd_notify_user_zapier_send').is(":checked")) {
            alert(notifyparams.i10n.empty_zapier_webhook);
            return false;
        }

        return true;
    }

    // For test zapier webhook in backend.
    function initBackendZapierWebhookTest() {
        // For test zapier webhook with dummy test data.
        $('body').on('click', '#wpcd-user-zapier-webhook-test', function (e) {
            e.preventDefault();

            var enable_btn = jQuery(this);
            jQuery('.wpcd_error_msg_show').remove();

            var zapier_webhooks = jQuery('#wpcd_notify_user_zapier_webhooks').val();

            // Check validation for zapier field
            var valid = true;
            if (zapier_webhooks != '' && jQuery('#wpcd_notify_user_zapier_send').is(":checked")) {
                var validated = initCheckZapierValidationBackend(zapier_webhooks, valid);
            } else {
                alert(notifyparams.i10n.empty_zapier_webhook);
                return false;
            }

            if (!validated) {
                alert(notifyparams.i10n.invalid_zapier);
                return false;
            } else {
                var action = $(this).data('action');
                var nonce = $(this).data('nonce');

                jQuery('<div class="wpcd_error_msg_show"> ' + notifyparams.i10n.waiting_msg + '</div>').insertAfter(enable_btn);
                jQuery(this).attr('disabled', 'disabled');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: action,
                        nonce: nonce,
                        zapier_webhooks: zapier_webhooks
                    },
                    success: function (data) {
                        alert(data.data.msg);
                        enable_btn.removeAttr('disabled');
                        jQuery(".wpcd_error_msg_show").remove();
                    },
                    error: function (error) {
                        alert(error);
                        enable_btn.removeAttr('disabled');
                        jQuery(".wpcd_error_msg_show").remove();
                    }
                });
            }

        });
    }

    // Check zapier validation code.
    function initCheckZapierValidationBackend(wpcd_notify_zapier_webhooks, valid_zapier) {
        // Remove extra spaces from the zapier string
        var all_zapier_webhooks = wpcd_notify_zapier_webhooks.replace(/ /g, '');

        if (wpcd_notify_zapier_webhooks != '' && jQuery('#wpcd_notify_user_zapier_send').is(":checked")) {
            var valid_zapier = true;

            /* 
            * Regex for zapier webhoook URL validation
            * zapier webhook URL should be something like : 
            https://hooks.zapier.com/hooks/catch/123456/abcdef/
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
                jQuery('#wpcd_notify_user_zapier_webhooks').val('');
            } else {
                // Check validation for the each webhook url
                jQuery('#wpcd_notify_user_zapier_webhooks').val(filter_webhooks.join(","));
                $.each(filter_webhooks, function (index, value) {
                    var word_count = value.split('://').length - 1;
                    if (!zapier_validate.test(value) || word_count > 1) {
                        valid_zapier = false;
                    }
                });
            }
        }

        return valid_zapier;
    }

    // For hide or show the zapier webhook option
    function initCheckZapierEnabled() {
        if (jQuery('#wpcd_notify_user_zapier_send').is(":checked")) {
            jQuery('#wpcd_notify_user_zapier_webhooks').parent().parent().show();
            jQuery('#wpcd-user-zapier-webhook-test').parent().parent().show();
        } else {
            jQuery('#wpcd_notify_user_zapier_webhooks').parent().parent().hide();
            jQuery('#wpcd-user-zapier-webhook-test').parent().parent().hide();
        }
    }

})(jQuery, notifyparams);