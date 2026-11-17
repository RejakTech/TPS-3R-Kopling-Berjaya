
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
$penarikan_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
// Token keamanan formulir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil seluruh data pengajuan penarikan
$query = mysqli_query(
    $conn,
    "SELECT
        p.*,
        u.nama_lengkap,
        u.username
     FROM penarikan_tabungan p
     INNER JOIN users u ON p.user_id = u.id
     ORDER BY p.id DESC"
);

$data_penarikan = [];

$total_pengajuan = 0;
$total_menunggu = 0;
$total_disetujui = 0;
$total_ditolak = 0;

while ($row = mysqli_fetch_assoc($query)) {

    $data_penarikan[] = $row;

    $total_pengajuan++;

    if ($row['status'] == 'menunggu') {
        $total_menunggu++;
    } elseif ($row['status'] == 'disetujui') {
        $total_disetujui++;
    } elseif ($row['status'] == 'ditolak') {
        $total_ditolak++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pengelolaan Penarikan - Admin</title>

    <link rel="stylesheet" href="../assets/css/style.css">

<style>

/* =========================================
   PENARIKAN TABUNGAN
   NAVY - TEAL - CYAN
   KONSISTEN DENGAN MUTASI TABUNGAN
========================================= */

* {
    box-sizing: border-box;
}

html,
body {
    width: 100%;
    min-height: 100%;
    margin: 0;
}

body {
    background: #f4f9f8;
    overflow-x: hidden;
}

/* AREA KONTEN */

.admin-main {
    margin-left: 250px;
    width: calc(100% - 250px);
    min-height: 100vh;
    background: #f4f9f8;
    box-sizing: border-box;
    overflow-x: hidden;
}

.admin-content {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 38px 42px;
    box-sizing: border-box;
}

.penarikan-page {
    width: 100%;
    max-width: none;
    min-width: 0;
    margin: 0;
}

/* HEADER */

.admin-heading {
    margin-bottom: 8px;
}

.admin-heading h1 {
    margin: 0 0 7px;
    color: #0B4650;
    font-size: 29px;
    font-weight: 700;
    line-height: 1.3;
}

.admin-heading p {
    margin: 0;
    color: #718995;
    font-size: 13px;
    line-height: 1.6;
}

/* TOMBOL KEMBALI */

.btn-kembali-penarikan {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 40px;
    padding: 10px 18px;
    margin: 12px 0 25px;
    border-radius: 9px;
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    color: #fff;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(11, 53, 77, .13);
    transition: .2s ease;
}

.btn-kembali-penarikan:hover {
    background: linear-gradient(110deg, #082B40, #109EAF);
    color: #fff;
    transform: translateY(-2px);
}

/* ALERT */

.form-alert-success,
.form-alert-error {
    padding: 14px 17px;
    margin-bottom: 20px;
    border-radius: 10px;
    font-size: 13px;
    line-height: 1.6;
    transition: opacity .4s ease;
}

.form-alert-success {
    color: #176b3a;
    background: #e5f7eb;
    border: 1px solid #b9e7ca;
}

.form-alert-error {
    color: #a52b2b;
    background: #fdeaea;
    border: 1px solid #f4caca;
}

.form-alert-hide {
    opacity: 0;
}

/* STATISTIK */

.admin-statistics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    width: 100%;
    margin-bottom: 27px;
}

.admin-stat-card {
    position: relative;
    overflow: hidden;
    min-width: 0;
    background: #fff;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    padding: 23px 24px;
    box-shadow: 0 4px 15px rgba(11, 45, 70, .045);
    transition: .2s ease;
}

.admin-stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(180deg, #159BAE, #17B8C8);
}

.admin-stat-card:nth-child(2)::before {
    background: linear-gradient(180deg, #E49A0F, #F5BD48);
}

.admin-stat-card:nth-child(3)::before {
    background: linear-gradient(180deg, #0D9488, #34BFA8);
}

.admin-stat-card:nth-child(4)::before {
    background: linear-gradient(180deg, #DC6565, #F39A9A);
}

.admin-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(11, 45, 70, .08);
    border-color: #C9E1E6;
}

.admin-stat-card h3 {
    display: block;
    color: #718995;
    font-size: 12px;
    font-weight: 600;
    margin: 0 0 12px;
}

.admin-stat-card p {
    display: block;
    color: #159BAE;
    font-size: 27px;
    font-weight: 700;
    line-height: 1.3;
    margin: 0;
    overflow-wrap: anywhere;
}

.admin-stat-card:nth-child(2) p {
    color: #D58B0A;
}

.admin-stat-card:nth-child(3) p {
    color: #0D9488;
}

.admin-stat-card:nth-child(4) p {
    color: #DC6565;
}

/* SECTION TABEL */

.admin-penarikan-section {
    width: 100%;
    min-width: 0;
    padding: 25px;
    margin-bottom: 25px;
    background: #fff;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(11, 45, 70, .035);
}

.admin-penarikan-heading {
    margin-bottom: 22px;
}

.admin-penarikan-heading h2 {
    color: #0B4650;
    font-size: 19px;
    font-weight: 700;
    margin: 0 0 8px;
}

.admin-penarikan-heading p {
    color: #718995;
    font-size: 13px;
    line-height: 1.6;
    margin: 0;
}

/* TABEL RESPONSIVE */

.warga-table-container {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    border: 1px solid #E1EAED;
    border-radius: 10px;
    -webkit-overflow-scrolling: touch;
}

.warga-table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
    background: #fff;
}

.warga-table thead {
    background: #EAF3F6;
    color: #315A69;
}

.warga-table th {
    padding: 15px 14px;
    color: #315A69;
    font-size: 11px;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
    border-bottom: 1px solid #DCE8EC;
}

.warga-table td {
    padding: 15px 14px;
    color: #526671;
    font-size: 12px;
    line-height: 1.6;
    border-bottom: 1px solid #EDF2F3;
    vertical-align: middle;
}

.warga-table tbody tr {
    transition: background .2s ease;
}

.warga-table tbody tr:hover {
    background: #F2F9FA;
}

.warga-table tbody tr:last-child td {
    border-bottom: none;
}

.warga-table td strong {
    color: #0B4650;
    font-weight: 700;
}

.warga-table td small {
    color: #91A3AA;
    font-size: 11px;
}

/* STATUS */

.status-penarikan {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.status-pending {
    background: #FFF3D5;
    color: #95620B;
}

.status-approved {
    background: #E2F5F1;
    color: #087C72;
}

.status-rejected {
    background: #FCEBEC;
    color: #B42318;
}

/* FORM PROSES */

.form-proses-penarikan {
    display: flex;
    flex-direction: column;
    gap: 9px;
    min-width: 160px;
}

.form-proses-penarikan textarea {
    width: 100%;
    min-height: 65px;
    padding: 10px 11px;
    border: 1px solid #DCE7EC;
    border-radius: 8px;
    background: #fff;
    color: #344D59;
    font-family: inherit;
    font-size: 12px;
    outline: none;
    resize: vertical;
}

.form-proses-penarikan textarea:focus {
    border-color: #17AEBE;
    box-shadow: 0 0 0 3px rgba(23, 184, 200, .12);
}

/* TOMBOL AKSI */

.btn-setujui-penarikan,
.btn-tolak-penarikan {
    width: 100%;
    min-height: 36px;
    padding: 9px 12px;
    border: none;
    border-radius: 8px;
    color: #fff;
    font-family: inherit;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: .2s ease;
}

.btn-setujui-penarikan {
    background: linear-gradient(110deg, #0D9488, #34BFA8);
}

.btn-tolak-penarikan {
    background: linear-gradient(110deg, #D84B4B, #ED7777);
}

.btn-setujui-penarikan:hover,
.btn-tolak-penarikan:hover {
    opacity: .88;
    transform: translateY(-1px);
}

button:disabled {
    opacity: .6;
    cursor: not-allowed;
}

/* HIGHLIGHT */

.penarikan-highlight {
    background: #EAF8FA !important;
    outline: 2px solid #17B8C8;
    outline-offset: -2px;
}

/* TABLET */

@media screen and (max-width: 1100px) {
    .admin-content {
        padding: 30px 25px;
    }

    .admin-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 13px;
    }

    .admin-stat-card {
        padding: 20px;
    }

    .admin-stat-card p {
        font-size: 24px;
    }

    .admin-penarikan-section {
        padding: 20px;
    }
}

/* HP */

@media screen and (max-width: 700px) {
    .admin-main {
        margin-left: 0;
        width: 100%;
    }

    .admin-content {
        padding: 22px 16px;
    }

    .admin-heading h1 {
        font-size: 23px;
    }

    .admin-heading p {
        font-size: 12px;
    }

    .btn-kembali-penarikan {
        width: 100%;
        min-height: 40px;
        margin-bottom: 22px;
    }

    .admin-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 11px;
        margin-bottom: 22px;
    }

    .admin-stat-card {
        padding: 17px 15px;
        border-radius: 12px;
    }

    .admin-stat-card h3 {
        font-size: 10px;
    }

    .admin-stat-card p {
        font-size: 21px;
    }

    .admin-penarikan-section {
        padding: 15px;
        border-radius: 12px;
    }

    .admin-penarikan-heading h2 {
        font-size: 16px;
    }

    .admin-penarikan-heading p {
        font-size: 12px;
    }

    .warga-table {
        min-width: 1000px;
    }

    .warga-table th,
    .warga-table td {
        padding: 11px 10px;
        font-size: 11px;
    }
}

/* HP KECIL */

@media screen and (max-width: 380px) {
    .admin-content {
        padding: 18px 12px;
    }

    .admin-statistics {
        grid-template-columns: 1fr;
        gap: 9px;
    }

    .admin-stat-card {
        padding: 15px;
    }

    .admin-stat-card p {
        font-size: 20px;
    }

    .admin-penarikan-section {
        padding: 12px;
    }

    .admin-heading h1 {
        font-size: 20px;
    }
}
.admin-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 28px;
}

.admin-heading h1 {
    margin: 0;
}

.admin-heading p {
    margin: 6px 0 0;
}

.btn-kembali-penarikan {
    flex-shrink: 0;
    align-self: center;
}

/* Tampilan HP */
@media (max-width: 600px) {
    .admin-heading {
        flex-direction: column;
        align-items: flex-start;
    }

    .btn-kembali-penarikan {
        align-self: flex-start;
    }
}
/* =========================================
   RESPONSIVE SIDEBAR DAN HAMBURGER
========================================= */

.mobile-menu-button {
    display: none !important;
}

/* TAMPILAN HP DAN TABLET */
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
        min-height: 100vh;
    }

    .admin-content {
        width: 100% !important;
        padding: 22px 16px !important;
    }

    /* HEADER */
    .admin-heading {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 13px !important;
        margin-bottom: 22px !important;
    }

    .admin-heading-title {
        width: 100%;
        min-width: 0;
    }

    .admin-title-line {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        width: 100%;
    }

    .admin-heading h1 {
        font-size: 23px !important;
        line-height: 1.3;
        margin: 0 !important;
    }

    .admin-heading p {
        font-size: 12px !important;
        margin-top: 7px !important;
    }

    /* TOMBOL HAMBURGER */
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
        color: #FFFFFF !important;
        font-size: 23px !important;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(11, 53, 77, .18);
    }

    /* TOMBOL KEMBALI */
    .btn-kembali-penarikan {
        align-self: flex-start !important;
        width: auto !important;
        margin: 0 0 8px !important;
    }

    /* STATISTIK */
    .admin-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 11px !important;
    }

    .admin-stat-card {
        min-width: 0;
        padding: 17px 15px !important;
    }

    .admin-stat-card h3 {
        font-size: 10px !important;
    }

    .admin-stat-card p {
        font-size: 20px !important;
    }

    /* TABEL */
    .admin-penarikan-section {
        width: 100%;
        padding: 15px !important;
        overflow: hidden;
    }

    .warga-table-container {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }

    .warga-table {
        min-width: 1000px !important;
    }
}

/* HP KECIL */
@media screen and (max-width: 380px) {

    .admin-content {
        padding: 18px 12px !important;
    }

    .admin-heading h1 {
        font-size: 20px !important;
    }

    .admin-stat-card {
        padding: 14px 11px !important;
    }

    .admin-stat-card p {
        font-size: 18px !important;
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

<div class="admin-dashboard">

    <!-- OVERLAY SIDEBAR -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

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

        <a href="penarikan.php" class="sidebar-link active">
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

<!-- KONTEN PENARIKAN -->
<main class="admin-main">
    <div class="admin-content">
        <div class="penarikan-page">

        <!-- HEADER -->
<div class="admin-heading">

    <div class="admin-heading-title">

        <div class="admin-title-line">
            <h1>Pengelolaan Penarikan Tabungan</h1>

            <button
                type="button"
                class="mobile-menu-button"
                id="mobileMenuButton"
                aria-label="Buka menu"
            >
                ☰
            </button>
        </div>

        <p>Kelola pengajuan penarikan tabungan warga.</p>

    </div>

    <a href="dashboard.php" class="btn-kembali-penarikan">
        ← Kembali ke Dashboard
    </a>

</div>

        
<?php if (isset($_GET['status'])) : ?>

    <?php if ($_GET['status'] == 'disetujui') : ?>

        <div class="form-alert-success">
            Penarikan berhasil disetujui. Saldo warga telah diperbarui.
        </div>

    <?php elseif ($_GET['status'] == 'ditolak') : ?>

        <div class="form-alert-success">
            Pengajuan penarikan berhasil ditolak.
        </div>

    <?php elseif ($_GET['status'] == 'saldo_tidak_cukup') : ?>

        <div class="form-alert-error">
            Saldo warga tidak mencukupi. Penarikan tidak dapat disetujui.
        </div>

    <?php elseif ($_GET['status'] == 'sudah_diproses') : ?>

        <div class="form-alert-error">
            Pengajuan ini sudah pernah diproses.
        </div>

    <?php elseif ($_GET['status'] == 'saldo_gagal') : ?>

        <div class="form-alert-error">
            Rekening tabungan warga tidak ditemukan.
        </div>

    <?php elseif ($_GET['status'] == 'alasan_kosong') : ?>

        <div class="form-alert-error">
            Alasan penolakan wajib diisi.
        </div>

    <?php elseif ($_GET['status'] == 'token_gagal') : ?>

        <div class="form-alert-error">
            Permintaan tidak valid. Silakan muat ulang halaman.
        </div>

    <?php elseif ($_GET['status'] == 'data_gagal') : ?>

        <div class="form-alert-error">
            Data pengajuan tidak ditemukan atau tidak valid.
        </div>

    <?php elseif ($_GET['status'] == 'catatan_gagal') : ?>

        <div class="form-alert-error">
            Catatan admin maksimal 5000 karakter.
        </div>

    <?php elseif ($_GET['status'] == 'proses_gagal') : ?>

        <div class="form-alert-error">
            Proses penarikan gagal. Silakan coba kembali.
        </div>

    <?php endif; ?>

<?php endif; ?>



        <!-- RINGKASAN -->

        <div class="admin-statistics">

            <div class="admin-stat-card">

                <h3>Total Pengajuan</h3>

                <p><?php echo $total_pengajuan; ?></p>

            </div>

            <div class="admin-stat-card">

                <h3>Menunggu</h3>

                <p><?php echo $total_menunggu; ?></p>

            </div>

            <div class="admin-stat-card">

                <h3>Disetujui</h3>

                <p><?php echo $total_disetujui; ?></p>

            </div>

            <div class="admin-stat-card">

                <h3>Ditolak</h3>

                <p><?php echo $total_ditolak; ?></p>

            </div>

        </div>

        <!-- DAFTAR PENARIKAN -->

        <section class="admin-penarikan-section">

            <div class="admin-penarikan-heading">

                <h2>Daftar Pengajuan Penarikan</h2>

                <p>
                    Periksa informasi pengajuan sebelum memberikan persetujuan.
                </p>

            </div>

            <div class="warga-table-container">

                <table class="warga-table">

                    <thead>

                        <tr>
                            <th>No</th>
                            <th>Nama Warga</th>
                            <th>Nominal</th>
                            <th>Metode</th>
                            <th>Informasi Rekening</th>
                            <th>Tanggal Pengajuan</th>
                            <th>Status</th>
                            <th>Aksi</th>
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

                    <strong>
                        <?php echo htmlspecialchars(
                            $penarikan['nama_lengkap']
                        ); ?>
                    </strong>

                    <br>

                    <small>
                        @<?php echo htmlspecialchars(
                            $penarikan['username']
                        ); ?>
                    </small>

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

                    <?php echo $penarikan['metode_penarikan'] == 'tunai'
                        ? 'Tunai'
                        : 'Transfer'; ?>

                </td>

                <td>

                    <?php if ($penarikan['metode_penarikan'] == 'transfer') : ?>

                        <strong>
                            <?php echo htmlspecialchars(
                                $penarikan['nama_pemilik_rekening'] ?? '-'
                            ); ?>
                        </strong>

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

                    <?php echo date(
                        'd/m/Y H:i',
                        strtotime($penarikan['tanggal_pengajuan'])
                    ); ?>

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

                    <?php endif; ?>

                </td>

                <!-- AKSI -->
                <td>

                    <?php if ($penarikan['status'] == 'menunggu') : ?>

                            <form
                                action="proses_penarikan.php"
                                method="POST"
                                class="form-proses-penarikan"
                            >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
                            >

                            <input
                                type="hidden"
                                name="penarikan_id"
                                value="<?php echo (int) $penarikan['id']; ?>"
                            >

                            <textarea
                                name="catatan_admin"
                                placeholder="Alasan penolakan (wajib jika ditolak)"
                                rows="2"
                                maxlength="5000"
                            ></textarea>

                            <button
                                type="submit"
                                name="aksi"
                                value="disetujui"
                                class="btn-setujui-penarikan"
                                onclick="return confirm('Apakah kamu yakin ingin menyetujui penarikan ini?')"
                            >
                                Setujui
                            </button>

                            <button
                                type="submit"
                                name="aksi"
                                value="ditolak"
                                class="btn-tolak-penarikan"
                                onclick="return confirm('Apakah kamu yakin ingin menolak penarikan ini?')"
                            >
                                Tolak
                            </button>

                        </form>

                    <?php else : ?>

                        <span style="color:#718783;">
                            Sudah diproses
                        </span>

                    <?php endif; ?>

                </td>

            </tr>

        <?php endforeach; ?>

    <?php else : ?>

        <tr>

            <td colspan="8" style="text-align:center;">
                Belum ada pengajuan penarikan dari warga.
            </td>

        </tr>

    <?php endif; ?>

</tbody>

                </table>

            </div>
        </section>

        </div> <!-- penarikan-page -->
    </div> <!-- admin-content -->
</main>

</div> <!-- admin-dashboard -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const penarikanHighlight = document.querySelector('.penarikan-highlight');

    if (penarikanHighlight) {
        setTimeout(function () {
            penarikanHighlight.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
            inline: 'nearest'
        });
        }, 300);
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const forms = document.querySelectorAll('.form-proses-penarikan');

    forms.forEach(function (form) {

        form.addEventListener('submit', function (event) {

            const submitButton = event.submitter;

            if (!submitButton) {
                return;
            }

            // Cegah submit kedua kali
            if (form.dataset.submitted === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.submitted = 'true';

            // Nonaktifkan seluruh tombol
            const buttons = form.querySelectorAll('button[type="submit"]');

            buttons.forEach(function (button) {
                button.disabled = true;
            });

            // Ubah tombol yang diklik
            if (submitButton.value === 'disetujui') {
                submitButton.textContent = 'Memproses...';
            } else if (submitButton.value === 'ditolak') {
                submitButton.textContent = 'Memproses...';
            }

        });

    });

});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const alerts = document.querySelectorAll(
        '.form-alert-success, .form-alert-error'
    );

    alerts.forEach(function (alert) {

        setTimeout(function () {

            alert.classList.add('form-alert-hide');

            setTimeout(function () {
                alert.remove();
            }, 400);

        }, 4000);

    });

});
</script>
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
