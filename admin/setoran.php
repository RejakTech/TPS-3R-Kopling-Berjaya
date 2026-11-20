
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

// =============================
// NOTIFIKASI
// =============================

$status_notifikasi = $_GET['status'] ?? '';

// =============================
// FILTER DAN PENCARIAN
// =============================

$cari = trim($_GET['cari'] ?? '');
$status_filter = $_GET['filter_status'] ?? '';
$tanggal_filter = $_GET['tanggal'] ?? '';

$status_valid = ['menunggu', 'diterima', 'ditolak'];

if (!in_array($status_filter, $status_valid, true)) {
    $status_filter = '';
}

// Validasi tanggal
if (
    $tanggal_filter !== '' &&
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_filter)
) {
    $tanggal_filter = '';
}

// =============================
// QUERY DATA SETORAN + PAGINATION
// =============================

// Jumlah data per halaman
$batas = 10;

// Halaman aktif
$halaman = isset($_GET['halaman']) ? (int) $_GET['halaman'] : 1;

if ($halaman < 1) {
    $halaman = 1;
}

// =============================
// KONDISI PENCARIAN DAN FILTER
// =============================

$from_where = " FROM setoran_sampah s
                INNER JOIN users u ON s.user_id = u.id
                INNER JOIN kategori_sampah k ON s.kategori_id = k.id
                WHERE 1=1";

$types = '';
$params = [];

// Filter nama warga
if ($cari !== '') {
    $from_where .= " AND u.nama_lengkap LIKE ?";
    $types .= 's';
    $params[] = '%' . $cari . '%';
}

// Filter status
if ($status_filter !== '') {
    $from_where .= " AND s.status = ?";
    $types .= 's';
    $params[] = $status_filter;
}

// Filter tanggal
if ($tanggal_filter !== '') {
    $from_where .= " AND DATE(s.tanggal_setor) = ?";
    $types .= 's';
    $params[] = $tanggal_filter;
}

// =============================
// HITUNG TOTAL DATA SESUAI FILTER
// =============================

$sql_count = "SELECT COUNT(*) AS total " . $from_where;

$stmt_count = mysqli_prepare($conn, $sql_count);

if (!$stmt_count) {
    die("Gagal menghitung data setoran.");
}

if ($types !== '') {
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
}

mysqli_stmt_execute($stmt_count);

$result_count = mysqli_stmt_get_result($stmt_count);
$data_count = mysqli_fetch_assoc($result_count);

$total_data = (int) $data_count['total'];

// =============================
// HITUNG TOTAL HALAMAN
// =============================

$total_halaman = (int) ceil($total_data / $batas);

if ($total_halaman > 0 && $halaman > $total_halaman) {
    $halaman = $total_halaman;
}

$offset = ($halaman - 1) * $batas;

// =============================
// AMBIL DATA SESUAI HALAMAN
// =============================

$sql = "SELECT
            s.*,
            u.nama_lengkap,
            k.nama_kategori"
        . $from_where .
        " ORDER BY s.id DESC
          LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Gagal menyiapkan query setoran.");
}

$types_data = $types . 'ii';
$params_data = array_merge($params, [$batas, $offset]);

mysqli_stmt_bind_param($stmt, $types_data, ...$params_data);

mysqli_stmt_execute($stmt);

$query = mysqli_stmt_get_result($stmt);

if (!$query) {
    die("Gagal mengambil data setoran.");
}

// =============================
// STATISTIK SELURUH SETORAN
// =============================

$query_statistik = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total_setoran,
        SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) AS jumlah_menunggu,
        SUM(CASE WHEN status = 'diterima' THEN 1 ELSE 0 END) AS jumlah_diterima,
        SUM(CASE WHEN status = 'ditolak' THEN 1 ELSE 0 END) AS jumlah_ditolak
     FROM setoran_sampah"
);

$statistik = mysqli_fetch_assoc($query_statistik);
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Setoran - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">
<style>/* =========================================
   HALAMAN DATA SETORAN
========================================= */

.admin-main .setoran-page {
    width: 100%;
    margin-bottom: 25px;
}

.admin-main .setoran-page .admin-heading {
    margin-bottom: 22px;
}

.admin-main .setoran-page .admin-heading h1 {
    color: #0B354D;
    font-size: 27px;
    font-weight: 700;
    margin-bottom: 7px;
    letter-spacing: -0.5px;
}

.admin-main .setoran-page .admin-heading p {
    color: #718995;
    font-size: 13px;
    margin: 0;
}

/* TOMBOL KEMBALI */

.admin-main .setoran-page > a[href="dashboard.php"] {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 5px 0 22px;
    padding: 11px 17px;
    border-radius: 9px;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(21, 155, 174, .15);
    transition: .25s ease;
}

.admin-main .setoran-page > a[href="dashboard.php"]:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    transform: translateY(-2px);
}

/* PANEL FILTER */

.admin-main .setoran-filter-container {
    background: #fff;
    border: 1px solid #e5eeeb;
    border-radius: 15px;
    padding: 23px;
    margin-bottom: 23px;
    box-shadow: 0 4px 18px rgba(11, 53, 77, .045);
}

.admin-main .setoran-filter-heading {
    margin-bottom: 20px;
}

.admin-main .setoran-filter-heading h2 {
    color: #0B354D;
    font-size: 17px;
    font-weight: 700;
    margin: 0 0 6px;
}

.admin-main .setoran-filter-heading p {
    color: #82949c;
    font-size: 12px;
    margin: 0;
}

.admin-main .setoran-filter-form {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    align-items: end;
}

.admin-main .setoran-filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.admin-main .setoran-filter-group label {
    color: #385563;
    font-size: 12px;
    font-weight: 600;
}

.admin-main .setoran-filter-group input,
.admin-main .setoran-filter-group select {
    width: 100%;
    height: 43px;
    padding: 0 13px;
    border: 1px solid #dce7e5;
    border-radius: 8px;
    outline: none;
    background: #fbfdfc;
    color: #294653;
    font-family: inherit;
    font-size: 12px;
    transition: .2s ease;
}

.admin-main .setoran-filter-group input:focus,
.admin-main .setoran-filter-group select:focus {
    border-color: #159BAE;
    box-shadow: 0 0 0 3px rgba(21, 155, 174, .10);
    background: #fff;
}

.admin-main .setoran-filter-actions {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 3px;
}

.admin-main .btn-filter-setoran,
.admin-main .btn-reset-filter {
    min-height: 39px;
    padding: 0 17px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: .2s ease;
}

.admin-main .btn-filter-setoran {
    border: none;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #fff;
}

.admin-main .btn-filter-setoran:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 12px rgba(21, 155, 174, .2);
}

.admin-main .btn-reset-filter {
    border: 1px solid #dce7e5;
    background: #f5f8f7;
    color: #58717c;
}

.admin-main .btn-reset-filter:hover {
    background: #eaf2f0;
    color: #0B354D;
}

/* KARTU STATISTIK */
/* STATISTIK SETORAN - MINIMALIST ELEGANT */

.admin-main .setoran-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin: 22px 0 25px;
}

.admin-main .setoran-summary-card {
    position: relative;
    overflow: hidden;
    padding: 23px 22px;
    min-height: 160px;
    background: #ffffff;
    border: 1px solid #e8efed;
    border-radius: 13px;
    box-shadow: 0 3px 14px rgba(11, 53, 77, 0.04);
    transition: all 0.25s ease;
}

.admin-main .setoran-summary-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--summary-color);
}

.admin-main .setoran-summary-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 22px rgba(11, 53, 77, 0.08);
}

.admin-main .summary-label {
    display: inline-block;
    margin-bottom: 17px;
    color: var(--summary-color);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
}

.admin-main .setoran-summary-card h3 {
    margin: 0 0 8px;
    color: #718995;
    font-size: 13px;
    font-weight: 500;
}

.admin-main .setoran-summary-card p {
    margin: 0;
    color: #0B354D;
    font-size: 34px;
    font-weight: 800;
    line-height: 1.2;
}

.admin-main .summary-description {
    display: block;
    margin-top: 12px;
    color: #9aa9ae;
    font-size: 11px;
}

/* WARNA SETIAP KARTU */

.admin-main .setoran-summary-card.total {
    --summary-color: #159BAE;
}

.admin-main .setoran-summary-card.menunggu {
    --summary-color: #D99518;
}

.admin-main .setoran-summary-card.diterima {
    --summary-color: #218653;
}

.admin-main .setoran-summary-card.ditolak {
    --summary-color: #D65353;
}

/* RESPONSIVE */

@media (max-width: 1100px) {
    .admin-main .setoran-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 480px) {
    .admin-main .setoran-summary {
        gap: 11px;
    }

    .admin-main .setoran-summary-card {
        min-height: 145px;
        padding: 17px 14px;
    }

    .admin-main .summary-label {
        font-size: 9px;
        letter-spacing: 0.5px;
    }

    .admin-main .setoran-summary-card h3 {
        font-size: 12px;
    }

    .admin-main .setoran-summary-card p {
        font-size: 28px;
    }

    .admin-main .summary-description {
        font-size: 10px;
    }
}
/* RESPONSIVE STATISTIK */

@media (max-width: 1100px) {
    .admin-main .setoran-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 480px) {
    .admin-main .setoran-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .admin-main .setoran-summary-card {
        min-height: 140px;
        padding: 14px;
    }

    .admin-main .summary-icon {
        width: 36px;
        height: 36px;
        font-size: 17px;
    }

    .admin-main .summary-label {
        font-size: 9px;
    }

    .admin-main .summary-card-bottom p {
        font-size: 25px;
    }

    .admin-main .summary-decoration {
        font-size: 25px;
    }
}


/* INFORMASI HASIL FILTER */

.admin-main .setoran-filter-result {
    color: #718995;
    background: #fff;
    border: 1px solid #e7efec;
    border-radius: 10px;
    padding: 13px 17px;
    margin: 0 0 15px;
    font-size: 12px;
}

.admin-main .setoran-filter-result strong {
    color: #159BAE;
    font-size: 14px;
}

/* PAGINATION */

.admin-main .setoran-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    background: #fff;
    border: 1px solid #e7efec;
    border-radius: 10px;
    padding: 13px 17px;
    margin-bottom: 16px;
}

.admin-main .pagination-info {
    color: #718995;
    font-size: 12px;
}

.admin-main .pagination-buttons {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.admin-main .pagination-link {
    min-width: 34px;
    height: 34px;
    padding: 0 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e0eae7;
    border-radius: 7px;
    color: #54707b;
    background: #fff;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
    transition: .2s ease;
}

.admin-main .pagination-link:hover,
.admin-main .pagination-link.active {
    background: linear-gradient(135deg, #0B354D, #159BAE);
    border-color: transparent;
    color: #fff;
}

/* TABEL DATA SETORAN */

.admin-main .warga-table-container {
    width: 100%;
    overflow-x: auto;
    background: #fff;
    border: 1px solid #e5eeeb;
    border-radius: 13px;
    box-shadow: 0 4px 18px rgba(11, 53, 77, .045);
}

.admin-main .warga-table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
    background: #fff;
}

.admin-main .warga-table thead th {
    padding: 16px 13px;
    background: #f0f7f5;
    color: #0B354D;
    border-bottom: 1px solid #e1ece8;
    font-size: 11px;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}

.admin-main .warga-table tbody td {
    padding: 15px 13px;
    color: #526d78;
    border-bottom: 1px solid #edf2f0;
    font-size: 11px;
    vertical-align: middle;
}

.admin-main .warga-table tbody tr {
    transition: background .2s ease;
}

.admin-main .warga-table tbody tr:hover {
    background: #f7fbfa;
}

.admin-main .warga-table tbody tr:last-child td {
    border-bottom: none;
}

/* TOMBOL DETAIL */

.admin-main .warga-table .btn-edit-petugas {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 15px;
    border-radius: 7px;
    background: #e8f5f6;
    color: #117f91;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
    transition: .2s ease;
}

.admin-main .warga-table .btn-edit-petugas:hover {
    background: #159BAE;
    color: #fff;
}

/* STATUS SETORAN */

.admin-main .warga-table .status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.admin-main .warga-table .status-menunggu {
    color: #a66b08;
    background: #fff4d9;
}

.admin-main .warga-table .status-diterima {
    color: #167746;
    background: #e3f7eb;
}

.admin-main .warga-table .status-ditolak {
    color: #bd4141;
    background: #fdeaea;
}

/* RESPONSIVE */

@media (max-width: 1100px) {
    .admin-main .setoran-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .admin-main .setoran-filter-form {
        grid-template-columns: 1fr;
    }

    .admin-main .setoran-filter-actions {
        grid-column: auto;
        justify-content: stretch;
    }

    .admin-main .setoran-filter-actions > * {
        flex: 1;
    }

    .admin-main .setoran-page .admin-heading h1 {
        font-size: 23px;
    }

    .admin-main .setoran-filter-container {
        padding: 17px;
    }

    .admin-main .setoran-pagination {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .admin-main .setoran-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .admin-main .setoran-summary-card {
        padding: 15px;
        min-height: 90px;
    }

    .admin-main .setoran-summary-card p {
        font-size: 23px;
    }

    .admin-main .setoran-summary-card h3 {
        font-size: 11px;
    }

    .admin-main .setoran-filter-actions {
        flex-direction: column;
    }
}
/* JUDUL DAN TOMBOL KEMBALI SEJAJAR */

.setoran-heading-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 22px;
}

.setoran-heading-row .admin-heading {
    margin-bottom: 0 !important;
}

.setoran-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    flex-shrink: 0;
    padding: 12px 19px;
    border-radius: 9px;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #ffffff !important;
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(21, 155, 174, .16);
    transition: all .25s ease;
}

.setoran-back:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    color: #ffffff !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(21, 155, 174, .23);
}

@media (max-width: 600px) {
    .setoran-heading-row {
        align-items: flex-start;
        flex-direction: column;
    }
}
/* =====================================
   HAMBURGER DAN SIDEBAR RESPONSIVE
===================================== */

.mobile-menu-button {
    display: none !important;
}

/* TAMPILAN HP DAN TABLET */
@media (max-width: 800px) {

    body {
        overflow-x: hidden;
    }

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

    .admin-main {
        margin-left: 0 !important;
        width: 100% !important;
        min-height: 100vh;
    }

    .admin-content {
        width: 100% !important;
        padding: 20px 18px 25px !important;
        margin: 0 !important;
    }

    /* JUDUL DAN HAMBURGER */
    .setoran-heading-row {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 12px !important;
        margin-bottom: 20px !important;
    }

    .setoran-heading-row .admin-heading {
        width: 100%;
        margin-bottom: 0 !important;
    }

    .setoran-title-line {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        width: 100%;
    }

    .setoran-title-line h1 {
        font-size: 23px !important;
        margin: 0 !important;
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
        color: #fff !important;
        font-size: 23px !important;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(11, 53, 77, .18);
    }

    .setoran-back {
        align-self: flex-start !important;
    }

    /* FILTER */
    .setoran-filter-form {
        grid-template-columns: 1fr !important;
    }

    .setoran-filter-actions {
        grid-column: auto !important;
        flex-direction: column !important;
    }

    .setoran-filter-actions > * {
        width: 100%;
    }

    /* STATISTIK */
    .setoran-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 10px !important;
    }

    .setoran-summary-card {
        min-width: 0;
        padding: 15px !important;
        min-height: 130px !important;
    }

    .setoran-summary-card p {
        font-size: 25px !important;
    }

    /* TABEL */
    .warga-table-container {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }

    .warga-table {
        min-width: 1050px !important;
    }

    /* PAGINATION */
    .setoran-pagination {
        flex-direction: column !important;
        align-items: flex-start !important;
    }

    .pagination-buttons {
        max-width: 100%;
    }
}

/* TAMPILAN LAPTOP */
@media (min-width: 801px) {

    .mobile-menu-button {
        display: none !important;
    }

    .sidebar-overlay {
        display: none !important;
    }

    .admin-main {
    margin-left: 250px !important;
    width: calc(100% - 250px) !important;
    min-height: 100vh;
}

.admin-content {
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 38px 35px !important;
    box-sizing: border-box !important;
}

    .setoran-heading-row {
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
    }

    .setoran-title-line {
        display: block;
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

        <a href="kategori.php" class="sidebar-link">
            <span class="sidebar-icon">♻️</span>
            <span>Kategori Sampah</span>
        </a>

        <!-- INI YANG AKTIF -->
        <a href="setoran.php" class="sidebar-link active">
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

        <div class="setoran-page">

<div class="setoran-heading-row">

    <div class="admin-heading">

        <div class="setoran-title-line">
            <h1>Data Setoran Sampah</h1>

            <button
                type="button"
                class="mobile-menu-button"
                id="mobileMenuButton"
                aria-label="Buka menu"
            >
                ☰
            </button>
        </div>

        <p>Kelola dan pantau pengajuan setoran sampah dari warga.</p>

    </div>

    <a href="dashboard.php" class="setoran-back">
        ← Kembali ke Dashboard
    </a>

</div>


<!-- NOTIFIKASI VERIFIKASI SETORAN -->

<?php
$status_notifikasi = $_GET['status'] ?? '';
?>

<?php if ($status_notifikasi === 'diterima') : ?>

    <div class="form-alert-success">
        Setoran berhasil diterima. Saldo warga telah diperbarui.
    </div>

<?php elseif ($status_notifikasi === 'ditolak') : ?>

    <div class="form-alert-success">
        Setoran berhasil ditolak. Saldo warga tidak berubah.
    </div>

<?php elseif ($status_notifikasi === 'verifikasi_gagal') : ?>

    <div class="form-alert-error">
        Verifikasi gagal. Setoran mungkin sudah diproses atau terjadi kesalahan.
    </div>

<?php elseif ($status_notifikasi === 'token_gagal') : ?>

    <div class="form-alert-error">
        Permintaan tidak valid. Silakan muat ulang halaman.
    </div>

<?php elseif ($status_notifikasi === 'alasan_gagal') : ?>

    <div class="form-alert-error">
        Alasan penolakan wajib diisi dan maksimal 1.000 karakter.
    </div>

<?php endif; ?>


    
<!-- PANEL PENCARIAN DAN FILTER -->

<div class="setoran-filter-container">

    <div class="setoran-filter-heading">
        <h2>Filter Data Setoran</h2>
        <p>Cari dan temukan pengajuan setoran dengan mudah.</p>
    </div>

    <form method="GET" action="setoran.php" class="setoran-filter-form">

        <div class="setoran-filter-group">

            <label for="cari">Nama Warga</label>

            <input
                type="text"
                name="cari"
                id="cari"
                placeholder="Cari nama warga..."
                value="<?php echo htmlspecialchars($cari); ?>"
            >

        </div>

        <div class="setoran-filter-group">

            <label for="filter_status">Status Setoran</label>

            <select name="filter_status" id="filter_status">

                <option value="">Semua Status</option>

                <option value="menunggu"
                    <?php echo $status_filter === 'menunggu' ? 'selected' : ''; ?>>
                    Menunggu
                </option>

                <option value="diterima"
                    <?php echo $status_filter === 'diterima' ? 'selected' : ''; ?>>
                    Diterima
                </option>

                <option value="ditolak"
                    <?php echo $status_filter === 'ditolak' ? 'selected' : ''; ?>>
                    Ditolak
                </option>

            </select>

        </div>

        <div class="setoran-filter-group">

            <label for="tanggal">Tanggal Setoran</label>

            <input
                type="date"
                name="tanggal"
                id="tanggal"
                value="<?php echo htmlspecialchars($tanggal_filter); ?>"
            >

        </div>

        <div class="setoran-filter-actions">

            <button type="submit" class="btn-filter-setoran">
                <span>⌕</span> Terapkan Filter
            </button>

            <a href="setoran.php" class="btn-reset-filter">
                Reset
            </a>

        </div>

    </form>

</div>



   
<div class="setoran-summary">

    <div class="setoran-summary-card total">
        <span class="summary-label">KESELURUHAN</span>
        <h3>Total Setoran</h3>
        <p><?php echo (int)$statistik['total_setoran']; ?></p>
        <span class="summary-description">Seluruh data setoran</span>
    </div>

    <div class="setoran-summary-card menunggu">
        <span class="summary-label">PERLU DIPROSES</span>
        <h3>Menunggu</h3>
        <p><?php echo (int)$statistik['jumlah_menunggu']; ?></p>
        <span class="summary-description">Menunggu verifikasi</span>
    </div>

    <div class="setoran-summary-card diterima">
        <span class="summary-label">TERVERIFIKASI</span>
        <h3>Diterima</h3>
        <p><?php echo (int)$statistik['jumlah_diterima']; ?></p>
        <span class="summary-description">Setoran berhasil diterima</span>
    </div>

    <div class="setoran-summary-card ditolak">
        <span class="summary-label">TIDAK DITERIMA</span>
        <h3>Ditolak</h3>
        <p><?php echo (int)$statistik['jumlah_ditolak']; ?></p>
        <span class="summary-description">Setoran tidak diterima</span>
    </div>

</div>



    </div>

    <div class="setoran-filter-result">
    Menampilkan <strong><?php echo $total_data; ?></strong>
    data setoran
    <?php if ($cari !== '' || $status_filter !== '' || $tanggal_filter !== '') : ?>
        sesuai filter yang dipilih.
    <?php else : ?>
        dari seluruh pengajuan.
    <?php endif; ?>
</div>


<!-- PAGINATION -->

<?php if ($total_halaman > 1) : ?>

    <div class="setoran-pagination">

        <div class="pagination-info">
            Halaman <?php echo $halaman; ?>
            dari <?php echo $total_halaman; ?>
        </div>

        <div class="pagination-buttons">

            <?php
            // Simpan filter agar tidak hilang saat pindah halaman
            $parameter_filter = [
                'cari' => $cari,
                'filter_status' => $status_filter,
                'tanggal' => $tanggal_filter
            ];
            ?>

            <?php if ($halaman > 1) : ?>

                <a
                    href="?<?php echo htmlspecialchars(http_build_query(array_merge($parameter_filter, ['halaman' => $halaman - 1]))); ?>"
                    class="pagination-link"
                >
                    &laquo; Sebelumnya
                </a>

            <?php endif; ?>

            <?php
            $awal = max(1, $halaman - 2);
            $akhir = min($total_halaman, $halaman + 2);
            ?>

            <?php for ($i = $awal; $i <= $akhir; $i++) : ?>

                <a
                    href="?<?php echo htmlspecialchars(http_build_query(array_merge($parameter_filter, ['halaman' => $i]))); ?>"
                    class="pagination-link <?php echo $i === $halaman ? 'active' : ''; ?>"
                >
                    <?php echo $i; ?>
                </a>

            <?php endfor; ?>

            <?php if ($halaman < $total_halaman) : ?>

                <a
                    href="?<?php echo htmlspecialchars(http_build_query(array_merge($parameter_filter, ['halaman' => $halaman + 1]))); ?>"
                    class="pagination-link"
                >
                    Berikutnya &raquo;
                </a>

            <?php endif; ?>

        </div>

    </div>

<?php endif; ?>



    <div class="warga-table-container">

        <table class="warga-table">

            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Warga</th>
                    <th>Kategori Sampah</th>
                    <th>Tanggal Setor</th>
                    <th>Berat</th>
                    <th>Harga/Kg</th>
                    <th>Total Harga</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

                <?php
                $no = 1;

                if (mysqli_num_rows($query) > 0) :

                    while ($setoran = mysqli_fetch_assoc($query)) :
                ?>

                <tr>

                    <td><?php echo $no++; ?></td>

                    <td>
                        <?php echo htmlspecialchars($setoran['nama_lengkap']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($setoran['nama_kategori']); ?>
                    </td>

                    <td>
                        <?php
                        echo date(
                            'd-m-Y',
                            strtotime($setoran['tanggal_setor'])
                        );
                        ?>
                    </td>

                    <td>
                        <?php echo number_format((float)$setoran['berat_kg'], 2, ',', '.'); ?> Kg
                    </td>

                    <td>
                        Rp <?php echo number_format((float)$setoran['harga_per_kg'], 0, ',', '.'); ?>
                    </td>

                    <td>
                        Rp <?php echo number_format((float)$setoran['total_harga'], 0, ',', '.'); ?>
                    </td>

                    <td>
    <span class="status-badge status-<?php echo htmlspecialchars($setoran['status']); ?>">
        <?php echo htmlspecialchars(ucfirst($setoran['status'])); ?>
    </span>
</td>
<td>
    <a
        href="detail_setoran.php?id=<?php echo (int)$setoran['id']; ?>"
        class="btn-edit-petugas"
    >
        Detail
    </a>
</td>

                </tr>

                <?php
                    endwhile;

                else :
                ?>

                <tr>
                    <td colspan="9" style="text-align:center;">
                        Belum ada data setoran sampah.
                    </td>
                </tr>

                <?php endif; ?>

            </tbody>

        </table>

        </div> 

    </div> 

</main>
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