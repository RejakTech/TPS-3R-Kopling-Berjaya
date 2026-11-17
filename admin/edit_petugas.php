
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$error = "";

// Ambil ID petugas dari URL
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: petugas.php");
    exit;
}

// Ambil data petugas
$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM users WHERE id = ? AND role = 'petugas' LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$petugas = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$petugas) {
    header("Location: petugas.php");
    exit;
}

// Token keamanan
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Proses update
if (isset($_POST['update'])) {

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
        $status = $_POST['status'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($nama_lengkap == "" || $username == "") {
            $error = "Nama lengkap dan username wajib diisi.";

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

        } elseif (!in_array($status, ['aktif', 'nonaktif'], true)) {
            $error = "Status akun tidak valid.";

        } elseif ($password != "" && strlen($password) < 8) {
            $error = "Password baru minimal 8 karakter.";

        } else {

            try {

                // Periksa username milik akun lain
                $cek = mysqli_prepare(
                    $conn,
                    "SELECT id FROM users
                     WHERE username = ? AND id != ?
                     LIMIT 1"
                );

                mysqli_stmt_bind_param($cek, "si", $username, $id);
                mysqli_stmt_execute($cek);
                mysqli_stmt_store_result($cek);

                if (mysqli_stmt_num_rows($cek) > 0) {

                    $error = "Username sudah digunakan oleh akun lain.";

                } else {

                    $email_db = $email !== "" ? $email : null;
                    $no_hp_db = $no_hp !== "" ? $no_hp : null;
                    $alamat_db = $alamat !== "" ? $alamat : null;

                    if ($password != "") {

                        // Update termasuk password
                        $password_hash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $query = mysqli_prepare(
                            $conn,
                            "UPDATE users SET
                                nama_lengkap = ?,
                                username = ?,
                                email = ?,
                                no_hp = ?,
                                alamat = ?,
                                status = ?,
                                password = ?
                             WHERE id = ? AND role = 'petugas'"
                        );

                        mysqli_stmt_bind_param(
                            $query,
                            "sssssssi",
                            $nama_lengkap,
                            $username,
                            $email_db,
                            $no_hp_db,
                            $alamat_db,
                            $status,
                            $password_hash,
                            $id
                        );

                    } else {

                        // Update tanpa mengubah password
                        $query = mysqli_prepare(
                            $conn,
                            "UPDATE users SET
                                nama_lengkap = ?,
                                username = ?,
                                email = ?,
                                no_hp = ?,
                                alamat = ?,
                                status = ?
                             WHERE id = ? AND role = 'petugas'"
                        );

                        mysqli_stmt_bind_param(
                            $query,
                            "ssssssi",
                            $nama_lengkap,
                            $username,
                            $email_db,
                            $no_hp_db,
                            $alamat_db,
                            $status,
                            $id
                        );
                    }

                    mysqli_stmt_execute($query);

                    mysqli_stmt_close($query);
                    mysqli_stmt_close($cek);

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header("Location: petugas.php?status=edit_berhasil");
                    exit;
                }

                mysqli_stmt_close($cek);

            } catch (mysqli_sql_exception $e) {

                if ($e->getCode() == 1062) {
                    $error = "Username sudah digunakan.";
                } else {
                    $error = "Terjadi kesalahan saat memperbarui data petugas.";
                }
            }
        }

        // Pertahankan input ketika validasi gagal
        $petugas['nama_lengkap'] = $nama_lengkap;
        $petugas['username'] = $username;
        $petugas['email'] = $email;
        $petugas['no_hp'] = $no_hp;
        $petugas['alamat'] = $alamat;
        $petugas['status'] = $status;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Petugas - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<div class="admin-content">

    <div class="admin-heading">
        <h1>Edit Petugas</h1>
        <p>Perbarui informasi akun petugas Bank Sampah Digital.</p>
    </div>

    <div class="petugas-form-container">

        <div class="petugas-form-heading">
            <h2>Formulir Edit Petugas</h2>
            <p>Ubah informasi petugas sesuai kebutuhan.</p>
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
                        value="<?php echo htmlspecialchars($petugas['nama_lengkap']); ?>"
                        maxlength="100"
                        required
                    >
                </div>

                <div class="petugas-form-group">
                    <label>Username <span>*</span></label>
                    <input
                        type="text"
                        name="username"
                        value="<?php echo htmlspecialchars($petugas['username']); ?>"
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
                        value="<?php echo htmlspecialchars($petugas['email'] ?? ''); ?>"
                        maxlength="100"
                    >
                </div>

                <div class="petugas-form-group">
                    <label>Nomor HP</label>
                    <input
                        type="text"
                        name="no_hp"
                        value="<?php echo htmlspecialchars($petugas['no_hp'] ?? ''); ?>"
                        maxlength="20"
                    >
                </div>

                <div class="petugas-form-group full-width">
                    <label>Alamat</label>
                    <textarea
                        name="alamat"
                        rows="3"
                    ><?php echo htmlspecialchars($petugas['alamat'] ?? ''); ?></textarea>
                </div>

                <div class="petugas-form-group">
                    <label>Status Akun</label>
                    <select name="status" required>
                        <option value="aktif"
                            <?php echo $petugas['status'] == 'aktif' ? 'selected' : ''; ?>>
                            Aktif
                        </option>

                        <option value="nonaktif"
                            <?php echo $petugas['status'] == 'nonaktif' ? 'selected' : ''; ?>>
                            Nonaktif
                        </option>
                    </select>
                </div>

                <div class="petugas-form-group">
                    <label>Password Baru</label>
                    <input
                        type="password"
                        name="password"
                        placeholder="Kosongkan jika tidak ingin mengganti"
                        minlength="8"
                    >
                    <small>Password minimal 8 karakter.</small>
                </div>

            </div>

            <div class="petugas-form-actions">

                <a href="petugas.php" class="btn-batal-petugas">
                    Batal
                </a>

                <button
                    type="submit"
                    name="update"
                    class="btn-simpan-petugas"
                >
                    Simpan Perubahan
                </button>

            </div>

        </form>

    </div>

</div>

</body>
</html>
