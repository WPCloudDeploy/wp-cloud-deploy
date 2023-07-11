/*
 * This JS file is loaded for the WordPress APP wp-admin APPS listing screen screen.
 * It currently handles the following actions:
 * - Adds an INSTALL WORDPRESS button to the top of the apps listStyleType
 * - Calls an AJAX action to remove a WordPress site using a link on the apps list.
 */

/* global ajaxurl */
/* global params */

(function($, params) {

    $(document).ready(function() {
        init();
    });

    function init() {

        // add a custom INSTALL WORDPRESS button
        if (params.user_can_manage_servers) {
            $('.wp-heading-inline').append($('<a href="' + params.install_wpapp_url + '" class="wpcd-wp-install page-title-action" target="_blank">' + params.i10n.install_wpapp + '</a>'));
        }

        // Remove A WordPress Site.
        $('body').on('click', '.wpcd_action_remove_site', function(e) {

            e.preventDefault();

            var id = $(this).attr('data-wpcd-id');

            if (confirm(params.i10n.remove_site_prompt)) {
                // Setup form data as necessary
                var formData = new FormData();
                formData.append('action', params.action);
                formData.append('_action', params._action);
                formData.append('nonce', params.nonce);
                formData.append('id', id);
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
                    success: function(data) {
                        if (!data.success) {
                            // throw an alert when an unforseen error occurs.
                            alert(data.data.msg);
                            // And refresh the page so that any temporary state changes that the user might see are gone (eg: from switches and checkboxes)
                            location.reload();
                        } else if (data.data && data.data.result && data.data.result.redirect && data.data.result.redirect === 'yes') {
                            // redirect if required.
                            if (data.data.result.msg) {
                                alert(data.data.result.msg);
                            }
                            var redirect_url = is_public ? wpcd_wpapp_params.apps_list_page_url : params.redirect;
                            location.href = redirect_url;
                        }
                    },
                    complete: function() {
						// An action that threw up the spinner lock screen is complete so unlock it.
                        $lock.unlock();
                    },
                    error: function(event, xhr, settings, thrownError) {
                        alert('AJAX Error - something went wrong but we cannot tell you what it was.  Its a bummer and illogical I know.  Most likely its a 504 gateway timeout error.  Increase the time your server allows for a script to run to maybe 300 seconds. In the meantime you can check the SSH LOG or COMMAND LOG screens to see if more data was logged there.');
                    }
                });
            }

        });

        // Confirm alert display when performing a bulk action on the site list screen.
        // This code is mostly a duplicate of that in wpcd-wpapp-server-admin.js.
        // If it changes here it might need to be changed there as well.
        $('#doaction').click(function() {
            console.log('here...');
            var selected_action = $("#bulk-action-selector-top option:selected").val();
            var valid_actions = ['wpcd_sites_update_themes_and_plugins', 'wpcd_sites_update_allthemes', 'wpcd_sites_update_allplugins', 'wpcd_sites_update_wordpress', 'wpcd_sites_update_everything'];
            if (valid_actions.indexOf(selected_action) >= 0) {
                var confirm_res = confirm(params.bulk_actions_confirm);
                if (!confirm_res) {
                    return false;
                }
            }
        });
    }

})(jQuery, params3);


// show/hide the spinner
(function($) {
    $.fn.lock = function() {
        $(this).each(function() {
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
            $(window).resize(function() {
                $this.find('.locker,.locker-loader').width($this.width()).height($this.height());
            });
        });

        return $(this);
    };

    $.fn.unlock = function() {
        $(this).each(function() {
            $(this).find('.locker').remove();
            $(this).css('position', $(this).data('position'));
        });

        return $(this);
    };
})(jQuery);