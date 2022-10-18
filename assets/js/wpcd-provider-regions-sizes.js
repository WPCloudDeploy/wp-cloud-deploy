(function($, attributes){

    $(document).ready(function(){
        init();
    });

    function init(){
        // change regions when provider changes.
        $('body').delegate('.wpcd_app_provider', 'change', function(e){
            if(Object.keys(attributes.provider_regions).length > 0){
                $('.wpcd_app_region').empty();
                $regions = attributes.provider_regions[ $(this).val() ];
                $.each($regions, function(i, j){
                    $('.wpcd_app_region').append('<option value="' + j.slug + '">' + j.name + '</option>');
                });
            }
            if(Object.keys(attributes.provider_sizes).length > 0){
                $('.wpcd_app_size').empty();
                $sizes = attributes.provider_sizes[ $(this).val() ];
                $.each($sizes, function(i, j){
                    $('.wpcd_app_size').append('<option value="' + j.slug + '">' + j.name + '</option>');
                });
            }
        });

        // change regions when provider changes for bulk installs (part of powertools).
        $('body').delegate('select[name="wpcd_bulk_installs_provider[]"]', 'change', function (e) {

            var bulk_install_provider = $(this);
            
            if (Object.keys(attributes.provider_regions).length > 0) {
               
                $(bulk_install_provider).closest('.wpcd-bulk-installs-form-fields').find('select[name="wpcd_bulk_installs_region[]"]').empty();
                $regions = attributes.provider_regions[$(this).val()];
                $.each($regions, function (i, j) {
                    $(bulk_install_provider).closest('.wpcd-bulk-installs-form-fields').find('select[name="wpcd_bulk_installs_region[]"]').append('<option value="' + j.slug + '">' + j.name + '</option>');
                });
            }
            if (Object.keys(attributes.provider_sizes).length > 0) {
                $(bulk_install_provider).closest('.wpcd-bulk-installs-form-fields').find('select[name="wpcd_bulk_installs_size[]"]').empty();
                $sizes = attributes.provider_sizes[$(this).val()];
                $.each($sizes, function (i, j) {
                    $(bulk_install_provider).closest('.wpcd-bulk-installs-form-fields').find('select[name="wpcd_bulk_installs_size[]"]').append('<option value="' + j.slug + '">' + j.name + '</option>');
                });
            }
        });
    }
})(jQuery, attributes);
