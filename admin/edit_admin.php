<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
require_once '../config/log_aktivitas.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

$error = '';
$success = '';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header("Location: kelola_admin.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil data admin
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, nama_lengkap, username, email, no_hp, status
     FROM users
     WHERE id = ?
     AND role = 'admin'
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$admin) {
    header("Location: kelola_admin.php?status=not_found");
    exit;
}

/*
|--------------------------------------------------------------------------
| PROSES UPDATE
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = "Token keamanan tidak valid.";
    } else {

        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $no_hp = trim($_POST['no_hp'] ?? '');
        $password = $_POST['password'] ?? '';
        $status = $_POST['status'] ?? '';

        /*
        |--------------------------------------------------------------------------
        | Validasi
        |--------------------------------------------------------------------------
        */
        if ($nama_lengkap === '' || strlen($nama_lengkap) < 3) {

            $error = "Nama lengkap minimal 3 karakter.";

        } elseif ($username === '' || strlen($username) < 4) {

            $error = "Username minimal 4 karakter.";

        } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {

            $error = "Username hanya boleh menggunakan huruf, angka, titik, underscore, dan strip.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = "Format email tidak valid.";

        } elseif (!in_array($status, ['aktif', 'nonaktif'], true)) {

            $error = "Status tidak valid.";

        } elseif ($password !== '' && strlen($password) < 8) {

            $error = "Password baru minimal 8 karakter.";

        } elseif (
            (int)$id === (int)$_SESSION['user_id'] &&
            $status === 'nonaktif'
        ) {

            $error = "Anda tidak dapat menonaktifkan akun yang sedang digunakan.";

        }

        /*
        |--------------------------------------------------------------------------
        | Cek username/email duplikat
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

            mysqli_stmt_bind_param(
                $stmt,
                "ssi",
                $username,
                $email,
                $id
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $duplikat = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if ($duplikat) {
                $error = "Username atau email sudah digunakan oleh admin lain.";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Jangan nonaktifkan admin terakhir
        |--------------------------------------------------------------------------
        */
        if (
            $error === '' &&
            $status === 'nonaktif' &&
            (int)$admin['status'] === 1
        ) {

            $stmt = mysqli_prepare(
                $conn,
                "SELECT COUNT(*) AS total
                 FROM users
                 WHERE role = 'admin'
                 AND status = 'aktif'
                 AND id != ?"
            );

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if ((int)($data['total'] ?? 0) < 1) {
                $error = "Admin terakhir tidak dapat dinonaktifkan.";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */
        if ($error === '') {

            if ($password !== '') {

                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE users
                     SET nama_lengkap = ?,
                         username = ?,
                         email = ?,
                         no_hp = ?,
                         password = ?,
                         status = ?
                     WHERE id = ?
                     AND role = 'admin'"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssssi",
                    $nama_lengkap,
                    $username,
                    $email,
                    $no_hp,
                    $password_hash,
                    $status,
                    $id
                );

            } else {

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE users
                     SET nama_lengkap = ?,
                         username = ?,
                         email = ?,
                         no_hp = ?,
                         status = ?
                     WHERE id = ?
                     AND role = 'admin'"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssssi",
                    $nama_lengkap,
                    $username,
                    $email,
                    $no_hp,
                    $status,
                    $id
                );
            }

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                catatAktivitas(
                    $conn,
                    'Edit Admin',
                    'Mengubah data admin ID #' . $id
                );

                $_SESSION['csrf_token'] = bin2hex(
                    random_bytes(32)
                );

                header("Location: kelola_admin.php?status=edit");
                exit;

            } else {

                $error = "Data admin gagal diperbarui.";
                mysqli_stmt_close($stmt);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Pertahankan input jika gagal
        |--------------------------------------------------------------------------
        */
        $admin['nama_lengkap'] = $nama_lengkap;
        $admin['username'] = $username;
        $admin['email'] = $email;
        $admin['no_hp'] = $no_hp;
        $admin['status'] = $status;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Admin - Bank Sampah Digital</title>

    <link rel="stylesheet"
          href="../assets/css/style.css">
<style>
/* LAYOUT UTAMA */
.admin-content {
    width: calc(100% - 250px);
    min-height: 100vh;
    margin-left: 250px;
    padding: 30px 35px;
    box-sizing: border-box;
    max-width: none;
}

/* HEADING */
.admin-heading {
    margin-bottom: 25px;
}

.admin-heading h1 {
    margin: 0 0 8px;
    font-size: 28px;
    color: #172033;
}

.admin-heading p {
    margin: 0;
    color: #6b7280;
}

/* FORM CARD */
.admin-form-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 28px;
    box-shadow: 0 4px 16px rgba(11, 53, 77, 0.04);
}

.admin-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-group label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}

.form-group input,
.form-group select {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 13px;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    outline: none;
    font-family: inherit;
    transition: border-color .2s ease;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #159BAE;
}

.form-full {
    grid-column: 1 / -1;
}

.form-help {
    font-size: 12px;
    color: #6b7280;
}

/* PESAN ERROR */
.form-alert {
    padding: 13px 16px;
    border-radius: 9px;
    margin-bottom: 20px;
}

.form-alert-error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* TOMBOL AKSI */
.form-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    margin-top: 28px;
    padding-top: 22px;
    border-top: 1px solid #edf1f2;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 11px 20px;
    border: 1px solid transparent;
    border-radius: 8px;
    text-decoration: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    transition: all .2s ease;
}

.btn-back {
    background: #ffffff;
    color: #0B354D;
    border-color: #dce5e8;
}

.btn-back:hover {
    background: #f2f7f8;
    border-color: #0B354D;
}

.btn-save {
    background: #0B4650;
    color: #ffffff;
    border-color: #0B4650;
}

.btn-save:hover {
    background: #159BAE;
    border-color: #159BAE;
}

/* RESPONSIVE */
@media (max-width: 700px) {
    .admin-content {
        width: 100%;
        margin-left: 0;
        padding: 20px 15px;
    }

    .admin-form-card {
        padding: 20px;
    }

    .admin-form-grid {
        grid-template-columns: 1fr;
    }

    .form-full {
        grid-column: auto;
    }

    .form-actions {
        flex-direction: column-reverse;
        align-items: stretch;
    }

    .btn {
        width: 100%;
        box-sizing: border-box;
        text-align: center;
    }
}
/* TOMBOL SIMPAN PERUBAHAN */
.btn-save {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 20px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    color: #FFFFFF;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 5px 13px rgba(11, 53, 77, .15);
    transition: .25s ease;
}

.btn-save:hover {
    background: linear-gradient(110deg, #082B40, #109EAF);
    transform: translateY(-2px);
}
</style>
</head>

<body>

<div class="admin-dashboard">
        
<aside class="admin-sidebar">

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

        <a href="kelola_admin.php" class="sidebar-link active">
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

        <a href="profil.php" class="sidebar-link ">
            <span class="sidebar-icon">⚙️</span>
            <span>Profil</span>
        </a>

        <a href="ubah_password.php" class="sidebar-link">
            <span class="sidebar-icon">🔑</span>
            <span>Ubah Password</span>
        </a>

        

    </nav>
</aside>
<div class="admin-content">

    <div class="admin-heading">

        <h1>Edit Admin</h1>

        <p>
            Perbarui informasi akun administrator.
        </p>

    </div>


    <?php if ($error !== ''): ?>

        <div class="form-alert form-alert-error">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ); ?>

        </div>

    <?php endif; ?>


    <div class="admin-form-card">

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrf_token,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >

            <div class="admin-form-grid">

                <div class="form-group">

                    <label for="nama_lengkap">
                        Nama Lengkap
                    </label>

                    <input
                        type="text"
                        id="nama_lengkap"
                        name="nama_lengkap"
                        value="<?= htmlspecialchars(
                            $admin['nama_lengkap'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        minlength="3"
                        maxlength="100"
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
                        value="<?= htmlspecialchars(
                            $admin['username'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        minlength="4"
                        maxlength="50"
                        pattern="[A-Za-z0-9._-]+"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="email">
                        Email
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars(
                            $admin['email'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="no_hp">
                        No. HP
                    </label>

                    <input
                        type="text"
                        id="no_hp"
                        name="no_hp"
                        value="<?= htmlspecialchars(
                            $admin['no_hp'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        maxlength="20"
                    >

                </div>


                <div class="form-group">

                    <label for="password">
                        Password Baru
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        maxlength="100"
                        placeholder="Kosongkan jika tidak diganti"
                    >

                    <span class="form-help">
                        Minimal 8 karakter. Kosongkan jika password tetap.
                    </span>

                </div>


                <div class="form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        required
                    >

                        <option
                            value="aktif"
                            <?= ($admin['status'] ?? '') === 'aktif'
                                ? 'selected'
                                : ''; ?>
                        >
                            Aktif
                        </option>

                        <option
                            value="nonaktif"
                            <?= ($admin['status'] ?? '') === 'nonaktif'
                                ? 'selected'
                                : ''; ?>
                        >
                            Nonaktif
                        </option>

                    </select>

                </div>

            </div>


            <div class="form-actions">

                <a
                    href="kelola_admin.php"
                    class="btn btn-back"
                >
                    ← Kembali
                </a>

                <button
                    type="submit"
                    class="btn btn-save"
                >
                    Simpan Perubahan
                </button>

            </div>

        </form>

    </div>

</div>

</body>

</html>