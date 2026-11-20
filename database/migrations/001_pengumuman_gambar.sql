CREATE TABLE IF NOT EXISTS pengumuman_gambar (
    id INT NOT NULL AUTO_INCREMENT,
    pengumuman_id INT NOT NULL,
    nama_file VARCHAR(255) NOT NULL,
    urutan INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_pengumuman_gambar_pengumuman_urutan (pengumuman_id, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
