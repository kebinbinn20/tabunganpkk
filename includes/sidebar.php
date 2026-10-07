<?php
/**
 * TabunganPKK - Sidebar Navigation Component
 *
 * Menu dibuat berdasarkan role pada sesi login. Pemeriksaan ini hanya
 * mengatur navigasi yang terlihat; pembatasan akses halaman tetap dilakukan
 * oleh require_role() pada masing-masing halaman.
 */
$userRole = $_SESSION['role'] ?? 'pengguna';
$userName = $_SESSION['nama'] ?? 'Pengguna';
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<aside class="app-sidebar">
    <a href="<?= base_url() ?>" class="sidebar-brand">
        <div class="brand-logo">
            <i class="fa-solid fa-hand-holding-dollar"></i>
        </div>
        <div class="brand-text">
            <span class="brand-title">TabunganPKK</span>
            <span class="brand-subtitle">Sistem Digital PKK</span>
        </div>
    </a>

    <div class="sidebar-menu">
        <?php if ($userRole === 'admin'): ?>
            <!-- MENU ADMIN -->
            <div class="menu-category">Utama</div>
            <div class="nav-item">
                <a href="<?= base_url('admin/dashboard.php') ?>" class="nav-link <?= ($currentScript === 'dashboard.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="menu-category">Manajemen Data</div>
            <div class="nav-item">
                <a href="<?= base_url('admin/pengguna.php') ?>" class="nav-link <?= ($currentScript === 'pengguna.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-users-gear"></i>
                    <span>Kelola Pengguna</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= base_url('admin/anggota.php') ?>" class="nav-link <?= in_array($currentScript, ['anggota.php', 'anggota_detail.php']) ? 'active' : '' ?>">
                    <i class="fa-solid fa-address-book"></i>
                    <span>Kelola Anggota</span>
                </a>
            </div>

            <div class="menu-category">Sistem & Laporan</div>
            <div class="nav-item">
                <a href="<?= base_url('admin/laporan.php') ?>" class="nav-link <?= ($currentScript === 'laporan.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>Laporan Transaksi</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= base_url('admin/sistem.php') ?>" class="nav-link <?= ($currentScript === 'sistem.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-sliders"></i>
                    <span>Kelola Sistem</span>
                </a>
            </div>

        <?php elseif ($userRole === 'bendahara'): ?>
            <!-- MENU BENDAHARA -->
            <div class="menu-category">Utama</div>
            <div class="nav-item">
                <a href="<?= base_url('bendahara/dashboard.php') ?>" class="nav-link <?= ($currentScript === 'dashboard.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="menu-category">Transaksi Tabungan</div>
            <div class="nav-item">
                <a href="<?= base_url('bendahara/setoran.php') ?>" class="nav-link <?= ($currentScript === 'setoran.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-circle-arrow-down text-success"></i>
                    <span>Setoran Tabungan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= base_url('bendahara/penarikan.php') ?>" class="nav-link <?= ($currentScript === 'penarikan.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-circle-arrow-up text-danger"></i>
                    <span>Penarikan Tabungan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= base_url('bendahara/pengajuan.php') ?>" class="nav-link <?= ($currentScript === 'pengajuan.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-hand-holding-hand text-warning"></i>
                    <span>Pengajuan Penarikan</span>
                </a>
            </div>

            <div class="menu-category">Data & Laporan</div>
            <div class="nav-item">
                <a href="<?= base_url('bendahara/anggota.php') ?>" class="nav-link <?= ($currentScript === 'anggota.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-users"></i>
                    <span>Data Anggota</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= base_url('bendahara/riwayat.php') ?>" class="nav-link <?= ($currentScript === 'riwayat.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Riwayat Transaksi</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= base_url('bendahara/laporan.php') ?>" class="nav-link <?= ($currentScript === 'laporan.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Laporan Keuangan</span>
                </a>
            </div>

        <?php else: ?>
            <!-- MENU PENGGUNA / ANGGOTA -->
            <div class="menu-category">Menu Utama</div>
            <div class="nav-item">
                <a href="<?= base_url('pengguna/dashboard.php') ?>" class="nav-link <?= ($currentScript === 'dashboard.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-wallet"></i>
                    <span>Dashboard Saya</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= base_url('pengguna/saldo.php') ?>" class="nav-link <?= ($currentScript === 'saldo.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-money-bill-trend-up"></i>
                    <span>Saldo Tabungan</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= base_url('pengguna/riwayat.php') ?>" class="nav-link <?= ($currentScript === 'riwayat.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>Riwayat Transaksi</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= base_url('pengguna/ajukan_penarikan.php') ?>" class="nav-link <?= ($currentScript === 'ajukan_penarikan.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                    <span>Ajukan Penarikan</span>
                </a>
            </div>

            <div class="menu-category">Akun</div>
            <div class="nav-item">
                <a href="<?= base_url('pengguna/notifikasi.php') ?>" class="nav-link <?= ($currentScript === 'notifikasi.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-bell"></i>
                    <span>Notifikasi</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= base_url('pengguna/profil.php') ?>" class="nav-link <?= ($currentScript === 'profil.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-id-card"></i>
                    <span>Profil Saya</span>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <div class="user-badge-mini">
            <div class="user-avatar">
                <?= strtoupper(substr($userName, 0, 1)) ?>
            </div>
            <div class="user-info flex-grow-1 overflow-hidden">
                <div class="fw-bold text-truncate text-dark small"><?= htmlspecialchars($userName) ?></div>
                <span class="badge badge-role-<?= $userRole ?> text-uppercase" style="font-size: 0.65rem;">
                    <?= $userRole ?>
                </span>
            </div>
            <a href="<?= base_url('logout.php') ?>" class="text-danger p-1" title="Keluar">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>
<div id="sidebarOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:1030;"></div>
