
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
require_once '../config/notifikasi.php';
require_once '../config/log_aktivitas.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Pastikan permintaan menggunakan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: penarikan.php");
    exit;
}

// Validasi token keamanan
if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    header("Location: penarikan.php?status=token_gagal");
    exit;
}

$penarikan_id = filter_var(
    $_POST['penarikan_id'] ?? null,
    FILTER_VALIDATE_INT
);

$aksi = $_POST['aksi'] ?? '';
$catatan_admin = trim($_POST['catatan_admin'] ?? '');

if (!$penarikan_id || $penarikan_id <= 0) {
    header("Location: penarikan.php?status=data_gagal");
    exit;
}

if (!in_array($aksi, ['disetujui', 'ditolak'], true)) {
    header("Location: penarikan.php?status=data_gagal");
    exit;
}

if ($aksi === 'ditolak' && $catatan_admin === '') {
    header("Location: penarikan.php?status=alasan_kosong");
    exit;
}

if (strlen($catatan_admin) > 5000) {
    header("Location: penarikan.php?status=catatan_gagal");
    exit;
}

try {

    mysqli_begin_transaction($conn);

    // 1. Kunci data pengajuan agar tidak diproses bersamaan
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, user_id, jumlah, metode_penarikan,
                status, catatan
         FROM penarikan_tabungan
         WHERE id = ?
         FOR UPDATE"
    );

    mysqli_stmt_bind_param($stmt, "i", $penarikan_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $penarikan = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if (!$penarikan) {
        throw new Exception("data_tidak_ditemukan");
    }

    // 2. Pastikan belum pernah diproses
    if ($penarikan['status'] !== 'menunggu') {
        throw new Exception("sudah_diproses");
    }

    $user_id = (int) $penarikan['user_id'];
    $jumlah = (float) $penarikan['jumlah'];

    // 3. Ambil dan kunci rekening tabungan warga
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, saldo
         FROM tabungan_warga
         WHERE user_id = ?
         FOR UPDATE"
    );

    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $tabungan = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if (!$tabungan) {
        throw new Exception("rekening_tidak_ditemukan");
    }

    $tabungan_id = (int) $tabungan['id'];
    $saldo = (float) $tabungan['saldo'];

    // 4. Proses jika admin menyetujui
    if ($aksi === 'disetujui') {

        // Periksa saldo terbaru
        if ($jumlah <= 0 || $saldo < $jumlah) {
            throw new Exception("saldo_tidak_cukup");
        }

        // Kurangi saldo warga
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE tabungan_warga
             SET saldo = saldo - ?
             WHERE id = ? AND saldo >= ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "did",
            $jumlah,
            $tabungan_id,
            $jumlah
        );

        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) !== 1) {
            mysqli_stmt_close($stmt);
            throw new Exception("saldo_tidak_cukup");
        }

        mysqli_stmt_close($stmt);

        // 5. Catat transaksi keluar
        $keterangan = "Penarikan tabungan #" . $penarikan_id
            . " melalui " . $penarikan['metode_penarikan'];

        $jenis_transaksi = "keluar";

        $setoran_id = null;

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO mutasi_tabungan
            (
                tabungan_id,
                setoran_id,
                jenis_transaksi,
                jumlah,
                keterangan
            )
            VALUES (?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "iisds",
            $tabungan_id,
            $setoran_id,
            $jenis_transaksi,
            $jumlah,
            $keterangan
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

    }

    // 6. Simpan catatan admin tanpa menghapus catatan warga
    $catatan_lama = trim($penarikan['catatan'] ?? '');

    if ($catatan_admin !== '') {

        $catatan_baru = $catatan_lama !== ''
            ? $catatan_lama . "\n\nCatatan Admin: " . $catatan_admin
            : "Catatan Admin: " . $catatan_admin;

    } else {

        $catatan_baru = $catatan_lama !== ''
            ? $catatan_lama
            : null;
    }

    // 7. Perbarui status pengajuan
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE penarikan_tabungan
         SET status = ?,
             catatan = ?,
             tanggal_diproses = CURRENT_TIMESTAMP
         WHERE id = ? AND status = 'menunggu'"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ssi",
        $aksi,
        $catatan_baru,
        $penarikan_id
    );

    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) !== 1) {
        mysqli_stmt_close($stmt);
        throw new Exception("sudah_diproses");
    }

    mysqli_stmt_close($stmt);

    
    // 8. Kirim notifikasi kepada warga
    if ($aksi === 'disetujui') {

        $judul_notifikasi = 'Penarikan Tabungan Disetujui';

        $pesan_notifikasi =
            'Pengajuan penarikan tabungan #' . $penarikan_id .
            ' sebesar Rp ' . number_format($jumlah, 0, ',', '.') .
            ' telah disetujui oleh admin dan saldo tabungan Anda telah dikurangi.';

        $tipe_notifikasi = 'sukses';

    } else {

        $judul_notifikasi = 'Penarikan Tabungan Ditolak';

        $pesan_notifikasi =
            'Pengajuan penarikan tabungan #' . $penarikan_id .
            ' sebesar Rp ' . number_format($jumlah, 0, ',', '.') .
            ' ditolak oleh admin. Catatan: ' .
            ($catatan_admin !== '' ? $catatan_admin : 'Tidak ada catatan.');

        $tipe_notifikasi = 'peringatan';
    }

    if (!kirimNotifikasi(
    $conn,
    $user_id,
    $judul_notifikasi,
    $pesan_notifikasi,
    $tipe_notifikasi,
    'riwayat_penarikan.php?id=' . $penarikan_id
    )) {
        throw new Exception("Gagal mengirim notifikasi penarikan.");
    }

    $aktivitas_log = $aksi === 'disetujui'
    ? 'Menyetujui penarikan tabungan'
    : 'Menolak penarikan tabungan';

$keterangan_log =
    'Pengajuan penarikan #' . $penarikan_id .
    ' sebesar Rp ' . number_format($jumlah, 0, ',', '.') .
    ' milik user #' . $user_id .
    ($catatan_admin !== ''
        ? '. Catatan admin: ' . $catatan_admin
        : '.');

catatAktivitas(
    $conn,
    $aktivitas_log,
    $keterangan_log
);

// ===================================
// CATAT AKTIVITAS UNTUK WARGA
// ===================================

if ($aksi === 'disetujui') {

    $aktivitas_warga = 'Penarikan Tabungan Disetujui';

    $keterangan_warga =
        'Pengajuan penarikan #' . $penarikan_id .
        ' sebesar Rp ' .
        number_format($jumlah, 0, ',', '.') .
        ' telah disetujui oleh admin dan saldo tabungan Anda telah dikurangi.';

} else {

    $aktivitas_warga = 'Penarikan Tabungan Ditolak';

    $keterangan_warga =
        'Pengajuan penarikan #' . $penarikan_id .
        ' sebesar Rp ' .
        number_format($jumlah, 0, ',', '.') .
        ' ditolak oleh admin.';

    if ($catatan_admin !== '') {
        $keterangan_warga .=
            ' Catatan admin: ' . $catatan_admin;
    }
}

if (!catatAktivitas(
    $conn,
    $aktivitas_warga,
    $keterangan_warga,
    $user_id
)) {
    throw new Exception("Gagal mencatat aktivitas warga.");
}

    // 9. Simpan seluruh perubahan sekaligus
    mysqli_commit($conn);


    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    if ($aksi === 'disetujui') {
        header("Location: penarikan.php?status=disetujui&id=" . $penarikan_id);
    } else {
        header("Location: penarikan.php?status=ditolak&id=" . $penarikan_id);
    }

    exit;

} catch (Throwable $e) {

    mysqli_rollback($conn);

    if ($e->getMessage() === "data_tidak_ditemukan") {
        header("Location: penarikan.php?status=data_gagal");

    } elseif ($e->getMessage() === "sudah_diproses") {
        header("Location: penarikan.php?status=sudah_diproses");

    } elseif ($e->getMessage() === "rekening_tidak_ditemukan") {
        header("Location: penarikan.php?status=saldo_gagal");

    } elseif ($e->getMessage() === "saldo_tidak_cukup") {
        header("Location: penarikan.php?status=saldo_tidak_cukup");

    } else {

        error_log(
            "Proses penarikan gagal: " . $e->getMessage()
        );

        header("Location: penarikan.php?status=proses_gagal");
    }

    exit;
}
