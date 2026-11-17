<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../config/koneksi.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$error = "";
$success = "";

if (isset($_POST['register'])) {

    /*
     * Validasi CSRF
     */
    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } else {

        /*
         * Ambil data form
         */
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $no_hp = trim($_POST['no_hp'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');

        $password = $_POST['password'] ?? '';
        $konfirmasi = $_POST['konfirmasi_password'] ?? '';

        /*
         * Validasi input
         */
        if ($nama === "" || $username === "" || $password === "") {

            $error = "Nama lengkap, username, dan password wajib diisi!";

        } elseif ($password !== $konfirmasi) {

            $error = "Konfirmasi password tidak sama!";

        } elseif (strlen($password) < 8) {

            $error = "Password minimal 8 karakter!";

        } elseif (strlen($nama) > 150) {

            $error = "Nama lengkap terlalu panjang!";

        } elseif (strlen($username) > 50) {

            $error = "Username terlalu panjang!";

        } elseif (strlen($email) > 150) {

            $error = "Email terlalu panjang!";

        } elseif (strlen($no_hp) > 20) {

            $error = "Nomor HP terlalu panjang!";

        } elseif (strlen($alamat) > 255) {

            $error = "Alamat terlalu panjang!";

        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = "Format email tidak valid!";

        } else {

            try {

                /*
                 * Periksa username dan email
                 */
                $cek = mysqli_prepare(
                    $conn,
                    "SELECT id
                     FROM users
                     WHERE username = ?
                     OR (email = ? AND ? != '')
                     LIMIT 1"
                );

                mysqli_stmt_bind_param(
                    $cek,
                    "sss",
                    $username,
                    $email,
                    $email
                );

                mysqli_stmt_execute($cek);
                mysqli_stmt_store_result($cek);

                if (mysqli_stmt_num_rows($cek) > 0) {

                    $error = "Username atau email sudah digunakan!";

                    mysqli_stmt_close($cek);

                } else {

                    mysqli_stmt_close($cek);

                    /*
                     * Hash password
                     */
                    $password_hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    /*
                     * Data opsional
                     */
                    $email_db = $email !== "" ? $email : null;
                    $no_hp_db = $no_hp !== "" ? $no_hp : null;
                    $alamat_db = $alamat !== "" ? $alamat : null;

                    /*
                     * Mulai transaksi
                     */
                    mysqli_begin_transaction($conn);

                    /*
                     * 1. Buat akun warga
                     */
                    $query_user = mysqli_prepare(
                        $conn,
                        "INSERT INTO users
                        (
                            nama_lengkap,
                            username,
                            email,
                            password,
                            no_hp,
                            alamat,
                            role
                        )
                        VALUES (?, ?, ?, ?, ?, ?, 'warga')"
                    );

                    mysqli_stmt_bind_param(
                        $query_user,
                        "ssssss",
                        $nama,
                        $username,
                        $email_db,
                        $password_hash,
                        $no_hp_db,
                        $alamat_db
                    );

                    mysqli_stmt_execute($query_user);

                    /*
                     * Ambil ID user
                     */
                    $user_id = mysqli_insert_id($conn);

                    mysqli_stmt_close($query_user);

                    /*
                     * 2. Buat tabungan warga
                     */
                    $saldo_awal = 0;

                    $query_tabungan = mysqli_prepare(
                        $conn,
                        "INSERT INTO tabungan_warga
                        (user_id, saldo)
                        VALUES (?, ?)"
                    );

                    mysqli_stmt_bind_param(
                        $query_tabungan,
                        "id",
                        $user_id,
                        $saldo_awal
                    );

                    mysqli_stmt_execute($query_tabungan);

                    mysqli_stmt_close($query_tabungan);

                    /*
                     * Simpan transaksi
                     */
                    mysqli_commit($conn);

                    $success = "Pendaftaran berhasil! Rekening tabungan Anda telah dibuat dengan saldo Rp0. Silakan login.";
                }

            } catch (mysqli_sql_exception $e) {

                /*
                 * Batalkan transaksi jika terjadi error
                 */
                mysqli_rollback($conn);

                if ($e->getCode() == 1062) {

                    $error = "Username atau email sudah digunakan!";

                } else {

                    $error = "Pendaftaran gagal. Silakan coba kembali.";
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

    <title>Daftar Akun - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <div class="login-container">

    <div class="login-box">

        <div class="login-logo">
            <img src="../assets/images/logo-tps3r.png" alt="Logo TPS 3R">
        </div>

        <h2>Buat Akun Baru</h2>

        <p class="login-description">
            Bergabunglah bersama kami untuk lingkungan yang lebih bersih.
        </p>

        <?php if (!empty($error)) : ?>
    <div class="login-error">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)) : ?>
    <div class="register-success">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

       <form action="" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
        >

            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama_lengkap"
                    placeholder="Masukkan nama lengkap" required>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username"
                    placeholder="Buat username" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email"
                    placeholder="Masukkan email">
            </div>

            <div class="form-group">
                <label>Nomor HP</label>
                <input type="text" name="no_hp"
                    placeholder="Masukkan nomor HP">
            </div>

            <div class="form-group">
                <label>Alamat</label>
                <input type="text" name="alamat"
                    placeholder="Masukkan alamat">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password"
                    placeholder="Buat password" required>
            </div>

            <div class="form-group">
                <label>Konfirmasi Password</label>
                <input type="password" name="konfirmasi_password"
                    placeholder="Ulangi password" required>
            </div>

            <button type="submit" name="register"
                class="btn-login-submit">
                Daftar Sekarang
            </button>

        </form>

        <p class="register-link">
            Sudah punya akun?
            <a href="login.php">Login di sini</a>
        </p>

    </div>

</div>



</body>

</html>