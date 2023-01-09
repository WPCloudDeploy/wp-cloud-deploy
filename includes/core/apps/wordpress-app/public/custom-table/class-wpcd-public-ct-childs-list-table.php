<?php

require_once WPCD_PATH . 'includes/core/custom-table/class-wpcd-ct-childs-list-table.php';
require_once WPCD_PATH . 'includes/core/apps/wordpress-app/public/traits/grid_table.php';

class WPCD_Public_CT_Childs_List_Table extends WPCD_CT_Childs_List_Table {
	
	use wpcd_grid_table;
	
	/**
	 * Return default column
	 * 
	 * @return string
	 */
	public function getPrimaryColumn() {
		return 'id';
	}
	
}