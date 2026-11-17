<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../config/koneksi.php';


// ========================================
// AMBIL ID WARGA
// ========================================

$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($id === false || $id <= 0) {
    header("Location: warga.php");
    exit;
}


// ========================================
// DATA WARGA
// ========================================

$query_warga = mysqli_prepare(
    $conn,
    "SELECT
        id,
        nama_lengkap,
        username,
        email,
        no_hp,
        alamat,
        status
     FROM users
     WHERE id = ?
     AND role = 'warga'
     LIMIT 1"
);

if (!$query_warga) {
    die("Gagal menyiapkan query data warga.");
}

mysqli_stmt_bind_param($query_warga, "i", $id);

if (!mysqli_stmt_execute($query_warga)) {
    mysqli_stmt_close($query_warga);
    die("Gagal mengambil data warga.");
}

$result_warga = mysqli_stmt_get_result($query_warga);

if (!$result_warga || mysqli_num_rows($result_warga) !== 1) {
    mysqli_stmt_close($query_warga);
    header("Location: warga.php");
    exit;
}

$warga = mysqli_fetch_assoc($result_warga);

mysqli_stmt_close($query_warga);


// ========================================
// DATA TABUNGAN
// ========================================

$query_tabungan = mysqli_prepare(
    $conn,
    "SELECT saldo
     FROM tabungan_warga
     WHERE user_id = ?
     LIMIT 1"
);

if (!$query_tabungan) {
    die("Gagal menyiapkan query tabungan.");
}

mysqli_stmt_bind_param($query_tabungan, "i", $id);

if (!mysqli_stmt_execute($query_tabungan)) {
    mysqli_stmt_close($query_tabungan);
    die("Gagal mengambil data tabungan.");
}

$result_tabungan = mysqli_stmt_get_result($query_tabungan);

$saldo = 0;

if ($result_tabungan && mysqli_num_rows($result_tabungan) === 1) {
    $data_tabungan = mysqli_fetch_assoc($result_tabungan);
    $saldo = (float) $data_tabungan['saldo'];
}

mysqli_stmt_close($query_tabungan);


// ========================================
// STATISTIK SETORAN
// ========================================

$query_statistik = mysqli_prepare(
    $conn,
    "SELECT
        COUNT(*) AS total_setoran,
        COALESCE(
            SUM(
                CASE
                    WHEN status = 'diterima'
                    THEN berat_kg
                    ELSE 0
                END
            ),
            0
        ) AS total_berat,
        COALESCE(
            SUM(
                CASE
                    WHEN status = 'diterima'
                    THEN total_harga
                    ELSE 0
                END
            ),
            0
        ) AS total_nilai
     FROM setoran_sampah
     WHERE user_id = ?"
);

if (!$query_statistik) {
    die("Gagal menyiapkan query statistik.");
}

mysqli_stmt_bind_param($query_statistik, "i", $id);

if (!mysqli_stmt_execute($query_statistik)) {
    mysqli_stmt_close($query_statistik);
    die("Gagal mengambil statistik setoran.");
}

$result_statistik = mysqli_stmt_get_result($query_statistik);

$statistik = [
    'total_setoran' => 0,
    'total_berat'   => 0,
    'total_nilai'   => 0
];

if ($result_statistik) {
    $data_statistik = mysqli_fetch_assoc($result_statistik);

    if ($data_statistik) {
        $statistik = $data_statistik;
    }
}

mysqli_stmt_close($query_statistik);


// ========================================
// RIWAYAT SETORAN TERBARU
// ========================================

$query_riwayat = mysqli_prepare(
    $conn,
    "SELECT
        s.id,
        s.tanggal_setor,
        s.berat_kg,
        s.total_harga,
        s.status,
        k.nama_kategori
     FROM setoran_sampah s
     LEFT JOIN kategori_sampah k
        ON s.kategori_id = k.id
     WHERE s.user_id = ?
     ORDER BY s.id DESC
     LIMIT 10"
);

if (!$query_riwayat) {
    die("Gagal menyiapkan query riwayat.");
}

mysqli_stmt_bind_param($query_riwayat, "i", $id);

if (!mysqli_stmt_execute($query_riwayat)) {
    mysqli_stmt_close($query_riwayat);
    die("Gagal mengambil riwayat setoran.");
}

$result_riwayat = mysqli_stmt_get_result($query_riwayat);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Warga - <?php echo htmlspecialchars($warga['nama_lengkap']); ?></title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .detail-warga-wrapper {
            max-width: 1150px;
            margin: 40px auto;
            padding: 0 24px;
        }

        .detail-warga-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 25px;
        }

        .detail-warga-header h1 {
            margin: 0 0 7px;
            color: #172033;
            font-size: 28px;
        }

        .detail-warga-header p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .detail-warga-back {
            display: inline-flex;
            align-items: center;
            padding: 10px 16px;
            border-radius: 9px;
            background: #f3f4f6;
            color: #374151;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .detail-warga-profile {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 22px;
            box-shadow: 0 8px 30px rgba(15, 118, 110, 0.05);
        }

        .detail-warga-profile-top {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 25px;
        }

        .detail-warga-avatar {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            background: #e6f4f1;
            color: #0f766e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
        }

        .detail-warga-profile-top h2 {
            margin: 0 0 5px;
            color: #172033;
            font-size: 21px;
        }

        .detail-warga-profile-top p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .detail-warga-status {
            margin-left: auto;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .detail-warga-status.aktif {
            background: #dcfce7;
            color: #166534;
        }

        .detail-warga-status.nonaktif {
            background: #fee2e2;
            color: #991b1b;
        }

        .detail-warga-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .detail-warga-info-item {
            padding: 15px 16px;
            border: 1px solid #edf0f2;
            border-radius: 10px;
            background: #fafafa;
        }

        .detail-warga-info-item span {
            display: block;
            font-size: 12px;
            color: #8a8f98;
            margin-bottom: 5px;
        }

        .detail-warga-info-item strong {
            color: #172033;
            font-size: 14px;
            font-weight: 600;
            word-break: break-word;
        }

        .detail-warga-statistics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 22px;
        }

        .detail-warga-stat {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 22px;
        }

        .detail-warga-stat span {
            display: block;
            color: #8a8f98;
            font-size: 12px;
            margin-bottom: 9px;
        }

        .detail-warga-stat strong {
            display: block;
            color: #172033;
            font-size: 24px;
            line-height: 1.2;
        }

        .detail-warga-stat small {
            display: block;
            margin-top: 6px;
            color: #6b7280;
            font-size: 12px;
        }

        .detail-warga-section {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
        }

        .detail-warga-section-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-warga-section-header h2 {
            margin: 0;
            font-size: 18px;
            color: #172033;
        }

        .detail-warga-table-wrapper {
            overflow-x: auto;
        }

        .detail-warga-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .detail-warga-table th {
            text-align: left;
            padding: 13px 18px;
            background: #fafafa;
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .detail-warga-table td {
            padding: 15px 18px;
            border-top: 1px solid #f0f1f2;
            color: #374151;
            font-size: 13px;
        }

        .detail-warga-table tbody tr:hover {
            background: #fafdfc;
        }

        .status-setoran {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-setoran.diterima {
            background: #dcfce7;
            color: #166534;
        }

        .status-setoran.menunggu {
            background: #fef3c7;
            color: #92400e;
        }

        .status-setoran.ditolak {
            background: #fee2e2;
            color: #991b1b;
        }

        .detail-warga-empty {
            padding: 35px 24px;
            text-align: center;
            color: #8a8f98;
            font-size: 14px;
        }

        @media (max-width: 800px) {
            .detail-warga-header {
                flex-direction: column;
            }

            .detail-warga-statistics {
                grid-template-columns: 1fr;
            }

            .detail-warga-info {
                grid-template-columns: 1fr;
            }

            .detail-warga-profile-top {
                align-items: flex-start;
            }

            .detail-warga-status {
                margin-left: 0;
            }
        }

        @media (max-width: 550px) {
            .detail-warga-wrapper {
                margin: 20px auto;
                padding: 0 15px;
            }

            .detail-warga-profile {
                padding: 20px;
            }

            .detail-warga-profile-top {
                flex-wrap: wrap;
            }

            .detail-warga-header h1 {
                font-size: 23px;
            }
        }
    </style>
</head>

<body>

<div class="detail-warga-wrapper">

    <!-- HEADER -->

    <div class="detail-warga-header">

        <div>
            <h1>Detail Warga</h1>

            <p>
                Informasi profil dan aktivitas warga
            </p>
        </div>

        <a
            href="warga.php"
            class="detail-warga-back"
        >
            ← Kembali
        </a>

    </div>


    <!-- PROFIL -->

    <div class="detail-warga-profile">

        <div class="detail-warga-profile-top">

            <div class="detail-warga-avatar">
                <?php
                echo strtoupper(
                    substr($warga['nama_lengkap'], 0, 1)
                );
                ?>
            </div>

            <div>
                <h2>
                    <?php echo htmlspecialchars($warga['nama_lengkap']); ?>
                </h2>

                <p>
                    @<?php echo htmlspecialchars($warga['username']); ?>
                </p>
            </div>

            <span
                class="detail-warga-status <?php echo $warga['status'] === 'aktif' ? 'aktif' : 'nonaktif'; ?>"
            >
                <?php echo ucfirst(htmlspecialchars($warga['status'])); ?>
            </span>

        </div>


        <div class="detail-warga-info">

            <div class="detail-warga-info-item">
                <span>Email</span>

                <strong>
                    <?php echo htmlspecialchars($warga['email']); ?>
                </strong>
            </div>


            <div class="detail-warga-info-item">
                <span>No. HP</span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $warga['no_hp'] ?: '-'
                    );
                    ?>
                </strong>
            </div>


            <div class="detail-warga-info-item">
                <span>Alamat</span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $warga['alamat'] ?: '-'
                    );
                    ?>
                </strong>
            </div>


            <div class="detail-warga-info-item">
                <span>ID Warga</span>

                <strong>
                    #<?php echo (int)$warga['id']; ?>
                </strong>
            </div>

        </div>

    </div>


    <!-- STATISTIK -->

    <div class="detail-warga-statistics">

        <div class="detail-warga-stat">

            <span>Saldo Tabungan</span>

            <strong>
                Rp <?php echo number_format($saldo, 0, ',', '.'); ?>
            </strong>

            <small>
                Saldo saat ini
            </small>

        </div>


        <div class="detail-warga-stat">

            <span>Total Setoran</span>

            <strong>
                <?php echo number_format((int)$statistik['total_setoran'], 0, ',', '.'); ?>
            </strong>

            <small>
                Semua pengajuan setoran
            </small>

        </div>


        <div class="detail-warga-stat">

            <span>Total Berat Diterima</span>

            <strong>
                <?php
                echo number_format(
                    (float)$statistik['total_berat'],
                    2,
                    ',',
                    '.'
                );
                ?> kg
            </strong>

            <small>
                Setoran yang diterima
            </small>

        </div>

    </div>


    <!-- RIWAYAT SETORAN -->

    <div class="detail-warga-section">

        <div class="detail-warga-section-header">

            <h2>
                Riwayat Setoran
            </h2>

        </div>


        <div class="detail-warga-table-wrapper">

            <?php if ($result_riwayat && mysqli_num_rows($result_riwayat) > 0): ?>

                <table class="detail-warga-table">

                    <thead>

                        <tr>
                            <th>Tanggal</th>
                            <th>Kategori</th>
                            <th>Berat</th>
                            <th>Nilai</th>
                            <th>Status</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php while ($riwayat = mysqli_fetch_assoc($result_riwayat)): ?>

                        <?php
                        $tanggal = strtotime(
                            $riwayat['tanggal_setor']
                        );

                        $status_setoran = strtolower(
                            $riwayat['status'] ?? ''
                        );
                        ?>

                        <tr>

                            <td>
                                <?php
                                echo $tanggal !== false
                                    ? date('d/m/Y', $tanggal)
                                    : '-';
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $riwayat['nama_kategori'] ?? '-'
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo number_format(
                                    (float)$riwayat['berat_kg'],
                                    2,
                                    ',',
                                    '.'
                                );
                                ?> kg
                            </td>

                            <td>
                                Rp
                                <?php
                                echo number_format(
                                    (float)$riwayat['total_harga'],
                                    0,
                                    ',',
                                    '.'
                                );
                                ?>
                            </td>

                            <td>

                                <span
                                    class="status-setoran <?php echo htmlspecialchars($status_setoran); ?>"
                                >
                                    <?php
                                    echo ucfirst(
                                        htmlspecialchars(
                                            $riwayat['status']
                                        )
                                    );
                                    ?>
                                </span>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="detail-warga-empty">
                    Belum ada riwayat setoran warga ini.
                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

</body>
</html>