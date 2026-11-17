
<?php

/**
 * Mengirim notifikasi kepada satu pengguna.
 */
function kirimNotifikasi(
    $conn,
    $user_id,
    $judul,
    $pesan,
    $tipe = 'informasi',
    $link = null
) {
    $user_id = (int) $user_id;

    if ($user_id <= 0 || trim($judul) === '' || trim($pesan) === '') {
        return false;
    }

    $sql = "INSERT INTO notifikasi
            (user_id, judul, pesan, tipe, link)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        error_log("Gagal menyiapkan notifikasi: " . mysqli_error($conn));
        return false;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "issss",
        $user_id,
        $judul,
        $pesan,
        $tipe,
        $link
    );

    $berhasil = mysqli_stmt_execute($stmt);

    if (!$berhasil) {
        error_log("Gagal mengirim notifikasi: " . mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

    return $berhasil;
}
