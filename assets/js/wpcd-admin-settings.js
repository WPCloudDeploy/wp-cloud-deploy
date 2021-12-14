/*
 * This JS file is loaded for the WPCD settings screen.
 */

(function($) {

    var interval;

    $(document).ready(function() {
        init();
    });

    // for toggle password text
    function initPasswordToggle() {
        // add the password toggle icon to text fields and text areas that have a class of wpcd_settings_pass_toggle
        $('.wpcd_settings_pass_toggle input').after($('<span class="wpcd_settings_pass_toggle_icon dashicons dashicons-visibility wpcd-not-showing"></span>'));
        $('.wpcd_settings_pass_toggle textarea').after($('<span class="wpcd_settings_pass_toggle_icon dashicons dashicons-visibility wpcd-not-showing"></span>'));

        // hide the passwords by default
        $('.wpcd_settings_pass_toggle_icon').parent().find('input[type="text"]').attr('type', 'password');
        $('.wpcd_settings_pass_toggle_icon').parent().find('textarea').css('color', '#F5F5F5').css('text-decoration', 'line-through underline overline'); // text areas cannot be password fields so make the text difficult to see.
        $('.wpcd_settings_pass_toggle_icon').removeClass('dashicons-hidden').addClass('dashicons-visibility').addClass('wpcd-not-showing');

        // toggle the showing of plain text password.
        $('.wpcd_settings_pass_toggle_icon').on('click', function(e) {
            e.preventDefault();
            if ($(this).hasClass('wpcd-not-showing')) {
                // show data in text and text area fields
                $(this).parent().find('input[type="password"]').attr('type', 'text');
                $(this).removeClass('dashicons-visibility').addClass('dashicons-hidden').removeClass('wpcd-not-showing');

                $(this).parent().find('textarea').css('color', 'inherit').css('text-decoration', 'none');
            } else {
                // hide the data in text and text area fields
                $(this).parent().find('input[type="text"]').attr('type', 'password');
                $(this).removeClass('dashicons-hidden').addClass('dashicons-visibility').addClass('wpcd-not-showing');

                $(this).parent().find('textarea').css('color', '#F5F5F5').css('text-decoration', 'line-through underline overline'); // text areas cannot be password fields so make the text difficult to see.
            }
        });
    }

    // for cleaning up apps - triggered from the SETTINGS->TOOLS->CLEAN UP APPS button.
    function initCleanUpApps() {
        $('body').on('click', '#wpcd-cleanup-apps', function(e) {
            e.preventDefault();

            var action = $(this).data('action');
            var nonce = $(this).data('nonce');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: action,
                    nonce: nonce
                },
                success: function(data) {
                    alert(data.data.msg);
                    location.reload();
                }
            });

        });
    }

    // for cleaning up servers - triggered from the SETTINGS->TOOLS->CLEAN UP SERVERS button
    function initCleanUpServers() {
        $('body').on('click', '#wpcd-cleanup-servers', function(e) {
            e.preventDefault();

            var action = $(this).data('action');
            var nonce = $(this).data('nonce');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: action,
                    nonce: nonce
                },
                success: function(data) {
                    alert(data.data.msg);
                    location.reload();
                }
            });

        });
    }

    // for clearing provider cache
    function initClearProviderCache() {
        $('body').on('click', '.wpcd-provider-clear-cache', function(e) {
            e.preventDefault();

            var action = $(this).data('action');
            var nonce = $(this).data('nonce');
            var provider = $(this).data('provider');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: action,
                    nonce: nonce,
                    provider: provider
                },
                success: function(data) {
                    alert(data.data.msg);
                    location.reload();
                }
            });

        });
    }

    // Checking for WPCD updates - triggered from the SETTINGS->LICENSE AND UPDATES->CHECK FOR UPDATES button
    // Validate licenses - triggered from the SETTINGS->LICENSE AND UPDATES->VALIDATE LICENSES button
    function initCheckUpdatesValidateLicenses() {
        $('body').on('click', '#wpcd-check-for-updates, #wpcd-validate-licenses', function(e) {
            e.preventDefault();

            var current_btn = $(this);
            var action = $(this).data('action');
            var nonce = $(this).data('nonce');
            var loading_msg = $(this).data('loading_msg');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: action,
                    nonce: nonce
                },
                beforeSend: function() {
                    current_btn.prop('disabled', true);
                    $("<div class='wpcd_btn_loading_msg'>" + loading_msg + "</p>").insertAfter(current_btn);
                },
                success: function(data) {
                    location.reload();
                }
            });

        });
    }


    function init() {
        initPasswordToggle();
        initCleanUpApps();
        initCleanUpServers();
        initClearProviderCache();
        initCheckUpdatesValidateLicenses();
    }

})(jQuery);