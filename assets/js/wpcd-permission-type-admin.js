/*
 * This JS file is loaded for cpt wpcd_permission_type details screen only!
*/
(function ($) {
	init();

	/**
	 * Instantiate more JS functions
	 * @return {Void} 
	 */
	function init() {
		/**
		 * Custom validation method for a valid permission name
		 * @param  {string} value 
		 * @param  {string} ''     
		 * @return {Boolean}
		 */
		$.validator.addMethod('valid_permission', function (value) {
			return /^[a-z_]+$/.test(value);
		}, '');
	}

})(jQuery);