/*
 * This JS file is loaded for both the WordPress APP wp-admin SERVERS and APPS screen.
*/

/* global ajaxurl */
/* global params */

(function ($, params) {

    var interval;

    $(document).ready(function () {
        init();
    });

    function init() {

        // clicking an action
        $('body').on('click', '.wpcd_app_action input[type="checkbox"], .wpcd_app_action button', function (e) {

            var $action = $(this);
            var id = $(this).attr('data-wpcd-id');

            // if a confirmation prompt exists, show it.
            var $confirm_msg = $(this).attr('data-wpcd-confirmation-prompt');
            if (typeof $confirm_msg !== 'undefined' && $confirm_msg !== '' && !confirm($confirm_msg)) {
                e.preventDefault();
                return;
            }

            var $lock = $(this).parents('.rwmb-tab-panels');
            $lock.lock();

            // let's see if the element specifies any other fields it is dependent on for values.
            // add those values as additional params to the request
            var additional_params = [];
            var fields = $(this).attr('data-wpcd-fields');
            if (typeof fields !== 'undefined' && fields !== '') {
                $.each(JSON.parse(fields), function (index, field) {
                    additional_params.push($(field).attr('data-wpcd-name') + '=' + encodeURIComponent($(field).val()));
                });
            }

            // Var indicating whether we need to show the console
            var $show_log_console = $(this).attr('data-show-log-console');

            // Figure out what the initial console message would be if we were showing the console
            var $initial_console_message = $(this).attr('data-initial-console-message');
            if ($show_log_console && typeof $initial_console_message !== 'undefined') {
                $initial_console_message = params.i10n.loading;
            }

            // Popup the console if necessary and setup polling/callback loops
            if (typeof $show_log_console !== 'undefined') {

                // Add the console element to the body
                $('body').append($('<a class="wpcd_app_log_console" href="' + ajaxurl + '?nonce=' + params.nonce + '&_action=show-log-console&action=' + params.action + '"></a>'));
                var $a = $('body').parent().find('a.wpcd_app_log_console');
                $a.magnificPopup({
                    type: 'ajax',
                    modal: true,
                    callbacks: {
                        ajaxContentAdded: function () {
                            $('.wpcd-log-console').html($initial_console_message);
                            // hide the close button...
                            hide_console_close_button();

                            // clicking the close button on the console
                            $('.wpcd-log-close-button, .wpcd-log-close-button a, a.wpcd-log-close-button').on('click', function (e) {
                                e.preventDefault();
                                $a.magnificPopup('close');
                                location.reload();
                            });
                        }
                    }
                });
                $($a).on('click', function (e) {
                    e.preventDefault();
                });
                $a.trigger('click');
            }

            // Setup form data as necessary
            var formData = new FormData();
            formData.append('action', params.action);
            formData.append('_action', $(this).attr('data-wpcd-action'));
            formData.append('nonce', params.nonce);
            formData.append('id', id);
            formData.append('params', additional_params.join('&'));

            // Some actions may need extra data.
            switch ($(this).attr('data-wpcd-action')) {
                case 'sftp-set-key':
                    // certain data from the sFTP tab in the site detail screen
                    formData.append('file', $('.wpcd_app_setkey input[type="file"]')[0].files[0]);
                    break;
                case 'site-user-set-key':
                    // certain data from the SITE / SYSTEM USERS tab in the site detail screen
                    formData.append('file', $('.wpcd_app_site_user_setkey input[type="file"]')[0].files[0]);
                    break;
                case 'server-ssh-keys-save':
                    // certain data from the server detail KEYS tab needs to be sent in individual array items because php parse_args will mangle them.
                    formData.append('ssh-private-key', $('#wpcd_app_action_server-ssh-keys-private-key')[0].value);
                    formData.append('ssh-public-key', $('#wpcd_app_action_server-ssh-keys-public-key')[0].value);
                    formData.append('ssh-private-key-password', $('#wpcd_app_action_server-ssh-keys-private-key-password')[0].value);
                    break;
            }

            $.ajax({
                url: ajaxurl,
                timeout: 120000,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (data) {
                    if (!data.success) {
                        // throw an alert when an unforeseen error occurs.
                        alert(data.data.msg);
                        // And refresh the page so that any temporary state changes that the user might see are gone (eg: from switches and check-boxes)
                        location.reload();
                    } else if (data.data && data.data.result && data.data.result.redirect && data.data.result.redirect === 'yes') {
                        // redirect if required.
                        if (data.data.result.msg) {
                            alert(data.data.result.msg);
                        }
                        location.href = params.redirect;
                    } else if (data.data && data.data.result && data.data.result.refresh && data.data.result.refresh === 'yes') {
                        // Refresh if required.
                        // But first, show message if one exists.
                        if (data.data.result.msg) {
                            alert(data.data.result.msg);
                        }
                        // Or, if it's a file that needs to be downloaded then do so.
                        // Note that we're assuming a TEXT file here, not a .zip file
                        // or similar.
                        if (data.data.result.file_url) {
                            var fileName = data.data.result.file_name;
                            var fileData = data.data.result.file_data;

                            // Create element for download txt file
                            var element = document.createElement('a');
                            element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(fileData));
                            element.setAttribute('download', fileName);

                            // Hide created element
                            element.style.display = 'none';
                            document.body.appendChild(element);

                            // Trigger click on created element
                            element.click();

                            // Remove created element
                            document.body.removeChild(element);
                        }
                        // @TODO: does not go back to the tab it was on.
                        // Actually do the refresh.
                        location.reload();
                    } else if (data.data && data.data.result && data.data.result.async && data.data.result.async === 'yes') {
                        // long running commands.
                        var $object = { post_id: id, command: { name: data.data.result.command } };
                        interval = setInterval(function () {
                            $currentConsole = $('.wpcd-log-console');  // get reference to the console window
                            $currentConsoleHTML = $currentConsole.html(); // get the current html text inside the console.
                            $('.wpcd-log-console').html($currentConsoleHTML + '<p>' + params.l10n.checking_for_logs + '</p>');	// add message to window showing user that we're checking for logs
                            fetchLogs($object, true);
                        }, params.refresh_seconds * 1000);  // params.refresh_seconds is likely set to 30 seconds - server cron is 60 seconds so no point in polling too much sooner than 60 seconds
                    } else if (data.data && data.data.result && data.data.result.email_fields) {
                        var app_action_prefix = 'wpcd_app_action_email-gateway';
                        $('#' + app_action_prefix + '-smtp-server').val(data.data.result.email_fields.smtp_server);
                        $('#' + app_action_prefix + '-smtp-user').val(data.data.result.email_fields.smtp_user);
                        $('#' + app_action_prefix + '-smtp-password').val(data.data.result.email_fields.smtp_pass);
                        $('#' + app_action_prefix + '-smtp-domain').val(data.data.result.email_fields.domain);
                        $('#' + app_action_prefix + '-smtp-note').val(data.data.result.email_fields.note);
                        $('#' + app_action_prefix + '-smtp-hostname').val(data.data.result.email_fields.hostname1);

                        if (data.data.result.msg) {
                            alert(data.data.result.msg);
                        }
                    }
                },
                complete: function (event, xhr, settings) {
                    //@TODO: This can get called before success because of things like gateway timeout errors.  Need to handle!
                    $lock.unlock();
                },
                error: function (event, xhr, settings, thrownError) {
                    alert('AJAX Error - something went wrong but we cannot tell you what it was.  Its a bummer and illogical I know.  Most likely its a 504 gateway timeout error.  Increase the time your server allows for a script to run to maybe 300 seconds. In the meantime you can check the SSH LOG or COMMAND LOG screens to see if more data was logged there.');
                    // show the close button...
                    show_console_close_button();
                }
            });
        });
        // End clicking an action

    }

    // fetching logs.
    // cycle: boolean, which indicates if this function is being called repeatedly using setInterval
    function fetchLogs(result, cycle) {
        var is_old = typeof result.old !== 'undefined' ? result.old : false;
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: params.action,
                _action: 'fetch-logs-from-db',
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
                        clearInterval(interval);
                        interval = null;
                        if (!is_old) {
                            // Show completed message...
                            alert(params.l10n.done);
                        }
                        // show the close button...
                        show_console_close_button();
                    }
                    if (data.data.result.logs) {
                        $('.wpcd-log-console').html(data.data.result.logs);
                    } else {
                        $('.wpcd-log-console').html($('.wpcd-log-console').html() + '<p>' + params.l10n.no_progress_data_in_logs + '</p>');
                    }
                    // auto scroll to the end.
                    $('.wpcd-log-console').scrollTop($('.wpcd-log-console')[0].scrollHeight);
                }
            },
            error: function (event, xhr, settings, thrownError) {
                console.log('error fetching logs...');
                console.log(event);
                console.log(thrownError);
                console.log(settings);
                console.log(xhr);
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

})(jQuery, params);


// show/hide the spinner
(function ($) {
    $.fn.lock = function () {
        $(this).each(function () {
            var $this = $(this);
            var position = $this.css('position');

            if (!position) {
                position = 'static';
            }

            switch (position) {
                case 'absolute':
                case 'relative':
                    break;
                default:
                    $this.css('position', 'relative');
                    break;
            }
            $this.data('position', position);

            var width = $this.width(),
                height = $this.height();

            var locker = $('<div class="locker"></div>');
            locker.width(width).height(height);

            var loader = $('<div class="locker-loader"></div>');
            loader.width(width).height(height);

            locker.append(loader);
            $this.append(locker);
            $(window).resize(function () {
                $this.find('.locker,.locker-loader').width($this.width()).height($this.height());
            });
        });

        return $(this);
    };

    $.fn.unlock = function () {
        $(this).each(function () {
            $(this).find('.locker').remove();
            $(this).css('position', $(this).data('position'));
        });

        return $(this);
    };
})(jQuery);
