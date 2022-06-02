/*
 * This file is used to create chart for server statistics data on the server detail screen.
 *
 */
(function ($, wpcd_server_stat_data) {

    $(document).ready(function () {
        init();
    });

    // Set default colors for chart 
    window.chartColors = {
        red: 'rgb(255, 99, 132)',
        orange: 'rgb(255, 159, 64)',
        yellow: 'rgb(255, 205, 86)',
        green: 'rgb(75, 192, 192)',
        blue: 'rgb(54, 162, 235)',
        purple: 'rgb(153, 102, 255)',
        gray: 'rgb(201, 203, 207)'
    };

    function init() {

        initActions();

        createDiskStatChart();

        // VNSTAT chart for Current Day
        var curr_day_vnstat_data = JSON.parse(wpcd_server_stat_data.vnstat.curr_day_vnstat_data);
        createVnstatChart('wpcd-vnstat-data-day-canvas', wpcd_server_stat_data.vnstat.chart_labels.chart_curr_day_title, curr_day_vnstat_data);

        // VNSTAT chart for Current month
        var curr_month_vnstat_data = JSON.parse(wpcd_server_stat_data.vnstat.curr_month_vnstat_data);
        createVnstatChart('wpcd-vnstat-data-month-canvas', wpcd_server_stat_data.vnstat.chart_labels.chart_curr_month_title, curr_month_vnstat_data);

        // VNSTAT chart for All Time
        var all_time_vnstat_data = JSON.parse(wpcd_server_stat_data.vnstat.all_time_vnstat_data);
        createVnstatChart('wpcd-vnstat-data-all-canvas', wpcd_server_stat_data.vnstat.chart_labels.chart_all_time_title, all_time_vnstat_data);

        createVmstatChart();
    }

    function initActions() {
        // Hide text data on load
        $('#wpcd-diskstatistics-text').closest('.rwmb-row').hide();
        $('#wpcd-vnstat-text').closest('.rwmb-row').hide();
        $('#wpcd-vmstat-text').closest('.rwmb-row').hide();

        $('body').on('click', '.wpcd-stat-switch', function (e) {
            e.preventDefault();
            var $this = $(this);

            var data_section_to_hide = $this.attr('data-section-to-hide');
            var data_section_to_show = $this.attr('data-section-to-show');

            $(data_section_to_hide).closest('.rwmb-row').hide();
            $(data_section_to_show).closest('.rwmb-row').show();

            $('[data-section-to-hide="' + data_section_to_show + '"]').removeClass('active');
            $this.addClass('active');

        });
    }

    function createDiskStatChart() {

        // Holds the chart data for the Disk Statistics
        var diskStatChartData = {
            labels: JSON.parse(wpcd_server_stat_data.disk_stat.chart_column_labels),
            datasets: [{
                label: wpcd_server_stat_data.disk_stat.chart_labels.chart_column_1K_blocks,
                borderColor: window.chartColors.red,
                backgroundColor: window.chartColors.red,
                data: JSON.parse(wpcd_server_stat_data.disk_stat.chart_column_1K_blocks)
            }, {
                label: wpcd_server_stat_data.disk_stat.chart_labels.chart_column_Used,
                borderColor: window.chartColors.gray,
                backgroundColor: window.chartColors.gray,
                data: JSON.parse(wpcd_server_stat_data.disk_stat.chart_column_Used)
            }, {
                label: wpcd_server_stat_data.disk_stat.chart_labels.chart_column_Available,
                borderColor: window.chartColors.green,
                backgroundColor: window.chartColors.green,
                data: JSON.parse(wpcd_server_stat_data.disk_stat.chart_column_Available)
            }]
        };
        
        if( $('#wpcd-diskstatistics-canvas').length == 0 ) {
                return;
        }

        // Get the element for the chart to render
        var ctx = document.getElementById('wpcd-diskstatistics-canvas').getContext('2d');

        // Create the Disk Statistics chart
        window.myBar = new Chart(ctx, {
            type: 'bar',
            data: diskStatChartData,
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: wpcd_server_stat_data.disk_stat.chart_labels.chart_main_title
                },
                tooltips: {
                    mode: 'index',
                    intersect: false
                },
                hover: {
                    mode: 'nearest',
                    intersect: true
                },
                scales: {
                    yAxes: [{
                        type: 'linear',
                        display: true,
                        position: 'left',
                    }, {
                        type: 'linear',
                        display: false,
                        position: 'right',
                    }, {
                        type: 'linear',
                        display: false,
                        position: 'left',
                    }],
                }
            }
        });

    }

    /**
     * Creates the VNStat cart for the passed data
     * @param  {String} element_id  ID of the Chart canvas
     * @param  {String} chart_title Chart Title
     * @param  {Array}  chart_data  Chart Data
     * @return {Void}             
     */
    function createVnstatChart(element_id, chart_title, chart_data) {

        var vnStatChartData = {
            type: 'pie',
            data: {
                datasets: [{
                    data: chart_data,
                    backgroundColor: [
                        window.chartColors.red,
                        window.chartColors.green
                    ]
                }],
                labels: [
                    wpcd_server_stat_data.vnstat.chart_labels.chart_rx_label,
                    wpcd_server_stat_data.vnstat.chart_labels.chart_tx_label
                ]
            },
            options: {
                responsive: true,
                legend: {
                    display: true,
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: chart_title
                }
            }
        };
        
        if( $('#'+element_id).length == 0 ) {
                return;
        }
        // Get the element for the chart to render
        var ctx = document.getElementById(element_id).getContext('2d');

        // Create the VNSTAT data chart for the current day
        new Chart(ctx, vnStatChartData);

    }

    function createVmstatChart() {

        // Holds the chart data for the VMSTAT
        var vmstatBarChartData = {
            labels: JSON.parse(wpcd_server_stat_data.vmstat.chart_column_labels),
            datasets: [{
                label: wpcd_server_stat_data.vmstat.chart_labels.chart_dataset_label,
                backgroundColor: [
                    window.chartColors.red,
                    window.chartColors.orange,
                    window.chartColors.yellow,
                    window.chartColors.green,
                    window.chartColors.blue,
                    window.chartColors.purple,
                    window.chartColors.gray
                ],
                data: JSON.parse(wpcd_server_stat_data.vmstat.chart_column_memory)
            }]
        };
        
        if( $('#wpcd-vmstat-canvas').length == 0 ) {
                return;
        }

        // Get the element for the chart to render
        var ctx = document.getElementById('wpcd-vmstat-canvas').getContext('2d');

        // Create the VMSTAT chart
        window.myHorizontalBar = new Chart(ctx, {
            type: 'horizontalBar',
            data: vmstatBarChartData,
            options: {
                responsive: true,
                legend: {
                    position: 'right',
                },
                title: {
                    display: true,
                    text: wpcd_server_stat_data.vmstat.chart_labels.chart_main_title
                }
            }
        });
    }

})(jQuery, wpcd_server_stat_data);
