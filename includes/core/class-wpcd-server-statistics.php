<?php
/**
 * WPCD_SERVER_STATISTICS class for server statistics.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_SERVER_STATISTICS
 */
class WPCD_SERVER_STATISTICS {

	/**
	 * WPCD_SERVER_STATISTICS instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * WPCD_SERVER_STATISTICS constructor.
	 *
	 * @return void
	 */
	public function __construct() {

	}

	/**
	 * Gets the formatted Disk Statistics data of server post for the chart.
	 *
	 * @param  int $post_id Server ID.
	 * @return array
	 */
	public function wpcd_app_server_get_formatted_disk_statistics( $post_id ) {

		if ( empty( $post_id ) ) {
			return array();
		}

		$disk_statistics = get_post_meta( $post_id, 'wpcd_wpapp_disk_statistics', true );

		$disk_statistics = wp_strip_all_tags( $disk_statistics );

		if ( empty( $disk_statistics ) ) {
			return array();
		}

		$disk_statistics_parts = array_filter( preg_split( '/\r\n|\r|\n/', $disk_statistics ) );

		$label_for_datasets = $disk_statistics_parts[0];

		unset( $disk_statistics_parts[0] ); // To remove the first line that contains labels.

		$disk_statistics_parts = array_values( $disk_statistics_parts );

		$line_parts = array();
		// Explode each line and store it into a variable.
		foreach ( $disk_statistics_parts as $line ) {
			$line_parts[] = preg_split( '/\s+/', $line );
		}

		$combine_snap       = array();
		$count              = 0;
		$combine_1kblocks   = 0;
		$combine_used       = 0;
		$combine_available  = 0;
		$combine_percentage = 0;

		// Store each line values in to a separate array.
		foreach ( $line_parts as $line_key => $line_part ) {
			if ( strpos( $line_part[5], '/snap' ) !== false ) {
				$count++;

				$combine_1kblocks  = $combine_1kblocks + $line_part[1];
				$combine_used      = $combine_used + $line_part[2];
				$combine_available = $combine_available + $line_part[3];

				$after_sign_removed = str_replace( array( '%' ), '', $line_part[4] );
				$combine_percentage = $combine_percentage + $after_sign_removed;

				unset( $line_parts[ $line_key ] );
			}
		}

		$avg_percentage = 0;

		if ( $count > 0 ) {
			$avg_percentage = $combine_percentage / $count;
		}

		$combine_snap[0] = __( '/dev/all-loops', 'wpcd' );
		$combine_snap[1] = $combine_1kblocks;
		$combine_snap[2] = $combine_used;
		$combine_snap[3] = $combine_available;
		$combine_snap[4] = (int) $avg_percentage . '%';
		$combine_snap[5] = __( '/all-snaps/', 'wpcd' );

		$line_parts[] = $combine_snap;

		// Store each line values in to a separate array.
		foreach ( $line_parts as $line_part ) {
			$label                 = $line_part[0] . ': ' . $line_part[5];
			$labels[]              = sanitize_text_field( $label ); // Filesystem: Mounted on.
			$chart_col_1_kblocks[] = sanitize_text_field( $line_part[1] ); // 1K-blocks values.
			$chart_col_used[]      = sanitize_text_field( $line_part[2] ); // Used values.
			$chart_col_available[] = sanitize_text_field( $line_part[3] ); // Available values.
		}

		$return = array();

		$return['chart_column_labels']    = wp_json_encode( $labels );
		$return['chart_column_1K_blocks'] = wp_json_encode( $chart_col_1_kblocks );
		$return['chart_column_Used']      = wp_json_encode( $chart_col_used );
		$return['chart_column_Available'] = wp_json_encode( $chart_col_available );

		return $return;
	}

	/**
	 * Gets the formatted VNSTAT data of server post for the chart.
	 *
	 * @param  int $post_id Server ID.
	 * @return array
	 */
	public function wpcd_app_server_get_formatted_vnstat_data( $post_id ) {
		if ( empty( $post_id ) ) {
			return array();
		}

		$vnstat_data = get_post_meta( $post_id, 'wpcd_wpapp_vnstat_one_line_data', true );

		$vnstat_data = wp_strip_all_tags( $vnstat_data );

		if ( empty( $vnstat_data ) ) {
			return array();
		}

		$vnstat_data = explode( ';', $vnstat_data );

		$curr_day_vnstat_data   = array();
		$curr_month_vnstat_data = array();
		$all_time_vnstat_data   = array();

		// Data for the current day.
		$curr_day_vnstat_data[] = $this->convert_to_numeric_data( $vnstat_data[3] );
		$curr_day_vnstat_data[] = $this->convert_to_numeric_data( $vnstat_data[4] );

		// Data for the current month.
		$curr_month_vnstat_data[] = $this->convert_to_numeric_data( $vnstat_data[8] );
		$curr_month_vnstat_data[] = $this->convert_to_numeric_data( $vnstat_data[9] );

		// Data for the all time.
		$all_time_vnstat_data[] = $this->convert_to_numeric_data( $vnstat_data[12] );
		$all_time_vnstat_data[] = $this->convert_to_numeric_data( $vnstat_data[13] );

		$return = array();

		$return['curr_day_vnstat_data']   = wp_json_encode( $curr_day_vnstat_data );
		$return['curr_month_vnstat_data'] = wp_json_encode( $curr_month_vnstat_data );
		$return['all_time_vnstat_data']   = wp_json_encode( $all_time_vnstat_data );

		return $return;

	}

	/**
	 * Takes the string data and return the numeric value.
	 * Also it converts MiB or GiB values into KiB value.
	 *
	 * @param  string $data data.
	 * @return float
	 */
	public function convert_to_numeric_data( $data = '' ) {
		if ( empty( $data ) ) {
			return 0;
		}

		$data_parts = explode( ' ', $data );

		if ( 'KiB' === $data_parts[1] ) {
			return $data_parts[0];
		}

		switch ( $data_parts[1] ) {
			case 'MiB':
				$data_to_return = $data_parts[0] * 1024;
				break;

			case 'GiB':
				$data_to_return = $data_parts[0] * 1048576;
				break;
		}

		return number_format( (float) $data_to_return, 2, '.', '' );

	}

	/**
	 * Gets the formatted VMSTAT data of server post for the chart.
	 *
	 * @param  int $post_id Server ID.
	 * @return array
	 */
	public function wpcd_app_server_get_formatted_vmstat_data( $post_id ) {
		if ( empty( $post_id ) ) {
			return array();
		}

		$vmstat_data = get_post_meta( $post_id, 'wpcd_wpapp_vmstat_data', true );

		// Remove tags from the string.
		$vmstat_data = wp_strip_all_tags( $vmstat_data );

		// If no data just return.
		if ( empty( $vmstat_data ) ) {
			return array();
		}

		// Explode string by new line character.
		$vmstat_data_parts = array_filter( preg_split( '/\r\n|\r|\n/', $vmstat_data ) );

		foreach ( $vmstat_data_parts as $key => $value ) {
			// Get only first 7 items from the array, then break.
			if ( $key > 6 ) {
				break;
			}

			$value = trim( $value );

			$value_parts = explode( ' ', $value, 3 );

			// Stores dataset value into the array.
			$chart_col_memory[] = sanitize_text_field( $value_parts[0] );

			// Stores dataset labels into the array.
			$labels[] = sanitize_text_field( $value_parts[2] );

		}

		$return = array();

		$return['chart_column_labels'] = wp_json_encode( $labels );
		$return['chart_column_memory'] = wp_json_encode( $chart_col_memory );

		return $return;

	}

}
