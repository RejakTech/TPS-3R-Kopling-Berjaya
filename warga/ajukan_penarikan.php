
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

// Token keamanan CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int) $_SESSION['user_id'];

// Ambil saldo tabungan warga
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, saldo
     FROM tabungan_warga
     WHERE user_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$tabungan = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$saldo = $tabungan ? (float) $tabungan['saldo'] : 0;

$jumlah = "";
$metode = "tunai";
$nomor_rekening = "";
$nama_pemilik = "";
$catatan = "";

// Proses pengajuan penarikan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $jumlah = trim($_POST['jumlah'] ?? '');
    $metode = $_POST['metode_penarikan'] ?? '';
    $nomor_rekening = trim($_POST['nomor_rekening'] ?? '');
    $nama_pemilik = trim($_POST['nama_pemilik_rekening'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {

        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";

    } elseif (
        !is_numeric($jumlah) ||
        !is_finite((float) $jumlah) ||
        (float) $jumlah <= 0 ||
        (float) $jumlah > 9999999999.99
    ) {

        $error = "Nominal penarikan tidak valid.";

    } elseif (!in_array($metode, ['tunai', 'transfer'], true)) {

        $error = "Silakan pilih metode penarikan yang valid.";

    } elseif ($metode === 'transfer' && $nomor_rekening === '') {

        $error = "Nomor rekening wajib diisi.";

    } elseif ($metode === 'transfer' && $nama_pemilik === '') {

        $error = "Nama pemilik rekening wajib diisi.";

    } elseif (strlen($nomor_rekening) > 50 || strlen($nama_pemilik) > 100) {

        $error = "Informasi rekening terlalu panjang.";

    } elseif (strlen($catatan) > 5000) {

        $error = "Catatan maksimal 5000 karakter.";

    } else {

        try {

            $nominal = (float) $jumlah;

            // Periksa saldo terbaru dari database
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id, saldo
                 FROM tabungan_warga
                 WHERE user_id = ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $rekening = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (!$rekening) {

                $error = "Rekening tabungan belum tersedia.";

            } elseif ($nominal > (float) $rekening['saldo']) {

                $error = "Saldo tabungan tidak mencukupi untuk penarikan.";

            } else {

                $nomor_db = $metode === 'transfer'
                    ? $nomor_rekening
                    : null;

                $nama_db = $metode === 'transfer'
                    ? $nama_pemilik
                    : null;

                $catatan_db = $catatan !== ""
                    ? $catatan
                    : null;

                $status = "menunggu";

                // Simpan pengajuan penarikan
                $insert = mysqli_prepare(
                    $conn,
                    "INSERT INTO penarikan_tabungan
                    (
                        user_id,
                        jumlah,
                        metode_penarikan,
                        nomor_rekening,
                        nama_pemilik_rekening,
                        status,
                        catatan
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $insert,
                    "idsssss",
                    $user_id,
                    $nominal,
                    $metode,
                    $nomor_db,
                    $nama_db,
                    $status,
                    $catatan_db
                );

                mysqli_stmt_execute($insert);

                $penarikan_id = mysqli_insert_id($conn);

                mysqli_stmt_close($insert);

                // Catat aktivitas warga
                catatAktivitas(
                    $conn,
                    'Pengajuan Penarikan',
                    'Mengajukan penarikan tabungan sebesar Rp ' .
                    number_format($nominal, 0, ',', '.') .
                    ' melalui metode ' .
                    ($metode === 'transfer' ? 'Transfer Bank' : 'Tunai') .
                    ' dengan nomor pengajuan #' . $penarikan_id . '.'
                );

                // Kirim notifikasi kepada admin
                try {

                    $query_admin = mysqli_query(
                        $conn,
                        "SELECT id FROM users WHERE role = 'admin'"
                    );

                    $judul_notifikasi = "Pengajuan Penarikan Baru";

                    $pesan_notifikasi =
                        ($_SESSION['nama'] ?? 'Warga') .
                        " mengajukan penarikan tabungan sebesar Rp " .
                        number_format($nominal, 0, ',', '.') .
                        ". Silakan periksa dan proses pengajuan tersebut.";

                    while ($admin = mysqli_fetch_assoc($query_admin)) {

                        $berhasil_notifikasi = kirimNotifikasi(
                            $conn,
                            (int) $admin['id'],
                            $judul_notifikasi,
                            $pesan_notifikasi,
                            "informasi",
                            "penarikan.php?id=" . $penarikan_id
                        );

                        if (!$berhasil_notifikasi) {
                            error_log(
                                "Notifikasi penarikan #$penarikan_id gagal dikirim ke admin ID " .
                                $admin['id']
                            );
                        }
                    }

                } catch (Throwable $e) {

                    error_log(
                        "Pengiriman notifikasi penarikan #$penarikan_id gagal: " .
                        $e->getMessage()
                    );
                }

                // Buat token baru
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header("Location: riwayat_penarikan.php?status=berhasil");
                exit;
            }

        } catch (mysqli_sql_exception $e) {

            error_log("Pengajuan penarikan gagal: " . $e->getMessage());

            $error = "Pengajuan penarikan gagal disimpan. Silakan coba kembali.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ajukan Penarikan - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #F2F7FA;
        }

        .dashboard-warga {
            min-height: 100vh;
            background: #F2F7FA;
        }

        /* SIDEBAR */
        .warga-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            z-index: 1001;
            color: #fff;
            background: linear-gradient(180deg, #071F33, #0B2D46);
        }

        .sidebar-brand {
            flex-shrink: 0;
            padding: 24px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }

        .sidebar-brand-top {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-logo {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            padding: 3px;
            border-radius: 50%;
            background: #fff;
        }

        .sidebar-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .sidebar-brand h2 {
            margin: 0;
            color: #fff;
            font-size: 13px;
            line-height: 1.5;
            font-weight: 700;
        }

        .sidebar-brand p {
            margin: 4px 0 0;
            color: #AFC4D2;
            font-size: 9px;
            line-height: 1.5;
            letter-spacing: .4px;
        }

        .sidebar-nav {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
            padding: 14px 12px 20px;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: none;
        }

        .sidebar-nav::-webkit-scrollbar {
            display: none;
        }

        .sidebar-section-title {
            margin: 13px 0 6px;
            padding: 0 10px;
            color: #8FA9BA;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .sidebar-link {
            min-height: 43px;
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 12px;
            margin: 2px 0;
            border-radius: 9px;
            color: #D8E5ED;
            text-decoration: none;
            font-size: 12px;
            transition: .2s ease;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: #fff;
            background: rgba(24,195,216,.17);
        }

        .sidebar-link.active {
            box-shadow: inset 3px 0 0 #18C3D8;
        }

        .sidebar-link span:first-child {
            width: 20px;
            flex-shrink: 0;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-logout:hover {
            background: rgba(255,90,90,.14);
        }

        /* KONTEN */
        .warga-main {
            width: calc(100% - 250px);
            min-height: 100vh;
            margin-left: 250px;
            padding: 30px 35px;
        }

        .page-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .warga-heading h1 {
            margin: 0;
            color: #102F43;
            font-size: 25px;
            font-weight: 750;
        }

        .warga-heading p {
            margin: 7px 0 0;
            color: #718493;
            font-size: 12px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-kembali {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 17px;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(135deg, #071F33, #147F91);
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            box-shadow: 0 5px 13px rgba(7,31,51,.15);
            transition: .2s ease;
            white-space: nowrap;
        }

        .btn-kembali:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 16px rgba(7,31,51,.22);
        }

        .mobile-menu-button {
            display: none;
            width: 42px;
            height: 42px;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(135deg, #071F33, #147F91);
            font-size: 22px;
            cursor: pointer;
        }

        .sidebar-overlay {
            display: none;
        }

        /* KARTU SALDO */
        .tabungan-saldo-card {
            position: relative;
            overflow: hidden;
            width: 100%;
            padding: 25px 28px;
            margin-bottom: 25px;
            border-radius: 18px;
            color: #fff;
            background: linear-gradient(120deg, #071F33, #147F91);
            box-shadow: 0 8px 22px rgba(7,31,51,.13);
        }

        .tabungan-saldo-card::after {
            content: "";
            position: absolute;
            width: 180px;
            height: 180px;
            right: -45px;
            top: -75px;
            border: 25px solid rgba(255,255,255,.07);
            border-radius: 50%;
        }

        .saldo-label {
            display: block;
            color: #D5EAF0;
            font-size: 12px;
        }

        .tabungan-saldo-card h2 {
            position: relative;
            z-index: 1;
            margin: 10px 0;
            color: #fff;
            font-size: 30px;
            font-weight: 750;
        }

        .tabungan-saldo-card p {
            margin: 0;
            color: #C5DCE4;
            font-size: 11px;
        }

        /* FORMULIR */
        .petugas-form-container {
            width: 100%;
            padding: 27px;
            border: 1px solid #E5EDF2;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 5px 18px rgba(15,45,65,.045);
        }

        .petugas-form-heading {
            margin-bottom: 23px;
            padding-bottom: 17px;
            border-bottom: 1px solid #EAF0F4;
        }

        .petugas-form-heading h2 {
            margin: 0;
            color: #102F43;
            font-size: 18px;
            font-weight: 700;
        }

        .petugas-form-heading p {
            margin: 6px 0 0;
            color: #82919C;
            font-size: 11px;
        }

        .petugas-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 19px;
        }

        .petugas-form-group {
            min-width: 0;
        }

        .petugas-form-group.full-width {
            grid-column: 1 / -1;
        }

        .petugas-form-group label,
        .rekening-form-group label {
            display: block;
            margin-bottom: 8px;
            color: #294456;
            font-size: 12px;
            font-weight: 650;
        }

        .petugas-form-group label span,
        .rekening-form-group label span {
            color: #E34F4F;
        }

        .petugas-form-group input,
        .petugas-form-group select,
        .petugas-form-group textarea,
        .rekening-form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #DCE6EC;
            border-radius: 9px;
            outline: none;
            color: #294456;
            background: #FAFCFD;
            font-family: inherit;
            font-size: 12px;
            transition: .2s ease;
        }

        .petugas-form-group input:focus,
        .petugas-form-group select:focus,
        .petugas-form-group textarea:focus,
        .rekening-form-group input:focus {
            border-color: #18AFC2;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(24,195,216,.1);
        }

        .petugas-form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        #informasi_rekening {
            display: none;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
            padding: 17px;
            border: 1px solid #DCEEF1;
            border-radius: 12px;
            background: #F4FAFB;
        }

        .rekening-form-group {
            min-width: 0;
        }

        /* INFORMASI */
        .penarikan-info {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 22px;
            padding: 16px 17px;
            border-left: 4px solid #18B7C9;
            border-radius: 8px;
            background: #EFF9FA;
        }

        .info-icon {
            width: 27px;
            height: 27px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #fff;
            background: #147F91;
            font-size: 14px;
            font-weight: 700;
        }

        .penarikan-info strong {
            color: #126C7B;
            font-size: 12px;
        }

        .penarikan-info p {
            margin: 5px 0 0;
            color: #526D7A;
            font-size: 11px;
            line-height: 1.7;
        }

        /* TOMBOL */
        .petugas-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 23px;
        }

        .btn-batal-petugas,
        .btn-simpan-petugas {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 19px;
            border: none;
            border-radius: 9px;
            font-family: inherit;
            font-size: 11px;
            font-weight: 650;
            text-decoration: none;
            cursor: pointer;
            transition: .2s ease;
        }

        .btn-batal-petugas {
            color: #526777;
            background: #EDF2F5;
        }

        .btn-simpan-petugas {
            color: #fff;
            background: linear-gradient(135deg, #0B2D46, #147F91);
        }

        .btn-batal-petugas:hover,
        .btn-simpan-petugas:hover {
            transform: translateY(-2px);
        }

        .form-alert-error {
            padding: 13px 16px;
            margin-bottom: 18px;
            border: 1px solid #F3CACA;
            border-radius: 9px;
            color: #A83232;
            background: #FFF2F2;
            font-size: 12px;
        }

        /* RESPONSIVE TABLET DAN HP */
        @media (max-width: 768px) {

            .warga-sidebar {
                width: 260px;
                min-width: 260px;
                transform: translateX(-100%);
                transition: transform .3s ease;
            }

            .warga-sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: block;
                position: fixed;
                inset: 0;
                z-index: 1000;
                background: rgba(0,0,0,.45);
                opacity: 0;
                visibility: hidden;
                transition: .3s ease;
            }

            .sidebar-overlay.show {
                opacity: 1;
                visibility: visible;
            }

            .warga-main {
                width: 100%;
                margin-left: 0;
                padding: 22px 18px 30px;
            }

            .mobile-menu-button {
                display: flex;
            }

            .page-header-row {
                align-items: flex-start;
            }

            .header-actions {
                flex-direction: column;
                align-items: flex-end;
                flex-shrink: 0;
                gap: 8px;
            }

            .warga-heading h1 {
                font-size: 22px;
            }

            .petugas-form-container {
                padding: 21px;
            }
        }

        @media (max-width: 480px) {

            .warga-main {
                padding: 19px 13px 25px;
            }

            .page-header-row {
                gap: 8px;
            }

            .warga-heading h1 {
                font-size: 18px;
            }

            .warga-heading p {
                font-size: 10px;
                line-height: 1.6;
            }

            .header-actions {
                gap: 6px;
            }

            .mobile-menu-button {
                width: 39px;
                height: 39px;
                font-size: 20px;
            }

            .btn-kembali {
                padding: 9px 10px;
                font-size: 9px;
            }

            .tabungan-saldo-card {
                padding: 21px;
            }

            .tabungan-saldo-card h2 {
                font-size: 25px;
            }

            .petugas-form-container {
                padding: 17px 14px;
            }

            .petugas-form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .petugas-form-group.full-width {
                grid-column: auto;
            }

            #informasi_rekening {
                grid-template-columns: 1fr;
            }

            .petugas-form-actions {
                flex-direction: column-reverse;
            }

            .btn-batal-petugas,
            .btn-simpan-petugas {
                width: 100%;
            }
        }

/* FIX: FORMULIR MEMENUHI SELURUH LEBAR KONTEN */

.warga-main {
    width: calc(100% - 250px) !important;
    max-width: none !important;
}

.petugas-form-container {
    display: block !important;
    width: 100% !important;
    max-width: none !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    box-sizing: border-box !important;
}

.tabungan-saldo-card {
    width: 100% !important;
    max-width: none !important;
    box-sizing: border-box !important;
}

@media (max-width: 768px) {
    .warga-main {
        width: 100% !important;
        max-width: none !important;
    }

    .petugas-form-container {
        width: 100% !important;
        max-width: none !important;
    }
}

    </style>
</head>

<body>

<div class="dashboard-warga">

    <!-- SIDEBAR -->
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
                <span>🏠</span>
                <span>Dashboard</span>
            </a>

            <div class="sidebar-section-title">Layanan Warga</div>

            <a href="qr_saya.php" class="sidebar-link">
                <span>▣</span>
                <span>QR Code Saya</span>
            </a>

            <a href="ajukan_setoran.php" class="sidebar-link">
                <span>♻️</span>
                <span>Ajukan Setoran</span>
            </a>

            <a href="riwayat_setoran.php" class="sidebar-link">
                <span>📦</span>
                <span>Riwayat Setoran</span>
            </a>

            <a href="riwayat_tabungan.php" class="sidebar-link">
                <span>💰</span>
                <span>Riwayat Tabungan</span>
            </a>

            <a href="ajukan_penarikan.php" class="sidebar-link active">
                <span>💸</span>
                <span>Penarikan Tabungan</span>
            </a>

            <a href="riwayat_penarikan.php" class="sidebar-link">
                <span>📋</span>
                <span>Riwayat Penarikan</span>
            </a>

            <a href="notifikasi.php" class="sidebar-link">
                <span>🔔</span>
                <span>Notifikasi</span>
            </a>

            <div class="sidebar-section-title">Akun</div>

            <a href="profil.php" class="sidebar-link">
                <span>⚙️</span>
                <span>Profil Saya</span>
            </a>
             <a href="aktivitas.php" class="sidebar-link">
            <span class="sidebar-icon">🕘</span>
            <span>Aktivitas Saya</span>
        </a>
            <a href="../auth/logout.php" class="sidebar-link sidebar-logout">
                <span>🚪</span>
                <span>Logout</span>
            </a>

        </nav>

    </aside>

    <!-- OVERLAY SIDEBAR -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- KONTEN UTAMA -->
    <main class="warga-main">

        <!-- HEADER -->
        <div class="page-header-row">

            <div class="warga-heading">

                <h1>Penarikan Tabungan</h1>

                <p>
                    Ajukan penarikan saldo tabungan Bank Sampah Digital.
                </p>

            </div>

            <div class="header-actions">

                <button
                    type="button"
                    class="mobile-menu-button"
                    id="mobileMenuButton"
                    aria-label="Buka menu"
                    aria-expanded="false"
                >
                    ☰
                </button>

                <a href="dashboard.php" class="btn-kembali">
                    <span>←</span>
                    Kembali ke Dashboard
                </a>

            </div>

        </div>

        <!-- PESAN ERROR -->
        <?php if ($error !== "") : ?>

            <div class="form-alert-error">
                ⚠️ <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>

        <!-- INFORMASI SALDO -->
        <div class="tabungan-saldo-card">

            <span class="saldo-label">💰 Saldo Tersedia</span>

            <h2>
                Rp <?php echo number_format($saldo, 0, ',', '.'); ?>
            </h2>

            <p>Saldo rekening tabungan kamu saat ini.</p>

        </div>

        <!-- FORM PENARIKAN -->
        <div class="petugas-form-container">

            <div class="petugas-form-heading">

                <h2>Formulir Penarikan</h2>

                <p>
                    Lengkapi informasi penarikan tabungan dengan benar.
                </p>

            </div>

            <?php if ($tabungan && $saldo > 0) : ?>

                <form method="POST" action="">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
                    >

                    <div class="petugas-form-grid">

                        <!-- NOMINAL -->
                        <div class="petugas-form-group full-width">

                            <label>
                                Nominal Penarikan <span>*</span>
                            </label>

                            <input
                                type="number"
                                name="jumlah"
                                min="0.01"
                                max="<?php echo htmlspecialchars((string)$saldo); ?>"
                                step="0.01"
                                placeholder="Masukkan nominal penarikan"
                                value="<?php echo htmlspecialchars($jumlah); ?>"
                                required
                            >

                        </div>

                        <!-- METODE -->
                        <div class="petugas-form-group full-width">

                            <label>
                                Metode Penarikan <span>*</span>
                            </label>

                            <select name="metode_penarikan" id="metode_penarikan" required>

                                <option value="tunai"
                                    <?php echo $metode === 'tunai' ? 'selected' : ''; ?>>
                                    Tunai
                                </option>

                                <option value="transfer"
                                    <?php echo $metode === 'transfer' ? 'selected' : ''; ?>>
                                    Transfer Bank
                                </option>

                            </select>

                        </div>

                        <!-- INFORMASI REKENING -->
                        <div
                            id="informasi_rekening"
                            class="petugas-form-group full-width"
                        >

                            <div class="rekening-form-group">

                                <label>
                                    Nomor Rekening <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    name="nomor_rekening"
                                    id="nomor_rekening"
                                    maxlength="50"
                                    placeholder="Masukkan nomor rekening"
                                    value="<?php echo htmlspecialchars($nomor_rekening); ?>"
                                >

                            </div>

                            <div class="rekening-form-group">

                                <label>
                                    Nama Pemilik Rekening <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    name="nama_pemilik_rekening"
                                    id="nama_pemilik_rekening"
                                    maxlength="100"
                                    placeholder="Masukkan nama pemilik rekening"
                                    value="<?php echo htmlspecialchars($nama_pemilik); ?>"
                                >

                            </div>

                        </div>

                        <!-- CATATAN -->
                        <div class="petugas-form-group full-width">

                            <label>Catatan Tambahan</label>

                            <textarea
                                name="catatan"
                                rows="4"
                                maxlength="5000"
                                placeholder="Tambahkan keterangan jika diperlukan (opsional)"
                            ><?php echo htmlspecialchars($catatan); ?></textarea>

                        </div>

                    </div>

                    <!-- INFORMASI -->
                    <div class="penarikan-info">

                        <div class="info-icon">i</div>

                        <div>
                            <strong>Informasi Penarikan</strong>

                            <p>
                                Pengajuan penarikan akan diperiksa oleh admin.
                                Saldo tabungan baru akan berkurang setelah
                                penarikan disetujui oleh admin.
                            </p>
                        </div>

                    </div>

                    <!-- TOMBOL -->
                    <div class="petugas-form-actions">

                        <a href="dashboard.php" class="btn-batal-petugas">
                            Batal
                        </a>

                        <button type="submit" class="btn-simpan-petugas">
                            💸 Ajukan Penarikan
                        </button>

                    </div>

                </form>

            <?php else : ?>

                <div class="form-alert-error">
                    Saldo tabungan kamu masih kosong atau rekening belum tersedia.
                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // =================================
    // HAMBURGER SIDEBAR
    // =================================

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

        overlay.addEventListener('click', function () {

            sidebar.classList.remove('open');
            overlay.classList.remove('show');

            menuButton.innerHTML = '☰';
            menuButton.setAttribute('aria-expanded', 'false');

        });

        sidebar.querySelectorAll('.sidebar-link').forEach(link => {

            link.addEventListener('click', function () {

                sidebar.classList.remove('open');
                overlay.classList.remove('show');

                menuButton.innerHTML = '☰';
                menuButton.setAttribute('aria-expanded', 'false');

            });

        });

    }

    // =================================
    // METODE PENARIKAN
    // =================================

    const metodeSelect = document.getElementById('metode_penarikan');
    const informasiRekening = document.getElementById('informasi_rekening');

    const nomorRekening = document.getElementById('nomor_rekening');
    const namaPemilik = document.getElementById('nama_pemilik_rekening');

    if (metodeSelect && informasiRekening) {

        function tampilkanRekening() {

            const gunakanTransfer = metodeSelect.value === 'transfer';

            informasiRekening.style.display = gunakanTransfer ? 'grid' : 'none';

            nomorRekening.required = gunakanTransfer;
            namaPemilik.required = gunakanTransfer;

        }

        metodeSelect.addEventListener('change', tampilkanRekening);

        tampilkanRekening();

    }

});
</script>

</body>
</html>
