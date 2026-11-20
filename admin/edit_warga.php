
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
// AMBIL ID WARGA
// ========================================

$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null || $id <= 0) {
    header("Location: warga.php");
    exit;
}

$error = '';

// ========================================
// PROSES UPDATE
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (
        empty($csrf_token) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $csrf_token)
    ) {
        $error = "Permintaan tidak valid.";
    } else {

        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $username     = trim($_POST['username'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $no_hp        = trim($_POST['no_hp'] ?? '');
        $alamat       = trim($_POST['alamat'] ?? '');
        $status       = trim($_POST['status'] ?? '');

        // VALIDASI INPUT

        if ($nama_lengkap === '') {
            $error = "Nama lengkap wajib diisi.";
        } elseif (strlen($nama_lengkap) > 100) {
            $error = "Nama lengkap maksimal 100 karakter.";
        } elseif ($username === '') {
            $error = "Username wajib diisi.";
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $error = "Username harus 3-50 karakter.";
        } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            $error = "Username hanya boleh mengandung huruf, angka, titik, garis bawah, dan tanda hubung.";
        } elseif ($email === '') {
            $error = "Email wajib diisi.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format email tidak valid.";
        } elseif (strlen($email) > 100) {
            $error = "Email maksimal 100 karakter.";
        } elseif (strlen($no_hp) > 20) {
            $error = "No. HP maksimal 20 karakter.";
        } elseif (strlen($alamat) > 500) {
            $error = "Alamat maksimal 500 karakter.";
        } elseif (!in_array($status, ['aktif', 'nonaktif'], true)) {
            $error = "Status akun tidak valid.";
        }

        // CEK USERNAME DAN EMAIL DUPLIKAT

        if ($error === '') {

            $cek = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM users
                 WHERE (username = ? OR email = ?)
                 AND id != ?
                 LIMIT 1"
            );

            if (!$cek) {
                $error = "Gagal menyiapkan pemeriksaan data.";
            } else {

                mysqli_stmt_bind_param(
                    $cek,
                    "ssi",
                    $username,
                    $email,
                    $id
                );

                if (!mysqli_stmt_execute($cek)) {
                    $error = "Gagal memeriksa data warga.";
                } else {

                    $result_cek = mysqli_stmt_get_result($cek);

                    if ($result_cek && mysqli_num_rows($result_cek) > 0) {
                        $error = "Username atau email sudah digunakan oleh akun lain.";
                    }
                }

                mysqli_stmt_close($cek);
            }
        }

        // UPDATE DATA WARGA

        if ($error === '') {

            $update = mysqli_prepare(
                $conn,
                "UPDATE users
                 SET nama_lengkap = ?,
                     username = ?,
                     email = ?,
                     no_hp = ?,
                     alamat = ?,
                     status = ?
                 WHERE id = ?
                 AND role = 'warga'"
            );

            if (!$update) {
                $error = "Gagal menyiapkan proses update.";
            } else {

                mysqli_stmt_bind_param(
                    $update,
                    "ssssssi",
                    $nama_lengkap,
                    $username,
                    $email,
                    $no_hp,
                    $alamat,
                    $status,
                    $id
                );

                if (!mysqli_stmt_execute($update)) {
                    $error = "Gagal memperbarui data warga.";
                } else {

                    mysqli_stmt_close($update);

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header("Location: warga.php?success=updated");
                    exit;
                }

                mysqli_stmt_close($update);
            }
        }
    }
}

// ========================================
// AMBIL DATA WARGA
// ========================================

$query = mysqli_prepare(
    $conn,
    "SELECT
        id,
        nama_lengkap,
        username,
        email,
        no_hp,
        alamat,
        status
     FROM users
     WHERE id = ?
     AND role = 'warga'
     LIMIT 1"
);

if (!$query) {
    die("Gagal menyiapkan query data warga.");
}

mysqli_stmt_bind_param($query, "i", $id);

if (!mysqli_stmt_execute($query)) {
    mysqli_stmt_close($query);
    die("Gagal mengambil data warga.");
}

$result = mysqli_stmt_get_result($query);

if (!$result || mysqli_num_rows($result) !== 1) {
    mysqli_stmt_close($query);
    header("Location: warga.php");
    exit;
}

$warga = mysqli_fetch_assoc($result);

mysqli_stmt_close($query);

// ========================================
// NILAI FORM
// ========================================

$nama_lengkap = $_POST['nama_lengkap'] ?? $warga['nama_lengkap'];
$username     = $_POST['username'] ?? $warga['username'];
$email        = $_POST['email'] ?? $warga['email'];
$no_hp        = $_POST['no_hp'] ?? $warga['no_hp'];
$alamat       = $_POST['alamat'] ?? $warga['alamat'];
$status       = $_POST['status'] ?? $warga['status'];

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Data Warga - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

/* =========================================
   RESET DAN DASAR HALAMAN
========================================= */

*,
*::before,
*::after {
    box-sizing: border-box;
}

html,
body {
    width: 100%;
    min-height: 100%;
    margin: 0;
    padding: 0;
    overflow-x: hidden !important;
}

body {
    background: #F2F7FA;
    font-family: 'Poppins', Arial, sans-serif;
}

/* =========================================
   SIDEBAR ADMIN - SAMAKAN DENGAN WARGA.PHP
========================================= */

.admin-sidebar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    bottom: 0 !important;

    width: 250px !important;
    min-width: 250px !important;
    max-width: 250px !important;
    height: 100vh !important;

    display: flex !important;
    flex-direction: column !important;

    padding: 0 !important;
    margin: 0 !important;

    overflow: hidden !important;
    z-index: 1001 !important;

    background: linear-gradient(
        180deg,
        #071F33 0%,
        #0B2D46 100%
    ) !important;

    box-shadow: none !important;
}

/* =========================================
   BAGIAN LOGO DAN IDENTITAS
========================================= */

.admin-sidebar .sidebar-brand {
    width: 100% !important;
    min-height: 120px !important;
    flex-shrink: 0 !important;

    display: flex !important;
    align-items: center !important;

    padding: 20px 18px !important;
    margin: 0 !important;

    border-bottom: 1px solid rgba(255,255,255,.08) !important;
}

.admin-sidebar .sidebar-brand-top {
    width: 100% !important;

    display: flex !important;
    align-items: center !important;

    gap: 12px !important;
}

.admin-sidebar .sidebar-logo {
    width: 58px !important;
    height: 58px !important;
    min-width: 58px !important;
    max-width: 58px !important;

    flex-shrink: 0 !important;

    display: flex !important;
    align-items: center !important;
    justify-content: center !important;

    padding: 5px !important;
    margin: 0 !important;

    border-radius: 50% !important;
    background: #FFFFFF !important;
}

.admin-sidebar .sidebar-logo img {
    display: block !important;

    width: 100% !important;
    height: 100% !important;

    object-fit: contain !important;
}

.admin-sidebar .sidebar-brand h2 {
    margin: 0 !important;
    padding: 0 !important;

    color: #FFFFFF !important;

    font-family: 'Poppins', Arial, sans-serif !important;
    font-size: 13px !important;
    line-height: 1.35 !important;
    font-weight: 700 !important;
}

.admin-sidebar .sidebar-brand p {
    margin: 5px 0 0 !important;
    padding: 0 !important;

    color: #AFC4D2 !important;

    font-family: 'Poppins', Arial, sans-serif !important;
    font-size: 10px !important;
    line-height: 1.4 !important;
    font-weight: 400 !important;
}

/* =========================================
   NAVIGASI SIDEBAR
========================================= */

.admin-sidebar .sidebar-nav {
    width: 100% !important;
    flex: 1 1 auto !important;
    min-height: 0 !important;

    display: flex !important;
    flex-direction: column !important;
    gap: 0 !important;

    padding: 14px 12px 20px !important;
    margin: 0 !important;

    overflow-y: auto !important;
    overflow-x: hidden !important;

    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
}

.admin-sidebar .sidebar-nav::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
}

/* =========================================
   JUDUL KELOMPOK MENU
========================================= */

.admin-sidebar .sidebar-section-title {
    display: block !important;
    flex-shrink: 0 !important;

    margin: 12px 0 5px !important;
    padding: 0 10px !important;

    color: #8FA9BA !important;

    font-family: 'Poppins', Arial, sans-serif !important;
    font-size: 10px !important;
    line-height: 1.4 !important;
    font-weight: 700 !important;

    letter-spacing: 1px !important;
    text-transform: uppercase !important;
}

/* =========================================
   LINK MENU SIDEBAR
========================================= */

.admin-sidebar .sidebar-link {
    width: 100% !important;
    min-width: 0 !important;

    min-height: 43px !important;
    height: 43px !important;
    max-height: 43px !important;

    flex: 0 0 43px !important;

    display: flex !important;
    align-items: center !important;

    gap: 11px !important;

    padding: 8px 12px !important;
    margin: 0 !important;

    border: none !important;
    border-radius: 10px !important;

    color: #D8E5ED !important;
    background: transparent !important;

    font-family: 'Poppins', Arial, sans-serif !important;
    font-size: 13px !important;
    line-height: 1.3 !important;
    font-weight: 600 !important;

    text-decoration: none !important;
    white-space: nowrap !important;

    box-shadow: none !important;
    transition: background .2s ease, color .2s ease !important;
}

.admin-sidebar .sidebar-link:hover {
    color: #FFFFFF !important;
    background: rgba(24,195,216,.12) !important;
}

.admin-sidebar .sidebar-link.active {
    color: #FFFFFF !important;
    background: rgba(24,195,216,.17) !important;
    box-shadow: inset 3px 0 0 #18C3D8 !important;
}

/* =========================================
   IKON MENU
========================================= */

.admin-sidebar .sidebar-icon {
    width: 20px !important;
    min-width: 20px !important;
    max-width: 20px !important;

    flex: 0 0 20px !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    margin: 0 !important;
    padding: 0 !important;

    font-size: 16px !important;
    line-height: 1 !important;
    text-align: center !important;
}

/* =========================================
   OVERLAY SIDEBAR HP
========================================= */

.sidebar-overlay {
    display: none;
}

/* =========================================
   KONTEN UTAMA DESKTOP
========================================= */

.admin-main {
    width: calc(100% - 250px) !important;
    min-width: 0 !important;
    min-height: 100vh !important;

    margin-left: 250px !important;
    padding: 0 !important;

    background: #F2F7FA !important;
}

.admin-content {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;

    padding: 36px 40px !important;
    margin: 0 !important;
}

/* =========================================
   HEADER EDIT WARGA
========================================= */

.edit-header {
    width: 100%;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 20px;
    margin-bottom: 28px;
}

.edit-header h1 {
    margin: 0;

    color: #0B354D;
    font-size: 28px;
    line-height: 1.3;
    font-weight: 750;
}

.edit-header p {
    margin: 7px 0 0;

    color: #718995;
    font-size: 13px;
    line-height: 1.6;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Tombol kembali */

.btn-kembali {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 11px 18px;
    border-radius: 10px;

    color: #FFFFFF;
    background: linear-gradient(135deg, #0B354D, #159BAE);

    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;

    box-shadow: 0 4px 12px rgba(11,53,77,.14);
    transition: .2s ease;
}

.btn-kembali:hover {
    color: #FFFFFF;
    transform: translateY(-2px);
}

/* =========================================
   TOMBOL HAMBURGER
========================================= */

.mobile-menu-button {
    display: none;

    width: 42px;
    height: 42px;

    align-items: center;
    justify-content: center;

    border: none;
    border-radius: 10px;

    color: #FFFFFF;
    background: linear-gradient(110deg, #0B354D, #17B8C8);

    font-size: 22px;
    cursor: pointer;

    box-shadow: 0 4px 12px rgba(11,53,77,.2);
}

/* =========================================
   KARTU FORM EDIT WARGA
========================================= */

.edit-warga-card {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;

    padding: 28px;
    margin: 0;

    border: 1px solid #E1EAED;
    border-radius: 16px;

    background: #FFFFFF;
    box-shadow: 0 5px 18px rgba(11,45,70,.045);
}

/* Heading kartu */

.edit-warga-heading {
    margin-bottom: 24px;
    padding-bottom: 18px;

    border-bottom: 1px solid #EAF0F4;
}

.edit-warga-heading h2 {
    margin: 0;

    color: #0B354D;
    font-size: 19px;
    font-weight: 700;
}

.edit-warga-heading p {
    margin: 6px 0 0;

    color: #82919C;
    font-size: 12px;
    line-height: 1.5;
}

/* =========================================
   FORM EDIT WARGA
========================================= */

.edit-warga-form {
    width: 100%;
    min-width: 0;

    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));

    gap: 20px;
}

.edit-warga-group {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 8px;
}

.edit-warga-group.full {
    grid-column: 1 / -1;
}

.edit-warga-group label {
    color: #294456;
    font-size: 13px;
    font-weight: 650;
}

.edit-warga-group label span {
    color: #E34F4F;
}

/* Input, textarea, select */

.edit-warga-group input,
.edit-warga-group textarea,
.edit-warga-group select {
    display: block;

    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;

    padding: 12px 14px;

    border: 1px solid #DCE6EC;
    border-radius: 9px;

    outline: none;
    background: #FAFCFD;

    color: #294456;
    font-family: inherit;
    font-size: 13px;

    transition: .2s ease;
}

.edit-warga-group input:focus,
.edit-warga-group textarea:focus,
.edit-warga-group select:focus {
    border-color: #18AFC2;
    background: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(24,195,216,.1);
}

.edit-warga-group textarea {
    min-height: 115px;
    resize: vertical;
}

.edit-warga-hint {
    color: #82919C;
    font-size: 11px;
}

/* =========================================
   ALERT ERROR
========================================= */

.edit-warga-alert {
    margin-bottom: 20px;
    padding: 13px 16px;

    border: 1px solid #F3CACA;
    border-radius: 9px;

    color: #A83232;
    background: #FFF2F2;

    font-size: 13px;
    line-height: 1.5;
}

/* =========================================
   TOMBOL FORM
========================================= */

.edit-warga-actions {
    grid-column: 1 / -1;

    display: flex;
    justify-content: flex-end;
    gap: 11px;

    margin-top: 8px;
    padding-top: 22px;

    border-top: 1px solid #EAF0F4;
}

.btn-edit-cancel,
.btn-edit-save {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 12px 20px;

    border: none;
    border-radius: 9px;

    font-family: inherit;
    font-size: 12px;
    font-weight: 650;

    text-decoration: none;
    cursor: pointer;

    transition: .2s ease;
}

.btn-edit-cancel {
    color: #526777;
    background: #EDF2F5;
}

.btn-edit-save {
    color: #FFFFFF;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    box-shadow: 0 4px 10px rgba(11,53,77,.13);
}

.btn-edit-cancel:hover,
.btn-edit-save:hover {
    transform: translateY(-2px);
}

/* =========================================
   RESPONSIVE TABLET DAN HP
========================================= */

@media (max-width: 800px) {

    /* Sidebar menjadi drawer */

    .admin-sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        bottom: 0 !important;

        width: 250px !important;
        min-width: 250px !important;
        max-width: 250px !important;
        height: 100vh !important;

        transform: translateX(-100%) !important;
        transition: transform .3s ease !important;

        z-index: 1001 !important;
    }

    .admin-sidebar.open {
        transform: translateX(0) !important;
    }

    /* Overlay */

    .sidebar-overlay {
        display: block !important;

        position: fixed !important;
        inset: 0 !important;

        z-index: 1000 !important;

        background: rgba(4,25,39,.55) !important;

        opacity: 0;
        visibility: hidden;

        transition: .3s ease;
    }

    .sidebar-overlay.show {
        opacity: 1 !important;
        visibility: visible !important;
    }

    /* Konten */

    .admin-main {
        width: 100% !important;
        min-width: 0 !important;

        margin-left: 0 !important;
    }

    .admin-content {
        width: 100% !important;
        padding: 25px 20px !important;
    }

    /* Hamburger */

    .mobile-menu-button {
        display: inline-flex !important;
        flex-shrink: 0;
    }

    /* Header */

    .edit-header {
        align-items: flex-start;
        gap: 12px;
    }

    .edit-header h1 {
        font-size: 23px;
    }

    .header-actions {
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
    }

    /* Form */

    .edit-warga-card {
        padding: 22px;
    }
}

/* =========================================
   RESPONSIVE HP KECIL
========================================= */

@media (max-width: 520px) {

    .admin-content {
        width: 100% !important;
        padding: 20px 14px 28px !important;
    }

    .edit-header {
        gap: 8px;
    }

    .edit-header h1 {
        font-size: 19px;
    }

    .edit-header p {
        font-size: 11px;
    }

    .mobile-menu-button {
        width: 39px;
        height: 39px;
        font-size: 20px;
    }

    .btn-kembali {
        padding: 9px 10px;
        font-size: 10px;
    }

    .edit-warga-card {
        padding: 18px 14px;
    }

    .edit-warga-heading h2 {
        font-size: 17px;
    }

    /* Form menjadi satu kolom */

    .edit-warga-form {
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
    }

    .edit-warga-group.full {
        grid-column: auto;
    }

    .edit-warga-actions {
        grid-column: auto;
        flex-direction: column-reverse;
    }

    .btn-edit-cancel,
    .btn-edit-save {
        width: 100%;
    }
}

/* MEMBUAT TULISAN MENU SIDEBAR PUTIH */
.admin-sidebar .sidebar-link,
.admin-sidebar .sidebar-link span {
    color: #B8CDD7 !important;
}

/* Warna saat menu aktif */
.admin-sidebar .sidebar-link.active,
.admin-sidebar .sidebar-link.active span {
    color: #B8CDD7 !important;
}

/* Warna saat menu disentuh mouse */
.admin-sidebar .sidebar-link:hover,
.admin-sidebar .sidebar-link:hover span {
    color: #ffffff !important;
}

    </style>
</head>

<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- =================================
     SIDEBAR ADMIN
================================= -->

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

<!-- =================================
     KONTEN UTAMA
================================= -->

<main class="admin-main">

    <div class="admin-content">

        <!-- HEADER -->

        <div class="edit-header">

            <div>
                <h1>Edit Data Warga</h1>

                <p>
                    Perbarui informasi akun warga Bank Sampah Digital.
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

                <a href="warga.php" class="btn-kembali">
                    ← Kembali ke Data Warga
                </a>

            </div>

        </div>

        <!-- FORM CARD -->

        <div class="edit-warga-card">

            <div class="edit-warga-heading">

                <h2>Formulir Edit Warga</h2>

                <p>
                    Periksa kembali informasi sebelum menyimpan perubahan.
                </p>

            </div>

            <?php if ($error !== ''): ?>

                <div class="edit-warga-alert">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>

            <form method="POST" class="edit-warga-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
                >

                <div class="edit-warga-group">

                    <label for="nama_lengkap">
                        Nama Lengkap <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="nama_lengkap"
                        name="nama_lengkap"
                        maxlength="100"
                        placeholder="Masukkan nama lengkap"
                        value="<?php echo htmlspecialchars($nama_lengkap); ?>"
                        required
                    >

                </div>

                <div class="edit-warga-group">

                    <label for="username">
                        Username <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        minlength="3"
                        maxlength="50"
                        placeholder="Masukkan username"
                        value="<?php echo htmlspecialchars($username); ?>"
                        required
                    >

                </div>

                <div class="edit-warga-group">

                    <label for="email">
                        Email <span>*</span>
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="100"
                        placeholder="Masukkan alamat email"
                        value="<?php echo htmlspecialchars($email); ?>"
                        required
                    >

                </div>

                <div class="edit-warga-group">

                    <label for="no_hp">
                        Nomor HP
                    </label>

                    <input
                        type="text"
                        id="no_hp"
                        name="no_hp"
                        maxlength="20"
                        placeholder="Masukkan nomor HP"
                        value="<?php echo htmlspecialchars($no_hp ?? ''); ?>"
                    >

                </div>

                <div class="edit-warga-group full">

                    <label for="alamat">
                        Alamat
                    </label>

                    <textarea
                        id="alamat"
                        name="alamat"
                        maxlength="500"
                        placeholder="Masukkan alamat lengkap warga"
                    ><?php echo htmlspecialchars($alamat ?? ''); ?></textarea>

                </div>

                <div class="edit-warga-group">

                    <label for="status">
                        Status Akun <span>*</span>
                    </label>

                    <select id="status" name="status" required>

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

                    <small class="edit-warga-hint">
                        Pilih status akun warga yang sesuai.
                    </small>

                </div>

                <div class="edit-warga-actions">

                    <a href="warga.php" class="btn-edit-cancel">
                        Batal
                    </a>

                    <button type="submit" class="btn-edit-save">
                        ✓ Simpan Perubahan
                    </button>

                </div>

            </form>

        </div>

    </div>

</main>

<!-- =================================
     JAVASCRIPT SIDEBAR
================================= -->

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
        menuButton.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');

        menuButton.innerHTML = '☰';
        menuButton.setAttribute('aria-expanded', 'false');
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
