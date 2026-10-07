<?php
/**
 * =========================================================================================
 * APLIKASI TABUNGAN DIGITAL PKK (TabunganPKK)
 * File: login.php
 * -----------------------------------------------------------------------------------------
 * PENJELASAN UNTUK PRESENTASI:
 * 1. Fungsi File:
 *    File ini adalah pintu gerbang utama (autentikasi) ke dalam sistem TabunganPKK.
 *    Halaman ini memvalidasi identitas pengguna (Username/Email dan Password) sebelum
 *    memberikan hak akses ke dashboard yang sesuai (Admin, Bendahara, atau Anggota).
 *
 * 2. Aspek Keamanan yang Diterapkan:
 *    - Prepared Statements (PDO): Mencegah serangan SQL Injection karena input pengguna
 *      dipisahkan dari struktur perintah SQL menggunakan parameter binding (tanda tanya ?).
 *    - Password Hashing (BCRYPT): Kata sandi tidak pernah disimpan dalam bentuk teks biasa
 *      (plain text), melainkan diuji menggunakan fungsi 'password_verify()' terhadap hash acak.
 *    - Session Fixation & Sanitasi: Nilai input dibersihkan dengan 'trim()' dan sesi login
 *      dikelola secara eksklusif menggunakan '$_SESSION'.
 *    - Validasi Status Akun: Akun yang dinonaktifkan oleh Admin tidak diizinkan masuk sistem.
 * =========================================================================================
 */

// 1. Memanggil berkas konfigurasi database dan fungsi-fungsi bantuan (helpers)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// 2. Membuka koneksi database menggunakan PDO (PHP Data Objects)
$pdo = getDBConnection();
$pengaturan = get_pengaturan($pdo);

// 3. Pengecekan Sesi Login (Role Routing Guard)
// Jika pengguna ternyata sudah login sebelumnya dan belum logout,
// langsung arahkan ke dashboard masing-masing tanpa harus login ulang.
if (is_logged_in()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin') {
        header("Location: " . base_url('admin/dashboard.php'));
    } elseif ($role === 'bendahara') {
        header("Location: " . base_url('bendahara/dashboard.php'));
    } else {
        header("Location: " . base_url('pengguna/dashboard.php'));
    }
    exit;
}

// Inisialisasi variabel pesan error dan nilai input form
$error = '';
$usernameVal = '';

// 4. Memproses Pengiriman Data Form (HTTP POST Method)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mengambil dan membersihkan spasi di awal/akhir input pengguna
    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $usernameVal = $usernameOrEmail;

    // Validasi apakah ada kolom input yang masih kosong
    if (empty($usernameOrEmail) || empty($password)) {
        $error = 'Silakan isi username/email dan kata sandi Anda.';
    } else {
        /**
         * Query Pencarian Pengguna dengan Prepared Statement
         * Pengguna bisa masuk menggunakan 'username' ATAU alamat 'email'.
         * Tanda '?' adalah parameter binding untuk mencegah manipulasi query (SQL Injection).
         */
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $user = $stmt->fetch();

        // 5. Verifikasi Keberadaan Pengguna & Kecocokan Kata Sandi (password_verify)
        if ($user && password_verify($password, $user['password'])) {
            // Validasi status akun (aktif atau nonaktif)
            if ($user['status'] !== 'aktif') {
                $error = 'Akun Anda sedang dinonaktifkan oleh Administrator. Silakan hubungi pengurus PKK.';
            } else {
                /**
                 * 6. Menyimpan Data Pengguna ke dalam Session Server
                 * Data ini akan digunakan oleh sistem untuk mengenali siapa yang sedang login
                 * di setiap halaman aplikasi tanpa perlu memasukkan password berulang kali.
                 */
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Jika pengguna adalah anggota PKK biasa (role = pengguna),
                // ambil data nomor anggota dan ID anggota dari tabel 'anggota'
                if ($user['role'] === 'pengguna') {
                    $stmtAnggota = $pdo->prepare("SELECT * FROM anggota WHERE user_id = ? LIMIT 1");
                    $stmtAnggota->execute([$user['id']]);
                    $anggota = $stmtAnggota->fetch();
                    if ($anggota) {
                        $_SESSION['anggota_id'] = $anggota['id'];
                        $_SESSION['nomor_anggota'] = $anggota['nomor_anggota'];
                    }
                }

                /**
                 * 7. Redirection (Pengalihan Halaman) Sesuai Hak Akses (Role-Based Access Control)
                 * - Admin -> Diarahkan ke Dashboard Manajemen Sistem & Pengguna
                 * - Bendahara -> Diarahkan ke Dashboard Keuangan & Kasir Transaksi
                 * - Pengguna -> Diarahkan ke Dashboard Tabungan Pribadi Anggota
                 */
                if ($user['role'] === 'admin') {
                    header("Location: " . base_url('admin/dashboard.php'));
                } elseif ($user['role'] === 'bendahara') {
                    header("Location: " . base_url('bendahara/dashboard.php'));
                } else {
                    header("Location: " . base_url('pengguna/dashboard.php'));
                }
                exit;
            }
        } else {
            // Pesan kesalahan sengaja disamarkan agar pihak tak berwenang tidak tahu
            // apakah username yang salah atau password yang salah (standar OWASP Security).
            $error = 'Username/email atau kata sandi tidak sesuai.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - TabunganPKK</title>
    
    <!-- Favicon Aplikasi -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%230f5132'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/></svg>">
    
    <!-- Framework CSS Bootstrap 5.3 & Icon FontAwesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Typography: Plus Jakarta Sans untuk keterbacaan tinggi -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS Kustom dengan Kontras Warna Tegas & Elegan -->
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #e8f5e9 0%, #d1e7dd 50%, #cbd5e1 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            margin: 0;
            color: #0b1320;
        }
        .login-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 16px 36px rgba(11, 70, 38, 0.16), 0 2px 6px rgba(0, 0, 0, 0.08);
            border: 1px solid #94a3b8;
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        .login-header {
            padding: 2.5rem 2rem 1.25rem;
            text-align: center;
        }
        .login-logo {
            width: 68px;
            height: 68px;
            background: linear-gradient(135deg, #0b4626, #157347);
            color: #ffffff;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: 0 8px 20px rgba(11, 70, 38, 0.35);
            margin-bottom: 1.25rem;
        }
        .login-title {
            font-weight: 800;
            color: #072e18;
            font-size: 1.75rem;
            letter-spacing: -0.03em;
            margin: 0;
        }
        .login-body {
            padding: 1rem 2.25rem 2.5rem;
        }
        .form-label {
            color: #1e293b;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.35rem;
        }
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1.5px solid #94a3b8;
            font-size: 0.95rem;
            color: #0b1320;
            background-color: #ffffff;
        }
        .form-control:focus {
            border-color: #0f5132;
            box-shadow: 0 0 0 3.5px rgba(15, 81, 50, 0.22);
        }
        .input-group-text {
            border: 1.5px solid #94a3b8;
            background-color: #f8fafc;
            color: #334155;
        }
        .btn-login {
            background: linear-gradient(135deg, #0b4626, #0f5132);
            color: #ffffff;
            font-weight: 700;
            padding: 0.85rem;
            border-radius: 10px;
            border: none;
            width: 100%;
            font-size: 1rem;
            transition: all 0.25s ease;
            box-shadow: 0 4px 12px rgba(11, 70, 38, 0.3);
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #072e18, #0b4626);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(11, 70, 38, 0.4);
        }
        .link-forgot {
            color: #0b4626;
            font-weight: 700;
            font-size: 0.85rem;
            text-decoration: none;
        }
        .link-forgot:hover {
            color: #072e18;
            text-decoration: underline;
        }
        .security-badge {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0.85rem;
            font-size: 0.8rem;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1.75rem;
        }
    </style>
</head>
<body>

<!-- Kontainer Card Login -->
<div class="login-card">
    <!-- Header Kartu: Logo dan Nama Aplikasi TabunganPKK Saja -->
    <div class="login-header">
        <div class="login-logo">
            <i class="fa-solid fa-hand-holding-dollar"></i>
        </div>
        <h3 class="login-title">TabunganPKK</h3>
    </div>

    <!-- Body Kartu: Formulir Autentikasi -->
    <div class="login-body">
        <!-- Menampilkan Alert Kesalahan Validasi Login -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 px-3 small d-flex align-items-center mb-3" style="border-radius: 8px;">
                <i class="fa-solid fa-circle-exclamation me-2 fs-6"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <!-- Menampilkan Flash Message (misal: pesan sukses setelah logout) -->
        <?php
        $flash = get_flash();
        if ($flash):
        ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> py-2 px-3 small d-flex align-items-center mb-3" style="border-radius: 8px;">
                <i class="fa-solid fa-circle-check me-2 fs-6"></i>
                <div><?= htmlspecialchars($flash['message']) ?></div>
            </div>
        <?php endif; ?>

        <!-- Formulir Login -->
        <form method="POST" action="">
            <!-- Input Username atau Email -->
            <div class="mb-3">
                <label class="form-label" for="usernameInput">Username atau Email</label>
                <div class="input-group">
                    <span class="input-group-text border-end-0" style="border-radius: 10px 0 0 10px;">
                        <i class="fa-regular fa-user"></i>
                    </span>
                    <input type="text" name="username" id="usernameInput" class="form-control border-start-0" placeholder="Masukkan username atau email" value="<?= htmlspecialchars($usernameVal) ?>" required autofocus>
                </div>
            </div>

            <!-- Input Password -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0" for="passwordInput">Kata Sandi</label>
                    <a href="<?= base_url('lupa-password.php') ?>" class="link-forgot">Lupa Kata Sandi?</a>
                </div>
                <div class="input-group">
                    <span class="input-group-text border-end-0" style="border-radius: 10px 0 0 10px;">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" name="password" id="passwordInput" class="form-control border-start-0" placeholder="Masukkan kata sandi akun" required>
                </div>
            </div>

            <!-- Tombol Submit Masuk -->
            <button type="submit" class="btn btn-login mt-2">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Masuk
            </button>
        </form>

        <!-- Informasi Keamanan Sistem -->
        <div class="security-badge">
            <i class="fa-solid fa-shield-halved text-success fs-4"></i>
            <div>
                <strong>Aman & Terenkripsi:</strong> Transaksi tabungan tercatat otomatis dengan otentikasi role PKK terintegrasi.
            </div>
        </div>
    </div>
</div>

</body>
</html>
