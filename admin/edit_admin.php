<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
require_once '../config/log_aktivitas.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

$error = '';
$success = '';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header("Location: kelola_admin.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil data admin
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, nama_lengkap, username, email, no_hp, status
     FROM users
     WHERE id = ?
     AND role = 'admin'
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$admin) {
    header("Location: kelola_admin.php?status=not_found");
    exit;
}

/*
|--------------------------------------------------------------------------
| PROSES UPDATE
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = "Token keamanan tidak valid.";
    } else {

        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $no_hp = trim($_POST['no_hp'] ?? '');
        $password = $_POST['password'] ?? '';
        $status = $_POST['status'] ?? '';

        if ($nama_lengkap === '' || strlen($nama_lengkap) < 3) {
            $error = "Nama lengkap minimal 3 karakter.";

        } elseif ($username === '' || strlen($username) < 4) {
            $error = "Username minimal 4 karakter.";

        } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
            $error = "Username hanya boleh menggunakan huruf, angka, titik, underscore, dan strip.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format email tidak valid.";

        } elseif (!in_array($status, ['aktif', 'nonaktif'], true)) {
            $error = "Status tidak valid.";

        } elseif ($password !== '' && strlen($password) < 8) {
            $error = "Password baru minimal 8 karakter.";

        } elseif (
            (int)$id === (int)$_SESSION['user_id'] &&
            $status === 'nonaktif'
        ) {
            $error = "Anda tidak dapat menonaktifkan akun yang sedang digunakan.";
        }

        /*
        |--------------------------------------------------------------------------
        | Cek username/email duplikat
        |--------------------------------------------------------------------------
        */
        if ($error === '') {

            $stmt = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM users
                 WHERE (username = ? OR email = ?)
                 AND id != ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssi",
                $username,
                $email,
                $id
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $duplikat = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if ($duplikat) {
                $error = "Username atau email sudah digunakan oleh admin lain.";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Jangan nonaktifkan admin terakhir
        |--------------------------------------------------------------------------
        */
        if (
            $error === '' &&
            $status === 'nonaktif' &&
            $admin['status'] === 'aktif'
        ) {

            $stmt = mysqli_prepare(
                $conn,
                "SELECT COUNT(*) AS total
                 FROM users
                 WHERE role = 'admin'
                 AND status = 'aktif'
                 AND id != ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if ((int)($data['total'] ?? 0) < 1) {
                $error = "Admin terakhir tidak dapat dinonaktifkan.";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */
        if ($error === '') {

            if ($password !== '') {

                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE users
                     SET nama_lengkap = ?,
                         username = ?,
                         email = ?,
                         no_hp = ?,
                         password = ?,
                         status = ?
                     WHERE id = ?
                     AND role = 'admin'"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssssi",
                    $nama_lengkap,
                    $username,
                    $email,
                    $no_hp,
                    $password_hash,
                    $status,
                    $id
                );

            } else {

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE users
                     SET nama_lengkap = ?,
                         username = ?,
                         email = ?,
                         no_hp = ?,
                         status = ?
                     WHERE id = ?
                     AND role = 'admin'"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssssi",
                    $nama_lengkap,
                    $username,
                    $email,
                    $no_hp,
                    $status,
                    $id
                );
            }

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                catatAktivitas(
                    $conn,
                    'Edit Admin',
                    'Mengubah data admin ID #' . $id
                );

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header("Location: kelola_admin.php?status=edit");
                exit;

            } else {

                $error = "Data admin gagal diperbarui.";
                mysqli_stmt_close($stmt);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Pertahankan input jika gagal
        |--------------------------------------------------------------------------
        */
        $admin['nama_lengkap'] = $nama_lengkap;
        $admin['username'] = $username;
        $admin['email'] = $email;
        $admin['no_hp'] = $no_hp;
        $admin['status'] = $status;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Admin - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            width: 100%;
            overflow-x: hidden;
        }

        body {
            background: #f4f9f8;
        }

        /* SIDEBAR */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            z-index: 1001;
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* LAYOUT UTAMA */
        .admin-main {
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;
            background: #f4f9f8;
        }

        .admin-content {
            width: 100%;
            max-width: none;
            min-height: 100vh;
            margin: 0;
            padding: 42px 50px;
            box-sizing: border-box;
        }

        /* HEADING */
        .admin-heading {
            width: 100%;
            margin: 0 0 25px;
        }

        .admin-heading h1 {
            margin: 0 0 8px;
            color: #0B4650;
            font-size: 30px;
            font-weight: 700;
            line-height: 1.3;
        }

        .admin-heading p {
            margin: 0;
            color: #718995;
            font-size: 14px;
            line-height: 1.5;
        }

        .heading-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .heading-text {
            min-width: 0;
        }

        /* TOMBOL HAMBURGER */
        .mobile-menu-button {
            display: none !important;
        }

        /* FORM CARD */
        .admin-form-card {
            width: 100%;
            max-width: 100%;
            padding: 30px;
            background: #fff;
            border: 1px solid #E1EAED;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(11, 45, 70, .05);
        }

        .admin-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
            width: 100%;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .form-group label {
            color: #315A69;
            font-size: 13px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            display: block;
            width: 100%;
            min-width: 0;
            min-height: 45px;
            padding: 11px 13px;
            border: 1px solid #DCE8EC;
            border-radius: 9px;
            outline: none;
            background: #FBFDFE;
            color: #334E5A;
            font-family: inherit;
            font-size: 13px;
            transition: .2s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #17B8C8;
            box-shadow: 0 0 0 3px rgba(23, 184, 200, .12);
            background: #fff;
        }

        .form-full {
            grid-column: 1 / -1;
        }

        .form-help {
            color: #81939B;
            font-size: 11px;
            line-height: 1.5;
        }

        /* PESAN ERROR */
        .form-alert {
            width: 100%;
            padding: 13px 16px;
            margin-bottom: 20px;
            border-radius: 9px;
            font-size: 13px;
            box-sizing: border-box;
        }

        .form-alert-error {
            background: #FFF0F0;
            color: #B42318;
            border: 1px solid #F3D0D2;
        }

        /* TOMBOL AKSI */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #EDF2F4;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 11px 20px;
            border: 1px solid transparent;
            border-radius: 9px;
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            transition: .2s ease;
        }

        .btn-back {
            background: #F5F8FA;
            color: #526671;
            border-color: #DCE8EC;
        }

        .btn-back:hover {
            background: #EAF1F4;
        }

        .btn-save {
            background: linear-gradient(110deg, #0B354D, #17B8C8);
            color: #fff;
            box-shadow: 0 5px 13px rgba(11, 53, 77, .15);
        }

        .btn-save:hover {
            background: linear-gradient(110deg, #082B40, #109EAF);
            transform: translateY(-1px);
        }

        /* OVERLAY */
        .sidebar-overlay {
            display: none;
        }

        /* TABLET DAN HP */
        @media (max-width: 800px) {

            html,
            body {
                width: 100%;
                overflow-x: hidden;
            }

            .admin-dashboard {
                width: 100%;
                min-height: 100vh;
            }

            .admin-sidebar {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 250px !important;
                max-width: 85vw !important;
                height: 100vh !important;
                z-index: 1001 !important;
                transform: translateX(-100%) !important;
                transition: transform .3s ease !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch;
            }

            .admin-sidebar.open {
                transform: translateX(0) !important;
            }

            .sidebar-overlay.show {
                display: block !important;
                position: fixed !important;
                inset: 0 !important;
                width: 100% !important;
                height: 100% !important;
                background: rgba(4, 25, 39, .55) !important;
                z-index: 1000 !important;
            }

            .admin-main {
                width: 100% !important;
                min-height: 100vh;
                margin-left: 0 !important;
            }

            .admin-content {
                width: 100% !important;
                min-height: 100vh;
                margin: 0 !important;
                padding: 20px 20px 30px !important;
            }

            .admin-heading {
                position: sticky;
                top: 0;
                z-index: 998;
                padding: 10px 0 15px;
                margin-bottom: 18px;
                background: #f4f9f8;
            }

            .admin-heading h1 {
                font-size: 26px;
            }

            .admin-heading p {
                font-size: 13px;
            }

            .mobile-menu-button {
                display: inline-flex !important;
                flex: 0 0 42px !important;
                width: 42px !important;
                height: 42px !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 0 !important;
                border: none !important;
                border-radius: 10px !important;
                background: linear-gradient(110deg, #0B354D, #17B8C8) !important;
                color: #fff !important;
                font-size: 23px !important;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(11, 53, 77, .2);
            }

            .admin-form-card {
                padding: 25px 22px;
            }

            .admin-form-grid {
                gap: 18px;
            }
        }

        /* HP */
        @media (max-width: 600px) {

            .admin-content {
                padding: 15px 14px 25px !important;
            }

            .admin-heading {
                padding: 8px 0 13px;
                margin-bottom: 15px;
            }

            .admin-heading h1 {
                font-size: 23px;
            }

            .admin-heading p {
                font-size: 12px;
            }

            .mobile-menu-button {
                flex-basis: 40px !important;
                width: 40px !important;
                height: 40px !important;
                font-size: 21px !important;
            }

            .admin-form-card {
                padding: 20px 16px;
                border-radius: 12px;
            }

            .admin-form-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 17px;
            }

            .form-full {
                grid-column: auto;
            }

            .form-group input,
            .form-group select {
                min-height: 44px;
                font-size: 13px;
            }

            .form-actions {
                flex-direction: column-reverse;
                align-items: stretch;
                gap: 10px;
                margin-top: 22px;
                padding-top: 18px;
            }

            .btn {
                width: 100%;
                min-height: 45px;
                box-sizing: border-box;
            }
        }

        /* DESKTOP: SIDEBAR TETAP DAN TANPA HAMBURGER */
        @media (min-width: 801px) {
            .admin-sidebar {
                transform: translateX(0) !important;
            }

            .mobile-menu-button {
                display: none !important;
            }

            .sidebar-overlay {
                display: none !important;
            }
        }
    </style>
</head>

<body>

<div class="admin-dashboard">

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="admin-sidebar" id="adminSidebar">

        <div class="sidebar-brand">

            <div class="sidebar-brand-top">

                <div class="sidebar-logo">
                    <img src="../assets/images/logo-tps3r.png" alt="Logo TPS 3R">
                </div>

                <div>
                    <h2>Bank Sampah TPS 3R Kopling Berjaya</h2>
                    <p>TPS 3R KELURAHAN PULAU KAROMAH</p>
                </div>

            </div>

        </div>

        <nav class="sidebar-nav">

            <div class="sidebar-section-title">
                Utama
            </div>

            <a href="dashboard.php" class="sidebar-link">
                <span class="sidebar-icon">🏠</span>
                <span>Dashboard</span>
            </a>

            <div class="sidebar-section-title">
                Pengelolaan
            </div>

            <a href="warga.php" class="sidebar-link">
                <span class="sidebar-icon">👥</span>
                <span>Data Warga</span>
            </a>

            <a href="petugas.php" class="sidebar-link">
                <span class="sidebar-icon">👤</span>
                <span>Data Petugas</span>
            </a>

            <a href="kelola_admin.php" class="sidebar-link active">
                <span class="sidebar-icon">🛡️</span>
                <span>Kelola Admin</span>
            </a>

            <a href="kategori.php" class="sidebar-link">
                <span class="sidebar-icon">♻️</span>
                <span>Kategori Sampah</span>
            </a>

            <a href="setoran.php" class="sidebar-link">
                <span class="sidebar-icon">📦</span>
                <span>Data Setoran</span>
            </a>

            <a href="tabungan.php" class="sidebar-link">
                <span class="sidebar-icon">💰</span>
                <span>Tabungan Warga</span>
            </a>

            <a href="mutasi_tabungan.php" class="sidebar-link">
                <span class="sidebar-icon">↕️</span>
                <span>Mutasi Tabungan</span>
            </a>

            <a href="penarikan.php" class="sidebar-link">
                <span class="sidebar-icon">💸</span>
                <span>Penarikan Tabungan</span>
            </a>

            <a href="laporan.php" class="sidebar-link">
                <span class="sidebar-icon">📊</span>
                <span>Laporan</span>
            </a>

            <a href="backup_database.php" class="sidebar-link">
                <span class="sidebar-icon">💾</span>
                <span>Backup Database</span>
            </a>

            <a href="pengumuman.php" class="sidebar-link">
                <span class="sidebar-icon">📢</span>
                <span>Kelola Pengumuman</span>
            </a>

            <a href="log_aktivitas.php" class="sidebar-link">
                <span class="sidebar-icon">📋</span>
                <span>Log Aktivitas</span>
            </a>

            <div class="sidebar-section-title">
                Akun
            </div>

            <a href="profil.php" class="sidebar-link">
                <span class="sidebar-icon">⚙️</span>
                <span>Profil</span>
            </a>

            <a href="ubah_password.php" class="sidebar-link">
                <span class="sidebar-icon">🔑</span>
                <span>Ubah Password</span>
            </a>

        </nav>
    </aside>

    <main class="admin-main">

        <div class="admin-content">

            <div class="admin-heading">

                <div class="heading-row">

                    <div class="heading-text">
                        <h1>Edit Admin</h1>
                        <p>Perbarui informasi akun administrator.</p>
                    </div>

                    <button
                        type="button"
                        class="mobile-menu-button"
                        id="mobileMenuButton"
                        aria-label="Buka menu"
                    >
                        ☰
                    </button>

                </div>

            </div>

            <?php if ($error !== ''): ?>

                <div class="form-alert form-alert-error">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>

            <?php endif; ?>

            <div class="admin-form-card">

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>"
                    >

                    <div class="admin-form-grid">

                        <div class="form-group">

                            <label for="nama_lengkap">
                                Nama Lengkap
                            </label>

                            <input
                                type="text"
                                id="nama_lengkap"
                                name="nama_lengkap"
                                value="<?= htmlspecialchars($admin['nama_lengkap'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                minlength="3"
                                maxlength="100"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label for="username">
                                Username
                            </label>

                            <input
                                type="text"
                                id="username"
                                name="username"
                                value="<?= htmlspecialchars($admin['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                minlength="4"
                                maxlength="50"
                                pattern="[A-Za-z0-9._-]+"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label for="email">
                                Email
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars($admin['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                maxlength="100"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label for="no_hp">
                                No. HP
                            </label>

                            <input
                                type="text"
                                id="no_hp"
                                name="no_hp"
                                value="<?= htmlspecialchars($admin['no_hp'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                maxlength="20"
                            >

                        </div>

                        <div class="form-group">

                            <label for="password">
                                Password Baru
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                minlength="8"
                                maxlength="100"
                                placeholder="Kosongkan jika tidak diganti"
                            >

                            <span class="form-help">
                                Minimal 8 karakter. Kosongkan jika password tetap.
                            </span>

                        </div>

                        <div class="form-group">

                            <label for="status">
                                Status
                            </label>

                            <select id="status" name="status" required>

                                <option
                                    value="aktif"
                                    <?= ($admin['status'] ?? '') === 'aktif' ? 'selected' : ''; ?>
                                >
                                    Aktif
                                </option>

                                <option
                                    value="nonaktif"
                                    <?= ($admin['status'] ?? '') === 'nonaktif' ? 'selected' : ''; ?>
                                >
                                    Nonaktif
                                </option>

                            </select>

                        </div>

                    </div>

                    <div class="form-actions">

                        <a href="kelola_admin.php" class="btn btn-back">
                            ← Kembali
                        </a>

                        <button type="submit" class="btn btn-save">
                            Simpan Perubahan
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const menuButton = document.getElementById('mobileMenuButton');
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (!menuButton || !sidebar || !overlay) {
        return;
    }

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    }

    menuButton.addEventListener('click', function () {
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    overlay.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 800) {
            closeSidebar();
        }
    });

});
</script>

</body>
</html>