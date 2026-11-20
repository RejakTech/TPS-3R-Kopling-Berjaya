
<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$error = "";
$nama_kategori = "";
$deskripsi = "";
$harga_per_kg = "";

// Token keamanan
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Permintaan tidak valid. Silakan coba kembali.";

    } else {

        $nama_kategori = trim($_POST['nama_kategori'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $harga_per_kg = trim($_POST['harga_per_kg'] ?? '');

        if ($nama_kategori === "" || $harga_per_kg === "") {
            $error = "Nama kategori dan harga wajib diisi.";

        } elseif (strlen($nama_kategori) > 100) {
            $error = "Nama kategori maksimal 100 karakter.";

        } elseif (strlen($deskripsi) > 1000) {
            $error = "Deskripsi maksimal 1000 karakter.";
 

        } elseif (!is_numeric($harga_per_kg) || (float)$harga_per_kg < 0) {
            $error = "Harga harus berupa angka dan tidak boleh negatif.";

        } elseif (!is_finite((float)$harga_per_kg) || (float)$harga_per_kg > 9999999999.99) {
            $error = "Harga melebihi batas yang diperbolehkan.";

        } else {

            try {

                // Periksa nama kategori
                $cek = mysqli_prepare(
                    $conn,
                    "SELECT id FROM kategori_sampah
                     WHERE nama_kategori = ? LIMIT 1"
                );

                mysqli_stmt_bind_param($cek, "s", $nama_kategori);
                mysqli_stmt_execute($cek);
                mysqli_stmt_store_result($cek);

                if (mysqli_stmt_num_rows($cek) > 0) {

                    $error = "Nama kategori sudah terdaftar.";

                } else {

                    $harga = (float)$harga_per_kg;
                    $deskripsi_db = $deskripsi !== "" ? $deskripsi : null;
                    $status = "aktif";

                    $query = mysqli_prepare(
                        $conn,
                        "INSERT INTO kategori_sampah
                        (nama_kategori, deskripsi, harga_per_kg, status)
                        VALUES (?, ?, ?, ?)"
                    );

                    mysqli_stmt_bind_param(
                        $query,
                        "ssds",
                        $nama_kategori,
                        $deskripsi_db,
                        $harga,
                        $status
                    );

                    if (!mysqli_stmt_execute($query)) {
    throw new mysqli_sql_exception("Gagal menyimpan kategori.");
}

                    mysqli_stmt_close($query);
                    mysqli_stmt_close($cek);

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header("Location: kategori.php?status=berhasil");
                    exit;
                }

                mysqli_stmt_close($cek);

            } catch (mysqli_sql_exception $e) {
                $error = "Terjadi kesalahan saat menyimpan kategori.";
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
    
    <title>Tambah Kategori - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <style>

/* ========================================
   RESET DAN LAYOUT UTAMA
======================================== */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    min-height: 100%;
}

body {
    overflow-x: hidden;
    background: #f4f9f8;
}
/* ========================================
   SIDEBAR - MENGIKUTI STYLE.CSS UTAMA
======================================== */

.admin-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    z-index: 1001;
}

/* ========================================
   KONTEN UTAMA
======================================== */
.admin-main {
    display: block;
    position: relative;
    margin-left: 280px;
    min-width: 0;
    min-height: 100vh;
    background: #f4f9f8;
    box-sizing: border-box;
}

.admin-content {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 35px 30px;
    box-sizing: border-box;
}

/* ========================================
   FORM CONTAINER
======================================== */

.petugas-form-container {
    display: block;
    width: 100% !important;
    max-width: 1050px !important;
    margin: 0 auto !important;
    padding: 32px !important;
    background: #ffffff;
    border: 1px solid #e2edf0;
    border-radius: 16px;
    box-shadow: 0 5px 20px rgba(11, 53, 77, .05);
}

/* ========================================
   JUDUL FORM
======================================== */

.petugas-form-heading {
    width: 100%;
    margin-bottom: 28px;
}

.petugas-heading-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    width: 100%;
}

.petugas-heading-title h2 {
    margin: 0;
    color: #0B354D;
    font-size: 24px;
    font-weight: 700;
    line-height: 1.4;
}

.petugas-heading-title p {
    margin: 7px 0 0;
    color: #718995;
    font-size: 14px;
    line-height: 1.5;
}

/* ========================================
   FORM INPUT
======================================== */

.petugas-form-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 20px;
    width: 100%;
}

.petugas-form-group {
    width: 100%;
    min-width: 0;
}

.petugas-form-group.full-width {
    grid-column: 1 / -1;
}

.petugas-form-group label {
    display: block;
    margin-bottom: 9px;
    color: #0B354D;
    font-size: 14px;
    font-weight: 600;
}

.petugas-form-group label span {
    color: #dc2626;
}

.petugas-form-group input,
.petugas-form-group textarea {
    display: block;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0;
    padding: 13px 15px;
    border: 1px solid #dce7eb;
    border-radius: 9px;
    background: #ffffff;
    color: #263b49;
    font-family: inherit;
    font-size: 14px;
    outline: none;
}

.petugas-form-group input {
    height: 48px;
}

.petugas-form-group textarea {
    min-height: 110px;
    resize: vertical;
}

.petugas-form-group input:focus,
.petugas-form-group textarea:focus {
    border-color: #159BAE;
    box-shadow: 0 0 0 3px rgba(21, 155, 174, .10);
}

.petugas-form-group small {
    display: block;
    margin-top: 8px;
    color: #81939c;
    font-size: 12px;
    line-height: 1.5;
}

/* ========================================
   TOMBOL FORM
======================================== */

.petugas-form-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 28px;
}

.btn-simpan-petugas {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    min-height: 44px;
    padding: 12px 19px;
    border: none;
    border-radius: 9px;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #ffffff !important;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(21, 155, 174, .16);
    transition: all .25s ease;
}

.btn-simpan-petugas:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    transform: translateY(-2px);
}

/* ========================================
   TOMBOL HAMBURGER
======================================== */

.mobile-menu-button {
    display: none !important;
}

/* ========================================
   ALERT
======================================== */

.form-alert-error {
    margin-bottom: 20px;
    padding: 13px 16px;
    border: 1px solid #fecaca;
    border-radius: 9px;
    background: #fef2f2;
    color: #991b1b;
    font-size: 13px;
}

/* ========================================
   RESPONSIVE TABLET DAN HP
======================================== */

@media (max-width: 800px) {

    body {
        overflow-x: hidden;
    }

  .admin-sidebar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 250px !important;
    max-width: 85vw !important;
    height: 100vh !important;
    z-index: 1001 !important;
    transform: translateX(-100%) !important;
    transition: transform .3s ease !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
}

.admin-sidebar.open {
    transform: translateX(0) !important;
}
    .sidebar-overlay {
        display: none !important;
    }

    .sidebar-overlay.show {
        display: block !important;
        position: fixed !important;
        inset: 0 !important;
        z-index: 1000 !important;
        background: rgba(4, 25, 39, .55) !important;
    }

    .admin-main {
        display: block !important;
        width: 100% !important;
        margin-left: 0 !important;
        min-height: 100vh;
    }

    .admin-content {
        width: 100% !important;
        padding: 24px 18px !important;
    }

    .petugas-form-container {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 auto !important;
        padding: 23px 18px !important;
        border-radius: 13px;
    }

    .petugas-heading-title {
        align-items: flex-start !important;
        gap: 10px !important;
    }

    .petugas-heading-title h2 {
        font-size: 20px !important;
    }

    .petugas-heading-title p {
        font-size: 13px !important;
    }

    .mobile-menu-button {
        display: inline-flex !important;
        flex: 0 0 42px !important;
        width: 42px !important;
        height: 42px !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 !important;
        border: none !important;
        border-radius: 10px !important;
        background: linear-gradient(110deg, #0B354D, #17B8C8) !important;
        color: #ffffff !important;
        font-size: 23px !important;
        cursor: pointer;
    }

    .petugas-form-grid {
        grid-template-columns: minmax(0, 1fr) !important;
        gap: 17px;
    }

    .petugas-form-actions {
        justify-content: stretch;
    }

    .petugas-form-actions a,
    .petugas-form-actions button {
        flex: 1;
        min-width: 120px;
    }
}

/* ========================================
   HP KECIL
======================================== */

@media (max-width: 400px) {

    .admin-content {
        padding: 16px 12px !important;
    }

    .petugas-form-container {
        padding: 19px 14px !important;
    }

    .petugas-heading-title h2 {
        font-size: 18px !important;
    }

    .petugas-form-group input,
    .petugas-form-group textarea {
        font-size: 13px;
    }

    .petugas-form-actions {
        flex-direction: column-reverse;
    }

    .petugas-form-actions a,
    .petugas-form-actions button {
        width: 100%;
    }
}

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

        <a href="warga.php" class="sidebar-link ">
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

        <a href="kategori.php" class="sidebar-link active">
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
<main class="admin-main">

    <div class="admin-content">

    <div class="petugas-form-container">

<div class="petugas-form-heading">

    <div class="petugas-heading-title">
        <div>
            <h2>Formulir Tambah Kategori Sampah</h2>
            <p>Lengkapi informasi kategori berikut.</p>
        </div>

        <button
            type="button"
            class="mobile-menu-button"
            id="mobileMenuButton"
            aria-label="Buka menu"
        >
            ☰
        </button>
    </div>

</div>

        <?php if ($error != "") : ?>
            <div class="form-alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
            >

            <div class="petugas-form-grid">

                <div class="petugas-form-group full-width">
                    <label>Nama Kategori <span>*</span></label>

                    <input
                        type="text"
                        name="nama_kategori"
                        placeholder="Contoh: Plastik"
                        value="<?php echo htmlspecialchars($nama_kategori); ?>"
                        maxlength="100"
                        required
                    >
                </div>

                <div class="petugas-form-group full-width">
                    <label>Deskripsi</label>
                    <textarea
                        name="deskripsi"
                        rows="4"
                        maxlength="1000"
                        placeholder="Masukkan deskripsi kategori sampah"
                    ><?php echo htmlspecialchars($deskripsi); ?></textarea>
                </div>

                <div class="petugas-form-group full-width">
                    <label>Harga per Kilogram (Rp) <span>*</span></label>

                    <input
                        type="number"
                        name="harga_per_kg"
                        placeholder="Contoh: 2500"
                        min="0"
                        max="9999999999.99"
                        step="0.01"
                        value="<?php echo htmlspecialchars($harga_per_kg); ?>"
                        required
                    >

                    <small>Masukkan harga pembelian sampah untuk setiap kilogram.</small>
                </div>

            </div>

            <div class="petugas-form-actions">

                <a href="kategori.php" class="btn-batal-petugas">
                    Batal
                </a>

                <button
                    type="submit"
                    name="simpan"
                    class="btn-simpan-petugas"
                >
                    + Simpan Kategori
                </button>

            </div>

    </div>

        </form>

    </div>

    </div>

</main>

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
