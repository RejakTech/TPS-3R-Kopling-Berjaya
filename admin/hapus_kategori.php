
<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

// Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: kategori.php");
    exit;
}

// Validasi token keamanan
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    header("Location: kategori.php?status=token_gagal");
    exit;
}

// Ambil ID kategori
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    header("Location: kategori.php?status=hapus_gagal");
    exit;
}

try {

    // Periksa apakah kategori digunakan dalam transaksi
    $cek = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM setoran_sampah
         WHERE kategori_id = ?"
    );

    mysqli_stmt_bind_param($cek, "i", $id);

    if (!mysqli_stmt_execute($cek)) {
        throw new mysqli_sql_exception(
            mysqli_stmt_error($cek),
            mysqli_stmt_errno($cek)
        );
    }

    $result = mysqli_stmt_get_result($cek);
    $data = mysqli_fetch_assoc($result);

    mysqli_stmt_close($cek);

    // Jika kategori sudah digunakan
    if ($data['total'] > 0) {
        header("Location: kategori.php?status=kategori_digunakan");
        exit;
    }

    // Hapus kategori jika belum digunakan
    $query = mysqli_prepare(
        $conn,
        "DELETE FROM kategori_sampah WHERE id = ?"
    );

    mysqli_stmt_bind_param($query, "i", $id);

    if (!mysqli_stmt_execute($query)) {
        throw new mysqli_sql_exception(
            mysqli_stmt_error($query),
            mysqli_stmt_errno($query)
        );
    }

    $terhapus = mysqli_stmt_affected_rows($query);

    mysqli_stmt_close($query);

    if ($terhapus > 0) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header("Location: kategori.php?status=hapus_berhasil");
        exit;
    }

    header("Location: kategori.php?status=hapus_gagal");
    exit;

} catch (mysqli_sql_exception $e) {

    // Tampilkan error asli untuk pemeriksaan sementara
    die(
        "DEBUG HAPUS KATEGORI: " .
        htmlspecialchars($e->getMessage())
    );

}
