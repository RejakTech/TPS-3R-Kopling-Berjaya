
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$user_id = (int) $_SESSION['user_id'];

// Ambil rekening tabungan warga
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, saldo
     FROM tabungan_warga
     WHERE user_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result_tabungan = mysqli_stmt_get_result($stmt);
$tabungan = mysqli_fetch_assoc($result_tabungan);

mysqli_stmt_close($stmt);

$saldo = 0;
$total_masuk = 0;
$total_keluar = 0;
$data_mutasi = [];

if ($tabungan) {

    $tabungan_id = (int) $tabungan['id'];
    $saldo = (float) $tabungan['saldo'];

    // Hitung total transaksi masuk dan keluar
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            COALESCE(SUM(CASE
                WHEN jenis_transaksi = 'masuk' THEN jumlah
                ELSE 0
            END), 0) AS total_masuk,

            COALESCE(SUM(CASE
                WHEN jenis_transaksi = 'keluar' THEN jumlah
                ELSE 0
            END), 0) AS total_keluar

         FROM mutasi_tabungan
         WHERE tabungan_id = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $tabungan_id);
    mysqli_stmt_execute($stmt);

    $result_ringkasan = mysqli_stmt_get_result($stmt);
    $ringkasan = mysqli_fetch_assoc($result_ringkasan);

    $total_masuk = (float) $ringkasan['total_masuk'];
    $total_keluar = (float) $ringkasan['total_keluar'];

    mysqli_stmt_close($stmt);

    // Ambil riwayat transaksi
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            id,
            setoran_id,
            jenis_transaksi,
            jumlah,
            keterangan,
            tanggal_transaksi
         FROM mutasi_tabungan
         WHERE tabungan_id = ?
         ORDER BY id DESC"
    );

    mysqli_stmt_bind_param($stmt, "i", $tabungan_id);
    mysqli_stmt_execute($stmt);

    $result_mutasi = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result_mutasi)) {
        $data_mutasi[] = $row;
    }

    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Tabungan - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

<div class="dashboard-warga">

    <main class="warga-content">

        <div class="warga-heading">

            <h1>Riwayat Tabungan</h1>

            <p>
                Pantau saldo dan seluruh aktivitas transaksi tabunganmu.
            </p>

        </div>

        <a href="dashboard.php">
            ← Kembali ke Dashboard
        </a>

        <!-- SALDO SAAT INI -->

        <section class="tabungan-saldo-card">

            <span>Saldo Tabungan Saat Ini</span>

            <h2>
                Rp <?php echo number_format($saldo, 0, ',', '.'); ?>
            </h2>

            <p>Saldo rekening Bank Sampah Digital</p>

        </section>

        <!-- RINGKASAN TRANSAKSI -->

        <div class="warga-cards">

            <div class="warga-card">

                <h3>Total Uang Masuk</h3>

                <p>
                    Rp <?php echo number_format($total_masuk, 0, ',', '.'); ?>
                </p>

            </div>

            <div class="warga-card">

                <h3>Total Uang Keluar</h3>

                <p>
                    Rp <?php echo number_format($total_keluar, 0, ',', '.'); ?>
                </p>

            </div>

        </div>

        <!-- DAFTAR TRANSAKSI -->

        <section class="riwayat-tabungan-section">

            <div class="riwayat-tabungan-heading">

                <h2>Riwayat Transaksi</h2>

            </div>

            <div class="warga-table-container">

                <table class="warga-table">

                    <thead>

                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Jenis Transaksi</th>
                            <th>Keterangan</th>
                            <th>Jumlah</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($data_mutasi) > 0) : ?>

                            <?php foreach ($data_mutasi as $index => $mutasi) : ?>

                                <tr>

                                    <td>
                                        <?php echo $index + 1; ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo date(
                                            'd/m/Y H:i',
                                            strtotime($mutasi['tanggal_transaksi'])
                                        );
                                        ?>
                                    </td>

                                    <td>

                                        <?php if ($mutasi['jenis_transaksi'] == 'masuk') : ?>

                                            <span class="mutasi-masuk">
                                                Uang Masuk
                                            </span>

                                        <?php else : ?>

                                            <span class="mutasi-keluar">
                                                Uang Keluar
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars(
                                            $mutasi['keterangan'] ?: '-'
                                        ); ?>
                                    </td>

                                    <td>

                                        <strong class="<?php
                                            echo $mutasi['jenis_transaksi'] == 'masuk'
                                                ? 'nominal-masuk'
                                                : 'nominal-keluar';
                                        ?>">

                                            <?php
                                            echo $mutasi['jenis_transaksi'] == 'masuk'
                                                ? '+ Rp '
                                                : '- Rp ';
                                            ?>

                                            <?php echo number_format(
                                                (float) $mutasi['jumlah'],
                                                0,
                                                ',',
                                                '.'
                                            ); ?>

                                        </strong>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else : ?>

                            <tr>

                                <td colspan="5" style="text-align:center;">
                                    Belum ada transaksi tabungan.
                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

</body>

</html>
