
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require '../config/koneksi.php';

// Ambil nama database aktif
// Ambil nama database aktif
$result_db = mysqli_query($conn, "SELECT DATABASE() AS nama_database");

if (!$result_db) {
    die("Gagal mengambil nama database.");
}

$data_db = mysqli_fetch_assoc($result_db);
$nama_database = $data_db['nama_database'] ?? '';

if ($nama_database === '') {
    die("Database tidak ditemukan.");
}

if (!$nama_database) {
    die("Database tidak ditemukan.");
}

// Nama file backup
$nama_file = 'Backup_Bank_Sampah_' . date('Ymd_His') . '.sql';

// Header download
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nama_file . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Awal isi file SQL
echo "-- ========================================\n";
echo "-- BACKUP DATABASE BANK SAMPAH DIGITAL\n";
$nama_database_komentar = str_replace(
    ["\r", "\n"],
    ' ',
    $nama_database
);

echo "-- Database: " . $nama_database_komentar . "\n";
echo "-- Tanggal: " . date('d-m-Y H:i:s') . "\n";
echo "-- ========================================\n\n";

echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

// Ambil seluruh tabel database
$result_tables = mysqli_query($conn, "SHOW TABLES");

if (!$result_tables) {
    die("Gagal mengambil daftar tabel database.");
}

while ($table_row = mysqli_fetch_row($result_tables)) {

    $table = $table_row[0];
    $table_komentar = str_replace(
    ["\r", "\n"],
    ' ',
    $table
);

    // Amankan nama tabel untuk query
    $table_sql = '`' . str_replace('`', '``', $table) . '`';

    echo "\n-- ========================================\n";
    echo "-- TABEL: " . $table_komentar . "\n";
    echo "-- ========================================\n\n";

    // Ambil struktur tabel
   $result_structure = mysqli_query(
    $conn,
    "SHOW CREATE TABLE " . $table_sql
);

if (!$result_structure) {
    die("Gagal mengambil struktur tabel: " . $table);
}

    $structure = mysqli_fetch_assoc($result_structure);

    $create_sql = $structure['Create Table'] ?? '';

    if ($create_sql !== '') {
        echo "DROP TABLE IF EXISTS " . $table_sql . ";\n";
        echo $create_sql . ";\n\n";
    }

    // Ambil seluruh data tabel
    $result_data = mysqli_query(
    $conn,
    "SELECT * FROM " . $table_sql
);

if (!$result_data) {
    die("Gagal mengambil data tabel: " . $table);
}

if (mysqli_num_rows($result_data) === 0) {
    echo "-- Tidak ada data pada tabel ini.\n\n";
    continue;
}

    $fields = mysqli_fetch_fields($result_data);

    while ($row = mysqli_fetch_assoc($result_data)) {

        $kolom = [];
        $nilai = [];

        foreach ($fields as $field) {
            $kolom[] = '`' . str_replace('`', '``', $field->name) . '`';

            $value = $row[$field->name];

            if ($value === null) {
                $nilai[] = 'NULL';
            } else {
                $nilai[] = "'" . mysqli_real_escape_string($conn, $value) . "'";
            }
        }

        $sql_insert = "INSERT INTO " . $table_sql .
            " (" . implode(', ', $kolom) . ") VALUES (" .
            implode(', ', $nilai) . ");\n";

        echo $sql_insert;
    }

    echo "\n";
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n";
echo "\n-- BACKUP SELESAI\n";

exit;
