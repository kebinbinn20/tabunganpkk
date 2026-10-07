<?php
/**
 * TabunganPKK - Notifikasi Anggota
 *
 * Membatasi daftar notifikasi pada user yang login. Aksi tandai dibaca dan
 * hapus juga menyertakan user_id pada query agar user tidak dapat mengubah
 * notifikasi milik akun lain dengan menebak ID.
 */

// Muat koneksi database dan helper untuk sesi, hak akses, serta URL.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

// Izinkan role pengguna yang memakai halaman profil anggota.
require_role(['pengguna', 'admin', 'bendahara']);

// Siapkan koneksi serta identitas akun penerima notifikasi.
$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Handle Action: Tandai Semua Dibaca
if (isset($_GET['mark_all_read'])) {
    // Ubah hanya notifikasi milik akun yang sedang masuk.
    $stmt = $pdo->prepare("UPDATE notifikasi SET status_baca = 'sudah' WHERE user_id = ?");
    $stmt->execute([$userId]);
    set_flash('success', 'Semua notifikasi telah ditandai sudah dibaca.');
    // Muat ulang daftar agar status baca terbaru terlihat.
    header("Location: " . base_url('pengguna/notifikasi.php'));
    exit;
}

// Handle Action: Hapus Satu Notifikasi
if (isset($_GET['hapus']) && isset($_GET['id'])) {
    // Ubah ID URL menjadi integer sebelum dipakai sebagai parameter.
    $notifId = (int)$_GET['id'];
    // Sertakan user_id supaya akun tidak dapat menghapus notifikasi milik orang lain.
    $stmt = $pdo->prepare("DELETE FROM notifikasi WHERE id = ? AND user_id = ?");
    $stmt->execute([$notifId, $userId]);
    set_flash('success', 'Notifikasi berhasil dihapus.');
    // Kembali ke daftar setelah tindakan selesai.
    header("Location: " . base_url('pengguna/notifikasi.php'));
    exit;
}

// Ambil notifikasi pengguna
// Ambil notifikasi akun ini dari yang terbaru agar informasi baru muncul pertama.
$stmt = $pdo->prepare("SELECT * FROM notifikasi WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$daftarNotifikasi = $stmt->fetchAll();

$pageTitle = 'Notifikasi Saya';
$pageHeading = 'Pemberitahuan & Notifikasi';
$pageSubheading = 'Informasi mutasi tabungan dan status pengajuan penarikan Anda';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0">Daftar Notifikasi</h5>
        <small class="text-muted"><?= count($daftarNotifikasi) ?> Pemberitahuan</small>
    </div>
    <div>
        <a href="<?= base_url('pengguna/notifikasi.php?mark_all_read=1') ?>" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-check-double me-1"></i> Tandai Semua Sudah Dibaca
        </a>
    </div>
</div>

<div class="card-pkk">
    <div class="card-pkk-body p-0">
        <?php if (empty($daftarNotifikasi)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-regular fa-bell-slash fs-1 d-block mb-3 text-secondary"></i>
                <h6>Belum Ada Notifikasi</h6>
                <p class="small mb-0">Setiap ada pencatatan setoran atau respon pengajuan dana akan muncul di sini.</p>
            </div>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($daftarNotifikasi as $n): ?>
                    <li class="list-group-item p-3 <?= $n['status_baca'] === 'belum' ? 'bg-light border-start border-success border-4' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="d-flex align-items-start gap-3">
                                <div class="stat-icon" style="width: 40px; height: 40px; border-radius: 10px; background: <?= $n['status_baca'] === 'belum' ? '#dcfce7' : '#f1f5f9' ?>; color: <?= $n['status_baca'] === 'belum' ? '#15803d' : '#64748b' ?>; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                                    <i class="fa-solid fa-bell"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                        <?= htmlspecialchars($n['judul']) ?>
                                        <?php if ($n['status_baca'] === 'belum'): ?>
                                            <span class="badge bg-danger rounded-pill" style="font-size: 0.6rem;">BARU</span>
                                        <?php endif; ?>
                                    </h6>
                                    <p class="text-secondary small mb-1"><?= htmlspecialchars($n['pesan']) ?></p>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        <i class="fa-regular fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?> WIB
                                    </small>
                                </div>
                            </div>
                            <div>
                                <a href="<?= base_url('pengguna/notifikasi.php?hapus=1&id=' . $n['id']) ?>" class="btn btn-sm btn-link text-danger p-0" title="Hapus Notifikasi">
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
