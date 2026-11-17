
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

// Hapus data dari database
$hapus = mysqli_prepare(
    $conn,
    "DELETE FROM pengumuman WHERE id = ?"
);

mysqli_stmt_bind_param($hapus, "i", $id);

if (mysqli_stmt_execute($hapus)) {

    // Hapus gambar jika tersedia
    if (!empty($data['gambar'])) {

        $namaGambar = basename($data['gambar']);

        $lokasiGambar = __DIR__ .
            '/../uploads/pengumuman/' .
            $namaGambar;

        if (is_file($lokasiGambar)) {
            unlink($lokasiGambar);
        }
    }

    mysqli_stmt_close($hapus);

    header("Location: pengumuman.php?pesan=terhapus");
    exit;

} else {

    mysqli_stmt_close($hapus);

    header("Location: pengumuman.php?pesan=gagal_hapus");
    exit;
}
?>
