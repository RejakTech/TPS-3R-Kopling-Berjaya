<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../config/koneksi.php';


/*
|--------------------------------------------------------------------------
| Filter
|--------------------------------------------------------------------------
*/

$keyword = isset($_GET['keyword'])
    ? trim($_GET['keyword'])
    : '';

$jenis = isset($_GET['jenis'])
    ? trim($_GET['jenis'])
    : '';

$conditions = [];
$params = [];
$types = '';

if ($keyword !== '') {

    $conditions[] = "
        (
            u.nama_lengkap LIKE ?
            OR u.username LIKE ?
        )
    ";

    $search = '%' . $keyword . '%';

    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
}

if (in_array($jenis, ['kredit', 'debit'], true)) {

    $conditions[] = "m.jenis_transaksi = ?";

    $params[] = $jenis;
    $types .= 's';
}

$where_sql = '';

if (!empty($conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $conditions);
}

$sql_mutasi = "
    SELECT
        m.id,
        m.tabungan_id,
        m.setoran_id,
        m.jenis_transaksi,
        m.jumlah,
        m.keterangan,
        m.tanggal_transaksi,
        t.user_id,
        u.nama_lengkap,
        u.username
    FROM mutasi_tabungan m
    INNER JOIN tabungan_warga t
        ON m.tabungan_id = t.id
    INNER JOIN users u
        ON t.user_id = u.id
    $where_sql
    ORDER BY m.id DESC
";

$query_mutasi = mysqli_prepare($conn, $sql_mutasi);

if (!$query_mutasi) {
    die("Gagal menyiapkan query mutasi.");
}

if (!empty($params)) {
    mysqli_stmt_bind_param(
        $query_mutasi,
        $types,
        ...$params
    );
}

if (!mysqli_stmt_execute($query_mutasi)) {
    die("Gagal mengambil data mutasi.");
}

$result_mutasi = mysqli_stmt_get_result($query_mutasi);

if (!$result_mutasi) {
    mysqli_stmt_close($query_mutasi);
    die("Gagal mengambil hasil data mutasi.");
}

$data_mutasi = [];

while ($row = mysqli_fetch_assoc($result_mutasi)) {
    $data_mutasi[] = $row;
}

mysqli_stmt_close($query_mutasi);


/*
|--------------------------------------------------------------------------
| Statistik
|--------------------------------------------------------------------------
*/

$query_statistik = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total_transaksi,
        COALESCE(
            SUM(
                CASE
                    WHEN jenis_transaksi = 'kredit'
                    THEN jumlah
                    ELSE 0
                END
            ),
            0
        ) AS total_kredit,
        COALESCE(
            SUM(
                CASE
                    WHEN jenis_transaksi = 'debit'
                    THEN jumlah
                    ELSE 0
                END
            ),
            0
        ) AS total_debit
     FROM mutasi_tabungan"
);

if (!$query_statistik) {
    die("Gagal mengambil statistik mutasi.");
}

$statistik = mysqli_fetch_assoc($query_statistik);

if (!$statistik) {
    $statistik = [
        'total_transaksi' => 0,
        'total_kredit' => 0,
        'total_debit' => 0
    ];
}

$total_transaksi = (int) $statistik['total_transaksi'];
$total_kredit = (float) $statistik['total_kredit'];
$total_debit = (float) $statistik['total_debit'];
/*
|--------------------------------------------------------------------------
| Data Mutasi
|--------------------------------------------------------------------------
*/


?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mutasi Tabungan - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

<style>
/* =========================================
   MUTASI TABUNGAN
   MODERN NAVY - TEAL - CYAN
   SIDEBAR TIDAK DIUBAH
========================================= */

/* AREA KONTEN */
.admin-main {
    margin-left: var(--sidebar-width, 250px);
    width: calc(100% - var(--sidebar-width, 250px));
    min-height: 100vh;
    background: #f4f9f8;
    box-sizing: border-box;
}

.admin-content {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 38px 42px;
    box-sizing: border-box;
}

/* HALAMAN */
.mutasi-page {
    width: 100%;
    max-width: none;
    margin: 0;
    box-sizing: border-box;
}

/* HEADER */
.mutasi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 28px;
}

.mutasi-header-left {
    min-width: 0;
}

.mutasi-header-left h1 {
    margin: 0 0 7px;
    color: #0B4650;
    font-size: 29px;
    font-weight: 700;
    line-height: 1.3;
}

.mutasi-header-left p {
    margin: 0;
    color: #718995;
    font-size: 13px;
    line-height: 1.6;
}

/* TOMBOL KEMBALI */
.btn-kembali-mutasi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 40px;
    padding: 10px 18px;
    border: 1px solid transparent;
    border-radius: 9px;
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    color: #FFFFFF;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(11, 53, 77, .13);
    transition: all .2s ease;
}

.btn-kembali-mutasi:hover {
    background: linear-gradient(110deg, #082B40, #109EAF);
    color: #FFFFFF;
    transform: translateY(-2px);
}

/* STATISTIK */
.mutasi-statistics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    width: 100%;
    margin-bottom: 27px;
}

.mutasi-stat-card {
    position: relative;
    overflow: hidden;
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    padding: 23px 24px;
    box-shadow: 0 4px 15px rgba(11, 45, 70, .045);
    transition: all .2s ease;
}

.mutasi-stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(180deg, #159BAE, #17B8C8);
}

.mutasi-stat-card.kredit::before {
    background: linear-gradient(180deg, #0D9488, #34BFA8);
}

.mutasi-stat-card.debit::before {
    background: linear-gradient(180deg, #E49A0F, #F5BD48);
}

.mutasi-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(11, 45, 70, .08);
    border-color: #C9E1E6;
}

.mutasi-stat-card span {
    display: block;
    color: #718995;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 12px;
}

.mutasi-stat-card strong {
    display: block;
    color: #159BAE;
    font-size: 27px;
    font-weight: 700;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.mutasi-stat-card.kredit strong {
    color: #0D9488;
}

.mutasi-stat-card.debit strong {
    color: #D58B0A;
}

/* TOOLBAR */
.mutasi-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 17px;
}

.mutasi-search-form {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    max-width: 480px;
}

.mutasi-search-form input {
    flex: 1;
    min-width: 0;
    width: 100%;
    padding: 11px 14px;
    border: 1px solid #DCE7EC;
    border-radius: 9px;
    background: #FFFFFF;
    color: #344D59;
    font-family: inherit;
    font-size: 12px;
    outline: none;
    box-sizing: border-box;
    transition: .2s ease;
}

.mutasi-search-form input:focus {
    border-color: #17AEBE;
    box-shadow: 0 0 0 3px rgba(23, 184, 200, .12);
}

.btn-cari-mutasi {
    min-height: 40px;
    padding: 0 20px;
    border: none;
    border-radius: 9px;
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    color: #FFFFFF;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: .2s ease;
}

.btn-cari-mutasi:hover {
    background: linear-gradient(110deg, #082B40, #109EAF);
    transform: translateY(-1px);
}

/* FILTER */
.mutasi-filter {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-wrap: wrap;
}

.mutasi-filter a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 8px 14px;
    border: 1px solid #DCE7EC;
    border-radius: 8px;
    background: #FFFFFF;
    color: #718995;
    text-decoration: none;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    transition: .2s ease;
}

.mutasi-filter a:hover {
    background: #EAF5F7;
    border-color: #BBDDE3;
    color: #087987;
}

.mutasi-filter a.active {
    background: #E5F3F5;
    border-color: #BBDDE3;
    color: #087987;
}

/* TABEL */
.mutasi-table-container {
    width: 100%;
    overflow-x: auto;
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(11, 45, 70, .035);
    box-sizing: border-box;
    -webkit-overflow-scrolling: touch;
}

.mutasi-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
    font-size: 12px;
}

.mutasi-table th {
    padding: 15px 17px;
    background: #EAF3F6;
    color: #315A69;
    font-size: 11px;
    font-weight: 700;
    text-align: left;
    border-bottom: 1px solid #DCE8EC;
    white-space: nowrap;
}

.mutasi-table td {
    padding: 16px 17px;
    border-bottom: 1px solid #EDF2F3;
    color: #526671;
    font-size: 12px;
    vertical-align: middle;
}

.mutasi-table tbody tr {
    transition: background .2s ease;
}

.mutasi-table tbody tr:last-child td {
    border-bottom: none;
}

.mutasi-table tbody tr:hover {
    background: #F2F9FA;
}

/* IDENTITAS WARGA */
.mutasi-nama {
    color: #0B4650;
    font-size: 12px;
    font-weight: 700;
}

.mutasi-username {
    color: #91A3AA;
    font-size: 11px;
    margin-top: 4px;
}

/* JENIS TRANSAKSI */
.badge-mutasi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.badge-kredit {
    background: #E2F5F1;
    color: #087C72;
}

.badge-debit {
    background: #FCEBEC;
    color: #B42318;
}

/* JUMLAH TRANSAKSI */
.jumlah-kredit {
    color: #0D9488;
    font-weight: 700;
    white-space: nowrap;
}

.jumlah-debit {
    color: #C65B50;
    font-weight: 700;
    white-space: nowrap;
}

/* NOMOR SETORAN */
.nomor-setoran {
    color: #526C78;
    font-size: 11px;
    font-weight: 600;
}

/* DATA KOSONG */
.mutasi-empty {
    padding: 45px 20px !important;
    text-align: center;
    color: #81949C !important;
    font-size: 13px !important;
}

/* TABLET */
@media (max-width: 1000px) {
    .admin-content {
        padding: 30px 25px;
    }

    .mutasi-statistics {
        gap: 13px;
    }

    .mutasi-stat-card {
        padding: 20px;
    }

    .mutasi-stat-card strong {
        font-size: 23px;
    }

    .mutasi-toolbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .mutasi-search-form {
        max-width: none;
    }
}

/* MOBILE */
@media (max-width: 700px) {
    .admin-main {
        width: 100%;
        margin-left: 0;
    }

    .admin-content {
        padding: 22px 16px;
    }

    .mutasi-header {
        flex-direction: column;
        align-items: stretch;
        gap: 14px;
        margin-bottom: 22px;
    }

    .mutasi-header-left h1 {
        font-size: 24px;
    }

    .mutasi-header-left p {
        font-size: 12px;
        line-height: 1.5;
    }

    .btn-kembali-mutasi {
        width: 100%;
        min-height: 40px;
        box-sizing: border-box;
    }

    .mutasi-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 11px;
        margin-bottom: 22px;
    }

    .mutasi-stat-card {
        padding: 17px 15px;
        border-radius: 12px;
    }

    .mutasi-stat-card span {
        font-size: 10px;
        line-height: 1.5;
    }

    .mutasi-stat-card strong {
        font-size: 20px;
    }

    .mutasi-stat-card:last-child {
        grid-column: 1 / -1;
    }

    .mutasi-toolbar {
        gap: 12px;
        margin-bottom: 14px;
    }

    .mutasi-search-form {
        gap: 7px;
    }

    .mutasi-search-form input {
        font-size: 11px;
        padding: 10px;
    }

    .btn-cari-mutasi {
        padding: 0 15px;
        font-size: 11px;
    }

    .mutasi-filter {
        width: 100%;
        gap: 6px;
    }

    .mutasi-filter a {
        flex: 1;
        padding: 8px 7px;
        font-size: 9px;
    }

    .mutasi-table-container {
        border-radius: 11px;
    }
}

/* HP KECIL */
@media (max-width: 380px) {
    .admin-content {
        padding: 18px 12px;
    }

    .mutasi-statistics {
        gap: 8px;
    }

    .mutasi-stat-card {
        padding: 14px 12px;
    }

    .mutasi-stat-card strong {
        font-size: 18px;
    }
}
/* =========================================
   RESPONSIVE SIDEBAR DAN HAMBURGER
   MUTASI TABUNGAN
========================================= */

.mobile-menu-button {
    display: none !important;
}

/* TABLET DAN HP */
@media (max-width: 800px) {

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
        padding: 22px 16px !important;
        box-sizing: border-box !important;
    }

    .mutasi-page {
        width: 100% !important;
        max-width: 100% !important;
    }

    /* HEADER DAN HAMBURGER */
    .mutasi-header {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 13px !important;
        margin-bottom: 22px !important;
    }

    .mutasi-header-left {
        width: 100% !important;
    }

    .mutasi-title-line {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        width: 100% !important;
    }

    .mutasi-header-left h1 {
        font-size: 23px !important;
        margin: 0 !important;
    }

    .mutasi-header-left p {
        font-size: 12px !important;
        margin-top: 7px !important;
    }

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
        background: linear-gradient(110deg, #0B354D, #17B8C8) !important;
        color: #ffffff !important;
        font-size: 23px !important;
        cursor: pointer !important;
        box-shadow: 0 4px 12px rgba(11, 53, 77, .18);
    }

    .btn-kembali-mutasi {
        width: 100% !important;
        box-sizing: border-box !important;
    }

    /* STATISTIK */
    .mutasi-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 11px !important;
    }

    .mutasi-stat-card {
        padding: 16px 13px !important;
    }

    .mutasi-stat-card span {
        font-size: 10px !important;
    }

    .mutasi-stat-card strong {
        font-size: 19px !important;
    }

    .mutasi-stat-card:last-child {
        grid-column: 1 / -1;
    }

    /* PENCARIAN DAN FILTER */
    .mutasi-toolbar {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 12px !important;
    }

    .mutasi-search-form {
        width: 100% !important;
        max-width: none !important;
    }

    .mutasi-filter {
        width: 100% !important;
        display: flex !important;
        gap: 6px !important;
    }

    .mutasi-filter a {
        flex: 1 !important;
        padding: 9px 5px !important;
        font-size: 10px !important;
    }

    /* TABEL */
    .mutasi-table-container {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }

    .mutasi-table {
        min-width: 900px !important;
    }
}

/* HP KECIL */
@media (max-width: 380px) {

    .admin-content {
        padding: 18px 12px !important;
    }

    .mutasi-header-left h1 {
        font-size: 21px !important;
    }

    .mutasi-stat-card strong {
        font-size: 17px !important;
    }
}

/* DESKTOP */
@media (min-width: 801px) {

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

        <a href="kelola_admin.php" class="sidebar-link">
            <span class="sidebar-icon">🛡️</span>
            <span>Kelola Admin</span>
        </a>

        <!-- KATEGORI SAMPAH AKTIF -->
        <a href="kategori.php" class="sidebar-link link">
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

       <a href="mutasi_tabungan.php" class="sidebar-link active">
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
        <a href="pengumuman.php" class="sidebar-link ">
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
    <div class="mutasi-page">


        <!-- HEADER -->

        <div class="mutasi-header">

<div class="mutasi-header-left">

    <div class="mutasi-title-line">
        <h1>Mutasi Tabungan</h1>

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
        Riwayat perubahan saldo tabungan seluruh warga.
    </p>

</div>

            <a
                href="tabungan.php"
                class="btn-kembali-mutasi"
            >
                ← Kembali ke Tabungan
            </a>

        </div>


        <!-- STATISTICS -->

        <div class="mutasi-statistics">

            <div class="mutasi-stat-card">

                <span>Total Transaksi</span>

                <strong>
                    <?php echo number_format(
                        $total_transaksi,
                        0,
                        ',',
                        '.'
                    ); ?>
                </strong>

            </div>


            <div class="mutasi-stat-card kredit">

                <span>Total Saldo Masuk</span>

                <strong>
                    Rp <?php echo number_format(
                        $total_kredit,
                        0,
                        ',',
                        '.'
                    ); ?>
                </strong>

            </div>


            <div class="mutasi-stat-card debit">

                <span>Total Saldo Keluar</span>

                <strong>
                    Rp <?php echo number_format(
                        $total_debit,
                        0,
                        ',',
                        '.'
                    ); ?>
                </strong>

            </div>

        </div>


        <!-- TOOLBAR -->

        <div class="mutasi-toolbar">


            <!-- SEARCH -->

            <form
                method="GET"
                class="mutasi-search-form"
            >

                <input
                    type="text"
                    name="keyword"
                    value="<?php echo htmlspecialchars($keyword); ?>"
                    placeholder="Cari nama atau username warga..."
                >

                <?php if ($jenis !== ''): ?>

                    <input
                        type="hidden"
                        name="jenis"
                        value="<?php echo htmlspecialchars($jenis); ?>"
                    >

                <?php endif; ?>

                <button
                    type="submit"
                    class="btn-cari-mutasi"
                >
                    Cari
                </button>

            </form>


            <!-- FILTER -->

            <div class="mutasi-filter">

                <a
                    href="mutasi_tabungan.php"
                    class="<?php echo $jenis === '' ? 'active' : ''; ?>"
                >
                    Semua
                </a>

                <a
                    href="mutasi_tabungan.php?jenis=kredit<?php echo $keyword !== '' ? '&keyword=' . urlencode($keyword) : ''; ?>"
                    class="<?php echo $jenis === 'kredit' ? 'active' : ''; ?>"
                >
                    Saldo Masuk
                </a>

                <a
                    href="mutasi_tabungan.php?jenis=debit<?php echo $keyword !== '' ? '&keyword=' . urlencode($keyword) : ''; ?>"
                    class="<?php echo $jenis === 'debit' ? 'active' : ''; ?>"
                >
                    Saldo Keluar
                </a>

            </div>

        </div>


        <!-- TABLE -->

        <div class="mutasi-table-container">

            <table class="mutasi-table">

                <thead>

                    <tr>

                        <th>No</th>

                        <th>Warga</th>

                        <th>Jenis</th>

                        <th>Jumlah</th>

                        <th>Setoran</th>

                        <th>Keterangan</th>

                        <th>Tanggal</th>

                    </tr>

                </thead>


                <tbody>

                    <?php if (count($data_mutasi) > 0): ?>

                        <?php foreach ($data_mutasi as $index => $mutasi): ?>

                            <tr>

                                <td>
                                    <?php echo $index + 1; ?>
                                </td>


                                <td>

                                    <div class="mutasi-nama">
                                        <?php echo htmlspecialchars(
                                            $mutasi['nama_lengkap']
                                        ); ?>
                                    </div>

                                    <div class="mutasi-username">
                                        @<?php echo htmlspecialchars(
                                            $mutasi['username']
                                        ); ?>
                                    </div>

                                </td>


                                <td>

                                    <?php if ($mutasi['jenis_transaksi'] === 'kredit'): ?>

                                        <span class="badge-mutasi badge-kredit">
                                            ↑ Saldo Masuk
                                        </span>

                                    <?php else: ?>

                                        <span class="badge-mutasi badge-debit">
                                            ↓ Saldo Keluar
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php if ($mutasi['jenis_transaksi'] === 'kredit'): ?>

                                        <span class="jumlah-kredit">
                                            + Rp
                                            <?php echo number_format(
                                                (float) $mutasi['jumlah'],
                                                0,
                                                ',',
                                                '.'
                                            ); ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="jumlah-debit">
                                            - Rp
                                            <?php echo number_format(
                                                (float) $mutasi['jumlah'],
                                                0,
                                                ',',
                                                '.'
                                            ); ?>
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php if (!empty($mutasi['setoran_id'])): ?>

                                        <span class="nomor-setoran">
                                            #<?php echo (int) $mutasi['setoran_id']; ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="nomor-setoran">
                                            -
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $mutasi['keterangan'] ?? '-'
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    $tanggal_mutasi = strtotime($mutasi['tanggal_transaksi']);

                                    if ($tanggal_mutasi !== false) {
                                        echo date('d/m/Y H:i', $tanggal_mutasi);
                                    } else {
                                        echo '-';
                                    }
                                    ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="7"
                                class="mutasi-empty"
                            >

                                <?php if ($keyword !== '' || $jenis !== ''): ?>

                                    Tidak ditemukan riwayat mutasi
                                    sesuai filter yang dipilih.

                                <?php else: ?>

                                    Belum ada riwayat mutasi tabungan.

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const menuButton = document.getElementById('mobileMenuButton');
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (!menuButton || !sidebar || !overlay) return;

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