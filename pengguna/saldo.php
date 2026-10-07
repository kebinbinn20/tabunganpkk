<?php
/**
 * TabunganPKK - Saldo Tabungan Anggota
 *
 * Menampilkan saldo anggota, total setoran, total penarikan, identitas kartu
 * anggota, dan aturan penarikan yang diambil dari pengaturan sistem.
 */

// Muat PDO dan helper untuk sesi, hak akses, format angka, serta konfigurasi.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

// Pastikan role pengguna memiliki izin melihat informasi saldo.
require_role(['pengguna', 'admin', 'bendahara']);

// Ambil koneksi dan pengaturan organisasi yang ditampilkan pada halaman saldo.
$pdo = getDBConnection();
$pengaturan = get_pengaturan($pdo);

// Gunakan ID sesi untuk menemukan profil anggota pemilik saldo.
$userId = $_SESSION['user_id'];
$anggota = get_anggota_by_user_id($pdo, $userId);
// Simpan ID anggota untuk perhitungan saldo dan mutasi.
$anggotaId = $anggota['id'];

// Gunakan helper yang sama dengan halaman lain supaya perhitungan saldo konsisten.
$saldoData = hitung_saldo_anggota($pdo, $anggotaId);
// Pisahkan nilai agregat agar template dapat menampilkan setiap ringkasan.
$saldoSaatIni = $saldoData['saldo'];
$totalSetoran = $saldoData['total_setoran'];
$totalPenarikan = $saldoData['total_penarikan'];

$pageTitle = 'Saldo Tabungan Saya';
$pageHeading = 'Rincian Saldo Tabungan';
$pageSubheading = 'Informasi mutasi akumulatif dan ketersediaan dana simpanan';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="row g-4 mb-4">
    <!-- Big Saldo Card -->
    <div class="col-lg-8">
        <div class="saldo-hero-card">
            <span class="hero-label"><i class="fa-solid fa-piggy-bank me-1"></i> SALDO TABUNGAN SAAT INI</span>
            <div class="hero-nominal"><?= format_rupiah($saldoSaatIni) ?></div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="saldo-hero-stat">
                        <small class="d-block opacity-75"><i class="fa-solid fa-circle-arrow-down me-1"></i> Total Akumulasi Setoran</small>
                        <strong class="fs-5 text-white"><?= format_rupiah($totalSetoran) ?></strong>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="saldo-hero-stat">
                        <small class="d-block opacity-75"><i class="fa-solid fa-circle-arrow-up me-1"></i> Total Akumulasi Penarikan</small>
                        <strong class="fs-5 text-white"><?= format_rupiah($totalPenarikan) ?></strong>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top border-white-50 d-flex flex-wrap gap-2">
                <a href="<?= base_url('pengguna/ajukan_penarikan.php') ?>" class="btn btn-light text-success fw-bold">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Ajukan Penarikan Tabungan
                </a>
                <a href="<?= base_url('pengguna/riwayat.php') ?>" class="btn btn-outline-light">
                    <i class="fa-solid fa-receipt me-1"></i> Lihat Rincian Mutasi
                </a>
            </div>
        </div>
    </div>

    <!-- Informasi Kartu Anggota -->
    <div class="col-lg-4">
        <div class="card-pkk h-100">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-id-card text-success"></i> Kartu Simpanan PKK</h5>
            </div>
            <div class="card-pkk-body">
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="text-muted small">Nomor Anggota:</div>
                    <div class="fw-bold fs-6 font-monospace text-dark"><?= htmlspecialchars($anggota['nomor_anggota']) ?></div>
                    <div class="text-muted small mt-2">Nama Pemilik Tabungan:</div>
                    <div class="fw-bold text-dark"><?= htmlspecialchars($anggota['nama']) ?></div>
                    <div class="text-muted small mt-2">Status Keanggotaan:</div>
                    <span class="badge badge-status-<?= $anggota['status'] ?> text-uppercase"><?= $anggota['status'] ?></span>
                </div>

                <div class="small text-muted">
                    <i class="fa-solid fa-circle-info text-info me-1"></i>
                    Tabungan dikelola transparan oleh bendahara PKK RW 05 sesuai AD/ART organisasi.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Aturan & Ketentuan Penarikan -->
<div class="card-pkk">
    <div class="card-pkk-header">
        <h5><i class="fa-solid fa-circle-question text-primary"></i> Aturan & Mekanisme Pengambilan Tabungan</h5>
    </div>
    <div class="card-pkk-body">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="d-flex gap-3">
                    <div class="stat-icon" style="width: 44px; height: 44px; border-radius: 10px; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fa-solid fa-mobile-screen"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">1. Ajukan Online</h6>
                        <p class="small text-muted mb-0">Klik tombol <strong>Ajukan Penarikan</strong> pada menu aplikasi dan tentukan nominal.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-3">
                    <div class="stat-icon" style="width: 44px; height: 44px; border-radius: 10px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">2. Verifikasi Bendahara</h6>
                        <p class="small text-muted mb-0">Bendahara akan memeriksa permohonan dan ketersediaan kas PKK.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-3">
                    <div class="stat-icon" style="width: 44px; height: 44px; border-radius: 10px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">3. Penerimaan Dana</h6>
                        <p class="small text-muted mb-0">Uang diserahkan tunai di pertemuan PKK atau melalui transfer bank.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-light border mt-4 mb-0 small text-secondary">
            <strong>Catatan Pengurus:</strong> <?= htmlspecialchars($pengaturan['aturan_penarikan']) ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
