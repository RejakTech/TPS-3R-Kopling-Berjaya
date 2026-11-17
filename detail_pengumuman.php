
<?php
require_once __DIR__ . '/config/koneksi.php';

function escapeOutput($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Validasi ID pengumuman
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(404);
    exit('Pengumuman tidak ditemukan.');
}

// Ambil detail pengumuman yang sudah diterbitkan
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, judul, kategori, isi, gambar, created_at
     FROM pengumuman
     WHERE id = ? AND status = 'terbit'
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$data) {
    http_response_code(404);
    exit('Pengumuman tidak ditemukan atau belum diterbitkan.');
}

$ikon = [
    'Informasi' => '📢',
    'Edukasi' => '♻️',
    'Kegiatan' => '🌱'
];

$gambar = !empty($data['gambar'])
    ? 'uploads/pengumuman/' . basename($data['gambar'])
    : '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo escapeOutput($data['judul']); ?> - Bank Sampah Digital</title>

    <link rel="stylesheet" href="assets/css/style.css?v=3">

    <style>
        .detail-pengumuman {
            max-width: 900px;
            margin: 150px auto 80px;
            padding: 0 20px;
        }

        .detail-card {
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 35px rgba(10, 50, 70, .08);
        }

        .detail-image {
            width: 100%;
            max-height: 480px;
            object-fit: cover;
            display: block;
        }

        .detail-image-placeholder {
            min-height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 75px;
            background: #eaf5fc;
        }

        .detail-content {
            padding: 35px 40px 45px;
        }

        .detail-category {
            display: inline-block;
            background: #e5f5f8;
            color: #07869b;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .detail-content h1 {
            color: #0b354d;
            font-size: 32px;
            line-height: 1.4;
            margin: 20px 0 12px;
        }

        .detail-date {
            color: #8195a5;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .detail-body {
            color: #526b7d;
            font-size: 16px;
            line-height: 1.9;
            overflow-wrap: anywhere;
        }

        .detail-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 25px;
            color: #087f9c;
            text-decoration: none;
            font-weight: 700;
        }

        .detail-back:hover {
            color: #0b354d;
        }

        @media (max-width: 600px) {
            .detail-pengumuman {
                margin-top: 110px;
            }

            .detail-content {
                padding: 25px 22px 30px;
            }

            .detail-content h1 {
                font-size: 24px;
            }
        }
        /* BACKGROUND HALAMAN DETAIL PENGUMUMAN */
body {
    min-height: 100vh;
    background:
        radial-gradient(
            circle at 10% 15%,
            rgba(84, 196, 216, 0.18),
            transparent 30%
        ),
        radial-gradient(
            circle at 90% 85%,
            rgba(22, 134, 176, 0.13),
            transparent 32%
        ),
        linear-gradient(
            135deg,
            #EAF5FC 0%,
            #F3F8FB 50%,
            #DCECF7 100%
        );

    background-attachment: fixed;
}

/* TOMBOL KEMBALI */
.back-link {
    color: #087F9C;
    font-weight: 700;
    transition: 0.3s;
}

.back-link:hover {
    color: #071F33;
    transform: translateX(-4px);
}

/* GAMBAR PENGUMUMAN */
.detail-image {
    border-radius: 22px;
    box-shadow: 0 15px 40px rgba(7, 31, 51, 0.12);
}
    </style>
</head>

<body>

<div class="detail-pengumuman">

    <a href="index.php#pengumuman" class="detail-back">
        ← Kembali ke Pengumuman
    </a>

    <article class="detail-card">

        <?php if ($gambar): ?>

            <img
                src="<?php echo escapeOutput($gambar); ?>"
                alt="<?php echo escapeOutput($data['judul']); ?>"
                class="detail-image"
            >

        <?php else: ?>

            <div class="detail-image-placeholder">
                <?php echo $ikon[$data['kategori']] ?? '📢'; ?>
            </div>

        <?php endif; ?>

        <div class="detail-content">

            <span class="detail-category">
                <?php echo escapeOutput($data['kategori']); ?>
            </span>

            <h1>
                <?php echo escapeOutput($data['judul']); ?>
            </h1>

            <div class="detail-date">
                📅
                <?php echo date('d F Y', strtotime($data['created_at'])); ?>
            </div>

            <div class="detail-body">
                <?php
                echo nl2br(escapeOutput($data['isi']));
                ?>
            </div>

        </div>

    </article>

</div>

</body>
</html>
