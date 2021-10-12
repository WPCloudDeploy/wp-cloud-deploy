(function ($, attributes) {

    $(document).ready(function () {
        init();
    });

    function init() {
        $('.wpcd-basic-server-action-type').on('click', function (e) {
            var $lock = $(this).parents('.wpcd-basic-server-instance');
            $lock.lock();
            var $action = $(this).data('action');
            e.preventDefault();
            $.ajax({
                url: wp.ajax.settings.url,
                method: 'post',
                data: {
                    'action': 'wpcd_basic_server',
                    'nonce': attributes.nonce,
                    'basic_server_action': $action,
                    'basic_server_id': $(this).data('id'),
                    'basic_server_app_id': $(this).data('app-id'),
                    'basic_server_additional': $(this).parent().find('.wpcd-basic-server-additional').serialize()
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
                            $('.wpcd-basic-server-additional.wpcd-basic-server-connected').empty();
                            if (data.data.result.length > 0) {
                                $.each(data.data.result, function (i, j) {
                                    $('.wpcd-basic-server-additional.wpcd-basic-server-connected').append('<option value="' + j.name + '">' + j.name + '</option>');
                                });
                                if ($action === 'connected') {
                                    $('.wpcd-basic-server-action-type.wpcd-basic-server-action-connected').hide();
                                    $('.wpcd-basic-server-action-type.wpcd-basic-server-action-disconnect').show();
                                }
                            }
                            if ($action === 'disconnect') {
                                $('.wpcd-basic-server-action-type.wpcd-basic-server-action-disconnect').hide();
                                $('.wpcd-basic-server-action-type.wpcd-basic-server-action-connected').show();
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
        $('.wpcd-basic-server-additional.wpcd-basic-server-provider').on('change', function (e) {
            $('.wpcd-basic-server-additional.wpcd-basic-server-region').empty();
            $regions = attributes.provider_regions[$(this).val()];
            $.each($regions, function (i, j) {
                $('.wpcd-basic-server-additional.wpcd-basic-server-region').append('<option value="' + j.slug + '">' + j.name + '</option>');
            });
        });

        // modal
        $('.wpcd-basic-server-action-instructions').magnificPopup({
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
