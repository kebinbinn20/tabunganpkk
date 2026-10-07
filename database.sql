-- ========================================================
-- Database Dump: TabunganPKK
-- Aplikasi Digital Pengelolaan Tabungan PKK
-- ========================================================

CREATE DATABASE IF NOT EXISTS `tabungan_pkk` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tabungan_pkk`;

-- --------------------------------------------------------
-- 1. Struktur Tabel `users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  -- ID internal yang bertambah otomatis untuk setiap akun.
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  -- Nama lengkap yang ditampilkan pada aplikasi.
  `nama` VARCHAR(150) NOT NULL,
  -- Nama login yang harus unik dan dipakai saat autentikasi.
  `username` VARCHAR(100) NOT NULL UNIQUE,
  -- Email akun; UNIQUE mencegah satu email dipakai oleh dua akun.
  `email` VARCHAR(150) NOT NULL UNIQUE,
  -- Hash kata sandi, bukan kata sandi asli.
  `password` VARCHAR(255) NOT NULL,
  -- Menentukan hak akses dan dashboard: admin, bendahara, atau pengguna.
  `role` ENUM('admin', 'bendahara', 'pengguna') NOT NULL DEFAULT 'pengguna',
  -- Status aktif mengizinkan login; nonaktif memblokir akun.
  `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
  -- Nomor telepon akun; boleh kosong.
  `no_hp` VARCHAR(25) NULL,
  -- Alamat pemilik akun; boleh kosong.
  `alamat` TEXT NULL,
  -- Waktu akun dibuat oleh database.
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- Waktu data akun terakhir diperbarui oleh database.
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. Struktur Tabel `anggota`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `anggota` (
  -- ID internal anggota yang dipakai sebagai relasi transaksi.
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  -- Menghubungkan profil anggota ke akun login; boleh NULL jika belum punya akun.
  `user_id` INT NULL UNIQUE,
  -- Nomor keanggotaan yang terlihat di kartu dan laporan; harus unik.
  `nomor_anggota` VARCHAR(50) NOT NULL UNIQUE,
  -- Nama anggota yang dicatat pada bukti dan laporan.
  `nama` VARCHAR(150) NOT NULL,
  -- Nomor identitas kependudukan; opsional.
  `nik` VARCHAR(30) NULL,
  -- Kontak telepon anggota.
  `nomor_hp` VARCHAR(25) NULL,
  -- Alamat anggota.
  `alamat` TEXT NULL,
  -- Tanggal anggota mulai terdaftar.
  `tanggal_gabung` DATE NOT NULL,
  -- Status keanggotaan aktif atau nonaktif.
  `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
  -- Waktu profil anggota dibuat.
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- Jika akun dihapus, profil anggota tetap ada dengan user_id menjadi NULL.
  CONSTRAINT `fk_anggota_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Struktur Tabel `transaksi`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transaksi` (
  -- ID internal setiap transaksi.
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  -- Nomor bukti transaksi yang ditampilkan dan harus unik.
  `kode_transaksi` VARCHAR(50) NOT NULL UNIQUE,
  -- Anggota pemilik mutasi; terhubung ke tabel anggota.
  `anggota_id` INT NOT NULL,
  -- Arah mutasi saldo: setoran menambah dan penarikan mengurangi.
  `jenis_transaksi` ENUM('setoran', 'penarikan') NOT NULL,
  -- Nilai uang transaksi dengan presisi dua angka desimal.
  `nominal` DECIMAL(15, 2) NOT NULL,
  -- Cara uang diterima atau diserahkan, misalnya tunai atau transfer.
  `metode_pembayaran` VARCHAR(50) DEFAULT 'Tunai',
  -- Lokasi bukti transfer yang diunggah, jika tersedia.
  `bukti_transaksi` VARCHAR(255) NULL,
  -- Tanggal transaksi yang digunakan untuk laporan.
  `tanggal` DATE NOT NULL,
  -- Catatan transaksi, misalnya alasan penarikan.
  `keterangan` TEXT NULL,
  -- Hanya transaksi berhasil yang dihitung ke dalam saldo.
  `status` ENUM('berhasil', 'menunggu', 'dibatalkan') NOT NULL DEFAULT 'berhasil',
  -- Akun petugas pencatat transaksi; boleh NULL bila petugas dihapus.
  `petugas_id` INT NULL,
  -- Waktu pencatatan transaksi.
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- Penghapusan anggota akan menghapus transaksi milik anggota tersebut.
  CONSTRAINT `fk_transaksi_anggota` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  -- Penghapusan petugas tidak menghapus transaksi; identitas petugas menjadi NULL.
  CONSTRAINT `fk_transaksi_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Struktur Tabel `pengajuan_penarikan`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pengajuan_penarikan` (
  -- ID internal permohonan penarikan.
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  -- Anggota yang mengajukan penarikan.
  `anggota_id` INT NOT NULL,
  -- Nilai penarikan yang diminta anggota.
  `nominal` DECIMAL(15, 2) NOT NULL,
  -- Tanggal permohonan dikirim.
  `tanggal_pengajuan` DATE NOT NULL,
  -- Alasan atau kebutuhan dana dari anggota.
  `keterangan` TEXT NULL,
  -- Status alur persetujuan permohonan.
  `status` ENUM('menunggu', 'disetujui', 'ditolak', 'selesai') NOT NULL DEFAULT 'menunggu',
  -- Petugas yang memproses permohonan; NULL sebelum diproses.
  `diproses_oleh` INT NULL,
  -- Waktu bendahara memberikan keputusan.
  `tanggal_proses` DATETIME NULL,
  -- Catatan persetujuan atau alasan penolakan dari petugas.
  `catatan_petugas` TEXT NULL,
  -- Waktu record permohonan dibuat.
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- Permohonan ikut terhapus bila profil anggota dihapus.
  CONSTRAINT `fk_pengajuan_anggota` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  -- Petugas yang dihapus tidak menghilangkan histori permohonan.
  CONSTRAINT `fk_pengajuan_petugas` FOREIGN KEY (`diproses_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Struktur Tabel `notifikasi`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifikasi` (
  -- ID internal notifikasi.
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  -- Akun penerima notifikasi.
  `user_id` INT NOT NULL,
  -- Judul singkat yang ditampilkan pada daftar notifikasi.
  `judul` VARCHAR(150) NOT NULL,
  -- Isi pemberitahuan, misalnya status transaksi atau pengajuan.
  `pesan` TEXT NOT NULL,
  -- Penanda apakah penerima sudah membuka/membaca notifikasi.
  `status_baca` ENUM('belum', 'sudah') NOT NULL DEFAULT 'belum',
  -- Waktu notifikasi dibuat.
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- Notifikasi dihapus otomatis jika akun penerima dihapus.
  CONSTRAINT `fk_notifikasi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. Struktur Tabel `pengaturan`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pengaturan` (
  -- ID record konfigurasi organisasi.
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  -- Nama organisasi yang muncul di dashboard dan dokumen.
  `nama_organisasi` VARCHAR(150) NOT NULL DEFAULT 'PKK RW 05 Kelurahan Harapan Baru',
  -- Nama produk/aplikasi yang ditampilkan pada halaman.
  `nama_aplikasi` VARCHAR(100) NOT NULL DEFAULT 'TabunganPKK',
  -- Batas minimal setoran yang diperiksa oleh form setoran.
  `nominal_minimal_setor` DECIMAL(15, 2) NOT NULL DEFAULT 10000,
  -- Ketentuan penarikan yang ditampilkan kepada anggota.
  `aturan_penarikan` TEXT NULL,
  -- Periode kepengurusan atau tabungan aktif.
  `periode_aktif` VARCHAR(50) NOT NULL DEFAULT '2026 / 2027',
  -- Kontak telepon resmi pengurus.
  `kontak_hp` VARCHAR(30) NOT NULL DEFAULT '081234567890',
  -- Email resmi pengurus.
  `kontak_email` VARCHAR(100) NOT NULL DEFAULT 'pkk@harapanbaru.desa.id',
  -- Alamat kantor/sekretariat yang dicetak pada kuitansi.
  `alamat_kantor` TEXT NULL,
  -- Deskripsi singkat aplikasi/organisasi.
  `deskripsi` TEXT NULL,
  -- Waktu konfigurasi terakhir diperbarui.
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Data Awal / Seeder
-- Password semua akun: password123
-- Hash: $2y$10$wT8vI8e3YV8zK/9jW1f7p.d4eLgZ0c9G0oT2dC2uJ5mQ7pP1e3x12
-- (Dibuat otomatis juga di database.php)
-- --------------------------------------------------------
INSERT INTO `users` (`id`, `nama`, `username`, `email`, `password`, `role`, `status`, `no_hp`, `alamat`) VALUES
(1, 'Administrator PKK', 'admin', 'admin@tabunganpkk.local', '$2y$10$QO90rJzN299l0c3oYj5XqOB0g9Oa6Uo30P54Uo3o9Yj5XqOB0g9Oa', 'admin', 'aktif', '081200000001', 'Sekretariat PKK RW 05'),
(2, 'Ibu Siti Nurhaliza (Bendahara)', 'bendahara', 'bendahara@tabunganpkk.local', '$2y$10$QO90rJzN299l0c3oYj5XqOB0g9Oa6Uo30P54Uo3o9Yj5XqOB0g9Oa', 'bendahara', 'aktif', '081200000002', 'Jl. Melati No. 12, RT 02/05'),
(3, 'Ibu Ani Suryani', 'anggota', 'ani@tabunganpkk.local', '$2y$10$QO90rJzN299l0c3oYj5XqOB0g9Oa6Uo30P54Uo3o9Yj5XqOB0g9Oa', 'pengguna', 'aktif', '081200000003', 'Jl. Mawar No. 05, RT 01/05'),
(4, 'Ibu Dewi Lestari', 'ibu_dewi', 'dewi@tabunganpkk.local', '$2y$10$QO90rJzN299l0c3oYj5XqOB0g9Oa6Uo30P54Uo3o9Yj5XqOB0g9Oa', 'pengguna', 'aktif', '081200000004', 'Jl. Anggrek No. 08, RT 03/05'),
(5, 'Ibu Rina Kartika', 'ibu_rina', 'rina@tabunganpkk.local', '$2y$10$QO90rJzN299l0c3oYj5XqOB0g9Oa6Uo30P54Uo3o9Yj5XqOB0g9Oa', 'pengguna', 'aktif', '081200000005', 'Jl. Cempaka No. 17, RT 04/05')
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `anggota` (`id`, `user_id`, `nomor_anggota`, `nama`, `nik`, `nomor_hp`, `alamat`, `tanggal_gabung`, `status`) VALUES
(1, 3, 'PKK-2026-001', 'Ibu Ani Suryani', '3201015501800001', '081200000003', 'Jl. Mawar No. 05, RT 01/05', '2026-01-10', 'aktif'),
(2, 4, 'PKK-2026-002', 'Ibu Dewi Lestari', '3201015602850002', '081200000004', 'Jl. Anggrek No. 08, RT 03/05', '2026-01-12', 'aktif'),
(3, 5, 'PKK-2026-003', 'Ibu Rina Kartika', '3201015703880003', '081200000005', 'Jl. Cempaka No. 17, RT 04/05', '2026-02-01', 'aktif'),
(4, NULL, 'PKK-2026-004', 'Ibu Endang Rahayu', '3201015804790004', '081200000006', 'Jl. Kenanga No. 21, RT 02/05', '2026-02-15', 'aktif'),
(5, NULL, 'PKK-2026-005', 'Ibu Sri Wahyuni', '3201015905820005', '081200000007', 'Jl. Flamboyan No. 04, RT 05/05', '2026-03-01', 'aktif')
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `transaksi` (`id`, `kode_transaksi`, `anggota_id`, `jenis_transaksi`, `nominal`, `metode_pembayaran`, `tanggal`, `keterangan`, `status`, `petugas_id`) VALUES
(1, 'TRX-20260301-0001', 1, 'setoran', 100000, 'Tunai', '2026-03-01', 'Setoran tabungan bulanan PKK', 'berhasil', 2),
(2, 'TRX-20260315-0002', 1, 'setoran', 150000, 'Transfer', '2026-03-15', 'Setoran tambahan arisan/tabungan', 'berhasil', 2),
(3, 'TRX-20260401-0003', 1, 'setoran', 100000, 'Tunai', '2026-04-01', 'Setoran tabungan bulan April', 'berhasil', 2),
(4, 'WD-20260410-0004', 1, 'penarikan', 50000, 'Tunai', '2026-04-10', 'Penarikan untuk keperluan keluarga', 'berhasil', 2),
(5, 'TRX-20260305-0005', 2, 'setoran', 200000, 'Tunai', '2026-03-05', 'Setoran awal tabungan', 'berhasil', 2),
(6, 'TRX-20260320-0006', 2, 'setoran', 250000, 'Transfer', '2026-03-20', 'Setoran tabungan PKK', 'berhasil', 2),
(7, 'TRX-20260310-0007', 3, 'setoran', 300000, 'Tunai', '2026-03-10', 'Setoran tabungan triwulan', 'berhasil', 2),
(8, 'TRX-20260312-0008', 4, 'setoran', 100000, 'Tunai', '2026-03-12', 'Setoran rutin PKK', 'berhasil', 2),
(9, 'TRX-20260315-0009', 5, 'setoran', 150000, 'Tunai', '2026-03-15', 'Setoran rutin PKK', 'berhasil', 2)
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `pengaturan` (`id`, `nama_organisasi`, `nama_aplikasi`, `nominal_minimal_setor`, `aturan_penarikan`, `periode_aktif`, `kontak_hp`, `kontak_email`, `alamat_kantor`, `deskripsi`) VALUES
(1, 'PKK RW 05 Harapan Jaya', 'TabunganPKK', 10000, 'Penarikan tabungan dapat dilakukan setiap hari kerja dengan konfirmasi bendahara maksimal H-1 atau melalui pengajuan online.', '2026 / 2027', '0812-9876-5432', 'pkk.rw05@harapanjaya.desa.id', 'Balai Warga RW 05, Jl. Harmoni No. 01', 'Aplikasi digital pencatatan dan transparansi tabungan anggota PKK RW 05.')
ON DUPLICATE KEY UPDATE `id`=`id`;
