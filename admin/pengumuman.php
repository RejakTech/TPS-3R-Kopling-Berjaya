
<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';


/* ==============================
   AMBIL DATA PENGUMUMAN
============================== */

$sql = "
    SELECT
        id,
        judul,
        isi,
        gambar,
        penulis_id,
        status,
        tanggal_terbit,
        created_at
    FROM pengumuman
    ORDER BY created_at DESC
";

$query = mysqli_query($conn, $sql);

if (!$query) {
    die("Gagal mengambil data pengumuman: " . mysqli_error($conn));
}

$data_pengumuman = [];

while ($row = mysqli_fetch_assoc($query)) {
    $data_pengumuman[] = $row;
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}


/* ==============================
   STATISTIK PENGUMUMAN
============================== */

$query_total = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM pengumuman"
);

$query_draft = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM pengumuman WHERE status = 'draft'"
);

$query_terbit = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM pengumuman WHERE status = 'terbit'"
);

$total_pengumuman = $query_total
    ? (int) mysqli_fetch_assoc($query_total)['total']
    : 0;

$total_draft = $query_draft
    ? (int) mysqli_fetch_assoc($query_draft)['total']
    : 0;

$total_terbit = $query_terbit
    ? (int) mysqli_fetch_assoc($query_terbit)['total']
    : 0;

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kelola Pengumuman - Bank Sampah Digital</title>

    <!-- CSS UTAMA ADMIN -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- FONT AWESOME -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>

/* =====================================
   AREA KONTEN
   MENGIKUTI HALAMAN DATA WARGA
===================================== */

.admin-main {
    margin-left: var(--sidebar-width, 250px);
    min-height: 100vh;
    background: #f4f9f8;
    box-sizing: border-box;
}

.admin-content {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 42px 50px;
    box-sizing: border-box;
}

.pengumuman-page {
    width: 100%;
    max-width: none;
    margin: 0;
    box-sizing: border-box;
}


/* =====================================
   HEADER
===================================== */

.pengumuman-header {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 25px;
    width: 100%;
    margin-bottom: 28px;
}

.pengumuman-header-left {
    min-width: 0;
}

.pengumuman-header-left h1 {
    margin: 0 0 8px;
    color: #0B4650;
    font-family: inherit;
    font-size: 30px;
    font-weight: 700;
    line-height: 1.3;
}

.pengumuman-header-left p {
    margin: 0;
    color: #718995;
    font-size: 14px;
    line-height: 1.6;
}

.pengumuman-header-action {
    display: flex;
    justify-content: flex-end;
    align-items: flex-start;
    gap: 10px;
    flex-wrap: wrap;
}


/* =====================================
   TOMBOL KEMBALI
===================================== */

.btn-kembali-pengumuman {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 18px;
    border-radius: 9px;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #FFFFFF !important;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none !important;
    white-space: nowrap;
    box-shadow: 0 3px 9px rgba(21, 155, 174, 0.15);
    transition: .25s ease;
}

.btn-kembali-pengumuman:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    color: #FFFFFF !important;
    transform: translateY(-2px);
}


/* =====================================
   TOMBOL TAMBAH
===================================== */

.btn-tambah-pengumuman {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 18px;
    border-radius: 9px;
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    color: #FFFFFF !important;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none !important;
    white-space: nowrap;
    box-shadow: 0 3px 9px rgba(21, 155, 174, 0.15);
    transition: .25s ease;
}

.btn-tambah-pengumuman:hover {
    background: linear-gradient(110deg, #082B40, #109EAF);
    color: #FFFFFF !important;
    transform: translateY(-2px);
}


/* =====================================
   STATISTIK
===================================== */

.pengumuman-statistics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    width: 100%;
    margin: 25px 0 30px;
}

.pengumuman-stat-card {
    position: relative;
    overflow: hidden;
    background: #FFFFFF;
    border: 1px solid #E2EDF0;
    border-radius: 15px;
    padding: 23px 25px;
    box-shadow: 0 4px 14px rgba(11, 53, 77, 0.045);
    transition: all .25s ease;
    box-sizing: border-box;
}

.pengumuman-stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(180deg, #159BAE, #17B8C8);
}

.pengumuman-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 9px 22px rgba(11, 53, 77, 0.09);
}

.pengumuman-stat-card h3 {
    color: #718995;
    font-size: 13px;
    font-weight: 700;
    margin: 0 0 13px;
}

.pengumuman-stat-card p {
    color: #0B354D;
    font-size: 26px;
    font-weight: 700;
    line-height: 1.2;
    margin: 0;
}

.pengumuman-stat-card:nth-child(2)::before {
    background: linear-gradient(180deg, #0D9488, #34BFA8);
}

.pengumuman-stat-card:nth-child(3)::before {
    background: linear-gradient(180deg, #E49A0F, #F5BD48);
}

.pengumuman-stat-card:nth-child(2) p {
    color: #0D9488;
}

.pengumuman-stat-card:nth-child(3) p {
    color: #E49A0F;
}


/* =====================================
   INFORMASI
===================================== */

.pengumuman-info {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 18px 22px;
    margin-bottom: 25px;
    background: linear-gradient(110deg, #E7F3F7, #F0FAFA);
    border: 1px solid #DCEBED;
    border-radius: 13px;
    box-sizing: border-box;
}

.pengumuman-info-icon {
    width: 43px;
    height: 43px;
    min-width: 43px;
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 12px;
    background: linear-gradient(135deg, #0B354D, #17B8C8);
    color: #FFFFFF;
    font-size: 18px;
}

.pengumuman-info strong {
    display: block;
    color: #0B4650;
    font-size: 13px;
    margin-bottom: 4px;
}

.pengumuman-info p {
    color: #718995;
    font-size: 12px;
    line-height: 1.6;
    margin: 0;
}


/* =====================================
   TABEL PENGUMUMAN
===================================== */

.pengumuman-table-container {
    width: 100%;
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    overflow-x: auto;
    box-shadow: 0 4px 15px rgba(11, 45, 70, .035);
    box-sizing: border-box;
}

.pengumuman-table {
    width: 100%;
    min-width: 750px;
    border-collapse: collapse;
    font-size: 13px;
}

.pengumuman-table th {
    background: #EAF3F6;
    color: #315A69;
    font-size: 12px;
    font-weight: 700;
    text-align: left;
    padding: 15px 16px;
    border-bottom: 1px solid #DCE8EC;
    white-space: nowrap;
}

.pengumuman-table td {
    padding: 14px 16px;
    color: #526671;
    border-bottom: 1px solid #EDF2F0;
    vertical-align: middle;
}

.pengumuman-table tbody tr:hover {
    background: #F2F9FA;
}

.pengumuman-table tbody tr:last-child td {
    border-bottom: none;
}


/* =====================================
   GAMBAR
===================================== */

.pengumuman-gambar {
    width: 65px;
    height: 52px;
    object-fit: cover;
    border-radius: 8px;
    background: #EAF3F6;
    display: block;
}

.pengumuman-no-gambar {
    width: 65px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: #EAF3F6;
    color: #159BAE;
    font-size: 19px;
}

.pengumuman-judul {
    color: #0B354D;
    font-weight: 700;
    max-width: 260px;
}


/* =====================================
   STATUS
===================================== */

.pengumuman-status {
    display: inline-flex;
    align-items: center;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.pengumuman-status-terbit {
    background: #E2F5F1;
    color: #087C72;
}

.pengumuman-status-draft {
    background: #FFF3E5;
    color: #B96B17;
}


/* =====================================
   TOMBOL AKSI
===================================== */

.pengumuman-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    margin: 2px;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: .2s ease;
}

.pengumuman-action-edit {
    background: linear-gradient(110deg, #0B354D, #159BAE);
    color: #FFFFFF;
}

.pengumuman-action-edit:hover {
    background: linear-gradient(110deg, #082B40, #108B9D);
    transform: translateY(-2px);
}

.pengumuman-action-delete {
    background: #FCEBEC;
    color: #B42318;
}

.pengumuman-action-delete:hover {
    background: #F5D6D8;
    transform: translateY(-2px);
}


/* =====================================
   DATA KOSONG
===================================== */

.pengumuman-empty {
    text-align: center;
    padding: 45px 20px !important;
    color: #718995 !important;
}

.pengumuman-empty i {
    display: block;
    font-size: 35px;
    color: #159BAE;
    margin-bottom: 12px;
}

.pengumuman-empty strong {
    display: block;
    color: #0B354D;
    margin-bottom: 5px;
}

.pengumuman-empty p {
    font-size: 12px;
    margin: 0;
}


/* =====================================
   RESPONSIVE
===================================== */

@media (max-width: 900px) {

    .admin-content {
        padding: 30px;
    }

    .pengumuman-statistics {
        gap: 14px;
    }

    .pengumuman-stat-card {
        padding: 19px;
    }

}

@media (max-width: 600px) {

    .admin-content {
        padding: 24px 18px;
    }

    .pengumuman-header {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .pengumuman-header-left h1 {
        font-size: 25px;
    }

    .pengumuman-header-action {
        justify-content: flex-start;
    }

    .pengumuman-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .pengumuman-stat-card {
        padding: 16px;
    }

    .pengumuman-stat-card h3 {
        font-size: 11px;
    }

    .pengumuman-stat-card p {
        font-size: 23px;
    }

    .pengumuman-info {
        align-items: flex-start;
        padding: 15px;
    }

    .pengumuman-info p {
        font-size: 11px;
    }

}

/* =====================================
   RESPONSIVE SIDEBAR ADMIN
===================================== */

.mobile-menu-button {
    display: none !important;
}

/* MOBILE DAN TABLET */
@media screen and (max-width: 800px) {

    body {
        overflow-x: hidden;
    }

    /* SIDEBAR */
    .admin-sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 250px !important;
        height: 100vh !important;
        z-index: 1001 !important;
        transform: translateX(-100%) !important;
        transition: transform .3s ease !important;
        overflow-y: auto !important;
    }

    .admin-sidebar.open {
        transform: translateX(0) !important;
    }

    /* OVERLAY */
    .sidebar-overlay {
        display: none !important;
    }

    .sidebar-overlay.show {
        display: block !important;
        position: fixed !important;
        inset: 0 !important;
        background: rgba(4, 25, 39, .55) !important;
        z-index: 1000 !important;
    }

    /* KONTEN */
    .admin-main {
        margin-left: 0 !important;
        width: 100% !important;
        min-height: 100vh !important;
    }

    .admin-content {
        width: 100% !important;
        max-width: 100% !important;
        padding: 22px 16px !important;
        box-sizing: border-box !important;
    }

    .pengumuman-page {
        width: 100% !important;
    }

    /* HEADER */
    .pengumuman-header {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 15px !important;
        margin-bottom: 22px !important;
    }

    .pengumuman-header-left {
        width: 100% !important;
        min-width: 0 !important;
    }

    .pengumuman-title-line {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 10px !important;
        width: 100% !important;
    }

    .pengumuman-header-left h1 {
        font-size: 23px !important;
        line-height: 1.35 !important;
        margin: 0 !important;
    }

    .pengumuman-header-left p {
        font-size: 12px !important;
        margin-top: 8px !important;
    }

    /* HAMBURGER */
    .mobile-menu-button {
        display: inline-flex !important;
        position: static !important;
        flex: 0 0 42px !important;
        width: 42px !important;
        height: 42px !important;
        align-items: center !important;
        justify-content: center !important;
        border: none !important;
        border-radius: 10px !important;
        background: linear-gradient(
            110deg,
            #0B354D,
            #17B8C8
        ) !important;
        color: #fff !important;
        font-size: 23px !important;
        cursor: pointer !important;
        box-shadow: 0 4px 12px rgba(11, 53, 77, .18);
    }

    /* TOMBOL HEADER */
    .pengumuman-header-action {
        width: 100% !important;
        display: flex !important;
        justify-content: flex-start !important;
        flex-wrap: wrap !important;
        gap: 9px !important;
    }

    .pengumuman-header-action a {
        flex: 1 1 auto;
        min-width: 0;
        text-align: center;
    }

    /* STATISTIK */
    .pengumuman-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 12px !important;
        width: 100% !important;
    }

    .pengumuman-stat-card {
        min-width: 0 !important;
        padding: 16px !important;
    }

    .pengumuman-stat-card h3 {
        font-size: 11px !important;
    }

    .pengumuman-stat-card p {
        font-size: 22px !important;
    }

    /* INFORMASI */
    .pengumuman-info {
        width: 100% !important;
        padding: 15px !important;
        gap: 11px !important;
    }

    .pengumuman-info-icon {
        width: 38px !important;
        height: 38px !important;
        min-width: 38px !important;
        font-size: 16px !important;
    }

    /* TABEL */
    .pengumuman-table-container {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }

    .pengumuman-table {
        min-width: 750px !important;
    }
}

/* HP KECIL */
@media screen and (max-width: 380px) {

    .admin-content {
        padding: 18px 12px !important;
    }

    .pengumuman-header-left h1 {
        font-size: 20px !important;
    }

    .pengumuman-stat-card {
        padding: 13px 10px !important;
    }

    .pengumuman-stat-card h3 {
        font-size: 10px !important;
    }

    .pengumuman-stat-card p {
        font-size: 19px !important;
    }

    .pengumuman-header-action {
        flex-direction: column !important;
    }

    .pengumuman-header-action a {
        width: 100% !important;
    }
}

/* LAPTOP */
@media screen and (min-width: 801px) {

    .mobile-menu-button {
        display: none !important;
    }

    .sidebar-overlay {
        display: none !important;
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

<!-- OVERLAY SIDEBAR MOBILE -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
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

        <a href="kelola_admin.php" class="sidebar-link">
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

        <a href="pengumuman.php" class="sidebar-link active">
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


<!-- =====================================
     AREA KONTEN KANAN
===================================== -->

<main class="admin-main">

    <div class="admin-content">

        <div class="pengumuman-page">


            <!-- HEADER -->

            <div class="pengumuman-header">

<div class="pengumuman-header-left">

    <div class="pengumuman-title-line">

        <h1>
            <i class="fa-solid fa-bullhorn"></i>
            Kelola Pengumuman
        </h1>

        <button
            type="button"
            class="mobile-menu-button"
            id="mobileMenuButton"
            aria-label="Buka menu"
        >
            ☰
        </button>

    </div>

    <p>
        Kelola informasi dan pengumuman Bank Sampah
        TPS 3R Kopling Berjaya.
    </p>

</div>


                <div class="pengumuman-header-action">

                    <a href="dashboard.php" class="btn-kembali-pengumuman">
                        <i class="fa-solid fa-arrow-left"></i>
                        &nbsp; Kembali ke Dashboard
                    </a>

                    <a href="tambah_pengumuman.php" class="btn-tambah-pengumuman">
                        <i class="fa-solid fa-plus"></i>
                        Tambah Pengumuman
                    </a>

                </div>

            </div>


            <!-- STATISTIK -->

            <div class="pengumuman-statistics">

                <div class="pengumuman-stat-card">

                    <h3>Total Pengumuman</h3>

                    <p>
                        <?= number_format($total_pengumuman, 0, ',', '.') ?>
                    </p>

                </div>


                <div class="pengumuman-stat-card">

                    <h3>Pengumuman Terbit</h3>

                    <p>
                        <?= number_format($total_terbit, 0, ',', '.') ?>
                    </p>

                </div>


                <div class="pengumuman-stat-card">

                    <h3>Pengumuman Draft</h3>

                    <p>
                        <?= number_format($total_draft, 0, ',', '.') ?>
                    </p>

                </div>

            </div>


            <!-- INFORMASI -->

            <div class="pengumuman-info">

                <div class="pengumuman-info-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </div>

                <div>

                    <strong>Informasi Pengumuman</strong>

                    <p>
                        Pengumuman berstatus Terbit dapat ditampilkan
                        kepada pengunjung website. Pengumuman Draft
                        hanya tersimpan di halaman admin.
                    </p>

                </div>

            </div>


            <!-- TABEL -->

            <div class="pengumuman-table-container">

                <table class="pengumuman-table">

                    <thead>

                        <tr>
                            <th>No</th>
                            <th>Gambar</th>
                            <th>Judul Pengumuman</th>
                            <th>Penulis ID</th>
                            <th>Status</th>
                            <th>Tanggal Dibuat</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($data_pengumuman)): ?>

                        <tr>

                            <td colspan="7" class="pengumuman-empty">

                                <i class="fa-solid fa-bullhorn"></i>

                                <strong>Belum Ada Pengumuman</strong>

                                <p>
                                    Silakan tambahkan pengumuman baru
                                    melalui tombol Tambah Pengumuman.
                                </p>

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php $no = 1; ?>

                        <?php foreach ($data_pengumuman as $pengumuman): ?>

                            <tr>

                                <td>
                                    <?= $no++ ?>
                                </td>


                                <td>

                                    <?php if (!empty($pengumuman['gambar'])): ?>

                                        <img
                                            src="../uploads/pengumuman/<?= e(basename($pengumuman['gambar'])) ?>"
                                            alt="Gambar pengumuman"
                                            class="pengumuman-gambar"
                                        >

                                    <?php else: ?>

                                        <div class="pengumuman-no-gambar">
                                            <i class="fa-solid fa-image"></i>
                                        </div>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <div class="pengumuman-judul">
                                        <?= e($pengumuman['judul']) ?>
                                    </div>

                                </td>


                                <td>
                                    <?= (int) $pengumuman['penulis_id'] ?>
                                </td>


                                <td>

                                    <?php if ($pengumuman['status'] === 'terbit'): ?>

                                        <span class="pengumuman-status pengumuman-status-terbit">
                                            <i class="fa-solid fa-circle-check"></i>
                                            &nbsp; Terbit
                                        </span>

                                    <?php else: ?>

                                        <span class="pengumuman-status pengumuman-status-draft">
                                            <i class="fa-solid fa-file-lines"></i>
                                            &nbsp; Draft
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= !empty($pengumuman['created_at'])
                                        ? date('d-m-Y', strtotime($pengumuman['created_at']))
                                        : '-' ?>

                                </td>


                                <td>

                                    <div style="display:flex; align-items:center;">

                                        <a
                                            href="edit_pengumuman.php?id=<?= (int) $pengumuman['id'] ?>"
                                            class="pengumuman-action pengumuman-action-edit"
                                            title="Edit Pengumuman"
                                        >
                                            <i class="fa-solid fa-pen"></i>
                                        </a>

                                        
<form
    action="hapus_pengumuman.php"
    method="POST"
    onsubmit="return confirm('Yakin ingin menghapus pengumuman ini? Data dan gambarnya akan dihapus permanen.');"
    style="display:inline-block;"
>
    <input
        type="hidden"
        name="id"
        value="<?= (int) $pengumuman['id'] ?>"
    >

    <input
        type="hidden"
        name="csrf_token"
        value="<?= e($_SESSION['csrf_token']) ?>"
    >

    <button
        type="submit"
        class="pengumuman-action pengumuman-action-delete"
        title="Hapus Pengumuman"
    >
        <i class="fa-solid fa-trash"></i>
    </button>
</form>


                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $pengumuman['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_token']) ?>"
                                            >

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</main>

<!-- JAVASCRIPT HAMBURGER -->
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
