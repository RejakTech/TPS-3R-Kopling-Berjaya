
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

// Nilai awal formulir
$kategori_id = "";
$berat_kg = "";
$tanggal_input = date('d/m/Y');
$tanggal_setor = "";
$catatan = "";

// Proses pengajuan setoran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $kategori_id = (int) ($_POST['kategori_id'] ?? 0);
    $berat_kg = trim($_POST['berat_kg'] ?? '');
    $tanggal_input = trim($_POST['tanggal_setor'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    // Validasi token keamanan
   if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {

        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";

    } elseif ($kategori_id <= 0) {

        $error = "Silakan pilih kategori sampah.";

    } elseif (
        !is_numeric($berat_kg) ||
        !is_finite((float) $berat_kg) ||
        (float) $berat_kg <= 0 ||
        (float) $berat_kg > 99999999.99
    ) {

        $error = "Berat sampah harus berupa angka lebih dari 0.";

    } else {

        // Konversi tanggal DD/MM/YYYY ke YYYY-MM-DD
        $tanggal_obj = DateTime::createFromFormat(
            '!d/m/Y',
            $tanggal_input
        );

        // Pastikan tanggal benar dan tidak mengalami perubahan otomatis
        if (
            !$tanggal_obj ||
            $tanggal_obj->format('d/m/Y') !== $tanggal_input
        ) {

            $error = "Format tanggal tidak valid. Gunakan DD/MM/YYYY.";

        } else {

            $tanggal_setor = $tanggal_obj->format('Y-m-d');

            // Tanggal tidak boleh melebihi hari ini
            if ($tanggal_setor > date('Y-m-d')) {

                $error = "Tanggal setoran tidak boleh melebihi hari ini.";

            } elseif (strlen($catatan) > 5000) {

                $error = "Catatan maksimal 5000 karakter.";

            } else {

                try {

                    // Ambil kategori aktif dan harga dari database
                    $cek_kategori = mysqli_prepare(
                        $conn,
                        "SELECT nama_kategori, harga_per_kg
                         FROM kategori_sampah
                         WHERE id = ? AND status = 'aktif'
                         LIMIT 1"
                    );

                    mysqli_stmt_bind_param(
                        $cek_kategori,
                        "i",
                        $kategori_id
                    );

                    mysqli_stmt_execute($cek_kategori);

                    $result_kategori = mysqli_stmt_get_result(
                        $cek_kategori
                    );

                    $kategori = mysqli_fetch_assoc(
                        $result_kategori
                    );

                    mysqli_stmt_close($cek_kategori);

                    if (!$kategori) {

                        $error = "Kategori tidak ditemukan atau sedang nonaktif.";

                    } else {

                        // Ambil berat dan harga dari database
                        $berat = (float) $berat_kg;
                        $harga = (float) $kategori['harga_per_kg'];

                        // Hitung total harga di server
                        $total_harga = round(
                            $berat * $harga,
                            2
                        );

                        if (
                            !is_finite($total_harga) ||
                            $total_harga > 9999999999.99
                        ) {

                            $error = "Total harga melebihi batas yang diperbolehkan.";

                        } else {

                            $user_id = (int) $_SESSION['user_id'];
                            $status = "menunggu";

                            $catatan_db = $catatan !== ""
                                ? $catatan
                                : null;

                            // Simpan pengajuan ke database
                            $insert = mysqli_prepare(
                                $conn,
                                "INSERT INTO setoran_sampah
                                (
                                    user_id,
                                    kategori_id,
                                    tanggal_setor,
                                    berat_kg,
                                    harga_per_kg,
                                    total_harga,
                                    status,
                                    catatan
                                )
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                            );

                            mysqli_stmt_bind_param(
                                $insert,
                                "iisdddss",
                                $user_id,
                                $kategori_id,
                                $tanggal_setor,
                                $berat,
                                $harga,
                                $total_harga,
                                $status,
                                $catatan_db
                            );

                            
mysqli_stmt_execute($insert);

// Ambil ID setoran yang baru dibuat
$setoran_id = mysqli_insert_id($conn);

mysqli_stmt_close($insert);

// ===================================
// CATAT AKTIVITAS WARGA
// ===================================

catatAktivitas(
    $conn,
    'Pengajuan Setoran',
    'Mengajukan setoran #' . $setoran_id .
    ' dengan kategori ' . $kategori['nama_kategori'] .
    ' sebanyak ' . number_format($berat, 2, ',', '.') . ' Kg.'
);


// ===================================
// KIRIM NOTIFIKASI KE ADMIN & PETUGAS
// ===================================

try {

    // Ambil seluruh akun admin dan petugas
    $query_penerima = mysqli_query(
        $conn,
        "SELECT id
         FROM users
         WHERE role IN ('admin', 'petugas')"
    );

    $nama_warga = $_SESSION['nama'] ?? 'Warga';

    $judul_notifikasi = 'Setoran Sampah Baru';

    $pesan_notifikasi =
        $nama_warga .
        ' mengajukan setoran sampah baru dengan nomor #' .
        $setoran_id .
        '. Kategori: ' . $kategori['nama_kategori'] .
        ', berat: ' . number_format($berat, 2, ',', '.') .
        ' Kg. Silakan periksa dan lakukan verifikasi.';

    while ($penerima = mysqli_fetch_assoc($query_penerima)) {

       $berhasil_notifikasi = kirimNotifikasi(
        $conn,
        (int) $penerima['id'],
        $judul_notifikasi,
        $pesan_notifikasi,
        'informasi',
        'detail_setoran.php?id=' . $setoran_id
    );

        if (!$berhasil_notifikasi) {
            error_log(
                "Notifikasi setoran #$setoran_id gagal dikirim ke user ID " .
                $penerima['id']
            );
        }
    }

} catch (Throwable $e) {

    // Kegagalan notifikasi tidak membatalkan pengajuan setoran
    error_log(
        "Pengiriman notifikasi setoran #$setoran_id gagal: " .
        $e->getMessage()
    );
}


// Perbarui token keamanan

                            $_SESSION['csrf_token'] = bin2hex(
                                random_bytes(32)
                            );

                            // Kembali ke halaman dengan notifikasi berhasil
                            header(
                                "Location: ajukan_setoran.php?status=berhasil"
                            );

                            exit;
                        }
                    }

                } catch (mysqli_sql_exception $e) {

                    error_log(
                        "Pengajuan setoran gagal: " . $e->getMessage()
                    );

                    $error = "Pengajuan setoran gagal disimpan. Silakan coba kembali.";
                }
            }
        }
    }
}

// Ambil kategori sampah yang aktif
$kategori_query = mysqli_query(
    $conn,
    "SELECT id, nama_kategori, harga_per_kg
     FROM kategori_sampah
     WHERE status = 'aktif'
     ORDER BY nama_kategori ASC"
);
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ajukan Setoran - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

<div class="dashboard-warga">

    <main class="warga-content">

        <div class="warga-heading">

            <h1>Ajukan Setoran Sampah</h1>

            <p>
                Masukkan informasi sampah yang ingin kamu setorkan.
            </p>

        </div>

        <a href="dashboard.php">
            ← Kembali ke Dashboard
        </a>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'berhasil') : ?>

            <div class="form-alert-success">
                Pengajuan setoran berhasil dikirim.
                Silakan tunggu verifikasi admin.
            </div>

        <?php endif; ?>

        <?php if ($error != "") : ?>

            <div class="form-alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>

        <div class="petugas-form-container">

            <div class="petugas-form-heading">

                <h2>Formulir Setoran</h2>

                <p>
                    Lengkapi informasi berikut dengan benar.
                </p>

            </div>

            <?php if (mysqli_num_rows($kategori_query) > 0) : ?>

                <form method="POST" action="">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
                    >

                    <div class="petugas-form-grid">

                        <!-- KATEGORI SAMPAH -->

                        <div class="petugas-form-group full-width">

                            <label>
                                Kategori Sampah <span>*</span>
                            </label>

                            <select
                                name="kategori_id"
                                id="kategori_id"
                                required
                            >

                                <option value="">
                                    Pilih kategori sampah
                                </option>

                                <?php while ($kategori = mysqli_fetch_assoc($kategori_query)) : ?>

                                    <option
                                        value="<?php echo (int)$kategori['id']; ?>"
                                        data-harga="<?php echo htmlspecialchars((string)$kategori['harga_per_kg']); ?>"
                                        <?php echo (string)$kategori_id === (string)$kategori['id'] ? 'selected' : ''; ?>
                                    >

                                        <?php echo htmlspecialchars($kategori['nama_kategori']); ?>

                                    </option>

                                <?php endwhile; ?>

                            </select>

                        </div>

                        <!-- BERAT SAMPAH -->

                        <div class="petugas-form-group">

                            <label>
                                Berat Sampah (Kg) <span>*</span>
                            </label>

                            <input
                                type="number"
                                name="berat_kg"
                                id="berat_kg"
                                placeholder="Contoh: 5"
                                min="0.01"
                                max="99999999.99"
                                step="0.01"
                                value="<?php echo htmlspecialchars($berat_kg); ?>"
                                required
                            >

                        </div>

                        <!-- TANGGAL SETORAN -->

                        <div class="petugas-form-group">

                            <label>
                                Tanggal Setoran <span>*</span>
                            </label>

                            <input
                                type="text"
                                name="tanggal_setor"
                                id="tanggal_setor"
                                placeholder="DD/MM/YYYY"
                                value="<?php echo htmlspecialchars($tanggal_input); ?>"
                                pattern="\d{2}/\d{2}/\d{4}"
                                title="Gunakan format tanggal DD/MM/YYYY"
                                maxlength="10"
                                inputmode="numeric"
                                required
                            >

                            <small>
                                Format tanggal: Hari/Bulan/Tahun
                            </small>

                        </div>

                        <!-- CATATAN -->

                        <div class="petugas-form-group full-width">

                            <label>
                                Catatan Tambahan
                            </label>

                            <textarea
                                name="catatan"
                                rows="4"
                                maxlength="5000"
                                placeholder="Contoh: Sampah sudah dipilah dan dibersihkan."
                            ><?php echo htmlspecialchars($catatan); ?></textarea>

                        </div>

                    </div>

                    <!-- ESTIMASI HARGA -->

                    <div class="estimasi-setoran">

                        <span>Harga per Kg</span>

                        <strong id="harga_tampil">
                            Rp0
                        </strong>

                        <span>Estimasi Total Setoran</span>

                        <strong id="total_tampil">
                            Rp0
                        </strong>

                    </div>

                    <!-- TOMBOL -->

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
                            Kirim Pengajuan
                        </button>

                    </div>

                </form>

            <?php else : ?>

                <div class="form-alert-error">

                    Belum tersedia kategori sampah aktif.
                    Silakan hubungi admin.

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

<!-- JAVASCRIPT ESTIMASI HARGA -->

<script>

const kategoriSelect = document.getElementById('kategori_id');

const beratInput = document.getElementById('berat_kg');

const hargaTampil = document.getElementById('harga_tampil');

const totalTampil = document.getElementById('total_tampil');

function hitungEstimasi() {

    const pilihan = kategoriSelect.options[
        kategoriSelect.selectedIndex
    ];

    const harga = parseFloat(pilihan.dataset.harga) || 0;

    const berat = parseFloat(beratInput.value) || 0;

    const total = harga * berat;

    const formatRupiah = angka => {

        return 'Rp' + angka.toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });

    };

    hargaTampil.textContent = formatRupiah(harga);

    totalTampil.textContent = formatRupiah(total);

}

kategoriSelect.addEventListener(
    'change',
    hitungEstimasi
);

beratInput.addEventListener(
    'input',
    hitungEstimasi
);

hitungEstimasi();

</script>

</body>
</html>
