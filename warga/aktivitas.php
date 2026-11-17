<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$user_id = (int) $_SESSION['user_id'];

/* ===============================
   FILTER
================================ */
$keyword = trim($_GET['keyword'] ?? '');
$aktivitas_filter = trim($_GET['aktivitas'] ?? '');
$tanggal_dari = trim($_GET['tanggal_dari'] ?? '');
$tanggal_sampai = trim($_GET['tanggal_sampai'] ?? '');

/* ===============================
   VALIDASI TANGGAL
================================ */
if (
    $tanggal_dari !== '' &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_dari)
) {
    $tanggal_dari = '';
}

if (
    $tanggal_sampai !== '' &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_sampai)
) {
    $tanggal_sampai = '';
}

/* ===============================
   QUERY AKTIVITAS
================================ */
$sql = "
    SELECT
        id,
        aktivitas,
        keterangan,
        tanggal_aktivitas
    FROM log_aktivitas
    WHERE user_id = ?
";

$types = "i";
$params = [$user_id];

if ($keyword !== '') {
    $sql .= " AND (aktivitas LIKE ? OR keterangan LIKE ?)";
    $keyword_param = '%' . $keyword . '%';

    $types .= "ss";
    $params[] = $keyword_param;
    $params[] = $keyword_param;
}

if ($aktivitas_filter !== '') {
    $sql .= " AND aktivitas = ?";
    $types .= "s";
    $params[] = $aktivitas_filter;
}

if ($tanggal_dari !== '') {
    $sql .= " AND DATE(tanggal_aktivitas) >= ?";
    $types .= "s";
    $params[] = $tanggal_dari;
}

if ($tanggal_sampai !== '') {
    $sql .= " AND DATE(tanggal_aktivitas) <= ?";
    $types .= "s";
    $params[] = $tanggal_sampai;
}

$sql .= " ORDER BY tanggal_aktivitas DESC, id DESC LIMIT 100";

$stmt = mysqli_prepare($conn, $sql);

$aktivitas_data = [];

if ($stmt) {

    mysqli_stmt_bind_param($stmt, $types, ...$params);

    if (mysqli_stmt_execute($stmt)) {

        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $aktivitas_data[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}

/* ===============================
   DAFTAR JENIS AKTIVITAS
================================ */
$jenis_aktivitas = [];

$stmt_jenis = mysqli_prepare(
    $conn,
    "SELECT DISTINCT aktivitas
     FROM log_aktivitas
     WHERE user_id = ?
     AND aktivitas IS NOT NULL
     AND aktivitas <> ''
     ORDER BY aktivitas ASC"
);

if ($stmt_jenis) {

    mysqli_stmt_bind_param($stmt_jenis, "i", $user_id);
    mysqli_stmt_execute($stmt_jenis);

    $result_jenis = mysqli_stmt_get_result($stmt_jenis);

    while ($row = mysqli_fetch_assoc($result_jenis)) {
        $jenis_aktivitas[] = $row['aktivitas'];
    }

    mysqli_stmt_close($stmt_jenis);
}

/* ===============================
   HELPER TAMPILAN
================================ */
function getIconAktivitas($aktivitas)
{
    $aktivitas_lower = strtolower($aktivitas);

    if (
        strpos($aktivitas_lower, 'setor') !== false
    ) {
        return '♻';
    }

    if (
        strpos($aktivitas_lower, 'tarik') !== false ||
        strpos($aktivitas_lower, 'penarikan') !== false
    ) {
        return '💰';
    }

    if (
        strpos($aktivitas_lower, 'password') !== false ||
        strpos($aktivitas_lower, 'akun') !== false
    ) {
        return '🔐';
    }

    if (
        strpos($aktivitas_lower, 'login') !== false
    ) {
        return '↪';
    }

    if (
        strpos($aktivitas_lower, 'profil') !== false
    ) {
        return '👤';
    }

    return '●';
}

function getClassAktivitas($aktivitas)
{
    $aktivitas_lower = strtolower($aktivitas);

    if (strpos($aktivitas_lower, 'setor') !== false) {
        return 'activity-green';
    }

    if (
        strpos($aktivitas_lower, 'tarik') !== false ||
        strpos($aktivitas_lower, 'penarikan') !== false
    ) {
        return 'activity-gold';
    }

    if (
        strpos($aktivitas_lower, 'password') !== false ||
        strpos($aktivitas_lower, 'akun') !== false
    ) {
        return 'activity-blue';
    }

    return 'activity-default';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Aktivitas Saya - Bank Sampah Digital</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f8f7;
            color: #263b37;
        }

        .activity-page {
            min-height: 100vh;
        }

        /* ===============================
           HEADER
        ================================ */

        .activity-header {
            background: #ffffff;
            border-bottom: 1px solid #e2ebe8;
            padding: 18px 34px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            position: sticky;
            top: 0;
            z-index: 10;
        }

        .activity-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;

            background: #e8f5f2;
            color: #0f766e;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 20px;
        }

        .brand-text h1 {
            font-size: 17px;
            color: #173b35;
            margin-bottom: 3px;
        }

        .brand-text p {
            font-size: 12px;
            color: #7a8b87;
        }

        .btn-kembali {
            text-decoration: none;
            color: #0f766e;
            background: #ffffff;

            border: 1px solid #cfe1dd;
            border-radius: 9px;

            padding: 9px 15px;

            font-size: 13px;
            font-weight: 600;

            transition: 0.2s ease;
        }

        .btn-kembali:hover {
            background: #edf7f4;
            border-color: #0f766e;
        }

        /* ===============================
           CONTENT
        ================================ */

        .activity-container {
            max-width: 1050px;
            margin: 0 auto;
            padding: 34px 25px 60px;
        }

        .page-heading {
            margin-bottom: 24px;
        }

        .page-heading h2 {
            font-size: 26px;
            color: #173b35;
            margin-bottom: 7px;
        }

        .page-heading p {
            color: #778984;
            font-size: 14px;
        }

        /* ===============================
           FILTER
        ================================ */

        .filter-card {
            background: #ffffff;
            border: 1px solid #e2ebe8;
            border-radius: 14px;

            padding: 20px;

            margin-bottom: 28px;
        }

        .filter-title {
            font-size: 14px;
            font-weight: 700;
            color: #28443e;

            margin-bottom: 15px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr auto;
            gap: 10px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 11px;
            font-weight: 600;
            color: #71817d;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            height: 40px;

            padding: 0 11px;

            border: 1px solid #d8e5e2;
            border-radius: 8px;

            background: #ffffff;
            color: #314640;

            outline: none;
            font-size: 13px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            border-color: #0f766e;
        }

        .btn-filter {
            height: 40px;
            padding: 0 17px;

            border: 0;
            border-radius: 8px;

            background: #0f766e;
            color: #ffffff;

            font-size: 13px;
            font-weight: 600;

            cursor: pointer;
        }

        .btn-filter:hover {
            background: #0c625b;
        }

        .btn-reset {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            height: 40px;
            padding: 0 13px;

            border-radius: 8px;
            border: 1px solid #d8e5e2;

            color: #667873;
            background: #ffffff;

            text-decoration: none;
            font-size: 13px;
        }

        .btn-reset:hover {
            background: #f5f8f7;
        }

        /* ===============================
           ACTIVITY
        ================================ */

        .activity-section {
            background: #ffffff;
            border: 1px solid #e2ebe8;
            border-radius: 14px;

            padding: 25px;
        }

        .activity-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-bottom: 24px;
        }

        .activity-section-header h3 {
            font-size: 16px;
            color: #27423d;
        }

        .activity-count {
            font-size: 12px;
            color: #81908c;
        }

        .timeline {
            position: relative;
        }

        .timeline::before {
            content: "";
            position: absolute;

            left: 19px;
            top: 18px;
            bottom: 18px;

            width: 1px;

            background: #dce9e6;
        }

        .activity-item {
            position: relative;

            display: flex;
            gap: 16px;

            padding-bottom: 24px;
        }

        .activity-item:last-child {
            padding-bottom: 0;
        }

        .activity-icon {
            width: 39px;
            height: 39px;

            min-width: 39px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            position: relative;
            z-index: 2;

            font-size: 16px;
            font-weight: bold;
        }

        .activity-green {
            background: #e8f5f2;
            color: #0f766e;
        }

        .activity-gold {
            background: #fff7e6;
            color: #a46a00;
        }

        .activity-blue {
            background: #edf3ff;
            color: #4169a1;
        }

        .activity-default {
            background: #eef2f1;
            color: #63736f;
        }

        .activity-content {
            flex: 1;

            padding-top: 1px;
            padding-bottom: 18px;

            border-bottom: 1px solid #edf2f0;
        }

        .activity-item:last-child .activity-content {
            border-bottom: 0;
        }

        .activity-title {
            color: #28443e;
            font-size: 14px;
            font-weight: 700;

            margin-bottom: 5px;
        }

        .activity-description {
            color: #71817d;
            font-size: 13px;
            line-height: 1.6;

            margin-bottom: 7px;
        }

        .activity-date {
            color: #9aa7a4;
            font-size: 11px;
        }

        /* ===============================
           EMPTY
        ================================ */

        .empty-state {
            text-align: center;
            padding: 55px 20px;
        }

        .empty-icon {
            width: 56px;
            height: 56px;

            margin: 0 auto 15px;

            border-radius: 50%;

            background: #edf6f4;
            color: #0f766e;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 23px;
        }

        .empty-state h4 {
            color: #314640;
            font-size: 15px;
            margin-bottom: 7px;
        }

        .empty-state p {
            color: #879590;
            font-size: 13px;
        }

        /* ===============================
           RESPONSIVE
        ================================ */

        @media (max-width: 850px) {

            .filter-form {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-actions {
                display: flex;
                gap: 8px;
            }

        }

        @media (max-width: 600px) {

            .activity-header {
                padding: 15px 18px;
            }

            .brand-text h1 {
                font-size: 15px;
            }

            .brand-text p {
                display: none;
            }

            .btn-kembali {
                padding: 8px 11px;
                font-size: 12px;
            }

            .activity-container {
                padding: 25px 15px 45px;
            }

            .page-heading h2 {
                font-size: 22px;
            }

            .filter-card {
                padding: 16px;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                width: 100%;
            }

            .btn-filter,
            .btn-reset {
                flex: 1;
            }

            .activity-section {
                padding: 18px 15px;
            }

            .activity-section-header {
                align-items: flex-start;
            }

            .timeline::before {
                left: 17px;
            }

            .activity-icon {
                width: 35px;
                min-width: 35px;
                height: 35px;
                font-size: 14px;
            }

            .activity-item {
                gap: 12px;
            }

            .activity-title {
                font-size: 13px;
            }

            .activity-description {
                font-size: 12px;
            }
        }

    </style>
</head>

<body>

<div class="activity-page">

    <!-- HEADER -->
    <header class="activity-header">

        <div class="activity-brand">

            <div class="brand-icon">
                ♻
            </div>

            <div class="brand-text">
                <h1>Bank Sampah Digital</h1>
                <p>Aktivitas akun Anda</p>
            </div>

        </div>

        <a href="profil.php" class="btn-kembali">
            ← Kembali ke Profil
        </a>

    </header>


    <!-- CONTENT -->
    <main class="activity-container">

        <div class="page-heading">
            <h2>Aktivitas Saya</h2>

            <p>
                Lihat riwayat aktivitas yang berkaitan dengan akun Anda.
            </p>
        </div>


        <!-- FILTER -->
        <div class="filter-card">

            <div class="filter-title">
                Filter Aktivitas
            </div>

            <form method="GET" class="filter-form">

                <div class="filter-group">

                    <label for="keyword">
                        Cari aktivitas
                    </label>

                    <input
                        type="text"
                        id="keyword"
                        name="keyword"
                        value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Cari aktivitas..."
                    >

                </div>


                <div class="filter-group">

                    <label for="aktivitas">
                        Jenis aktivitas
                    </label>

                    <select id="aktivitas" name="aktivitas">

                        <option value="">
                            Semua aktivitas
                        </option>

                        <?php foreach ($jenis_aktivitas as $jenis): ?>

                            <option
                                value="<?= htmlspecialchars($jenis, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $aktivitas_filter === $jenis ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($jenis, ENT_QUOTES, 'UTF-8') ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="filter-group">

                    <label for="tanggal_dari">
                        Dari tanggal
                    </label>

                    <input
                        type="date"
                        id="tanggal_dari"
                        name="tanggal_dari"
                        value="<?= htmlspecialchars($tanggal_dari, ENT_QUOTES, 'UTF-8') ?>"
                    >

                </div>


                <div class="filter-group">

                    <label for="tanggal_sampai">
                        Sampai tanggal
                    </label>

                    <input
                        type="date"
                        id="tanggal_sampai"
                        name="tanggal_sampai"
                        value="<?= htmlspecialchars($tanggal_sampai, ENT_QUOTES, 'UTF-8') ?>"
                    >

                </div>


                <div class="filter-actions">

                    <button type="submit" class="btn-filter">
                        Filter
                    </button>

                    <a href="aktivitas.php" class="btn-reset">
                        Reset
                    </a>

                </div>

            </form>

        </div>


        <!-- ACTIVITY LIST -->
        <section class="activity-section">

            <div class="activity-section-header">

                <h3>Riwayat Aktivitas</h3>

                <span class="activity-count">
                    <?= count($aktivitas_data) ?> aktivitas
                </span>

            </div>


            <?php if (empty($aktivitas_data)): ?>

                <div class="empty-state">

                    <div class="empty-icon">
                        ✓
                    </div>

                    <h4>Belum ada aktivitas</h4>

                    <p>
                        Aktivitas akun Anda akan muncul di halaman ini.
                    </p>

                </div>

            <?php else: ?>

                <div class="timeline">

                    <?php foreach ($aktivitas_data as $item): ?>

                        <div class="activity-item">

                            <div class="activity-icon <?= getClassAktivitas($item['aktivitas']) ?>">
                                <?= getIconAktivitas($item['aktivitas']) ?>
                            </div>

                            <div class="activity-content">

                                <div class="activity-title">
                                    <?= htmlspecialchars(
                                        $item['aktivitas'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>

                                <?php if (!empty($item['keterangan'])): ?>

                                    <div class="activity-description">
                                        <?= htmlspecialchars(
                                            $item['keterangan'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                <?php endif; ?>

                                <div class="activity-date">

                                    <?= !empty($item['tanggal_aktivitas'])
                                        ? date(
                                            'd M Y, H:i',
                                            strtotime($item['tanggal_aktivitas'])
                                        )
                                        : '-'
                                    ?>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>