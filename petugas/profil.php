<?php
session_start();

require_once '../config/koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, nama_lengkap, username, email, no_hp, alamat, role, status
     FROM users
     WHERE id = ? AND role = 'petugas'
     LIMIT 1"
);

if (!$stmt) {
    die("Terjadi kesalahan saat mengambil data profil.");
}

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$petugas = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$petugas) {
    session_unset();
    session_destroy();

    header("Location: ../auth/login.php");
    exit;
}

$nama_lengkap = $petugas['nama_lengkap'] ?? '-';
$username = $petugas['username'] ?? '-';
$email = $petugas['email'] ?? '-';
$no_hp = $petugas['no_hp'] ?? '-';
$alamat = $petugas['alamat'] ?? '-';
$status = $petugas['status'] ?? '-';

$status_label = ucfirst(strtolower($status));

$status_class = ($status === 'aktif')
    ? 'status-aktif'
    : 'status-nonaktif';
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profil Petugas - Bank Sampah Digital</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        .profil-page {
            max-width: 900px;
            margin: 0 auto;
        }

        .profil-header {
            margin-bottom: 24px;
        }

        .profil-header h2 {
            margin: 0 0 7px;
            color: #145c52;
            font-size: 26px;
        }

        .profil-header p {
            margin: 0;
            color: #78908e;
            font-size: 14px;
        }

        .profil-card {
            background: #ffffff;
            border: 1px solid #e3eeec;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 6px 24px rgba(15, 118, 110, 0.05);
        }

        .profil-top {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 28px;
            background: #f0faf8;
            border-bottom: 1px solid #e1efed;
        }

        .profil-avatar {
            width: 64px;
            height: 64px;
            min-width: 64px;
            border-radius: 50%;
            background: #d8f1ed;
            color: #0f766e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            font-weight: 700;
        }

        .profil-top-info h3 {
            margin: 0 0 5px;
            color: #145c52;
            font-size: 20px;
        }

        .profil-top-info p {
            margin: 0;
            color: #708582;
            font-size: 13px;
        }

        .profil-content {
            padding: 28px;
        }

        .profil-section-title {
            margin: 0 0 18px;
            color: #145c52;
            font-size: 16px;
        }

        .profil-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .profil-item {
            padding: 16px;
            background: #f8fbfb;
            border: 1px solid #e7eeee;
            border-radius: 11px;
        }

        .profil-item.full {
            grid-column: 1 / -1;
        }

        .profil-label {
            display: block;
            margin-bottom: 6px;
            color: #8a9997;
            font-size: 12px;
            font-weight: 600;
        }

        .profil-value {
            color: #263b38;
            font-size: 14px;
            font-weight: 600;
            word-break: break-word;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-aktif {
            background: #e8f8f1;
            color: #16704f;
        }

        .status-nonaktif {
            background: #fef0f0;
            color: #a53c3c;
        }

        .profil-actions {
            display: flex;
            gap: 10px;
            margin-top: 26px;
        }

        .btn-profil {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            border-radius: 9px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .btn-kembali {
            background: #f1f4f4;
            color: #40504e;
        }

        .btn-kembali:hover {
            background: #e6ebeb;
        }

        .btn-edit {
            background: #e8f6f3;
            color: #0f766e;
        }

        .btn-edit:hover {
            background: #d9eee9;
        }

        .btn-password {
            background: #0f766e;
            color: #ffffff;
        }

        .btn-password:hover {
            background: #0d655e;
        }

        @media (max-width: 650px) {

            .profil-top {
                padding: 22px;
            }

            .profil-content {
                padding: 22px;
            }

            .profil-grid {
                grid-template-columns: 1fr;
            }

            .profil-item.full {
                grid-column: auto;
            }

            .profil-actions {
                flex-direction: column;
            }

            .btn-profil {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="admin-content">

    <div class="profil-page">

        <div class="profil-header">

            <h2>
                Profil Petugas
            </h2>

            <p>
                Informasi akun petugas yang sedang digunakan.
            </p>

        </div>


        <div class="profil-card">

            <!-- Header Profil -->
            <div class="profil-top">

                <div class="profil-avatar">
                    <?= htmlspecialchars(
                        strtoupper(substr($nama_lengkap, 0, 1)),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </div>

                <div class="profil-top-info">

                    <h3>
                        <?= htmlspecialchars(
                            $nama_lengkap,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </h3>

                    <p>
                        Petugas · @<?= htmlspecialchars(
                            $username,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </p>

                </div>

            </div>


            <!-- Isi Profil -->
            <div class="profil-content">

                <h3 class="profil-section-title">
                    Informasi Akun
                </h3>


                <div class="profil-grid">

                    <div class="profil-item">

                        <span class="profil-label">
                            Nama Lengkap
                        </span>

                        <div class="profil-value">
                            <?= htmlspecialchars(
                                $nama_lengkap,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </div>

                    </div>


                    <div class="profil-item">

                        <span class="profil-label">
                            Username
                        </span>

                        <div class="profil-value">
                            <?= htmlspecialchars(
                                $username,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </div>

                    </div>


                    <div class="profil-item">

                        <span class="profil-label">
                            Email
                        </span>

                        <div class="profil-value">
                            <?= htmlspecialchars(
                                $email,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </div>

                    </div>


                    <div class="profil-item">

                        <span class="profil-label">
                            Nomor HP
                        </span>

                        <div class="profil-value">
                            <?= htmlspecialchars(
                                $no_hp,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </div>

                    </div>


                    <div class="profil-item full">

                        <span class="profil-label">
                            Alamat
                        </span>

                        <div class="profil-value">
                            <?= htmlspecialchars(
                                $alamat,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </div>

                    </div>


                    <div class="profil-item">

                        <span class="profil-label">
                            Role
                        </span>

                        <div class="profil-value">
                            Petugas
                        </div>

                    </div>


                    <div class="profil-item">

                        <span class="profil-label">
                            Status Akun
                        </span>

                        <div class="profil-value">

                            <span class="status-badge <?= $status_class; ?>">
                                <?= htmlspecialchars(
                                    $status_label,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </span>

                        </div>

                    </div>

                </div>


                <!-- Tombol -->
                <div class="profil-actions">

                    <a
                        href="dashboard.php"
                        class="btn-profil btn-kembali"
                    >
                        ← Kembali ke Dashboard
                    </a>

                    <a
                        href="edit_profil.php"
                        class="btn-profil btn-edit"
                    >
                        ✎ Edit Profil
                    </a>

                    <a
                        href="ubah_password.php"
                        class="btn-profil btn-password"
                    >
                        🔐 Ubah Password
                    </a>

                </div>

                </div>

            </div>

        </div>

    </div>

</div>

</body>

</html>