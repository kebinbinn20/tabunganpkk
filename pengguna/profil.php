<?php
/**
 * TabunganPKK - Profil Anggota
 *
 * Memproses dua formulir terpisah: pembaruan biodata (disinkronkan ke profil
 * anggota) dan perubahan password yang memerlukan verifikasi password lama.
 * Password baru disimpan sebagai hash, bukan teks biasa.
 */

// Muat PDO dan helper umum untuk autentikasi dan pemformatan URL.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

// Batasi halaman profil untuk role yang memakai fitur anggota.
require_role(['pengguna', 'admin', 'bendahara']);

// Ambil data akun dan profil anggota untuk ditampilkan pada formulir.
$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$user = get_current_user_data($pdo);
$anggota = get_anggota_by_user_id($pdo, $userId);

// Handle Update Profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profil') {
    // Ambil nilai biodata dan buang spasi di bagian awal/akhir.
    $nama = trim($_POST['nama'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Nama dan email wajib ada sebelum database diperbarui.
    if (empty($nama) || empty($email)) {
        set_flash('danger', 'Nama dan Email wajib diisi.');
    } else {
        // Cek duplikasi email dengan user lain
        // Cari email yang sama pada akun lain; akun saat ini dikecualikan.
        $cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $cek->execute([$email, $userId]);
        if ($cek->fetchColumn() > 0) {
            set_flash('danger', 'Email sudah digunakan oleh akun lain.');
        } else {
            // Update users
            // Simpan biodata utama ke tabel akun menggunakan prepared statement.
            $stmtU = $pdo->prepare("UPDATE users SET nama = ?, email = ?, no_hp = ?, alamat = ? WHERE id = ?");
            $stmtU->execute([$nama, $email, $no_hp, $alamat, $userId]);

            // Update anggota
            // Samakan kontak profil keanggotaan jika akun terhubung ke record anggota.
            if ($anggota) {
                $stmtA = $pdo->prepare("UPDATE anggota SET nama = ?, nomor_hp = ?, alamat = ? WHERE id = ?");
                $stmtA->execute([$nama, $no_hp, $alamat, $anggota['id']]);
            }

            // Perbarui nama/email pada sesi agar tampilan langsung konsisten.
            $_SESSION['nama'] = $nama;
            $_SESSION['email'] = $email;

            set_flash('success', 'Data profil Anda berhasil diperbarui.');
            header("Location: " . base_url('pengguna/profil.php'));
            exit;
        }
    }
}

// Handle Ganti Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ganti_password') {
    // Ambil tiga nilai password untuk verifikasi, pembaruan, dan konfirmasi.
    $passLama = $_POST['password_lama'] ?? '';
    $passBaru = $_POST['password_baru'] ?? '';
    $passKonf = $_POST['password_konfirmasi'] ?? '';

    // Semua kolom wajib diisi agar password dapat diganti dengan aman.
    if (empty($passLama) || empty($passBaru) || empty($passKonf)) {
        set_flash('danger', 'Semua kolom password wajib diisi.');
    } elseif ($passBaru !== $passKonf) {
        set_flash('danger', 'Konfirmasi password baru tidak cocok.');
    } elseif (strlen($passBaru) < 6) {
        set_flash('danger', 'Password baru minimal harus 6 karakter.');
    } else {
        // Cek password lama
        // Cocokkan password lama dengan hash akun tanpa membandingkan teks biasa.
        if (!password_verify($passLama, $user['password'])) {
            set_flash('danger', 'Password lama yang Anda masukkan salah.');
        } else {
            // Simpan password pengganti sebagai hash bcrypt.
            $newHash = password_hash($passBaru, PASSWORD_BCRYPT);
            $stmtP = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmtP->execute([$newHash, $userId]);

            set_flash('success', 'Kata sandi berhasil diperbarui.');
            header("Location: " . base_url('pengguna/profil.php'));
            exit;
        }
    }
}

$pageTitle = 'Profil Saya';
$pageHeading = 'Profil Anggota';
$pageSubheading = 'Kelola data pribadi dan pengaturan keamanan kata sandi Anda';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="row g-4">
    <!-- Ringkasan Profil Card -->
    <div class="col-lg-4">
        <div class="card-pkk text-center p-4">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #15803d, #22c55e); color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 800; margin-bottom: 1rem; box-shadow: 0 4px 14px rgba(21, 128, 61, 0.3);">
                <?= strtoupper(substr($user['nama'], 0, 1)) ?>
            </div>
            <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($user['nama']) ?></h5>
            <div class="mb-2">
                <span class="badge bg-success-subtle text-success font-monospace px-3 py-1">
                    <?= htmlspecialchars($anggota['nomor_anggota'] ?? 'ANGGOTA') ?>
                </span>
            </div>
            <p class="text-muted small mb-3">@<?= htmlspecialchars($user['username']) ?> • Anggota PKK RW 05</p>

            <div class="border-top pt-3 text-start small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Status Akun:</span>
                    <span class="badge badge-status-<?= $user['status'] ?> text-uppercase"><?= $user['status'] ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Tanggal Gabung:</span>
                    <strong><?= format_tanggal($anggota['tanggal_gabung'] ?? $user['created_at']) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">NIK KTP:</span>
                    <strong><?= htmlspecialchars($anggota['nik'] ?? '-') ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profil & Password -->
    <div class="col-lg-8">
        <!-- Form Biodata -->
        <div class="card-pkk mb-4">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-user-pen text-success"></i> Edit Data Pribadi</h5>
            </div>
            <div class="card-pkk-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profil">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nomor WhatsApp / HP</label>
                        <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($anggota['nomor_hp'] ?? $user['no_hp'] ?? '') ?>" placeholder="0812xxxxxxx">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Alamat Rumah Lengkap</label>
                        <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($anggota['alamat'] ?? $user['alamat'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-pkk-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan Profil
                    </button>
                </form>
            </div>
        </div>

        <!-- Form Ganti Password -->
        <div class="card-pkk">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-lock text-primary"></i> Ganti Kata Sandi (Password)</h5>
            </div>
            <div class="card-pkk-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="ganti_password">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kata Sandi Lama *</label>
                        <input type="password" name="password_lama" class="form-control" placeholder="Masukkan password saat ini" required>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Kata Sandi Baru *</label>
                            <input type="password" name="password_baru" class="form-control" placeholder="Minimal 6 karakter" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Konfirmasi Sandi Baru *</label>
                            <input type="password" name="password_konfirmasi" class="form-control" placeholder="Ulangi sandi baru" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fa-solid fa-key me-1"></i> Perbarui Kata Sandi
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
