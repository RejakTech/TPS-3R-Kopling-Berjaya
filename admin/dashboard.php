<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

// ========================================
// NOTIFIKASI ADMIN
// ========================================

$jumlah_notifikasi = 0;

$query_notifikasi = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM notifikasi
     WHERE user_id = " . (int)$_SESSION['user_id'] . "
     AND dibaca = 0"
);

if ($query_notifikasi) {
    $data_notifikasi = mysqli_fetch_assoc($query_notifikasi);

    if ($data_notifikasi) {
        $jumlah_notifikasi = (int)$data_notifikasi['total'];
    }
}

// Ambil 5 notifikasi terbaru
$query_preview_notifikasi = mysqli_query(
    $conn,
    "SELECT id, judul, pesan, tipe, dibaca, tanggal_dibuat, link
     FROM notifikasi
     WHERE user_id = " . (int)$_SESSION['user_id'] . "
     ORDER BY id DESC
     LIMIT 5"
);

// ========================================
// STATISTIK UTAMA
// ========================================

// Total warga
$query_warga = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'warga'"
);

$total_warga = mysqli_fetch_assoc($query_warga)['total'];

// Total petugas
$query_petugas = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'petugas'"
);

$total_petugas = mysqli_fetch_assoc($query_petugas)['total'];

// Total pengajuan setoran
$query_setoran = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM setoran_sampah"
);

$total_setoran = mysqli_fetch_assoc($query_setoran)['total'];

// Total berat sampah diterima
$query_sampah = mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(berat_kg), 0) AS total
     FROM setoran_sampah
     WHERE status = 'diterima'"
);

$total_sampah = mysqli_fetch_assoc($query_sampah)['total'];


// ========================================
// GRAFIK BERAT DAN NILAI EKONOMI BULANAN
// ========================================

$bulanLabels = [
    'Januari', 'Februari', 'Maret',
    'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September',
    'Oktober', 'November', 'Desember'
];

$beratBulanan = array_fill(1, 12, 0);
$nilaiBulanan = array_fill(1, 12, 0);
$wargaAktifBulanan = array_fill(1, 12, 0);

$tahunSekarang = (int)date('Y');

$tahun = isset($_GET['tahun'])
    ? (int)$_GET['tahun']
    : $tahunSekarang;

if ($tahun < 2020 || $tahun > $tahunSekarang) {
    $tahun = $tahunSekarang;
}

$query_bulanan = mysqli_query(
    $conn,
    "SELECT
        MONTH(tanggal_setor) AS bulan,
        COALESCE(SUM(berat_kg), 0) AS total_berat,
        COALESCE(SUM(total_harga), 0) AS total_nilai,
        COUNT(DISTINCT user_id) AS warga_aktif
     FROM setoran_sampah
     WHERE status = 'diterima'
       AND YEAR(tanggal_setor) = $tahun
     GROUP BY MONTH(tanggal_setor)
     ORDER BY MONTH(tanggal_setor)"
);

if ($query_bulanan) {
    while ($data = mysqli_fetch_assoc($query_bulanan)) {

        $bulan = (int)$data['bulan'];

        $beratBulanan[$bulan] = (float)$data['total_berat'];
        $nilaiBulanan[$bulan] = (float)$data['total_nilai'];
        $wargaAktifBulanan[$bulan] = (int)$data['warga_aktif'];
    }
}


// ========================================
// GRAFIK KATEGORI SAMPAH
// ========================================

$kategoriLabels = [];
$kategoriBerat = [];

$query_kategori = mysqli_query(
    $conn,
    "SELECT
        COALESCE(k.nama_kategori, 'Tidak diketahui') AS nama_kategori,
        COALESCE(SUM(s.berat_kg), 0) AS total_berat
     FROM setoran_sampah s
     LEFT JOIN kategori_sampah k
        ON s.kategori_id = k.id
     WHERE s.status = 'diterima'
     GROUP BY s.kategori_id, k.nama_kategori
     ORDER BY total_berat DESC
     LIMIT 5"
);

if ($query_kategori) {

    while ($data = mysqli_fetch_assoc($query_kategori)) {

        $kategoriLabels[] = $data['nama_kategori'];
        $kategoriBerat[] = (float)$data['total_berat'];
    }
}


// ========================================
// FORMAT DATA CHART.JS
// ========================================

$labelsJson = json_encode(
    $bulanLabels,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);

$beratJson = json_encode(array_values($beratBulanan));
$nilaiJson = json_encode(array_values($nilaiBulanan));
$wargaJson = json_encode(array_values($wargaAktifBulanan));

$kategoriLabelsJson = json_encode(
    $kategoriLabels,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);

$kategoriBeratJson = json_encode($kategoriBerat);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Admin - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>

        :root {
            --primary: #0f766e;
            --primary-dark: #145b53;
            --primary-soft: #e8f5f1;
            --gold: #d4af37;

            --bg: #f4f8f7;
            --text: #173f3a;
            --muted: #71817c;

            --border: #e4ece9;

            --white: #ffffff;

            --sidebar-width: 250px;
        }


        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            background: var(--bg);

            color: var(--text);

            font-family: Arial, sans-serif;
        }


        /* ==========================================
           SIDEBAR
        ========================================== */

        .admin-sidebar {
            position: fixed;

            left: 0;
            top: 0;
            bottom: 0;

            width: var(--sidebar-width);

            background: linear-gradient(
                180deg,
                #071F33 0%,
                #0B2D46 100%
            );

            border-right: 1px solid rgba(255, 255, 255, 0.08);

            z-index: 1000;

            display: flex;

            flex-direction: column;

            overflow-y: auto;
        }


        .sidebar-brand {
    padding: 24px 22px 20px;

    border-bottom: 1px solid rgba(255, 255, 255, 0.08);

    background: rgba(255, 255, 255, 0.02);
}


        .sidebar-brand-top {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar-logo {
    width: 48px;
    height: 48px;
    min-width: 45px;
    border-radius: 50%;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}

.sidebar-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 2px;
}

.sidebar-brand-top > div:last-child {
    flex: 1;
    min-width: 0;
}

.sidebar-brand h2 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #ffffff;
    line-height: 1.15;
}

.sidebar-brand p {
    margin: 5px 0 0;
    color: #8FB3C2;
    font-size: 10px;
    line-height: 1.25;
}


        .sidebar-nav {

            padding: 20px 14px;

            flex: 1;
        }


       .sidebar-section-title {
    padding: 0 12px;

    margin: 12px 0 8px;

    font-size: 10px;

    font-weight: 800;

    letter-spacing: .09em;

    color: #6F98A9;

    text-transform: uppercase;
}


       .sidebar-link {
    display: flex;

    align-items: center;

    gap: 12px;

    width: 100%;

    padding: 11px 12px;

    margin: 3px 0;

    border-radius: 10px;

    color: #B8CDD7;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;

    transition: .18s ease;
}


       .sidebar-link:hover {
    background: rgba(24, 195, 216, 0.10);

    color: #ffffff;

    transform: translateX(2px);
}


       .sidebar-link.active {
    background: rgba(24, 195, 216, 0.14);

    color: #18C3D8;

    box-shadow: inset 3px 0 0 #18C3D8;
}


        .sidebar-icon {

            width: 22px;

            text-align: center;

            font-size: 16px;

            flex: 0 0 22px;
        }


        .sidebar-bottom {

            padding: 14px;

            border-top: 1px solid var(--border);
        }


        .sidebar-user {

            display: flex;

            align-items: center;

            gap: 10px;

            padding: 10px 12px;

            margin-bottom: 8px;

            border-radius: 10px;

            background: #f7faf9;
        }


        .sidebar-user-avatar {

            width: 32px;
            height: 32px;

            border-radius: 50%;

            background: var(--primary-soft);

            color: var(--primary);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 14px;

            font-weight: 700;
        }


        .sidebar-user-info {

            min-width: 0;
        }


       .sidebar-user-info strong {
    display: block;

    font-size: 12px;

    color: #ffffff;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


        .sidebar-user-info span {

            display: block;

            font-size: 10px;

            color: #8b9995;

            margin-top: 2px;
        }


        /* ==========================================
           MAIN
        ========================================== */

        .admin-main {
    margin-left: var(--sidebar-width);
    min-height: 100vh;
    background: #f4f9f8;
}


        /* ==========================================
           TOPBAR
        ========================================== */

        .admin-topbar {

            height: 76px;

            padding: 0 34px;

            background: #ffffff;

            border-bottom: 1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            position: sticky;

            top: 0;

            z-index: 900;
        }


        .topbar-title h1 {

            margin: 0;

            color: var(--primary-dark);

            font-size: 21px;
        }


        .topbar-title p {

            margin: 4px 0 0;

            color: var(--muted);

            font-size: 12px;
        }


        .topbar-actions {

            display: flex;

            align-items: center;

            gap: 12px;
        }


        /* ==========================================
           NOTIFIKASI
        ========================================== */

        .admin-notification-wrapper {

            position: relative;
        }


        .admin-notification {

            position: relative;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            width: 42px;
            height: 42px;

            border: 1px solid #dce9e5;

            border-radius: 11px;

            background: #f7fbfa;

            color: var(--primary);

            cursor: pointer;

            font-size: 18px;
        }


        .admin-notification:hover {

            background: var(--primary-soft);
        }


        .admin-notification-badge {

            position: absolute;

            top: -5px;
            right: -5px;

            min-width: 19px;

            height: 19px;

            padding: 0 5px;

            border-radius: 20px;

            background: #dc3545;

            color: #fff;

            font-size: 10px;

            font-weight: 700;

            display: flex;

            align-items: center;

            justify-content: center;

            border: 2px solid #fff;
        }


        .admin-notification-dropdown {

            display: none;

            position: absolute;

            right: 0;

            top: 52px;

            width: 350px;

            background: #fff;

            border: 1px solid var(--border);

            border-radius: 14px;

            box-shadow: 0 15px 40px rgba(25,70,62,.14);

            overflow: hidden;

            z-index: 1200;
        }


        .admin-notification-dropdown.show {

            display: block;
        }


        .admin-notification-dropdown-header {

            padding: 15px 16px;

            display: flex;

            justify-content: space-between;

            gap: 10px;

            border-bottom: 1px solid var(--border);
        }


        .admin-notification-dropdown-header strong {

            display: block;

            color: var(--text);

            font-size: 13px;
        }


        .admin-notification-dropdown-header span {

            display: block;

            color: #899793;

            font-size: 10px;

            margin-top: 3px;
        }


        .admin-notification-dropdown-header a {

            color: var(--primary);

            font-size: 11px;

            font-weight: 700;

            text-decoration: none;
        }


        .admin-notification-preview {

            max-height: 330px;

            overflow: auto;
        }


        .admin-notification-preview-item {

            display: flex;

            gap: 10px;

            padding: 13px 16px;

            color: inherit;

            text-decoration: none;

            border-bottom: 1px solid #f0f4f2;
        }


        .admin-notification-preview-item:hover {

            background: #f8fbfa;
        }


        .admin-notification-preview-item.belum-dibaca {

            background: #f0f8f6;
        }


        .admin-notification-preview-icon {

            width: 30px;
            height: 30px;

            border-radius: 9px;

            background: var(--primary-soft);

            color: var(--primary);

            display: flex;

            align-items: center;

            justify-content: center;

            flex: 0 0 30px;
        }


        .admin-notification-preview-content {

            min-width: 0;
        }


        .admin-notification-preview-content strong {

            display: block;

            font-size: 11px;

            color: var(--text);
        }


        .admin-notification-preview-content p {

            margin: 3px 0;

            font-size: 10px;

            color: #75837f;

            line-height: 1.4;
        }


        .admin-notification-preview-content small {

            font-size: 9px;

            color: #a0aaa7;
        }


        .admin-notification-empty {

            padding: 30px 15px;

            text-align: center;

            color: #8b9895;
        }


        .admin-notification-empty div {

            font-size: 25px;

            margin-bottom: 7px;
        }


        .admin-notification-empty p {

            margin: 0;

            font-size: 11px;
        }


        .admin-notification-footer {

            display: block;

            padding: 12px;

            text-align: center;

            background: #fafcfb;

            color: var(--primary);

            text-decoration: none;

            font-size: 11px;

            font-weight: 700;
        }


        .admin-profile {

            display: flex;

            align-items: center;

            gap: 12px;

            font-size: 12px;

            color: #65736f;
        }


        .admin-profile a {

            color: var(--primary) !important;

            text-decoration: none;

            font-weight: 700;
        }


        .logout-btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 9px 13px !important;

            border-radius: 9px !important;

            background: #fdf1f1 !important;

            color: #c0392b !important;

            border: none !important;

            font-size: 11px !important;
        }


        /* ==========================================
           CONTENT
        ========================================== */

        .admin-content {

            padding: 30px 34px 45px;

            max-width: 1500px;

            margin: 0 auto;
        }


        /* ==========================================
           STATISTIK UTAMA
        ========================================== */

        .admin-statistics {

            display: grid;

            grid-template-columns: repeat(4, minmax(0, 1fr));

            gap: 16px;

            margin-bottom: 30px;
        }


        .admin-stat-card {

            background: #fff;

            border: 1px solid var(--border);

            border-radius: 14px;

            padding: 20px;

            box-shadow: 0 4px 14px rgba(20,83,69,.035);
        }


        .admin-stat-card h3 {

            margin: 0 0 8px;

            font-size: 11px;

            color: #84918e;

            font-weight: 700;
        }


        .admin-stat-card p {

            margin: 0;

            color: var(--primary-dark);

            font-size: 27px;

            font-weight: 800;
        }


        /* ==========================================
           STATISTIK PERKEMBANGAN
        ========================================== */

        .dashboard-statistik {

            margin-top: 5px;
        }


        .dashboard-statistik-heading {

            margin-bottom: 18px;
        }


        .dashboard-statistik-heading h2 {

            font-size: 19px;

            color: var(--primary-dark);

            margin: 0 0 5px;
        }


        .dashboard-statistik-heading p {

            color: var(--muted);

            font-size: 12px;

            margin: 0;
        }


        .filter-statistik {

            display: flex;

            align-items: center;

            gap: 10px;

            margin: 14px 0 18px;
        }


        .filter-statistik label {

            font-size: 11px;

            color: #718783;

            font-weight: 700;
        }


        .filter-statistik select {

            padding: 8px 12px;

            border: 1px solid #dce8e5;

            border-radius: 8px;

            background: #fff;

            color: #145b53;

            font-size: 11px;

            font-weight: 600;

            cursor: pointer;
        }


        .dashboard-statistik-grid {

            display: grid;

            grid-template-columns: repeat(2, minmax(0, 1fr));

            gap: 16px;
        }


        .dashboard-chart-card {

            background: #fff;

            border: 1px solid var(--border);

            border-radius: 14px;

            padding: 20px;

            min-width: 0;

            box-shadow: 0 4px 14px rgba(20,83,69,.035);
        }


        .dashboard-chart-card h3 {

            color: var(--primary-dark);

            font-size: 14px;

            margin: 0 0 5px;
        }


        .dashboard-chart-card p {

            color: #81908b;

            font-size: 11px;

            margin: 0 0 16px;
        }


        .dashboard-chart-wrapper {

            position: relative;

            width: 100%;

            height: 260px;
        }


        /* ==========================================
           MOBILE
        ========================================== */

        .mobile-menu-button {

            display: none;

            width: 40px;
            height: 40px;

            border: 1px solid #dce9e5;

            border-radius: 10px;

            background: #fff;

            color: var(--primary);

            cursor: pointer;

            font-size: 18px;
        }


        .sidebar-overlay {

            display: none;
        }


        @media (max-width: 1100px) {

            .admin-statistics {

                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }


        @media (max-width: 800px) {

    /* SIDEBAR TETAP TAMPIL */
    .admin-sidebar {
        position: fixed;

        left: 0;
        top: 0;
        bottom: 0;

        width: 250px;

        transform: none;

        display: flex;

        overflow-y: auto;

        z-index: 1000;
    }


    /* KONTEN TETAP BERGESER KE KANAN */
    .admin-main {
        margin-left: 250px;
        width: calc(100% - 250px);
    }


    /* TOPBAR */
    .admin-topbar {
        height: 68px;

        padding: 0 18px;
    }


    .topbar-title h1 {
        font-size: 17px;
    }


    .topbar-title p {
        display: none;
    }


    .admin-profile {
        display: none;
    }


    /* KONTEN */
    .admin-content {
        padding: 30px;
    }


    /* STATISTIK */
    .admin-statistics {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }


    /* GRAFIK */
    .dashboard-statistik-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }


    .dashboard-chart-wrapper {
        height: 240px;
    }


    /* TOMBOL MENU TIDAK DIPERLUKAN */
    .mobile-menu-button {
        display: none;
    }


    /* OVERLAY TIDAK DIGUNAKAN */
    .sidebar-overlay {
        display: none !important;
    }
}


        @media (max-width: 520px) {

    .admin-statistics {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .admin-stat-card {
        padding: 15px;
    }

    .admin-stat-card p {
        font-size: 21px;
    }

    .admin-content {
        padding: 20px;
    }

    .admin-notification-dropdown {
        position: fixed;

        top: 63px;
        right: 12px;
        left: 262px;

        width: auto;
    }

    .dashboard-chart-wrapper {
        height: 225px;
    }

    .filter-statistik {
        align-items: flex-start;
        flex-direction: column;
    }
}

/* =========================================================
   FIX DASHBOARD - SAMAKAN DENGAN HALAMAN ADMIN LAIN
========================================================= */

/* AREA UTAMA */
.admin-main {
    margin-left: 250px !important;
    width: calc(100% - 250px) !important;
    min-height: 100vh;
    background: #f4f9f8;
    box-sizing: border-box;
}


/* KONTEN */
.admin-content {
    width: 100% !important;
    max-width: none !important;

    margin: 0 !important;

    padding: 30px !important;

    box-sizing: border-box;
}


/* =========================================================
   SIDEBAR
========================================================= */

.admin-sidebar {
    position: fixed !important;

    left: 0 !important;
    top: 0 !important;
    bottom: 0 !important;

    width: 250px !important;

    background: linear-gradient(
        180deg,
        #071F33 0%,
        #0B2D46 100%
    ) !important;

    border-right: none !important;

    z-index: 1000;

    display: flex !important;
    flex-direction: column;

    overflow-y: auto;

    box-sizing: border-box;
}


/* BRAND */

.sidebar-brand {
    padding: 24px 22px 20px !important;

    border-bottom: 1px solid rgba(255,255,255,.08) !important;
}

.sidebar-brand-top {
    display: flex;

    align-items: center;

    gap: 10px;
}

.sidebar-logo {
    width: 48px !important;
    height: 48px !important;
    min-width: 48px !important;

    border-radius: 50% !important;

    background: #ffffff !important;

    display: flex;

    align-items: center;
    justify-content: center;

    overflow: hidden;

    flex-shrink: 0;
}

.sidebar-logo img {
    width: 100%;
    height: 100%;

    object-fit: contain;

    padding: 2px;
}

.sidebar-brand h2 {
    margin: 0 !important;

    color: #ffffff !important;

    font-size: 15px !important;

    font-weight: 700;

    line-height: 1.15;
}

.sidebar-brand p {
    margin: 5px 0 0 !important;

    color: #8FB3C2 !important;

    font-size: 10px !important;

    line-height: 1.25;
}


/* NAVIGASI */

.sidebar-nav {
    padding: 14px 12px 24px !important;

    box-sizing: border-box;

    flex: 1;
}

.sidebar-section-title {
    padding: 0 12px !important;

    margin: 12px 0 8px !important;

    color: #6F98A9 !important;

    font-size: 10px !important;

    font-weight: 800;

    letter-spacing: .09em;

    text-transform: uppercase;
}

.sidebar-link {
    display: flex !important;

    align-items: center;

    gap: 12px;

    width: 100%;

    padding: 11px 12px !important;

    margin: 3px 0 !important;

    border-radius: 10px;

    color: #B8CDD7 !important;

    text-decoration: none;

    font-size: 13px !important;

    font-weight: 600;

    box-sizing: border-box;

    transition: .18s ease;
}

.sidebar-link:hover {
    background: rgba(24,195,216,.10) !important;

    color: #ffffff !important;
}

.sidebar-link.active {
    background: rgba(24,195,216,.14) !important;

    color: #18C3D8 !important;

    box-shadow: inset 3px 0 0 #18C3D8;
}

.sidebar-icon {
    width: 22px;

    text-align: center;

    font-size: 16px;

    flex: 0 0 22px;
}


/* =========================================================
   STATISTIK
========================================================= */

.admin-statistics {
    width: 100% !important;

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 16px;

    margin-bottom: 30px;
}

.admin-stat-card {
    min-width: 0;

    background: #ffffff;

    border: 1px solid #e4ece9;

    border-radius: 14px;

    padding: 20px;

    box-sizing: border-box;

    box-shadow:
        0 4px 14px rgba(20,83,69,.035);
}


/* =========================================================
   GRAFIK
========================================================= */

.dashboard-statistik {
    width: 100% !important;

    margin-top: 5px;
}

.dashboard-statistik-grid {
    width: 100% !important;

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 16px;
}

.dashboard-chart-card {
    min-width: 0;

    box-sizing: border-box;
}


/* =========================================================
   MOBILE / LAYAR SEMPIT
   SIDEBAR TETAP TAMPIL
========================================================= */

@media (max-width: 1100px) {

    .admin-statistics {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}


@media (max-width: 800px) {

    .admin-main {
        margin-left: 250px !important;

        width: calc(100% - 250px) !important;
    }

    .admin-sidebar {
        transform: none !important;

        width: 250px !important;
    }

    .admin-content {
        width: 100% !important;

        padding: 30px !important;
    }

    .mobile-menu-button {
        display: none !important;
    }

    .sidebar-overlay {
        display: none !important;
    }

    .admin-topbar {
        height: 68px;

        padding: 0 20px;
    }

    .topbar-title h1 {
        font-size: 19px;
    }

    .topbar-title p {
        display: none;
    }

    .admin-profile {
        display: none;
    }

    /*
       Tetap 2 kolom supaya ukurannya
       seperti halaman Data Setoran
    */
    .admin-statistics {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 16px;
    }

    .dashboard-statistik-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 16px;
    }

    .dashboard-chart-wrapper {
        height: 240px;
    }
}


@media (max-width: 520px) {

    .admin-content {
        padding: 22px !important;
    }

    .admin-statistics {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 12px;
    }

    .admin-stat-card {
        padding: 16px;
    }

    .admin-stat-card p {
        font-size: 22px;
    }

    .dashboard-statistik-grid {
        grid-template-columns: 1fr;
    }
}

/* =====================================================
   DASHBOARD ADMIN - MODERN NAVY TEAL CYAN
   PENYESUAIAN TAMPILAN TANPA MENGUBAH PHP
===================================================== */

:root {
    --primary: #0B354D;
    --primary-dark: #082B40;
    --primary-soft: #EAF3F6;
    --accent: #159BAE;
    --cyan: #17B8C8;
    --bg: #F4F9F8;
    --text: #0B4650;
    --muted: #718995;
    --border: #E1EAED;
}

/* AREA UTAMA */
.admin-main {
    margin-left: 250px !important;
    width: calc(100% - 250px) !important;
    min-height: 100vh;
    background: var(--bg);
    box-sizing: border-box;
}

.admin-content {
    width: 100%;
    max-width: none !important;
    margin: 0 !important;
    padding: 32px 36px 45px !important;
    box-sizing: border-box;
}

/* TOPBAR */
.admin-topbar {
    height: 76px;
    padding: 0 36px;
    background: #FFFFFF;
    border-bottom: 1px solid var(--border);
    box-shadow: 0 2px 10px rgba(11, 53, 77, .025);
}

.topbar-title h1 {
    color: var(--primary);
    font-size: 21px;
    font-weight: 700;
}

.topbar-title p {
    color: var(--muted);
}

/* NOTIFIKASI */
.admin-notification {
    border: 1px solid #DCE8EC;
    background: #F4F9FA;
    color: var(--primary);
    border-radius: 11px;
    transition: .2s ease;
}

.admin-notification:hover {
    background: var(--primary-soft);
    border-color: #BBDDE3;
    transform: translateY(-2px);
}

.admin-notification-dropdown {
    border-radius: 14px;
    box-shadow: 0 12px 35px rgba(11, 53, 77, .13);
}

/* KARTU STATISTIK */
.admin-statistics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 34px;
}

.admin-stat-card {
    position: relative;
    overflow: hidden;
    min-width: 0;
    background: #FFFFFF;
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 22px;
    box-shadow: 0 4px 15px rgba(11, 53, 77, .045);
    transition: .25s ease;
}

.admin-stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--primary), var(--cyan));
}

.admin-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(11, 53, 77, .09);
    border-color: #C9E1E5;
}

.admin-stat-card h3 {
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 12px;
}

.admin-stat-card p {
    color: var(--primary);
    font-size: 29px;
    font-weight: 800;
    line-height: 1.2;
    overflow-wrap: anywhere;
}

/* AKSEN SETIAP KARTU */
.admin-stat-card:nth-child(1)::before {
    background: #159BAE;
}

.admin-stat-card:nth-child(2)::before {
    background: #0B354D;
}

.admin-stat-card:nth-child(3)::before {
    background: #17B8C8;
}

.admin-stat-card:nth-child(4)::before {
    background: #D4AF37;
}

/* HEADING STATISTIK */
.dashboard-statistik {
    margin-top: 8px;
}

.dashboard-statistik-heading {
    margin-bottom: 20px;
}

.dashboard-statistik-heading h2 {
    color: var(--primary);
    font-size: 21px;
    font-weight: 700;
    margin-bottom: 7px;
}

.dashboard-statistik-heading p {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.6;
}

/* FILTER TAHUN */
.filter-statistik {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 18px 0 22px;
}

.filter-statistik label {
    color: var(--primary);
    font-size: 12px;
    font-weight: 700;
}

.filter-statistik select {
    min-width: 125px;
    padding: 10px 14px;
    background: #FFFFFF;
    color: var(--primary);
    border: 1px solid #DCE8EC;
    border-radius: 9px;
    font-size: 12px;
    outline: none;
}

.filter-statistik select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(21, 155, 174, .10);
}

/* GRID GRAFIK */
.dashboard-statistik-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.dashboard-chart-card {
    min-width: 0;
    background: #FFFFFF;
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 23px;
    box-shadow: 0 4px 15px rgba(11, 53, 77, .04);
    transition: .25s ease;
}

.dashboard-chart-card:hover {
    border-color: #C9E1E5;
    box-shadow: 0 8px 22px rgba(11, 53, 77, .07);
}

.dashboard-chart-card h3 {
    color: var(--primary);
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 7px;
}

.dashboard-chart-card p {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
    margin-bottom: 20px;
}

.dashboard-chart-wrapper {
    position: relative;
    width: 100%;
    height: 270px;
}

/* SIDEBAR DESKTOP */
.admin-sidebar {
    width: 250px !important;
    background: linear-gradient(180deg, #071F33, #0B2D46) !important;
    box-shadow: 3px 0 15px rgba(0, 0, 0, .04);
}

.sidebar-bottom {
    border-top: 1px solid rgba(255,255,255,.10) !important;
}

.sidebar-user {
    background: rgba(255,255,255,.07) !important;
    border: 1px solid rgba(255,255,255,.07);
}

.sidebar-user-avatar {
    background: rgba(23,184,200,.15) !important;
    color: #17B8C8 !important;
}

.sidebar-user-info strong {
    color: #FFFFFF !important;
}

.sidebar-user-info span {
    color: #A4BBC6 !important;
}

/* =====================================================
   RESPONSIVE TABLET
===================================================== */
@media (max-width: 1100px) {
    .admin-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .admin-content {
        padding: 28px !important;
    }
}

/* =====================================================
   RESPONSIVE HP - SIDEBAR HAMBURGER
===================================================== */
@media (max-width: 800px) {

    .admin-sidebar {
        position: fixed !important;
        left: 0 !important;
        top: 0 !important;
        bottom: 0 !important;
        width: 250px !important;
        transform: translateX(-100%) !important;
        transition: transform .28s ease;
        z-index: 1100;
    }

    .admin-sidebar.open {
        transform: translateX(0) !important;
    }

    .admin-main {
        margin-left: 0 !important;
        width: 100% !important;
    }

    .admin-topbar {
        height: 68px;
        padding: 0 18px;
    }

    .topbar-title h1 {
        font-size: 18px;
    }

    .topbar-title p {
        display: none;
    }

    .mobile-menu-button {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        background: #EAF3F6;
        color: #0B354D;
        border: 1px solid #DCE8EC;
        border-radius: 10px;
    }

    .admin-profile {
        display: none;
    }

    .admin-content {
        padding: 24px 20px !important;
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(5, 24, 37, .48);
        z-index: 1050;
    }

    .sidebar-overlay.show {
        display: block !important;
    }

    .admin-notification-dropdown {
        position: fixed;
        top: 75px;
        right: 12px;
        left: auto;
        width: min(350px, calc(100vw - 24px));
    }

    .admin-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .dashboard-statistik-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .dashboard-chart-wrapper {
        height: 230px;
    }
}

/* =====================================================
   RESPONSIVE HP KECIL
===================================================== */
@media (max-width: 520px) {

    .admin-topbar {
        padding: 0 13px;
        gap: 8px;
    }

    .topbar-title h1 {
        font-size: 16px;
    }

    .admin-content {
        padding: 20px 14px !important;
    }

    .admin-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 11px;
        margin-bottom: 27px;
    }

    .admin-stat-card {
        padding: 17px 14px;
        border-radius: 12px;
    }

    .admin-stat-card h3 {
        font-size: 10px;
        line-height: 1.5;
    }

    .admin-stat-card p {
        font-size: 21px;
    }

    .dashboard-statistik-heading h2 {
        font-size: 18px;
    }

    .dashboard-statistik-heading p {
        font-size: 11px;
    }

    .filter-statistik {
        align-items: flex-start;
        flex-direction: column;
        gap: 8px;
    }

    .filter-statistik select {
        width: 100%;
    }

    .dashboard-statistik-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .dashboard-chart-card {
        padding: 17px 14px;
    }

    .dashboard-chart-card h3 {
        font-size: 13px;
    }

    .dashboard-chart-wrapper {
        height: 230px;
    }
}

/* HP SANGAT KECIL */
@media (max-width: 360px) {
    .admin-stat-card p {
        font-size: 18px;
    }

    .admin-stat-card {
        padding: 14px 11px;
    }

    .topbar-title h1 {
        font-size: 14px;
    }
}
/* Lonceng notifikasi biru */
.admin-notification {
    color: #159BAE !important;
    background: #EAF3F6 !important;
    border: 1px solid #D5E8ED !important;
    transition: all 0.3s ease;
}

.admin-notification:hover {
    color: #0B354D !important;
    background: #DCEFF3 !important;
}

/* Ikon lonceng */
.admin-notification i,
.admin-notification span {
    color: #159BAE !important;
}
/* Judul Dashboard Admin */
.topbar-title {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 7px;
}

.topbar-title h1 {
    font-family: 'Poppins', sans-serif;
    font-size: 28px;
    font-weight: 800;
    color: #0B354D;
    letter-spacing: -0.7px;
    line-height: 1.3;
    margin: 0;
}

.topbar-title p {
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    font-weight: 400;
    color: #718995;
    line-height: 1.6;
    margin: 0;
}

/* Tampilan responsif */
@media (max-width: 600px) {
    .topbar-title h1 {
        font-size: 22px;
    }

    .topbar-title p {
        font-size: 11px;
    }
}

/* =====================================
   SIDEBAR - SEMBUNYIKAN SCROLLBAR
   ===================================== */

.warga-sidebar {
    overflow: hidden !important;
}

.sidebar-nav {
    overflow-y: auto !important;
    overflow-x: hidden !important;

    /* Firefox */
    scrollbar-width: none !important;

    /* Internet Explorer dan Edge lama */
    -ms-overflow-style: none !important;
}

/* Chrome, Edge, dan Safari */
.sidebar-nav::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
}

    </style>

</head>

<body>

<div class="admin-dashboard">

    <!-- OVERLAY MOBILE -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>


    <!-- ==========================================
         SIDEBAR
    ========================================== -->

    <aside class="admin-sidebar" id="adminSidebar">

        <!-- BRAND -->
        <div class="sidebar-brand">

            <div class="sidebar-brand-top">
                <div class="sidebar-logo">
                    <img src="../assets/images/logo-tps3r.png" alt="Logo TPS 3R">
                </div>

                <div>
                    <h2>
                        Bank Sampah TPS 3R Kopling Berjaya
                    </h2>

                    <p>
                        TPS 3R KELURAHAN PULAU KAROMAH
                    </p>
                </div>
            </div>

        </div>


        <!-- MENU -->
        <nav class="sidebar-nav">

            <div class="sidebar-section-title">
                Utama
            </div>


            <a href="dashboard.php"
               class="sidebar-link active">

                <span class="sidebar-icon">
                    🏠
                </span>

                <span>
                    Dashboard
                </span>

            </a>


            <div class="sidebar-section-title">
                Pengelolaan
            </div>


            <a href="warga.php"
               class="sidebar-link">

                <span class="sidebar-icon">
                    👥
                </span>

                <span>
                    Data Warga
                </span>

            </a>


            <a href="petugas.php"
               class="sidebar-link">

                <span class="sidebar-icon">
                    👤
                </span>

                <span>
                    Data Petugas
                </span>

            </a>


            <a href="kelola_admin.php"
               class="sidebar-link">

                <span class="sidebar-icon">
                    🛡️
                </span>

                <span>
                    Kelola Admin
                </span>

            </a>


            <a href="kategori.php"
               class="sidebar-link">

                <span class="sidebar-icon">
                    ♻️
                </span>

                <span>
                    Kategori Sampah
                </span>

            </a>


            <a href="setoran.php"
               class="sidebar-link">

                <span class="sidebar-icon">
                    📦
                </span>

                <span>
                    Data Setoran
                </span>

            </a>


            <a href="tabungan.php"
               class="sidebar-link">

                <span class="sidebar-icon">
                    💰
                </span>

                <span>
                    Tabungan Warga
                </span>

            </a>


            <a href="mutasi_tabungan.php"
               class="sidebar-link">

                <span class="sidebar-icon">
                    ↕️
                </span>

                <span>
                    Mutasi Tabungan
                </span>

            </a>


            <a href="penarikan.php"
               class="sidebar-link">

                <span class="sidebar-icon">
                    💸
                </span>

                <span>
                    Penarikan Tabungan
                </span>

            </a>


            <a href="laporan.php"
               class="sidebar-link">

                <span class="sidebar-icon">
                    📊
                </span>

                <span>
                    Laporan
                </span>

            </a>


            <a href="backup_database.php"
               class="sidebar-link">

                <span class="sidebar-icon">
                    💾
                </span>

                <span>
                    Backup Database
                </span>

            </a>
            
<a href="pengumuman.php"
   class="sidebar-link">

    <span class="sidebar-icon">
        📢
    </span>

    <span>
        Kelola Pengumuman
    </span>

</a>


            <a href="log_aktivitas.php"
               class="sidebar-link">

                <span class="sidebar-icon">
                    📋
                </span>

                <span>
                    Log Aktivitas
                </span>

            </a>


            <div class="sidebar-section-title">
                Akun
            </div>


            <a href="profil.php"
               class="sidebar-link">

                <span class="sidebar-icon">
                    ⚙️
                </span>

                <span>
                    Profil
                </span>

            </a>


            <a href="ubah_password.php"
               class="sidebar-link">

                <span class="sidebar-icon">
                    🔐
                </span>

                <span>
                    Ubah Password
                </span>

            </a>

        </nav>


        <!-- USER -->
        <div class="sidebar-bottom">

            <div class="sidebar-user">

                <div class="sidebar-user-avatar">
                    A
                </div>

                <div class="sidebar-user-info">

                    <strong>
                        <?php echo htmlspecialchars($_SESSION['nama']); ?>
                    </strong>

                    <span>
                        Administrator
                    </span>

                </div>

            </div>


            <a href="../auth/logout.php"
               class="sidebar-link">

                <span class="sidebar-icon">
                    🚪
                </span>

                <span>
                    Logout
                </span>

            </a>

        </div>

    </aside>


    <!-- ==========================================
         MAIN
    ========================================== -->

    <div class="admin-main">


        <!-- TOPBAR -->
        <header class="admin-topbar">

            <div style="display:flex;align-items:center;gap:12px;">

                <!-- MOBILE MENU -->
                <button
                    type="button"
                    class="mobile-menu-button"
                    id="mobileMenuButton"
                    aria-label="Buka menu"
                >
                    ☰
                </button>


                <div class="topbar-title">
    <h1>Dashboard Admin</h1>
    <p>Selamat datang di halaman pengelolaan Bank Sampah Digital.</p>
</div>

            </div>


            <div class="topbar-actions">


                <!-- NOTIFIKASI -->
                <div class="admin-notification-wrapper">

                    <button
                        type="button"
                        class="admin-notification"
                        id="adminNotificationButton"
                        title="Notifikasi"
                    >

                        🔔


                        <?php if ($jumlah_notifikasi > 0): ?>

                            <span class="admin-notification-badge">

                                <?php
                                echo $jumlah_notifikasi > 99
                                    ? '99+'
                                    : $jumlah_notifikasi;
                                ?>

                            </span>

                        <?php endif; ?>

                    </button>


                    <!-- DROPDOWN -->
                    <div
                        class="admin-notification-dropdown"
                        id="adminNotificationDropdown"
                    >

                        <div class="admin-notification-dropdown-header">

                            <div>

                                <strong>
                                    Notifikasi
                                </strong>

                                <span>
                                    <?php echo $jumlah_notifikasi; ?>
                                    belum dibaca
                                </span>

                            </div>


                            <a href="notifikasi.php">
                                Lihat Semua
                            </a>

                        </div>


                        <div class="admin-notification-preview">


                            <?php if (mysqli_num_rows($query_preview_notifikasi) > 0): ?>


                                <?php while ($notif = mysqli_fetch_assoc($query_preview_notifikasi)): ?>


                                    <a
                                        href="buka_notifikasi.php?id=<?php echo (int)$notif['id']; ?>"
                                        class="admin-notification-preview-item <?php echo $notif['dibaca'] == 0 ? 'belum-dibaca' : ''; ?>"
                                    >


                                        <div class="admin-notification-preview-icon">

                                            <?php

                                            if ($notif['tipe'] === 'sukses') {

                                                echo '✓';

                                            } elseif ($notif['tipe'] === 'peringatan') {

                                                echo '⚠';

                                            } else {

                                                echo '🔔';

                                            }

                                            ?>

                                        </div>


                                        <div class="admin-notification-preview-content">


                                            <strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $notif['judul']
                                                );
                                                ?>

                                            </strong>


                                            <p>

                                                <?php

                                                $pesan_preview =
                                                    strip_tags($notif['pesan']);

                                                if (strlen($pesan_preview) > 75) {

                                                    $pesan_preview =
                                                        substr(
                                                            $pesan_preview,
                                                            0,
                                                            75
                                                        ) . '...';
                                                }

                                                echo htmlspecialchars(
                                                    $pesan_preview
                                                );

                                                ?>

                                            </p>


                                            <small>

                                                <?php

                                                echo date(
                                                    'd/m/Y H:i',
                                                    strtotime(
                                                        $notif['tanggal_dibuat']
                                                    )
                                                );

                                                ?>

                                            </small>


                                        </div>

                                    </a>


                                <?php endwhile; ?>


                            <?php else: ?>


                                <div class="admin-notification-empty">

                                    <div>
                                        🔔
                                    </div>

                                    <p>
                                        Belum ada notifikasi.
                                    </p>

                                </div>


                            <?php endif; ?>


                        </div>


                        <a
                            href="notifikasi.php"
                            class="admin-notification-footer"
                        >
                            Lihat semua notifikasi →
                        </a>

                    </div>

                </div>


            </div>

        </header>


        <!-- ==========================================
             CONTENT
        ========================================== -->

        <main class="admin-content">


            <!-- ==========================================
                 STATISTIK UTAMA
            ========================================== -->

            <div class="admin-statistics">


                <div class="admin-stat-card">

                    <h3>
                        Total Warga
                    </h3>

                    <p>
                        <?php
                        echo number_format(
                            $total_warga,
                            0,
                            ',',
                            '.'
                        );
                        ?>
                    </p>

                </div>


                <div class="admin-stat-card">

                    <h3>
                        Total Petugas
                    </h3>

                    <p>
                        <?php
                        echo number_format(
                            $total_petugas,
                            0,
                            ',',
                            '.'
                        );
                        ?>
                    </p>

                </div>


                <div class="admin-stat-card">

                    <h3>
                        Total Setoran
                    </h3>

                    <p>
                        <?php
                        echo number_format(
                            $total_setoran,
                            0,
                            ',',
                            '.'
                        );
                        ?>
                    </p>

                </div>


                <div class="admin-stat-card">

                    <h3>
                        Total Sampah
                    </h3>

                    <p>

                        <?php
                        echo number_format(
                            (float)$total_sampah,
                            2,
                            ',',
                            '.'
                        );
                        ?>

                        Kg

                    </p>

                </div>


            </div>


            <!-- ==========================================
                 STATISTIK PERKEMBANGAN
            ========================================== -->

            <section class="dashboard-statistik">


                <div class="dashboard-statistik-heading">

                    <h2>
                        Statistik Perkembangan
                    </h2>


                    <p>
                        Gambaran aktivitas dan perkembangan bank sampah berdasarkan transaksi yang diterima.
                    </p>


                    <form
                        method="GET"
                        class="filter-statistik"
                    >

                        <label for="tahun">
                            Periode Statistik
                        </label>


                        <select
                            name="tahun"
                            id="tahun"
                            onchange="this.form.submit()"
                        >

                            <?php
                            for (
                                $i = $tahunSekarang;
                                $i >= 2020;
                                $i--
                            ):
                            ?>

                                <option
                                    value="<?php echo $i; ?>"
                                    <?php
                                    echo ($tahun == $i)
                                        ? 'selected'
                                        : '';
                                    ?>
                                >
                                    Tahun <?php echo $i; ?>
                                </option>

                            <?php endfor; ?>

                        </select>

                    </form>

                </div>


                <!-- CHART GRID -->
                <div class="dashboard-statistik-grid">


                    <!-- BERAT -->
                    <div class="dashboard-chart-card">

                        <h3>
                            Berat Sampah Bulanan
                        </h3>

                        <p>
                            Total berat sampah yang berhasil diterima setiap bulan.
                        </p>


                        <div class="dashboard-chart-wrapper">

                            <canvas id="grafikBerat"></canvas>

                        </div>

                    </div>


                    <!-- NILAI -->
                    <div class="dashboard-chart-card">

                        <h3>
                            Nilai Ekonomi Bulanan
                        </h3>

                        <p>
                            Total nilai ekonomi dari setoran yang diterima.
                        </p>


                        <div class="dashboard-chart-wrapper">

                            <canvas id="grafikNilai"></canvas>

                        </div>

                    </div>


                    <!-- KATEGORI -->
                    <div class="dashboard-chart-card">

                        <h3>
                            Kategori Sampah Terbanyak
                        </h3>

                        <p>
                            Lima kategori berdasarkan total berat sampah diterima.
                        </p>


                        <div class="dashboard-chart-wrapper">

                            <canvas id="grafikKategori"></canvas>

                        </div>

                    </div>


                    <!-- WARGA -->
                    <div class="dashboard-chart-card">

                        <h3>
                            Partisipasi Warga Bulanan
                        </h3>

                        <p>
                            Jumlah warga berbeda yang menyetor setiap bulan.
                        </p>


                        <div class="dashboard-chart-wrapper">

                            <canvas id="grafikWarga"></canvas>

                        </div>

                    </div>


                </div>

            </section>


        </main>

    </div>

</div>


<!-- ==========================================
     JAVASCRIPT SIDEBAR
========================================== -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const sidebar =
            document.getElementById('adminSidebar');

        const overlay =
            document.getElementById('sidebarOverlay');

        const menuButton =
            document.getElementById('mobileMenuButton');


        function openSidebar() {

            sidebar.classList.add('open');

            overlay.classList.add('show');

        }


        function closeSidebar() {

            sidebar.classList.remove('open');

            overlay.classList.remove('show');

        }


        if (menuButton) {

            menuButton.addEventListener(
                'click',
                openSidebar
            );

        }


        if (overlay) {

            overlay.addEventListener(
                'click',
                closeSidebar
            );

        }


        document
            .querySelectorAll('.sidebar-link')
            .forEach(function (link) {

                link.addEventListener(
                    'click',
                    function () {

                        if (window.innerWidth <= 800) {

                            closeSidebar();

                        }

                    }
                );

            });

    }
);

</script>


<!-- ==========================================
     JAVASCRIPT GRAFIK
========================================== -->

<script>

const labelsBulan =
    <?php echo $labelsJson ?: '[]'; ?>;

const dataBerat =
    <?php echo $beratJson ?: '[]'; ?>;

const dataNilai =
    <?php echo $nilaiJson ?: '[]'; ?>;

const dataWarga =
    <?php echo $wargaJson ?: '[]'; ?>;

const labelsKategori =
    <?php echo $kategoriLabelsJson ?: '[]'; ?>;

const dataKategori =
    <?php echo $kategoriBeratJson ?: '[]'; ?>;


// ==========================================
// KONFIGURASI UMUM
// ==========================================

Chart.defaults.font.family =
    'Arial, sans-serif';

Chart.defaults.color =
    '#71817c';


// ==========================================
// GRAFIK BERAT SAMPAH
// ==========================================

new Chart(
    document.getElementById('grafikBerat'),
    {

        type: 'bar',

        data: {

            labels: labelsBulan,

            datasets: [{

                label: 'Berat Sampah (Kg)',

                data: dataBerat,

                backgroundColor: '#159b83',

                borderRadius: 6,

                maxBarThickness: 28

            }]

        },


        options: {

            responsive: true,

            maintainAspectRatio: false,

            interaction: {

                mode: 'index',

                intersect: false

            },


            plugins: {

                legend: {

                    display: false

                },


                tooltip: {

                    callbacks: {

                        label: function(context) {

                            return ' '
                                + context.parsed.y
                                + ' Kg';

                        }

                    }

                }

            },


            scales: {

                x: {

                    grid: {

                        display: false

                    },


                    ticks: {

                        font: {

                            size: 9

                        },

                        maxRotation: 45,

                        minRotation: 45,

                        autoSkip: true,

                        maxTicksLimit: 6

                    }

                },


                y: {

                    beginAtZero: true,

                    grid: {

                        color:
                            'rgba(113, 129, 124, 0.12)'

                    },


                    ticks: {

                        font: {

                            size: 9

                        },


                        callback: function(value) {

                            return value + ' Kg';

                        }

                    }

                }

            }

        }

    }
);


// ==========================================
// GRAFIK NILAI EKONOMI
// ==========================================

new Chart(
    document.getElementById('grafikNilai'),
    {

        type: 'line',

        data: {

            labels: labelsBulan,

            datasets: [{

                label: 'Nilai Ekonomi',

                data: dataNilai,

                borderColor: '#0f766e',

                backgroundColor:
                    'rgba(15, 118, 110, 0.12)',

                fill: true,

                tension: 0.35,

                pointRadius: 3,

                pointHoverRadius: 5,

                pointBackgroundColor: '#0f766e'

            }]

        },


        options: {

            responsive: true,

            maintainAspectRatio: false,

            interaction: {

                mode: 'index',

                intersect: false

            },


            plugins: {

                legend: {

                    display: false

                },


                tooltip: {

                    callbacks: {

                        label: function(context) {

                            return ' Rp ' +

                                new Intl.NumberFormat(
                                    'id-ID'
                                ).format(
                                    context.parsed.y
                                );

                        }

                    }

                }

            },


            scales: {

                x: {

                    grid: {

                        display: false

                    },


                    ticks: {

                        font: {

                            size: 9

                        },

                        maxRotation: 45,

                        minRotation: 45,

                        autoSkip: true,

                        maxTicksLimit: 6

                    }

                },


                y: {

                    beginAtZero: true,

                    grid: {

                        color:
                            'rgba(113, 129, 124, 0.12)'

                    },


                    ticks: {

                        font: {

                            size: 8

                        },


                        callback: function(value) {

                            return 'Rp ' +

                                new Intl.NumberFormat(
                                    'id-ID'
                                ).format(value);

                        }

                    }

                }

            }

        }

    }
);


// ==========================================
// GRAFIK KATEGORI SAMPAH
// ==========================================

new Chart(
    document.getElementById('grafikKategori'),
    {

        type: 'doughnut',

        data: {

            labels: labelsKategori,

            datasets: [{

                label: 'Berat Sampah',

                data: dataKategori,

                backgroundColor: [

                    '#0f766e',

                    '#159b83',

                    '#6b9b83',

                    '#d4af37',

                    '#9bb7a8'

                ],

                borderWidth: 2,

                borderColor: '#ffffff'

            }]

        },


        options: {

            responsive: true,

            maintainAspectRatio: false,

            cutout: '62%',


            plugins: {

                legend: {

                    position: 'bottom',

                    labels: {

                        boxWidth: 12,

                        boxHeight: 12,

                        padding: 12,

                        font: {

                            size: 9

                        }

                    }

                },


                tooltip: {

                    callbacks: {

                        label: function(context) {

                            return ' ' +

                                context.label +

                                ': ' +

                                context.parsed +

                                ' Kg';

                        }

                    }

                }

            }

        }

    }
);


// ==========================================
// GRAFIK PARTISIPASI WARGA
// ==========================================

new Chart(
    document.getElementById('grafikWarga'),
    {

        type: 'line',

        data: {

            labels: labelsBulan,

            datasets: [{

                label: 'Warga Aktif',

                data: dataWarga,

                borderColor: '#d4af37',

                backgroundColor:
                    'rgba(212, 175, 55, 0.12)',

                fill: true,

                tension: 0.35,

                pointRadius: 3,

                pointHoverRadius: 5,

                pointBackgroundColor: '#d4af37'

            }]

        },


        options: {

            responsive: true,

            maintainAspectRatio: false,

            interaction: {

                mode: 'index',

                intersect: false

            },


            plugins: {

                legend: {

                    display: false

                },


                tooltip: {

                    callbacks: {

                        label: function(context) {

                            return ' ' +

                                context.parsed.y +

                                ' warga';

                        }

                    }

                }

            },


            scales: {

                x: {

                    grid: {

                        display: false

                    },


                    ticks: {

                        font: {

                            size: 9

                        },

                        maxRotation: 45,

                        minRotation: 45,

                        autoSkip: true,

                        maxTicksLimit: 6

                    }

                },


                y: {

                    beginAtZero: true,

                    grid: {

                        color:
                            'rgba(113, 129, 124, 0.12)'

                    },


                    ticks: {

                        font: {

                            size: 9

                        },

                        precision: 0

                    }

                }

            }

        }

    }
);

</script>


<!-- ==========================================
     JAVASCRIPT NOTIFIKASI
========================================== -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const button =
            document.getElementById(
                'adminNotificationButton'
            );

        const dropdown =
            document.getElementById(
                'adminNotificationDropdown'
            );


        if (!button || !dropdown) {

            return;

        }


        button.addEventListener(
            'click',
            function (event) {

                event.stopPropagation();

                dropdown.classList.toggle('show');

            }
        );


        dropdown.addEventListener(
            'click',
            function (event) {

                event.stopPropagation();

            }
        );


        document.addEventListener(
            'click',
            function () {

                dropdown.classList.remove('show');

            }
        );

    }
);

</script>


</body>

</html>