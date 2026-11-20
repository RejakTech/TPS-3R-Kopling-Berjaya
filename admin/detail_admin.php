<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header("Location: kelola_admin.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil data admin
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        id,
        nama_lengkap,
        username,
        email,
        no_hp,
        status,
        created_at
     FROM users
     WHERE id = ?
     AND role = 'admin'
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$admin) {
    header("Location: kelola_admin.php?status=not_found");
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil riwayat aktivitas admin
|--------------------------------------------------------------------------
*/
$aktivitas = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        aktivitas,
        keterangan,
        tanggal_aktivitas
     FROM log_aktivitas
     WHERE user_id = ?
     ORDER BY tanggal_aktivitas DESC
     LIMIT 15"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $aktivitas[] = $row;
}

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Admin - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            width: 100%;
            overflow-x: hidden;
        }

        body {
            background: #f4f9f8;
        }

        /* SIDEBAR */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            z-index: 1001;
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* LAYOUT UTAMA */
        .admin-main {
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;
            background: #f4f9f8;
        }

        .admin-content {
            width: 100%;
            max-width: none;
            min-height: 100vh;
            margin: 0;
            padding: 42px 50px;
            box-sizing: border-box;
        }

        /* HEADING */
        .admin-heading {
            width: 100%;
            margin: 0 0 25px;
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
            line-height: 1.5;
        }

        .heading-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .heading-text {
            min-width: 0;
        }

        /* HAMBURGER */
        .mobile-menu-button {
            display: none !important;
        }

        /* DETAIL GRID */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            width: 100%;
            margin-bottom: 22px;
        }

        .detail-card,
        .activity-card {
            width: 100%;
            min-width: 0;
            background: #fff;
            border: 1px solid #E1EAED;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(11, 45, 70, .05);
        }

        .detail-card h2,
        .activity-card h2 {
            margin: 0 0 20px;
            color: #0B354D;
            font-size: 19px;
            font-weight: 700;
        }

        /* PROFIL */
        .profile-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 22px;
            min-width: 0;
        }

        .profile-avatar {
            flex: 0 0 58px;
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #DDF5F4;
            color: #0B8290;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
        }

        .profile-name {
            color: #0B354D;
            font-size: 18px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .profile-role {
            color: #718995;
            font-size: 13px;
            margin-top: 4px;
        }

        /* DETAIL ITEM */
        .detail-item {
            padding: 13px 0;
            border-bottom: 1px solid #EDF2F4;
            overflow-wrap: anywhere;
        }

        .detail-item:last-child {
            border-bottom: 0;
        }

        .detail-label {
            display: block;
            color: #81939B;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .detail-value {
            color: #315A69;
            font-size: 14px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        /* STATUS */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-aktif {
            background: #E5F8EF;
            color: #087443;
        }

        .status-nonaktif {
            background: #F1F4F6;
            color: #687780;
        }

        /* RIWAYAT AKTIVITAS */
        .activity-card {
            margin-bottom: 22px;
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #EDF2F4;
        }

        .activity-item:last-child {
            border-bottom: 0;
        }

        .activity-title {
            color: #315A69;
            font-weight: 700;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .activity-description {
            color: #718995;
            font-size: 13px;
            line-height: 1.6;
            margin-top: 5px;
            overflow-wrap: anywhere;
        }

        .activity-date {
            color: #9AAAB1;
            font-size: 12px;
            margin-top: 7px;
        }

        .empty-state {
            padding: 18px 0;
            color: #81939B;
            font-size: 13px;
            line-height: 1.6;
        }

        /* TOMBOL */
        .action-buttons {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 22px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 11px 20px;
            border-radius: 9px;
            text-decoration: none;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            transition: .2s ease;
        }

        .btn-back {
            background: #F5F8FA;
            color: #526671;
            border: 1px solid #DCE8EC;
        }

        .btn-back:hover {
            background: #EAF1F4;
        }

        .btn-edit {
            background: linear-gradient(110deg, #0B354D, #17B8C8);
            color: #fff;
            box-shadow: 0 5px 13px rgba(11, 53, 77, .15);
        }

        .btn-edit:hover {
            background: linear-gradient(110deg, #082B40, #109EAF);
            transform: translateY(-1px);
        }

        /* OVERLAY */
        .sidebar-overlay {
            display: none;
        }

        /* TABLET DAN HP */
        @media (max-width: 800px) {

            html,
            body {
                width: 100%;
                overflow-x: hidden;
            }

            .admin-dashboard {
                width: 100%;
                min-height: 100vh;
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
                -webkit-overflow-scrolling: touch;
            }

            .admin-sidebar.open {
                transform: translateX(0) !important;
            }

            .sidebar-overlay.show {
                display: block !important;
                position: fixed !important;
                inset: 0 !important;
                width: 100% !important;
                height: 100% !important;
                background: rgba(4, 25, 39, .55) !important;
                z-index: 1000 !important;
            }

            .admin-main {
                width: 100% !important;
                min-height: 100vh;
                margin-left: 0 !important;
            }

            .admin-content {
                width: 100% !important;
                min-height: 100vh;
                margin: 0 !important;
                padding: 20px 20px 30px !important;
            }

            .admin-heading {
                position: sticky;
                top: 0;
                z-index: 998;
                padding: 10px 0 15px;
                margin-bottom: 18px;
                background: #f4f9f8;
            }

            .admin-heading h1 {
                font-size: 26px;
            }

            .admin-heading p {
                font-size: 13px;
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
                color: #fff !important;
                font-size: 23px !important;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(11, 53, 77, .2);
            }

            .detail-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 15px;
            }

            .detail-card,
            .activity-card {
                padding: 20px;
            }
        }

        /* HP */
        @media (max-width: 600px) {

            .admin-content {
                padding: 15px 14px 25px !important;
            }

            .admin-heading {
                padding: 8px 0 13px;
                margin-bottom: 15px;
            }

            .admin-heading h1 {
                font-size: 23px;
            }

            .admin-heading p {
                font-size: 12px;
            }

            .mobile-menu-button {
                flex-basis: 40px !important;
                width: 40px !important;
                height: 40px !important;
                font-size: 21px !important;
            }

            .detail-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 14px;
                margin-bottom: 15px;
            }

            .detail-card,
            .activity-card {
                padding: 19px 16px;
                border-radius: 12px;
            }

            .detail-card h2,
            .activity-card h2 {
                font-size: 17px;
                margin-bottom: 16px;
            }

            .profile-avatar {
                flex-basis: 52px;
                width: 52px;
                height: 52px;
                font-size: 20px;
            }

            .profile-name {
                font-size: 16px;
            }

            .detail-value {
                font-size: 13px;
            }

            .activity-title {
                font-size: 13px;
            }

            .activity-description {
                font-size: 12px;
            }

            .action-buttons {
                flex-direction: column-reverse;
                align-items: stretch;
                gap: 10px;
                margin-top: 17px;
            }

            .btn {
                width: 100%;
                min-height: 45px;
                box-sizing: border-box;
            }
        }

        /* DESKTOP: SIDEBAR TETAP DAN TANPA HAMBURGER */
        @media (min-width: 801px) {

            .admin-sidebar {
                transform: translateX(0) !important;
            }

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

<div class="admin-dashboard">

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

            <a href="kelola_admin.php" class="sidebar-link active">
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

    <main class="admin-main">

        <div class="admin-content">

            <div class="admin-heading">

                <div class="heading-row">

                    <div class="heading-text">
                        <h1>Detail Admin</h1>
                        <p>Informasi akun administrator dan aktivitas terakhir.</p>
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

            <div class="detail-grid">

                <!-- PROFIL -->
                <div class="detail-card">

                    <div class="profile-header">

                        <div class="profile-avatar">
                            <?= htmlspecialchars(
                                strtoupper(substr($admin['nama_lengkap'], 0, 1)),
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </div>

                        <div>

                            <div class="profile-name">
                                <?= htmlspecialchars(
                                    $admin['nama_lengkap'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </div>

                            <div class="profile-role">
                                Administrator Sistem
                            </div>

                        </div>

                    </div>

                    <div class="detail-item">

                        <span class="detail-label">
                            Username
                        </span>

                        <span class="detail-value">
                            @<?= htmlspecialchars(
                                $admin['username'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </span>

                    </div>

                    <div class="detail-item">

                        <span class="detail-label">
                            Email
                        </span>

                        <span class="detail-value">
                            <?= htmlspecialchars(
                                $admin['email'] ?? '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </span>

                    </div>

                    <div class="detail-item">

                        <span class="detail-label">
                            No. HP
                        </span>

                        <span class="detail-value">
                            <?= htmlspecialchars(
                                $admin['no_hp'] ?: '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </span>

                    </div>

                </div>

                <!-- STATUS -->
                <div class="detail-card">

                    <h2>Status Akun</h2>

                    <div class="detail-item">

                        <span class="detail-label">
                            Status
                        </span>

                        <?php if ($admin['status'] === 'aktif'): ?>

                            <span class="status-badge status-aktif">
                                Aktif
                            </span>

                        <?php else: ?>

                            <span class="status-badge status-nonaktif">
                                Nonaktif
                            </span>

                        <?php endif; ?>

                    </div>

                    <div class="detail-item">

                        <span class="detail-label">
                            ID Admin
                        </span>

                        <span class="detail-value">
                            #<?= (int)$admin['id']; ?>
                        </span>

                    </div>

                    <div class="detail-item">

                        <span class="detail-label">
                            Akun Dibuat
                        </span>

                        <span class="detail-value">

                            <?php
                            $created_at = $admin['created_at'] ?? '';

                            if (
                                $created_at !== '' &&
                                strtotime($created_at) !== false
                            ) {
                                echo date(
                                    'd M Y, H:i',
                                    strtotime($created_at)
                                );
                            } else {
                                echo '-';
                            }
                            ?>

                        </span>

                    </div>

                    <div class="detail-item">

                        <span class="detail-label">
                            Status Login
                        </span>

                        <span class="detail-value">

                            <?php if (
                                (int)$admin['id'] === (int)$_SESSION['user_id']
                            ): ?>

                                Sedang digunakan

                            <?php else: ?>

                                Tidak sedang digunakan

                            <?php endif; ?>

                        </span>

                    </div>

                </div>

            </div>

            <!-- RIWAYAT AKTIVITAS -->
            <div class="activity-card">

                <h2>Riwayat Aktivitas</h2>

                <?php if (empty($aktivitas)): ?>

                    <div class="empty-state">
                        Belum ada aktivitas yang tercatat untuk admin ini.
                    </div>

                <?php else: ?>

                    <?php foreach ($aktivitas as $item): ?>

                        <div class="activity-item">

                            <div class="activity-title">
                                <?= htmlspecialchars(
                                    $item['aktivitas'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </div>

                            <?php if (!empty($item['keterangan'])): ?>

                                <div class="activity-description">
                                    <?= htmlspecialchars(
                                        $item['keterangan'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </div>

                            <?php endif; ?>

                            <div class="activity-date">

                                <?php
                                $tanggal = $item['tanggal_aktivitas'] ?? '';

                                if (
                                    $tanggal !== '' &&
                                    strtotime($tanggal) !== false
                                ) {
                                    echo date(
                                        'd M Y, H:i',
                                        strtotime($tanggal)
                                    );
                                } else {
                                    echo '-';
                                }
                                ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            <!-- TOMBOL AKSI -->
            <div class="action-buttons">

                <a href="kelola_admin.php" class="btn btn-back">
                    ← Kembali
                </a>

                <a
                    href="edit_admin.php?id=<?= (int)$admin['id']; ?>"
                    class="btn btn-edit"
                >
                    Edit Admin
                </a>

            </div>

        </div>

    </main>

</div>

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