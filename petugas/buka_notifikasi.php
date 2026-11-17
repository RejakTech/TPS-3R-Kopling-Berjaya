<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
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
 * Pastikan notifikasi milik petugas yang sedang login
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

$link = trim($notifikasi['link'] ?? '');

if ($link === '') {
    header("Location: notifikasi.php");
    exit;
}

/*
 * Tolak URL absolut/external.
 * Link notifikasi harus berupa halaman internal petugas.
 */
$parsed_link = parse_url($link);

if (
    isset($parsed_link['scheme']) ||
    isset($parsed_link['host']) ||
    str_starts_with($link, '//')
) {
    header("Location: notifikasi.php");
    exit;
}

/*
 * Tandai notifikasi sebagai sudah dibaca
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
 * Hanya izinkan halaman internal petugas
 */
$allowed_pages = [
    'dashboard.php',
    'setoran.php',
    'detail_setoran.php',
    'notifikasi.php'
];

$link_path = parse_url($link, PHP_URL_PATH);
$link_file = basename($link_path);

if (!in_array($link_file, $allowed_pages, true)) {
    header("Location: notifikasi.php");
    exit;
}

/*
 * Pertahankan query string seperti:
 * detail_setoran.php?id=42
 */
$query_string = parse_url($link, PHP_URL_QUERY);

$redirect_url = $link_file;

if (!empty($query_string)) {
    $redirect_url .= '?' . $query_string;
}

header("Location: " . $redirect_url);
exit;
?>