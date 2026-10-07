<?php
/**
 * TabunganPKK - Footer Component
 *
 * Menutup area konten, menampilkan navigasi bawah khusus layar kecil, lalu
 * memuat Bootstrap dan JavaScript aplikasi sebelum menutup dokumen HTML.
 */
$userRole = $_SESSION['role'] ?? 'pengguna';
$currentScript = basename($_SERVER['PHP_SELF']);
?>
    </main>

    <!-- Mobile Bottom Navigation Bar -->
    <nav class="mobile-bottom-nav">
        <ul>
            <?php if ($userRole === 'admin'): ?>
                <li>
                    <a href="<?= base_url('admin/dashboard.php') ?>" class="<?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-gauge"></i>
                        <span>Beranda</span>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('admin/pengguna.php') ?>" class="<?= $currentScript === 'pengguna.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-users"></i>
                        <span>Pengguna</span>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('admin/anggota.php') ?>" class="<?= in_array($currentScript, ['anggota.php', 'anggota_detail.php']) ? 'active' : '' ?>">
                        <i class="fa-solid fa-address-book"></i>
                        <span>Anggota</span>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('admin/laporan.php') ?>" class="<?= $currentScript === 'laporan.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span>Laporan</span>
                    </a>
                </li>
            <?php elseif ($userRole === 'bendahara'): ?>
                <li>
                    <a href="<?= base_url('bendahara/dashboard.php') ?>" class="<?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-gauge"></i>
                        <span>Beranda</span>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('bendahara/setoran.php') ?>" class="<?= $currentScript === 'setoran.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-circle-arrow-down text-success"></i>
                        <span>Setoran</span>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('bendahara/penarikan.php') ?>" class="<?= $currentScript === 'penarikan.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-circle-arrow-up text-danger"></i>
                        <span>Tarik</span>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('bendahara/riwayat.php') ?>" class="<?= $currentScript === 'riwayat.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-receipt"></i>
                        <span>Riwayat</span>
                    </a>
                </li>
            <?php else: ?>
                <li>
                    <a href="<?= base_url('pengguna/dashboard.php') ?>" class="<?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-wallet"></i>
                        <span>Saldo</span>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('pengguna/riwayat.php') ?>" class="<?= $currentScript === 'riwayat.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Riwayat</span>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('pengguna/ajukan_penarikan.php') ?>" class="<?= $currentScript === 'ajukan_penarikan.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                        <span>Ajukan</span>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('pengguna/profil.php') ?>" class="<?= $currentScript === 'profil.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-user"></i>
                        <span>Profil</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div> <!-- End app-main -->
</div> <!-- End app-wrapper -->

<!-- Bootstrap 5 Bundle JS with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- TabunganPKK JS -->
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
