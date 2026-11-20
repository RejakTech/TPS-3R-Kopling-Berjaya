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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (
        empty($csrf_token) ||
        !hash_equals($_SESSION['csrf_token'], $csrf_token)
    ) {
        $error = "Permintaan tidak valid. Silakan coba lagi.";
    } else {

        $password_lama = $_POST['password_lama'] ?? '';
        $password_baru = $_POST['password_baru'] ?? '';
        $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

        if ($password_lama === '' || $password_baru === '' || $konfirmasi_password === '') {
            $error = "Semua field password wajib diisi.";
        } elseif (strlen($password_baru) < 8) {
            $error = "Password baru minimal 8 karakter.";
        } elseif ($password_baru !== $konfirmasi_password) {
            $error = "Konfirmasi password tidak sama.";
        } else {

            $stmt = mysqli_prepare(
                $conn,
                "SELECT password FROM users WHERE id = ? AND role = 'admin' LIMIT 1"
            );

            if (!$stmt) {
                $error = "Terjadi kesalahan saat mengambil data akun.";
            } else {

                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);
                $admin = mysqli_fetch_assoc($result);

                mysqli_stmt_close($stmt);

                if (!$admin) {
                    $error = "Data akun admin tidak ditemukan.";
                } elseif (!password_verify($password_lama, $admin['password'])) {
                    $error = "Password lama yang Anda masukkan salah.";
                } else {

                    $password_hash = password_hash(
                        $password_baru,
                        PASSWORD_DEFAULT
                    );

                    $stmt_update = mysqli_prepare(
                        $conn,
                        "UPDATE users SET password = ? WHERE id = ? AND role = 'admin'"
                    );

                    if (!$stmt_update) {
                        $error = "Password gagal diperbarui.";
                    } else {

                        mysqli_stmt_bind_param(
                            $stmt_update,
                            "si",
                            $password_hash,
                            $user_id
                        );

                        if (mysqli_stmt_execute($stmt_update)) {

                            catatAktivitas(
                                $conn,
                                'Ubah Password',
                                'Admin mengubah password akun sendiri.'
                            );

                            $pesan = "Password berhasil diperbarui.";

                            // Regenerasi token CSRF setelah perubahan password
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        } else {
                            $error = "Password gagal diperbarui.";
                        }

                        mysqli_stmt_close($stmt_update);
                    }
                }
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

    <title>Ubah Password - Admin</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
 

/* =========================
   HALAMAN UBAH PASSWORD
========================= */

.password-page {
    width: 100%;
    max-width: 1100px;
    margin: 0 auto;
    box-sizing: border-box;
}

/* HEADER */

.password-header {
    margin-bottom: 26px;
}

.password-header h2 {
    margin: 0;
    color: #0B4650;
    font-size: 29px;
    font-weight: 700;
    line-height: 1.3;
}

.password-header p {
    margin: 8px 0 0;
    color: #718995;
    font-size: 14px;
    line-height: 1.6;
}

/* CARD */

.password-card {
    width: 100%;
    max-width: 850px;
    box-sizing: border-box;
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 5px 20px rgba(11, 53, 77, 0.05);
}

/* INFO PASSWORD */

.password-info {
    background: #EDF8F8;
    border: 1px solid #CDE9EC;
    color: #0B6470;
    border-radius: 10px;
    padding: 15px 17px;
    font-size: 13px;
    line-height: 1.7;
    margin-bottom: 26px;
}

.password-info strong {
    color: #0B4650;
}

/* FORM */

.password-form-group {
    margin-bottom: 21px;
}

.password-form-group label {
    display: block;
    margin-bottom: 9px;
    color: #0B4650;
    font-size: 13px;
    font-weight: 600;
}

.password-form-group input {
    display: block;
    width: 100%;
    box-sizing: border-box;
    padding: 13px 15px;
    border: 1px solid #D6E2E6;
    border-radius: 10px;
    background: #FFFFFF;
    color: #0B354D;
    font-size: 14px;
    outline: none;
    transition: all 0.2s ease;
}

.password-form-group input:hover {
    border-color: #A9CDD2;
}

.password-form-group input:focus {
    border-color: #159BAE;
    box-shadow: 0 0 0 3px rgba(21, 155, 174, 0.12);
}

/* NOTIFIKASI */

.form-alert-success {
    background: #E7F6EF;
    color: #18734F;
    border: 1px solid #BDE7D0;
    border-radius: 10px;
    padding: 13px 16px;
    margin-bottom: 20px;
    font-size: 14px;
}

.form-alert-error {
    background: #FDEEEE;
    color: #A53C3C;
    border: 1px solid #F3CACA;
    border-radius: 10px;
    padding: 13px 16px;
    margin-bottom: 20px;
    font-size: 14px;
}

/* TOMBOL */

.password-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    margin-top: 28px;
    padding-top: 23px;
    border-top: 1px solid #EAF0F2;
}

.btn-kembali-password,
.btn-simpan-password {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 20px;
    min-height: 43px;
    box-sizing: border-box;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-kembali-password {
    background: #E8F1F3;
    color: #0B4650;
}

.btn-kembali-password:hover {
    background: #D5E8EB;
    transform: translateY(-2px);
}

.btn-simpan-password {
    border: none;
    background: linear-gradient(135deg, #0B4650, #159BAE);
    color: #FFFFFF;
    cursor: pointer;
}

.btn-simpan-password:hover {
    background: linear-gradient(135deg, #083641, #11899A);
    transform: translateY(-2px);
    box-shadow: 0 5px 12px rgba(11, 70, 80, 0.16);
}

/* RESPONSIVE */

@media (max-width: 900px) {
    .password-page {
        max-width: 100%;
    }

    .password-card {
        max-width: 100%;
    }
}

@media (max-width: 600px) {
    .password-header h2 {
        font-size: 25px;
    }

    .password-header p {
        font-size: 13px;
    }

    .password-card {
        padding: 21px 17px;
        border-radius: 13px;
    }

    .password-info {
        padding: 13px;
        font-size: 12px;
    }

    .password-form-group input {
        padding: 12px;
    }

    .password-actions {
        flex-direction: column-reverse;
    }

    .btn-kembali-password,
    .btn-simpan-password {
        width: 100%;
    }
}

/* =========================================
   RESPONSIVE UBAH PASSWORD
========================================= */

.mobile-menu-button {
    display: none !important;
}

@media screen and (max-width: 800px) {

    html,
    body {
        width: 100% !important;
        max-width: 100% !important;
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

    .password-page {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        margin: 0 auto !important;
    }

    /* HEADER */
    .password-header {
        width: 100% !important;
        margin-bottom: 20px !important;
    }

    .password-title-line {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 10px !important;
        width: 100% !important;
    }

    .password-header h2 {
        flex: 1 !important;
        min-width: 0 !important;
        font-size: 23px !important;
    }

    .password-header p {
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

    /* CARD */
    .password-card {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        padding: 21px 16px !important;
        box-sizing: border-box !important;
    }

    .password-form-group input {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        font-size: 16px !important;
    }

    /* TOMBOL */
    .password-actions {
        display: flex !important;
        flex-direction: column-reverse !important;
        align-items: stretch !important;
        width: 100% !important;
        gap: 10px !important;
    }

    .btn-kembali-password,
    .btn-simpan-password {
        width: 100% !important;
    }
}

@media screen and (max-width: 380px) {

    .admin-content {
        padding: 15px 11px !important;
    }

    .password-card {
        padding: 18px 13px !important;
    }

    .password-header h2 {
        font-size: 21px !important;
    }
}

@media screen and (min-width: 801px) {
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

        <a href="profil.php" class="sidebar-link ">
            <span class="sidebar-icon">⚙️</span>
            <span>Profil</span>
        </a>

        <a href="ubah_password.php" class="sidebar-link active">
            <span class="sidebar-icon">🔑</span>
            <span>Ubah Password</span>
        </a>

        

    </nav>
</aside>

<main class="admin-main">

    <div class="admin-content">

        <div class="password-page">

           <div class="password-header">

    <div class="password-title-line">
        <h2>Ubah Password</h2>

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
        Perbarui password akun admin Anda untuk menjaga keamanan akun.
    </p>

</div>

            <!-- CARD PASSWORD -->
            <div class="password-card">

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

                <div class="password-info">
                    Password baru harus memiliki minimal
                    <strong>8 karakter</strong>.
                    Gunakan kombinasi huruf, angka, dan karakter khusus
                    agar lebih aman.
                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
                    >

                    <div class="password-form-group">
                        <label for="password_lama">Password Lama</label>
                        <input
                            type="password"
                            id="password_lama"
                            name="password_lama"
                            autocomplete="current-password"
                            required
                        >
                    </div>

                    <div class="password-form-group">
                        <label for="password_baru">Password Baru</label>
                        <input
                            type="password"
                            id="password_baru"
                            name="password_baru"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="password-form-group">
                        <label for="konfirmasi_password">
                            Konfirmasi Password Baru
                        </label>
                        <input
                            type="password"
                            id="konfirmasi_password"
                            name="konfirmasi_password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <!-- TOMBOL -->
                    <div class="password-actions">

                        <a href="dashboard.php" class="btn-kembali-password">
                            ← Kembali ke Dashboard
                        </a>

                        <button
                            type="submit"
                            class="btn-simpan-password"
                        >
                            Simpan Password
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</main>

</div>

</body>
</html>
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