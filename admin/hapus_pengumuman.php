
<?php
session_start();

// Proteksi halaman admin
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

const REDIRECT_GAGAL_HAPUS = "Location: pengumuman.php?pesan=gagal_hapus";

// Pastikan penghapusan menggunakan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: pengumuman.php");
    exit;
}

// Ambil ID pengumuman
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    header("Location: pengumuman.php?pesan=id_tidak_valid");
    exit;
}

// Ambil nama gambar berdasarkan ID
$stmt = mysqli_prepare(
    $conn,
    "SELECT gambar FROM pengumuman WHERE id = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

// Periksa apakah pengumuman tersedia
if (!$data) {
    header("Location: pengumuman.php?pesan=data_tidak_ditemukan");
    exit;
}

$gambarTambahan = [];
$gambarStmt = mysqli_prepare(
    $conn,
    "SELECT nama_file FROM pengumuman_gambar WHERE pengumuman_id = ?"
);

if (!$gambarStmt) {
    header(REDIRECT_GAGAL_HAPUS);
    exit;
}

mysqli_stmt_bind_param($gambarStmt, "i", $id);
mysqli_stmt_execute($gambarStmt);
$gambarResult = mysqli_stmt_get_result($gambarStmt);

while ($row = mysqli_fetch_assoc($gambarResult)) {
    $gambarTambahan[] = $row['nama_file'];
}

mysqli_stmt_close($gambarStmt);

// Hapus data dan relasi gambar dalam satu transaksi.
$hapusGambar = mysqli_prepare(
    $conn,
    "DELETE FROM pengumuman_gambar WHERE pengumuman_id = ?"
);
$hapus = mysqli_prepare(
    $conn,
    "DELETE FROM pengumuman WHERE id = ?"
);

if (!$hapusGambar || !$hapus) {
    if ($hapusGambar) {
        mysqli_stmt_close($hapusGambar);
    }
    if ($hapus) {
        mysqli_stmt_close($hapus);
    }
    header(REDIRECT_GAGAL_HAPUS);
    exit;
}

mysqli_begin_transaction($conn);
mysqli_stmt_bind_param($hapusGambar, "i", $id);
mysqli_stmt_bind_param($hapus, "i", $id);

if (mysqli_stmt_execute($hapusGambar) && mysqli_stmt_execute($hapus) && mysqli_commit($conn)) {

    $fileGambar = $gambarTambahan;
    if (!empty($data['gambar'])) {
        $fileGambar[] = $data['gambar'];
    }
    require_once __DIR__ . '/../config/pengumuman_gambar.php';
    hapusFileGambarPengumuman($fileGambar);

    mysqli_stmt_close($hapusGambar);
    mysqli_stmt_close($hapus);

    header("Location: pengumuman.php?pesan=terhapus");
    exit;

} else {

    mysqli_rollback($conn);
    mysqli_stmt_close($hapusGambar);
    mysqli_stmt_close($hapus);

    header(REDIRECT_GAGAL_HAPUS);
    exit;
}
