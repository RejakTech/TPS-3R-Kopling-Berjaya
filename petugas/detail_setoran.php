
<?php
session_start();

// =============================
// VALIDASI AKSES PETUGAS
// =============================

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

// =============================
// TOKEN KEAMANAN
// =============================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =============================
// AMBIL ID SETORAN
// =============================

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: setoran.php");
    exit;
}

// =============================
// AMBIL DETAIL SETORAN
// =============================

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        s.*,
        u.nama_lengkap,
        u.username,
        u.email,
        u.no_hp,
        u.alamat,
        k.nama_kategori
     FROM setoran_sampah s
     LEFT JOIN users u ON s.user_id = u.id
     LEFT JOIN kategori_sampah k ON s.kategori_id = k.id
     WHERE s.id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$setoran = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

// Jika data tidak ditemukan
if (!$setoran) {
    header("Location: setoran.php");
    exit;
}

// =============================
// FORMAT STATUS
// =============================

$status = $setoran['status'];

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Setoran - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

<div class="admin-dashboard">

    <!-- NAVBAR -->

    <header class="admin-navbar">

        <h2>♻ Bank Sampah Digital</h2>

        <div class="admin-profile">

            Halo, <?php echo htmlspecialchars($_SESSION['nama']); ?>!

            <a href="../auth/logout.php">Logout</a>

        </div>

    </header>


    <main class="admin-content">

        <!-- HEADER -->

        <div class="admin-heading">

            <h1>Detail Setoran Sampah</h1>

            <p>
                Periksa informasi pengajuan sebelum melakukan verifikasi.
            </p>

        </div>


        <!-- TOMBOL KEMBALI -->

        <div style="margin-bottom: 25px;">

            <a href="setoran.php" class="btn-edit-petugas">
                ← Kembali ke Data Setoran
            </a>

        </div>


        <!-- INFORMASI SETORAN -->

        <section class="petugas-detail-container">

            <div class="petugas-detail-heading">

                <div>

                    <h2>Informasi Setoran #<?php echo (int)$setoran['id']; ?></h2>

                    <p>
                        Detail pengajuan setoran sampah warga.
                    </p>

                </div>


                <div>

                    <?php if ($status === 'menunggu') : ?>

                        <span class="petugas-status menunggu">
                            Menunggu Verifikasi
                        </span>

                    <?php elseif ($status === 'diterima') : ?>

                        <span class="petugas-status diterima">
                            Diterima
                        </span>

                    <?php elseif ($status === 'ditolak') : ?>

                        <span class="petugas-status ditolak">
                            Ditolak
                        </span>

                    <?php endif; ?>

                </div>

            </div>


            <!-- DATA WARGA -->

            <div class="petugas-detail-section">

                <h3>Informasi Warga</h3>

                <div class="petugas-detail-grid">

                    <div class="petugas-detail-item">

                        <span>Nama Lengkap</span>

                        <strong>
                            htmlspecialchars($setoran['nama_lengkap'] ?? '-', ENT_QUOTES, 'UTF-8')
                        </strong>

                    </div>


                    <div class="petugas-detail-item">

                        <span>Username</span>

                        <strong>
                            <?php echo htmlspecialchars($setoran['username'] ?? '-'); ?>
                        </strong>

                    </div>


                    <div class="petugas-detail-item">

                        <span>Email</span>

                        <strong>
                            <?php echo htmlspecialchars($setoran['email'] ?? '-'); ?>
                        </strong>

                    </div>


                    <div class="petugas-detail-item">

                        <span>Nomor HP</span>

                        <strong>
                            <?php echo htmlspecialchars($setoran['no_hp'] ?? '-'); ?>
                        </strong>

                    </div>


                    <div class="petugas-detail-item petugas-detail-full">

                        <span>Alamat</span>

                        <strong>
                            <?php echo htmlspecialchars($setoran['alamat'] ?? '-'); ?>
                        </strong>

                    </div>

                </div>

            </div>


            <!-- DATA SETORAN -->

            <div class="petugas-detail-section">

                <h3>Informasi Sampah</h3>

                <div class="petugas-detail-grid">

                    <div class="petugas-detail-item">

                        <span>Kategori Sampah</span>

                        <strong>
                            <?php echo htmlspecialchars($setoran['nama_kategori'] ?? '-'); ?>
                        </strong>

                    </div>


                    <div class="petugas-detail-item">

                        <span>Tanggal Setor</span>

                        <strong>
                            <?php
                            $tanggal_setor = $setoran['tanggal_setor'] ?? '';

                            if (
                                $tanggal_setor !== '' &&
                                strtotime($tanggal_setor) !== false
                            ) {
                                echo date('d/m/Y', strtotime($tanggal_setor));
                            } else {
                                echo '-';
                            }
                            ?>
                        </strong>

                    </div>


                    <div class="petugas-detail-item">

                        <span>Berat Sampah</span>

                        <strong>
                            <?php echo number_format((float)$setoran['berat_kg'], 2, ',', '.'); ?> Kg
                        </strong>

                    </div>


                    <div class="petugas-detail-item">

                        <span>Harga per Kg</span>

                        <strong>
                            Rp <?php echo number_format((float)$setoran['harga_per_kg'], 0, ',', '.'); ?>
                        </strong>

                    </div>


                    <div class="petugas-detail-item">

                        <span>Total Nilai Setoran</span>

                        <strong class="petugas-detail-total">
                            Rp <?php echo number_format((float)$setoran['total_harga'], 0, ',', '.'); ?>
                        </strong>

                    </div>


                    <div class="petugas-detail-item">

                        <span>Status Setoran</span>

                        <strong>
                            <?php echo htmlspecialchars(ucfirst($status)); ?>
                        </strong>

                    </div>


                    <div class="petugas-detail-item petugas-detail-full">

                        <span>Catatan Warga</span>

                        <strong>
                            <?php echo !empty($setoran['catatan'])
                                ? nl2br(htmlspecialchars($setoran['catatan']))
                                : 'Tidak ada catatan.'; ?>
                        </strong>

                    </div>

                </div>

            </div>


            <!-- AKSI VERIFIKASI -->

            <?php if ($status === 'menunggu') : ?>

                <div class="petugas-verifikasi-section">

                    <h3>Verifikasi Pengajuan</h3>

                    <p>
                        Pastikan data setoran sudah diperiksa sebelum menentukan keputusan.
                    </p>


                    <div class="petugas-verifikasi-actions">

                        <!-- FORM TERIMA -->

                        <form
                            action="../admin/proses_verifikasi_setoran.php"
                            method="POST"
                            onsubmit="return confirm('Apakah Anda yakin ingin menerima setoran ini? Saldo tabungan warga akan bertambah.');"
                        >

                            <input
                                type="hidden"
                                name="id"
                                value="<?php echo (int)$setoran['id']; ?>"
                            >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
                            >

                            <input
                                type="hidden"
                                name="aksi"
                                value="terima"
                            >

                            <button type="submit" class="btn-terima-setoran">
                                ✓ Terima Setoran
                            </button>

                        </form>


                        
<!-- FORM TOLAK -->

<form
    action="../admin/proses_verifikasi_setoran.php"
    method="POST"
    onsubmit="return confirm('Apakah Anda yakin ingin menolak setoran ini?');"
>

    <input
        type="hidden"
        name="id"
        value="<?php echo (int)$setoran['id']; ?>"
    >

    <input
        type="hidden"
        name="csrf_token"
        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
    >

    <input
        type="hidden"
        name="aksi"
        value="tolak"
    >

    <div class="petugas-alasan-group">

        <label for="alasan_penolakan">
            Alasan Penolakan <span>*</span>
        </label>

        <textarea
            name="alasan_penolakan"
            id="alasan_penolakan"
            rows="3"
            maxlength="1000"
            placeholder="Contoh: Sampah masih tercampur dengan bahan lain..."
            required
        ></textarea>

        <small>
            Berikan alasan yang jelas agar warga mengetahui penyebab penolakan.
        </small>

    </div>

    <button type="submit" class="btn-tolak-setoran">
        ✕ Tolak Setoran
    </button>

</form>


                    </div>

                </div>

            <?php else : ?>

                <div class="petugas-verifikasi-selesai">

                    <strong>Setoran Sudah Diverifikasi</strong>

                    <p>
                        Pengajuan ini telah memiliki status
                        <strong><?php echo htmlspecialchars($status); ?></strong>
                        dan tidak dapat diproses kembali.
                    </p>

                </div>

            <?php endif; ?>

            
<?php if ($status === 'ditolak' && !empty($setoran['alasan_penolakan'])) : ?>

    <div class="petugas-alasan-ditolak">

        <div class="petugas-alasan-ditolak-heading">
            <span class="petugas-alasan-icon">!</span>

            <div>
                <h4>Alasan Penolakan</h4>
                <p>Catatan dari petugas mengenai pengajuan ini.</p>
            </div>
        </div>

        <div class="petugas-alasan-ditolak-content">
            <?php echo nl2br(htmlspecialchars($setoran['alasan_penolakan'])); ?>
        </div>

    </div>

<?php endif; ?>


        </section>

    </main>

</div>

</body>

</html>
