/*
 * This JS file is loaded for  WordPress APP wp-admin APPS screen ONLY.
 * It is loaded when an app is being edited, not on the app listing screen.
*/

/* global ajaxurl */
/* global params */

(function ($, params) {

    var interval;

    $(document).ready(function () {
        init();
    });

    function initPasswordToggle() {

        // add the password toggle icon to text fields and text areas that have a class of wpcd_settings_pass_toggle
        $('.wpcd_app_pass_toggle input').after($('<span class="wpcd_app_pass_toggle_icon dashicons dashicons-visibility wpcd-not-showing"></span>'));
        $('.wpcd_app_pass_toggle textarea').after($('<span class="wpcd_app_pass_toggle_icon dashicons dashicons-visibility wpcd-not-showing"></span>'));

        // hide the passwords by default
        $('.wpcd_app_pass_toggle_icon').removeClass('dashicons-hidden').addClass('dashicons-visibility').addClass('wpcd-not-showing');
        $('.wpcd_app_pass_toggle_icon').parent().find('textarea').css('color', '#F5F5F5').css('text-decoration', 'line-through underline overline'); // text areas cannot be password fields so make the text difficult to see.

        // toggle the showing of plain text password.
        $('.wpcd_app_pass_toggle_icon').on('click', function (e) {
            e.preventDefault();
            if ($(this).hasClass('wpcd-not-showing')) {
                $(this).parent().find('input[type="password"]').attr('type', 'text');
                $(this).removeClass('dashicons-visibility').addClass('dashicons-hidden').removeClass('wpcd-not-showing');

                $(this).parent().find('textarea').css('color', 'inherit').css('text-decoration', 'none');
            } else {
                $(this).parent().find('input[type="text"]').attr('type', 'password');
                $(this).removeClass('dashicons-hidden').addClass('dashicons-visibility').addClass('wpcd-not-showing');

                $(this).parent().find('textarea').css('color', '#F5F5F5').css('text-decoration', 'line-through underline overline'); // text areas cannot be password fields so make the text difficult to see.
            }
        });

    }

    function init() {

        // Initialize password toggling functionality.
        initPasswordToggle();

        // clicking the change password select box to fetch the password.
        $('#wpcd_app_user3').on('change', function (e) {
            var user = $(this);
            $('#wpcd_app_pass3').val('');
            var $lock = $(this).parents('.rwmb-tab-panels');
            $lock.lock();

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: params.action,
                    _action: 'sftp-get-passwd',
                    nonce: params.nonce,
                    id: $(this).attr('data-wpcd-id'),
                    user: user.val(),
                },
                success: function (data) {
                    if (data.success) {
                        $('#wpcd_app_pass3').val(data.data.result);
                    } else {
                        alert(data.data.msg);
                    }
                },
                complete: function () {
                    //@TODO: This can get called before success because of things like gateway timeout errors.  Need to handle!
                    $lock.unlock();
                }
            });
        });
    }

})(jQuery, params);
