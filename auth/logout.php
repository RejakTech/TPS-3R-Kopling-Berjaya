<?php
session_start();
require_once '../config/koneksi.php';
require_once '../config/log_aktivitas.php';

if (isset($_SESSION['user_id'])) {
    catatAktivitas(
        $conn,
        'Logout',
        'Pengguna keluar dari sistem.'
    );
}

/*
 * Hapus seluruh data session
 */
$_SESSION = [];

/*
 * Hapus cookie session dari browser
 */
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/*
 * Hancurkan session di server
 */
session_destroy();

/*
 * Kembali ke halaman login
 */
header("Location: login.php");
exit;
?>