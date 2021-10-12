(function ($, attributes) {

    $(document).ready(function () {
        init();
    });

    function init() {
        $('.wpcd-vpn-action-type').on('click', function (e) {
            var $lock = $(this).parents('.wpcd-vpn-instance');
            $lock.lock();
            var $action = $(this).data('action');
            e.preventDefault();
            $.ajax({
                url: wp.ajax.settings.url,
                method: 'post',
                data: {
                    'action': 'wpcd_vpn',
                    'nonce': attributes.nonce,
                    'vpn_action': $action,
                    'vpn_id': $(this).data('id'),
                    'vpn_app_id': $(this).data('app-id'),
                    'vpn_additional': $(this).parent().find('.wpcd-vpn-additional').serialize()
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
                            $('.wpcd-vpn-additional.wpcd-vpn-connected').empty();
                            if (data.data.result.length > 0) {
                                $.each(data.data.result, function (i, j) {
                                    $('.wpcd-vpn-additional.wpcd-vpn-connected').append('<option value="' + j.name + '">' + j.name + '</option>');
                                });
                                if ($action === 'connected') {
                                    $('.wpcd-vpn-action-type.wpcd-vpn-action-connected').hide();
                                    $('.wpcd-vpn-action-type.wpcd-vpn-action-disconnect').show();
                                }
                            }
                            if ($action === 'disconnect') {
                                $('.wpcd-vpn-action-type.wpcd-vpn-action-disconnect').hide();
                                $('.wpcd-vpn-action-type.wpcd-vpn-action-connected').show();
                            }
                        }
                        if ($action !== 'connected' && $action !== 'disconnect' && $action !== 'download-file' && $action !== 'instructions') {
                            window.location.reload();
                        }
                    } else {
                        $.magnificPopup.open({
                            items: {
                                src: $('<div class="wpcd-vpn-alert">' + data.data.msg + '</div>')
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
        $('.wpcd-vpn-additional.wpcd-vpn-provider').on('change', function (e) {
            $('.wpcd-vpn-additional.wpcd-vpn-region').empty();
            $regions = attributes.provider_regions[$(this).val()];
            $.each($regions, function (i, j) {
                $('.wpcd-vpn-additional.wpcd-vpn-region').append('<option value="' + j.slug + '">' + j.name + '</option>');
            });
        });

        // modal
        $('.wpcd-vpn-action-instructions').magnificPopup({
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
