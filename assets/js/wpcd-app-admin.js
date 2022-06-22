/*
 * This JS file is loaded for the wp-admin app screen.
 */

/* global ajaxurl */
/* global app_owner_params */

(function($, app_owner_params) {

    $(document).ready(function() {
        init();
    });

    function init() {

        var no_owners_found_msg = app_owner_params.i10n.no_owners_found_msg;
        var search_owner_placeholder = app_owner_params.i10n.search_owner_placeholder;

        // Server owners dropdown with search box.
        $(".wpcd_search_owner_filter#filter-by-wpcd_server_owner").select2({
            language: {
                noResults: function(app_owner_params) {
                    return no_owners_found_msg;
                }
            },
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                type: "post",
                data: function(param_search) {
                    var search_term = param_search.term;
                    return {
                        search_term: search_term, // search term.
                        post_type: app_owner_params.i10n.server_post_type, // post type.
                        field_key: app_owner_params.i10n.server_field_key, // field key.
                        first_option: app_owner_params.i10n.server_first_option, // first option.
                        action: app_owner_params.i10n.action, // ajax action.
                        nonce: app_owner_params.i10n.nonce, // nonce.
                    };
                },
                processResults: function(data, app_owner_params) {
                    var data_json = data.data.items;
                    return {
                        results: $.map(data_json, function(obj, index) {
                            return { id: index, text: obj };
                        })
                    };
                },
            }
        });

        // App owners dropdown with search box.
        $(".wpcd_search_owner_filter#filter-by-wpcd_app_owner").select2({
            language: {
                noResults: function(app_owner_params) {
                    return no_owners_found_msg;
                }
            },
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                type: "post",
                data: function(param_search) {
                    var search_term = param_search.term;
                    return {
                        search_term: search_term, // search term.
                        post_type: app_owner_params.i10n.app_post_type, // post type.
                        field_key: app_owner_params.i10n.app_field_key, // field key.
                        first_option: app_owner_params.i10n.app_first_option, // first option.
                        action: app_owner_params.i10n.action, // ajax action.
                        nonce: app_owner_params.i10n.nonce, // nonce.
                    };
                },
                processResults: function(data, app_owner_params) {
                    var data_json = data.data.items;
                    return {
                        results: $.map(data_json, function(obj, index) {
                            return { id: index, text: obj };
                        })
                    };
                },
            }
        });

        // Set placeholder for search box.
        $('.wpcd_search_owner_filter#filter-by-wpcd_server_owner, .wpcd_search_owner_filter#filter-by-wpcd_app_owner').one('select2:open', function(e) {
            $('input.select2-search__field').prop('placeholder', search_owner_placeholder);
        });

    }

})(jQuery, app_owner_params);