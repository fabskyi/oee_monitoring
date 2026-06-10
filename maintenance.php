<?php
$pageTitle = 'Maintenance';
$currentPage = 'maintenance';
require_once 'includes/config.php';
require_once 'includes/auth_check.php';

$db = getDB();

// --- Filters from GET ---
$filterMachine = isset($_GET['machine_id']) && $_GET['machine_id'] !== '' ? (int)$_GET['machine_id'] : null;
$filterType    = isset($_GET['type'])       && $_GET['type']       !== '' ? $_GET['type']       : null;
$filterFrom    = isset($_GET['from'])       && $_GET['from']       !== '' ? $_GET['from']       : null;
$filterTo      = isset($_GET['to'])         && $_GET['to']         !== '' ? $_GET['to']         : null;

// --- Calendar month ---
$calMonth = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])
    ? $_GET['month']
    : date('Y-m');
$calFrom  = $calMonth . '-01';
$calTo    = date('Y-m-t', strtotime($calFrom));

// --- Fetch machines for selects ---
$machines = $db->query("SELECT id, name FROM machines ORDER BY name ASC")->fetchAll();

// --- Stats ---
$thisMonth = date('Y-m');
$statsTotal = (int)$db->query("SELECT COUNT(*) FROM maintenance_records")->fetchColumn();

$stmtPrev = $db->prepare("SELECT COUNT(*) FROM maintenance_records WHERE type='preventive' AND DATE_FORMAT(maint_date,'%Y-%m')=?");
$stmtPrev->execute([$thisMonth]);
$statsPrev = (int)$stmtPrev->fetchColumn();

$stmtBrkd = $db->prepare("SELECT COUNT(*) FROM maintenance_records WHERE type='breakdown' AND DATE_FORMAT(maint_date,'%Y-%m')=?");
$stmtBrkd->execute([$thisMonth]);
$statsBrkd = (int)$stmtBrkd->fetchColumn();

$avgDur = (float)$db->query("SELECT COALESCE(AVG(duration_min),0) FROM maintenance_records")->fetchColumn();

// --- Main table records ---
$where  = ['1=1'];
$params = [];
if ($filterMachine) { $where[] = 'mr.machine_id = ?'; $params[] = $filterMachine; }
if ($filterType)    { $where[] = 'mr.type = ?';       $params[] = $filterType; }
if ($filterFrom)    { $where[] = 'mr.maint_date >= ?'; $params[] = $filterFrom; }
if ($filterTo)      { $where[] = 'mr.maint_date <= ?'; $params[] = $filterTo; }

$sql = "SELECT mr.*, m.name AS machine_name
        FROM maintenance_records mr
        JOIN machines m ON mr.machine_id = m.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY mr.maint_date DESC, mr.created_at DESC";
$stmtRec = $db->prepare($sql);
$stmtRec->execute($params);
$records = $stmtRec->fetchAll();

// --- Calendar events ---
$stmtCal = $db->prepare(
    "SELECT mr.id, mr.type, mr.description, mr.maint_date, m.name AS machine_name
     FROM maintenance_records mr
     JOIN machines m ON mr.machine_id = m.id
     WHERE mr.maint_date BETWEEN ? AND ?
     ORDER BY mr.maint_date ASC"
);
$stmtCal->execute([$calFrom, $calTo]);
$calEvents = $stmtCal->fetchAll();

require_once 'includes/header.php';
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-wrench mr-2"></i>Maintenance
    </h1>
    <button class="btn btn-primary btn-sm shadow-sm" data-toggle="modal" data-target="#addMaintModal">
        <i class="fas fa-plus fa-sm text-white-50 mr-1"></i> Tambah Record
    </button>
</div>

<!-- Stat Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Records</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statsTotal; ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-clipboard-list fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Preventive Bulan Ini</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statsPrev; ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-shield-alt fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Breakdown Bulan Ini</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $statsBrkd; ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Rata-rata Durasi (menit)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($avgDur, 1); ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <ul class="nav nav-tabs card-header-tabs" id="maintTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="table-tab" data-toggle="tab" href="#tabTable" role="tab">
                    <i class="fas fa-table mr-1"></i> Tabel Riwayat
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="calendar-tab" data-toggle="tab" href="#tabCalendar" role="tab">
                    <i class="fas fa-calendar-alt mr-1"></i> Kalender
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="maintTabsContent">

            <!-- TAB 1: Table -->
            <div class="tab-pane fade show active" id="tabTable" role="tabpanel">

                <!-- Filter Form -->
                <form method="GET" class="form-inline mb-3 flex-wrap" id="filterForm">
                    <input type="hidden" name="month" value="<?php echo htmlspecialchars($calMonth); ?>">
                    <div class="form-group mr-2 mb-2">
                        <label class="mr-1 small">Mesin:</label>
                        <select name="machine_id" class="form-control form-control-sm">
                            <option value="">Semua Mesin</option>
                            <?php foreach ($machines as $m): ?>
                                <option value="<?php echo $m['id']; ?>" <?php echo $filterMachine == $m['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <label class="mr-1 small">Tipe:</label>
                        <select name="type" class="form-control form-control-sm">
                            <option value="">Semua Tipe</option>
                            <option value="preventive"  <?php echo $filterType === 'preventive'  ? 'selected' : ''; ?>>Preventive</option>
                            <option value="corrective"  <?php echo $filterType === 'corrective'  ? 'selected' : ''; ?>>Corrective</option>
                            <option value="breakdown"   <?php echo $filterType === 'breakdown'   ? 'selected' : ''; ?>>Breakdown</option>
                            <option value="inspection"  <?php echo $filterType === 'inspection'  ? 'selected' : ''; ?>>Inspection</option>
                        </select>
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <label class="mr-1 small">Dari:</label>
                        <input type="date" name="from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterFrom ?? ''); ?>">
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <label class="mr-1 small">Sampai:</label>
                        <input type="date" name="to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterTo ?? ''); ?>">
                    </div>
                    <div class="mb-2">
                        <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter mr-1"></i>Filter</button>
                        <a href="maintenance.php" class="btn btn-secondary btn-sm"><i class="fas fa-times mr-1"></i>Reset</a>
                    </div>
                </form>

                <!-- DataTable -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="mainTable" width="100%" cellspacing="0">
                        <thead class="thead-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Mesin</th>
                                <th>Tipe</th>
                                <th>Deskripsi</th>
                                <th>Teknisi</th>
                                <th>Durasi</th>
                                <th>Biaya</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($row['maint_date']))); ?></td>
                                <td><?php echo htmlspecialchars($row['machine_name']); ?></td>
                                <td>
                                    <?php
                                    $typeBadge = [
                                        'preventive' => 'badge-primary',
                                        'corrective' => 'badge-warning',
                                        'breakdown'  => 'badge-danger',
                                        'inspection' => 'badge-info',
                                    ];
                                    $cls = $typeBadge[$row['type']] ?? 'badge-secondary';
                                    ?>
                                    <span class="badge <?php echo $cls; ?>"><?php echo ucfirst(htmlspecialchars($row['type'])); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                <td><?php echo htmlspecialchars($row['technician']); ?></td>
                                <td><?php echo $row['duration_min'] !== null ? htmlspecialchars($row['duration_min']) . ' mnt' : '-'; ?></td>
                                <td><?php echo $row['cost'] !== null ? 'Rp ' . number_format((float)$row['cost'], 0, ',', '.') : '-'; ?></td>
                                <td class="text-center" style="white-space:nowrap;">
                                    <button class="btn btn-warning btn-sm btn-edit"
                                        data-id="<?php echo $row['id']; ?>"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm btn-delete"
                                        data-id="<?php echo $row['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($row['machine_name'] . ' - ' . $row['maint_date']); ?>"
                                        title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: Calendar -->
            <div class="tab-pane fade" id="tabCalendar" role="tabpanel">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <?php
                    $prevM = date('Y-m', strtotime($calMonth . '-01 -1 month'));
                    $nextM = date('Y-m', strtotime($calMonth . '-01 +1 month'));
                    $qBase = http_build_query([
                        'machine_id' => $filterMachine ?? '',
                        'type'       => $filterType    ?? '',
                        'from'       => $filterFrom    ?? '',
                        'to'         => $filterTo      ?? '',
                    ]);
                    ?>
                    <a href="?month=<?php echo $prevM; ?>&<?php echo $qBase; ?>"
                       class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-chevron-left"></i> Prev
                    </a>
                    <h5 class="mb-0 font-weight-bold" id="calTitle"></h5>
                    <a href="?month=<?php echo $nextM; ?>&<?php echo $qBase; ?>"
                       class="btn btn-outline-primary btn-sm">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>

                <!-- Legend -->
                <div class="mb-3 small">
                    <span class="badge badge-primary mr-2">Preventive</span>
                    <span class="badge badge-warning mr-2">Corrective</span>
                    <span class="badge badge-danger mr-2">Breakdown</span>
                    <span class="badge badge-info mr-2">Inspection</span>
                </div>

                <div id="calGrid" class="cal-grid"></div>

                <!-- Event detail panel -->
                <div id="calDetail" class="card shadow-sm mt-3 d-none" style="max-width:420px;">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <strong id="calDetailTitle">Detail</strong>
                        <button type="button" class="close" id="calDetailClose"><span>&times;</span></button>
                    </div>
                    <div class="card-body py-2" id="calDetailBody"></div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ===== ADD MODAL ===== -->
<div class="modal fade" id="addMaintModal" tabindex="-1" role="dialog" aria-labelledby="addMaintModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMaintModalLabel"><i class="fas fa-plus mr-1"></i> Tambah Record Maintenance</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="addMaintForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Mesin <span class="text-danger">*</span></label>
                                <select name="machine_id" id="add_machine_id" class="form-control" required>
                                    <option value="">-- Pilih Mesin --</option>
                                    <?php foreach ($machines as $m): ?>
                                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tipe <span class="text-danger">*</span></label>
                                <select name="type" id="add_type" class="form-control" required>
                                    <option value="">-- Pilih Tipe --</option>
                                    <option value="preventive">Preventive</option>
                                    <option value="corrective">Corrective</option>
                                    <option value="breakdown">Breakdown</option>
                                    <option value="inspection">Inspection</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label>Deskripsi <span class="text-danger">*</span></label>
                                <textarea name="description" id="add_description" class="form-control" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Teknisi <span class="text-danger">*</span></label>
                                <input type="text" name="technician" id="add_technician" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tanggal Maintenance <span class="text-danger">*</span></label>
                                <input type="date" name="maint_date" id="add_maint_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Durasi (menit)</label>
                                <input type="number" name="duration_min" id="add_duration_min" class="form-control" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Biaya (Rp)</label>
                                <input type="number" name="cost" id="add_cost" class="form-control" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnAddSave"><i class="fas fa-save mr-1"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== EDIT MODAL ===== -->
<div class="modal fade" id="editMaintModal" tabindex="-1" role="dialog" aria-labelledby="editMaintModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMaintModalLabel"><i class="fas fa-edit mr-1"></i> Edit Record Maintenance</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="editMaintForm">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Mesin <span class="text-danger">*</span></label>
                                <select name="machine_id" id="edit_machine_id" class="form-control" required>
                                    <option value="">-- Pilih Mesin --</option>
                                    <?php foreach ($machines as $m): ?>
                                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tipe <span class="text-danger">*</span></label>
                                <select name="type" id="edit_type" class="form-control" required>
                                    <option value="">-- Pilih Tipe --</option>
                                    <option value="preventive">Preventive</option>
                                    <option value="corrective">Corrective</option>
                                    <option value="breakdown">Breakdown</option>
                                    <option value="inspection">Inspection</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label>Deskripsi <span class="text-danger">*</span></label>
                                <textarea name="description" id="edit_description" class="form-control" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Teknisi <span class="text-danger">*</span></label>
                                <input type="text" name="technician" id="edit_technician" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tanggal Maintenance <span class="text-danger">*</span></label>
                                <input type="date" name="maint_date" id="edit_maint_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Durasi (menit)</label>
                                <input type="number" name="duration_min" id="edit_duration_min" class="form-control" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Biaya (Rp)</label>
                                <input type="number" name="cost" id="edit_cost" class="form-control" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnEditSave"><i class="fas fa-save mr-1"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== DELETE MODAL ===== -->
<div class="modal fade" id="deleteMaintModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-trash mr-1 text-danger"></i> Hapus Record</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p>Yakin ingin menghapus record: <strong id="deleteRecordName"></strong>?</p>
                <p class="text-danger small">Tindakan ini tidak dapat dibatalkan.</p>
                <input type="hidden" id="deleteRecordId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="btnDeleteConfirm"><i class="fas fa-trash mr-1"></i> Hapus</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div aria-live="polite" aria-atomic="true" style="position:fixed;bottom:1rem;right:1rem;z-index:9999;">
    <div id="toastMsg" class="toast" role="alert" data-delay="3500">
        <div class="toast-header">
            <strong class="mr-auto" id="toastTitle">Info</strong>
            <button type="button" class="ml-2 mb-1 close" data-dismiss="toast"><span>&times;</span></button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>

<style>
.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}
.cal-day-header {
    text-align: center;
    font-weight: 700;
    font-size: 0.78rem;
    padding: 6px 0;
    background: #f8f9fa;
    border-radius: 4px;
    color: #5a5c69;
}
.cal-day {
    min-height: 80px;
    background: #fff;
    border: 1px solid #e3e6f0;
    border-radius: 4px;
    padding: 4px;
    font-size: 0.8rem;
    position: relative;
}
.cal-day.cal-other-month { background: #f8f9fa; opacity: 0.5; }
.cal-day.cal-today { border-color: #4e73df; border-width: 2px; }
.cal-day-num {
    font-weight: 700;
    color: #5a5c69;
    margin-bottom: 2px;
}
.cal-event-badge {
    display: block;
    border-radius: 3px;
    padding: 1px 4px;
    color: #fff;
    font-size: 0.68rem;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
}
.cal-event-badge:hover { opacity: 0.85; }
</style>

<?php require_once 'includes/footer.php'; ?>

<script>
var calEvents = <?php echo json_encode(array_values($calEvents)); ?>;
var calMonthStr = '<?php echo htmlspecialchars($calMonth); ?>';

function showToast(title, message, type) {
    type = type || 'success';
    var $toast = $('#toastMsg');
    $('#toastTitle').text(title);
    $('#toastBody').text(message);
    $toast.removeClass('bg-success bg-danger bg-warning text-white');
    if (type === 'success') { $toast.addClass('bg-success text-white'); }
    else if (type === 'error') { $toast.addClass('bg-danger text-white'); }
    else if (type === 'warning') { $toast.addClass('bg-warning'); }
    $toast.toast('show');
}

$(document).ready(function () {

    // --- DataTable ---
    safeDataTable('#mainTable', {
        pageLength: 25,
        order: [[0, 'desc']],
        columnDefs: [{ orderable: false, targets: 7 }]
    });

    // --- ADD: save ---
    $('#btnAddSave').on('click', function () {
        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');
        var data = {
            action:       'create',
            machine_id:   $('#add_machine_id').val(),
            type:         $('#add_type').val(),
            description:  $('#add_description').val(),
            technician:   $('#add_technician').val(),
            maint_date:   $('#add_maint_date').val(),
            duration_min: $('#add_duration_min').val(),
            cost:         $('#add_cost').val()
        };
        if (!data.machine_id || !data.type || !data.description || !data.technician || !data.maint_date) {
            showToast('Peringatan', 'Semua field wajib harus diisi.', 'warning');
            $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
            return;
        }
        $.ajax({
            url: 'api/maintenance.php?action=create',
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showToast('Berhasil', 'Record berhasil ditambahkan.', 'success');
                    $('#addMaintModal').modal('hide');
                    $('#addMaintForm')[0].reset();
                    setTimeout(function () { location.reload(); }, 1200);
                } else {
                    showToast('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
                }
            },
            error: function () {
                showToast('Error', 'Tidak dapat terhubung ke server.', 'error');
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
            }
        });
    });

    // --- EDIT: open modal and pre-fill ---
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        $.get('api/maintenance.php', { action: 'get', id: id }, function (res) {
            if (res.success && res.data) {
                var d = res.data;
                $('#edit_id').val(d.id);
                $('#edit_machine_id').val(d.machine_id);
                $('#edit_type').val(d.type);
                $('#edit_description').val(d.description);
                $('#edit_technician').val(d.technician);
                $('#edit_maint_date').val(d.maint_date);
                $('#edit_duration_min').val(d.duration_min !== null && d.duration_min !== undefined ? d.duration_min : '');
                $('#edit_cost').val(d.cost !== null && d.cost !== undefined ? d.cost : '');
                $('#editMaintModal').modal('show');
            } else {
                showToast('Error', res.message || 'Data tidak ditemukan.', 'error');
            }
        }, 'json').fail(function () {
            showToast('Error', 'Tidak dapat mengambil data.', 'error');
        });
    });

    // --- EDIT: save ---
    $('#btnEditSave').on('click', function () {
        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');
        var data = {
            action:       'update',
            id:           $('#edit_id').val(),
            machine_id:   $('#edit_machine_id').val(),
            type:         $('#edit_type').val(),
            description:  $('#edit_description').val(),
            technician:   $('#edit_technician').val(),
            maint_date:   $('#edit_maint_date').val(),
            duration_min: $('#edit_duration_min').val(),
            cost:         $('#edit_cost').val()
        };
        if (!data.machine_id || !data.type || !data.description || !data.technician || !data.maint_date) {
            showToast('Peringatan', 'Semua field wajib harus diisi.', 'warning');
            $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
            return;
        }
        $.ajax({
            url: 'api/maintenance.php?action=update',
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showToast('Berhasil', 'Record berhasil diperbarui.', 'success');
                    $('#editMaintModal').modal('hide');
                    setTimeout(function () { location.reload(); }, 1200);
                } else {
                    showToast('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
                }
            },
            error: function () {
                showToast('Error', 'Tidak dapat terhubung ke server.', 'error');
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
            }
        });
    });

    // --- DELETE: open confirm modal ---
    $(document).on('click', '.btn-delete', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#deleteRecordId').val(id);
        $('#deleteRecordName').text(name);
        $('#deleteMaintModal').modal('show');
    });

    // --- DELETE: confirm ---
    $('#btnDeleteConfirm').on('click', function () {
        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menghapus...');
        var id = $('#deleteRecordId').val();
        $.ajax({
            url: 'api/maintenance.php?action=delete',
            method: 'POST',
            data: { action: 'delete', id: id },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showToast('Berhasil', 'Record berhasil dihapus.', 'success');
                    $('#deleteMaintModal').modal('hide');
                    setTimeout(function () { location.reload(); }, 1200);
                } else {
                    showToast('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-trash mr-1"></i> Hapus');
                }
            },
            error: function () {
                showToast('Error', 'Tidak dapat terhubung ke server.', 'error');
                $btn.prop('disabled', false).html('<i class="fas fa-trash mr-1"></i> Hapus');
            }
        });
    });

    // --- Reset buttons on modal open ---
    $('#addMaintModal').on('show.bs.modal', function () {
        $('#addMaintForm')[0].reset();
        $('#btnAddSave').prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
    });
    $('#editMaintModal').on('show.bs.modal', function () {
        $('#btnEditSave').prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
    });
    $('#deleteMaintModal').on('show.bs.modal', function () {
        $('#btnDeleteConfirm').prop('disabled', false).html('<i class="fas fa-trash mr-1"></i> Hapus');
    });

    // =====================
    // CALENDAR
    // =====================
    var TYPE_COLORS = {
        'preventive': '#4e73df',
        'corrective': '#f6c23e',
        'breakdown':  '#e74a3b',
        'inspection': '#36b9cc'
    };
    var MONTH_NAMES = ['Januari','Februari','Maret','April','Mei','Juni',
                       'Juli','Agustus','September','Oktober','November','Desember'];
    var DAY_NAMES   = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];

    function buildCalendar() {
        var parts  = calMonthStr.split('-');
        var year   = parseInt(parts[0], 10);
        var month  = parseInt(parts[1], 10) - 1;
        var today  = new Date();

        $('#calTitle').text(MONTH_NAMES[month] + ' ' + year);

        var firstDayOfWeek = new Date(year, month, 1).getDay();
        var daysInMonth    = new Date(year, month + 1, 0).getDate();

        // Build event map: 'YYYY-MM-DD' -> array of events
        var evtMap = {};
        for (var i = 0; i < calEvents.length; i++) {
            var ev = calEvents[i];
            var key = ev.maint_date;
            if (!evtMap[key]) evtMap[key] = [];
            evtMap[key].push(ev);
        }

        var $grid = $('#calGrid');
        $grid.empty();

        // Day headers
        for (var dh = 0; dh < 7; dh++) {
            $grid.append($('<div>').addClass('cal-day-header').text(DAY_NAMES[dh]));
        }

        // Leading empty cells
        for (var b = 0; b < firstDayOfWeek; b++) {
            $grid.append($('<div>').addClass('cal-day cal-other-month'));
        }

        // Day cells
        for (var day = 1; day <= daysInMonth; day++) {
            var mm  = String(month + 1).padStart(2, '0');
            var dd  = String(day).padStart(2, '0');
            var dateKey = year + '-' + mm + '-' + dd;
            var isToday = (today.getFullYear() === year && today.getMonth() === month && today.getDate() === day);

            var $cell = $('<div>').addClass('cal-day' + (isToday ? ' cal-today' : ''));
            $cell.append($('<div>').addClass('cal-day-num').text(day));

            if (evtMap[dateKey]) {
                var events = evtMap[dateKey];
                for (var e = 0; e < events.length; e++) {
                    (function(evData) {
                        var bg = TYPE_COLORS[evData.type] || '#858796';
                        var $badge = $('<span>')
                            .addClass('cal-event-badge')
                            .css('background-color', bg)
                            .text(evData.machine_name);
                        $badge.on('click', function (evt) {
                            evt.stopPropagation();
                            showCalDetail(evData);
                        });
                        $cell.append($badge);
                    })(events[e]);
                }
            }
            $grid.append($cell);
        }

        // Trailing empty cells
        var totalCells = firstDayOfWeek + daysInMonth;
        var trailing   = (7 - (totalCells % 7)) % 7;
        for (var t = 0; t < trailing; t++) {
            $grid.append($('<div>').addClass('cal-day cal-other-month'));
        }
    }

    function showCalDetail(ev) {
        var typeName = ev.type ? (ev.type.charAt(0).toUpperCase() + ev.type.slice(1)) : '';
        $('#calDetailTitle').text(typeName + ' - ' + ev.machine_name);
        var html = '<table class="table table-sm table-borderless mb-0">'
            + '<tr><th class="pl-0" style="width:100px">Tanggal</th><td>' + $('<span>').text(ev.maint_date).html() + '</td></tr>'
            + '<tr><th class="pl-0">Mesin</th><td>' + $('<span>').text(ev.machine_name).html() + '</td></tr>'
            + '<tr><th class="pl-0">Tipe</th><td>' + $('<span>').text(typeName).html() + '</td></tr>'
            + '<tr><th class="pl-0">Deskripsi</th><td>' + $('<span>').text(ev.description).html() + '</td></tr>'
            + '</table>';
        $('#calDetailBody').html(html);
        $('#calDetail').removeClass('d-none');
    }

    $('#calDetailClose').on('click', function () {
        $('#calDetail').addClass('d-none');
    });

    // Build calendar on page load
    buildCalendar();

    // Rebuild when switching to calendar tab
    $('a[href="#tabCalendar"]').on('shown.bs.tab', function () {
        buildCalendar();
    });

});
</script>
