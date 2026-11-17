
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

// Ambil ID warga yang sedang login
$user_id = (int) $_SESSION['user_id'];
$setoran_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
// Ambil riwayat setoran milik warga
$query = mysqli_prepare(
    $conn,
    "SELECT
        s.*,
        k.nama_kategori
    FROM setoran_sampah s
    INNER JOIN kategori_sampah k
        ON s.kategori_id = k.id
    WHERE s.user_id = ?
    ORDER BY s.id DESC"
);

mysqli_stmt_bind_param($query, "i", $user_id);
mysqli_stmt_execute($query);

$result = mysqli_stmt_get_result($query);

// Hitung ringkasan
$total_setoran = 0;
$total_berat = 0;
$total_diterima = 0;

$data_setoran = [];

while ($row = mysqli_fetch_assoc($result)) {

    $data_setoran[] = $row;

    $total_setoran++;
    $total_berat += (float) $row['berat_kg'];

    if ($row['status'] == 'diterima') {
        $total_diterima++;
    }
}

mysqli_stmt_close($query);
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Setoran - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
    /* Highlight setoran yang dibuka dari notifikasi */
    .setoran-highlight {
        background: #eefaf7 !important;
        box-shadow: inset 4px 0 0 #0f766e;
    }

    .setoran-highlight td {
        font-weight: 600;
    }

    /* Animasi agar highlight lebih terlihat */
    .setoran-highlight {
        animation: highlightSetoran 1.5s ease-in-out;
    }

    @keyframes highlightSetoran {
        0% {
            background: #bcefe4 !important;
        }

        100% {
            background: #eefaf7 !important;
        }
    }
</style>

<style>
    /* Highlight setoran yang dibuka dari notifikasi */
    .setoran-highlight {
        background: #eefaf7 !important;
        box-shadow: inset 4px 0 0 #0f766e;
    }

    .setoran-highlight td {
        font-weight: 600;
    }
</style>

</head>

<body>

<div class="dashboard-warga">

    <main class="warga-content">

        <div class="warga-heading">

            <h1>Riwayat Setoran</h1>

            <p>
                Pantau seluruh pengajuan setoran sampah yang telah kamu lakukan.
            </p>

        </div>

        <a href="dashboard.php">
            ← Kembali ke Dashboard
        </a>

        <!-- RINGKASAN -->

        <div class="warga-cards">

            <div class="warga-card">

                <h3>Total Pengajuan</h3>

                <p><?php echo $total_setoran; ?></p>

            </div>

            <div class="warga-card">

                <h3>Total Berat Sampah</h3>

                <p>
                    <?php echo number_format($total_berat, 2, ',', '.'); ?> Kg
                </p>

            </div>

            <div class="warga-card">

                <h3>Setoran Diterima</h3>

                <p><?php echo $total_diterima; ?></p>

            </div>

        </div>

        <!-- TABEL RIWAYAT -->

        <section class="riwayat-setoran-section">

            <div class="riwayat-setoran-heading">

                <h2>Daftar Pengajuan Setoran</h2>

                <a href="ajukan_setoran.php" class="btn-ajukan-riwayat">
                    + Ajukan Setoran
                </a>

            </div>

            <div class="warga-table-container">

                <table class="warga-table">

                    <thead>

                        <tr>
                            <th>No</th>
                            <th>Kategori Sampah</th>
                            <th>Tanggal Setor</th>
                            <th>Berat</th>
                            <th>Harga/Kg</th>
                            <th>Total Harga</th>
                            <th>Status</th>
                            <th>Catatan Warga</th>
                            <th>Alasan Penolakan</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($data_setoran) > 0) : ?>

                            <?php foreach ($data_setoran as $index => $setoran) : ?>

                                <tr class="<?php echo $setoran_id === (int) $setoran['id'] ? 'setoran-highlight' : ''; ?>">

                                    <td><?php echo $index + 1; ?></td>

                                    <td>
                                        <?php echo htmlspecialchars($setoran['nama_kategori']); ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo date(
                                            'd/m/Y',
                                            strtotime($setoran['tanggal_setor'])
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo number_format(
                                            (float) $setoran['berat_kg'],
                                            2,
                                            ',',
                                            '.'
                                        ); ?> Kg
                                    </td>

                                    <td>
                                        Rp <?php echo number_format(
                                            (float) $setoran['harga_per_kg'],
                                            0,
                                            ',',
                                            '.'
                                        ); ?>
                                    </td>

                                    <td>
                                        Rp <?php echo number_format(
                                            (float) $setoran['total_harga'],
                                            0,
                                            ',',
                                            '.'
                                        ); ?>
                                    </td>

                                    <td>

                                        <?php if ($setoran['status'] == 'menunggu') : ?>

                                            <span class="status-setoran status-menunggu">
                                                Menunggu
                                            </span>

                                        <?php elseif ($setoran['status'] == 'diterima') : ?>

                                            <span class="status-setoran status-diterima">
                                                Diterima
                                            </span>

                                        <?php elseif ($setoran['status'] == 'ditolak') : ?>

                                            <span class="status-setoran status-ditolak">
                                                Ditolak
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    
<td>
    <?php echo htmlspecialchars(
        $setoran['catatan'] ?: '-'
    ); ?>
</td>

<td>
    <?php if (
        $setoran['status'] === 'ditolak' &&
        !empty($setoran['alasan_penolakan'])
    ) : ?>

        <div class="warga-alasan-penolakan">
            <strong>!</strong>

            <span>
                <?php echo htmlspecialchars($setoran['alasan_penolakan']); ?>
            </span>
        </div>

    <?php elseif ($setoran['status'] === 'ditolak') : ?>

        <span class="warga-alasan-kosong">
            Tidak ada alasan
        </span>

    <?php else : ?>

        <span style="color: #a0aaa7;">-</span>

    <?php endif; ?>
</td>


                                </tr>

                            <?php endforeach; ?>

                        <?php else : ?>

                            <tr>

                                <td colspan="9" style="text-align:center;">
                                    Belum ada riwayat setoran sampah.
                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const setoranHighlight = document.querySelector('.setoran-highlight');

    if (setoranHighlight) {
        setTimeout(function () {
            setoranHighlight.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }, 300);
    }
});
</script>

</body>

</html>
