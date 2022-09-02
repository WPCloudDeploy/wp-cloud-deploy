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

        // Use Copy Clipboard when connection unsecure.
        function unsecuredCopyToClipboard(text) {
            const input = document.createElement("input");
            input.setAttribute("type", "text");
            input.value = text;
            document.body.appendChild(input);
            // input.focus();
            input.select();
            try {
                document.execCommand('copy');
            } catch (err) {
                console.error('Unable to copy to clipboard', err);
            }
            document.body.removeChild(input);
        }

        // Click to copy functionality.
        $('body').on('click', '.wpcd-click-to-copy', function(e) {
            e.preventDefault();

            console.log(e);
            /* Get the IP field */
            var copyText = $(this).find('.wpcd-click-to-copy-text');

            /* Copy the IP inside the IP field */
            var IP = copyText.text();

            if (window.isSecureContext && navigator.clipboard) {
                navigator.clipboard.writeText(IP);
            } else {
                unsecuredCopyToClipboard(IP);
            }
            /* Alert the copied IP */
            var $copiedElement = $("<span>");
            $copiedElement.addClass('wpcd-copied').text($(this).find('.wpcd-click-to-copy-label').data('label'));
            $(this).append($copiedElement);
            $(this).find('.wpcd-click-to-copy-label').addClass('wpcd-copy-hidden');
            $copiedElement.fadeIn(100);
            $copiedElement.fadeOut(2000);
            setTimeout(function() { $($copiedElement).remove() }, 3000);
        });

        // Show copy text on mouse hover.

        $(".wpcd-click-to-copy").hover(
            function() {
                $(this).children(".wpcd-click-to-copy-label").removeClass("wpcd-copy-hidden");
            },
            function() {

                $(this).children(".wpcd-click-to-copy-label").addClass('wpcd-copy-hidden');
            }
        );
    }

})(jQuery, readableCheck);