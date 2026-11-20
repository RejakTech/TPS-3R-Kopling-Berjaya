<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$error = "";

// Ambil ID petugas dari URL
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: petugas.php");
    exit;
}

// Ambil data petugas
$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM users WHERE id = ? AND role = 'petugas' LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$petugas = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$petugas) {
    header("Location: petugas.php");
    exit;
}

// Token keamanan
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Proses update
if (isset($_POST['update'])) {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Permintaan tidak valid. Silakan coba kembali.";

    } else {

        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $no_hp = trim($_POST['no_hp'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $status = $_POST['status'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($nama_lengkap == "" || $username == "") {
            $error = "Nama lengkap dan username wajib diisi.";

        } elseif (strlen($nama_lengkap) > 100) {
            $error = "Nama lengkap maksimal 100 karakter.";

        } elseif (!preg_match('/^[a-zA-Z0-9._-]{4,50}$/', $username)) {
            $error = "Username harus 4-50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau strip.";

        } elseif ($email != "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format email tidak valid.";

        } elseif (strlen($email) > 100) {
            $error = "Email maksimal 100 karakter.";

        } elseif (strlen($no_hp) > 20) {
            $error = "Nomor HP maksimal 20 karakter.";

        } elseif (!in_array($status, ['aktif', 'nonaktif'], true)) {
            $error = "Status akun tidak valid.";

        } elseif ($password != "" && strlen($password) < 8) {
            $error = "Password baru minimal 8 karakter.";

        } else {

            try {

                // Periksa username milik akun lain
                $cek = mysqli_prepare(
                    $conn,
                    "SELECT id FROM users
                     WHERE username = ? AND id != ?
                     LIMIT 1"
                );

                mysqli_stmt_bind_param($cek, "si", $username, $id);
                mysqli_stmt_execute($cek);
                mysqli_stmt_store_result($cek);

                if (mysqli_stmt_num_rows($cek) > 0) {

                    $error = "Username sudah digunakan oleh akun lain.";

                } else {

                    $email_db = $email !== "" ? $email : null;
                    $no_hp_db = $no_hp !== "" ? $no_hp : null;
                    $alamat_db = $alamat !== "" ? $alamat : null;

                    if ($password != "") {

                        // Update termasuk password
                        $password_hash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $query = mysqli_prepare(
                            $conn,
                            "UPDATE users SET
                                nama_lengkap = ?,
                                username = ?,
                                email = ?,
                                no_hp = ?,
                                alamat = ?,
                                status = ?,
                                password = ?
                             WHERE id = ? AND role = 'petugas'"
                        );

                        mysqli_stmt_bind_param(
                            $query,
                            "sssssssi",
                            $nama_lengkap,
                            $username,
                            $email_db,
                            $no_hp_db,
                            $alamat_db,
                            $status,
                            $password_hash,
                            $id
                        );

                    } else {

                        // Update tanpa mengubah password
                        $query = mysqli_prepare(
                            $conn,
                            "UPDATE users SET
                                nama_lengkap = ?,
                                username = ?,
                                email = ?,
                                no_hp = ?,
                                alamat = ?,
                                status = ?
                             WHERE id = ? AND role = 'petugas'"
                        );

                        mysqli_stmt_bind_param(
                            $query,
                            "ssssssi",
                            $nama_lengkap,
                            $username,
                            $email_db,
                            $no_hp_db,
                            $alamat_db,
                            $status,
                            $id
                        );
                    }

                    mysqli_stmt_execute($query);

                    mysqli_stmt_close($query);
                    mysqli_stmt_close($cek);

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header("Location: petugas.php?status=edit_berhasil");
                    exit;
                }

                mysqli_stmt_close($cek);

            } catch (mysqli_sql_exception $e) {

                if ($e->getCode() == 1062) {
                    $error = "Username sudah digunakan.";
                } else {
                    $error = "Terjadi kesalahan saat memperbarui data petugas.";
                }
            }
        }

        // Pertahankan input ketika validasi gagal
        $petugas['nama_lengkap'] = $nama_lengkap;
        $petugas['username'] = $username;
        $petugas['email'] = $email;
        $petugas['no_hp'] = $no_hp;
        $petugas['alamat'] = $alamat;
        $petugas['status'] = $status;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Petugas - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        /* =========================
           AREA KONTEN
           MENGIKUTI HALAMAN WARGA
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

        .edit-petugas-page {
            width: 100%;
            max-width: none;
            margin: 0;
            min-width: 0;
            box-sizing: border-box;
        }

        /* =========================
           HEADER
        ========================= */

        .admin-heading {
            margin-bottom: 25px;
        }

        .petugas-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            width: 100%;
        }

        .petugas-title-text {
            min-width: 0;
        }

        .admin-heading h1 {
            margin: 0 0 8px;
            color: #0B4650;
            font-size: 30px;
            font-weight: 700;
            line-height: 1.3;
        }

        .admin-heading p {
            margin: 0;
            color: #718995;
            font-size: 14px;
            line-height: 1.6;
        }

        /* Hamburger disembunyikan di laptop */
        .mobile-menu-button {
            display: none !important;
        }

        /* =========================
           FORM EDIT PETUGAS
        ========================= */

        .petugas-form-container {
            width: 100%;
            max-width: 100%;
            padding: 28px 30px;
            background: #FFFFFF;
            border: 1px solid #E1EAED;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(11, 45, 70, .05);
            box-sizing: border-box;
        }

        .petugas-form-heading {
            margin-bottom: 24px;
        }

        .petugas-form-heading h2 {
            margin: 0;
            color: #0B354D;
            font-size: 20px;
            font-weight: 700;
        }

        .petugas-form-heading p {
            margin: 7px 0 0;
            color: #718995;
            font-size: 13px;
            line-height: 1.5;
        }

        .form-alert-error {
            width: 100%;
            padding: 13px 16px;
            margin-bottom: 20px;
            border: 1px solid #F3D0D2;
            border-radius: 10px;
            background: #FFF0F0;
            color: #B42318;
            font-size: 13px;
            font-weight: 600;
            box-sizing: border-box;
        }

        .petugas-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            width: 100%;
        }

        .petugas-form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .petugas-form-group.full-width {
            grid-column: 1 / -1;
        }

        .petugas-form-group label {
            color: #315A69;
            font-size: 13px;
            font-weight: 600;
        }

        .petugas-form-group label span {
            color: #D64545;
        }

        .petugas-form-group input,
        .petugas-form-group select,
        .petugas-form-group textarea {
            display: block;
            width: 100%;
            min-width: 0;
            min-height: 44px;
            padding: 11px 13px;
            border: 1px solid #DCE8EC;
            border-radius: 9px;
            outline: none;
            background: #FBFDFE;
            color: #334E5A;
            font: inherit;
            font-size: 13px;
            box-sizing: border-box;
            transition: .2s ease;
        }

        .petugas-form-group textarea {
            min-height: 90px;
            resize: vertical;
        }

        .petugas-form-group input:focus,
        .petugas-form-group select:focus,
        .petugas-form-group textarea:focus {
            border-color: #17B8C8;
            box-shadow: 0 0 0 3px rgba(23, 184, 200, .12);
            background: #FFFFFF;
        }

        .petugas-form-group small {
            color: #81939B;
            font-size: 11px;
        }

        /* =========================
           TOMBOL FORM
        ========================= */

        .petugas-form-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #EDF2F4;
        }

        .btn-batal-petugas,
        .btn-simpan-petugas {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 20px;
            border: 0;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            box-sizing: border-box;
            transition: .2s ease;
        }

        .btn-batal-petugas {
            border: 1px solid #DCE8EC;
            background: #F5F8FA;
            color: #526671;
        }

        .btn-batal-petugas:hover {
            background: #EAF1F4;
        }

        .btn-simpan-petugas {
            background: linear-gradient(110deg, #0B354D, #17B8C8);
            color: #FFFFFF;
            box-shadow: 0 5px 13px rgba(11, 53, 77, .15);
        }

        .btn-simpan-petugas:hover {
            background: linear-gradient(110deg, #082B40, #109EAF);
            transform: translateY(-1px);
        }

        /* =========================
           RESPONSIVE SIDEBAR
           HAMBURGER HANYA DI HP
        ========================= */

        .sidebar-overlay {
            display: none;
        }

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
                padding: 18px 20px 25px !important;
            }

            .admin-heading {
                position: sticky;
                top: 0;
                z-index: 998;
                padding: 8px 0 14px;
                margin-bottom: 16px;
                background: #f4f9f8;
            }

            .admin-heading h1 {
                font-size: 26px;
            }

            .admin-heading p {
                font-size: 13px;
            }

            .mobile-menu-button {
                display: inline-flex !important;
                position: static !important;
                flex: 0 0 42px !important;
                width: 42px !important;
                height: 42px !important;
                align-items: center !important;
                justify-content: center !important;
                border: 0 !important;
                border-radius: 10px !important;
                background: linear-gradient(110deg, #0B354D, #17B8C8) !important;
                color: #FFFFFF !important;
                font-size: 23px !important;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(11, 53, 77, .2);
            }

            .petugas-form-container {
                padding: 23px 20px;
            }
        }

        /* =========================
           RESPONSIVE HP KECIL
        ========================= */

        @media (max-width: 600px) {

            .admin-content {
                padding: 16px 14px 22px !important;
            }

            .admin-heading h1 {
                font-size: 23px;
            }

            .admin-heading p {
                font-size: 12px;
            }

            .petugas-form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .petugas-form-group.full-width {
                grid-column: auto;
            }

            .petugas-form-container {
                padding: 20px 16px;
                border-radius: 12px;
            }

            .petugas-form-heading h2 {
                font-size: 18px;
            }

            .petugas-form-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .btn-batal-petugas,
            .btn-simpan-petugas {
                width: 100%;
            }
        }

        /* Pastikan laptop tidak menggunakan hamburger */
        @media (min-width: 801px) {
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

        <a href="petugas.php" class="sidebar-link active">
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

        <div class="edit-petugas-page">

            <div class="admin-heading">

                <div class="petugas-title-row">

                    <div class="petugas-title-text">
                        <h1>Edit Petugas</h1>
                        <p>Perbarui informasi akun petugas Bank Sampah Digital.</p>
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

            <div class="petugas-form-container">

                <div class="petugas-form-heading">
                    <h2>Formulir Edit Petugas</h2>
                    <p>Ubah informasi petugas sesuai kebutuhan.</p>
                </div>

                <?php if ($error != "") : ?>
                    <div class="form-alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
                    >

                    <div class="petugas-form-grid">

                        <div class="petugas-form-group">
                            <label>Nama Lengkap <span>*</span></label>
                            <input
                                type="text"
                                name="nama_lengkap"
                                value="<?php echo htmlspecialchars($petugas['nama_lengkap']); ?>"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div class="petugas-form-group">
                            <label>Username <span>*</span></label>
                            <input
                                type="text"
                                name="username"
                                value="<?php echo htmlspecialchars($petugas['username']); ?>"
                                minlength="4"
                                maxlength="50"
                                required
                            >
                        </div>

                        <div class="petugas-form-group">
                            <label>Email</label>
                            <input
                                type="email"
                                name="email"
                                value="<?php echo htmlspecialchars($petugas['email'] ?? ''); ?>"
                                maxlength="100"
                            >
                        </div>

                        <div class="petugas-form-group">
                            <label>Nomor HP</label>
                            <input
                                type="text"
                                name="no_hp"
                                value="<?php echo htmlspecialchars($petugas['no_hp'] ?? ''); ?>"
                                maxlength="20"
                            >
                        </div>

                        <div class="petugas-form-group full-width">
                            <label>Alamat</label>
                            <textarea
                                name="alamat"
                                rows="3"
                            ><?php echo htmlspecialchars($petugas['alamat'] ?? ''); ?></textarea>
                        </div>

                        <div class="petugas-form-group">
                            <label>Status Akun</label>

                            <select name="status" required>

                                <option
                                    value="aktif"
                                    <?php echo $petugas['status'] == 'aktif' ? 'selected' : ''; ?>
                                >
                                    Aktif
                                </option>

                                <option
                                    value="nonaktif"
                                    <?php echo $petugas['status'] == 'nonaktif' ? 'selected' : ''; ?>
                                >
                                    Nonaktif
                                </option>

                            </select>
                        </div>

                        <div class="petugas-form-group">
                            <label>Password Baru</label>
                            <input
                                type="password"
                                name="password"
                                placeholder="Kosongkan jika tidak ingin mengganti"
                                minlength="8"
                            >
                            <small>Password minimal 8 karakter.</small>
                        </div>

                    </div>

                    <div class="petugas-form-actions">

                        <a href="petugas.php" class="btn-batal-petugas">
                            Batal
                        </a>

                        <button
                            type="submit"
                            name="update"
                            class="btn-simpan-petugas"
                        >
                            Simpan Perubahan
                        </button>

                    </div>

                </form>

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