
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

// Token keamanan
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil ID setoran
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: setoran.php");
    exit;
}

// Ambil detail setoran
$query = mysqli_prepare(
    $conn,
    "SELECT
        s.*,
        u.nama_lengkap,
        u.username,
        k.nama_kategori
     FROM setoran_sampah s
     INNER JOIN users u ON s.user_id = u.id
     INNER JOIN kategori_sampah k ON s.kategori_id = k.id
     WHERE s.id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($query, "i", $id);
mysqli_stmt_execute($query);

$result = mysqli_stmt_get_result($query);
$setoran = mysqli_fetch_assoc($result);

mysqli_stmt_close($query);

if (!$setoran) {
    header("Location: setoran.php");
    exit;
}
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

<div class="admin-content">

    <div class="admin-heading">
        <h1>Detail Setoran Sampah</h1>
        <p>Periksa informasi setoran warga sebelum melakukan verifikasi.</p>
    </div>

    <div class="petugas-form-container">

        <div class="petugas-form-heading">
            <h2>Informasi Setoran #<?php echo (int) $setoran['id']; ?></h2>
            <p>Detail data pengajuan setoran sampah warga.</p>
        </div>

        <div class="detail-setoran-grid">

            <div class="detail-setoran-item">
                <span>Nama Warga</span>
                <strong><?php echo htmlspecialchars($setoran['nama_lengkap']); ?></strong>
            </div>

            <div class="detail-setoran-item">
                <span>Username</span>
                <strong><?php echo htmlspecialchars($setoran['username']); ?></strong>
            </div>

            <div class="detail-setoran-item">
                <span>Kategori Sampah</span>
                <strong><?php echo htmlspecialchars($setoran['nama_kategori']); ?></strong>
            </div>

            <div class="detail-setoran-item">
                <span>Tanggal Setor</span>
                <strong><?php echo date('d-m-Y', strtotime($setoran['tanggal_setor'])); ?></strong>
            </div>

            <div class="detail-setoran-item">
                <span>Berat Sampah</span>
                <strong><?php echo number_format((float)$setoran['berat_kg'], 2, ',', '.'); ?> Kg</strong>
            </div>

            <div class="detail-setoran-item">
                <span>Harga per Kg</span>
                <strong>Rp <?php echo number_format((float)$setoran['harga_per_kg'], 0, ',', '.'); ?></strong>
            </div>

            <div class="detail-setoran-item detail-setoran-total">
                <span>Total Nilai Setoran</span>
                <strong>
                    Rp <?php echo number_format((float)$setoran['total_harga'], 0, ',', '.'); ?>
                </strong>
            </div>

            <div class="detail-setoran-item">
                <span>Status Setoran</span>
                <strong><?php echo htmlspecialchars(ucfirst($setoran['status'])); ?></strong>
            </div>

            <div class="detail-setoran-item detail-setoran-note">
                <span>Catatan Warga</span>
                <strong><?php echo htmlspecialchars($setoran['catatan'] ?: '-'); ?></strong>
            </div>

        </div>

        
        <?php if (
            $setoran['status'] === 'ditolak' &&
            !empty($setoran['alasan_penolakan'])
        ) : ?>

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


        <?php if ($setoran['status'] === 'menunggu') : ?>

            <div class="detail-setoran-actions">

                <form action="proses_verifikasi_setoran.php" method="POST"
                    onsubmit="return confirm('Apakah Anda yakin ingin menerima setoran ini? Saldo warga akan bertambah.');">

                    <input type="hidden" name="id"
                        value="<?php echo (int)$setoran['id']; ?>">

                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <input type="hidden" name="aksi" value="terima">

                    <button type="submit" class="btn-terima-setoran">
                        Terima Setoran
                    </button>

                </form>

                
<!-- FORM TOLAK -->

<form
    action="proses_verifikasi_setoran.php"
    method="POST"
    onsubmit="return confirm('Apakah Anda yakin ingin menolak setoran ini?');"
>

    <input type="hidden" name="id"
        value="<?php echo (int)$setoran['id']; ?>">

    <input type="hidden" name="csrf_token"
        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

    <input type="hidden" name="aksi" value="tolak">

    <div class="petugas-alasan-group">

        <label for="alasan_penolakan">
            Alasan Penolakan <span>*</span>
        </label>

        <textarea
            name="alasan_penolakan"
            id="alasan_penolakan"
            rows="3"
            maxlength="1000"
            placeholder="Tuliskan alasan penolakan setoran..."
            required
        ></textarea>

        <small>
            Alasan ini akan ditampilkan kepada warga.
        </small>

    </div>

    <button type="submit" class="btn-tolak-setoran">
        ✕ Tolak Setoran
    </button>

</form>


            </div>

        <?php else : ?>

            <div class="form-alert-success">
                Setoran ini sudah diproses dan tidak dapat diverifikasi kembali.
            </div>

        <?php endif; ?>

        <div class="petugas-form-actions">
            <a href="setoran.php" class="btn-batal-petugas">
                ← Kembali ke Data Setoran
            </a>
        </div>

    </div>

</div>

</body>
</html>
