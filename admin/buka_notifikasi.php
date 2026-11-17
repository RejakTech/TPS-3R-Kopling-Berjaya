<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$user_id = (int) $_SESSION['user_id'];
$notifikasi_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($notifikasi_id <= 0) {
    header("Location: notifikasi.php");
    exit;
}

/*
 * Pastikan notifikasi milik admin yang sedang login
 * sekaligus ambil link asli dari database.
 */
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, link
     FROM notifikasi
     WHERE id = ?
     AND user_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $notifikasi_id,
    $user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$notifikasi = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$notifikasi) {
    header("Location: notifikasi.php");
    exit;
}

$link = $notifikasi['link'] ?? '';

if (trim($link) === '') {
    header("Location: notifikasi.php");
    exit;
}

/*
 * Tandai sebagai sudah dibaca
 */
$stmt = mysqli_prepare(
    $conn,
    "UPDATE notifikasi
     SET dibaca = 1
     WHERE id = ?
     AND user_id = ?"
);

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $notifikasi_id,
    $user_id
);

mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

/*
 * Halaman internal yang diizinkan
 */
$allowed_pages = [
    'dashboard.php',
    'setoran.php',
    'detail_setoran.php',
    'notifikasi.php',
    'penarikan.php',
    'laporan.php'
];

/*
 * Ambil nama file tujuan
 */
$link_path = parse_url($link, PHP_URL_PATH);
$link_file = basename($link_path);

/*
 * Cegah redirect ke halaman luar
 */
if (!in_array($link_file, $allowed_pages, true)) {
    header("Location: notifikasi.php");
    exit;
}

/*
 * Pertahankan query seperti ?id=42
 */
$query_string = parse_url($link, PHP_URL_QUERY);

$redirect_url = $link_file;

if (!empty($query_string)) {
    $redirect_url .= '?' . $query_string;
}

/*
 * Redirect
 */
header("Location: " . $redirect_url);
exit;
?>