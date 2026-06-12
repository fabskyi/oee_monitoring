<?php
require_once 'includes/auth_check.php';
$currentPage = 'esp32';
$pageTitle   = 'ESP32 Monitor';

$conn = new mysqli('localhost', 'root', '', 'oee_monitoring');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// ── Mark offline devices (last_seen > 60s ago) ───────────────────────────────
$conn->query("UPDATE esp32_devices SET status='offline' WHERE last_seen < DATE_SUB(NOW(), INTERVAL 60 SECOND)");

// ── Stat cards ────────────────────────────────────────────────────────────────
$statOnline  = 0;
$statOffline = 0;
$lastDataReceived = '-';

$res = $conn->query("
    SELECT
        SUM(CASE WHEN last_seen >= DATE_SUB(NOW(), INTERVAL 60 SECOND) THEN 1 ELSE 0 END) AS cnt_online,
        SUM(CASE WHEN last_seen <  DATE_SUB(NOW(), INTERVAL 60 SECOND) OR last_seen IS NULL THEN 1 ELSE 0 END) AS cnt_offline
    FROM esp32_devices
");
if ($res && $row = $res->fetch_assoc()) {
    $statOnline  = (int)($row['cnt_online']  ?? 0);
    $statOffline = (int)($row['cnt_offline'] ?? 0);
}

$res2 = $conn->query("SELECT MAX(recorded_at) AS last_rec FROM sensor_readings");
if ($res2 && $row2 = $res2->fetch_assoc()) {
    $lastDataReceived = $row2['last_rec'] ?? '-';
}

// ── Device list with latest sensor per device's machine ──────────────────────
$devices = [];
$res3 = $conn->query("
    SELECT
        e.id, e.device_id, e.machine_id, m.name AS machine_name,
        e.ip_address, e.mac_address, e.firmware_version,
        e.last_seen, e.status,
        s.temp_panel, s.hum_panel, s.v_r, s.a_r
    FROM esp32_devices e
    LEFT JOIN machines m ON e.machine_id = m.id
    LEFT JOIN (
        SELECT sr.*
        FROM sensor_readings sr
        INNER JOIN (
            SELECT machine_id, MAX(recorded_at) AS max_rec
            FROM sensor_readings
            GROUP BY machine_id
        ) latest ON sr.machine_id = latest.machine_id AND sr.recorded_at = latest.max_rec
    ) s ON s.machine_id = e.machine_id
    ORDER BY e.status ASC, e.device_id ASC
");
if ($res3) {
    while ($row3 = $res3->fetch_assoc()) {
        $isOnline = false;
        if (!empty($row3['last_seen'])) {
            $diff = time() - strtotime($row3['last_seen']);
            $isOnline = ($diff <= 60);
        }
        $row3['is_online'] = $isOnline;
        $devices[] = $row3;
    }
}

// ── Machine list for dropdown ─────────────────────────────────────────────────
$machines = [];
$res4 = $conn->query("SELECT id, name FROM machines ORDER BY name ASC");
if ($res4) {
    while ($row4 = $res4->fetch_assoc()) {
        $machines[] = $row4;
    }
}

// ── Helper: time ago ──────────────────────────────────────────────────────────
function timeAgo($datetime) {
    if (empty($datetime)) return 'Never';
    $diff = time() - strtotime($datetime);
    if ($diff < 5)    return 'Just now';
    if ($diff < 60)   return $diff . 's ago';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}

$conn->close();
require_once 'includes/header.php';
?>

<!-- Page Wrapper -->
<div id="wrapper">
    <!-- Sidebar -->
    <?php
    $sidebarFile = __DIR__ . '/includes/sidebar.php';
    if (file_exists($sidebarFile)) include $sidebarFile;
    ?>
    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">
        <!-- Main Content -->
        <div id="content">
            <!-- Topbar -->
            <?php
            $topbarFile = __DIR__ . '/includes/topbar.php';
            if (file_exists($topbarFile)) include $topbarFile;
            ?>

            <!-- Begin Page Content -->
            <div class="container-fluid">

                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-microchip text-primary mr-2"></i>ESP32 Device Monitor
                    </h1>
                    <div class="d-flex align-items-center">
                        <span class="mr-3 text-muted small" id="lastRefreshLabel">Last refresh: 0 seconds ago</span>
                        <?php if (($currentRole ?? 'operator') === 'admin'): ?>
                        <button class="btn btn-primary btn-sm shadow-sm" data-toggle="modal" data-target="#addDeviceModal">
                            <i class="fas fa-plus fa-sm mr-1"></i>Add Device
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── Stat Cards ────────────────────────────────────────── -->
                <div class="row mb-4">
                    <!-- Online Devices -->
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Online Devices</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="statOnline"><?= $statOnline ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-wifi fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Offline Devices -->
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Offline Devices</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="statOffline"><?= $statOffline ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-plug fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Last Data Received -->
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Last Data Received</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="statLastData"><?= htmlspecialchars($lastDataReceived) ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-database fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Device Cards ──────────────────────────────────────── -->
                <div class="row" id="deviceCardsRow">
                    <?php if (empty($devices)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-1"></i>No ESP32 devices registered yet.
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($devices as $dev):
                        $isOnline    = $dev['is_online'];
                        $statusClass = $isOnline ? 'online' : 'offline';
                        $statusText  = $isOnline ? 'Online' : 'Offline';
                        $cardBorder  = $isOnline ? 'border-success' : 'border-danger';
                        $deviceIdSafe = htmlspecialchars($dev['device_id']);
                    ?>
                    <div class="col-xl-4 col-md-6 mb-4 device-card-col" data-device-id="<?= $deviceIdSafe ?>">
                        <div class="card shadow <?= $cardBorder ?>" style="border-width:2px;">
                            <!-- Card Header -->
                            <div class="card-header py-2 d-flex justify-content-between align-items-center bg-white">
                                <span class="font-weight-bold text-gray-800">
                                    <i class="fas fa-microchip text-primary mr-1"></i><?= $deviceIdSafe ?>
                                </span>
                                <span>
                                    <span class="status-dot <?= $statusClass ?><?= $isOnline ? ' pulse' : '' ?>" title="<?= $statusText ?>"></span>
                                    <small class="text-muted device-status-text"><?= $statusText ?></small>
                                </span>
                            </div>
                            <!-- Card Body -->
                            <div class="card-body py-2">
                                <table class="table table-sm table-borderless mb-1" style="font-size:.85rem;">
                                    <tr>
                                        <td class="text-muted py-0" width="40%">IP Address</td>
                                        <td class="py-0 font-weight-bold"><?= htmlspecialchars($dev['ip_address'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-0">MAC</td>
                                        <td class="py-0 font-weight-bold"><?= htmlspecialchars($dev['mac_address'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-0">Firmware</td>
                                        <td class="py-0"><?= htmlspecialchars($dev['firmware_version'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-0">Machine</td>
                                        <td class="py-0">
                                            <?php if (!empty($dev['machine_id']) && !empty($dev['machine_name'])): ?>
                                            <a href="machine_detail.php?id=<?= (int)$dev['machine_id'] ?>"><?= htmlspecialchars($dev['machine_name']) ?></a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-0">Last Seen</td>
                                        <td class="py-0 device-last-seen" data-last-seen="<?= htmlspecialchars($dev['last_seen'] ?? '') ?>">
                                            <?= timeAgo($dev['last_seen']) ?>
                                        </td>
                                    </tr>
                                </table>
                                <!-- Latest sensor readings -->
                                <div class="mt-2 p-2 rounded" style="background:#f8f9fc;">
                                    <div class="row text-center">
                                        <div class="col-6 col-xl-3 mb-1">
                                            <div class="text-xs text-muted">Temp</div>
                                            <div class="font-weight-bold sensor-temp">
                                                <?= $dev['temp_panel'] !== null ? number_format((float)$dev['temp_panel'],1).'°C' : '-' ?>
                                            </div>
                                        </div>
                                        <div class="col-6 col-xl-3 mb-1">
                                            <div class="text-xs text-muted">Voltage</div>
                                            <div class="font-weight-bold sensor-voltage">
                                                <?= $dev['v_r'] !== null ? number_format((float)$dev['v_r'],1).' V' : '-' ?>
                                            </div>
                                        </div>
                                        <div class="col-6 col-xl-3 mb-1">
                                            <div class="text-xs text-muted">Current</div>
                                            <div class="font-weight-bold sensor-current">
                                                <?= $dev['a_r'] !== null ? number_format((float)$dev['a_r'],2).' A' : '-' ?>
                                            </div>
                                        </div>
                                        <div class="col-6 col-xl-3 mb-1">
                                            <div class="text-xs text-muted">Humidity</div>
                                            <div class="font-weight-bold sensor-humidity">
                                                <?= $dev['hum_panel'] !== null ? number_format((float)$dev['hum_panel'],1).'%' : '-' ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Card Footer -->
                            <div class="card-footer py-2 bg-white d-flex justify-content-between">
                                <a href="machine_detail.php?id=<?= (int)($dev['machine_id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye mr-1"></i>Detail
                                </a>
                                <button class="btn btn-sm btn-outline-secondary btn-ping" data-device-id="<?= $deviceIdSafe ?>">
                                    <i class="fas fa-satellite-dish mr-1"></i>Ping
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- ── Live Data Table ────────────────────────────────────── -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-table mr-1"></i>Live Sensor Data (Latest 50)
                        </h6>
                        <span class="badge badge-secondary" id="tableRefreshBadge">Auto-refresh 15s</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="liveDataTable" width="100%">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Time</th>
                                        <th>Device / Machine</th>
                                        <th>V_R (V)</th>
                                        <th>A_R (A)</th>
                                        <th>Temp (°C)</th>
                                        <th>Hum (%)</th>
                                        <th>Source</th>
                                    </tr>
                                </thead>
                                <tbody id="liveDataBody">
                                    <tr><td colspan="7" class="text-center text-muted">Loading data...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->

        <?php require_once 'includes/footer.php'; ?>
    </div>
    <!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->

<!-- ── Add Device Modal ─────────────────────────────────────────────────────── -->
<div class="modal fade" id="addDeviceModal" tabindex="-1" role="dialog" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDeviceModalLabel">
                    <i class="fas fa-microchip mr-1"></i>Add ESP32 Device
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="addDeviceForm">
                    <div class="form-group">
                        <label>Device ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="form_device_id" placeholder="e.g. ESP32-001" required>
                    </div>
                    <div class="form-group">
                        <label>Machine</label>
                        <select class="form-control" id="form_machine_id">
                            <option value="">-- Select Machine --</option>
                            <?php foreach ($machines as $m): ?>
                            <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>IP Address</label>
                        <input type="text" class="form-control" id="form_ip_address" placeholder="e.g. 192.168.1.100">
                    </div>
                    <div class="form-group">
                        <label>MAC Address</label>
                        <input type="text" class="form-control" id="form_mac_address" placeholder="e.g. AA:BB:CC:DD:EE:FF">
                    </div>
                    <div class="form-group">
                        <label>Firmware Version</label>
                        <input type="text" class="form-control" id="form_firmware_version" placeholder="e.g. 1.0.0">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveDevice">
                    <i class="fas fa-save mr-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Toast ─────────────────────────────────────────────────────────────────── -->
<div class="position-fixed" style="bottom:1rem;right:1rem;z-index:9999;">
    <div id="liveToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true" data-delay="3500">
        <div class="toast-header">
            <strong class="mr-auto" id="toastTitle">Info</strong>
            <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>

<!-- ── All scripts at bottom, inside $(document).ready ───────────────────────── -->
<script>
$(document).ready(function () {

    /* ── Toast helper ──────────────────────────────────────────────────────── */
    function showToast(title, message, type) {
        type = type || 'info';
        var colorMap = {success:'#1cc88a', danger:'#e74a3b', warning:'#f6c23e', info:'#36b9cc'};
        var color = colorMap[type] || colorMap.info;
        $('#toastTitle').text(title).css('color', color);
        $('#toastBody').text(message);
        $('#liveToast').toast('show');
    }
    window.showToast = showToast;

    /* ── HTML escape helper ────────────────────────────────────────────────── */
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ── JS time-ago helper ────────────────────────────────────────────────── */
    function timeAgoJs(datetime) {
        if (!datetime) return 'Never';
        var d = new Date(datetime.replace(' ','T'));
        if (isNaN(d.getTime())) return String(datetime);
        var diff = Math.floor((Date.now() - d.getTime()) / 1000);
        if (diff < 5)     return 'Just now';
        if (diff < 60)    return diff + 's ago';
        if (diff < 3600)  return Math.floor(diff/60) + 'm ago';
        if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
        return Math.floor(diff/86400) + 'd ago';
    }

    /* ── DataTable for live data ───────────────────────────────────────────── */
    var liveTable = null;

    function renderLiveTable(readings) {
        if (liveTable && $.fn.DataTable.isDataTable('#liveDataTable')) {
            liveTable.destroy();
            liveTable = null;
        }
        $('#liveDataBody').empty();

        if (!readings || readings.length === 0) {
            $('#liveDataBody').html('<tr><td colspan="7" class="text-center text-muted">No data</td></tr>');
            return;
        }

        var html = '';
        $.each(readings, function(i, r) {
            html += '<tr>';
            html += '<td>' + escHtml(r.recorded_at || '-') + '</td>';
            html += '<td>';
            if (r.device_id) html += '<span class="badge badge-primary mr-1">' + escHtml(r.device_id) + '</span>';
            html += escHtml(r.machine_name || '-') + '</td>';
            html += '<td>' + (r.v_r        != null ? parseFloat(r.v_r).toFixed(1)        : '-') + '</td>';
            html += '<td>' + (r.a_r        != null ? parseFloat(r.a_r).toFixed(2)        : '-') + '</td>';
            html += '<td>' + (r.temp_panel != null ? parseFloat(r.temp_panel).toFixed(1) : '-') + '</td>';
            html += '<td>' + (r.hum_panel  != null ? parseFloat(r.hum_panel).toFixed(1)  : '-') + '</td>';
            html += '<td>' + escHtml(r.source || 'sensor') + '</td>';
            html += '</tr>';
        });
        $('#liveDataBody').html(html);

        // Destroy existing instance first (destroy:true not reliable with colspan rows)
        if ($.fn.DataTable.isDataTable('#liveDataTable')) {
            $('#liveDataTable').DataTable().destroy();
        }
        // Remove any colspan placeholder rows before init
        $('#liveDataTable tbody tr').each(function () {
            if ($(this).find('td[colspan]').length) $(this).remove();
        });
        liveTable = $('#liveDataTable').DataTable({
            pageLength : 10,
            order      : [[0,'desc']],
            language   : {url:'vendor/datatables/i18n/id.json'},
            responsive : true
        });
    }

    function loadLiveData() {
        // Try sensors API first; fall back to esp32 readings endpoint
        $.ajax({
            url     : 'api/sensors.php',
            data    : {action:'latest', limit:50},
            success : function(res) {
                if (res && res.success && res.readings && res.readings.length > 0) {
                    renderLiveTable(res.readings);
                } else {
                    loadLiveDataFallback();
                }
            },
            error: function() { loadLiveDataFallback(); }
        });
    }

    function loadLiveDataFallback() {
        $.ajax({
            url     : 'api/esp32.php',
            data    : {action:'readings', limit:50},
            success : function(res) {
                if (res && res.success && res.readings) {
                    renderLiveTable(res.readings);
                }
            },
            error: function() {
                $('#liveDataBody').html('<tr><td colspan="7" class="text-center text-danger">Failed to load sensor data.</td></tr>');
            }
        });
    }

    // Initial table load
    loadLiveData();

    /* ── Auto-refresh table every 15s ─────────────────────────────────────── */
    var tableCountdown = 15;
    setInterval(function () {
        tableCountdown--;
        if (tableCountdown <= 0) {
            tableCountdown = 15;
            loadLiveData();
        }
        $('#tableRefreshBadge').text('Auto-refresh ' + tableCountdown + 's');
    }, 1000);

    /* ── Auto-refresh device cards every 30s ──────────────────────────────── */
    function refreshDeviceCards() {
        $.get('api/esp32.php', {action:'list'}, function(res) {
            if (!res || !res.success || !res.devices) return;

            var now = Math.floor(Date.now() / 1000);
            var cntOnline = 0, cntOffline = 0;

            $.each(res.devices, function(i, d) {
                var lastSeenTs = 0;
                if (d.last_seen) {
                    lastSeenTs = Math.floor(new Date(d.last_seen.replace(' ','T')).getTime() / 1000);
                }
                var isOnline = lastSeenTs > 0 && (now - lastSeenTs) <= 60;
                if (isOnline) cntOnline++; else cntOffline++;

                // Update card DOM
                var col = $('.device-card-col[data-device-id="' + escHtml(d.device_id) + '"]');
                if (!col.length) return;

                var card = col.find('.card');
                card.removeClass('border-success border-danger').addClass(isOnline ? 'border-success' : 'border-danger');

                var dot = col.find('.status-dot');
                dot.removeClass('online offline pulse').addClass(isOnline ? 'online pulse' : 'offline');

                col.find('.device-status-text').text(isOnline ? 'Online' : 'Offline');
                col.find('.device-last-seen')
                   .attr('data-last-seen', d.last_seen || '')
                   .text(timeAgoJs(d.last_seen));
            });

            $('#statOnline').text(cntOnline);
            $('#statOffline').text(cntOffline);
        });
    }

    setInterval(refreshDeviceCards, 30000);

    /* ── Last refresh counter ──────────────────────────────────────────────── */
    var refreshSeconds = 0;
    setInterval(function () {
        refreshSeconds++;
        $('#lastRefreshLabel').text('Last refresh: ' + refreshSeconds + ' seconds ago');
    }, 1000);

    /* ── Ping button ───────────────────────────────────────────────────────── */
    $(document).on('click', '.btn-ping', function () {
        var btn      = $(this);
        var deviceId = btn.data('device-id');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Ping...');

        $.get('api/esp32.php', {action:'heartbeat', device_id: deviceId}, function (res) {
            if (res && res.success) {
                var col = $('.device-card-col[data-device-id="' + deviceId + '"]');
                col.find('.device-last-seen').text('Just now').attr('data-last-seen', res.last_seen || '');
                col.find('.status-dot').removeClass('offline').addClass('online pulse');
                col.find('.device-status-text').text('Online');
                col.find('.card').removeClass('border-danger').addClass('border-success');
                showToast('Ping', 'Device ' + deviceId + ' responded!', 'success');
            } else {
                showToast('Ping Failed', 'Device ' + deviceId + ' did not respond.', 'danger');
            }
        }).fail(function () {
            showToast('Ping Error', 'Failed to contact API.', 'danger');
        }).always(function () {
            btn.prop('disabled', false).html('<i class="fas fa-satellite-dish mr-1"></i>Ping');
        });
    });

    /* ── Add Device: Save ──────────────────────────────────────────────────── */
    $('#btnSaveDevice').on('click', function () {
        var deviceId = $.trim($('#form_device_id').val());
        if (!deviceId) {
            showToast('Validation', 'Device ID is required.', 'warning');
            $('#form_device_id').focus();
            return;
        }

        var payload = {
            device_id        : deviceId,
            machine_id       : $('#form_machine_id').val() || null,
            ip_address       : $('#form_ip_address').val(),
            mac_address      : $('#form_mac_address').val(),
            firmware_version : $('#form_firmware_version').val()
        };

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');

        $.ajax({
            url         : 'api/esp32.php?action=register',
            method      : 'POST',
            contentType : 'application/json',
            data        : JSON.stringify(payload),
            success     : function (res) {
                if (res && res.success) {
                    showToast('Success', 'Device saved successfully. Reloading...', 'success');
                    $('#addDeviceModal').modal('hide');
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    showToast('Failed', res.message || 'An error occurred.', 'danger');
                }
            },
            error: function (xhr) {
                var msg = 'Failed to save device.';
                try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
                showToast('Error', msg, 'danger');
            },
            complete: function () {
                btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save');
            }
        });
    });

    /* ── Reset modal on close ──────────────────────────────────────────────── */
    $('#addDeviceModal').on('hidden.bs.modal', function () {
        $('#addDeviceForm')[0].reset();
    });

});
</script>
