/**
 * Dashboard Scripts
 */
FixAltText = (typeof FixAltText === 'undefined') ? {} : FixAltText;

// Use jQuery shorthand
(function ($) {

    FixAltText.dashboard = {

        /**
         * Houses all the chart objects for the dashboard
         */
        charts: {
            altTextIssues: false
        },

        /**
         * Set listeners when the script loads
         *
         * @package FixAltText
         * @since 1.0.0
         */
        init: function () {

            FixAltText.dashboard.updateProgressBar(0);

            let scan = $('#scan');

            // Start Scan
            scan.on('click', '.scan-link.start', FixAltText.dashboard.startScan);

            // Cancel Scan
            scan.on('click', '#cancel-scan', FixAltText.dashboard.cancelScan);

            // Pause Scan
            scan.on('click', '#pause-scan', FixAltText.dashboard.pauseScan);

            // Resume Scan
            scan.on('click', '#resume-scan', FixAltText.dashboard.resumeScan);

            // Initially Draw Charts
            FixAltText.dashboard.drawCharts();

            // Redraw Charts On Metabox Rearrange
            $(document).on('postbox-moved', FixAltText.dashboard.detectRedraw);

            $('#detected_issues').on('click', '.dashboard-start-scan', FixAltText.dashboard.startScanAlias);

        },

        drawCharts: function () {

            const issueChart = $("#issues-chart");

            if(issueChart.length == 0){
                return;
            }

            let chartData = String(issueChart.data('data'));
            chartData = chartData.split("|");

            let chartLabels = String(issueChart.data('labels'));
            chartLabels = chartLabels.split("|");

            let chartColors = String(issueChart.data('backgroundcolor'));
            chartColors = chartColors.split("|");

            FixAltText.dashboard.charts.altTextIssues = new Chart(issueChart, {
                type: "doughnut",
                data: {
                    labels: chartLabels,
                    datasets: [{
                        data: chartData,
                        backgroundColor: chartColors
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },

        reDrawCharts: function () {
            FixAltText.dashboard.charts.altTextIssues.destroy();

            FixAltText.dashboard.drawCharts();
        },

        detectRedraw: function (item) {
            FixAltText.dashboard.reDrawCharts();
        },

        /**
         * User cancels a scan
         *
         * @package FixAltText
         * @since 1.0.0
         */
        cancelScan: function () {

            let link = $(this);
            let nonce = link.data('nonce');
            let target = link.closest('.inside'); // All the content of the metabox

            let scanType = link.data('scan-type');
            let scanPortion = link.data('scan-portion');
            let scanSubPortions = link.data('scan-sub-portions');
            let scanSpecific = link.data('scan-specific');

            target.fadeOut(0, function () {
                $(this).html('<span class="loading-text"><span class="dashicons spin dashicons-update-alt"></span>Cancelling Scan...</span>').fadeIn(200);
            });

            $.ajax({
                type: "post",
                dataType: "html",
                url: FixAltTextAjax.ajaxURL,
                data: {
                    action: 'fixalttext_scan_cancel',
                    nonce: nonce,
                    scan_type: scanType,
                    scan_portion: scanPortion,
                    scan_sub_portions: scanSubPortions,
                    scan_specific: scanSpecific
                },
                success: function (json) {
                    let response = JSON.parse(json);

                    target.fadeOut(0, function () {

                        $(this).html(response.html);
                        $(this).fadeIn(200);

                    });
                },
                fail: function () {
                    target.html("Ajax failed. Please try again.");
                }
            });

        },

        /**
         * User pauses a scan
         *
         * @package FixAltText
         * @since 1.5.0
         */
        pauseScan: function () {

            let link = $(this);
            let nonce = link.data('nonce');
            let target = link.closest('.inside'); // All the content of the metabox

            let scanType = link.data('scan-type');
            let scanPortion = link.data('scan-portion');
            let scanSubPortions = link.data('scan-sub-portions');
            let scanSpecific = link.data('scan-specific');

            target.data('scan-type', scanType);
            target.data('scan-portion', scanPortion);
            target.data('scan-sub-portions', scanSubPortions);
            target.data('scan-specific', scanSpecific);

            target.fadeOut(0, function () {
                $(this).html('<span class="loading-text"><span class="dashicons spin dashicons-update-alt"></span>Pausing Scan...</span>').fadeIn(200);
            });

            $.ajax({
                type: "post",
                dataType: "html",
                url: FixAltTextAjax.ajaxURL,
                data: {
                    action: 'fixalttext_scan_pause',
                    nonce: nonce,
                    scan_type: scanType,
                    scan_portion: scanPortion,
                    scan_sub_portions: scanSubPortions,
                    scan_specific: scanSpecific
                },
                success: function (json) {
                    let response = JSON.parse(json);

                    target.fadeOut(0, function () {

                        $(this).html(response.html);
                        $(this).fadeIn(200);

                    });
                },
                fail: function () {
                    target.html("Ajax failed. Please try again.");
                }
            });

        },

        /**
         * User resumes a scan
         *
         * @package FixAltText
         * @since 1.5.0
         */
        resumeScan: function () {

            let link = $(this);
            let nonce = link.data('nonce');
            let target = link.closest('.inside'); // All the content of the metabox

            let scanType = link.data('scan-type');
            let scanPortion = link.data('scan-portion');
            let scanSubPortions = link.data('scan-sub-portions');
            let scanSpecific = link.data('scan-specific');

            target.data('scan-type', scanType);
            target.data('scan-portion', scanPortion);
            target.data('scan-sub-portions', scanSubPortions);
            target.data('scan-specific', scanSpecific);

            target.fadeOut(0, function () {
                $(this).html('<span class="loading-text"><span class="dashicons spin dashicons-update"></span>Resuming Scan...</span>').fadeIn(200);
            });

            $.ajax({
                type: "post",
                dataType: "html",
                url: FixAltTextAjax.ajaxURL,
                data: {
                    action: 'fixalttext_scan_resume',
                    nonce: nonce,
                    scan_type: scanType,
                    scan_portion: scanPortion,
                    scan_sub_portions: scanSubPortions,
                    scan_specific: scanSpecific
                },
                success: function (json) {
                    let response = JSON.parse(json);

                    target.fadeOut(0, function () {

                        $(this).html(response.html);
                        $(this).fadeIn(200);

                        // Update Progress Bar
                        FixAltText.dashboard.updateProgressBar();

                    });
                },
                fail: function () {
                    target.html("Ajax failed. Please try again.");
                }
            });

        },

        /**
         * User initiates a scan
         *
         * @package FixAltText
         * @since 1.0.0
         */
        startScan: function (e) {

            e.preventDefault();

            let link = $(this);
            let nonce = link.data('nonce');
            let target = link.closest('.inside'); // All the content of the metabox

            let scanType = link.data('scan-type');
            let scanPortion = link.data('scan-portion');
            let scanSubPortions = link.data('scan-sub-portions');
            let scanSpecific = link.data('scan-specific');

            target.data('scan-type', scanType);
            target.data('scan-portion', scanPortion);
            target.data('scan-sub-portions', scanSubPortions);
            target.data('scan-specific', scanSpecific);

            target.fadeOut(0, function () {
                $(this).html('<span class="loading-text"><span class="dashicons spin dashicons-update"></span>Starting Scan...</span>').fadeIn(200);
            });

            $.ajax({
                type: "post",
                dataType: "html",
                url: FixAltTextAjax.ajaxURL,
                data: {
                    action: 'fixalttext_scan_start',
                    nonce: nonce,
                    scan_type: scanType,
                    scan_portion: scanPortion,
                    scan_sub_portions: scanSubPortions,
                    scan_specific: scanSpecific
                },
                success: function (json) {
                    let response = JSON.parse(json);
                    target.fadeOut(0, function () {

                        $(this).html(response.html);
                        $(this).fadeIn(200);

                        // Update Progress Bar
                        FixAltText.dashboard.updateProgressBar();

                    });
                },
                fail: function () {
                    target.html("Ajax failed. Please try again.");
                }
            });

        },

        startScanAlias: function (e) {

            $('#detected_issues').remove();
            $('#scan .scan-link').trigger('click');

        },

        /**
         * Grabs an updated progress bar
         *
         * @package FixAltText
         * @since 1.0.0
         */
        updateProgressBar: function (timeout = 5000) {

            setTimeout(function () {

                let metabox = $('#scan');
                let progressBar = metabox.find('#progress-bar');

                if (progressBar.length == 0) {
                    // bail
                    return;
                }

                let target = metabox.find('.inside'); // All the content of the metabox
                let scanType = target.data('scan-type');
                let scanPortion = target.data('scan-portion');
                let scanSubPortions = target.data('scan-sub-portions');
                let scanSpecific = target.data('scan-specific');

                $.ajax({
                    type: "post",
                    dataType: "html",
                    url: FixAltTextAjax.ajaxURL,
                    data: {
                        action: 'fixalttext_scan_progress_bar',
                        progress_only: true,
                        scan_type: scanType,
                        scan_portion: scanPortion,
                        scan_sub_portions: scanSubPortions,
                        scan_specific: scanSpecific
                    },
                    success: function (json) {

                        let progress = JSON.parse(json);
                        let currentPercent = progress.percent;
                        let metabox = $('#scan');
                        let progressBar = metabox.find('#progress-bar');
                        let currently = progress.currently;

                        // Currently Scanning
                        if ( currently == '' ){
                            progressBar.find('.currently').html('...');
                        } else {
                            progressBar.find('.currently').html(currently);
                        }

                        // Updated Progress
                        progressBar.find('.percent').html(currentPercent + '%');
                        progressBar.find('.current-progress').css('width', currentPercent + '%');

                        if (100 == currentPercent) {
                            // We are finished!
                            target.find('.scan-message').html('Dashboard will refresh shortly.');
                            progressBar.find('.text').html('Finishing up the scan...');
                        }

                        if (progress.endDate) {
                            $('html head').append('<meta http-equiv="refresh" content="3">');
                        } else {

                            if( ! progressBar.find('.current-progress').hasClass('paused-progress') ) {
                                // Update progress bar
                                FixAltText.dashboard.updateProgressBar();
                            }

                        }

                    },
                    fail: function () {
                        target.html("Ajax failed. Please try again.");
                    }
                });
            }, timeout);

        }
    }

    /**
     * Wait until document loads before adding listeners / calling functions
     */
    $(document).ready(function () {
        // Set Listeners
        FixAltText.dashboard.init();
    });

})(jQuery);