/*
 * This JS file is loaded for cpt wpcd_cloud_provider details screen only!
*/
/* global params */
(function ($, params) {
    init();

    /**
     * Instantiate more JS functions
     * @return {Void} 
     */
    function init() {
        // to check the validation rules 
        jQuery('#submitdiv').on('click', '#publish', function (e) {
            var virtual_provider_title = $.trim($('#title').val());
            if ((!virtual_provider_title)) {
                alert(params.i10n.empty_title);
                return false;
            }
        });

        // for switch the cloud provider active status
        $('body').on('click', '.wpcd_active_provider', function (e) {

            var provider_active = 0;

            if (jQuery(this).is(":checked")) {
                provider_active = 0;
            } else {
                provider_active = 1;
            }

            var action = $(this).data('action');
            var nonce = $(this).data('nonce');
            var post_id = $(this).data('post_id');

            jQuery('#wpbody').addClass('all_provider_list_table');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: action,
                    nonce: nonce,
                    post_id: post_id,
                    provider_active: provider_active
                },
                success: function (data) {
                    jQuery('#wpbody').removeClass('all_provider_list_table');
                    alert(data.data.msg);
                    location.reload();
                },
                error: function (error) {
                    jQuery('#wpbody').removeClass('all_provider_list_table');
                    alert(error);
                    location.reload();
                }
            });

        });
    }

})(jQuery, params);