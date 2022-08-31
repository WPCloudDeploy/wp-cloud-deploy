/*
 * This JS file is loaded for WordPress admin.
 */

/* global ajaxurl */
/* global readableCheck */

(function($, readableCheck) {

    $(document).ready(function() {
        init();
    });

    function init() {

        // Dismiss the admin notice that shows up when the bash script folded isn't readable by outside http calls.
        $('body').on('click', '.wpcd-readability-check .notice-dismiss', function(e) {
            e.preventDefault();
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: readableCheck.action,
                    nonce: readableCheck.nonce,
                },
                success: function(data) {
                    location.reload();
                }
            });
        });

        // Recheck to see if the bash scripts folded can be read by outside http calls.
        // This is triggered when the user clicks a button on the notice that shows up when the bash script folded isn't readable by outside http calls.
        $('body').on('click', '.wpcd-readability-check #wpcd-check-again', function(e) {
            e.preventDefault();
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: readableCheck.check_again_action,
                    nonce: readableCheck.nonce,
                },
                success: function(data) {
                    alert(data.data.message);
                    location.reload();
                }
            });
        });

        // Dismiss the admin notice that shows up when the crons aren't scheduled and loaded.
        $('body').on('click', '.wpcd-cron-check .notice-dismiss', function(e) {
            e.preventDefault();
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: readableCheck.cron_check_action,
                    nonce: readableCheck.nonce,
                },
                success: function(data) {
                    location.reload();
                }
            });
        });

        // Dismiss the admin notice that shows up when the PHP version does not match the condition.
        $('body').on('click', '.wpcd-php-version-check .notice-dismiss', function(e) {
            e.preventDefault();
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: readableCheck.php_version_check_action,
                    nonce: readableCheck.nonce,
                },
                success: function(data) {
                    location.reload();
                }
            });
        });

        // Dismiss the admin notice that shows up when the plugin is on a localhost server.
        $('body').on('click', '.wpcd-localhost-check .notice-dismiss', function(e) {
            e.preventDefault();
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: readableCheck.localhost_check_action,
                    nonce: readableCheck.nonce,
                },
                success: function(data) {
                    location.reload();
                }
            });
        });

        // Click to copy functionality.
        $('body').on('click', '.click-to-copy-label', function(e) {
            e.preventDefault();

            /* Get the IP field */
            var copyText = $(this).closest('span').prev('.click-to-copy-text').addClass('DestQID');

            /* Copy the IP inside the IP field */
            var IP = copyText.html().replace(/<br\s*\/?>/gi, ' ');

            navigator.clipboard.writeText(IP);

            /* Alert the copied IP */
            var $copiedElement = $("<span>");
            $copiedElement.addClass('copied').text($(this).data('label'));
            $(this).parent('div').append($copiedElement);
            $(this).addClass('copy-hidden');
            $copiedElement.fadeIn(100);
            $copiedElement.fadeOut(2000);
            setTimeout(function() { $($copiedElement).remove() }, 3000);
        });

        // Show copy text on mouse hover.

        $(".click-to-copy").hover(
            function() {
                $(this).children(".click-to-copy-label").removeClass("copy-hidden");
            },
            function() {

                $(this).children(".click-to-copy-label").addClass('copy-hidden');
            }
        );
    }

})(jQuery, readableCheck);