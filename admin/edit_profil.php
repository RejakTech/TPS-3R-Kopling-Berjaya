<?php
session_start();

require_once '../config/koneksi.php';
require_once '../config/log_aktivitas.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int) $_SESSION['user_id'];

$pesan = '';
$error = '';

$nama_lengkap = '';
$username = '';
$email = '';
$no_hp = '';
$alamat = '';

/*
|--------------------------------------------------------------------------
| Ambil data profil
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $conn,
    "SELECT nama_lengkap, username, email, no_hp, alamat
     FROM users
     WHERE id = ? AND role = 'admin'
     LIMIT 1"
);

if (!$stmt) {
    die("Terjadi kesalahan saat mengambil data profil.");
}

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$admin) {
    session_unset();
    session_destroy();

    header("Location: ../auth/login.php");
    exit;
}

$nama_lengkap = $admin['nama_lengkap'] ?? '';
$username = $admin['username'] ?? '';
$email = $admin['email'] ?? '';
$no_hp = $admin['no_hp'] ?? '';
$alamat = $admin['alamat'] ?? '';

/*
|--------------------------------------------------------------------------
| Proses update profil
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (
        empty($csrf_token) ||
        !hash_equals($_SESSION['csrf_token'], $csrf_token)
    ) {
        $error = "Permintaan tidak valid. Silakan coba lagi.";
    } else {

        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $no_hp = trim($_POST['no_hp'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');

        /*
        |--------------------------------------------------------------------------
        | Validasi
        |--------------------------------------------------------------------------
        */

        if ($nama_lengkap === '') {
            $error = "Nama lengkap wajib diisi.";
        } elseif (strlen($nama_lengkap) > 100) {
            $error = "Nama lengkap maksimal 100 karakter.";
        } elseif ($username === '') {
            $error = "Username wajib diisi.";
        } elseif (strlen($username) < 4) {
            $error = "Username minimal 4 karakter.";
        } elseif (strlen($username) > 50) {
            $error = "Username maksimal 50 karakter.";
        } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            $error = "Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.";
        } elseif ($email === '') {
            $error = "Email wajib diisi.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format email tidak valid.";
        } elseif (strlen($email) > 100) {
            $error = "Email maksimal 100 karakter.";
        } elseif (strlen($no_hp) > 20) {
            $error = "Nomor HP maksimal 20 karakter.";
        } elseif (strlen($alamat) > 500) {
            $error = "Alamat maksimal 500 karakter.";
        }

        /*
        |--------------------------------------------------------------------------
        | Cek username / email duplikat
        |--------------------------------------------------------------------------
        */
        if ($error === '') {

            $stmt = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM users
                 WHERE (username = ? OR email = ?)
                 AND id != ?
                 LIMIT 1"
            );

            if (!$stmt) {
                $error = "Terjadi kesalahan saat memeriksa data akun.";
            } else {

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssi",
                    $username,
                    $email,
                    $user_id
                );

                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);

                if (mysqli_num_rows($result) > 0) {
                    $error = "Username atau email sudah digunakan oleh akun lain.";
                }

                mysqli_stmt_close($stmt);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Update database
        |--------------------------------------------------------------------------
        */
        if ($error === '') {

            $stmt = mysqli_prepare(
                $conn,
                "UPDATE users
                 SET nama_lengkap = ?,
                     username = ?,
                     email = ?,
                     no_hp = ?,
                     alamat = ?
                 WHERE id = ? AND role = 'admin'"
            );

            if (!$stmt) {
                $error = "Profil gagal diperbarui.";
            } else {

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssssi",
                    $nama_lengkap,
                    $username,
                    $email,
                    $no_hp,
                    $alamat,
                    $user_id
                );

                if (mysqli_stmt_execute($stmt)) {

                    /*
                    |--------------------------------------------------------------------------
                    | Update session nama
                    |--------------------------------------------------------------------------
                    */
                    $_SESSION['nama'] = $nama_lengkap;

                    /*
                    |--------------------------------------------------------------------------
                    | Catat aktivitas
                    |--------------------------------------------------------------------------
                    */
                    catatAktivitas(
                        $conn,
                        'Edit Profil',
                        'Admin memperbarui informasi profil akunnya sendiri.'
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Regenerasi CSRF token
                    |--------------------------------------------------------------------------
                    */
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    $pesan = "Profil berhasil diperbarui.";

                } else {
                    $error = "Profil gagal diperbarui.";
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Profil Admin - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

/* =========================
   EDIT PROFIL ADMIN
   NAVY - TEAL - CYAN
========================= */

.edit-profil-page {
    width: 100%;
    max-width: 1100px;
    margin: 0 auto;
    box-sizing: border-box;
}

/* HEADER */

.edit-profil-header-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 26px;
}

.edit-profil-header {
    margin: 0;
}

.edit-profil-header h2 {
    margin: 0;
    color: #0B4650;
    font-size: 29px;
    font-weight: 700;
    line-height: 1.3;
}

.edit-profil-header p {
    margin: 8px 0 0;
    color: #718995;
    font-size: 14px;
    line-height: 1.6;
}

/* CARD */
.edit-profil-card {
    width: 100%;
    max-width: 850px;
    box-sizing: border-box;
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 5px 20px rgba(11, 53, 77, 0.05);
}

/* JUDUL FORM */

.form-section-title {
    margin: 0 0 25px;
    padding-bottom: 17px;
    border-bottom: 1px solid #EAF0F2;
    color: #0B4650;
    font-size: 17px;
    font-weight: 700;
}

/* GRID FORM */

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
}

.form-group {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    margin-bottom: 9px;
    color: #0B4650;
    font-size: 13px;
    font-weight: 600;
}

.form-group input,
.form-group textarea {
    display: block;
    width: 100%;
    box-sizing: border-box;
    padding: 13px 15px;
    border: 1px solid #D6E2E6;
    border-radius: 10px;
    background: #FFFFFF;
    color: #0B354D;
    font-family: inherit;
    font-size: 14px;
    outline: none;
    transition: all 0.2s ease;
}

.form-group input:hover,
.form-group textarea:hover {
    border-color: #A9CDD2;
}

.form-group input:focus,
.form-group textarea:focus {
    border-color: #159BAE;
    box-shadow: 0 0 0 3px rgba(21, 155, 174, 0.12);
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
}

.form-help {
    margin-top: 7px;
    color: #8498A0;
    font-size: 11px;
    line-height: 1.6;
}

/* NOTIFIKASI */

.form-alert-success {
    margin-bottom: 20px;
    padding: 13px 16px;
    border: 1px solid #BDE7D0;
    border-radius: 10px;
    background: #E7F6EF;
    color: #18734F;
    font-size: 13px;
}

.form-alert-error {
    margin-bottom: 20px;
    padding: 13px 16px;
    border: 1px solid #F3CACA;
    border-radius: 10px;
    background: #FDEEEE;
    color: #A53C3C;
    font-size: 13px;
}

/* TOMBOL */

.form-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    margin-top: 30px;
    padding-top: 23px;
    border-top: 1px solid #EAF0F2;
}

.btn-edit-kembali,
.btn-edit-simpan,
.btn-edit-dashboard {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 43px;
    box-sizing: border-box;
    padding: 12px 20px;
    border-radius: 9px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-edit-kembali {
    background: #E8F1F3;
    color: #0B4650;
}

.btn-edit-kembali:hover {
    background: #D5E8EB;
    transform: translateY(-2px);
}

.btn-edit-simpan {
    border: none;
    background: linear-gradient(135deg, #0B4650, #159BAE);
    color: #FFFFFF;
    cursor: pointer;
}

.btn-edit-simpan:hover {
    background: linear-gradient(135deg, #083641, #11899A);
    transform: translateY(-2px);
    box-shadow: 0 5px 12px rgba(11, 70, 80, 0.16);
}

/* TOMBOL HEADER */

.btn-edit-dashboard {
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #FFFFFF;
    white-space: nowrap;
    box-shadow: 0 4px 10px rgba(11, 53, 77, 0.12);
}

.btn-edit-dashboard:hover {
    background: linear-gradient(135deg, #082C40, #11899A);
    transform: translateY(-2px);
}

/* RESPONSIVE */

@media (max-width: 900px) {
    .edit-profil-page {
        max-width: 100%;
    }

    .edit-profil-card {
        max-width: 100%;
    }
}

@media (max-width: 650px) {
    .edit-profil-header-wrapper {
        align-items: flex-start;
        flex-direction: column;
        gap: 14px;
    }

    .edit-profil-header h2 {
        font-size: 25px;
    }

    .edit-profil-header p {
        font-size: 13px;
    }

    .edit-profil-card {
        padding: 21px 17px;
        border-radius: 13px;
    }

    .form-grid {
        grid-template-columns: 1fr;
        gap: 17px;
    }

    .form-group.full {
        grid-column: auto;
    }

    .form-actions {
        flex-direction: column-reverse;
    }

    .btn-edit-kembali,
    .btn-edit-simpan {
        width: 100%;
    }
}

/* =========================================
   RESPONSIVE EDIT PROFIL ADMIN
========================================= */

.mobile-menu-button {
    display: none !important;
}


@media screen and (max-width: 800px) {

    *,
    *::before,
    *::after {
        box-sizing: border-box !important;
    }

    html,
    body {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        overflow-x: hidden !important;
    }

    .admin-dashboard {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
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
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .admin-content {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 18px 14px !important;
    }

    .edit-profil-page {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        margin: 0 auto !important;
    }

    /* HEADER */
    .edit-profil-header-wrapper {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        width: 100% !important;
        gap: 14px !important;
        margin-bottom: 20px !important;
    }

    .edit-profil-header {
        width: 100% !important;
        min-width: 0 !important;
    }

    .edit-profil-title-line {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        width: 100% !important;
        gap: 10px !important;
    }

    .edit-profil-header h2 {
        flex: 1 !important;
        min-width: 0 !important;
        font-size: 23px !important;
        margin: 0 !important;
    }

    .edit-profil-header p {
        font-size: 13px !important;
        line-height: 1.5 !important;
    }

    /* HAMBURGER */
    .mobile-menu-button {
        display: inline-flex !important;
        flex: 0 0 42px !important;
        width: 42px !important;
        height: 42px !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 !important;
        border: none !important;
        border-radius: 10px !important;
        background: linear-gradient(
            110deg,
            #0B354D,
            #17B8C8
        ) !important;
        color: white !important;
        font-size: 23px !important;
        cursor: pointer !important;
    }

    .btn-edit-dashboard {
        align-self: flex-start !important;
        white-space: normal !important;
        max-width: 100% !important;
    }

    /* FORM CARD */
    .edit-profil-card {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        padding: 21px 16px !important;
        border-radius: 13px !important;
        margin: 0 !important;
    }

    .form-section-title {
        font-size: 16px !important;
    }

    /* FORM GRID */
    .form-grid {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) !important;
        width: 100% !important;
        gap: 17px !important;
    }

    .form-group,
    .form-group.full {
        width: 100% !important;
        min-width: 0 !important;
        grid-column: auto !important;
    }

    .form-group input,
    .form-group textarea {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
        font-size: 16px !important;
    }

    .form-group textarea {
        min-height: 110px !important;
    }

    /* TOMBOL */
    .form-actions {
        display: flex !important;
        flex-direction: column-reverse !important;
        align-items: stretch !important;
        width: 100% !important;
        gap: 10px !important;
    }

    .btn-edit-kembali,
    .btn-edit-simpan {
        width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }
}

@media screen and (max-width: 380px) {

    .admin-content {
        padding: 15px 11px !important;
    }

    .edit-profil-card {
        padding: 18px 13px !important;
    }

    .edit-profil-header h2 {
        font-size: 21px !important;
    }
}


/* =========================================
   DESKTOP EDIT PROFIL
   MENYESUAIKAN HALAMAN UBAH PASSWORD
   ========================================= */

@media screen and (min-width: 801px) {

    .edit-profil-page {
        width: 100%;
        max-width: 1100px;
        margin: 0 auto;
    }

    .edit-profil-card {
        width: 100%;
        max-width: 850px;
        margin: 0;
    }

    .edit-profil-header-wrapper {
        width: 100%;
    }

    .edit-profil-title-line {
        display: block;
    }

    .mobile-menu-button {
        display: none !important;
    }
}


    </style>

</head>

<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="admin-dashboard">
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

        <a href="log_aktivitas.php" class="sidebar-link">
            <span class="sidebar-icon">📋</span>
            <span>Log Aktivitas</span>
        </a>

        <div class="sidebar-section-title">
            Akun
        </div>

        <a href="profil.php" class="sidebar-link active">
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

            <div class="edit-profil-page">

                <!-- HEADER -->
                <div class="edit-profil-header-wrapper">

                    <div class="edit-profil-header">

    <div class="edit-profil-title-line">
        <h2>Edit Profil</h2>

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
        Perbarui informasi akun administrator Anda.
    </p>

</div>

                    <a href="dashboard.php" class="btn-edit-dashboard">
                        ← Kembali ke Dashboard
                    </a>

                </div>

                <!-- CARD -->
                <div class="edit-profil-card">

                    <?php if ($pesan !== ''): ?>
                        <div class="form-alert-success">
                            <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error !== ''): ?>
                        <div class="form-alert-error">
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <h3 class="form-section-title">
                        Informasi Pribadi
                    </h3>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
                        >

                        <div class="form-grid">

                            <div class="form-group">
                                <label for="nama_lengkap">
                                    Nama Lengkap
                                </label>

                                <input
                                    type="text"
                                    id="nama_lengkap"
                                    name="nama_lengkap"
                                    maxlength="100"
                                    value="<?= htmlspecialchars($nama_lengkap, ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="username">
                                    Username
                                </label>

                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    maxlength="50"
                                    value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                >

                                <div class="form-help">
                                    Gunakan huruf, angka, titik, garis bawah,
                                    atau tanda hubung.
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="email">
                                    Email
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    maxlength="100"
                                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="no_hp">
                                    Nomor HP
                                </label>

                                <input
                                    type="text"
                                    id="no_hp"
                                    name="no_hp"
                                    maxlength="20"
                                    value="<?= htmlspecialchars($no_hp, ENT_QUOTES, 'UTF-8'); ?>"
                                >
                            </div>

                            <div class="form-group full">
                                <label for="alamat">
                                    Alamat
                                </label>

                                <textarea
                                    id="alamat"
                                    name="alamat"
                                    maxlength="500"
                                ><?= htmlspecialchars($alamat, ENT_QUOTES, 'UTF-8'); ?></textarea>

                                <div class="form-help">
                                    Maksimal 500 karakter.
                                </div>
                            </div>

                        </div>

                        <!-- TOMBOL -->
                        <div class="form-actions">

                            <a href="profil.php" class="btn-edit-kembali">
                                ← Kembali
                            </a>

                            <button
                                type="submit"
                                class="btn-edit-simpan"
                            >
                                Simpan Perubahan
                            </button>

                        </div>

                    </form>

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
