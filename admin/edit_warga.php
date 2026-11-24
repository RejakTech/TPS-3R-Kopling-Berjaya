
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

// ========================================
// PROSES UPDATE
// ========================================

$error = '';
$success = '';

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

        // ========================================
        // VALIDASI
        // ========================================

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

        // ========================================
        // CEK USERNAME DAN EMAIL DUPLIKAT
        // ========================================

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

        // ========================================
        // UPDATE DATA WARGA
        // ========================================

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

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    mysqli_stmt_close($update);

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

    <title>Edit Data Warga | Admin TPS 3R</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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
            font-family: 'sans-serif', Arial;
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
            min-height: 100vh;
            min-width: 0;
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
           HEADER HALAMAN
        ========================================== */

        .edit-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .edit-page-heading {
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .edit-page-icon {
            width: 48px;
            height: 48px;
            min-width: 48px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 13px;
            background: #E0F5F7;

            color: #159BAE;
            font-size: 22px;
        }

        .edit-page-heading h1 {
            margin: 0;
            color: #0B354D;
            font-size: 26px;
            font-weight: 750;
            line-height: 1.3;
        }

        .edit-page-heading p {
            margin: 6px 0 0;
            color: #718995;
            font-size: 12px;
            line-height: 1.6;
        }

        .mobile-menu-button {
            display: none;

            width: 42px;
            height: 42px;
            min-width: 42px;

            align-items: center;
            justify-content: center;

            border: none;
            border-radius: 10px;

            color: #fff;
            background: linear-gradient(110deg, #0B354D, #17B8C8);

            font-size: 21px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(11,53,77,.2);
        }

        .sidebar-overlay {
            display: none;
        }

        /* ==========================================
           FORM CARD
        ========================================== */

        .edit-warga-wrapper {
            width: 100%;
            max-width: 950px;
            margin: 0 auto;
        }

        .edit-warga-card {
            overflow: hidden;

            background: #fff;
            border: 1px solid #E1EAED;
            border-radius: 16px;

            box-shadow: 0 5px 20px rgba(11,45,70,.045);
        }

        .edit-warga-card-header {
            display: flex;
            align-items: center;
            gap: 13px;

            padding: 22px 28px;

            background: linear-gradient(110deg, #0B354D, #126B7C);
        }

        .edit-card-header-icon {
            width: 42px;
            height: 42px;
            min-width: 42px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 11px;
            background: rgba(255,255,255,.14);

            color: #fff;
            font-size: 19px;
        }

        .edit-warga-card-header h2 {
            margin: 0;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
        }

        .edit-warga-card-header p {
            margin: 4px 0 0;
            color: #C6E4E9;
            font-size: 11px;
        }

        .edit-warga-card-body {
            padding: 30px;
        }

        /* ALERT */
        .edit-warga-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;

            margin-bottom: 24px;
            padding: 14px 16px;

            border-radius: 10px;
            font-size: 12px;
            line-height: 1.6;
        }

        .edit-warga-alert.error {
            background: #FFF0F0;
            border: 1px solid #F3D0D2;
            color: #B42318;
        }

        .alert-icon {
            font-size: 17px;
            flex-shrink: 0;
        }

        /* FORM */
        .edit-warga-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .edit-warga-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .edit-warga-group.full {
            grid-column: 1 / -1;
        }

        .edit-warga-group label {
            color: #294456;
            font-size: 12px;
            font-weight: 700;
        }

        .required-mark {
            color: #E05252;
        }

        .edit-warga-group input,
        .edit-warga-group textarea,
        .edit-warga-group select {
            width: 100%;
            box-sizing: border-box;

            padding: 12px 14px;

            border: 1px solid #DCE6EA;
            border-radius: 9px;

            background: #FAFCFD;
            color: #294456;

            font-family: inherit;
            font-size: 12px;

            outline: none;
            transition: .2s ease;
        }

        .edit-warga-group input:hover,
        .edit-warga-group textarea:hover,
        .edit-warga-group select:hover {
            border-color: #A9CBD2;
        }

        .edit-warga-group input:focus,
        .edit-warga-group textarea:focus,
        .edit-warga-group select:focus {
            border-color: #159BAE;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(21,155,174,.10);
        }

        .edit-warga-group input {
            min-height: 45px;
        }

        .edit-warga-group textarea {
            min-height: 115px;
            resize: vertical;
            line-height: 1.7;
        }

        .edit-warga-group select {
            min-height: 45px;
            cursor: pointer;
        }

        .edit-warga-group small {
            color: #91A2AC;
            font-size: 10px;
            line-height: 1.5;
        }

        /* STATUS */
        .edit-status-note {
            display: flex;
            align-items: center;
            gap: 8px;

            margin-top: 3px;

            color: #82919C;
            font-size: 10px;
        }

        /* ==========================================
           TOMBOL FORM
        ========================================== */

        .edit-warga-actions {
            grid-column: 1 / -1;

            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 11px;

            margin-top: 7px;
            padding-top: 23px;

            border-top: 1px solid #EAF0F4;
        }

        .btn-edit-warga-cancel,
        .btn-edit-warga-save {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            min-height: 44px;
            padding: 0 20px;

            border-radius: 9px;

            text-decoration: none;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;

            cursor: pointer;
            transition: .2s ease;
        }

        .btn-edit-warga-cancel {
            background: #F1F5F7;
            border: 1px solid #E1E9ED;
            color: #526671;
        }

        .btn-edit-warga-cancel:hover {
            background: #E5EDF0;
            color: #294456;
        }

        .btn-edit-warga-save {
            border: none;
            background: linear-gradient(110deg, #0B354D, #17B8C8);
            color: #fff;

            box-shadow: 0 4px 12px rgba(11,53,77,.13);
        }

        .btn-edit-warga-save:hover {
            background: linear-gradient(110deg, #082B40, #109EAF);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(11,53,77,.18);
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
                z-index: 999 !important;
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

            .edit-page-heading h1 {
                font-size: 23px;
            }
        }

        /* ==========================================
           RESPONSIVE HP
        ========================================== */

        @media (max-width: 550px) {

            .admin-content {
                padding: 20px 14px 30px;
            }

            .edit-page-header {
                align-items: flex-start;
                gap: 10px;
                margin-bottom: 20px;
            }

            .edit-page-heading {
                gap: 10px;
            }

            .edit-page-icon {
                width: 40px;
                height: 40px;
                min-width: 40px;
                font-size: 19px;
            }

            .edit-page-heading h1 {
                font-size: 19px;
            }

            .edit-page-heading p {
                font-size: 10px;
            }

            .mobile-menu-button {
                width: 39px;
                height: 39px;
                min-width: 39px;
                font-size: 19px;
            }

            .edit-warga-card {
                border-radius: 13px;
            }

            .edit-warga-card-header {
                padding: 17px;
            }

            .edit-card-header-icon {
                width: 37px;
                height: 37px;
                min-width: 37px;
                font-size: 17px;
            }

            .edit-warga-card-header h2 {
                font-size: 14px;
            }

            .edit-warga-card-header p {
                font-size: 10px;
            }

            .edit-warga-card-body {
                padding: 20px 16px;
            }

            .edit-warga-form {
                grid-template-columns: 1fr;
                gap: 17px;
            }

            .edit-warga-group.full {
                grid-column: auto;
            }

            .edit-warga-actions {
                grid-column: auto;
                flex-direction: column-reverse;
                align-items: stretch;
                gap: 9px;
            }

            .btn-edit-warga-cancel,
            .btn-edit-warga-save {
                width: 100%;
            }
        }

    </style>
</head>

<body>

<!-- ==========================================
     SIDEBAR
========================================== -->

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
            <span class="sidebar-icon">📊</span>
            <span>Dashboard</span>
        </a>

        <div class="sidebar-section-title">Pengelolaan</div>

        <a href="warga.php" class="sidebar-link active">
            <span class="sidebar-icon">👥</span>
            <span>Data Warga</span>
        </a>

        <a href="petugas.php" class="sidebar-link">
            <span class="sidebar-icon">🧑‍💼</span>
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
            <span class="sidebar-icon">🔄</span>
            <span>Mutasi Tabungan</span>
        </a>

        <a href="penarikan.php" class="sidebar-link">
            <span class="sidebar-icon">💳</span>
            <span>Penarikan Tabungan</span>
        </a>

        <a href="laporan.php" class="sidebar-link">
            <span class="sidebar-icon">📑</span>
            <span>Laporan</span>
        </a>

        <a href="backup_database.php" class="sidebar-link">
            <span class="sidebar-icon">🗄️</span>
            <span>Backup Database</span>
        </a>

        <a href="log_aktivitas.php" class="sidebar-link">
            <span class="sidebar-icon">📋</span>
            <span>Log Aktivitas</span>
        </a>

        <div class="sidebar-section-title">Akun</div>

        <a href="profil.php" class="sidebar-link">
            <span class="sidebar-icon">👤</span>
            <span>Profil</span>
        </a>

        <a href="ubah_password.php" class="sidebar-link">
            <span class="sidebar-icon">🔐</span>
            <span>Ubah Password</span>
        </a>

    </nav>

</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ==========================================
     KONTEN UTAMA
========================================== -->

<main class="admin-main">
    <div class="admin-content">
        <!-- HEADER -->
        <div class="edit-page-header">
            <div class="edit-page-heading">
                <div>
                    <h1>Edit Data Warga</h1>
                    <p>Kelola dan perbarui informasi akun warga TPS 3R Kopling Berjaya.</p>
                </div>

            </div>

            <button
                type="button"
                class="mobile-menu-button"
                id="mobileMenuButton"
                aria-label="Buka menu navigasi"
            >
                ☰
            </button>

        </div>

        <!-- FORM CARD -->
        <div class="edit-warga-wrapper">

            <div class="edit-warga-card">

                <div class="edit-warga-card-header">
                    <div>
                        <h2>Informasi Akun Warga</h2>
                        <p>Pastikan seluruh informasi yang diperbarui sudah benar.</p>
                    </div>

                </div>

                <div class="edit-warga-card-body">

                    <?php if ($error !== ''): ?>

                        <div class="edit-warga-alert error">
                            <span class="alert-icon">⚠️</span>
                            <span>
                                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>

                    <?php endif; ?>

                    <form method="POST" class="edit-warga-form">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
                        >

                        <div class="edit-warga-group">

                            <label for="nama_lengkap">
                                Nama Lengkap <span class="required-mark">*</span>
                            </label>

                            <input
                                type="text"
                                id="nama_lengkap"
                                name="nama_lengkap"
                                maxlength="100"
                                placeholder="Masukkan nama lengkap"
                                value="<?php echo htmlspecialchars($nama_lengkap, ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >

                        </div>

                        <div class="edit-warga-group">

                            <label for="username">
                                Username <span class="required-mark">*</span>
                            </label>

                            <input
                                type="text"
                                id="username"
                                name="username"
                                maxlength="50"
                                placeholder="Masukkan username"
                                value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >

                        </div>

                        <div class="edit-warga-group">

                            <label for="email">
                                Email <span class="required-mark">*</span>
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                maxlength="100"
                                placeholder="contoh@email.com"
                                value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >

                        </div>

                        <div class="edit-warga-group">

                            <label for="no_hp">
                                Nomor Handphone
                            </label>

                            <input
                                type="text"
                                id="no_hp"
                                name="no_hp"
                                maxlength="20"
                                placeholder="Masukkan nomor handphone"
                                value="<?php echo htmlspecialchars($no_hp ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            >

                        </div>

                        <div class="edit-warga-group full">

                            <label for="alamat">
                                Alamat Lengkap
                            </label>

                            <textarea
                                id="alamat"
                                name="alamat"
                                maxlength="500"
                                placeholder="Masukkan alamat lengkap warga..."
                            ><?php echo htmlspecialchars($alamat ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>

                        </div>

                        <div class="edit-warga-group">

                            <label for="status">
                                Status Akun <span class="required-mark">*</span>
                            </label>

                            <select
                                id="status"
                                name="status"
                                required
                            >

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

                            <div class="edit-status-note">
                                <span>ℹ️</span>
                                <span>Pilih status akun sesuai kondisi warga.</span>
                            </div>

                        </div>

                        <!-- TOMBOL -->
                        <div class="edit-warga-actions">

                            <a
                                href="warga.php"
                                class="btn-edit-warga-cancel"
                            >
                                <span>←</span>
                                Batal
                            </a>

                            <button
                                type="submit"
                                class="btn-edit-warga-save"
                            >
                                <span>✓</span>
                                Simpan Perubahan
                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</main>

<!-- ==========================================
     JAVASCRIPT SIDEBAR MOBILE
========================================== -->

<script>
    const sidebar = document.getElementById('adminSidebar');
    const menuButton = document.getElementById('mobileMenuButton');
    const overlay = document.getElementById('sidebarOverlay');

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

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });
</script>

</body>
</html>
