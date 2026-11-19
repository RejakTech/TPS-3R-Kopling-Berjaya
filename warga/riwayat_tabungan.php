
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$user_id = (int) $_SESSION['user_id'];

// Ambil rekening tabungan warga
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, saldo
     FROM tabungan_warga
     WHERE user_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result_tabungan = mysqli_stmt_get_result($stmt);
$tabungan = mysqli_fetch_assoc($result_tabungan);

mysqli_stmt_close($stmt);

$saldo = 0;
$total_masuk = 0;
$total_keluar = 0;
$data_mutasi = [];

if ($tabungan) {

    $tabungan_id = (int) $tabungan['id'];
    $saldo = (float) $tabungan['saldo'];

    // Hitung total transaksi masuk dan keluar
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            COALESCE(SUM(CASE
                WHEN jenis_transaksi = 'masuk' THEN jumlah
                ELSE 0
            END), 0) AS total_masuk,

            COALESCE(SUM(CASE
                WHEN jenis_transaksi = 'keluar' THEN jumlah
                ELSE 0
            END), 0) AS total_keluar

         FROM mutasi_tabungan
         WHERE tabungan_id = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $tabungan_id);
    mysqli_stmt_execute($stmt);

    $result_ringkasan = mysqli_stmt_get_result($stmt);
    $ringkasan = mysqli_fetch_assoc($result_ringkasan);

    $total_masuk = (float) $ringkasan['total_masuk'];
    $total_keluar = (float) $ringkasan['total_keluar'];

    mysqli_stmt_close($stmt);

    // Ambil riwayat transaksi
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            id,
            setoran_id,
            jenis_transaksi,
            jumlah,
            keterangan,
            tanggal_transaksi
         FROM mutasi_tabungan
         WHERE tabungan_id = ?
         ORDER BY id DESC"
    );

    mysqli_stmt_bind_param($stmt, "i", $tabungan_id);
    mysqli_stmt_execute($stmt);

    $result_mutasi = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result_mutasi)) {
        $data_mutasi[] = $row;
    }

    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Tabungan - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #F1F6F9;
            font-family: sans-serif;
            color: #183B55;
        }

        /* =================================
           SIDEBAR WARGA
        ================================= */

        .warga-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 250px;
            background: linear-gradient(180deg, #071F33 0%, #0B2D46 100%);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-brand {
            padding: 24px 22px 20px;
            border-bottom: 1px solid rgba(255,255,255,.09);
            background: rgba(255,255,255,.02);
        }

        .sidebar-brand-top {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-logo {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 50%;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .sidebar-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 2px;
        }

        .sidebar-brand-top > div:last-child {
            flex: 1;
            min-width: 0;
        }

        .sidebar-brand h2 {
            margin: 0;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.2;
        }

        .sidebar-brand p {
            margin: 5px 0 0;
            color: #8FB3C2;
            font-size: 10px;
            line-height: 1.3;
        }

        .sidebar-nav {
            padding: 20px 14px;
            flex: 1;
        }

        .sidebar-section-title {
            padding: 0 12px;
            margin: 12px 0 8px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .09em;
            color: #6F98A9;
            text-transform: uppercase;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 11px 12px;
            margin: 3px 0;
            border-radius: 10px;
            color: #B8CDD7;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: .2s ease;
        }

        .sidebar-link:hover {
            background: rgba(24,195,216,.10);
            color: #fff;
            transform: translateX(2px);
        }

        .sidebar-link.active {
            background: rgba(24,195,216,.14);
            color: #18C3D8;
            box-shadow: inset 3px 0 0 #18C3D8;
        }

        .sidebar-icon {
            width: 22px;
            min-width: 22px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-logout {
            color: #FFB4B4;
        }

        .sidebar-logout:hover {
            background: rgba(239,68,68,.12);
            color: #fff;
        }

        /* =================================
           KONTEN UTAMA
        ================================= */

        .warga-content {
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;
            padding: 36px 38px 25px;
        }

        .warga-top-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 27px;
        }

        .warga-heading {
            margin: 0;
        }

        .warga-heading h1 {
            margin: 0;
            color: #0B354D;
            font-size: 25px;
            font-weight: 800;
            line-height: 1.3;
        }

        .warga-heading p {
            margin: 7px 0 0;
            color: #8295A2;
            font-size: 13px;
        }

        /* TOMBOL KEMBALI */

        .btn-kembali {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 12px 19px;
            background: linear-gradient(110deg, #0B354D, #159BAE);
            color: #fff;
            text-decoration: none;
            border-radius: 11px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            box-shadow: 0 5px 14px rgba(11,53,77,.12);
            transition: .2s ease;
        }

        .btn-kembali:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 18px rgba(21,155,174,.22);
        }

        /* =================================
           KARTU SALDO
        ================================= */

        .tabungan-saldo-card {
            position: relative;
            overflow: hidden;
            padding: 27px 30px;
            margin-bottom: 22px;
            border-radius: 17px;
            background: linear-gradient(115deg, #082C43 0%, #0C536A 55%, #159BAE 100%);
            color: #fff;
            box-shadow: 0 9px 24px rgba(11,53,77,.13);
        }

        .tabungan-saldo-card::before {
            content: "";
            position: absolute;
            width: 210px;
            height: 210px;
            border-radius: 50%;
            background: rgba(255,255,255,.06);
            right: -55px;
            top: -100px;
        }

        .tabungan-saldo-card::after {
            content: "";
            position: absolute;
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: rgba(255,255,255,.05);
            right: 100px;
            bottom: -95px;
        }

        .saldo-label {
            position: relative;
            z-index: 1;
            display: block;
            color: #C2E4EC;
            font-size: 13px;
            font-weight: 600;
        }

        .tabungan-saldo-card h2 {
            position: relative;
            z-index: 1;
            margin: 11px 0 7px;
            color: #fff;
            font-size: 31px;
            font-weight: 800;
            letter-spacing: .2px;
        }

        .tabungan-saldo-card p {
            position: relative;
            z-index: 1;
            margin: 0;
            color: #B8DCE5;
            font-size: 12px;
        }

        /* =================================
           RINGKASAN TRANSAKSI
        ================================= */

        .warga-cards {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .warga-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 21px 23px;
            background: #fff;
            border: 1px solid #E5EDF2;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(11,53,77,.045);
            min-width: 0;
        }

        .mutasi-card-icon {
            width: 48px;
            height: 48px;
            min-width: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            font-size: 22px;
        }

        .icon-masuk {
            background: #E2F6ED;
        }

        .icon-keluar {
            background: #FDECEC;
        }

        .mutasi-card-info {
            min-width: 0;
        }

        .mutasi-card-info h3 {
            margin: 0 0 7px;
            color: #8295A2;
            font-size: 12px;
            font-weight: 600;
        }

        .mutasi-card-info p {
            margin: 0;
            color: #0B354D;
            font-size: 20px;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        /* =================================
           TABEL RIWAYAT
        ================================= */

        .riwayat-tabungan-section {
            background: #fff;
            border: 1px solid #E5EDF2;
            border-radius: 17px;
            overflow: hidden;
            box-shadow: 0 7px 24px rgba(11,53,77,.045);
        }

        .riwayat-tabungan-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 21px 25px;
            border-bottom: 1px solid #EDF2F5;
        }

        .riwayat-tabungan-heading h2 {
            margin: 0;
            color: #0B354D;
            font-size: 17px;
            font-weight: 800;
        }

        .riwayat-tabungan-heading p {
            margin: 5px 0 0;
            color: #8295A2;
            font-size: 12px;
        }

        .warga-table-container {
            width: 100%;
            overflow-x: auto;
        }

        .warga-table {
            width: 100%;
            min-width: 700px;
            border-collapse: collapse;
        }

        .warga-table thead {
            background: #F3F8FA;
        }

        .warga-table th {
            padding: 15px 17px;
            color: #58778A;
            text-align: left;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .warga-table td {
            padding: 17px;
            color: #587487;
            border-bottom: 1px solid #EDF2F5;
            font-size: 12px;
            line-height: 1.5;
        }

        .warga-table tbody tr:last-child td {
            border-bottom: none;
        }

        .warga-table tbody tr:hover {
            background: #F8FBFC;
        }

        /* BADGE TRANSAKSI */

        .mutasi-masuk,
        .mutasi-keluar {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .mutasi-masuk {
            color: #07844F;
            background: #E1F6EC;
        }

        .mutasi-keluar {
            color: #D14E4E;
            background: #FCEAEA;
        }

        .nominal-masuk {
            color: #07945A;
            font-weight: 800;
            white-space: nowrap;
        }

        .nominal-keluar {
            color: #D14E4E;
            font-weight: 800;
            white-space: nowrap;
        }

        .empty-table {
            padding: 35px 15px !important;
            text-align: center;
            color: #91A2AD !important;
        }

        /* =================================
           FOOTER
        ================================= */

        .warga-footer {
            margin-top: 28px;
            padding: 20px 10px 5px;
            border-top: 1px solid #E1EAF0;
            color: #91A5B1;
            text-align: center;
            font-size: 11px;
        }

        /* =================================
           RESPONSIVE TABLET
        ================================= */

        @media (max-width: 1000px) {
            .warga-content {
                padding: 30px 24px;
            }

            .warga-heading h1 {
                font-size: 23px;
            }

            .mutasi-card-info p {
                font-size: 17px;
            }
        }

        /* =================================
           RESPONSIVE HP
        ================================= */

        @media (max-width: 768px) {
            .warga-sidebar {
                position: relative;
                width: 100%;
                height: auto;
                max-height: none;
                overflow: visible;
            }

            .sidebar-brand {
                padding: 15px 18px;
            }

            .sidebar-nav {
                padding: 10px 13px 14px;
            }

            .sidebar-section-title {
                margin-top: 8px;
            }

            .sidebar-link {
                padding: 9px 11px;
                font-size: 12px;
            }

            .warga-content {
                margin-left: 0;
                width: 100%;
                min-height: auto;
                padding: 24px 16px;
            }

            .warga-top-heading {
                align-items: flex-start;
                flex-direction: column;
                gap: 15px;
                margin-bottom: 22px;
            }

            .warga-heading h1 {
                font-size: 22px;
            }

            .warga-heading p {
                font-size: 12px;
                line-height: 1.6;
            }

            .btn-kembali {
                padding: 11px 15px;
                font-size: 11px;
            }

            .tabungan-saldo-card {
                padding: 23px 21px;
                margin-bottom: 17px;
            }

            .tabungan-saldo-card h2 {
                font-size: 27px;
            }

            .warga-cards {
                grid-template-columns: 1fr;
                gap: 13px;
                margin-bottom: 20px;
            }

            .warga-card {
                padding: 17px;
            }

            .mutasi-card-info p {
                font-size: 18px;
            }

            .riwayat-tabungan-heading {
                padding: 17px 18px;
            }

            .riwayat-tabungan-heading h2 {
                font-size: 15px;
            }

            .warga-table th,
            .warga-table td {
                padding: 13px 14px;
                font-size: 11px;
            }
        }

        @media (max-width: 380px) {
            .warga-content {
                padding: 20px 12px;
            }

            .tabungan-saldo-card h2 {
                font-size: 24px;
            }

            .warga-heading h1 {
                font-size: 20px;
            }
        }

/* =================================
   MOBILE HAMBURGER SIDEBAR
================================= */

.mobile-menu-button {
    display: none;
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    border: none;
    border-radius: 11px;
    background: linear-gradient(135deg, #071F33 0%, #0B354D 55%, #147F91 100%);
    color: white;
    font-size: 23px;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(11,53,77,.20);
    transition: .25s ease;
}

.mobile-menu-button:hover {
    background: linear-gradient(135deg, #0B354D, #17B8C8);
}

.sidebar-overlay {
    display: none;
}

/* RESPONSIVE HAMBURGER */
@media (max-width: 768px) {

    .warga-sidebar {
        position: fixed !important;
        top: 0;
        left: 0;
        bottom: 0;
        width: 260px !important;
        height: 100vh !important;
        max-height: none !important;
        min-height: 100vh !important;
        display: flex;
        flex-direction: column;
        padding: 0 !important;
        background: linear-gradient(180deg, #071F33 0%, #0B2D46 100%);
        overflow-y: auto;
        z-index: 1001;
        transform: translateX(-100%);
        transition: transform .3s ease;
    }

    .warga-sidebar.open {
        transform: translateX(0);
    }

    .sidebar-brand {
        padding: 24px 18px 20px !important;
    }

    .sidebar-nav {
        display: flex;
        flex-direction: column;
        padding: 15px 14px !important;
        overflow-x: visible;
        overflow-y: auto;
    }

    .sidebar-section-title {
        display: block;
        margin: 12px 0 8px;
    }

    .sidebar-link {
        width: 100%;
        flex: none;
        white-space: normal;
        padding: 11px 12px;
        margin: 3px 0;
    }

    .sidebar-link.active {
        box-shadow: inset 3px 0 0 #18C3D8;
    }

    .warga-content {
        margin-left: 0 !important;
        width: 100% !important;
        min-height: 100vh;
        padding: 22px 15px 30px !important;
    }

    .mobile-menu-button {
        display: flex;
    }

    .sidebar-overlay {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.45);
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: .3s ease;
    }

    .sidebar-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    /* Header HP */
    .warga-top-heading {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .warga-heading {
        flex: 1;
        min-width: 0;
    }

    .warga-heading h1 {
        font-size: 19px;
    }

    .warga-heading p {
        font-size: 11px;
    }

.header-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    flex-shrink: 0;
}

    .btn-kembali {
        padding: 9px 10px;
        font-size: 10px;
        gap: 5px;
    }
}

@media (max-width: 420px) {

    .warga-top-heading {
        gap: 7px;
    }

    .warga-heading h1 {
        font-size: 17px;
    }

    .warga-heading p {
        font-size: 10px;
    }
.header-actions {
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
}

    .mobile-menu-button {
        width: 39px;
        height: 39px;
        font-size: 21px;
    }

    .btn-kembali {
        padding: 9px 8px;
        font-size: 9px;
    }
}

/* =====================================
   SIDEBAR - SEMBUNYIKAN SCROLLBAR
   ===================================== */

.warga-sidebar {
    overflow: hidden !important;
}

.sidebar-nav {
    overflow-y: auto !important;
    overflow-x: hidden !important;

    /* Firefox */
    scrollbar-width: none !important;

    /* Internet Explorer dan Edge lama */
    -ms-overflow-style: none !important;
}

/* Chrome, Edge, dan Safari */
.sidebar-nav::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
}


    </style>
</head>

<body>

<div class="dashboard-warga">

    <!-- SIDEBAR -->
<aside class="warga-sidebar" id="wargaSidebar">

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

            <a href="riwayat_tabungan.php" class="sidebar-link active">
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

            <a href="profil.php" class="sidebar-link">
                <span class="sidebar-icon">⚙️</span>
                <span>Profil Saya</span>
            </a>

            <a href="../auth/logout.php"
               class="sidebar-link sidebar-logout"
               onclick="return confirm('Yakin ingin keluar dari akun?')">
                <span class="sidebar-icon">🚪</span>
                <span>Logout</span>
            </a>

        </nav>

    </aside>

    <!-- OVERLAY SIDEBAR MOBILE -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- KONTEN -->
    <main class="warga-content">

        <!-- JUDUL DAN TOMBOL KEMBALI -->
<div class="warga-top-heading">

    <div class="warga-heading">
        <h1>Riwayat Tabungan</h1>

        <p>
            Pantau saldo dan seluruh aktivitas transaksi tabunganmu.
        </p>
    </div>

    <div class="header-actions">

        <!-- TOMBOL HAMBURGER MOBILE -->
        <button
            type="button"
            class="mobile-menu-button"
            id="mobileMenuButton"
            aria-label="Buka menu"
            aria-expanded="false">
            ☰
        </button>

        <a href="dashboard.php" class="btn-kembali">
            <span>←</span>
            Kembali ke Dashboard
        </a>

    </div>

</div>

        <!-- SALDO SAAT INI -->
        <section class="tabungan-saldo-card">

            <span class="saldo-label">Saldo Tabungan Saat Ini</span>

            <h2>
                Rp <?php echo number_format($saldo, 0, ',', '.'); ?>
            </h2>

            <p>Saldo rekening Bank Sampah Digital</p>

        </section>

        <!-- RINGKASAN TRANSAKSI -->
        <div class="warga-cards">

            <div class="warga-card">

                <div class="mutasi-card-icon icon-masuk">
                    📈
                </div>

                <div class="mutasi-card-info">
                    <h3>Total Uang Masuk</h3>

                    <p>
                        Rp <?php echo number_format($total_masuk, 0, ',', '.'); ?>
                    </p>
                </div>

            </div>

            <div class="warga-card">

                <div class="mutasi-card-icon icon-keluar">
                    📉
                </div>

                <div class="mutasi-card-info">
                    <h3>Total Uang Keluar</h3>

                    <p>
                        Rp <?php echo number_format($total_keluar, 0, ',', '.'); ?>
                    </p>
                </div>

            </div>

        </div>

        <!-- DAFTAR TRANSAKSI -->
        <section class="riwayat-tabungan-section">

            <div class="riwayat-tabungan-heading">
                <div>
                    <h2>💰 Riwayat Transaksi</h2>

                    <p>
                        Daftar seluruh aktivitas uang masuk dan keluar dari tabunganmu.
                    </p>
                </div>
            </div>

            <div class="warga-table-container">

                <table class="warga-table">

                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal Transaksi</th>
                            <th>Jenis Transaksi</th>
                            <th>Keterangan</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($data_mutasi) > 0) : ?>

                            <?php foreach ($data_mutasi as $index => $mutasi) : ?>

                                <tr>

                                    <td>
                                        <?php echo $index + 1; ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo date(
                                            'd/m/Y H:i',
                                            strtotime($mutasi['tanggal_transaksi'])
                                        );
                                        ?>
                                    </td>

                                    <td>

                                        <?php if ($mutasi['jenis_transaksi'] === 'masuk') : ?>

                                            <span class="mutasi-masuk">
                                                ↑ Uang Masuk
                                            </span>

                                        <?php else : ?>

                                            <span class="mutasi-keluar">
                                                ↓ Uang Keluar
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $mutasi['keterangan'] ?: '-'
                                        );
                                        ?>
                                    </td>

                                    <td>

                                        <strong class="<?php
                                            echo $mutasi['jenis_transaksi'] === 'masuk'
                                                ? 'nominal-masuk'
                                                : 'nominal-keluar';
                                        ?>">

                                            <?php
                                            echo $mutasi['jenis_transaksi'] === 'masuk'
                                                ? '+ Rp '
                                                : '- Rp ';
                                            ?>

                                            <?php echo number_format(
                                                (float) $mutasi['jumlah'],
                                                0,
                                                ',',
                                                '.'
                                            ); ?>

                                        </strong>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else : ?>

                            <tr>
                                <td colspan="5" class="empty-table">
                                    <div style="font-size:28px; margin-bottom:8px;">💳</div>
                                    Belum ada transaksi tabungan.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

        <footer class="warga-footer">
            © <?php echo date('Y'); ?> Bank Sampah Digital. Bersama Jaga Lingkungan.
        </footer>

    </main>

</div>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const menuButton = document.getElementById('mobileMenuButton');
    const sidebar = document.getElementById('wargaSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (menuButton && sidebar && overlay) {

        function bukaTutupMenu() {

            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');

            const isOpen = sidebar.classList.contains('open');

            menuButton.innerHTML = isOpen ? '✕' : '☰';

            menuButton.setAttribute(
                'aria-expanded',
                isOpen ? 'true' : 'false'
            );
        }

        menuButton.addEventListener('click', bukaTutupMenu);

        overlay.addEventListener('click', bukaTutupMenu);

        sidebar.querySelectorAll('.sidebar-link').forEach(link => {

            link.addEventListener('click', function () {

                sidebar.classList.remove('open');
                overlay.classList.remove('show');

                menuButton.innerHTML = '☰';

                menuButton.setAttribute('aria-expanded', 'false');

            });

        });
    }

});
</script>
</body>
</html>
