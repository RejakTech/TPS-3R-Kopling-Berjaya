
<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
require_once __DIR__ . '/../config/pengumuman_gambar.php';

/* ==============================
   CSRF TOKEN
============================== */

if (empty($_SESSION['csrf_pengumuman'])) {
    $_SESSION['csrf_pengumuman'] = bin2hex(random_bytes(32));
}

/* ==============================
   FUNGSI ESCAPE
============================== */

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* ==============================
   DATA FORM
============================== */

$judul = '';
$kategori = 'Informasi';
$isi = '';
$status = 'draft';
$error = '';

/* ==============================
   PROSES SIMPAN
============================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_pengumuman'], $csrf)) {
        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } else {

        $judul = trim($_POST['judul'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $isi = trim($_POST['isi'] ?? '');
        $status = trim($_POST['status'] ?? 'draft');

        $kategori_valid = ['Informasi', 'Edukasi', 'Kegiatan'];
        $status_valid = ['draft', 'terbit'];

        if ($judul === '' || mb_strlen($judul) > 200) {
            $error = "Judul wajib diisi dan maksimal 200 karakter.";
        } elseif (!in_array($kategori, $kategori_valid, true)) {
            $error = "Kategori pengumuman tidak valid.";
        } elseif ($isi === '') {
            $error = "Isi pengumuman wajib diisi.";
        } elseif (!in_array($status, $status_valid, true)) {
            $error = "Status pengumuman tidak valid.";
        }

        $gambar_headline = [];
        $gambar_tambahan = [];

        if ($error === '') {
            $gambar_headline = uploadPengumumanImages(
                $_FILES['gambar'] ?? null,
                false,
                $error
            );
        }

        if ($error === '') {
            $gambar_tambahan = uploadPengumumanImages(
                $_FILES['gambar_tambahan'] ?? null,
                true,
                $error
            );
        }

        /* ==============================
           SIMPAN KE DATABASE
        ============================== */

        if ($error === '') {

            $penulis_id = (int) $_SESSION['user_id'];

            $tanggal_terbit = ($status === 'terbit')
                ? date('Y-m-d H:i:s')
                : null;
            $gambar = $gambar_headline[0] ?? null;

            $sql = "
                INSERT INTO pengumuman
                (
                    judul,
                    kategori,
                    isi,
                    gambar,
                    penulis_id,
                    status,
                    tanggal_terbit
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssiss",
                    $judul,
                    $kategori,
                    $isi,
                    $gambar,
                    $penulis_id,
                    $status,
                    $tanggal_terbit
                );

                mysqli_begin_transaction($conn);

                if (!mysqli_stmt_execute($stmt)) {
                    $error = "Gagal menyimpan pengumuman. Silakan coba lagi.";
                } else {
                    $pengumuman_id = mysqli_insert_id($conn);
                    $gambar_stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO pengumuman_gambar (pengumuman_id, nama_file, urutan) VALUES (?, ?, ?)"
                    );

                    if (!$gambar_stmt) {
                        $error = "Gagal menyiapkan penyimpanan gambar tambahan.";
                    } else {
                        foreach ($gambar_tambahan as $urutan => $nama_file) {
                            $urutan++;
                            mysqli_stmt_bind_param(
                                $gambar_stmt,
                                "isi",
                                $pengumuman_id,
                                $nama_file,
                                $urutan
                            );

                            if (!mysqli_stmt_execute($gambar_stmt)) {
                                $error = "Gagal menyimpan gambar tambahan.";
                                break;
                            }
                        }

                        mysqli_stmt_close($gambar_stmt);
                    }
                }

                mysqli_stmt_close($stmt);

                if ($error === '' && mysqli_commit($conn)) {
                    header("Location: pengumuman.php?pesan=berhasil");
                    exit;
                }

                if ($error === '') {
                    $error = "Gagal menyelesaikan penyimpanan pengumuman.";
                }

                mysqli_rollback($conn);

            } else {
                $error = "Gagal menyiapkan penyimpanan data.";
            }

            if ($error !== '') {
                hapusFileGambarPengumuman(
                    array_merge($gambar_headline, $gambar_tambahan)
                );
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Pengumuman - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>

        /* =========================
           AREA KONTEN
        ========================= */

        .admin-main {
            margin-left: var(--sidebar-width, 250px);
            min-height: 100vh;
            background: #f4f9f8;
            box-sizing: border-box;
        }

        .admin-content {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 42px 50px;
            box-sizing: border-box;
        }

        .pengumuman-page {
            width: 100%;
            max-width: none;
            margin: 0;
        }

        /* =========================
           HEADER
        ========================= */

        .pengumuman-header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: start;
            gap: 25px;
            margin-bottom: 28px;
        }

        .pengumuman-header h1 {
            margin: 0 0 8px;
            color: #0B4650;
            font-size: 30px;
            font-weight: 700;
            line-height: 1.3;
        }

        .pengumuman-header p {
            margin: 0;
            color: #718995;
            font-size: 14px;
            line-height: 1.6;
        }

        .btn-kembali {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 18px;
            border-radius: 9px;
            background: linear-gradient(135deg, #0B354D, #159BAE);
            color: #FFFFFF;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            box-shadow: 0 3px 9px rgba(21, 155, 174, .15);
            transition: .25s ease;
        }

        .btn-kembali:hover {
            background: linear-gradient(135deg, #082B40, #117F91);
            color: #FFFFFF;
            transform: translateY(-2px);
        }

        /* =========================
           FORM CARD
        ========================= */

        .pengumuman-card {
            width: 100%;
            background: #FFFFFF;
            border: 1px solid #E1EAED;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(11, 45, 70, .045);
            box-sizing: border-box;
        }

        .form-card-heading {
            margin-bottom: 25px;
            padding-bottom: 18px;
            border-bottom: 1px solid #EAF0F3;
        }

        .form-card-heading h2 {
            margin: 0 0 6px;
            color: #0B354D;
            font-size: 18px;
            font-weight: 700;
        }

        .form-card-heading p {
            margin: 0;
            color: #82949E;
            font-size: 12px;
        }

        /* =========================
           FORM INPUT
        ========================= */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            color: #315A69;
            font-size: 13px;
            font-weight: 600;
        }

        .required {
            color: #D64545;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 1px solid #DCE7EC;
            border-radius: 9px;
            outline: none;
            background: #F7FAFC;
            color: #344C59;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            transition: .2s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #17B8C8;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(23, 184, 200, .12);
        }

        .form-group textarea {
            min-height: 180px;
            resize: vertical;
            line-height: 1.7;
        }

        .form-hint {
            color: #8A9BA4;
            font-size: 11px;
        }

        /* =========================
           UPLOAD GAMBAR
        ========================= */

        .upload-box {
            border: 1.5px dashed #B9D6DF;
            border-radius: 12px;
            padding: 25px;
            background: #F6FBFD;
            text-align: center;
        }

        .upload-box i {
            color: #159BAE;
            font-size: 30px;
            margin-bottom: 10px;
        }

        .upload-box p {
            color: #526C78;
            font-size: 12px;
            margin-bottom: 12px;
        }

        .upload-box input {
            background: #FFFFFF;
        }

        .preview-gambar {
            display: none;
            width: 180px;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
            margin: 15px auto 0;
            border: 1px solid #DCE7EC;
        }

        .preview-gambar-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }

        .preview-gambar-list .preview-gambar {
            display: block;
            margin: 0;
        }

        /* =========================
           ALERT
        ========================= */

        .form-alert {
            background: #FDECEC;
            color: #B42318;
            border: 1px solid #F5CACA;
            border-radius: 9px;
            padding: 13px 16px;
            font-size: 12px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        /* =========================
           BUTTON
        ========================= */

        .form-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #EAF0F3;
        }

        .btn-batal,
        .btn-simpan {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 43px;
            padding: 0 20px;
            border-radius: 9px;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: .2s ease;
        }

        .btn-batal {
            background: #EDF3F5;
            color: #526C78;
            border: 1px solid #DCE7EC;
        }

        .btn-batal:hover {
            background: #E2EBEF;
        }

        .btn-simpan {
            background: linear-gradient(110deg, #0B354D, #17B8C8);
            color: #FFFFFF;
            border: none;
            box-shadow: 0 3px 9px rgba(11, 53, 77, .12);
        }

        .btn-simpan:hover {
            background: linear-gradient(110deg, #082B40, #109EAF);
            transform: translateY(-2px);
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 900px) {
            .admin-content {
                padding: 30px;
            }
        }

        @media (max-width: 600px) {
            .admin-content {
                padding: 24px 18px;
            }

            .pengumuman-header {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .pengumuman-header h1 {
                font-size: 25px;
            }

            .pengumuman-card {
                padding: 20px 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .form-group.full {
                grid-column: auto;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn-batal,
            .btn-simpan {
                width: 100%;
            }
        }

/* =================================
   MOBILE HAMBURGER SIDEBAR
================================= */

.mobile-menu-button {
    display: none !important;
}

.sidebar-overlay {
    display: none;
}

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
    .sidebar-overlay.show {
        display: block !important;
        position: fixed !important;
        inset: 0 !important;
        background: rgba(4, 25, 39, .55) !important;
        z-index: 1000 !important;
    }

    /* AREA KONTEN */
    .admin-main {
        margin-left: 0 !important;
        width: 100% !important;
        min-height: 100vh;
    }

    .admin-content {
        width: 100% !important;
        padding: 22px 16px !important;
        box-sizing: border-box !important;
    }

    .pengumuman-page {
        width: 100% !important;
    }

    /* HEADER */
    .pengumuman-header {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 16px !important;
    }

    .pengumuman-title-line {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
    }

    .pengumuman-header h1 {
        font-size: 23px !important;
        margin: 0 !important;
    }

    .pengumuman-header p {
        margin-top: 9px !important;
        font-size: 12px !important;
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
        color: #FFFFFF !important;
        font-size: 23px !important;
        cursor: pointer !important;
    }

    .btn-kembali {
        width: 100%;
        box-sizing: border-box;
    }

    /* FORM CARD */
    .pengumuman-card {
        width: 100% !important;
        padding: 20px 16px !important;
        box-sizing: border-box !important;
    }

    .form-grid {
        grid-template-columns: 1fr !important;
        gap: 18px !important;
    }

    .form-group.full {
        grid-column: auto !important;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        max-width: 100%;
    }

    .upload-box {
        padding: 18px 12px !important;
    }

    .upload-box input {
        max-width: 100%;
        font-size: 11px;
    }

    .preview-gambar {
        max-width: 100%;
    }

    /* BUTTON FORM */
    .form-actions {
        flex-direction: column-reverse !important;
        align-items: stretch !important;
    }

    .btn-batal,
    .btn-simpan {
        width: 100% !important;
        box-sizing: border-box;
    }
}

/* HP KECIL */
@media screen and (max-width: 380px) {

    .admin-content {
        padding: 18px 12px !important;
    }

    .pengumuman-header h1 {
        font-size: 20px !important;
    }

    .pengumuman-card {
        padding: 17px 12px !important;
    }

    .form-card-heading h2 {
        font-size: 16px !important;
    }
}

/* DESKTOP */
@media screen and (min-width: 801px) {

    .mobile-menu-button {
        display: none !important;
    }

    .sidebar-overlay {
        display: none !important;
    }
}

    </style>
</head>

<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- =========================
     SIDEBAR ADMIN
     SAMA DENGAN DATA WARGA
========================= -->

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

        <div class="sidebar-section-title">Utama</div>

        <a href="dashboard.php" class="sidebar-link">
            <span class="sidebar-icon">🏠</span>
            <span>Dashboard</span>
        </a>

        <div class="sidebar-section-title">Pengelolaan</div>

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

        <a href="pengumuman.php" class="sidebar-link active">
            <span class="sidebar-icon">📢</span>
            <span>Kelola Pengumuman</span>
        </a>

        <a href="log_aktivitas.php" class="sidebar-link">
            <span class="sidebar-icon">📋</span>
            <span>Log Aktivitas</span>
        </a>

        <div class="sidebar-section-title">Akun</div>

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


<!-- =========================
     AREA KONTEN
========================= -->

<main class="admin-main">

    <div class="admin-content">

        <div class="pengumuman-page">

            <!-- HEADER -->

<div class="pengumuman-header">

    <div class="pengumuman-header-left">

        <div class="pengumuman-title-line">
            <h1>Tambah Pengumuman</h1>

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
            Buat informasi, edukasi, dan kegiatan terbaru
            TPS 3R Kopling Berjaya.
        </p>

    </div>

    <div>
        <a href="dashboard.php" class="btn-kembali">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Dashboard
        </a>
    </div>

</div>


            <!-- FORM CARD -->

            <div class="pengumuman-card">

                <div class="form-card-heading">
                    <h2>
                        <i class="fa-solid fa-bullhorn"></i>
                        Form Pengumuman
                    </h2>

                    <p>
                        Lengkapi informasi pengumuman yang ingin diterbitkan.
                    </p>
                </div>


                <?php if ($error !== ''): ?>

                    <div class="form-alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <?= e($error) ?>
                    </div>

                <?php endif; ?>


                <form method="POST"
                      action=""
                      enctype="multipart/form-data">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_pengumuman']) ?>"
                    >

                    <div class="form-grid">

                        <!-- JUDUL -->

                        <div class="form-group full">

                            <label for="judul">
                                Judul Pengumuman
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                id="judul"
                                name="judul"
                                maxlength="200"
                                placeholder="Masukkan judul pengumuman"
                                value="<?= e($judul) ?>"
                                required
                            >

                        </div>


                        <!-- KATEGORI -->

                        <div class="form-group">

                            <label for="kategori">
                                Kategori
                                <span class="required">*</span>
                            </label>

                            <select id="kategori" name="kategori" required>

                                <option value="Informasi"
                                    <?= $kategori === 'Informasi' ? 'selected' : '' ?>>
                                    Informasi
                                </option>

                                <option value="Edukasi"
                                    <?= $kategori === 'Edukasi' ? 'selected' : '' ?>>
                                    Edukasi
                                </option>

                                <option value="Kegiatan"
                                    <?= $kategori === 'Kegiatan' ? 'selected' : '' ?>>
                                    Kegiatan
                                </option>

                            </select>

                        </div>


                        <!-- STATUS -->

                        <div class="form-group">

                            <label for="status">
                                Status Pengumuman
                                <span class="required">*</span>
                            </label>

                            <select id="status" name="status" required>

                                <option value="draft"
                                    <?= $status === 'draft' ? 'selected' : '' ?>>
                                    Draft
                                </option>

                                <option value="terbit"
                                    <?= $status === 'terbit' ? 'selected' : '' ?>>
                                    Terbitkan
                                </option>

                            </select>

                            <span class="form-hint">
                                Draft tidak akan ditampilkan di halaman utama.
                            </span>

                        </div>


                        <!-- ISI -->

                        <div class="form-group full">

                            <label for="isi">
                                Isi Pengumuman
                                <span class="required">*</span>
                            </label>

                            <textarea
                                id="isi"
                                name="isi"
                                placeholder="Tuliskan isi pengumuman di sini..."
                                required
                            ><?= e($isi) ?></textarea>

                        </div>


                        <!-- GAMBAR HEADLINE -->

                        <div class="form-group full">

                            <label for="gambar">
                                Gambar Headline
                            </label>

                            <div class="upload-box">

                                <i class="fa-solid fa-cloud-arrow-up"></i>

                                <p>
                                    Gambar utama yang ditampilkan pada kartu berita dan bagian atas detail.
                                </p>

                                <input
                                    type="file"
                                    id="gambar"
                                    name="gambar"
                                    accept=".jpg,.jpeg,.png,.webp"
                                >

                                <span class="form-hint">
                                    Format JPG, PNG, WEBP. Maksimal 3 MB per gambar.
                                </span>

                                <img
                                    id="previewGambar"
                                    class="preview-gambar"
                                    alt="Preview gambar pengumuman"
                                >

                            </div>

                        </div>

                        <!-- GAMBAR ISI BERITA -->

                        <div class="form-group full">

                            <label for="gambarTambahan">
                                Gambar Isi Berita
                            </label>

                            <div class="upload-box">
                                <i class="fa-solid fa-images"></i>
                                <p>Pilih satu atau beberapa gambar untuk galeri setelah teks isi berita.</p>
                                <input
                                    type="file"
                                    id="gambarTambahan"
                                    name="gambar_tambahan[]"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    multiple
                                >
                                <span class="form-hint">
                                    Format JPG, PNG, WEBP. Maksimal 3 MB per gambar.
                                </span>
                                <div id="previewGambarTambahan" class="preview-gambar-list"></div>
                            </div>

                        </div>

                    </div>


                    <!-- BUTTON -->

                    <div class="form-actions">

                        <a href="pengumuman.php" class="btn-batal">
                            <i class="fa-solid fa-xmark"></i>
                            Batal
                        </a>

                        <button type="submit" class="btn-simpan">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Simpan Pengumuman
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</main>


<script>
/* PREVIEW GAMBAR */

document.getElementById('gambar').addEventListener('change', function(event) {

    const file = event.target.files[0];
    const preview = document.getElementById('previewGambar');

    if (!file) {
        preview.style.display = 'none';
        preview.removeAttribute('src');
        return;
    }

    const allowed = ['image/jpeg', 'image/png', 'image/webp'];

    if (!allowed.includes(file.type)) {
        alert('Format gambar harus JPG, PNG, atau WEBP.');
        event.target.value = '';
        preview.style.display = 'none';
        return;
    }

    if (file.size > 3 * 1024 * 1024) {
        alert('Ukuran gambar maksimal 3 MB.');
        event.target.value = '';
        preview.style.display = 'none';
        return;
    }

    preview.src = URL.createObjectURL(file);
    preview.style.display = 'block';
});

document.getElementById('gambarTambahan').addEventListener('change', function(event) {

    const previewList = document.getElementById('previewGambarTambahan');
    previewList.replaceChildren();

    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    const files = Array.from(event.target.files);

    for (const file of files) {
        if (!allowed.includes(file.type) || file.size > 3 * 1024 * 1024) {
            alert('Setiap gambar harus berformat JPG, PNG, atau WEBP dan maksimal 3 MB.');
            event.target.value = '';
            previewList.replaceChildren();
            return;
        }

        const image = document.createElement('img');
        image.src = URL.createObjectURL(file);
        image.alt = 'Preview gambar isi berita';
        image.className = 'preview-gambar';
        previewList.appendChild(image);
    }
});
</script>

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
