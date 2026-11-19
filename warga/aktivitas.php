
<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'warga') {
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
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_dari)
) {
    $tanggal_dari = '';
}

if (
    $tanggal_sampai !== '' &&
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_sampai)
) {
    $tanggal_sampai = '';
}

if (
    $tanggal_dari !== '' &&
    $tanggal_sampai !== '' &&
    $tanggal_dari > $tanggal_sampai
) {
    $tanggal_dari = '';
    $tanggal_sampai = '';
}

/* ===============================
   QUERY AKTIVITAS
================================ */
$sql = "
    SELECT id, aktivitas, keterangan, tanggal_aktivitas
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

    if (strpos($aktivitas_lower, 'setor') !== false) {
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

    if (strpos($aktivitas_lower, 'login') !== false) {
        return '↪';
    }

    if (strpos($aktivitas_lower, 'profil') !== false) {
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

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Aktivitas Saya - Bank Sampah TPS 3R</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #F2F7FA;
            font-family: 'Poppins', sans-serif;
            color: #183B55;
        }

        /* ===============================
           SIDEBAR - SAMA DENGAN HALAMAN WARGA
        ================================ */
        .warga-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            z-index: 1001;
            color: #fff;
            background: linear-gradient(180deg, #071F33, #0B2D46);
            border-right: 1px solid rgba(255,255,255,.08);
        }

        .sidebar-brand {
            flex-shrink: 0;
            padding: 24px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }

        .sidebar-brand-top {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-logo {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            padding: 3px;
            border-radius: 50%;
            background: #fff;
            overflow: hidden;
        }

        .sidebar-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .sidebar-brand-top > div:last-child {
            flex: 1;
            min-width: 0;
        }

        .sidebar-brand h2 {
            margin: 0;
            color: #fff;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 700;
        }

        .sidebar-brand p {
            margin: 4px 0 0;
            color: #AFC4D2;
            font-size: 9px;
            line-height: 1.5;
            letter-spacing: .4px;
        }

        .sidebar-nav {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
            padding: 14px 12px 20px;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .sidebar-nav::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        .sidebar-section-title {
            margin: 13px 0 6px;
            padding: 0 10px;
            color: #8FA9BA;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .sidebar-link {
            min-height: 43px;
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 12px;
            margin: 2px 0;
            border-radius: 9px;
            color: #D8E5ED;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: .2s ease;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: #fff;
            background: rgba(24,195,216,.17);
        }

        .sidebar-link.active {
            box-shadow: inset 3px 0 0 #18C3D8;
        }

        .sidebar-icon {
            width: 22px;
            min-width: 22px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-logout:hover {
            background: rgba(255,90,90,.14);
        }

        /* ===============================
           MAIN & HEADER
        ================================ */
        .warga-main {
            width: calc(100% - 250px);
            min-height: 100vh;
            margin-left: 250px;
            padding: 30px 35px;
        }

        .page-header-row {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .warga-heading {
            flex: 1;
            min-width: 0;
        }

        .warga-heading h1 {
            margin: 0;
            color: #102F43;
            font-size: 25px;
            font-weight: 750;
            letter-spacing: -.4px;
        }

        .warga-heading p {
            margin: 7px 0 0;
            color: #718493;
            font-size: 12px;
            line-height: 1.7;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .mobile-menu-button {
            display: none;
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(135deg, #071F33, #147F91);
            font-size: 22px;
            cursor: pointer;
        }

        .btn-kembali {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 17px;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(135deg, #071F33, #147F91);
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            box-shadow: 0 5px 13px rgba(7,31,51,.15);
            transition: .2s ease;
        }

        .btn-kembali:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 16px rgba(7,31,51,.22);
        }

        .sidebar-overlay {
            display: none;
        }

        /* ===============================
           FILTER
        ================================ */
        .filter-card,
        .activity-section {
            background: #fff;
            border: 1px solid #E1EAF0;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(7,31,51,.035);
        }

        .filter-card {
            padding: 22px;
            margin-bottom: 22px;
        }

        .filter-title {
            margin-bottom: 17px;
            color: #102F43;
            font-size: 14px;
            font-weight: 700;
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
            gap: 7px;
            min-width: 0;
        }

        .filter-group label {
            color: #718493;
            font-size: 10px;
            font-weight: 600;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            height: 41px;
            padding: 0 11px;
            border: 1px solid #D8E5EC;
            border-radius: 8px;
            background: #fff;
            color: #183B55;
            outline: none;
            font-family: inherit;
            font-size: 11px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            border-color: #18AFC2;
            box-shadow: 0 0 0 3px rgba(24,195,216,.10);
        }

        .filter-actions {
            display: flex;
            gap: 7px;
        }

        .btn-filter,
        .btn-reset {
            height: 41px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 14px;
            border-radius: 8px;
            font-family: inherit;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
            transition: .2s ease;
        }

        .btn-filter {
            border: none;
            color: #fff;
            background: linear-gradient(135deg, #071F33, #147F91);
        }

        .btn-filter:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(7,31,51,.15);
        }

        .btn-reset {
            border: 1px solid #D8E5EC;
            color: #536D7E;
            background: #fff;
        }

        .btn-reset:hover {
            background: #F2F7FA;
        }

        /* ===============================
           ACTIVITY SECTION
        ================================ */
        .activity-section {
            padding: 24px;
        }

        .activity-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
        }

        .activity-section-header h3 {
            margin: 0;
            color: #102F43;
            font-size: 15px;
            font-weight: 700;
        }

        .activity-count {
            padding: 6px 11px;
            border-radius: 20px;
            color: #147F91;
            background: #E8F7F9;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
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
            background: #DCE9EE;
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
            background: #E5F6EF;
            color: #16845B;
        }

        .activity-gold {
            background: #FFF5E3;
            color: #A46A00;
        }

        .activity-blue {
            background: #EAF2FF;
            color: #4169A1;
        }

        .activity-default {
            background: #EAF2F6;
            color: #536D7E;
        }

        .activity-content {
            flex: 1;
            min-width: 0;
            padding-top: 2px;
            padding-bottom: 18px;
            border-bottom: 1px solid #EDF2F5;
        }

        .activity-item:last-child .activity-content {
            border-bottom: 0;
        }

        .activity-title {
            margin-bottom: 5px;
            color: #183B55;
            font-size: 12px;
            font-weight: 700;
        }

        .activity-description {
            margin-bottom: 7px;
            color: #718493;
            font-size: 11px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .activity-date {
            color: #94A5B1;
            font-size: 10px;
        }

        /* ===============================
           EMPTY STATE
        ================================ */
        .empty-state {
            text-align: center;
            padding: 55px 20px;
        }

        .empty-icon {
            width: 58px;
            height: 58px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: #E8F7F9;
            color: #147F91;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 23px;
        }

        .empty-state h4 {
            margin: 0 0 7px;
            color: #183B55;
            font-size: 14px;
            font-weight: 700;
        }

        .empty-state p {
            margin: 0;
            color: #8799A5;
            font-size: 11px;
            line-height: 1.7;
        }

        /* ===============================
           RESPONSIVE - SAMA DENGAN REFERENSI
        ================================ */
        @media(max-width: 1000px) {
            .warga-main {
                padding: 25px 22px;
            }

            .filter-form {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-actions {
                align-self: end;
            }
        }

        @media(max-width: 768px) {
            .warga-sidebar {
                width: 260px;
                min-width: 260px;
                transform: translateX(-100%);
                transition: transform .3s ease;
            }

            .warga-sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: block;
                position: fixed;
                inset: 0;
                z-index: 1000;
                background: rgba(0,0,0,.45);
                opacity: 0;
                visibility: hidden;
                transition: .3s ease;
            }

            .sidebar-overlay.show {
                opacity: 1;
                visibility: visible;
            }

            .warga-main {
                width: 100%;
                margin-left: 0;
                padding: 22px 18px 30px;
            }

            .page-header-row {
                display: flex;
                flex-direction: row;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
            }

            .warga-heading {
                flex: 1;
                min-width: 0;
            }

            .warga-heading h1 {
                font-size: 22px;
            }

            .warga-heading p {
                font-size: 11px;
            }

            .header-actions {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                justify-content: flex-start;
                flex-shrink: 0;
                gap: 8px;
            }

            .mobile-menu-button {
                display: flex;
            }

            .btn-kembali {
                padding: 9px 12px;
                font-size: 10px;
            }

            .filter-card {
                padding: 18px;
            }

            .activity-section {
                padding: 21px;
            }
        }

        @media(max-width: 480px) {
            .warga-main {
                padding: 19px 13px 25px;
            }

            .page-header-row {
                gap: 8px;
            }

            .warga-heading h1 {
                font-size: 18px;
            }

            .warga-heading p {
                font-size: 10px;
                line-height: 1.6;
            }

            .header-actions {
                gap: 6px;
            }

            .mobile-menu-button {
                width: 39px;
                height: 39px;
                font-size: 20px;
            }

            .btn-kembali {
                padding: 9px 10px;
                font-size: 9px;
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
                font-size: 12px;
            }

            .activity-description {
                font-size: 11px;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="warga-sidebar" id="wargaSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-top">
            <div class="sidebar-logo">
                <img src="../assets/images/logo-tps3r.png" alt="Logo TPS 3R">
            </div>

            <div>
                <h2>Bank Sampah TPS 3R Kopling Berjaya</h2>
                <p>TPS 3R KELURAHAN PULAU KAROMAH</p>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section-title">Utama</div>

        <a href="dashboard.php" class="sidebar-link">
            <span class="sidebar-icon">🏠</span>
            <span>Dashboard</span>
        </a>

        <div class="sidebar-section-title">Layanan Warga</div>

        <a href="qr_saya.php" class="sidebar-link">
            <span class="sidebar-icon">▣</span>
            <span>QR Code Saya</span>
        </a>

        <a href="ajukan_setoran.php" class="sidebar-link">
            <span class="sidebar-icon">♻️</span>
            <span>Ajukan Setoran</span>
        </a>

        <a href="riwayat_setoran.php" class="sidebar-link">
            <span class="sidebar-icon">📦</span>
            <span>Riwayat Setoran</span>
        </a>

        <a href="riwayat_tabungan.php" class="sidebar-link">
            <span class="sidebar-icon">💰</span>
            <span>Riwayat Tabungan</span>
        </a>

        <a href="ajukan_penarikan.php" class="sidebar-link">
            <span class="sidebar-icon">💸</span>
            <span>Penarikan Tabungan</span>
        </a>

        <a href="riwayat_penarikan.php" class="sidebar-link">
            <span class="sidebar-icon">📋</span>
            <span>Riwayat Penarikan</span>
        </a>

        <a href="notifikasi.php" class="sidebar-link">
            <span class="sidebar-icon">🔔</span>
            <span>Notifikasi</span>
        </a>

        <div class="sidebar-section-title">Akun</div>

        <a href="profil.php" class="sidebar-link">
            <span class="sidebar-icon">⚙️</span>
            <span>Profil Saya</span>
        </a>

        <a href="aktivitas.php" class="sidebar-link active">
            <span class="sidebar-icon">🕘</span>
            <span>Aktivitas Saya</span>
        </a>

        <a href="../auth/logout.php" class="sidebar-link sidebar-logout">
            <span class="sidebar-icon">🚪</span>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- MAIN -->
<main class="warga-main">

    <!-- HEADER -->
    <div class="page-header-row">
        <div class="warga-heading">
            <h1>Aktivitas Saya</h1>
            <p>Lihat riwayat aktivitas yang berkaitan dengan akun Anda.</p>
        </div>

        <div class="header-actions">
            <button
                type="button"
                class="mobile-menu-button"
                id="mobileMenuButton"
                aria-label="Buka menu"
                aria-expanded="false"
            >☰</button>

            <a href="profil.php" class="btn-kembali">
                ← Kembali ke Profil
            </a>
        </div>
    </div>

    <!-- FILTER -->
    <section class="filter-card">

        <div class="filter-title">Filter Aktivitas</div>

        <form method="GET" class="filter-form">

            <div class="filter-group">
                <label for="keyword">Cari aktivitas</label>
                <input
                    type="text"
                    id="keyword"
                    name="keyword"
                    value="<?= e($keyword) ?>"
                    placeholder="Cari aktivitas..."
                >
            </div>

            <div class="filter-group">
                <label for="aktivitas">Jenis aktivitas</label>

                <select id="aktivitas" name="aktivitas">
                    <option value="">Semua aktivitas</option>

                    <?php foreach ($jenis_aktivitas as $jenis): ?>
                        <option
                            value="<?= e($jenis) ?>"
                            <?= $aktivitas_filter === $jenis ? 'selected' : '' ?>
                        >
                            <?= e($jenis) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="tanggal_dari">Dari tanggal</label>
                <input
                    type="date"
                    id="tanggal_dari"
                    name="tanggal_dari"
                    value="<?= e($tanggal_dari) ?>"
                >
            </div>

            <div class="filter-group">
                <label for="tanggal_sampai">Sampai tanggal</label>
                <input
                    type="date"
                    id="tanggal_sampai"
                    name="tanggal_sampai"
                    value="<?= e($tanggal_sampai) ?>"
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
    </section>

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
                <div class="empty-icon">✓</div>

                <h4>Belum ada aktivitas</h4>

                <p>
                    Aktivitas akun Anda akan muncul di halaman ini.
                </p>
            </div>

        <?php else: ?>

            <div class="timeline">

                <?php foreach ($aktivitas_data as $item): ?>

                    <div class="activity-item">

                        <div class="activity-icon <?= e(getClassAktivitas($item['aktivitas'])) ?>">
                            <?= e(getIconAktivitas($item['aktivitas'])) ?>
                        </div>

                        <div class="activity-content">

                            <div class="activity-title">
                                <?= e($item['aktivitas']) ?>
                            </div>

                            <?php if (!empty($item['keterangan'])): ?>
                                <div class="activity-description">
                                    <?= e($item['keterangan']) ?>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const menuButton = document.getElementById('mobileMenuButton');
    const sidebar = document.getElementById('wargaSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (menuButton && sidebar && overlay) {

        function tutupMenu() {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            menuButton.innerHTML = '☰';
            menuButton.setAttribute('aria-expanded', 'false');
        }

        menuButton.addEventListener('click', function () {
            const terbuka = sidebar.classList.toggle('open');

            overlay.classList.toggle('show', terbuka);
            menuButton.innerHTML = terbuka ? '✕' : '☰';
            menuButton.setAttribute('aria-expanded', String(terbuka));
        });

        overlay.addEventListener('click', tutupMenu);

        sidebar.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', tutupMenu);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                tutupMenu();
            }
        });
    }
});
</script>

</body>
</html>
