<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/config.php';

$pageTitle   = 'Laporan & Export';
$currentPage = 'reports';

// Load machines for select
$machines = [];
$recordCounts = [
    'oee_summary'        => 0,
    'alert_report'       => 0,
    'maintenance_report' => 0,
    'e_report'      => 0,
    'vibration_report'   => 0,
];

try {
    $db = getDB();

    $stmt = $db->query("SELECT id, name FROM machines ORDER BY name");
    $machines = $stmt->fetchAll();

    $recordCounts['oee_summary']        = (int)$db->query("SELECT COUNT(*) FROM oee_daily")->fetchColumn();
    $recordCounts['alert_report']       = (int)$db->query("SELECT COUNT(*) FROM alerts")->fetchColumn();
    $recordCounts['maintenance_report'] = (int)$db->query("SELECT COUNT(*) FROM maintenance_records")->fetchColumn();
    $recordCounts['e_report']      = (int)$db->query("SELECT COUNT(*) FROM sensor_readings")->fetchColumn();
    $recordCounts['vibration_report']   = (int)$db->query("SELECT COUNT(*) FROM vibration_readings")->fetchColumn();

} catch (Exception $e) {
    // silently continue with defaults
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-file-export mr-2"></i>Reports &amp; Export
    </h1>
    <small class="text-muted">Select a report type, configure parameters, then generate</small>
</div>

<!-- Stats Row -->
<div class="row mb-4">
    <?php
    $statCards = [
        ['label' => 'OEE Summary',   'key' => 'oee_summary',        'icon' => 'fas fa-chart-bar', 'color' => 'primary'],
        ['label' => 'Alert Report',  'key' => 'alert_report',       'icon' => 'fas fa-bell',      'color' => 'danger'],
        ['label' => 'Maintenance',   'key' => 'maintenance_report', 'icon' => 'fas fa-wrench',    'color' => 'warning'],
        ['label' => 'Energy Report', 'key' => 'e_report',      'icon' => 'fas fa-bolt',      'color' => 'success'],
    ];
    foreach ($statCards as $card):
    ?>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-<?php echo $card['color']; ?> shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-<?php echo $card['color']; ?> text-uppercase mb-1">
                            <?php echo $card['label']; ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($recordCounts[$card['key']]); ?> records
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="<?php echo $card['icon']; ?> fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Main Panel Row -->
<div class="row">

    <!-- Left Panel: Report Type List -->
    <div class="col-lg-3 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-list mr-1"></i> Report Types
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" id="reportTypeList">

                    <a href="#" class="list-group-item list-group-item-action report-type-item" data-type="oee_summary">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-chart-bar fa-lg mr-3 mt-1 text-primary"></i>
                            <div>
                                <div class="font-weight-bold">OEE Summary</div>
                                <small class="text-muted">Daily OEE value summary per machine</small>
                            </div>
                        </div>
                    </a>

                    <a href="#" class="list-group-item list-group-item-action report-type-item" data-type="alert_report">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-bell fa-lg mr-3 mt-1 text-danger"></i>
                            <div>
                                <div class="font-weight-bold">Alert Report</div>
                                <small class="text-muted">Alert history and handling status</small>
                            </div>
                        </div>
                    </a>

                    <a href="#" class="list-group-item list-group-item-action report-type-item" data-type="maintenance_report">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-wrench fa-lg mr-3 mt-1 text-warning"></i>
                            <div>
                                <div class="font-weight-bold">Maintenance Report</div>
                                <small class="text-muted">Machine maintenance and repair records</small>
                            </div>
                        </div>
                    </a>

                    <a href="#" class="list-group-item list-group-item-action report-type-item" data-type="e_report">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-bolt fa-lg mr-3 mt-1 text-success"></i>
                            <div>
                                <div class="font-weight-bold">Energy Report</div>
                                <small class="text-muted">Energy consumption data from sensors</small>
                            </div>
                        </div>
                    </a>

                    <a href="#" class="list-group-item list-group-item-action report-type-item" data-type="vibration_report">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-wave-square fa-lg mr-3 mt-1 text-info"></i>
                            <div>
                                <div class="font-weight-bold">Vibration Report</div>
                                <small class="text-muted">Machine vibration sensor readings</small>
                            </div>
                        </div>
                    </a>

                </div>
            </div>
        </div>
    </div>

    <!-- Right Panel: Config + Preview -->
    <div class="col-lg-9 mb-4">

        <!-- Default instruction state -->
        <div id="defaultState" class="card shadow">
            <div class="card-body text-center py-5">
                <i class="fas fa-arrow-left fa-3x text-gray-300 mb-3"></i>
                <h5 class="text-gray-500">&larr; Select a report type to get started</h5>
                <p class="text-muted">Click one of the report types on the left to display configuration and export options.</p>
            </div>
        </div>

        <!-- Config Panel (hidden until type selected) -->
        <div id="configPanel" class="card shadow" style="display:none;">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary" id="reportPanelTitle">
                    <i id="reportPanelIcon" class="fas fa-chart-bar mr-2"></i>
                    <span id="reportPanelTitleText">Laporan</span>
                </h6>
                <small class="text-muted" id="reportPanelDesc"></small>
            </div>
            <div class="card-body">

                <!-- Config Form -->
                <div class="row form-row mb-3">
                    <div class="col-md-3">
                        <label class="small font-weight-bold text-gray-700">From Date</label>
                        <input type="date" id="fromDate" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="small font-weight-bold text-gray-700">To Date</label>
                        <input type="date" id="toDate" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="small font-weight-bold text-gray-700">Machine</label>
                        <select id="machineSelect" class="form-control form-control-sm">
                            <option value="">All Machines</option>
                            <?php foreach ($machines as $m): ?>
                            <option value="<?php echo (int)$m['id']; ?>">
                                <?php echo htmlspecialchars($m['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="small font-weight-bold text-gray-700">Format</label>
                        <div class="mt-1">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="reportFormat" id="fmtPreview" value="preview" checked>
                                <label class="form-check-label small" for="fmtPreview">Preview</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="reportFormat" id="fmtCsv" value="csv">
                                <label class="form-check-label small" for="fmtCsv">CSV</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="reportFormat" id="fmtPrint" value="print">
                                <label class="form-check-label small" for="fmtPrint">Print</label>
                            </div>
                        </div>
                    </div>
                </div>

                <button id="btnGenerate" class="btn btn-primary btn-sm">
                    <i class="fas fa-play mr-1"></i> Generate Report
                </button>
                <span id="loadingSpinner" class="ml-2" style="display:none;">
                    <i class="fas fa-spinner fa-spin text-primary"></i> Loading data...
                </span>

            </div>
        </div>

        <!-- Preview Result -->
        <div id="previewCard" class="card shadow mt-3" style="display:none;">
            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-gray-700">
                    <i class="fas fa-table mr-1"></i> Report Results
                </h6>
                <small class="text-muted" id="previewMeta"></small>
            </div>
            <div class="card-body p-0">
                <div id="reportPreview" class="table-responsive p-3"></div>
            </div>
        </div>

    </div>
</div>

<!-- Recent Activity Row -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card shadow">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-history mr-1"></i> Generation History This Session
                </h6>
                <button class="btn btn-sm btn-outline-secondary" id="btnClearHistory">
                    <i class="fas fa-trash-alt mr-1"></i> Clear History
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" id="historyTable">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Report Type</th>
                                <th>Period</th>
                                <th>Format</th>
                                <th>Machine</th>
                                <th>Generated At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="historyBody">
                            <tr id="historyEmpty">
                                <td colspan="7" class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle mr-1"></i> No reports have been generated this session.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- ===== PAGE SCRIPTS (after footer/jQuery) ===== -->
<script>
(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /* Report type metadata                                                 */
    /* ------------------------------------------------------------------ */
    var reportMeta = {
        oee_summary: {
            title: 'OEE Summary',
            desc:  'Daily OEE value summary per machine (availability, performance, quality)',
            icon:  'fas fa-chart-bar'
        },
        alert_report: {
            title: 'Alert Report',
            desc:  'Sensor alert history and handling status',
            icon:  'fas fa-bell'
        },
        maintenance_report: {
            title: 'Maintenance Report',
            desc:  'Machine maintenance and repair work records',
            icon:  'fas fa-wrench'
        },
        e_report: {
            title: 'Energy Report',
            desc:  'Energy consumption data (voltage, current, power) from sensors',
            icon:  'fas fa-bolt'
        },
        vibration_report: {
            title: 'Vibration Report',
            desc:  'Machine vibration sensor readings',
            icon:  'fas fa-wave-square'
        }
    };

    /* ------------------------------------------------------------------ */
    /* State                                                                */
    /* ------------------------------------------------------------------ */
    var currentType    = null;
    var sessionHistory = [];
    var histCounter    = 0;

    /* ------------------------------------------------------------------ */
    /* Default dates (last 30 days)                                         */
    /* ------------------------------------------------------------------ */
    (function setDefaultDates() {
        var now  = new Date();
        var from = new Date(now);
        from.setDate(from.getDate() - 30);
        document.getElementById('toDate').value   = now.toISOString().slice(0, 10);
        document.getElementById('fromDate').value = from.toISOString().slice(0, 10);
    })();

    /* ------------------------------------------------------------------ */
    /* Maroon active style                                                  */
    /* ------------------------------------------------------------------ */
    var style = document.createElement('style');
    style.textContent =
        '.report-type-item.active { background-color: #7b1d3f !important; color: #fff !important; border-color: #7b1d3f !important; }' +
        '.report-type-item.active .text-muted { color: #f8d7da !important; }' +
        '.report-type-item.active i { color: #fff !important; }' +
        '.btn-xs { padding: .15rem .45rem; font-size: .75rem; line-height: 1.4; }';
    document.head.appendChild(style);

    /* ------------------------------------------------------------------ */
    /* Report type item click                                               */
    /* ------------------------------------------------------------------ */
    document.querySelectorAll('.report-type-item').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();

            document.querySelectorAll('.report-type-item').forEach(function (x) {
                x.classList.remove('active');
            });
            el.classList.add('active');

            currentType = el.getAttribute('data-type');
            var meta = reportMeta[currentType];

            document.getElementById('reportPanelIcon').className      = meta.icon + ' mr-2';
            document.getElementById('reportPanelTitleText').textContent = meta.title;
            document.getElementById('reportPanelDesc').textContent      = meta.desc;

            document.getElementById('defaultState').style.display  = 'none';
            document.getElementById('configPanel').style.display   = '';
            document.getElementById('previewCard').style.display   = 'none';
            document.getElementById('reportPreview').innerHTML     = '';
        });
    });

    /* ------------------------------------------------------------------ */
    /* Generate button                                                      */
    /* ------------------------------------------------------------------ */
    document.getElementById('btnGenerate').addEventListener('click', function () {
        if (!currentType) {
            alert('Please select a report type first.');
            return;
        }

        var fromDate     = document.getElementById('fromDate').value;
        var toDate       = document.getElementById('toDate').value;
        var machineId    = document.getElementById('machineSelect').value;
        var selEl        = document.getElementById('machineSelect');
        var machineLabel = selEl.options[selEl.selectedIndex].text;
        var format       = document.querySelector('input[name="reportFormat"]:checked').value;

        if (!fromDate || !toDate) {
            alert('Please fill in the from and to dates.');
            return;
        }

        // Record history entry
        histCounter++;
        var entry = {
            id:           histCounter,
            type:         currentType,
            from:         fromDate,
            to:           toDate,
            machine:      machineId,
            machineLabel: machineLabel,
            format:       format,
            generatedAt:  new Date().toLocaleString('en-US')
        };
        sessionHistory.unshift(entry);
        renderHistory();

        // Build URL
        var baseUrl = 'api/reports.php?action=' + encodeURIComponent(currentType)
                    + '&from='       + encodeURIComponent(fromDate)
                    + '&to='         + encodeURIComponent(toDate)
                    + '&machine_id=' + encodeURIComponent(machineId);

        if (format === 'csv') {
            window.location = baseUrl + '&export=csv';
            return;
        }

        if (format === 'print') {
            window.open(baseUrl + '&export=print', '_blank');
            return;
        }

        // Preview via AJAX
        var spinner = document.getElementById('loadingSpinner');
        var btn     = document.getElementById('btnGenerate');
        spinner.style.display = '';
        btn.disabled          = true;

        document.getElementById('previewCard').style.display = 'none';
        document.getElementById('reportPreview').innerHTML   = '';

        $.get(baseUrl)
            .done(function (res) {
                spinner.style.display = 'none';
                btn.disabled          = false;

                var meta = reportMeta[currentType];
                document.getElementById('previewMeta').textContent =
                    meta.title + ' | ' + fromDate + ' s/d ' + toDate + ' | ' + machineLabel;
                document.getElementById('previewCard').style.display = '';

                if (!res || !res.success) {
                    document.getElementById('reportPreview').innerHTML =
                        '<div class="alert alert-warning m-3"><i class="fas fa-exclamation-triangle mr-2"></i>' +
                        escHtml((res && res.message) || 'An error occurred.') + '</div>';
                    return;
                }

                var html = '';
                var d    = res.data;

                if (currentType === 'oee_summary') {
                    html = renderOeeSummary(d, fromDate, toDate);
                } else if (currentType === 'alert_report') {
                    html = renderAlertReport(d);
                } else if (currentType === 'e_report') {
                    html = renderEnergyReport(d);
                } else if (currentType === 'maintenance_report') {
                    html = renderMaintenanceReport(d);
                } else if (currentType === 'vibration_report') {
                    html = renderVibrationReport(d);
                } else {
                    html = '<pre class="m-3 small">' + escHtml(JSON.stringify(d, null, 2)) + '</pre>';
                }

                document.getElementById('reportPreview').innerHTML = html ||
                    '<div class="alert alert-info m-3">No data available for the selected parameters.</div>';
            })
            .fail(function (xhr) {
                spinner.style.display = 'none';
                btn.disabled          = false;
                document.getElementById('previewCard').style.display = '';
                document.getElementById('reportPreview').innerHTML =
                    '<div class="alert alert-danger m-3"><i class="fas fa-times-circle mr-2"></i>' +
                    'Failed to load report. HTTP ' + xhr.status + ' — ' + escHtml(xhr.responseText.substring(0,200)) + '</div>';
            });
    });

    /* ------------------------------------------------------------------ */
    /* Render history table                                                 */
    /* ------------------------------------------------------------------ */
    function renderHistory() {
        var tbody = document.getElementById('historyBody');

        if (sessionHistory.length === 0) {
            tbody.innerHTML =
                '<tr id="historyEmpty"><td colspan="7" class="text-center text-muted py-3">' +
                '<i class="fas fa-info-circle mr-1"></i> No reports have been generated this session.</td></tr>';
            return;
        }

        var fmtBadges = {
            preview: '<span class="badge badge-info">Preview</span>',
            csv:     '<span class="badge badge-success">CSV</span>',
            print:   '<span class="badge badge-secondary">Print</span>'
        };

        var html = '';
        sessionHistory.forEach(function (entry, idx) {
            var meta = reportMeta[entry.type] || { title: entry.type, icon: 'fas fa-file' };
            html +=
                '<tr>' +
                '<td>' + entry.id + '</td>' +
                '<td><i class="' + meta.icon + ' mr-1"></i>' + escHtml(meta.title) + '</td>' +
                '<td>' + escHtml(entry.from) + ' &mdash; ' + escHtml(entry.to) + '</td>' +
                '<td>' + (fmtBadges[entry.format] || escHtml(entry.format)) + '</td>' +
                '<td>' + escHtml(entry.machineLabel) + '</td>' +
                '<td>' + escHtml(entry.generatedAt) + '</td>' +
                '<td>' +
                    '<button class="btn btn-xs btn-outline-primary" onclick="reGenerate(' + idx + ')">' +
                    '<i class="fas fa-redo mr-1"></i>Re-generate</button>' +
                '</td>' +
                '</tr>';
        });
        tbody.innerHTML = html;
    }

    /* ------------------------------------------------------------------ */
    /* Re-generate from history                                             */
    /* ------------------------------------------------------------------ */
    window.reGenerate = function (idx) {
        var entry = sessionHistory[idx];
        if (!entry) return;

        // Activate the correct type item
        document.querySelectorAll('.report-type-item').forEach(function (el) {
            el.classList.remove('active');
            if (el.getAttribute('data-type') === entry.type) el.classList.add('active');
        });
        currentType = entry.type;

        var meta = reportMeta[currentType];
        document.getElementById('reportPanelIcon').className        = meta.icon + ' mr-2';
        document.getElementById('reportPanelTitleText').textContent  = meta.title;
        document.getElementById('reportPanelDesc').textContent       = meta.desc;
        document.getElementById('defaultState').style.display        = 'none';
        document.getElementById('configPanel').style.display         = '';

        // Restore form values
        document.getElementById('fromDate').value      = entry.from;
        document.getElementById('toDate').value        = entry.to;
        document.getElementById('machineSelect').value = entry.machine;
        document.querySelectorAll('input[name="reportFormat"]').forEach(function (r) {
            r.checked = (r.value === entry.format);
        });

        document.getElementById('btnGenerate').click();
    };

    /* ------------------------------------------------------------------ */
    /* Clear history button                                                 */
    /* ------------------------------------------------------------------ */
    document.getElementById('btnClearHistory').addEventListener('click', function () {
        sessionHistory = [];
        histCounter    = 0;
        renderHistory();
    });

    /* ------------------------------------------------------------------ */
    /* HTML escape helper                                                   */
    /* ------------------------------------------------------------------ */
    function escHtml(str) {
        return String(str == null ? '-' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function oeeClass(v) {
        v = parseFloat(v);
        return v >= 85 ? 'success' : (v >= 60 ? 'warning' : 'danger');
    }

    /* ------------------------------------------------------------------ */
    /* RENDER: OEE Summary                                                  */
    /* ------------------------------------------------------------------ */
    function renderOeeSummary(d) {
        if (!d || !d.summary || d.summary.length === 0)
            return '<div class="alert alert-info m-3">No OEE data available for the selected period.</div>';

        var h = '<div class="p-3">';
        h += '<h6 class="font-weight-bold mb-3"><i class="fas fa-table mr-2 text-primary"></i>OEE Summary per Machine</h6>';
        h += '<div class="table-responsive mb-4"><table class="table table-bordered table-sm table-hover">';
        h += '<thead class="thead-dark"><tr><th>Machine</th><th>Days</th><th>Availability</th>' +
             '<th>Performance</th><th>Quality</th><th>Avg OEE</th><th>Min OEE</th><th>Max OEE</th>' +
             '<th>Planned (min)</th><th>Run (min)</th></tr></thead><tbody>';
        d.summary.forEach(function(r) {
            var cls = oeeClass(r.avg_oee);
            h += '<tr>' +
                 '<td class="font-weight-bold">' + escHtml(r.machine_name) + '</td>' +
                 '<td class="text-center">' + r.days + '</td>' +
                 '<td class="text-center">' + r.avg_availability + '%</td>' +
                 '<td class="text-center">' + r.avg_performance + '%</td>' +
                 '<td class="text-center">' + r.avg_quality + '%</td>' +
                 '<td class="text-center"><span class="badge badge-' + cls + ' px-2">' + r.avg_oee + '%</span></td>' +
                 '<td class="text-center text-danger">' + r.min_oee + '%</td>' +
                 '<td class="text-center text-success">' + r.max_oee + '%</td>' +
                 '<td class="text-right">' + (r.total_planned || '-') + '</td>' +
                 '<td class="text-right">' + (r.total_run || '-') + '</td>' +
                 '</tr>';
        });
        h += '</tbody></table></div>';

        if (d.trend && d.trend.length > 0) {
            h += '<h6 class="font-weight-bold mb-3"><i class="fas fa-chart-line mr-2 text-primary"></i>Daily OEE Trend</h6>';
            h += '<div class="table-responsive"><table class="table table-bordered table-sm table-hover">';
            h += '<thead class="thead-light"><tr><th>Date</th><th>Availability</th><th>Performance</th><th>Quality</th><th>OEE Score</th></tr></thead><tbody>';
            d.trend.forEach(function(r) {
                h += '<tr><td>' + escHtml(r.snap_date) + '</td>' +
                     '<td class="text-center">' + r.availability + '%</td>' +
                     '<td class="text-center">' + r.performance + '%</td>' +
                     '<td class="text-center">' + r.quality + '%</td>' +
                     '<td class="text-center"><span class="badge badge-' + oeeClass(r.oee_score) + '">' + r.oee_score + '%</span></td></tr>';
            });
            h += '</tbody></table></div>';
        }
        h += '</div>';
        return h;
    }

    /* ------------------------------------------------------------------ */
    /* RENDER: Alert Report                                                 */
    /* ------------------------------------------------------------------ */
    function renderAlertReport(d) {
        if (!d || !d.list || d.list.length === 0)
            return '<div class="alert alert-info m-3">No alerts for the selected period.</div>';

        var total = 0, critical = 0, warning = 0;
        (d.stats || []).forEach(function(s) {
            total += parseInt(s.total);
            if (s.severity === 'critical') critical = parseInt(s.total);
            if (s.severity === 'warning')  warning  = parseInt(s.total);
        });

        var h = '<div class="p-3">';
        h += '<div class="row mb-3">' +
             '<div class="col-auto"><div class="card border-left-primary shadow-sm py-2 px-3"><div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Alerts</div><div class="h5 mb-0 font-weight-bold">' + total + '</div></div></div>' +
             '<div class="col-auto"><div class="card border-left-danger shadow-sm py-2 px-3"><div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Critical</div><div class="h5 mb-0 font-weight-bold text-danger">' + critical + '</div></div></div>' +
             '<div class="col-auto"><div class="card border-left-warning shadow-sm py-2 px-3"><div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Warning</div><div class="h5 mb-0 font-weight-bold text-warning">' + warning + '</div></div></div>' +
             '</div>';

        if (d.by_machine && d.by_machine.length > 0) {
            h += '<h6 class="font-weight-bold mb-2"><i class="fas fa-cogs mr-2 text-danger"></i>Alerts per Machine</h6>';
            h += '<div class="table-responsive mb-4"><table class="table table-sm table-bordered table-hover">';
            h += '<thead class="thead-light"><tr><th>Machine</th><th>Total</th><th>Critical</th><th>Warning</th></tr></thead><tbody>';
            d.by_machine.forEach(function(r) {
                h += '<tr><td>' + escHtml(r.machine_name) + '</td><td><b>' + r.alert_count + '</b></td>' +
                     '<td><span class="badge badge-danger">' + r.critical + '</span></td>' +
                     '<td><span class="badge badge-warning">' + r.warning + '</span></td></tr>';
            });
            h += '</tbody></table></div>';
        }

        h += '<h6 class="font-weight-bold mb-2"><i class="fas fa-list mr-2 text-danger"></i>Alert List</h6>';
        h += '<div class="table-responsive"><table class="table table-sm table-bordered table-hover">';
        h += '<thead class="thead-light"><tr><th>#</th><th>Machine</th><th>Sensor</th><th>Value</th><th>Severity</th><th>Status</th><th>Time</th></tr></thead><tbody>';
        d.list.forEach(function(r) {
            var sevCls = r.severity === 'critical' ? 'danger' : (r.severity === 'warning' ? 'warning' : 'info');
            var ackBadge = r.acknowledged
                ? '<span class="badge badge-success">Acknowledged</span>'
                : '<span class="badge badge-secondary">Pending</span>';
            h += '<tr><td class="small text-muted">' + r.id + '</td>' +
                 '<td>' + escHtml(r.machine_name) + '</td>' +
                 '<td><code>' + escHtml(r.sensor_key) + '</code></td>' +
                 '<td class="text-right">' + parseFloat(r.sensor_value).toFixed(2) + '</td>' +
                 '<td><span class="badge badge-' + sevCls + '">' + escHtml(r.severity) + '</span></td>' +
                 '<td>' + ackBadge + '</td>' +
                 '<td class="small">' + escHtml(r.created_at) + '</td></tr>';
        });
        h += '</tbody></table></div></div>';
        return h;
    }

    /* ------------------------------------------------------------------ */
    /* RENDER: Energy Report                                                */
    /* ------------------------------------------------------------------ */
    function renderEnergyReport(d) {
        if (!d || !d.summary || d.summary.length === 0)
            return '<div class="alert alert-info m-3">No energy data available for the selected period.</div>';

        var h = '<div class="p-3">';
        h += '<h6 class="font-weight-bold mb-3"><i class="fas fa-bolt mr-2 text-warning"></i>Energy Summary per Machine</h6>';
        h += '<div class="table-responsive mb-4"><table class="table table-bordered table-sm table-hover">';
        h += '<thead class="thead-dark"><tr><th>Machine</th><th>Readings</th><th>Avg kWh</th>' +
             '<th>Total kWh</th><th>Avg Voltage (V)</th><th>Avg Current (A)</th>' +
             '<th>Avg Temp (°C)</th><th>Avg Hum (%)</th></tr></thead><tbody>';
        d.summary.forEach(function(r) {
            h += '<tr><td class="font-weight-bold">' + escHtml(r.machine_name) + '</td>' +
                 '<td class="text-center">' + r.readings + '</td>' +
                 '<td class="text-right">' + r.avg_total_kwh + '</td>' +
                 '<td class="text-right font-weight-bold">' + r.sum_total_kwh + '</td>' +
                 '<td class="text-center">' + r.avg_voltage + '</td>' +
                 '<td class="text-center">' + r.avg_current + '</td>' +
                 '<td class="text-center">' + r.avg_temp + '</td>' +
                 '<td class="text-center">' + r.avg_hum + '</td></tr>';
        });
        h += '</tbody></table></div>';

        if (d.trend && d.trend.length > 0) {
            h += '<h6 class="font-weight-bold mb-2"><i class="fas fa-chart-area mr-2 text-warning"></i>Daily Energy Trend</h6>';
            h += '<div class="table-responsive"><table class="table table-sm table-bordered table-hover">';
            h += '<thead class="thead-light"><tr><th>Date</th><th>Avg kWh</th><th>Total kWh</th></tr></thead><tbody>';
            d.trend.forEach(function(r) {
                h += '<tr><td>' + escHtml(r.day) + '</td><td class="text-right">' + r.avg_kwh + '</td><td class="text-right font-weight-bold">' + r.sum_kwh + '</td></tr>';
            });
            h += '</tbody></table></div>';
        }
        h += '</div>';
        return h;
    }

    /* ------------------------------------------------------------------ */
    /* RENDER: Maintenance Report                                           */
    /* ------------------------------------------------------------------ */
    function renderMaintenanceReport(d) {
        if (!d || !d.records || d.records.length === 0)
            return '<div class="alert alert-info m-3">No maintenance data available for the selected period.</div>';

        var h = '<div class="p-3">';
        h += '<h6 class="font-weight-bold mb-3"><i class="fas fa-wrench mr-2 text-warning"></i>Maintenance History</h6>';
        h += '<div class="table-responsive"><table class="table table-sm table-bordered table-hover">';
        h += '<thead class="thead-dark"><tr><th>Date</th><th>Machine</th><th>Type</th>' +
             '<th>Technician</th><th>Duration (min)</th><th>Description</th></tr></thead><tbody>';
        d.records.forEach(function(r) {
            h += '<tr><td>' + escHtml(r.maint_date) + '</td>' +
                 '<td>' + escHtml(r.machine_name) + '</td>' +
                 '<td><span class="badge badge-info">' + escHtml(r.type) + '</span></td>' +
                 '<td>' + escHtml(r.technician) + '</td>' +
                 '<td class="text-center">' + (r.duration_min || '-') + '</td>' +
                 '<td class="small text-muted">' + escHtml(r.description) + '</td></tr>';
        });
        h += '</tbody></table></div></div>';
        return h;
    }

    /* ------------------------------------------------------------------ */
    /* RENDER: Vibration Report                                             */
    /* ------------------------------------------------------------------ */
    function renderVibrationReport(d) {
        if (!d || !d.readings || d.readings.length === 0)
            return '<div class="alert alert-info m-3">No vibration data available for the selected period.</div>';

        var h = '<div class="p-3">';
        h += '<h6 class="font-weight-bold mb-3"><i class="fas fa-wave-square mr-2 text-info"></i>Vibration Data</h6>';
        h += '<div class="table-responsive"><table class="table table-sm table-bordered table-hover">';
        h += '<thead class="thead-dark"><tr><th>Time</th><th>Machine</th><th>Sensor 1</th>' +
             '<th>Sensor 2</th><th>Sensor 3</th><th>RMS</th><th>Status</th></tr></thead><tbody>';
        d.readings.forEach(function(r) {
            var stCls = r.status === 'normal' ? 'success' : (r.status === 'warning' ? 'warning' : 'danger');
            h += '<tr><td class="small">' + escHtml(r.recorded_at) + '</td>' +
                 '<td>' + escHtml(r.machine_name) + '</td>' +
                 '<td class="text-right">' + parseFloat(r.sensor_1||0).toFixed(2) + '</td>' +
                 '<td class="text-right">' + parseFloat(r.sensor_2||0).toFixed(2) + '</td>' +
                 '<td class="text-right">' + parseFloat(r.sensor_3||0).toFixed(2) + '</td>' +
                 '<td class="text-right font-weight-bold">' + parseFloat(r.rms_overall||0).toFixed(2) + '</td>' +
                 '<td><span class="badge badge-' + stCls + '">' + escHtml(r.status) + '</span></td></tr>';
        });
        h += '</tbody></table></div></div>';
        return h;
    }

})();
</script>
