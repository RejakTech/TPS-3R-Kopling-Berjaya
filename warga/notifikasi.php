
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$user_id = (int) $_SESSION['user_id'];

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Tandai satu notifikasi sudah dibaca
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        header("Location: notifikasi.php?status=token_gagal");
        exit;
    }

    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'baca_semua') {

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE notifikasi
             SET dibaca = 1
             WHERE user_id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

    } elseif ($aksi === 'baca_satu') {

        $notifikasi_id = filter_var(
            $_POST['notifikasi_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($notifikasi_id && $notifikasi_id > 0) {
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
        }
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header("Location: notifikasi.php");
    exit;
}

// Buat CSRF token jika belum tersedia
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Hitung notifikasi
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN dibaca = 0 THEN 1 ELSE 0 END) AS belum_dibaca
     FROM notifikasi
     WHERE user_id = ?"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$ringkasan = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
);

mysqli_stmt_close($stmt);

$total_notifikasi = (int) $ringkasan['total'];
$belum_dibaca = (int) ($ringkasan['belum_dibaca'] ?? 0);

// Ambil daftar notifikasi
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, judul, pesan, tipe, link, dibaca, tanggal_dibuat
     FROM notifikasi
     WHERE user_id = ?
     ORDER BY tanggal_dibuat DESC, id DESC"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - Bank Sampah Digital</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .notifikasi-content {
            max-width: 1050px;
            margin: auto;
            padding: 30px;
        }

        .notifikasi-heading {
            margin-bottom: 25px;
        }

        .notifikasi-heading h1 {
            color: #183b35;
            font-size: 26px;
            margin-bottom: 7px;
        }

        .notifikasi-heading p {
            color: #71817d;
            font-size: 14px;
        }

        .notifikasi-summary {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .notifikasi-summary-card {
            background: white;
            padding: 20px;
            border: 1px solid #e5eeea;
            border-radius: 12px;
        }

        .notifikasi-summary-card span {
            display: block;
            color: #71817d;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .notifikasi-summary-card strong {
            color: #145c52;
            font-size: 25px;
        }

        .notifikasi-panel {
            background: white;
            border: 1px solid #e5eeea;
            border-radius: 13px;
            overflow: hidden;
        }

        .notifikasi-panel-heading {
            padding: 20px;
            border-bottom: 1px solid #edf1ef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .notifikasi-panel-heading h3 {
            margin: 0;
            color: #183b35;
            font-size: 16px;
        }

        .notifikasi-baca-semua {
            background: #edf7f3;
            color: #0f766e;
            border: none;
            padding: 10px 13px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
        }

        .notifikasi-item {
            display: flex;
            gap: 15px;
            padding: 20px;
            border-bottom: 1px solid #edf1ef;
            background: white;
        }

        .notifikasi-item.belum-dibaca {
            background: #f5fbf8;
        }

        .notifikasi-icon {
            width: 42px;
            height: 42px;
            min-width: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            background: #e3f5ee;
            font-size: 19px;
        }

        .notifikasi-icon.peringatan {
            background: #fff1e8;
        }

        .notifikasi-icon.informasi {
            background: #eaf1ff;
        }

        .notifikasi-detail {
            flex: 1;
            min-width: 0;
        }

        .notifikasi-title {
            color: #183b35;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 7px;
        }

        .notifikasi-pesan {
            color: #647570;
            font-size: 13px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .notifikasi-waktu {
            color: #98a49f;
            font-size: 11px;
            margin-top: 9px;
        }

        .notifikasi-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .notifikasi-link {
            color: #0f766e;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
        }

        .notifikasi-baca {
            background: none;
            border: none;
            padding: 0;
            color: #71817d;
            font-size: 12px;
            cursor: pointer;
        }

        .notifikasi-dot {
            width: 8px;
            height: 8px;
            min-width: 8px;
            border-radius: 50%;
            background: #159b83;
            margin-top: 7px;
        }

        .notifikasi-empty {
            padding: 55px 20px;
            text-align: center;
            color: #899691;
        }

        .notifikasi-empty div {
            font-size: 35px;
            margin-bottom: 12px;
        }

        @media (max-width: 600px) {
            .notifikasi-content {
                padding: 20px 12px;
            }

            .notifikasi-panel-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .notifikasi-item {
                padding: 16px 12px;
            }
        }
    </style>
</head>

<body>

<div class="notifikasi-content">

    <div class="notifikasi-heading">
        <h1>Notifikasi</h1>
        <p>Informasi terbaru mengenai aktivitas dan transaksi tabungan Anda.</p>
    </div>

    <div class="notifikasi-summary">
        <div class="notifikasi-summary-card">
            <span>Total Notifikasi</span>
            <strong><?= number_format($total_notifikasi, 0, ',', '.') ?></strong>
        </div>

        <div class="notifikasi-summary-card">
            <span>Belum Dibaca</span>
            <strong><?= number_format($belum_dibaca, 0, ',', '.') ?></strong>
        </div>
    </div>

    <div class="notifikasi-panel">

        <div class="notifikasi-panel-heading">
            <h3>Semua Pemberitahuan</h3>

            <?php if ($belum_dibaca > 0): ?>
                <form method="POST">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >
                    <input type="hidden" name="aksi" value="baca_semua">

                    <button class="notifikasi-baca-semua" type="submit">
                        Tandai Semua Dibaca
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (mysqli_num_rows($result) > 0): ?>

            <?php while ($row = mysqli_fetch_assoc($result)): ?>

                <?php
                $ikon = '🔔';

                if ($row['tipe'] === 'sukses') {
                    $ikon = '✓';
                } elseif ($row['tipe'] === 'peringatan') {
                    $ikon = '!';
                } elseif ($row['tipe'] === 'informasi') {
                    $ikon = 'ℹ';
                }
                ?>

                <div class="notifikasi-item <?= $row['dibaca'] == 0 ? 'belum-dibaca' : '' ?>">

                    <div class="notifikasi-icon <?= e($row['tipe']) ?>">
                        <?= $ikon ?>
                    </div>

                    <div class="notifikasi-detail">

                        <div class="notifikasi-title">
                            <?= e($row['judul']) ?>
                        </div>

                        <div class="notifikasi-pesan">
                            <?= nl2br(e($row['pesan'])) ?>
                        </div>

                        <div class="notifikasi-waktu">
                            <?= date('d M Y, H:i', strtotime($row['tanggal_dibuat'])) ?>
                        </div>

                        <div class="notifikasi-actions">

                            <?php if (!empty($row['link'])): ?>
                                <a
                                    class="notifikasi-link"
                                    href="buka_notifikasi.php?id=<?= (int) $row['id'] ?>&link=<?= urlencode($row['link']) ?>"
                                >
                                    Lihat Detail →
                                </a>
                            <?php endif; ?>

                            <?php if ($row['dibaca'] == 0): ?>
                                <form method="POST">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION['csrf_token']) ?>"
                                    >
                                    <input type="hidden" name="aksi" value="baca_satu">
                                    <input
                                        type="hidden"
                                        name="notifikasi_id"
                                        value="<?= (int) $row['id'] ?>"
                                    >

                                    <button class="notifikasi-baca" type="submit">
                                        Tandai sudah dibaca
                                    </button>
                                </form>
                            <?php endif; ?>

                        </div>
                    </div>

                    <?php if ($row['dibaca'] == 0): ?>
                        <div class="notifikasi-dot"></div>
                    <?php endif; ?>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="notifikasi-empty">
                <div>🔔</div>
                <strong>Belum Ada Notifikasi</strong>
                <p>Pemberitahuan baru akan muncul di sini.</p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>

<?php
mysqli_stmt_close($stmt);
?>
