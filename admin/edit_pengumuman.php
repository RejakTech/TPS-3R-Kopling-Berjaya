
<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
require_once __DIR__ . '/../config/pengumuman_gambar.php';

/* =========================
   CSRF TOKEN
========================= */

if (empty($_SESSION['csrf_pengumuman'])) {
    $_SESSION['csrf_pengumuman'] = bin2hex(random_bytes(32));
}

/* =========================
   FUNGSI KEAMANAN
========================= */

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* =========================
   AMBIL ID PENGUMUMAN
========================= */

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: pengumuman.php?pesan=id_tidak_valid");
    exit;
}

/* =========================
   AMBIL DATA PENGUMUMAN
========================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM pengumuman WHERE id = ? LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$pengumuman = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$pengumuman) {
    header("Location: pengumuman.php?pesan=data_tidak_ditemukan");
    exit;
}

$gambar_tambahan = [];
$gambar_stmt = mysqli_prepare(
    $conn,
    "SELECT id, nama_file, urutan FROM pengumuman_gambar WHERE pengumuman_id = ? ORDER BY urutan, id"
);

if (!$gambar_stmt) {
    die("Gagal menyiapkan data gambar tambahan.");
}

mysqli_stmt_bind_param($gambar_stmt, "i", $id);
mysqli_stmt_execute($gambar_stmt);
$gambar_result = mysqli_stmt_get_result($gambar_stmt);

while ($row = mysqli_fetch_assoc($gambar_result)) {
    $gambar_tambahan[] = $row;
}

mysqli_stmt_close($gambar_stmt);

/* =========================
   PROSES UPDATE
========================= */

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_pengumuman'], $csrf)) {
        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } else {

        $judul = trim($_POST['judul'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $isi = trim($_POST['isi'] ?? '');
        $status = trim($_POST['status'] ?? '');

        $kategori_valid = ['Informasi', 'Edukasi', 'Kegiatan'];
        $status_valid = ['draft', 'terbit'];

        if ($judul === '' || $kategori === '' || $isi === '') {
            $error = "Semua kolom wajib diisi.";
        } elseif (mb_strlen($judul) > 200) {
            $error = "Judul maksimal 200 karakter.";
        } elseif (!in_array($kategori, $kategori_valid, true)) {
            $error = "Kategori pengumuman tidak valid.";
        } elseif (!in_array($status, $status_valid, true)) {
            $error = "Status pengumuman tidak valid.";
        } else {
            $gambar_lama = $pengumuman['gambar'] ?? '';
            $gambar_headline_baru = uploadPengumumanImages(
                $_FILES['gambar'] ?? null,
                false,
                $error
            );
            $gambar_tambahan_baru = [];

            if ($error === '') {
                $gambar_tambahan_baru = uploadPengumumanImages(
                    $_FILES['gambar_tambahan'] ?? null,
                    true,
                    $error
                );
            }

            $hapus_headline = isset($_POST['hapus_gambar_headline']);
            $gambar_baru = $gambar_headline_baru
                ? $gambar_headline_baru[0]
                : ($hapus_headline ? null : $gambar_lama);

            $id_gambar_dihapus = [];
            $gambar_dihapus = [];
            $id_dipilih = $_POST['hapus_gambar_tambahan'] ?? [];

            if (!is_array($id_dipilih)) {
                $id_dipilih = [];
            }

            foreach ($gambar_tambahan as $gambar_lama_tambahan) {
                if (in_array((string) $gambar_lama_tambahan['id'], $id_dipilih, true)) {
                    $id_gambar_dihapus[] = (int) $gambar_lama_tambahan['id'];
                    $gambar_dihapus[] = $gambar_lama_tambahan['nama_file'];
                }
            }

            if ($error === '') {
                $sql = "
                    UPDATE pengumuman
                    SET judul = ?, kategori = ?, isi = ?, gambar = ?, status = ?
                    WHERE id = ?
                ";
                $update = mysqli_prepare($conn, $sql);

                if (!$update) {
                    $error = "Gagal menyiapkan proses update.";
                } else {
                    mysqli_begin_transaction($conn);
                    mysqli_stmt_bind_param(
                        $update,
                        "sssssi",
                        $judul,
                        $kategori,
                        $isi,
                        $gambar_baru,
                        $status,
                        $id
                    );

                    if (!mysqli_stmt_execute($update)) {
                        $error = "Pengumuman gagal diperbarui.";
                    }
                    mysqli_stmt_close($update);

                    if ($error === '' && $id_gambar_dihapus) {
                        $deleteImage = mysqli_prepare(
                            $conn,
                            "DELETE FROM pengumuman_gambar WHERE id = ? AND pengumuman_id = ?"
                        );

                        if (!$deleteImage) {
                            $error = "Gagal menyiapkan penghapusan gambar.";
                        } else {
                            foreach ($id_gambar_dihapus as $gambar_id) {
                                mysqli_stmt_bind_param($deleteImage, "ii", $gambar_id, $id);
                                if (!mysqli_stmt_execute($deleteImage)) {
                                    $error = "Gambar tambahan gagal diperbarui.";
                                    break;
                                }
                            }
                            mysqli_stmt_close($deleteImage);
                        }
                    }

                    if ($error === '' && $gambar_tambahan_baru) {
                        $nextOrder = 0;
                        foreach ($gambar_tambahan as $gambar_lama_tambahan) {
                            if (!in_array((int) $gambar_lama_tambahan['id'], $id_gambar_dihapus, true)) {
                                $nextOrder = max($nextOrder, (int) $gambar_lama_tambahan['urutan']);
                            }
                        }

                        $insertImage = mysqli_prepare(
                            $conn,
                            "INSERT INTO pengumuman_gambar (pengumuman_id, nama_file, urutan) VALUES (?, ?, ?)"
                        );

                        if (!$insertImage) {
                            $error = "Gagal menyiapkan penyimpanan gambar tambahan.";
                        } else {
                            foreach ($gambar_tambahan_baru as $nama_file) {
                                $nextOrder++;
                                mysqli_stmt_bind_param(
                                    $insertImage,
                                    "isi",
                                    $id,
                                    $nama_file,
                                    $nextOrder
                                );
                                if (!mysqli_stmt_execute($insertImage)) {
                                    $error = "Gagal menyimpan gambar tambahan.";
                                    break;
                                }
                            }
                            mysqli_stmt_close($insertImage);
                        }
                    }

                    if ($error === '' && mysqli_commit($conn)) {
                        $hapusSetelahSimpan = $gambar_dihapus;
                        if ($gambar_headline_baru && $gambar_lama !== '') {
                            $hapusSetelahSimpan[] = $gambar_lama;
                        } elseif ($hapus_headline && $gambar_lama !== '') {
                            $hapusSetelahSimpan[] = $gambar_lama;
                        }
                        hapusFileGambarPengumuman($hapusSetelahSimpan);

                        header("Location: pengumuman.php?pesan=berhasil_edit");
                        exit;
                    }

                    if ($error === '') {
                        $error = "Gagal menyelesaikan perubahan pengumuman.";
                    }

                    mysqli_rollback($conn);
                }
            }

            if ($error !== '') {
                hapusFileGambarPengumuman(
                    array_merge($gambar_headline_baru, $gambar_tambahan_baru)
                );
            }
        }
    }
}

/* =========================
   DATA FORM
========================= */

$judul_tampil = $_POST['judul'] ?? $pengumuman['judul'];
$kategori_tampil = $_POST['kategori'] ?? $pengumuman['kategori'];
$isi_tampil = $_POST['isi'] ?? $pengumuman['isi'];
$status_tampil = $_POST['status'] ?? $pengumuman['status'];

$gambar_tampil = $pengumuman['gambar'] ?? '';

?>

<!DOCTYPE html>
<html lang="id">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Pengumuman - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
          rel="stylesheet">

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

.edit-page {
    width: 100%;
    max-width: none;
    margin: 0;
}

/* =========================
   HEADER
========================= */

.edit-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 28px;
    flex-wrap: wrap;
}

.edit-heading h1 {
    color: #0B4650;
    font-size: 30px;
    font-weight: 700;
    margin: 0 0 8px;
}

.edit-heading p {
    color: #718995;
    font-size: 14px;
    margin: 0;
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
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
    box-shadow: 0 3px 9px rgba(21,155,174,.15);
    transition: .25s ease;
}

.btn-kembali:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    color: #fff;
    transform: translateY(-2px);
}

/* =========================
   FORM CARD
========================= */

.edit-card {
    width: 100%;
    background: #fff;
    border: 1px solid #E1EAED;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 15px rgba(11,45,70,.045);
    box-sizing: border-box;
}

.edit-card-title {
    color: #0B354D;
    font-size: 17px;
    font-weight: 700;
    margin-bottom: 23px;
    padding-bottom: 15px;
    border-bottom: 1px solid #EAF0F3;
}

/* =========================
   FORM INPUT
========================= */

.form-group {
    margin-bottom: 21px;
}

.form-group label {
    display: block;
    color: #526C78;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 8px;
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
    color: #344F5B;
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    transition: .2s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #17AEBE;
    box-shadow: 0 0 0 3px rgba(23,184,200,.12);
    background: #fff;
}

.form-group textarea {
    min-height: 170px;
    resize: vertical;
    line-height: 1.7;
}

.form-hint {
    color: #8499A3;
    font-size: 11px;
    margin-top: 7px;
}

/* =========================
   PREVIEW GAMBAR
========================= */

.preview-wrapper {
    margin-top: 12px;
}

.preview-wrapper img,
.preview-image-list img {
    width: 190px;
    height: 135px;
    object-fit: cover;
    border-radius: 11px;
    border: 1px solid #DCE7EC;
    background: #EAF5FC;
}

.preview-image-list {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.preview-image-list label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    color: #718995;
    font-size: 11px;
}

.preview-image-list input[type="checkbox"],
.preview-wrapper input[type="checkbox"] {
    width: auto;
    margin-right: 5px;
}

.preview-label {
    display: block;
    font-size: 11px;
    color: #718995;
    margin-bottom: 8px;
}

/* =========================
   ALERT
========================= */

.alert-error {
    background: #FCEBEC;
    color: #B42318;
    border: 1px solid #F4D0D2;
    border-radius: 9px;
    padding: 13px 15px;
    font-size: 12px;
    margin-bottom: 20px;
}

/* =========================
   TOMBOL SIMPAN
========================= */

.form-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 28px;
    padding-top: 22px;
    border-top: 1px solid #EAF0F3;
    flex-wrap: wrap;
}

.btn-simpan {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 23px;
    border: 0;
    border-radius: 9px;
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    color: #fff;
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(11,53,77,.13);
    transition: .2s ease;
}

.btn-simpan:hover {
    background: linear-gradient(110deg, #082B40, #109EAF);
    transform: translateY(-2px);
}

.btn-batal {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 22px;
    border-radius: 9px;
    background: #EDF3F5;
    color: #415D69;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: .2s ease;
}

.btn-batal:hover {
    background: #DCE9ED;
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

    .edit-heading h1 {
        font-size: 25px;
    }

    .edit-card {
        padding: 20px 16px;
    }

    .edit-header {
        gap: 14px;
    }

    .btn-kembali {
        padding: 10px 13px;
        font-size: 11px;
    }

    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .btn-simpan,
    .btn-batal {
        width: 100%;
        box-sizing: border-box;
    }

    .preview-wrapper img {
        width: 100%;
        max-width: 250px;
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

<!-- =========================
     SIDEBAR
========================= -->

<aside class="admin-sidebar">

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

        <div class="edit-page">

            <div class="edit-header">

                <div class="edit-heading">
                    <h1>
                        <i class="fa-solid fa-pen-to-square"></i>
                        Edit Pengumuman
                    </h1>

                    <p>
                        Perbarui informasi dan pengumuman TPS 3R Kopling Berjaya.
                    </p>
                </div>

                <a href="pengumuman.php" class="btn-kembali">
                    <i class="fa-solid fa-arrow-left"></i>
                    Kembali ke Pengumuman
                </a>

            </div>

            <div class="edit-card">

                <div class="edit-card-title">
                    <i class="fa-solid fa-file-pen"></i>
                    Form Edit Pengumuman
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert-error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_pengumuman']) ?>"
                    >

                    <div class="form-group">

                        <label for="judul">Judul Pengumuman</label>

                        <input
                            type="text"
                            id="judul"
                            name="judul"
                            value="<?= e($judul_tampil) ?>"
                            placeholder="Masukkan judul pengumuman"
                            maxlength="200"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="kategori">Kategori Pengumuman</label>

                        <select id="kategori" name="kategori" required>

                            <option value="">-- Pilih Kategori --</option>

                            <option value="Informasi"
                                <?= $kategori_tampil === 'Informasi' ? 'selected' : '' ?>>
                                Informasi
                            </option>

                            <option value="Edukasi"
                                <?= $kategori_tampil === 'Edukasi' ? 'selected' : '' ?>>
                                Edukasi
                            </option>

                            <option value="Kegiatan"
                                <?= $kategori_tampil === 'Kegiatan' ? 'selected' : '' ?>>
                                Kegiatan
                            </option>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="isi">Isi Pengumuman</label>

                        <textarea
                            id="isi"
                            name="isi"
                            placeholder="Tuliskan isi pengumuman..."
                            required
                        ><?= e($isi_tampil) ?></textarea>

                    </div>

                    <div class="form-group">

                        <label for="gambar">Gambar Headline</label>

                        <input
                            type="file"
                            id="gambar"
                            name="gambar"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        >

                        <p class="form-hint">
                            Format JPG, PNG, atau WEBP. Maksimal 3 MB. Kosongkan jika tidak ingin mengganti.
                        </p>

                        <?php if ($gambar_tampil !== ''): ?>

                            <div class="preview-wrapper">

                                <span class="preview-label">
                                    Gambar yang digunakan saat ini:
                                </span>

                                <img
                                    src="../uploads/pengumuman/<?= e(basename($gambar_tampil)) ?>"
                                    alt="Gambar pengumuman saat ini"
                                    onerror="this.style.display='none';"
                                >

                                <label class="form-hint">
                                    <input type="checkbox" name="hapus_gambar_headline" value="1">
                                    Hapus gambar headline saat ini
                                </label>

                            </div>

                        <?php endif; ?>

                        <div class="preview-wrapper" id="previewBaru" style="display:none;">

                            <span class="preview-label">
                                Preview gambar baru:
                            </span>

                            <img id="gambarPreview" src="" alt="Preview gambar baru">

                        </div>

                    </div>

                    <div class="form-group">

                        <label for="gambarTambahan">Gambar Isi Berita</label>

                        <input
                            type="file"
                            id="gambarTambahan"
                            name="gambar_tambahan[]"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            multiple
                        >

                        <p class="form-hint">
                            Pilih satu atau beberapa gambar. Maksimal 3 MB per gambar; gambar akan ditambahkan ke galeri setelah teks isi berita.
                        </p>

                        <?php if ($gambar_tambahan): ?>
                            <div class="preview-wrapper">
                                <span class="preview-label">Gambar isi berita saat ini (centang untuk menghapus):</span>
                                <div class="preview-image-list">
                                    <?php foreach ($gambar_tambahan as $gambar_item): ?>
                                        <label>
                                            <img
                                                src="../uploads/pengumuman/<?= e(basename($gambar_item['nama_file'])) ?>"
                                                alt="Gambar isi berita"
                                                onerror="this.style.display='none';"
                                            >
                                            <span>
                                                <input
                                                    type="checkbox"
                                                    name="hapus_gambar_tambahan[]"
                                                    value="<?= (int) $gambar_item['id'] ?>"
                                                >
                                                Hapus
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="preview-wrapper" id="previewGambarTambahan" style="display:none;">
                            <span class="preview-label">Preview gambar baru:</span>
                            <div class="preview-image-list" id="gambarTambahanPreview"></div>
                        </div>

                    </div>

                    <div class="form-group">

                        <label for="status">Status Pengumuman</label>

                        <select id="status" name="status" required>

                            <option value="draft"
                                <?= $status_tampil === 'draft' ? 'selected' : '' ?>>
                                Draft
                            </option>

                            <option value="terbit"
                                <?= $status_tampil === 'terbit' ? 'selected' : '' ?>>
                                Terbit / Dipublikasikan
                            </option>

                        </select>

                        <p class="form-hint">
                            Pengumuman berstatus Terbit akan ditampilkan di halaman utama website.
                        </p>

                    </div>

                    <div class="form-actions">

                        <button type="submit" class="btn-simpan">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Simpan Perubahan
                        </button>

                        <a href="pengumuman.php" class="btn-batal">
                            Batal
                        </a>

                    </div>

                </form>

            </div>

        </div>

    </div>

</main>

<script>
document.getElementById('gambar').addEventListener('change', function () {

    const file = this.files[0];
    const preview = document.getElementById('previewBaru');
    const gambar = document.getElementById('gambarPreview');

    if (!file) {
        preview.style.display = 'none';
        gambar.src = '';
        return;
    }

    const allowed = ['image/jpeg', 'image/png', 'image/webp'];

    if (!allowed.includes(file.type)) {
        alert('Format gambar harus JPG, PNG, atau WEBP.');
        this.value = '';
        preview.style.display = 'none';
        return;
    }

    if (file.size > 3 * 1024 * 1024) {
        alert('Ukuran gambar maksimal 3 MB.');
        this.value = '';
        preview.style.display = 'none';
        return;
    }

    gambar.src = URL.createObjectURL(file);
    preview.style.display = 'block';
});

document.getElementById('gambarTambahan').addEventListener('change', function () {
    const preview = document.getElementById('previewGambarTambahan');
    const imageList = document.getElementById('gambarTambahanPreview');
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    const files = Array.from(this.files);

    imageList.replaceChildren();

    for (const file of files) {
        if (!allowed.includes(file.type) || file.size > 3 * 1024 * 1024) {
            alert('Setiap gambar harus berformat JPG, PNG, atau WEBP dan maksimal 3 MB.');
            this.value = '';
            imageList.replaceChildren();
            preview.style.display = 'none';
            return;
        }

        const image = document.createElement('img');
        image.src = URL.createObjectURL(file);
        image.alt = 'Preview gambar isi berita';
        imageList.appendChild(image);
    }

    preview.style.display = files.length ? 'block' : 'none';
});
</script>

</body>
</html>
