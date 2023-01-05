/* This code fixes an issue in METABOX.io tabs.  It tries to detect what 
 * tab you're on before a page refresh and then take you right back there
 * after the page refresh is complete.
 * 
 * Currently, this file is being enqueued in class-wpcd-settings.php on
 * the admin_enqueue_scripts action hook.
 */
(function (window, document, $) {
        'use strict';

        var processing = false;
        var tabs_switched = false;

        // Get main and sub tab ids from url hash and auto select once page loaded
        function switchTab() {

                if (processing) {
                        return;
                }

                var hash_parts = window.location.hash.split('~~');

                var main_tab = hash_parts[0];
                var sub_tab = hash_parts[1];

                if (!sub_tab) {
                        return;
                }

                processing = true;

                // Trigger main tab select
                $('.nav-tab-wrapper a[href="' + main_tab + '"]').trigger('click');

                // Trigger sub tab select
                $('.rwmb-tab-nav li[data-panel="' + sub_tab + '"] a').trigger('click');

                processing = false;
                tabs_switched = true;
        }

        $(function () {

                setTimeout(switchTab, 500);
                
                // Append sub tab id in url hash, so we can auto select after page reload
                $('.rwmb-tab-nav').on('click', 'a', function () {

                        if( !tabs_switched ) {
                                return;
                        }
                        
                        var panel = $(this).closest('li').data('panel');
                        var hash_parts = window.location.hash.split('~~');
                        var main_tab = hash_parts[0];

                        window.location.hash = main_tab + '~~' + panel;
                });

        });
})(window, document, jQuery);
