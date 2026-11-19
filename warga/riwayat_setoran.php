
<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$user_id = (int) $_SESSION['user_id'];
$nama_warga = htmlspecialchars($_SESSION['nama'] ?? 'Warga', ENT_QUOTES, 'UTF-8');
$setoran_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

/* =========================================
   1. AMBIL RIWAYAT SETORAN WARGA
========================================= */

$query = mysqli_prepare(
    $conn,
    "SELECT
        s.*,
        k.nama_kategori
    FROM setoran_sampah s
    INNER JOIN kategori_sampah k ON s.kategori_id = k.id
    WHERE s.user_id = ?
    ORDER BY s.id DESC"
);

mysqli_stmt_bind_param($query, "i", $user_id);
mysqli_stmt_execute($query);

$result = mysqli_stmt_get_result($query);

/* =========================================
   2. HITUNG RINGKASAN
========================================= */

$total_setoran = 0;
$total_berat = 0;
$total_diterima = 0;

$data_setoran = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data_setoran[] = $row;

    $total_setoran++;
    $total_berat += (float) $row['berat_kg'];

    if ($row['status'] === 'diterima') {
        $total_diterima++;
    }
}

mysqli_stmt_close($query);

/* =========================================
   3. FORMAT RUPIAH
========================================= */

function rupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

/* =========================================
   4. FORMAT TANGGAL
========================================= */

function tanggalIndonesia($tanggal)
{
    if (!$tanggal) {
        return '-';
    }

    $waktu = strtotime($tanggal);

    if (!$waktu) {
        return '-';
    }

    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April',
        'Mei', 'Juni', 'Juli', 'Agustus',
        'September', 'Oktober', 'November', 'Desember'
    ];

    return date('d', $waktu) . ' ' .
        $bulan[(int) date('n', $waktu)] . ' ' .
        date('Y', $waktu);
}

/* =========================================
   5. HELPER HTML
========================================= */

function e($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Setoran - Bank Sampah Digital</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    

    <style>
        :root {
            --navy: #071F33;
            --navy-light: #0B2D46;
            --cyan: #18C3D8;
            --teal: #159BAE;

            --bg: #F2F7FA;
            --white: #FFFFFF;
            --text: #183B55;
            --muted: #8295A2;
            --border: #E5EDF2;

            --shadow: 0 8px 28px rgba(11, 53, 77, .055);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #F1F6F9;
            font-family: sans-serif;
            color: #183B55;
        }

        a {
            text-decoration: none;
        }

        /* ==========================================
           SIDEBAR - SAMA DENGAN ADMIN
        ========================================== */

        .dashboard-layout {
            display: block;
            min-height: 100vh;
        }

        .warga-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;

            width: 250px;
            min-width: 250px;
            height: 100vh;

            padding: 0;

            background: linear-gradient(
                180deg,
                #071F33 0%,
                #0B2D46 100%
            );

            border-right: 1px solid rgba(255,255,255,.08);

            z-index: 1000;

            display: flex;
            flex-direction: column;

            overflow-y: auto;
            box-sizing: border-box;
        }

        /* BRAND */
        .sidebar-brand {
            display: block;
            padding: 24px 22px 20px;

            border-bottom: 1px solid rgba(255,255,255,.08);
            background: rgba(255,255,255,.02);
        }

        .sidebar-brand-top {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* LOGO */
        .sidebar-logo {
            width: 48px;
            height: 48px;
            min-width: 48px;

            border-radius: 50%;
            background: #FFFFFF;

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

        /* NAMA TPS 3R */
        .sidebar-brand-top > div:last-child {
            flex: 1;
            min-width: 0;
        }

        .sidebar-brand h2 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #FFFFFF;
            line-height: 1.15;
        }

        .sidebar-brand p {
            margin: 5px 0 0;
            color: #8FB3C2;
            font-size: 10px;
            line-height: 1.25;
        }

        /* NAVIGASI */
        .sidebar-nav {
            padding: 20px 14px;
            flex: 1;

            display: flex;
            flex-direction: column;
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

            box-sizing: border-box;
            transition: .18s ease;
        }

        .sidebar-link:hover {
            background: rgba(24,195,216,.10);
            color: #FFFFFF;
            transform: translateX(2px);
        }

        .sidebar-link.active {
            background: rgba(24,195,216,.14);
            color: var(--cyan);
            box-shadow: inset 3px 0 0 var(--cyan);
        }

        .sidebar-icon {
            width: 22px;
            text-align: center;
            font-size: 16px;
            flex: 0 0 22px;
        }

        .sidebar-logout {
            color: #FFE0E0;
        }

        .sidebar-logout:hover {
            background: rgba(220,53,69,.15);
            color: #FFFFFF;
        }

        /* ==========================================
           KONTEN UTAMA
        ========================================== */

        .main-area {
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;

            padding: 30px 32px 45px;

            box-sizing: border-box;
        }

        /* HEADER */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-heading h1 {
            margin: 0;
            color: var(--navy);
            font-size: 25px;
            font-weight: 800;
        }

        .page-heading p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .header-profile {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 13px 8px 8px;

            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        .header-avatar {
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;

            background: #E3F3F7;
            color: var(--teal);
            border-radius: 50%;

            font-size: 13px;
            font-weight: 800;
        }

        .header-profile span {
            color: var(--text);
            font-size: 11px;
            font-weight: 700;
        }

        /* TOMBOL KEMBALI */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            margin-bottom: 22px;
            padding: 10px 14px;

            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 9px;

            color: var(--teal);
            font-size: 11px;
            font-weight: 700;

            transition: .2s;
        }

        .back-link:hover {
            background: #E8F7F9;
            border-color: #B7E2E9;
            transform: translateY(-2px);
        }

        /* ==========================================
           KARTU RINGKASAN
        ========================================== */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 27px;
        }

        .stat-card {
            position: relative;
            display: flex;
            align-items: center;
            gap: 14px;

            min-width: 0;
            padding: 21px;

            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 15px;

            box-shadow: var(--shadow);
            overflow: hidden;

            transition: .2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            min-width: 48px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 13px;
            font-size: 22px;
        }

        .stat-icon.total {
            background: #E6F3FA;
        }

        .stat-icon.berat {
            background: #E4F6F2;
        }

        .stat-icon.diterima {
            background: #E8F6EB;
        }

        .stat-info {
            min-width: 0;
        }

        .stat-info span {
            display: block;
            color: var(--muted);
            font-size: 10px;
            font-weight: 600;
        }

        .stat-info strong {
            display: block;
            margin-top: 7px;

            color: var(--navy);
            font-size: 21px;
            font-weight: 800;

            overflow-wrap: anywhere;
        }

        /* ==========================================
           PANEL TABEL
        ========================================== */

        .table-panel {
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;

            padding: 20px 22px;
            border-bottom: 1px solid #EDF2F5;
        }

        .table-panel-title h2 {
            margin: 0;
            color: var(--navy);
            font-size: 15px;
            font-weight: 800;
        }

        .table-panel-title p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 10px;
        }

        .btn-ajukan {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            padding: 11px 15px;

            background: linear-gradient(135deg, #0B354D, #147F91);
            color: #FFFFFF;

            border-radius: 9px;
            font-size: 11px;
            font-weight: 700;

            white-space: nowrap;
            transition: .2s;
        }

        .btn-ajukan:hover {
            background: linear-gradient(135deg, #12677F, #18A8B8);
            transform: translateY(-2px);
            box-shadow: 0 5px 14px rgba(11,53,77,.15);
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            padding: 0 20px 20px;
        }

        .riwayat-table {
            width: 100%;
            min-width: 1050px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 11px;
        }

        .riwayat-table thead th {
            padding: 15px 12px;

            background: #F4F9FA;
            color: #527080;

            font-size: 10px;
            font-weight: 800;
            text-align: left;
            white-space: nowrap;

            border-bottom: 1px solid #E5EDF2;
        }

        .riwayat-table thead th:first-child {
            border-radius: 9px 0 0 9px;
        }

        .riwayat-table thead th:last-child {
            border-radius: 0 9px 9px 0;
        }

        .riwayat-table tbody td {
            padding: 16px 12px;

            color: #536D7B;
            vertical-align: middle;

            border-bottom: 1px solid #EDF2F5;
            line-height: 1.6;
        }

        .riwayat-table tbody tr:last-child td {
            border-bottom: none;
        }

        .riwayat-table tbody tr:hover {
            background: #F9FCFD;
        }

        .nomor-cell {
            color: #91A4AE !important;
            font-weight: 700;
        }

        .kategori-cell {
            color: var(--navy) !important;
            font-weight: 700;
        }

        .harga-cell {
            color: var(--navy) !important;
            font-weight: 700;
            white-space: nowrap;
        }

        /* ==========================================
           STATUS SETORAN
        ========================================== */

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;

            padding: 7px 10px;
            border-radius: 20px;

            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-menunggu {
            background: #FFF5DE;
            color: #A46B00;
        }

        .status-diterima {
            background: #E4F7ED;
            color: #16804F;
        }

        .status-ditolak {
            background: #FDECEC;
            color: #C0392B;
        }

        /* ==========================================
           ALASAN PENOLAKAN
        ========================================== */

        .alasan-penolakan {
            display: flex;
            align-items: flex-start;
            gap: 7px;

            max-width: 210px;
            padding: 9px 10px;

            background: #FFF2F0;
            border: 1px solid #F9D8D4;
            border-radius: 8px;

            color: #B63D32;
            font-size: 10px;
            line-height: 1.5;
        }

        .alasan-icon {
            width: 17px;
            height: 17px;
            min-width: 17px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #F8D9D5;
            border-radius: 50%;

            font-size: 10px;
            font-weight: 800;
        }

        .alasan-kosong {
            color: #A0AAA7;
            font-size: 10px;
        }

        .catatan-cell {
            max-width: 170px;
            overflow-wrap: anywhere;
        }

        /* ==========================================
           HIGHLIGHT DARI NOTIFIKASI
        ========================================== */

        .setoran-highlight {
            background: #EEFaf7 !important;
            box-shadow: inset 4px 0 0 #159BAE;
            animation: highlightSetoran 1.5s ease-in-out;
        }

        .setoran-highlight td {
            font-weight: 600;
        }

        @keyframes highlightSetoran {
            0% {
                background: #BCEFE4;
            }

            100% {
                background: #EEFaf7;
            }
        }

        /* ==========================================
           DATA KOSONG
        ========================================== */

        .empty-state {
            padding: 55px 20px !important;
            text-align: center !important;
        }

        .empty-icon {
            width: 65px;
            height: 65px;
            margin: 0 auto 15px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #E8F6F7;
            border-radius: 18px;

            font-size: 30px;
        }

        .empty-state strong {
            display: block;
            color: var(--navy);
            font-size: 13px;
        }

        .empty-state p {
            margin: 7px 0 15px;
            color: var(--muted);
            font-size: 11px;
        }

        .empty-action {
            display: inline-block;
            padding: 10px 14px;

            background: #E6F5F7;
            color: var(--teal);

            border-radius: 8px;
            font-size: 10px;
            font-weight: 800;
        }

        /* ==========================================
           FOOTER
        ========================================== */

        .page-footer {
            margin-top: 28px;
            padding-top: 17px;

            border-top: 1px solid #E2EBF0;

            color: #94A5AE;
            text-align: center;
            font-size: 10px;
        }

        /* ==========================================
           TABLET
        ========================================== */

        @media (max-width: 1050px) {
            .main-area {
                padding: 25px 22px 35px;
            }

            .stats-grid {
                gap: 12px;
            }

            .stat-card {
                padding: 16px;
            }
        }

        /* ==========================================
           HP
        ========================================== */

        @media (max-width: 700px) {
            .warga-sidebar {
                position: relative;
                top: auto;
                left: auto;
                bottom: auto;

                width: 100%;
                min-width: 0;
                height: auto;
                min-height: auto;

                overflow: visible;
            }

            .sidebar-brand {
                padding: 18px 20px;
            }

            .sidebar-nav {
                padding: 10px 14px 13px;
                overflow-x: auto;
            }

            .sidebar-section-title {
                display: none;
            }

            .sidebar-nav {
                flex-direction: row;
                gap: 7px;
                scrollbar-width: thin;
            }

            .sidebar-link {
                flex: 0 0 auto;
                width: auto;

                padding: 10px 12px;
                margin: 0;

                background: rgba(255,255,255,.06);

                font-size: 10px;
                white-space: nowrap;
            }

            .sidebar-link:hover {
                transform: none;
            }

            .sidebar-link.active {
                box-shadow: inset 0 -3px 0 var(--cyan);
            }

            .sidebar-icon {
                font-size: 14px;
            }

            .main-area {
                margin-left: 0;
                width: 100%;
                padding: 22px 15px 30px;
            }

            .page-header {
                margin-bottom: 18px;
            }

            .page-heading h1 {
                font-size: 20px;
            }

            .page-heading p {
                font-size: 10px;
            }

            .header-profile {
                display: none;
            }

            .back-link {
                margin-bottom: 17px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 11px;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-icon {
                width: 43px;
                height: 43px;
                min-width: 43px;
                font-size: 20px;
            }

            .stat-info strong {
                font-size: 19px;
            }

            .table-panel-header {
                align-items: flex-start;
                flex-direction: column;
                padding: 17px;
            }

            .btn-ajukan {
                width: 100%;
            }

            .table-container {
                padding: 0 12px 15px;
            }

            .riwayat-table {
                min-width: 1000px;
            }

            .page-footer {
                font-size: 9px;
            }
        }

        @media (max-width: 360px) {
            .main-area {
                padding-left: 11px;
                padding-right: 11px;
            }

            .page-heading h1 {
                font-size: 18px;
            }
        }
        /* TOMBOL KEMBALI GRADASI */
.btn-kembali {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;

    padding: 11px 18px;
    margin-top: 18px;

    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #ffffff;

    border: none;
    border-radius: 9px;

    font-size: 12px;
    font-weight: 700;
    text-decoration: none;

    box-shadow: 0 4px 12px rgba(21, 155, 174, 0.16);
    transition: all 0.25s ease;
}

.btn-kembali:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(21, 155, 174, 0.23);
}

.btn-kembali span {
    font-size: 16px;
    line-height: 1;
}

/* RESPONSIVE HP */
@media (max-width: 600px) {
    .btn-kembali {
        padding: 9px 13px;
        font-size: 11px;
    }
}
/* HEADER JUDUL DAN TOMBOL KEMBALI */
.page-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 25px;
}

.page-header-row .warga-heading {
    margin: 0;
}

.page-header-row .btn-kembali {
    flex-shrink: 0;
    margin-top: 0;
}

/* HILANGKAN PROFIL DI KANAN JIKA ADA */
.topbar-profile {
    display: none !important;
}

/* RESPONSIVE HP */
@media (max-width: 600px) {
    .page-header-row {
        align-items: flex-start;
        flex-direction: column;
        gap: 14px;
    }

    .page-header-row .btn-kembali {
        align-self: flex-start;
    }
}
/* UKURAN FONT JUDUL RIWAYAT SETORAN */
.page-header-row .warga-heading h1 {
    font-family: inherit;
    font-size: 24px;
    font-weight: 800;
    line-height: 1.3;
    color: #0B354D;
    margin: 0 0 7px;
}

.page-header-row .warga-heading p {
    font-family: inherit;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.6;
    color: #8295A2;
    margin: 0;
}

/* UKURAN FONT TOMBOL KEMBALI */
.page-header-row .btn-kembali {
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
}

/* =====================================
   HAMBURGER MENU WARGA
===================================== */

.mobile-menu-button {
    display: none;
    width: 42px;
    height: 42px;
    flex-shrink: 0;

    border: none;
    border-radius: 10px;

    background: linear-gradient(110deg, #0B354D, #17B8C8);
    color: #ffffff;

    font-size: 23px;
    cursor: pointer;

    align-items: center;
    justify-content: center;

    box-shadow: 0 4px 12px rgba(11, 53, 77, .18);
}

.sidebar-overlay {
    display: none;
}

/* =====================================
   HEADER DAN TOMBOL KEMBALI
===================================== */

.page-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    width: 100%;
    margin-bottom: 25px;
}

.page-header-row .warga-heading {
    flex: 1;
    min-width: 0;
}

.warga-title-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.page-header-row .warga-heading h1 {
    margin: 0 0 7px;
    color: #0B354D;
    font-size: 24px;
    font-weight: 800;
    line-height: 1.3;
}

.page-header-row .warga-heading p {
    margin: 0;
    color: #8295A2;
    font-size: 12px;
    line-height: 1.6;
}

.page-header-row .btn-kembali {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;

    flex: 0 0 auto;
    min-width: 220px;
    padding: 11px 18px;
    margin: 0;

    border: none;
    border-radius: 9px;

    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #ffffff;

    font-size: 12px;
    font-weight: 700;
    text-decoration: none;

    box-shadow: 0 4px 12px rgba(21, 155, 174, .16);
    transition: all .25s ease;
}

.page-header-row .btn-kembali:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    color: #ffffff;
    transform: translateY(-2px);
}

/* =====================================
   RESPONSIVE HP DAN TABLET
===================================== */

@media (max-width: 700px) {

    body {
        overflow-x: hidden;
    }

    /* SIDEBAR SLIDE */
    .warga-sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        bottom: 0 !important;

        width: 260px !important;
        min-width: 260px !important;
        height: 100vh !important;

        display: flex !important;
        flex-direction: column !important;

        padding: 0 !important;

        background: linear-gradient(
            180deg,
            #071F33 0%,
            #0B2D46 100%
        ) !important;

        overflow-y: auto !important;
        z-index: 1001 !important;

        transform: translateX(-100%);
        transition: transform .3s ease;
    }

    .warga-sidebar.open {
        transform: translateX(0);
    }

    .sidebar-brand {
        padding: 24px 18px 20px !important;
    }

    /* NAVIGASI VERTIKAL */
    .sidebar-nav {
        display: flex !important;
        flex-direction: column !important;

        flex: 1;
        padding: 15px 14px !important;

        overflow-x: visible !important;
        overflow-y: auto !important;
    }

    .sidebar-section-title {
        display: block !important;
        margin: 12px 0 8px !important;
    }

    .sidebar-link {
        display: flex !important;
        align-items: center !important;

        width: 100% !important;
        flex: none !important;

        padding: 11px 12px !important;
        margin: 3px 0 !important;

        background: transparent !important;
        white-space: normal !important;

        font-size: 13px !important;
    }

    .sidebar-link:hover {
        transform: none;
    }

    .sidebar-link.active {
        box-shadow: inset 3px 0 0 #18C3D8 !important;
    }

    /* OVERLAY */
    .sidebar-overlay {
        display: block;
        position: fixed;
        inset: 0;

        background: rgba(4, 25, 39, .55);

        z-index: 1000;

        opacity: 0;
        visibility: hidden;
        transition: .3s ease;
    }

    .sidebar-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    /* KONTEN UTAMA */
    .main-area {
        margin-left: 0 !important;
        width: 100% !important;

        padding: 22px 15px 30px !important;
    }

    /* HEADER */
    .page-header-row {
        display: flex;
        flex-direction: column;
        align-items: stretch;

        gap: 10px;
        margin-bottom: 20px;
    }

    .page-header-row .warga-heading {
        width: 100%;
    }

    .warga-title-line {
        width: 100%;
        align-items: center;
    }

    .page-header-row .warga-heading h1 {
        font-size: 20px;
        margin: 0;
    }

    .page-header-row .warga-heading p {
        margin-top: 7px;
        font-size: 11px;
    }

    /* HAMBURGER */
    .mobile-menu-button {
        display: inline-flex !important;
    }

    /* TOMBOL KEMBALI */
    .page-header-row .btn-kembali {
        align-self: flex-end;
        width: auto;
        min-width: auto;

        padding: 9px 13px;
        font-size: 11px;
    }

    /* STATISTIK TETAP 3 KOLOM */
    .stats-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 8px !important;
    }

    .stat-card {
        padding: 12px 8px !important;
        flex-direction: column;
        align-items: flex-start;
        gap: 9px;
    }

    .stat-icon {
        width: 35px;
        height: 35px;
        min-width: 35px;
        font-size: 17px;
    }

    .stat-info span {
        font-size: 9px;
    }

    .stat-info strong {
        font-size: 17px;
    }

    /* TABEL TETAP BISA DIGESER */
    .table-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .riwayat-table {
        min-width: 1000px;
    }

}

/* HP KECIL */
@media (max-width: 420px) {

    .main-area {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }

    .page-header-row .warga-heading h1 {
        font-size: 18px;
    }

    .page-header-row .btn-kembali {
        padding: 9px 12px;
        font-size: 10px;
    }

    .stats-grid {
        gap: 6px !important;
    }

    .stat-card {
        padding: 10px 6px !important;
    }

    .stat-info span {
        font-size: 8px;
    }

    .stat-info strong {
        font-size: 15px;
    }
}

/* LAPTOP */
@media (min-width: 701px) {

    .mobile-menu-button {
        display: none !important;
    }

    .sidebar-overlay {
        display: none !important;
    }

    .page-header-row {
        flex-direction: row;
        align-items: center;
    }

    .page-header-row .btn-kembali {
        min-width: 220px;
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
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="dashboard-layout">

    <!-- ==========================================
         SIDEBAR WARGA
    ========================================== -->

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

            <div class="sidebar-section-title">
                Utama
            </div>

            <a href="dashboard.php" class="sidebar-link">
                <span class="sidebar-icon">🏠</span>
                <span>Dashboard</span>
            </a>

            <div class="sidebar-section-title">
                Layanan Warga
            </div>

            <a href="qr_saya.php" class="sidebar-link">
                <span class="sidebar-icon">▣</span>
                <span>QR Code Saya</span>
            </a>

            <a href="ajukan_setoran.php" class="sidebar-link">
                <span class="sidebar-icon">♻️</span>
                <span>Ajukan Setoran</span>
            </a>

            <a href="riwayat_setoran.php" class="sidebar-link active">
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

            <div class="sidebar-section-title">
                Akun
            </div>

            <a href="profil.php" class="sidebar-link">
                <span class="sidebar-icon">⚙️</span>
                <span>Profil Saya</span>
            </a>
             <a href="aktivitas.php" class="sidebar-link">
            <span class="sidebar-icon">🕘</span>
            <span>Aktivitas Saya</span>
        </a>
            <a href="../auth/logout.php" class="sidebar-link sidebar-logout">
                <span class="sidebar-icon">🚪</span>
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <!-- ==========================================
         KONTEN UTAMA
    ========================================== -->

    <main class="main-area">

  
<div class="page-header-row">

    <div class="warga-heading">

        <div class="warga-title-line">
            <h1>Riwayat Setoran</h1>

            <button
                type="button"
                class="mobile-menu-button"
                id="mobileMenuButton"
                aria-label="Buka menu"
                aria-expanded="false"
            >
                ☰
            </button>
        </div>

        <p>
            Pantau seluruh pengajuan setoran sampah yang telah kamu lakukan.
        </p>

    </div>

    <a href="dashboard.php" class="btn-kembali">
        <span>←</span>
        Kembali ke Dashboard
    </a>

</div>

        <!-- ==========================================
             RINGKASAN SETORAN
        ========================================== -->

        <section class="stats-grid">

            <div class="stat-card">

                <div class="stat-icon total">
                    📦
                </div>

                <div class="stat-info">
                    <span>Total Pengajuan</span>

                    <strong>
                        <?= number_format($total_setoran, 0, ',', '.'); ?>
                    </strong>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon berat">
                    ♻️
                </div>

                <div class="stat-info">
                    <span>Total Berat Sampah</span>

                    <strong>
                        <?= number_format($total_berat, 2, ',', '.'); ?> Kg
                    </strong>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon diterima">
                    ✓
                </div>

                <div class="stat-info">
                    <span>Setoran Diterima</span>

                    <strong>
                        <?= number_format($total_diterima, 0, ',', '.'); ?>
                    </strong>
                </div>

            </div>

        </section>


        <!-- ==========================================
             TABEL RIWAYAT SETORAN
        ========================================== -->

        <section class="table-panel">

            <div class="table-panel-header">

                <div class="table-panel-title">
                    <h2>Daftar Pengajuan Setoran</h2>

                    <p>
                        Berikut adalah daftar seluruh aktivitas setoran sampah kamu.
                    </p>
                </div>

                <a href="ajukan_setoran.php" class="btn-ajukan">
                    <span>＋</span>
                    Ajukan Setoran
                </a>

            </div>


            <div class="table-container">

                <table class="riwayat-table">

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

                        <?php if (count($data_setoran) > 0): ?>

                            <?php foreach ($data_setoran as $index => $setoran): ?>

                                <tr
                                    id="setoran-<?= (int) $setoran['id']; ?>"
                                    class="<?= $setoran_id === (int) $setoran['id'] ? 'setoran-highlight' : ''; ?>"
                                >

                                    <td class="nomor-cell">
                                        <?= $index + 1; ?>
                                    </td>


                                    <td class="kategori-cell">
                                        <?= e($setoran['nama_kategori']); ?>
                                    </td>


                                    <td>
                                        <?= tanggalIndonesia($setoran['tanggal_setor']); ?>
                                    </td>


                                    <td>
                                        <?= number_format(
                                            (float) $setoran['berat_kg'],
                                            2,
                                            ',',
                                            '.'
                                        ); ?> Kg
                                    </td>


                                    <td class="harga-cell">
                                        <?= rupiah($setoran['harga_per_kg']); ?>
                                    </td>


                                    <td class="harga-cell">
                                        <?= rupiah($setoran['total_harga']); ?>
                                    </td>


                                    <td>

                                        <?php if ($setoran['status'] === 'menunggu'): ?>

                                            <span class="status-badge status-menunggu">
                                                ● Menunggu
                                            </span>

                                        <?php elseif ($setoran['status'] === 'diterima'): ?>

                                            <span class="status-badge status-diterima">
                                                ✓ Diterima
                                            </span>

                                        <?php elseif ($setoran['status'] === 'ditolak'): ?>

                                            <span class="status-badge status-ditolak">
                                                ✕ Ditolak
                                            </span>

                                        <?php else: ?>

                                            <span class="status-badge status-menunggu">
                                                <?= e(ucfirst($setoran['status'])); ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td class="catatan-cell">
                                        <?= !empty($setoran['catatan'])
                                            ? e($setoran['catatan'])
                                            : '<span class="alasan-kosong">-</span>'; ?>
                                    </td>


                                    <td>

                                        <?php if (
                                            $setoran['status'] === 'ditolak' &&
                                            !empty($setoran['alasan_penolakan'])
                                        ): ?>

                                            <div class="alasan-penolakan">

                                                <span class="alasan-icon">!</span>

                                                <span>
                                                    <?= e($setoran['alasan_penolakan']); ?>
                                                </span>

                                            </div>

                                        <?php elseif ($setoran['status'] === 'ditolak'): ?>

                                            <span class="alasan-kosong">
                                                Tidak ada alasan
                                            </span>

                                        <?php else: ?>

                                            <span class="alasan-kosong">-</span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>


                        <?php else: ?>

                            <tr>
                                <td colspan="9" class="empty-state">

                                    <div class="empty-icon">
                                        ♻️
                                    </div>

                                    <strong>Belum Ada Riwayat Setoran</strong>

                                    <p>
                                        Kamu belum memiliki pengajuan setoran sampah.
                                        Yuk, mulai setor sampah sekarang!
                                    </p>

                                    <a href="ajukan_setoran.php" class="empty-action">
                                        + Ajukan Setoran
                                    </a>

                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- FOOTER -->

        <footer class="page-footer">
            © <?= date('Y'); ?> Bank Sampah Digital.
            Bersama Jaga Lingkungan.
        </footer>

    </main>

</div>


<!-- ==========================================
     SCROLL OTOMATIS KE SETORAN DARI NOTIFIKASI
========================================== -->

<script>
document.addEventListener('DOMContentLoaded', function () {

    // SCROLL OTOMATIS DARI NOTIFIKASI
    const setoranHighlight = document.querySelector('.setoran-highlight');

    if (setoranHighlight) {
        setTimeout(function () {
            setoranHighlight.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
                inline: 'center'
            });
        }, 300);
    }

    // HAMBURGER MENU WARGA
    const menuButton = document.getElementById('mobileMenuButton');
    const sidebar = document.getElementById('wargaSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (!menuButton || !sidebar || !overlay) return;

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        menuButton.innerHTML = '✕';
        menuButton.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        menuButton.innerHTML = '☰';
        menuButton.setAttribute('aria-expanded', 'false');
    }

    menuButton.addEventListener('click', function () {
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    overlay.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('.sidebar-link').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 700) {
            closeSidebar();
        }
    });

});
</script>
</body>
</html>

