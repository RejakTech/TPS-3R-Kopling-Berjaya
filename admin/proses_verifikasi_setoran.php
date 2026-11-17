
<?php
session_start();

// =============================
// VALIDASI AKSES
// =============================

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'], ['admin', 'petugas'], true)
) {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
require_once '../config/notifikasi.php';
require_once '../config/log_aktivitas.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// =============================
// HALAMAN TUJUAN
// =============================

$halaman_setoran = $_SESSION['role'] === 'petugas'
    ? '../petugas/setoran.php'
    : 'setoran.php';

function kembaliSetoran($halaman, $status = '')
{
    $url = $halaman;

    if ($status !== '') {
        $url .= '?status=' . urlencode($status);
    }

    header("Location: " . $url);
    exit;
}

// =============================
// VALIDASI REQUEST
// =============================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kembaliSetoran($halaman_setoran);
}

// Validasi CSRF
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    kembaliSetoran($halaman_setoran, 'token_gagal');
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$aksi = $_POST['aksi'] ?? '';

$alasan_penolakan = trim($_POST['alasan_penolakan'] ?? '');

if (
    $id <= 0 ||
    !in_array($aksi, ['terima', 'tolak'], true)
) {
    kembaliSetoran($halaman_setoran, 'verifikasi_gagal');
}

// Alasan wajib diisi jika setoran ditolak
if ($aksi === 'tolak') {

    if ($alasan_penolakan === '' || strlen($alasan_penolakan) > 1000) {
        kembaliSetoran($halaman_setoran, 'alasan_gagal');
    }

} else {
    $alasan_penolakan = null;
}

// =============================
// PROSES VERIFIKASI
// =============================

try {

    mysqli_begin_transaction($conn);

    // Ambil data setoran dan kunci baris
    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM setoran_sampah WHERE id = ? FOR UPDATE"
    );

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $setoran = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if (!$setoran) {
        throw new Exception("Setoran tidak ditemukan.");
    }

    if ($setoran['status'] !== 'menunggu') {
        throw new Exception("Setoran sudah pernah diproses.");
    }

    $user_id = (int) $setoran['user_id'];
    $verifikator_id = (int) $_SESSION['user_id'];

    // =============================
    // JIKA SETORAN DITERIMA
    // =============================

    if ($aksi === 'terima') {

        $jumlah = (float) $setoran['total_harga'];

        if (!is_finite($jumlah) || $jumlah < 0) {
            throw new Exception("Nilai setoran tidak valid.");
        }

        // Buat tabungan jika belum tersedia
        $buat_tabungan = mysqli_prepare(
            $conn,
            "INSERT IGNORE INTO tabungan_warga (user_id, saldo)
             VALUES (?, 0)"
        );

        mysqli_stmt_bind_param($buat_tabungan, "i", $user_id);
        mysqli_stmt_execute($buat_tabungan);
        mysqli_stmt_close($buat_tabungan);

        // Ambil tabungan warga
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM tabungan_warga
             WHERE user_id = ? FOR UPDATE"
        );

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $tabungan = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        if (!$tabungan) {
            throw new Exception("Rekening tabungan tidak ditemukan.");
        }

        $tabungan_id = (int) $tabungan['id'];

        // Tambahkan saldo
        $update_saldo = mysqli_prepare(
            $conn,
            "UPDATE tabungan_warga
             SET saldo = saldo + ?
             WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $update_saldo,
            "di",
            $jumlah,
            $tabungan_id
        );

        mysqli_stmt_execute($update_saldo);
        mysqli_stmt_close($update_saldo);

        // Catat mutasi tabungan
        $keterangan = "Penerimaan setoran sampah #" . $id;
        $jenis = "masuk";

        $mutasi = mysqli_prepare(
            $conn,
            "INSERT INTO mutasi_tabungan
            (tabungan_id, setoran_id, jenis_transaksi, jumlah, keterangan)
            VALUES (?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $mutasi,
            "iisds",
            $tabungan_id,
            $id,
            $jenis,
            $jumlah,
            $keterangan
        );

        mysqli_stmt_execute($mutasi);
        mysqli_stmt_close($mutasi);

        // Kirim notifikasi saldo bertambah kepada warga
        $judul_saldo = "Saldo Tabungan Bertambah";

        $pesan_saldo =
            "Setoran sampah #" . $id .
            " telah diterima. Saldo tabungan Anda bertambah sebesar Rp " .
            number_format($jumlah, 0, ',', '.') . ".";

        if (!kirimNotifikasi(
            $conn,
            (int) $setoran['user_id'],
            $judul_saldo,
            $pesan_saldo,
            "sukses",
            "riwayat_tabungan.php"
        )) {
            throw new Exception("Gagal mengirim notifikasi saldo kepada warga.");
        }

        $status = "diterima";

    } else {

        // =============================
        // JIKA SETORAN DITOLAK
        // =============================

        $status = "ditolak";
    }

    // =============================
    // UPDATE STATUS DAN ALASAN
    // =============================

    $update_setoran = mysqli_prepare(
        $conn,
        "UPDATE setoran_sampah
         SET status = ?,
             petugas_id = ?,
             alasan_penolakan = ?
         WHERE id = ? AND status = 'menunggu'"
    );

    mysqli_stmt_bind_param(
        $update_setoran,
        "sisi",
        $status,
        $verifikator_id,
        $alasan_penolakan,
        $id
    );

    mysqli_stmt_execute($update_setoran);

    if (mysqli_stmt_affected_rows($update_setoran) !== 1) {
        mysqli_stmt_close($update_setoran);
        throw new Exception("Gagal memperbarui status setoran.");
    }

    mysqli_stmt_close($update_setoran);

    // =============================
// CATAT LOG AKTIVITAS
// =============================

$nama_role = ucfirst($_SESSION['role']);

if ($aksi === 'terima') {

    $aktivitas = 'Verifikasi Setoran Diterima';

    $keterangan = $nama_role .
        ' menerima setoran #' . $id .
        ' dengan berat ' . $setoran['berat_kg'] .
        ' Kg dan nilai Rp ' .
        number_format($setoran['total_harga'], 0, ',', '.');

} else {

    $aktivitas = 'Verifikasi Setoran Ditolak';

    $keterangan = $nama_role .
        ' menolak setoran #' . $id .
        '. Alasan: ' . $alasan_penolakan;
}

if (!catatAktivitas($conn, $aktivitas, $keterangan)) {
    throw new Exception("Gagal mencatat log aktivitas.");
}

// ===================================
// CATAT AKTIVITAS UNTUK WARGA
// ===================================

if ($aksi === 'terima') {

    $aktivitas_warga = 'Setoran Diterima';

    $keterangan_warga =
        'Setoran sampah #' . $id .
        ' telah diterima dengan berat ' .
        number_format((float) $setoran['berat_kg'], 2, ',', '.') .
        ' Kg dan nilai Rp ' .
        number_format((float) $setoran['total_harga'], 0, ',', '.') . '.';

} else {

    $aktivitas_warga = 'Setoran Ditolak';

    $keterangan_warga =
        'Setoran sampah #' . $id .
        ' ditolak. Alasan: ' .
        $alasan_penolakan;
}

if (!catatAktivitas(
    $conn,
    $aktivitas_warga,
    $keterangan_warga,
    $user_id
)) {
    throw new Exception("Gagal mencatat aktivitas warga.");
}
    
// Kirim notifikasi kepada warga pemilik setoran
$user_id_notifikasi = (int) $setoran['user_id'];

if ($aksi === 'terima') {

    $judul_notifikasi = 'Setoran Berhasil Diterima';

    $pesan_notifikasi =
        'Setoran sampah Anda dengan nomor #' . $id .
        ' telah diterima. Saldo tabungan Anda telah diperbarui.';

    $tipe_notifikasi = 'sukses';

} else {

    $judul_notifikasi = 'Setoran Ditolak';

    $pesan_notifikasi =
        'Setoran sampah Anda dengan nomor #' . $id .
        ' ditolak. Alasan: ' . $alasan_penolakan;

    $tipe_notifikasi = 'peringatan';
}

if (!kirimNotifikasi(
    $conn,
    $user_id_notifikasi,
    $judul_notifikasi,
    $pesan_notifikasi,
    $tipe_notifikasi,
    'riwayat_setoran.php?id=' . $id
)) {
    throw new Exception("Gagal mengirim notifikasi kepada warga.");
}


    // Simpan seluruh perubahan
    mysqli_commit($conn);

    // Perbarui token keamanan
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    kembaliSetoran(
        $halaman_setoran,
        $aksi === 'terima' ? 'diterima' : 'ditolak'
    );

} catch (Throwable $e) {

    mysqli_rollback($conn);

    error_log("Verifikasi setoran gagal: " . $e->getMessage());

    kembaliSetoran($halaman_setoran, 'verifikasi_gagal');
}
