<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include_once '../config/koneksi.php';
require_once '../config/log_aktivitas.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int) $_SESSION['user_id'];

$pesan = '';
$tipe_pesan = '';

// ===============================
// PROSES UPDATE PROFIL
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $pesan = "Token keamanan tidak valid.";
        $tipe_pesan = 'error';
    } else {

        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $no_hp = trim($_POST['no_hp'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');

        // Validasi
        if ($nama_lengkap === '' || mb_strlen($nama_lengkap) > 100) {
            $pesan = "Nama lengkap wajib diisi dan maksimal 100 karakter.";
            $tipe_pesan = 'error';

        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pesan = "Format email tidak valid.";
            $tipe_pesan = 'error';

        } elseif (mb_strlen($no_hp) > 20) {
            $pesan = "Nomor HP maksimal 20 karakter.";
            $tipe_pesan = 'error';

        } elseif (mb_strlen($alamat) > 500) {
            $pesan = "Alamat maksimal 500 karakter.";
            $tipe_pesan = 'error';

        } else {

            // Cek email digunakan user lain
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1"
            );

            if (!$stmt) {
                $pesan = "Terjadi kesalahan pada sistem.";
                $tipe_pesan = 'error';
            } else {

                mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if (mysqli_num_rows($result) > 0) {

                    $pesan = "Email tersebut sudah digunakan oleh akun lain.";
                    $tipe_pesan = 'error';

                } else {

                    mysqli_stmt_close($stmt);

                    // Update profil
                    $stmt_update = mysqli_prepare(
                        $conn,
                        "UPDATE users
                         SET nama_lengkap = ?, email = ?, no_hp = ?, alamat = ?
                         WHERE id = ? AND role = 'warga'"
                    );

                    if (!$stmt_update) {

                        $pesan = "Gagal menyiapkan proses update.";
                        $tipe_pesan = 'error';

                    } else {

                        mysqli_stmt_bind_param(
                            $stmt_update,
                            "ssssi",
                            $nama_lengkap,
                            $email,
                            $no_hp,
                            $alamat,
                            $user_id
                        );

                        if (mysqli_stmt_execute($stmt_update)) {

                        // Update nama session
                        $_SESSION['nama'] = $nama_lengkap;

                        // ===================================
                        // CATAT AKTIVITAS PERUBAHAN PROFIL
                        // ===================================

                        if (!catatAktivitas(
                            $conn,
                            'Profil Diperbarui',
                            'Data profil akun berhasil diperbarui.'
                        )) {
                            error_log("Gagal mencatat aktivitas perubahan profil untuk user #" . $user_id);
                        }

                        // Regenerasi CSRF
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        $pesan = "Profil berhasil diperbarui.";
                        $tipe_pesan = 'success';

                        } else {

                            $pesan = "Gagal memperbarui profil.";
                            $tipe_pesan = 'error';
                        }

                        mysqli_stmt_close($stmt_update);
                    }
                }

                if (isset($stmt) && $stmt) {
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

// ===============================
// AMBIL DATA PROFIL
// ===============================
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, nama_lengkap, username, email, no_hp, alamat, status
     FROM users
     WHERE id = ? AND role = 'warga'
     LIMIT 1"
);

if (!$stmt) {
    die("Gagal mengambil data profil.");
}

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

// Data tampilan
$nama_lengkap = $user['nama_lengkap'] ?? '';
$username = $user['username'] ?? '';
$email = $user['email'] ?? '';
$no_hp = $user['no_hp'] ?? '';
$alamat = $user['alamat'] ?? '';
$status = $user['status'] ?? 'aktif';

// Inisial nama
$inisial = strtoupper(substr($nama_lengkap, 0, 1));
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profil Saya - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f5f8f7;
            color: #172a27;
        }

        .profil-page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 24px 50px;
        }

        /* HEADER */
        .profil-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
        }

        .profil-title h1 {
            margin: 0 0 6px;
            font-size: 28px;
            font-weight: 700;
            color: #123b35;
        }

        .profil-title p {
            margin: 0;
            color: #71817e;
            font-size: 14px;
        }

        .btn-kembali {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #145c52;
            background: #ffffff;
            border: 1px solid #dce7e4;
            padding: 11px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-kembali:hover {
            background: #edf7f4;
        }

        /* ALERT */
        .profil-alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 14px;
            font-weight: 500;
        }

        .profil-alert.success {
            background: #e9f8f1;
            border: 1px solid #b8e6d0;
            color: #176b4d;
        }

        .profil-alert.error {
            background: #fff0f0;
            border: 1px solid #f1c3c3;
            color: #a33a3a;
        }

        /* LAYOUT */
        .profil-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 24px;
            align-items: start;
        }

        /* CARD */
        .profil-card {
            background: #ffffff;
            border: 1px solid #e1eae8;
            border-radius: 16px;
            box-shadow: 0 4px 18px rgba(22, 57, 50, 0.04);
        }

        .btn-aktivitas {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            background: #f5f8f7;
            color: #35524c;
            border: 1px solid #d8e5e2;
            border-radius: 9px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-aktivitas:hover {
            background: #eaf2f0;
            border-color: #bcd8d2;
            color: #0f766e;
        }

        /* SIDEBAR PROFIL */
        .profil-summary {
            padding: 30px 24px;
            text-align: center;
        }

        .profil-avatar {
            width: 82px;
            height: 82px;
            margin: 0 auto 16px;
            border-radius: 50%;
            background: #e3f3ef;
            color: #0f766e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: 700;
        }

        .profil-summary h2 {
            margin: 0 0 6px;
            font-size: 20px;
            color: #173f38;
        }

        .profil-summary .username {
            margin: 0 0 18px;
            color: #7b8987;
            font-size: 14px;
        }

        .status-akun {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #eaf8f1;
            color: #16704e;
            padding: 7px 13px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #24a36f;
        }

        .profil-info {
            border-top: 1px solid #edf1f0;
            margin-top: 24px;
            padding-top: 20px;
            text-align: left;
        }

        .profil-info-item {
            margin-bottom: 17px;
        }

        .profil-info-item:last-child {
            margin-bottom: 0;
        }

        .profil-info-label {
            display: block;
            color: #8a9795;
            font-size: 11px;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .profil-info-value {
            color: #29413d;
            font-size: 13px;
            word-break: break-word;
        }

        .btn-ubah-password {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            background: #ffffff;
            color: #0f766e;
            border: 1px solid #b9dcd5;
            border-radius: 9px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-ubah-password:hover {
            background: #edf7f4;
            border-color: #0f766e;
        }

        /* FORM */
        .profil-form-card {
            padding: 30px;
        }

        .form-heading {
            margin-bottom: 25px;
        }

        .form-heading h2 {
            margin: 0 0 6px;
            color: #173f38;
            font-size: 20px;
        }

        .form-heading p {
            margin: 0;
            color: #7b8987;
            font-size: 13px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            margin-bottom: 8px;
            color: #40524f;
            font-size: 13px;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            border: 1px solid #d9e4e1;
            background: #ffffff;
            color: #243b37;
            border-radius: 10px;
            padding: 12px 13px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #159b83;
            box-shadow: 0 0 0 3px rgba(21, 155, 131, 0.09);
        }

        .form-group input[readonly] {
            background: #f5f7f7;
            color: #7c8987;
            cursor: not-allowed;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 105px;
        }

        .form-help {
            margin-top: 6px;
            font-size: 11px;
            color: #8a9795;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid #edf1f0;
        }

        .btn-simpan {
            border: none;
            background: #0f766e;
            color: white;
            padding: 12px 22px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-simpan:hover {
            background: #0c625c;
            transform: translateY(-1px);
        }

        /* MOBILE */
        @media (max-width: 800px) {
            .profil-page {
                padding: 24px 16px 40px;
            }

            .profil-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .profil-grid {
                grid-template-columns: 1fr;
            }

            .profil-form-card {
                padding: 22px 18px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .form-group.full {
                grid-column: auto;
            }

            .form-actions {
                justify-content: stretch;
            }

            .btn-simpan {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .profil-title h1 {
                font-size: 24px;
            }

            .profil-summary {
                padding: 25px 20px;
            }

            .btn-kembali {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>

<div class="profil-page">

    <!-- HEADER -->
    <div class="profil-header">

        <div class="profil-title">
            <h1>Profil Saya</h1>
            <p>Kelola informasi pribadi akun Bank Sampah Digital.</p>
        </div>

        <a href="dashboard.php" class="btn-kembali">
            ← Kembali ke Dashboard
        </a>

    </div>

    <!-- ALERT -->
    <?php if ($pesan !== ''): ?>
        <div class="profil-alert <?= $tipe_pesan === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="profil-grid">

        <!-- RINGKASAN PROFIL -->
        <div class="profil-card">

            <div class="profil-summary">

                <div class="profil-avatar">
                    <?= htmlspecialchars($inisial, ENT_QUOTES, 'UTF-8') ?>
                </div>

                <h2>
                    <?= htmlspecialchars($nama_lengkap, ENT_QUOTES, 'UTF-8') ?>
                </h2>

                <p class="username">
                    @<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                </p>

                <span class="status-akun">
                    <span class="status-dot"></span>
                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                </span>

                <div class="profil-info">

                    <div class="profil-info-item">
                        <span class="profil-info-label">Email</span>
                        <div class="profil-info-value">
                            <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>

                    <div class="profil-info-item">
                        <span class="profil-info-label">Nomor HP</span>
                        <div class="profil-info-value">
                            <?= htmlspecialchars($no_hp ?: '-', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>

                    <div class="profil-info-item">
                        <span class="profil-info-label">Alamat</span>
                        <div class="profil-info-value">
                            <?= nl2br(htmlspecialchars($alamat ?: '-', ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <!-- FORM EDIT -->
        <div class="profil-card profil-form-card">

            <div class="form-heading">
                <h2>Informasi Pribadi</h2>
                <p>Perbarui informasi yang digunakan pada akun Anda.</p>
            </div>

            <form method="POST" action="profil.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="form-grid">

                    <!-- NAMA -->
                    <div class="form-group">
                        <label for="nama_lengkap">Nama Lengkap</label>

                        <input
                            type="text"
                            id="nama_lengkap"
                            name="nama_lengkap"
                            value="<?= htmlspecialchars($nama_lengkap, ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <!-- USERNAME -->
                    <div class="form-group">
                        <label for="username">Username</label>

                        <input
                            type="text"
                            id="username"
                            value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                            readonly
                        >

                        <span class="form-help">
                            Username tidak dapat diubah.
                        </span>
                    </div>

                    <!-- EMAIL -->
                    <div class="form-group">
                        <label for="email">Email</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="150"
                            required
                        >
                    </div>

                    <!-- NO HP -->
                    <div class="form-group">
                        <label for="no_hp">Nomor HP</label>

                        <input
                            type="text"
                            id="no_hp"
                            name="no_hp"
                            value="<?= htmlspecialchars($no_hp, ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="20"
                            placeholder="Contoh: 081234567890"
                        >
                    </div>

                    <!-- ALAMAT -->
                    <div class="form-group full">
                        <label for="alamat">Alamat</label>

                        <textarea
                            id="alamat"
                            name="alamat"
                            maxlength="500"
                            placeholder="Masukkan alamat lengkap Anda..."
                        ><?= htmlspecialchars($alamat, ENT_QUOTES, 'UTF-8') ?></textarea>

                        <span class="form-help">
                            Maksimal 500 karakter.
                        </span>
                    </div>

                </div>

                <div class="form-actions">

                    <button type="submit" class="btn-simpan">
                        Simpan Perubahan
                    </button>

                    <a href="ubah_password.php" class="btn-ubah-password">
                        🔒 Ubah Password
                    </a>

                    <a href="aktivitas.php" class="btn-aktivitas">
                        🕘 Aktivitas Saya
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>