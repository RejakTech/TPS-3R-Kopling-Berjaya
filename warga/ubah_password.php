<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
require_once '../config/log_aktivitas.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int) $_SESSION['user_id'];

$pesan = '';
$tipe_pesan = '';

/* ===============================
   PROSES UBAH PASSWORD
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
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
                $user = mysqli_fetch_assoc($result);

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

                            // Regenerasi CSRF
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                            // Catat aktivitas perubahan password
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

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f5f8f7;
            color: #173f38;
        }

        .password-page {
            max-width: 760px;
            margin: 0 auto;
            padding: 38px 24px 60px;
        }

        /* HEADER */
        .password-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
        }

        .password-heading h1 {
            margin: 0 0 6px;
            color: #123b35;
            font-size: 27px;
        }

        .password-heading p {
            margin: 0;
            color: #71817e;
            font-size: 14px;
        }

        .btn-kembali {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 10px 15px;

            background: #ffffff;
            color: #145c52;

            border: 1px solid #dce7e4;
            border-radius: 10px;

            text-decoration: none;

            font-size: 13px;
            font-weight: 600;

            transition: 0.2s;
        }

        .btn-kembali:hover {
            background: #edf7f4;
        }

        /* ALERT */
        .password-alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 22px;

            font-size: 14px;
            font-weight: 500;
        }

        .password-alert.success {
            background: #e9f8f1;
            border: 1px solid #b8e6d0;
            color: #176b4d;
        }

        .password-alert.error {
            background: #fff0f0;
            border: 1px solid #f1c3c3;
            color: #a33a3a;
        }

        /* CARD */
        .password-card {
            background: #ffffff;
            border: 1px solid #e1eae8;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 18px rgba(22, 57, 50, 0.04);
        }

        .password-card-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 28px;
        }

        .password-icon {
            width: 46px;
            height: 46px;
            min-width: 46px;

            border-radius: 12px;

            background: #e3f3ef;
            color: #0f766e;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 20px;
        }

        .password-card-header h2 {
            margin: 0 0 5px;
            font-size: 19px;
            color: #173f38;
        }

        .password-card-header p {
            margin: 0;
            color: #7b8987;
            font-size: 13px;
            line-height: 1.5;
        }

        /* FORM */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;

            color: #40524f;
            font-size: 13px;
            font-weight: 600;
        }

        .password-input-wrapper {
            position: relative;
        }

        .password-input-wrapper input {
            width: 100%;
            padding: 12px 48px 12px 13px;

            border: 1px solid #d9e4e1;
            border-radius: 10px;

            background: #ffffff;
            color: #243b37;

            font-family: inherit;
            font-size: 14px;

            outline: none;
            transition: 0.2s;
        }

        .password-input-wrapper input:focus {
            border-color: #159b83;
            box-shadow: 0 0 0 3px rgba(21, 155, 131, 0.09);
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 12px;

            transform: translateY(-50%);

            border: none;
            background: transparent;

            color: #7b8987;

            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }

        .toggle-password:hover {
            color: #0f766e;
        }

        .password-help {
            margin-top: 6px;
            color: #8a9795;
            font-size: 11px;
        }

        /* ACTION */
        .password-actions {
            display: flex;
            justify-content: flex-end;

            margin-top: 28px;
            padding-top: 22px;

            border-top: 1px solid #edf1f0;
        }

        .btn-simpan-password {
            border: none;

            padding: 12px 22px;

            background: #0f766e;
            color: #ffffff;

            border-radius: 10px;

            font-size: 14px;
            font-weight: 700;

            cursor: pointer;
            transition: 0.2s;
        }

        .btn-simpan-password:hover {
            background: #0c625c;
            transform: translateY(-1px);
        }

        /* SECURITY INFO */
        .security-info {
            margin-top: 18px;
            padding: 15px 17px;

            background: #f3f8f6;
            border: 1px solid #e0ece8;
            border-radius: 11px;

            color: #667572;
            font-size: 12px;
            line-height: 1.6;
        }

        .security-info strong {
            color: #31534c;
        }

        /* MOBILE */
        @media (max-width: 600px) {

            .password-page {
                padding: 25px 16px 45px;
            }

            .password-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .btn-kembali {
                width: 100%;
            }

            .password-card {
                padding: 23px 18px;
            }

            .password-actions {
                justify-content: stretch;
            }

            .btn-simpan-password {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="password-page">

    <!-- HEADER -->
    <div class="password-header">

        <div class="password-heading">
            <h1>Ubah Password</h1>
            <p>Perbarui password akun kamu untuk menjaga keamanan akun.</p>
        </div>

        <a href="profil.php" class="btn-kembali">
            ← Kembali ke Profil
        </a>

    </div>

    <!-- ALERT -->
    <?php if ($pesan !== ''): ?>

        <div class="password-alert <?= $tipe_pesan === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <!-- CARD -->
    <div class="password-card">

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

            <!-- KONFIRMASI -->
            <div class="form-group">

                <label for="konfirmasi_password">
                    Konfirmasi Password Baru
                </label>

                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="konfirmasi_password"
                        name="konfirmasi_password"
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

            <div class="security-info">
                <strong>Tips keamanan:</strong>
                gunakan password yang sulit ditebak dan jangan menggunakan
                password yang sama dengan akun lain.
            </div>

            <div class="password-actions">

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

<script>
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
</script>

</body>
</html>