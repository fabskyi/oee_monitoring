<?php
require_once 'includes/auth_check.php';
$currentPage = 'machines';
$pageTitle   = 'Machine Status';

$db = getDB();

// ── Fetch production lines for filter dropdown ────────────────────────────────
$linesStmt = $db->query("SELECT id, name FROM production_lines ORDER BY name ASC");
$allLines  = $linesStmt->fetchAll();

// ── Filter params ─────────────────────────────────────────────────────────────
$filterLine   = isset($_GET['line_id'])  ? (int)$_GET['line_id']      : 0;
$filterStatus = isset($_GET['status'])   ? trim($_GET['status'])       : 'all';
$filterSearch = isset($_GET['search'])   ? trim($_GET['search'])       : '';

// ── Build query ───────────────────────────────────────────────────────────────
$whereClauses = [];
$params       = [];

if ($filterLine > 0) {
    $whereClauses[] = 'm.line_id = ?';
    $params[]       = $filterLine;
}
if (in_array($filterStatus, ['run', 'stop'])) {
    $whereClauses[] = 'm.status = ?';
    $params[]       = $filterStatus;
}
if ($filterSearch !== '') {
    $whereClauses[] = '(m.name LIKE ? OR m.model LIKE ?)';
    $params[]       = '%' . $filterSearch . '%';
    $params[]       = '%' . $filterSearch . '%';
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$sql = "
    SELECT
        m.id,
        m.line_id,
        m.name,
        m.model,
        m.status,
        m.image_base64,
        m.sort_order,
        pl.name AS line_name,
        sr.v_r, sr.v_s, sr.v_t,
        sr.a_r, sr.a_s, sr.a_t,
        sr.temp_panel,
        sr.recorded_at AS sensor_recorded_at,
        os.availability AS oee_availability,
        os.performance  AS oee_performance,
        os.quality      AS oee_quality
    FROM machines m
    LEFT JOIN production_lines pl ON pl.id = m.line_id
    LEFT JOIN sensor_readings sr ON sr.id = (
        SELECT id FROM sensor_readings
        WHERE machine_id = m.id
        ORDER BY recorded_at DESC
        LIMIT 1
    )
    LEFT JOIN oee_settings os ON os.machine_id = m.id
    $whereSQL
    ORDER BY m.sort_order ASC, m.id ASC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$machines = $stmt->fetchAll();

// ── Role helpers ──────────────────────────────────────────────────────────────
$role        = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'viewer';
$canEdit     = in_array($role, ['admin', 'operator']);

// ── Helper: OEE % from settings (simple average) ─────────────────────────────
function calcOEE($avail, $perf, $qual): float {
    $a = is_numeric($avail) ? (float)$avail : 0;
    $p = is_numeric($perf)  ? (float)$perf  : 0;
    $q = is_numeric($qual)  ? (float)$qual  : 0;
    if ($a <= 0 && $p <= 0 && $q <= 0) return 0;
    return round(($a * $p * $q) / 10000, 1); // inputs are %, result %
}

include 'includes/header.php';
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-cogs mr-2"></i>Machine Status</h1>
    <?php if ($canEdit): ?>
        <button class="btn btn-primary btn-sm shadow-sm" data-toggle="modal" data-target="#addMachineModal">
            <i class="fas fa-plus fa-sm text-white-50 mr-1"></i> Add Machine
        </button>
    <?php endif; ?>
</div>

<!-- Filter Bar -->
<div class="card shadow mb-4">
    <div class="card-body py-2">
        <form method="GET" action="machines.php" class="form-inline flex-wrap" id="filterForm">
            <!-- Line filter -->
            <div class="form-group mr-3 mb-2">
                <label class="mr-2 text-gray-600 small font-weight-bold">Line:</label>
                <select name="line_id" class="form-control form-control-sm" id="filterLine">
                    <option value="0">All Lines</option>
                    <?php foreach ($allLines as $ln): ?>
                        <option value="<?= $ln['id'] ?>"
                            <?= ($filterLine == $ln['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ln['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Status filter -->
            <div class="form-group mr-3 mb-2">
                <label class="mr-2 text-gray-600 small font-weight-bold">Status:</label>
                <select name="status" class="form-control form-control-sm" id="filterStatus">
                    <option value="all"  <?= $filterStatus === 'all'  ? 'selected' : '' ?>>All</option>
                    <option value="run"  <?= $filterStatus === 'run'  ? 'selected' : '' ?>>Run</option>
                    <option value="stop" <?= $filterStatus === 'stop' ? 'selected' : '' ?>>Stop</option>
                </select>
            </div>
            <!-- Search -->
            <div class="form-group mr-3 mb-2">
                <label class="mr-2 text-gray-600 small font-weight-bold">Search:</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name / Model..."
                    value="<?= htmlspecialchars($filterSearch) ?>" id="filterSearch" style="min-width:180px;">
            </div>
            <div class="mb-2">
                <button type="submit" class="btn btn-primary btn-sm mr-1">
                    <i class="fas fa-search fa-sm mr-1"></i>Filter
                </button>
                <a href="machines.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times fa-sm mr-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Machine Cards Grid -->
<?php if (empty($machines)): ?>
    <div class="text-center py-5 text-gray-500">
        <i class="fas fa-cogs fa-3x mb-3"></i>
        <p class="h5">No machines found.</p>
    </div>
<?php else: ?>
<div class="row" id="machinesGrid">
    <?php foreach ($machines as $m):
        $isRun    = strtolower($m['status']) === 'run';
        $statusLabel = $isRun ? 'RUN' : 'STOP';
        $statusBadge = $isRun ? 'badge-success' : 'badge-danger';
        $borderColor = $isRun ? 'border-left-success' : 'border-left-danger';

        // Sensor averages
        $voltAvg = '-';
        $currAvg = '-';
        $tempPanel = '-';
        if ($m['v_r'] !== null || $m['v_s'] !== null || $m['v_t'] !== null) {
            $voltVals = array_filter([$m['v_r'], $m['v_s'], $m['v_t']], fn($v) => $v !== null);
            if (count($voltVals) > 0) $voltAvg = number_format(array_sum($voltVals) / count($voltVals), 1);
        }
        if ($m['a_r'] !== null || $m['a_s'] !== null || $m['a_t'] !== null) {
            $currVals = array_filter([$m['a_r'], $m['a_s'], $m['a_t']], fn($v) => $v !== null);
            if (count($currVals) > 0) $currAvg = number_format(array_sum($currVals) / count($currVals), 2);
        }
        if ($m['temp_panel'] !== null) {
            $tempPanel = number_format((float)$m['temp_panel'], 1);
        }

        $oee = calcOEE($m['oee_availability'], $m['oee_performance'], $m['oee_quality']);
        $oeeColor = $oee >= 85 ? 'bg-success' : ($oee >= 65 ? 'bg-warning' : 'bg-danger');
    ?>
    <div class="col-xl-4 col-md-6 mb-4 machine-card-wrapper">
        <div class="card shadow h-100 <?= $borderColor ?>" style="border-left-width:4px;">
            <!-- Card Header -->
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-gray-800 text-truncate" style="max-width:70%;">
                    <?= htmlspecialchars($m['name']) ?>
                </h6>
                <div class="d-flex align-items-center">
                    <span class="badge <?= $statusBadge ?> mr-2"><?= $statusLabel ?></span>
                    <?php if ($canEdit): ?>
                    <div class="dropdown no-arrow">
                        <a href="#" class="dropdown-toggle text-gray-400" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                            <button class="dropdown-item btn-toggle-status"
                                data-id="<?= $m['id'] ?>"
                                data-status="<?= $m['status'] ?>">
                                <i class="fas fa-power-off fa-sm fa-fw mr-2 text-gray-400"></i>
                                <?= $isRun ? 'Set STOP' : 'Set RUN' ?>
                            </button>
                            <div class="dropdown-divider"></div>
                            <button class="dropdown-item btn-edit-machine"
                                data-id="<?= $m['id'] ?>">
                                <i class="fas fa-edit fa-sm fa-fw mr-2 text-gray-400"></i>Edit
                            </button>
                            <button class="dropdown-item text-danger btn-delete-machine"
                                data-id="<?= $m['id'] ?>"
                                data-name="<?= htmlspecialchars($m['name']) ?>">
                                <i class="fas fa-trash fa-sm fa-fw mr-2"></i>Delete
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card Body -->
            <div class="card-body">
                <?php if (!empty($m['image_base64'])): ?>
                    <img src="<?= $m['image_base64'] ?>" alt="<?= htmlspecialchars($m['name']) ?>"
                        class="img-fluid rounded mb-3" style="max-height:120px;object-fit:contain;display:block;margin:0 auto;">
                <?php endif; ?>

                <div class="row no-gutters mb-2">
                    <div class="col-6">
                        <small class="text-muted d-block">Model</small>
                        <span class="font-weight-bold text-gray-800 small"><?= htmlspecialchars($m['model'] ?: '-') ?></span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Production Line</small>
                        <span class="font-weight-bold text-gray-800 small"><?= htmlspecialchars($m['line_name'] ?: '-') ?></span>
                    </div>
                </div>

                <hr class="my-2">

                <!-- Sensor Values -->
                <div class="row no-gutters text-center mb-2">
                    <div class="col-4">
                        <small class="text-muted d-block">Voltage (V)</small>
                        <span class="font-weight-bold text-primary"><?= $voltAvg ?></span>
                    </div>
                    <div class="col-4">
                        <small class="text-muted d-block">Current (A)</small>
                        <span class="font-weight-bold text-success"><?= $currAvg ?></span>
                    </div>
                    <div class="col-4">
                        <small class="text-muted d-block">Temp Panel</small>
                        <span class="font-weight-bold text-warning"><?= $tempPanel ?><?= $tempPanel !== '-' ? '°C' : '' ?></span>
                    </div>
                </div>

                <!-- OEE Mini Progress -->
                <div class="mt-2">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted font-weight-bold">OEE</small>
                        <small class="font-weight-bold text-gray-800"><?= $oee ?>%</small>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar <?= $oeeColor ?>" role="progressbar"
                            style="width: <?= min($oee, 100) ?>%"
                            aria-valuenow="<?= $oee ?>" aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>
                </div>

                <?php if ($m['sensor_recorded_at']): ?>
                    <div class="mt-2 text-right">
                        <small class="text-muted"><i class="fas fa-clock fa-xs mr-1"></i><?= date('d/m/Y H:i', strtotime($m['sensor_recorded_at'])) ?></small>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card Footer -->
            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center py-2">
                <a href="machine_detail.php?id=<?= $m['id'] ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-chart-bar fa-sm mr-1"></i>Detail
                </a>
                <?php if ($canEdit): ?>
                    <button class="btn btn-outline-secondary btn-sm btn-edit-machine" data-id="<?= $m['id'] ?>">
                        <i class="fas fa-edit fa-sm mr-1"></i>Edit
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     ADD MACHINE MODAL
══════════════════════════════════════════════════════════ -->
<?php if ($canEdit): ?>
<div class="modal fade" id="addMachineModal" tabindex="-1" role="dialog" aria-labelledby="addMachineModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addMachineModalLabel"><i class="fas fa-plus-circle mr-2"></i>Add Machine</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="addMachineForm">
                    <div class="form-group">
                        <label>Machine Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="add_name" required placeholder="e.g. Lathe Machine A">
                    </div>
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" class="form-control" name="model" id="add_model" placeholder="e.g. CNC-1000">
                    </div>
                    <div class="form-group">
                        <label>Production Line <span class="text-danger">*</span></label>
                        <select class="form-control" name="line_id" id="add_line_id" required>
                            <option value="">-- Select Line --</option>
                            <?php foreach ($allLines as $ln): ?>
                                <option value="<?= $ln['id'] ?>"><?= htmlspecialchars($ln['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" name="status" id="add_status">
                            <option value="stop">Stop</option>
                            <option value="run">Run</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Display Order</label>
                        <input type="number" class="form-control" name="sort_order" id="add_sort_order" value="0" min="0">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnAddMachineSave">
                    <i class="fas fa-save mr-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     EDIT MACHINE MODAL
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editMachineModal" tabindex="-1" role="dialog" aria-labelledby="editMachineModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="editMachineModalLabel"><i class="fas fa-edit mr-2"></i>Edit Machine</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="editLoadingSpinner" class="text-center py-4" style="display:none;">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Loading data...</p>
                </div>
                <form id="editMachineForm">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-group">
                        <label>Machine Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" class="form-control" name="model" id="edit_model">
                    </div>
                    <div class="form-group">
                        <label>Production Line <span class="text-danger">*</span></label>
                        <select class="form-control" name="line_id" id="edit_line_id" required>
                            <option value="">-- Select Line --</option>
                            <?php foreach ($allLines as $ln): ?>
                                <option value="<?= $ln['id'] ?>"><?= htmlspecialchars($ln['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" name="status" id="edit_status">
                            <option value="stop">Stop</option>
                            <option value="run">Run</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Display Order</label>
                        <input type="number" class="form-control" name="sort_order" id="edit_sort_order" value="0" min="0">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning text-white" id="btnEditMachineSave">
                    <i class="fas fa-save mr-1"></i>Update
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     DELETE CONFIRM MODAL
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="deleteMachineModal" tabindex="-1" role="dialog" aria-labelledby="deleteMachineModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteMachineModalLabel"><i class="fas fa-trash mr-2"></i>Delete Machine</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Delete machine <strong id="deleteMachineName"></strong>?</p>
                <p class="text-danger small">This action cannot be undone.</p>
                <input type="hidden" id="deleteMachineId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnDeleteMachineConfirm">
                    <i class="fas fa-trash mr-1"></i>Delete
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast Container -->
<div aria-live="polite" aria-atomic="true" style="position:fixed;bottom:20px;right:20px;z-index:9999;min-width:250px;">
    <div id="toastContainer"></div>
</div>

<?php include 'includes/footer.php'; // footer outputs vendor scripts ?>
<!-- ══════════════════════════════════════════════════════════
     PAGE SCRIPTS  (all at bottom, after footer vendor scripts)
══════════════════════════════════════════════════════════ -->
<script>
$(document).ready(function () {

    // ─── Toast helper ────────────────────────────────────────────────────────
    function showToast(msg, type) {
        type = type || 'success';
        var colorMap = {
            'success': 'bg-success',
            'error':   'bg-danger',
            'warning': 'bg-warning',
            'info':    'bg-info'
        };
        var bgClass = colorMap[type] || 'bg-secondary';
        var id = 'toast_' + Date.now();
        var html = '<div id="' + id + '" class="toast text-white border-0 mb-2 ' + bgClass + '" role="alert" ' +
            'data-delay="3500" data-autohide="true" style="min-width:220px;">' +
            '<div class="d-flex">' +
            '<div class="toast-body">' + msg + '</div>' +
            '<button type="button" class="ml-auto mr-2 close text-white" data-dismiss="toast">' +
            '<span aria-hidden="true">&times;</span></button>' +
            '</div></div>';
        $('#toastContainer').append(html);
        $('#' + id).toast('show');
        $('#' + id).on('hidden.bs.toast', function () { $(this).remove(); });
    }

    // ─── Filter form: submit on dropdown change ───────────────────────────────
    $('#filterLine, #filterStatus').on('change', function () {
        $('#filterForm').submit();
    });

    <?php if ($canEdit): ?>

    // ─── ADD MACHINE ─────────────────────────────────────────────────────────
    $('#btnAddMachineSave').on('click', function () {
        var btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');
        var formData = $('#addMachineForm').serialize();
        $.ajax({
            url: 'api/machines.php?action=create',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showToast('Machine added successfully!', 'success');
                    $('#addMachineModal').modal('hide');
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    showToast('Failed: ' + (res.message || 'Unknown error'), 'error');
                    $('#btnAddMachineSave').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save');
                }
            },
            error: function (xhr) {
                var msg = 'A server error occurred.';
                try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
                showToast(msg, 'error');
                $('#btnAddMachineSave').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save');
            }
        });
    });

    // Reset add form when modal closes
    $('#addMachineModal').on('hidden.bs.modal', function () {
        $('#addMachineForm')[0].reset();
        $('#btnAddMachineSave').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save');
    });

    // ─── OPEN EDIT MODAL ─────────────────────────────────────────────────────
    $(document).on('click', '.btn-edit-machine', function () {
        var id = $(this).data('id');
        $('#editMachineForm').hide();
        $('#editLoadingSpinner').show();
        $('#editMachineModal').modal('show');

        $.get('api/machines.php', { action: 'get', id: id }, function (res) {
            if (res.success && res.data) {
                var d = res.data;
                $('#edit_id').val(d.id);
                $('#edit_name').val(d.name);
                $('#edit_model').val(d.model);
                $('#edit_line_id').val(d.line_id);
                $('#edit_status').val(d.status);
                $('#edit_sort_order').val(d.sort_order);
                $('#editLoadingSpinner').hide();
                $('#editMachineForm').show();
                $('#btnEditMachineSave').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Update');
            } else {
                showToast('Failed to load machine data.', 'error');
                $('#editMachineModal').modal('hide');
            }
        }, 'json').fail(function () {
            showToast('Failed to load machine data.', 'error');
            $('#editMachineModal').modal('hide');
        });
    });

    // ─── SAVE EDIT ───────────────────────────────────────────────────────────
    $('#btnEditMachineSave').on('click', function () {
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');
        var formData = $('#editMachineForm').serialize();
        $.ajax({
            url: 'api/machines.php?action=update',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showToast('Machine updated successfully!', 'success');
                    $('#editMachineModal').modal('hide');
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    showToast('Failed: ' + (res.message || 'Unknown error'), 'error');
                    $('#btnEditMachineSave').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Update');
                }
            },
            error: function (xhr) {
                var msg = 'A server error occurred.';
                try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
                showToast(msg, 'error');
                $('#btnEditMachineSave').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Update');
            }
        });
    });

    // ─── OPEN DELETE MODAL ───────────────────────────────────────────────────
    $(document).on('click', '.btn-delete-machine', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#deleteMachineId').val(id);
        $('#deleteMachineName').text(name);
        $('#deleteMachineModal').modal('show');
    });

    // ─── CONFIRM DELETE ──────────────────────────────────────────────────────
    $('#btnDeleteMachineConfirm').on('click', function () {
        var id = $('#deleteMachineId').val();
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Deleting...');
        $.ajax({
            url: 'api/machines.php?action=delete',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showToast('Machine deleted successfully.', 'success');
                    $('#deleteMachineModal').modal('hide');
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    showToast('Failed: ' + (res.message || 'Unknown error'), 'error');
                    $('#btnDeleteMachineConfirm').prop('disabled', false).html('<i class="fas fa-trash mr-1"></i>Delete');
                }
            },
            error: function (xhr) {
                var msg = 'A server error occurred.';
                try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
                showToast(msg, 'error');
                $('#btnDeleteMachineConfirm').prop('disabled', false).html('<i class="fas fa-trash mr-1"></i>Delete');
            }
        });
    });

    // Reset delete btn on close
    $('#deleteMachineModal').on('hidden.bs.modal', function () {
        $('#btnDeleteMachineConfirm').prop('disabled', false).html('<i class="fas fa-trash mr-1"></i>Delete');
    });

    // ─── TOGGLE STATUS ───────────────────────────────────────────────────────
    $(document).on('click', '.btn-toggle-status', function () {
        var btn       = $(this);
        var id        = btn.data('id');
        var curStatus = btn.data('status');
        var newStatus = (curStatus === 'run') ? 'stop' : 'run';

        btn.prop('disabled', true).prepend('<i class="fas fa-spinner fa-spin fa-sm mr-1"></i>');

        $.ajax({
            url: 'api/machines.php?action=update_status',
            method: 'POST',
            data: { id: id, status: newStatus },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showToast('Status changed to ' + newStatus.toUpperCase() + '.', 'success');
                    setTimeout(function () { location.reload(); }, 600);
                } else {
                    showToast('Failed to change status: ' + (res.message || ''), 'error');
                    btn.prop('disabled', false);
                    btn.find('.fa-spinner').remove();
                }
            },
            error: function () {
                showToast('A server error occurred.', 'error');
                btn.prop('disabled', false);
                btn.find('.fa-spinner').remove();
            }
        });
    });

    <?php endif; ?>

}); // end document.ready
</script>
