
<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include_once '../config/koneksi.php';

$user_id = (int) $_SESSION['user_id'];

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// ===================================
// PROSES TANDAI NOTIFIKASI DIBACA
// ===================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        header("Location: notifikasi.php?status=token_gagal");
        exit;
    }

    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'baca_semua') {

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE notifikasi
             SET dibaca = 1
             WHERE user_id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

    } elseif ($aksi === 'baca_satu') {

        $notifikasi_id = filter_var(
            $_POST['notifikasi_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($notifikasi_id && $notifikasi_id > 0) {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE notifikasi
                 SET dibaca = 1
                 WHERE id = ? AND user_id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $notifikasi_id,
                $user_id
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header("Location: notifikasi.php");
    exit;
}

// ===================================
// CSRF TOKEN
// ===================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ===================================
// RINGKASAN NOTIFIKASI
// ===================================

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN dibaca = 0 THEN 1 ELSE 0 END) AS belum_dibaca
     FROM notifikasi
     WHERE user_id = ?"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$ringkasan = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
);

mysqli_stmt_close($stmt);

$total_notifikasi = (int) ($ringkasan['total'] ?? 0);
$belum_dibaca = (int) ($ringkasan['belum_dibaca'] ?? 0);

// ===================================
// AMBIL DATA NOTIFIKASI
// ===================================

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, judul, pesan, tipe, link, dibaca, tanggal_dibuat
     FROM notifikasi
     WHERE user_id = ?
     ORDER BY tanggal_dibuat DESC, id DESC"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Notifikasi - Bank Sampah Digital</title>

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
           RINGKASAN NOTIFIKASI
        ===================================== */

        .warga-cards {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .warga-card {
            min-width: 0;
            padding: 21px 22px;
            border: 1px solid #E5EDF2;
            border-radius: 13px;
            background: #fff;
            box-shadow: 0 3px 12px rgba(7,31,51,.035);
            transition: .2s ease;
        }

        .warga-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 18px rgba(7,31,51,.07);
        }

        .warga-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .warga-card h3 {
            margin: 0 0 10px;
            color: #718096;
            font-size: 12px;
            font-weight: 500;
        }

        .warga-card p {
            margin: 0;
            color: #071F33;
            font-size: 26px;
            font-weight: 700;
            line-height: 1.2;
        }

        .warga-card-icon {
            width: 45px;
            height: 45px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            color: #147F91;
            background: #E7F5F8;
            font-size: 21px;
        }

        .warga-card-icon.unread {
            color: #2879BB;
            background: #EAF2FF;
        }

        /* =====================================
           ALERT
        ===================================== */

        .form-alert-success {
            padding: 13px 16px;
            margin-bottom: 22px;
            border: 1px solid #BDE8CE;
            border-radius: 10px;
            color: #18794E;
            background: #E8F7EF;
            font-size: 12px;
            font-weight: 500;
        }

        .form-alert-error {
            padding: 13px 16px;
            margin-bottom: 22px;
            border: 1px solid #F2C5C5;
            border-radius: 10px;
            color: #B93838;
            background: #FDECEC;
            font-size: 12px;
            font-weight: 500;
        }

        /* =====================================
           PANEL NOTIFIKASI
        ===================================== */

        .notifikasi-section {
            min-width: 0;
            padding: 23px;
            border: 1px solid #E5EDF2;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 3px 12px rgba(7,31,51,.035);
        }

        .notifikasi-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .notifikasi-heading h2 {
            margin: 0;
            color: #071F33;
            font-size: 17px;
            font-weight: 700;
        }

        .notifikasi-heading p {
            margin: 5px 0 0;
            color: #8293A0;
            font-size: 10px;
        }

        .notifikasi-baca-semua {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            color: #fff;
            background: linear-gradient(135deg, #071F33, #147F91);
            font-family: inherit;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: .2s ease;
        }

        .notifikasi-baca-semua:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(7,31,51,.15);
        }

        /* =====================================
           ITEM NOTIFIKASI
        ===================================== */

        .notifikasi-list {
            border: 1px solid #E8EEF2;
            border-radius: 10px;
            overflow: hidden;
        }

        .notifikasi-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 19px 20px;
            border-bottom: 1px solid #EDF1F4;
            background: #fff;
            transition: .2s ease;
        }

        .notifikasi-item:last-child {
            border-bottom: none;
        }

        .notifikasi-item:hover {
            background: #F8FBFC;
        }

        .notifikasi-item.belum-dibaca {
            background: #F0F9FB;
        }

        .notifikasi-icon {
            width: 43px;
            height: 43px;
            min-width: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            color: #18794E;
            background: #E6F6ED;
            font-size: 19px;
            font-weight: 700;
        }

        .notifikasi-icon.peringatan {
            color: #A66A0A;
            background: #FFF4D9;
        }

        .notifikasi-icon.informasi {
            color: #397CC0;
            background: #EAF2FF;
        }

        .notifikasi-detail {
            flex: 1;
            min-width: 0;
        }

        .notifikasi-title {
            margin-bottom: 6px;
            color: #102F43;
            font-size: 12px;
            font-weight: 700;
        }

        .notifikasi-pesan {
            color: #617687;
            font-size: 11px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .notifikasi-waktu {
            margin-top: 9px;
            color: #91A0AA;
            font-size: 10px;
        }

        .notifikasi-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .notifikasi-actions form {
            margin: 0;
        }

        .notifikasi-link {
            color: #147F91;
            text-decoration: none;
            font-size: 10px;
            font-weight: 700;
        }

        .notifikasi-link:hover {
            text-decoration: underline;
        }

        .notifikasi-baca {
            padding: 0;
            border: none;
            color: #718493;
            background: transparent;
            font-family: inherit;
            font-size: 10px;
            font-weight: 500;
            cursor: pointer;
        }

        .notifikasi-baca:hover {
            color: #147F91;
        }

        .notifikasi-dot {
            width: 9px;
            height: 9px;
            min-width: 9px;
            margin-top: 7px;
            border-radius: 50%;
            background: #18C3D8;
        }

        /* =====================================
           EMPTY STATE
        ===================================== */

        .notifikasi-empty {
            padding: 55px 20px;
            text-align: center;
            border: 1px solid #E8EEF2;
            border-radius: 10px;
        }

        .notifikasi-empty-icon {
            width: 65px;
            height: 65px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: #E7F5F8;
            font-size: 28px;
        }

        .notifikasi-empty strong {
            display: block;
            color: #183B55;
            font-size: 13px;
        }

        .notifikasi-empty p {
            margin: 7px 0 0;
            color: #8293A0;
            font-size: 11px;
        }

        /* =====================================
           RESPONSIVE TABLET
        ===================================== */

        @media (max-width: 1000px) {

            .warga-main {
                padding: 25px 22px;
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

            .warga-cards {
                gap: 10px;
            }

            .warga-card {
                padding: 16px;
            }

            .warga-card h3 {
                font-size: 10px;
            }

            .warga-card p {
                font-size: 23px;
            }

            .notifikasi-section {
                padding: 16px;
            }

            .notifikasi-heading h2 {
                font-size: 15px;
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

            .warga-card {
                padding: 13px;
            }

            .warga-card h3 {
                font-size: 10px;
            }

            .warga-card p {
                font-size: 21px;
            }

            .warga-card-icon {
                width: 36px;
                height: 36px;
                font-size: 17px;
            }

            .notifikasi-section {
                padding: 13px;
            }

            .notifikasi-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .notifikasi-baca-semua {
                width: 100%;
            }

            .notifikasi-item {
                padding: 15px 12px;
                gap: 10px;
            }

            .notifikasi-icon {
                width: 36px;
                height: 36px;
                min-width: 36px;
                font-size: 16px;
            }

            .notifikasi-title {
                font-size: 11px;
            }

            .notifikasi-pesan {
                font-size: 10px;
            }

            .notifikasi-dot {
                width: 7px;
                height: 7px;
                min-width: 7px;
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

        <a href="notifikasi.php" class="sidebar-link active">
            <span class="sidebar-icon">🔔</span>
            <span>Notifikasi</span>
        </a>

        <div class="sidebar-section-title">Akun</div>

        <a href="profil.php" class="sidebar-link">
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

<!-- OVERLAY SIDEBAR -->

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- =====================================
     KONTEN UTAMA
===================================== -->

<main class="warga-main">

    <!-- HEADER -->

    <div class="page-header-row">

        <div class="warga-heading">

            <h1>Notifikasi</h1>

            <p>
                Informasi terbaru mengenai aktivitas dan transaksi tabungan kamu.
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

    <!-- ALERT TOKEN -->

    <?php if (isset($_GET['status']) && $_GET['status'] === 'token_gagal'): ?>

        <div class="form-alert-error">
            Permintaan tidak dapat diproses. Silakan muat ulang halaman dan coba kembali.
        </div>

    <?php endif; ?>

    <!-- =====================================
         RINGKASAN NOTIFIKASI
    ===================================== -->

    <div class="warga-cards">

        <div class="warga-card">

            <div class="warga-card-top">

                <div>
                    <h3>Total Notifikasi</h3>

                    <p>
                        <?= number_format($total_notifikasi, 0, ',', '.') ?>
                    </p>
                </div>

                <div class="warga-card-icon">
                    🔔
                </div>

            </div>

        </div>

        <div class="warga-card">

            <div class="warga-card-top">

                <div>
                    <h3>Belum Dibaca</h3>

                    <p>
                        <?= number_format($belum_dibaca, 0, ',', '.') ?>
                    </p>
                </div>

                <div class="warga-card-icon unread">
                    ✉️
                </div>

            </div>

        </div>

    </div>

    <!-- =====================================
         DAFTAR NOTIFIKASI
    ===================================== -->

    <section class="notifikasi-section">

        <div class="notifikasi-heading">

            <div>
                <h2>Semua Pemberitahuan</h2>

                <p>
                    Daftar informasi dan aktivitas terbaru akun kamu.
                </p>
            </div>

            <?php if ($belum_dibaca > 0): ?>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="aksi"
                        value="baca_semua"
                    >

                    <button
                        class="notifikasi-baca-semua"
                        type="submit"
                    >
                        ✓ Tandai Semua Dibaca
                    </button>

                </form>

            <?php endif; ?>

        </div>

        <div class="notifikasi-list">

            <?php if (mysqli_num_rows($result) > 0): ?>

                <?php while ($row = mysqli_fetch_assoc($result)): ?>

                    <?php
                    $ikon = '🔔';

                    if ($row['tipe'] === 'sukses') {
                        $ikon = '✓';
                    } elseif ($row['tipe'] === 'peringatan') {
                        $ikon = '!';
                    } elseif ($row['tipe'] === 'informasi') {
                        $ikon = 'ℹ';
                    }

                    $tipe_ikon = in_array(
                        $row['tipe'],
                        ['sukses', 'peringatan', 'informasi'],
                        true
                    ) ? $row['tipe'] : '';
                    ?>

                    <div class="notifikasi-item <?= (int)$row['dibaca'] === 0 ? 'belum-dibaca' : '' ?>">

                        <!-- IKON -->

                        <div class="notifikasi-icon <?= e($tipe_ikon) ?>">
                            <?= e($ikon) ?>
                        </div>

                        <!-- DETAIL -->

                        <div class="notifikasi-detail">

                            <div class="notifikasi-title">
                                <?= e($row['judul']) ?>
                            </div>

                            <div class="notifikasi-pesan">
                                <?= nl2br(e($row['pesan'])) ?>
                            </div>

                            <div class="notifikasi-waktu">

                                <?php
                                echo !empty($row['tanggal_dibuat'])
                                    ? date(
                                        'd M Y, H:i',
                                        strtotime($row['tanggal_dibuat'])
                                    )
                                    : '-';
                                ?>

                            </div>

                            <div class="notifikasi-actions">

                                <?php if (!empty($row['link'])): ?>

                                    <a
                                        class="notifikasi-link"
                                        href="buka_notifikasi.php?id=<?= (int)$row['id'] ?>&link=<?= urlencode($row['link']) ?>"
                                    >
                                        Lihat Detail →
                                    </a>

                                <?php endif; ?>

                                <?php if ((int)$row['dibaca'] === 0): ?>

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($_SESSION['csrf_token']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="aksi"
                                            value="baca_satu"
                                        >

                                        <input
                                            type="hidden"
                                            name="notifikasi_id"
                                            value="<?= (int)$row['id'] ?>"
                                        >

                                        <button
                                            class="notifikasi-baca"
                                            type="submit"
                                        >
                                            Tandai sudah dibaca
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>

                        </div>

                        <!-- PENANDA BELUM DIBACA -->

                        <?php if ((int)$row['dibaca'] === 0): ?>

                            <div class="notifikasi-dot"></div>

                        <?php endif; ?>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="notifikasi-empty">

                    <div class="notifikasi-empty-icon">
                        🔔
                    </div>

                    <strong>Belum Ada Notifikasi</strong>

                    <p>
                        Pemberitahuan baru akan muncul di sini.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </section>

</main>

<!-- =====================================
     JAVASCRIPT
===================================== -->

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ===================================
    // HAMBURGER MENU
    // ===================================

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

<?php
mysqli_stmt_close($stmt);
?>
