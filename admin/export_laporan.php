<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require '../config/koneksi.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// ========================================
// VALIDASI FILTER TANGGAL
// ========================================

$tanggal_awal = $_GET['tanggal_awal'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? '';

function tanggalValid($tanggal)
{
    if ($tanggal === '') {
        return true;
    }

    $date = DateTime::createFromFormat('Y-m-d', $tanggal);

    return $date && $date->format('Y-m-d') === $tanggal;
}

if (!tanggalValid($tanggal_awal)) {
    $tanggal_awal = '';
}

if (!tanggalValid($tanggal_akhir)) {
    $tanggal_akhir = '';
}

if (
    $tanggal_awal !== '' &&
    $tanggal_akhir !== '' &&
    $tanggal_awal > $tanggal_akhir
) {
    die("Rentang tanggal tidak valid.");
}

// ========================================
// KONDISI FILTER
// ========================================

$where = " WHERE 1=1";
$params = [];

if ($tanggal_awal !== '') {
    $where .= " AND DATE(s.tanggal_setor) >= ?";
    $params[] = $tanggal_awal;
}

if ($tanggal_akhir !== '') {
    $where .= " AND DATE(s.tanggal_setor) <= ?";
    $params[] = $tanggal_akhir;
}

// ========================================
// FUNGSI QUERY
// ========================================

function queryExport($conn, $sql, $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        die("Gagal menyiapkan query export.");
    }

    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        die("Gagal menjalankan query export.");
    }

    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        mysqli_stmt_close($stmt);
        die("Gagal mengambil data export.");
    }

    mysqli_stmt_close($stmt);

    return $result;
}

// ========================================
// DATA RINGKASAN
// ========================================

$sql_statistik = "
    SELECT
        COUNT(*) AS total_setoran,

        SUM(CASE
            WHEN s.status = 'diterima' THEN 1
            ELSE 0
        END) AS total_diterima,

        SUM(CASE
            WHEN s.status = 'menunggu' THEN 1
            ELSE 0
        END) AS total_menunggu,

        SUM(CASE
            WHEN s.status = 'ditolak' THEN 1
            ELSE 0
        END) AS total_ditolak,

        COALESCE(SUM(
            CASE
                WHEN s.status = 'diterima' THEN s.berat_kg
                ELSE 0
            END
        ), 0) AS total_berat,

        COALESCE(SUM(
            CASE
                WHEN s.status = 'diterima' THEN s.total_harga
                ELSE 0
            END
        ), 0) AS total_nilai,

        COUNT(DISTINCT CASE
            WHEN s.status = 'diterima' THEN s.user_id
            ELSE NULL
        END) AS warga_aktif

    FROM setoran_sampah s
    $where
";

$result_statistik = queryExport(
    $conn,
    $sql_statistik,
    $params
);

$statistik = mysqli_fetch_assoc($result_statistik);

if (!$statistik) {
    $statistik = [
        'total_setoran' => 0,
        'total_diterima' => 0,
        'total_menunggu' => 0,
        'total_ditolak' => 0,
        'total_berat' => 0,
        'total_nilai' => 0,
        'warga_aktif' => 0
    ];
}

// ========================================
// BUAT FILE EXCEL
// ========================================

$spreadsheet = new Spreadsheet();

$warnaHijau = '0F766E';
$warnaHijauTua = '115E59';
$warnaMuda = 'E8F5F1';
$warnaAbu = 'F4F8F6';
$warnaPutih = 'FFFFFF';
$warnaBorder = 'DCE8E5';

$warnaDiterima = 'DCFCE7';
$warnaTeksDiterima = '166534';

$warnaMenunggu = 'FEF3C7';
$warnaTeksMenunggu = '92400E';

$warnaDitolak = 'FEE2E2';
$warnaTeksDitolak = '991B1B';

$formatRupiah = '"Rp" #,##0';
$formatBerat = '#,##0.00" Kg"';

$periode = ($tanggal_awal ?: 'Semua tanggal') .
    ' s/d ' .
    ($tanggal_akhir ?: 'Semua tanggal');

// ========================================
// SHEET 1: RINGKASAN
// ========================================

$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Ringkasan');

$sheet1->setCellValue('A1', 'LAPORAN BANK SAMPAH DIGITAL');
$sheet1->mergeCells('A1:B1');

$sheet1->setCellValue('A2', 'Periode Laporan');
$sheet1->setCellValue('B2', $periode);

$sheet1->setCellValue('A4', 'INDIKATOR');
$sheet1->setCellValue('B4', 'JUMLAH');

$dataRingkasan = [
    ['Total Pengajuan', (int)($statistik['total_setoran'] ?? 0)],
    ['Setoran Diterima', (int)($statistik['total_diterima'] ?? 0)],
    ['Menunggu Verifikasi', (int)($statistik['total_menunggu'] ?? 0)],
    ['Setoran Ditolak', (int)($statistik['total_ditolak'] ?? 0)],
    ['Total Berat Sampah', (float)($statistik['total_berat'] ?? 0)],
    ['Nilai Ekonomi', (float)($statistik['total_nilai'] ?? 0)],
    ['Warga Aktif', (int)($statistik['warga_aktif'] ?? 0)]
];

$baris = 5;

foreach ($dataRingkasan as $data) {
    $sheet1->setCellValue('A' . $baris, $data[0]);
    $sheet1->setCellValue('B' . $baris, $data[1]);
    $baris++;
}

// Format judul
$sheet1->getStyle('A1:B1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 16,
        'color' => ['rgb' => $warnaPutih]
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $warnaHijau]
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);

$sheet1->getRowDimension(1)->setRowHeight(35);

// Periode
$sheet1->getStyle('A2:B2')->applyFromArray([
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $warnaMuda]
    ],
    'font' => [
        'color' => ['rgb' => $warnaHijauTua]
    ]
]);

$sheet1->getStyle('A2')->getFont()->setBold(true);

// Header tabel
$sheet1->getStyle('A4:B4')->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => $warnaPutih]
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $warnaHijauTua]
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);

$sheet1->getRowDimension(4)->setRowHeight(26);

// Isi tabel
$sheet1->getStyle('A5:B11')->applyFromArray([
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $warnaAbu]
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => $warnaBorder]
        ]
    ]
]);

$sheet1->getStyle('A5:A11')->getFont()->setBold(true);

$sheet1->getStyle('B5:B11')->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// Format berat dan Rupiah
$sheet1->getStyle('B9')->getNumberFormat()
    ->setFormatCode($formatBerat);

$sheet1->getStyle('B10')->getNumberFormat()
    ->setFormatCode($formatRupiah);

$sheet1->getColumnDimension('A')->setWidth(32);
$sheet1->getColumnDimension('B')->setWidth(30);

$sheet1->freezePane('A5');

// ========================================
// SHEET 2: REKAP KATEGORI
// ========================================

$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Rekap Kategori');

$sheet2->setCellValue('A1', 'REKAP BERDASARKAN KATEGORI');
$sheet2->mergeCells('A1:E1');

$sheet2->setCellValue('A2', 'Periode Laporan');
$sheet2->mergeCells('B2:E2');
$sheet2->setCellValue('B2', $periode);

$judulKategori = [
    'No',
    'Kategori Sampah',
    'Jumlah Setoran',
    'Total Berat',
    'Nilai Ekonomi'
];

$kolom = ['A', 'B', 'C', 'D', 'E'];

foreach ($judulKategori as $i => $judul) {
    $sheet2->setCellValue($kolom[$i] . '3', $judul);
}

$sql_kategori = "
    SELECT
        k.nama_kategori,
        COUNT(s.id) AS jumlah_setoran,
        COALESCE(SUM(s.berat_kg), 0) AS total_berat,
        COALESCE(SUM(s.total_harga), 0) AS total_nilai

    FROM setoran_sampah s

    LEFT JOIN kategori_sampah k
        ON s.kategori_id = k.id

    $where
    AND s.status = 'diterima'

    GROUP BY s.kategori_id, k.nama_kategori

    ORDER BY total_berat DESC
";

$result_kategori = queryExport(
    $conn,
    $sql_kategori,
    $params
);

$baris = 4;
$no = 1;

$totalKategoriSetoran = 0;
$totalKategoriBerat = 0;
$totalKategoriNilai = 0;

while ($kategori = mysqli_fetch_assoc($result_kategori)) {

    $jumlah = (int)$kategori['jumlah_setoran'];
    $berat = (float)$kategori['total_berat'];
    $nilai = (float)$kategori['total_nilai'];

    $sheet2->setCellValue('A' . $baris, $no++);
    $sheet2->setCellValue('B' . $baris, $kategori['nama_kategori'] ?? '-');
    $sheet2->setCellValue('C' . $baris, $jumlah);
    $sheet2->setCellValue('D' . $baris, $berat);
    $sheet2->setCellValue('E' . $baris, $nilai);

    $totalKategoriSetoran += $jumlah;
    $totalKategoriBerat += $berat;
    $totalKategoriNilai += $nilai;

    $baris++;
}

// Baris total
$totalRowKategori = $baris;

$sheet2->setCellValue('B' . $totalRowKategori, 'TOTAL');
$sheet2->setCellValue('C' . $totalRowKategori, $totalKategoriSetoran);
$sheet2->setCellValue('D' . $totalRowKategori, $totalKategoriBerat);
$sheet2->setCellValue('E' . $totalRowKategori, $totalKategoriNilai);

// Format judul
$sheet2->getStyle('A1:E1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 15,
        'color' => ['rgb' => $warnaPutih]
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $warnaHijau]
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);

$sheet2->getRowDimension(1)->setRowHeight(34);

$sheet2->getStyle('A2:E2')->applyFromArray([
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $warnaMuda]
    ]
]);

$sheet2->getStyle('A2')->getFont()->setBold(true);

// Header
$sheet2->getStyle('A3:E3')->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => $warnaPutih]
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $warnaHijauTua]
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ]
]);

$sheet2->getRowDimension(3)->setRowHeight(30);

// Format isi dan total
$sheet2->getStyle('A3:E' . $totalRowKategori)->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => $warnaBorder]
        ]
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);

if ($totalRowKategori > 4) {
    $sheet2->getStyle('D4:D' . ($totalRowKategori - 1))
        ->getNumberFormat()->setFormatCode($formatBerat);

    $sheet2->getStyle('E4:E' . ($totalRowKategori - 1))
        ->getNumberFormat()->setFormatCode($formatRupiah);
}

$sheet2->getStyle('D' . $totalRowKategori)
    ->getNumberFormat()->setFormatCode($formatBerat);

$sheet2->getStyle('E' . $totalRowKategori)
    ->getNumberFormat()->setFormatCode($formatRupiah);

$sheet2->getStyle('A' . $totalRowKategori . ':E' . $totalRowKategori)
    ->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => $warnaHijauTua]
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => $warnaMuda]
        ],
        'borders' => [
            'top' => [
                'borderStyle' => Border::BORDER_MEDIUM,
                'color' => ['rgb' => $warnaHijau]
            ]
        ]
    ]);

$sheet2->getColumnDimension('A')->setWidth(8);
$sheet2->getColumnDimension('B')->setWidth(25);
$sheet2->getColumnDimension('C')->setWidth(20);
$sheet2->getColumnDimension('D')->setWidth(22);
$sheet2->getColumnDimension('E')->setWidth(25);

$sheet2->freezePane('A4');

if ($totalRowKategori > 4) {
    $sheet2->setAutoFilter('A3:E' . ($totalRowKategori - 1));
} else {
    $sheet2->setAutoFilter('A3:E3');
}

// ========================================
// SHEET 3: RIWAYAT SETORAN
// ========================================

$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Riwayat Setoran');

$sheet3->setCellValue('A1', 'RIWAYAT SELURUH SETORAN');
$sheet3->mergeCells('A1:I1');

$sheet3->setCellValue('A2', 'Periode Laporan');
$sheet3->mergeCells('B2:I2');
$sheet3->setCellValue('B2', $periode);

$judulRiwayat = [
    'No',
    'Nama Warga',
    'Kategori Sampah',
    'Tanggal Setor',
    'Berat (Kg)',
    'Harga per Kg (Rp)',
    'Total Harga (Rp)',
    'Status',
    'Alasan Penolakan'
];

$kolomRiwayat = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

foreach ($judulRiwayat as $i => $judul) {
    $sheet3->setCellValue($kolomRiwayat[$i] . '3', $judul);
}

$sql_riwayat = "
    SELECT
        s.id,
        s.tanggal_setor,
        s.berat_kg,
        s.harga_per_kg,
        s.total_harga,
        s.status,
        s.alasan_penolakan,
        u.nama_lengkap,
        k.nama_kategori

    FROM setoran_sampah s

    LEFT JOIN users u
        ON s.user_id = u.id

    LEFT JOIN kategori_sampah k
        ON s.kategori_id = k.id

    $where

    ORDER BY s.id DESC
";

$result_riwayat = queryExport(
    $conn,
    $sql_riwayat,
    $params
);

$baris = 4;
$no = 1;

$totalRiwayatDiterima = 0;
$totalRiwayatBeratDiterima = 0;
$totalRiwayatNilaiDiterima = 0;

while ($riwayat = mysqli_fetch_assoc($result_riwayat)) {

    $status = strtolower($riwayat['status'] ?? '');

    $berat = (float)$riwayat['berat_kg'];
    $nilai = (float)$riwayat['total_harga'];

    $sheet3->setCellValue('A' . $baris, $no++);
    $sheet3->setCellValueExplicit(
    'B' . $baris,
    (string)($riwayat['nama_lengkap'] ?? '-'),
    DataType::TYPE_STRING
);

$sheet3->setCellValueExplicit(
    'C' . $baris,
    (string)($riwayat['nama_kategori'] ?? '-'),
    DataType::TYPE_STRING
);

$sheet3->setCellValueExplicit(
    'D' . $baris,
    (string)($riwayat['tanggal_setor'] ?? '-'),
    DataType::TYPE_STRING
);

$sheet3->setCellValue(
    'E' . $baris,
    $berat
);

$sheet3->setCellValue(
    'F' . $baris,
    (float)$riwayat['harga_per_kg']
);

$sheet3->setCellValue(
    'G' . $baris,
    $nilai
);

$sheet3->setCellValueExplicit(
    'H' . $baris,
    ucfirst($status),
    DataType::TYPE_STRING
);

$sheet3->setCellValueExplicit(
    'I' . $baris,
    (string)($riwayat['alasan_penolakan'] ?? ''),
    DataType::TYPE_STRING
);

    // Hitung total hanya untuk setoran diterima
    if ($status === 'diterima') {
        $totalRiwayatDiterima++;
        $totalRiwayatBeratDiterima += $berat;
        $totalRiwayatNilaiDiterima += $nilai;
    }

    // Warna status otomatis
    if ($status === 'diterima') {
        $sheet3->getStyle('H' . $baris)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => $warnaTeksDiterima]
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $warnaDiterima]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);
    } elseif ($status === 'menunggu') {
        $sheet3->getStyle('H' . $baris)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => $warnaTeksMenunggu]
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $warnaMenunggu]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);
    } elseif ($status === 'ditolak') {
        $sheet3->getStyle('H' . $baris)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => $warnaTeksDitolak]
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $warnaDitolak]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);
    }

    $baris++;
}

// Baris total
$totalRowRiwayat = $baris;

$sheet3->setCellValue('B' . $totalRowRiwayat, 'TOTAL DITERIMA');
$sheet3->setCellValue('C' . $totalRowRiwayat, $totalRiwayatDiterima);
$sheet3->setCellValue('E' . $totalRowRiwayat, $totalRiwayatBeratDiterima);
$sheet3->setCellValue('G' . $totalRowRiwayat, $totalRiwayatNilaiDiterima);

// Judul
$sheet3->getStyle('A1:I1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 15,
        'color' => ['rgb' => $warnaPutih]
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $warnaHijau]
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);

$sheet3->getRowDimension(1)->setRowHeight(34);

$sheet3->getStyle('A2:I2')->applyFromArray([
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $warnaMuda]
    ]
]);

$sheet3->getStyle('A2')->getFont()->setBold(true);

// Header tabel
$sheet3->getStyle('A3:I3')->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => $warnaPutih]
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $warnaHijauTua]
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ]
]);

$sheet3->getRowDimension(3)->setRowHeight(34);

// Border tabel
$sheet3->getStyle('A3:I' . $totalRowRiwayat)->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => $warnaBorder]
        ]
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);

// Format angka pada data
if ($totalRowRiwayat > 4) {
    $lastDataRow = $totalRowRiwayat - 1;

    $sheet3->getStyle('E4:E' . $lastDataRow)
        ->getNumberFormat()->setFormatCode($formatBerat);

    $sheet3->getStyle('F4:G' . $lastDataRow)
        ->getNumberFormat()->setFormatCode($formatRupiah);
}

// Format angka pada total
$sheet3->getStyle('E' . $totalRowRiwayat)
    ->getNumberFormat()->setFormatCode($formatBerat);

$sheet3->getStyle('G' . $totalRowRiwayat)
    ->getNumberFormat()->setFormatCode($formatRupiah);

// Tampilan baris total
$sheet3->getStyle('A' . $totalRowRiwayat . ':I' . $totalRowRiwayat)
    ->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => $warnaHijauTua]
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => $warnaMuda]
        ],
        'borders' => [
            'top' => [
                'borderStyle' => Border::BORDER_MEDIUM,
                'color' => ['rgb' => $warnaHijau]
            ]
        ]
    ]);

// Lebar kolom
$lebarKolom = [
    'A' => 8,
    'B' => 25,
    'C' => 22,
    'D' => 18,
    'E' => 16,
    'F' => 22,
    'G' => 22,
    'H' => 16,
    'I' => 38
];

foreach ($lebarKolom as $kolom => $lebar) {
    $sheet3->getColumnDimension($kolom)->setWidth($lebar);
}

$sheet3->getStyle('I4:I' . $totalRowRiwayat)
    ->getAlignment()->setWrapText(true);

$sheet3->freezePane('A4');

if ($totalRowRiwayat > 4) {
    $sheet3->setAutoFilter('A3:I' . ($totalRowRiwayat - 1));
} else {
    $sheet3->setAutoFilter('A3:I3');
}

// ========================================
// FORMAT UMUM SEMUA SHEET
// ========================================

foreach ($spreadsheet->getAllSheets() as $sheet) {

    $sheet->getDefaultRowDimension()->setRowHeight(23);

    $sheet->getStyle(
        'A1:' . $sheet->getHighestColumn() . $sheet->getHighestRow()
    )->getAlignment()->setVertical(
        Alignment::VERTICAL_CENTER
    );

    // Menyembunyikan garis grid Excel
    $sheet->setShowGridlines(false);
}

// ========================================
// DOWNLOAD EXCEL
// ========================================

$spreadsheet->setActiveSheetIndex(0);

$namaFile = 'Laporan_Bank_Sampah_' . date('Ymd_His') . '.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $namaFile . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$spreadsheet->disconnectWorksheets();
exit;