
<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$error = "";

// Ambil ID kategori
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: kategori.php");
    exit;
}

// Ambil data kategori menggunakan SELECT
try {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, nama_kategori, deskripsi, harga_per_kg, status
         FROM kategori_sampah
         WHERE id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $kategori = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if (!$kategori) {
        header("Location: kategori.php");
        exit;
    }

} catch (mysqli_sql_exception $e) {
    die("Gagal mengambil data kategori. Silakan periksa database.");
}

// Proses update kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validasi CSRF
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Permintaan tidak valid. Silakan coba kembali.";

    } else {

        $nama_kategori = trim($_POST['nama_kategori'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $harga_per_kg = trim($_POST['harga_per_kg'] ?? '');
        $status = $_POST['status'] ?? '';

        // Pertahankan input jika validasi gagal
        $kategori['nama_kategori'] = $nama_kategori;
        $kategori['deskripsi'] = $deskripsi;
        $kategori['harga_per_kg'] = $harga_per_kg;
        $kategori['status'] = $status;

        // Validasi input
        if ($nama_kategori === '' || $harga_per_kg === '') {

            $error = "Nama kategori dan harga wajib diisi.";

        } elseif (mb_strlen($nama_kategori) > 100) {

            $error = "Nama kategori maksimal 100 karakter.";

        } elseif (mb_strlen($deskripsi) > 1000) {

            $error = "Deskripsi maksimal 1000 karakter.";

        } elseif (
            !is_numeric($harga_per_kg) ||
            !is_finite((float) $harga_per_kg) ||
            (float) $harga_per_kg < 0 ||
            (float) $harga_per_kg > 9999999999.99
        ) {

            $error = "Harga tidak valid atau melebihi batas.";

        } elseif (!in_array($status, ['aktif', 'nonaktif'], true)) {

            $error = "Status kategori tidak valid.";

        } else {

            try {

                // Periksa nama kategori agar tidak duplikat
                $cek = mysqli_prepare(
                    $conn,
                    "SELECT id
                     FROM kategori_sampah
                     WHERE nama_kategori = ? AND id != ?
                     LIMIT 1"
                );

                mysqli_stmt_bind_param(
                    $cek,
                    "si",
                    $nama_kategori,
                    $id
                );

                mysqli_stmt_execute($cek);
                mysqli_stmt_store_result($cek);

                if (mysqli_stmt_num_rows($cek) > 0) {

                    $error = "Nama kategori sudah digunakan.";

                } else {

                    // Update data tanpa mengubah ID kategori
                    $harga = (float) $harga_per_kg;
                    $deskripsi_db = $deskripsi !== ''
                        ? $deskripsi
                        : null;

                    $query = mysqli_prepare(
                        $conn,
                        "UPDATE kategori_sampah SET
                            nama_kategori = ?,
                            deskripsi = ?,
                            harga_per_kg = ?,
                            status = ?
                         WHERE id = ?"
                    );

                    mysqli_stmt_bind_param(
                        $query,
                        "ssdsi",
                        $nama_kategori,
                        $deskripsi_db,
                        $harga,
                        $status,
                        $id
                    );

                    mysqli_stmt_execute($query);

                    mysqli_stmt_close($query);
                    mysqli_stmt_close($cek);

                    // Perbarui token setelah berhasil
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header("Location: kategori.php?status=edit_berhasil");
                    exit;
                }

                mysqli_stmt_close($cek);

            } catch (mysqli_sql_exception $e) {

                if ($e->getCode() == 1062) {
                    $error = "Nama kategori sudah digunakan.";
                } else {
                    $error = "Terjadi kesalahan saat memperbarui kategori.";
                }
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

    <title>Edit Kategori - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
 
/* ========================================
   TOMBOL SIMPAN
======================================== */
.btn-simpan-petugas {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 12px 19px;
    border: none;
    border-radius: 9px;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #ffffff !important;
    font-family: inherit;
    font-size: 12px;
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
   LAYOUT UTAMA
======================================== */
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
    padding: 35px 30px; /* sebelumnya 42px 50px */
    box-sizing: border-box;
}

.petugas-form-container {
    width: 100%;
    max-width: 1050px; /* sebelumnya 950px */
    margin: 0 auto;
    padding: 30px;
    box-sizing: border-box;
    background: #ffffff;
    border: 1px solid #E2EDF0;
    border-radius: 16px;
    box-shadow: 0 5px 20px rgba(11, 53, 77, .05);
}

.petugas-form-heading {
    margin-bottom: 25px;
}

.petugas-heading-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    width: 100%;
}

.petugas-form-heading h2 {
    margin: 0;
    color: #0B4650;
    font-size: 24px;
    font-weight: 700;
}

.petugas-form-heading p {
    margin: 8px 0 0;
    color: #718995;
    font-size: 14px;
}

/* ========================================
   HAMBURGER
======================================== */
.mobile-menu-button {
    display: none !important;
}

/* ========================================
   RESPONSIVE
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
        height: 100vh !important;
        z-index: 1001 !important;
        transform: translateX(-100%) !important;
        transition: transform .3s ease !important;
        overflow-y: auto !important;
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
        background: rgba(4, 25, 39, .55) !important;
        z-index: 1000 !important;
    }

    .admin-main {
        margin-left: 0 !important;
        width: 100% !important;
        min-height: 100vh;
    }

    .admin-content {
        width: 100% !important;
        padding: 20px 16px 30px !important;
    }

    .petugas-form-container {
        width: 100% !important;
        max-width: 100% !important;
        padding: 22px 18px !important;
        margin: 0 auto;
    }

    .petugas-heading-title {
        align-items: center !important;
        justify-content: space-between !important;
    }

    .petugas-form-heading h2 {
        font-size: 20px !important;
    }

    .petugas-form-heading p {
        font-size: 13px !important;
        line-height: 1.5;
    }

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
        background: linear-gradient(110deg, #0B354D, #17B8C8) !important;
        color: #ffffff !important;
        font-size: 23px !important;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(11, 53, 77, .18);
    }

    .petugas-form-grid {
        display: grid !important;
        grid-template-columns: 1fr !important;
        gap: 16px !important;
    }

    .petugas-form-group {
        width: 100% !important;
        min-width: 0 !important;
    }

    .petugas-form-group input,
    .petugas-form-group textarea,
    .petugas-form-group select {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    .petugas-form-actions {
        display: flex !important;
        flex-wrap: wrap;
        gap: 10px;
    }
}

@media (max-width: 400px) {
    .admin-content {
        padding: 15px 12px 25px !important;
    }

    .petugas-form-container {
        padding: 18px 14px !important;
    }

    .petugas-form-heading h2 {
        font-size: 18px !important;
    }

    .petugas-form-actions > * {
        flex: 1;
        text-align: center;
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

    <!-- PERTAHANKAN ISI SIDEBAR LAMA DI SINI -->
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

<main class="admin-main">

    <div class="admin-content">

        <div class="petugas-form-container">

            <div class="petugas-form-heading">

                <div class="petugas-heading-title">
                    <div>
                        <h2>Formulir Edit Kategori</h2>
                        <p>Ubah informasi kategori sesuai kebutuhan.</p>
                    </div>

                    <button
                        type="button"
                        class="mobile-menu-button"
                        id="mobileMenuButton"
                        aria-label="Buka menu"
                    >☰</button>
                </div>

            </div>

            <?php if ($error !== "") : ?>
                <div class="form-alert-error">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
                >

                <div class="petugas-form-grid">

                    <div class="petugas-form-group full-width">
                        <label>Nama Kategori <span>*</span></label>
                        <input
                            type="text"
                            name="nama_kategori"
                            value="<?php echo htmlspecialchars($kategori['nama_kategori'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="petugas-form-group full-width">
                        <label>Deskripsi</label>
                        <textarea
                            name="deskripsi"
                            maxlength="1000"
                            rows="5"
                        ><?php echo htmlspecialchars($kategori['deskripsi'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="petugas-form-group">
                        <label>Harga per Kilogram (Rp) <span>*</span></label>
                        <input
                            type="number"
                            name="harga_per_kg"
                            min="0"
                            max="9999999999.99"
                            step="0.01"
                            value="<?php echo htmlspecialchars((string) $kategori['harga_per_kg'], ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                    </div>

                    <div class="petugas-form-group">
                        <label>Status Kategori</label>
                        <select name="status" required>
                            <option value="aktif" <?php echo ($kategori['status'] ?? '') === 'aktif' ? 'selected' : ''; ?>>
                                Aktif
                            </option>
                            <option value="nonaktif" <?php echo ($kategori['status'] ?? '') === 'nonaktif' ? 'selected' : ''; ?>>
                                Nonaktif
                            </option>
                        </select>
                    </div>

                </div>

                <div class="petugas-form-actions">
                    <a href="kategori.php" class="btn-batal-petugas">
                        Batal
                    </a>

                    <button
                        type="submit"
                        name="update"
                        class="btn-simpan-petugas"
                    >
                        Simpan Perubahan
                    </button>
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

</html>
