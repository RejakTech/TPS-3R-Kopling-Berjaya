
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$user_id = (int) $_SESSION['user_id'];
$penarikan_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
// Ambil seluruh pengajuan penarikan milik warga
$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM penarikan_tabungan
     WHERE user_id = ?
     ORDER BY id DESC"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data_penarikan = [];

$total_pengajuan = 0;
$total_menunggu = 0;
$total_disetujui = 0;
$total_ditolak = 0;
$total_nominal_disetujui = 0;

while ($row = mysqli_fetch_assoc($result)) {

    $data_penarikan[] = $row;

    $total_pengajuan++;

    if ($row['status'] == 'menunggu') {
        $total_menunggu++;
    }

    elseif ($row['status'] == 'disetujui') {
        $total_disetujui++;
        $total_nominal_disetujui += (float) $row['jumlah'];
    }

    elseif ($row['status'] == 'ditolak') {
        $total_ditolak++;
    }
}

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Penarikan - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

<div class="dashboard-warga">

    <main class="warga-content">

        <!-- HEADER -->

        <div class="warga-heading">

            <h1>Riwayat Penarikan</h1>

            <p>
                Pantau status pengajuan penarikan saldo tabungan kamu.
            </p>

        </div>

        <a href="dashboard.php">
            ← Kembali ke Dashboard
        </a>

        <!-- NOTIFIKASI -->

        <?php if (isset($_GET['status']) && $_GET['status'] == 'berhasil') : ?>

            <div class="form-alert-success">
                Pengajuan penarikan berhasil dikirim.
                Silakan tunggu persetujuan admin.
            </div>

        <?php endif; ?>

        <!-- RINGKASAN -->

        <div class="warga-cards">

            <div class="warga-card">

                <h3>Total Pengajuan</h3>

                <p><?php echo $total_pengajuan; ?></p>

            </div>

            <div class="warga-card">

                <h3>Menunggu</h3>

                <p><?php echo $total_menunggu; ?></p>

            </div>

            <div class="warga-card">

                <h3>Disetujui</h3>

                <p><?php echo $total_disetujui; ?></p>

            </div>

            <div class="warga-card">

                <h3>Ditolak</h3>

                <p><?php echo $total_ditolak; ?></p>

            </div>

        </div>

        <!-- TOTAL PENARIKAN -->

        <div class="tabungan-saldo-card">

            <span>Total Penarikan Disetujui</span>

            <h2>
                Rp <?php echo number_format(
                    $total_nominal_disetujui,
                    0,
                    ',',
                    '.'
                ); ?>
            </h2>

            <p>
                Total nominal dari seluruh pengajuan yang telah disetujui.
            </p>

        </div>

        <!-- TABEL RIWAYAT -->

        <section class="riwayat-penarikan-section">

            <div class="riwayat-penarikan-heading">

                <h2>Daftar Pengajuan Penarikan</h2>

                <a
                    href="ajukan_penarikan.php"
                    class="btn-ajukan-riwayat"
                >
                    + Ajukan Penarikan
                </a>

            </div>

            <div class="warga-table-container">

                <table class="warga-table">

                    <thead>

                        <tr>
                            <th>No</th>
                            <th>Tanggal Pengajuan</th>
                            <th>Jumlah</th>
                            <th>Metode</th>
                            <th>Informasi Rekening</th>
                            <th>Status</th>
                            <th>Catatan</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($data_penarikan) > 0) : ?>

                            <?php foreach ($data_penarikan as $index => $penarikan) : ?>

                                 <tr class="<?php echo $penarikan_id === (int) $penarikan['id'] ? 'penarikan-highlight' : ''; ?>"></tr>

                                    <td>
                                        <?php echo $index + 1; ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo date(
                                            'd/m/Y H:i',
                                            strtotime($penarikan['tanggal_pengajuan'])
                                        );
                                        ?>
                                    </td>

                                    <td>

                                        <strong>
                                            Rp <?php echo number_format(
                                                (float) $penarikan['jumlah'],
                                                0,
                                                ',',
                                                '.'
                                            ); ?>
                                        </strong>

                                    </td>

                                    <td>
                                        <?php echo $penarikan['metode_penarikan'] == 'tunai'
                                            ? 'Tunai'
                                            : 'Transfer'; ?>
                                    </td>

                                    <td>

                                        <?php if ($penarikan['metode_penarikan'] == 'transfer') : ?>

                                            <?php echo htmlspecialchars(
                                                $penarikan['nama_pemilik_rekening'] ?? '-'
                                            ); ?>

                                            <br>

                                            <small>
                                                <?php echo htmlspecialchars(
                                                    $penarikan['nomor_rekening'] ?? '-'
                                                ); ?>
                                            </small>

                                        <?php else : ?>

                                            -

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?php if ($penarikan['status'] == 'menunggu') : ?>

                                            <span class="status-penarikan status-pending">
                                                Menunggu
                                            </span>

                                        <?php elseif ($penarikan['status'] == 'disetujui') : ?>

                                            <span class="status-penarikan status-approved">
                                                Disetujui
                                            </span>

                                        <?php elseif ($penarikan['status'] == 'ditolak') : ?>

                                            <span class="status-penarikan status-rejected">
                                                Ditolak
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars(
                                            $penarikan['catatan'] ?: '-'
                                        ); ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else : ?>

                            <tr>

                                <td colspan="7" style="text-align:center;">
                                    Belum ada pengajuan penarikan tabungan.
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
    const penarikanHighlight = document.querySelector('.penarikan-highlight');

    if (penarikanHighlight) {
        setTimeout(function () {
            penarikanHighlight.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }, 300);
    }
});
</script>

</body>

</html>
