
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

// Ambil 3 notifikasi terbaru warga
$query_notifikasi_terbaru = mysqli_query(
    $conn,
    "SELECT id, judul, pesan, tipe, dibaca, tanggal_dibuat, link
     FROM notifikasi
     WHERE user_id = " . (int) $_SESSION['user_id'] . "
     ORDER BY tanggal_dibuat DESC, id DESC
     LIMIT 3"
);

$user_id = (int) $_SESSION['user_id'];

// 1. Menghitung notifikasi yang belum dibaca
$stmt_notifikasi = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM notifikasi
     WHERE user_id = ? AND dibaca = 0"
);

mysqli_stmt_bind_param(
    $stmt_notifikasi,
    "i",
    $user_id
);

mysqli_stmt_execute($stmt_notifikasi);

$data_notifikasi = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt_notifikasi)
);

$jumlah_notifikasi = (int) $data_notifikasi['total'];

mysqli_stmt_close($stmt_notifikasi);
// 5. Ambil setoran terbaru
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        s.id,
        s.tanggal_setor,
        s.berat_kg,
        s.total_harga,
        s.status,
        s.alasan_penolakan,
        k.nama_kategori
     FROM setoran_sampah s
     INNER JOIN kategori_sampah k
        ON s.kategori_id = k.id
     WHERE s.user_id = ?
     ORDER BY s.id DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$setoran_terbaru = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

// 2. Ambil saldo tabungan
$stmt = mysqli_prepare(
    $conn,
    "SELECT COALESCE(MAX(saldo), 0) AS saldo
     FROM tabungan_warga
     WHERE user_id = ?"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$data_saldo = mysqli_fetch_assoc($result);

$saldo = (float) $data_saldo['saldo'];

mysqli_stmt_close($stmt);


// 3. Total berat setoran yang diterima
$stmt = mysqli_prepare(
    $conn,
    "SELECT COALESCE(SUM(berat_kg), 0) AS total_berat
     FROM setoran_sampah
     WHERE user_id = ?
     AND status = 'diterima'"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$data_setoran = mysqli_fetch_assoc($result);

$total_berat = (float) $data_setoran['total_berat'];

mysqli_stmt_close($stmt);


// 4. Total transaksi tabungan
$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(m.id) AS total_transaksi
     FROM mutasi_tabungan m
     INNER JOIN tabungan_warga t
        ON m.tabungan_id = t.id
     WHERE t.user_id = ?"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$data_transaksi = mysqli_fetch_assoc($result);

$total_transaksi = (int) $data_transaksi['total_transaksi'];

mysqli_stmt_close($stmt);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Warga - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        /* ========================================
   PROFILE MENU HEADER
======================================== */

.warga-profile-menu {
    display: flex;
    align-items: center;
    gap: 10px;
    white-space: nowrap;
}

.warga-profile-menu > span {
    color: #27423d;
    font-size: 14px;
    font-weight: 500;
}

.btn-profil {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 9px 15px;

    background: #ffffff;
    color: #0f766e;

    border: 1px solid #d7e7e3;
    border-radius: 9px;

    text-decoration: none;

    font-size: 13px;
    font-weight: 600;

    transition: all 0.2s ease;
}

.btn-profil:hover {
    background: #edf7f4;
    border-color: #bcded7;
}

.btn-logout {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 9px 16px;

    background: #0f766e;
    color: #ffffff;

    border: 1px solid #0f766e;
    border-radius: 9px;

    text-decoration: none;

    font-size: 13px;
    font-weight: 600;

    transition: all 0.2s ease;
}

.btn-logout:hover {
    background: #0c625b;
    border-color: #0c625b;
}

/* MOBILE */
@media (max-width: 600px) {

    .warga-profile-menu {
        gap: 6px;
    }

    .warga-profile-menu > span {
        display: none;
    }

    .btn-profil,
    .btn-logout {
        padding: 8px 12px;
        font-size: 12px;
    }
}

        /* Heading Dashboard dan Notifikasi */
        .warga-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .warga-heading-text {
            flex: 1;
        }

        .warga-heading-text h1 {
            margin-bottom: 7px;
        }

        .warga-heading-text p {
            margin: 0;
        }

        /* Ikon Notifikasi */
        .warga-notification-link {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            min-width: 46px;
            border-radius: 12px;
            background: #edf7f3;
            color: #145c52;
            text-decoration: none;
            transition: 0.2s ease;
        }

        .warga-notification-link:hover {
            background: #dcefe8;
            transform: translateY(-2px);
        }

        .warga-notification-icon {
            font-size: 21px;
        }

        .warga-notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            min-width: 19px;
            height: 19px;
            padding: 0 4px;
            border-radius: 20px;
            background: #e53935;
            color: white;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-sizing: content-box;
        }

        @media (max-width: 520px) {
            .warga-heading {
                align-items: flex-start;
                gap: 12px;
            }

            .warga-notification-link {
                width: 42px;
                height: 42px;
                min-width: 42px;
            }
        }

        /* ========================================
   SETORAN TERBARU
======================================== */

.setoran-terbaru-section {
    margin-top: 30px;
}

.setoran-terbaru-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 14px;
}

.setoran-terbaru-heading h2 {
    margin: 0 0 5px;
    color: #174c43;
}

.setoran-terbaru-heading p {
    margin: 0;
    color: #71817c;
    font-size: 13px;
}

.setoran-terbaru-heading a {
    color: #0f766e;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.setoran-terbaru-heading a:hover {
    text-decoration: underline;
}

.setoran-terbaru-card {
    background: #ffffff;
    border: 1px solid #e5eeeb;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.setoran-terbaru-info {
    display: flex;
    align-items: center;
    gap: 14px;
}

.setoran-terbaru-icon {
    width: 46px;
    height: 46px;
    border-radius: 13px;
    background: #e8f5f1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
}

.setoran-terbaru-info h3 {
    margin: 0 0 5px;
    color: #174c43;
    font-size: 15px;
}

.setoran-terbaru-info p {
    margin: 0;
    color: #81908b;
    font-size: 12px;
}

.setoran-terbaru-status {
    text-align: right;
}

.status-dashboard {
    display: inline-block;
    padding: 7px 11px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.status-dashboard-menunggu {
    background: #fff7df;
    color: #a46b00;
}

.status-dashboard-diterima {
    background: #e7f7ef;
    color: #16804f;
}

.status-dashboard-ditolak {
    background: #fdecec;
    color: #c0392b;
}

.setoran-terbaru-status strong {
    display: block;
    margin-top: 6px;
    color: #174c43;
    font-size: 14px;
}

.setoran-terbaru-status small {
    display: block;
    max-width: 250px;
    margin-top: 6px;
    color: #81908b;
    font-size: 11px;
}

.setoran-terbaru-empty {
    background: #ffffff;
    border: 1px dashed #d6e4e0;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.setoran-terbaru-empty > span {
    font-size: 25px;
}

.setoran-terbaru-empty strong {
    color: #174c43;
    font-size: 14px;
}

.setoran-terbaru-empty p {
    margin: 4px 0 0;
    color: #81908b;
    font-size: 12px;
}

.setoran-terbaru-empty a {
    margin-left: auto;
    color: #0f766e;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
}

@media (max-width: 600px) {

    .setoran-terbaru-heading {
        align-items: flex-start;
    }

    .setoran-terbaru-card {
        flex-direction: column;
        align-items: flex-start;
    }

    .setoran-terbaru-status {
        text-align: left;
    }

    .setoran-terbaru-empty {
        flex-wrap: wrap;
    }

    .setoran-terbaru-empty a {
        width: 100%;
        margin-left: 0;
        margin-top: 5px;
    }

}

/* Preview Notifikasi */
.warga-notifikasi-preview {
    background: #ffffff;
    border: 1px solid #e5eeea;
    border-radius: 16px;
    margin-top: 24px;
    overflow: hidden;
}

.warga-notifikasi-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 20px;
    border-bottom: 1px solid #edf1ef;
}

.warga-notifikasi-preview-header h3 {
    margin: 0;
    color: #183b35;
    font-size: 16px;
}

.warga-notifikasi-preview-header a {
    color: #0f766e;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
}

.warga-notifikasi-preview-item {
    display: flex;
    gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid #edf1ef;
}

.warga-notifikasi-preview-item:last-child {
    border-bottom: none;
}

.warga-notifikasi-preview-item.belum-dibaca {
    background: #f5fbf8;
}

.warga-notifikasi-preview-item.belum-dibaca
.warga-notifikasi-preview-title {
    font-weight: 700;
}

.warga-notifikasi-preview-item.belum-dibaca
.warga-notifikasi-preview-icon {
    background: #d8f2e9;
}

.warga-notifikasi-preview-item.belum-dibaca::after {
    content: "";
    width: 7px;
    height: 7px;
    min-width: 7px;
    border-radius: 50%;
    background: #159b83;
    align-self: flex-start;
    margin-top: 8px;
}

.warga-notifikasi-preview-icon {
    width: 36px;
    height: 36px;
    min-width: 36px;
    border-radius: 10px;
    background: #e3f5ee;
    color: #0f766e;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.warga-notifikasi-preview-detail {
    min-width: 0;
    flex: 1;
}

.warga-notifikasi-preview-title {
    color: #183b35;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 4px;
}

.warga-notifikasi-preview-message {
    color: #71817d;
    font-size: 12px;
    line-height: 1.5;
}

.warga-notifikasi-preview-time {
    color: #98a49f;
    font-size: 10px;
    margin-top: 5px;
}

.warga-notifikasi-preview-empty {
    padding: 25px 20px;
    text-align: center;
    color: #899691;
    font-size: 12px;
}



    </style>
</head>

<body>

    <div class="dashboard-warga">

        <header class="warga-navbar">
            <h2>♻ Bank Sampah Digital</h2>

           <div class="warga-profile-menu">
    <span>
        Halo, <?= htmlspecialchars($_SESSION['nama'], ENT_QUOTES, 'UTF-8') ?>!
    </span>

    <a href="profil.php" class="btn-profil">
        Profil
    </a>

    <a href="../auth/logout.php" class="btn-logout">
        Logout
    </a>
</div>
        </header>

        <main class="warga-content">

            <!-- Heading Dashboard -->
            <div class="warga-heading">

                <div class="warga-heading-text">
                    <h1>Dashboard Warga</h1>
                    <p>Selamat datang! Mari bersama menjaga lingkungan tetap bersih.</p>
                </div>

                <!-- Ikon Notifikasi -->
                <a href="notifikasi.php"
                   class="warga-notification-link"
                   title="Lihat Notifikasi"
                   aria-label="Lihat Notifikasi">

                    <span class="warga-notification-icon">🔔</span>

                    <?php if ($jumlah_notifikasi > 0): ?>
                        <span class="warga-notification-badge">
                            <?= $jumlah_notifikasi > 99 ? '99+' : $jumlah_notifikasi ?>
                        </span>
                    <?php endif; ?>

                </a>

            </div>


            <!-- Statistik Warga -->
            <div class="warga-cards">

                <div class="warga-card">
                    <h3>Saldo Tabungan</h3>

                    <p>
                        Rp <?php echo number_format(
                            $saldo,
                            0,
                            ',',
                            '.'
                        ); ?>
                    </p>
                </div>

                <div class="warga-card">
                    <h3>Total Setoran</h3>

                    <p>
                        <?php echo number_format(
                            $total_berat,
                            2,
                            ',',
                            '.'
                        ); ?> Kg
                    </p>
                </div>

                <div class="warga-card">
                    <h3>Transaksi</h3>

                    <p>
                        <?php echo number_format(
                            $total_transaksi,
                            0,
                            ',',
                            '.'
                        ); ?>
                    </p>
                </div>

            </div>


            <div class="warga-notifikasi-preview">

    <div class="warga-notifikasi-preview-header">
        <h3>Notifikasi Terbaru</h3>

        <a href="notifikasi.php">
            Lihat Semua →
        </a>
    </div>

    <?php if (mysqli_num_rows($query_notifikasi_terbaru) > 0): ?>

        <?php while ($notif = mysqli_fetch_assoc($query_notifikasi_terbaru)): ?>

            <div class="warga-notifikasi-preview-item <?= $notif['dibaca'] == 0 ? 'belum-dibaca' : '' ?>">

                <div class="warga-notifikasi-preview-icon">
                    <?php
                    if ($notif['tipe'] === 'sukses') {
                        echo '✓';
                    } elseif ($notif['tipe'] === 'peringatan') {
                        echo '!';
                    } else {
                        echo 'ℹ';
                    }
                    ?>
                </div>

                <div class="warga-notifikasi-preview-detail">

                    <div class="warga-notifikasi-preview-title">
                        <?= htmlspecialchars($notif['judul']) ?>
                    </div>

                    <div class="warga-notifikasi-preview-message">
                        <?= htmlspecialchars($notif['pesan']) ?>
                    </div>

                    <div class="warga-notifikasi-preview-time">
                        <?= date('d M Y, H:i', strtotime($notif['tanggal_dibuat'])) ?>
                    </div>

                </div>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="warga-notifikasi-preview-empty">
            Belum ada notifikasi terbaru.
        </div>

    <?php endif; ?>

</div>

            <!-- Setoran Terbaru -->
<section class="setoran-terbaru-section">

    <div class="setoran-terbaru-heading">
        <div>
            <h2>Setoran Terbaru</h2>
            <p>Informasi pengajuan setoran terakhir kamu.</p>
        </div>

        <a href="riwayat_setoran.php">
            Lihat Semua →
        </a>
    </div>

    <?php if ($setoran_terbaru): ?>

        <div class="setoran-terbaru-card">

            <div class="setoran-terbaru-info">

                <div class="setoran-terbaru-icon">
                    ♻️
                </div>

                <div>
                    <h3>
                        <?php echo htmlspecialchars(
                            $setoran_terbaru['nama_kategori']
                        ); ?>
                    </h3>

                    <p>
                        <?php echo date(
                            'd/m/Y',
                            strtotime($setoran_terbaru['tanggal_setor'])
                        ); ?>

                        ·

                        <?php echo number_format(
                            (float) $setoran_terbaru['berat_kg'],
                            2,
                            ',',
                            '.'
                        ); ?>
                        Kg
                    </p>
                </div>

            </div>


            <div class="setoran-terbaru-status">

                <?php if ($setoran_terbaru['status'] === 'menunggu'): ?>

                    <span class="status-dashboard status-dashboard-menunggu">
                        ● Menunggu Verifikasi
                    </span>

                <?php elseif ($setoran_terbaru['status'] === 'diterima'): ?>

                    <div>
                        <span class="status-dashboard status-dashboard-diterima">
                            ✓ Diterima
                        </span>

                        <strong>
                            Rp <?php echo number_format(
                                (float) $setoran_terbaru['total_harga'],
                                0,
                                ',',
                                '.'
                            ); ?>
                        </strong>
                    </div>

                <?php elseif ($setoran_terbaru['status'] === 'ditolak'): ?>

                    <div>
                        <span class="status-dashboard status-dashboard-ditolak">
                            ✕ Ditolak
                        </span>

                        <?php if (!empty($setoran_terbaru['alasan_penolakan'])): ?>

                            <small>
                                <?php echo htmlspecialchars(
                                    $setoran_terbaru['alasan_penolakan']
                                ); ?>
                            </small>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    <?php else: ?>

        <div class="setoran-terbaru-empty">
            <span>♻️</span>

            <div>
                <strong>Belum ada setoran</strong>

                <p>
                    Kamu belum mengajukan setoran sampah.
                </p>
            </div>

            <a href="ajukan_setoran.php">
                Ajukan Setoran →
            </a>
        </div>

    <?php endif; ?>

</section>

            <!-- Menu Layanan -->
            <section class="warga-menu-section">

                <div class="warga-menu-heading">
                    <h2>Layanan Bank Sampah</h2>
                    <p>Pilih layanan yang ingin kamu gunakan.</p>
                </div>

                <div class="warga-menu-container">

                    <a href="ajukan_setoran.php" class="warga-menu-card">
                        <div class="warga-menu-icon">♻️</div>
                        <h3>Ajukan Setoran</h3>
                        <p>Ajukan penyetoran sampah dan dapatkan saldo tabungan.</p>
                        <span>Mulai Setoran →</span>
                    </a>

                    <a href="riwayat_setoran.php" class="warga-menu-card">
                        <div class="warga-menu-icon">📦</div>
                        <h3>Riwayat Setoran</h3>
                        <p>Lihat daftar pengajuan dan status setoran sampah.</p>
                        <span>Lihat Riwayat →</span>
                    </a>

                    <a href="riwayat_tabungan.php" class="warga-menu-card">
                        <div class="warga-menu-icon">💰</div>
                        <h3>Riwayat Tabungan</h3>
                        <p>Pantau transaksi masuk dan keluar saldo tabungan.</p>
                        <span>Lihat Transaksi →</span>
                    </a>

                    <a href="ajukan_penarikan.php" class="warga-menu-card">
                        <div class="warga-menu-icon">💸</div>
                        <h3>Penarikan Tabungan</h3>
                        <p>Ajukan pencairan saldo tabungan yang kamu miliki.</p>
                        <span>Ajukan Penarikan →</span>
                    </a>

                    <a href="riwayat_penarikan.php" class="warga-menu-card">
                        <div class="warga-menu-icon">📋</div>
                        <h3>Riwayat Penarikan</h3>
                        <p>Lihat status pengajuan penarikan saldo tabungan.</p>
                        <span>Lihat Riwayat →</span>
                    </a>

                </div>

            </section>

        </main>

    </div>

</body>
</html>
