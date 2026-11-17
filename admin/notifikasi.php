<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$user_id = (int) $_SESSION['user_id'];

// ========================================
// CSRF TOKEN
// ========================================

if (empty($_SESSION['csrf_notifikasi'])) {
    $_SESSION['csrf_notifikasi'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_notifikasi'];


// ========================================
// TANDAI SATU NOTIFIKASI SUDAH DIBACA
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['baca_id'])) {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($csrf_token, $_POST['csrf_token'])
    ) {
        die("Token keamanan tidak valid.");
    }

    $notifikasi_id = (int) $_POST['baca_id'];

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE notifikasi
         SET dibaca = 1
         WHERE id = ? AND user_id = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $notifikasi_id,
        $user_id
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("Location: notifikasi.php");
    exit;
}


// ========================================
// TANDAI SEMUA NOTIFIKASI SUDAH DIBACA
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['baca_semua'])) {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($csrf_token, $_POST['csrf_token'])
    ) {
        die("Token keamanan tidak valid.");
    }

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE notifikasi
         SET dibaca = 1
         WHERE user_id = ? AND dibaca = 0"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("Location: notifikasi.php");
    exit;
}


// ========================================
// AMBIL NOTIFIKASI
// ========================================

$query_notifikasi = mysqli_query(
    $conn,
    "SELECT id, judul, pesan, tipe, link, dibaca, tanggal_dibuat
     FROM notifikasi
     WHERE user_id = $user_id
     ORDER BY tanggal_dibuat DESC"
);


// ========================================
// HITUNG NOTIFIKASI
// ========================================

$query_total = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN dibaca = 0 THEN 1 ELSE 0 END) AS belum_dibaca
     FROM notifikasi
     WHERE user_id = $user_id"
);

$stat_notifikasi = mysqli_fetch_assoc($query_total);

$total_notifikasi = (int) ($stat_notifikasi['total'] ?? 0);
$belum_dibaca = (int) ($stat_notifikasi['belum_dibaca'] ?? 0);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Notifikasi Admin - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        .notifikasi-page {
            max-width: 1000px;
            margin: 0 auto;
        }

        .notifikasi-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .notifikasi-header-left h1 {
            color: #174c43;
            margin-bottom: 6px;
        }

        .notifikasi-header-left p {
            color: #71817c;
            font-size: 14px;
        }

        .notifikasi-summary {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
        }

        .notifikasi-summary-card {
            background: #ffffff;
            border: 1px solid #e5eeeb;
            border-radius: 14px;
            padding: 16px 20px;
            min-width: 150px;
        }

        .notifikasi-summary-card span {
            display: block;
            color: #71817c;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .notifikasi-summary-card strong {
            color: #0f766e;
            font-size: 22px;
        }

        .btn-baca-semua {
            border: none;
            background: #0f766e;
            color: #ffffff;
            padding: 10px 16px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-baca-semua:hover {
            background: #145c52;
        }

        .notifikasi-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .notifikasi-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            background: #ffffff;
            border: 1px solid #e5eeeb;
            border-radius: 14px;
            padding: 18px;
            transition: 0.2s ease;
        }

        .notifikasi-item:hover {
            border-color: #c9ddd8;
            transform: translateY(-1px);
        }

        .notifikasi-item.belum-dibaca {
            background: #f1faf8;
            border-left: 4px solid #0f766e;
        }

        .notifikasi-icon {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            border-radius: 12px;
            background: #e8f5f1;
            color: #0f766e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .notifikasi-content {
            flex: 1;
            min-width: 0;
        }

        .notifikasi-content h3 {
            color: #174c43;
            font-size: 15px;
            margin-bottom: 5px;
        }

        .notifikasi-content p {
            color: #5f716c;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .notifikasi-waktu {
            color: #91a09c;
            font-size: 11px;
        }

        .notifikasi-action {
            flex-shrink: 0;
        }

        .btn-baca {
            border: 1px solid #cfe2dd;
            background: #ffffff;
            color: #0f766e;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-baca:hover {
            background: #e8f5f1;
        }

        .notifikasi-kosong {
            text-align: center;
            background: #ffffff;
            border: 1px dashed #d6e4e0;
            border-radius: 14px;
            padding: 55px 20px;
        }

        .notifikasi-kosong-icon {
            font-size: 42px;
            margin-bottom: 12px;
        }

        .notifikasi-kosong h3 {
            color: #174c43;
            margin-bottom: 6px;
        }

        .notifikasi-kosong p {
            color: #81908b;
            font-size: 13px;
        }

        .btn-kembali {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #0f766e;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .btn-kembali:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {

    /* Header */
    .notifikasi-header {
        align-items: flex-start;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 18px;
    }

    .notifikasi-header-left h1 {
        font-size: 21px;
        line-height: 1.2;
        margin-bottom: 5px;
    }

    .notifikasi-header-left p {
        font-size: 10px;
        line-height: 1.45;
    }

    /* Tombol tandai semua */
    .btn-baca-semua {
        width: 100%;
        padding: 9px 12px;
        font-size: 10px;
    }

    /* Summary */
    .notifikasi-summary {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
        margin-bottom: 18px;
    }

    .notifikasi-summary-card {
        min-width: 0;
        padding: 11px 12px;
        border-radius: 12px;
    }

    .notifikasi-summary-card span {
        font-size: 9px;
        margin-bottom: 4px;
    }

    .notifikasi-summary-card strong {
        font-size: 19px;
    }

    /* Item notifikasi */
    .notifikasi-list {
        gap: 9px;
    }

    .notifikasi-item {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        padding: 12px;
        border-radius: 12px;
    }

    .notifikasi-item.belum-dibaca {
        border-left-width: 3px;
    }

    /* Icon */
    .notifikasi-icon {
        width: 32px;
        height: 32px;
        min-width: 32px;
        border-radius: 9px;
        font-size: 13px;
    }

    /* Isi */
    .notifikasi-content h3 {
        font-size: 11px;
        line-height: 1.35;
        margin-bottom: 4px;
    }

    .notifikasi-content p {
        font-size: 9px;
        line-height: 1.45;
        margin-bottom: 6px;
    }

    .notifikasi-waktu {
        font-size: 8px;
    }

    /* Link periksa */
    .btn-notifikasi-link {
        display: inline-block;
        margin-top: 7px;
        font-size: 9px;
    }

    /* Tombol tandai dibaca */
    .notifikasi-action {
        align-self: flex-start;
    }

    .btn-baca {
        font-size: 8px;
        padding: 6px 8px;
        border-radius: 7px;
        white-space: nowrap;
    }

    /* Kondisi kosong */
    .notifikasi-kosong {
        padding: 40px 15px;
        border-radius: 12px;
    }

    .notifikasi-kosong-icon {
        font-size: 34px;
        margin-bottom: 9px;
    }

    .notifikasi-kosong h3 {
        font-size: 15px;
    }

    .notifikasi-kosong p {
        font-size: 10px;
        line-height: 1.45;
    }

    /* Tombol kembali */
    .btn-kembali {
        font-size: 10px;
        margin-bottom: 15px;
    }

}

/* =========================================
   INDIKATOR NOTIFIKASI BARU
========================================= */

.notifikasi-baru {
    flex-shrink: 0;
    background: #dff5ee;
    color: #0f766e;
    font-size: 9px;
    font-weight: 700;
    padding: 4px 7px;
    border-radius: 20px;
    line-height: 1;
    margin-top: 2px;
}

.notifikasi-item.belum-dibaca .notifikasi-content h3 {
    font-weight: 700;
}

.notifikasi-item.belum-dibaca .notifikasi-content p {
    color: #526963;
}

/* Mobile */
@media (max-width: 600px) {

    .notifikasi-baru {
        font-size: 7px;
        padding: 3px 5px;
        margin-top: 1px;
    }

}

/* =========================================
   IKON BERDASARKAN TIPE NOTIFIKASI
========================================= */

.notifikasi-icon.notifikasi-sukses {
    background: #e3f6ed;
    color: #0f766e;
}

.notifikasi-icon.notifikasi-peringatan {
    background: #fff1df;
    color: #b86b00;
}

.notifikasi-icon.notifikasi-informasi {
    background: #e8f5f1;
    color: #0f766e;
}

/* =========================================
   NOTIFIKASI BELUM DIBACA - EMPHASIS
========================================= */

.notifikasi-item.belum-dibaca {
    position: relative;
    background: #f1faf8;
    border-color: #cfe5df;
    border-left: 4px solid #0f766e;
}

.notifikasi-item.belum-dibaca::after {
    content: "";
    position: absolute;
    top: 15px;
    right: 15px;
    width: 6px;
    height: 6px;
    background: #0f766e;
    border-radius: 50%;
}

/* Jangan tampilkan titik tambahan terlalu dekat di mobile */
@media (max-width: 600px) {

    .notifikasi-item.belum-dibaca::after {
        top: 12px;
        right: 12px;
        width: 5px;
        height: 5px;
    }

}

/* =========================================
   TOMBOL TANDAI DIBACA
========================================= */

.btn-baca {
    border: 1px solid #d6e6e2;
    background: #ffffff;
    color: #0f766e;
    padding: 7px 10px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-baca:hover {
    background: #e8f5f1;
    border-color: #b9d8d1;
}

.notifikasi-item.belum-dibaca .btn-baca {
    background: #eef9f6;
    border-color: #c9e3dc;
}

.notifikasi-item.belum-dibaca .btn-baca:hover {
    background: #dff2ec;
}

/* Mobile */
@media (max-width: 600px) {

    .btn-baca {
        font-size: 8px;
        padding: 6px 8px;
        border-radius: 7px;
    }

}

/* =========================================
   ACTION LINK NOTIFIKASI
========================================= */

.btn-notifikasi-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    width: fit-content;
    margin-top: 9px;
    padding: 7px 10px;
    background: #eef8f5;
    border: 1px solid #d4e9e3;
    border-radius: 7px;
    color: #0f766e;
    font-size: 10px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-notifikasi-link:hover {
    background: #dff2ec;
    border-color: #bcdcd3;
    text-decoration: none;
    transform: translateY(-1px);
}

/* Notifikasi belum dibaca */
.notifikasi-item.belum-dibaca .btn-notifikasi-link {
    background: #e3f5ef;
    border-color: #c5e2da;
}

/* Mobile */
@media (max-width: 600px) {

    .btn-notifikasi-link {
        margin-top: 7px;
        padding: 6px 8px;
        font-size: 8px;
        border-radius: 6px;
    }

}

/* =========================================
   EMPTY STATE NOTIFIKASI
========================================= */

.notifikasi-kosong {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 220px;
    padding: 35px 20px;
    background: #ffffff;
    border: 1px solid #e5eeeb;
    border-radius: 16px;
    box-sizing: border-box;
}

.notifikasi-kosong-icon {
    width: 54px;
    height: 54px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    background: #e8f5f1;
    border-radius: 50%;
    font-size: 24px;
}

.notifikasi-kosong h3 {
    color: #174c43;
    font-size: 16px;
    margin-bottom: 6px;
}

.notifikasi-kosong p {
    max-width: 360px;
    color: #81908b;
    font-size: 11px;
    line-height: 1.5;
    text-align: center;
}

/* Mobile */
@media (max-width: 600px) {

    .notifikasi-kosong {
        min-height: 190px;
        padding: 28px 15px;
        border-radius: 13px;
    }

    .notifikasi-kosong-icon {
        width: 46px;
        height: 46px;
        font-size: 20px;
        margin-bottom: 10px;
    }

    .notifikasi-kosong h3 {
        font-size: 14px;
    }

    .notifikasi-kosong p {
        font-size: 9px;
        max-width: 280px;
    }

}


/* =========================================
   REDESIGN HALAMAN NOTIFIKASI
   TEMA BIRU - TEAL DATA WARGA
========================================= */

.admin-content {
    background: #f4f9f8;
}

/* Bagian tombol kembali */
.notifikasi-top-action {
    display: flex;
    justify-content: flex-end;
    width: 100%;
    margin-bottom: 22px;
}

.btn-kembali {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 11px 18px;
    margin: 0;
    border-radius: 10px;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(21, 155, 174, 0.18);
    transition: all 0.25s ease;
}

.btn-kembali:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 7px 16px rgba(21, 155, 174, 0.25);
}

/* Header notifikasi */
/* Header notifikasi tanpa kolom */
.notifikasi-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 0;
    margin-bottom: 25px;
    background: transparent;
    border: none;
    border-radius: 0;
    box-shadow: none;
}

.notifikasi-header-left h1 {
    color: #0B354D;
    font-size: 25px;
    font-weight: 800;
    margin-bottom: 6px;
}

.notifikasi-header-left p {
    color: #718995;
    font-size: 12px;
}

.notifikasi-header-left h1 {
    color: #0B354D;
    font-size: 24px;
    font-weight: 800;
    letter-spacing: -0.4px;
}

.notifikasi-header-left p {
    color: #718995;
    font-size: 12px;
}

/* Tombol tandai semua */
.btn-baca-semua {
    background: linear-gradient(135deg, #0B354D, #159BAE);
    border-radius: 9px;
    padding: 11px 17px;
    box-shadow: 0 3px 9px rgba(21, 155, 174, 0.15);
    transition: 0.25s ease;
}

.btn-baca-semua:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    transform: translateY(-2px);
}

/* Kartu ringkasan */
.notifikasi-summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 190px));
    gap: 15px;
    margin-bottom: 22px;
}

.notifikasi-summary-card {
    position: relative;
    overflow: hidden;
    min-width: 0;
    padding: 20px;
    border: 1px solid #e2edf0;
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 3px 12px rgba(11, 53, 77, 0.04);
    transition: 0.25s ease;
}

.notifikasi-summary-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, #159BAE, #17B8C8);
}

.notifikasi-summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 7px 17px rgba(11, 53, 77, 0.08);
}

.notifikasi-summary-card span {
    color: #718995;
    font-size: 11px;
    font-weight: 600;
}

.notifikasi-summary-card strong {
    color: #0B4650;
    font-size: 25px;
    font-weight: 800;
}

/* Daftar notifikasi */
.notifikasi-list {
    gap: 14px;
}

.notifikasi-item {
    position: relative;
    padding: 20px;
    gap: 15px;
    border: 1px solid #e2edf0;
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 2px 9px rgba(11, 53, 77, 0.035);
    transition: all 0.25s ease;
}

.notifikasi-item:hover {
    border-color: #b9dce3;
    box-shadow: 0 6px 16px rgba(11, 53, 77, 0.07);
    transform: translateY(-2px);
}

.notifikasi-item.belum-dibaca {
    background: #f0f9fa;
    border-color: #c9e7ec;
    border-left: 4px solid #159BAE;
}

.notifikasi-item.belum-dibaca::after {
    background: #17B8C8;
}

/* Ikon notifikasi */
.notifikasi-icon {
    width: 45px;
    height: 45px;
    border-radius: 13px;
    font-size: 19px;
    background: #EAF3F6;
    color: #159BAE;
}

.notifikasi-icon.notifikasi-sukses {
    background: #E2F5F1;
    color: #087C72;
}

.notifikasi-icon.notifikasi-peringatan {
    background: #FFF4E5;
    color: #C17A12;
}

.notifikasi-icon.notifikasi-informasi {
    background: #EAF3F6;
    color: #159BAE;
}

/* Isi notifikasi */
.notifikasi-content h3 {
    color: #0B354D;
    font-size: 14px;
    font-weight: 700;
}

.notifikasi-content p {
    color: #526671;
    font-size: 12px;
    line-height: 1.7;
}

.notifikasi-waktu {
    display: inline-block;
    margin-top: 5px;
    color: #8799A1;
    font-size: 10px;
}

/* Label baru */
.notifikasi-baru {
    background: #DDF4F5;
    color: #087C8A;
    font-size: 9px;
    padding: 5px 9px;
}

/* Tombol tandai dibaca */
.btn-baca {
    background: #EAF3F6;
    border: 1px solid #D5E8ED;
    color: #117F91;
    padding: 9px 12px;
    font-size: 10px;
    border-radius: 8px;
}

.btn-baca:hover {
    background: #DCEFF3;
    color: #0B354D;
}

/* Link periksa */
.btn-notifikasi-link {
    background: #EAF3F6;
    border: 1px solid #D5E8ED;
    color: #117F91;
}

.btn-notifikasi-link:hover {
    background: #DCEFF3;
    border-color: #B9DCE3;
    color: #0B354D;
}

/* Kondisi kosong */
.notifikasi-kosong {
    border: 1px dashed #cbdfe5;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 3px 12px rgba(11, 53, 77, 0.035);
}

.notifikasi-kosong-icon {
    background: #EAF3F6;
}

.notifikasi-kosong h3 {
    color: #0B354D;
}

.notifikasi-kosong p {
    color: #718995;
}

/* Responsif tablet dan HP */
@media (max-width: 800px) {
    .notifikasi-header {
        padding: 18px;
    }

    .notifikasi-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .notifikasi-top-action {
        margin-bottom: 15px;
    }

    .btn-kembali {
        padding: 9px 13px;
        font-size: 10px;
    }

    .notifikasi-header {
        padding: 15px;
    }

    .notifikasi-header-left h1 {
        font-size: 20px;
    }

    .notifikasi-summary-card {
        padding: 13px;
    }

    .notifikasi-item {
        padding: 12px;
    }
}
/* Mengatur posisi halaman notifikasi agar sedikit lebih naik */
.notifikasi-page {
    position: relative;
    top: -18px;
}

/* Penyesuaian tampilan HP */
@media (max-width: 600px) {
    .notifikasi-page {
        top: -10px;
    }
}
/* HEADER NOTIFIKASI */
.notifikasi-header {
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: flex-start;
    width: 100%;
    gap: 20px;
    padding: 0;
    margin: 0 0 28px 0;
    background: transparent;
    border: none;
    border-radius: 0;
    box-shadow: none;
}

/* JUDUL NOTIFIKASI */
.notifikasi-header-left {
    flex: 1;
    min-width: 0;
}

.notifikasi-header-left h1 {
    font-family: 'Poppins', sans-serif;
    font-size: 28px;
    font-weight: 800;
    line-height: 1.3;
    color: #0B354D;
    margin: 0 0 7px 0;
}

.notifikasi-header-left p {
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    font-weight: 400;
    color: #718995;
    margin: 0;
}

/* TOMBOL KEMBALI */
.notifikasi-top-action {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-shrink: 0;
    margin-top: 2px;
}

.btn-kembali {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 18px;
    margin: 0;
    border: none;
    border-radius: 9px;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #fff;
    font-family: 'Poppins', sans-serif;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
    box-shadow: 0 3px 9px rgba(21, 155, 174, 0.15);
    transition: 0.25s ease;
}

.btn-kembali:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    color: #fff;
    transform: translateY(-2px);
}

/* RESPONSIVE */
@media (max-width: 600px) {
    .notifikasi-header {
        align-items: flex-start;
        gap: 10px;
    }

    .notifikasi-header-left h1 {
        font-size: 22px;
    }

    .notifikasi-header-left p {
        font-size: 11px;
    }

    .btn-kembali {
        padding: 9px 11px;
        font-size: 10px;
    }
}
/* HEADER NOTIFIKASI */
.notifikasi-header {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) auto !important;
    align-items: start !important;
    gap: 25px !important;
    width: 100% !important;
    margin-bottom: 28px !important;
    padding: 0 !important;
}

/* AREA JUDUL */
.notifikasi-header-left {
    display: block !important;
    width: 100% !important;
    max-width: none !important;
    min-width: 0 !important;
}

.notifikasi-header-left h1 {
    font-size: 36px !important;
    font-weight: 800 !important;
    color: #0B354D !important;
    margin: 0 0 8px 0 !important;
}

.notifikasi-header-left p {
    display: block !important;
    width: 100% !important;
    max-width: 500px !important;
    min-width: 0 !important;
    font-size: 14px !important;
    line-height: 1.6 !important;
    color: #718995 !important;
    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: normal !important;
    margin: 0 !important;
}

/* TOMBOL KEMBALI */
.notifikasi-top-action {
    display: flex !important;
    flex-shrink: 0 !important;
    justify-content: flex-end !important;
    align-items: flex-start !important;
}

@media (max-width: 600px) {
    .notifikasi-header {
        grid-template-columns: minmax(0, 1fr) auto !important;
        gap: 10px !important;
    }

    .notifikasi-header-left h1 {
        font-size: 23px !important;
    }

    .notifikasi-header-left p {
        font-size: 11px !important;
    }
}
.notifikasi-header {
    transform: translateY(12px);
}

    </style>

</head>

<body>

<div class="admin-dashboard">
<!-- SIDEBAR -->
<aside class="admin-sidebar">

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


    <nav class="sidebar-nav">

        <div class="sidebar-section-title">
            Utama
        </div>

        <a href="dashboard.php" class="sidebar-link active">
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

        <!-- AKTIF -->
        <a href="petugas.php" class="sidebar-link">
            <span class="sidebar-icon">👤</span>
            <span>Data Petugas</span>
        </a>

        <a href="kelola_admin.php" class="sidebar-link">
            <span class="sidebar-icon">🛡️</span>
            <span>Kelola Admin</span>
        </a>

        <a href="kategori.php" class="sidebar-link">
            <span class="sidebar-icon">♻️</span>
            <span>Kategori Sampah</span>
        </a>

        <a href="setoran.php" class="sidebar-link">
            <span class="sidebar-icon">📦</span>
            <span>Data Setoran</span>
        </a>

        <a href="tabungan.php" class="sidebar-link">
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
   
    <!-- CONTENT -->

    <main class="admin-content">

        <div class="notifikasi-page">


     <!-- HEADER -->
<div class="notifikasi-header">

    <div class="notifikasi-header-left">
        <h1>Notifikasi</h1>
        <p>Informasi terbaru terkait aktivitas Bank Sampah Digital.</p>
    </div>

    <div class="notifikasi-top-action">
        <a href="dashboard.php" class="btn-kembali">
            ← Kembali ke Dashboard
        </a>
    </div>

</div>


                <?php if ($belum_dibaca > 0): ?>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo htmlspecialchars($csrf_token); ?>"
                        >

                        <button
                            type="submit"
                            name="baca_semua"
                            class="btn-baca-semua"
                        >
                            Tandai Semua Dibaca
                        </button>

                    </form>

                <?php endif; ?>

            </div>


            <!-- SUMMARY -->

            <div class="notifikasi-summary">

                <div class="notifikasi-summary-card">

                    <span>Total Notifikasi</span>

                    <strong>
                        <?php echo number_format($total_notifikasi, 0, ',', '.'); ?>
                    </strong>

                </div>


                <div class="notifikasi-summary-card">

                    <span>Belum Dibaca</span>

                    <strong>
                        <?php echo number_format($belum_dibaca, 0, ',', '.'); ?>
                    </strong>

                </div>

            </div>


            <!-- LIST NOTIFIKASI -->

            <div class="notifikasi-list">

                <?php if (mysqli_num_rows($query_notifikasi) > 0): ?>

                    <?php while ($row = mysqli_fetch_assoc($query_notifikasi)): ?>

                        <div class="notifikasi-item <?php echo $row['dibaca'] == 0 ? 'belum-dibaca' : ''; ?>">

                            <div class="notifikasi-icon <?php echo 'notifikasi-' . htmlspecialchars($row['tipe']); ?>">
                                <?php
                                if ($row['tipe'] === 'sukses') {
                                    echo '✓';
                                } elseif ($row['tipe'] === 'peringatan') {
                                    echo '⚠';
                                } else {
                                    echo '🔔';
                                }
                                ?>
                            </div>

                            <?php if ($row['dibaca'] == 0): ?>
                                <span class="notifikasi-baru">Baru</span>
                            <?php endif; ?>


                            <div class="notifikasi-content">

                                <h3>
                                    <?php echo htmlspecialchars($row['judul']); ?>
                                </h3>

                                <p>
                                    <?php echo nl2br(htmlspecialchars($row['pesan'])); ?>
                                </p>

                                <?php if (!empty($row['link'])): ?>

                                    <a
                                        href="buka_notifikasi.php?id=<?php echo (int) $row['id']; ?>&link=<?php echo urlencode($row['link']); ?>"
                                        class="btn-notifikasi-link"
                                    >
                                        <?php if ($row['judul'] === 'Pengajuan Penarikan Baru'): ?>
                                            Periksa Penarikan →
                                        <?php else: ?>
                                            Periksa Setoran →
                                        <?php endif; ?>
                                    </a>

                                <?php endif; ?>

                                <span class="notifikasi-waktu">
                                    <?php
                                    echo date(
                                        'd/m/Y H:i',
                                        strtotime($row['tanggal_dibuat'])
                                    );
                                    ?>
                                </span>

                            </div>


                            <div class="notifikasi-action">

                                <?php if ($row['dibaca'] == 0): ?>

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo htmlspecialchars($csrf_token); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="baca_id"
                                            value="<?php echo (int)$row['id']; ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn-baca"
                                        >
                                            Tandai Dibaca
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="notifikasi-kosong">

                        <div class="notifikasi-kosong-icon">
                            🔔
                        </div>

                        <h3>Belum Ada Notifikasi</h3>

                        <p>
                            Belum ada aktivitas baru yang perlu diperhatikan.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

</body>

</html>