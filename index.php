<?php
/**
 * TabunganPKK - Entry Point Router
 *
 * Halaman ini menjadi pintu masuk aplikasi. Koneksi database dipanggil agar
 * proses setup awal berjalan, lalu sesi login diperiksa. Pengguna diarahkan
 * ke dashboard sesuai role; pengunjung yang belum login menuju halaman login.
 */

// Muat koneksi dan utilitas autentikasi sebelum memeriksa sesi.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Membuka PDO; fungsi ini juga membuat database dan tabel jika belum tersedia.
$pdo = getDBConnection();

// Sesi aktif berarti aplikasi dapat memilih dashboard berdasarkan role akun.
if (is_logged_in()) {
    // Baca role dari sesi; string kosong akan diarahkan ke login sebagai kondisi aman.
    $role = $_SESSION['role'] ?? '';
    // Akun administrator masuk ke area pengelolaan sistem.
    if ($role === 'admin') {
        header("Location: " . base_url('admin/dashboard.php'));
    // Akun bendahara masuk ke area transaksi dan laporan kas.
    } elseif ($role === 'bendahara') {
        header("Location: " . base_url('bendahara/dashboard.php'));
    // Anggota masuk ke dashboard saldo pribadinya.
    } elseif ($role === 'pengguna') {
        header("Location: " . base_url('pengguna/dashboard.php'));
    // Role yang tidak dikenal tidak diberi akses ke dashboard.
    } else {
        header("Location: " . base_url('login.php'));
    }
// Pengunjung tanpa sesi login harus melewati autentikasi terlebih dahulu.
} else {
    header("Location: " . base_url('login.php'));
}
// Hentikan request setelah header pengalihan dikirim.
exit;
