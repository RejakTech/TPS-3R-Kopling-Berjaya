
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Ambil filter
$keyword = trim($_GET['keyword'] ?? '');
$role = trim($_GET['role'] ?? '');
$tanggal_awal = trim($_GET['tanggal_awal'] ?? '');
$tanggal_akhir = trim($_GET['tanggal_akhir'] ?? '');

function tanggalValid($tanggal) {
    $date = DateTime::createFromFormat('Y-m-d', $tanggal);
    return $date && $date->format('Y-m-d') === $tanggal;
}

if ($tanggal_awal !== '' && !tanggalValid($tanggal_awal)) {
    $tanggal_awal = '';
}

if ($tanggal_akhir !== '' && !tanggalValid($tanggal_akhir)) {
    $tanggal_akhir = '';
}

$daftar_role = ['admin', 'petugas', 'warga'];

if (!in_array($role, $daftar_role, true)) {
    $role = '';
}

// Pagination
$per_halaman = 10;
$halaman = max(1, (int)($_GET['halaman'] ?? 1));

// Kondisi pencarian
$keyword_sql = '%' . $keyword . '%';

$where = "
    WHERE (
        ? = ''
        OR u.nama_lengkap LIKE ?
        OR u.username LIKE ?
        OR l.aktivitas LIKE ?
        OR l.keterangan LIKE ?
    )
    AND (? = '' OR u.role = ?)
    AND (? = '' OR DATE(l.tanggal_aktivitas) >= ?)
    AND (? = '' OR DATE(l.tanggal_aktivitas) <= ?)
";

// Hitung total data
$sql_count = "
    SELECT COUNT(*) AS total
    FROM log_aktivitas l
    LEFT JOIN users u ON l.user_id = u.id
    $where
";

$stmt_count = mysqli_prepare($conn, $sql_count);

mysqli_stmt_bind_param(
    $stmt_count,
    "sssssssssss",
    $keyword,
    $keyword_sql,
    $keyword_sql,
    $keyword_sql,
    $keyword_sql,
    $role,
    $role,
    $tanggal_awal,
    $tanggal_awal,
    $tanggal_akhir,
    $tanggal_akhir
);

mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
$total_data = (int)mysqli_fetch_assoc($result_count)['total'];
mysqli_stmt_close($stmt_count);

$total_halaman = max(1, (int)ceil($total_data / $per_halaman));
$halaman = min($halaman, $total_halaman);
$offset = ($halaman - 1) * $per_halaman;

// Ambil data aktivitas
$sql = "
    SELECT
        l.id,
        l.user_id,
        l.aktivitas,
        l.keterangan,
        l.tanggal_aktivitas,
        u.nama_lengkap,
        u.username,
        u.role
    FROM log_aktivitas l
    LEFT JOIN users u ON l.user_id = u.id
    $where
    ORDER BY l.tanggal_aktivitas DESC, l.id DESC
    LIMIT ? OFFSET ?
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "sssssssssssii",
    $keyword,
    $keyword_sql,
    $keyword_sql,
    $keyword_sql,
    $keyword_sql,
    $role,
    $role,
    $tanggal_awal,
    $tanggal_awal,
    $tanggal_akhir,
    $tanggal_akhir,
    $per_halaman,
    $offset
);

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Statistik sederhana
$total_aktivitas = $total_data;

$query_hari_ini = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM log_aktivitas
     WHERE DATE(tanggal_aktivitas) = CURDATE()"
);
$aktivitas_hari_ini = (int)mysqli_fetch_assoc($query_hari_ini)['total'];

$query_admin = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM log_aktivitas l
     INNER JOIN users u ON l.user_id = u.id
     WHERE u.role = 'admin'"
);
$aktivitas_admin = (int)mysqli_fetch_assoc($query_admin)['total'];

$query_petugas = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM log_aktivitas l
     INNER JOIN users u ON l.user_id = u.id
     WHERE u.role = 'petugas'"
);
$aktivitas_petugas = (int)mysqli_fetch_assoc($query_petugas)['total'];

function urlHalaman($nomor) {
    $params = $_GET;
    $params['halaman'] = $nomor;
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aktivitas - Bank Sampah Digital</title>
    <link rel="stylesheet" href="../assets/css/style.css">
<style>
   
/* =========================================
   LOG AKTIVITAS - MODERN ADMIN DESIGN
========================================= */

.log-content {
    width: 100%;
    max-width: 1500px;
    margin: 0 auto;
    padding: 25px 28px;
    box-sizing: border-box;
}

/* HEADER */
/* HEADER DAN TOMBOL KEMBALI */
.log-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 28px;
}

.log-heading {
    margin-bottom: 0;
}

.log-btn-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 11px 17px;
    min-height: 42px;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #FFFFFF;
    border-radius: 9px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 4px 10px rgba(11, 53, 77, 0.12);
    transition: all .2s ease;
}

.log-btn-back:hover {
    background: linear-gradient(135deg, #082C40, #11899A);
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(11, 53, 77, 0.18);
}

.log-btn-back span {
    font-size: 18px;
    line-height: 1;
}

/* RESPONSIVE */
@media (max-width: 600px) {
    .log-header {
        align-items: flex-start;
        flex-direction: column;
        gap: 14px;
    }

    .log-btn-back {
        width: fit-content;
    }
}
.log-heading {
    margin-bottom: 1px;
}

.log-heading h1 {
    margin: 0;
    color: #0B4650;
    font-size: 29px;
    font-weight: 700;
    line-height: 1.3;
}

.log-heading p {
    margin: 8px 0 0;
    color: #718995;
    font-size: 14px;
    line-height: 1.6;
}

/* STATISTICS */
.log-statistics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    width: 100%;
    margin-bottom: 25px;
}

.log-stat-card {
    position: relative;
    min-width: 0;
    min-height: 115px;
    padding: 22px 20px;
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(11, 53, 77, 0.04);
    overflow: hidden;
    transition: transform .2s ease, box-shadow .2s ease;
}

.log-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(11, 53, 77, 0.08);
}

.log-stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: #159BAE;
}

.log-stat-card:nth-child(2)::before {
    background: #17B8C8;
}

.log-stat-card:nth-child(3)::before {
    background: #E49A0F;
}

.log-stat-card:nth-child(4)::before {
    background: #0B354D;
}

.log-stat-card span {
    display: block;
    color: #718995;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.5;
    margin-bottom: 12px;
}

.log-stat-card strong {
    display: block;
    color: #0B4650;
    font-size: 27px;
    font-weight: 700;
    line-height: 1.3;
}

/* FILTER */
.log-filter {
    background: #FFFFFF;
    padding: 23px;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    margin-bottom: 23px;
    box-shadow: 0 4px 15px rgba(11, 53, 77, 0.035);
}

.log-filter h3 {
    margin: 0 0 20px;
    color: #0B4650;
    font-size: 16px;
    font-weight: 700;
}

.log-filter-grid {
    display: grid;
    grid-template-columns: minmax(200px, 2fr) repeat(3, minmax(130px, 1fr)) auto;
    gap: 13px;
    align-items: end;
}

.log-filter-group {
    min-width: 0;
}

.log-filter-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #526E79;
    margin-bottom: 8px;
}

.log-filter-group input,
.log-filter-group select {
    width: 100%;
    height: 43px;
    padding: 10px 12px;
    background: #FFFFFF;
    border: 1px solid #DCE7EB;
    border-radius: 9px;
    outline: none;
    color: #294B56;
    font-size: 13px;
    box-sizing: border-box;
    transition: border-color .2s ease, box-shadow .2s ease;
}

.log-filter-group input:focus,
.log-filter-group select:focus {
    border-color: #159BAE;
    box-shadow: 0 0 0 3px rgba(21, 155, 174, 0.10);
}

.log-filter-actions {
    display: flex;
    gap: 8px;
}

.log-btn {
    height: 43px;
    padding: 0 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    border: none;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: .2s ease;
}

.log-btn-filter {
    background: linear-gradient(135deg, #0B4650, #159BAE);
    color: #FFFFFF;
}

.log-btn-filter:hover {
    background: linear-gradient(135deg, #083641, #11899A);
    box-shadow: 0 4px 12px rgba(21, 155, 174, .2);
}

.log-btn-reset {
    background: #EDF3F5;
    color: #526E79;
}

.log-btn-reset:hover {
    background: #DCE9ED;
}

/* TABLE CONTAINER */
.log-table-container {
    width: 100%;
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(11, 53, 77, 0.04);
}

.log-table-heading {
    padding: 21px 23px;
    border-bottom: 1px solid #EAF0F2;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.log-table-heading h3 {
    color: #0B4650;
    font-size: 16px;
    font-weight: 700;
    margin: 0;
}

.log-table-heading span {
    font-size: 12px;
    color: #718995;
    background: #EFF7F8;
    padding: 7px 12px;
    border-radius: 20px;
}

/* TABLE */
.log-table-scroll {
    width: 100%;
    overflow-x: auto;
}

.log-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

.log-table th {
    background: #F1F7F8;
    color: #526E79;
    text-align: left;
    padding: 15px 16px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.log-table td {
    padding: 16px;
    border-top: 1px solid #EDF2F4;
    color: #526E79;
    font-size: 13px;
    vertical-align: top;
}

.log-table tbody tr {
    transition: background .2s ease;
}

.log-table tbody tr:hover td {
    background: #F8FCFC;
}

.log-user-name {
    color: #0B4650;
    font-weight: 700;
    margin-bottom: 5px;
}

.log-username {
    color: #82969E;
    font-size: 11px;
}

.log-role {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}

.log-role-admin {
    background: #E7EFFB;
    color: #355A9B;
}

.log-role-petugas {
    background: #E3F5EE;
    color: #18734F;
}

.log-role-warga {
    background: #FFF2DF;
    color: #9A641D;
}

.log-activity-name {
    font-weight: 700;
    color: #0B4650;
    margin-bottom: 5px;
}

.log-description {
    color: #718995;
    line-height: 1.7;
    max-width: 350px;
    overflow-wrap: anywhere;
}

.log-date {
    white-space: nowrap;
    color: #526E79;
    line-height: 1.8;
    font-size: 12px;
}

.log-empty {
    text-align: center;
    padding: 45px 20px !important;
    color: #82969E !important;
}

/* PAGINATION */
.log-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 19px 23px;
    border-top: 1px solid #EAF0F2;
    flex-wrap: wrap;
}

.log-pagination-info {
    color: #718995;
    font-size: 12px;
}

.log-pagination-links {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.log-pagination-links a,
.log-pagination-links span {
    min-width: 34px;
    height: 34px;
    padding: 0 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    border-radius: 8px;
    border: 1px solid #E1EAED;
    color: #526E79;
    background: #FFFFFF;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    transition: .2s ease;
}

.log-pagination-links a:hover {
    background: #EDF7F8;
    border-color: #159BAE;
    color: #0B4650;
}

.log-pagination-links .active {
    background: linear-gradient(135deg, #0B4650, #159BAE);
    color: #FFFFFF;
    border-color: #159BAE;
}

/* RESPONSIVE TABLET */
@media (max-width: 1100px) {
    .log-content {
        padding: 22px 20px;
    }

    .log-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .log-filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .log-filter-actions {
        grid-column: 1 / -1;
    }
}

/* RESPONSIVE HP */
@media (max-width: 600px) {
    .log-content {
        padding: 18px 13px;
    }

    .log-heading {
        margin-bottom: 20px;
    }

    .log-heading h1 {
        font-size: 24px;
    }

    .log-heading p {
        font-size: 13px;
    }

    .log-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 11px;
        margin-bottom: 19px;
    }

    .log-stat-card {
        min-height: 100px;
        padding: 16px 14px;
    }

    .log-stat-card span {
        font-size: 11px;
        margin-bottom: 9px;
    }

    .log-stat-card strong {
        font-size: 22px;
    }

    .log-filter {
        padding: 17px;
        margin-bottom: 18px;
    }

    .log-filter-grid {
        grid-template-columns: 1fr;
        gap: 13px;
    }

    .log-filter-actions {
        grid-column: auto;
    }

    .log-filter-actions .log-btn {
        flex: 1;
    }

    .log-table-heading {
        padding: 17px;
        align-items: flex-start;
        flex-direction: column;
    }

    .log-pagination {
        padding: 16px;
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (max-width: 350px) {
    .log-statistics {
        grid-template-columns: 1fr;
    }
}

/* =========================================
   RESPONSIVE SIDEBAR LOG AKTIVITAS
========================================= */

.mobile-menu-button {
    display: none !important;
}

.sidebar-overlay {
    display: none;
}

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
    .sidebar-overlay.show {
        display: block !important;
        position: fixed !important;
        inset: 0 !important;
        background: rgba(4, 25, 39, .55) !important;
        z-index: 1000 !important;
    }

    /* DASHBOARD WRAPPER */
    .admin-dashboard {
        width: 100% !important;
        min-height: 100vh !important;
        overflow-x: hidden !important;
    }

    /* KONTEN */
    main.admin-content {
        width: 100% !important;
        margin-left: 0 !important;
        padding: 0 !important;
        box-sizing: border-box !important;
    }

    .log-content {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 22px 16px !important;
        box-sizing: border-box !important;
    }

    /* HEADER */
    .log-header {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 15px !important;
        margin-bottom: 22px !important;
    }

    .log-heading {
        width: 100%;
    }

    .log-title-line {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
    }

    .log-heading h1 {
        font-size: 23px !important;
        margin: 0 !important;
    }

    .log-heading p {
        font-size: 12px !important;
        margin-top: 9px !important;
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
        color: #FFFFFF !important;
        font-size: 23px !important;
        cursor: pointer !important;
    }

    /* TOMBOL KEMBALI */
    .log-btn-back {
        width: fit-content;
        max-width: 100%;
        box-sizing: border-box;
    }

    /* STATISTIK */
    .log-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 11px !important;
    }

    .log-stat-card {
        min-width: 0 !important;
        padding: 16px 13px !important;
    }

    .log-stat-card span {
        font-size: 11px !important;
    }

    .log-stat-card strong {
        font-size: 22px !important;
    }

    /* FILTER */
    .log-filter {
        padding: 17px !important;
    }

    .log-filter-grid {
        grid-template-columns: 1fr !important;
        gap: 13px !important;
    }

    .log-filter-actions {
        grid-column: auto !important;
        width: 100%;
    }

    .log-filter-actions .log-btn {
        flex: 1;
    }

    /* TABEL */
    .log-table-container {
        width: 100% !important;
        overflow: hidden !important;
    }

    .log-table-scroll {
        width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }

    .log-table {
        min-width: 850px !important;
    }

    .log-table-heading {
        padding: 17px !important;
        align-items: flex-start !important;
        flex-direction: column !important;
    }

    /* PAGINATION */
    .log-pagination {
        padding: 16px !important;
        flex-direction: column !important;
        align-items: flex-start !important;
    }

    .log-pagination-links {
        max-width: 100%;
    }
}

/* HP KECIL */
@media screen and (max-width: 380px) {

    .log-content {
        padding: 18px 12px !important;
    }

    .log-heading h1 {
        font-size: 20px !important;
    }

    .log-stat-card {
        padding: 14px 10px !important;
    }

    .log-stat-card strong {
        font-size: 19px !important;
    }

    .log-btn-back {
        font-size: 11px;
        padding: 10px 12px;
    }
}

/* DESKTOP */
@media screen and (min-width: 801px) {

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

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="admin-dashboard">

    <!-- SIDEBAR KIRI -->
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
       <a href="kategori.php" class="sidebar-link">
            <span class="sidebar-icon">♻️</span>
            <span>Kategori Sampah</span>
        </a>

        <a href="setoran.php" class="sidebar-link">
            <span class="sidebar-icon">📦</span>
            <span>Data Setoran</span>
        </a>

        <a href="tabungan.php" class="sidebar-link ">
            <span class="sidebar-icon">💰</span>
            <span>Tabungan Warga</span>
        </a>

        <a href="mutasi_tabungan.php" class="sidebar-link">
            <span class="sidebar-icon">↕️</span>
            <span>Mutasi Tabungan</span>
        </a>

        <a href="penarikan.php" class="sidebar-link ">
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
        <a href="log_aktivitas.php" class="sidebar-link active">
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
<main class="admin-content">
<div class="log-content">

<div class="log-header">

    <div class="log-heading">

        <div class="log-title-line">

            <h1>Log Aktivitas</h1>

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
            Pantau dan telusuri aktivitas yang dilakukan pengguna
            dalam sistem Bank Sampah Digital.
        </p>

    </div>

    <a href="dashboard.php" class="log-btn-back">
        <span>←</span>
        Kembali ke Dashboard
    </a>

</div>

    <div class="log-statistics">
        <div class="log-stat-card">
            <span>Total Aktivitas (sesuai filter)</span>
            <strong><?= number_format($total_aktivitas, 0, ',', '.') ?></strong>
        </div>

        <div class="log-stat-card">
            <span>Aktivitas Hari Ini</span>
            <strong><?= number_format($aktivitas_hari_ini, 0, ',', '.') ?></strong>
        </div>

        <div class="log-stat-card">
            <span>Aktivitas Admin</span>
            <strong><?= number_format($aktivitas_admin, 0, ',', '.') ?></strong>
        </div>

        <div class="log-stat-card">
            <span>Aktivitas Petugas</span>
            <strong><?= number_format($aktivitas_petugas, 0, ',', '.') ?></strong>
        </div>
    </div>

    <div class="log-filter">
        <h3>Filter Aktivitas</h3>

        <form method="GET">
            <div class="log-filter-grid">

                <div class="log-filter-group">
                    <label>Cari Aktivitas</label>
                    <input
                        type="text"
                        name="keyword"
                        placeholder="Nama, username, atau aktivitas..."
                        value="<?= e($keyword) ?>"
                    >
                </div>

                <div class="log-filter-group">
                    <label>Role Pengguna</label>
                    <select name="role">
                        <option value="">Semua Role</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="petugas" <?= $role === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                        <option value="warga" <?= $role === 'warga' ? 'selected' : '' ?>>Warga</option>
                    </select>
                </div>

                <div class="log-filter-group">
                    <label>Tanggal Awal</label>
                    <input type="date" name="tanggal_awal" value="<?= e($tanggal_awal) ?>">
                </div>

                <div class="log-filter-group">
                    <label>Tanggal Akhir</label>
                    <input type="date" name="tanggal_akhir" value="<?= e($tanggal_akhir) ?>">
                </div>

                <div class="log-filter-actions">
                    <button type="submit" class="log-btn log-btn-filter">Terapkan</button>
                    <a href="log_aktivitas.php" class="log-btn log-btn-reset">Reset</a>
                </div>

            </div>
        </form>
    </div>

    <div class="log-table-container">

        <div class="log-table-heading">
            <h3>Riwayat Aktivitas Pengguna</h3>
            <span><?= number_format($total_data, 0, ',', '.') ?> data ditemukan</span>
        </div>

        <div class="log-table-scroll">
            <table class="log-table">
                <thead>
                    <tr>
                        <th width="55">No.</th>
                        <th>Pengguna</th>
                        <th>Role</th>
                        <th>Aktivitas</th>
                        <th>Keterangan</th>
                        <th>Waktu</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php $nomor = $offset + 1; ?>

                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= $nomor++ ?></td>

                            <td>
                                <div class="log-user-name">
                                    <?= e($row['nama_lengkap'] ?: 'Akun tidak ditemukan') ?>
                                </div>
                                <div class="log-username">
                                    <?= $row['username'] ? '@' . e($row['username']) : 'Pengguna tidak tersedia' ?>
                                </div>
                            </td>

                            <td>
                                <?php if ($row['role']): ?>
                                    <span class="log-role log-role-<?= e($row['role']) ?>">
                                        <?= e($row['role']) ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="log-activity-name">
                                    <?= e($row['aktivitas']) ?>
                                </div>
                            </td>

                            <td>
                                <div class="log-description">
                                    <?= e($row['keterangan'] ?: '-') ?>
                                </div>
                            </td>

                            <td>
                                <div class="log-date">
                                    <?= date('d/m/Y', strtotime($row['tanggal_aktivitas'])) ?><br>
                                    <?= date('H:i:s', strtotime($row['tanggal_aktivitas'])) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="6" class="log-empty">
                            Belum ada aktivitas yang sesuai dengan pencarian.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="log-pagination">
            <div class="log-pagination-info">
                Halaman <?= $halaman ?> dari <?= $total_halaman ?>
                · <?= $per_halaman ?> data per halaman
            </div>

            <div class="log-pagination-links">
                <?php if ($halaman > 1): ?>
                    <a href="<?= e(urlHalaman(1)) ?>">Awal</a>
                    <a href="<?= e(urlHalaman($halaman - 1)) ?>">‹</a>
                <?php endif; ?>

                <?php
                $mulai = max(1, $halaman - 2);
                $akhir = min($total_halaman, $halaman + 2);
                ?>

                <?php for ($i = $mulai; $i <= $akhir; $i++): ?>
                    <?php if ($i === $halaman): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= e(urlHalaman($i)) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($halaman < $total_halaman): ?>
                    <a href="<?= e(urlHalaman($halaman + 1)) ?>">›</a>
                    <a href="<?= e(urlHalaman($total_halaman)) ?>">Akhir</a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
</main>

</div>

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
<?php
mysqli_stmt_close($stmt);
?>
