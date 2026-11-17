
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
require_once '../config/notifikasi.php';
require_once '../config/log_aktivitas.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$error = "";

// Token keamanan
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int) $_SESSION['user_id'];

// Ambil saldo tabungan warga
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, saldo
     FROM tabungan_warga
     WHERE user_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$tabungan = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$saldo = $tabungan ? (float) $tabungan['saldo'] : 0;

$jumlah = "";
$metode = "tunai";
$nomor_rekening = "";
$nama_pemilik = "";
$catatan = "";

// Proses pengajuan penarikan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $jumlah = trim($_POST['jumlah'] ?? '');
    $metode = $_POST['metode_penarikan'] ?? '';
    $nomor_rekening = trim($_POST['nomor_rekening'] ?? '');
    $nama_pemilik = trim($_POST['nama_pemilik_rekening'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {

        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";

    } elseif (
        !is_numeric($jumlah) ||
        !is_finite((float) $jumlah) ||
        (float) $jumlah <= 0 ||
        (float) $jumlah > 9999999999.99
    ) {

        $error = "Nominal penarikan tidak valid.";

    } elseif (!in_array($metode, ['tunai', 'transfer'], true)) {

        $error = "Silakan pilih metode penarikan yang valid.";

    } elseif ($metode === 'transfer' && $nomor_rekening === '') {

        $error = "Nomor rekening wajib diisi.";

    } elseif ($metode === 'transfer' && $nama_pemilik === '') {

        $error = "Nama pemilik rekening wajib diisi.";

    } elseif (strlen($nomor_rekening) > 50 || strlen($nama_pemilik) > 100) {

        $error = "Informasi rekening terlalu panjang.";

    } elseif (strlen($catatan) > 5000) {

        $error = "Catatan maksimal 5000 karakter.";

    } else {

        try {

            $nominal = (float) $jumlah;

            // Periksa kembali saldo terbaru dari database
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id, saldo
                 FROM tabungan_warga
                 WHERE user_id = ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $rekening = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (!$rekening) {

                $error = "Rekening tabungan belum tersedia.";

            } elseif ($nominal > (float) $rekening['saldo']) {

                $error = "Saldo tabungan tidak mencukupi untuk penarikan.";

            } else {

                // Data rekening hanya digunakan untuk transfer
                $nomor_db = $metode === 'transfer'
                    ? $nomor_rekening
                    : null;

                $nama_db = $metode === 'transfer'
                    ? $nama_pemilik
                    : null;

                $catatan_db = $catatan !== ""
                    ? $catatan
                    : null;

                $status = "menunggu";

                // Simpan pengajuan penarikan
                $insert = mysqli_prepare(
                    $conn,
                    "INSERT INTO penarikan_tabungan
                    (
                        user_id,
                        jumlah,
                        metode_penarikan,
                        nomor_rekening,
                        nama_pemilik_rekening,
                        status,
                        catatan
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $insert,
                    "idsssss",
                    $user_id,
                    $nominal,
                    $metode,
                    $nomor_db,
                    $nama_db,
                    $status,
                    $catatan_db
                );

                mysqli_stmt_execute($insert);

                // Ambil ID pengajuan penarikan yang baru dibuat
                $penarikan_id = mysqli_insert_id($conn);

                mysqli_stmt_close($insert);

                // ===================================
                // CATAT AKTIVITAS WARGA
                // ===================================

                catatAktivitas(
                    $conn,
                    'Pengajuan Penarikan',
                    'Mengajukan penarikan tabungan sebesar Rp ' .
                    number_format($nominal, 0, ',', '.') .
                    ' melalui metode ' .
                    ($metode === 'transfer' ? 'Transfer Bank' : 'Tunai') .
                    ' dengan nomor pengajuan #' . $penarikan_id . '.'
                );

                // Kirim notifikasi kepada Admin
                try {

                    $query_admin = mysqli_query(
                        $conn,
                        "SELECT id
                        FROM users
                        WHERE role = 'admin'"
                    );

                    $judul_notifikasi = "Pengajuan Penarikan Baru";

                    $pesan_notifikasi =
                        ($_SESSION['nama'] ?? 'Warga') .
                        " mengajukan penarikan tabungan sebesar Rp " .
                        number_format($nominal, 0, ',', '.') .
                        ". Silakan periksa dan proses pengajuan tersebut.";

                    while ($admin = mysqli_fetch_assoc($query_admin)) {

                        $berhasil_notifikasi = kirimNotifikasi(
                            $conn,
                            (int) $admin['id'],
                            $judul_notifikasi,
                            $pesan_notifikasi,
                            "informasi",
                            "penarikan.php?id=" . $penarikan_id
                        );

                        if (!$berhasil_notifikasi) {
                            error_log(
                                "Notifikasi penarikan #$penarikan_id gagal dikirim ke admin ID " .
                                $admin['id']
                            );
                        }
                    }

                } catch (Throwable $e) {

                    error_log(
                        "Pengiriman notifikasi penarikan #$penarikan_id gagal: " .
                        $e->getMessage()
                    );
                }

                // Buat token baru
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header(
                    "Location: riwayat_penarikan.php?status=berhasil"
                );
                exit;
            }

        } catch (mysqli_sql_exception $e) {

            error_log("Pengajuan penarikan gagal: " . $e->getMessage());

            $error = "Pengajuan penarikan gagal disimpan. Silakan coba kembali.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ajukan Penarikan - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

<div class="dashboard-warga">

    <main class="warga-content">

        <div class="warga-heading">

            <h1>Penarikan Tabungan</h1>

            <p>
                Ajukan penarikan saldo tabungan Bank Sampah Digital.
            </p>

        </div>

        <a href="dashboard.php">
            ← Kembali ke Dashboard
        </a>

        <?php if ($error !== "") : ?>

            <div class="form-alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>

        <!-- INFORMASI SALDO -->

        <div class="tabungan-saldo-card">

            <span>Saldo Tersedia</span>

            <h2>
                Rp <?php echo number_format($saldo, 0, ',', '.'); ?>
            </h2>

            <p>Saldo rekening tabungan kamu saat ini.</p>

        </div>

        <!-- FORM PENARIKAN -->

        <div class="petugas-form-container">

            <div class="petugas-form-heading">

                <h2>Formulir Penarikan</h2>

                <p>
                    Lengkapi informasi penarikan dengan benar.
                </p>

            </div>

            <?php if ($tabungan && $saldo > 0) : ?>

                <form method="POST" action="">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
                    >

                    <div class="petugas-form-grid">

                        <!-- NOMINAL -->

                        <div class="petugas-form-group full-width">

                            <label>
                                Nominal Penarikan <span>*</span>
                            </label>

                            <input
                                type="number"
                                name="jumlah"
                                min="0.01"
                                max="<?php echo htmlspecialchars((string)$saldo); ?>"
                                step="0.01"
                                placeholder="Masukkan nominal penarikan"
                                value="<?php echo htmlspecialchars($jumlah); ?>"
                                required
                            >

                        </div>

                        <!-- METODE PENARIKAN -->

                        <div class="petugas-form-group full-width">

                            <label>
                                Metode Penarikan <span>*</span>
                            </label>

                            <select
                                name="metode_penarikan"
                                id="metode_penarikan"
                                required
                            >

                                <option value="tunai"
                                    <?php echo $metode === 'tunai' ? 'selected' : ''; ?>>
                                    Tunai
                                </option>

                                <option value="transfer"
                                    <?php echo $metode === 'transfer' ? 'selected' : ''; ?>>
                                    Transfer Bank
                                </option>

                            </select>

                        </div>

                        <!-- INFORMASI REKENING -->

                        <div
                            id="informasi_rekening"
                            class="petugas-form-group full-width"
                            style="display:none;"
                        >

                            <div class="rekening-form-group">

                                <label>
                                    Nomor Rekening <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    name="nomor_rekening"
                                    id="nomor_rekening"
                                    maxlength="50"
                                    placeholder="Masukkan nomor rekening"
                                    value="<?php echo htmlspecialchars($nomor_rekening); ?>"
                                >

                            </div>

                            <div class="rekening-form-group">

                                <label>
                                    Nama Pemilik Rekening <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    name="nama_pemilik_rekening"
                                    id="nama_pemilik_rekening"
                                    maxlength="100"
                                    placeholder="Masukkan nama pemilik rekening"
                                    value="<?php echo htmlspecialchars($nama_pemilik); ?>"
                                >

                            </div>

                        </div>

                        <!-- CATATAN -->

                        <div class="petugas-form-group full-width">

                            <label>Catatan Tambahan</label>

                            <textarea
                                name="catatan"
                                rows="4"
                                maxlength="5000"
                                placeholder="Keterangan tambahan (opsional)"
                            ><?php echo htmlspecialchars($catatan); ?></textarea>

                        </div>

                    </div>

                    <div class="penarikan-info">

                        <strong>Informasi:</strong>

                        <p>
                            Pengajuan penarikan akan diperiksa oleh admin.
                            Saldo baru akan berkurang setelah penarikan disetujui.
                        </p>

                    </div>

                    <div class="petugas-form-actions">

                        <a
                            href="dashboard.php"
                            class="btn-batal-petugas"
                        >
                            Batal
                        </a>

                        <button
                            type="submit"
                            class="btn-simpan-petugas"
                        >
                            Ajukan Penarikan
                        </button>

                    </div>

                </form>

            <?php else : ?>

                <div class="form-alert-error">
                    Saldo tabungan kamu masih kosong atau rekening belum tersedia.
                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

<script>

const metodeSelect = document.getElementById('metode_penarikan');

const informasiRekening = document.getElementById('informasi_rekening');

const nomorRekening = document.getElementById('nomor_rekening');

const namaPemilik = document.getElementById('nama_pemilik_rekening');

function tampilkanRekening() {

    if (metodeSelect.value === 'transfer') {

        informasiRekening.style.display = 'block';

        nomorRekening.required = true;
        namaPemilik.required = true;

    } else {

        informasiRekening.style.display = 'none';

        nomorRekening.required = false;
        namaPemilik.required = false;

    }

}

metodeSelect.addEventListener('change', tampilkanRekening);

tampilkanRekening();

</script>

</body>

</html>
