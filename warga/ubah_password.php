
<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') != 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include_once '../config/koneksi.php';
require_once '../config/log_aktivitas.php';

// ===================================
// CSRF TOKEN
// ===================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int) $_SESSION['user_id'];

$pesan = '';
$tipe_pesan = '';


// ===================================
// PROSES UBAH PASSWORD
// ===================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token_post = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token_post) ||
        !hash_equals($_SESSION['csrf_token'], $token_post)
    ) {

        $pesan = "Token keamanan tidak valid.";
        $tipe_pesan = 'error';

    } else {

        $password_lama = $_POST['password_lama'] ?? '';
        $password_baru = $_POST['password_baru'] ?? '';
        $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

        if ($password_lama === '') {

            $pesan = "Password lama wajib diisi.";
            $tipe_pesan = 'error';

        } elseif ($password_baru === '') {

            $pesan = "Password baru wajib diisi.";
            $tipe_pesan = 'error';

        } elseif (strlen($password_baru) < 8) {

            $pesan = "Password baru minimal 8 karakter.";
            $tipe_pesan = 'error';

        } elseif ($password_baru !== $konfirmasi_password) {

            $pesan = "Konfirmasi password tidak sama.";
            $tipe_pesan = 'error';

        } elseif ($password_lama === $password_baru) {

            $pesan = "Password baru tidak boleh sama dengan password lama.";
            $tipe_pesan = 'error';

        } else {

            // Ambil password saat ini
            $stmt = mysqli_prepare(
                $conn,
                "SELECT password
                 FROM users
                 WHERE id = ? AND role = 'warga'
                 LIMIT 1"
            );

            if (!$stmt) {

                $pesan = "Terjadi kesalahan pada sistem.";
                $tipe_pesan = 'error';

            } else {

                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);
                $user = $result ? mysqli_fetch_assoc($result) : null;

                mysqli_stmt_close($stmt);

                if (!$user) {

                    $pesan = "Data akun tidak ditemukan.";
                    $tipe_pesan = 'error';

                } elseif (!password_verify($password_lama, $user['password'])) {

                    $pesan = "Password lama yang kamu masukkan salah.";
                    $tipe_pesan = 'error';

                } else {

                    $password_hash = password_hash(
                        $password_baru,
                        PASSWORD_DEFAULT
                    );

                    $stmt_update = mysqli_prepare(
                        $conn,
                        "UPDATE users
                         SET password = ?
                         WHERE id = ? AND role = 'warga'"
                    );

                    if (!$stmt_update) {

                        $pesan = "Gagal menyiapkan perubahan password.";
                        $tipe_pesan = 'error';

                    } else {

                        mysqli_stmt_bind_param(
                            $stmt_update,
                            "si",
                            $password_hash,
                            $user_id
                        );

                        if (mysqli_stmt_execute($stmt_update)) {

                            // Regenerasi session ID
                            session_regenerate_id(true);

                            // Regenerasi CSRF Token
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                            // Catat aktivitas
                            if (!catatAktivitas(
                                $conn,
                                'Password Diubah',
                                'Password akun berhasil diubah oleh warga.'
                            )) {
                                error_log(
                                    "Gagal mencatat aktivitas perubahan password untuk user #" . $user_id
                                );
                            }

                            $pesan = "Password berhasil diubah.";
                            $tipe_pesan = 'success';

                        } else {

                            $pesan = "Gagal mengubah password.";
                            $tipe_pesan = 'error';
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

    <title>Ubah Password - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">


    <style>

        /* =====================================
           GLOBAL
        ===================================== */

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #F2F7FA;
            font-family: 'Poppins', sans-serif;
            color: #183B55;
        }


        /* =====================================
           SIDEBAR WARGA
        ===================================== */

        .warga-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            z-index: 1001;
            color: #fff;
            background: linear-gradient(180deg, #071F33, #0B2D46);
            border-right: 1px solid rgba(255,255,255,.08);
        }

        .sidebar-brand {
            flex-shrink: 0;
            padding: 24px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }

        .sidebar-brand-top {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-logo {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            padding: 3px;
            border-radius: 50%;
            background: #fff;
            overflow: hidden;
        }

        .sidebar-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .sidebar-brand-top > div:last-child {
            flex: 1;
            min-width: 0;
        }

        .sidebar-brand h2 {
            margin: 0;
            color: #fff;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 700;
        }

        .sidebar-brand p {
            margin: 4px 0 0;
            color: #AFC4D2;
            font-size: 9px;
            line-height: 1.5;
            letter-spacing: .4px;
        }

        .sidebar-nav {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
            padding: 14px 12px 20px;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .sidebar-nav::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        .sidebar-section-title {
            margin: 13px 0 6px;
            padding: 0 10px;
            color: #8FA9BA;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .sidebar-link {
            min-height: 43px;
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 12px;
            margin: 2px 0;
            border-radius: 9px;
            color: #D8E5ED;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: .2s ease;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: #fff;
            background: rgba(24,195,216,.17);
        }

        .sidebar-link.active {
            box-shadow: inset 3px 0 0 #18C3D8;
        }

        .sidebar-icon {
            width: 22px;
            min-width: 22px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-logout:hover {
            background: rgba(255,90,90,.14);
        }


        /* =====================================
           KONTEN UTAMA
        ===================================== */

        .warga-main {
            width: calc(100% - 250px);
            min-height: 100vh;
            margin-left: 250px;
            padding: 30px 35px;
        }


        /* =====================================
           HEADER
        ===================================== */

        .page-header-row {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .warga-heading {
            flex: 1;
            min-width: 0;
        }

        .warga-heading h1 {
            margin: 0;
            color: #102F43;
            font-size: 25px;
            font-weight: 750;
            letter-spacing: -.4px;
        }

        .warga-heading p {
            margin: 7px 0 0;
            color: #718493;
            font-size: 12px;
            line-height: 1.7;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .mobile-menu-button {
            display: none;
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(135deg, #071F33, #147F91);
            font-size: 22px;
            cursor: pointer;
        }

        .btn-kembali {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 17px;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(135deg, #071F33, #147F91);
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            box-shadow: 0 5px 13px rgba(7,31,51,.15);
            transition: .2s ease;
        }

        .btn-kembali:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 16px rgba(7,31,51,.22);
        }

        .sidebar-overlay {
            display: none;
        }


        /* =====================================
           NOTIFIKASI
        ===================================== */

        .password-alert {
            padding: 13px 16px;
            margin-bottom: 22px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 500;
            line-height: 1.7;
        }

        .password-alert.success {
            border: 1px solid #BDE8CE;
            color: #18794E;
            background: #E8F7EF;
        }

        .password-alert.error {
            border: 1px solid #F1C3C3;
            color: #A33A3A;
            background: #FFF0F0;
        }


        /* =====================================
           KARTU PASSWORD
        ===================================== */

        .password-card {
            width: 100%;
            max-width: 850px;
            padding: 25px;
            border: 1px solid #E5EDF2;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 3px 12px rgba(7,31,51,.035);
        }

        .password-card-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-bottom: 20px;
            margin-bottom: 23px;
            border-bottom: 1px solid #EAF0F4;
        }

        .password-icon {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            color: #147F91;
            background: #E8F6F8;
            font-size: 21px;
        }

        .password-card-header h2 {
            margin: 0;
            color: #102F43;
            font-size: 17px;
            font-weight: 700;
        }

        .password-card-header p {
            margin: 6px 0 0;
            color: #718493;
            font-size: 11px;
            line-height: 1.7;
        }


        /* =====================================
           FORM PASSWORD
        ===================================== */

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #40596B;
            font-size: 12px;
            font-weight: 600;
        }

        .password-input-wrapper {
            position: relative;
        }

        .password-input-wrapper input {
            width: 100%;
            padding: 12px 85px 12px 14px;
            border: 1px solid #DCE6EC;
            border-radius: 9px;
            background: #fff;
            color: #183B55;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            outline: none;
            transition: .2s ease;
        }

        .password-input-wrapper input:focus {
            border-color: #18AFC1;
            box-shadow: 0 0 0 3px rgba(24,195,216,.10);
        }

        .password-input-wrapper input::placeholder {
            color: #9AAAB5;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 13px;
            transform: translateY(-50%);
            padding: 4px;
            border: none;
            background: transparent;
            color: #147F91;
            font-family: 'Poppins', sans-serif;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
        }

        .toggle-password:hover {
            color: #071F33;
        }

        .password-help {
            margin-top: 7px;
            color: #8293A0;
            font-size: 10px;
        }


        /* =====================================
           INFORMASI KEAMANAN
        ===================================== */

        .security-info {
            padding: 15px 17px;
            margin-top: 23px;
            border: 1px solid #D9EAF0;
            border-radius: 10px;
            color: #637D89;
            background: #F2F8FA;
            font-size: 11px;
            line-height: 1.8;
        }

        .security-info strong {
            color: #0B5268;
            font-weight: 700;
        }


        /* =====================================
           TOMBOL SIMPAN
        ===================================== */

        .password-actions {
            display: flex;
            justify-content: flex-end;
            padding-top: 21px;
            margin-top: 24px;
            border-top: 1px solid #EAF0F4;
        }

        .btn-simpan-password {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 20px;
            border: none;
            border-radius: 9px;
            color: #fff;
            background: linear-gradient(135deg, #071F33, #147F91);
            font-family: 'Poppins', sans-serif;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 5px 13px rgba(7,31,51,.13);
            transition: .2s ease;
        }

        .btn-simpan-password:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 16px rgba(7,31,51,.20);
        }


        /* =====================================
           RESPONSIVE TABLET
        ===================================== */

        @media (max-width: 1000px) {

            .warga-main {
                padding: 25px 22px;
            }

        }


        /* =====================================
           RESPONSIVE MOBILE
           HEADER SAMA DENGAN RIWAYAT PENARIKAN
        ===================================== */

        @media (max-width: 768px) {

            .warga-sidebar {
                width: 260px;
                min-width: 260px;
                transform: translateX(-100%);
                transition: transform .3s ease;
            }

            .warga-sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: block;
                position: fixed;
                inset: 0;
                z-index: 1000;
                background: rgba(0,0,0,.45);
                opacity: 0;
                visibility: hidden;
                transition: .3s ease;
            }

            .sidebar-overlay.show {
                opacity: 1;
                visibility: visible;
            }

            .warga-main {
                width: 100%;
                margin-left: 0;
                padding: 22px 18px 30px;
            }

            /* Header tetap horizontal */
            .page-header-row {
                display: flex;
                flex-direction: row;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
            }

            .warga-heading {
                flex: 1;
                min-width: 0;
            }

            .warga-heading h1 {
                font-size: 22px;
            }

            .warga-heading p {
                font-size: 11px;
            }

            /* Hamburger dan tombol kembali bertumpuk */
            .header-actions {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                justify-content: flex-start;
                flex-shrink: 0;
                gap: 8px;
            }

            .mobile-menu-button {
                display: flex;
            }

            .btn-kembali {
                padding: 9px 12px;
                font-size: 10px;
            }

            .password-card {
                padding: 22px;
            }

        }


        /* =====================================
           RESPONSIVE HP KECIL
        ===================================== */

        @media (max-width: 480px) {

            .warga-main {
                padding: 19px 13px 25px;
            }

            .page-header-row {
                gap: 8px;
            }

            .warga-heading h1 {
                font-size: 18px;
            }

            .warga-heading p {
                font-size: 10px;
                line-height: 1.6;
            }

            .header-actions {
                gap: 6px;
            }

            .mobile-menu-button {
                width: 39px;
                height: 39px;
                font-size: 20px;
            }

            .btn-kembali {
                padding: 9px 10px;
                font-size: 9px;
            }

            .password-card {
                padding: 17px 14px;
            }

            .password-card-header {
                gap: 11px;
            }

            .password-icon {
                width: 42px;
                height: 42px;
                font-size: 19px;
            }

            .password-card-header h2 {
                font-size: 15px;
            }

            .password-card-header p {
                font-size: 10px;
            }

            .password-input-wrapper input {
                padding: 11px 80px 11px 11px;
                font-size: 11px;
            }

            .password-actions {
                margin-top: 20px;
            }

            .btn-simpan-password {
                width: 100%;
            }

        }

    </style>

</head>

<body>


<!-- =====================================
     SIDEBAR WARGA
===================================== -->

<aside class="warga-sidebar" id="wargaSidebar">

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


        <div class="sidebar-section-title">Layanan Warga</div>

        <a href="qr_saya.php" class="sidebar-link">
            <span class="sidebar-icon">▣</span>
            <span>QR Code Saya</span>
        </a>

        <a href="ajukan_setoran.php" class="sidebar-link">
            <span class="sidebar-icon">♻️</span>
            <span>Ajukan Setoran</span>
        </a>

        <a href="riwayat_setoran.php" class="sidebar-link">
            <span class="sidebar-icon">📦</span>
            <span>Riwayat Setoran</span>
        </a>

        <a href="riwayat_tabungan.php" class="sidebar-link">
            <span class="sidebar-icon">💰</span>
            <span>Riwayat Tabungan</span>
        </a>

        <a href="ajukan_penarikan.php" class="sidebar-link">
            <span class="sidebar-icon">💸</span>
            <span>Penarikan Tabungan</span>
        </a>

        <a href="riwayat_penarikan.php" class="sidebar-link">
            <span class="sidebar-icon">📋</span>
            <span>Riwayat Penarikan</span>
        </a>

        <a href="notifikasi.php" class="sidebar-link">
            <span class="sidebar-icon">🔔</span>
            <span>Notifikasi</span>
        </a>


        <div class="sidebar-section-title">Akun</div>

        <a href="profil.php" class="sidebar-link active">
            <span class="sidebar-icon">⚙️</span>
            <span>Profil Saya</span>
        </a>

        <a href="../auth/logout.php" class="sidebar-link sidebar-logout">
            <span class="sidebar-icon">🚪</span>
            <span>Logout</span>
        </a>

    </nav>

</aside>


<!-- OVERLAY SIDEBAR -->

<div class="sidebar-overlay" id="sidebarOverlay"></div>


<!-- =====================================
     KONTEN UTAMA
===================================== -->

<main class="warga-main">


    <!-- HEADER -->

    <div class="page-header-row">

        <div class="warga-heading">

            <h1>Ubah Password</h1>

            <p>
                Perbarui password akun kamu untuk menjaga keamanan akun.
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

            <a href="profil.php" class="btn-kembali">
                <span>←</span>
                Kembali ke Profil
            </a>

        </div>

    </div>


    <!-- NOTIFIKASI -->

    <?php if ($pesan !== '') : ?>

        <div class="password-alert <?= $tipe_pesan === 'success' ? 'success' : 'error' ?>">

            <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>

        </div>

    <?php endif; ?>


    <!-- KARTU PASSWORD -->

    <section class="password-card">


        <!-- HEADER KARTU -->

        <div class="password-card-header">

            <div class="password-icon">
                🔐
            </div>

            <div>

                <h2>Keamanan Akun</h2>

                <p>
                    Masukkan password lama, kemudian buat password baru
                    untuk akun Bank Sampah Digital kamu.
                </p>

            </div>

        </div>


        <!-- FORM UBAH PASSWORD -->

        <form method="POST" action="ubah_password.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
            >


            <!-- PASSWORD LAMA -->

            <div class="form-group">

                <label for="password_lama">
                    Password Lama
                </label>

                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="password_lama"
                        name="password_lama"
                        placeholder="Masukkan password lama"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword('password_lama', this)"
                    >
                        Lihat
                    </button>

                </div>

            </div>


            <!-- PASSWORD BARU -->

            <div class="form-group">

                <label for="password_baru">
                    Password Baru
                </label>

                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="password_baru"
                        name="password_baru"
                        placeholder="Masukkan password baru"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword('password_baru', this)"
                    >
                        Lihat
                    </button>

                </div>

                <div class="password-help">
                    Minimal 8 karakter.
                </div>

            </div>


            <!-- KONFIRMASI PASSWORD -->

            <div class="form-group">

                <label for="konfirmasi_password">
                    Konfirmasi Password Baru
                </label>

                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="konfirmasi_password"
                        name="konfirmasi_password"
                        placeholder="Ulangi password baru"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword('konfirmasi_password', this)"
                    >
                        Lihat
                    </button>

                </div>

            </div>


            <!-- INFORMASI KEAMANAN -->

            <div class="security-info">

                <strong>Tips keamanan:</strong>

                Gunakan password yang sulit ditebak dan jangan menggunakan
                password yang sama dengan akun lain.

            </div>


            <!-- TOMBOL SIMPAN -->

            <div class="password-actions">

                <button
                    type="submit"
                    class="btn-simpan-password"
                >
                    Simpan Password
                </button>

            </div>

        </form>

    </section>

</main>


<!-- =====================================
     JAVASCRIPT
===================================== -->

<script>

// ===================================
// TOGGLE PASSWORD
// ===================================

function togglePassword(id, button) {

    const input = document.getElementById(id);

    if (input.type === 'password') {

        input.type = 'text';
        button.textContent = 'Sembunyikan';

    } else {

        input.type = 'password';
        button.textContent = 'Lihat';

    }

}


// ===================================
// HAMBURGER MENU
// ===================================

document.addEventListener('DOMContentLoaded', function () {

    const menuButton = document.getElementById('mobileMenuButton');
    const sidebar = document.getElementById('wargaSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (menuButton && sidebar && overlay) {

        function tutupMenu() {

            sidebar.classList.remove('open');
            overlay.classList.remove('show');

            menuButton.innerHTML = '☰';

            menuButton.setAttribute('aria-expanded', 'false');

        }

        menuButton.addEventListener('click', function () {

            const terbuka = sidebar.classList.toggle('open');

            overlay.classList.toggle('show', terbuka);

            menuButton.innerHTML = terbuka ? '✕' : '☰';

            menuButton.setAttribute(
                'aria-expanded',
                String(terbuka)
            );

        });

        overlay.addEventListener('click', tutupMenu);

        sidebar.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', tutupMenu);
        });

        document.addEventListener('keydown', function (event) {

            if (event.key === 'Escape') {
                tutupMenu();
            }

        });

    }

});

</script>

</body>
</html>
