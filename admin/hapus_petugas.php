
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

// Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: petugas.php");
    exit;
}

// Validasi token keamanan
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    header("Location: petugas.php?status=token_gagal");
    exit;
}

// Ambil ID petugas
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    header("Location: petugas.php?status=hapus_gagal");
    exit;
}

// Pastikan akun benar-benar petugas
$query = mysqli_prepare(
    $conn,
    "DELETE FROM users WHERE id = ? AND role = 'petugas'"
);

mysqli_stmt_bind_param($query, "i", $id);

if (mysqli_stmt_execute($query)) {

    if (mysqli_stmt_affected_rows($query) > 0) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        mysqli_stmt_close($query);

        header("Location: petugas.php?status=hapus_berhasil");
        exit;
    }

    mysqli_stmt_close($query);

    header("Location: petugas.php?status=hapus_gagal");
    exit;

} else {
    mysqli_stmt_close($query);

    header("Location: petugas.php?status=hapus_gagal");
    exit;
}
