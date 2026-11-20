
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$user_id = (int) $_SESSION['user_id'];
$penarikan_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// ===================================
// AMBIL DATA PENARIKAN
// ===================================

$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM penarikan_tabungan
     WHERE user_id = ?
     ORDER BY id DESC"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data_penarikan = [];

$total_pengajuan = 0;
$total_menunggu = 0;
$total_disetujui = 0;
$total_ditolak = 0;
$total_nominal_disetujui = 0;

while ($row = mysqli_fetch_assoc($result)) {

    $data_penarikan[] = $row;
    $total_pengajuan++;

    if ($row['status'] == 'menunggu') {
        $total_menunggu++;
    } elseif ($row['status'] == 'disetujui') {
        $total_disetujui++;
        $total_nominal_disetujui += (float) $row['jumlah'];
    } elseif ($row['status'] == 'ditolak') {
        $total_ditolak++;
    }
}

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Penarikan - Bank Sampah Digital</title>

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
           NOTIFIKASI
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

        /* =====================================
           KARTU RINGKASAN
        ===================================== */

        .warga-cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
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

        /* =====================================
           TOTAL PENARIKAN
        ===================================== */

        .tabungan-saldo-card {
            position: relative;
            width: 100%;
            overflow: hidden;
            padding: 24px 27px;
            margin-bottom: 25px;
            border-radius: 15px;
            color: #fff;
            background: linear-gradient(115deg, #071F33, #0B3B55);
            box-shadow: 0 6px 18px rgba(7,31,51,.10);
        }

        .tabungan-saldo-card::after {
            content: "";
            position: absolute;
            width: 150px;
            height: 150px;
            top: -75px;
            right: -35px;
            border: 25px solid rgba(24,195,216,.10);
            border-radius: 50%;
        }

        .tabungan-saldo-card span {
            display: block;
            margin-bottom: 9px;
            color: #B9D9E4;
            font-size: 12px;
            font-weight: 500;
        }

        .tabungan-saldo-card h2 {
            position: relative;
            z-index: 1;
            margin: 0 0 7px;
            color: #fff;
            font-size: 29px;
            font-weight: 700;
            letter-spacing: -.5px;
        }

        .tabungan-saldo-card p {
            margin: 0;
            color: #B9D9E4;
            font-size: 11px;
        }

        /* =====================================
           TABEL RIWAYAT
        ===================================== */

        .riwayat-penarikan-section {
            min-width: 0;
            padding: 23px;
            border: 1px solid #E5EDF2;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 3px 12px rgba(7,31,51,.035);
        }

        .riwayat-penarikan-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .riwayat-penarikan-heading h2 {
            margin: 0;
            color: #071F33;
            font-size: 17px;
            font-weight: 700;
        }

        .btn-ajukan-riwayat {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            border-radius: 8px;
            color: #fff;
            background: #147F91;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            transition: .2s ease;
        }

        .btn-ajukan-riwayat:hover {
            background: #0B5367;
            transform: translateY(-2px);
        }

        .warga-table-container {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #E8EEF2;
            border-radius: 10px;
        }

        .warga-table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        .warga-table thead {
            background: #F2F7FA;
        }

        .warga-table th {
            padding: 14px 15px;
            border-bottom: 1px solid #E5EDF2;
            color: #526777;
            font-size: 10px;
            font-weight: 700;
            text-align: left;
            white-space: nowrap;
        }

        .warga-table td {
            padding: 15px;
            border-bottom: 1px solid #EDF1F4;
            color: #435667;
            font-size: 11px;
            font-weight: 400;
            line-height: 1.7;
            vertical-align: middle;
        }

        .warga-table tbody tr:last-child td {
            border-bottom: none;
        }

        .warga-table tbody tr:hover {
            background: #F8FBFC;
        }

        .warga-table td strong {
            color: #071F33;
            font-weight: 600;
            white-space: nowrap;
        }

        .warga-table small {
            color: #8293A0;
            font-size: 10px;
        }

        /* =====================================
           STATUS
        ===================================== */

        .status-penarikan {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-pending {
            color: #A66A0A;
            background: #FFF4D9;
        }

        .status-approved {
            color: #18794E;
            background: #E6F6ED;
        }

        .status-rejected {
            color: #B93838;
            background: #FDECEC;
        }

        /* =====================================
           HIGHLIGHT RIWAYAT
        ===================================== */

        .warga-table tbody tr.penarikan-highlight {
            background: #E9F8FB;
            animation: highlightRow 2.5s ease;
        }

        @keyframes highlightRow {
            0%, 100% {
                background: #E9F8FB;
            }

            50% {
                background: #D2F1F5;
            }
        }

        /* =====================================
           RESPONSIVE TABLET
        ===================================== */

        @media (max-width: 1000px) {

            .warga-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .warga-main {
                padding: 25px 22px;
            }

        }

        /* =====================================
           RESPONSIVE MOBILE
           HEADER SAMA DENGAN AJUKAN PENARIKAN
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

            /* Header tetap horizontal */
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

            /* Hamburger dan tombol kembali bertumpuk */
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
                grid-template-columns: repeat(2, minmax(0, 1fr));
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

            .tabungan-saldo-card {
                padding: 20px;
            }

            .tabungan-saldo-card h2 {
                font-size: 23px;
            }

            .riwayat-penarikan-section {
                padding: 16px;
            }

            .riwayat-penarikan-heading h2 {
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

            .warga-cards {
                gap: 9px;
            }

            .warga-card {
                padding: 13px;
            }

            .warga-card p {
                font-size: 21px;
            }

            .tabungan-saldo-card {
                padding: 19px;
            }

            .tabungan-saldo-card h2 {
                font-size: 21px;
            }

            .riwayat-penarikan-section {
                padding: 13px;
            }

            .riwayat-penarikan-heading {
                align-items: flex-start;
                flex-direction: column;
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

        <a href="riwayat_penarikan.php" class="sidebar-link active">
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

            <h1>Riwayat Penarikan</h1>

            <p>
                Lihat riwayat pengajuan penarikan saldo tabungan kamu.
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

    <!-- NOTIFIKASI BERHASIL -->

    <?php if (isset($_GET['status']) && $_GET['status'] == 'berhasil') : ?>

        <div class="form-alert-success">
            ✓ Pengajuan penarikan berhasil dikirim.
            Silakan tunggu persetujuan admin.
        </div>

    <?php endif; ?>

    <!-- RINGKASAN -->

    <div class="warga-cards">

        <div class="warga-card">
            <h3>Total Pengajuan</h3>
            <p><?php echo $total_pengajuan; ?></p>
        </div>

        <div class="warga-card">
            <h3>Menunggu</h3>
            <p><?php echo $total_menunggu; ?></p>
        </div>

        <div class="warga-card">
            <h3>Disetujui</h3>
            <p><?php echo $total_disetujui; ?></p>
        </div>

        <div class="warga-card">
            <h3>Ditolak</h3>
            <p><?php echo $total_ditolak; ?></p>
        </div>

    </div>

    <!-- TOTAL PENARIKAN -->

    <div class="tabungan-saldo-card">

        <span>Total Penarikan Disetujui</span>

        <h2>
            Rp <?php echo number_format(
                $total_nominal_disetujui,
                0,
                ',',
                '.'
            ); ?>
        </h2>

        <p>
            Total nominal dari seluruh pengajuan yang telah disetujui.
        </p>

    </div>

    <!-- TABEL RIWAYAT -->

    <section class="riwayat-penarikan-section">

        <div class="riwayat-penarikan-heading">

            <h2>Daftar Pengajuan Penarikan</h2>

            <a href="ajukan_penarikan.php" class="btn-ajukan-riwayat">
                + Ajukan Penarikan
            </a>

        </div>

        <div class="warga-table-container">

            <table class="warga-table">

                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal Pengajuan</th>
                        <th>Jumlah</th>
                        <th>Metode</th>
                        <th>Informasi Rekening</th>
                        <th>Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($data_penarikan) > 0) : ?>

                        <?php foreach ($data_penarikan as $index => $penarikan) : ?>

                            <tr class="<?php echo $penarikan_id === (int) $penarikan['id'] ? 'penarikan-highlight' : ''; ?>">

                                <td>
                                    <?php echo $index + 1; ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($penarikan['tanggal_pengajuan'])
                                        ? date(
                                            'd/m/Y H:i',
                                            strtotime($penarikan['tanggal_pengajuan'])
                                        )
                                        : '-';
                                    ?>
                                </td>

                                <td>
                                    <strong>
                                        Rp <?php echo number_format(
                                            (float) $penarikan['jumlah'],
                                            0,
                                            ',',
                                            '.'
                                        ); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php
                                    echo $penarikan['metode_penarikan'] == 'tunai'
                                        ? 'Tunai'
                                        : 'Transfer';
                                    ?>
                                </td>

                                <td>

                                    <?php if ($penarikan['metode_penarikan'] == 'transfer') : ?>

                                        <?php echo htmlspecialchars(
                                            $penarikan['nama_pemilik_rekening'] ?? '-'
                                        ); ?>

                                        <br>

                                        <small>
                                            <?php echo htmlspecialchars(
                                                $penarikan['nomor_rekening'] ?? '-'
                                            ); ?>
                                        </small>

                                    <?php else : ?>

                                        -

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if ($penarikan['status'] == 'menunggu') : ?>

                                        <span class="status-penarikan status-pending">
                                            Menunggu
                                        </span>

                                    <?php elseif ($penarikan['status'] == 'disetujui') : ?>

                                        <span class="status-penarikan status-approved">
                                            Disetujui
                                        </span>

                                    <?php elseif ($penarikan['status'] == 'ditolak') : ?>

                                        <span class="status-penarikan status-rejected">
                                            Ditolak
                                        </span>

                                    <?php else : ?>

                                        <span class="status-penarikan">
                                            <?php echo htmlspecialchars(
                                                $penarikan['status']
                                            ); ?>
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?php echo htmlspecialchars(
                                        $penarikan['catatan'] ?: '-'
                                    ); ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else : ?>

                        <tr>
                            <td colspan="7" style="text-align:center; padding:35px;">

                                <div style="font-size:30px; margin-bottom:8px;">
                                    📋
                                </div>

                                <strong>Belum Ada Pengajuan</strong>

                                <br>

                                <small>
                                    Riwayat pengajuan penarikan kamu akan muncul di sini.
                                </small>

                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

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

    // ===================================
    // HIGHLIGHT RIWAYAT PENARIKAN
    // ===================================

    const penarikanHighlight = document.querySelector(
        '.penarikan-highlight'
    );

    if (penarikanHighlight) {

        setTimeout(function () {

            penarikanHighlight.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });

        }, 300);

    }

});
</script>

</body>
</html>
