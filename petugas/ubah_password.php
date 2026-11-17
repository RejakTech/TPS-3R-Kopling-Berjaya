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

        if (
            $password_lama === '' ||
            $password_baru === '' ||
            $konfirmasi_password === ''
        ) {
            $error = "Semua field password wajib diisi.";

        } elseif (strlen($password_baru) < 8) {
            $error = "Password baru minimal 8 karakter.";

        } elseif ($password_baru !== $konfirmasi_password) {
            $error = "Konfirmasi password tidak sama.";

        } elseif ($password_baru === $password_lama) {
            $error = "Password baru harus berbeda dari password lama.";

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "SELECT password
                 FROM users
                 WHERE id = ? AND role = 'petugas'
                 LIMIT 1"
            );

            if (!$stmt) {
                $error = "Terjadi kesalahan saat mengambil data akun.";
            } else {

                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);
                $petugas = mysqli_fetch_assoc($result);

                mysqli_stmt_close($stmt);

                if (!$petugas) {

                    $error = "Data akun petugas tidak ditemukan.";

                } elseif (
                    !password_verify(
                        $password_lama,
                        $petugas['password']
                    )
                ) {

                    $error = "Password lama yang Anda masukkan salah.";

                } else {

                    $password_hash = password_hash(
                        $password_baru,
                        PASSWORD_DEFAULT
                    );

                    $stmt_update = mysqli_prepare(
                        $conn,
                        "UPDATE users
                         SET password = ?
                         WHERE id = ? AND role = 'petugas'"
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
                                'Petugas mengubah password akun sendiri.'
                            );

                            $pesan = "Password berhasil diperbarui.";

                            $_SESSION['csrf_token'] = bin2hex(
                                random_bytes(32)
                            );

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

    <title>Ubah Password - Petugas</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        .password-page {
            max-width: 720px;
            margin: 0 auto;
        }

        .password-card {
            background: #ffffff;
            border: 1px solid #e3eeec;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 6px 24px rgba(15, 118, 110, 0.05);
        }

        .password-header {
            margin-bottom: 25px;
        }

        .password-header h2 {
            margin: 0 0 7px;
            color: #145c52;
            font-size: 25px;
        }

        .password-header p {
            margin: 0;
            color: #78908e;
            font-size: 14px;
            line-height: 1.6;
        }

        .password-info {
            background: #f0faf8;
            border: 1px solid #d7eeea;
            border-radius: 10px;
            padding: 13px 15px;
            margin-bottom: 24px;
            color: #28635c;
            font-size: 13px;
            line-height: 1.6;
        }

        .password-form-group {
            margin-bottom: 20px;
        }

        .password-form-group label {
            display: block;
            margin-bottom: 7px;
            color: #374151;
            font-size: 13px;
            font-weight: 600;
        }

        .password-form-group input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 1px solid #d7e2e0;
            border-radius: 9px;
            background: #ffffff;
            color: #263b38;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: 0.2s ease;
        }

        .password-form-group input:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.08);
        }

        .form-alert-success {
            background: #ecfdf5;
            color: #166534;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 13px 15px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .form-alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 13px 15px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .password-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #edf2f1;
        }

        .btn-password-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            border-radius: 9px;
            background: #f1f4f4;
            color: #40504e;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-password-save {
            border: none;
            padding: 11px 20px;
            border-radius: 9px;
            background: #0f766e;
            color: #ffffff;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-password-save:hover {
            background: #0d655e;
        }

        @media (max-width: 600px) {

            .password-card {
                padding: 20px;
            }

            .password-actions {
                flex-direction: column-reverse;
            }

            .btn-password-back,
            .btn-password-save {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="admin-content">

    <div class="password-page">

        <div class="password-card">

            <div class="password-header">

                <h2>
                    Ubah Password
                </h2>

                <p>
                    Perbarui password akun petugas untuk menjaga keamanan akun.
                </p>

            </div>


            <?php if ($pesan !== ''): ?>

                <div class="form-alert-success">
                    <?= htmlspecialchars(
                        $pesan,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </div>

            <?php endif; ?>


            <?php if ($error !== ''): ?>

                <div class="form-alert-error">
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </div>

            <?php endif; ?>


            <div class="password-info">
                Password baru minimal <strong>8 karakter</strong>
                dan harus berbeda dari password lama.
            </div>


            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_token'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >


                <div class="password-form-group">

                    <label for="password_lama">
                        Password Lama
                    </label>

                    <input
                        type="password"
                        id="password_lama"
                        name="password_lama"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <div class="password-form-group">

                    <label for="password_baru">
                        Password Baru
                    </label>

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


                <div class="password-actions">

                    <a
                        href="profil.php"
                        class="btn-password-back"
                    >
                        ← Kembali
                    </a>

                    <button
                        type="submit"
                        class="btn-password-save"
                    >
                        Simpan Password
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

</body>

</html>