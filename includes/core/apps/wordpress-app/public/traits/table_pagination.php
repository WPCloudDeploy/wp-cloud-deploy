<?php


trait table_pagination {
	/**
	 * Update paged query var from pagination links
	 */
	public function update_table_pagination_js() {
		?>
		<script type="text/javascript">
		( function($){

			$( function() {
				$('#posts-filter .tablenav-pages .pagination-links a').each( function() {
					var url = jQuery(this).attr('href');
					url = url.replace("paged=", "_page=")
					$(this).attr('href', url);
				});
			});
			
			$('#posts-filter .tablenav-pages .paging-input input.current-page[name=paged]').attr('name', '_page');

		})(jQuery);
		
		</script>
		<?php
	}
	
	
	/**
	 * Gets the current page number.
	 *
	 * @since 3.1.0
	 *
	 * @return int
	 */
	public function get_pagenum() {

		$pagenum = 0;
		if ( isset( $_REQUEST['_page'] ) ) {
			$pagenum = filter_input( INPUT_GET, '_page', FILTER_SANITIZE_NUMBER_INT );
		}

		if ( isset( $this->_pagination_args['total_pages'] ) && $pagenum > $this->_pagination_args['total_pages'] ) {
			$pagenum = $this->_pagination_args['total_pages'];
		}

		return max( 1, $pagenum );
	}
	
}