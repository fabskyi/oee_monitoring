<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Handle remember me cookie
$remembered_username = '';
if (isset($_COOKIE['remember_username'])) {
    $remembered_username = htmlspecialchars($_COOKIE['remember_username']);
}

// Handle POST login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        // DB connection
        $host = 'localhost';
        $db   = 'oee_monitoring';
        $user = 'root';
        $pass = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $row = $stmt->fetch();

            if ($row && password_verify($password, $row['password_hash'])) {
                if (isset($row['is_active']) && $row['is_active'] == 0) {
                    $error = 'Akun Anda tidak aktif. Hubungi administrator.';
                } else {
                    // Update last_login
                    $upd = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                    $upd->execute([$row['id']]);

                    // Set session
                    $_SESSION['user_id']   = $row['id'];
                    $_SESSION['username']  = $row['username'];
                    $_SESSION['full_name'] = $row['full_name'] ?? $row['username'];
                    $_SESSION['role']      = $row['role'] ?? 'operator';
                    $_SESSION['user_role'] = $row['role'] ?? 'operator';

                    // Remember me cookie (30 days)
                    if ($remember) {
                        setcookie('remember_username', $username, time() + (30 * 24 * 3600), '/');
                    } else {
                        setcookie('remember_username', '', time() - 3600, '/');
                    }

                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error = 'Username atau password salah.';
            }
        } catch (PDOException $e) {
            $error = 'Kesalahan koneksi database. Silakan coba lagi.';
            // Uncomment for debug: $error .= ' ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="OEE Monitoring System - PT. YADIN">
    <title>Login &mdash; OEE Monitoring System</title>

    <!-- Custom fonts -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/oee-theme.css" rel="stylesheet">

    <style>
        .login-left-panel {
            background: linear-gradient(135deg, #1a237e 0%, #283593 40%, #1565c0 100%);
            min-height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 30px;
            color: #fff;
            border-radius: 0.35rem 0 0 0.35rem;
        }
        .login-left-panel .brand-title {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }
        .login-left-panel .brand-subtitle {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-bottom: 30px;
            text-align: center;
        }
        .login-left-panel .icon-placeholder {
            width: 110px;
            height: 110px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
        }
        .login-left-panel .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
            text-align: left;
            width: 100%;
        }
        .login-left-panel .feature-list li {
            padding: 7px 0;
            font-size: 0.875rem;
            opacity: 0.9;
        }
        .login-left-panel .feature-list li i {
            margin-right: 10px;
            color: #80d8ff;
        }
        .password-wrapper {
            position: relative;
        }
        .password-wrapper .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #b7b9cc;
            background: none;
            border: none;
            padding: 0;
            line-height: 1;
        }
        .password-wrapper .toggle-password:focus {
            outline: none;
        }
        .card-login {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15) !important;
        }
        @media (max-width: 767px) {
            .login-left-panel {
                border-radius: 0.35rem 0.35rem 0 0;
                min-height: auto;
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body class="bg-gradient-primary">

<div class="container">
    <div class="row justify-content-center" style="min-height:100vh; align-items:center;">
        <div class="col-xl-10 col-lg-12 col-md-10">
            <div class="card o-hidden border-0 shadow-lg my-5 card-login">
                <div class="card-body p-0">
                    <div class="row">

                        <!-- Left Decorative Panel -->
                        <div class="col-lg-5 d-none d-lg-block">
                            <div class="login-left-panel h-100">

                                <!-- Gear / Factory Icon SVG Placeholder -->
                                <div class="icon-placeholder">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <!-- Gear shape -->
                                        <circle cx="12" cy="12" r="3"/>
                                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                                    </svg>
                                </div>

                                <div class="brand-title">PT. YADIN</div>
                                <div class="brand-subtitle">OEE Monitoring System v2.0</div>

                                <ul class="feature-list">
                                    <li>
                                        <i class="fas fa-chart-line"></i>
                                        Real-time Monitoring
                                    </li>
                                    <li>
                                        <i class="fas fa-microchip"></i>
                                        ESP32 Integration
                                    </li>
                                    <li>
                                        <i class="fas fa-wave-square"></i>
                                        Vibration Analysis
                                    </li>
                                </ul>

                            </div>
                        </div>

                        <!-- Right Form Panel -->
                        <div class="col-lg-7">
                            <div class="p-5">

                                <div class="text-center mb-4">
                                    <h1 class="h4 text-gray-900 mb-1">Selamat Datang</h1>
                                    <p class="text-muted small">Masuk ke akun Anda untuk melanjutkan</p>
                                </div>

                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-circle mr-2"></i>
                                        <?= htmlspecialchars($error) ?>
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Tutup">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="login.php" autocomplete="off">

                                    <!-- Username -->
                                    <div class="form-group">
                                        <label for="username" class="small font-weight-bold text-gray-700">Username</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-user fa-sm text-gray-400"></i></span>
                                            </div>
                                            <input
                                                type="text"
                                                id="username"
                                                name="username"
                                                class="form-control form-control-user"
                                                placeholder="Masukkan username"
                                                value="<?= htmlspecialchars($remembered_username) ?>"
                                                required
                                                autofocus
                                            >
                                        </div>
                                    </div>

                                    <!-- Password -->
                                    <div class="form-group">
                                        <label for="password" class="small font-weight-bold text-gray-700">Password</label>
                                        <div class="input-group password-wrapper">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-lock fa-sm text-gray-400"></i></span>
                                            </div>
                                            <input
                                                type="password"
                                                id="password"
                                                name="password"
                                                class="form-control form-control-user"
                                                placeholder="Masukkan password"
                                                required
                                            >
                                            <button type="button" class="toggle-password" onclick="togglePassword()" tabindex="-1" title="Tampilkan/Sembunyikan password">
                                                <i class="fas fa-eye" id="toggleIcon"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Remember Me -->
                                    <div class="form-group">
                                        <div class="custom-control custom-checkbox small">
                                            <input
                                                type="checkbox"
                                                class="custom-control-input"
                                                id="remember"
                                                name="remember"
                                                <?= !empty($remembered_username) ? 'checked' : '' ?>
                                            >
                                            <label class="custom-control-label" for="remember">Ingat saya selama 30 hari</label>
                                        </div>
                                    </div>

                                    <!-- Submit -->
                                    <button type="submit" class="btn btn-primary btn-user btn-block">
                                        <i class="fas fa-sign-in-alt mr-2"></i> Masuk
                                    </button>

                                </form>

                                <hr>

                                <div class="text-center">
                                    <a class="small text-muted" href="forgot_password.php">
                                        <i class="fas fa-key mr-1"></i> Lupa password?
                                    </a>
                                </div>

                                <div class="text-center mt-3">
                                    <small class="text-muted">&copy; <?= date('Y') ?> PT. YADIN &mdash; OEE Monitoring System</small>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap core JS -->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="js/sb-admin-2.min.js"></script>

<script>
    function togglePassword() {
        var pwd = document.getElementById('password');
        var icon = document.getElementById('toggleIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            pwd.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>

</body>
</html>
