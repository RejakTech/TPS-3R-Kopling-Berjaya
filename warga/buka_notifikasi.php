<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$user_id = (int) $_SESSION['user_id'];
$notifikasi_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

/*
 * Validasi ID notifikasi
 */
if ($notifikasi_id <= 0) {
    header("Location: notifikasi.php");
    exit;
}

/*
 * Pastikan notifikasi milik warga yang sedang login
 * sekaligus mengambil link asli dari database.
 */
$stmt_cek = mysqli_prepare(
    $conn,
    "SELECT id, link
     FROM notifikasi
     WHERE id = ?
     AND user_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt_cek,
    "ii",
    $notifikasi_id,
    $user_id
);

mysqli_stmt_execute($stmt_cek);

$result_cek = mysqli_stmt_get_result($stmt_cek);
$notifikasi = mysqli_fetch_assoc($result_cek);

mysqli_stmt_close($stmt_cek);

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
 * Tandai notifikasi sebagai sudah dibaca
 */
$stmt_baca = mysqli_prepare(
    $conn,
    "UPDATE notifikasi
     SET dibaca = 1
     WHERE id = ?
     AND user_id = ?"
);

mysqli_stmt_bind_param(
    $stmt_baca,
    "ii",
    $notifikasi_id,
    $user_id
);

mysqli_stmt_execute($stmt_baca);
mysqli_stmt_close($stmt_baca);

/*
 * Hanya izinkan halaman internal warga
 */
$allowed_pages = [
    'dashboard.php',
    'riwayat_setoran.php',
    'riwayat_tabungan.php',
    'riwayat_penarikan.php',
    'notifikasi.php'
];

/*
 * Ambil nama file tujuan
 */
$link_path = parse_url($link, PHP_URL_PATH);
$link_file = basename($link_path);

/*
 * Jika tujuan tidak diizinkan,
 * kembali ke halaman notifikasi
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
 * Redirect ke halaman tujuan
 */
header("Location: " . $redirect_url);
exit;
?>