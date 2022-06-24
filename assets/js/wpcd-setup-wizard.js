jQuery(document).ready(function(){
	jQuery('#wpcd-skip-wizard').click(function(){
		jQuery.ajax({
			url : WPCD_Wizard.ajax_url,
			type : 'post',
			data : {
				action : 'wpcd_skip_wizard_setup',
				skip_wizard : true
			},
			success : function( response ) {
				/** 
				 * We only added new option for skipping wizard
				 * On success, simply refresh the page, or redirect to about?
				*/
				window.location = WPCD_Wizard.about_page;
			}
		});
	});
});