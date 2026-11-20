
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
require_once '../config/log_aktivitas.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int) $_SESSION['user_id'];

$pesan = '';
$tipe_pesan = '';

// ===================================
// PROSES UPDATE PROFIL
// ===================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $pesan = "Token keamanan tidak valid.";
        $tipe_pesan = 'error';

    } else {

        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $no_hp = trim($_POST['no_hp'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');

        if ($nama_lengkap === '' || mb_strlen($nama_lengkap) > 100) {
            $pesan = "Nama lengkap wajib diisi dan maksimal 100 karakter.";
            $tipe_pesan = 'error';

        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pesan = "Format email tidak valid.";
            $tipe_pesan = 'error';

        } elseif (mb_strlen($no_hp) > 20) {
            $pesan = "Nomor HP maksimal 20 karakter.";
            $tipe_pesan = 'error';

        } elseif (mb_strlen($alamat) > 500) {
            $pesan = "Alamat maksimal 500 karakter.";
            $tipe_pesan = 'error';

        } else {

            // Cek email digunakan user lain
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1"
            );

            if (!$stmt) {
                $pesan = "Terjadi kesalahan pada sistem.";
                $tipe_pesan = 'error';

            } else {

                mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
                mysqli_stmt_execute($stmt);

                $result_cek = mysqli_stmt_get_result($stmt);
                $email_dipakai = mysqli_num_rows($result_cek) > 0;

                mysqli_stmt_close($stmt);

                if ($email_dipakai) {

                    $pesan = "Email tersebut sudah digunakan oleh akun lain.";
                    $tipe_pesan = 'error';

                } else {

                    // Update profil
                    $stmt_update = mysqli_prepare(
                        $conn,
                        "UPDATE users
                         SET nama_lengkap = ?, email = ?, no_hp = ?, alamat = ?
                         WHERE id = ? AND role = 'warga'"
                    );

                    if (!$stmt_update) {

                        $pesan = "Gagal menyiapkan proses update.";
                        $tipe_pesan = 'error';

                    } else {

                        mysqli_stmt_bind_param(
                            $stmt_update,
                            "ssssi",
                            $nama_lengkap,
                            $email,
                            $no_hp,
                            $alamat,
                            $user_id
                        );

                        if (mysqli_stmt_execute($stmt_update)) {

                            $_SESSION['nama'] = $nama_lengkap;

                            // Catat aktivitas
                            if (!catatAktivitas(
                                $conn,
                                'Profil Diperbarui',
                                'Data profil akun berhasil diperbarui.'
                            )) {
                                error_log(
                                    "Gagal mencatat aktivitas perubahan profil untuk user #" . $user_id
                                );
                            }

                            // Regenerasi CSRF
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                            $pesan = "Profil berhasil diperbarui.";
                            $tipe_pesan = 'success';

                        } else {

                            $pesan = "Gagal memperbarui profil.";
                            $tipe_pesan = 'error';
                        }

                        mysqli_stmt_close($stmt_update);
                    }
                }
            }
        }
    }
}

// ===================================
// AMBIL DATA PROFIL
// ===================================

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, nama_lengkap, username, email, no_hp, alamat, status
     FROM users
     WHERE id = ? AND role = 'warga'
     LIMIT 1"
);

if (!$stmt) {
    die("Gagal mengambil data profil.");
}

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

// Data tampilan
$nama_lengkap = $user['nama_lengkap'] ?? '';
$username = $user['username'] ?? '';
$email = $user['email'] ?? '';
$no_hp = $user['no_hp'] ?? '';
$alamat = $user['alamat'] ?? '';
$status = $user['status'] ?? 'aktif';

$inisial = strtoupper(mb_substr($nama_lengkap, 0, 1));
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profil Saya - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* =====================================
           GLOBAL
        ===================================== */

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #F2F7FA;
            font-family: 'Poppins', sans-serif;
            color: #183B55;
        }

        /* =====================================
           SIDEBAR WARGA
        ===================================== */

        .warga-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            z-index: 1001;
            color: #fff;
            background: linear-gradient(180deg, #071F33, #0B2D46);
            border-right: 1px solid rgba(255,255,255,.08);
        }

        .sidebar-brand {
            flex-shrink: 0;
            padding: 24px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }

        .sidebar-brand-top {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-logo {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            padding: 3px;
            border-radius: 50%;
            background: #fff;
            overflow: hidden;
        }

        .sidebar-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .sidebar-brand-top > div:last-child {
            flex: 1;
            min-width: 0;
        }

        .sidebar-brand h2 {
            margin: 0;
            color: #fff;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 700;
        }

        .sidebar-brand p {
            margin: 4px 0 0;
            color: #AFC4D2;
            font-size: 9px;
            line-height: 1.5;
            letter-spacing: .4px;
        }

        .sidebar-nav {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
            padding: 14px 12px 20px;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .sidebar-nav::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        .sidebar-section-title {
            margin: 13px 0 6px;
            padding: 0 10px;
            color: #8FA9BA;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .sidebar-link {
            min-height: 43px;
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 12px;
            margin: 2px 0;
            border-radius: 9px;
            color: #D8E5ED;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: .2s ease;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: #fff;
            background: rgba(24,195,216,.17);
        }

        .sidebar-link.active {
            box-shadow: inset 3px 0 0 #18C3D8;
        }

        .sidebar-icon {
            width: 22px;
            min-width: 22px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-logout:hover {
            background: rgba(255,90,90,.14);
        }

        /* =====================================
           KONTEN UTAMA
        ===================================== */

        .warga-main {
            width: calc(100% - 250px);
            min-height: 100vh;
            margin-left: 250px;
            padding: 30px 35px;
        }

        /* =====================================
           HEADER
        ===================================== */

        .page-header-row {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .warga-heading {
            flex: 1;
            min-width: 0;
        }

        .warga-heading h1 {
            margin: 0;
            color: #102F43;
            font-size: 25px;
            font-weight: 750;
            letter-spacing: -.4px;
        }

        .warga-heading p {
            margin: 7px 0 0;
            color: #718493;
            font-size: 12px;
            line-height: 1.7;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .mobile-menu-button {
            display: none;
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(135deg, #071F33, #147F91);
            font-size: 22px;
            cursor: pointer;
        }

        .btn-kembali {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 17px;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(135deg, #071F33, #147F91);
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            box-shadow: 0 5px 13px rgba(7,31,51,.15);
            transition: .2s ease;
        }

        .btn-kembali:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 16px rgba(7,31,51,.22);
        }

        .sidebar-overlay {
            display: none;
        }

        /* =====================================
           ALERT
        ===================================== */

        .profil-alert {
            padding: 13px 16px;
            margin-bottom: 22px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 500;
        }

        .profil-alert.success {
            border: 1px solid #BDE8CE;
            color: #18794E;
            background: #E8F7EF;
        }

        .profil-alert.error {
            border: 1px solid #F1C3C3;
            color: #B93838;
            background: #FDECEC;
        }

        /* =====================================
           PROFIL GRID
        ===================================== */

        .profil-grid {
            display: grid;
            grid-template-columns: 300px minmax(0, 1fr);
            gap: 20px;
            align-items: start;
        }

        .profil-card {
            min-width: 0;
            overflow: hidden;
            border: 1px solid #E5EDF2;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 3px 12px rgba(7,31,51,.035);
        }

        /* =====================================
           RINGKASAN PROFIL
        ===================================== */

        .profil-summary {
            padding: 25px 22px;
            text-align: center;
        }

        .profil-avatar {
            width: 76px;
            height: 76px;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid #E6F5F7;
            border-radius: 50%;
            color: #fff;
            background: linear-gradient(135deg, #071F33, #147F91);
            font-size: 28px;
            font-weight: 700;
        }

        .profil-summary h2 {
            margin: 0 0 5px;
            color: #102F43;
            font-size: 17px;
            font-weight: 700;
            word-break: break-word;
        }

        .profil-summary .username {
            margin: 0 0 14px;
            color: #8293A0;
            font-size: 11px;
        }

        .status-akun {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 12px;
            border-radius: 20px;
            color: #18794E;
            background: #E6F6ED;
            font-size: 10px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #24A36F;
        }

        .profil-info {
            margin-top: 21px;
            padding-top: 19px;
            border-top: 1px solid #E8EEF2;
            text-align: left;
        }

        .profil-info-item {
            margin-bottom: 15px;
        }

        .profil-info-item:last-child {
            margin-bottom: 0;
        }

        .profil-info-label {
            display: block;
            margin-bottom: 5px;
            color: #8293A0;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .profil-info-value {
            color: #435667;
            font-size: 11px;
            line-height: 1.7;
            word-break: break-word;
        }

        /* =====================================
           TOMBOL AKUN
        ===================================== */

        .profil-shortcuts {
            display: flex;
            flex-direction: column;
            gap: 9px;
            margin-top: 20px;
        }

        .btn-ubah-password,
        .btn-aktivitas {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px 13px;
            border-radius: 9px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            transition: .2s ease;
        }

        .btn-ubah-password {
            color: #147F91;
            background: #EAF5F8;
            border: 1px solid #C9E3E9;
        }

        .btn-ubah-password:hover {
            background: #DDF0F4;
        }

        .btn-aktivitas {
            color: #35566B;
            background: #F2F7FA;
            border: 1px solid #DCE6EC;
        }

        .btn-aktivitas:hover {
            background: #E8EFF4;
        }

        /* =====================================
           FORM PROFIL
        ===================================== */

        .profil-form-card {
            padding: 25px;
        }

        .form-heading {
            margin-bottom: 22px;
        }

        .form-heading h2 {
            margin: 0;
            color: #102F43;
            font-size: 17px;
            font-weight: 700;
        }

        .form-heading p {
            margin: 7px 0 0;
            color: #718493;
            font-size: 11px;
            line-height: 1.7;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            margin-bottom: 8px;
            color: #526777;
            font-size: 11px;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid #DCE6EC;
            border-radius: 9px;
            outline: none;
            color: #183B55;
            background: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 11px;
            transition: .2s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #159BAE;
            box-shadow: 0 0 0 3px rgba(24,195,216,.10);
        }

        .form-group input[readonly] {
            background: #F2F7FA;
            color: #8293A0;
            cursor: not-allowed;
        }

        .form-group textarea {
            min-height: 105px;
            resize: vertical;
        }

        .form-help {
            margin-top: 6px;
            color: #8293A0;
            font-size: 10px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #E8EEF2;
        }

        .btn-simpan {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 20px;
            border: none;
            border-radius: 9px;
            color: #fff;
            background: linear-gradient(135deg, #071F33, #147F91);
            font-family: 'Poppins', sans-serif;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 5px 13px rgba(7,31,51,.13);
            transition: .2s ease;
        }

        .btn-simpan:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 16px rgba(7,31,51,.20);
        }

        /* =====================================
           RESPONSIVE TABLET
        ===================================== */

        @media (max-width: 1000px) {

            .warga-main {
                padding: 25px 22px;
            }

            .profil-grid {
                grid-template-columns: 260px minmax(0, 1fr);
                gap: 15px;
            }

            .profil-form-card {
                padding: 20px;
            }

        }

        /* =====================================
           RESPONSIVE MOBILE
        ===================================== */

        @media (max-width: 768px) {

            .warga-sidebar {
                width: 260px;
                min-width: 260px;
                transform: translateX(-100%);
                transition: transform .3s ease;
            }

            .warga-sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: block;
                position: fixed;
                inset: 0;
                z-index: 1000;
                background: rgba(0,0,0,.45);
                opacity: 0;
                visibility: hidden;
                transition: .3s ease;
            }

            .sidebar-overlay.show {
                opacity: 1;
                visibility: visible;
            }

            .warga-main {
                width: 100%;
                margin-left: 0;
                padding: 22px 18px 30px;
            }

            .page-header-row {
                display: flex;
                flex-direction: row;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
            }

            .warga-heading {
                flex: 1;
                min-width: 0;
            }

            .warga-heading h1 {
                font-size: 22px;
            }

            .warga-heading p {
                font-size: 11px;
            }

            .header-actions {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                justify-content: flex-start;
                flex-shrink: 0;
                gap: 8px;
            }

            .mobile-menu-button {
                display: flex;
            }

            .btn-kembali {
                padding: 9px 12px;
                font-size: 10px;
            }

            .profil-grid {
                grid-template-columns: 1fr;
            }

            .profil-summary {
                padding: 25px 22px;
            }

        }

        /* =====================================
           RESPONSIVE HP KECIL
        ===================================== */

        @media (max-width: 480px) {

            .warga-main {
                padding: 19px 13px 25px;
            }

            .page-header-row {
                gap: 8px;
            }

            .warga-heading h1 {
                font-size: 18px;
            }

            .warga-heading p {
                font-size: 10px;
                line-height: 1.6;
            }

            .header-actions {
                gap: 6px;
            }

            .mobile-menu-button {
                width: 39px;
                height: 39px;
                font-size: 20px;
            }

            .btn-kembali {
                padding: 9px 10px;
                font-size: 9px;
            }

            .profil-form-card {
                padding: 18px 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .form-group.full {
                grid-column: auto;
            }

            .form-actions {
                justify-content: stretch;
            }

            .btn-simpan {
                width: 100%;
            }

        }

    </style>
</head>

<body>

<!-- =====================================
     SIDEBAR WARGA
===================================== -->

<aside class="warga-sidebar" id="wargaSidebar">

    <div class="sidebar-brand">

        <div class="sidebar-brand-top">

            <div class="sidebar-logo">
                <img
                    src="../assets/images/logo-tps3r.png"
                    alt="Logo TPS 3R"
                >
            </div>

            <div>
                <h2>Bank Sampah TPS 3R Kopling Berjaya</h2>
                <p>TPS 3R KELURAHAN PULAU KAROMAH</p>
            </div>

        </div>

    </div>

    <nav class="sidebar-nav">

        <div class="sidebar-section-title">Utama</div>

        <a href="dashboard.php" class="sidebar-link">
            <span class="sidebar-icon">🏠</span>
            <span>Dashboard</span>
        </a>

        <div class="sidebar-section-title">Layanan Warga</div>

        <a href="qr_saya.php" class="sidebar-link">
            <span class="sidebar-icon">▣</span>
            <span>QR Code Saya</span>
        </a>

        <a href="ajukan_setoran.php" class="sidebar-link">
            <span class="sidebar-icon">♻️</span>
            <span>Ajukan Setoran</span>
        </a>

        <a href="riwayat_setoran.php" class="sidebar-link">
            <span class="sidebar-icon">📦</span>
            <span>Riwayat Setoran</span>
        </a>

        <a href="riwayat_tabungan.php" class="sidebar-link">
            <span class="sidebar-icon">💰</span>
            <span>Riwayat Tabungan</span>
        </a>

        <a href="ajukan_penarikan.php" class="sidebar-link">
            <span class="sidebar-icon">💸</span>
            <span>Penarikan Tabungan</span>
        </a>

        <a href="riwayat_penarikan.php" class="sidebar-link">
            <span class="sidebar-icon">📋</span>
            <span>Riwayat Penarikan</span>
        </a>

        <a href="notifikasi.php" class="sidebar-link">
            <span class="sidebar-icon">🔔</span>
            <span>Notifikasi</span>
        </a>

        <div class="sidebar-section-title">Akun</div>

        <a href="profil.php" class="sidebar-link active">
            <span class="sidebar-icon">⚙️</span>
            <span>Profil Saya</span>
        </a>
         <a href="aktivitas.php" class="sidebar-link">
            <span class="sidebar-icon">🕘</span>
            <span>Aktivitas Saya</span>
        </a>
        <a href="../auth/logout.php" class="sidebar-link sidebar-logout">
            <span class="sidebar-icon">🚪</span>
            <span>Logout</span>
        </a>

    </nav>

</aside>

<!-- OVERLAY -->

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- =====================================
     KONTEN UTAMA
===================================== -->

<main class="warga-main">

    <!-- HEADER -->

    <div class="page-header-row">

        <div class="warga-heading">

            <h1>Profil Saya</h1>

            <p>
                Kelola informasi pribadi akun Bank Sampah Digital.
            </p>

        </div>

        <div class="header-actions">

            <button
                type="button"
                class="mobile-menu-button"
                id="mobileMenuButton"
                aria-label="Buka menu"
                aria-expanded="false"
            >
                ☰
            </button>

            <a href="dashboard.php" class="btn-kembali">
                <span>←</span>
                Kembali ke Dashboard
            </a>

        </div>

    </div>

    <!-- ALERT -->

    <?php if ($pesan !== ''): ?>

        <div class="profil-alert <?= $tipe_pesan === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <!-- =====================================
         GRID PROFIL
    ===================================== -->

    <div class="profil-grid">

        <!-- RINGKASAN PROFIL -->

        <div class="profil-card">

            <div class="profil-summary">

                <div class="profil-avatar">
                    <?= htmlspecialchars($inisial, ENT_QUOTES, 'UTF-8') ?>
                </div>

                <h2>
                    <?= htmlspecialchars($nama_lengkap, ENT_QUOTES, 'UTF-8') ?>
                </h2>

                <p class="username">
                    @<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                </p>

                <span class="status-akun">
                    <span class="status-dot"></span>
                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                </span>

                <div class="profil-info">

                    <div class="profil-info-item">
                        <span class="profil-info-label">Email</span>

                        <div class="profil-info-value">
                            <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>

                    <div class="profil-info-item">
                        <span class="profil-info-label">Nomor HP</span>

                        <div class="profil-info-value">
                            <?= htmlspecialchars($no_hp ?: '-', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>

                    <div class="profil-info-item">
                        <span class="profil-info-label">Alamat</span>

                        <div class="profil-info-value">
                            <?= nl2br(htmlspecialchars($alamat ?: '-', ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                    </div>

                </div>

                <div class="profil-shortcuts">

                    <a href="ubah_password.php" class="btn-ubah-password">
                        🔒 Ubah Password
                    </a>

                    <a href="aktivitas.php" class="btn-aktivitas">
                        🕘 Aktivitas Saya
                    </a>

                </div>

            </div>

        </div>

        <!-- FORM EDIT PROFIL -->

        <div class="profil-card profil-form-card">

            <div class="form-heading">

                <h2>Informasi Pribadi</h2>

                <p>
                    Perbarui informasi yang digunakan pada akun Anda.
                </p>

            </div>

            <form method="POST" action="profil.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="form-grid">

                    <!-- NAMA -->

                    <div class="form-group">

                        <label for="nama_lengkap">
                            Nama Lengkap
                        </label>

                        <input
                            type="text"
                            id="nama_lengkap"
                            name="nama_lengkap"
                            value="<?= htmlspecialchars($nama_lengkap, ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="100"
                            required
                        >

                    </div>

                    <!-- USERNAME -->

                    <div class="form-group">

                        <label for="username">
                            Username
                        </label>

                        <input
                            type="text"
                            id="username"
                            value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                            readonly
                        >

                        <span class="form-help">
                            Username tidak dapat diubah.
                        </span>

                    </div>

                    <!-- EMAIL -->

                    <div class="form-group">

                        <label for="email">
                            Email
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="150"
                            required
                        >

                    </div>

                    <!-- NOMOR HP -->

                    <div class="form-group">

                        <label for="no_hp">
                            Nomor HP
                        </label>

                        <input
                            type="text"
                            id="no_hp"
                            name="no_hp"
                            value="<?= htmlspecialchars($no_hp, ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="20"
                            placeholder="Contoh: 081234567890"
                        >

                    </div>

                    <!-- ALAMAT -->

                    <div class="form-group full">

                        <label for="alamat">
                            Alamat
                        </label>

                        <textarea
                            id="alamat"
                            name="alamat"
                            maxlength="500"
                            placeholder="Masukkan alamat lengkap Anda..."
                        ><?= htmlspecialchars($alamat, ENT_QUOTES, 'UTF-8') ?></textarea>

                        <span class="form-help">
                            Maksimal 500 karakter.
                        </span>

                    </div>

                </div>

                <div class="form-actions">

                    <button type="submit" class="btn-simpan">
                        💾 Simpan Perubahan
                    </button>

                </div>

            </form>

        </div>

    </div>

</main>

<!-- =====================================
     JAVASCRIPT HAMBURGER
===================================== -->

<script>
document.addEventListener('DOMContentLoaded', function () {

    const menuButton = document.getElementById('mobileMenuButton');
    const sidebar = document.getElementById('wargaSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (menuButton && sidebar && overlay) {

        function tutupMenu() {

            sidebar.classList.remove('open');
            overlay.classList.remove('show');

            menuButton.innerHTML = '☰';

            menuButton.setAttribute('aria-expanded', 'false');

        }

        menuButton.addEventListener('click', function () {

            const terbuka = sidebar.classList.toggle('open');

            overlay.classList.toggle('show', terbuka);

            menuButton.innerHTML = terbuka ? '✕' : '☰';

            menuButton.setAttribute(
                'aria-expanded',
                String(terbuka)
            );

        });

        overlay.addEventListener('click', tutupMenu);

        sidebar.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', tutupMenu);
        });

        document.addEventListener('keydown', function (event) {

            if (event.key === 'Escape') {
                tutupMenu();
            }

        });

    }

});
</script>

</body>
</html>
