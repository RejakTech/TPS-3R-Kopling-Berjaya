
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
require_once '../config/notifikasi.php';
require_once '../config/log_aktivitas.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$error = "";

// Token keamanan
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Nilai awal formulir
$kategori_id = "";
$berat_kg = "";
$tanggal_input = date('d/m/Y');
$tanggal_setor = "";
$catatan = "";

// Proses pengajuan setoran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $kategori_id = (int) ($_POST['kategori_id'] ?? 0);
    $berat_kg = trim($_POST['berat_kg'] ?? '');
    $tanggal_input = trim($_POST['tanggal_setor'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    // Validasi token keamanan
   if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {

        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";

    } elseif ($kategori_id <= 0) {

        $error = "Silakan pilih kategori sampah.";

    } elseif (
        !is_numeric($berat_kg) ||
        !is_finite((float) $berat_kg) ||
        (float) $berat_kg <= 0 ||
        (float) $berat_kg > 99999999.99
    ) {

        $error = "Berat sampah harus berupa angka lebih dari 0.";

    } else {

        // Konversi tanggal DD/MM/YYYY ke YYYY-MM-DD
        $tanggal_obj = DateTime::createFromFormat(
            '!d/m/Y',
            $tanggal_input
        );

        // Pastikan tanggal benar dan tidak mengalami perubahan otomatis
        if (
            !$tanggal_obj ||
            $tanggal_obj->format('d/m/Y') !== $tanggal_input
        ) {

            $error = "Format tanggal tidak valid. Gunakan DD/MM/YYYY.";

        } else {

            $tanggal_setor = $tanggal_obj->format('Y-m-d');

            // Tanggal tidak boleh melebihi hari ini
            if ($tanggal_setor > date('Y-m-d')) {

                $error = "Tanggal setoran tidak boleh melebihi hari ini.";

            } elseif (strlen($catatan) > 5000) {

                $error = "Catatan maksimal 5000 karakter.";

            } else {

                try {

                    // Ambil kategori aktif dan harga dari database
                    $cek_kategori = mysqli_prepare(
                        $conn,
                        "SELECT nama_kategori, harga_per_kg
                         FROM kategori_sampah
                         WHERE id = ? AND status = 'aktif'
                         LIMIT 1"
                    );

                    mysqli_stmt_bind_param(
                        $cek_kategori,
                        "i",
                        $kategori_id
                    );

                    mysqli_stmt_execute($cek_kategori);

                    $result_kategori = mysqli_stmt_get_result(
                        $cek_kategori
                    );

                    $kategori = mysqli_fetch_assoc(
                        $result_kategori
                    );

                    mysqli_stmt_close($cek_kategori);

                    if (!$kategori) {

                        $error = "Kategori tidak ditemukan atau sedang nonaktif.";

                    } else {

                        // Ambil berat dan harga dari database
                        $berat = (float) $berat_kg;
                        $harga = (float) $kategori['harga_per_kg'];

                        // Hitung total harga di server
                        $total_harga = round(
                            $berat * $harga,
                            2
                        );

                        if (
                            !is_finite($total_harga) ||
                            $total_harga > 9999999999.99
                        ) {

                            $error = "Total harga melebihi batas yang diperbolehkan.";

                        } else {

                            $user_id = (int) $_SESSION['user_id'];
                            $status = "menunggu";

                            $catatan_db = $catatan !== ""
                                ? $catatan
                                : null;

                            // Simpan pengajuan ke database
                            $insert = mysqli_prepare(
                                $conn,
                                "INSERT INTO setoran_sampah
                                (
                                    user_id,
                                    kategori_id,
                                    tanggal_setor,
                                    berat_kg,
                                    harga_per_kg,
                                    total_harga,
                                    status,
                                    catatan
                                )
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                            );

                            mysqli_stmt_bind_param(
                                $insert,
                                "iisdddss",
                                $user_id,
                                $kategori_id,
                                $tanggal_setor,
                                $berat,
                                $harga,
                                $total_harga,
                                $status,
                                $catatan_db
                            );

                            
mysqli_stmt_execute($insert);

// Ambil ID setoran yang baru dibuat
$setoran_id = mysqli_insert_id($conn);

mysqli_stmt_close($insert);

// ===================================
// CATAT AKTIVITAS WARGA
// ===================================

catatAktivitas(
    $conn,
    'Pengajuan Setoran',
    'Mengajukan setoran #' . $setoran_id .
    ' dengan kategori ' . $kategori['nama_kategori'] .
    ' sebanyak ' . number_format($berat, 2, ',', '.') . ' Kg.'
);


// ===================================
// KIRIM NOTIFIKASI KE ADMIN & PETUGAS
// ===================================

try {

    // Ambil seluruh akun admin dan petugas
    $query_penerima = mysqli_query(
        $conn,
        "SELECT id
         FROM users
         WHERE role IN ('admin', 'petugas')"
    );

    $nama_warga = $_SESSION['nama'] ?? 'Warga';

    $judul_notifikasi = 'Setoran Sampah Baru';

    $pesan_notifikasi =
        $nama_warga .
        ' mengajukan setoran sampah baru dengan nomor #' .
        $setoran_id .
        '. Kategori: ' . $kategori['nama_kategori'] .
        ', berat: ' . number_format($berat, 2, ',', '.') .
        ' Kg. Silakan periksa dan lakukan verifikasi.';

    while ($penerima = mysqli_fetch_assoc($query_penerima)) {

       $berhasil_notifikasi = kirimNotifikasi(
        $conn,
        (int) $penerima['id'],
        $judul_notifikasi,
        $pesan_notifikasi,
        'informasi',
        'detail_setoran.php?id=' . $setoran_id
    );

        if (!$berhasil_notifikasi) {
            error_log(
                "Notifikasi setoran #$setoran_id gagal dikirim ke user ID " .
                $penerima['id']
            );
        }
    }

} catch (Throwable $e) {

    // Kegagalan notifikasi tidak membatalkan pengajuan setoran
    error_log(
        "Pengiriman notifikasi setoran #$setoran_id gagal: " .
        $e->getMessage()
    );
}


// Perbarui token keamanan

                            $_SESSION['csrf_token'] = bin2hex(
                                random_bytes(32)
                            );

                            // Kembali ke halaman dengan notifikasi berhasil
                            header(
                                "Location: ajukan_setoran.php?status=berhasil"
                            );

                            exit;
                        }
                    }

                } catch (mysqli_sql_exception $e) {

                    error_log(
                        "Pengajuan setoran gagal: " . $e->getMessage()
                    );

                    $error = "Pengajuan setoran gagal disimpan. Silakan coba kembali.";
                }
            }
        }
    }
}

// Ambil kategori sampah yang aktif
$kategori_query = mysqli_query(
    $conn,
    "SELECT id, nama_kategori, harga_per_kg
     FROM kategori_sampah
     WHERE status = 'aktif'
     ORDER BY nama_kategori ASC"
);
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ajukan Setoran - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
:root {
    --navy: #071F33;
    --navy-light: #0B2D46;
    --cyan: #18C3D8;
    --teal: #159BAE;
    --bg: #F2F7FA;
    --text: #183B55;
    --muted: #8295A2;
    --border: #E5EDF2;
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

/* =================================
   SIDEBAR WARGA - TEMA ADMIN
================================= */

.warga-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 250px;
    background: linear-gradient(180deg, #071F33 0%, #0B2D46 100%);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    z-index: 1000;
    border-right: 1px solid rgba(255,255,255,.08);
}

.sidebar-brand {
    padding: 24px 22px 20px;
    border-bottom: 1px solid rgba(255,255,255,.08);
    background: rgba(255,255,255,.02);
}

.sidebar-brand-top {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar-logo {
    width: 48px;
    height: 48px;
    min-width: 48px;
    border-radius: 50%;
    background: #fff;
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
    color: #fff;
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
    transition: .18s ease;
}

.sidebar-link:hover {
    background: rgba(24,195,216,.10);
    color: #fff;
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

.sidebar-logout {
    margin-top: auto;
    color: #FFE0E0;
}

/* =================================
   KONTEN UTAMA
================================= */

.warga-main {
    margin-left: 250px;
    min-height: 100vh;
    padding: 32px;
}

.page-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.warga-heading {
    margin: 0;
}

.warga-heading h1 {
    margin: 0 0 7px;
    color: #0B354D;
    font-size: 24px;
    font-weight: 800;
    line-height: 1.3;
}

.warga-heading p {
    margin: 0;
    color: #8295A2;
    font-size: 12px;
    line-height: 1.6;
}

/* TOMBOL KEMBALI */

.btn-kembali {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-shrink: 0;
    padding: 11px 17px;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: white !important;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(21,155,174,.16);
    transition: .25s ease;
}

.btn-kembali:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    color: white !important;
    transform: translateY(-2px);
}

/* =================================
   NOTIFIKASI
================================= */

.form-alert-success,
.form-alert-error {
    padding: 14px 17px;
    margin-bottom: 18px;
    border-radius: 11px;
    font-size: 12px;
    line-height: 1.6;
}

.form-alert-success {
    color: #16804F;
    background: #E7F8EF;
    border: 1px solid #BCE9CE;
}

.form-alert-error {
    color: #B93838;
    background: #FFF0F0;
    border: 1px solid #F4CACA;
}

/* =================================
   KARTU FORMULIR
================================= */

.petugas-form-container {
    width: 100%;
    max-width: 1100px;
    margin: 0 auto;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 17px;
    box-shadow: 0 8px 28px rgba(11,53,77,.055);
    overflow: hidden;
}

.petugas-form-heading {
    padding: 26px 30px;
    border-bottom: 1px solid #EDF2F5;
    background: linear-gradient(110deg, #FFFFFF, #F3FAFC);
}

.petugas-form-heading h2 {
    margin: 0;
    color: #0B354D;
    font-size: 19px;
    font-weight: 800;
}

.petugas-form-heading p {
    margin: 7px 0 0;
    color: #8295A2;
    font-size: 13px;
}

.petugas-form-container form {
    padding: 30px;
}

.petugas-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 25px;
}

.petugas-form-group {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.petugas-form-group.full-width {
    grid-column: 1 / -1;
}

.petugas-form-group label {
    color: #24465B;
    font-size: 14px;
    font-weight: 700;
}

.petugas-form-group label span {
    color: #E05252;
}

.petugas-form-group input,
.petugas-form-group select,
.petugas-form-group textarea {
    width: 100%;
    padding: 14px 16px;
    background: #FAFCFD;
    color: #183B55;
    border: 1px solid #DCE7ED;
    border-radius: 10px;
    outline: none;
    font-family: inherit;
    font-size: 14px;
    transition: .2s ease;
}

.petugas-form-group input,
.petugas-form-group select {
    min-height: 49px;
}

.petugas-form-group textarea {
    min-height: 125px;
    resize: vertical;
    line-height: 1.7;
}

.petugas-form-group input:focus,
.petugas-form-group select:focus,
.petugas-form-group textarea:focus {
    border-color: #159BAE;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(21,155,174,.10);
}

.petugas-form-group small {
    color: #8295A2;
    font-size: 12px;
}

/* RESPONSIVE HP */
@media (max-width: 768px) {
    .petugas-form-container {
        max-width: 100%;
    }

    .petugas-form-heading {
        padding: 20px;
    }

    .petugas-form-container form {
        padding: 20px;
    }

    .petugas-form-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .petugas-form-group.full-width {
        grid-column: auto;
    }
}


/* =================================
   ESTIMASI HARGA
================================= */

.estimasi-setoran {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 12px 20px;
    margin-top: 25px;
    padding: 19px 21px;
    background: linear-gradient(110deg, #EFF9FA, #F5FAFC);
    border: 1px solid #D5EBEF;
    border-radius: 12px;
}

.estimasi-setoran span {
    color: #66818F;
    font-size: 11px;
    font-weight: 600;
}

.estimasi-setoran strong {
    color: #0B354D;
    font-size: 14px;
    font-weight: 800;
    text-align: right;
}

#total_tampil {
    color: #159BAE;
    font-size: 18px;
}

/* =================================
   TOMBOL FORMULIR
================================= */

.petugas-form-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 11px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #EDF2F5;
}

.btn-batal-petugas,
.btn-simpan-petugas {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-height: 42px;
    padding: 11px 20px;
    border-radius: 9px;
    font-family: sans-serif;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: .2s ease;
}

.btn-batal-petugas {
    background: #F0F4F6;
    color: #607785;
    border: 1px solid #E1E9ED;
}

.btn-batal-petugas:hover {
    background: #E5EDF1;
}

.btn-simpan-petugas {
    border: none;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #fff;
    box-shadow: 0 4px 12px rgba(21,155,174,.16);
}

.btn-simpan-petugas:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    transform: translateY(-2px);
}

/* =================================
   RESPONSIVE TABLET
================================= */

@media (max-width: 900px) {
    .warga-main {
        padding: 25px 20px;
    }

    .petugas-form-container form {
        padding: 22px;
    }
}

/* =================================
   RESPONSIVE HP
================================= */

@media (max-width: 700px) {
    .warga-sidebar {
        position: relative;
        width: 100%;
        min-height: auto;
        height: auto;
    }

    .sidebar-brand {
        padding: 15px 18px;
    }

    .sidebar-nav {
        padding: 8px 12px 12px;
    }

    .sidebar-section-title {
        display: none;
    }

    .sidebar-nav {
        flex-direction: row;
        overflow-x: auto;
        gap: 7px;
    }

    .sidebar-link {
        width: auto;
        flex: 0 0 auto;
        padding: 10px 12px;
        margin: 0;
        background: rgba(255,255,255,.06);
        font-size: 11px;
    }

    .sidebar-link.active {
        box-shadow: inset 0 -3px 0 #18C3D8;
    }

    .warga-main {
        margin-left: 0;
        padding: 22px 15px 30px;
    }

    .page-header-row {
        align-items: flex-start;
        flex-direction: column;
        gap: 14px;
    }

    .warga-heading h1 {
        font-size: 21px;
    }

    .warga-heading p {
        font-size: 11px;
    }

    .petugas-form-heading {
        padding: 19px;
    }

    .petugas-form-container form {
        padding: 19px;
    }

    .petugas-form-grid {
        grid-template-columns: 1fr;
        gap: 17px;
    }

    .petugas-form-group.full-width {
        grid-column: auto;
    }

    .estimasi-setoran {
        padding: 16px;
    }

    .petugas-form-actions {
        flex-direction: column-reverse;
    }

    .btn-batal-petugas,
    .btn-simpan-petugas {
        width: 100%;
    }
}

@media (max-width: 360px) {
    .warga-main {
        padding-left: 11px;
        padding-right: 11px;
    }

    .warga-heading h1 {
        font-size: 19px;
    }
}

/* =================================
   RAPATKAN LAYOUT FORMULIR SETORAN
================================= */

/* Header formulir */
.petugas-form-heading {
    padding: 18px 22px !important;
}

/* Area isi formulir */
.petugas-form-container form {
    padding: 20px 22px !important;
    box-sizing: border-box;
}

/* Jarak antar kolom */
.petugas-form-grid {
    gap: 16px 20px !important;
}

/* Jarak label dengan input */
.petugas-form-group {
    gap: 7px !important;
}

/* Ukuran input */
.petugas-form-group input,
.petugas-form-group select {
    min-height: 44px !important;
    padding: 11px 13px !important;
}

/* Area catatan */
.petugas-form-group textarea {
    min-height: 95px !important;
    padding: 12px 13px !important;
}

/* Cegah input melebar keluar kartu */
.petugas-form-container,
.petugas-form-container * {
    box-sizing: border-box;
}

/* Jarak antar kolom di HP */
@media (max-width: 768px) {
    .petugas-form-heading {
        padding: 17px 18px !important;
    }

    .petugas-form-container form {
        padding: 18px !important;
    }

    .petugas-form-grid {
        gap: 15px !important;
    }
}
/* =================================
   HAMBURGER MENU RESPONSIVE
================================= */

.mobile-menu-button {
    display: none;
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    border: none;
    border-radius: 11px;
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
    background: linear-gradient(135deg, #0B354D, #17B8C8);
}

.sidebar-overlay {
    display: none;
}

@media (max-width: 700px) {

    .warga-sidebar {
        position: fixed !important;
        top: 0;
        left: 0;
        bottom: 0;
        width: 260px !important;
        height: 100vh !important;
        min-height: 100vh !important;
        display: flex;
        flex-direction: column;
        padding: 0 !important;
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

    .warga-sidebar.open {
        transform: translateX(0);
    }

    .sidebar-brand {
        padding: 24px 18px 20px !important;
    }

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
        margin: 3px 0;
        background: transparent;
    }

    .sidebar-link.active {
        box-shadow: inset 3px 0 0 #18C3D8;
    }

    .warga-main {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 22px 15px 30px !important;
    }

    .mobile-menu-button {
        display: flex;
    }

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

    .page-header-row {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .warga-heading {
        flex: 1;
        min-width: 0;
    }

    .warga-heading h1 {
        font-size: 19px;
    }

    .btn-kembali {
        padding: 9px 11px;
        font-size: 10px;
    }

}

@media (max-width: 420px) {

    .page-header-row {
        align-items: flex-start;
    }

    .warga-heading h1 {
        font-size: 17px;
    }

    .btn-kembali {
        font-size: 9px;
        padding: 9px;
    }

}

/* =====================================
   HEADING DAN TOMBOL KEMBALI
   HALAMAN AJUKAN SETORAN
===================================== */

.page-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    width: 100%;
    margin-bottom: 25px;
}

.warga-heading {
    flex: 1;
    min-width: 0;
}

.warga-title-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.warga-heading h1 {
    margin: 0;
}

/* Tombol kembali */
.page-header-row .btn-kembali {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex: 0 0 auto;
    width: auto;
    min-width: 220px;
    padding: 11px 18px;
    border-radius: 9px;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(21, 155, 174, 0.16);
    transition: all .25s ease;
}

.page-header-row .btn-kembali:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    color: #ffffff;
    transform: translateY(-2px);
}

/* Laptop */
@media (min-width: 701px) {
    .page-header-row {
        flex-direction: row;
        align-items: center;
    }

    .page-header-row .btn-kembali {
        align-self: center;
    }
}

/* HP dan tablet */
@media (max-width: 700px) {
    .page-header-row {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        margin-bottom: 20px;
    }

    .warga-heading {
        width: 100%;
    }

    .warga-title-line {
        width: 100%;
        align-items: center;
    }

    .page-header-row .btn-kembali {
        align-self: flex-end;
        min-width: auto;
        width: auto;
        padding: 9px 13px;
        font-size: 11px;
    }
}

/* HP kecil */
@media (max-width: 420px) {
    .page-header-row .btn-kembali {
        padding: 9px 12px;
        font-size: 10px;
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

        <a href="ajukan_setoran.php" class="sidebar-link active">
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

<main class="warga-main">

<!-- HEADER HALAMAN -->


<div class="page-header-row">

    <div class="warga-heading">

        <div class="warga-title-line">
            <h1>Ajukan Setoran Sampah</h1>

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
            Masukkan informasi sampah yang ingin kamu setorkan.
        </p>

    </div>

    <a href="dashboard.php" class="btn-kembali">
        <span>←</span>
        Kembali ke Dashboard
    </a>

</div>



    <!-- NOTIFIKASI BERHASIL -->

    <?php if (isset($_GET['status']) && $_GET['status'] == 'berhasil') : ?>

        <div class="form-alert-success">
            ✓ Pengajuan setoran berhasil dikirim.
            Silakan tunggu verifikasi admin.
        </div>

    <?php endif; ?>


    <!-- NOTIFIKASI ERROR -->

    <?php if ($error != "") : ?>

        <div class="form-alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <!-- FORMULIR SETORAN -->

    <div class="petugas-form-container">

        <div class="petugas-form-heading">
            <h2>Formulir Setoran</h2>
            <p>Lengkapi informasi berikut dengan benar.</p>
        </div>

        <?php if (mysqli_num_rows($kategori_query) > 0) : ?>

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
                >

                <div class="petugas-form-grid">

                    <!-- KATEGORI -->

                    <div class="petugas-form-group full-width">

                        <label>
                            Kategori Sampah <span>*</span>
                        </label>

                        <select
                            name="kategori_id"
                            id="kategori_id"
                            required
                        >

                            <option value="">
                                Pilih kategori sampah
                            </option>

                            <?php while ($kategori = mysqli_fetch_assoc($kategori_query)) : ?>

                                <option
                                    value="<?php echo (int)$kategori['id']; ?>"
                                    data-harga="<?php echo htmlspecialchars((string)$kategori['harga_per_kg']); ?>"
                                    <?php echo (string)$kategori_id === (string)$kategori['id'] ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($kategori['nama_kategori']); ?>
                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>


                    <!-- BERAT -->

                    <div class="petugas-form-group">

                        <label>
                            Berat Sampah (Kg) <span>*</span>
                        </label>

                        <input
                            type="number"
                            name="berat_kg"
                            id="berat_kg"
                            placeholder="Contoh: 5"
                            min="0.01"
                            max="99999999.99"
                            step="0.01"
                            value="<?php echo htmlspecialchars($berat_kg); ?>"
                            required
                        >

                    </div>


                    <!-- TANGGAL -->

                    <div class="petugas-form-group">

                        <label>
                            Tanggal Setoran <span>*</span>
                        </label>

                        <input
                            type="text"
                            name="tanggal_setor"
                            id="tanggal_setor"
                            placeholder="DD/MM/YYYY"
                            value="<?php echo htmlspecialchars($tanggal_input); ?>"
                            pattern="\d{2}/\d{2}/\d{4}"
                            title="Gunakan format tanggal DD/MM/YYYY"
                            maxlength="10"
                            inputmode="numeric"
                            required
                        >

                        <small>
                            Format tanggal: Hari/Bulan/Tahun
                        </small>

                    </div>


                    <!-- CATATAN -->

                    <div class="petugas-form-group full-width">

                        <label>Catatan Tambahan</label>

                        <textarea
                            name="catatan"
                            rows="4"
                            maxlength="5000"
                            placeholder="Contoh: Sampah sudah dipilah dan dibersihkan."
                        ><?php echo htmlspecialchars($catatan); ?></textarea>

                    </div>

                </div>


                <!-- ESTIMASI HARGA -->

                <div class="estimasi-setoran">

                    <span>Harga per Kg</span>

                    <strong id="harga_tampil">Rp0</strong>

                    <span>Estimasi Total Setoran</span>

                    <strong id="total_tampil">Rp0</strong>

                </div>


                <!-- TOMBOL FORM -->

                <div class="petugas-form-actions">

                    <a href="dashboard.php" class="btn-batal-petugas">
                        Batal
                    </a>

                    <button type="submit" class="btn-simpan-petugas">
                        ✓ Kirim Pengajuan
                    </button>

                </div>

            </form>

        <?php else : ?>

            <div class="form-alert-error" style="margin:20px;">
                Belum tersedia kategori sampah aktif.
                Silakan hubungi admin.
            </div>

        <?php endif; ?>

    </div>
<script>

<!-- JAVASCRIPT ESTIMASI HARGA -->
<script>
const kategoriSelect = document.getElementById('kategori_id');
const beratInput = document.getElementById('berat_kg');
const hargaTampil = document.getElementById('harga_tampil');
const totalTampil = document.getElementById('total_tampil');

function hitungEstimasi() {

    // Pastikan elemen formulir tersedia
    if (!kategoriSelect || !beratInput || !hargaTampil || !totalTampil) {
        return;
    }

    const pilihan = kategoriSelect.options[
        kategoriSelect.selectedIndex
    ];

    const harga = parseFloat(pilihan.dataset.harga) || 0;
    const berat = parseFloat(beratInput.value) || 0;
    const total = harga * berat;

    const formatRupiah = angka => {
        return 'Rp' + angka.toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });
    };

    hargaTampil.textContent = formatRupiah(harga);
    totalTampil.textContent = formatRupiah(total);
}

if (kategoriSelect && beratInput) {
    kategoriSelect.addEventListener('change', hitungEstimasi);
    beratInput.addEventListener('input', hitungEstimasi);
    hitungEstimasi();
}


// ===================================
// HAMBURGER MENU WARGA
// ===================================

const menuButton = document.getElementById('mobileMenuButton');
const sidebar = document.getElementById('wargaSidebar');
const overlay = document.getElementById('sidebarOverlay');

if (menuButton && sidebar && overlay) {

    function bukaTutupMenu() {

        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');

        const isOpen = sidebar.classList.contains('open');

        menuButton.innerHTML = isOpen ? '✕' : '☰';

        menuButton.setAttribute(
            'aria-expanded',
            isOpen ? 'true' : 'false'
        );
    }

    menuButton.addEventListener('click', bukaTutupMenu);

    overlay.addEventListener('click', bukaTutupMenu);

    sidebar.querySelectorAll('.sidebar-link').forEach(link => {

        link.addEventListener('click', () => {

            sidebar.classList.remove('open');
            overlay.classList.remove('show');

            menuButton.innerHTML = '☰';

            menuButton.setAttribute('aria-expanded', 'false');

        });

    });
}


</script>
</script>
</main>

</body>
</html>