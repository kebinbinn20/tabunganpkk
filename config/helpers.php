<?php
/**
 * =========================================================================================
 * APLIKASI TABUNGAN DIGITAL PKK (TabunganPKK)
 * File: config/helpers.php
 * -----------------------------------------------------------------------------------------
 * PENJELASAN MATERI UNTUK PRESENTASI KE GURU:
 * 1. Fungsi File Helper:
 *    File helper berisi kumpulan fungsi pembantu umum (utility functions) yang digunakan
 *    berulang kali di berbagai halaman aplikasi. Mengikuti prinsip pemrograman DRY (Don't Repeat Yourself),
 *    logika umum tidak ditulis berulang-ulang, melainkan dipusatkan di sini.
 *
 * 2. Mengapa Perhitungan Saldo Dinamis (Dynamic Balance Calculation)?
 *    Salah satu keunggulan aplikasi ini yang sangat penting dipresentasikan adalah integritas data keuangan:
 *    - Saldo TIDAK disimpan dalam kolom statis di tabel 'anggota'.
 *    - Saldo selalu dihitung secara 'real-time' dari rumus: Total Setoran (Berhasil) - Total Penarikan (Berhasil).
 *    - Manfaat: Mencegah korupsi data saldo, mencegah manipulasi angka manual, dan menjamin
 *      setiap Rupiah pada saldo memiliki rekam jejak transaksi yang sah (Audit Trail).
 *
 * 3. Keamanan Hak Akses (Role-Based Access Control / RBAC):
 *    Fungsi 'require_role()' bertindak sebagai middleware penjaga pintu. Jika akun pengguna biasa
 *    mencoba mengetik URL admin (misal: /admin/dashboard.php), sistem akan mendeteksi dan
 *    otomatis menolaknya serta mengembalikan pengguna ke dashboard hak miliknya.
 * =========================================================================================
 */

// 1. Memulai Sesi Server jika belum aktif
if (session_status() === PHP_SESSION_NONE) {
    // Mulai sesi satu kali agar halaman dapat membaca login dan flash message.
    session_start();
}

/**
 * Mendapatkan Base URL Aplikasi Secara Dinamis
 * Fungsi ini secara otomatis mendeteksi apakah aplikasi berjalan di http:// atau https://,
 * nama domain/host, serta lokasi folder tabunganPKK.
 * @param string $path Sub-direktori atau file tujuan
 * @return string Alamat URL absolut lengkap
 */
function base_url($path = '') {
    // Mendeteksi protokol aman HTTPS atau standar HTTP
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    // Gunakan nama host dari request, atau localhost saat dijalankan tanpa web server.
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Mendeteksi folder instalasi aplikasi
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Samakan pemisah direktori supaya pencarian folder bekerja di Windows dan Linux.
    $dir = str_replace('\\', '/', dirname($script));
    
    // Ubah direktori menjadi daftar bagian untuk mencari folder proyek.
    $parts = explode('/', trim($dir, '/'));
    // Nilai -1 menandakan folder proyek belum ditemukan.
    $projectIndex = -1;
    // Temukan posisi folder aplikasi tanpa membedakan huruf besar/kecil.
    foreach ($parts as $idx => $part) {
        if (strtolower($part) === 'tabunganpkk') {
            $projectIndex = $idx;
            break;
        }
    }

    if ($projectIndex !== -1) {
        // Pertahankan path induk hingga folder aplikasi sebagai lokasi dasar.
        $projectPath = '/' . implode('/', array_slice($parts, 0, $projectIndex + 1));
        // Gabungkan protokol, host, dan path aplikasi menjadi URL dasar.
        $base = $protocol . $host . $projectPath;
    } else {
        // Gunakan lokasi instalasi XAMPP standar jika nama folder tidak terdeteksi.
        $base = $protocol . $host . '/tabunganPKK';
    }

    // Hapus slash awal agar penambahan path tidak menghasilkan garis miring ganda.
    $path = ltrim($path, '/');
    // Tambahkan path tujuan jika ada; jika kosong, kembalikan URL dasar saja.
    return $path ? rtrim($base, '/') . '/' . $path : rtrim($base, '/');
}

/**
 * Format Angka Menjadi Format Mata Uang Rupiah (Contoh: Rp 150.000)
 * Menggunakan fungsi bawaan PHP 'number_format' dengan pemisah ribuan titik.
 * @param float|int $angka Nilai uang
 * @param bool $withPrefix Apakah menyertakan teks 'Rp ' di depan
 * @return string Teks mata uang Rupiah rapi
 */
function format_rupiah($angka, $withPrefix = true) {
    // Pakai angka nol untuk input nonnumerik supaya formatter tidak menghasilkan error.
    $val = is_numeric($angka) ? (float)$angka : 0;
    // Format tanpa angka desimal dan gunakan titik sebagai pemisah ribuan.
    $hasil = number_format($val, 0, ',', '.');
    // Awali dengan simbol Rupiah bila hasil akan ditampilkan sebagai mata uang lengkap.
    return $withPrefix ? 'Rp ' . $hasil : $hasil;
}

/**
 * Format Tanggal Indonesia (Contoh: 15 Maret 2026)
 * Mengubah format standar database (YYYY-MM-DD) menjadi format bahasa Indonesia yang mudah dibaca.
 * @param string $dateString Tanggal dari database
 * @param bool $withTime Apakah menyertakan jam dan menit
 * @return string Tanggal dalam bahasa Indonesia
 */
function format_tanggal($dateString, $withTime = false) {
    // Tanggal kosong atau nilai tanggal nol dari database ditampilkan sebagai tanda strip.
    if (empty($dateString) || $dateString === '0000-00-00') {
        return '-';
    }
    
    $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    // Ubah tanggal dari database menjadi timestamp untuk pemformatan.
    $timestamp = strtotime($dateString);
    // Kembalikan nilai awal bila PHP tidak dapat mengenali format tanggalnya.
    if (!$timestamp) return $dateString;

    // Ambil komponen tanggal yang nantinya dirangkai dalam bahasa Indonesia.
    $d = date('d', $timestamp);
    $m = (int)date('m', $timestamp);
    $y = date('Y', $timestamp);

    // Susun tanggal dan nama bulan Indonesia menjadi teks yang mudah dibaca.
    $hasil = $d . ' ' . ($bulanIndo[$m] ?? date('M', $timestamp)) . ' ' . $y;

    // Tambahkan jam hanya saat pemanggil meminta format tanggal dan waktu.
    if ($withTime) {
        $hasil .= ' ' . date('H:i', $timestamp) . ' WIB';
    }

    return $hasil;
}

/**
 * RUMUS UTAMA TABUNGAN (INTEGRITAS DATA AKUNTANSI):
 * Menghitung Saldo Anggota Berdasarkan Seluruh Mutasi Transaksi
 * @param PDO $pdo Objek koneksi database
 * @param int $anggota_id ID anggota yang diperiksa
 * @return array Berisi: total_setoran, total_penarikan, dan saldo akhir
 */
function hitung_saldo_anggota($pdo, $anggota_id) {
    /**
     * Query Agregasi Bersyarat (Conditional Aggregation):
     * - CASE WHEN setoran AND status berhasil -> Tambahkan ke total_setoran
     * - CASE WHEN penarikan AND status berhasil -> Tambahkan ke total_penarikan
     */
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN jenis_transaksi = 'setoran' AND status = 'berhasil' THEN nominal ELSE 0 END), 0) AS total_setoran,
            COALESCE(SUM(CASE WHEN jenis_transaksi = 'penarikan' AND status = 'berhasil' THEN nominal ELSE 0 END), 0) AS total_penarikan
        FROM transaksi 
        WHERE anggota_id = ?
    ");
    // Jalankan query hanya untuk ID anggota yang diminta.
    $stmt->execute([$anggota_id]);
    // Ambil satu baris hasil agregasi total setoran dan penarikan.
    $data = $stmt->fetch();

    // Pastikan nilai database dipakai sebagai angka untuk perhitungan.
    $setoran = (float)($data['total_setoran'] ?? 0);
    $penarikan = (float)($data['total_penarikan'] ?? 0);

    // Kembalikan komponen saldo supaya halaman dapat menampilkan rincian dan saldo bersih.
    return [
        'total_setoran' => $setoran,
        'total_penarikan' => $penarikan,
        'saldo' => $setoran - $penarikan // Saldo = Total Setoran - Total Penarikan
    ];
}

/**
 * Menghitung Total Saldo Kas Seluruh Anggota PKK (Kas Tabungan Berjalan)
 * @param PDO $pdo Objek database
 * @return float Total uang tabungan di kas PKK
 */
function hitung_total_saldo_semua($pdo) {
    // Jumlahkan transaksi berhasil seluruh anggota dalam satu query agregasi.
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(CASE WHEN jenis_transaksi = 'setoran' AND status = 'berhasil' THEN nominal ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN jenis_transaksi = 'penarikan' AND status = 'berhasil' THEN nominal ELSE 0 END), 0) AS total_saldo
        FROM transaksi
    ");
    // Hasil tunggal diubah ke angka sebelum digunakan pada dashboard/laporan.
    return (float)($stmt->fetchColumn() ?? 0);
}

/**
 * Generator Kode Transaksi Otomatis dan Unik
 * Pola Kode: TRX-YYYYMMDD-0001 (untuk Setoran) atau WD-YYYYMMDD-0001 (untuk Penarikan / Withdrawal)
 * @param PDO $pdo Objek database
 * @param string $jenis 'setoran' atau 'penarikan'
 * @return string Kode transaksi unik
 */
function generate_kode_transaksi($pdo, $jenis = 'setoran') {
    // Bedakan awalan kode agar setoran dan penarikan mudah dikenali.
    $prefix = ($jenis === 'penarikan') ? 'WD' : 'TRX';
    // Gunakan tanggal server sebagai bagian identitas kode transaksi.
    $date = date('Ymd');
    
    // Menghitung jumlah transaksi ber-prefix sama pada hari ini
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE kode_transaksi LIKE ?");
    // Hitung kode dengan awalan dan tanggal yang sama.
    $stmt->execute([$prefix . '-' . $date . '-%']);
    // Nomor berikutnya adalah jumlah kode yang ada ditambah satu.
    $count = (int)$stmt->fetchColumn() + 1;

    // Bentuk nomor empat digit dengan format awalan-tanggal-nomor.
    $kode = sprintf("%s-%s-%04d", $prefix, $date, $count);

    // Verifikasi tambahan untuk memastikan kode 100% belum pernah digunakan
    $cek = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE kode_transaksi = ?");
    // Periksa benturan kode sebelum kode dikembalikan ke pemanggil.
    $cek->execute([$kode]);
    // Jika kode sudah pernah dipakai, geser nomor secara acak untuk menghindari duplikasi.
    if ($cek->fetchColumn() > 0) {
        $kode = sprintf("%s-%s-%04d", $prefix, $date, $count + rand(1, 99));
    }

    return $kode;
}

/**
 * Sistem Flash Message (Pesan Notifikasi Satu Kali Tayang)
 * Digunakan untuk menampilkan pesan sukses atau error setelah proses penyimpanan data dan redirect.
 */
function set_flash($type, $message) {
    // Simpan satu pesan pada sesi untuk ditampilkan setelah redirect.
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash() {
    // Hanya kembalikan pesan jika masih tersimpan pada sesi.
    if (isset($_SESSION['flash'])) {
        // Salin isi sebelum menghapusnya agar pesan hanya tampil sekali.
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']); // Hapus dari sesi setelah dibaca agar tidak muncul lagi
        return $flash;
    }
    // Nilai null menandakan halaman tidak memiliki pesan sementara.
    return null;
}

/**
 * Pengecekan Status Login Pengguna
 * @return bool True jika sudah login, False jika belum
 */
function is_logged_in() {
    // User dianggap login bila ID akun sudah tercatat pada sesi.
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Mengambil Data Pengguna yang Sedang Login
 * @param PDO $pdo
 * @return array|null Baris data tabel users
 */
function get_current_user_data($pdo) {
    // Hindari query akun bila belum ada sesi login.
    if (!is_logged_in()) return null;
    // Gunakan ID sesi sebagai parameter query akun.
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    // Kembalikan satu baris profil atau false bila akun tidak ditemukan.
    return $stmt->fetch();
}

/**
 * Mengambil Profil Anggota Berdasarkan User ID
 * @param PDO $pdo
 * @param int $user_id
 * @return array|null Baris data tabel anggota
 */
function get_anggota_by_user_id($pdo, $user_id) {
    // Cari profil anggota yang terhubung ke akun melalui user_id.
    $stmt = $pdo->prepare("SELECT * FROM anggota WHERE user_id = ?");
    $stmt->execute([$user_id]);
    // Satu akun hanya boleh tertaut ke satu profil anggota.
    return $stmt->fetch();
}

/**
 * MIDDLEWARE PENJAGA HAK AKSES (ROLE-BASED ACCESS CONTROL / RBAC)
 * Memastikan hanya role yang diizinkan yang dapat membuka halaman tertentu.
 * @param array|string $roles Daftar role yang diizinkan (contoh: ['admin', 'bendahara'])
 */
function require_role($roles = []) {
    // Jika belum login sama sekali, lempar ke halaman login
    if (!is_logged_in()) {
        header("Location: " . base_url('login.php'));
        exit;
    }

    // Izinkan pemanggil memberi satu role atau beberapa role dalam bentuk array.
    if (!is_array($roles)) {
        $roles = [$roles];
    }

    // Ambil role sesi untuk dibandingkan dengan daftar role yang diizinkan.
    $userRole = $_SESSION['role'] ?? '';
    // Jika role akun pengguna saat ini tidak terdaftar pada izin akses:
    if (!in_array($userRole, $roles)) {
        // Kembalikan ke dashboard sesuai hak akses aslinya
        if ($userRole === 'admin') {
            header("Location: " . base_url('admin/dashboard.php'));
        } elseif ($userRole === 'bendahara') {
            header("Location: " . base_url('bendahara/dashboard.php'));
        } elseif ($userRole === 'pengguna') {
            header("Location: " . base_url('pengguna/dashboard.php'));
        } else {
            header("Location: " . base_url('login.php'));
        }
        exit;
    }
}

/**
 * Mengirimkan Notifikasi In-App ke Pengguna Tertentu
 * @param PDO $pdo
 * @param int $user_id
 * @param string $judul
 * @param string $pesan
 */
function tambah_notifikasi($pdo, $user_id, $judul, $pesan) {
    // Jangan buat notifikasi bila penerima belum memiliki ID akun.
    if (!$user_id) return false;
    // Siapkan query insert; status awal notifikasi adalah belum dibaca.
    $stmt = $pdo->prepare("INSERT INTO notifikasi (user_id, judul, pesan, status_baca) VALUES (?, ?, ?, 'belum')");
    return $stmt->execute([$user_id, $judul, $pesan]);
}

/**
 * Mengambil Konfigurasi Sistem Organisasi
 * @param PDO $pdo
 * @return array Data tabel pengaturan
 */
function get_pengaturan($pdo) {
    // Ambil satu baris konfigurasi utama yang dipakai oleh seluruh aplikasi.
    $stmt = $pdo->query("SELECT * FROM pengaturan ORDER BY id ASC LIMIT 1");
    $pengaturan = $stmt->fetch();
    // Sediakan nilai cadangan agar tampilan masih berjalan jika tabel belum berisi data.
    if (!$pengaturan) {
        return [
            'nama_organisasi' => 'PKK RW 05 Harapan Baru',
            'nama_aplikasi' => 'TabunganPKK',
            'nominal_minimal_setor' => 10000,
            'aturan_penarikan' => 'Penarikan tabungan dapat diajukan secara online atau langsung kepada bendahara.',
            'periode_aktif' => '2026 / 2027',
            'kontak_hp' => '081234567890',
            'kontak_email' => 'pkk@harapanbaru.desa.id',
            'alamat_kantor' => 'Balai Pertemuan Warga',
            'deskripsi' => 'Aplikasi Pengelolaan Tabungan Anggota PKK'
        ];
    }
    return $pengaturan;
}

/**
 * Sanitasi String Input untuk Mencegah Serangan Cross-Site Scripting (XSS)
 * Mengubah karakter khusus seperti <, >, &, ' menjadi entitas HTML aman.
 */
function sanitize($data) {
    // Sanitasi tiap elemen secara rekursif bila input berupa array form.
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    // Rapikan spasi lalu ubah karakter HTML khusus untuk mencegah injeksi markup.
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}
