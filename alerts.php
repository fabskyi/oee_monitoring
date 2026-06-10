<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';

$pageTitle = 'Alert';
$currentPage = 'alerts';

$db = getDB();

// --- Filters from GET ---
$filterFrom     = $_GET['from']       ?? date('Y-m-01');
$filterTo       = $_GET['to']         ?? date('Y-m-d');
$filterMachine  = isset($_GET['machine_id']) && $_GET['machine_id'] !== '' ? (int)$_GET['machine_id'] : null;
$filterSeverity = $_GET['severity']   ?? 'all';
$filterStatus   = $_GET['status']     ?? 'all';

// --- Stat Cards ---
$stmtTotal = $db->query("SELECT COUNT(*) FROM alerts WHERE DATE(created_at) = CURDATE()");
$statTotal = (int)$stmtTotal->fetchColumn();

$stmtCritical = $db->query("SELECT COUNT(*) FROM alerts WHERE acknowledged = 0 AND severity = 'critical'");
$statCritical = (int)$stmtCritical->fetchColumn();

$stmtWarning = $db->query("SELECT COUNT(*) FROM alerts WHERE acknowledged = 0 AND severity = 'warning'");
$statWarning = (int)$stmtWarning->fetchColumn();

$stmtUnack = $db->query("SELECT COUNT(*) FROM alerts WHERE acknowledged = 0");
$statUnack = (int)$stmtUnack->fetchColumn();

// --- Machines for filter dropdown ---
$machines = $db->query("SELECT id, name FROM machines ORDER BY name")->fetchAll();

// --- Main query with filters ---
$sql = "SELECT a.*, m.name AS machine_name
        FROM alerts a
        LEFT JOIN machines m ON a.machine_id = m.id
        WHERE DATE(a.created_at) BETWEEN :from AND :to";
$params = [':from' => $filterFrom, ':to' => $filterTo];

if ($filterMachine !== null) {
    $sql .= " AND a.machine_id = :machine_id";
    $params[':machine_id'] = $filterMachine;
}
if ($filterSeverity !== 'all') {
    $sql .= " AND a.severity = :severity";
    $params[':severity'] = $filterSeverity;
}
if ($filterStatus === 'acknowledged') {
    $sql .= " AND a.acknowledged = 1";
} elseif ($filterStatus === 'unacknowledged') {
    $sql .= " AND a.acknowledged = 0";
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$alerts = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-bell mr-2"></i>Manajemen Alert
    </h1>
    <div>
        <button id="btnAckSelected" class="btn btn-warning btn-sm shadow-sm mr-2" disabled>
            <i class="fas fa-check-double fa-sm mr-1"></i>Acknowledge Selected
        </button>
        <a href="#" id="btnExportCsv" class="btn btn-success btn-sm shadow-sm">
            <i class="fas fa-file-csv fa-sm mr-1"></i>Export CSV
        </a>
    </div>
</div>

<!-- Stat Cards Row -->
<div class="row mb-4">
    <!-- Total Hari Ini -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Hari Ini</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statTotal; ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-calendar fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Critical -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Critical (Unack)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statCritical; ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Warning -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Warning (Unack)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statWarning; ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-exclamation-circle fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Unacknowledged -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Unacknowledged</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statUnack; ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-bell fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i>Filter Alert</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="alerts.php" id="filterForm">
            <div class="form-row align-items-end">
                <div class="form-group col-md-2 mb-2">
                    <label class="small font-weight-bold">Dari Tanggal</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterFrom); ?>">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small font-weight-bold">Sampai Tanggal</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterTo); ?>">
                </div>
                <div class="form-group col-md-3 mb-2">
                    <label class="small font-weight-bold">Mesin</label>
                    <select name="machine_id" class="form-control form-control-sm">
                        <option value="">-- Semua Mesin --</option>
                        <?php foreach ($machines as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo $filterMachine == $m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small font-weight-bold">Severity</label>
                    <select name="severity" class="form-control form-control-sm">
                        <option value="all" <?php echo $filterSeverity === 'all' ? 'selected' : ''; ?>>Semua</option>
                        <option value="warning" <?php echo $filterSeverity === 'warning' ? 'selected' : ''; ?>>Warning</option>
                        <option value="critical" <?php echo $filterSeverity === 'critical' ? 'selected' : ''; ?>>Critical</option>
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small font-weight-bold">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>Semua</option>
                        <option value="acknowledged" <?php echo $filterStatus === 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                        <option value="unacknowledged" <?php echo $filterStatus === 'unacknowledged' ? 'selected' : ''; ?>>Unacknowledged</option>
                    </select>
                </div>
                <div class="form-group col-md-1 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm btn-block">
                        <i class="fas fa-search fa-sm"></i> Cari
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Alerts DataTable Card -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-table mr-1"></i>Daftar Alert</h6>
        <span class="badge badge-secondary"><?php echo count($alerts); ?> record</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm" id="alertsTable" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th width="30">
                            <input type="checkbox" id="selectAll" title="Select All">
                        </th>
                        <th>#</th>
                        <th>Waktu</th>
                        <th>Mesin</th>
                        <th>Sensor</th>
                        <th>Nilai</th>
                        <th>Batas Lo-Hi</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Acknowledged By</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alerts as $i => $alert): ?>
                        <?php
                        $rowClass = '';
                        if ($alert['severity'] === 'critical') $rowClass = 'table-danger';
                        elseif ($alert['severity'] === 'warning') $rowClass = 'table-warning';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td class="text-center">
                                <?php if (!$alert['acknowledged']): ?>
                                    <input type="checkbox" class="row-checkbox" value="<?php echo $alert['id']; ?>">
                                <?php endif; ?>
                            </td>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars($alert['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($alert['machine_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($alert['sensor_key']); ?></td>
                            <td><?php echo htmlspecialchars($alert['sensor_value']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($alert['threshold_lo'] ?? '-'); ?>
                                &mdash;
                                <?php echo htmlspecialchars($alert['threshold_hi'] ?? '-'); ?>
                            </td>
                            <td class="text-center">
                                <?php if ($alert['severity'] === 'critical'): ?>
                                    <span class="badge badge-danger">Critical</span>
                                <?php else: ?>
                                    <span class="badge badge-warning text-dark">Warning</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($alert['acknowledged']): ?>
                                    <span class="badge badge-success">Acknowledged</span>
                                <?php else: ?>
                                    <span class="badge badge-warning text-dark">Unacknowledged</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($alert['acknowledged'] && $alert['acknowledged_by']): ?>
                                    <?php echo htmlspecialchars($alert['acknowledged_by']); ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($alert['acknowledged_at'] ?? ''); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!$alert['acknowledged']): ?>
                                    <button class="btn btn-sm btn-primary btn-ack"
                                        data-id="<?php echo $alert['id']; ?>"
                                        data-machine="<?php echo htmlspecialchars($alert['machine_name'] ?? 'N/A', ENT_QUOTES); ?>"
                                        data-sensor="<?php echo htmlspecialchars($alert['sensor_key'], ENT_QUOTES); ?>"
                                        data-value="<?php echo htmlspecialchars($alert['sensor_value'], ENT_QUOTES); ?>"
                                        data-severity="<?php echo htmlspecialchars($alert['severity'], ENT_QUOTES); ?>">
                                        <i class="fas fa-check fa-sm mr-1"></i>Acknowledge
                                    </button>
                                <?php else: ?>
                                    <span class="text-success"><i class="fas fa-check-circle"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Acknowledge Modal -->
<div class="modal fade" id="ackModal" tabindex="-1" role="dialog" aria-labelledby="ackModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="ackModalLabel"><i class="fas fa-check-circle mr-2"></i>Konfirmasi Acknowledge</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Anda akan mengakui alert berikut:</p>
                <table class="table table-sm table-bordered">
                    <tr><th width="100">Mesin</th><td id="ackMachine">-</td></tr>
                    <tr><th>Sensor</th><td id="ackSensor">-</td></tr>
                    <tr><th>Nilai</th><td id="ackValue">-</td></tr>
                    <tr><th>Severity</th><td id="ackSeverity">-</td></tr>
                </table>
                <input type="hidden" id="ackAlertId" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnConfirmAck">
                    <i class="fas fa-check mr-1"></i>Konfirmasi
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div aria-live="polite" aria-atomic="true" style="position:fixed; bottom:1rem; right:1rem; z-index:9999; min-width:280px;">
    <div id="toastContainer"></div>
</div>

<?php
$jsFrom     = json_encode($filterFrom);
$jsTo       = json_encode($filterTo);
$jsMachine  = json_encode($filterMachine !== null ? (string)$filterMachine : '');
$jsSeverity = json_encode($filterSeverity !== 'all' ? $filterSeverity : '');
$jsStatus   = json_encode($filterStatus !== 'all' ? $filterStatus : '');
?>

<script>
$(document).ready(function () {

    // ---- DataTable init ----
    safeDataTable('#alertsTable', {
        order: [[2, 'desc']],
        columnDefs: [{ orderable: false, targets: [0, 10] }],
        pageLength: 25
    });
    var table = $.fn.DataTable.isDataTable('#alertsTable') ? $('#alertsTable').DataTable() : null;

    // ---- showToast ----
    function showToast(message, type) {
        type = type || 'success';
        var bgClass = type === 'success' ? 'bg-success' : (type === 'danger' ? 'bg-danger' : 'bg-warning');
        var id = 'toast_' + Date.now();
        var html = '<div id="' + id + '" class="toast text-white ' + bgClass + ' border-0 mb-2" role="alert" data-delay="3500">'
                 + '<div class="toast-header ' + bgClass + ' text-white">'
                 + '<strong class="mr-auto"><i class="fas fa-bell mr-1"></i>Notifikasi</strong>'
                 + '<button type="button" class="ml-2 mb-1 close text-white" data-dismiss="toast">&times;</button>'
                 + '</div><div class="toast-body">' + message + '</div></div>';
        $('#toastContainer').append(html);
        $('#' + id).toast('show');
        $('#' + id).on('hidden.bs.toast', function () { $(this).remove(); });
    }

    // ---- Select All ----
    $('#selectAll').on('change', function () {
        var checked = $(this).prop('checked');
        // Apply only to visible (current page) rows via DataTable nodes
        table.rows({ search: 'applied', page: 'current' }).nodes().to$().find('.row-checkbox').prop('checked', checked);
        updateBulkBtn();
    });

    $(document).on('change', '.row-checkbox', function () {
        updateBulkBtn();
        if (!$(this).prop('checked')) {
            $('#selectAll').prop('checked', false);
        } else if ($('.row-checkbox:not(:checked)').length === 0) {
            $('#selectAll').prop('checked', true);
        }
    });

    function updateBulkBtn() {
        var count = $('.row-checkbox:checked').length;
        var $btn = $('#btnAckSelected');
        $btn.prop('disabled', count === 0);
        if (count > 0) {
            $btn.html('<i class="fas fa-check-double fa-sm mr-1"></i>Acknowledge Selected (' + count + ')');
        } else {
            $btn.html('<i class="fas fa-check-double fa-sm mr-1"></i>Acknowledge Selected');
        }
    }

    // ---- Single Acknowledge ----
    $(document).on('click', '.btn-ack', function () {
        var btn = $(this);
        $('#ackAlertId').val(btn.data('id'));
        $('#ackMachine').text(btn.data('machine'));
        $('#ackSensor').text(btn.data('sensor'));
        $('#ackValue').text(btn.data('value'));
        var sev = btn.data('severity');
        var sevBadge = sev === 'critical'
            ? '<span class="badge badge-danger">Critical</span>'
            : '<span class="badge badge-warning text-dark">Warning</span>';
        $('#ackSeverity').html(sevBadge);
        $('#ackModal').modal('show');
    });

    // ---- Confirm single acknowledge ----
    $('#btnConfirmAck').on('click', function () {
        var id = $('#ackAlertId').val();
        if (!id) return;
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Memproses...');

        $.ajax({
            url: 'api/alerts.php?action=acknowledge',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: parseInt(id) }),
            dataType: 'json',
            success: function () {
                $('#ackModal').modal('hide');
                showToast('Alert berhasil di-acknowledge.', 'success');
                setTimeout(function () { location.reload(); }, 1200);
            },
            error: function () {
                $('#ackModal').modal('hide');
                showToast('Gagal acknowledge alert. Silakan coba lagi.', 'danger');
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Konfirmasi');
            }
        });
    });

    // ---- Bulk acknowledge ----
    $('#btnAckSelected').on('click', function () {
        var ids = [];
        $('.row-checkbox:checked').each(function () {
            ids.push(parseInt($(this).val()));
        });
        if (ids.length === 0) return;
        if (!confirm('Acknowledge ' + ids.length + ' alert yang dipilih?')) return;

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Memproses...');

        $.ajax({
            url: 'api/alerts.php?action=acknowledge_bulk',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ ids: ids }),
            dataType: 'json',
            success: function () {
                showToast(ids.length + ' alert berhasil di-acknowledge.', 'success');
                setTimeout(function () { location.reload(); }, 1200);
            },
            error: function () {
                showToast('Gagal bulk acknowledge. Silakan coba lagi.', 'danger');
                $btn.prop('disabled', false).html('<i class="fas fa-check-double fa-sm mr-1"></i>Acknowledge Selected');
            }
        });
    });

    // ---- Export CSV ----
    $('#btnExportCsv').on('click', function (e) {
        e.preventDefault();
        var from     = <?php echo $jsFrom; ?>;
        var to       = <?php echo $jsTo; ?>;
        var machine  = <?php echo $jsMachine; ?>;
        var severity = <?php echo $jsSeverity; ?>;
        var status   = <?php echo $jsStatus; ?>;

        var url = 'api/reports.php?action=alert_report&export=csv';
        if (from)     url += '&from='      + encodeURIComponent(from);
        if (to)       url += '&to='        + encodeURIComponent(to);
        if (machine)  url += '&machine_id='+ encodeURIComponent(machine);
        if (severity) url += '&severity='  + encodeURIComponent(severity);
        if (status)   url += '&status='    + encodeURIComponent(status);

        window.location = url;
    });

});
</script>

<?php require_once 'includes/footer.php'; ?>
