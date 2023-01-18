/* 
 * Handles operations for custom tables such as the DNS and PROVIDER tables.
 */

(function($) {
        
        

$(document).ready(function() {
        
        /**
         * Close add/edit form window
         */
        $('body').delegate('.wpcd_mb_inline_edit_form_window .mfp-close-window-button', 'click', function() {
                $('.wpcd_mb_inline_edit_form_window .mfp-close').trigger('click');
        });
        
        
        /**
         * Handle add/edit window form submit
         */
        $('body').delegate('.wpcd_mb_inline_edit_form_window form', 'submit', function(e) {
                e.preventDefault();
                
                var form = $(this);
                form.find('> .notice').remove();
                
                var buttons = form.find('.wpcd_ct_buttons_row button[type=submit], .wpcd_ct_buttons_row button.mfp-close-window-button');
                buttons.prop('disabled', true);
                
                var spinner = form.find('.wpcd_ct_buttons_row .spinner');
                spinner.addClass('is-active wpcd-loader-public');
                
                var container = $.magnificPopup.instance.currItem.el.closest('.wpcd_ct_table_container');
                
                $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: $(this).serializeArray(),
                        success: function(data) {
                                spinner.removeClass('is-active wpcd-loader-public');
                                buttons.prop('disabled', false );
                                
                                if( !data.success ) {
                                        form.prepend('<div class="notice notice-error">'+data.error+'</div>');
                                        return;
                                }
                                wpcd_ct_load_child_items_table_view( container );
                                $.magnificPopup.close();
                                
                                
                        }
                });
        });
        
        /**
         * Load dns zones
         */
        if( $('#wpcd_ct_provider_zones_table_container').length === 1 ) {
                wpcd_ct_load_child_items_table_view( $('#wpcd_ct_provider_zones_table_container') );
        }
        
        /**
         * Handle load dns zone records
         */
        $('body').delegate('.wpcd_ct_load_dns_record_btn', 'click', function(e) {
                e.preventDefault();
                
                $('#wpcd_ct_zone_records_table_container').data( 'zone', $(this).data('zone') );
                $('#wpcd_ct_zone_records_table_container').data( 'parent_id', $(this).data('zone') );
                
                $(this).closest('tbody').find('>tr').removeClass('active');
                $(this).closest('tr').addClass('active');
                
                var link_el = $('#wpcd_ct_zone_records_table_container > a.mp_edit_inline:first');
                
                var action = $(link_el).data('action');
                var nonce = $(link_el).data('nonce');
                var view = $(link_el).data('view');
                var zone = $(this).data('zone');
                
                
                
                var add_item_url = ajaxurl + '?' + $.param({action:action, "parent-id":zone, nonce : nonce, view: view});
                link_el.attr( 'href', add_item_url );
                
                var container = '#wpcd_ct_zone_records_table_container';
                $(container).find('.wpcd-ct-add-item-link').hide();
                wpcd_ct_load_child_items_table_view(container);
        });
        
        
        /**
         * Unload dns zone records table
         * 
         * @param string container
         * @returns void
         */
        function wpcd_ct_unload_child_items_table_view( container ) {
                $(container).find('.wpcd_ct_items_table_view_container').html('');
                $(container).find('.wpcd-ct-add-item-link').hide();
        }
        
        /**
         * Load child items table view
         * 
         * @param string container
         * 
         * @returns void
         */
        function wpcd_ct_load_child_items_table_view(container) {
                $(container).find('.wpcd_ct_items_table_view_container').html('<div class="spinner is-active wpcd-loader-public"></div>');
                
                var data = $(container).data();
                
                $.ajax({
                        url: ajaxurl,
                        method: 'GET',
                        data: data,
                        success: function(data) {
                                if( data.can_add_item ) {
                                        $(container).find('.wpcd-ct-add-item-link').show();
                                } else {
                                        $(container).find('.wpcd-ct-add-item-link').hide();
                                }
                                
                                if( data.error ) {
                                        add_ajax_table_error( container, data.error );
                                        $(container).find('.wpcd_ct_items_table_view_container').html( '' );
                                } else {
                                        remove_ajax_table_error( container );
                                        $(container).find('.wpcd_ct_items_table_view_container').html( data.items );
                                }
                        }
                });
                
        }
        
        /**
         * Show ajax error
         * 
         * @param string container
         * @param string message
         * 
         * @returns void
         */
        function add_ajax_table_error( container, message ) {
                $( container ).find('> .wpcd-ct-notices').html( message );
        }
        
        /**
         * Remove ajax error
         * 
         * @param string container
         * 
         * @returns void
         */
        function remove_ajax_table_error( container ) {
                $( container ).find('> .wpcd-ct-notices').html('');
        }
        
        
        /**
         * Handle delete item on page edit screen
         */
        $('body').delegate('#wpcd-mbct-delete', 'click', function(e) {
                e.preventDefault();
                
                if( !confirm( Mbct.confirm ) ) {
                        return;
                }
                var container = $(this).closest('form');
                delete_item( $(this), 'edit_delete_item', container );
        });
        
        /**
         * Handle delete item on listing page
         */
        $('body').delegate('.wpcd-ct-delete .wpcd-ct-delete-item', 'click', function(e) {
                e.preventDefault();
                
                if( !confirm( MbctListTable.confirm ) ) {
                        return;
                }
                var container = $(this).closest('form');
                delete_item( $(this), 'list_delete_item', container );
        });
        
        
        /**
         * Handle delete items on child table view
         */
        $('body').delegate('.wpcd-ct-delete-child-item', 'click', function(e) {
                e.preventDefault();
                
                if( !confirm( Mbct.confirm ) ) {
                        return;
                }
                
                var container = $(this).closest('.wpcd_ct_table_container');
                delete_item( $(this), 'child_item', container );
        });
        
        
        /**
         * Handle delete item via ajax
         * 
         * @param object ele
         * @param string type
         * @param object container
         * 
         * @returns void
         */
        function delete_item( ele, type, container ) {
                
                var data = new Array();
                var model = $(ele).data('model');
                var action = 'wpcd_ct_'+model+'_delete_item';
                
                var item_id = $(ele).data('id');
                
                data.push( {name:'action',  value:action} );
                data.push( {name:'id',      value: item_id } );
                data.push( {name:'view',    value:$(ele).data('view')} );
                data.push( {name:'nonce',   value:$(ele).data('nonce')} );
                
                $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: data,
                        success: function( response ) {
                                
                                if( type == 'child_item' ) {
                                        handle_delete_child_item_response( response, container )
                                } else if( type == 'edit_delete_item') {
                                        handle_delete_edit_item_response( response, container )
                                } else if( type == 'list_delete_item') {
                                        handle_delete_edit_item_response( response, container )
                                } 
                        }
                });
                
        }
        
        
        /**
         * Handle delete item response from edit screen
         * 
         * @param object response
         * @param object container
         * 
         * @returns void
         */
        function handle_delete_edit_item_response( response, container ) {
                
                
                if( !response.success ) {
                        alert( response.message );
                } else {
                        
                        if( response.location ) {
                                window.location = response.location;
                        } else if( response.message ) {
                                alert( response.message );
                        }
                }
        }
        
        /**
         * Handle delete child item response
         * 
         * @param object response
         * @param object container
         * 
         * @returns void
         */
        function handle_delete_child_item_response( response, container ) {
                if( response.error ) {
                        add_ajax_table_error( container, response.error );
                } else {

                        if( response.unload_child_table && item_id == $(response.unload_child_table).data('parent_id') ) {
                                wpcd_ct_unload_child_items_table_view( response.unload_child_table );
                        }

                        remove_ajax_table_error( container );
                        wpcd_ct_load_child_items_table_view( container );
                }
        }
        
        
        /**
         * Open add/edit child item window
         */
        $('body').delegate('.mp_edit_inline', 'click', function(e) {
                e.preventDefault();
                
                if( typeof $(this).data('magnificPopup') == 'object' ) {
                        return;
                }

                $(this).magnificPopup({
                    type: 'ajax',
                    modal: true,
                    prependTo : 'body',
                    callbacks: {}
                }).magnificPopup('open');
        });
    })
})(jQuery);