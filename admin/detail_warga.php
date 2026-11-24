
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../config/koneksi.php';

// ========================================
// AMBIL ID WARGA
// ========================================

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id <= 0) {
    header("Location: warga.php");
    exit;
}

// ========================================
// DATA WARGA
// ========================================

$query_warga = mysqli_prepare(
    $conn,
    "SELECT id, nama_lengkap, username, email, no_hp, alamat, status
     FROM users
     WHERE id = ? AND role = 'warga'
     LIMIT 1"
);

if (!$query_warga) {
    die("Gagal menyiapkan query data warga.");
}

mysqli_stmt_bind_param($query_warga, "i", $id);

if (!mysqli_stmt_execute($query_warga)) {
    mysqli_stmt_close($query_warga);
    die("Gagal mengambil data warga.");
}

$result_warga = mysqli_stmt_get_result($query_warga);

if (!$result_warga || mysqli_num_rows($result_warga) !== 1) {
    mysqli_stmt_close($query_warga);
    header("Location: warga.php");
    exit;
}

$warga = mysqli_fetch_assoc($result_warga);
mysqli_stmt_close($query_warga);

// ========================================
// DATA TABUNGAN
// ========================================

$query_tabungan = mysqli_prepare(
    $conn,
    "SELECT saldo
     FROM tabungan_warga
     WHERE user_id = ?
     LIMIT 1"
);

if (!$query_tabungan) {
    die("Gagal menyiapkan query tabungan.");
}

mysqli_stmt_bind_param($query_tabungan, "i", $id);

if (!mysqli_stmt_execute($query_tabungan)) {
    mysqli_stmt_close($query_tabungan);
    die("Gagal mengambil data tabungan.");
}

$result_tabungan = mysqli_stmt_get_result($query_tabungan);

$saldo = 0;

if ($result_tabungan && mysqli_num_rows($result_tabungan) === 1) {
    $data_tabungan = mysqli_fetch_assoc($result_tabungan);
    $saldo = (float) $data_tabungan['saldo'];
}

mysqli_stmt_close($query_tabungan);

// ========================================
// STATISTIK SETORAN
// ========================================

$query_statistik = mysqli_prepare(
    $conn,
    "SELECT
        COUNT(*) AS total_setoran,
        COALESCE(
            SUM(
                CASE
                    WHEN status = 'diterima' THEN berat_kg
                    ELSE 0
                END
            ), 0
        ) AS total_berat,
        COALESCE(
            SUM(
                CASE
                    WHEN status = 'diterima' THEN total_harga
                    ELSE 0
                END
            ), 0
        ) AS total_nilai
     FROM setoran_sampah
     WHERE user_id = ?"
);

if (!$query_statistik) {
    die("Gagal menyiapkan query statistik.");
}

mysqli_stmt_bind_param($query_statistik, "i", $id);

if (!mysqli_stmt_execute($query_statistik)) {
    mysqli_stmt_close($query_statistik);
    die("Gagal mengambil statistik setoran.");
}

$result_statistik = mysqli_stmt_get_result($query_statistik);

$statistik = [
    'total_setoran' => 0,
    'total_berat' => 0,
    'total_nilai' => 0
];

if ($result_statistik) {
    $data_statistik = mysqli_fetch_assoc($result_statistik);

    if ($data_statistik) {
        $statistik = $data_statistik;
    }
}

mysqli_stmt_close($query_statistik);

// ========================================
// RIWAYAT SETORAN TERBARU
// ========================================

$query_riwayat = mysqli_prepare(
    $conn,
    "SELECT
        s.id,
        s.tanggal_setor,
        s.berat_kg,
        s.total_harga,
        s.status,
        k.nama_kategori
     FROM setoran_sampah s
     LEFT JOIN kategori_sampah k
        ON s.kategori_id = k.id
     WHERE s.user_id = ?
     ORDER BY s.id DESC
     LIMIT 10"
);

if (!$query_riwayat) {
    die("Gagal menyiapkan query riwayat.");
}

mysqli_stmt_bind_param($query_riwayat, "i", $id);

if (!mysqli_stmt_execute($query_riwayat)) {
    mysqli_stmt_close($query_riwayat);
    die("Gagal mengambil riwayat setoran.");
}

$result_riwayat = mysqli_stmt_get_result($query_riwayat);

// ========================================
// DATA TAMBAHAN
// ========================================

$nama_inisial = strtoupper(
    substr($warga['nama_lengkap'], 0, 1)
);

$status_akun = strtolower($warga['status'] ?? '');

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Detail Warga - <?php echo htmlspecialchars($warga['nama_lengkap']); ?>
    </title>

    <link rel="stylesheet" href="../assets/css/style.css">


<style>
    * {
        box-sizing: border-box;
    }

    html,
    body {
        margin: 0;
        min-height: 100%;
        max-width: 100%;
        overflow-x: hidden;
    }

    body {
        background: #F2F7FA;
        font-family: 'Poppins', Arial, sans-serif;
    }

/* Area konten */
.admin-main {
    background: #f4f9f8;
    min-height: 100vh;
}

.admin-content {
    padding: 42px 50px;
    box-sizing: border-box;
}

/* Heading */
.petugas-page .admin-heading h1 {
    color: #0B4650;
    font-size: 30px;
    font-weight: 700;
}

.petugas-page .admin-heading p {
    color: #718995;
    font-size: 14px;
}

/* Tombol kembali */
.petugas-back {
    color: #087987;
    font-weight: 600;
    transition: .2s ease;
}

.petugas-back:hover {
    color: #0B354D;
}

/* Tombol tambah */
.btn-tambah-petugas {
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    border-radius: 9px;
    box-shadow: 0 4px 12px rgba(11, 53, 77, .14);
    transition: .2s ease;
}

.btn-tambah-petugas:hover {
    background: linear-gradient(110deg, #082B40, #109EAF);
    transform: translateY(-2px);
}

/* Notifikasi */
.form-alert-success {
    background: #E2F5F1;
    border: 1px solid #C5E9DF;
    color: #087C72;
}

.form-alert-error {
    background: #FFF0F0;
    border: 1px solid #F3D0D2;
    color: #B42318;
}

/* Kotak tabel */
.warga-table-container {
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(11, 45, 70, .04);
    overflow-x: auto;
}

/* Header tabel */
.warga-table th {
    background: #EAF3F6;
    color: #315A69;
    border-bottom: 1px solid #DCE8EC;
    padding: 16px;
}

/* Isi tabel */
.warga-table td {
    color: #526671;
    padding: 15px 16px;
    border-bottom: 1px solid #EDF2F4;
}

.warga-table tbody tr:hover {
    background: #F2F9FA;
}

.warga-table tbody tr:last-child td {
    border-bottom: none;
}

/* Kolom status */
.warga-table td:nth-child(7) {
    color: #087C72;
    font-weight: 600;
}

/* Tombol edit */
.btn-edit-petugas {
    background: linear-gradient(110deg, #0B354D, #159BAE);
    color: #FFFFFF;
    border-radius: 8px;
    transition: .2s ease;
}

.btn-edit-petugas:hover {
    transform: translateY(-2px);
}

/* Tombol hapus */
.btn-hapus-petugas {
    background: #FFF0F0;
    color: #B42318;
    border: 1px solid #F5D7D7;
    border-radius: 8px;
    transition: .2s ease;
}

.btn-hapus-petugas:hover {
    background: #FCE0E0;
}

/* Jarak tombol aksi */
.petugas-action-buttons {
    display: flex;
    align-items: center;
    gap: 7px;
}

.petugas-action-buttons form {
    margin: 0;
}


/* TOMBOL KEMBALI - SAMA PERSIS DENGAN DATA WARGA */
.petugas-page .btn-login-submit {
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

.petugas-page .btn-login-submit:hover {
    background: linear-gradient(110deg, #082B40, #109EAF) !important;
    transform: translateY(-2px);
}
/* SEJAJARKAN TOMBOL KEMBALI DAN TAMBAH PETUGAS */
.petugas-page-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    width: 100%;
    margin: 15px 0 24px;
}

/* Hilangkan jarak tambahan tombol */
.petugas-page-actions .btn-login-submit,
.petugas-page-actions .btn-tambah-petugas {
    margin: 0 !important;
    min-height: 45px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* PERBAIKAN PROPORSI TOMBOL PETUGAS */
.petugas-page-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    width: 100%;
    margin: 15px 0 24px;
}

/* Tombol kembali */
.petugas-page-actions .btn-login-submit {
    width: auto !important;
    min-width: 0;
    flex: 0 0 auto;
    min-height: 45px;
    padding: 0 20px !important;
    white-space: nowrap;
    border-radius: 10px;
}

/* Tombol tambah */
.petugas-page-actions .btn-tambah-petugas {
    width: auto !important;
    min-width: 0;
    flex: 0 0 auto;
    min-height: 45px;
    padding: 0 20px;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
}


    /* ==========================================
       LAYOUT UTAMA
    ========================================== */

    .admin-main {
        width: calc(100% - 250px) !important;
        min-width: 0 !important;
        min-height: 100vh !important;
        margin-left: 250px !important;
        background: #F2F7FA;
    }

    .admin-content {
        width: 100%;
        max-width: 1450px;
        margin: 0 auto;
        padding: 34px 38px;
    }

    /* ==========================================
       HEADER
    ========================================== */

    .detail-page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 26px;
    }

    .detail-page-title h1 {
        margin: 0;
        color: #0B354D;
        font-size: 28px;
        font-weight: 750;
        line-height: 1.3;
    }

    .detail-page-title p {
        margin: 7px 0 0;
        color: #718995;
        font-size: 13px;
        line-height: 1.6;
    }

    .detail-header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .detail-warga-back {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        padding: 11px 17px;
        border-radius: 9px;

        color: #fff;
        background: linear-gradient(135deg, #0B354D, #159BAE);

        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;

        box-shadow: 0 4px 12px rgba(11,53,77,.13);
        transition: .2s ease;
    }

    .detail-warga-back:hover {
        color: #fff;
        transform: translateY(-2px);
    }

    .mobile-menu-button {
        display: none;

        width: 42px;
        height: 42px;

        align-items: center;
        justify-content: center;

        border: none;
        border-radius: 10px;

        color: #fff;
        background: linear-gradient(110deg, #0B354D, #17B8C8);

        font-size: 22px;
        cursor: pointer;

        box-shadow: 0 4px 12px rgba(11,53,77,.2);
    }

    .sidebar-overlay {
        display: none;
    }

    /* ==========================================
       KARTU PROFIL WARGA
    ========================================== */

    .detail-warga-profile {
        padding: 26px;
        margin-bottom: 22px;

        border: 1px solid #E1EAED;
        border-radius: 16px;

        background: #fff;
        box-shadow: 0 5px 18px rgba(11,45,70,.045);
    }

    .detail-warga-profile-top {
        display: flex;
        align-items: center;
        gap: 16px;

        margin-bottom: 24px;
        padding-bottom: 22px;

        border-bottom: 1px solid #EAF0F4;
    }

    .detail-warga-avatar {
        width: 64px;
        height: 64px;
        min-width: 64px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 50%;

        color: #fff;
        background: linear-gradient(135deg, #0B354D, #18B8C8);

        font-size: 25px;
        font-weight: 700;

        box-shadow: 0 5px 14px rgba(11,53,77,.15);
    }

    .detail-warga-profile-name {
        min-width: 0;
    }

    .detail-warga-profile-name h2 {
        margin: 0 0 5px;

        color: #0B354D;
        font-size: 20px;
        font-weight: 700;

        overflow-wrap: anywhere;
    }

    .detail-warga-profile-name p {
        margin: 0;
        color: #82919C;
        font-size: 13px;
    }

    .detail-warga-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        margin-left: auto;
        padding: 7px 13px;

        border-radius: 30px;

        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }

    .detail-warga-status.aktif {
        color: #16804A;
        background: #E2F8EB;
    }

    .detail-warga-status.nonaktif {
        color: #B83232;
        background: #FDE8E8;
    }

    .detail-warga-info {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .detail-warga-info-item {
        min-width: 0;
        padding: 15px 17px;

        border: 1px solid #E8EEF2;
        border-radius: 10px;

        background: #F8FAFB;
    }

    .detail-warga-info-item span {
        display: block;
        margin-bottom: 7px;

        color: #82919C;
        font-size: 11px;
        font-weight: 600;
    }

    .detail-warga-info-item strong {
        display: block;

        color: #294456;
        font-size: 13px;
        font-weight: 600;

        overflow-wrap: anywhere;
        line-height: 1.6;
    }

    /* ==========================================
       STATISTIK
    ========================================== */

    .detail-warga-statistics {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 22px;
    }

    .detail-warga-stat {
        position: relative;
        min-width: 0;

        padding: 21px;
        overflow: hidden;

        border: 1px solid #E1EAED;
        border-radius: 14px;

        background: #fff;
        box-shadow: 0 5px 18px rgba(11,45,70,.04);
    }

    .detail-warga-stat::after {
        content: "";

        position: absolute;
        top: 0;
        left: 0;

        width: 4px;
        height: 100%;

        background: #18C3D8;
    }

    .detail-warga-stat-label {
        display: flex;
        align-items: center;
        gap: 9px;

        margin-bottom: 13px;

        color: #718995;
        font-size: 12px;
        font-weight: 600;
    }

    .detail-warga-stat-icon {
        width: 32px;
        height: 32px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 9px;
        background: #E8F8FA;

        font-size: 17px;
    }

    .detail-warga-stat strong {
        display: block;

        color: #0B354D;
        font-size: 23px;
        font-weight: 750;
        line-height: 1.3;

        overflow-wrap: anywhere;
    }

    .detail-warga-stat small {
        display: block;
        margin-top: 6px;

        color: #9AA8B1;
        font-size: 11px;
    }

    /* ==========================================
       RIWAYAT SETORAN
    ========================================== */

    .detail-warga-section {
        overflow: hidden;

        border: 1px solid #E1EAED;
        border-radius: 16px;

        background: #fff;
        box-shadow: 0 5px 18px rgba(11,45,70,.04);
    }

    .detail-warga-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;

        padding: 20px 23px;

        border-bottom: 1px solid #EAF0F4;
    }

    .detail-warga-section-header h2 {
        margin: 0;

        color: #0B354D;
        font-size: 17px;
        font-weight: 700;
    }

    .detail-warga-section-header p {
        margin: 5px 0 0;

        color: #82919C;
        font-size: 11px;
    }

    .detail-warga-table-wrapper {
        width: 100%;
        overflow-x: auto;
    }

    .detail-warga-table {
        width: 100%;
        min-width: 650px;
        border-collapse: collapse;
    }

    .detail-warga-table th {
        padding: 14px 18px;
        text-align: left;

        color: #718995;
        background: #F7FAFB;

        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }

    .detail-warga-table td {
        padding: 15px 18px;

        border-top: 1px solid #EDF1F4;

        color: #405665;
        font-size: 12px;
        white-space: nowrap;
    }

    .detail-warga-table tbody tr {
        transition: background .2s ease;
    }

    .detail-warga-table tbody tr:hover {
        background: #F5FBFC;
    }

    .status-setoran {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        padding: 6px 11px;
        border-radius: 30px;

        font-size: 10px;
        font-weight: 700;
    }

    .status-setoran.diterima {
        color: #16804A;
        background: #E2F8EB;
    }

    .status-setoran.menunggu {
        color: #946200;
        background: #FFF4D6;
    }

    .status-setoran.ditolak {
        color: #B83232;
        background: #FDE8E8;
    }

    .detail-warga-empty {
        padding: 42px 20px;
        text-align: center;

        color: #82919C;
        font-size: 13px;
    }

    .detail-warga-empty-icon {
        display: block;
        margin-bottom: 10px;
        font-size: 30px;
    }

    /* ==========================================
       RESPONSIVE TABLET
    ========================================== */

    @media (max-width: 800px) {

        .admin-sidebar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            bottom: 0 !important;

            width: 250px !important;
            height: 100vh !important;

            transform: translateX(-100%) !important;
            transition: transform .3s ease !important;

            overflow-y: auto !important;
            overflow-x: hidden !important;
        }

        .admin-sidebar.open {
            transform: translateX(0) !important;
        }

        .sidebar-overlay.show {
            display: block !important;
            position: fixed !important;
            inset: 0 !important;

            background: rgba(4,25,39,.55) !important;
            z-index: 1000 !important;
        }

        .admin-main {
            width: 100% !important;
            margin-left: 0 !important;
        }

        .admin-content {
            padding: 25px 20px;
        }

        .mobile-menu-button {
            display: inline-flex;
        }

        .detail-page-title h1 {
            font-size: 24px;
        }

        .detail-warga-statistics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    /* ==========================================
       RESPONSIVE HP
    ========================================== */

    @media (max-width: 550px) {

        .admin-content {
            padding: 20px 14px 28px;
        }

        .detail-page-header {
            align-items: flex-start;
            gap: 10px;
        }

        .detail-page-title h1 {
            font-size: 20px;
        }

        .detail-page-title p {
            font-size: 11px;
        }

        .detail-header-actions {
            gap: 7px;
        }

        .mobile-menu-button {
            width: 39px;
            height: 39px;
            min-width: 39px;
            font-size: 20px;
        }

        .detail-warga-back {
            padding: 10px 11px;
            font-size: 10px;
        }

        .detail-warga-profile {
            padding: 18px 15px;
        }

        .detail-warga-profile-top {
            flex-wrap: wrap;
            gap: 12px;
        }

        .detail-warga-avatar {
            width: 52px;
            height: 52px;
            min-width: 52px;
            font-size: 21px;
        }

        .detail-warga-profile-name {
            flex: 1;
        }

        .detail-warga-profile-name h2 {
            font-size: 16px;
        }

        .detail-warga-status {
            margin-left: 0;
        }

        .detail-warga-info {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .detail-warga-statistics {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .detail-warga-stat {
            padding: 18px;
        }

        .detail-warga-stat strong {
            font-size: 21px;
        }

        .detail-warga-section-header {
            padding: 17px 15px;
        }

        .detail-warga-section-header h2 {
            font-size: 15px;
        }
    }
</style>


</head>

<body>

<!-- ==========================================
     OVERLAY SIDEBAR
========================================== -->

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ==========================================
     SIDEBAR ADMIN
========================================== -->

<aside class="admin-sidebar" id="adminSidebar">

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

        <div class="sidebar-section-title">Pengelolaan</div>

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

        <a href="pengumuman.php" class="sidebar-link">
            <span class="sidebar-icon">📢</span>
            <span>Kelola Pengumuman</span>
        </a>

        <a href="log_aktivitas.php" class="sidebar-link">
            <span class="sidebar-icon">📋</span>
            <span>Log Aktivitas</span>
        </a>

        <div class="sidebar-section-title">Akun</div>

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

<!-- ==========================================
     KONTEN UTAMA
========================================== -->

<main class="admin-main">

    <div class="admin-content">

        <!-- HEADER -->

        <div class="detail-page-header">

            <div class="detail-page-title">

                <h1>Detail Warga</h1>

                <p>
                    Informasi profil dan aktivitas warga Bank Sampah Digital.
                </p>

            </div>

            <div class="detail-header-actions">

                <button
                    type="button"
                    class="mobile-menu-button"
                    id="mobileMenuButton"
                    aria-label="Buka menu"
                    aria-expanded="false"
                >
                    ☰
                </button>

                <a href="warga.php" class="detail-warga-back">
                    ← Kembali ke Dashboard
                </a>

            </div>

        </div>

        <!-- ==================================
             PROFIL WARGA
        ================================== -->

        <div class="detail-warga-profile">

            <div class="detail-warga-profile-top">

                <div class="detail-warga-avatar">
                    <?php echo htmlspecialchars($nama_inisial); ?>
                </div>

                <div class="detail-warga-profile-name">

                    <h2>
                        <?php echo htmlspecialchars($warga['nama_lengkap']); ?>
                    </h2>

                    <p>
                        @<?php echo htmlspecialchars($warga['username']); ?>
                    </p>

                </div>

                <span class="detail-warga-status <?php echo $status_akun === 'aktif' ? 'aktif' : 'nonaktif'; ?>">

                    <?php echo ucfirst(htmlspecialchars($warga['status'] ?? 'Tidak diketahui')); ?>

                </span>

            </div>

            <div class="detail-warga-info">

                <div class="detail-warga-info-item">

                    <span>Email</span>

                    <strong>
                        <?php echo htmlspecialchars($warga['email'] ?? '-'); ?>
                    </strong>

                </div>

                <div class="detail-warga-info-item">

                    <span>Nomor HP</span>

                    <strong>
                        <?php echo htmlspecialchars($warga['no_hp'] ?: '-'); ?>
                    </strong>

                </div>

                <div class="detail-warga-info-item">

                    <span>Alamat</span>

                    <strong>
                        <?php echo htmlspecialchars($warga['alamat'] ?: '-'); ?>
                    </strong>

                </div>

                <div class="detail-warga-info-item">

                    <span>ID Warga</span>

                    <strong>
                        #<?php echo (int) $warga['id']; ?>
                    </strong>

                </div>

            </div>

        </div>

        <!-- ==================================
             STATISTIK WARGA
        ================================== -->

        <div class="detail-warga-statistics">

            <!-- SALDO TABUNGAN -->

            <div class="detail-warga-stat">

                <div class="detail-warga-stat-label">

                    <span class="detail-warga-stat-icon">💰</span>

                    <span>Saldo Tabungan</span>

                </div>

                <strong>
                    Rp <?php echo number_format($saldo, 0, ',', '.'); ?>
                </strong>

                <small>Saldo tabungan saat ini</small>

            </div>

            <!-- TOTAL SETORAN -->

            <div class="detail-warga-stat">

                <div class="detail-warga-stat-label">

                    <span class="detail-warga-stat-icon">📦</span>

                    <span>Total Setoran</span>

                </div>

                <strong>
                    <?php
                    echo number_format(
                        (int) $statistik['total_setoran'],
                        0,
                        ',',
                        '.'
                    );
                    ?>
                </strong>

                <small>Semua pengajuan setoran</small>

            </div>

            <!-- TOTAL BERAT -->

            <div class="detail-warga-stat">

                <div class="detail-warga-stat-label">

                    <span class="detail-warga-stat-icon">♻️</span>

                    <span>Total Berat Diterima</span>

                </div>

                <strong>

                    <?php
                    echo number_format(
                        (float) $statistik['total_berat'],
                        2,
                        ',',
                        '.'
                    );
                    ?>

                    kg

                </strong>

                <small>Total berat setoran diterima</small>

            </div>

        </div>

        <!-- ==================================
             RIWAYAT SETORAN
        ================================== -->

        <div class="detail-warga-section">

            <div class="detail-warga-section-header">

                <div>

                    <h2>Riwayat Setoran</h2>

                    <p>
                        Menampilkan maksimal 10 riwayat setoran terbaru warga.
                    </p>

                </div>

            </div>

            <div class="detail-warga-table-wrapper">

                <?php if ($result_riwayat && mysqli_num_rows($result_riwayat) > 0): ?>

                    <table class="detail-warga-table">

                        <thead>

                            <tr>
                                <th>Tanggal</th>
                                <th>Kategori</th>
                                <th>Berat</th>
                                <th>Nilai</th>
                                <th>Status</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php while ($riwayat = mysqli_fetch_assoc($result_riwayat)): ?>

                                <?php

                                $tanggal = strtotime(
                                    $riwayat['tanggal_setor']
                                );

                                $status_setoran = strtolower(
                                    trim($riwayat['status'] ?? '')
                                );

                                $status_class = str_replace(
                                    ' ',
                                    '-',
                                    $status_setoran
                                );

                                if (
                                    $status_class === 'menunggu-konfirmasi' ||
                                    $status_class === 'menunggu'
                                ) {
                                    $status_class = 'menunggu';
                                }

                                ?>

                                <tr>

                                    <td>

                                        <?php

                                        echo $tanggal !== false
                                            ? date('d/m/Y', $tanggal)
                                            : '-';

                                        ?>

                                    </td>

                                    <td>

                                        <?php

                                        echo htmlspecialchars(
                                            $riwayat['nama_kategori'] ?? '-'
                                        );

                                        ?>

                                    </td>

                                    <td>

                                        <?php

                                        echo number_format(
                                            (float) $riwayat['berat_kg'],
                                            2,
                                            ',',
                                            '.'
                                        );

                                        ?>

                                        kg

                                    </td>

                                    <td>

                                        Rp

                                        <?php

                                        echo number_format(
                                            (float) $riwayat['total_harga'],
                                            0,
                                            ',',
                                            '.'
                                        );

                                        ?>

                                    </td>

                                    <td>

                                        <span class="status-setoran <?php echo htmlspecialchars($status_class); ?>">

                                            <?php

                                            echo ucfirst(
                                                htmlspecialchars(
                                                    $riwayat['status'] ?? '-'
                                                )
                                            );

                                            ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        </tbody>

                    </table>

                <?php else: ?>

                    <div class="detail-warga-empty">

                        <span class="detail-warga-empty-icon">📭</span>

                        Belum ada riwayat setoran warga ini.

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</main>

<!-- ==========================================
     JAVASCRIPT HAMBURGER SIDEBAR
========================================== -->

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

        menuButton.innerHTML = '✕';

        menuButton.setAttribute(
            'aria-expanded',
            'true'
        );

    }

    function closeSidebar() {

        sidebar.classList.remove('open');
        overlay.classList.remove('show');

        menuButton.innerHTML = '☰';

        menuButton.setAttribute(
            'aria-expanded',
            'false'
        );

    }

    menuButton.addEventListener('click', function () {

        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }

    });

    overlay.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('.sidebar-link').forEach(function (link) {

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
