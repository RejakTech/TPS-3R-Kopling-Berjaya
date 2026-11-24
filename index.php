<?php
require_once __DIR__ . '/config/koneksi.php';

// Mengambil pengumuman yang sudah diterbitkan
$sql_pengumuman = "
    SELECT
        id,
        judul,
        kategori,
        isi,
        COALESCE(
            NULLIF(gambar, ''),
            (
                SELECT nama_file
                FROM pengumuman_gambar
                WHERE pengumuman_id = pengumuman.id
                ORDER BY urutan, id
                LIMIT 1
            )
        ) AS gambar,
        created_at
    FROM pengumuman
    WHERE status = 'terbit'
    ORDER BY created_at DESC
    LIMIT 6
";

$query_pengumuman = mysqli_query($conn, $sql_pengumuman);

if (!$query_pengumuman) {
    die("Gagal mengambil data pengumuman: " . mysqli_error($conn));
}

// Fungsi pengaman output
function escapeOutput($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
// Menghitung jumlah warga
$query_warga = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'warga'
");

$data_warga = mysqli_fetch_assoc($query_warga);
$total_warga = $data_warga['total'] ?? 0;


// Menghitung total berat sampah yang diterima
$query_sampah = mysqli_query($conn, "
    SELECT COALESCE(SUM(berat_kg), 0) AS total
    FROM setoran_sampah
    WHERE status = 'diterima'
");

$data_sampah = mysqli_fetch_assoc($query_sampah);
$total_sampah = $data_sampah['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Sampah Digital</title>

       <link rel="stylesheet" href="assets/css/style.css?v=4">
       <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w0U1o3fYySgU"
        crossorigin="anonymous">
</head>
<body>

 <header class="navbar" id="navbar">
    <a href="index.php" class="logo">
        <img
            src="assets/images/logo-kopling-berjaya.png"
            alt="Logo TPS 3R Kopling Berjaya"
            class="logo-image"
        >
        <span class="logo-text">
            <strong>Bank Sampah TPS 3R Kopling Berjaya</strong>
            <small>TPS 3R Kelurahan Pulau Karomah</small>
        </span>
    </a>

    <!-- TOMBOL HAMBURGER (hanya tampil di HP) -->
    <button class="nav-toggle" id="navToggle" type="button"
            aria-label="Buka menu" aria-expanded="false" aria-controls="navCollapse">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <!-- MENU + TOMBOL LOGIN (dibungkus agar bisa jadi dropdown di HP) -->
    <div class="nav-collapse" id="navCollapse">
        <nav class="nav-menu">
            <a href="#beranda" class="nav-link">Beranda</a>
            <a href="#tentang" class="nav-link">Tentang</a>
            <a href="#layanan" class="nav-link">Layanan</a>
            <a href="#statistik" class="nav-link">Statistik</a>
            <a href="#pengumuman" class="nav-link">Pengumuman</a>
            <a href="#kontak" class="nav-link">Kontak</a>
        </nav>

        <div class="nav-actions">
            <a href="auth/login.php" class="btn-nav-login">Masuk</a>
            <a href="auth/register.php" class="btn-nav-register">Registrasi</a>
        </div>
    </div>
</header>

    
<!-- HERO SECTION -->
<section class="hero" id="beranda">

    <div class="hero-content">
    
        <div class="hero-badge">
            <span>●</span>
            SISTEM BANK SAMPAH DIGITAL
        </div>

        <h1>
            Kelola Sampah
            <span>& Tabungan Warga</span>
            Secara Digital
        </h1>

        <p>
            Platform digital untuk mencatat setoran sampah,
            mengelola tabungan warga, memantau transaksi,
            dan membangun lingkungan yang lebih bersih.
        </p>

        <div class="hero-features">

    <a href="#layanan" class="hero-feature">
        <span class="hero-feature-icon">♻</span>
        <span>Setoran Sampah</span>
    </a>

    <a href="#layanan" class="hero-feature">
        <span class="hero-feature-icon">▦</span>
        <span>Pembayaran QRIS</span>
    </a>

    <a href="#layanan" class="hero-feature">
        <span class="hero-feature-icon">▣</span>
        <span>Tabungan Digital</span>
    </a>

    <a href="#tentang" class="hero-feature">
        <span class="hero-feature-icon">🌱</span>
        <span>Lingkungan Bersih</span>
    </a>

    <a href="#kontak" class="hero-feature">
        <span class="hero-feature-icon">♟</span>
        <span>Komunitas Warga</span>
    </a>

</div>

        <div class="hero-buttons">

         

        </div>


    </div>


    <!-- VISUAL HERO -->
    <div class="hero-visual">

        <div class="hero-main-card">

            <div class="hero-card-header">
                <div>
                    <span class="hero-card-label">
                        BANK SAMPAH DIGITAL
                    </span>

                    <h3>Aktivitas Pengelolaan</h3>
                </div>

                <div class="hero-card-icon">
                    ♻
                </div>
            </div>

            <div class="hero-card-stat">
    <span>Total Sampah</span>
    <strong>
        <?= number_format((float)$total_sampah, 1, ',', '.') ?> Kg
    </strong>
</div>

            <div class="hero-progress">
                <div class="hero-progress-bar"></div>
            </div>

            <div class="hero-card-footer">
                <span>Pengelolaan lebih teratur</span>
                <span>● Aktif</span>
            </div>

        </div>


        <div class="floating-card floating-card-top">
            <span class="floating-icon">♻</span>

            <div>
                <strong>Setoran Sampah</strong>
                <small>Lebih mudah dicatat</small>
            </div>
        </div>


        <div class="floating-card floating-card-bottom">
            <span class="floating-icon money">Rp</span>

            <div>
                <strong>Tabungan Digital</strong>
                <small>Transparan & terpantau</small>
            </div>
        </div>

    </div>

</section>



<!-- TENTANG BANK SAMPAH KOPLING BERJAYA -->
<section class="about-bank" id="tentang">

    <div class="about-bank-container">

        <div class="about-bank-heading">

            <span class="about-bank-label">
                MENGENAL KAMI
            </span>

            <h2>
                Tentang Bank Sampah Kopling Berjaya
            </h2>

            <p>
                TPS 3R Kopling Berjaya Kelurahan Pulau Karomah
                hadir untuk mewujudkan pengelolaan sampah dan
                administrasi warga yang modern, transparan,
                serta terintegrasi melalui sistem digital.
            </p>

        </div>

        <div class="about-bank-cards">

            <!-- VISI -->
            <article class="about-bank-card">

              <div class="about-bank-icon">
    <i class="fa-solid fa-bullseye"></i>
</div>

                <h3>Visi Kami</h3>

                <p>
                    Menjadi pengelola bank sampah yang unggul,
                    mandiri, dan berkelanjutan serta mewujudkan
                    lingkungan Kelurahan Pulau Karomah yang
                    bersih, sehat, dan bernilai.
                </p>

            </article>

            <!-- MISI -->
            <article class="about-bank-card">

                <div class="about-bank-icon">
    <i class="fa-solid fa-seedling"></i>
</div>

                <h3>Misi Kami</h3>

                <p>
                    Meningkatkan kesadaran warga dalam memilah
                    dan mengelola sampah, menerapkan pencatatan
                    digital, serta menyediakan pengelolaan
                    tabungan dan iuran yang transparan.
                </p>

            </article>

            <!-- NILAI -->
            <article class="about-bank-card">

                <div class="about-bank-icon">
    <i class="fa-solid fa-handshake"></i>
</div>

                <h3>Nilai Kami</h3>

                <p>
                    Menjunjung tinggi transparansi, gotong royong,
                    tanggung jawab, inovasi teknologi, dan
                    kepedulian terhadap kelestarian lingkungan.
                </p>

            </article>

        </div>

    </div>

</section>


<!-- LAYANAN KAMI -->
<section class="services services-editorial" id="layanan">

    <div class="services-editorial-container">

        <!-- BAGIAN JUDUL -->
        <div class="services-heading">

            <span class="services-label">LAYANAN KAMI</span>

            <h2>
                Solusi Digital untuk
                Pengelolaan Sampah
            </h2>

            <p>
                Berbagai layanan digital untuk mendukung
                pengelolaan sampah yang lebih mudah,
                transparan, dan terintegrasi.
            </p>

        </div>

        
<!-- ENAM KARTU LAYANAN -->
<div class="services-cards">

    <a href="#layanan-setoran" class="service-card">
        <div class="service-icon">
            <i class="fas fa-recycle"></i>
        </div>
        <h3>Bank Sampah</h3>
        <p>
            Setoran sampah harian dengan pencatatan
            otomatis dan konversi nilai ke tabungan warga.
        </p>
        <span class="service-arrow">→</span>
    </a>

    <a href="#layanan-tabungan" class="service-card">
        <div class="service-icon">
            <i class="fas fa-sack-dollar"></i>
        </div>
        <h3>Tabungan Warga</h3>
        <p>
            Saldo tabungan otomatis bertambah setiap
            setoran sampah dan bisa ditarik kapan saja.
        </p>
        <span class="service-arrow">→</span>
    </a>

    <a href="#layanan-qris" class="service-card">
        <div class="service-icon">
            <i class="fas fa-credit-card"></i>
        </div>
        <h3>Iuran Digital</h3>
        <p>
            Pembayaran iuran melalui transfer atau
            QRIS dengan bukti pembayaran digital.
        </p>
        <span class="service-arrow">→</span>
    </a>

    <a href="#layanan-qris" class="service-card">
        <div class="service-icon">
            <i class="fas fa-qrcode"></i>
        </div>
        <h3>QRIS & Transfer</h3>
        <p>
            Mendukung berbagai metode pembayaran
            digital secara praktis dan mudah.
        </p>
        <span class="service-arrow">→</span>
    </a>

    <a href="#statistik" class="service-card">
        <div class="service-icon">
            <i class="fas fa-chart-column"></i>
        </div>
        <h3>Laporan Realtime</h3>
        <p>
            Dashboard interaktif dengan grafik dan
            laporan yang dapat diekspor.
        </p>
        <span class="service-arrow">→</span>
    </a>

    <a href="#keamanan-data" class="service-card">
        <div class="service-icon">
            <i class="fas fa-lock"></i>
        </div>
        <h3>Keamanan Data</h3>
        <p>
            Sistem login aman dengan perlindungan
            data dan pengelolaan akses pengguna.
        </p>
        <span class="service-arrow">→</span>
    </a>

</div>


    </div>

</section>
<!-- AKHIR LAYANAN KAMI -->


<section class="statistics" id="statistik">

    <div class="statistics-heading">
        <span class="section-label">DATA BANK SAMPAH</span>

        <h2>Statistik Bank Sampah</h2>

        <p>
            Informasi terkini mengenai jumlah warga
            dan total sampah yang telah diterima
            oleh TPS 3R Kopling Berjaya.
        </p>
    </div>

    <div class="statistics-container">

        <div class="stat-card">
            <div class="stat-icon">👥</div>

            <h3>
                <?= number_format($total_warga, 0, ',', '.') ?>
            </h3>

            <p>Jumlah Warga</p>
        </div>

        <div class="stat-card">
            <div class="stat-icon">♻️</div>

            <h3>
                <?= number_format((float)$total_sampah, 1, ',', '.') ?> Kg
            </h3>

            <p>Total Sampah Diterima</p>
        </div>

    </div>

</section>
<!-- PENGUMUMAN -->

<!-- PENGUMUMAN DINAMIS -->
<section class="announcement-section" id="pengumuman">

    <div class="announcement-heading">

        <span class="section-label">
            INFORMASI TERBARU
        </span>

        <h2>
            Pengumuman & Berita
        </h2>

        <p>
            Dapatkan informasi terbaru seputar kegiatan,
            perkembangan, dan informasi TPS 3R Kopling Berjaya.
        </p>

    </div>

    <div class="announcement-container">

        <?php if (mysqli_num_rows($query_pengumuman) > 0): ?>

            <?php while ($pengumuman = mysqli_fetch_assoc($query_pengumuman)): ?>

                <article class="announcement-card">

                    <div class="announcement-visual">

                        <?php if (!empty($pengumuman['gambar'])): ?>

                            <img
                                src="uploads/pengumuman/<?php echo escapeOutput(basename($pengumuman['gambar'])); ?>"
                                alt="<?php echo escapeOutput($pengumuman['judul']); ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <span>
                                <?php
                                $ikon = [
                                    'Informasi' => '📢',
                                    'Edukasi' => '♻️',
                                    'Kegiatan' => '🌱'
                                ];

                                echo $ikon[$pengumuman['kategori']] ?? '📢';
                                ?>
                            </span>

                        <?php endif; ?>

                    </div>

                    <div class="announcement-content">

    <!-- KATEGORI -->
    <span class="announcement-category">
        <?php echo escapeOutput($pengumuman['kategori']); ?>
    </span>

    <!-- JUDUL -->
    <h3>
        <?php echo escapeOutput($pengumuman['judul']); ?>
    </h3>

    <!-- RINGKASAN -->
    <p>
        <?php
        $isi = strip_tags($pengumuman['isi']);

        if (function_exists('mb_strimwidth')) {
            echo escapeOutput(mb_strimwidth($isi, 0, 130, '...'));
        } else {
            echo escapeOutput(substr($isi, 0, 130));
        }
        ?>
    </p>

    <!-- TANGGAL -->
    <small class="announcement-date">
        <i class="fa-regular fa-calendar"></i>
        <?php echo date('d M Y', strtotime($pengumuman['created_at'])); ?>
    </small>

    <!-- BACA SELENGKAPNYA -->
    <a href="detail_pengumuman.php?id=<?php echo (int) $pengumuman['id']; ?>"
       class="announcement-read-more">
        Baca Selengkapnya <span>→</span>
    </a>

</div>

                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="announcement-empty">
                <span>📢</span>
                <h3>Belum Ada Pengumuman</h3>
                <p>
                    Informasi dan kegiatan terbaru akan ditampilkan di sini.
                </p>
            </div>

        <?php endif; ?>

    </div>

</section>
<!-- AKHIR PENGUMUMAN DINAMIS -->





<!-- HUBUNGI KAMI -->
<section class="section contact-section" id="kontak">

    <div class="section-heading">
        <span class="section-label">KONTAK</span>
        <h2>Hubungi Kami</h2>
        <p>
            Punya pertanyaan? Kami siap membantu Anda.
        </p>
    </div>

    <div class="contact-wrapper">

        <!-- KARTU GOOGLE MAPS -->
        <div class="contact-map-card">

            <div class="contact-map-heading">
                <div class="contact-map-icon">🗺️</div>

                <div>
                    <h3>Lokasi TPS 3R Kopling Berjaya</h3>
                    <p>Lihat lokasi kami melalui Google Maps</p>
                </div>
            </div>

            <div class="map-container">

                <iframe
                    src="https://maps.google.com/maps?q=TPS%203R%20koplingberjaya&t=&z=15&ie=UTF8&iwloc=&output=embed"
                    title="Peta lokasi TPS 3R Kopling Berjaya"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen>
                </iframe>

                <a
                    href="https://maps.app.goo.gl/2eCXkQCH5BwGMaWT7"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="map-click-link"
                    aria-label="Buka lokasi di Google Maps">
                </a>

                <div class="map-open-label">
                    Klik peta untuk membuka Google Maps ↗
                </div>

            </div>

        </div>

        <!-- KARTU INFORMASI KONTAK -->
        <div class="contact-info-card">

            <div class="contact-info-icon">📲</div>

            <span class="contact-label">
                KAMI SIAP MEMBANTU
            </span>

            <h3>Ada yang ingin ditanyakan?</h3>

            <p>
                Kami siap membantu memberikan informasi
                seputar pengelolaan sampah dan layanan warga.
            </p>

            <div class="contact-buttons">

                <a
                    href="https://wa.me/62XXXXXXXXXX"
                    class="contact-btn contact-btn-primary"
                    target="_blank"
                    rel="noopener noreferrer">
                    ☎ WhatsApp
                    
                </a>

                <a
                    href="mailto:email@example.com"
                    class="contact-btn contact-btn-secondary">
                    ✉ E-mail
                    
                </a>

            </div>

        </div>

    </div>

</section>
<!-- AKHIR HUBUNGI KAMI -->


<!-- FOOTER -->
<footer class="footer-copyright">
    <div class="footer-copyright-container">
        <p>
            © 2026 Bank Sampah Digital.
            Semua Hak Dilindungi.
        </p>
    </div>
</footer>


<script>
document.addEventListener("DOMContentLoaded", function () {

    const sections = document.querySelectorAll("section[id]");
    const navLinks = document.querySelectorAll(".nav-link");

    function updateActiveMenu() {

        let currentSection = "";

        sections.forEach(section => {

            const sectionTop = section.offsetTop - 120;
            const sectionHeight = section.offsetHeight;

            if (
                window.scrollY >= sectionTop &&
                window.scrollY < sectionTop + sectionHeight
            ) {
                currentSection = section.getAttribute("id");
            }

        });

        navLinks.forEach(link => {

            link.classList.remove("active");

            if (link.getAttribute("href") === "#" + currentSection) {
                link.classList.add("active");
            }

        });
    }

    window.addEventListener("scroll", updateActiveMenu);

    updateActiveMenu();
    // ===== HAMBURGER MENU =====
    const navbar = document.getElementById("navbar");
    const navToggle = document.getElementById("navToggle");

    function closeMenu() {
        navbar.classList.remove("open");
        navToggle.setAttribute("aria-expanded", "false");
        navToggle.setAttribute("aria-label", "Buka menu");
    }

    navToggle.addEventListener("click", function () {
        const isOpen = navbar.classList.toggle("open");
        navToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
        navToggle.setAttribute("aria-label", isOpen ? "Tutup menu" : "Buka menu");
    });

    // Tutup menu saat link diklik
    document.querySelectorAll(".nav-collapse a").forEach(function (link) {
        link.addEventListener("click", closeMenu);
    });

    // Tutup menu jika layar kembali ke ukuran laptop
    window.addEventListener("resize", function () {
        if (window.innerWidth > 1200) closeMenu();
    });
});
</script>

</body>
</html>
