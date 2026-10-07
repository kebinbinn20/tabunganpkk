<?php

/**
 * TabunganPKK - Logout Script
 *
 * Proses keluar membersihkan data sesi dan cookie sesi di browser. Sesi baru
 * kemudian dipakai untuk menyimpan pesan sekali tampil sebelum kembali ke login.
 */

// Muat helper agar fungsi sesi, URL aplikasi, dan pesan sementara tersedia.
require_once __DIR__ . '/config/helpers.php';

// Pastikan sesi aktif sebelum data sesi dibersihkan.
if (session_status() === PHP_SESSION_NONE) {
    // Mulai sesi saat halaman logout diakses langsung tanpa sesi sebelumnya.
    session_start();
}

// Hapus identitas login dan seluruh data sementara dari sesi.
$_SESSION = [];
// Periksa apakah sesi menggunakan cookie yang tersimpan pada browser.
if (ini_get("session.use_cookies")) {
    // Ambil konfigurasi cookie agar penghapusan memakai path dan domain yang sama.
    $params = session_get_cookie_params();
    // Kedaluwarsakan cookie sesi agar browser berhenti mengirim identitas lama.
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
// Hapus sesi lama di server, lalu siapkan sesi baru untuk flash message.
session_destroy();

// Buat sesi baru khusus untuk menyimpan pemberitahuan setelah logout.
session_start();
// Simpan pesan sukses yang hanya akan ditampilkan satu kali pada halaman login.
set_flash('success', 'Anda telah berhasil keluar dari sistem TabunganPKK.');
// Arahkan pengguna kembali ke halaman login menggunakan alamat dasar aplikasi.
header("Location: " . base_url('login.php'));
// Hentikan eksekusi agar tidak ada kode lain berjalan setelah pengalihan.
exit;
