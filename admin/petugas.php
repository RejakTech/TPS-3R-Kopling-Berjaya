<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Petugas</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
/* =========================
   AREA KONTEN
   SAMA DENGAN KATEGORI
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
/* =========================
   HALAMAN PETUGAS
========================= */

.petugas-page {
    width: 100%;

    max-width: none;

    margin: 0;

    box-sizing: border-box;
}


/* =========================
   HEADING
========================= */

.admin-heading {
    margin-bottom: 8px;
}

.admin-heading h1 {
    margin: 0;

    color: #075B5B;

    font-size: 30px;

    font-weight: 700;

    line-height: 1.2;
}

.admin-heading p {
    margin: 8px 0 0;

    color: #6D858D;

    font-size: 15px;

    line-height: 1.5;
}


/* =========================
   LINK KEMBALI
========================= */

.petugas-back {
    display: inline-flex;

    align-items: center;

    margin-top: 22px;

    margin-bottom: 20px;

    color: #49615c;

    text-decoration: none;

    font-size: 14px;

    font-weight: 500;
}

.petugas-back:hover {
    color: #0f766e;
}


/* =========================
   ACTION BAR
========================= */

.petugas-page-actions {
    display: flex;

    align-items: center;

    justify-content: flex-end;

    width: 100%;

    margin-bottom: 20px;

    box-sizing: border-box;
}

.btn-tambah-petugas {
    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-height: 42px;

    padding: 0 18px;

    border-radius: 8px;

    background: #0f766e;

    color: #ffffff;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;

    box-sizing: border-box;
}

.btn-tambah-petugas:hover {
    background: #0d655f;
}


/* =========================
   ALERT
========================= */

.form-alert-success,
.form-alert-error {
    width: 100%;

    box-sizing: border-box;

    padding: 13px 16px;

    margin-bottom: 20px;

    border-radius: 10px;

    font-size: 13px;

    font-weight: 600;
}

.form-alert-success {
    background: #e8f5f1;

    border: 1px solid #cce9e1;

    color: #0f766e;
}

.form-alert-error {
    background: #fcebec;

    border: 1px solid #f3d0d2;

    color: #b42318;
}


/* =========================
   TABEL
========================= */

.warga-table-container {
    width: 100%;

    background: #ffffff;

    border: 1px solid #e7eeeb;

    border-radius: 14px;

    overflow-x: auto;

    box-sizing: border-box;
}

.warga-table {
    width: 100%;

    min-width: 900px;

    border-collapse: collapse;

    font-size: 13px;
}

.warga-table th {
    background: #f7faf9;

    color: #52635e;

    font-size: 12px;

    font-weight: 700;

    text-align: left;

    padding: 14px 16px;

    border-bottom: 1px solid #e7eeeb;

    white-space: nowrap;
}

.warga-table td {
    padding: 14px 16px;

    color: #465650;

    border-bottom: 1px solid #edf2f0;

    vertical-align: middle;
}

.warga-table tbody tr:hover {
    background: #fafcfb;
}

.warga-table tbody tr:last-child td {
    border-bottom: none;
}


/* =========================
   STATUS
========================= */

.warga-status {
    display: inline-flex;

    align-items: center;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 700;
}

.warga-status-aktif {
    background: #e8f5f1;

    color: #0f766e;
}

.warga-status-nonaktif {
    background: #fcebec;

    color: #b42318;
}


/* =========================
   ACTION BUTTON
========================= */

.petugas-action-buttons {
    display: flex;

    align-items: center;

    gap: 6px;

    flex-wrap: wrap;
}

.btn-edit-petugas,
.btn-hapus-petugas {
    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-height: 34px;

    padding: 0 11px;

    border-radius: 7px;

    font-size: 12px;

    font-weight: 700;

    box-sizing: border-box;

    white-space: nowrap;
}

.btn-edit-petugas {
    background: #e8f5f1;

    color: #0f766e;

    text-decoration: none;
}

.btn-edit-petugas:hover {
    background: #d9eee8;
}

.btn-hapus-petugas {
    border: 0;

    background: #fcebec;

    color: #b42318;

    cursor: pointer;
}

.btn-hapus-petugas:hover {
    background: #f7dfe1;
}


/* =========================
   EMPTY DATA
========================= */

.petugas-empty {
    text-align: center;

    padding: 35px 20px;

    color: #71817c;
}


/* =====================================
   PETUGAS - PENYESUAIAN TAMPILAN
   TEMA NAVY • TEAL • CYAN
   SIDEBAR TIDAK DIUBAH
===================================== */

/* Area konten */
.admin-main {
    background: #f4f9f8;
    min-height: 100vh;
}

.admin-content {
    padding: 42px 50px;
    box-sizing: border-box;
}

/* Heading */
.petugas-page .admin-heading h1 {
    color: #0B4650;
    font-size: 30px;
    font-weight: 700;
}

.petugas-page .admin-heading p {
    color: #718995;
    font-size: 14px;
}

/* Tombol kembali */
.petugas-back {
    color: #087987;
    font-weight: 600;
    transition: .2s ease;
}

.petugas-back:hover {
    color: #0B354D;
}

/* Tombol tambah */
.btn-tambah-petugas {
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    border-radius: 9px;
    box-shadow: 0 4px 12px rgba(11, 53, 77, .14);
    transition: .2s ease;
}

.btn-tambah-petugas:hover {
    background: linear-gradient(110deg, #082B40, #109EAF);
    transform: translateY(-2px);
}

/* Notifikasi */
.form-alert-success {
    background: #E2F5F1;
    border: 1px solid #C5E9DF;
    color: #087C72;
}

.form-alert-error {
    background: #FFF0F0;
    border: 1px solid #F3D0D2;
    color: #B42318;
}

/* Kotak tabel */
.warga-table-container {
    background: #FFFFFF;
    border: 1px solid #E1EAED;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(11, 45, 70, .04);
    overflow-x: auto;
}

/* Header tabel */
.warga-table th {
    background: #EAF3F6;
    color: #315A69;
    border-bottom: 1px solid #DCE8EC;
    padding: 16px;
}

/* Isi tabel */
.warga-table td {
    color: #526671;
    padding: 15px 16px;
    border-bottom: 1px solid #EDF2F4;
}

.warga-table tbody tr:hover {
    background: #F2F9FA;
}

.warga-table tbody tr:last-child td {
    border-bottom: none;
}

/* Kolom status */
.warga-table td:nth-child(7) {
    color: #087C72;
    font-weight: 600;
}

/* Tombol edit */
.btn-edit-petugas {
    background: linear-gradient(110deg, #0B354D, #159BAE);
    color: #FFFFFF;
    border-radius: 8px;
    transition: .2s ease;
}

.btn-edit-petugas:hover {
    transform: translateY(-2px);
}

/* Tombol hapus */
.btn-hapus-petugas {
    background: #FFF0F0;
    color: #B42318;
    border: 1px solid #F5D7D7;
    border-radius: 8px;
    transition: .2s ease;
}

.btn-hapus-petugas:hover {
    background: #FCE0E0;
}

/* Jarak tombol aksi */
.petugas-action-buttons {
    display: flex;
    align-items: center;
    gap: 7px;
}

.petugas-action-buttons form {
    margin: 0;
}


/* TOMBOL KEMBALI - SAMA PERSIS DENGAN DATA WARGA */
.petugas-page .btn-login-submit {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    padding: 11px 20px !important;
    border: none;
    border-radius: 10px !important;
    background: linear-gradient(110deg, #0B354D, #17B8C8) !important;
    color: #FFFFFF !important;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    box-shadow: 0 5px 13px rgba(11, 53, 77, .15);
    transition: .25s ease;
}

.petugas-page .btn-login-submit:hover {
    background: linear-gradient(110deg, #082B40, #109EAF) !important;
    transform: translateY(-2px);
}
/* SEJAJARKAN TOMBOL KEMBALI DAN TAMBAH PETUGAS */
.petugas-page-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    width: 100%;
    margin: 15px 0 24px;
}

/* Hilangkan jarak tambahan tombol */
.petugas-page-actions .btn-login-submit,
.petugas-page-actions .btn-tambah-petugas {
    margin: 0 !important;
    min-height: 45px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* PERBAIKAN PROPORSI TOMBOL PETUGAS */
.petugas-page-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    width: 100%;
    margin: 15px 0 24px;
}

/* Tombol kembali */
.petugas-page-actions .btn-login-submit {
    width: auto !important;
    min-width: 0;
    flex: 0 0 auto;
    min-height: 45px;
    padding: 0 20px !important;
    white-space: nowrap;
    border-radius: 10px;
}

/* Tombol tambah */
.petugas-page-actions .btn-tambah-petugas {
    width: auto !important;
    min-width: 0;
    flex: 0 0 auto;
    min-height: 45px;
    padding: 0 20px;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
}

/* =====================================
   RESPONSIVE SIDEBAR DATA PETUGAS
===================================== */

/* Hamburger tidak muncul di laptop */
.mobile-menu-button {
    display: none !important;
}

/* Overlay default tersembunyi */
.sidebar-overlay {
    display: none;
}

/* Mencegah konten melebar */
.admin-main,
.admin-content,
.petugas-page {
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

/* TABLET DAN HP */
@media (max-width: 800px) {

    body {
        overflow-x: hidden;
    }

    /* SIDEBAR */
    .admin-sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 250px !important;
        height: 100vh !important;
        z-index: 1001 !important;
        transform: translateX(-100%) !important;
        transition: transform .3s ease !important;
        overflow-y: auto;
    }

    .admin-sidebar.open {
        transform: translateX(0) !important;
    }

    /* OVERLAY */
    .sidebar-overlay.show {
        display: block !important;
        position: fixed;
        inset: 0;
        background: rgba(4, 25, 39, .55);
        z-index: 1000;
    }

    /* KONTEN */
    .admin-main {
        margin-left: 0 !important;
        width: 100% !important;
        min-height: 100vh;
    }

    .admin-content {
        width: 100%;
        padding: 18px 20px 25px !important;
    }

    /* HEADER STICKY */
    .petugas-page .admin-heading {
        position: sticky;
        top: 0;
        z-index: 998;
        background: #f4f9f8;
        padding: 8px 0 14px;
        margin-bottom: 12px;
    }

    .petugas-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
    }

    .petugas-title-text {
        min-width: 0;
    }

    .petugas-page .admin-heading h1 {
        font-size: 26px;
    }

    .petugas-page .admin-heading p {
        font-size: 13px;
    }

    /* HAMBURGER */
    .mobile-menu-button {
        display: inline-flex !important;
        position: static !important;
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        align-items: center;
        justify-content: center;
        border: none;
        border-radius: 10px;
        background: linear-gradient(110deg, #0B354D, #17B8C8);
        color: #FFFFFF;
        font-size: 23px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(11, 53, 77, .2);
    }

    /* TOMBOL KEMBALI DAN TAMBAH */
    .petugas-page-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        width: 100%;
        margin: 12px 0 20px;
    }

    .petugas-page-actions .btn-login-submit,
    .petugas-page-actions .btn-tambah-petugas {
        flex: 1 1 140px;
        width: auto !important;
        min-width: 0;
        min-height: 43px;
        padding: 0 12px !important;
        font-size: 11px;
        white-space: normal;
        text-align: center;
    }

    /* TABEL */
    .warga-table-container {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .warga-table {
        min-width: 900px;
    }

    .petugas-action-buttons {
        flex-direction: row;
        align-items: center;
        flex-wrap: nowrap;
    }
}

/* HP KECIL */
@media (max-width: 480px) {

    .admin-content {
        padding: 15px 14px 20px !important;
    }

    .petugas-page .admin-heading h1 {
        font-size: 23px;
    }

    .petugas-page .admin-heading p {
        font-size: 12px;
    }

    .petugas-page-actions {
        gap: 8px;
    }

    .petugas-page-actions .btn-login-submit,
    .petugas-page-actions .btn-tambah-petugas {
        flex: 1 1 100%;
        width: 100% !important;
    }

    .petugas-action-buttons {
        flex-direction: row;
        align-items: center;
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

<!-- OVERLAY SIDEBAR -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="admin-sidebar" id="adminSidebar">

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

        <!-- AKTIF -->
        <a href="petugas.php" class="sidebar-link active">
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
        <a href="pengumuman.php"
        class="sidebar-link">
        <span class="sidebar-icon">📢</span>
        <span>Kelola Pengumuman</span></a>
        
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

        <div class="petugas-page">

            <div class="admin-heading">

    <!-- JUDUL DAN HAMBURGER -->
    <div class="petugas-title-row">

        <div class="petugas-title-text">
            <h1>Data Petugas</h1>
            <p>Daftar petugas Bank Sampah Digital.</p>
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
<div class="petugas-page-actions">

    <a href="dashboard.php" class="btn-login-submit">
        ← Kembali ke Dashboard
    </a>

    <a href="tambah_petugas.php" class="btn-tambah-petugas">
        + Tambah Petugas
    </a>

</div>


<?php if (isset($_GET['status'])) : ?>

    <?php if ($_GET['status'] == 'berhasil') : ?>

        <div class="form-alert-success">
            Petugas berhasil ditambahkan!
        </div>

    <?php elseif ($_GET['status'] == 'edit_berhasil') : ?>

        <div class="form-alert-success">
            Data petugas berhasil diperbarui!
        </div>

    <?php elseif ($_GET['status'] == 'hapus_berhasil') : ?>

        <div class="form-alert-success">
            Petugas berhasil dihapus!
        </div>

    <?php elseif ($_GET['status'] == 'hapus_gagal') : ?>

        <div class="form-alert-error">
            Gagal menghapus petugas. Silakan coba kembali.
        </div>

    <?php elseif ($_GET['status'] == 'token_gagal') : ?>

        <div class="form-alert-error">
            Permintaan tidak valid. Silakan muat ulang halaman.
        </div>

    <?php endif; ?>

<?php endif; ?>



    <div class="warga-table-container">
        <table class="warga-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Lengkap</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Nomor HP</th>
                    <th>Alamat</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php
                $query = mysqli_query(
                    $conn,
                    "SELECT * FROM users
                     WHERE role = 'petugas'
                     ORDER BY id DESC"
                );

                $no = 1;

                if (mysqli_num_rows($query) > 0) :
                    while ($petugas = mysqli_fetch_assoc($query)) :
                ?>

                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($petugas['nama_lengkap']); ?></td>
                    <td><?php echo htmlspecialchars($petugas['username']); ?></td>
                    <td><?php echo htmlspecialchars($petugas['email'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($petugas['no_hp'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($petugas['alamat'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($petugas['status']); ?></td>
<td>
    <div class="petugas-action-buttons">

        <a
            href="edit_petugas.php?id=<?php echo (int) $petugas['id']; ?>"
            class="btn-edit-petugas"
        >
            Edit
        </a>

        <form
            action="hapus_petugas.php"
            method="POST"
            onsubmit="return confirm('Apakah Anda yakin ingin menghapus petugas ini? Data akun yang dihapus tidak dapat dikembalikan.');"
        >

            <input
                type="hidden"
                name="id"
                value="<?php echo (int) $petugas['id']; ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
            >

            <button type="submit" class="btn-hapus-petugas">
                Hapus
            </button>

        </form>

    </div>
</td>

                </tr>

                <?php
                    endwhile;
                else :
                ?>

                <tr>
                    <td colspan="8" style="text-align:center;">
                        Belum ada data petugas.
                    </td>
                </tr>


                
                <?php endif; ?>
            </tbody>
        </table>
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