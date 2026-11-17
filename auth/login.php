<?php
session_start();

include '../config/koneksi.php';
require_once '../config/log_aktivitas.php';
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = "Username dan password wajib diisi.";

    } else {

        /*
         * Ambil data user menggunakan prepared statement
         */
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, nama_lengkap, username, password, role, status
             FROM users
             WHERE username = ?
             AND status = 'aktif'
             LIMIT 1"
        );

        if (!$stmt) {
            $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
        } else {

            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            /*
             * Verifikasi password
             */
            if ($user && password_verify($password, $user['password'])) {

                /*
                 * Regenerasi session ID setelah login berhasil
                 * untuk mencegah session fixation.
                 */
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['nama'] = $user['nama_lengkap'];
                $_SESSION['role'] = $user['role'];

                // ===================================
                // CATAT AKTIVITAS LOGIN
                // ===================================

                catatAktivitas(
                    $conn,
                    'Login Berhasil',
                    'Login berhasil ke sistem sebagai ' . ucfirst($user['role']) . '.'
                );

                /*
                 * Redirect berdasarkan role
                 */
                if ($user['role'] === 'admin') {

                    header("Location: ../admin/dashboard.php");

                } elseif ($user['role'] === 'petugas') {

                    header("Location: ../petugas/dashboard.php");

                } elseif ($user['role'] === 'warga') {

                    header("Location: ../warga/dashboard.php");

                } else {

                    /*
                     * Jika role tidak dikenal,
                     * hapus session dan kembali ke login.
                     */
                    session_unset();
                    session_destroy();

                    $error = "Role akun tidak valid.";
                }

                if (empty($error)) {
                    exit;
                }

            } else {

                /*
                 * Pesan dibuat umum agar tidak memberi tahu
                 * apakah username terdaftar atau tidak.
                 */
                $error = "Username atau password salah!";
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

    <title>Login - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <div class="login-container">

    <div class="login-box">

    <div class="login-logo">
    <img src="../assets/images/logo-tps3r.png" alt="Logo TPS 3R">
</div>

        <h2>Selamat Datang!</h2>

        <p class="login-description">
            Silakan login untuk masuk ke Bank Sampah Digital.
        </p>

        <?php if (!empty($error)) : ?>
    <div class="login-error">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

        <form action="" method="POST">

            <div class="form-group">
                <label>Username</label>
                <input
                    type="text"
                    name="username"
                    placeholder="Masukkan username"
                    required
                >
            </div>

            <div class="form-group">
    <label>Password</label>

    <div class="password-wrapper">

        <input
            type="password"
            name="password"
            id="password"
            placeholder="Masukkan password"
            required
        >

        <button
    type="button"
    class="toggle-password"
    onclick="togglePassword()"
    aria-label="Tampilkan password"
>
    <svg id="eyeIcon" width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path
            d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <circle
            cx="12"
            cy="12"
            r="3"
            stroke="currentColor"
            stroke-width="1.8"
        />
    </svg>
</button>

    </div>
</div>

            <button type="submit" name="login" class="btn-login-submit">
                Login
            </button>
           <a href="../index.php" class="btn-login-submit btn-back-home">
                Kembali ke Beranda</a>

        </form>

        <p class="register-link">
            Belum punya akun?
            <a href="register.php">Daftar sekarang</a>
        </p>

    </div>

</div>

<script>
function togglePassword() {

    const password = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');

    if (password.type === 'password') {

        password.type = 'text';

        icon.innerHTML = `
            <path
                d="M3 3l18 18"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
            />

            <path
                d="M10.6 10.6a2 2 0 0 0 2.8 2.8"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
            />

            <path
                d="M9.9 4.2A10.8 10.8 0 0 1 12 4c7 0 10 8 10 8a17.4 17.4 0 0 1-3.1 4.3"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            />

            <path
                d="M6.2 6.2C3.5 8.1 2 12 2 12s3 8 10 8c2 0 3.7-.6 5.2-1.5"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        `;

    } else {

        password.type = 'password';

        icon.innerHTML = `
            <path
                d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            />

            <circle
                cx="12"
                cy="12"
                r="3"
                stroke="currentColor"
                stroke-width="1.8"
            />
        `;
    }
}
</script>

</body>
</html>