
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';


// =============================
// NOTIFIKASI PETUGAS
// =============================

$user_id = (int) $_SESSION['user_id'];

$stmt_notifikasi = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM notifikasi
     WHERE user_id = ? AND dibaca = 0"
);

mysqli_stmt_bind_param($stmt_notifikasi, "i", $user_id);
mysqli_stmt_execute($stmt_notifikasi);

$data_notifikasi = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt_notifikasi)
);

$jumlah_notifikasi = (int) $data_notifikasi['total'];

mysqli_stmt_close($stmt_notifikasi);


// =============================
// STATISTIK SETORAN
// =============================

$query_statistik = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total_setoran,

        SUM(CASE
            WHEN status = 'menunggu' THEN 1
            ELSE 0
        END) AS total_menunggu,

        SUM(CASE
            WHEN status = 'diterima' THEN 1
            ELSE 0
        END) AS total_diterima,

        SUM(CASE
            WHEN status = 'ditolak' THEN 1
            ELSE 0
        END) AS total_ditolak,

        COALESCE(SUM(
            CASE
                WHEN status = 'diterima' THEN berat_kg
                ELSE 0
            END
        ), 0) AS total_berat

     FROM setoran_sampah"
);

$statistik = mysqli_fetch_assoc($query_statistik);


// =============================
// SETORAN TERBARU
// =============================

$query_setoran = mysqli_query(
    $conn,
    "SELECT
        s.id,
        s.tanggal_setor,
        s.berat_kg,
        s.total_harga,
        s.status,
        u.nama_lengkap,
        k.nama_kategori

     FROM setoran_sampah s

     LEFT JOIN users u
        ON s.user_id = u.id

     LEFT JOIN kategori_sampah k
        ON s.kategori_id = k.id

     ORDER BY s.id DESC
     LIMIT 8"
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Petugas - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        /* Header Dashboard Petugas */
        .petugas-heading-notifikasi {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .petugas-heading-text {
            flex: 1;
        }

        .petugas-heading-text h1 {
            margin-bottom: 7px;
        }

        .petugas-heading-text p {
            margin: 0;
        }

        /* Ikon Notifikasi */
        .petugas-notification-link {
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

        .petugas-notification-link:hover {
            background: #dcefe8;
            transform: translateY(-2px);
        }

        .petugas-notification-icon {
            font-size: 21px;
        }

        .petugas-notification-badge {
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
            .petugas-heading-notifikasi {
                align-items: flex-start;
                gap: 12px;
            }

            .petugas-notification-link {
                width: 42px;
                height: 42px;
                min-width: 42px;
            }
        }

        .admin-profile .profile-link {
    display: inline;
    color: #0f766e;
    text-decoration: none;
    font-weight: 600;
    margin-right: 18px;
    padding: 0;
    background: transparent !important;
    border: none !important;
    border-radius: 0;
}

.admin-profile .profile-link:hover {
    color: #0b5f59;
    text-decoration: underline;
}
    </style>

</head>

<body>

<div class="admin-dashboard">

    <!-- NAVBAR -->

    <header class="admin-navbar">

        <h2>♻ Bank Sampah Digital</h2>

        <div class="admin-profile">

            <span>
                Halo, <?= htmlspecialchars($_SESSION['nama'], ENT_QUOTES, 'UTF-8'); ?>!
            </span>

            <a href="profil.php" class="profile-link">
                Profil
            </a>

            <a href="../auth/logout.php">
                Logout
            </a>

        </div>

    </header>


    <main class="admin-content">

        <!-- HEADER DASHBOARD -->

        <div class="admin-heading petugas-heading-notifikasi">

            <div class="petugas-heading-text">

                <h1>Dashboard Petugas</h1>

                <p>
                    Selamat datang! Kelola dan verifikasi setoran sampah warga.
                </p>

            </div>

            <!-- IKON NOTIFIKASI -->

            <a href="notifikasi.php"
               class="petugas-notification-link"
               title="Lihat Notifikasi"
               aria-label="Lihat Notifikasi">

                <span class="petugas-notification-icon">🔔</span>

                <?php if ($jumlah_notifikasi > 0): ?>

                    <span class="petugas-notification-badge">
                        <?= $jumlah_notifikasi > 99 ? '99+' : $jumlah_notifikasi ?>
                    </span>

                <?php endif; ?>

            </a>

        </div>


        <!-- STATISTIK -->

        <div class="admin-statistics">

            <div class="admin-stat-card">

                <h3>Total Setoran</h3>

                <p>
                    <?php echo number_format(
                        (int)$statistik['total_setoran'],
                        0,
                        ',',
                        '.'
                    ); ?>
                </p>

            </div>


            <div class="admin-stat-card">

                <h3>Menunggu Verifikasi</h3>

                <p>
                    <?php echo number_format(
                        (int)$statistik['total_menunggu'],
                        0,
                        ',',
                        '.'
                    ); ?>
                </p>

            </div>


            <div class="admin-stat-card">

                <h3>Setoran Diterima</h3>

                <p>
                    <?php echo number_format(
                        (int)$statistik['total_diterima'],
                        0,
                        ',',
                        '.'
                    ); ?>
                </p>

            </div>


            <div class="admin-stat-card">

                <h3>Setoran Ditolak</h3>

                <p>
                    <?php echo number_format(
                        (int)$statistik['total_ditolak'],
                        0,
                        ',',
                        '.'
                    ); ?>
                </p>

            </div>

        </div>


        <!-- TOTAL BERAT -->

        <div class="petugas-berat-card">

            <div>

                <span>Total Sampah Diterima</span>

                <h2>
                    <?php echo number_format(
                        (float)$statistik['total_berat'],
                        2,
                        ',',
                        '.'
                    ); ?> Kg
                </h2>

                <p>
                    Akumulasi berat seluruh setoran yang sudah diterima.
                </p>

            </div>

            <span class="petugas-berat-icon">♻</span>

        </div>


        <!-- SETORAN TERBARU -->

        <section class="petugas-setoran-section">

            <div class="petugas-section-heading">

                <div>

                    <h2>Setoran Terbaru</h2>

                    <p>
                        Daftar pengajuan setoran sampah dari warga.
                    </p>

                </div>

                <a href="setoran.php" class="btn-edit-petugas">
                    Lihat Semua Setoran →
                </a>

            </div>


            <div class="warga-table-container">

                <table class="warga-table">

                    <thead>

                        <tr>

                            <th>No</th>
                            <th>Nama Warga</th>
                            <th>Kategori Sampah</th>
                            <th>Tanggal Setor</th>
                            <th>Berat</th>
                            <th>Total Harga</th>
                            <th>Status</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (mysqli_num_rows($query_setoran) > 0) : ?>

                            <?php $no = 1; ?>

                            <?php while ($setoran = mysqli_fetch_assoc($query_setoran)) : ?>

                                <tr>

                                    <td>
                                        <?php echo $no++; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars(
                                            $setoran['nama_lengkap'] ?? '-'
                                        ); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars(
                                            $setoran['nama_kategori'] ?? '-'
                                        ); ?>
                                    </td>

                                    <td>
                                       <?php
                                        $tanggal_setor = $setoran['tanggal_setor'] ?? '';

                                        if ($tanggal_setor !== '' && strtotime($tanggal_setor) !== false) {
                                            echo date('d/m/Y', strtotime($tanggal_setor));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo number_format(
                                            (float)$setoran['berat_kg'],
                                            2,
                                            ',',
                                            '.'
                                        ); ?> Kg
                                    </td>

                                    <td>
                                        Rp <?php echo number_format(
                                            (float)$setoran['total_harga'],
                                            0,
                                            ',',
                                            '.'
                                        ); ?>
                                    </td>

                                    <td>

                                        <?php if ($setoran['status'] == 'menunggu') : ?>

                                            <span class="petugas-status menunggu">
                                                Menunggu
                                            </span>

                                        <?php elseif ($setoran['status'] == 'diterima') : ?>

                                            <span class="petugas-status diterima">
                                                Diterima
                                            </span>

                                        <?php elseif ($setoran['status'] == 'ditolak') : ?>

                                            <span class="petugas-status ditolak">
                                                Ditolak
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else : ?>

                            <tr>

                                <td colspan="7" style="text-align:center;">
                                    Belum ada data setoran sampah.
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
