
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warga') {
    header("Location: ../auth/login.php");
    exit;
}

include_once '../config/koneksi.php';

$user_id = (int) $_SESSION['user_id'];

// Ambil data warga yang sedang login
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, nama_lengkap
     FROM users
     WHERE id = ? AND role = 'warga'
     LIMIT 1"
);

if (!$stmt) {
    die("Gagal mengambil data warga.");
}

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$warga = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$warga) {
    die("Data warga tidak ditemukan.");
}

$qrData = "BSW|WARGA|" . $warga['id'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR Saya</title>



<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="" crossorigin="anonymous"></script>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    padding: 35px 15px;
    background: #edf5f5;
    font-family: Arial, sans-serif;
    color: #0B354D;
}

.page-wrapper {
    width: 100%;
    max-width: 650px;
    margin: auto;
    text-align: center;
}

.page-heading {
    margin-bottom: 25px;
}

.page-heading h2 {
    margin: 0 0 8px;
    font-size: 23px;
    color: #0B354D;
}

.page-heading p {
    margin: 0;
    color: #718995;
    font-size: 13px;
}

.card-preview {
    width: 100%;
    max-width: 500px;
    margin: auto;
    padding: 10px;
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 12px 35px rgba(11, 53, 77, .13);
}

#cardCanvas {
    display: block;
    width: 100%;
    height: auto;
    border-radius: 12px;
}

.action-buttons {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 25px;
}

.btn {
    min-height: 46px;
    padding: 0 23px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: .2s ease;
}

.btn-download {
    background: linear-gradient(110deg, #0B354D, #17B8C8);
    color: white;
    box-shadow: 0 5px 13px rgba(11, 53, 77, .16);
}

.btn-download:hover {
    background: linear-gradient(110deg, #082B40, #109EAF);
    transform: translateY(-2px);
}

.btn-download:disabled {
    opacity: .5;
    cursor: not-allowed;
    transform: none;
}

.btn-back {
    background: #ffffff;
    color: #315A69;
    border: 1px solid #d5e5e9;
}

.btn-back:hover {
    background: #e5f2f5;
}

.note {
    margin-top: 18px;
    color: #718995;
    font-size: 11px;
    line-height: 1.6;
}

#qrSource {
    position: absolute;
    left: -9999px;
    top: 0;
}

@media (max-width: 480px) {
    body {
        padding: 25px 12px;
    }

    .page-heading h2 {
        font-size: 19px;
    }

    .action-buttons {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }
}
</style>
</head>

<body>

<div class="page-wrapper">
    <div class="card-preview">
        <canvas id="cardCanvas" width="1000" height="1200"></canvas>
    </div>

    <div class="action-buttons">

        <button
            type="button"
            class="btn btn-download"
            id="downloadButton"
            disabled
        >
             Unduh QR Code
        </button>

        <a href="dashboard.php" class="btn btn-back">
             Kembali
        </a>

    </div>

    <p class="note" id="statusText">
        Sedang membuat kartu keanggotaan...
    </p>

</div>

<div id="qrSource"></div>

<script>
const warga = {
    id: <?= json_encode((int)$warga['id']); ?>,
    nama: <?= json_encode($warga['nama_lengkap']); ?>
};

const qrData = <?= json_encode($qrData); ?>;

const canvas = document.getElementById("cardCanvas");
const ctx = canvas.getContext("2d");

const downloadButton = document.getElementById("downloadButton");
const statusText = document.getElementById("statusText");

function roundedRect(ctx, x, y, width, height, radius) {
    ctx.beginPath();
    ctx.roundRect(x, y, width, height, radius);
    ctx.closePath();
}

function drawCard(qrCanvas) {
    const W = 1000;
    const H = 1200;

    // Background
    ctx.fillStyle = "#FFFFFF";
    ctx.fillRect(0, 0, W, H);

    // Header navy
    const gradient = ctx.createLinearGradient(0, 0, W, 0);
    gradient.addColorStop(0, "#0B354D");
    gradient.addColorStop(1, "#147F91");

    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, W, 270);

    // Garis aksen cyan
    ctx.fillStyle = "#17B8C8";
    ctx.fillRect(0, 265, W, 5);

    // Header text
    ctx.textAlign = "center";

    ctx.font = "bold 30px Arial";
    ctx.fillStyle = "#BCEEF1";
    ctx.fillText("TPS 3R KOPLING BERJAYA", 500, 85);

    ctx.font = "bold 48px Arial";
    ctx.fillStyle = "#FFFFFF";
    ctx.fillText("QR CODE KEANGGOTAAN", 500, 155);

    ctx.font = "23px Arial";
    ctx.fillStyle = "#D8EFF2";
    ctx.fillText("BANK SAMPAH DIGITAL", 500, 205);

    // QR frame
    ctx.fillStyle = "#FFFFFF";
    roundedRect(ctx, 220, 330, 560, 560, 24);
    ctx.fill();

    ctx.strokeStyle = "#DCECEF";
    ctx.lineWidth = 3;
    roundedRect(ctx, 220, 330, 560, 560, 24);
    ctx.stroke();

    // QR code
    ctx.imageSmoothingEnabled = false;
    ctx.drawImage(qrCanvas, 260, 370, 480, 480);
    ctx.imageSmoothingEnabled = true;

    // Nama warga
    ctx.textAlign = "center";

    ctx.font = "bold 38px Arial";
    ctx.fillStyle = "#0B354D";
    ctx.fillText(
        String(warga.nama).toUpperCase(),
        500,
        970,
        850
    );

    // Garis pemisah
    ctx.fillStyle = "#17B8C8";
    ctx.fillRect(390, 1010, 220, 4);

    // Footer
    ctx.font = "22px Arial";
    ctx.fillStyle = "#718995";
    ctx.fillText(
        "KARTU IDENTITAS ANGGOTA",
        500,
        1070
    );

    ctx.font = "bold 22px Arial";
    ctx.fillStyle = "#0B5360";
    ctx.fillText(
        "TPS 3R KOPLING BERJAYA",
        500,
        1115
    );
}

// Membuat QR Code
if (typeof QRCode === "undefined") {

    statusText.textContent =
        "Gagal memuat QR Code. Periksa koneksi internet.";

} else {

    new QRCode(document.getElementById("qrSource"), {
        text: qrData,
        width: 500,
        height: 500,
        colorDark: "#0B354D",
        colorLight: "#FFFFFF",
        correctLevel: QRCode.CorrectLevel.H
    });

    function waitForQR() {

        const qrCanvas = document.querySelector("#qrSource canvas");

        const qrImage = document.querySelector("#qrSource img");

        if (qrCanvas) {

            drawCard(qrCanvas);
            ready();

        } else if (
            qrImage &&
            qrImage.complete &&
            qrImage.naturalWidth > 0
        ) {

            drawCard(qrImage);
            ready();

        } else {

            requestAnimationFrame(waitForQR);

        }
    }

    function ready() {
        downloadButton.disabled = false;

        statusText.textContent =
            "Kartu keanggotaan berhasil dibuat. Silakan unduh PNG.";
    }

    waitForQR();
}

// Download PNG
downloadButton.addEventListener("click", function() {

    if (downloadButton.disabled) return;

    const link = document.createElement("a");

    link.download = "kartu-qr-warga-" + warga.id + ".png";

    link.href = canvas.toDataURL("image/png");

    link.click();
});
</script>

</body>
</html>
