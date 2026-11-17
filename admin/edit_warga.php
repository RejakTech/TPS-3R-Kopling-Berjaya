<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../config/koneksi.php';


// ========================================
// CSRF TOKEN
// ========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ========================================
// AMBIL ID WARGA
// ========================================

$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($id === false || $id <= 0) {
    header("Location: warga.php");
    exit;
}


// ========================================
// PROSES UPDATE
// ========================================

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ------------------------------------
    // VALIDASI CSRF
    // ------------------------------------

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (
        empty($csrf_token) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $csrf_token)
    ) {
        $error = "Permintaan tidak valid.";
    } else {

        // --------------------------------
        // AMBIL INPUT
        // --------------------------------

        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $username     = trim($_POST['username'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $no_hp        = trim($_POST['no_hp'] ?? '');
        $alamat       = trim($_POST['alamat'] ?? '');
        $status       = trim($_POST['status'] ?? '');

        // --------------------------------
        // VALIDASI
        // --------------------------------

        if ($nama_lengkap === '') {
            $error = "Nama lengkap wajib diisi.";
        } elseif (strlen($nama_lengkap) > 100) {
            $error = "Nama lengkap maksimal 100 karakter.";
        } elseif ($username === '') {
            $error = "Username wajib diisi.";
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $error = "Username harus 3-50 karakter.";
        } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            $error = "Username hanya boleh mengandung huruf, angka, titik, garis bawah, dan tanda hubung.";
        } elseif ($email === '') {
            $error = "Email wajib diisi.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format email tidak valid.";
        } elseif (strlen($email) > 100) {
            $error = "Email maksimal 100 karakter.";
        } elseif (strlen($no_hp) > 20) {
            $error = "No. HP maksimal 20 karakter.";
        } elseif (strlen($alamat) > 500) {
            $error = "Alamat maksimal 500 karakter.";
        } elseif (!in_array($status, ['aktif', 'nonaktif'], true)) {
            $error = "Status akun tidak valid.";
        }

        // --------------------------------
        // CEK USERNAME & EMAIL DUPLIKAT
        // --------------------------------

        if ($error === '') {

            $cek = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM users
                 WHERE (username = ? OR email = ?)
                 AND id != ?
                 LIMIT 1"
            );

            if (!$cek) {
                $error = "Gagal menyiapkan pemeriksaan data.";
            } else {

                mysqli_stmt_bind_param(
                    $cek,
                    "ssi",
                    $username,
                    $email,
                    $id
                );

                if (!mysqli_stmt_execute($cek)) {
                    $error = "Gagal memeriksa data warga.";
                } else {

                    $result_cek = mysqli_stmt_get_result($cek);

                    if ($result_cek && mysqli_num_rows($result_cek) > 0) {
                        $error = "Username atau email sudah digunakan oleh akun lain.";
                    }
                }

                mysqli_stmt_close($cek);
            }
        }

        // --------------------------------
        // UPDATE DATA
        // --------------------------------

        if ($error === '') {

            $update = mysqli_prepare(
                $conn,
                "UPDATE users
                 SET nama_lengkap = ?,
                     username = ?,
                     email = ?,
                     no_hp = ?,
                     alamat = ?,
                     status = ?
                 WHERE id = ?
                 AND role = 'warga'"
            );

            if (!$update) {
                $error = "Gagal menyiapkan proses update.";
            } else {

                mysqli_stmt_bind_param(
                    $update,
                    "ssssssi",
                    $nama_lengkap,
                    $username,
                    $email,
                    $no_hp,
                    $alamat,
                    $status,
                    $id
                );

                if (!mysqli_stmt_execute($update)) {
                    $error = "Gagal memperbarui data warga.";
                } else {

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    mysqli_stmt_close($update);

                    header("Location: warga.php?success=updated");
                    exit;
                }

                mysqli_stmt_close($update);
            }
        }
    }
}


// ========================================
// AMBIL DATA WARGA
// ========================================

$query = mysqli_prepare(
    $conn,
    "SELECT
        id,
        nama_lengkap,
        username,
        email,
        no_hp,
        alamat,
        status
     FROM users
     WHERE id = ?
     AND role = 'warga'
     LIMIT 1"
);

if (!$query) {
    die("Gagal menyiapkan query data warga.");
}

mysqli_stmt_bind_param($query, "i", $id);

if (!mysqli_stmt_execute($query)) {
    mysqli_stmt_close($query);
    die("Gagal mengambil data warga.");
}

$result = mysqli_stmt_get_result($query);

if (!$result || mysqli_num_rows($result) !== 1) {
    mysqli_stmt_close($query);
    header("Location: warga.php");
    exit;
}

$warga = mysqli_fetch_assoc($result);

mysqli_stmt_close($query);


// ========================================
// NILAI FORM
// ========================================

$nama_lengkap = $_POST['nama_lengkap'] ?? $warga['nama_lengkap'];
$username     = $_POST['username'] ?? $warga['username'];
$email        = $_POST['email'] ?? $warga['email'];
$no_hp        = $_POST['no_hp'] ?? $warga['no_hp'];
$alamat       = $_POST['alamat'] ?? $warga['alamat'];
$status       = $_POST['status'] ?? $warga['status'];

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Data Warga</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .edit-warga-wrapper {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 24px;
        }

        .edit-warga-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 8px 30px rgba(15, 118, 110, 0.06);
        }

        .edit-warga-heading {
            margin-bottom: 28px;
        }

        .edit-warga-heading h1 {
            margin: 0 0 8px;
            font-size: 28px;
            color: #172033;
        }

        .edit-warga-heading p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .edit-warga-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .edit-warga-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .edit-warga-group.full {
            grid-column: 1 / -1;
        }

        .edit-warga-group label {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
        }

        .edit-warga-group input,
        .edit-warga-group textarea,
        .edit-warga-group select {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #ffffff;
            color: #172033;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .edit-warga-group input:focus,
        .edit-warga-group textarea:focus,
        .edit-warga-group select:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.10);
        }

        .edit-warga-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .edit-warga-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 8px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .btn-edit-warga-cancel,
        .btn-edit-warga-save {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-edit-warga-cancel {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-edit-warga-save {
            border: 0;
            background: #0f766e;
            color: #ffffff;
        }

        .btn-edit-warga-save:hover {
            background: #0d665f;
        }

        .edit-warga-alert {
            margin-bottom: 22px;
            padding: 13px 16px;
            border-radius: 10px;
            font-size: 14px;
        }

        .edit-warga-alert.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        @media (max-width: 700px) {
            .edit-warga-wrapper {
                margin: 20px auto;
                padding: 0 16px;
            }

            .edit-warga-card {
                padding: 22px;
            }

            .edit-warga-form {
                grid-template-columns: 1fr;
            }

            .edit-warga-group.full {
                grid-column: auto;
            }

            .edit-warga-actions {
                grid-column: auto;
                flex-direction: column-reverse;
            }

            .btn-edit-warga-cancel,
            .btn-edit-warga-save {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="edit-warga-wrapper">

    <div class="edit-warga-card">

        <div class="edit-warga-heading">
            <h1>Edit Data Warga</h1>
            <p>Perbarui informasi akun warga dengan benar.</p>
        </div>


        <?php if ($error !== ''): ?>

            <div class="edit-warga-alert error">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>


        <form
            method="POST"
            class="edit-warga-form"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
            >


            <div class="edit-warga-group">
                <label for="nama_lengkap">
                    Nama Lengkap
                </label>

                <input
                    type="text"
                    id="nama_lengkap"
                    name="nama_lengkap"
                    maxlength="100"
                    value="<?php echo htmlspecialchars($nama_lengkap); ?>"
                    required
                >
            </div>


            <div class="edit-warga-group">
                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    maxlength="50"
                    value="<?php echo htmlspecialchars($username); ?>"
                    required
                >
            </div>


            <div class="edit-warga-group">
                <label for="email">
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="100"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required
                >
            </div>


            <div class="edit-warga-group">
                <label for="no_hp">
                    No. HP
                </label>

                <input
                    type="text"
                    id="no_hp"
                    name="no_hp"
                    maxlength="20"
                    value="<?php echo htmlspecialchars($no_hp ?? ''); ?>"
                >
            </div>


            <div class="edit-warga-group full">
                <label for="alamat">
                    Alamat
                </label>

                <textarea
                    id="alamat"
                    name="alamat"
                    maxlength="500"
                ><?php echo htmlspecialchars($alamat ?? ''); ?></textarea>
            </div>


            <div class="edit-warga-group">
                <label for="status">
                    Status Akun
                </label>

                <select
                    id="status"
                    name="status"
                    required
                >
                    <option
                        value="aktif"
                        <?php echo $status === 'aktif' ? 'selected' : ''; ?>
                    >
                        Aktif
                    </option>

                    <option
                        value="nonaktif"
                        <?php echo $status === 'nonaktif' ? 'selected' : ''; ?>
                    >
                        Nonaktif
                    </option>
                </select>
            </div>


            <div class="edit-warga-actions">

                <a
                    href="warga.php"
                    class="btn-edit-warga-cancel"
                >
                    Batal
                </a>

                <button
                    type="submit"
                    class="btn-edit-warga-save"
                >
                    Simpan Perubahan
                </button>

            </div>

        </form>

    </div>

</div>

</body>
</html>