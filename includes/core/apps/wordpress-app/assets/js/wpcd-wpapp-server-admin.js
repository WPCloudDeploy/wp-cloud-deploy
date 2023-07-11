/* global ajaxurl */
/* global params */

(function ($, params) {

    $(document).ready(function () {
        init();
    });

    /**
     * Variable used to control a timer
     */
    var interval;

    /**
     * Instantiate more JS functions 
     */
    function init() {
        // Call the function that sets up the JS scripts for creating or provisioning a new server
        initCreate();

        // Call the function that sets up the JS scripts for updating the remote state of the server in the wp-admin UI.
        get_remote_state();

        // Call the function that sets up some clicks on the wp-admin ui list/screen for servers
        initActions();
    }

    /*
     * Setup JS scripts to handle (mostly) click actions on the UI in the server list cpt admin screen.
     */
    function initActions() {
        $('.wpcd_action_show_logs').magnificPopup({
            type: 'ajax',
            modal: true,
            callbacks: {
                ajaxContentAdded: function () {
                    $('.wpcd-log-console').html(params.i10n.loading);
                    $clicked = $.magnificPopup.instance.st.el;
                    var $object = { post_id: $($clicked).attr('data-wpcd-id'), command: { name: $($clicked).attr('data-wpcd-name') } };
                    interval = setInterval(function () {
                        fetchLogs($object, true);
                    }, params.refresh_seconds * 1000);
                }
            }
        });
        $('.wpcd_action_show_logs').attr('href', ajaxurl + '?nonce=' + params.nonce + '&_action=log-console&action=' + params.action);
        $('.wpcd_action_show_logs').on('click', function (e) {
            e.preventDefault();
        });

        // Confirm alert display when perfoming a bulk action on the server list screen.
        // This code is mostly a duplicate of that in wpcd-wpapp-admin-post-type-wpcd-app.js.
        // If it changes here it might need to be changed there as well.        
        $('#doaction').click(function () {
            var selected_action = $("#bulk-action-selector-top option:selected").val();
            var valid_actions = ['wpcd_install_callbacks', 'wpcd_apply_all_linux_updates', 'wpcd_apply_security_linux_updates', 'wpcd_soft_reboot'];
            if ( valid_actions.indexOf(selected_action) >= 0) {
                var confirm_res = confirm(params.bulk_actions_confirm);
                if (!confirm_res) {
                    return false;
                }
            }
        });

        // when an option is selected, we trigger a click on the empty anchor tag so that the pop-up is fired.
        $('select.wpcd_action_show_old_logs').on('change', function () {
            $clicked = $(this).find('option:selected');

            // first option, ignore!
            if ($clicked.val() === '') {
                return;
            }
            var prependTo = null;
            if( typeof wpcd_wpapp_params != 'undefined' && wpcd_wpapp_params.is_public ) {
                prependTo = $('#wpcd_public_wrapper');
            }

            $('a.wpcd_action_show_old_logs').magnificPopup({
                type: 'ajax',
                modal: true,
                prependTo : prependTo,
                callbacks: {
                    ajaxContentAdded: function () {
                        $('.wpcd-log-console').html(params.i10n.loading);
                        var $object = { post_id: $($clicked).attr('data-wpcd-id'), command: { name: $($clicked).attr('data-wpcd-name') }, old: true };
                        fetchLogs($object, false);

                        // clicking the close button on the console 
                        $('.wpcd-log-close-button, .wpcd-log-close-button a, a.wpcd-log-close-button').on('click', function (e) {
                            e.preventDefault();
                            $.magnificPopup.close();
                            location.reload();
                        });

                    }
                }
            });
            $('a.wpcd_action_show_old_logs').trigger('click');
        });
        $('a.wpcd_action_show_old_logs').attr('href', ajaxurl + '?nonce=' + params.nonce + '&_action=log-console&action=' + params.action);
        $('a.wpcd_action_show_old_logs').on('click', function (e) {
            e.preventDefault();
        });

        // Delete server records action.
        $('body').on('click', '.wpcd_action_delete_server_record', function (e) {

            e.preventDefault();

            if (confirm(params.delete_server_record_prompt)) {
                // Setup form data as necessary
                var id = $(this).attr('data-wpcd-id');
                var formData = new FormData();
                formData.append('action', params.action);
                formData.append('_action', params._action);
                formData.append('nonce', params.nonce);
                formData.append('server_id', id);
                formData.append('params', '');

                var is_public = $('#wpcd_public_wrapper').length == 1;

                // Used for the 'spinner'
                var $lock = is_public ? $('body') : $(this).parents('#wpbody-content');
                $lock.lock();

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (data) {
                        if (!data.data.result.done) {
                           // throw an alert when an unforseen error occurs.
                           alert(data.data.result.status);
                           location.reload();
                        } else if (data.data.result.done) {
                            alert(data.data.result.status); 
                            location.reload();
                        }
                    },
                    complete: function () {
                        // An action that threw up the spinner lock screen is complete so unlock it.
                        $lock.unlock();
                    },
                    error: function (event, xhr, settings, thrownError) {
                        alert('AJAX Error - something went wrong but we cannot tell you what it was.  Its a bummer and illogical I know.  Most likely its a 504 gateway timeout error.  Increase the time your server allows for a script to run to maybe 300 seconds. In the meantime you can check the SSH LOG or COMMAND LOG screens to see if more data was logged there.');
                    }
                });
            }
        });

        /**
         * Setup script used for installing a new WordPress app.
         */
        initInstallApp();
    }


    /*
     * Provision a new server
     */
    function initCreate() {
        clearInterval(interval);

        if (params.user_can_provision_servers) {
            // add a custom add new button for provisioning servers
            $('.wp-heading-inline').append($('<a href="' + ajaxurl + '?nonce=' + params.nonce + '&_action=create-popup&action=' + params.action + '" class="wpcd-server-install page-title-action">' + params.l10n.add_new + '</a>'));
            $('a.wpcd-server-install.page-title-action').magnificPopup({
                type: 'ajax',
                modal: true
            });
            $('a.wpcd-server-install.page-title-action').on('click', function (e) {
                e.preventDefault();
            });
        }

        // clicking the close button on the console before installation has started.
        $('body').on('click', '.wpcd-log-close-button, .wpcd-log-close-button a, a.wpcd-log-close-button', function (e) {
            e.preventDefault();
            $.magnificPopup.close();
            //location.reload();
            if( wpcd_wpapp_params.is_public ) {
                    window.location.href = wpcd_wpapp_params.servers_list_page_url;
            } else {
                    location.reload();
            }
        });

        // clicking the install button.
        $('body').on('click', '.wpcd-install-server', function (e) {
            e.preventDefault();

            // Prevent certain chars from being used in fields
            var other_specialChars = "'\";\\|<>`@$&()/";
            var server_name = jQuery('.wpcd_server_name').val();
            var valid_server = check_field_value_chars(other_specialChars, server_name);

            if (valid_server == false) {
                $(".wp_server_name_error_msg").hide();
            } else {
                $(".wp_server_name_error_msg").show();
                return false;
            }

            // Additional validation on the server name
            var validated = initValidation();

            if (!validated) {
                // do nothing
            } else {
                var $lock = $('.wpcd-log-wrap');
                $lock.lock();

                // hide the close button
                hide_console_close_button();

                // hide the data entry fields & slide the 'console' over to the center.
                $('.wpcd-install-server').attr('disabled', 'disabled');
                $('.wpcd-create-popup-fields-wrap').hide(600);
                $('.wpcd-create-popup-console-wrap').css('float', 'none');
                $('.wpcd-create-popup-console-wrap').closest('.wpcd-install-popup-container').addClass('wpcd-install-app-container-show-log-console');

                // set a variable that will count how many times we ask for logs and get nothing back...
                let log_request_count = 0;

                $.ajax({
                    url: ajaxurl,
                    data: {
                        action: params.action,
                        _action: 'create',
                        nonce: params.nonce,
                        invalid_message: params.i10n.invalid_server_name,
                        params: $('form#wpcd-install').serialize()
                    },
                    success: function (data) {
                        $('.wpcd-log-console').html(data.data.msg);
                        if (data.success) {
                            interval = setInterval(function () {
                                log_request_count++;
                                fetchLogs(data.data.result, null, log_request_count, params.i10n.taking_too_long);
                            }, params.refresh_seconds * 1000);
                        } else {
                            alert(data.data.msg);
                        }
                    },
                    complete: function () {
                        $lock.unlock();
                    }
                });

                // clicking the close button on the console after action is completed.
                $('.wpcd-log-close-button, .wpcd-log-close-button a, a.wpcd-log-close-button').on('click', function (e) {
                    e.preventDefault();
                    $.magnificPopup.close();
                    location.reload();
                });
            }



        });

    }

    /*
     * Provision a new server - validation for the server name
     */
    function initValidation() {
        var name = document.querySelector('.wpcd_server_name').value;
        var provider = document.querySelector('.wpcd_app_provider').value;
        var regex = /^[a-z0-9-_]+$/ig;

        /* Some providers require additional chars to be allowed - primarily HIVELOCITY which requires periods. */
        if (provider.includes('hivelocity')) {
            regex = /^[a-z0-9-_.]+$/ig;
        }

        if (name != '' && !regex.test(name)) {
            alert(params.i10n.invalid_server_name);
            return false;
        } else {
            return true;
        }
    }

    /*
     * Install a new app - right now the only option is a WordPress App
     */
    function initInstallApp() {
        clearInterval(interval);

        var prependTo = null;
        if( typeof wpcd_wpapp_params != 'undefined' && wpcd_wpapp_params.is_public ) {
                prependTo = $('#wpcd_public_wrapper');
        }
        
        $('a.wpcd_action_install_app, button.wpcd_action_install_app').magnificPopup({
            type: 'ajax',
            modal: true,
            prependTo : prependTo,
            callbacks: {
                ajaxContentAdded: function () {
                    $('#wpcd-wp-version').select2({
                        tags: true,
                        multiple: false,
                        dropdownParent: $("#wpcd-install")
                    });

                    // Disable the button on Popup shown
                    $('#wpcd-install button.wpcd-install-app').prop('disabled', true);

                    // Validate the fields
                    $('form#wpcd-install input').keyup(function () {

                        var error = false;
                        var specialChars = ";|'\"\\<>`&()";
                        var other_specialChars = "'\";\\|<>`@$&#^%!()/";
						var domain_IllegalChars = "'\";\\|<>`@$&_#^%!()/";
                        $('form#wpcd-install input').each(function () {

                            // validate domain field - make sure it has at least one period in it.
                            if ($(this).val() != '' && $(this).attr('name') == 'wp_domain') {
                                var domain = $(this).val();
                                var $regexname = /[a-z0-9]\.[a-z0-9]{1,3}/i;
                                if (!domain.match($regexname)) {
                                    error = true;
                                    $(this).next('span.wpcd-domain-error').css('display', 'block');
                                    $(this).css('margin-bottom', '0');
                                } else {
                                    error = false;
                                    $(this).next('span.wpcd-domain-error').css('display', 'none');
                                    $(this).css('margin-bottom', '');
                                }

                                // Make sure that domain field doesn't contain illegal chars
                                var check_domain = check_field_value_chars(domain_IllegalChars, domain);
                                if (check_domain == false) {
                                    error = false;
                                    $(".wp_domain_error_msg").hide();
                                } else {
                                    $(".wp_domain_error_msg").show();
                                    error = true;
                                }
                            } else if ($(this).val() != '' && $(this).attr('name') == 'wp_password') {
                                // Make sure that the password field doesn't contain illegal chars
                                var wp_password = $(this).val();
                                var check_password = check_field_value_chars(specialChars, wp_password);

                                if (check_password == false) {
                                    error = false;
                                    $(".wp_password_error_msg").hide();
                                } else {
                                    $(".wp_password_error_msg").show();
                                    error = true;
                                }
                            } else if ($(this).val() != '' && $(this).attr('name') == 'wp_user') {
                                // Make sure that the user field doesn't contain illegal chars
                                var wp_user = $(this).val();
                                var check_username = check_field_value_chars(other_specialChars, wp_user);

                                if (check_username == false) {
                                    error = false;
                                    $(".wp_username_error_msg").hide();
                                } else {
                                    $(".wp_username_error_msg").show();
                                    error = true;
                                }
                            } else if ($(this).val() != '' && $(this).attr('name') == 'wp_email') {
                                // make sure email is of valid format
                                var wp_email = $(this).val();
                                var valid_email = check_is_email(wp_email);

                                if (valid_email == true) {
                                    error = false;
                                    $(".wp_check_email_error_msg").hide();
                                } else {
                                    $(".wp_check_email_error_msg").show();
                                    error = true;
                                }

                                // Make sure that email field doesn't contain illegal chars
                                var check_email = check_field_value_chars(specialChars, wp_email);
                                if (check_email == false) {
                                    error = false;
                                    $(".wp_email_error_msg").hide();
                                } else {
                                    $(".wp_email_error_msg").show();
                                    error = true;
                                }
                            } else if ($(this).val() == '') {
                                error = true;
                            }
                        });


                        if (error) {
                            $('#wpcd-install button.wpcd-install-app').prop('disabled', true);
                        } else {
                            $('#wpcd-install button.wpcd-install-app').prop('disabled', false);
                        }
                    });
                    // end validate fields
                }
            }
        });

        $('a.wpcd_action_install_app, button.wpcd_action_install_app').each(function (index) {
            $(this).attr('href', ajaxurl + '?nonce=' + params.nonce + '&_action=install-app-popup&action=' + params.action + '&server_id=' + $(this).attr('data-wpcd-id'));
        });

        $('a.wpcd_action_install_app, button.wpcd_action_install_app').on('click', function (e) {
            e.preventDefault();
        });

        // clicking the close button on the console before installation has started.
        $('body').on('click', '.wpcd-log-close-button, .wpcd-log-close-button a, a.wpcd-log-close-button', function (e) {
            e.preventDefault();
            $.magnificPopup.close();
            //location.reload();
            if( wpcd_wpapp_params.is_public ) {
                    window.location.href = wpcd_wpapp_params.servers_list_page_url;
            } else {
                    location.reload();
            }
        });

        // clicking the install button.
        $('body').on('click', '.wpcd-install-app', function (e) {
            e.preventDefault();

            var version_value = $.trim($('#wpcd-wp-version').val());
            var valid_version = check_wp_version(version_value);
            if (!valid_version) {
                alert(params.i10n.invalid_version);
                return false;
            } else {

                // Hide the close button.
                hide_console_close_button();
                var $clicked = $.magnificPopup.instance.st.el;

                // hide the data entry fields & slide the 'console' over to the center.
                $('.wpcd-install-app').attr('disabled', 'disabled');
                $('.wpcd-create-popup-fields-wrap').hide(600);
                $('.wpcd-create-popup-console-wrap').css('float', 'none');
                $('.wpcd-create-popup-console-wrap').closest('.wpcd-install-popup-container').addClass('wpcd-install-app-container-show-log-console');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: params.action,
                        _action: 'install-app',
                        nonce: params.nonce,
                        id: $($clicked).attr('data-wpcd-id'),
                        params: $('form#wpcd-install').serialize()
                    },
                    success: function (data) {
                        $('.wpcd-log-console').html(data.data.msg);
                        if (data.success) {
                            interval = setInterval(function () {
                                fetchLogs(data.data.result, true);
                            }, params.refresh_seconds * 1000);
                        }
                    }
                });
            }

            // clicking the close button on the console after action is completed.
            $('.wpcd-log-close-button, .wpcd-log-close-button a, a.wpcd-log-close-button').on('click', function (e) {
                e.preventDefault();
                $.magnificPopup.close();
                location.reload();
            });

        });
    }

    // fetching logs.
    // result: data object passed in from the controlling process - usually an output of an ajax call.
    // cycle: boolean, which indicates if this function is being called repeatedly using setInterval
    // requestcount: the number of times fetchlogs has been called by the controlling process.
    // taking_too_long_message: a message to show if the request_count exceeds a particular number
    function fetchLogs(result, cycle, request_count, taking_too_long_message) {
        var is_old = typeof result.old !== 'undefined' ? result.old : false;
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: params.action,
                _action: 'logs',
                nonce: params.nonce,
                params: {
                    id: result.post_id,
                    name: result.command.name,
                    old: is_old
                }
            },
            success: function (data) {
                // if the interval does not exist, which means it has been cleared
                // then ignore any further callbacks
                // this happens when a request takes too long and many requests end up in pending state
                if (cycle && !interval) {
                    return;
                }
                if (!data.success) {
                    $('.wpcd-log-console').html(data.data.msg);
                } else if (data.data.result) {
                    if (data.data.result.done) {
                        if (!is_old) {
                            alert(params.l10n.done);
                        }
                        clearInterval(interval);
                        interval = null;
                        show_console_close_button();
                    }
                    $('.wpcd-log-console').html(data.data.result.logs);
                    // auto scroll to the end.
                    $('.wpcd-log-console').scrollTop($('.wpcd-log-console')[0].scrollHeight);
                } else if (request_count >= 12) {
                    // stuff taking a while so depending on how long, lets throw up a message...
                    current_message = $('.wpcd-log-console').html();
                    switch (request_count) {
                        case 12:
                            $('.wpcd-log-console').html(current_message + params.i10n.server_install_feedback_1);
                            break;
                        case 24:
                            $('.wpcd-log-console').html(current_message + "<br />" + params.i10n.server_install_feedback_2);
                            break
                        case 36:
                            $('.wpcd-log-console').html(current_message + "<br />" + params.i10n.server_install_feedback_3);
                            break
                        case 48:
                            $('.wpcd-log-console').html(current_message + "<br />" + params.i10n.server_install_feedback_4);
                            break
                        case 60:
                            $('.wpcd-log-console').html(current_message + "<br />" + params.i10n.server_install_feedback_5 + "<br />" + "█");
                            break
                    }
                    if (request_count > 61) {
                        $('.wpcd-log-console').html(current_message + '<span style="word-break:break-all;word-wrap:break-word;">' + "█" + "</span>");
                        // auto scroll to the end.
                        $('.wpcd-log-console').scrollTop($('.wpcd-log-console')[0].scrollHeight);
                    }

                    // stuff really taking too long, likely because there's a login or api issue.
                    // since we're polling at 5 second intervals,240 is the equivalent of 20 minutes
                    if (request_count >= 240) {
                        $('.wpcd-log-console').html(taking_too_long_message);
                        $('.wpcd-log-console').html(taking_too_long_message + "<br />" + '<span style="word-break:break-all;word-wrap:break-word;">' + "█".repeat((request_count - 240) / 12) + "</span>");
                        // auto scroll to the end.
                        $('.wpcd-log-console').scrollTop($('.wpcd-log-console')[0].scrollHeight);
                        // show the button to close the window
                        show_console_close_button();
                    }
                }
            }
        });

    }

    // hide the close button on the terminal/console
    function hide_console_close_button() {
        $('.wpcd-log-close-wrap').hide();
    }

    // show the close button on the terminal/console
    function show_console_close_button() {
        $('.wpcd-log-close-wrap').show();
    }

    // Send a request to update the remote state of the server
    function get_remote_state() {
        $('body').on('click', '.wpcd-update-server-status', function (e) {
            e.preventDefault();
            var id = $(this).attr('data-wpcd-id');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: params.action,
                    _action: 'update-status',
                    nonce: params.nonce,
                    params: {
                        id: id
                    }
                },
                success: function (data) {
                    if (data.success) {
                        alert(data.data.msg);
                        location.reload();
                    } else {
                        alert(data.data.msg);
                    }
                }
            });

        });
    }

    // Checks the WordPress version is name or number is valid or not
    function check_wp_version(value) {

        if (value == 'latest') {
            return true;
        }

        if (/^\d{1,2}\.\d{1,2}\.\d{0,2}$/.test(value)) {
            return true;
        } else {
            return false;
        }
    }


    // Checks the field value contains some special characters or not
    function check_field_value_chars(specialChars, field_value) {
        for (i = 0; i < specialChars.length; i++) {
            if (field_value.indexOf(specialChars[i]) > -1) {
                return true;
            }
        }

        if (/\s/.test(field_value) || /^\s+$/.test(field_value) || field_value.indexOf('/s') > -1 ||
            field_value.indexOf('\r') > -1 || field_value.indexOf('\n') > -1 ||
            field_value.indexOf('\t') > -1 || field_value.indexOf('/gm') > -1) {
            return true;
        }
        return false;
    }

    //	Checks to make sure that email is in a valid form
    function check_is_email(email) {
        var regex = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
        if (!regex.test(email)) {
            return false;
        } else {
            return true;
        }
    }

})(jQuery, params);