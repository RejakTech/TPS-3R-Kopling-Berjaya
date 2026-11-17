
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

// =============================
// NOTIFIKASI
// =============================

$status_notifikasi = $_GET['status'] ?? '';

// =============================
// FILTER DAN PENCARIAN
// =============================

$cari = trim($_GET['cari'] ?? '');
$status_filter = $_GET['filter_status'] ?? '';
$tanggal_filter = $_GET['tanggal'] ?? '';

$status_valid = ['menunggu', 'diterima', 'ditolak'];

if (!in_array($status_filter, $status_valid, true)) {
    $status_filter = '';
}

// Validasi tanggal
// Validasi tanggal
if (
    $tanggal_filter !== '' &&
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_filter)
) {
    $tanggal_filter = '';
}

// =============================
// QUERY SELURUH STATISTIK
// =============================

$query_statistik = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total_setoran,
        SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) AS total_menunggu,
        SUM(CASE WHEN status = 'diterima' THEN 1 ELSE 0 END) AS total_diterima,
        SUM(CASE WHEN status = 'ditolak' THEN 1 ELSE 0 END) AS total_ditolak
     FROM setoran_sampah"
);

$statistik = mysqli_fetch_assoc($query_statistik);


// =============================
// QUERY DATA SETORAN + PAGINATION
// =============================

// Jumlah data per halaman
$batas = 10;

// Halaman aktif
$halaman = isset($_GET['halaman']) ? (int) $_GET['halaman'] : 1;

if ($halaman < 1) {
    $halaman = 1;
}

// =============================
// KONDISI PENCARIAN DAN FILTER
// =============================

$from_where = " FROM setoran_sampah s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN kategori_sampah k ON s.kategori_id = k.id
                WHERE 1=1";

$types = '';
$params = [];

// Pencarian nama warga
if ($cari !== '') {
    $from_where .= " AND u.nama_lengkap LIKE ?";
    $types .= 's';
    $params[] = '%' . $cari . '%';
}

// Filter status
if ($status_filter !== '') {
    $from_where .= " AND s.status = ?";
    $types .= 's';
    $params[] = $status_filter;
}

// Filter tanggal
if ($tanggal_filter !== '') {
    $from_where .= " AND DATE(s.tanggal_setor) = ?";
    $types .= 's';
    $params[] = $tanggal_filter;
}

// =============================
// HITUNG TOTAL DATA SESUAI FILTER
// =============================

$sql_count = "SELECT COUNT(*) AS total " . $from_where;

$stmt_count = mysqli_prepare($conn, $sql_count);

if (!$stmt_count) {
    die("Gagal menghitung data setoran.");
}

if ($types !== '') {
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
}

mysqli_stmt_execute($stmt_count);

$result_count = mysqli_stmt_get_result($stmt_count);
$data_count = mysqli_fetch_assoc($result_count);

$total_data = (int) $data_count['total'];

// =============================
// HITUNG TOTAL HALAMAN
// =============================

$total_halaman = (int) ceil($total_data / $batas);

if ($total_halaman > 0 && $halaman > $total_halaman) {
    $halaman = $total_halaman;
}

$offset = ($halaman - 1) * $batas;

// =============================
// AMBIL DATA SESUAI HALAMAN
// =============================

$sql = "SELECT
            s.id,
            s.tanggal_setor,
            s.berat_kg,
            s.harga_per_kg,
            s.total_harga,
            s.status,
            u.nama_lengkap,
            k.nama_kategori"
        . $from_where .
        " ORDER BY s.id DESC
          LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Gagal menyiapkan query setoran.");
}

$types_data = $types . 'ii';
$params_data = array_merge($params, [$batas, $offset]);

mysqli_stmt_bind_param($stmt, $types_data, ...$params_data);

mysqli_stmt_execute($stmt);

$query_setoran = mysqli_stmt_get_result($stmt);

if (!$query_setoran) {
    die("Gagal mengambil data setoran.");
}
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Setoran - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<div class="admin-dashboard">

    <!-- NAVBAR -->
    <header class="admin-navbar">

        <h2>♻ Bank Sampah Digital</h2>

        <div class="admin-profile">
            Halo, <?php echo htmlspecialchars($_SESSION['nama']); ?>!
            <a href="../auth/logout.php">Logout</a>
        </div>

    </header>

    <main class="admin-content">

        <!-- HEADER HALAMAN -->
        <div class="admin-heading">

            <h1>Data Setoran Sampah</h1>

            <p>
                Kelola dan verifikasi pengajuan setoran sampah dari warga.
            </p>

        </div>

        
<!-- NOTIFIKASI -->

<?php
$status_notifikasi = $_GET['status'] ?? '';
?>

<?php if ($status_notifikasi === 'diterima') : ?>

    <div class="form-alert-success">
        Setoran berhasil diterima dan saldo tabungan warga telah diperbarui.
    </div>

<?php elseif ($status_notifikasi === 'ditolak') : ?>

    <div class="form-alert-success">
        Pengajuan setoran berhasil ditolak.
    </div>

<?php elseif ($status_notifikasi === 'token_gagal') : ?>

    <div class="form-alert-error">
        Sesi keamanan tidak valid. Silakan muat ulang halaman.
    </div>

<?php elseif ($status_notifikasi === 'verifikasi_gagal') : ?>

    <div class="form-alert-error">
        Verifikasi gagal. Setoran mungkin sudah diproses atau terjadi kesalahan.
    </div>

<?php elseif ($status_notifikasi === 'alasan_gagal') : ?>

    <div class="form-alert-error">
        Alasan penolakan wajib diisi dan maksimal 1.000 karakter.
    </div>

<?php endif; ?>


<!-- PANEL PENCARIAN DAN FILTER -->

<div class="setoran-filter-container">

    <div class="setoran-filter-heading">

        <h2>Filter Data Setoran</h2>

        <p>
            Cari dan temukan pengajuan setoran dengan mudah.
        </p>

    </div>

    <form method="GET" action="setoran.php" class="setoran-filter-form">

        <div class="setoran-filter-group">

            <label for="cari">Nama Warga</label>

            <input
                type="text"
                name="cari"
                id="cari"
                placeholder="Cari nama warga..."
                value="<?php echo htmlspecialchars($cari); ?>"
            >

        </div>

        <div class="setoran-filter-group">

            <label for="filter_status">Status Setoran</label>

            <select name="filter_status" id="filter_status">

                <option value="">Semua Status</option>

                <option value="menunggu"
                    <?php echo $status_filter === 'menunggu' ? 'selected' : ''; ?>>
                    Menunggu
                </option>

                <option value="diterima"
                    <?php echo $status_filter === 'diterima' ? 'selected' : ''; ?>>
                    Diterima
                </option>

                <option value="ditolak"
                    <?php echo $status_filter === 'ditolak' ? 'selected' : ''; ?>>
                    Ditolak
                </option>

            </select>

        </div>

        <div class="setoran-filter-group">

            <label for="tanggal">Tanggal Setoran</label>

            <input
                type="date"
                name="tanggal"
                id="tanggal"
                value="<?php echo htmlspecialchars($tanggal_filter); ?>"
            >

        </div>

        <div class="setoran-filter-actions">

            <button type="submit" class="btn-filter-setoran">
                ⌕ Terapkan Filter
            </button>

            <a href="setoran.php" class="btn-reset-filter">
                Reset
            </a>

        </div>

    </form>

</div>



        <!-- STATISTIK -->
        <div class="admin-statistics">

            <div class="admin-stat-card">
                <h3>Total Setoran</h3>
                <p>
                    <?php echo number_format((int)$statistik['total_setoran'], 0, ',', '.'); ?>
                </p>
            </div>

            <div class="admin-stat-card">
                <h3>Menunggu Verifikasi</h3>
                <p>
                    <?php echo number_format((int)$statistik['total_menunggu'], 0, ',', '.'); ?>
                </p>
            </div>

            <div class="admin-stat-card">
                <h3>Setoran Diterima</h3>
                <p>
                    <?php echo number_format((int)$statistik['total_diterima'], 0, ',', '.'); ?>
                </p>
            </div>

            <div class="admin-stat-card">
                <h3>Setoran Ditolak</h3>
                <p>
                    <?php echo number_format((int)$statistik['total_ditolak'], 0, ',', '.'); ?>
                </p>
            </div>

        </div>

        <!-- TABEL SETORAN -->
        <section class="petugas-setoran-section">

            <div class="petugas-section-heading">

                <div>
                    <h2>Seluruh Pengajuan Setoran</h2>
                    <p>
                        Periksa data setoran dan lakukan verifikasi.
                    </p>
                </div>

            </div>

            
<div class="setoran-filter-result">

    Menampilkan
    <strong><?php echo $total_data; ?></strong>

    <?php if ($cari !== '' || $status_filter !== '' || $tanggal_filter !== '') : ?>

        sesuai filter yang dipilih.

    <?php else : ?>

        dari seluruh pengajuan.

    <?php endif; ?>

</div>


            <div class="warga-table-container">

                <table class="warga-table">

                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Warga</th>
                            <th>Kategori Sampah</th>
                            <th>Tanggal Setor</th>
                            <th>Berat</th>
                            <th>Harga/Kg</th>
                            <th>Total Harga</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (mysqli_num_rows($query_setoran) > 0) : ?>

                            <?php $no = $offset + 1; ?>

                            <?php while ($setoran = mysqli_fetch_assoc($query_setoran)) : ?>

                                <tr>

                                    <td><?php echo $no++; ?></td>

                                    <td>
                                        <?php echo htmlspecialchars($setoran['nama_lengkap'] ?? '-'); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($setoran['nama_kategori'] ?? '-'); ?>
                                    </td>

                                    <td>
                                       <?php
                                        $tanggal_setor = $setoran['tanggal_setor'] ?? '';

                                        if (
                                            $tanggal_setor !== '' &&
                                            strtotime($tanggal_setor) !== false
                                        ) {
                                            echo date('d/m/Y', strtotime($tanggal_setor));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo number_format((float)$setoran['berat_kg'], 2, ',', '.'); ?> Kg
                                    </td>

                                    <td>
                                        Rp <?php echo number_format((float)$setoran['harga_per_kg'], 0, ',', '.'); ?>
                                    </td>

                                    <td>
                                        Rp <?php echo number_format((float)$setoran['total_harga'], 0, ',', '.'); ?>
                                    </td>

                                    <td>

                                        <?php if ($setoran['status'] === 'menunggu') : ?>

                                            <span class="petugas-status menunggu">
                                                Menunggu
                                            </span>

                                        <?php elseif ($setoran['status'] === 'diterima') : ?>

                                            <span class="petugas-status diterima">
                                                Diterima
                                            </span>

                                        <?php elseif ($setoran['status'] === 'ditolak') : ?>

                                            <span class="petugas-status ditolak">
                                                Ditolak
                                            </span>

                                        <?php else : ?>

                                            <span class="petugas-status">
                                                <?php echo htmlspecialchars($setoran['status']); ?>
                                            </span>


                                        <?php endif; ?>

                                        

                                    </td>

                                    <td>
                                        <a
                                            href="detail_setoran.php?id=<?php echo (int)$setoran['id']; ?>"
                                            class="btn-edit-petugas"
                                        >
                                            Detail
                                        </a>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else : ?>

                            <tr>
                                <td colspan="9" style="text-align:center;">
                                    Belum ada data setoran sampah.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            
<!-- PAGINATION -->

<?php if ($total_halaman > 1) : ?>

    <div class="setoran-pagination">

        <div class="pagination-info">
            Halaman <?php echo $halaman; ?>
            dari <?php echo $total_halaman; ?>
        </div>

        <div class="pagination-buttons">

            <?php
            $parameter_filter = [
                'cari' => $cari,
                'filter_status' => $status_filter,
                'tanggal' => $tanggal_filter
            ];
            ?>

            <?php if ($halaman > 1) : ?>

                <a
                    href="?<?php echo htmlspecialchars(http_build_query(array_merge($parameter_filter, ['halaman' => $halaman - 1]))); ?>"
                    class="pagination-link"
                >
                    &laquo; Sebelumnya
                </a>

            <?php endif; ?>

            <?php
            $awal = max(1, $halaman - 2);
            $akhir = min($total_halaman, $halaman + 2);
            ?>

            <?php for ($i = $awal; $i <= $akhir; $i++) : ?>

                <a
                    href="?<?php echo htmlspecialchars(http_build_query(array_merge($parameter_filter, ['halaman' => $i]))); ?>"
                    class="pagination-link <?php echo $i === $halaman ? 'active' : ''; ?>"
                >
                    <?php echo $i; ?>
                </a>

            <?php endfor; ?>

            <?php if ($halaman < $total_halaman) : ?>

                <a
                    href="?<?php echo htmlspecialchars(http_build_query(array_merge($parameter_filter, ['halaman' => $halaman + 1]))); ?>"
                    class="pagination-link"
                >
                    Berikutnya &raquo;
                </a>

            <?php endif; ?>

        </div>

    </div>

<?php endif; ?>


        </section>

    </main>

</div>

</body>
</html>
