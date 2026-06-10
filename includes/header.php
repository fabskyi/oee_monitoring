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
<html lang="id">

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

    <!-- jQuery HARUS load di HEAD agar tersedia untuk semua inline script -->
    <script src="vendor/jquery/jquery.min.js"></script>

    <style>
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
            <div class="sidebar-brand-icon rotate-n-15">
                <i class="fas fa-cog"></i>
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

        <!-- Nav Item - Status Mesin -->
        <li class="nav-item <?php echo ($currentPage === 'machines') ? 'active' : ''; ?>">
            <a class="nav-link" href="machines.php">
                <i class="fas fa-fw fa-cogs"></i>
                <span>Status Mesin</span>
            </a>
        </li>

        <!-- Nav Item - ESP32 Monitor -->
        <li class="nav-item <?php echo ($currentPage === 'esp32_monitor') ? 'active' : ''; ?>">
            <a class="nav-link" href="esp32_monitor.php">
                <i class="fas fa-fw fa-broadcast-tower"></i>
                <span>ESP32 Monitor</span>
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

        <!-- Heading - ANALISIS -->
        <div class="sidebar-heading">
            Analisis
        </div>

        <!-- Nav Item - Detail Mesin -->
        <li class="nav-item <?php echo ($currentPage === 'machine_detail') ? 'active' : ''; ?>">
            <a class="nav-link" href="machine_detail.php">
                <i class="fas fa-fw fa-microscope"></i>
                <span>Detail Mesin</span>
            </a>
        </li>

        <!-- Nav Item - Analisis Vibrasi (+ Portable Monitor) -->
        <li class="nav-item <?php echo in_array($currentPage, ['vibration','vibration_portable']) ? 'active' : ''; ?>">
            <a class="nav-link" href="vibration.php">
                <i class="fas fa-fw fa-wave-square"></i>
                <span>Analisis Vibrasi</span>
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

        <!-- Nav Item - Laporan Energi -->
        <li class="nav-item <?php echo ($currentPage === 'energy') ? 'active' : ''; ?>">
            <a class="nav-link" href="energy.php">
                <i class="fas fa-fw fa-bolt"></i>
                <span>Laporan Energi</span>
            </a>
        </li>

        <!-- Divider -->
        <hr class="sidebar-divider">

        <!-- Heading - MANAJEMEN -->
        <div class="sidebar-heading">
            Manajemen
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

        <!-- Nav Item - Laporan & Export -->
        <li class="nav-item <?php echo ($currentPage === 'reports') ? 'active' : ''; ?>">
            <a class="nav-link" href="reports.php">
                <i class="fas fa-fw fa-file-export"></i>
                <span>Laporan &amp; Export</span>
            </a>
        </li>

        <?php if (strtolower($currentRole) === 'admin'): ?>
        <!-- Divider -->
        <hr class="sidebar-divider">

        <!-- Heading - ADMIN -->
        <div class="sidebar-heading">
            Admin
        </div>

        <!-- Nav Item - Pengguna -->
        <li class="nav-item <?php echo ($currentPage === 'users') ? 'active' : ''; ?>">
            <a class="nav-link" href="users.php">
                <i class="fas fa-fw fa-users"></i>
                <span>Pengguna</span>
            </a>
        </li>

        <!-- Nav Item - Pengaturan -->
        <li class="nav-item <?php echo ($currentPage === 'settings') ? 'active' : ''; ?>">
            <a class="nav-link" href="settings.php">
                <i class="fas fa-fw fa-cog"></i>
                <span>Pengaturan</span>
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

                <!-- Topbar Search -->
                <form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search">
                    <div class="input-group">
                        <input type="text" class="form-control bg-light border-0 small" placeholder="Cari mesin, alert..." aria-label="Search" aria-describedby="basic-addon2">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button">
                                <i class="fas fa-search fa-sm"></i>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Topbar Navbar -->
                <ul class="navbar-nav ml-auto">

                    <!-- Nav Item - Search Dropdown (Visible Only XS) -->
                    <li class="nav-item dropdown no-arrow d-sm-none">
                        <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-search fa-fw"></i>
                        </a>
                        <!-- Dropdown - Messages -->
                        <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in" aria-labelledby="searchDropdown">
                            <form class="form-inline mr-auto w-100 navbar-search">
                                <div class="input-group">
                                    <input type="text" class="form-control bg-light border-0 small" placeholder="Cari..." aria-label="Search" aria-describedby="basic-addon2">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" type="button">
                                            <i class="fas fa-search fa-sm"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </li>

                    <!-- Nav Item - Alerts -->
                    <li class="nav-item dropdown no-arrow mx-1">
                        <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-bell fa-fw"></i>
                            <?php if ($unreadAlertCount > 0): ?>
                                <span class="badge badge-danger badge-counter"><?php echo $unreadAlertCount > 99 ? '99+' : $unreadAlertCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <!-- Dropdown - Alerts -->
                        <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="alertsDropdown">
                            <h6 class="dropdown-header">
                                Alert Center
                            </h6>
                            <?php if ($unreadAlertCount > 0): ?>
                                <a class="dropdown-item d-flex align-items-center" href="alerts.php">
                                    <div class="mr-3">
                                        <div class="icon-circle bg-danger">
                                            <i class="fas fa-bell text-white"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="small text-gray-500">Belum Dibaca</div>
                                        <span class="font-weight-bold"><?php echo $unreadAlertCount; ?> alert menunggu perhatian</span>
                                    </div>
                                </a>
                            <?php else: ?>
                                <a class="dropdown-item text-center small text-gray-500" href="alerts.php">Tidak ada alert baru</a>
                            <?php endif; ?>
                            <a class="dropdown-item text-center small text-gray-500" href="alerts.php">Lihat Semua Alert</a>
                        </div>
                    </li>

                    <div class="topbar-divider d-none d-sm-block"></div>

                    <!-- Nav Item - User Information -->
                    <li class="nav-item dropdown no-arrow">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                <?php echo htmlspecialchars($currentUser); ?>
                                <br>
                                <small class="text-gray-400"><?php echo htmlspecialchars(ucfirst($currentRole)); ?></small>
                            </span>
                            <img class="img-profile rounded-circle" src="img/undraw_profile.svg" alt="User">
                        </a>
                        <!-- Dropdown - User Information -->
                        <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                            <div class="dropdown-header text-center">
                                <strong><?php echo htmlspecialchars($currentUser); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars(ucfirst($currentRole)); ?></small>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                Profil
                            </a>
                            <a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
                                Pengaturan
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                Logout
                            </a>
                        </div>
                    </li>

                </ul>

            </nav>
            <!-- End of Topbar -->

            <!-- Begin Page Content -->
            <div class="container-fluid">
