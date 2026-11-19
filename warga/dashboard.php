
<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$user_id = (int) $_SESSION['user_id'];
$nama_warga = htmlspecialchars($_SESSION['nama'] ?? 'Warga', ENT_QUOTES, 'UTF-8');

/* =========================================
   1. NOTIFIKASI TERBARU
========================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, judul, pesan, tipe, dibaca, tanggal_dibuat, link
     FROM notifikasi
     WHERE user_id = ?
     ORDER BY tanggal_dibuat DESC, id DESC
     LIMIT 3"
);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$query_notifikasi_terbaru = mysqli_stmt_get_result($stmt);

$notifikasi = [];
while ($row = mysqli_fetch_assoc($query_notifikasi_terbaru)) {
    $notifikasi[] = $row;
}
mysqli_stmt_close($stmt);


/* =========================================
   2. JUMLAH NOTIFIKASI BELUM DIBACA
========================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM notifikasi
     WHERE user_id = ? AND dibaca = 0"
);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$data_notifikasi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$jumlah_notifikasi = (int) ($data_notifikasi['total'] ?? 0);
mysqli_stmt_close($stmt);


/* =========================================
   3. SETORAN TERBARU
========================================= */

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
     INNER JOIN kategori_sampah k ON s.kategori_id = k.id
     WHERE s.user_id = ?
     ORDER BY s.id DESC
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$setoran_terbaru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);


/* =========================================
   4. SALDO TABUNGAN
========================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT COALESCE(MAX(saldo), 0) AS saldo
     FROM tabungan_warga
     WHERE user_id = ?"
);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$data_saldo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$saldo = (float) ($data_saldo['saldo'] ?? 0);
mysqli_stmt_close($stmt);


/* =========================================
   5. TOTAL BERAT SETORAN DITERIMA
========================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT COALESCE(SUM(berat_kg), 0) AS total_berat
     FROM setoran_sampah
     WHERE user_id = ? AND status = 'diterima'"
);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$data_setoran = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$total_berat = (float) ($data_setoran['total_berat'] ?? 0);
mysqli_stmt_close($stmt);


/* =========================================
   6. TOTAL TRANSAKSI TABUNGAN
========================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(m.id) AS total_transaksi
     FROM mutasi_tabungan m
     INNER JOIN tabungan_warga t ON m.tabungan_id = t.id
     WHERE t.user_id = ?"
);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$data_transaksi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$total_transaksi = (int) ($data_transaksi['total_transaksi'] ?? 0);
mysqli_stmt_close($stmt);


/* =========================================
   7. FORMAT TANGGAL
========================================= */

function tanggalIndonesia($tanggal)
{
    if (!$tanggal) {
        return '-';
    }

    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April',
        'Mei', 'Juni', 'Juli', 'Agustus',
        'September', 'Oktober', 'November', 'Desember'
    ];

    $waktu = strtotime($tanggal);

    return date('d', $waktu) . ' ' .
        $bulan[(int) date('n', $waktu)] . ' ' .
        date('Y', $waktu);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Warga - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --navy: #0B354D;
            --blue: #12677F;
            --teal: #147F91;
            --cyan: #17B8C8;
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
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
        }

        a {
            text-decoration: none;
        }

        /* =================================
           LAYOUT UTAMA
        ================================= */

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

        /* =================================
           SIDEBAR
        ================================= */

        .warga-sidebar {
            width: 255px;
            min-width: 255px;
            min-height: 100vh;
            padding: 25px 17px;
            background: linear-gradient(180deg, #0B354D 0%, #12677F 65%, #147F91 100%);
            color: white;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 5px 9px 27px;
            border-bottom: 1px solid rgba(255,255,255,.15);
        }

        .brand-icon {
            width: 43px;
            height: 43px;
            min-width: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.17);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 13px;
            font-size: 23px;
        }

        .brand-text h2 {
            margin: 0;
            color: white;
            font-size: 14px;
            font-weight: 800;
            line-height: 1.4;
        }

        .brand-text p {
            margin: 3px 0 0;
            color: rgba(255,255,255,.7);
            font-size: 10px;
        }

        .sidebar-profile {
            display: flex;
            align-items: center;
            gap: 11px;
            margin: 23px 0 27px;
            padding: 13px 11px;
            background: rgba(255,255,255,.11);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 13px;
        }

        .profile-avatar {
            width: 39px;
            height: 39px;
            min-width: 39px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #17B8C8, #79DCE2);
            color: #0B354D;
            border-radius: 50%;
            font-size: 17px;
            font-weight: 800;
        }

        .profile-info {
            min-width: 0;
        }

        .profile-info strong {
            display: block;
            color: white;
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .profile-info span {
            display: block;
            margin-top: 4px;
            color: rgba(255,255,255,.68);
            font-size: 10px;
        }

        .sidebar-label {
            margin: 0 10px 11px;
            color: rgba(255,255,255,.55);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.1px;
            text-transform: uppercase;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 13px;
            color: rgba(255,255,255,.78);
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            transition: .2s ease;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: white;
            background: rgba(255,255,255,.17);
        }

        .sidebar-link.active {
            box-shadow: inset 3px 0 #57E0E4;
        }

        .sidebar-icon {
            width: 20px;
            text-align: center;
            font-size: 17px;
        }

        .sidebar-bottom {
            margin-top: auto;
            padding-top: 25px;
        }

        .sidebar-bottom .sidebar-link {
            border-top: 1px solid rgba(255,255,255,.13);
            border-radius: 0;
            padding-top: 17px;
        }

        .sidebar-bottom .logout-link {
            color: #FFE1E1;
        }

        /* =================================
           AREA KONTEN
        ================================= */

        .main-area {
            flex: 1;
            min-width: 0;
            padding: 27px 32px 45px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 27px;
        }

        .topbar-title h1 {
            margin: 0;
            color: var(--navy);
            font-size: 23px;
            font-weight: 800;
        }

        .topbar-title p {
            margin: 7px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification-button {
            position: relative;
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--blue);
            font-size: 19px;
            box-shadow: var(--shadow);
        }

        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            min-width: 19px;
            height: 19px;
            padding: 0 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #E64C4C;
            color: white;
            border: 2px solid white;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 800;
        }

        .topbar-profile {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 7px 12px 7px 7px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        .topbar-avatar {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #E3F3F7;
            color: var(--blue);
            border-radius: 50%;
            font-weight: 800;
            font-size: 12px;
        }

        .topbar-profile span {
            color: var(--text);
            font-size: 11px;
            font-weight: 700;
        }

        /* =================================
           HERO
        ================================= */

        .welcome-banner {
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            min-height: 175px;
            padding: 29px 32px;
            overflow: hidden;
            background: linear-gradient(110deg, #0B354D, #12677F 65%, #17A9B8);
            border-radius: 19px;
            color: white;
            box-shadow: 0 12px 30px rgba(11,53,77,.13);
        }

        .welcome-banner::before {
            content: "";
            position: absolute;
            width: 210px;
            height: 210px;
            right: 105px;
            top: -125px;
            border: 1px solid rgba(255,255,255,.13);
            border-radius: 50%;
        }

        .welcome-banner::after {
            content: "";
            position: absolute;
            width: 170px;
            height: 170px;
            right: -40px;
            bottom: -115px;
            border: 25px solid rgba(255,255,255,.08);
            border-radius: 50%;
        }

        .welcome-text {
            position: relative;
            z-index: 1;
        }

        .welcome-text span {
            color: #A8EAF0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .7px;
        }

        .welcome-text h2 {
            margin: 10px 0 8px;
            color: white;
            font-size: 24px;
            font-weight: 800;
        }

        .welcome-text p {
            max-width: 480px;
            margin: 0;
            color: rgba(255,255,255,.78);
            font-size: 12px;
            line-height: 1.7;
        }

        .welcome-button {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 12px 17px;
            background: white;
            color: var(--navy);
            border-radius: 10px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
            transition: .2s;
        }

        .welcome-button:hover {
            background: #DDF8FA;
            transform: translateY(-2px);
        }

        /* =================================
           STATISTIK
        ================================= */

        .section-heading {
            margin: 29px 0 16px;
        }

        .section-heading h2 {
            margin: 0;
            color: var(--navy);
            font-size: 16px;
            font-weight: 800;
        }

        .section-heading p {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: 11px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 17px;
        }

        .stat-card {
            position: relative;
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
            padding: 20px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
            transition: .2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 47px;
            height: 47px;
            min-width: 47px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            font-size: 21px;
        }

        .stat-icon.saldo {
            background: #E4F5F7;
        }

        .stat-icon.berat {
            background: #E6F3FA;
        }

        .stat-icon.transaksi {
            background: #F0ECFA;
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
            font-size: 18px;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        /* =================================
           GRID KONTEN
        ================================= */

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(280px, .75fr);
            gap: 18px;
            margin-top: 25px;
        }

        .panel {
            min-width: 0;
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 18px 20px;
            border-bottom: 1px solid #EDF2F5;
        }

        .panel-header h3 {
            margin: 0;
            color: var(--navy);
            font-size: 13px;
            font-weight: 800;
        }

        .panel-header a {
            color: var(--teal);
            font-size: 10px;
            font-weight: 700;
        }

        .panel-body {
            padding: 18px 20px;
        }

        /* =================================
           SETORAN TERBARU
        ================================= */

        .setoran-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .setoran-icon {
            width: 43px;
            height: 43px;
            min-width: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #E6F5F6;
            border-radius: 12px;
            font-size: 20px;
        }

        .setoran-detail {
            min-width: 0;
        }

        .setoran-detail strong {
            display: block;
            color: var(--navy);
            font-size: 12px;
        }

        .setoran-detail span {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 10px;
        }

        .setoran-status {
            margin-top: 17px;
            padding-top: 15px;
            border-top: 1px solid #EDF2F5;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
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

        .setoran-price {
            display: block;
            margin-top: 10px;
            color: var(--navy);
            font-size: 15px;
            font-weight: 800;
        }

        .setoran-reason {
            display: block;
            margin-top: 8px;
            color: #C0392B;
            font-size: 10px;
            line-height: 1.5;
        }

        .empty-state {
            padding: 16px 5px;
            text-align: center;
        }

        .empty-state .empty-icon {
            font-size: 30px;
        }

        .empty-state strong {
            display: block;
            margin-top: 8px;
            color: var(--navy);
            font-size: 12px;
        }

        .empty-state p {
            margin: 6px 0 12px;
            color: var(--muted);
            font-size: 10px;
        }

        .small-action {
            display: inline-block;
            padding: 9px 13px;
            background: #E7F4F7;
            color: var(--teal);
            border-radius: 8px;
            font-size: 10px;
            font-weight: 800;
        }

        /* =================================
           NOTIFIKASI
        ================================= */

        .notification-item {
            display: flex;
            gap: 10px;
            padding: 12px 0;
            border-bottom: 1px solid #EDF2F5;
        }

        .notification-item:first-child {
            padding-top: 0;
        }

        .notification-item:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .notification-item.unread {
            background: #F3FAFC;
            margin: 0 -10px;
            padding: 12px 10px;
            border-radius: 9px;
        }

        .notification-item:first-child.unread {
            padding-top: 12px;
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            min-width: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #E5F3F7;
            color: var(--teal);
            border-radius: 10px;
            font-size: 13px;
            font-weight: 800;
        }

        .notification-detail {
            min-width: 0;
            flex: 1;
        }

        .notification-detail strong {
            display: block;
            color: var(--navy);
            font-size: 11px;
        }

        .notification-detail p {
            margin: 5px 0 0;
            color: #718995;
            font-size: 10px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .notification-detail small {
            display: block;
            margin-top: 5px;
            color: #9AAAB3;
            font-size: 9px;
        }

        .notification-empty {
            padding: 18px 5px;
            color: var(--muted);
            text-align: center;
            font-size: 11px;
        }

        /* =================================
           LAYANAN
        ================================= */

        .services-section {
            margin-top: 28px;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .service-card {
            display: block;
            min-width: 0;
            padding: 19px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
            transition: .22s ease;
        }

        .service-card:hover {
            transform: translateY(-4px);
            border-color: #A8DCE9;
            box-shadow: 0 12px 25px rgba(11,53,77,.09);
        }

        .service-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #E6F4F7;
            border-radius: 12px;
            font-size: 20px;
        }

        .service-card h3 {
            margin: 13px 0 7px;
            color: var(--navy);
            font-size: 12px;
            font-weight: 800;
        }

        .service-card p {
            min-height: 34px;
            margin: 0;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.6;
        }

        .service-card > span {
            display: inline-block;
            margin-top: 13px;
            color: var(--teal);
            font-size: 10px;
            font-weight: 800;
        }

        .service-card.qr-service {
            background: linear-gradient(145deg, #FFFFFF, #EAF8FA);
            border-color: #B9DFEA;
        }

        .service-card.qr-service .service-icon {
            background: linear-gradient(135deg, #0B354D, #17B8C8);
            color: white;
        }

        /* =================================
           FOOTER
        ================================= */

        .dashboard-footer {
            margin-top: 32px;
            padding-top: 17px;
            border-top: 1px solid #E2EBF0;
            color: #94A5AE;
            text-align: center;
            font-size: 10px;
        }

        /* =================================
           TABLET
        ================================= */

        @media (max-width: 1050px) {
            .warga-sidebar {
                width: 220px;
                min-width: 220px;
            }

            .main-area {
                padding: 24px 22px 35px;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .services-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        /* =================================
           HP
        ================================= */

        @media (max-width: 700px) {
            .dashboard-layout {
                display: block;
            }

            .warga-sidebar {
                width: 100%;
                min-width: 0;
                min-height: auto;
                padding: 14px 13px;
            }

            .sidebar-brand {
                padding: 0 3px 13px;
            }

            .brand-icon {
                width: 37px;
                height: 37px;
                min-width: 37px;
                font-size: 19px;
            }

            .brand-text h2 {
                font-size: 13px;
            }

            .brand-text p {
                font-size: 9px;
            }

            .sidebar-profile {
                display: none;
            }

            .sidebar-label {
                display: none;
            }

            .sidebar-menu {
                flex-direction: row;
                gap: 7px;
                overflow-x: auto;
                padding: 12px 0 3px;
                scrollbar-width: thin;
            }

            .sidebar-link {
                flex: 0 0 auto;
                padding: 9px 11px;
                gap: 7px;
                background: rgba(255,255,255,.08);
                font-size: 10px;
            }

            .sidebar-link.active {
                box-shadow: inset 0 -3px #57E0E4;
            }

            .sidebar-icon {
                font-size: 14px;
            }

            .sidebar-bottom {
                display: none;
            }

            .main-area {
                padding: 21px 15px 30px;
            }

            .topbar {
                margin-bottom: 19px;
            }

            .topbar-title h1 {
                font-size: 19px;
            }

            .topbar-title p {
                font-size: 10px;
                line-height: 1.5;
            }

            .topbar-profile {
                display: none;
            }

            .notification-button {
                width: 39px;
                height: 39px;
            }

            .welcome-banner {
                min-height: 160px;
                padding: 22px 19px;
                align-items: flex-start;
                flex-direction: column;
                gap: 16px;
            }

            .welcome-text h2 {
                font-size: 20px;
            }

            .welcome-text p {
                font-size: 10px;
            }

            .welcome-button {
                padding: 10px 13px;
                font-size: 10px;
            }

            .section-heading {
                margin-top: 23px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 11px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-icon {
                width: 42px;
                height: 42px;
                min-width: 42px;
            }

            .stat-info strong {
                font-size: 17px;
            }

            .content-grid {
                gap: 14px;
                margin-top: 20px;
            }

            .panel-header {
                padding: 15px;
            }

            .panel-body {
                padding: 15px;
            }

            .services-section {
                margin-top: 23px;
            }

            .services-grid {
                grid-template-columns: 1fr;
                gap: 11px;
            }

            .service-card {
                padding: 16px;
            }

            .service-card p {
                min-height: 0;
            }

            .dashboard-footer {
                font-size: 9px;
            }
        }

        @media (max-width: 360px) {
            .main-area {
                padding-left: 11px;
                padding-right: 11px;
            }

            .welcome-text h2 {
                font-size: 18px;
            }

            .topbar-title h1 {
                font-size: 17px;
            }
        }

/* ==========================================
   SIDEBAR WARGA - SAMA PERSIS DENGAN ADMIN
========================================== */

.warga-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;

    width: 250px;
    min-width: 250px;
    height: 100vh;
    min-height: 100vh;

    /* Penting: tidak menggunakan padding luar */
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

/* BRAND / LOGO */
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

/* LOGO LINGKARAN */
.sidebar-logo {
    width: 48px;
    height: 48px;
    min-width: 48px;

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

/* TULISAN BRANDING */
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
    color: #ffffff;
    transform: translateX(2px);
}

.sidebar-link.active {
    background: rgba(24,195,216,.14);
    color: #18C3D8;
    box-shadow: inset 3px 0 0 #18C3D8;
}

.sidebar-icon {
    width: 22px;
    text-align: center;
    font-size: 16px;
    flex: 0 0 22px;
}

/* BADGE NOTIFIKASI */
.sidebar-badge {
    margin-left: auto;
    min-width: 19px;
    padding: 3px 6px;

    border-radius: 20px;
    background: #dc3545;
    color: #ffffff;

    font-size: 10px;
    font-weight: 700;
    text-align: center;
}

/* LOGOUT */
.sidebar-logout {
    color: #FFE0E0;
}

.sidebar-logout:hover {
    background: rgba(220,53,69,.15);
    color: #ffffff;
}

/* ==========================================
   KONTEN DASHBOARD
========================================== */

.dashboard-layout {
    display: block;
    min-height: 100vh;
}

.main-area {
    margin-left: 250px;
    width: calc(100% - 250px);
    min-height: 100vh;

    padding: 27px 32px 45px;

    box-sizing: border-box;
}

/* ==========================================
   RESPONSIVE HP
========================================== */

@media (max-width: 700px) {
    .warga-sidebar {
        position: relative;
        width: 100%;
        min-width: 0;
        height: auto;
        min-height: auto;
    }

    .sidebar-brand {
        padding: 18px 20px;
    }

    .sidebar-nav {
        padding: 10px 14px;
    }

    .sidebar-section-title {
        display: none;
    }

    .sidebar-nav {
        overflow-x: auto;
    }

    .sidebar-link {
        font-size: 11px;
        white-space: nowrap;
    }

    .main-area {
        margin-left: 0;
        width: 100%;
        padding: 21px 15px 30px;
    }
}

/* ==========================================
   HAMBURGER MENU MOBILE
========================================== */

/* Tombol hamburger disembunyikan di laptop */
.mobile-menu-button {
    display: none;
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    border: none;
    border-radius: 10px;

    /* Gradasi navy ke teal */
    background: linear-gradient(
        135deg,
        #071F33 0%,
        #0B354D 55%,
        #147F91 100%
    );

    color: white;
    font-size: 23px;
    cursor: pointer;
    align-items: center;
    justify-content: center;

    box-shadow: 0 4px 12px rgba(11, 53, 77, .20);
    transition: .25s ease;
}

.mobile-menu-button:hover {
    background: linear-gradient(
        135deg,
        #0B354D,
        #17B8C8
    );
}
/* Lapisan gelap di belakang sidebar */
.sidebar-overlay {
    display: none;
}

/* Tampilan HP */
@media (max-width: 700px) {

    .dashboard-layout {
        display: block;
    }

    /* Sidebar menjadi menu geser */
    .warga-sidebar {
        position: fixed !important;
        top: 0;
        left: 0;
        bottom: 0;

        width: 260px !important;
        min-width: 260px !important;
        height: 100vh !important;
        min-height: 100vh !important;

        padding: 0 !important;

        display: flex;
        flex-direction: column;

        background: linear-gradient(
            180deg,
            #071F33 0%,
            #0B2D46 100%
        );

        overflow-y: auto;
        z-index: 1001;

        transform: translateX(-100%);
        transition: transform .3s ease;
    }

    /* Sidebar ketika dibuka */
    .warga-sidebar.open {
        transform: translateX(0);
    }

    /* Branding */
    .sidebar-brand {
        padding: 24px 18px 20px !important;
    }

    .sidebar-brand h2 {
        font-size: 14px;
    }

    .sidebar-brand p {
        font-size: 9px;
    }

    /* Navigasi kembali vertikal */
    .sidebar-nav {
        display: flex;
        flex-direction: column;
        padding: 15px 14px !important;
        overflow-x: visible;
        overflow-y: auto;
    }

    .sidebar-section-title {
        display: block;
        margin: 12px 0 8px;
    }

    .sidebar-link {
        width: 100%;
        flex: none;
        white-space: normal;
        padding: 11px 12px;
        font-size: 12px;
        background: transparent;
    }

    .sidebar-link.active {
        box-shadow: inset 3px 0 0 #18C3D8;
    }

    .sidebar-bottom {
        display: block;
    }

    /* Tombol hamburger muncul */
    .mobile-menu-button {
        display: flex;
    }

    .topbar {
        justify-content: flex-start;
        gap: 12px;
    }

    .topbar-title {
        flex: 1;
        min-width: 0;
    }

    .topbar-title h1 {
        font-size: 18px;
    }

    /* Overlay */
    .sidebar-overlay {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .45);
        z-index: 1000;

        opacity: 0;
        visibility: hidden;
        transition: .3s ease;
    }

    .sidebar-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    /* Konten HP */
    .main-area {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 21px 15px 30px !important;
    }
}

@media (max-width: 700px) {
    .topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .topbar-title {
        flex: 1;
        min-width: 0;
    }

    .topbar-actions {
        display: flex;
        align-items: center;
        gap: 9px;
        flex-shrink: 0;
    }

    .mobile-menu-button {
        display: flex;
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
/* =====================================
   GRADASI IKON DASHBOARD
===================================== */

/* Gradasi warna simbol */
.gradient-icon {
    background: linear-gradient(
        135deg,
        #0B354D 0%,
        #147F91 50%,
        #17B8C8 100%
    );

    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;

    display: inline-block;
}

/* Kotak ikon */
.stat-icon,
.service-icon {
    background: linear-gradient(
        145deg,
        #EDF9FC 0%,
        #D9EFF7 100%
    );

    border: 1px solid #D5EBF3;
    border-radius: 16px;
}

/* Ukuran ikon statistik */
.stat-icon .gradient-icon {
    font-size: 23px;
}

/* Ukuran ikon layanan */
.service-icon .gradient-icon {
    font-size: 23px;
}

/* Ikon sidebar */
.sidebar-icon .gradient-icon {
    font-size: 17px;
}

/* Ikon notifikasi */
.notification-button .gradient-icon {
    font-size: 19px;
}

/* Ikon setoran */
.setoran-icon .gradient-icon {
    font-size: 22px;
}

/* Ikon notifikasi kecil */
.notification-icon .gradient-icon {
    font-size: 15px;
}
    </style>
</head>

<body>

<div class="dashboard-layout">

    <!-- =================================
         SIDEBAR
    ================================== -->

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

        <a href="dashboard.php" class="sidebar-link active">
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

            <?php if ($jumlah_notifikasi > 0): ?>
                <span class="sidebar-badge">
                    <?= $jumlah_notifikasi > 99 ? '99+' : $jumlah_notifikasi; ?>
                </span>
            <?php endif; ?>
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
<div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- =================================
         KONTEN DASHBOARD
    ================================== -->

    <main class="main-area">

        <!-- TOPBAR -->
<header class="topbar">

    <div class="topbar-title">
        <h1>Dashboard Warga</h1>
        <p>Selamat datang kembali! Pantau aktivitas bank sampah kamu.</p>
    </div>

    <div class="topbar-actions">
        <!-- NOTIFIKASI -->
        <a href="notifikasi.php"
           class="notification-button"
           title="Notifikasi"
           aria-label="Notifikasi">

            🔔

            <?php if ($jumlah_notifikasi > 0): ?>
                <span class="notification-badge">
                    <?= $jumlah_notifikasi > 99 ? '99+' : $jumlah_notifikasi; ?>
                </span>
            <?php endif; ?>

        </a>
                <!-- HAMBURGER MENU -->
        <button type="button" class="mobile-menu-button"
                id="mobileMenuButton"
                aria-label="Buka menu"
                aria-expanded="false">
            ☰
        </button>

        <!-- PROFIL -->
        <a href="profil.php" class="topbar-profile">
            <div class="topbar-avatar">
                <?= strtoupper(substr($_SESSION['nama'] ?? 'W', 0, 1)); ?>
            </div>
            <span><?= $nama_warga; ?></span>
        </a>

    </div>

</header>


        <!-- WELCOME BANNER -->

        <section class="welcome-banner">

            <div class="welcome-text">
                <span>♻ BANK SAMPAH DIGITAL</span>

                <h2>Halo, <?= $nama_warga; ?>! 👋</h2>

                <p>
                    Terima kasih sudah menjadi bagian dari gerakan peduli lingkungan.
                    Yuk, kelola sampah menjadi sesuatu yang lebih bermanfaat!
                </p>
            </div>
        </section>


        <!-- STATISTIK -->

        <div class="section-heading">
            <h2>Ringkasan Aktivitas</h2>
            <p>Informasi tabungan dan aktivitas bank sampah kamu.</p>
        </div>

        <section class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon saldo">
    <i class="fa-solid fa-wallet gradient-icon"></i>
</div>

                <div class="stat-info">
                    <span>Saldo Tabungan</span>
                    <strong>
                        Rp <?= number_format($saldo, 0, ',', '.'); ?>
                    </strong>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon sampah">
                <i class="fa-solid fa-recycle gradient-icon"></i>
                </div>
                <div class="stat-info">
                    <span>Total Sampah Diterima</span>
                    <strong>
                        <?= number_format($total_berat, 2, ',', '.'); ?> Kg
                    </strong>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon transaksi">
                <i class="fa-solid fa-chart-column gradient-icon"></i>
                </div>
                <div class="stat-info">
                    <span>Total Transaksi</span>
                    <strong>
                        <?= number_format($total_transaksi, 0, ',', '.'); ?>
                    </strong>
                </div>
            </div>

        </section>


        <!-- SETORAN DAN NOTIFIKASI -->

        <section class="content-grid">

            <!-- SETORAN TERBARU -->

            <div class="panel">

                <div class="panel-header">
                    <h3>Setoran Terbaru</h3>

                    <a href="riwayat_setoran.php">
                        Lihat Semua →
                    </a>
                </div>

                <div class="panel-body">

                    <?php if ($setoran_terbaru): ?>

                        <div class="setoran-info">
                            <div class="setoran-detail">
                                <strong>
                                    <?= htmlspecialchars($setoran_terbaru['nama_kategori'], ENT_QUOTES, 'UTF-8'); ?>
                                </strong>

                                <span>
                                    <?= tanggalIndonesia($setoran_terbaru['tanggal_setor']); ?>
                                    ·
                                    <?= number_format((float) $setoran_terbaru['berat_kg'], 2, ',', '.'); ?>
                                    Kg
                                </span>
                            </div>

                        </div>

                        <div class="setoran-status">

                            <?php if ($setoran_terbaru['status'] === 'menunggu'): ?>

                                <span class="status-badge status-menunggu">
                                    ● Menunggu Verifikasi
                                </span>

                            <?php elseif ($setoran_terbaru['status'] === 'diterima'): ?>

                                <span class="status-badge status-diterima">
                                    ✓ Diterima
                                </span>

                                <strong class="setoran-price">
                                    Rp <?= number_format((float) $setoran_terbaru['total_harga'], 0, ',', '.'); ?>
                                </strong>

                            <?php elseif ($setoran_terbaru['status'] === 'ditolak'): ?>

                                <span class="status-badge status-ditolak">
                                    ✕ Ditolak
                                </span>

                                <?php if (!empty($setoran_terbaru['alasan_penolakan'])): ?>
                                    <small class="setoran-reason">
                                        Alasan:
                                        <?= htmlspecialchars($setoran_terbaru['alasan_penolakan'], ENT_QUOTES, 'UTF-8'); ?>
                                    </small>
                                <?php endif; ?>

                            <?php endif; ?>

                        </div>

                    <?php else: ?>

                        <div class="empty-state">
                            <div class="empty-icon">♻️</div>

                            <strong>Belum Ada Setoran</strong>

                            <p>
                                Kamu belum mengajukan setoran sampah.
                            </p>

                            <a href="ajukan_setoran.php" class="small-action">
                                Ajukan Setoran →
                            </a>
                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- NOTIFIKASI TERBARU -->

            <div class="panel">

                <div class="panel-header">
                    <h3>🔔 Notifikasi Terbaru</h3>

                    <a href="notifikasi.php">
                        Lihat Semua →
                    </a>
                </div>

                <div class="panel-body">

                    <?php if (count($notifikasi) > 0): ?>

                        <?php foreach ($notifikasi as $notif): ?>

                            <div class="notification-item <?= $notif['dibaca'] == 0 ? 'unread' : ''; ?>">

                                <div class="notification-icon">
                                    <?php
                                    if ($notif['tipe'] === 'sukses') {
                                        echo '✓';
                                    } elseif ($notif['tipe'] === 'peringatan') {
                                        echo '!';
                                    } else {
                                        echo 'i';
                                    }
                                    ?>
                                </div>

                                <div class="notification-detail">

                                    <strong>
                                        <?= htmlspecialchars($notif['judul'], ENT_QUOTES, 'UTF-8'); ?>
                                    </strong>

                                    <p>
                                        <?= htmlspecialchars($notif['pesan'], ENT_QUOTES, 'UTF-8'); ?>
                                    </p>

                                    <small>
                                        <?= date('d/m/Y H:i', strtotime($notif['tanggal_dibuat'])); ?>
                                    </small>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="notification-empty">
                            Belum ada notifikasi terbaru.
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </section>


        <!-- LAYANAN BANK SAMPAH -->

        <section class="services-section">

            <div class="section-heading">
                <h2>Layanan Bank Sampah</h2>
                <p>Pilih layanan yang ingin kamu gunakan.</p>
            </div>

            <div class="services-grid">

                <a href="qr_saya.php" class="service-card qr-service">
                    <div class="service-icon">▣</div>
                    <h3>QR Code Saya</h3>
                    <p>Lihat QR Code keanggotaan untuk proses pengangkutan sampah oleh petugas.</p>
                    <span>Lihat QR Code →</span>
                </a>

                <a href="ajukan_setoran.php" class="service-card">
                    <div class="service-icon">♻️</div>
                    <h3>Ajukan Setoran</h3>
                    <p>Ajukan penyetoran sampah dan dapatkan saldo tabungan.</p>
                    <span>Mulai Setoran →</span>
                </a>

                <a href="riwayat_setoran.php" class="service-card">
                    <div class="service-icon">📦</div>
                    <h3>Riwayat Setoran</h3>
                    <p>Lihat daftar pengajuan dan status setoran sampah.</p>
                    <span>Lihat Riwayat →</span>
                </a>

                <a href="riwayat_tabungan.php" class="service-card">
                    <div class="service-icon">💰</div>
                    <h3>Riwayat Tabungan</h3>
                    <p>Pantau transaksi masuk dan keluar saldo tabungan.</p>
                    <span>Lihat Transaksi →</span>
                </a>

                <a href="ajukan_penarikan.php" class="service-card">
                    <div class="service-icon">💸</div>
                    <h3>Penarikan Tabungan</h3>
                    <p>Ajukan pencairan saldo tabungan yang kamu miliki.</p>
                    <span>Ajukan Penarikan →</span>
                </a>

                <a href="riwayat_penarikan.php" class="service-card">
                    <div class="service-icon">📋</div>
                    <h3>Riwayat Penarikan</h3>
                    <p>Lihat status pengajuan penarikan saldo tabungan.</p>
                    <span>Lihat Riwayat →</span>
                </a>

            </div>

        </section>


        <footer class="dashboard-footer">
            © <?= date('Y'); ?> Bank Sampah Digital. Bersama Jaga Lingkungan.
        </footer>

    </main>

</div>
<script>
const menuButton = document.getElementById('mobileMenuButton');
const sidebar = document.getElementById('wargaSidebar');
const overlay = document.getElementById('sidebarOverlay');

function toggleSidebar() {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');

    const isOpen = sidebar.classList.contains('open');

    menuButton.innerHTML = isOpen ? '✕' : '☰';
    menuButton.setAttribute('aria-expanded', isOpen);
}

menuButton.addEventListener('click', toggleSidebar);

overlay.addEventListener('click', toggleSidebar);

// Tutup menu setelah memilih halaman
sidebar.querySelectorAll('.sidebar-link').forEach(link => {
    link.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        menuButton.innerHTML = '☰';
        menuButton.setAttribute('aria-expanded', 'false');
    });
});
</script>
</body>
</html>
