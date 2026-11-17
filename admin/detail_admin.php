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

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Detail Admin - Bank Sampah Digital</title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

    <style>

        .admin-content {
            padding: 30px;
            max-width: 1100px;
            margin: auto;
        }

        .admin-heading {
            margin-bottom: 25px;
        }

        .admin-heading h1 {
            margin: 0 0 8px;
            font-size: 28px;
            color: #172033;
        }

        .admin-heading p {
            margin: 0;
            color: #6b7280;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .detail-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 24px;
        }

        .detail-card h2 {
            margin: 0 0 20px;
            font-size: 18px;
            color: #172033;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .profile-avatar {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #e6f4f1;
            color: #0f766e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
        }

        .profile-name {
            font-size: 18px;
            font-weight: 700;
            color: #172033;
        }

        .profile-role {
            color: #6b7280;
            font-size: 13px;
            margin-top: 4px;
        }

        .detail-item {
            padding: 13px 0;
            border-bottom: 1px solid #f0f1f2;
        }

        .detail-item:last-child {
            border-bottom: 0;
        }

        .detail-label {
            display: block;
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .detail-value {
            color: #172033;
            font-size: 14px;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-aktif {
            background: #ecfdf5;
            color: #047857;
        }

        .status-nonaktif {
            background: #f3f4f6;
            color: #6b7280;
        }

        .activity-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 24px;
        }

        .activity-card h2 {
            margin: 0 0 20px;
            font-size: 18px;
            color: #172033;
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #f0f1f2;
        }

        .activity-item:last-child {
            border-bottom: 0;
        }

        .activity-title {
            font-weight: 700;
            color: #172033;
            font-size: 14px;
        }

        .activity-description {
            color: #6b7280;
            font-size: 13px;
            margin-top: 4px;
        }

        .activity-date {
            color: #9ca3af;
            font-size: 12px;
            margin-top: 6px;
        }

        .empty-state {
            padding: 20px 0;
            color: #6b7280;
            font-size: 14px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn {
            display: inline-block;
            padding: 11px 18px;
            border-radius: 9px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .btn-back {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-edit {
            background: #0f766e;
            color: #ffffff;
        }

        .btn-edit:hover {
            background: #115e59;
        }

        @media (max-width: 800px) {

            .admin-content {
                padding: 20px 15px;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                text-align: center;
            }
        }

    </style>

</head>

<body>

<div class="admin-content">

    <div class="admin-heading">

        <h1>Detail Admin</h1>

        <p>
            Informasi akun administrator dan aktivitas terakhir.
        </p>

    </div>


    <div class="detail-grid">

        <!-- PROFIL -->

        <div class="detail-card">

            <div class="profile-header">

                <div class="profile-avatar">

                    <?= strtoupper(
                        substr(
                            $admin['nama_lengkap'],
                            0,
                            1
                        )
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
                        $admin['email'],
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
                        (int)$admin['id'] ===
                        (int)$_SESSION['user_id']
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

        <h2>
            Riwayat Aktivitas
        </h2>


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


                    <?php if (
                        !empty($item['keterangan'])
                    ): ?>

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


    <!-- ACTION -->

    <div class="action-buttons">

        <a
            href="kelola_admin.php"
            class="btn btn-back"
        >
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

</body>

</html>