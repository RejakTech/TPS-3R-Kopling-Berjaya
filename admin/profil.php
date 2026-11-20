<?php
session_start();

require_once '../config/koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, nama_lengkap, username, email, no_hp, alamat, role, status
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

$nama_lengkap = $admin['nama_lengkap'] ?? '-';
$username = $admin['username'] ?? '-';
$email = $admin['email'] ?? '-';
$no_hp = $admin['no_hp'] ?? '-';
$alamat = $admin['alamat'] ?? '-';
$role = $admin['role'] ?? '-';
$status = $admin['status'] ?? '-';

$status_label = ucfirst(strtolower($status));

if ($status === 'aktif') {
    $status_class = 'status-aktif';
} else {
    $status_class = 'status-nonaktif';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profil Admin - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">
<style>
/* =========================================
   PROFIL ADMIN - MODERN DESIGN
========================================= */

.profil-page {
    width: 100%;
    max-width: 1100px;
    margin: 0 auto;
}

/* HEADER */
.profil-header-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 26px;
}

.profil-header {
    margin: 0;
}

.profil-header h2 {
    margin: 0;
    color: #0B4650;
    font-size: 29px;
    font-weight: 700;
    line-height: 1.3;
}

.profil-header p {
    margin: 8px 0 0;
    color: #718995;
    font-size: 14px;
    line-height: 1.6;
}

.btn-kembali {
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
    box-shadow: 0 4px 10px rgba(11, 53, 77, .12);
    transition: .2s ease;
}

.btn-kembali:hover {
    background: linear-gradient(135deg, #082C40, #11899A);
    transform: translateY(-2px);
}

/* PROFILE CARD */
.profil-card {
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(11, 53, 77, .05);
}

/* PROFILE TOP */
.profil-top {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 30px;
    background: linear-gradient(120deg, #EDF7F8, #F7FBFC);
    border-bottom: 1px solid #E1EAED;
}

.profil-avatar {
    width: 72px;
    height: 72px;
    min-width: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0B4650, #17B8C8);
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 700;
    box-shadow: 0 5px 14px rgba(21, 155, 174, .2);
}

.profil-top-info h3 {
    margin: 0 0 7px;
    color: #0B4650;
    font-size: 21px;
    font-weight: 700;
}

.profil-top-info p {
    margin: 0;
    color: #718995;
    font-size: 13px;
}

/* PROFILE CONTENT */
.profil-content {
    padding: 30px;
}

.profil-section-title {
    margin: 0 0 20px;
    color: #0B4650;
    font-size: 17px;
    font-weight: 700;
}

.profil-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.profil-item {
    min-width: 0;
    padding: 18px;
    background: #F7FAFB;
    border: 1px solid #E4ECEF;
    border-radius: 11px;
    transition: .2s ease;
}

.profil-item:hover {
    border-color: #B8DDE1;
    background: #F2F9FA;
}

.profil-item.full {
    grid-column: 1 / -1;
}

.profil-label {
    display: block;
    margin-bottom: 8px;
    color: #718995;
    font-size: 12px;
    font-weight: 600;
}

.profil-value {
    color: #0B4650;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

/* STATUS */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.status-aktif {
    background: #E3F5EE;
    color: #18734F;
}

.status-nonaktif {
    background: #FDECEC;
    color: #A53C3C;
}

/* ACTION BUTTONS */
.profil-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 11px;
    margin-top: 27px;
    padding-top: 23px;
    border-top: 1px solid #EAF0F2;
}

.btn-profil {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 11px 18px;
    min-height: 42px;
    border-radius: 9px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: .2s ease;
}

.btn-edit {
    background: #E5F4F5;
    color: #0B7180;
}

.btn-edit:hover {
    background: #D3EAED;
    transform: translateY(-2px);
}

.btn-password {
    background: #0B4650;
    color: #FFFFFF;
}

.btn-password:hover {
    background: #083641;
    transform: translateY(-2px);
}

/* TABLET */
@media (max-width: 900px) {
    .profil-page {
        max-width: 100%;
    }
}

/* MOBILE */
@media (max-width: 650px) {
    .profil-header-wrapper {
        align-items: flex-start;
        flex-direction: column;
        gap: 14px;
    }

    .profil-header h2 {
        font-size: 25px;
    }

    .profil-header p {
        font-size: 13px;
    }

    .profil-top {
        padding: 22px 18px;
    }

    .profil-avatar {
        width: 60px;
        height: 60px;
        min-width: 60px;
        font-size: 23px;
    }

    .profil-top-info h3 {
        font-size: 17px;
    }

    .profil-content {
        padding: 20px 17px;
    }

    .profil-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .profil-item.full {
        grid-column: auto;
    }

    .profil-actions {
        flex-direction: column;
    }

    .btn-profil {
        width: 100%;
        box-sizing: border-box;
    }
}
/* =========================================
   RESPONSIVE SIDEBAR DAN PROFIL ADMIN
========================================= */

.mobile-menu-button {
    display: none !important;
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

/* TABLET DAN MOBILE */
@media screen and (max-width: 800px) {

    body {
        overflow-x: hidden !important;
    }

    .admin-dashboard {
        width: 100% !important;
        min-height: 100vh !important;
        overflow-x: hidden !important;
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

    /* KONTEN UTAMA */
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

    .profil-page {
        width: 100% !important;
        max-width: 100% !important;
    }

    /* HEADER */
    .profil-header-wrapper {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 15px !important;
    }

    .profil-header {
        width: 100% !important;
    }

    .profil-title-line {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
    }

    .profil-header h2 {
        font-size: 25px !important;
    }

    .profil-header p {
        font-size: 13px !important;
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
        color: #FFFFFF !important;
        font-size: 23px !important;
        cursor: pointer !important;
    }

    /* TOMBOL KEMBALI */
    .btn-kembali {
        align-self: flex-start !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    /* KARTU PROFIL */
    .profil-card {
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .profil-top {
        gap: 15px !important;
    }

    .profil-content {
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .profil-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .profil-item {
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    .profil-value {
        overflow-wrap: anywhere !important;
    }
}

/* HP KECIL */
@media screen and (max-width: 480px) {

    .admin-content {
        padding: 18px 13px !important;
    }

    .profil-header h2 {
        font-size: 22px !important;
    }

    .profil-top {
        padding: 20px 15px !important;
    }

    .profil-avatar {
        width: 55px !important;
        height: 55px !important;
        min-width: 55px !important;
        font-size: 21px !important;
    }

    .profil-top-info h3 {
        font-size: 16px !important;
        overflow-wrap: anywhere !important;
    }

    .profil-top-info p {
        font-size: 12px !important;
        overflow-wrap: anywhere !important;
    }

    .profil-content {
        padding: 18px 14px !important;
    }

    .profil-grid {
        grid-template-columns: 1fr !important;
    }

    .profil-item.full {
        grid-column: auto !important;
    }

    .profil-actions {
        flex-direction: column !important;
    }

    .btn-profil {
        width: 100% !important;
        box-sizing: border-box !important;
    }
}

/* =========================================
   FIX FINAL TAMPILAN PROFIL ADMIN DI HP
========================================= */

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
        padding: 0 !important;
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

    .admin-main {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        box-sizing: border-box !important;
    }

    .admin-content {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 18px 14px !important;
    }

    .profil-page {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        margin: 0 auto !important;
    }

    /* HEADER PROFIL */
    .profil-header-wrapper {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        width: 100% !important;
        gap: 14px !important;
        margin-bottom: 20px !important;
    }

    .profil-header {
        width: 100% !important;
        min-width: 0 !important;
    }

    .profil-title-line {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        width: 100% !important;
        gap: 10px !important;
    }

    .profil-header h2 {
        flex: 1 !important;
        min-width: 0 !important;
        margin: 0 !important;
        font-size: 23px !important;
        line-height: 1.3 !important;
    }

    .profil-header p {
        margin-top: 7px !important;
        line-height: 1.5 !important;
    }

    .mobile-menu-button {
        flex-shrink: 0 !important;
        width: 42px !important;
        height: 42px !important;
    }

    .btn-kembali {
        display: inline-flex !important;
        width: fit-content !important;
        max-width: 100% !important;
        align-self: flex-start !important;
        white-space: normal !important;
    }

    /* KARTU PROFIL */
    .profil-card {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        margin: 0 !important;
        border-radius: 13px !important;
    }

    .profil-top {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        gap: 13px !important;
        width: 100% !important;
        padding: 20px 15px !important;
    }

    .profil-avatar {
        flex: 0 0 55px !important;
        width: 55px !important;
        height: 55px !important;
        min-width: 55px !important;
    }

    .profil-top-info {
        flex: 1 !important;
        min-width: 0 !important;
    }

    .profil-top-info h3 {
        font-size: 16px !important;
        overflow-wrap: anywhere !important;
    }

    .profil-top-info p {
        font-size: 12px !important;
        overflow-wrap: anywhere !important;
    }

    .profil-content {
        width: 100% !important;
        min-width: 0 !important;
        padding: 20px 15px !important;
    }

    /* INFORMASI AKUN */
    .profil-grid {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) !important;
        width: 100% !important;
        gap: 12px !important;
    }

    .profil-item,
    .profil-item.full {
        display: block !important;
        grid-column: auto !important;
        width: 100% !important;
        min-width: 0 !important;
        padding: 15px !important;
    }

    .profil-value {
        max-width: 100% !important;
        overflow-wrap: anywhere !important;
        word-break: break-word !important;
    }

    /* TOMBOL AKSI */
    .profil-actions {
        display: flex !important;
        flex-direction: column !important;
        width: 100% !important;
        gap: 10px !important;
    }

    .btn-profil {
        display: flex !important;
        width: 100% !important;
        min-width: 0 !important;
        justify-content: center !important;
    }
}

@media screen and (max-width: 380px) {

    .admin-content {
        padding: 15px 11px !important;
    }

    .profil-header h2 {
        font-size: 21px !important;
    }

    .profil-content {
        padding: 16px 12px !important;
    }

    .profil-top {
        padding: 17px 12px !important;
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

    <div class="profil-page">

<div class="profil-header-wrapper">

    <div class="profil-header">
        <div class="profil-title-line">
            <h2>Profil Admin</h2>

            <button
                type="button"
                class="mobile-menu-button"
                id="mobileMenuButton"
                aria-label="Buka menu"
            >
                ☰
            </button>
        </div>

        <p>Informasi akun administrator yang sedang digunakan.</p>
    </div>

    <a href="dashboard.php" class="btn-kembali">
        ← Kembali ke Dashboard
    </a>

</div>

        <div class="profil-card">

            <!-- Header Profil -->
            <div class="profil-top">

                <div class="profil-avatar">
                    <?= strtoupper(substr($nama_lengkap, 0, 1)); ?>
                </div>

                <div class="profil-top-info">
                    <h3>
                        <?= htmlspecialchars($nama_lengkap, ENT_QUOTES, 'UTF-8'); ?>
                    </h3>

                    <p>
                        Administrator · @<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>

            </div>


            <!-- Informasi Profil -->
            <div class="profil-content">

                <h3 class="profil-section-title">
                    Informasi Akun
                </h3>

                <div class="profil-grid">

                    <div class="profil-item">
                        <span class="profil-label">
                            Nama Lengkap
                        </span>

                        <div class="profil-value">
                            <?= htmlspecialchars($nama_lengkap, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>


                    <div class="profil-item">
                        <span class="profil-label">
                            Username
                        </span>

                        <div class="profil-value">
                            <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>


                    <div class="profil-item">
                        <span class="profil-label">
                            Email
                        </span>

                        <div class="profil-value">
                            <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>


                    <div class="profil-item">
                        <span class="profil-label">
                            Nomor HP
                        </span>

                        <div class="profil-value">
                            <?= htmlspecialchars($no_hp, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>


                    <div class="profil-item full">
                        <span class="profil-label">
                            Alamat
                        </span>

                        <div class="profil-value">
                            <?= htmlspecialchars($alamat, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>


                    <div class="profil-item">
                        <span class="profil-label">
                            Role
                        </span>

                        <div class="profil-value">
                            Administrator
                        </div>
                    </div>


                    <div class="profil-item">
                        <span class="profil-label">
                            Status Akun
                        </span>

                        <div class="profil-value">

                            <span class="status-badge <?= $status_class; ?>">
                                <?= htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8'); ?>
                            </span>

                        </div>
                    </div>

                </div>


                <!-- Tombol -->
                <div class="profil-actions">

                 

                    <a
                        href="edit_profil.php"
                        class="btn-profil btn-edit"
                    >
                        ✎ Edit Profil
                    </a>

                    <a
                        href="ubah_password.php"
                        class="btn-profil btn-password"
                    >
                        🔐 Ubah Password
                    </a>

                </div>

            </div>

        </div>

    </div>

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