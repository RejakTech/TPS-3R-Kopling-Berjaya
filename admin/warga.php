<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$conditions = ["role = 'warga'"];
$params = [];
$types = '';

if ($keyword !== '') {
    $conditions[] = "(nama_lengkap LIKE ? OR username LIKE ? OR email LIKE ? OR no_hp LIKE ?)";
    
    $search = '%' . $keyword . '%';

    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;

    $types .= 'ssss';
}

if (in_array($status, ['aktif', 'nonaktif'], true)) {
    $conditions[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

$where = implode(' AND ', $conditions);

$sql = "
    SELECT
        id,
        nama_lengkap,
        username,
        email,
        no_hp,
        alamat,
        status
    FROM users
    WHERE $where
    ORDER BY id DESC
";

$query = mysqli_prepare($conn, $sql);

if (!$query) {
    die("Gagal menyiapkan data warga.");
}

if (!empty($params)) {
    mysqli_stmt_bind_param($query, $types, ...$params);
}

if (!mysqli_stmt_execute($query)) {
    mysqli_stmt_close($query);
    die("Gagal mengambil data warga.");
}

$result = mysqli_stmt_get_result($query);

if (!$result) {
    mysqli_stmt_close($query);
    die("Gagal mengambil hasil data warga.");
}

$data_warga = [];

while ($warga = mysqli_fetch_assoc($result)) {
    $data_warga[] = $warga;
}

mysqli_stmt_close($query);


/* ==============================
   STATISTIK WARGA
============================== */

$query_total = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'warga'"
);

$query_aktif = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'warga'
     AND status = 'aktif'"
);

$query_nonaktif = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'warga'
     AND status = 'nonaktif'"
);

$total_warga = $query_total
    ? (int) mysqli_fetch_assoc($query_total)['total']
    : 0;

$total_aktif = $query_aktif
    ? (int) mysqli_fetch_assoc($query_aktif)['total']
    : 0;

$total_nonaktif = $query_nonaktif
    ? (int) mysqli_fetch_assoc($query_nonaktif)['total']
    : 0;

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Warga - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <style>


/* =========================
   AREA KONTEN
   SAMA DENGAN KATEGORI
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

/* =========================
   HALAMAN WARGA
========================= */

.warga-page {
    width: 100%;
    max-width: none;
    margin: 0;
    box-sizing: border-box;
}


/* =========================
   STATISTIK
========================= */

.warga-statistics {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 20px;

    margin: 25px 0;

    width: 100%;
    box-sizing: border-box;
}

.warga-stat-card {
    background: #ffffff;

    border: 1px solid #e7eeeb;
    border-radius: 14px;

    padding: 20px 24px;

    box-shadow:
        0 4px 14px rgba(20, 83, 69, 0.04);

    box-sizing: border-box;
    min-width: 0;
}

.warga-stat-card h3 {
    margin: 0 0 8px;

    color: #71817c;

    font-size: 13px;
    font-weight: 600;
}

.warga-stat-card p {
    margin: 0;

    color: #174c43;

    font-size: 28px;
    font-weight: 700;
}


/* =========================
   FILTER
========================= */

.warga-filter {
    background: #ffffff;

    border: 1px solid #e7eeeb;
    border-radius: 14px;

    padding: 20px 24px;

    margin-bottom: 25px;

    box-sizing: border-box;
}

.warga-filter-form {
    display: grid;

    grid-template-columns:
        minmax(250px, 1fr)
        200px
        auto
        auto;

    gap: 14px;

    align-items: end;
}

.warga-filter-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.warga-filter-group label {
    font-size: 12px;
    color: #71817c;
    font-weight: 600;
}

.warga-filter-group input,
.warga-filter-group select {
    width: 100%;

    box-sizing: border-box;

    padding: 11px 13px;

    border: 1px solid #dce8e5;
    border-radius: 8px;

    outline: none;

    font-size: 14px;
    color: #34443f;

    background: #ffffff;
}

.warga-filter-group input:focus,
.warga-filter-group select:focus {
    border-color: #159b83;
}


/* =========================
   BUTTON FILTER
========================= */

.btn-warga-filter,
.btn-warga-reset {
    min-height: 42px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 0 18px;

    border-radius: 8px;

    font-size: 13px;
    font-weight: 600;

    box-sizing: border-box;

    white-space: nowrap;
}

.btn-warga-filter {
    border: 0;

    background: #0f766e;
    color: #ffffff;

    cursor: pointer;
}

.btn-warga-filter:hover {
    background: #0d655f;
}

.btn-warga-reset {
    background: #f1f4f3;
    color: #49615c;

    text-decoration: none;
}

.btn-warga-reset:hover {
    background: #e7ecea;
}


/* =========================
   TABEL
========================= */

.warga-table-container {
    width: 100%;

    background: #ffffff;

    border: 1px solid #e7eeeb;
    border-radius: 14px;

    overflow-x: auto;

    box-sizing: border-box;
}

.warga-table {
    width: 100%;

    min-width: 900px;

    border-collapse: collapse;

    font-size: 13px;
}

.warga-table th {
    background: #f7faf9;

    color: #52635e;

    font-size: 12px;
    font-weight: 700;

    text-align: left;

    padding: 14px 16px;

    border-bottom: 1px solid #e7eeeb;

    white-space: nowrap;
}

.warga-table td {
    padding: 14px 16px;

    color: #465650;

    border-bottom: 1px solid #edf2f0;

    vertical-align: middle;
}

.warga-table tbody tr:hover {
    background: #fafcfb;
}

.warga-table tbody tr:last-child td {
    border-bottom: none;
}


/* =========================
   STATUS
========================= */

.warga-status {
    display: inline-flex;
    align-items: center;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;
    font-weight: 700;
}

.warga-status-aktif {
    background: #e8f5f1;
    color: #0f766e;
}

.warga-status-nonaktif {
    background: #fcebec;
    color: #b42318;
}


/* =========================
   ACTION
========================= */

.warga-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 7px 11px;

    margin: 2px;

    border: 0;
    border-radius: 7px;

    background: #e8f5f1;
    color: #0f766e;

    text-decoration: none;

    font-size: 12px;
    font-weight: 700;

    white-space: nowrap;
}

.warga-action:hover {
    background: #d9eee8;
}


/* =========================
   EMPTY DATA
========================= */

.warga-empty {
    text-align: center;

    padding: 35px 20px;

    color: #71817c;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 900px) {

    .admin-content {
        padding: 30px;
    }

    .warga-filter-form {
        grid-template-columns:
            minmax(200px, 1fr)
            180px;
    }

    .btn-warga-filter,
    .btn-warga-reset {
        width: 100%;
    }
}


@media (max-width: 600px) {

    .admin-content {
        padding: 24px 18px;
    }

    .warga-statistics {
        grid-template-columns: 1fr;
    }

    .warga-filter-form {
        grid-template-columns: 1fr;
    }
}

/* =====================================
   DATA WARGA - TEMA LOGIN BANK SAMPAH
   NAVY • TEAL • CYAN
   SIDEBAR TIDAK DIUBAH
===================================== */

/* HEADING */
.warga-page .admin-heading {
    margin-bottom: 25px;
}

.warga-page .admin-heading h1 {
    margin: 0 0 8px;
    color: #0B4650;
    font-size: 30px;
    font-weight: 700;
    line-height: 1.3;
}

.warga-page .admin-heading p {
    margin: 0;
    color: #718995;
    font-size: 14px;
    line-height: 1.6;
}

/* TOMBOL KEMBALI */
.warga-page .btn-login-submit {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    padding: 11px 20px !important;
    border: none;
    border-radius: 10px !important;
    background: linear-gradient(110deg, #0B354D, #17B8C8) !important;
    color: #FFFFFF !important;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    box-shadow: 0 5px 13px rgba(11, 53, 77, .15);
    transition: .25s ease;
}

.warga-page .btn-login-submit:hover {
    background: linear-gradient(110deg, #082B40, #109EAF) !important;
    transform: translateY(-2px);
}

/* KARTU STATISTIK */
.warga-statistics {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.warga-stat-card {
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    padding: 23px 24px;
    box-shadow: 0 4px 15px rgba(11, 45, 70, .045);
    transition: .2s ease;
}

.warga-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(11, 45, 70, .09);
}

.warga-stat-card h3 {
    color: #718995;
}

.warga-stat-card p {
    color: #0B5360;
    font-size: 30px;
}

/* FILTER */
.warga-filter {
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(11, 45, 70, .035);
}

.warga-filter-group label {
    color: #526C78;
}

.warga-filter-group input,
.warga-filter-group select {
    background: #F7FAFC;
    border: 1px solid #DCE7EC;
    border-radius: 9px;
}

.warga-filter-group input:focus,
.warga-filter-group select:focus {
    border-color: #17AEBE;
    box-shadow: 0 0 0 3px rgba(23, 184, 200, .12);
}

/* TOMBOL CARI */
.btn-warga-filter {
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    border-radius: 9px;
    box-shadow: 0 3px 9px rgba(11, 53, 77, .12);
    transition: .2s ease;
}

.btn-warga-filter:hover {
    background: linear-gradient(110deg, #082B40, #109EAF);
    transform: translateY(-1px);
}

.btn-warga-reset {
    background: #EDF3F5;
    color: #415D69;
    border-radius: 9px;
}

/* TABEL */
.warga-table-container {
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(11, 45, 70, .035);
}

.warga-table th {
    background: #EAF3F6;
    color: #315A69;
    border-bottom: 1px solid #DCE8EC;
}

.warga-table td {
    color: #526671;
}

.warga-table tbody tr:hover {
    background: #F2F9FA;
}

/* STATUS */
.warga-status-aktif {
    background: #E2F5F1;
    color: #087C72;
}

.warga-status-nonaktif {
    background: #FCEBEC;
    color: #B42318;
}

/* TOMBOL AKSI */
.warga-action {
    background: #E5F3F5;
    color: #087987;
    border-radius: 7px;
    transition: .2s ease;
}

.warga-action:hover {
    background: #D2ECEF;
    color: #075B68;
    transform: translateY(-1px);
}

/* RESPONSIVE */
@media (max-width: 900px) {
    .warga-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .warga-page .admin-heading h1 {
        font-size: 25px;
    }

    .warga-statistics {
        grid-template-columns: 1fr;
    }
}
/* TOMBOL AKSI TABEL */
.warga-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 13px;
    margin: 3px 2px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: all .2s ease;
}

/* DETAIL */
.warga-action-detail {
    background: #E5F3F7;
    color: #087987;
    border: 1px solid #CBE7ED;
}

.warga-action-detail:hover {
    background: #D2EBF0;
    color: #075B68;
    transform: translateY(-2px);
}

/* EDIT */
.warga-action-edit {
    background: linear-gradient(110deg, #0B354D, #159BAE);
    color: #FFFFFF;
    border: 1px solid transparent;
}

.warga-action-edit:hover {
    background: linear-gradient(110deg, #082B40, #108B9D);
    color: #FFFFFF;
    transform: translateY(-2px);
}

/* NONAKTIFKAN */
.warga-action-deactivate {
    background: #FFF3E5;
    color: #B96B17;
    border: 1px solid #F5DFC1;
}

.warga-action-deactivate:hover {
    background: #FCE7CC;
    color: #98510C;
    transform: translateY(-2px);
}

/* AKTIFKAN */
.warga-action-activate {
    background: #E2F5F1;
    color: #087C72;
    border: 1px solid #C5E9DF;
}

.warga-action-activate:hover {
    background: #CDEDE4;
    color: #075F56;
    transform: translateY(-2px);
}
/* RAPikan tombol aksi tabel */
.warga-table td:last-child {
    white-space: nowrap;
}

.warga-table td:last-child form {
    display: inline-flex !important;
    vertical-align: middle;
}

.warga-action {
    min-height: 34px;
    box-sizing: border-box;
    cursor: pointer;
    font-family: inherit;
}
.warga-table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.warga-table th,
.warga-table td {
    vertical-align: middle;
}
/* HEADER DATA WARGA */
.warga-header {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 25px;
    width: 100%;
    margin-bottom: 28px;
}

/* JUDUL */
.warga-header-left {
    min-width: 0;
}

.warga-header-left h1 {
    margin: 0 0 8px;
}

/* TOMBOL KEMBALI */
.warga-header-action {
    display: flex;
    justify-content: flex-end;
    align-items: flex-start;
}

.btn-kembali-warga {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 18px;
    border-radius: 9px;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #FFFFFF;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
    box-shadow: 0 3px 9px rgba(21, 155, 174, 0.15);
    transition: .25s ease;
}

.btn-kembali-warga:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    color: #FFFFFF;
    transform: translateY(-2px);
}

/* RESPONSIVE */
@media (max-width: 600px) {
    .warga-header {
        gap: 10px;
    }

    .btn-kembali-warga {
        padding: 9px 11px;
        font-size: 10px;
    }
}
/* =========================
   STATISTIK DATA WARGA
========================= */

.warga-statistics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    width: 100%;
    margin: 25px 0 30px;
}

.warga-stat-card {
    position: relative;
    overflow: hidden;
    background: #FFFFFF;
    border: 1px solid #E2EDF0;
    border-radius: 15px;
    padding: 23px 25px;
    box-shadow: 0 4px 14px rgba(11, 53, 77, 0.045);
    transition: all 0.25s ease;
}

/* Garis aksen di sebelah kiri */
.warga-stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(180deg, #159BAE, #17B8C8);
}

/* Efek hover */
.warga-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 9px 22px rgba(11, 53, 77, 0.09);
    border-color: #B9DCE3;
}

/* Judul statistik */
.warga-stat-card h3 {
    color: #718995;
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    font-weight: 600;
    margin: 0 0 13px;
}

/* Angka statistik */
.warga-stat-card p {
    color: #0B354D;
    font-family: 'Poppins', sans-serif;
    font-size: 30px;
    font-weight: 800;
    line-height: 1.2;
    margin: 0;
}

/* Variasi warna masing-masing kartu */
.warga-stat-card:nth-child(1)::before {
    background: linear-gradient(180deg, #159BAE, #17B8C8);
}

.warga-stat-card:nth-child(2)::before {
    background: linear-gradient(180deg, #0D9488, #34BFA8);
}

.warga-stat-card:nth-child(3)::before {
    background: linear-gradient(180deg, #E49A0F, #F5BD48);
}

/* RESPONSIVE TABLET */
@media (max-width: 900px) {
    .warga-statistics {
        gap: 14px;
    }

    .warga-stat-card {
        padding: 19px;
    }

    .warga-stat-card p {
        font-size: 26px;
    }
}

/* RESPONSIVE HP */
@media (max-width: 600px) {
    .warga-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .warga-stat-card {
        padding: 16px;
        border-radius: 12px;
    }

    .warga-stat-card h3 {
        font-size: 11px;
    }

    .warga-stat-card p {
        font-size: 23px;
    }
}
/* ANGKA STATISTIK DATA WARGA */
.warga-stat-card p {
    font-family: 'Poppins', sans-serif;
    font-size: 26px !important;
    font-weight: 700 !important;
    line-height: 1.2;
    margin-top: 10px;
    letter-spacing: -1px;
}

/* Total Warga - Biru Tosca */
.warga-stat-card:nth-child(1) p {
    color: #159BAE !important;
}

/* Warga Aktif - Hijau */
.warga-stat-card:nth-child(2) p {
    color: #0D9488 !important;
}

/* Warga Nonaktif - Oranye */
.warga-stat-card:nth-child(3) p {
    color: #E49A0F !important;
}

/* Judul statistik */
.warga-stat-card h3 {
    font-size: 14px;
    font-weight: 700;
    color: #718995;
}

/* RESPONSIVE */
@media (max-width: 600px) {
    .warga-stat-card p {
        font-size: 29px !important;
    }
}
/* =================================
   RESPONSIVE SIDEBAR DATA WARGA
================================= */

/* Tombol hamburger disembunyikan di laptop */
.mobile-menu-button {
    display: none;
}

.sidebar-overlay {
    display: none;
}

.admin-main {
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.admin-content,
.warga-page {
    min-width: 0;
    max-width: 100%;
}

/* TABLET DAN HP */
@media (max-width: 800px) {

    body {
        overflow-x: hidden;
    }

    /* Sidebar tertutup secara default */
    .admin-sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 250px !important;
        height: 100vh !important;
        z-index: 1001 !important;
        transform: translateX(-100%) !important;
        transition: transform .3s ease !important;
        overflow-y: auto;
    }

    /* Sidebar terbuka */
    .admin-sidebar.open {
        transform: translateX(0) !important;
    }

    /* Area gelap di belakang sidebar */
    .sidebar-overlay.show {
        display: block !important;
        position: fixed;
        inset: 0;
        background: rgba(4, 25, 39, .55);
        z-index: 1000;
    }

    /* Konten memenuhi layar */
    .admin-main {
        margin-left: 0 !important;
        width: 100% !important;
        min-height: 100vh;
    }

    .admin-content {
    width: 100%;
    padding: 10px 20px 25px !important;
}

    .warga-header {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .warga-header-action {
        justify-content: flex-start;
    }

    .warga-filter-form {
        grid-template-columns: 1fr;
    }

    .btn-warga-filter,
    .btn-warga-reset {
        width: 100%;
    }

    .warga-table-container {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .warga-table {
        min-width: 900px;
    }
}

/* HP KECIL */
@media (max-width: 480px) {

    .admin-content {
        padding: 65px 14px 20px !important;
    }

    .warga-header-left h1 {
        font-size: 23px;
    }

    .warga-header-left p {
        font-size: 12px;
    }

    .warga-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .warga-stat-card {
        padding: 15px 12px;
    }

    .warga-stat-card h3 {
        font-size: 11px;
    }

    .warga-stat-card p {
        font-size: 23px !important;
    }

    .warga-filter {
        padding: 16px;
    }
}
/* =================================
   HEADER DAN HAMBURGER RESPONSIVE
================================= */

/* Baris judul */
.warga-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    width: 100%;
}

/* Hamburger default tersembunyi di laptop */
.mobile-menu-button {
    display: none;
}

/* Header tetap terlihat saat halaman di-scroll */
@media (max-width: 800px) {

    .warga-header {
        position: sticky;
        top: 0;
        z-index: 998;
        background: #f4f9f8;
        padding: 10px 0 15px;
        margin-top: -10px;
    }

    .warga-title-row {
        align-items: center;
    }

    .mobile-menu-button {
        display: inline-flex !important;
        position: sticky;
        flex-shrink: 0;

        width: 42px;
        height: 42px;

        align-items: center;
        justify-content: center;

        border: none;
        border-radius: 10px;

        background: linear-gradient(110deg, #0B354D, #17B8C8);
        color: #FFFFFF;

        font-size: 23px;
        cursor: pointer;

        box-shadow: 0 4px 12px rgba(11, 53, 77, .2);
    }

    .warga-header-action {
        justify-content: flex-start;
    }
}
/* =================================
   HEADER DAN HAMBURGER RESPONSIVE
================================= */

/* Hamburger disembunyikan di laptop */
.mobile-menu-button {
    display: none;
}

/* Susunan judul dan hamburger */
.warga-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
}

.warga-title-row > div {
    min-width: 0;
}

/* Tampilan HP dan tablet */
@media (max-width: 800px) {

    .admin-content {
        padding: 18px 20px 25px !important;
    }

    /* Header tetap di atas ketika halaman di-scroll */
    .warga-header {
        position: sticky !important;
        top: 0;
        z-index: 998;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        background: #f4f9f8;
        padding: 8px 0 14px;
        margin-top: 0 !important;
    }

    .warga-header-left {
        flex: 1;
        min-width: 0;
    }

    .warga-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    /* Tombol hamburger di kanan judul */
    .mobile-menu-button {
        display: inline-flex !important;
        position: static !important;
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        align-items: center;
        justify-content: center;

        border: none;
        border-radius: 10px;
        background: linear-gradient(110deg, #0B354D, #17B8C8);
        color: #FFFFFF;
        font-size: 23px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(11, 53, 77, .2);
    }

    .warga-header-action {
        width: 100%;
        justify-content: flex-start;
    }

    .warga-filter-form {
        grid-template-columns: 1fr;
    }

    .btn-warga-filter,
    .btn-warga-reset {
        width: 100%;
    }

    .warga-table-container {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .warga-table {
        min-width: 900px;
    }
}

/* HP kecil */
@media (max-width: 480px) {

    .admin-content {
        padding: 15px 14px 20px !important;
    }

    .warga-header-left h1 {
        font-size: 23px;
    }

    .warga-header-left p {
        font-size: 12px;
    }

    .warga-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .warga-stat-card {
        padding: 15px 12px;
    }

    .warga-stat-card h3 {
        font-size: 11px;
    }

    .warga-stat-card p {
        font-size: 23px !important;
    }

    .warga-filter {
        padding: 16px;
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

        <a href="warga.php" class="sidebar-link active">
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


<!-- =========================
     AREA KANAN
========================= -->

<main class="admin-main">

    <div class="admin-content">

        <div class="warga-page">

            <div class="admin-heading">
                <div class="warga-header">
    <div class="warga-header-left">
    <div class="warga-title-row">
        <div>
            <h1>Data Warga</h1>
            <p>Kelola informasi dan data warga Bank Sampah Digital.</p>
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

    <div class="warga-header-action">
        <a href="dashboard.php" class="btn-kembali-warga">
            ← Kembali ke Dashboard
        </a>
    </div>
</div>

            <!-- =========================
                 STATISTIK
            ========================= -->

            <div class="warga-statistics">

                <div class="warga-stat-card">
                    <h3>Total Warga</h3>
                    <p>
                        <?php echo number_format($total_warga, 0, ',', '.'); ?>
                    </p>
                </div>

                <div class="warga-stat-card">
                    <h3>Warga Aktif</h3>
                    <p>
                        <?php echo number_format($total_aktif, 0, ',', '.'); ?>
                    </p>
                </div>

                <div class="warga-stat-card">
                    <h3>Warga Nonaktif</h3>
                    <p>
                        <?php echo number_format($total_nonaktif, 0, ',', '.'); ?>
                    </p>
                </div>

            </div>

            <!-- =========================
                 FILTER
            ========================= -->

            <div class="warga-filter">

                <form method="GET" class="warga-filter-form">

                    <div class="warga-filter-group">

                        <label for="keyword">
                            Cari Warga
                        </label>

                        <input
                            type="text"
                            id="keyword"
                            name="keyword"
                            value="<?php echo htmlspecialchars($keyword); ?>"
                            placeholder="Nama, username, email, atau nomor HP"
                        >

                    </div>

                    <div class="warga-filter-group">

                        <label for="status">
                            Status
                        </label>

                        <select name="status" id="status">

                            <option value="">
                                Semua Status
                            </option>

                            <option
                                value="aktif"
                                <?php echo $status === 'aktif' ? 'selected' : ''; ?>
                            >
                                Aktif
                            </option>

                            <option
                                value="nonaktif"
                                <?php echo $status === 'nonaktif' ? 'selected' : ''; ?>
                            >
                                Nonaktif
                            </option>

                        </select>

                    </div>

                    <button type="submit" class="btn-warga-filter">
                        Cari
                    </button>

                    <a href="warga.php" class="btn-warga-reset">
                        Reset
                    </a>

                </form>

            </div>

            <!-- =========================
                 TABEL WARGA
            ========================= -->

            <div class="warga-table-container">

                <table class="warga-table">

                    <thead>

                        <tr>
                            <th>No</th>
                            <th>Nama Lengkap</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Nomor HP</th>
                            <th>Alamat</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($data_warga)): ?>

                        <tr>
                            <td colspan="8" class="warga-empty">
                                Tidak ada data warga yang ditemukan.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php $no = 1; ?>

                        <?php foreach ($data_warga as $warga): ?>

                            <tr>

                                <td>
                                    <?php echo $no++; ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $warga['nama_lengkap'] ?? '-'
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $warga['username'] ?? '-'
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $warga['email'] ?? '-'
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $warga['no_hp'] ?? '-'
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $warga['alamat'] ?? '-'
                                    );
                                    ?>
                                </td>

                                <td>

                                    <?php if ($warga['status'] === 'aktif'): ?>

                                        <span class="warga-status warga-status-aktif">
                                            Aktif
                                        </span>

                                    <?php else: ?>

                                        <span class="warga-status warga-status-nonaktif">
                                            Nonaktif
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
<a
    href="detail_warga.php?id=<?php echo (int)$warga['id']; ?>"
    class="warga-action warga-action-detail"
>
    Detail
</a>

<a
    href="edit_warga.php?id=<?php echo (int)$warga['id']; ?>"
    class="warga-action warga-action-edit"
>
    Edit
</a>
                                    <form
                                        method="POST"
                                        action="warga_status.php"
                                        style="display:inline;"
                                        onsubmit="return confirm('Apakah Anda yakin ingin mengubah status warga ini?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?php echo (int)$warga['id']; ?>"
                                        >

                                        <button
                                        type="submit"
                                            class="warga-action <?php echo $warga['status'] === 'aktif' ? 'warga-action-deactivate' : 'warga-action-activate'; ?>"
                                            style="border:0; cursor:pointer;"
                                        >
                                        <?php
                                        echo $warga['status'] === 'aktif'
                                        ? 'Nonaktifkan'
                                        : 'Aktifkan';
                                        ?>
                                        </button>

                                    </form>

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
</script> </body>
</html>
