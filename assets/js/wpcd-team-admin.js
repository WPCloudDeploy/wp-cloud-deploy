/*
 * This JS file is loaded for cpt wpcd_team details screen only!
*/
/* global params */
(function ($, params) {
	init();

	/**
	 * For team manager is selected check
	 * @type {Boolean}
	 */
	var team_manager_selected = false;

	/**
	 * Instantiate more JS functions
	 * @return {Void} 
	 */
	function init() {
		$(document).on('click', '.wpcd_team-manager-checkbox input[type="checkbox"]', function (e) {
			var $this = $(this);

			if ($this.prop('checked') == true) {
				team_manager_selected = true;
			} else {
				team_manager_selected = false;
			}

			var $server_permissions = $this.closest('.wpcd_team-manager-checkbox').siblings('.wpcd_server-permissions');
			// to assign all server permissions to a user when user is set as a team manager
			$server_permissions.find('input[type="checkbox"]').each(function () {
				$(this).prop('checked', $this.prop('checked'));
			});

			var $app_permissions = $this.closest('.wpcd_team-manager-checkbox').siblings('.wpcd_app-permissions');
			// to assign all app permissions to a user when user is set as a team manager	
			$app_permissions.find('input[type="checkbox"]').each(function () {
				$(this).prop('checked', $this.prop('checked'));
			});

		});

		// If any server permission is removed, then deselect team manager checkbox
		$(document).on('click', '.wpcd_server-permissions input[type="checkbox"]', function (e) {
			var $this = $(this);
			var $context = $this.closest('.wpcd_server-permissions');
			var $otherContext = $this.closest('.wpcd_server-permissions').siblings('.wpcd_app-permissions');
			changeTeamManagerCheckbox($this, $context, $otherContext);
		});

		// If any app permission is removed, then deselect team manager checkbox
		$(document).on('click', '.wpcd_app-permissions input[type="checkbox"]', function (e) {
			var $this = $(this);
			var $context = $this.closest('.wpcd_app-permissions');
			var $otherContext = $this.closest('.wpcd_app-permissions').siblings('.wpcd_server-permissions');
			changeTeamManagerCheckbox($this, $context, $otherContext);
		});

		// to check the validation rules 
		jQuery('#submitdiv').on('click', '#publish', function (e) {
			var team_title = $.trim($('#title').val());
			if ((!team_title)) {
				alert(params.i10n.empty_title);
				return false;
			}

			var error = false;

			$('.wpcd_permission-rule .rwmb-group-clone').each(function (index) {
				var $this = $(this);

				// Team Member select box value
				var team_member = $this.find('.wpcd_team-member select').val();
				if (team_member == '') {
					alert(params.i10n.no_team_member);
					error = true;
					return false;
				}

				// Server Permissions checked checkbox
				var serverChecked = $('input:checkbox:checked', $this.find('.wpcd_server-permissions')).length;

				// App Permissions checked checkbox
				var appChecked = $('input:checkbox:checked', $this.find('.wpcd_app-permissions')).length;

				var totalChecked = serverChecked + appChecked;

				if (totalChecked == 0) {
					alert(params.i10n.no_permission);
					error = true;
					return false;
				}

			});

			// Do not save post if error is true
			if (error == true) {
				return false;
			} else {
				return true;
			}

		});
	}

	/**
	 * To check or uncheck team manager checkbox on assignment of all permissions or removal of any permission respectively
	 * @param  {Object} $this         Current permissions checkbox
	 * @param  {Object} $context      
	 * @param  {Object} $otherContext 
	 * @return {Void}               
	 */
	function changeTeamManagerCheckbox($this, $context, $otherContext) {
		var numberNotChecked1 = $('input:checkbox:not(":checked")', $context).length;
		var numberNotChecked2 = $('input:checkbox:not(":checked")', $otherContext).length;
		var numberNotChecked = numberNotChecked1 + numberNotChecked2;

		if (numberNotChecked > 0) {
			$context.siblings('.wpcd_team-manager-checkbox').find('input[type="checkbox"]').prop('checked', false);
			team_manager_selected = false;
		} else if (numberNotChecked == 0 && !team_manager_selected) {
			$context.siblings('.wpcd_team-manager-checkbox').find('input[type="checkbox"]').prop('checked', true);
			team_manager_selected = true;
		}
	}

})(jQuery, params);