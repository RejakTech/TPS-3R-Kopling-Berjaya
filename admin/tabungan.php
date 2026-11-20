<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../config/koneksi.php';

/*
|--------------------------------------------------------------------------
| Statistik Tabungan
|--------------------------------------------------------------------------
*/

$query_statistik = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total_warga,
        COALESCE(SUM(saldo), 0) AS total_saldo,
        COALESCE(AVG(saldo), 0) AS rata_rata_saldo
     FROM tabungan_warga"
);

$statistik = mysqli_fetch_assoc($query_statistik);

$total_warga = (int) $statistik['total_warga'];
$total_saldo = (float) $statistik['total_saldo'];
$rata_rata_saldo = (float) $statistik['rata_rata_saldo'];


/*
|--------------------------------------------------------------------------
| Pencarian
|--------------------------------------------------------------------------
*/

$keyword = isset($_GET['keyword'])
    ? trim($_GET['keyword'])
    : '';

if ($keyword !== '') {

    $query_tabungan = mysqli_prepare(
        $conn,
        "SELECT
            t.id,
            t.user_id,
            t.saldo,
            t.updated_at,
            u.nama_lengkap,
            u.username,
            u.email,
            u.no_hp
         FROM tabungan_warga t
         INNER JOIN users u ON t.user_id = u.id
         WHERE
            u.nama_lengkap LIKE ?
            OR u.username LIKE ?
            OR u.email LIKE ?
         ORDER BY t.saldo DESC, t.id DESC"
    );

    $search = '%' . $keyword . '%';

    mysqli_stmt_bind_param(
        $query_tabungan,
        "sss",
        $search,
        $search,
        $search
    );

if (!mysqli_stmt_execute($query_tabungan)) {
    mysqli_stmt_close($query_tabungan);
    die("Gagal mengambil data tabungan.");
}

$result_tabungan = mysqli_stmt_get_result($query_tabungan);

if (!$result_tabungan) {
    mysqli_stmt_close($query_tabungan);
    die("Gagal mengambil hasil data tabungan.");
}

} else {

    $query_tabungan = mysqli_prepare(
        $conn,
        "SELECT
            t.id,
            t.user_id,
            t.saldo,
            t.updated_at,
            u.nama_lengkap,
            u.username,
            u.email,
            u.no_hp
         FROM tabungan_warga t
         INNER JOIN users u ON t.user_id = u.id
         ORDER BY t.saldo DESC, t.id DESC"
    );

    if (!mysqli_stmt_execute($query_tabungan)) {
    mysqli_stmt_close($query_tabungan);
    die("Gagal mengambil data tabungan.");
}

$result_tabungan = mysqli_stmt_get_result($query_tabungan);

if (!$result_tabungan) {
    mysqli_stmt_close($query_tabungan);
    die("Gagal mengambil hasil data tabungan.");
}
}

$data_tabungan = [];

while ($row = mysqli_fetch_assoc($result_tabungan)) {
    $data_tabungan[] = $row;
}

mysqli_stmt_close($query_tabungan);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tabungan Warga - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        .tabungan-page {
            max-width: 1200px;
            margin: 0 auto;
        }

        .tabungan-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 25px;
        }

        .tabungan-header-left h1 {
            margin: 0 0 6px;
            color: #174c43;
            font-size: 26px;
        }

        .tabungan-header-left p {
            margin: 0;
            color: #81908b;
            font-size: 13px;
        }

        .btn-kembali-tabungan {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 13px;
            border: 1px solid #d6e6e2;
            border-radius: 9px;
            background: #ffffff;
            color: #0f766e;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
        }

        .btn-kembali-tabungan:hover {
            background: #eef8f5;
        }


        /* Statistik */

        .tabungan-statistics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .tabungan-stat-card {
            background: #ffffff;
            border: 1px solid #e3eeeb;
            border-radius: 14px;
            padding: 16px;
        }

        .tabungan-stat-card span {
            display: block;
            color: #81908b;
            font-size: 10px;
            margin-bottom: 7px;
        }

        .tabungan-stat-card strong {
            display: block;
            color: #174c43;
            font-size: 21px;
            line-height: 1.2;
        }


        /* Search */

        .tabungan-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .tabungan-search {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            max-width: 420px;
        }

        .tabungan-search input {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 1px solid #d9e7e3;
            border-radius: 9px;
            outline: none;
            font-size: 11px;
            color: #29443f;
        }

        .tabungan-search input:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.08);
        }

        .btn-cari-tabungan {
            border: none;
            background: #0f766e;
            color: #ffffff;
            padding: 10px 15px;
            border-radius: 9px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-cari-tabungan:hover {
            background: #0b625c;
        }


        /* Table */

        .tabungan-table-container {
            width: 100%;
            overflow-x: auto;
            background: #ffffff;
            border: 1px solid #e3eeeb;
            border-radius: 14px;
        }

        .tabungan-table {
            width: 100%;
            min-width: 820px;
            border-collapse: collapse;
        }

        .tabungan-table th {
            background: #f4f8f7;
            color: #526963;
            padding: 12px 13px;
            text-align: left;
            font-size: 9px;
            font-weight: 700;
            border-bottom: 1px solid #e3eeeb;
            white-space: nowrap;
        }

        .tabungan-table td {
            padding: 13px;
            border-bottom: 1px solid #edf3f1;
            color: #526963;
            font-size: 10px;
            vertical-align: middle;
        }

        .tabungan-table tbody tr:last-child td {
            border-bottom: none;
        }

        .tabungan-table tbody tr:hover {
            background: #f8fbfa;
        }

        .nama-warga {
            color: #174c43;
            font-weight: 700;
        }

        .username-warga {
            color: #91a09c;
            font-size: 9px;
            margin-top: 2px;
        }

        .saldo-warga {
            color: #0f766e;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-saldo {
            display: inline-flex;
            align-items: center;
            padding: 5px 8px;
            border-radius: 20px;
            font-size: 8px;
            font-weight: 700;
        }

        .saldo-tersedia {
            background: #e5f6ef;
            color: #0f766e;
        }

        .saldo-kosong {
            background: #f1f3f3;
            color: #7b8885;
        }

        .tabungan-empty {
            text-align: center;
            padding: 45px 20px !important;
            color: #91a09c !important;
        }


        /* Mobile */

        @media (max-width: 600px) {

            .tabungan-page {
                width: 100%;
            }

            .tabungan-header {
                flex-direction: column;
                gap: 12px;
                margin-bottom: 18px;
            }

            .tabungan-header-left h1 {
                font-size: 21px;
                line-height: 1.2;
            }

            .tabungan-header-left p {
                font-size: 10px;
                line-height: 1.45;
            }

            .btn-kembali-tabungan {
                width: 100%;
                justify-content: center;
                padding: 9px 12px;
                font-size: 10px;
            }


            .tabungan-statistics {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 9px;
                margin-bottom: 18px;
            }

            .tabungan-stat-card {
                padding: 11px 12px;
                border-radius: 12px;
            }

            .tabungan-stat-card span {
                font-size: 8px;
                margin-bottom: 5px;
            }

            .tabungan-stat-card strong {
                font-size: 17px;
            }

            .tabungan-stat-card:last-child {
                grid-column: 1 / -1;
            }


            .tabungan-toolbar {
                flex-direction: column;
                align-items: stretch;
                margin-bottom: 12px;
            }

            .tabungan-search {
                max-width: none;
            }

            .tabungan-search input {
                font-size: 10px;
                padding: 9px 10px;
            }

            .btn-cari-tabungan {
                padding: 9px 12px;
                font-size: 9px;
            }


            .tabungan-table-container {
                border-radius: 12px;
            }

        }

        .tabungan-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-mutasi-tabungan {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 13px;
    border: 1px solid #cfe3dd;
    border-radius: 9px;
    background: #eaf7f3;
    color: #0f766e;
    text-decoration: none;
    font-size: 11px;
    font-weight: 700;
}

.btn-mutasi-tabungan:hover {
    background: #dff2ec;
}

@media (max-width: 600px) {

    .tabungan-header-actions {
        width: 100%;
        flex-direction: column;
        gap: 7px;
    }

    .btn-mutasi-tabungan,
    .btn-kembali-tabungan {
        width: 100%;
        justify-content: center;
        box-sizing: border-box;
    }

}


/* =========================================
   TABUNGAN WARGA - MODERN NAVY & TEAL
   SIDEBAR TIDAK DIUBAH
========================================= */

/* AREA KONTEN */
.admin-main {
    margin-left: var(--sidebar-width, 250px);
    width: calc(100% - var(--sidebar-width, 250px));
    min-height: 100vh;
    background: #f4f9f8;
    box-sizing: border-box;
}

.admin-content {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 38px 42px;
    box-sizing: border-box;
}

/* HALAMAN */
.tabungan-page {
    width: 100%;
    max-width: none;
    margin: 0;
    box-sizing: border-box;
}

/* HEADER */
.tabungan-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 28px;
}

.tabungan-header-left h1 {
    color: #0B4650;
    font-size: 29px;
    font-weight: 700;
    line-height: 1.3;
    margin: 0 0 7px;
}

.tabungan-header-left p {
    color: #718995;
    font-size: 13px;
    line-height: 1.6;
    margin: 0;
}

.tabungan-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

/* TOMBOL HEADER */
.btn-mutasi-tabungan,
.btn-kembali-tabungan {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 40px;
    padding: 10px 17px;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    box-sizing: border-box;
    transition: all .2s ease;
}

.btn-mutasi-tabungan {
    background: #E5F3F5;
    color: #087987;
    border: 1px solid #CBE7ED;
}

.btn-mutasi-tabungan:hover {
    background: #D2ECEF;
    transform: translateY(-2px);
}

.btn-kembali-tabungan {
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    color: #FFFFFF;
    border: 1px solid transparent;
    box-shadow: 0 4px 12px rgba(11, 53, 77, .13);
}

.btn-kembali-tabungan:hover {
    background: linear-gradient(110deg, #082B40, #109EAF);
    color: #FFFFFF;
    transform: translateY(-2px);
}

/* STATISTIK */
.tabungan-statistics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    width: 100%;
    margin: 0 0 27px;
}

.tabungan-stat-card {
    position: relative;
    overflow: hidden;
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    padding: 23px 24px;
    box-shadow: 0 4px 15px rgba(11, 45, 70, .045);
    transition: all .2s ease;
}

.tabungan-stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(180deg, #159BAE, #17B8C8);
}

.tabungan-stat-card:nth-child(2)::before {
    background: linear-gradient(180deg, #0D9488, #34BFA8);
}

.tabungan-stat-card:nth-child(3)::before {
    background: linear-gradient(180deg, #E49A0F, #F5BD48);
}

.tabungan-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(11, 45, 70, .08);
    border-color: #C9E1E6;
}

.tabungan-stat-card span {
    display: block;
    color: #718995;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 12px;
}

.tabungan-stat-card strong {
    display: block;
    color: #0B354D;
    font-size: 27px;
    font-weight: 700;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.tabungan-stat-card:nth-child(2) strong {
    color: #0D9488;
}

.tabungan-stat-card:nth-child(3) strong {
    color: #D58B0A;
}

/* PENCARIAN */
.tabungan-toolbar {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    margin-bottom: 17px;
}

.tabungan-search {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    max-width: 480px;
}

.tabungan-search input {
    flex: 1;
    min-width: 0;
    width: 100%;
    padding: 11px 14px;
    background: #FFFFFF;
    border: 1px solid #DCE7EC;
    border-radius: 9px;
    color: #344D59;
    font-family: inherit;
    font-size: 12px;
    outline: none;
    box-sizing: border-box;
    transition: .2s ease;
}

.tabungan-search input:focus {
    border-color: #17AEBE;
    box-shadow: 0 0 0 3px rgba(23, 184, 200, .12);
}

.btn-cari-tabungan {
    min-height: 40px;
    padding: 0 20px;
    border: none;
    border-radius: 9px;
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    color: #FFFFFF;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: .2s ease;
}

.btn-cari-tabungan:hover {
    background: linear-gradient(110deg, #082B40, #109EAF);
    transform: translateY(-1px);
}

/* TABEL */
.tabungan-table-container {
    width: 100%;
    overflow-x: auto;
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(11, 45, 70, .035);
    box-sizing: border-box;
    -webkit-overflow-scrolling: touch;
}

.tabungan-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
    font-size: 12px;
}

.tabungan-table th {
    padding: 15px 17px;
    background: #EAF3F6;
    color: #315A69;
    font-size: 11px;
    font-weight: 700;
    text-align: left;
    border-bottom: 1px solid #DCE8EC;
    white-space: nowrap;
}

.tabungan-table td {
    padding: 16px 17px;
    color: #526671;
    font-size: 12px;
    border-bottom: 1px solid #EDF2F3;
    vertical-align: middle;
}

.tabungan-table tbody tr {
    transition: background .2s ease;
}

.tabungan-table tbody tr:hover {
    background: #F2F9FA;
}

.tabungan-table tbody tr:last-child td {
    border-bottom: none;
}

/* IDENTITAS WARGA */
.nama-warga {
    color: #0B4650;
    font-size: 12px;
    font-weight: 700;
}

.username-warga {
    color: #91A3AA;
    font-size: 11px;
    margin-top: 4px;
}

/* SALDO */
.saldo-warga {
    color: #0D9488;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

/* STATUS SALDO */
.status-saldo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.saldo-tersedia {
    background: #E2F5F1;
    color: #087C72;
}

.saldo-kosong {
    background: #EDF1F3;
    color: #71818A;
}

/* DATA KOSONG */
.tabungan-empty {
    padding: 45px 20px !important;
    text-align: center;
    color: #81949C !important;
    font-size: 13px !important;
}

/* RESPONSIVE TABLET */
@media (max-width: 1000px) {
    .admin-content {
        padding: 30px 25px;
    }

    .tabungan-statistics {
        gap: 13px;
    }

    .tabungan-stat-card {
        padding: 20px;
    }

    .tabungan-stat-card strong {
        font-size: 23px;
    }
}

/* RESPONSIVE MOBILE */
@media (max-width: 700px) {
    .admin-main {
        width: 100%;
        margin-left: 0;
    }

    .admin-content {
        padding: 22px 16px;
    }

    .tabungan-header {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
        margin-bottom: 22px;
    }

    .tabungan-header-left h1 {
        font-size: 24px;
    }

    .tabungan-header-left p {
        font-size: 12px;
    }

    .tabungan-header-actions {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }

    .btn-mutasi-tabungan,
    .btn-kembali-tabungan {
        width: 100%;
        min-height: 40px;
        justify-content: center;
        box-sizing: border-box;
    }

    .tabungan-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 11px;
        margin-bottom: 22px;
    }

    .tabungan-stat-card {
        padding: 17px 15px;
        border-radius: 12px;
    }

    .tabungan-stat-card span {
        font-size: 10px;
        line-height: 1.5;
    }

    .tabungan-stat-card strong {
        font-size: 20px;
    }

    .tabungan-stat-card:last-child {
        grid-column: 1 / -1;
    }

    .tabungan-toolbar {
        margin-bottom: 13px;
    }

    .tabungan-search {
        max-width: none;
        gap: 7px;
    }

    .tabungan-search input {
        font-size: 11px;
        padding: 10px;
    }

    .btn-cari-tabungan {
        padding: 0 15px;
        font-size: 11px;
    }

    .tabungan-table-container {
        border-radius: 11px;
    }
}

/* HP KECIL */
@media (max-width: 380px) {
    .admin-content {
        padding: 18px 12px;
    }

    .tabungan-statistics {
        gap: 8px;
    }

    .tabungan-stat-card {
        padding: 14px 12px;
    }

    .tabungan-stat-card strong {
        font-size: 18px;
    }
}

/* =====================================
   RESPONSIVE SIDEBAR TABUNGAN WARGA
===================================== */

/* Ukuran sidebar desktop */
.admin-sidebar {
    width: 250px;
    box-sizing: border-box;
}

.admin-main {
    margin-left: 250px;
    width: calc(100% - 250px);
    min-height: 100vh;
    box-sizing: border-box;
}

/* Tombol hamburger disembunyikan di laptop */
.mobile-menu-button {
    display: none !important;
}

/* Baris judul */
.tabungan-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
}

/* TABLET DAN HP */
@media (max-width: 800px) {

    body {
        overflow-x: hidden;
    }

    /* Sidebar menjadi menu geser */
    .admin-sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 250px !important;
        height: 100vh !important;
        z-index: 1001 !important;
        transform: translateX(-100%) !important;
        transition: transform .3s ease !important;
        overflow-y: auto !important;
    }

    .admin-sidebar.open {
        transform: translateX(0) !important;
    }

    /* Lapisan gelap */
    .sidebar-overlay {
        display: none !important;
    }

    .sidebar-overlay.show {
        display: block !important;
        position: fixed !important;
        inset: 0 !important;
        background: rgba(4, 25, 39, .55) !important;
        z-index: 1000 !important;
    }

    /* Area utama */
    .admin-main {
        margin-left: 0 !important;
        width: 100% !important;
        min-height: 100vh;
    }

    .admin-content {
        width: 100% !important;
        max-width: 100% !important;
        padding: 25px 16px !important;
        box-sizing: border-box !important;
    }

    .tabungan-page {
        width: 100% !important;
        max-width: 100% !important;
    }

    /* Header */
    .tabungan-header {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 14px !important;
    }

    .tabungan-title-row {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
    }

    .tabungan-header-left h1 {
        font-size: 23px !important;
        margin: 0 !important;
    }

    .tabungan-header-left p {
        font-size: 12px !important;
        margin-top: 7px !important;
    }

    /* Tombol hamburger */
    .mobile-menu-button {
        display: inline-flex !important;
        flex: 0 0 42px !important;
        width: 42px !important;
        height: 42px !important;
        align-items: center !important;
        justify-content: center !important;
        border: none !important;
        border-radius: 10px !important;
        background: linear-gradient(
            110deg,
            #0B354D,
            #17B8C8
        ) !important;
        color: #ffffff !important;
        font-size: 23px !important;
        cursor: pointer !important;
        box-shadow: 0 4px 12px rgba(11, 53, 77, .18);
    }

    /* Statistik */
    .tabungan-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 10px !important;
    }

    .tabungan-stat-card {
        min-width: 0;
        padding: 15px 12px !important;
    }

    .tabungan-stat-card strong {
        font-size: 19px !important;
        overflow-wrap: anywhere;
    }

    .tabungan-stat-card:last-child {
        grid-column: 1 / -1;
    }

    /* Tombol header */
    .tabungan-header-actions {
        width: 100% !important;
        flex-direction: column !important;
        align-items: stretch !important;
    }

    .btn-mutasi-tabungan,
    .btn-kembali-tabungan {
        width: 100% !important;
        justify-content: center !important;
        box-sizing: border-box !important;
    }

    /* Pencarian */
    .tabungan-toolbar,
    .tabungan-search {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
    }

    .tabungan-search input {
        min-width: 0 !important;
    }

    /* Tabel */
    .tabungan-table-container {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }

    .tabungan-table {
        min-width: 850px !important;
    }
}

/* HP KECIL */
@media (max-width: 380px) {

    .admin-content {
        padding: 20px 12px !important;
    }

    .tabungan-header-left h1 {
        font-size: 20px !important;
    }

    .tabungan-stat-card strong {
        font-size: 16px !important;
    }
}

/* LAPTOP */
@media (min-width: 801px) {

    .mobile-menu-button {
        display: none !important;
    }

    .sidebar-overlay {
        display: none !important;
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

<aside class="admin-sidebar" id="adminSidebar">

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
            Pengelolaan
        </div>

        <a href="warga.php" class="sidebar-link">
            <span class="sidebar-icon">👥</span>
            <span>Data Warga</span>
        </a>

        <a href="petugas.php" class="sidebar-link">
            <span class="sidebar-icon">👤</span>
            <span>Data Petugas</span>
        </a>

        <a href="kelola_admin.php" class="sidebar-link">
            <span class="sidebar-icon">🛡️</span>
            <span>Kelola Admin</span>
        </a>

        <!-- KATEGORI SAMPAH AKTIF -->
       <a href="kategori.php" class="sidebar-link">
            <span class="sidebar-icon">♻️</span>
            <span>Kategori Sampah</span>
        </a>

        <a href="setoran.php" class="sidebar-link">
            <span class="sidebar-icon">📦</span>
            <span>Data Setoran</span>
        </a>

        <a href="tabungan.php" class="sidebar-link active">
            <span class="sidebar-icon">💰</span>
            <span>Tabungan Warga</span>
        </a>

        <a href="mutasi_tabungan.php" class="sidebar-link">
            <span class="sidebar-icon">↕️</span>
            <span>Mutasi Tabungan</span>
        </a>

        <a href="penarikan.php" class="sidebar-link">
            <span class="sidebar-icon">💸</span>
            <span>Penarikan Tabungan</span>
        </a>

        <a href="laporan.php" class="sidebar-link">
            <span class="sidebar-icon">📊</span>
            <span>Laporan</span>
        </a>

        <a href="backup_database.php" class="sidebar-link">
            <span class="sidebar-icon">💾</span>
            <span>Backup Database</span>
        </a>
        <a href="pengumuman.php" class="sidebar-link ">
            <span class="sidebar-icon">📢</span>
            <span>Kelola Pengumuman</span>
        </a>
        <a href="log_aktivitas.php" class="sidebar-link">
            <span class="sidebar-icon">📋</span>
            <span>Log Aktivitas</span>
        </a>

        <div class="sidebar-section-title">
            Akun
        </div>

        <a href="profil.php" class="sidebar-link">
            <span class="sidebar-icon">⚙️</span>
            <span>Profil</span>
        </a>

        <a href="ubah_password.php" class="sidebar-link">
            <span class="sidebar-icon">🔑</span>
            <span>Ubah Password</span>
        </a>

    </nav>


</aside>

<main class="admin-main">

    <div class="admin-content">

        <div class="tabungan-page">


        <!-- Header -->

        <div class="tabungan-header">

<div class="tabungan-header-left">

    <div class="tabungan-title-row">
        <h1>Tabungan Warga</h1>

        <button
            type="button"
            class="mobile-menu-button"
            id="mobileMenuButton"
            aria-label="Buka menu"
        >
            ☰
        </button>
    </div>

    <p>
        Pantau saldo tabungan warga dari hasil setoran sampah.
    </p>

</div>

            <div class="tabungan-header-actions">

    <a href="mutasi_tabungan.php" class="btn-mutasi-tabungan">
        ↕ Lihat Mutasi
    </a>

    <a href="dashboard.php" class="btn-kembali-tabungan">
        ← Kembali ke Dashboard
    </a>

</div>

        </div>


        <!-- Statistik -->

        <div class="tabungan-statistics">

            <div class="tabungan-stat-card">

                <span>Total Warga Memiliki Tabungan</span>

                <strong>
                    <?php echo number_format($total_warga, 0, ',', '.'); ?>
                </strong>

            </div>


            <div class="tabungan-stat-card">

                <span>Total Saldo Seluruh Warga</span>

                <strong>
                    Rp <?php echo number_format($total_saldo, 0, ',', '.'); ?>
                </strong>

            </div>


            <div class="tabungan-stat-card">

                <span>Rata-rata Saldo Warga</span>

                <strong>
                    Rp <?php echo number_format($rata_rata_saldo, 0, ',', '.'); ?>
                </strong>

            </div>

        </div>


        <!-- Toolbar -->

        <div class="tabungan-toolbar">

            <form method="GET" class="tabungan-search">

                <input
                    type="text"
                    name="keyword"
                    value="<?php echo htmlspecialchars($keyword); ?>"
                    placeholder="Cari nama, username, atau email warga..."
                >

                <button type="submit" class="btn-cari-tabungan">
                    Cari
                </button>

            </form>

        </div>


        <!-- Table -->

        <div class="tabungan-table-container">

            <table class="tabungan-table">

                <thead>

                    <tr>

                        <th>No</th>

                        <th>Warga</th>

                        <th>Email</th>

                        <th>No. HP</th>

                        <th>Saldo</th>

                        <th>Status</th>

                        <th>Terakhir Diperbarui</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (count($data_tabungan) > 0): ?>

                        <?php foreach ($data_tabungan as $index => $tabungan): ?>

                            <tr>

                                <td>
                                    <?php echo $index + 1; ?>
                                </td>

                                <td>

                                    <div class="nama-warga">
                                        <?php echo htmlspecialchars($tabungan['nama_lengkap']); ?>
                                    </div>

                                    <div class="username-warga">
                                        @<?php echo htmlspecialchars($tabungan['username']); ?>
                                    </div>

                                </td>

                                <td>
                                    <?php echo htmlspecialchars($tabungan['email']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($tabungan['no_hp'] ?? '-'); ?>
                                </td>

                                <td>

                                    <span class="saldo-warga">
                                        Rp <?php echo number_format(
                                            (float) $tabungan['saldo'],
                                            0,
                                            ',',
                                            '.'
                                        ); ?>
                                    </span>

                                </td>

                                <td>

                                    <?php if ((float) $tabungan['saldo'] > 0): ?>

                                        <span class="status-saldo saldo-tersedia">
                                            Saldo Tersedia
                                        </span>

                                    <?php else: ?>

                                        <span class="status-saldo saldo-kosong">
                                            Saldo Kosong
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                   <?php
$tanggal_update = strtotime($tabungan['updated_at']);

if ($tanggal_update !== false) {
    echo date('d/m/Y H:i', $tanggal_update);
} else {
    echo '-';
}
?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="7" class="tabungan-empty">

                                <?php if ($keyword !== ''): ?>

                                    Tidak ditemukan data tabungan
                                    untuk pencarian
                                    "<strong><?php echo htmlspecialchars($keyword); ?></strong>".

                                <?php else: ?>

                                    Belum ada data tabungan warga.

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const menuButton = document.getElementById('mobileMenuButton');
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (!menuButton || !sidebar || !overlay) return;

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    }

    menuButton.addEventListener('click', function () {
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    overlay.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 800) {
            closeSidebar();
        }
    });

});
</script>
</body>
</html>

</body>

</html>