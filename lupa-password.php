<?php
/**
 * TabunganPKK - Lupa Password
 *
 * Form ini mencari akun menggunakan username, email, atau nomor anggota.
 * Demi keamanan, halaman tidak mengganti kata sandi secara otomatis; pemilik
 * akun diminta menghubungi pengurus untuk verifikasi identitas.
 */

// Muat konfigurasi database dan helper format URL/sesi.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Buka koneksi untuk mencari akun dan membaca kontak pengurus.
$pdo = getDBConnection();
// Ambil nomor dan informasi kontak pengurus dari konfigurasi aplikasi.
$pengaturan = get_pengaturan($pdo);

// Siapkan pesan kosong agar form tidak menampilkan status sebelum dikirim.
$successMsg = '';
$errorMsg = '';

// Jalankan pencarian akun hanya setelah formulir dikirim dengan metode POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil identitas yang dimasukkan dan buang spasi di bagian tepinya.
    $identifier = trim($_POST['identifier'] ?? '');
    // Tolak input kosong sebelum menjalankan query.
    if (empty($identifier)) {
        $errorMsg = 'Silakan masukkan username, email, atau nomor anggota Anda.';
    } else {
        // LEFT JOIN memungkinkan pencarian juga melalui nomor anggota.
        $stmt = $pdo->prepare("SELECT u.* FROM users u 
            LEFT JOIN anggota a ON a.user_id = u.id 
            WHERE u.username = ? OR u.email = ? OR a.nomor_anggota = ? LIMIT 1");
        // Bind nilai ke tiga parameter agar input tidak menjadi bagian sintaks SQL.
        $stmt->execute([$identifier, $identifier, $identifier]);
        // Ambil satu akun yang cocok untuk ditampilkan pada pesan hasil.
        $user = $stmt->fetch();

        // Jika akun ditemukan, tampilkan langkah verifikasi melalui pengurus.
        if ($user) {
            $successMsg = "Permintaan reset password untuk akun <strong>" . htmlspecialchars($user['nama']) . "</strong> telah dicatat. Demi keamanan tabungan, silakan hubungi pengurus PKK di nomor <strong>" . htmlspecialchars($pengaturan['kontak_hp']) . "</strong> untuk verifikasi dan penerbitan sandi baru.";
        // Beri informasi bahwa tidak ada akun yang cocok dengan identitas tersebut.
        } else {
            $errorMsg = "Data pengguna tidak ditemukan dalam database TabunganPKK.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - <?= htmlspecialchars($pengaturan['nama_aplikasi']) ?></title>
    
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2315803d'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            margin: 0;
        }
        .card-box {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            width: 100%;
            max-width: 460px;
            padding: 2.25rem 2rem;
        }
        .btn-pkk {
            background-color: #15803d;
            color: #ffffff;
            font-weight: 700;
            padding: 0.75rem;
            border-radius: 10px;
            border: none;
            width: 100%;
        }
        .btn-pkk:hover {
            background-color: #166534;
            color: #ffffff;
        }
    </style>
</head>
<body>

<div class="card-box">
    <div class="text-center mb-4">
        <div style="width: 56px; height: 56px; background: #dcfce7; color: #15803d; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 0.75rem;">
            <i class="fa-solid fa-key"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Bantuan Sandi Akun</h4>
        <p class="text-muted small mb-0">Pemulihan akses akun Tabungan PKK Anda</p>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success small mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= $successMsg ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger small mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?= $errorMsg ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label small fw-semibold text-secondary">Username, Email, atau No. Anggota</label>
            <input type="text" name="identifier" class="form-control py-2" placeholder="Contoh: PKK-2026-001 atau username" required>
        </div>

        <button type="submit" class="btn btn-pkk mb-3">
            <i class="fa-solid fa-paper-plane me-1"></i> Ajukan Pemulihan
        </button>
    </form>

    <div class="bg-light p-3 rounded-3 small text-muted mb-4 border">
        <div class="fw-bold text-dark mb-1"><i class="fa-solid fa-circle-info text-success me-1"></i> Kontak Pengurus PKK:</div>
        <div>WhatsApp / HP: <strong><?= htmlspecialchars($pengaturan['kontak_hp']) ?></strong></div>
        <div>Email: <strong><?= htmlspecialchars($pengaturan['kontak_email']) ?></strong></div>
        <div>Alamat: <?= htmlspecialchars($pengaturan['alamat_kantor']) ?></div>
    </div>

    <div class="text-center">
        <a href="<?= base_url('login.php') ?>" class="text-decoration-none small text-success fw-semibold">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Halaman Masuk
        </a>
    </div>
</div>

</body>
</html>
