<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
require_once '../config/log_aktivitas.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| PROSES FORM
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = "Token keamanan tidak valid. Silakan coba lagi.";
    } else {

        $aksi = $_POST['aksi'] ?? '';

        /*
        |--------------------------------------------------------------------------
        | TAMBAH / EDIT ADMIN
        |--------------------------------------------------------------------------
        */
        if ($aksi === 'simpan') {

            $id = isset($_POST['id'])
                ? (int) $_POST['id']
                : 0;

            $nama = trim($_POST['nama_lengkap'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $no_hp = trim($_POST['no_hp'] ?? '');
            $password = $_POST['password'] ?? '';
            $status = $_POST['status'] ?? 'aktif';

            if ($nama === '' || strlen($nama) < 3) {
                $error = "Nama lengkap minimal 3 karakter.";
            } elseif ($username === '' || strlen($username) < 4) {
                $error = "Username minimal 4 karakter.";
            } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
                $error = "Username hanya boleh menggunakan huruf, angka, titik, underscore, dan strip.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Format email tidak valid.";
            } elseif (!in_array($status, ['aktif', 'nonaktif'], true)) {
                $error = "Status tidak valid.";
            } elseif ($id === 0 && strlen($password) < 8) {
                $error = "Password admin minimal 8 karakter.";
            }

            /*
             * Cek username / email duplikat
             */
            if ($error === '') {

                if ($id > 0) {

                    $stmt = mysqli_prepare(
                        $conn,
                        "SELECT id
                         FROM users
                         WHERE (username = ? OR email = ?)
                         AND id != ?
                         LIMIT 1"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssi",
                        $username,
                        $email,
                        $id
                    );

                } else {

                    $stmt = mysqli_prepare(
                        $conn,
                        "SELECT id
                         FROM users
                         WHERE username = ? OR email = ?
                         LIMIT 1"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ss",
                        $username,
                        $email
                    );
                }

                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $duplikat = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                if ($duplikat) {
                    $error = "Username atau email sudah digunakan.";
                }
            }

            /*
             * Simpan
             */
            if ($error === '') {

                if ($id > 0) {

                    if ($password !== '') {

                        if (strlen($password) < 8) {
                            $error = "Password baru minimal 8 karakter.";
                        } else {

                            $password_hash = password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                            $stmt = mysqli_prepare(
                                $conn,
                                "UPDATE users
                                 SET nama_lengkap = ?,
                                     username = ?,
                                     email = ?,
                                     no_hp = ?,
                                     password = ?,
                                     status = ?
                                 WHERE id = ?
                                 AND role = 'admin'"
                            );

                            mysqli_stmt_bind_param(
                                $stmt,
                                "ssssssi",
                                $nama,
                                $username,
                                $email,
                                $no_hp,
                                $password_hash,
                                $status,
                                $id
                            );

                            $berhasil = mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                        }

                    } else {

                        $stmt = mysqli_prepare(
                            $conn,
                            "UPDATE users
                             SET nama_lengkap = ?,
                                 username = ?,
                                 email = ?,
                                 no_hp = ?,
                                 status = ?
                             WHERE id = ?
                             AND role = 'admin'"
                        );

                        mysqli_stmt_bind_param(
                            $stmt,
                            "sssssi",
                            $nama,
                            $username,
                            $email,
                            $no_hp,
                            $status,
                            $id
                        );

                        $berhasil = mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }

                    if ($error === '' && !empty($berhasil)) {

                        catatAktivitas(
                            $conn,
                            'Edit Admin',
                            'Mengubah data admin ID #' . $id
                        );

                        $_SESSION['csrf_token'] = bin2hex(
                            random_bytes(32)
                        );

                        header("Location: kelola_admin.php?status=edit");
                        exit;
                    }

                    if ($error === '' && empty($berhasil)) {
                        $error = "Data admin gagal diperbarui.";
                    }

                } else {

                    $password_hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO users
                        (
                            nama_lengkap,
                            username,
                            email,
                            password,
                            no_hp,
                            role,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?, 'admin', ?)"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssssss",
                        $nama,
                        $username,
                        $email,
                        $password_hash,
                        $no_hp,
                        $status
                    );

                    $berhasil = mysqli_stmt_execute($stmt);

                    mysqli_stmt_close($stmt);

                    if ($berhasil) {

                        catatAktivitas(
                            $conn,
                            'Tambah Admin',
                            'Menambahkan akun admin baru: ' . $username
                        );

                        $_SESSION['csrf_token'] = bin2hex(
                            random_bytes(32)
                        );

                        header("Location: kelola_admin.php?status=tambah");
                        exit;

                    } else {
                        $error = "Admin gagal ditambahkan.";
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | UBAH STATUS ADMIN
        |--------------------------------------------------------------------------
        */
        elseif ($aksi === 'ubah_status') {

            $id = filter_input(
                INPUT_POST,
                'id',
                FILTER_VALIDATE_INT
            );

            $status_baru = $_POST['status'] ?? '';

            if (!$id || $id <= 0) {
                $error = "ID admin tidak valid.";
            } elseif (!in_array($status_baru, ['aktif', 'nonaktif'], true)) {
                $error = "Status tidak valid.";
            } elseif ($id === (int) $_SESSION['user_id'] && $status_baru === 'nonaktif') {
                $error = "Anda tidak dapat menonaktifkan akun admin yang sedang digunakan.";
            }

            /*
             * Jangan sampai admin terakhir menjadi nonaktif
             */
            if ($error === '' && $status_baru === 'nonaktif') {

                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT COUNT(*) AS total
                     FROM users
                     WHERE role = 'admin'
                     AND status = 'aktif'
                     AND id != ?"
                );

                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);
                $data = mysqli_fetch_assoc($result);

                mysqli_stmt_close($stmt);

                if ((int) ($data['total'] ?? 0) < 1) {
                    $error = "Admin terakhir tidak dapat dinonaktifkan.";
                }
            }

            if ($error === '') {

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE users
                     SET status = ?
                     WHERE id = ?
                     AND role = 'admin'"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "si",
                    $status_baru,
                    $id
                );

                $berhasil = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                if ($berhasil) {

                    catatAktivitas(
                        $conn,
                        'Ubah Status Admin',
                        'Mengubah status admin ID #' . $id .
                        ' menjadi ' . $status_baru
                    );

                    $_SESSION['csrf_token'] = bin2hex(
                        random_bytes(32)
                    );

                    header("Location: kelola_admin.php?status=status");
                    exit;

                } else {
                    $error = "Status admin gagal diperbarui.";
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| PESAN URL
|--------------------------------------------------------------------------
*/
if (isset($_GET['status'])) {

    if ($_GET['status'] === 'tambah') {
        $success = "Admin berhasil ditambahkan.";
    } elseif ($_GET['status'] === 'edit') {
        $success = "Data admin berhasil diperbarui.";
    } elseif ($_GET['status'] === 'status') {
        $success = "Status admin berhasil diperbarui.";
    }
}

/*
|--------------------------------------------------------------------------
| DATA ADMIN
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
     WHERE role = 'admin'
     ORDER BY id DESC"
);

mysqli_stmt_execute($stmt);
$result_admin = mysqli_stmt_get_result($stmt);

$total_admin = 0;
$total_aktif = 0;
$total_nonaktif = 0;

$admins = [];

while ($row = mysqli_fetch_assoc($result_admin)) {

    $admins[] = $row;

    $total_admin++;

    if ($row['status'] === 'aktif') {
        $total_aktif++;
    } else {
        $total_nonaktif++;
    }
}

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Kelola Admin - Bank Sampah Digital</title>

    <link rel="stylesheet"
          href="../assets/css/style.css">
<style>

/* =========================
   AREA KONTEN
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
    padding: 38px 45px;
    box-sizing: border-box;
}

/* =========================
   HEADING
========================= */
.admin-heading {
    margin-bottom: 28px;
}

.admin-heading h1 {
    margin: 0;
    color: #075B5B;
    font-size: 30px;
    font-weight: 800;
    line-height: 1.3;
}

.admin-heading p {
    margin-top: 8px;
    color: #718995;
    font-size: 14px;
    line-height: 1.6;
}

/* =========================
   ALERT
========================= */
.form-alert {
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
   STATISTIK ADMIN
========================= */

.admin-statistics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    width: 100%;
    margin: 22px 0 28px;
    box-sizing: border-box;
}

.admin-stat-card {
    position: relative;
    min-width: 0;
    padding: 17px 20px;
    background: #ffffff;
    border: 1px solid #E2EDF0;
    border-radius: 13px;
    box-shadow: 0 3px 12px rgba(11, 53, 77, 0.04);
    box-sizing: border-box;
    transition: 0.25s ease;
    overflow: hidden;
}

.admin-stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

/* Warna garis setiap kartu */
.admin-stat-card:nth-child(1)::before {
    background: #159BAE;
}

.admin-stat-card:nth-child(2)::before {
    background: #0D9488;
}

.admin-stat-card:nth-child(3)::before {
    background: #E49A0F;
}

/* Label statistik */
.admin-stat-card span {
    display: block;
    margin-bottom: 7px;
    color: #718995;
    font-size: 12px;
    font-weight: 600;
}

/* Angka statistik */
.admin-stat-card strong {
    display: block;
    font-size: 32px;
    font-weight: 800;
    line-height: 1.2;
}

.admin-stat-card:nth-child(1) strong {
    color: #159BAE;
}

.admin-stat-card:nth-child(2) strong {
    color: #0D9488;
}

.admin-stat-card:nth-child(3) strong {
    color: #E49A0F;
}

.admin-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 7px 16px rgba(11, 53, 77, 0.08);
}

/* Tablet dan HP */
@media (max-width: 600px) {
    .admin-statistics {
        gap: 9px;
    }

    .admin-stat-card {
        padding: 14px 10px 14px 15px;
    }

    .admin-stat-card span {
        font-size: 10px;
    }

    .admin-stat-card strong {
        font-size: 24px;
    }
}

/* =========================
   PANEL
========================= */
.admin-panel {
    width: 100%;
    background: #fff;
    border: 1px solid #e2eeeb;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 25px;
    box-sizing: border-box;
    box-shadow: 0 4px 14px rgba(20, 83, 69, .035);
}

.admin-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 23px;
}


/* =========================
   FORM
========================= */
.admin-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
}

.form-group label {
    color: #526671;
    font-size: 13px;
    font-weight: 650;
}

.form-group input,
.form-group select {
    width: 100%;
    min-height: 44px;
    box-sizing: border-box;
    padding: 11px 13px;
    border: 1px solid #dce8e5;
    border-radius: 9px;
    outline: none;
    font-family: inherit;
    font-size: 13px;
    color: #34443f;
    background: #fff;
    transition: .2s ease;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #159BAE;
    box-shadow: 0 0 0 3px rgba(21, 155, 174, .10);
}

/* =========================
   TOMBOL FORM
========================= */
.form-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 11px;
    margin-top: 25px;
}

.btn-admin {
    min-height: 43px;
    border: 0;
    border-radius: 9px;
    padding: 0 19px;
    cursor: pointer;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    transition: .2s ease;
}

.btn-primary-admin {
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #fff;
}

.btn-primary-admin:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    transform: translateY(-2px);
}

.btn-reset-admin {
    background: #edf3f2;
    color: #49615c;
}

.btn-reset-admin:hover {
    background: #dfeae7;
}

/* =========================
   TABEL ADMIN
========================= */
.admin-table-wrapper {
    width: 100%;
    overflow-x: auto;
    box-sizing: border-box;
}

.admin-table {
    width: 100%;
    min-width: 850px;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
}

.admin-table th {
    padding: 15px 16px;
    background: #EAF3F6;
    color: #0B354D;
    font-size: 12px;
    font-weight: 750;
    text-align: center;
    border-bottom: 1px solid #dce8eb;
    white-space: nowrap;
}

.admin-table th:first-child {
    border-radius: 9px 0 0 0;
}

.admin-table th:last-child {
    border-radius: 0 9px 0 0;
}

.admin-table td {
    padding: 16px;
    color: #526671;
    border-bottom: 1px solid #edf2f0;
    vertical-align: middle;
}

.admin-table tbody tr {
    transition: background .2s ease;
}

.admin-table tbody tr:hover {
    background: #f5faf9;
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

/* Kolom aksi dan status */
.admin-table td:nth-child(4),
.admin-table td:nth-child(5) {
    text-align: center;
    white-space: nowrap;
}

/* =========================
   NAMA ADMIN
========================= */
.admin-name {
    color: #0B354D;
    font-size: 13px;
    font-weight: 750;
}

.admin-username {
    margin-top: 5px;
    color: #8799A1;
    font-size: 12px;
}

.current-admin {
    margin-left: 4px;
    color: #159BAE;
    font-size: 11px;
    font-weight: 700;
}

/* =========================
   STATUS
========================= */
.status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 75px;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 750;
}

.status-aktif {
    background: #E2F5F1;
    color: #087C72;
}

.status-nonaktif {
    background: #FFF0F0;
    color: #B42318;
}

/* =========================
   AKSI
========================= */
.admin-table td a {
    display: inline-block;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    margin-right: 9px;
}

.admin-table td a:first-child {
    color: #526671;
}

.admin-table td a:nth-child(2) {
    color: #117F91;
}

.admin-table td a:hover {
    text-decoration: underline;
}

.action-form {
    display: inline;
}

.btn-status {
    border: 0;
    background: #EAF3F6;
    border-radius: 7px;
    padding: 7px 10px;
    cursor: pointer;
    color: #117F91;
    font-family: inherit;
    font-size: 11px;
    font-weight: 700;
}

.btn-status:hover {
    background: #DCEFF3;
}

/* =========================
   RESPONSIVE
========================= */
@media (max-width: 900px) {
    .admin-content {
        padding: 28px 25px;
    }
}

@media (max-width: 700px) {
    .admin-statistics {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .admin-form-grid {
        grid-template-columns: 1fr;
    }

    .admin-panel {
        padding: 19px;
    }

    .admin-heading h1 {
        font-size: 25px;
    }
}

@media (max-width: 600px) {
    .admin-main {
        margin-left: 0;
    }

    .admin-content {
        padding: 22px 15px;
    }

    .form-actions {
        flex-direction: column-reverse;
        align-items: stretch;
    }

    .btn-admin {
        width: 100%;
    }
}


/* =========================
   HEADING
========================= */

.admin-heading {
    margin-bottom: 25px;
}

.admin-heading h1 {
    margin: 0;

    color:  #0B4650;

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
   ALERT
========================= */

.form-alert {
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
   STATISTIK
========================= */

.admin-statistics {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 20px;

    margin-bottom: 25px;

    width: 100%;

    box-sizing: border-box;
}

.admin-stat-card {
    background: #ffffff;

    border: 1px solid #e7eeeb;

    border-radius: 14px;

    padding: 20px 24px;

    box-shadow:
        0 4px 14px rgba(20, 83, 69, 0.04);

    box-sizing: border-box;
}

.admin-stat-card span {
    display: block;

    margin-bottom: 8px;

    color: #71817c;

    font-size: 13px;

    font-weight: 600;
}

.admin-stat-card strong {
    color: #174c43;

    font-size: 28px;

    font-weight: 700;
}


/* =========================
   PANEL
========================= */

.admin-panel {
    width: 100%;

    background: #ffffff;

    border: 1px solid #e7eeeb;

    border-radius: 14px;

    padding: 24px;

    margin-bottom: 25px;

    box-sizing: border-box;
}

.admin-panel-header {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 20px;
}

.admin-panel-header h2 {
    margin: 0;

    color: #174c43;

    font-size: 19px;

    font-weight: 700;
}


/* =========================
   FORM
========================= */

.admin-form-grid {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 18px;
}

.form-group {
    display: flex;

    flex-direction: column;

    gap: 7px;
}

.form-group label {
    color: #52635e;

    font-size: 12px;

    font-weight: 600;
}

.form-group input,
.form-group select {
    width: 100%;

    box-sizing: border-box;

    padding: 11px 13px;

    border: 1px solid #dce8e5;

    border-radius: 8px;

    outline: none;

    font-family: inherit;

    font-size: 14px;

    color: #34443f;

    background: #ffffff;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #159b83;
}

.form-full {
    grid-column: 1 / -1;
}


/* =========================
   FORM ACTION
========================= */

.form-actions {
    display: flex;

    justify-content: flex-end;

    align-items: center;

    gap: 10px;

    margin-top: 20px;
}

.btn-admin {
    min-height: 42px;

    border: 0;

    border-radius: 8px;

    padding: 0 18px;

    cursor: pointer;

    font-size: 13px;

    font-weight: 600;
}

.btn-primary-admin {
    background: #0f766e;

    color: #ffffff;
}

.btn-primary-admin:hover {
    background: #0d655f;
}

.btn-reset-admin {
    background: #f1f4f3;

    color: #49615c;
}


/* =========================
   TABLE
========================= */

.admin-table-wrapper {
    width: 100%;

    overflow-x: auto;

    box-sizing: border-box;
}

.admin-table {
    width: 100%;

    min-width: 850px;

    border-collapse: collapse;

    font-size: 13px;
}

.admin-table th {
    padding: 14px 16px;

    background: #f7faf9;

    color: #52635e;

    font-size: 12px;

    font-weight: 700;

    text-align: left;

    border-bottom: 1px solid #e7eeeb;

    white-space: nowrap;
}

.admin-table td {
    padding: 14px 16px;

    color: #465650;

    border-bottom: 1px solid #edf2f0;

    vertical-align: middle;
}

.admin-table tbody tr:hover {
    background: #fafcfb;
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}


/* =========================
   ADMIN NAME
========================= */

.admin-name {
    color: #174c43;

    font-size: 13px;

    font-weight: 700;
}

.admin-username {
    margin-top: 4px;

    color: #71817c;

    font-size: 12px;
}


/* =========================
   STATUS
========================= */

.status-badge {
    display: inline-flex;

    align-items: center;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 700;
}

.status-aktif {
    background: #e8f5f1;

    color: #0f766e;
}

.status-nonaktif {
    background: #fcebec;

    color: #b42318;
}


/* =========================
   ACTION
========================= */

.action-form {
    display: inline;
}

.admin-table td a {
    text-decoration: none;

    font-size: 12px;

    font-weight: 700;
}

.admin-table td a:first-child {
    color: #49615c;
}

.admin-table td a:nth-child(2) {
    color: #0f766e;
}

.admin-table td a:hover {
    text-decoration: underline;
}

.btn-status {
    border: 0;

    background: transparent;

    cursor: pointer;

    color: #0f766e;

    font-size: 12px;

    font-weight: 700;

    padding: 5px;
}

.btn-status:hover {
    text-decoration: underline;
}

.current-admin {
    margin-left: 5px;

    color: #0f766e;

    font-size: 11px;

    font-weight: 600;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 900px) {

    .admin-content {
        padding: 30px;
    }

    .admin-form-grid {
        grid-template-columns: 1fr;
    }

    .form-full {
        grid-column: auto;
    }
}

@media (max-width: 600px) {

    .admin-content {
        padding: 24px 18px;
    }

    .admin-statistics {
        grid-template-columns: 1fr;
    }

    .admin-panel {
        padding: 20px;
    }

    .form-actions {
        flex-direction: column-reverse;
        align-items: stretch;
    }

    .btn-admin {
        width: 100%;
    }
}
/* =========================
   TOMBOL KEMBALI
========================= */

.admin-top-action {
    display: flex;
    justify-content: flex-end;
    width: 100%;
    margin-bottom: 22px;
}

.btn-kembali {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 11px 18px;
    border-radius: 9px;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(21, 155, 174, 0.16);
    transition: all 0.25s ease;
}

.btn-kembali:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(21, 155, 174, 0.23);
}

@media (max-width: 600px) {
    .admin-top-action {
        margin-bottom: 17px;
    }

    .btn-kembali {
        padding: 9px 13px;
        font-size: 11px;
    }
}
/* HEADING DAN TOMBOL KEMBALI SEJAJAR */

.admin-heading-row {
    position: sticky !important;
    top: 0 !important;
    z-index: 998 !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 8px !important;
    padding: 5px 0 12px !important;
    margin-bottom: 15px !important;
    background: #f4f9f8 !important;
}

.admin-heading {
    margin-bottom: 0;
}

.btn-kembali {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-shrink: 0;
    padding: 11px 18px;
    border-radius: 9px;
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    transition: .25s ease;
}

.btn-kembali:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    color: #fff;
    transform: translateY(-2px);
}

@media (max-width: 600px) {
    .admin-heading-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .btn-kembali {
        align-self: flex-end;
    }
}
/* FIX: STATISTIK ADMIN 3 KOLOM SEJAJAR */
.admin-statistics {
    display: grid !important;
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    gap: 16px !important;
    width: 100% !important;
    margin: 22px 0 28px !important;
    box-sizing: border-box;
}

.admin-stat-card {
    width: 100% !important;
    min-width: 0 !important;
    padding: 15px 20px !important;
    box-sizing: border-box !important;
}

.admin-stat-card span {
    font-size: 12px;
}

.admin-stat-card strong {
    font-size: 28px;
}

/* Tetap 3 kolom pada layar kecil */
@media (max-width: 600px) {
    .admin-statistics {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 8px !important;
    }

    .admin-stat-card {
        padding: 12px 8px !important;
    }

    .admin-stat-card span {
        font-size: 10px;
    }

    .admin-stat-card strong {
        font-size: 22px;
    }
}
.btn-primary-admin {
    background: linear-gradient(135deg, #0B354D, #159BAE);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(21, 155, 174, 0.16);
}

.btn-primary-admin:hover {
    background: linear-gradient(135deg, #082B40, #117F91);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(21, 155, 174, 0.23);
}

/* =========================
   POLISH DAFTAR ADMINISTRATOR
========================= */

/* Header tabel */
.admin-table th {
    background: #EAF3F6 !important;
    color: #0B354D !important;
    padding: 16px 18px !important;
    font-size: 12px;
    font-weight: 750;
    letter-spacing: .3px;
    border-bottom: 1px solid #DCE8EB;
}

/* Isi tabel */
.admin-table td {
    padding: 19px 18px !important;
    color: #526671;
    font-size: 13px;
    border-bottom: 1px solid #EDF2F0;
}

/* Baris tabel */
.admin-table tbody tr {
    transition: background .2s ease;
}

.admin-table tbody tr:nth-child(even) {
    background: #FAFCFC;
}

.admin-table tbody tr:hover {
    background: #F0F8F8;
}

/* Nama administrator */
.admin-name {
    color: #0B354D !important;
    font-size: 13px;
    font-weight: 750;
}

.admin-username {
    margin-top: 5px;
    color: #8A9BA3;
    font-size: 12px;
}

/* Label akun Anda */
.current-admin {
    color: #159BAE !important;
    font-size: 11px;
    font-weight: 700;
}

/* Badge status */
.status-badge {
    min-width: 78px;
    padding: 7px 13px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 750;
}

.status-aktif {
    background: #E2F5F1 !important;
    color: #087C72 !important;
}

.status-nonaktif {
    background: #FFF0F0 !important;
    color: #B42318 !important;
}

/* Tombol Detail */
.admin-table td a:first-child {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 12px;
    margin-right: 5px;
    border-radius: 7px;
    background: #EEF3F6;
    color: #0B354D !important;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
    transition: .2s ease;
}

.admin-table td a:first-child:hover {
    background: #DCE9EF;
    transform: translateY(-1px);
}

/* Tombol Edit */
.admin-table td a:nth-child(2) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 12px;
    margin-right: 5px;
    border-radius: 7px;
    background: #E2F5F1;
    color: #087C72 !important;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
    transition: .2s ease;
}

.admin-table td a:nth-child(2):hover {
    background: #CDECE4;
    transform: translateY(-1px);
}

/* Tombol ubah status */
.btn-status {
    padding: 7px 11px !important;
    border-radius: 7px;
    background: #FFF4E5 !important;
    color: #B7791F !important;
    font-size: 11px;
    transition: .2s ease;
}

.btn-status:hover {
    background: #FCE8C8 !important;
}

/* Kolom aksi */
.admin-table td:last-child {
    white-space: nowrap;
}

/* Tampilan HP */
@media (max-width: 700px) {
    .admin-table {
        min-width: 850px;
    }

    .admin-table th,
    .admin-table td {
        padding: 13px 12px !important;
    }
}

/* =====================================
   RESPONSIVE SIDEBAR DAN HAMBURGER
   HALAMAN KELOLA ADMIN
===================================== */

/* Hamburger disembunyikan di laptop */
.mobile-menu-button {
    display: none !important;
}

/* Pastikan area konten tidak melebar */
.admin-main {
    box-sizing: border-box;
    min-width: 0;
}

.admin-content {
    box-sizing: border-box;
    min-width: 0;
}

/* Tampilan HP dan tablet */
@media (max-width: 800px) {

    body {
        overflow-x: hidden;
    }

    /* Sidebar menjadi menu geser */
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

    /* Overlay */
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

    /* Konten utama */
    .admin-main {
        margin-left: 0 !important;
        width: 100% !important;
        min-height: 100vh;
    }

    .admin-content {
        width: 100% !important;
        padding: 75px 18px 25px !important;
    }

    /* Judul dan hamburger */
    .admin-heading-row {
        position: sticky !important;
        top: 0 !important;
        z-index: 998 !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 12px !important;
        padding: 10px 0 15px !important;
        margin-bottom: 20px !important;
        background: #f4f9f8 !important;
    }

    .admin-heading {
        width: 100%;
        min-width: 0;
    }

    .admin-title-line {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        width: 100%;
    }

    .admin-title-line h1 {
        font-size: 25px !important;
        margin: 0 !important;
    }

    .admin-heading p {
        font-size: 13px !important;
    }

    /* Tombol hamburger */
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

    /* Tombol kembali */
    .btn-kembali {
        align-self: flex-start !important;
    }

    /* Statistik tetap 3 kolom */
    .admin-statistics {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 8px !important;
    }

    .admin-stat-card {
        padding: 12px 8px !important;
    }

    .admin-stat-card span {
        font-size: 10px !important;
    }

    .admin-stat-card strong {
        font-size: 21px !important;
    }

    /* Form */
    .admin-form-grid {
        grid-template-columns: 1fr !important;
    }

    .admin-panel {
        padding: 17px !important;
    }

    /* Tabel bisa digeser ke samping */
    .admin-table-wrapper {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }

    .admin-table {
        min-width: 850px !important;
    }
}

/* Laptop dan desktop */
@media (min-width: 801px) {
    .mobile-menu-button {
        display: none !important;
    }

    .sidebar-overlay {
        display: none !important;
    }
}
@media (max-width: 800px) {
    .admin-content {
        padding: 20px 18px 25px !important;
        margin-top: 0 !important;
    }

    .admin-heading-row {
        padding-top: 5px !important;
        margin-top: 0 !important;
        margin-bottom: 15px !important;
        gap: 8px !important;
    }
}
/* PERBAIKAN TAMPILAN LAPTOP / DESKTOP */
@media (min-width: 801px) {
    .admin-content {
        padding: 38px 45px !important;
    }

    .admin-heading-row {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 20px !important;
        padding: 0 !important;
        margin-bottom: 28px !important;
        position: static !important;
        background: transparent !important;
    }

    .admin-heading {
        flex: 1 !important;
    }

    .btn-kembali {
        width: auto !important;
        min-width: 220px !important;
        align-self: center !important;
        flex: 0 0 auto !important;
    }

    .mobile-menu-button {
        display: none !important;
    }
}
</style>
</head>

<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="admin-sidebar" id="adminSidebar">

    <div class="sidebar-brand">

        <div class="sidebar-brand-top">

            <div class="sidebar-logo">
                <img src="../assets/images/logo-tps3r.png" alt="Logo TPS 3R">
            </div>

            <div>
                <h2>Bank Sampah TPS 3R Kopling Berjaya</h2>

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
        <a href="pengumuman.php" class="sidebar-link ">
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

 <div class="admin-heading-row">

    <div class="admin-heading">
        <div class="admin-title-line">
            <h1>Kelola Admin</h1>

            <button
                type="button"
                class="mobile-menu-button"
                id="mobileMenuButton"
                aria-label="Buka menu"
            >
                ☰
            </button>
        </div>

        <p>
            Kelola akun administrator yang memiliki akses ke sistem
            Bank Sampah Digital.
        </p>
    </div>

    <a href="dashboard.php" class="btn-kembali">
        <span>←</span>
        Kembali ke Dashboard
    </a>

</div>

    <?php if ($success !== ''): ?>

        <div class="form-alert form-alert-success">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="form-alert form-alert-error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>

    <?php endif; ?>


    <!-- STATISTIK -->

    <div class="admin-statistics">

        <div class="admin-stat-card">
            <span>Total Admin</span>
            <strong><?= $total_admin; ?></strong>
        </div>

        <div class="admin-stat-card">
            <span>Admin Aktif</span>
            <strong><?= $total_aktif; ?></strong>
        </div>

        <div class="admin-stat-card">
            <span>Admin Nonaktif</span>
            <strong><?= $total_nonaktif; ?></strong>
        </div>

    </div>


    <!-- FORM TAMBAH ADMIN -->

    <div class="admin-panel">

        <div class="admin-panel-header">

            <h2>Tambah Admin Baru</h2>

        </div>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>"
            >

            <input
                type="hidden"
                name="aksi"
                value="simpan"
            >

            <div class="admin-form-grid">

                <div class="form-group">

                    <label>Nama Lengkap</label>

                    <input
                        type="text"
                        name="nama_lengkap"
                        required
                        minlength="3"
                        maxlength="100"
                        placeholder="Contoh: Admin Bank Sampah"
                    >

                </div>


                <div class="form-group">

                    <label>Username</label>

                    <input
                        type="text"
                        name="username"
                        required
                        minlength="4"
                        maxlength="50"
                        pattern="[A-Za-z0-9._-]+"
                        placeholder="admin_banksampah"
                    >

                </div>


                <div class="form-group">

                    <label>Email</label>

                    <input
                        type="email"
                        name="email"
                        required
                        maxlength="100"
                        placeholder="admin@example.com"
                    >

                </div>


                <div class="form-group">

                    <label>No. HP</label>

                    <input
                        type="text"
                        name="no_hp"
                        maxlength="20"
                        placeholder="08xxxxxxxxxx"
                    >

                </div>


                <div class="form-group">

                    <label>Password</label>

                    <input
                        type="password"
                        name="password"
                        required
                        minlength="8"
                        maxlength="100"
                        placeholder="Minimal 8 karakter"
                    >

                </div>


                <div class="form-group">

                    <label>Status</label>

                    <select name="status">

                        <option value="aktif">
                            Aktif
                        </option>

                        <option value="nonaktif">
                            Nonaktif
                        </option>

                    </select>

                </div>

            </div>


            <div class="form-actions">

                <button
                    type="reset"
                    class="btn-admin btn-reset-admin"
                >
                    Reset
                </button>

                <button
                    type="submit"
                    class="btn-admin btn-primary-admin"
                >
                    + Tambah Admin
                </button>

            </div>

        </form>

    </div>


    <!-- DAFTAR ADMIN -->

    <div class="admin-panel">

        <div class="admin-panel-header">

            <h2>Daftar Administrator</h2>

        </div>


        <div class="admin-table-wrapper">

            <table class="admin-table">

                <thead>

                    <tr>

                        <th>ADMIN</th>
                        <th>EMAIL</th>
                        <th>NO. HP</th>
                        <th>STATUS</th>
                        <th>AKSI</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (empty($admins)): ?>

                    <tr>

                        <td colspan="5">
                            Belum ada akun admin.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($admins as $admin): ?>

                        <tr>

                            <td>

                                <div class="admin-name">

                                    <?= htmlspecialchars(
                                        $admin['nama_lengkap'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>

                                    <?php if (
                                        (int)$admin['id'] ===
                                        (int)$_SESSION['user_id']
                                    ): ?>

                                        <span class="current-admin">
                                            (Anda)
                                        </span>

                                    <?php endif; ?>

                                </div>

                                <div class="admin-username">

                                    @<?= htmlspecialchars(
                                        $admin['username'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>

                                </div>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $admin['email'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $admin['no_hp'] ?: '-',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </td>


                            <td>

                                <?php if ($admin['status'] === 'aktif'): ?>

                                    <span class="status-badge status-aktif">
                                        Aktif
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge status-nonaktif">
                                        Nonaktif
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <a
                                    href="detail_admin.php?id=<?= (int)$admin['id']; ?>"
                                    style="text-decoration:none; color:#374151; font-weight:600; margin-right:10px;"
                                >
                                    Detail
                                </a>

                                <a
                                    href="edit_admin.php?id=<?= (int)$admin['id']; ?>"
                                    style="text-decoration:none; color:#0f766e; font-weight:600; margin-right:10px;"
                                >
                                    Edit
                                </a>


                                <?php if (
                                    (int)$admin['id'] !==
                                    (int)$_SESSION['user_id']
                                ): ?>

                                    <form
                                        method="POST"
                                        class="action-form"
                                        onsubmit="return confirm('Yakin ingin mengubah status admin ini?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars(
                                                $_SESSION['csrf_token'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="aksi"
                                            value="ubah_status"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$admin['id']; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="<?= $admin['status'] === 'aktif'
                                                ? 'nonaktif'
                                                : 'aktif'; ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn-status"
                                        >

                                            <?= $admin['status'] === 'aktif'
                                                ? 'Nonaktifkan'
                                                : 'Aktifkan'; ?>

                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span style="color:#9ca3af;">
                                        Akun Anda
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

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