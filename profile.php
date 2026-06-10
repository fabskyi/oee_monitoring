<?php
// ============================================================
//  profile.php  –  Halaman profil pengguna
// ============================================================
require_once 'includes/auth_check.php';

$pageTitle   = 'Profil Saya';
$currentPage = 'profile';

$db  = getDB();
$uid = (int)$_SESSION['user_id'];

// ── Ambil data user ──────────────────────────────────────────
$stmt = $db->prepare("SELECT id, username, full_name, email, role, is_active, last_login, created_at FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ── Proses update profil ─────────────────────────────────────
$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['form_type'] ?? '';

    $activeTab = $type === 'password' ? 'pw' : 'info';

    if ($type === 'profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email']     ?? '');

        if (empty($full_name)) {
            $errorMsg = 'Nama lengkap tidak boleh kosong.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Format email tidak valid.';
        } else {
            // Cek email unik (kecuali milik sendiri)
            $stmtChk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmtChk->execute([$email, $uid]);
            if ($stmtChk->fetch()) {
                $errorMsg = 'Email sudah digunakan oleh akun lain.';
            } else {
                $stmtUp = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                $stmtUp->execute([$full_name, $email, $uid]);
                $_SESSION['full_name'] = $full_name;
                $successMsg = 'Profil berhasil diperbarui.';
                // Refresh data
                $stmt->execute([$uid]);
                $user = $stmt->fetch();
            }
        }

    } elseif ($type === 'password') {
        $current  = $_POST['current_password']  ?? '';
        $new      = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        if (empty($current) || empty($new) || empty($confirm)) {
            $errorMsg = 'Semua kolom password wajib diisi.';
        } elseif (strlen($new) < 6) {
            $errorMsg = 'Password baru minimal 6 karakter.';
        } elseif ($new !== $confirm) {
            $errorMsg = 'Konfirmasi password tidak cocok.';
        } else {
            $stmtPw = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmtPw->execute([$uid]);
            $row = $stmtPw->fetch();
            if (!$row || !password_verify($current, $row['password_hash'])) {
                $errorMsg = 'Password saat ini tidak sesuai.';
            } else {
                $newHash = password_hash($new, PASSWORD_BCRYPT);
                $stmtUp  = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmtUp->execute([$newHash, $uid]);
                $successMsg = 'Password berhasil diubah.';
            }
        }
    }
}

// ── Hitung statistik user ─────────────────────────────────────
$totalAlerts = (int)$db->query("SELECT COUNT(*) FROM alerts")->fetchColumn();
$totalMaint  = (int)$db->query("SELECT COUNT(*) FROM maintenance_records")->fetchColumn();
$totalMachines = (int)$db->query("SELECT COUNT(*) FROM machines")->fetchColumn();

require_once 'includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-user-circle mr-2"></i>Profil Saya</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 bg-transparent">
            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Profil</li>
        </ol>
    </nav>
</div>

<?php if ($successMsg): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($successMsg) ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($errorMsg) ?>
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<div class="row">

    <!-- ── Kartu Profil Kiri ─────────────────────────────────── -->
    <div class="col-xl-4 col-lg-5">

        <!-- Info Card -->
        <div class="card shadow mb-4">
            <div class="card-body text-center pt-4">
                <div class="mb-3" style="position:relative;display:inline-block;">
                    <img class="img-fluid img-profile rounded-circle" src="img/undraw_profile.svg"
                         alt="Avatar" style="width:100px;height:100px;object-fit:cover;border:3px solid #8B1A1A;">
                    <span class="position-absolute" style="bottom:0;right:0;">
                        <span class="badge badge-pill <?= $user['is_active'] ? 'badge-success' : 'badge-secondary' ?>" style="font-size:.7rem;">
                            <?= $user['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </span>
                </div>
                <h5 class="font-weight-bold mb-0"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></h5>
                <p class="text-muted mb-1">@<?= htmlspecialchars($user['username']) ?></p>
                <span class="badge badge-pill badge-primary px-3 py-1" style="background-color:#8B1A1A;font-size:.8rem;">
                    <?= ucfirst(htmlspecialchars($user['role'])) ?>
                </span>
                <hr>
                <div class="text-left small">
                    <p class="mb-1"><i class="fas fa-envelope fa-fw text-muted mr-2"></i><?= htmlspecialchars($user['email'] ?: '-') ?></p>
                    <p class="mb-1"><i class="fas fa-calendar-alt fa-fw text-muted mr-2"></i>Bergabung: <?= $user['created_at'] ? date('d M Y', strtotime($user['created_at'])) : '-' ?></p>
                    <p class="mb-0"><i class="fas fa-sign-in-alt fa-fw text-muted mr-2"></i>Login terakhir:
                        <?= $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : 'Belum pernah' ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Statistik singkat -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold" style="color:#8B1A1A;"><i class="fas fa-chart-bar mr-2"></i>Statistik Sistem</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2">
                        <span><i class="fas fa-cogs text-primary mr-2"></i>Total Mesin</span>
                        <span class="badge badge-primary badge-pill"><?= $totalMachines ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2">
                        <span><i class="fas fa-bell text-danger mr-2"></i>Total Alert</span>
                        <span class="badge badge-danger badge-pill"><?= $totalAlerts ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2">
                        <span><i class="fas fa-wrench text-warning mr-2"></i>Total Maintenance</span>
                        <span class="badge badge-warning badge-pill"><?= $totalMaint ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Akses Cepat -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold" style="color:#8B1A1A;"><i class="fas fa-link mr-2"></i>Akses Cepat</h6>
            </div>
            <div class="card-body">
                <a href="dashboard.php" class="btn btn-sm btn-block mb-2" style="background-color:#8B1A1A;color:#fff;"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>
                <a href="machines.php"  class="btn btn-sm btn-block btn-outline-secondary mb-2"><i class="fas fa-cogs mr-2"></i>Status Mesin</a>
                <a href="alerts.php"    class="btn btn-sm btn-block btn-outline-secondary mb-2"><i class="fas fa-bell mr-2"></i>Alert</a>
                <?php if ($user['role'] === 'admin'): ?>
                <a href="settings.php"  class="btn btn-sm btn-block btn-outline-secondary"><i class="fas fa-cog mr-2"></i>Pengaturan</a>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ── Form Kanan ────────────────────────────────────────── -->
    <div class="col-xl-8 col-lg-7">

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-0" id="profileTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-info" data-toggle="tab" href="#pane-info" role="tab">
                    <i class="fas fa-user mr-1"></i> Informasi Profil
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-pw" data-toggle="tab" href="#pane-pw" role="tab">
                    <i class="fas fa-lock mr-1"></i> Ubah Password
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-activity" data-toggle="tab" href="#pane-activity" role="tab">
                    <i class="fas fa-history mr-1"></i> Aktivitas
                </a>
            </li>
        </ul>

        <div class="tab-content">

            <!-- ── Tab: Info Profil ───────────────────────────── -->
            <div class="tab-pane fade show active card shadow border-top-0 rounded-0 rounded-bottom" id="pane-info" role="tabpanel">
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="form_type" value="profile">

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold small text-uppercase text-muted">Username</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                <small class="form-text text-muted">Username tidak dapat diubah.</small>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold small text-uppercase text-muted">Role</label>
                                <input type="text" class="form-control" value="<?= ucfirst(htmlspecialchars($user['role'])) ?>" disabled>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold small text-uppercase text-muted" for="full_name">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name"
                                   value="<?= htmlspecialchars($user['full_name'] ?? '') ?>"
                                   placeholder="Masukkan nama lengkap" required>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold small text-uppercase text-muted" for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                   placeholder="email@domain.com" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold small text-uppercase text-muted">Status Akun</label>
                                <div class="form-control-plaintext">
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge badge-success px-3 py-2"><i class="fas fa-check-circle mr-1"></i>Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary px-3 py-2"><i class="fas fa-times-circle mr-1"></i>Nonaktif</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold small text-uppercase text-muted">Member Sejak</label>
                                <div class="form-control-plaintext text-muted">
                                    <?= $user['created_at'] ? date('d F Y', strtotime($user['created_at'])) : '-' ?>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary btn-icon-split" style="background-color:#8B1A1A;border-color:#8B1A1A;">
                            <span class="icon"><i class="fas fa-save"></i></span>
                            <span class="text">Simpan Perubahan</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── Tab: Ubah Password ─────────────────────────── -->
            <div class="tab-pane fade card shadow border-top-0 rounded-0 rounded-bottom" id="pane-pw" role="tabpanel">
                <div class="card-body">
                    <form method="POST" id="formPassword">
                        <input type="hidden" name="form_type" value="password">

                        <div class="form-group">
                            <label class="font-weight-bold small text-uppercase text-muted" for="current_password">Password Saat Ini <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password"
                                       placeholder="Masukkan password saat ini" required autocomplete="current-password">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary toggle-pw" type="button" data-target="current_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold small text-uppercase text-muted" for="new_password">Password Baru <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password"
                                       placeholder="Minimal 6 karakter" required autocomplete="new-password" minlength="6">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary toggle-pw" type="button" data-target="new_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <!-- Kekuatan password -->
                            <div class="progress mt-2" style="height:4px;">
                                <div class="progress-bar" id="pwStrengthBar" role="progressbar" style="width:0%;transition:width .3s;"></div>
                            </div>
                            <small id="pwStrengthText" class="form-text text-muted"></small>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold small text-uppercase text-muted" for="confirm_password">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                       placeholder="Ulangi password baru" required autocomplete="new-password">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary toggle-pw" type="button" data-target="confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small id="confirmMsg" class="form-text"></small>
                        </div>

                        <div class="alert alert-info py-2 small">
                            <i class="fas fa-info-circle mr-1"></i>
                            Password harus minimal <strong>6 karakter</strong>. Gunakan kombinasi huruf, angka, dan simbol untuk keamanan lebih baik.
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-warning btn-icon-split font-weight-bold">
                            <span class="icon"><i class="fas fa-key"></i></span>
                            <span class="text">Ubah Password</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── Tab: Aktivitas ─────────────────────────────── -->
            <div class="tab-pane fade card shadow border-top-0 rounded-0 rounded-bottom" id="pane-activity" role="tabpanel">
                <div class="card-body">

                    <h6 class="font-weight-bold mb-3"><i class="fas fa-bell mr-2 text-danger"></i>Alert Terbaru di Sistem</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered table-hover no-auto-init" id="tblActivity">
                            <thead class="thead-light">
                                <tr>
                                    <th>Waktu</th>
                                    <th>Mesin</th>
                                    <th>Sensor</th>
                                    <th>Nilai</th>
                                    <th>Severity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmtAct = $db->query("
                                    SELECT a.created_at, m.name AS machine_name,
                                           a.sensor_key, a.sensor_value, a.severity, a.acknowledged
                                    FROM alerts a
                                    LEFT JOIN machines m ON m.id = a.machine_id
                                    ORDER BY a.created_at DESC LIMIT 20
                                ");
                                foreach ($stmtAct->fetchAll() as $act):
                                    $sev = $act['severity'];
                                    $sevClass = $sev === 'critical' ? 'danger' : ($sev === 'warning' ? 'warning' : 'info');
                                ?>
                                <tr>
                                    <td class="small"><?= date('d/m/y H:i', strtotime($act['created_at'])) ?></td>
                                    <td class="small"><?= htmlspecialchars($act['machine_name'] ?? '-') ?></td>
                                    <td><code class="small"><?= htmlspecialchars(strtoupper($act['sensor_key'])) ?></code></td>
                                    <td class="text-right small"><?= number_format((float)$act['sensor_value'], 2) ?></td>
                                    <td><span class="badge badge-<?= $sevClass ?>"><?= ucfirst($sev) ?></span></td>
                                    <td>
                                        <?php if ($act['acknowledged']): ?>
                                            <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Diakui</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="font-weight-bold mb-3"><i class="fas fa-wrench mr-2 text-warning"></i>Maintenance Terbaru</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover no-auto-init">
                            <thead class="thead-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Mesin</th>
                                    <th>Jenis</th>
                                    <th>Teknisi</th>
                                    <th>Deskripsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmtMnt = $db->query("
                                    SELECT mr.maint_date, m.name AS machine_name,
                                           mr.type, mr.technician, mr.description
                                    FROM maintenance_records mr
                                    LEFT JOIN machines m ON m.id = mr.machine_id
                                    ORDER BY mr.maint_date DESC LIMIT 10
                                ");
                                foreach ($stmtMnt->fetchAll() as $mnt):
                                ?>
                                <tr>
                                    <td class="small"><?= $mnt['maint_date'] ? date('d/m/Y', strtotime($mnt['maint_date'])) : '-' ?></td>
                                    <td class="small"><?= htmlspecialchars($mnt['machine_name'] ?? '-') ?></td>
                                    <td class="small"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$mnt['type'] ?? ''))) ?></td>
                                    <td class="small"><?= htmlspecialchars($mnt['technician'] ?? '-') ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars(mb_strimwidth($mnt['description'] ?? '-', 0, 40, '...')) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
            <!-- ── End Tabs ───────────────────────────────────── -->
        </div>

    </div>
</div>

<script>
$(document).ready(function () {

    // ── Inisialisasi DataTable aktivitas saat tab dibuka ────────
    // Harus lazy-init karena tabel tersembunyi saat halaman load
    var activityTableInit = false;
    $('#tab-activity').on('shown.bs.tab', function () {
        if (!activityTableInit) {
            $('#tblActivity').DataTable({
                pageLength: 10,
                order: [[0, 'desc']],
                language: { url: 'vendor/datatables/i18n/id.json' }
            });
            activityTableInit = true;
        }
    });

    // ── Toggle show/hide password ─────────────────────────────
    $(document).on('click', '.toggle-pw', function () {
        var target = $(this).data('target');
        var input  = $('#' + target);
        var icon   = $(this).find('i');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // ── Kekuatan password realtime ────────────────────────────
    $('#new_password').on('input', function () {
        var pw  = $(this).val();
        var str = 0;
        if (pw.length >= 6)                          str++;
        if (pw.length >= 10)                         str++;
        if (/[A-Z]/.test(pw) && /[a-z]/.test(pw))   str++;
        if (/[0-9]/.test(pw))                        str++;
        if (/[^A-Za-z0-9]/.test(pw))                 str++;

        var pct   = str * 20;
        var color = pct <= 40 ? '#e74a3b' : (pct <= 60 ? '#f6c23e' : '#1cc88a');
        var label = pct <= 20 ? 'Sangat Lemah' : (pct <= 40 ? 'Lemah' : (pct <= 60 ? 'Sedang' : (pct <= 80 ? 'Kuat' : 'Sangat Kuat')));

        $('#pwStrengthBar').css({ width: pct + '%', backgroundColor: color });
        $('#pwStrengthText').text(pw.length > 0 ? 'Kekuatan: ' + label : '').css('color', color);
    });

    // ── Cek kecocokan konfirmasi password ──────────────────────
    $('#confirm_password').on('input', function () {
        var pw1 = $('#new_password').val();
        var pw2 = $(this).val();
        if (pw2.length === 0) {
            $('#confirmMsg').text('').removeClass('text-danger text-success');
        } else if (pw1 === pw2) {
            $('#confirmMsg').text('✓ Password cocok').removeClass('text-danger').addClass('text-success');
        } else {
            $('#confirmMsg').text('✗ Password tidak cocok').removeClass('text-success').addClass('text-danger');
        }
    });

    // ── Validasi form password sebelum submit ─────────────────
    $('#formPassword').on('submit', function (e) {
        var pw1 = $('#new_password').val();
        var pw2 = $('#confirm_password').val();
        if (pw1 !== pw2) {
            e.preventDefault();
            alert('Konfirmasi password tidak cocok!');
            return false;
        }
    });

    // ── Buka tab yang aktif setelah POST ────────────────────────
    <?php $activeTab = $activeTab ?? 'info'; ?>
    <?php if ($activeTab === 'pw'): ?>
    $('#tab-pw').tab('show');
    <?php endif; ?>

});
</script>

<?php require_once 'includes/footer.php'; ?>
