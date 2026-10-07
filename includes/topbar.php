<?php
/**
 * TabunganPKK - Topbar Navigation Component
 *
 * Menampilkan judul halaman, menu akun, dan ringkasan notifikasi untuk user
 * yang sedang login. Query notifikasi dibungkus try-catch agar gangguan pada
 * fitur pemberitahuan tidak menghentikan tampilan halaman utama.
 */
$pageHeading = $pageHeading ?? 'Dashboard';
$pageSubheading = $pageSubheading ?? 'Selamat datang di Aplikasi Tabungan PKK';
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'pengguna';
$userName = $_SESSION['nama'] ?? 'User';

// Ambil notifikasi hanya jika halaman sudah menyediakan koneksi database.
$unreadNotifCount = 0;
$recentNotifs = [];
if (isset($pdo) && $userId) {
    try {
        $stmtNotif = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND status_baca = 'belum'");
        $stmtNotif->execute([$userId]);
        $unreadNotifCount = (int)$stmtNotif->fetchColumn();

        $stmtList = $pdo->prepare("SELECT * FROM notifikasi WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
        $stmtList->execute([$userId]);
        $recentNotifs = $stmtList->fetchAll();
    } catch (Exception $e) {
        // Silently skip if query fails
    }
}
?>
<div class="app-main">
    <header class="app-topbar">
        <div class="topbar-left">
            <button class="topbar-toggle-btn" id="sidebarToggle" type="button" aria-label="Toggle Sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="page-title-wrap">
                <h1><?= htmlspecialchars($pageHeading) ?></h1>
                <small><?= htmlspecialchars($pageSubheading) ?></small>
            </div>
        </div>

        <div class="topbar-right">
            <!-- Notifikasi Dropdown -->
            <div class="dropdown">
                <button class="btn btn-light position-relative rounded-circle p-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 40px; height: 40px;">
                    <i class="fa-regular fa-bell text-secondary"></i>
                    <?php if ($unreadNotifCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                            <?= $unreadNotifCount ?>
                        </span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-2" style="width: 300px; border-radius: 12px;">
                    <div class="d-flex justify-content-between align-items-center px-2 py-1 mb-1 border-bottom">
                        <span class="fw-bold small text-dark">Pemberitahuan</span>
                        <?php if ($userRole === 'pengguna'): ?>
                            <a href="<?= base_url('pengguna/notifikasi.php') ?>" class="text-decoration-none small text-success">Lihat Semua</a>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($recentNotifs)): ?>
                        <div class="text-center py-3 text-muted small">
                            <i class="fa-regular fa-bell-slash d-block mb-1"></i>
                            Tidak ada notifikasi baru
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentNotifs as $notif): ?>
                            <div class="p-2 border-bottom small <?= $notif['status_baca'] === 'belum' ? 'bg-light' : '' ?>" style="border-radius: 6px;">
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($notif['judul']) ?></div>
                                <div class="text-muted text-truncate"><?= htmlspecialchars($notif['pesan']) ?></div>
                                <div class="text-secondary" style="font-size: 0.7rem;"><?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile dropdown -->
            <div class="dropdown">
                <button class="btn btn-light d-flex align-items-center gap-2 rounded-pill px-3 py-1 border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="d-none d-md-inline small fw-semibold text-dark"><?= htmlspecialchars($userName) ?></span>
                    <i class="fa-solid fa-chevron-down text-muted small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-2" style="border-radius: 12px;">
                    <li class="px-2 py-1 mb-1 border-bottom">
                        <div class="small fw-bold text-dark"><?= htmlspecialchars($userName) ?></div>
                        <span class="badge badge-role-<?= $userRole ?> text-uppercase"><?= $userRole ?></span>
                    </li>
                    <?php if ($userRole === 'pengguna'): ?>
                        <li><a class="dropdown-item rounded small py-2" href="<?= base_url('pengguna/profil.php') ?>"><i class="fa-solid fa-user me-2 text-muted"></i>Profil Saya</a></li>
                        <li><a class="dropdown-item rounded small py-2" href="<?= base_url('pengguna/saldo.php') ?>"><i class="fa-solid fa-wallet me-2 text-muted"></i>Saldo Tabungan</a></li>
                    <?php elseif ($userRole === 'bendahara'): ?>
                        <li><a class="dropdown-item rounded small py-2" href="<?= base_url('bendahara/dashboard.php') ?>"><i class="fa-solid fa-gauge me-2 text-muted"></i>Dashboard</a></li>
                    <?php else: ?>
                        <li><a class="dropdown-item rounded small py-2" href="<?= base_url('admin/sistem.php') ?>"><i class="fa-solid fa-gear me-2 text-muted"></i>Pengaturan Sistem</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item rounded small py-2 text-danger" href="<?= base_url('logout.php') ?>"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Keluar</a></li>
                </ul>
            </div>
        </div>
    </header>

    <main class="content-body">
        <?php
        // Tampilkan Flash Message jika ada
        $flash = get_flash();
        if ($flash):
        ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert" style="border-radius: 10px;">
                <div class="d-flex align-items-center">
                    <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-success' : ($flash['type'] === 'danger' ? 'fa-circle-xmark text-danger' : 'fa-circle-info text-primary') ?> me-2 fs-5"></i>
                    <div><?= htmlspecialchars($flash['message']) ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
