<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
 * CAPTCHA penjumlahan sederhana.
 * Jawaban disimpan di session, bukan dikirim ke browser.
 */
function buatCaptchaRegister() {
    $angka1 = random_int(1, 9);
    $angka2 = random_int(1, 9);

    $_SESSION['captcha_register_jawaban'] = (string) ($angka1 + $angka2);
    $_SESSION['captcha_register_pertanyaan'] = $angka1 . ' + ' . $angka2 . ' = ?';
}

if (empty($_SESSION['captcha_register_jawaban'])) {
    buatCaptchaRegister();
}

include '../config/koneksi.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$error = "";
$success = "";
$field_errors = [];

if (isset($_POST['register'])) {

    /*
     * Validasi CSRF
     */
    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } elseif (
        !isset($_SESSION['captcha_register_jawaban']) ||
        !hash_equals((string) $_SESSION['captcha_register_jawaban'], trim($_POST['captcha'] ?? ''))
    ) {
        $error = "Jawaban CAPTCHA salah. Silakan hitung kembali.";
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
         * Validasi input.
         * Aturan:
         * - Nama: huruf dan spasi saja.
         * - Username: huruf dan angka saja.
         * - Nomor WhatsApp: wajib, 10-15 angka, tanpa simbol/spasi.
         * - Alamat: wajib, format teks alamat yang wajar.
         * - Password: minimal 8 karakter dan karakter pertama kapital.
         *   Angka, simbol, dan spasi tidak diwajibkan.
         */
        if ($nama === "") {
            $field_errors['nama_lengkap'] = "Nama lengkap wajib diisi.";
        } elseif (!preg_match("/^[\p{L} ]+$/u", $nama)) {
            $field_errors['nama_lengkap'] = "Nama hanya boleh berisi huruf dan spasi, tanpa angka atau simbol.";
        } elseif (mb_strlen($nama, 'UTF-8') > 150) {
            $field_errors['nama_lengkap'] = "Nama lengkap maksimal 150 karakter.";
        }

        if ($username === "") {
            $field_errors['username'] = "Username wajib diisi.";
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $field_errors['username'] = "Username harus terdiri dari 3–50 karakter.";
        } elseif (!preg_match('/^[A-Za-z0-9]+$/', $username)) {
            $field_errors['username'] = "Username hanya boleh berisi huruf dan angka, tanpa simbol atau spasi.";
        }

        if (strlen($email) > 150) {
            $field_errors['email'] = "Email maksimal 150 karakter.";
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $field_errors['email'] = "Format email tidak valid.";
        }

        if ($no_hp === "") {
            $field_errors['no_hp'] = "Nomor WhatsApp wajib diisi.";
        } elseif (!preg_match('/^(0[0-9]{9,14}|62[0-9]{8,13})$/', $no_hp)) {
            $field_errors['no_hp'] = "Nomor WhatsApp harus 10–15 angka tanpa simbol atau spasi, diawali 0 atau 62.";
        }

        if ($alamat === "") {
            $field_errors['alamat'] = "Alamat lengkap wajib diisi.";
        } elseif (mb_strlen($alamat, 'UTF-8') < 10 || mb_strlen($alamat, 'UTF-8') > 255) {
            $field_errors['alamat'] = "Alamat harus terdiri dari 10–255 karakter.";
        } elseif (!preg_match("/^[\p{L}0-9 ,\.\/-]+$/u", $alamat) || !preg_match('/[\p{L}]{2}/u', $alamat)) {
            $field_errors['alamat'] = "Gunakan format alamat lengkap. Contoh: Jalan Mawar No 10, Kelurahan Sukamaju, Kecamatan Senapelan, Pekanbaru, Riau.";
        }

        if ($password === "") {
            $field_errors['password'] = "Password wajib diisi.";
        } elseif (mb_strlen($password, 'UTF-8') < 8) {
            $field_errors['password'] = "Password wajib minimal 8 karakter.";
        } elseif (!preg_match('/^[A-Z]/', $password)) {
            $field_errors['password'] = "Karakter pertama password wajib huruf kapital (A–Z).";
        }

        if ($konfirmasi === "") {
            $field_errors['konfirmasi_password'] = "Konfirmasi password wajib diisi.";
        } elseif ($password !== $konfirmasi) {
            $field_errors['konfirmasi_password'] = "Konfirmasi password tidak sama dengan password.";
        }

        if (empty($field_errors)) {

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
                     * Semua kolom disimpan sesuai input.
                     * Email tetap opsional seperti kode awal.
                     */
                    $email_db = $email !== "" ? $email : null;
                    $no_hp_db = $no_hp;
                    $alamat_db = $alamat;

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
                try {
                    mysqli_rollback($conn);
                } catch (Throwable $rollbackError) {
                    // Tidak ada transaksi aktif yang perlu dibatalkan.
                }

                if ($e->getCode() == 1062) {

                    $error = "Username atau email sudah digunakan!";

                } else {

                    $error = "Pendaftaran gagal. Silakan coba kembali.";
                }
            }
        }
    }

    // Ganti CAPTCHA setelah setiap pengiriman formulir.
    buatCaptchaRegister();
}

/*
 * Helper untuk mempertahankan input setelah validasi gagal.
 */
function nilaiLama($key) {
    return htmlspecialchars($_POST[$key] ?? '', ENT_QUOTES, 'UTF-8');
}

/*
 * Helper untuk menampilkan keterangan kesalahan per kolom.
 */
function tampilkanErrorKolom($field_errors, $field) {
    if (!empty($field_errors[$field])) {
        echo '<small style="display:block; margin-top:5px; color:#C62828; font-size:11px; line-height:1.5;">'
            . htmlspecialchars($field_errors[$field], ENT_QUOTES, 'UTF-8')
            . '</small>';
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
            <div class="login-error" role="alert">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)) : ?>
            <div class="register-success" role="status">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

       <form action="" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
        >

            <div class="form-group">
                <label>Nama Lengkap</label>
                <input
                    type="text"
                    name="nama_lengkap"
                    placeholder="Masukkan nama lengkap"
                    value="<?php echo nilaiLama('nama_lengkap'); ?>"
                    maxlength="150"
                    pattern="[\p{L} ]+"
                    title="Nama hanya boleh berisi huruf dan spasi, tanpa angka atau simbol."
                    required
                >
                <?php tampilkanErrorKolom($field_errors, 'nama_lengkap'); ?>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input
                    type="text"
                    name="username"
                    placeholder="Buat username"
                    value="<?php echo nilaiLama('username'); ?>"
                    minlength="3"
                    maxlength="50"
                    pattern="[A-Za-z0-9]+"
                    title="Username hanya boleh berisi huruf dan angka, tanpa simbol atau spasi."
                    required
                >
                <?php tampilkanErrorKolom($field_errors, 'username'); ?>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input
                    type="email"
                    name="email"
                    placeholder="Masukkan email"
                    value="<?php echo nilaiLama('email'); ?>"
                    maxlength="150"
                >
                <?php tampilkanErrorKolom($field_errors, 'email'); ?>
            </div>

            <div class="form-group">
                <label>Nomor HP / WhatsApp</label>
                <input
                    type="tel"
                    name="no_hp"
                    placeholder="Contoh: 081234567890"
                    value="<?php echo nilaiLama('no_hp'); ?>"
                    minlength="10"
                    maxlength="15"
                    pattern="(0[0-9]{9,14}|62[0-9]{8,13})"
                    inputmode="numeric"
                    title="Nomor WhatsApp wajib 10–15 angka, tanpa simbol atau spasi, diawali 0 atau 62."
                    required
                >
                <?php tampilkanErrorKolom($field_errors, 'no_hp'); ?>
            </div>

            <div class="form-group">
                <label>Alamat</label>
                <input
                    type="text"
                    name="alamat"
                    placeholder="Jalan, kelurahan, kecamatan, kota, provinsi"
                    value="<?php echo nilaiLama('alamat'); ?>"
                    minlength="10"
                    maxlength="255"
                    title="Masukkan alamat lengkap, misalnya jalan, kelurahan, kecamatan, kota, dan provinsi."
                    required
                >
                <?php tampilkanErrorKolom($field_errors, 'alamat'); ?>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input
                    type="password"
                    name="password"
                    placeholder="Buat password"
                    minlength="8"
                    pattern="[A-Z].{7,}"
                    title="Minimal 8 karakter dan karakter pertama harus huruf kapital. Angka, simbol, dan spasi tidak wajib."
                    autocomplete="new-password"
                    required
                >
                <small style="display:block; margin-top:5px; color:#8293A0; font-size:11px; line-height:1.5;">
                    Minimal 8 karakter dan karakter pertama wajib huruf kapital. Angka, simbol, dan spasi tidak wajib.
                </small>
                <?php tampilkanErrorKolom($field_errors, 'password'); ?>
            </div>

            <div class="form-group">
                <label>Konfirmasi Password</label>
                <input
                    type="password"
                    name="konfirmasi_password"
                    placeholder="Ulangi password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
                <?php tampilkanErrorKolom($field_errors, 'konfirmasi_password'); ?>
            </div>

            <!-- CAPTCHA ditambahkan tanpa mengubah struktur desain halaman -->
            <div class="form-group">
                <label>Verifikasi Keamanan</label>
                <p style="margin:6px 0; font-weight:bold;">
                    <?php echo htmlspecialchars($_SESSION['captcha_register_pertanyaan'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <input
                    type="number"
                    name="captcha"
                    placeholder="Masukkan hasil penjumlahan"
                    inputmode="numeric"
                    required
                >
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
