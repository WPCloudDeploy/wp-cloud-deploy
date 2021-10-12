/**
 * This file is currently not used.
 * For now, it's ok to delete the entire thing at any point.
 */
(function ($, attributes) {

    $(document).ready(function () {
        init();
    });

    function init() {
        $('.wpcd-wpapp-action-type').on('click', function (e) {
            var $lock = $(this).parents('.wpcd-wpapp-instance');
            $lock.lock();
            var $action = $(this).data('action');
            e.preventDefault();
            $.ajax({
                url: wp.ajax.settings.url,
                method: 'post',
                data: {
                    'action': 'wpcd_wpapp_frontend',
                    'nonce': attributes.nonce,
                    'wpapp_action': $action,
                    'wpapp_server_id': $(this).data('id'),
                    'wpapp_app_id': $(this).data('app-id'),
                    'wpapp_additional': $(this).parent().find('.wpcd-wpapp-additional').serialize()
                },
                success: function (data) {
                    if (data.success) {
                        // add-user and download-file.
                        if (typeof data.data !== 'undefined' && typeof data.data.contents !== 'undefined') {
                            var a = document.createElement("a");
                            document.body.appendChild(a);
                            a.style = "display: none";
                            var blob = new Blob([data.data.contents], { type: "application/x-openvpn-profile" }),
                                url = window.URL.createObjectURL(blob);
                            a.href = url;
                            a.download = data.data.name;
                            a.click();
                            setTimeout(function () {
                                window.URL.revokeObjectURL(url);
                            }, 100);
                        }

                        // list users and disconnect users.
                        if ($action === 'connected' || $action === 'disconnect') {
                            $('.wpcd-wpapp-additional.wpcd-wpapp-connected').empty();
                            if (data.data.result.length > 0) {
                                $.each(data.data.result, function (i, j) {
                                    $('.wpcd-wpapp-additional.wpcd-wpapp-connected').append('<option value="' + j.name + '">' + j.name + '</option>');
                                });
                                if ($action === 'connected') {
                                    $('.wpcd-wpapp-action-type.wpcd-wpapp-action-connected').hide();
                                    $('.wpcd-wpapp-action-type.wpcd-wpapp-action-disconnect').show();
                                }
                            }
                            if ($action === 'disconnect') {
                                $('.wpcd-wpapp-action-type.wpcd-wpapp-action-disconnect').hide();
                                $('.wpcd-wpapp-action-type.wpcd-wpapp-action-connected').show();
                            }
                        }
                        if ($action !== 'connected' && $action !== 'disconnect' && $action !== 'download-file' && $action !== 'instructions') {
                            window.location.reload();
                        }
                    } else {
                        $.magnificPopup.open({
                            items: {
                                src: $('<div class="wpcd-basic-server-alert">' + data.data.msg + '</div>')
                            },
                            type: 'inline'
                        });
                    }
                },
                complete: function () {
                    $lock.unlock();
                }
            });
        });

        // to change regions when provider changes.
        $('.wpcd-wpapp-additional.wpcd-wpapp-provider').on('change', function (e) {
            $('.wpcd-wpapp-additional.wpcd-wpapp-region').empty();
            $regions = attributes.provider_regions[$(this).val()];
            $.each($regions, function (i, j) {
                $('.wpcd-wpapp-additional.wpcd-wpapp-region').append('<option value="' + j.slug + '">' + j.name + '</option>');
            });
        });

        // modal
        $('.wpcd-wpapp-action-instructions').magnificPopup({
            type: 'inline'
            // other options
        });
    }
})(jQuery, attributes);


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
