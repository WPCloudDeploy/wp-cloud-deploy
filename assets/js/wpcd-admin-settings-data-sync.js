/*
 * This JS file is loaded for the WPCD settings screen.
 */

(function($, wpcd_admin_settings_data_sync_params) {

    var interval;

    $(document).ready(function() {
        init();
    });

    // for validate sync options when save the settings
    function initValidateSyncOptions() {
        jQuery("#post.rwmb-settings-form").submit(function() {
            if (jQuery('#wpcd_sync_auto_export').is(":checked")) {
                var validated = initValidation();
                if (!validated) {
                    return false;
                }
            }
        });
    }

    // for show or hide auto export settings
    function initAutoExportToggle() {

        // show or hide auto export option when page load
        if (jQuery('#wpcd_sync_auto_export').is(":checked")) {
            jQuery('#wpcd_sync_set_cron').parent().parent().show();
        } else {
            jQuery('#wpcd_sync_set_cron').parent().parent().hide();
        }

        // show or hide auto export option on enable or disable
        jQuery('#wpcd_sync_auto_export').on('click', function(e) {
            if (jQuery(this).is(":checked")) {
                jQuery('#wpcd_sync_set_cron').parent().parent().show();
            } else {
                jQuery('#wpcd_sync_set_cron').parent().parent().hide();
            }
        });
    }

    // for actions on the SETTINGS->SYNC Tab
    function initSyncPush() {
        // for pushing the data on target site - triggered from the SETTINGS->SYNC->PUSH button
        $('body').on('click', '#wpcd-sync-push', function(e) {
            e.preventDefault();

            jQuery('.display_waiting_message').remove();

            var validated = initValidation();

            if (!validated) {
                // do nothing
            } else {

                var action = $(this).data('action');
                var nonce = $(this).data('nonce');

                var wpcd_sync_target_site = $('#wpcd_sync_target_site').val();
                var wpcd_sync_enc_key = $('#wpcd_sync_enc_key').val();
                var wpcd_sync_user_id = $('#wpcd_sync_user_id').val();
                var wpcd_sync_password = $('#wpcd_sync_password').val();

                var wpcd_export_all_settings;
                if (jQuery('#wpcd_export_all_settings').is(":checked")) {
                    wpcd_export_all_settings = 1;
                } else {
                    wpcd_export_all_settings = 0;
                }

                jQuery("<span class='display_waiting_message'>" + wpcd_admin_settings_data_sync_params.i10n.wait_msg + "</span>").insertAfter('#wpcd-sync-push');
                jQuery('#wpcd-sync-push').attr('disabled', 'disabled');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: action,
                        nonce: nonce,
                        wpcd_sync_target_site: wpcd_sync_target_site,
                        wpcd_sync_enc_key: wpcd_sync_enc_key,
                        wpcd_sync_user_id: wpcd_sync_user_id,
                        wpcd_sync_password: wpcd_sync_password,
                        wpcd_export_all_settings: wpcd_export_all_settings,
                    },
                    success: function(data) {
                        jQuery('.display_waiting_message').remove();
                        alert(data.data.msg);
                        location.reload();
                    },
                    error: function(error) {
                        jQuery('.display_waiting_message').remove();
                        jQuery('#wpcd-sync-push').removeAttr('disabled');
                    }
                });

            }

        });

        // for deleting the data received as a file 
        $('body').on('click', '.wpcd-received-files-delete', function(e) {
            e.preventDefault();

            jQuery('.display_waiting_message').remove();

            var file_name = $(this).attr('data-file-name');
            var restore_id = $(this).attr('data-restore-id');

            jQuery(this).parent().parent().append('<span class="display_waiting_message">' + wpcd_admin_settings_data_sync_params.i10n.delete_wait_msg + '</span>');
            jQuery(this).css('pointer-events', 'none');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'wpcd_delete_received_file',
                    nonce: wpcd_admin_settings_data_sync_params.nonce,
                    file_name: file_name,
                    restore_id: restore_id,
                },
                success: function(data) {
                    jQuery('.display_waiting_message').remove();
                    alert(data.data.msg);
                    location.reload();
                },
                error: function(error) {
                    jQuery(this).css('pointer-events', 'inherit');
                    jQuery('.display_waiting_message').remove();
                }
            });
        });


        // for enter the decryption key before restore file
        jQuery('body').on('click', '.close_custom_popup', function(e) {
            jQuery('.cover-popup-bg-sec').hide();
            jQuery('.modal').hide();
        });


        // for restoring the data received as a file
        $('body').on('click', '.wpcd-received-files-restore', function(e) {
            e.preventDefault();

            // clear fields values
            jQuery('#decryption_key_to_restore').val('');
            jQuery('#restore_file_name').val('');
            jQuery('#restore_file_id').val('');
            jQuery('#restore_delete_existing').val('');

            var file_name = $(this).attr('data-file-name');
            var restore_id = $(this).attr('data-restore-id');
            var delete_existing = $(this).attr('data-delete-existing');

            // show decryption key popup
            jQuery('.cover-popup-bg-sec').show();
            jQuery('#restore_file_key_popup').show();

            // store file name and path in hidden fields of popup
            jQuery('#restore_file_name').val(file_name);
            jQuery('#restore_file_id').val(restore_id);
            jQuery('#restore_delete_existing').val(delete_existing);
        });


        // enter decryption key in popup and process ahead
        $('body').on('click', '#enter_decryption_key', function(e) {
            e.preventDefault();

            jQuery('.display_waiting_message').remove();

            var decryption_key_to_restore = jQuery('#decryption_key_to_restore').val();

            if (decryption_key_to_restore == '' || decryption_key_to_restore == null) {
                alert('Please enter decryption key');
            } else {
                var file_name = jQuery('#restore_file_name').val();
                var restore_id = jQuery('#restore_file_id').val();
                var delete_existing = jQuery('#restore_delete_existing').val();

                // hide decryption key popup
                jQuery('.cover-popup-bg-sec').hide();
                jQuery('#restore_file_key_popup').hide();

                // Confirmation for deleting the existing data on the restore action
                var confirm_restore = confirm(wpcd_admin_settings_data_sync_params.i10n.restore_confirmation);

                if (confirm_restore) {

                    jQuery('a[data-file-name="' + file_name + '"]').parent().parent().append('<span class="display_waiting_message">' + wpcd_admin_settings_data_sync_params.i10n.restore_wait_msg + '</span>');
                    jQuery('a[data-file-name="' + file_name + '"]').css('pointer-events', 'none');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'wpcd_restore_received_file',
                            nonce: wpcd_admin_settings_data_sync_params.nonce,
                            file_name: file_name,
                            restore_id: restore_id,
                            delete_existing: delete_existing,
                            decryption_key_to_restore: decryption_key_to_restore,
                        },
                        success: function(data) {
                            jQuery('.display_waiting_message').remove();
                            alert(data.data.msg);
                            location.reload();
                        },
                        error: function(error) {
                            jQuery('a[data-file-name="' + file_name + '"]').css('pointer-events', 'inherit');
                            jQuery('.display_waiting_message').remove();
                        }
                    });
                }
            }
        });


        // save the encryption key value
        $('body').on('click', '#wpcd-encryption-key-save', function(e) {
            e.preventDefault();

            jQuery('.display_waiting_message').remove();

            var wpcd_encryption_key_v2 = $('#wpcd_encryption_key_v2').val();
            if (wpcd_encryption_key_v2 == '') {
                alert(wpcd_admin_settings_data_sync_params.i10n.empty_encryption_key_v2)
                return false;
            }

            var action = $(this).data('action');
            var nonce = $(this).data('nonce');

            var wpcd_encryption_key_v2 = $('#wpcd_encryption_key_v2').val();

            jQuery("<span class='display_waiting_message'>" + wpcd_admin_settings_data_sync_params.i10n.save_wait_msg + "</span>").insertAfter('#wpcd-encryption-key-save');
            jQuery('#wpcd-encryption-key-save').attr('disabled', 'disabled');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: action,
                    nonce: nonce,
                    wpcd_encryption_key_v2: wpcd_encryption_key_v2,
                },
                success: function(data) {
                    jQuery('.display_waiting_message').remove();
                    alert(data.data.msg);
                    location.reload();
                },
                error: function(error) {
                    jQuery('.display_waiting_message').remove();
                    jQuery('#wpcd-encryption-key-save').removeAttr('disabled');
                }
            });
        });

    }

    // for validating the fields on the SETTINGS->SYNC - Checks if the fields are not empty before pushing to the Target site
    function initValidation() {

        // Get fields to check for validation
        var wpcd_sync_target_site = $('#wpcd_sync_target_site').val();
        var wpcd_sync_enc_key = $('#wpcd_sync_enc_key').val();
        var wpcd_sync_user_id = $('#wpcd_sync_user_id').val();
        var wpcd_sync_password = $('#wpcd_sync_password').val();

        if (wpcd_sync_target_site == '') {
            alert(wpcd_admin_settings_data_sync_params.i10n.empty_target_site)
            return false;
        } else if (wpcd_sync_enc_key == '') {
            alert(wpcd_admin_settings_data_sync_params.i10n.empty_enc_key)
            return false;
        } else if (wpcd_sync_user_id == '') {
            alert(wpcd_admin_settings_data_sync_params.i10n.empty_user_id)
            return false;
        } else if (wpcd_sync_password == '') {
            alert(wpcd_admin_settings_data_sync_params.i10n.empty_password)
            return false;
        } else {
            return true;
        }

    }

    function init() {
        initSyncPush();
        initAutoExportToggle();
        initValidateSyncOptions();
    }

})(jQuery, wpcd_admin_settings_data_sync_params);