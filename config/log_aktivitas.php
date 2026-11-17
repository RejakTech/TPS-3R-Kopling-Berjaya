
<?php

/**
 * Mencatat aktivitas pengguna yang sedang login.
 *
 * Penggunaan:
 * catatAktivitas($conn, 'Login', 'Admin berhasil login');
 */

function catatAktivitas($conn, $aktivitas, $keterangan = null, $user_id_target = null)
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

   $user_id = $user_id_target !== null
    ? (int) $user_id_target
    : ($_SESSION['user_id'] ?? null);

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO log_aktivitas
         (user_id, aktivitas, keterangan)
         VALUES (?, ?, ?)"
    );

    if (!$stmt) {
        error_log("Gagal menyiapkan log aktivitas: " . mysqli_error($conn));
        return false;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "iss",
        $user_id,
        $aktivitas,
        $keterangan
    );

    $berhasil = mysqli_stmt_execute($stmt);

    if (!$berhasil) {
        error_log("Gagal mencatat aktivitas: " . mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

    return $berhasil;
}
