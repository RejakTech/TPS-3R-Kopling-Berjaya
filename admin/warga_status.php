<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';


// ========================================
// CSRF TOKEN
// ========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ========================================
// PROSES PERUBAHAN STATUS
// ========================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: warga.php");
    exit;
}


// ========================================
// VALIDASI CSRF
// ========================================

$csrf_token = $_POST['csrf_token'] ?? '';

if (
    empty($csrf_token) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    die("Permintaan tidak valid.");
}


// ========================================
// VALIDASI ID
// ========================================

$id = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($id === false || $id <= 0) {
    die("ID warga tidak valid.");
}


// ========================================
// AMBIL STATUS WARGA
// ========================================

$query = mysqli_prepare(
    $conn,
    "SELECT status
     FROM users
     WHERE id = ?
     AND role = 'warga'
     LIMIT 1"
);

if (!$query) {
    die("Gagal menyiapkan query.");
}

mysqli_stmt_bind_param($query, "i", $id);

if (!mysqli_stmt_execute($query)) {
    mysqli_stmt_close($query);
    die("Gagal mengambil data warga.");
}

$result = mysqli_stmt_get_result($query);

if (!$result || mysqli_num_rows($result) !== 1) {
    mysqli_stmt_close($query);
    die("Data warga tidak ditemukan.");
}

$warga = mysqli_fetch_assoc($result);

mysqli_stmt_close($query);


// ========================================
// TENTUKAN STATUS BARU
// ========================================

$status_lama = $warga['status'];

if ($status_lama === 'aktif') {
    $status_baru = 'nonaktif';
} elseif ($status_lama === 'nonaktif') {
    $status_baru = 'aktif';
} else {
    die("Status warga tidak valid.");
}


// ========================================
// UPDATE STATUS
// ========================================

$update = mysqli_prepare(
    $conn,
    "UPDATE users
     SET status = ?
     WHERE id = ?
     AND role = 'warga'"
);

if (!$update) {
    die("Gagal menyiapkan proses perubahan status.");
}

mysqli_stmt_bind_param(
    $update,
    "si",
    $status_baru,
    $id
);

if (!mysqli_stmt_execute($update)) {
    mysqli_stmt_close($update);
    die("Gagal mengubah status warga.");
}

mysqli_stmt_close($update);


// ========================================
// REGENERATE CSRF TOKEN
// ========================================

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));


// ========================================
// KEMBALI KE DATA WARGA
// ========================================

header("Location: warga.php");
exit;
?>