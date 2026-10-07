<?php
/**
 * =========================================================================================
 * APLIKASI TABUNGAN DIGITAL PKK (TabunganPKK)
 * File: config/database.php
 * -----------------------------------------------------------------------------------------
 * PENJELASAN MATERI UNTUK PRESENTASI KE GURU:
 * 1. Apa itu PDO (PHP Data Objects)?
 *    PDO adalah ekstensi resmi PHP yang berfungsi sebagai penghubung (driver) antara aplikasi
 *    PHP dan sistem database (MySQL). Dibandingkan ekstensi lama (mysqli), PDO memiliki
 *    keunggulan utama:
 *    - Prepared Statements Asli: Mencegah celah keamanan SQL Injection 100%.
 *    - Exception Handling: Menangani error secara profesional dengan blok try-catch.
 *    - Portabilitas: Fleksibel jika suatu saat aplikasi dipindahkan ke database lain (PostgreSQL, SQLite).
 *
 * 2. Mengapa Menggunakan Pola Singleton pada Koneksi?
 *    Pada fungsi getDBConnection(), variabel static $pdo digunakan agar aplikasi hanya membuat
 *    1 (satu) koneksi aktif selama satu request berjalan. Hal ini menghemat penggunaan memori RAM
 *    server dan mempercepat loading aplikasi.
 *
 * 3. Fitur Auto-Setup Database (Self-Healing Migration):
 *    Aplikasi ini cerdas; jika database 'tabungan_pkk' atau tabel-tabelnya belum ada di MySQL
 *    XAMPP, sistem akan secara otomatis membuatkannya (CREATE DATABASE & CREATE TABLE IF NOT EXISTS)
 *    beserta data demo akun awal yang di-hash dengan algoritma BCRYPT.
 * =========================================================================================
 */

// Konstanta Konfigurasi Koneksi MySQL XAMPP
define('DB_HOST', 'localhost');  // Alamat server database lokal
define('DB_USER', 'root');       // Username default MySQL XAMPP
define('DB_PASS', '');           // Password default MySQL XAMPP (kosong)
define('DB_NAME', 'tabungan_pkk'); // Nama database aplikasi
define('DB_PORT', '3306');       // Port default MySQL

/**
 * Fungsi Mendapatkan Objek Koneksi PDO (Singleton)
 * @return PDO Objek koneksi database yang siap digunakan
 */
function getDBConnection() {
    // Variabel statis menyimpan referensi koneksi agar tidak membuka koneksi ganda
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        /**
         * DSN (Data Source Name): String informasi koneksi yang memuat tipe database,
         * nama host, port, nama database, dan set karakter UTF-8.
         */
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        
        /**
         * Konfigurasi Opsi Keamanan PDO:
         * 1. ATTR_ERRMODE => ERRMODE_EXCEPTION: Memaksa PDO melempar Exception jika terjadi kesalahan query.
         * 2. ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC: Hasil data query selalu berupa array asosiatif ($row['nama']).
         * 3. ATTR_EMULATE_PREPARES => false: Memaksa database menjalankan real prepared statement (Anti SQL Injection).
         */
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Memastikan tabel-tabel dan data awal selalu tersedia
        initDatabaseTables($pdo);
        return $pdo;
    } catch (PDOException $e) {
        /**
         * Blok Penanganan jika Database Belum Ada:
         * Jika koneksi ke database 'tabungan_pkk' gagal (misal database belum dibuat di phpMyAdmin),
         * sistem akan menyambung ke root MySQL untuk membuat database secara otomatis.
         */
        try {
            $rootDsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
            $rootPdo = new PDO($rootDsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            // Membuat database dengan pengkodean karakter internasional UTF-8
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Menyambung kembali ke database yang baru saja dibuat
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // Membuat struktur tabel dan data awal
            initDatabaseTables($pdo);
            return $pdo;
        } catch (PDOException $ex) {
            // Tampilan ramah jika MySQL di XAMPP belum dinyalakan
            die("<div style='font-family:sans-serif; padding:2rem; background:#fee2e2; color:#991b1b; border-radius:12px; margin:2rem auto; max-width:600px; border:1px solid #f87171;'>" .
                "<h3 style='margin-top:0;'>Koneksi Database MySQL Gagal</h3>" .
                "<p>Pesan Error: " . htmlspecialchars($ex->getMessage()) . "</p>" .
                "<hr style='border:none; border-top:1px solid #fca5a5;'>" .
                "<p style='margin-bottom:0;'><strong>Solusi:</strong> Pastikan Anda telah menekan tombol <strong>Start</strong> pada modul <strong>MySQL</strong> di XAMPP Control Panel.</p>" .
                "</div>");
        }
    }
}

/**
 * Fungsi Membuat Struktur Tabel Database (Data Definition Language / DDL)
 * Menggunakan perintah 'CREATE TABLE IF NOT EXISTS' agar aman dan tidak menimpa data yang sudah ada.
 */
function initDatabaseTables($pdo) {
    // 1. Tabel Users: Menyimpan akun login seluruh pengguna (Admin, Bendahara, Pengguna)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama` VARCHAR(150) NOT NULL,
        `username` VARCHAR(100) NOT NULL UNIQUE,
        `email` VARCHAR(150) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL, -- Menyimpan hash BCRYPT 60 karakter
        `role` ENUM('admin', 'bendahara', 'pengguna') NOT NULL DEFAULT 'pengguna',
        `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
        `no_hp` VARCHAR(25) NULL,
        `alamat` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Tabel Anggota: Menyimpan profil keanggotaan PKK dengan relasi One-to-One ke tabel users
    $pdo->exec("CREATE TABLE IF NOT EXISTS `anggota` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NULL UNIQUE,
        `nomor_anggota` VARCHAR(50) NOT NULL UNIQUE,
        `nama` VARCHAR(150) NOT NULL,
        `nik` VARCHAR(30) NULL,
        `nomor_hp` VARCHAR(25) NULL,
        `alamat` TEXT NULL,
        `tanggal_gabung` DATE NOT NULL,
        `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_anggota_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Tabel Transaksi: Menyimpan catatan mutasi setoran dan penarikan kas tabungan
    $pdo->exec("CREATE TABLE IF NOT EXISTS `transaksi` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `kode_transaksi` VARCHAR(50) NOT NULL UNIQUE,
        `anggota_id` INT NOT NULL,
        `jenis_transaksi` ENUM('setoran', 'penarikan') NOT NULL,
        `nominal` DECIMAL(15, 2) NOT NULL,
        `metode_pembayaran` VARCHAR(50) DEFAULT 'Tunai',
        `bukti_transaksi` VARCHAR(255) NULL,
        `tanggal` DATE NOT NULL,
        `keterangan` TEXT NULL,
        `status` ENUM('berhasil', 'menunggu', 'dibatalkan') NOT NULL DEFAULT 'berhasil',
        `petugas_id` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_transaksi_anggota` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_transaksi_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. Tabel Pengajuan Penarikan: Menampung permohonan penarikan dana mandiri oleh anggota
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pengajuan_penarikan` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `anggota_id` INT NOT NULL,
        `nominal` DECIMAL(15, 2) NOT NULL,
        `tanggal_pengajuan` DATE NOT NULL,
        `keterangan` TEXT NULL,
        `status` ENUM('menunggu', 'disetujui', 'ditolak', 'selesai') NOT NULL DEFAULT 'menunggu',
        `diproses_oleh` INT NULL,
        `tanggal_proses` DATETIME NULL,
        `catatan_petugas` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_pengajuan_anggota` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_pengajuan_petugas` FOREIGN KEY (`diproses_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 5. Tabel Notifikasi: Sistem pemberitahuan real-time untuk anggota
    $pdo->exec("CREATE TABLE IF NOT EXISTS `notifikasi` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `judul` VARCHAR(150) NOT NULL,
        `pesan` TEXT NOT NULL,
        `status_baca` ENUM('belum', 'sudah') NOT NULL DEFAULT 'belum',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT `fk_notifikasi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 6. Tabel Pengaturan: Konfigurasi global nama organisasi, batas setoran, dan kontak
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pengaturan` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama_organisasi` VARCHAR(150) NOT NULL DEFAULT 'PKK RW 05 Kelurahan Harapan Baru',
        `nama_aplikasi` VARCHAR(100) NOT NULL DEFAULT 'TabunganPKK',
        `nominal_minimal_setor` DECIMAL(15, 2) NOT NULL DEFAULT 10000,
        `aturan_penarikan` TEXT NULL,
        `periode_aktif` VARCHAR(50) NOT NULL DEFAULT '2026 / 2027',
        `kontak_hp` VARCHAR(30) NOT NULL DEFAULT '081234567890',
        `kontak_email` VARCHAR(100) NOT NULL DEFAULT 'pkk@harapanbaru.desa.id',
        `alamat_kantor` TEXT NULL,
        `deskripsi` TEXT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Cek apakah data user sudah ada, jika belum lakukan seeding data awal
    $count = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    if ($count == 0) {
        seedInitialData($pdo);
    }
}

/**
 * Fungsi Seeder Data Awal (Mengisi Data Pengujian Default)
 * Password seluruh akun dienkripsi dengan algoritma BCRYPT menggunakan fungsi bawaan PHP:
 * password_hash('password123', PASSWORD_BCRYPT)
 */
function seedInitialData($pdo) {
    $defaultHash = password_hash('password123', PASSWORD_BCRYPT);

    // 1. Menambahkan Akun Users Default: Admin, Bendahara, dan 3 Anggota
    $stmt = $pdo->prepare("INSERT INTO `users` (`nama`, `username`, `email`, `password`, `role`, `status`, `no_hp`, `alamat`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Admin
    $stmt->execute(['Administrator PKK', 'admin', 'admin@tabunganpkk.local', $defaultHash, 'admin', 'aktif', '081200000001', 'Sekretariat PKK RW 05']);
    $adminId = $pdo->lastInsertId();

    // Bendahara
    $stmt->execute(['Ibu Siti Nurhaliza (Bendahara)', 'bendahara', 'bendahara@tabunganpkk.local', $defaultHash, 'bendahara', 'aktif', '081200000002', 'Jl. Melati No. 12, RT 02/05']);
    $bendaharaId = $pdo->lastInsertId();

    // Anggota 1
    $stmt->execute(['Ibu Ani Suryani', 'anggota', 'ani@tabunganpkk.local', $defaultHash, 'pengguna', 'aktif', '081200000003', 'Jl. Mawar No. 05, RT 01/05']);
    $userAniId = $pdo->lastInsertId();

    // Anggota 2
    $stmt->execute(['Ibu Dewi Lestari', 'ibu_dewi', 'dewi@tabunganpkk.local', $defaultHash, 'pengguna', 'aktif', '081200000004', 'Jl. Anggrek No. 08, RT 03/05']);
    $userDewiId = $pdo->lastInsertId();

    // Anggota 3
    $stmt->execute(['Ibu Rina Kartika', 'ibu_rina', 'rina@tabunganpkk.local', $defaultHash, 'pengguna', 'aktif', '081200000005', 'Jl. Cempaka No. 17, RT 04/05']);
    $userRinaId = $pdo->lastInsertId();

    // 2. Menghubungkan Akun Pengguna ke Tabel Anggota PKK
    $stmtAnggota = $pdo->prepare("INSERT INTO `anggota` (`user_id`, `nomor_anggota`, `nama`, `nik`, `nomor_hp`, `alamat`, `tanggal_gabung`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $stmtAnggota->execute([$userAniId, 'PKK-2026-001', 'Ibu Ani Suryani', '3201015501800001', '081200000003', 'Jl. Mawar No. 05, RT 01/05', '2026-01-10', 'aktif']);
    $anggotaAniId = $pdo->lastInsertId();

    $stmtAnggota->execute([$userDewiId, 'PKK-2026-002', 'Ibu Dewi Lestari', '3201015602850002', '081200000004', 'Jl. Anggrek No. 08, RT 03/05', '2026-01-12', 'aktif']);
    $anggotaDewiId = $pdo->lastInsertId();

    $stmtAnggota->execute([$userRinaId, 'PKK-2026-003', 'Ibu Rina Kartika', '3201015703880003', '081200000005', 'Jl. Cempaka No. 17, RT 04/05', '2026-02-01', 'aktif']);
    $anggotaRinaId = $pdo->lastInsertId();

    // Anggota binaan tambahan
    $stmtAnggota->execute([NULL, 'PKK-2026-004', 'Ibu Endang Rahayu', '3201015804790004', '081200000006', 'Jl. Kenanga No. 21, RT 02/05', '2026-02-15', 'aktif']);
    $anggotaEndangId = $pdo->lastInsertId();

    $stmtAnggota->execute([NULL, 'PKK-2026-005', 'Ibu Sri Wahyuni', '3201015905820005', '081200000007', 'Jl. Flamboyan No. 04, RT 05/05', '2026-03-01', 'aktif']);
    $anggotaSriId = $pdo->lastInsertId();

    // 3. Menambahkan Riwayat Transaksi Nyata
    $stmtTrx = $pdo->prepare("INSERT INTO `transaksi` (`kode_transaksi`, `anggota_id`, `jenis_transaksi`, `nominal`, `metode_pembayaran`, `tanggal`, `keterangan`, `status`, `petugas_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmtTrx->execute(['TRX-20260301-0001', $anggotaAniId, 'setoran', 100000, 'Tunai', '2026-03-01', 'Setoran tabungan bulanan PKK', 'berhasil', $bendaharaId]);
    $stmtTrx->execute(['TRX-20260315-0002', $anggotaAniId, 'setoran', 150000, 'Transfer', '2026-03-15', 'Setoran tambahan tabungan', 'berhasil', $bendaharaId]);
    $stmtTrx->execute(['TRX-20260401-0003', $anggotaAniId, 'setoran', 100000, 'Tunai', '2026-04-01', 'Setoran tabungan bulan April', 'berhasil', $bendaharaId]);
    $stmtTrx->execute(['WD-20260410-0004', $anggotaAniId, 'penarikan', 50000, 'Tunai', '2026-04-10', 'Penarikan untuk keperluan keluarga', 'berhasil', $bendaharaId]);

    $stmtTrx->execute(['TRX-20260305-0005', $anggotaDewiId, 'setoran', 200000, 'Tunai', '2026-03-05', 'Setoran awal tabungan', 'berhasil', $bendaharaId]);
    $stmtTrx->execute(['TRX-20260320-0006', $anggotaDewiId, 'setoran', 250000, 'Transfer', '2026-03-20', 'Setoran tabungan PKK', 'berhasil', $bendaharaId]);

    $stmtTrx->execute(['TRX-20260310-0007', $anggotaRinaId, 'setoran', 300000, 'Tunai', '2026-03-10', 'Setoran tabungan triwulan', 'berhasil', $bendaharaId]);
    $stmtTrx->execute(['TRX-20260312-0008', $anggotaEndangId, 'setoran', 100000, 'Tunai', '2026-03-12', 'Setoran rutin PKK', 'berhasil', $bendaharaId]);
    $stmtTrx->execute(['TRX-20260315-0009', $anggotaSriId, 'setoran', 150000, 'Tunai', '2026-03-15', 'Setoran rutin PKK', 'berhasil', $bendaharaId]);

    // 4. Menambahkan Contoh Pengajuan Penarikan Mandiri
    $stmtPengajuan = $pdo->prepare("INSERT INTO `pengajuan_penarikan` (`anggota_id`, `nominal`, `tanggal_pengajuan`, `keterangan`, `status`, `diproses_oleh`, `tanggal_proses`, `catatan_petugas`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtPengajuan->execute([$anggotaAniId, 50000, '2026-04-09', 'Penarikan untuk keperluan mendesak', 'disetujui', $bendaharaId, '2026-04-10 09:30:00', 'Disetujui dan dicairkan tunai']);
    $stmtPengajuan->execute([$anggotaDewiId, 100000, '2026-04-12', 'Rencana penarikan persiapan sekolah anak', 'menunggu', NULL, NULL, NULL]);

    // 5. Menambahkan Notifikasi In-App
    $stmtNotif = $pdo->prepare("INSERT INTO `notifikasi` (`user_id`, `judul`, `pesan`, `status_baca`) VALUES (?, ?, ?, ?)");
    $stmtNotif->execute([$userAniId, 'Setoran Berhasil', 'Setoran sebesar Rp 100.000 pada tanggal 01 April 2026 telah berhasil dicatat oleh bendahara.', 'sudah']);
    $stmtNotif->execute([$userAniId, 'Pengajuan Penarikan Disetujui', 'Pengajuan penarikan sebesar Rp 50.000 telah disetujui dan dibayarkan tunai.', 'belum']);
    $stmtNotif->execute([$userDewiId, 'Pengajuan Penarikan Diterima', 'Pengajuan penarikan sebesar Rp 100.000 sedang menunggu verifikasi oleh bendahara.', 'belum']);

    // 6. Menambahkan Pengaturan Sistem Dasar
    $pdo->exec("INSERT INTO `pengaturan` (`nama_organisasi`, `nama_aplikasi`, `nominal_minimal_setor`, `aturan_penarikan`, `periode_aktif`, `kontak_hp`, `kontak_email`, `alamat_kantor`, `deskripsi`) VALUES
        ('PKK RW 05 Harapan Jaya', 'TabunganPKK', 10000, 'Penarikan tabungan dapat dilakukan setiap hari kerja dengan konfirmasi bendahara maksimal H-1 atau melalui pengajuan online.', '2026 / 2027', '0812-9876-5432', 'pkk.rw05@harapanjaya.desa.id', 'Balai Warga RW 05, Jl. Harmoni No. 01', 'Aplikasi digital pencatatan dan transparansi tabungan anggota PKK RW 05.')");
}
