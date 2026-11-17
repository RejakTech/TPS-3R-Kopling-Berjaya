<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../config/koneksi.php';


// ========================================
// CSRF TOKEN
// ========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ========================================
// FILTER
// ========================================

$keyword = isset($_GET['keyword'])
    ? trim($_GET['keyword'])
    : '';

$status_filter = isset($_GET['status'])
    ? trim($_GET['status'])
    : '';


// Validasi status
if (!in_array($status_filter, ['', 'aktif', 'nonaktif'], true)) {
    $status_filter = '';
}


// ========================================
// STATISTIK KATEGORI
// ========================================

$query_statistik = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total_kategori,
        SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) AS total_aktif,
        SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) AS total_nonaktif
     FROM kategori_sampah"
);

if (!$query_statistik) {
    die("Gagal mengambil statistik kategori.");
}

$statistik = mysqli_fetch_assoc($query_statistik);

if (!$statistik) {
    $statistik = [
        'total_kategori' => 0,
        'total_aktif' => 0,
        'total_nonaktif' => 0
    ];
}


// ========================================
// QUERY KATEGORI
// ========================================

$conditions = [];
$params = [];
$types = '';


// Filter keyword
if ($keyword !== '') {

    $conditions[] = "
        (
            nama_kategori LIKE ?
            OR deskripsi LIKE ?
        )
    ";

    $search = '%' . $keyword . '%';

    $params[] = $search;
    $params[] = $search;

    $types .= 'ss';
}


// Filter status
if ($status_filter !== '') {

    $conditions[] = "status = ?";

    $params[] = $status_filter;

    $types .= 's';
}


// WHERE
$where_sql = '';

if (!empty($conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $conditions);
}


// ========================================
// AMBIL DATA KATEGORI
// ========================================

$sql_kategori = "
    SELECT
        id,
        nama_kategori,
        deskripsi,
        harga_per_kg,
        status
    FROM kategori_sampah
    $where_sql
    ORDER BY id DESC
";

$query = mysqli_prepare($conn, $sql_kategori);

if (!$query) {
    die("Gagal menyiapkan query kategori.");
}


if (!empty($params)) {

    mysqli_stmt_bind_param(
        $query,
        $types,
        ...$params
    );
}


if (!mysqli_stmt_execute($query)) {
    mysqli_stmt_close($query);
    die("Gagal mengambil data kategori.");
}


$result_kategori = mysqli_stmt_get_result($query);

if (!$result_kategori) {
    mysqli_stmt_close($query);
    die("Gagal mengambil hasil data kategori.");
}


// ========================================
// HELPER QUERY STRING
// ========================================

function queryFilterKategori($keyword, $status)
{
    $params = [];

    if ($keyword !== '') {
        $params['keyword'] = $keyword;
    }

    if ($status !== '') {
        $params['status'] = $status;
    }

    return !empty($params)
        ? '?' . http_build_query($params)
        : '';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Kategori Sampah - Bank Sampah Digital</title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>

        /* ========================================
           PAGE
        ======================================== */

        .kategori-wrapper {
            max-width: 1200px;
            margin: 0 auto;
        }


        /* ========================================
           HEADER
        ======================================== */

        .kategori-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 25px;
        }

 .kategori-header h1 {
    margin: 0;
    color: #075B5B;
    font-size: 30px;
    font-weight: 700;
    line-height: 1.2;
}

   .kategori-header p {
    margin: 8px 0 0;
    color: #6D858D;
    font-size: 15px;
    line-height: 1.5;
}

        .kategori-back {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            color: #0f766e;
            font-size: 14px;
            font-weight: 600;
        }


        /* ========================================
           STATISTICS
        ======================================== */

        .kategori-statistics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 22px;
        }

        .kategori-stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px;
        }

        .kategori-stat-card span {
            display: block;
            color: #8a8f98;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .kategori-stat-card strong {
            display: block;
            color: #172033;
            font-size: 25px;
        }


        /* ========================================
           ACTION BAR
        ======================================== */

        .kategori-action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .kategori-search-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }

        .kategori-search-input,
        .kategori-status-select {
            height: 42px;
            padding: 0 13px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            background: #ffffff;
            color: #172033;
            font-size: 13px;
            outline: none;
            box-sizing: border-box;
        }

        .kategori-search-input {
            width: 100%;
            max-width: 350px;
        }

        .kategori-status-select {
            min-width: 150px;
        }

        .kategori-search-input:focus,
        .kategori-status-select:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.08);
        }

        .btn-kategori-cari {
            height: 42px;
            padding: 0 17px;
            border: 0;
            border-radius: 9px;
            background: #0f766e;
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-kategori-cari:hover {
            background: #0d665f;
        }

        .btn-kategori-reset {
            height: 42px;
            display: inline-flex;
            align-items: center;
            padding: 0 15px;
            border-radius: 9px;
            background: #f3f4f6;
            color: #374151;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-kategori-tambah {
            height: 42px;
            display: inline-flex;
            align-items: center;
            padding: 0 17px;
            border-radius: 9px;
            background: #0f766e;
            color: #ffffff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .btn-kategori-tambah:hover {
            background: #0d665f;
        }


        /* ========================================
           ALERT
        ======================================== */

        .kategori-alert {
            margin-bottom: 20px;
            padding: 13px 16px;
            border-radius: 10px;
            font-size: 14px;
        }

        .kategori-alert.success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .kategori-alert.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }


        /* ========================================
           TABLE
        ======================================== */

        .kategori-table-container {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 15px;
            overflow: hidden;
        }

        .kategori-table-wrapper {
            overflow-x: auto;
        }

        .kategori-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .kategori-table th {
            padding: 14px 18px;
            background: #fafafa;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .kategori-table td {
            padding: 16px 18px;
            border-bottom: 1px solid #f0f1f2;
            color: #374151;
            font-size: 13px;
            vertical-align: middle;
        }

        .kategori-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .kategori-table tbody tr:hover {
            background: #fafdfc;
        }

        .kategori-name {
            color: #172033;
            font-weight: 600;
        }

        .kategori-description {
            max-width: 300px;
            color: #6b7280;
            line-height: 1.5;
        }

        .kategori-price {
            color: #172033;
            font-weight: 700;
            white-space: nowrap;
        }


        /* ========================================
           STATUS
        ======================================== */

        .kategori-status {
            display: inline-flex;
            align-items: center;
            padding: 6px 11px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .kategori-status.aktif {
            background: #dcfce7;
            color: #166534;
        }

        .kategori-status.nonaktif {
            background: #fee2e2;
            color: #991b1b;
        }


        /* ========================================
           ACTION
        ======================================== */

        .kategori-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-kategori-edit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 8px;
            background: #e6f4f1;
            color: #0f766e;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
        }

        .btn-kategori-edit:hover {
            background: #d5eeea;
        }

        .btn-kategori-hapus {
            padding: 8px 12px;
            border: 0;
            border-radius: 8px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-kategori-hapus:hover {
            background: #fee2e2;
        }


        /* ========================================
           EMPTY
        ======================================== */

        .kategori-empty {
            padding: 45px 20px !important;
            text-align: center !important;
            color: #8a8f98 !important;
        }


        /* ========================================
           RESPONSIVE
        ======================================== */

        @media (max-width: 850px) {

            .kategori-statistics {
                grid-template-columns: 1fr;
            }

            .kategori-action-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .kategori-search-form {
                flex-wrap: wrap;
            }

            .kategori-search-input {
                max-width: none;
                flex: 1;
                min-width: 180px;
            }

            .btn-kategori-tambah {
                justify-content: center;
            }
        }

        @media (max-width: 600px) {

            .kategori-header {
                flex-direction: column;
            }

            .kategori-search-form {
                display: grid;
                grid-template-columns: 1fr;
            }

            .kategori-search-input,
            .kategori-status-select,
            .btn-kategori-cari,
            .btn-kategori-reset {
                width: 100%;
                max-width: none;
            }

            .kategori-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-kategori-edit,
            .btn-kategori-hapus {
                width: 100%;
            }
        }
/* =========================
   LAYOUT KATEGORI
   MENYESUAIKAN DATA PETUGAS
========================= */

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

.kategori-wrapper {
    width: 100%;
    max-width: none;
    margin: 0;
    box-sizing: border-box;
}

@media (max-width: 900px) {
    .admin-content {
        padding: 30px;
    }
}

@media (max-width: 600px) {
    .admin-content {
        padding: 24px 18px;
    }
}

//* ========================================
   HEADER KATEGORI - MODERN
======================================== */

.kategori-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 28px;
}

.kategori-header h1 {
    color: #0B4650;
    font-size: 29px;
    font-weight: 700;
    line-height: 1.2;
    margin: 0;
}

.kategori-header p {
    margin: 8px 0 0;
    color: #718995;
    font-size: 14px;
    font-weight: 400;
    line-height: 1.6;
}

/* Tombol kembali */
.kategori-back {
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

.kategori-back:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    color: #ffffff !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(21, 155, 174, .23);
}
/* ========================================
   STATISTIK KATEGORI
======================================== */

.kategori-statistics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 25px;
    width: 100%;
}

.kategori-stat-card {
    position: relative;
    background: #ffffff;
    border: 1px solid #E2EDF0;
    border-radius: 13px;
    padding: 17px 20px 17px 23px;
    box-shadow: 0 3px 12px rgba(11, 53, 77, .04);
    overflow: hidden;
    transition: .25s ease;
}

.kategori-stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.kategori-stat-card:nth-child(1)::before {
    background: #159BAE;
}

.kategori-stat-card:nth-child(2)::before {
    background: #0D9488;
}

.kategori-stat-card:nth-child(3)::before {
    background: #E49A0F;
}

.kategori-stat-card span {
    color: #718995;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 7px;
}

.kategori-stat-card strong {
    font-size: 30px;
    font-weight: 800;
    line-height: 1.2;
}

.kategori-stat-card:nth-child(1) strong {
    color: #159BAE;
}

.kategori-stat-card:nth-child(2) strong {
    color: #0D9488;
}

.kategori-stat-card:nth-child(3) strong {
    color: #E49A0F;
}

.kategori-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 7px 16px rgba(11, 53, 77, .08);
}


/* ========================================
   RESPONSIVE
======================================== */

@media (max-width: 700px) {
    .kategori-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .kategori-back {
        align-self: flex-end;
    }

    .kategori-statistics {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 9px;
    }

    .kategori-stat-card {
        padding: 14px 9px 14px 13px;
    }

    .kategori-stat-card span {
        font-size: 10px;
    }

    .kategori-stat-card strong {
        font-size: 23px;
    }
}

@media (max-width: 400px) {
    .kategori-statistics {
        gap: 6px;
    }

    .kategori-stat-card {
        padding: 12px 7px 12px 10px;
    }

    .kategori-stat-card strong {
        font-size: 20px;
    }
}
/* SAMAKAN FONT DENGAN HALAMAN DATA WARGA */
.admin-main,
.admin-main * {
    font-family: inherit;
}
/* ========================================
   TOMBOL TAMBAH KATEGORI
======================================== */

.btn-kategori-tambah {
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #ffffff;
    font-weight: 500;
    border-radius: 9px;
    box-shadow: 0 4px 12px rgba(21, 155, 174, .16);
    transition: all .25s ease;
}

.btn-kategori-tambah:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(21, 155, 174, .23);
}


/* ========================================
   TOMBOL CARI
======================================== */

.btn-kategori-cari {
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #ffffff;
    font-weight: 500;
    border-radius: 9px;
    transition: all .25s ease;
}

.btn-kategori-cari:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    transform: translateY(-2px);
}


/* ========================================
   HEADER TABEL
======================================== */

.kategori-table th {
    background: #EAF3F6;
    color: #0B4650;
    font-size: 12px;
    font-weight: 600;
    padding: 16px 18px;
    border-bottom: 1px solid #DCE9ED;
    letter-spacing: .2px;
}

/* Garis pemisah tabel */
.kategori-table td {
    border-bottom: 1px solid #EDF2F3;
}

/* Hover baris */
.kategori-table tbody tr:hover {
    background: #F4FAFA;
}

/* ========================================
   RESPONSIVE SIDEBAR DAN HAMBURGER
======================================== */

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
        max-width: none !important;
        margin: 0 !important;
        padding: 20px 18px 25px !important;
        box-sizing: border-box !important;
    }

    .kategori-wrapper {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
    }

    /* HEADER DAN HAMBURGER */
    .kategori-header {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 12px !important;
        margin-bottom: 22px !important;
    }

    .kategori-heading-content {
        width: 100%;
    }

    .kategori-title-line {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        width: 100%;
    }

    .kategori-title-line h1 {
        font-size: 25px !important;
        margin: 0 !important;
    }

    .kategori-heading-content p {
        font-size: 13px !important;
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

    .kategori-back {
        align-self: flex-start !important;
    }

    /* STATISTIK */
    .kategori-statistics {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 9px !important;
    }

    .kategori-stat-card {
        min-width: 0;
        padding: 14px 9px 14px 13px !important;
    }

    .kategori-stat-card span {
        font-size: 10px !important;
    }

    .kategori-stat-card strong {
        font-size: 22px !important;
    }

    /* PENCARIAN */
    .kategori-action-bar {
        flex-direction: column !important;
        align-items: stretch !important;
    }

    .kategori-search-form {
        display: grid !important;
        grid-template-columns: 1fr !important;
        width: 100%;
    }

    .kategori-search-input,
    .kategori-status-select,
    .btn-kategori-cari,
    .btn-kategori-reset {
        width: 100% !important;
        max-width: none !important;
        box-sizing: border-box;
    }

    .btn-kategori-tambah {
        width: 100%;
        justify-content: center;
        box-sizing: border-box;
    }

    /* TABEL */
    .kategori-table-container {
        width: 100% !important;
        max-width: 100% !important;
        overflow: hidden !important;
    }

    .kategori-table-wrapper {
        width: 100%;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }

    .kategori-table {
        min-width: 800px !important;
    }

    .kategori-actions {
        flex-direction: row !important;
    }
}

/* HP KECIL */
@media (max-width: 400px) {

    .kategori-statistics {
        gap: 6px !important;
    }

    .kategori-stat-card {
        padding: 12px 7px 12px 10px !important;
    }

    .kategori-stat-card strong {
        font-size: 20px !important;
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
        box-sizing: border-box !important;
    }

    .admin-content {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 38px 35px !important;
        box-sizing: border-box !important;
    }

    .kategori-wrapper {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
    }

    .kategori-header {
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
    }

    .kategori-title-line {
        display: block !important;
    }
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
        <a href="kategori.php" class="sidebar-link active">
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

        <div class="kategori-wrapper">


        <!-- ========================================
             HEADER
        ======================================== -->

        <div class="kategori-header">

    <div class="kategori-heading-content">

        <div class="kategori-title-line">

            <h1>Kategori Sampah</h1>

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
            Kelola jenis sampah dan harga pembelian per kilogram.
        </p>

    </div>

    <a
        href="dashboard.php"
        class="kategori-back"
    >
        ← Kembali ke Dashboard
    </a>

</div>


        <!-- ========================================
             ALERT
        ======================================== -->

        <?php if (isset($_GET['status'])) : ?>

            <?php if ($_GET['status'] === 'berhasil') : ?>

                <div class="kategori-alert success">
                    Kategori berhasil ditambahkan!
                </div>

            <?php elseif ($_GET['status'] === 'edit_berhasil') : ?>

                <div class="kategori-alert success">
                    Kategori berhasil diperbarui!
                </div>

            <?php elseif ($_GET['status'] === 'hapus_berhasil') : ?>

                <div class="kategori-alert success">
                    Kategori berhasil dihapus!
                </div>

            <?php elseif ($_GET['status'] === 'hapus_gagal') : ?>

                <div class="kategori-alert error">
                    Gagal menghapus kategori.
                </div>

            <?php elseif ($_GET['status'] === 'kategori_digunakan') : ?>

                <div class="kategori-alert error">
                    Kategori tidak dapat dihapus karena sudah digunakan
                    dalam transaksi setoran. Silakan nonaktifkan kategori tersebut.
                </div>

            <?php elseif ($_GET['status'] === 'token_gagal') : ?>

                <div class="kategori-alert error">
                    Permintaan tidak valid. Silakan muat ulang halaman.
                </div>

            <?php endif; ?>

        <?php endif; ?>


        <!-- ========================================
             STATISTICS
        ======================================== -->

        <div class="kategori-statistics">

            <div class="kategori-stat-card">

                <span>Total Kategori</span>

                <strong>
                    <?php
                    echo number_format(
                        (int) $statistik['total_kategori'],
                        0,
                        ',',
                        '.'
                    );
                    ?>
                </strong>

            </div>


            <div class="kategori-stat-card">

                <span>Kategori Aktif</span>

                <strong>
                    <?php
                    echo number_format(
                        (int) $statistik['total_aktif'],
                        0,
                        ',',
                        '.'
                    );
                    ?>
                </strong>

            </div>


            <div class="kategori-stat-card">

                <span>Kategori Nonaktif</span>

                <strong>
                    <?php
                    echo number_format(
                        (int) $statistik['total_nonaktif'],
                        0,
                        ',',
                        '.'
                    );
                    ?>
                </strong>

            </div>

        </div>


        <!-- ========================================
             SEARCH & ACTION
        ======================================== -->

        <div class="kategori-action-bar">

            <form
                method="GET"
                class="kategori-search-form"
            >

                <input
                    type="text"
                    name="keyword"
                    class="kategori-search-input"
                    placeholder="Cari nama kategori atau deskripsi..."
                    value="<?php echo htmlspecialchars($keyword); ?>"
                    maxlength="100"
                >


                <select
                    name="status"
                    class="kategori-status-select"
                >

                    <option
                        value=""
                        <?php echo $status_filter === '' ? 'selected' : ''; ?>
                    >
                        Semua Status
                    </option>

                    <option
                        value="aktif"
                        <?php echo $status_filter === 'aktif' ? 'selected' : ''; ?>
                    >
                        Aktif
                    </option>

                    <option
                        value="nonaktif"
                        <?php echo $status_filter === 'nonaktif' ? 'selected' : ''; ?>
                    >
                        Nonaktif
                    </option>

                </select>


                <button
                    type="submit"
                    class="btn-kategori-cari"
                >
                    Cari
                </button>


                <?php if ($keyword !== '' || $status_filter !== '') : ?>

                    <a
                        href="kategori.php"
                        class="btn-kategori-reset"
                    >
                        Reset
                    </a>

                <?php endif; ?>

            </form>


            <a
                href="tambah_kategori.php"
                class="btn-kategori-tambah"
            >
                + Tambah Kategori
            </a>

        </div>


        <!-- ========================================
             TABLE
        ======================================== -->

        <div class="kategori-table-container">

            <div class="kategori-table-wrapper">

                <table class="kategori-table">

                    <thead>

                        <tr>

                            <th>No</th>

                            <th>Nama Kategori</th>

                            <th>Deskripsi</th>

                            <th>Harga per Kg</th>

                            <th>Status</th>

                            <th>Aksi</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php

                    $no = 1;

                    if (mysqli_num_rows($result_kategori) > 0) :

                        while ($kategori = mysqli_fetch_assoc($result_kategori)) :

                            $status_kategori = $kategori['status'] ?? 'aktif';

                    ?>

                        <tr>

                            <td>
                                <?php echo $no++; ?>
                            </td>


                            <td>

                                <div class="kategori-name">
                                    <?php
                                    echo htmlspecialchars(
                                        $kategori['nama_kategori']
                                    );
                                    ?>
                                </div>

                            </td>


                            <td>

                                <div class="kategori-description">

                                    <?php

                                    $deskripsi = trim(
                                        $kategori['deskripsi'] ?? ''
                                    );

                                    echo $deskripsi !== ''
                                        ? htmlspecialchars($deskripsi)
                                        : '-';

                                    ?>

                                </div>

                            </td>


                            <td>

                                <div class="kategori-price">

                                    Rp
                                    <?php
                                    echo number_format(
                                        (float) $kategori['harga_per_kg'],
                                        0,
                                        ',',
                                        '.'
                                    );
                                    ?>

                                </div>

                            </td>


                            <td>

                                <span
                                    class="kategori-status <?php echo $status_kategori === 'aktif' ? 'aktif' : 'nonaktif'; ?>"
                                >

                                    <?php
                                    echo $status_kategori === 'aktif'
                                        ? 'Aktif'
                                        : 'Nonaktif';
                                    ?>

                                </span>

                            </td>


                            <td>

                                <div class="kategori-actions">

                                    <a
                                        href="edit_kategori.php?id=<?php echo (int) $kategori['id']; ?>"
                                        class="btn-kategori-edit"
                                    >
                                        Edit
                                    </a>


                                    <form
                                        action="hapus_kategori.php"
                                        method="POST"
                                        onsubmit="return confirm('Yakin ingin menghapus kategori ini?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?php echo (int) $kategori['id']; ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="btn-kategori-hapus"
                                        >
                                            Hapus
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php

                        endwhile;

                    else :

                    ?>

                        <tr>

                            <td
                                colspan="6"
                                class="kategori-empty"
                            >

                                <?php if ($keyword !== '' || $status_filter !== '') : ?>

                                    Tidak ada kategori yang sesuai dengan filter.

                                <?php else : ?>

                                    Belum ada kategori sampah.

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

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

<?php
mysqli_stmt_close($query);
?>