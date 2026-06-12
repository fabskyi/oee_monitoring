<?php
$requiredRole = 'admin';
require_once 'includes/config.php';
require_once 'includes/auth_check.php';

// Query all users
$db = getDB();
$stmt = $db->query("SELECT id, username, full_name, email, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalUsers = count($users);
$activeUsers = 0;
$onlineNow = 0;
$now = time();

foreach ($users as &$u) {
    if ($u['is_active'])
        $activeUsers++;
    if ($u['last_login']) {
        $diff = $now - strtotime($u['last_login']);
        if ($diff <= 1800)
            $onlineNow++;
        if ($diff < 60) {
            $u['last_login_ago'] = 'Just now';
        } elseif ($diff < 3600) {
            $u['last_login_ago'] = floor($diff / 60) . ' minutes ago';
        } elseif ($diff < 86400) {
            $u['last_login_ago'] = floor($diff / 3600) . ' hours ago';
        } else {
            $u['last_login_ago'] = floor($diff / 86400) . ' days ago';
        }
    } else {
        $u['last_login_ago'] = 'Never';
    }
}
unset($u);

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

$pageTitle = 'User Management';
$currentPage = 'users';
require_once 'includes/header.php';
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">User Management</h1>
    <button class="btn btn-primary btn-sm shadow-sm" data-toggle="modal" data-target="#addUserModal">
        <i class="fas fa-user-plus fa-sm text-white-50"></i> Add User
    </button>
</div>

<!-- Stat Cards -->
<div class="row mb-4">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalUsers; ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $activeUsers; ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-user-check fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Online Now <small
                                class="text-muted">(30 min)</small></div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $onlineNow; ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-circle fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users DataTable -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">User List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="usersTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th width="50">Avatar</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th width="130">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $initial = strtoupper(mb_substr($u['full_name'], 0, 1));
                        $avatarColors = ['admin' => '#8B1A1A', 'operator' => '#f6c23e', 'viewer' => '#36b9cc'];
                        $avatarBg = $avatarColors[$u['role']] ?? '#858796';
                        $avatarTextColor = ($u['role'] === 'operator') ? '#212529' : '#ffffff';
                        ?>
                        <tr>
                            <td class="text-center align-middle">
                                <div
                                    style="width:38px;height:38px;border-radius:50%;background:<?php echo $avatarBg; ?>;color:<?php echo $avatarTextColor; ?>;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;">
                                    <?php echo htmlspecialchars($initial); ?>
                                </div>
                            </td>
                            <td class="align-middle"><?php echo htmlspecialchars($u['full_name']); ?></td>
                            <td class="align-middle"><?php echo htmlspecialchars($u['username']); ?></td>
                            <td class="align-middle"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td class="align-middle">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge badge-danger">Admin</span>
                                <?php elseif ($u['role'] === 'operator'): ?>
                                    <span class="badge badge-warning text-dark">Operator</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Viewer</span>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle">
                                <?php if ($u['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle">
                                <small><?php echo htmlspecialchars($u['last_login_ago']); ?></small>
                            </td>
                            <td class="align-middle text-nowrap">
                                <button class="btn btn-sm btn-primary btn-edit mr-1" data-id="<?php echo $u['id']; ?>"
                                    title="Edit" data-toggle="tooltip">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-warning btn-reset-pwd mr-1" data-id="<?php echo $u['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($u['full_name'], ENT_QUOTES); ?>"
                                    title="Reset Password" data-toggle="tooltip">
                                    <i class="fas fa-key"></i>
                                </button>
                                <?php if ($u['id'] !== $currentUserId): ?>
                                    <button
                                        class="btn btn-sm <?php echo $u['is_active'] ? 'btn-danger' : 'btn-success'; ?> btn-toggle-active"
                                        data-id="<?php echo $u['id']; ?>" data-active="<?php echo $u['is_active']; ?>"
                                        data-name="<?php echo htmlspecialchars($u['full_name'], ENT_QUOTES); ?>"
                                        title="<?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                        data-toggle="tooltip">
                                        <i class="fas <?php echo $u['is_active'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled title="Cannot deactivate your own account"
                                        data-toggle="tooltip">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===================== ADD USER MODAL ===================== -->
<div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="addUserModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addUserModalLabel"><i class="fas fa-user-plus mr-2"></i>Add New User</h5>
                <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addUserForm" novalidate>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="full_name" id="add_full_name" required
                                    placeholder="Enter full name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" id="add_username" required
                                    placeholder="Enter username">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" id="add_email" required
                                    placeholder="Enter email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Role <span class="text-danger">*</span></label>
                                <select class="form-control" name="role" id="add_role" required>
                                    <option value="viewer">Viewer</option>
                                    <option value="operator">Operator</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password" id="add_password" required
                                    placeholder="Minimum 6 characters">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="confirm_password"
                                    id="add_confirm_password" required placeholder="Repeat password">
                                <div class="invalid-feedback" id="add_pwd_mismatch">Passwords do not match.</div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" name="is_active" id="add_is_active"
                                value="1" checked>
                            <label class="custom-control-label" for="add_is_active">Activate this user</label>
                        </div>
                    </div>
                    <div id="addUserAlert" class="alert d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addUserBtn">
                        <i class="fas fa-save mr-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===================== EDIT USER MODAL ===================== -->
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editUserModalLabel"><i class="fas fa-edit mr-2"></i>Edit User</h5>
                <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editUserForm" novalidate>
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" class="form-control" name="full_name" id="edit_full_name"
                                    placeholder="Full name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" class="form-control" name="username" id="edit_username"
                                    placeholder="Username">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email"
                                    placeholder="Email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Role</label>
                                <select class="form-control" name="role" id="edit_role">
                                    <option value="viewer">Viewer</option>
                                    <option value="operator">Operator</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>New Password <small class="text-muted">(leave blank to keep
                                        unchanged)</small></label>
                                <input type="password" class="form-control" name="password" id="edit_password"
                                    placeholder="Minimum 6 characters">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password"
                                    id="edit_confirm_password" placeholder="Repeat new password">
                                <div class="invalid-feedback" id="edit_pwd_mismatch">Passwords do not match.</div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" name="is_active" id="edit_is_active"
                                value="1">
                            <label class="custom-control-label" for="edit_is_active">Active user</label>
                        </div>
                    </div>
                    <div id="editUserAlert" class="alert d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editUserBtn">
                        <i class="fas fa-save mr-1"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===================== RESET PASSWORD MODAL ===================== -->
<div class="modal fade" id="resetPwdModal" tabindex="-1" role="dialog" aria-labelledby="resetPwdModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="resetPwdModalLabel"><i class="fas fa-key mr-2"></i>Reset Password</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="resetPwdForm" novalidate>
                <input type="hidden" name="id" id="reset_id">
                <div class="modal-body">
                    <p>Reset password untuk: <strong id="reset_user_name"></strong></p>
                    <div class="form-group">
                        <label>New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="new_password" id="reset_new_password" required
                            placeholder="Minimum 6 characters">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="reset_confirm_password" required
                            placeholder="Repeat new password">
                        <div class="invalid-feedback" id="reset_pwd_mismatch">Passwords do not match.</div>
                    </div>
                    <div id="resetPwdAlert" class="alert d-none" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="resetPwdBtn">
                        <i class="fas fa-key mr-1"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div aria-live="polite" aria-atomic="true" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;">
    <div id="toastNotif" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="3500">
        <div class="toast-header" id="toastHeader">
            <i class="fas mr-2" id="toastIcon"></i>
            <strong class="mr-auto" id="toastTitle">Notification</strong>
            <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<!-- ===================== PAGE SCRIPTS ===================== -->
<script>
    $(document).ready(function () {

        // Init DataTable
        var table = $('#usersTable').DataTable({
            responsive: true,
            order: [[1, 'asc']],
            columnDefs: [
                { orderable: false, targets: [0, 7] }
            ],
            language: {
                url: 'vendor/datatables/i18n/id.json'
            }
        });

        // Init tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // =========================================================
        // showToast
        // =========================================================
        function showToast(message, type) {
            type = type || 'success';
            var iconClass = type === 'success'
                ? 'fa-check-circle text-success'
                : 'fa-exclamation-circle text-danger';
            var titleText = type === 'success' ? 'Success' : 'Failed';
            $('#toastIcon').attr('class', 'fas mr-2 ' + iconClass);
            $('#toastTitle').text(titleText);
            $('#toastBody').text(message);
            $('#toastNotif').toast('show');
        }

        // =========================================================
        // Helper: show/hide alert inside modal
        // =========================================================
        function showModalAlert(alertId, message, type) {
            $('#' + alertId)
                .removeClass('d-none alert-success alert-danger alert-warning')
                .addClass('alert-' + (type || 'danger'))
                .text(message)
                .show();
        }

        function hideModalAlert(alertId) {
            $('#' + alertId).addClass('d-none').hide();
        }

        // =========================================================
        // ADD USER
        // =========================================================
        $('#addUserModal').on('hidden.bs.modal', function () {
            $('#addUserForm')[0].reset();
            hideModalAlert('addUserAlert');
            $('#add_confirm_password').removeClass('is-invalid');
        });

        $('#addUserForm').on('submit', function (e) {
            e.preventDefault();
            hideModalAlert('addUserAlert');

            var pwd = $('#add_password').val();
            var cpwd = $('#add_confirm_password').val();
            if (pwd !== cpwd) {
                $('#add_confirm_password').addClass('is-invalid');
                return;
            }
            $('#add_confirm_password').removeClass('is-invalid');

            $('#addUserBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

            $.ajax({
                url: 'api/users.php?action=create',
                type: 'POST',
                data: {
                    full_name: $('#add_full_name').val(),
                    username: $('#add_username').val(),
                    email: $('#add_email').val(),
                    password: pwd,
                    role: $('#add_role').val(),
                    is_active: $('#add_is_active').is(':checked') ? 1 : 0
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        $('#addUserModal').modal('hide');
                        showToast('User added successfully.', 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        showModalAlert('addUserAlert', res.message || 'Failed to add user.', 'danger');
                    }
                },
                error: function () {
                    showModalAlert('addUserAlert', 'A network error occurred.', 'danger');
                },
                complete: function () {
                    $('#addUserBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save');
                }
            });
        });

        // =========================================================
        // EDIT USER — open modal & prefill
        // =========================================================
        $(document).on('click', '.btn-edit', function () {
            var id = $(this).data('id');
            hideModalAlert('editUserAlert');
            $('#edit_confirm_password').removeClass('is-invalid');
            $('#editUserForm')[0].reset();

            $.get('api/users.php', { action: 'get', id: id }, function (res) {
                if (res.success && res.data) {
                    var u = res.data;
                    $('#edit_id').val(u.id);
                    $('#edit_full_name').val(u.full_name);
                    $('#edit_username').val(u.username);
                    $('#edit_email').val(u.email);
                    $('#edit_role').val(u.role);
                    $('#edit_is_active').prop('checked', u.is_active == 1);
                    $('#editUserModal').modal('show');
                } else {
                    showToast('Failed to load user data.', 'error');
                }
            }, 'json').fail(function () {
                showToast('A network error occurred.', 'error');
            });
        });

        $('#editUserModal').on('hidden.bs.modal', function () {
            $('#editUserForm')[0].reset();
            hideModalAlert('editUserAlert');
            $('#edit_confirm_password').removeClass('is-invalid');
        });

        $('#editUserForm').on('submit', function (e) {
            e.preventDefault();
            hideModalAlert('editUserAlert');

            var pwd = $('#edit_password').val();
            var cpwd = $('#edit_confirm_password').val();
            if (pwd && pwd !== cpwd) {
                $('#edit_confirm_password').addClass('is-invalid');
                return;
            }
            $('#edit_confirm_password').removeClass('is-invalid');

            $('#editUserBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

            var formData = {
                id: $('#edit_id').val(),
                full_name: $('#edit_full_name').val(),
                username: $('#edit_username').val(),
                email: $('#edit_email').val(),
                role: $('#edit_role').val(),
                is_active: $('#edit_is_active').is(':checked') ? 1 : 0
            };
            if (pwd) formData.password = pwd;

            $.ajax({
                url: 'api/users.php?action=update',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        $('#editUserModal').modal('hide');
                        showToast('User data updated successfully.', 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        showModalAlert('editUserAlert', res.message || 'Failed to update user.', 'danger');
                    }
                },
                error: function () {
                    showModalAlert('editUserAlert', 'A network error occurred.', 'danger');
                },
                complete: function () {
                    $('#editUserBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Update');
                }
            });
        });

        // =========================================================
        // RESET PASSWORD
        // =========================================================
        $(document).on('click', '.btn-reset-pwd', function () {
            $('#reset_id').val($(this).data('id'));
            $('#reset_user_name').text($(this).data('name'));
            $('#resetPwdForm')[0].reset();
            hideModalAlert('resetPwdAlert');
            $('#reset_confirm_password').removeClass('is-invalid');
            $('#resetPwdModal').modal('show');
        });

        $('#resetPwdModal').on('hidden.bs.modal', function () {
            $('#resetPwdForm')[0].reset();
            hideModalAlert('resetPwdAlert');
            $('#reset_confirm_password').removeClass('is-invalid');
        });

        $('#resetPwdForm').on('submit', function (e) {
            e.preventDefault();
            hideModalAlert('resetPwdAlert');

            var pwd = $('#reset_new_password').val();
            var cpwd = $('#reset_confirm_password').val();
            if (pwd !== cpwd) {
                $('#reset_confirm_password').addClass('is-invalid');
                return;
            }
            $('#reset_confirm_password').removeClass('is-invalid');

            $('#resetPwdBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Resetting...');

            $.ajax({
                url: 'api/users.php?action=reset_password',
                type: 'POST',
                data: { id: $('#reset_id').val(), new_password: pwd },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        $('#resetPwdModal').modal('hide');
                        showToast('Password reset successfully.', 'success');
                    } else {
                        showModalAlert('resetPwdAlert', res.message || 'Failed to reset password.', 'danger');
                    }
                },
                error: function () {
                    showModalAlert('resetPwdAlert', 'A network error occurred.', 'danger');
                },
                complete: function () {
                    $('#resetPwdBtn').prop('disabled', false).html('<i class="fas fa-key mr-1"></i> Reset Password');
                }
            });
        });

        // =========================================================
        // TOGGLE ACTIVE
        // =========================================================
        $(document).on('click', '.btn-toggle-active', function () {
            var id = $(this).data('id');
            var active = $(this).data('active');
            var name = $(this).data('name');
            var msg = active == 1
                ? 'Are you sure you want to deactivate user "' + name + '"?'
                : 'Reactivate user "' + name + '"?';

            if (!confirm(msg)) return;

            var $btn = $(this).prop('disabled', true);

            $.ajax({
                url: 'api/users.php?action=toggle_active',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        showToast(res.message, 'success');
                        setTimeout(function () { location.reload(); }, 800);
                    } else {
                        showToast(res.message || 'Failed to change status.', 'error');
                        $btn.prop('disabled', false);
                    }
                },
                error: function () {
                    showToast('A network error occurred.', 'error');
                    $btn.prop('disabled', false);
                }
            });
        });

    });
</script>