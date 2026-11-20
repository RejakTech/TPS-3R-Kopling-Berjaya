
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

// =============================
// VALIDASI FILTER TANGGAL
// =============================

$tanggal_awal = $_GET['tanggal_awal'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? '';

function validasiTanggal($tanggal) {
    if ($tanggal === '') {
        return true;
    }

    $format = DateTime::createFromFormat('Y-m-d', $tanggal);

    return $format && $format->format('Y-m-d') === $tanggal;
}

if (!validasiTanggal($tanggal_awal)) {
    $tanggal_awal = '';
}

if (!validasiTanggal($tanggal_akhir)) {
    $tanggal_akhir = '';
}

if (
    $tanggal_awal !== '' &&
    $tanggal_akhir !== '' &&
    $tanggal_awal > $tanggal_akhir
) {
    $tanggal_awal = '';
    $tanggal_akhir = '';
}

// =============================
// KONDISI FILTER TANGGAL
// =============================

$where = " WHERE 1=1";
$params = [];

if ($tanggal_awal !== '') {
    $where .= " AND DATE(s.tanggal_setor) >= ?";
    $params[] = $tanggal_awal;
}

if ($tanggal_akhir !== '') {
    $where .= " AND DATE(s.tanggal_setor) <= ?";
    $params[] = $tanggal_akhir;
}

// =============================
// FUNGSI MENJALANKAN QUERY
// =============================

function jalankanLaporan($conn, $sql, $params = []) {

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        die("Gagal menyiapkan laporan.");
    }

    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        die("Gagal menjalankan laporan.");
    }

    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        mysqli_stmt_close($stmt);
        die("Gagal mengambil data laporan.");
    }

    mysqli_stmt_close($stmt);

    return $result;
}

// =============================
// STATISTIK LAPORAN
// =============================

$sql_statistik = "
    SELECT
        COUNT(*) AS total_setoran,

        SUM(CASE
            WHEN s.status = 'diterima' THEN 1
            ELSE 0
        END) AS total_diterima,

        SUM(CASE
            WHEN s.status = 'menunggu' THEN 1
            ELSE 0
        END) AS total_menunggu,

        SUM(CASE
            WHEN s.status = 'ditolak' THEN 1
            ELSE 0
        END) AS total_ditolak,

        COALESCE(SUM(
            CASE
                WHEN s.status = 'diterima' THEN s.berat_kg
                ELSE 0
            END
        ), 0) AS total_berat,

        COALESCE(SUM(
            CASE
                WHEN s.status = 'diterima' THEN s.total_harga
                ELSE 0
            END
        ), 0) AS total_nilai,

        COUNT(DISTINCT CASE
            WHEN s.status = 'diterima' THEN s.user_id
            ELSE NULL
        END) AS warga_aktif

    FROM setoran_sampah s
    $where
";

$result_statistik = jalankanLaporan(
    $conn,
    $sql_statistik,
    $params
);

$statistik = mysqli_fetch_assoc($result_statistik);

// =============================
// REKAP BERDASARKAN KATEGORI
// =============================

$sql_kategori = "
    SELECT
        k.nama_kategori,
        COUNT(s.id) AS jumlah_setoran,
        COALESCE(SUM(s.berat_kg), 0) AS total_berat,
        COALESCE(SUM(s.total_harga), 0) AS total_nilai

    FROM setoran_sampah s

    LEFT JOIN kategori_sampah k
        ON s.kategori_id = k.id

    $where
    AND s.status = 'diterima'

    GROUP BY s.kategori_id, k.nama_kategori

    ORDER BY total_berat DESC
";

$result_kategori = jalankanLaporan(
    $conn,
    $sql_kategori,
    $params
);


// =============================
// DATA GRAFIK BERAT SAMPAH
// =============================

$sql_grafik = "
    SELECT
        k.nama_kategori,
        COALESCE(SUM(s.berat_kg), 0) AS total_berat
    FROM setoran_sampah s
    LEFT JOIN kategori_sampah k
        ON s.kategori_id = k.id
    $where
    AND s.status = 'diterima'
    GROUP BY s.kategori_id, k.nama_kategori
    ORDER BY total_berat DESC
";

$result_grafik = jalankanLaporan(
    $conn,
    $sql_grafik,
    $params
);

$label_grafik = [];
$data_grafik = [];

while ($grafik = mysqli_fetch_assoc($result_grafik)) {
    $label_grafik[] = $grafik['nama_kategori'] ?? 'Tidak diketahui';
    $data_grafik[] = (float) $grafik['total_berat'];
}


// =============================
// DAFTAR SETORAN TERBARU
// =============================

$sql_riwayat = "
    SELECT
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

    $where

    ORDER BY s.id DESC
    LIMIT 20
";

$result_riwayat = jalankanLaporan(
    $conn,
    $sql_riwayat,
    $params
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan Bank Sampah - Admin</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        /* ==============================
   HEADER LAPORAN
============================== */

.laporan-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 28px;
    width: 100%;
}

.laporan-header .admin-heading {
    flex: 1;
    min-width: 0;
}

.laporan-header .admin-heading h1 {
    color: #0B4650;
    font-size: 29px;
    font-weight: 700;
    margin: 0 0 7px;
}

.laporan-header .admin-heading p {
    color: #718995;
    font-size: 14px;
    margin: 0;
}

.laporan-header-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.laporan-kembali {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 18px;
    border-radius: 9px;
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    color: #fff !important;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
}

.laporan-filter {
    background: #fff;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 25px;
}

.laporan-filter h2,
.laporan-section-heading h2 {
    color: #0B4650;
}

.laporan-filter p,
.laporan-section-heading p {
    color: #718995;
}

.laporan-filter-form {
    display: flex;
    align-items: end;
    flex-wrap: wrap;
    gap: 15px;
}

.laporan-filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
    min-width: 180px;
}

.laporan-filter-group label {
    color: #345b65;
    font-size: 13px;
    font-weight: 600;
}

.laporan-filter-group input {
    padding: 11px 13px;
    border: 1px solid #E1EAED;
    border-radius: 8px;
    font-family: inherit;
}

.laporan-filter-actions {
    display: flex;
    gap: 8px;
}

.laporan-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 17px;
    border: none;
    border-radius: 8px;
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    color: white;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.laporan-btn-reset {
    background: #EAF3F6;
    color: #0B4650;
}

.laporan-section {
    margin-top: 32px;
}

.laporan-section-heading {
    margin-bottom: 17px;
}

.laporan-section-heading h2 {
    font-size: 20px;
    margin-bottom: 6px;
}

.laporan-table-container {
    background: #fff;
    border: 1px solid #E1EAED;
    border-radius: 12px;
    overflow-x: auto;
}

.laporan-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

.laporan-table th,
.laporan-table td {
    padding: 15px;
    border-bottom: 1px solid #edf2f0;
    text-align: left;
    font-size: 13px;
}

.laporan-table th {
    background: #EAF3F6;
    color: #0B4650;
    font-weight: 600;
}

.laporan-table td {
    color: #536b75;
}

.laporan-table tr:last-child td {
    border-bottom: none;
}

.laporan-table tr:hover td {
    background: #F4FAFB;
}

.laporan-status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.laporan-status.diterima {
    background: #dcf7e8;
    color: #16834b;
}

.laporan-status.menunggu {
    background: #fff4d8;
    color: #a36b00;
}

.laporan-status.ditolak {
    background: #ffe5e5;
    color: #c03939;
}

.laporan-catatan {
    color: #718995;
    font-size: 12px;
    margin-top: 12px;
}

/* GRAFIK */

.laporan-grafik-container {
    background: #fff;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(11, 53, 77, .04);
}

.laporan-grafik-header {
    margin-bottom: 22px;
}

.laporan-grafik-header h3 {
    color: #0B4650;
    font-size: 16px;
    margin: 0 0 6px;
}

.laporan-grafik-header p {
    color: #718995;
    font-size: 12px;
    margin: 0;
}

.laporan-grafik-area {
    position: relative;
    width: 100%;
    height: 320px;
}

.laporan-grafik-kosong {
    padding: 55px 15px;
    text-align: center;
    color: #718995;
    font-size: 14px;
}

/* TOMBOL EXPORT DAN CETAK */

.laporan-header-actions .btn-export-excel {
    background: #159BAE;
    color: #fff;
}

.laporan-header-actions .btn-cetak-laporan {
    background: #0B354D;
    color: #fff;
}

.btn-export-excel,
.btn-cetak-laporan {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 18px;
    border: none;
    border-radius: 9px;
    color: #fff;
    text-decoration: none;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: .2s ease;
}

.btn-export-excel:hover,
.btn-cetak-laporan:hover {
    background: #0B4650;
    transform: translateY(-1px);
}

/* RESPONSIVE */

@media(max-width: 700px) {
    .laporan-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .laporan-header-actions {
        width: 100%;
        justify-content: flex-start;
    }
}

@media(max-width: 600px) {
    .laporan-filter-form {
        flex-direction: column;
        align-items: stretch;
    }

    .laporan-filter-group {
        width: 100%;
    }

    .laporan-filter-actions {
        width: 100%;
    }

    .laporan-filter-actions a,
    .laporan-filter-actions button {
        flex: 1;
    }

    .laporan-grafik-container {
        padding: 16px;
    }

    .laporan-grafik-area {
        height: 260px;
    }
}

@media(max-width: 450px) {
    .laporan-header-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .laporan-header-actions a,
    .laporan-header-actions button {
        width: 100%;
    }
}
/* ==============================
   MODE CETAK PDF - FINAL
============================== */

@media print {

    @page {
        size: A4 landscape;
        margin: 12mm;
    }

    html,
    body {
        width: 100% !important;
        background: #fff !important;
        color: #263d38 !important;
        font-size: 11px !important;

        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Sembunyikan navigasi dan filter */

    .admin-navbar,
    .laporan-filter,
    .laporan-kembali,
    .btn-cetak-laporan,
    .btn-export-excel,
    .laporan-header-actions {
        display: none !important;
    }

    /* Gunakan seluruh lebar halaman */

    .admin-dashboard,
    .admin-content {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
    }

    /* Judul laporan */

    .laporan-header {
        display: block !important;
        margin: 0 0 20px !important;
        padding: 0 0 12px !important;
        border-bottom: 2px solid #0f766e;
    }

    .laporan-header h1 {
        font-size: 23px !important;
        color: #145c52 !important;
        margin-bottom: 5px !important;
    }

    .laporan-header p {
        font-size: 11px !important;
        color: #718783 !important;
    }

    /* Statistik */

    .admin-statistics {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 10px !important;
        margin-bottom: 15px !important;
    }

    .admin-stat-card {
        padding: 13px !important;
        border: 1px solid #e5eeeb !important;
        border-radius: 8px !important;
        box-shadow: none !important;
        break-inside: avoid;
    }

    .admin-stat-card h3 {
        font-size: 10px !important;
    }

    .admin-stat-card p {
        font-size: 17px !important;
    }

    /* Jarak antarbagian */

    .laporan-section {
        margin-top: 20px !important;
    }

    .laporan-section-heading {
        margin-bottom: 12px !important;
        break-after: avoid !important;
        page-break-after: avoid !important;
    }

    .laporan-section-heading h2 {
        font-size: 16px !important;
    }

    /* Grafik */

    .laporan-grafik-container {
        padding: 16px !important;
        border: 1px solid #e5eeeb !important;
        box-shadow: none !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    .laporan-grafik-area {
    position: relative !important;
    width: 100% !important;
    height: 190px !important;
    max-height: 190px !important;
    break-inside: avoid !important;
    page-break-inside: avoid !important;
}

.laporan-grafik-area canvas {
    display: block !important;
    width: 100% !important;
    height: 190px !important;
    max-height: 190px !important;
}

    /* Rekap kategori dimulai di halaman baru */

    .laporan-rekap-cetak {
        break-before: page !important;
        page-break-before: always !important;
    }

    /* Tabel */

    .laporan-table-container {
        width: 100% !important;
        overflow: visible !important;
        border: 1px solid #e5eeeb !important;
        border-radius: 8px !important;
        box-shadow: none !important;
    }

    .laporan-table {
        width: 100% !important;
        min-width: 100% !important;
        border-collapse: collapse !important;
    }

    .laporan-table thead {
        display: table-header-group;
    }

    .laporan-table tr {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    .laporan-table th,
    .laporan-table td {
        padding: 9px !important;
        font-size: 10px !important;
    }

    .laporan-table th {
        background: #f0f7f5 !important;
        color: #145c52 !important;
    }

    /* Catatan laporan */

    .laporan-catatan {
        font-size: 9px !important;
        margin-top: 10px !important;
    }

    a {
        text-decoration: none !important;
        color: inherit !important;
    }

}

.laporan-section:has(.laporan-grafik-container) {
    break-inside: avoid !important;
    page-break-inside: avoid !important;
}

.laporan-grafik-container {
    break-inside: avoid !important;
    page-break-inside: avoid !important;
}

.laporan-grafik-header {
    break-after: avoid !important;
    page-break-after: avoid !important;
}

.btn-export-excel {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 18px;
    border: none;
    border-radius: 9px;
    background: #16834b;
    color: #fff;
    text-decoration: none;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: .2s ease;
}

.btn-export-excel:hover {
    background: #11683b;
    transform: translateY(-1px);
}
/* ==============================
   STATISTIK LAPORAN
============================== */

.admin-statistics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    width: 100%;
    margin-bottom: 18px;
}

.admin-stat-card {
    position: relative;
    min-width: 0;
    min-height: 115px;
    padding: 22px 20px;
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(11, 53, 77, .04);
    overflow: hidden;
}

.admin-stat-card h3 {
    color: #718995;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.5;
    margin: 0 0 12px;
}

.admin-stat-card p {
    color: #0B4650;
    font-size: 25px;
    font-weight: 700;
    line-height: 1.3;
    margin: 0;
    overflow-wrap: anywhere;
}

/* Garis aksen setiap kartu */

.admin-stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: #159BAE;
}

.admin-stat-card:nth-child(2)::before {
    background: #17B8C8;
}

.admin-stat-card:nth-child(3)::before {
    background: #E49A0F;
}

.admin-stat-card:nth-child(4)::before {
    background: #0B354D;
}

/* Baris statistik kedua */

.admin-statistics + .admin-statistics {
    margin-top: 0;
}

/* RESPONSIVE TABLET */

@media (max-width: 1000px) {
    .admin-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 15px;
    }
}

/* RESPONSIVE HP */

@media (max-width: 500px) {
    .admin-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .admin-stat-card {
        min-height: 105px;
        padding: 17px 14px;
    }

    .admin-stat-card h3 {
        font-size: 11px;
        margin-bottom: 9px;
    }

    .admin-stat-card p {
        font-size: 20px;
    }
}

@media (max-width: 350px) {
    .admin-statistics {
        grid-template-columns: 1fr;
    }
}

/* =====================================
   RESPONSIVE SIDEBAR DAN HAMBURGER
===================================== */

.mobile-menu-button {
    display: none !important;
}

/* TAMPILAN HP DAN TABLET */
@media screen and (max-width: 800px) {

    body {
        overflow-x: hidden;
    }

    /* SIDEBAR MOBILE */
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

    /* OVERLAY */
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

    /* KONTEN UTAMA */
    .admin-content {
        margin-left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        padding: 22px 16px !important;
        box-sizing: border-box !important;
    }

    /* HEADER LAPORAN */
    .laporan-header {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 15px !important;
        width: 100% !important;
        margin-bottom: 22px !important;
    }

    .laporan-header .admin-heading {
        width: 100% !important;
        min-width: 0 !important;
    }

    .laporan-title-line {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        width: 100% !important;
    }

    .laporan-header .admin-heading h1 {
        font-size: 24px !important;
        line-height: 1.3 !important;
        margin: 0 !important;
    }

    .laporan-header .admin-heading p {
        font-size: 12px !important;
        margin-top: 8px !important;
    }

    /* TOMBOL HAMBURGER */
    .mobile-menu-button {
        display: inline-flex !important;
        position: static !important;
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
        color: #fff !important;
        font-size: 23px !important;
        cursor: pointer !important;
        box-shadow: 0 4px 12px rgba(11, 53, 77, .18);
    }

    /* TOMBOL HEADER */
    .laporan-header-actions {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: wrap !important;
        justify-content: flex-start !important;
        width: 100% !important;
        gap: 9px !important;
    }

    .laporan-header-actions a,
    .laporan-header-actions button {
        flex: 1 1 auto;
        min-width: 0;
        text-align: center;
    }

    /* FILTER TANGGAL */
    .laporan-filter {
        padding: 17px !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .laporan-filter-form {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 13px !important;
    }

    .laporan-filter-group {
        width: 100% !important;
        min-width: 0 !important;
    }

    .laporan-filter-actions {
        display: flex !important;
        width: 100% !important;
    }

    .laporan-filter-actions a,
    .laporan-filter-actions button {
        flex: 1 !important;
    }

    /* KARTU STATISTIK */
    .admin-statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 11px !important;
        width: 100% !important;
    }

    .admin-stat-card {
        min-width: 0 !important;
        padding: 16px 12px !important;
    }

    .admin-stat-card h3 {
        font-size: 11px !important;
    }

    .admin-stat-card p {
        font-size: 19px !important;
        overflow-wrap: anywhere !important;
    }

    /* GRAFIK */
    .laporan-grafik-container {
        width: 100% !important;
        padding: 16px !important;
        box-sizing: border-box !important;
    }

    .laporan-grafik-area {
        width: 100% !important;
        height: 260px !important;
    }

    /* TABEL */
    .laporan-table-container {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }

    .laporan-table {
        min-width: 650px !important;
    }

    .laporan-section {
        width: 100% !important;
    }
}

/* HP KECIL */
@media screen and (max-width: 380px) {

    .admin-content {
        padding: 18px 12px !important;
    }

    .laporan-header .admin-heading h1 {
        font-size: 21px !important;
    }

    .admin-stat-card {
        padding: 13px 10px !important;
    }

    .admin-stat-card p {
        font-size: 17px !important;
    }

    .laporan-header-actions {
        flex-direction: column !important;
    }

    .laporan-header-actions a,
    .laporan-header-actions button {
        width: 100% !important;
    }
}

/* TAMPILAN LAPTOP */
@media screen and (min-width: 801px) {

    .mobile-menu-button {
        display: none !important;
    }

    .sidebar-overlay {
        display: none !important;
    }
}

/* MODE CETAK */
@media print {

    .admin-sidebar,
    .sidebar-overlay,
    .mobile-menu-button {
        display: none !important;
    }

}

/* =====================================
   TYPOGRAPHY - LAPORAN BANK SAMPAH
===================================== */

/* Font utama halaman */
.admin-content {
    font-family: 'Poppins', sans-serif;
    color: #263F4A;
    line-height: 1.6;
    letter-spacing: 0;
}

/* JUDUL UTAMA */
.laporan-header .admin-heading h1 {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.35;
    letter-spacing: -0.6px;
    color: #0B354D;
    margin-bottom: 8px;
}

.laporan-header .admin-heading p {
    font-size: 13.5px;
    font-weight: 400;
    line-height: 1.7;
    color: #718995;
}

/* JUDUL FILTER */
.laporan-filter h2 {
    font-size: 17px;
    font-weight: 650;
    line-height: 1.4;
    margin: 0 0 5px;
    color: #0B354D;
}

.laporan-filter > p {
    font-size: 12.5px;
    line-height: 1.6;
    margin-bottom: 20px;
}

/* JUDUL SETIAP BAGIAN */
.laporan-section-heading h2 {
    font-size: 19px;
    font-weight: 700;
    line-height: 1.4;
    letter-spacing: -0.3px;
    margin: 0 0 5px;
    color: #0B354D;
}

.laporan-section-heading p {
    font-size: 12.5px;
    font-weight: 400;
    line-height: 1.7;
    margin: 0;
    color: #7A8E98;
}

/* KARTU STATISTIK */
.admin-stat-card h3 {
    font-size: 12px;
    font-weight: 600;
    line-height: 1.6;
    letter-spacing: 0;
    color: #718995;
    margin-bottom: 10px;
}

.admin-stat-card p {
    font-size: 25px;
    font-weight: 700;
    line-height: 1.35;
    letter-spacing: -0.5px;
    color: #0B354D;
}

/* JUDUL GRAFIK */
.laporan-grafik-header h3 {
    font-size: 15px;
    font-weight: 650;
    line-height: 1.5;
    color: #0B354D;
}

.laporan-grafik-header p {
    font-size: 12px;
    line-height: 1.6;
    color: #82939B;
}

/* LABEL FILTER */
.laporan-filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #345B65;
}

.laporan-filter-group input {
    font-size: 13px;
    color: #345B65;
}

/* TOMBOL */
.laporan-btn,
.laporan-kembali,
.btn-export-excel,
.btn-cetak-laporan {
    font-size: 12.5px;
    font-weight: 600;
    letter-spacing: 0;
}

/* TABEL */
.laporan-table th {
    font-size: 12px;
    font-weight: 650;
    line-height: 1.5;
    letter-spacing: 0;
    color: #0B4650;
}

.laporan-table td {
    font-size: 12px;
    font-weight: 400;
    line-height: 1.7;
    color: #536B75;
}

/* STATUS */
.laporan-status {
    font-size: 10.5px;
    font-weight: 650;
    letter-spacing: 0.1px;
}

/* CATATAN */
.laporan-catatan {
    font-size: 11px;
    font-weight: 400;
    line-height: 1.7;
    color: #82939B;
}

/* RESPONSIVE HP */
@media screen and (max-width: 800px) {
    .laporan-header .admin-heading h1 {
        font-size: 23px;
        letter-spacing: -0.4px;
    }

    .laporan-section-heading h2 {
        font-size: 17px;
    }

    .admin-stat-card p {
        font-size: 20px;
    }

    .laporan-table th,
    .laporan-table td {
        font-size: 11.5px;
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
<body>

<div class="admin-dashboard">

    <!-- OVERLAY SIDEBAR MOBILE -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- SIDEBAR KIRI -->
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

        <a href="tabungan.php" class="sidebar-link ">
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

        <a href="laporan.php" class="sidebar-link active">
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


    <main class="admin-content">

        <!-- HEADER -->

        
<div class="laporan-header">

    <div class="admin-heading">

        <div class="laporan-title-line">

            <h1>Laporan Bank Sampah</h1>

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
            Ringkasan aktivitas dan hasil pengelolaan bank sampah.
        </p>

    </div>

    <div class="laporan-header-actions">

        <a
    href="export_laporan.php?<?php
        echo htmlspecialchars(http_build_query([
            'tanggal_awal' => $tanggal_awal,
            'tanggal_akhir' => $tanggal_akhir
        ]));
    ?>"
    class="btn-export-excel"
>
    📊 Export Excel
</a>

<button
    type="button"
    onclick="window.print()"
    class="btn-cetak-laporan"
>
    🖨 Cetak Laporan
</button>

        <a href="dashboard.php" class="laporan-kembali">
            ← Kembali ke Dashboard
        </a>

    </div>

</div>


        <!-- FILTER PERIODE -->

        <section class="laporan-filter">

            <h2>Filter Periode Laporan</h2>

            <p>
                Tentukan rentang tanggal untuk melihat laporan.
            </p>

            <form method="GET" action="laporan.php" class="laporan-filter-form">

                <div class="laporan-filter-group">

                    <label for="tanggal_awal">Tanggal Awal</label>

                    <input
                        type="date"
                        name="tanggal_awal"
                        id="tanggal_awal"
                        value="<?php echo htmlspecialchars($tanggal_awal); ?>"
                    >

                </div>

                <div class="laporan-filter-group">

                    <label for="tanggal_akhir">Tanggal Akhir</label>

                    <input
                        type="date"
                        name="tanggal_akhir"
                        id="tanggal_akhir"
                        value="<?php echo htmlspecialchars($tanggal_akhir); ?>"
                    >

                </div>

                <div class="laporan-filter-actions">

                    <button type="submit" class="laporan-btn">
                        Terapkan
                    </button>

                    <a href="laporan.php" class="laporan-btn laporan-btn-reset">
                        Reset
                    </a>

                </div>

            </form>

        </section>

        <!-- RINGKASAN STATISTIK -->

        <div class="admin-statistics">

            <div class="admin-stat-card">

                <h3>Total Pengajuan</h3>

                <p>
                    <?php echo number_format((int)$statistik['total_setoran'], 0, ',', '.'); ?>
                </p>

            </div>

            <div class="admin-stat-card">

                <h3>Setoran Diterima</h3>

                <p>
                    <?php echo number_format((int)$statistik['total_diterima'], 0, ',', '.'); ?>
                </p>

            </div>

            <div class="admin-stat-card">

                <h3>Total Berat Sampah</h3>

                <p>
                    <?php echo number_format((float)$statistik['total_berat'], 2, ',', '.'); ?> Kg
                </p>

            </div>

            <div class="admin-stat-card">

                <h3>Nilai Ekonomi</h3>

                <p>
                    Rp <?php echo number_format((float)$statistik['total_nilai'], 0, ',', '.'); ?>
                </p>

            </div>

        </div>

        <div class="admin-statistics">

            <div class="admin-stat-card">

                <h3>Menunggu Verifikasi</h3>

                <p>
                    <?php echo number_format((int)$statistik['total_menunggu'], 0, ',', '.'); ?>
                </p>

            </div>

            <div class="admin-stat-card">

                <h3>Setoran Ditolak</h3>

                <p>
                    <?php echo number_format((int)$statistik['total_ditolak'], 0, ',', '.'); ?>
                </p>

            </div>

            <div class="admin-stat-card">

                <h3>Warga Aktif</h3>

                <p>
                    <?php echo number_format((int)$statistik['warga_aktif'], 0, ',', '.'); ?>
                </p>

            </div>

        </div>

        
<!-- GRAFIK LAPORAN -->

<section class="laporan-section">

    <div class="laporan-section-heading">

        <h2>Visualisasi Pengelolaan Sampah</h2>

        <p>
            Perbandingan berat sampah yang berhasil diterima berdasarkan kategori.
        </p>

    </div>

    <div class="laporan-grafik-container">

        <?php if (!empty($label_grafik)) : ?>

            <div class="laporan-grafik-header">
                <div>
                    <h3>Berat Sampah per Kategori</h3>
                    <p>Total berat dalam kilogram (Kg)</p>
                </div>
            </div>

            <div class="laporan-grafik-area">
                <canvas id="grafikSampah"></canvas>
            </div>

        <?php else : ?>

            <div class="laporan-grafik-kosong">
                Belum ada data setoran diterima untuk ditampilkan.
            </div>

        <?php endif; ?>

    </div>

</section>



        <!-- REKAP KATEGORI -->

        <section class="laporan-section laporan-rekap-cetak">

        <div class="laporan-section-heading">

        <h2>Rekap Berdasarkan Kategori</h2>

                <p>
                    Ringkasan sampah yang berhasil diterima berdasarkan jenisnya.
                </p>

            </div>

            <div class="laporan-table-container">

                <table class="laporan-table">

                    <thead>

                        <tr>
                            <th>No</th>
                            <th>Kategori Sampah</th>
                            <th>Jumlah Setoran</th>
                            <th>Total Berat</th>
                            <th>Nilai Ekonomi</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (mysqli_num_rows($result_kategori) > 0) : ?>

                            <?php $no = 1; ?>

                            <?php while ($kategori = mysqli_fetch_assoc($result_kategori)) : ?>

                                <tr>

                                    <td><?php echo $no++; ?></td>

                                    <td>
                                        <?php echo htmlspecialchars($kategori['nama_kategori'] ?? 'Kategori tidak tersedia'); ?>
                                    </td>

                                    <td>
                                        <?php echo number_format((int)$kategori['jumlah_setoran'], 0, ',', '.'); ?>
                                    </td>

                                    <td>
                                        <?php echo number_format((float)$kategori['total_berat'], 2, ',', '.'); ?> Kg
                                    </td>

                                    <td>
                                        Rp <?php echo number_format((float)$kategori['total_nilai'], 0, ',', '.'); ?>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else : ?>

                            <tr>
                                <td colspan="5" style="text-align:center;">
                                    Belum ada data setoran diterima pada periode ini.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

        <!-- RIWAYAT SETORAN -->

        <section class="laporan-section">

            <div class="laporan-section-heading">

                <h2>Riwayat Setoran</h2>

                <p>
                    Menampilkan maksimal 20 pengajuan setoran terbaru sesuai periode.
                </p>

            </div>

            <div class="laporan-table-container">

                <table class="laporan-table">

                    <thead>

                        <tr>
                            <th>No</th>
                            <th>Nama Warga</th>
                            <th>Kategori</th>
                            <th>Tanggal</th>
                            <th>Berat</th>
                            <th>Nilai</th>
                            <th>Status</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (mysqli_num_rows($result_riwayat) > 0) : ?>

                            <?php $no = 1; ?>

                            <?php while ($riwayat = mysqli_fetch_assoc($result_riwayat)) : ?>

                                <tr>

                                    <td><?php echo $no++; ?></td>

                                    <td>
                                        <?php echo htmlspecialchars($riwayat['nama_lengkap'] ?? '-'); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($riwayat['nama_kategori'] ?? '-'); ?>
                                    </td>

                                    <td>
                                        <?php
                                        $tanggal_riwayat = strtotime($riwayat['tanggal_setor']);

                                        if ($tanggal_riwayat !== false) {
                                            echo date('d/m/Y', $tanggal_riwayat);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo number_format((float)$riwayat['berat_kg'], 2, ',', '.'); ?> Kg
                                    </td>

                                    <td>
                                        Rp <?php echo number_format((float)$riwayat['total_harga'], 0, ',', '.'); ?>
                                    </td>

                                    <td>

                                        <span class="laporan-status <?php echo htmlspecialchars($riwayat['status']); ?>">

                                            <?php echo htmlspecialchars(ucfirst($riwayat['status'])); ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else : ?>

                            <tr>
                                <td colspan="7" style="text-align:center;">
                                    Belum ada data setoran pada periode ini.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <p class="laporan-catatan">
                Catatan: Nilai ekonomi dan total berat hanya menghitung setoran yang berstatus diterima.
                Tabel riwayat menampilkan seluruh status setoran.
            </p>

        </section>

    </main>

</div>


<!-- CHART JS -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if (!empty($label_grafik)) : ?>

<script>

const labelSampah = <?php
    echo json_encode(
        $label_grafik,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
?>;

const dataSampah = <?php
    echo json_encode($data_grafik);
?>;

const canvasSampah = document.getElementById('grafikSampah');

if (canvasSampah && typeof Chart !== 'undefined') {

    new Chart(canvasSampah, {

        type: 'bar',

        data: {
            labels: labelSampah,

            datasets: [{
                label: 'Berat Sampah (Kg)',
                data: dataSampah,

                backgroundColor: '#159b83',
                hoverBackgroundColor: '#0f766e',

                borderRadius: 7,
                maxBarThickness: 65
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: false
                },

                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' ' +
                                context.parsed.y.toLocaleString('id-ID') +
                                ' Kg';
                        }
                    }
                }
            },

            scales: {
                y: {
                    beginAtZero: true,

                    title: {
                        display: true,
                        text: 'Berat (Kg)'
                    },

                    ticks: {
                        precision: 0
                    },

                    grid: {
                        color: '#edf2f0'
                    }
                },

                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }

    });

}

</script>
<?php endif; ?>

<!-- JAVASCRIPT SIDEBAR MOBILE -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    const menuButton = document.getElementById('mobileMenuButton');
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (!menuButton || !sidebar || !overlay) {
        return;
    }

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

