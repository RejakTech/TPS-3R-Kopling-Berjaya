<?php
session_start();

require_once '../config/koneksi.php';
require_once '../config/log_aktivitas.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header("Location: ../auth/login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int) $_SESSION['user_id'];
$errors = [];
$success = '';

/* =========================
   AMBIL DATA PROFIL
========================= */
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, nama_lengkap, username, email, no_hp, alamat, status
     FROM users
     WHERE id = ? AND role = 'petugas'
     LIMIT 1"
);

if (!$stmt) {
    die("Gagal menyiapkan query profil.");
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

/* =========================
   PROSES UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $csrf_token
        )
    ) {
        $errors[] = "Token keamanan tidak valid. Silakan coba lagi.";
    }

    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $no_hp        = trim($_POST['no_hp'] ?? '');
    $alamat       = trim($_POST['alamat'] ?? '');

    /* =========================
       VALIDASI
    ========================= */

    if ($nama_lengkap === '') {
        $errors[] = "Nama lengkap wajib diisi.";
    } elseif (mb_strlen($nama_lengkap) > 100) {
        $errors[] = "Nama lengkap maksimal 100 karakter.";
    }

    if ($username === '') {
        $errors[] = "Username wajib diisi.";
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
        $errors[] = "Username hanya boleh berisi huruf, angka, titik, underscore, dan tanda minus.";
    }

    if ($email === '') {
        $errors[] = "Email wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid.";
    } elseif (mb_strlen($email) > 150) {
        $errors[] = "Email maksimal 150 karakter.";
    }

    if ($no_hp !== '' && !preg_match('/^[0-9+\-\s()]{8,20}$/', $no_hp)) {
        $errors[] = "Format nomor HP tidak valid.";
    }

    if (mb_strlen($alamat) > 500) {
        $errors[] = "Alamat maksimal 500 karakter.";
    }

    /* =========================
       CEK DUPLIKAT USERNAME / EMAIL
    ========================= */

    if (empty($errors)) {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id
             FROM users
             WHERE (username = ? OR email = ?)
             AND id != ?
             LIMIT 1"
        );

        if (!$stmt) {
            $errors[] = "Gagal menyiapkan pemeriksaan data.";
        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "ssi",
                $username,
                $email,
                $user_id
            );

            mysqli_stmt_execute($stmt);

            $duplicate_result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($duplicate_result) > 0) {
                $errors[] = "Username atau email sudah digunakan oleh pengguna lain.";
            }

            mysqli_stmt_close($stmt);
        }
    }

    /* =========================
       UPDATE DATA
    ========================= */

    if (empty($errors)) {

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE users
             SET nama_lengkap = ?,
                 username = ?,
                 email = ?,
                 no_hp = ?,
                 alamat = ?
             WHERE id = ? AND role = 'petugas'"
        );

        if (!$stmt) {
            $errors[] = "Gagal menyiapkan proses update profil.";
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

                $_SESSION['nama'] = $nama_lengkap;

                catatAktivitas(
                    $conn,
                    'Edit Profil',
                    'Petugas memperbarui data profilnya.'
                );

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                $success = "Profil berhasil diperbarui.";

                /* Update data yang tampil di form */
                $user['nama_lengkap'] = $nama_lengkap;
                $user['username'] = $username;
                $user['email'] = $email;
                $user['no_hp'] = $no_hp;
                $user['alamat'] = $alamat;

            } else {
                $errors[] = "Profil gagal diperbarui. Silakan coba lagi.";
            }

            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Profil - Petugas</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f5f7f7;
            color: #172033;
            font-family: Arial, sans-serif;
        }

        .profil-page {
            min-height: 100vh;
            padding: 40px 20px;
        }

        .profil-container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
        }

        .profil-header {
            margin-bottom: 24px;
        }

        .profil-header a {
            display: inline-block;
            margin-bottom: 18px;
            color: #0f766e;
            text-decoration: none;
            font-weight: 600;
        }

        .profil-header a:hover {
            text-decoration: underline;
        }

        .profil-header h1 {
            margin: 0 0 8px;
            font-size: 30px;
            font-weight: 700;
        }

        .profil-header p {
            margin: 0;
            color: #6b7280;
            font-size: 15px;
        }

        .profil-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(15, 118, 110, 0.05);
        }

        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-error ul {
            margin: 0;
            padding-left: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
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
            color: #374151;
            font-size: 14px;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #ffffff;
            color: #172033;
            font-size: 14px;
            outline: none;
            transition: 0.2s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.08);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-help {
            margin-top: 6px;
            color: #9ca3af;
            font-size: 12px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }

        .btn-batal {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-batal:hover {
            background: #e5e7eb;
        }

        .btn-simpan {
            background: #0f766e;
            color: #ffffff;
        }

        .btn-simpan:hover {
            background: #0b5f59;
        }

        .status-info {
            margin-top: 24px;
            padding: 15px 16px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            font-size: 13px;
            color: #6b7280;
        }

        .status-info strong {
            color: #374151;
        }

        @media (max-width: 700px) {
            .profil-page {
                padding: 24px 15px;
            }

            .profil-card {
                padding: 22px 18px;
                border-radius: 14px;
            }

            .profil-header h1 {
                font-size: 25px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .form-group.full {
                grid-column: auto;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="profil-page">

    <div class="profil-container">

        <div class="profil-header">

            <a href="profil.php">
                ← Kembali ke Profil
            </a>

            <h1>Edit Profil</h1>

            <p>
                Perbarui informasi pribadi akun petugas Anda.
            </p>

        </div>

        <div class="profil-card">

            <?php if ($success !== ''): ?>

                <div class="alert alert-success">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                </div>

            <?php endif; ?>


            <?php if (!empty($errors)): ?>

                <div class="alert alert-error">

                    <ul>
                        <?php foreach ($errors as $error): ?>

                            <li>
                                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                            </li>

                        <?php endforeach; ?>
                    </ul>

                </div>

            <?php endif; ?>


            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
                >

                <div class="form-grid">

                    <div class="form-group full">

                        <label for="nama_lengkap">
                            Nama Lengkap
                        </label>

                        <input
                            type="text"
                            id="nama_lengkap"
                            name="nama_lengkap"
                            maxlength="100"
                            required
                            value="<?= htmlspecialchars($user['nama_lengkap'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
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
                            required
                            value="<?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        >

                        <div class="form-help">
                            3–50 karakter. Gunakan huruf, angka, titik, underscore, atau tanda minus.
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
                            maxlength="150"
                            required
                            value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
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
                            value="<?= htmlspecialchars($user['no_hp'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="alamat">
                            Alamat
                        </label>

                        <textarea
                            id="alamat"
                            name="alamat"
                            maxlength="500"
                        ><?= htmlspecialchars($user['alamat'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>

                    </div>

                </div>


                <div class="status-info">

                    Status akun:
                    <strong>
                        <?= htmlspecialchars(
                            ucfirst($user['status'] ?? '-'),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </strong>

                    <br>

                    Perubahan password dilakukan melalui menu
                    <strong>Ubah Password</strong>.

                </div>


                <div class="form-actions">

                    <a
                        href="profil.php"
                        class="btn btn-batal"
                    >
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="btn btn-simpan"
                    >
                        Simpan Perubahan
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>