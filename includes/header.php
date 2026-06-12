<?php
// Load config if not already loaded
if (!defined('DB_NAME')) {
    require_once __DIR__ . '/config.php';
}

// Fetch unread alert count from DB
$unreadAlertCount = 0;
try {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) FROM alerts WHERE acknowledged = 0");
    $unreadAlertCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $unreadAlertCount = 0;
}

// Current user info (assumes session is started by the calling page)
$currentUser = $_SESSION['username'] ?? 'User';
$currentRole = $_SESSION['role'] ?? 'operator';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="OEE Monitoring System">

    <title><?php echo htmlspecialchars($pageTitle ?? 'Home'); ?> | OEE Monitoring System</title>

    <!-- Custom fonts -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">

    <!-- SB Admin 2 CSS -->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">

    <!-- OEE Maroon Theme Override (cache-bust: v3) -->
    <link href="css/oee-theme.css?v=3" rel="stylesheet">

    <!-- jQuery MUST be loaded in HEAD so it is available for all inline scripts -->
    <script src="vendor/jquery/jquery.min.js"></script>

    <style>
        /* ── Sidebar: sticky + compact (collapsible toggle kept) ── */
        /* SB Admin 2 sidebar is already position:fixed — stays on scroll */
        /* Compact sizing only */
        .sidebar .nav-item .nav-link { padding: 0.5rem 1rem; }
        .sidebar .nav-item .nav-link span { font-size: .72rem; }
        .sidebar .nav-item .nav-link i { font-size: .78rem; }
        .sidebar .sidebar-heading { font-size: .54rem; padding: .6rem 1rem .2rem; }
        .sidebar hr.sidebar-divider { margin: .35rem 0; }
        .sidebar-brand { padding: .8rem 1rem !important; }
        .sidebar-brand-text { font-size: .82rem !important; }
        .sidebar .badge-counter-sidebar { font-size: .58rem; }
        /* Toggle button style — keep visible */
        #sidebarToggle { width: 2rem; height: 2rem; }
        .text-center.d-none.d-md-inline { display: inline !important; }

        /* ══ Topbar bell ════════════════════════════════════════ */
        li.nav-item.dropdown.no-arrow.mx-1 > a.nav-link {
            width: 38px; height: 38px; padding: 0;
            border-radius: 50%; background: #f1f3f9; border: 1px solid #e3e6f0;
            display: flex; align-items: center; justify-content: center;
            color: #5a5c69; font-size: .88rem; position: relative;
        }
        li.nav-item.dropdown.no-arrow.mx-1 > a.nav-link:hover { background: #e2e6f0; }
        li.nav-item.dropdown.no-arrow.mx-1 > a.nav-link::after { display: none; }
        .tb-badge {
            position: absolute; top: -3px; right: -3px;
            background: #e74a3b; color: #fff; font-size: .5rem; font-weight: 800;
            min-width: 16px; height: 16px; border-radius: 10px; padding: 0 4px;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid #fff; line-height: 1;
        }

        /* ══ Alert dropdown ═════════════════════════════════════ */
        .tb-alert-menu { min-width: 290px; border-radius: 14px; border: none; padding: 0; overflow: hidden; margin-top: 8px; }
        .tb-drop-head {
            display: flex; align-items: center; gap: 8px;
            padding: 11px 14px; background: linear-gradient(135deg,#8B1A1A,#dc2626);
            color: #fff; font-size: .76rem; font-weight: 700;
        }
        .tb-alert-item {
            display: flex !important; align-items: center; gap: 11px;
            padding: 11px 14px !important; transition: background .15s; color: #212529 !important;
        }
        .tb-alert-item:hover { background: #fff8f8 !important; }
        .tb-alert-icon { width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: .85rem; }
        .tb-alert-body { flex: 1; min-width: 0; }
        .tb-alert-title { font-size: .78rem; font-weight: 700; }
        .tb-alert-sub   { font-size: .66rem; color: #858796; margin-top: 1px; }
        .tb-alert-empty { padding: 18px 14px; text-align: center; color: #858796; font-size: .76rem; }
        .tb-drop-footer { text-align: center; padding: 7px; background: #f8f9fa; border-top: 1px solid #f1f3f5; font-size: .7rem; }
        .tb-drop-footer a { color: #8B1A1A; font-weight: 700; text-decoration: none; }

        /* ══ Topbar user pill ═══════════════════════════════════ */
        .topbar-user-card {
            display: flex; align-items: center; gap: 8px;
            padding: 4px 12px 4px 5px; border-radius: 50px;
            background: #f8f9fc; border: 1px solid #e3e6f0;
            text-decoration: none; transition: background .18s;
        }
        .topbar-user-card:hover  { background: #eaecf4; text-decoration: none; }
        .topbar-user-card::after { display: none; }
        .tb-av-wrap { position: relative; flex-shrink: 0; }
        .topbar-user-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            object-fit: cover; border: 2px solid #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,.18); display: block;
        }
        .tb-av-online {
            position: absolute; bottom: 0; right: 0;
            width: 9px; height: 9px; border-radius: 50%;
            background: #1cc88a; border: 2px solid #fff;
        }
        .topbar-user-info { line-height: 1.2; }
        .topbar-user-name { font-size: .78rem; font-weight: 700; color: #3a3b45; display: block; white-space: nowrap; }
        .topbar-user-role { font-size: .63rem; color: #858796; display: block; }
        .topbar-divider   { width: 0; border-right: 1px solid #e3e6f0; height: 28px; margin: 0 6px; align-self: center; }

        /* ══ User dropdown ══════════════════════════════════════ */
        .tb-user-menu { min-width: 210px; border-radius: 14px; border: none; padding: 0; overflow: hidden; margin-top: 8px; }
        .tb-user-drop-head { display: flex; align-items: center; gap: 11px; padding: 14px; background: linear-gradient(135deg,#8B1A1A,#dc2626); }
        .tb-udh-av-wrap { position: relative; flex-shrink: 0; }
        .tb-udh-av { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,.45); display: block; }
        .tb-udh-online { position: absolute; bottom: 1px; right: 1px; width: 10px; height: 10px; border-radius: 50%; background: #4ade80; border: 2px solid #fff; }
        .tb-udh-name { font-size: .84rem; font-weight: 800; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tb-role-badge { display: inline-block; margin-top: 4px; font-size: .58rem; font-weight: 700; padding: 2px 8px; border-radius: 20px; background: rgba(255,255,255,.2); color: #fff; text-transform: uppercase; letter-spacing: .3px; }
        .tb-drop-item { display: flex !important; align-items: center; gap: 9px; padding: 9px 14px !important; font-size: .8rem; color: #3a3b45 !important; transition: background .15s; }
        .tb-drop-item:hover { background: #f8f9fa !important; }
        .tb-di-icon { width: 28px; height: 28px; border-radius: 7px; background: #f1f3f5; display: flex; align-items: center; justify-content: center; font-size: .75rem; color: #6c757d; flex-shrink: 0; }
        .tb-drop-item:hover .tb-di-icon { background: #e9ecef; }
        .tb-drop-logout { color: #dc2626 !important; }
        .tb-drop-logout .tb-di-icon { background: #fef2f2; color: #dc2626; }
        .tb-drop-logout:hover { background: #fff5f5 !important; }

        /* Status Indicators */
        .status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .status-dot.online {
            background-color: #1cc88a;
        }

        .status-dot.offline {
            background-color: #e74a3b;
        }

        .status-dot.warning {
            background-color: #f6c23e;
        }

        .status-dot.idle {
            background-color: #858796;
        }

        /* Pulse animation for active/live status */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(28, 200, 138, 0.7);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(28, 200, 138, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(28, 200, 138, 0);
            }
        }

        .status-dot.online.pulse {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse-warning {
            0% {
                box-shadow: 0 0 0 0 rgba(246, 194, 62, 0.7);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(246, 194, 62, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(246, 194, 62, 0);
            }
        }

        .status-dot.warning.pulse {
            animation: pulse-warning 1.5s infinite;
        }

        @keyframes pulse-danger {
            0% {
                box-shadow: 0 0 0 0 rgba(231, 74, 59, 0.7);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(231, 74, 59, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(231, 74, 59, 0);
            }
        }

        .status-dot.offline.pulse {
            animation: pulse-danger 1.5s infinite;
        }

        /* Sidebar nav item label */
        .sidebar .nav-item .nav-link span {
            font-size: 0.85rem;
        }

        /* Alert badge in sidebar */
        .sidebar .badge-counter-sidebar {
            font-size: 0.65rem;
            position: relative;
            top: -1px;
            margin-left: 4px;
        }

        /* Topbar alert count badge */
        .topbar .badge-counter {
            position: absolute;
            transform: scale(0.7);
            transform-origin: top right;
            right: .25rem;
            top: .25rem;
        }

        /* Ensure sidebar brand icon aligns */
        .sidebar-brand-icon i {
            font-size: 1.5rem;
        }
    </style>
</head>

<body id="page-top">

<!-- Page Wrapper -->
<div id="wrapper">

    <!-- Sidebar -->
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

        <!-- Sidebar - Brand -->
        <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
            <div class="sidebar-brand-icon" style="background:transparent;width:36px;height:36px;">
                <img src="img/yanmar.png" alt="Yanmar" style="width:36px;height:36px;object-fit:contain;filter:brightness(0) invert(1);">
            </div>
            <div class="sidebar-brand-text mx-3">OEE Monitoring</div>
        </a>

        <!-- Divider -->
        <hr class="sidebar-divider my-0">

        <!-- Nav Item - Dashboard -->
        <li class="nav-item <?php echo ($currentPage === 'dashboard') ? 'active' : ''; ?>">
            <a class="nav-link" href="index.php">
                <i class="fas fa-fw fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- Divider -->
        <hr class="sidebar-divider">

        <!-- Heading - MONITORING -->
        <div class="sidebar-heading">
            Monitoring
        </div>

        <!-- Nav Item - Machine Status -->
        <li class="nav-item <?php echo ($currentPage === 'machines') ? 'active' : ''; ?>">
            <a class="nav-link" href="machines.php">
                <i class="fas fa-fw fa-cogs"></i>
                <span>Machine Status</span>
            </a>
        </li>

        <!-- Nav Item - ESP32 Monitor -->
        <li class="nav-item <?php echo ($currentPage === 'esp32_monitor') ? 'active' : ''; ?>">
            <a class="nav-link" href="esp32_monitor.php">
                <i class="fas fa-fw fa-broadcast-tower"></i>
                <span>ESP32 Monitor</span>
            </a>
        </li>

        <!-- Nav Item - ESP32 Config -->
        <li class="nav-item <?php echo ($currentPage === 'esp32_config') ? 'active' : ''; ?>">
            <a class="nav-link" href="esp32_config.php">
                <i class="fas fa-fw fa-sliders-h"></i>
                <span>ESP32 Config</span>
            </a>
        </li>

        <!-- Nav Item - TV Dashboard -->
        <li class="nav-item">
            <a class="nav-link" href="tv_dashboard.php" target="_blank">
                <i class="fas fa-fw fa-tv"></i>
                <span>TV Dashboard</span>
                <span class="badge badge-pill" style="background:#F5C518;color:#333;font-size:.55rem;margin-left:4px;">LIVE</span>
            </a>
        </li>

        <!-- Divider -->
        <hr class="sidebar-divider">

        <!-- Heading - ANALYSIS -->
        <div class="sidebar-heading">
            Analysis
        </div>

        <!-- Nav Item - Machine Detail -->
        <li class="nav-item <?php echo ($currentPage === 'machine_detail') ? 'active' : ''; ?>">
            <a class="nav-link" href="machine_detail.php">
                <i class="fas fa-fw fa-microscope"></i>
                <span>Machine Detail</span>
            </a>
        </li>

        <!-- Nav Item - Vibration Analysis (+ Portable Monitor) -->
        <li class="nav-item <?php echo in_array($currentPage, ['vibration','vibration_portable']) ? 'active' : ''; ?>">
            <a class="nav-link" href="vibration.php">
                <i class="fas fa-fw fa-wave-square"></i>
                <span>Vibration Analysis</span>
                <span class="badge badge-primary ml-1" style="font-size:.55rem;">+Portable</span>
            </a>
        </li>

        <!-- Nav Item - Input OEE (Jam Kerja · Target · Produksi) -->
        <li class="nav-item <?php echo in_array($currentPage, ['oee_input','shift_input']) ? 'active' : ''; ?>">
            <a class="nav-link" href="oee_input.php">
                <i class="fas fa-fw fa-clipboard-list"></i>
                <span>Input OEE</span>
            </a>
        </li>

        <!-- Nav Item - Energy Report -->
        <li class="nav-item <?php echo ($currentPage === 'energy') ? 'active' : ''; ?>">
            <a class="nav-link" href="energy.php">
                <i class="fas fa-fw fa-bolt"></i>
                <span>Energy Report</span>
            </a>
        </li>

        <!-- Divider -->
        <hr class="sidebar-divider">

        <!-- Heading - MANAGEMENT -->
        <div class="sidebar-heading">
            Management
        </div>

        <!-- Nav Item - Alerts -->
        <li class="nav-item <?php echo ($currentPage === 'alerts') ? 'active' : ''; ?>">
            <a class="nav-link" href="alerts.php">
                <i class="fas fa-fw fa-bell"></i>
                <span>Alert</span>
                <?php if ($unreadAlertCount > 0): ?>
                    <span class="badge badge-danger badge-counter-sidebar"><?php echo $unreadAlertCount > 99 ? '99+' : $unreadAlertCount; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Nav Item - Maintenance -->
        <li class="nav-item <?php echo ($currentPage === 'maintenance') ? 'active' : ''; ?>">
            <a class="nav-link" href="maintenance.php">
                <i class="fas fa-fw fa-wrench"></i>
                <span>Maintenance</span>
            </a>
        </li>

        <!-- Nav Item - Reports & Export -->
        <li class="nav-item <?php echo ($currentPage === 'reports') ? 'active' : ''; ?>">
            <a class="nav-link" href="reports.php">
                <i class="fas fa-fw fa-file-export"></i>
                <span>Reports &amp; Export</span>
            </a>
        </li>

        <?php if (strtolower($currentRole) === 'admin'): ?>
        <!-- Divider -->
        <hr class="sidebar-divider">

        <!-- Heading - ADMIN -->
        <div class="sidebar-heading">
            Admin
        </div>

        <!-- Nav Item - Users -->
        <li class="nav-item <?php echo ($currentPage === 'users') ? 'active' : ''; ?>">
            <a class="nav-link" href="users.php">
                <i class="fas fa-fw fa-users"></i>
                <span>Users</span>
            </a>
        </li>

        <!-- Nav Item - Settings -->
        <li class="nav-item <?php echo ($currentPage === 'settings') ? 'active' : ''; ?>">
            <a class="nav-link" href="settings.php">
                <i class="fas fa-fw fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Divider -->
        <hr class="sidebar-divider d-none d-md-block">

        <!-- Sidebar Toggler (Sidebar) -->
        <div class="text-center d-none d-md-inline">
            <button class="rounded-circle border-0" id="sidebarToggle"></button>
        </div>

    </ul>
    <!-- End of Sidebar -->

    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">

        <!-- Main Content -->
        <div id="content">

            <!-- Topbar -->
            <nav class="navbar navbar-expand topbar mb-4 static-top shadow navbar-light bg-white">

                <!-- Sidebar Toggle (Topbar) -->
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                    <i class="fa fa-bars"></i>
                </button>


                <!-- Topbar Navbar -->
                <ul class="navbar-nav ml-auto">


                    <!-- Nav Item - Alerts -->
                    <li class="nav-item dropdown no-arrow mx-1">
                        <a class="nav-link tb-icon-btn" href="#" id="alertsDropdown" role="button"
                           data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadAlertCount > 0): ?>
                                <span class="tb-badge"><?php echo $unreadAlertCount > 99 ? '99+' : $unreadAlertCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in tb-alert-menu" aria-labelledby="alertsDropdown">
                            <div class="tb-drop-head">
                                <i class="fas fa-bell mr-2"></i>Alert Center
                                <?php if ($unreadAlertCount > 0): ?>
                                    <span class="badge badge-danger ml-auto" style="font-size:.6rem;"><?php echo $unreadAlertCount; ?> unread</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($unreadAlertCount > 0): ?>
                                <a class="dropdown-item tb-alert-item" href="alerts.php">
                                    <div class="tb-alert-icon bg-danger"><i class="fas fa-exclamation text-white"></i></div>
                                    <div class="tb-alert-body">
                                        <div class="tb-alert-title"><?php echo $unreadAlertCount; ?> alert<?php echo $unreadAlertCount > 1 ? 's' : ''; ?> perlu ditinjau</div>
                                        <div class="tb-alert-sub">Klik untuk melihat detail</div>
                                    </div>
                                    <i class="fas fa-chevron-right tb-alert-arrow"></i>
                                </a>
                            <?php else: ?>
                                <div class="tb-alert-empty">
                                    <i class="fas fa-check-circle text-success mb-2" style="font-size:1.5rem;"></i><br>
                                    Tidak ada alert baru
                                </div>
                            <?php endif; ?>
                            <div class="tb-drop-footer"><a href="alerts.php">Lihat Semua Alert <i class="fas fa-arrow-right ml-1"></i></a></div>
                        </div>
                    </li>

                    <div class="topbar-divider d-none d-sm-block"></div>

                    <!-- Nav Item - User Information -->
                    <?php $tbUid = (int)($_SESSION['user_id'] ?? 0); ?>
                    <li class="nav-item dropdown no-arrow">
                        <a class="topbar-user-card dropdown-toggle" href="#" id="userDropdown" role="button"
                           data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <div class="tb-av-wrap">
                                <img class="topbar-user-avatar" id="tbUserAv"
                                     src="api/avatar.php?id=<?php echo $tbUid; ?>&v=<?php echo time(); ?>"
                                     alt="<?php echo htmlspecialchars($currentUser); ?>"
                                     onerror="this.src='img/undraw_profile.svg'">
                                <span class="tb-av-online"></span>
                            </div>
                            <div class="topbar-user-info d-none d-lg-block">
                                <span class="topbar-user-name"><?php echo htmlspecialchars($currentUser); ?></span>
                                <span class="topbar-user-role"><?php echo htmlspecialchars(ucfirst($currentRole)); ?></span>
                            </div>
                        </a>
                        <!-- Dropdown -->
                        <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in tb-user-menu" aria-labelledby="userDropdown">
                            <!-- Profile header -->
                            <div class="tb-user-drop-head">
                                <div class="tb-udh-av-wrap">
                                    <img class="tb-udh-av"
                                         src="api/avatar.php?id=<?php echo $tbUid; ?>&v=<?php echo time(); ?>"
                                         alt="" onerror="this.src='img/undraw_profile.svg'">
                                    <span class="tb-udh-online"></span>
                                </div>
                                <div class="tb-udh-info">
                                    <div class="tb-udh-name"><?php echo htmlspecialchars($currentUser); ?></div>
                                    <div class="tb-udh-role">
                                        <span class="tb-role-badge"><?php echo htmlspecialchars(ucfirst($currentRole)); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown-divider m-0"></div>
                            <a class="dropdown-item tb-drop-item" href="profile.php">
                                <span class="tb-di-icon"><i class="fas fa-user-circle"></i></span>
                                <span>Profile</span>
                            </a>
                            <a class="dropdown-item tb-drop-item" href="settings.php">
                                <span class="tb-di-icon"><i class="fas fa-sliders-h"></i></span>
                                <span>Settings</span>
                            </a>
                            <div class="dropdown-divider m-0"></div>
                            <a class="dropdown-item tb-drop-item tb-drop-logout" href="logout.php">
                                <span class="tb-di-icon"><i class="fas fa-sign-out-alt"></i></span>
                                <span>Logout</span>
                            </a>
                        </div>
                    </li>

                </ul>

            </nav>
            <!-- End of Topbar -->

            <!-- Begin Page Content -->
            <div class="container-fluid">
