
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$error = "";
$nama_lengkap = "";
$username = "";
$email = "";
$no_hp = "";
$alamat = "";

// Token keamanan formulir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_POST['simpan'])) {

    // Validasi token
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
        $password = $_POST['password'] ?? '';

        // Validasi input
        if (
            $nama_lengkap == "" ||
            $username == "" ||
            $password == ""
        ) {
            $error = "Nama lengkap, username, dan password wajib diisi.";

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

        } elseif (strlen($password) < 8) {
            $error = "Password minimal 8 karakter.";

        } else {

            try {

                // Periksa username
                $cek = mysqli_prepare(
                    $conn,
                    "SELECT id FROM users WHERE username = ? LIMIT 1"
                );

                mysqli_stmt_bind_param($cek, "s", $username);
                mysqli_stmt_execute($cek);
                mysqli_stmt_store_result($cek);

                if (mysqli_stmt_num_rows($cek) > 0) {

                    $error = "Username sudah digunakan. Silakan gunakan username lain.";

                } else {

                    // Enkripsi password
                    $password_hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    // Data opsional
                    $email_db = $email !== "" ? $email : null;
                    $no_hp_db = $no_hp !== "" ? $no_hp : null;
                    $alamat_db = $alamat !== "" ? $alamat : null;

                    $role = "petugas";
                    $status = "aktif";

                    // Simpan petugas
                    $query = mysqli_prepare(
                        $conn,
                        "INSERT INTO users
                        (nama_lengkap, username, email, password, no_hp, alamat, role, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    mysqli_stmt_bind_param(
                        $query,
                        "ssssssss",
                        $nama_lengkap,
                        $username,
                        $email_db,
                        $password_hash,
                        $no_hp_db,
                        $alamat_db,
                        $role,
                        $status
                    );

                    mysqli_stmt_execute($query);

                    // Perbarui token setelah berhasil
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header("Location: petugas.php?status=berhasil");
                    exit;
                }

                mysqli_stmt_close($cek);

            } catch (mysqli_sql_exception $e) {

                if ($e->getCode() == 1062) {
                    $error = "Username sudah digunakan.";
                } else {
                    $error = "Terjadi kesalahan saat menyimpan data petugas.";
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

    <title>Tambah Petugas - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<div class="admin-content">

    <div class="admin-heading">
        <h1>Tambah Petugas</h1>
        <p>Tambahkan akun petugas baru ke sistem Bank Sampah Digital.</p>
    </div>

    <div class="petugas-form-container">

        <div class="petugas-form-heading">
            <h2>Formulir Data Petugas</h2>
            <p>Lengkapi informasi petugas berikut.</p>
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
                        placeholder="Masukkan nama lengkap"
                        value="<?php echo htmlspecialchars($nama_lengkap); ?>"
                        maxlength="100"
                        required
                    >
                </div>

                <div class="petugas-form-group">
                    <label>Username <span>*</span></label>
                    <input
                        type="text"
                        name="username"
                        placeholder="Contoh: petugas01"
                        value="<?php echo htmlspecialchars($username); ?>"
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
                        placeholder="Contoh: petugas@email.com"
                        value="<?php echo htmlspecialchars($email); ?>"
                        maxlength="100"
                    >
                </div>

                <div class="petugas-form-group">
                    <label>Nomor HP</label>
                    <input
                        type="text"
                        name="no_hp"
                        placeholder="Masukkan nomor HP"
                        value="<?php echo htmlspecialchars($no_hp); ?>"
                        maxlength="20"
                    >
                </div>

                <div class="petugas-form-group full-width">
                    <label>Alamat</label>
                    <textarea
                        name="alamat"
                        rows="3"
                        placeholder="Masukkan alamat lengkap"
                    ><?php echo htmlspecialchars($alamat); ?></textarea>
                </div>

                <div class="petugas-form-group full-width">
                    <label>Password <span>*</span></label>
                    <input
                        type="password"
                        name="password"
                        placeholder="Minimal 8 karakter"
                        minlength="8"
                        required
                    >
                    <small>Password akan dienkripsi sebelum disimpan.</small>
                </div>

            </div>

            <div class="petugas-form-actions">

                <a href="petugas.php" class="btn-batal-petugas">
                    Batal
                </a>

                <button type="submit" name="simpan" class="btn-simpan-petugas">
                    + Simpan Petugas
                </button>

            </div>

        </form>

    </div>

</div>

</body>
</html>
