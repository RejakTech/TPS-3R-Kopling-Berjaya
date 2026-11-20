
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

// Token keamanan
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil ID setoran
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: setoran.php");
    exit;
}

// Ambil detail setoran
$query = mysqli_prepare(
    $conn,
    "SELECT
        s.*,
        u.nama_lengkap,
        u.username,
        k.nama_kategori
     FROM setoran_sampah s
     INNER JOIN users u ON s.user_id = u.id
     INNER JOIN kategori_sampah k ON s.kategori_id = k.id
     WHERE s.id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($query, "i", $id);
mysqli_stmt_execute($query);

$result = mysqli_stmt_get_result($query);
$setoran = mysqli_fetch_assoc($result);

mysqli_stmt_close($query);

if (!$setoran) {
    header("Location: setoran.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Setoran - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f4f9f8;
            overflow-x: hidden;
        }

        /* =========================
           LAYOUT ADMIN
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
           SIDEBAR
        ========================= */

        .admin-sidebar {
            position: fixed !important;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            z-index: 1001;
            overflow-y: auto;
        }

        .sidebar-overlay {
            display: none;
        }

        /* =========================
           HEADER
        ========================= */

        .admin-heading {
            margin-bottom: 25px;
        }

        .admin-heading h1 {
            margin: 0 0 8px;
            color: #0B4650;
            font-size: 30px;
            font-weight: 700;
            line-height: 1.3;
        }

        .admin-heading p {
            margin: 0;
            color: #718995;
            font-size: 14px;
            line-height: 1.6;
        }

        .detail-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .detail-title-text {
            min-width: 0;
        }

        .mobile-menu-button {
            display: none !important;
        }

        /* =========================
           CONTAINER DETAIL
        ========================= */

        .petugas-form-container {
            width: 100%;
            max-width: 100%;
            padding: 28px 30px;
            background: #FFFFFF;
            border: 1px solid #E1EAED;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(11, 45, 70, .05);
            box-sizing: border-box;
        }

        .petugas-form-heading {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid #EDF2F4;
        }

        .petugas-form-heading h2 {
            margin: 0;
            color: #0B354D;
            font-size: 20px;
            font-weight: 700;
        }

        .petugas-form-heading p {
            margin: 7px 0 0;
            color: #718995;
            font-size: 13px;
            line-height: 1.5;
        }

        /* =========================
           DETAIL GRID
        ========================= */

        .detail-setoran-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            width: 100%;
        }

        .detail-setoran-item {
            min-width: 0;
            padding: 18px 20px;
            background: #F5F9FA;
            border: 1px solid #E1EAED;
            border-radius: 11px;
        }

        .detail-setoran-item span {
            display: block;
            margin-bottom: 8px;
            color: #718995;
            font-size: 13px;
        }

        .detail-setoran-item strong {
            display: block;
            color: #315A69;
            font-size: 15px;
            line-height: 1.6;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .detail-setoran-total {
            background: #EAF8F5;
            border-color: #C8EAE4;
        }

        .detail-setoran-total strong {
            color: #07836F;
            font-size: 20px;
        }

        .detail-setoran-note {
            grid-column: 1 / -1;
        }

        /* =========================
           ALASAN PENOLAKAN
        ========================= */

        .petugas-alasan-ditolak {
            margin-top: 25px;
            padding: 20px;
            background: #FFF5F5;
            border: 1px solid #F3D0D2;
            border-radius: 12px;
        }

        .petugas-alasan-ditolak-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .petugas-alasan-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            flex-shrink: 0;
            background: #FEE2E2;
            color: #DC2626;
            border-radius: 50%;
            font-weight: bold;
        }

        .petugas-alasan-ditolak-heading h4 {
            margin: 0 0 5px;
            color: #B91C1C;
        }

        .petugas-alasan-ditolak-heading p {
            margin: 0;
            color: #8B7777;
            font-size: 13px;
        }

        .petugas-alasan-ditolak-content {
            color: #7F1D1D;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        /* =========================
           ACTION VERIFIKASI
        ========================= */

        .detail-setoran-actions {
            display: flex;
            align-items: flex-start;
            gap: 18px;
            margin-top: 28px;
            padding: 22px;
            background: #F5F9FA;
            border: 1px solid #E1EAED;
            border-radius: 12px;
        }

        .detail-setoran-actions form {
            flex: 1;
            min-width: 0;
        }

        .btn-terima-setoran,
        .btn-tolak-setoran {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 12px 20px;
            border: none;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s ease;
        }

        .btn-terima-setoran {
            background: linear-gradient(110deg, #0B806E, #17B89B);
            color: #FFFFFF;
        }

        .btn-terima-setoran:hover {
            background: #087765;
            transform: translateY(-1px);
        }

        .btn-tolak-setoran {
            width: 100%;
            margin-top: 12px;
            background: #DC3545;
            color: #FFFFFF;
        }

        .btn-tolak-setoran:hover {
            background: #BD2635;
            transform: translateY(-1px);
        }

        .petugas-alasan-group {
            width: 100%;
        }

        .petugas-alasan-group label {
            display: block;
            margin-bottom: 9px;
            color: #315A69;
            font-size: 13px;
            font-weight: 600;
        }

        .petugas-alasan-group label span {
            color: #DC3545;
        }

        .petugas-alasan-group textarea {
            display: block;
            width: 100%;
            min-height: 95px;
            padding: 12px 13px;
            border: 1px solid #DCE8EC;
            border-radius: 9px;
            outline: none;
            background: #FFFFFF;
            color: #334E5A;
            font-family: inherit;
            font-size: 13px;
            resize: vertical;
            box-sizing: border-box;
        }

        .petugas-alasan-group textarea:focus {
            border-color: #17B8C8;
            box-shadow: 0 0 0 3px rgba(23, 184, 200, .12);
        }

        .petugas-alasan-group small {
            display: block;
            margin-top: 7px;
            color: #81939B;
            font-size: 11px;
        }

        /* =========================
           ALERT SUDAH DIPROSES
        ========================= */

        .form-alert-success {
            margin-top: 25px;
            padding: 15px 18px;
            color: #08765F;
            background: #E8F8F1;
            border: 1px solid #B8EAD5;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.6;
        }

        /* =========================
           TOMBOL KEMBALI
        ========================= */

.btn-batal-petugas {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 0 20px;
    border: none;
    border-radius: 9px;
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    color: #FFFFFF;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    box-shadow: 0 5px 13px rgba(11, 53, 77, .15);
    transition: .2s ease;
}

.btn-batal-petugas:hover {
    background: linear-gradient(110deg, #082B40, #109EAF);
    color: #FFFFFF;
    transform: translateY(-1px);
}
        /* =========================
           RESPONSIVE TABLET
        ========================= */

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
                padding: 18px 20px 25px !important;
            }

            .admin-heading {
                padding: 8px 0 14px;
                margin-bottom: 16px;
            }

            .admin-heading h1 {
                font-size: 26px;
            }

            .admin-heading p {
                font-size: 13px;
            }

            .mobile-menu-button {
                display: inline-flex !important;
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

            .petugas-form-container {
                padding: 23px 20px;
            }
        }

        /* =========================
           RESPONSIVE HP
        ========================= */

        @media (max-width: 600px) {

            .admin-content {
                padding: 16px 14px 22px !important;
            }

            .admin-heading h1 {
                font-size: 23px;
            }

            .admin-heading p {
                font-size: 12px;
            }

            .petugas-form-container {
                padding: 20px 16px;
                border-radius: 12px;
            }

            .petugas-form-heading h2 {
                font-size: 18px;
            }

            .detail-setoran-grid {
                grid-template-columns: 1fr;
                gap: 13px;
            }

            .detail-setoran-note {
                grid-column: auto;
            }

            .detail-setoran-item {
                padding: 15px;
            }

            .detail-setoran-actions {
                flex-direction: column;
                gap: 18px;
                padding: 16px;
            }

            .detail-setoran-actions form {
                width: 100%;
                flex: auto;
            }

            .btn-terima-setoran,
            .btn-tolak-setoran {
                width: 100%;
            }

            .petugas-alasan-ditolak {
                padding: 16px;
            }

            .btn-batal-petugas {
                width: 100%;
            }
        }

        /* LAPTOP: SIDEBAR TETAP TERLIHAT */
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

        <a href="kategori.php" class="sidebar-link">
            <span class="sidebar-icon">♻️</span>
            <span>Kategori Sampah</span>
        </a>

        <a href="setoran.php" class="sidebar-link active">
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

        <a href="pengumuman.php" class="sidebar-link">
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

<!-- KONTEN UTAMA -->
<main class="admin-main">

    <div class="admin-content">

        <div class="admin-heading">

            <div class="detail-title-row">

                <div class="detail-title-text">
                    <h1>Detail Setoran Sampah</h1>
                    <p>Periksa informasi setoran warga sebelum melakukan verifikasi.</p>
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

        <div class="petugas-form-container">

            <div class="petugas-form-heading">
                <h2>Informasi Setoran #<?php echo (int) $setoran['id']; ?></h2>
                <p>Detail data pengajuan setoran sampah warga.</p>
            </div>

            <div class="detail-setoran-grid">

                <div class="detail-setoran-item">
                    <span>Nama Warga</span>
                    <strong><?php echo htmlspecialchars($setoran['nama_lengkap']); ?></strong>
                </div>

                <div class="detail-setoran-item">
                    <span>Username</span>
                    <strong><?php echo htmlspecialchars($setoran['username']); ?></strong>
                </div>

                <div class="detail-setoran-item">
                    <span>Kategori Sampah</span>
                    <strong><?php echo htmlspecialchars($setoran['nama_kategori']); ?></strong>
                </div>

                <div class="detail-setoran-item">
                    <span>Tanggal Setor</span>
                    <strong><?php echo date('d-m-Y', strtotime($setoran['tanggal_setor'])); ?></strong>
                </div>

                <div class="detail-setoran-item">
                    <span>Berat Sampah</span>
                    <strong>
                        <?php echo number_format((float)$setoran['berat_kg'], 2, ',', '.'); ?> Kg
                    </strong>
                </div>

                <div class="detail-setoran-item">
                    <span>Harga per Kg</span>
                    <strong>
                        Rp <?php echo number_format((float)$setoran['harga_per_kg'], 0, ',', '.'); ?>
                    </strong>
                </div>

                <div class="detail-setoran-item detail-setoran-total">
                    <span>Total Nilai Setoran</span>
                    <strong>
                        Rp <?php echo number_format((float)$setoran['total_harga'], 0, ',', '.'); ?>
                    </strong>
                </div>

                <div class="detail-setoran-item">
                    <span>Status Setoran</span>
                    <strong><?php echo htmlspecialchars(ucfirst($setoran['status'])); ?></strong>
                </div>

                <div class="detail-setoran-item detail-setoran-note">
                    <span>Catatan Warga</span>
                    <strong>
                        <?php echo htmlspecialchars($setoran['catatan'] ?: '-'); ?>
                    </strong>
                </div>

            </div>

            <?php if (
                $setoran['status'] === 'ditolak' &&
                !empty($setoran['alasan_penolakan'])
            ) : ?>

                <div class="petugas-alasan-ditolak">

                    <div class="petugas-alasan-ditolak-heading">

                        <span class="petugas-alasan-icon">!</span>

                        <div>
                            <h4>Alasan Penolakan</h4>
                            <p>Catatan dari petugas mengenai pengajuan ini.</p>
                        </div>

                    </div>

                    <div class="petugas-alasan-ditolak-content">
                        <?php echo nl2br(htmlspecialchars($setoran['alasan_penolakan'])); ?>
                    </div>

                </div>

            <?php endif; ?>

            <?php if ($setoran['status'] === 'menunggu') : ?>

                <div class="detail-setoran-actions">

                    <!-- FORM TERIMA SETORAN -->
                    <form
                        action="proses_verifikasi_setoran.php"
                        method="POST"
                        onsubmit="return confirm('Apakah Anda yakin ingin menerima setoran ini? Saldo warga akan bertambah.');"
                    >

                        <input
                            type="hidden"
                            name="id"
                            value="<?php echo (int)$setoran['id']; ?>"
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
                        >

                        <input type="hidden" name="aksi" value="terima">

                        <button type="submit" class="btn-terima-setoran">
                            ✓ Terima Setoran
                        </button>

                    </form>

                    <!-- FORM TOLAK SETORAN -->
                    <form
                        action="proses_verifikasi_setoran.php"
                        method="POST"
                        onsubmit="return confirm('Apakah Anda yakin ingin menolak setoran ini?');"
                    >

                        <input
                            type="hidden"
                            name="id"
                            value="<?php echo (int)$setoran['id']; ?>"
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
                        >

                        <input type="hidden" name="aksi" value="tolak">

                        <div class="petugas-alasan-group">

                            <label for="alasan_penolakan">
                                Alasan Penolakan <span>*</span>
                            </label>

                            <textarea
                                name="alasan_penolakan"
                                id="alasan_penolakan"
                                rows="3"
                                maxlength="1000"
                                placeholder="Tuliskan alasan penolakan setoran..."
                                required
                            ></textarea>

                            <small>
                                Alasan ini akan ditampilkan kepada warga.
                            </small>

                        </div>

                        <button type="submit" class="btn-tolak-setoran">
                            ✕ Tolak Setoran
                        </button>

                    </form>

                </div>

            <?php else : ?>

                <div class="form-alert-success">
                    Setoran ini sudah diproses dan tidak dapat diverifikasi kembali.
                </div>

            <?php endif; ?>

            <div class="petugas-form-actions">

                <a href="setoran.php" class="btn-batal-petugas">
                    ← Kembali ke Data Setoran
                </a>

            </div>

        </div>

    </div>

</main>

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
