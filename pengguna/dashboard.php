<?php
/**
 * TabunganPKK - Dashboard Pengguna / Anggota PKK
 *
 * Mengambil profil anggota yang terhubung ke akun, ringkasan saldo dan mutasi,
 * serta membentuk data grafik enam bulan. Bila profil belum tersedia, sistem
 * membuat record anggota agar transaksi akun punya pemilik yang jelas.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

require_role(['pengguna', 'admin', 'bendahara']);

$pdo = getDBConnection();
$pengaturan = get_pengaturan($pdo);

// Ambil akun aktif dari sesi dan cari profil anggota yang tertaut.
$userId = $_SESSION['user_id'];
$anggota = get_anggota_by_user_id($pdo, $userId);

// Jika belum memiliki profil anggota terhubung, buatkan otomatis
if (!$anggota) {
    // Bentuk nomor anggota cadangan jika akun belum memiliki profil anggota.
    $noAnggota = 'PKK-' . date('Y') . '-' . sprintf('%03d', $userId);
    // Buat profil minimal yang terhubung langsung ke akun login.
    $stmtC = $pdo->prepare("
        INSERT INTO anggota (user_id, nomor_anggota, nama, nomor_hp, alamat, tanggal_gabung, status)
        VALUES (?, ?, ?, ?, ?, CURDATE(), 'aktif')
    ");
    // Isi profil menggunakan identitas akun dan tanggal hari ini dari database.
    $stmtC->execute([
        $userId,
        $noAnggota,
        $_SESSION['nama'],
        $_SESSION['no_hp'] ?? '',
        $_SESSION['alamat'] ?? ''
    ]);
    $anggota = get_anggota_by_user_id($pdo, $userId);
}

// Simpan ID anggota agar semua query berikutnya hanya membaca mutasi miliknya.
$anggotaId = $anggota['id'];

// Hitung saldo
// Gunakan perhitungan bersama untuk saldo dan total setoran/penarikan.
$saldoData = hitung_saldo_anggota($pdo, $anggotaId);
$saldoSaatIni = $saldoData['saldo'];
$totalSetoran = $saldoData['total_setoran'];
$totalPenarikan = $saldoData['total_penarikan'];

// Jumlah transaksi berhasil
// Hitung hanya transaksi berhasil untuk indikator jumlah transaksi.
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE anggota_id = ? AND status = 'berhasil'");
$stmtCount->execute([$anggotaId]);
$jumlahTransaksi = (int)$stmtCount->fetchColumn();

// Setoran Terakhir
// Cari setoran berhasil paling baru berdasarkan tanggal dan ID.
$stmtLastSetor = $pdo->prepare("
    SELECT * FROM transaksi 
    WHERE anggota_id = ? AND jenis_transaksi = 'setoran' AND status = 'berhasil'
    ORDER BY tanggal DESC, id DESC LIMIT 1
");
$stmtLastSetor->execute([$anggotaId]);
$setoranTerakhir = $stmtLastSetor->fetch();

// Penarikan Terakhir
// Cari penarikan berhasil paling baru dengan urutan yang sama.
$stmtLastTarik = $pdo->prepare("
    SELECT * FROM transaksi 
    WHERE anggota_id = ? AND jenis_transaksi = 'penarikan' AND status = 'berhasil'
    ORDER BY tanggal DESC, id DESC LIMIT 1
");
$stmtLastTarik->execute([$anggotaId]);
$penarikanTerakhir = $stmtLastTarik->fetch();

// 5 Transaksi Terbaru
// Ambil lima transaksi terbaru untuk ringkasan aktivitas dashboard.
$stmtTrx = $pdo->prepare("
    SELECT * FROM transaksi 
    WHERE anggota_id = ? 
    ORDER BY tanggal DESC, id DESC LIMIT 5
");
$stmtTrx->execute([$anggotaId]);
$transaksiTerbaru = $stmtTrx->fetchAll();

// Data Grafik Perkembangan Saldo 6 Bulan Terakhir
$grafikData = [];
for ($i = 5; $i >= 0; $i--) {
    // Tentukan label bulan untuk enam titik grafik yang akan ditampilkan.
    $bulanTahun = date('Y-m', strtotime("-$i months"));
    $namaBulan = date('M Y', strtotime("-$i months"));

    // Saldo kumulatif hingga akhir bulan tersebut
    // Hitung saldo kumulatif sampai akhir bulan agar grafik menunjukkan perkembangan saldo.
    $stmtG = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN jenis_transaksi = 'setoran' AND status = 'berhasil' THEN nominal ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN jenis_transaksi = 'penarikan' AND status = 'berhasil' THEN nominal ELSE 0 END), 0) AS saldo_kumulatif
        FROM transaksi
        WHERE anggota_id = ? AND DATE_FORMAT(tanggal, '%Y-%m') <= ?
    ");
    $stmtG->execute([$anggotaId, $bulanTahun]);
    $gRow = $stmtG->fetch();

    $grafikData[] = [
        'bulan' => $namaBulan,
        'saldo' => (float)$gRow['saldo_kumulatif']
    ];
}

$pageTitle = 'Dashboard Saya';
$pageHeading = 'Tabungan Saya';
$pageSubheading = 'Selamat datang, ' . $anggota['nama'] . ' (' . $anggota['nomor_anggota'] . ')';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Kartu Utama Saldo Tabungan (Hero Card) -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="saldo-hero-card">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="hero-label"><i class="fa-solid fa-wallet me-1"></i> SALDO TABUNGAN SAAT INI</span>
                <span class="badge bg-white text-success fw-bold px-3 py-1">PKK RW 05</span>
            </div>
            
            <div class="hero-nominal"><?= format_rupiah($saldoSaatIni) ?></div>

            <div class="row g-2">
                <div class="col-6 col-sm-4">
                    <div class="saldo-hero-stat">
                        <small class="d-block opacity-75"><i class="fa-solid fa-arrow-down me-1"></i> Total Setoran</small>
                        <strong class="fs-6"><?= format_rupiah($totalSetoran) ?></strong>
                    </div>
                </div>
                <div class="col-6 col-sm-4">
                    <div class="saldo-hero-stat">
                        <small class="d-block opacity-75"><i class="fa-solid fa-arrow-up me-1"></i> Total Penarikan</small>
                        <strong class="fs-6"><?= format_rupiah($totalPenarikan) ?></strong>
                    </div>
                </div>
                <div class="col-12 col-sm-4">
                    <div class="saldo-hero-stat">
                        <small class="d-block opacity-75"><i class="fa-solid fa-receipt me-1"></i> Total Transaksi</small>
                        <strong class="fs-6"><?= $jumlahTransaksi ?> Kali Transaksi</strong>
                    </div>
                </div>
            </div>

            <div class="mt-3 pt-3 border-top border-white-50 d-flex flex-wrap gap-2">
                <a href="<?= base_url('pengguna/ajukan_penarikan.php') ?>" class="btn btn-light text-success fw-bold btn-sm">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Ajukan Penarikan Dana
                </a>
                <a href="<?= base_url('pengguna/riwayat.php') ?>" class="btn btn-outline-light btn-sm">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i> Riwayat Lengkap
                </a>
            </div>
        </div>
    </div>

    <!-- Informasi Terakhir Transaksi -->
    <div class="col-lg-4">
        <div class="card-pkk h-100">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-bell text-warning"></i> Aktivitas Terakhir</h5>
            </div>
            <div class="card-pkk-body">
                <!-- Setoran Terakhir -->
                <div class="p-3 mb-3 rounded-3 border bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted fw-bold text-uppercase"><i class="fa-solid fa-arrow-down text-success me-1"></i> Setoran Terakhir</small>
                        <span class="badge bg-success-subtle text-success">Masuk</span>
                    </div>
                    <?php if ($setoranTerakhir): ?>
                        <div class="fs-5 fw-bold text-success mb-1">+<?= format_rupiah($setoranTerakhir['nominal']) ?></div>
                        <small class="text-muted d-block"><?= format_tanggal($setoranTerakhir['tanggal']) ?> (<?= htmlspecialchars($setoranTerakhir['metode_pembayaran'] ?: 'Tunai') ?>)</small>
                    <?php else: ?>
                        <div class="text-muted small py-2">Belum ada setoran tercatat.</div>
                    <?php endif; ?>
                </div>

                <!-- Penarikan Terakhir -->
                <div class="p-3 rounded-3 border bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted fw-bold text-uppercase"><i class="fa-solid fa-arrow-up text-danger me-1"></i> Penarikan Terakhir</small>
                        <span class="badge bg-danger-subtle text-danger">Keluar</span>
                    </div>
                    <?php if ($penarikanTerakhir): ?>
                        <div class="fs-5 fw-bold text-danger mb-1">-<?= format_rupiah($penarikanTerakhir['nominal']) ?></div>
                        <small class="text-muted d-block"><?= format_tanggal($penarikanTerakhir['tanggal']) ?> • <?= htmlspecialchars($penarikanTerakhir['keterangan']) ?></small>
                    <?php else: ?>
                        <div class="text-muted small py-2">Belum pernah melakukan penarikan.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grafik Perkembangan Tabungan & Transaksi Terkini -->
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card-pkk h-100">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-chart-line text-success"></i> Grafik Pertumbuhan Saldo Tabungan</h5>
                <span class="badge bg-light text-secondary border">6 Bulan Terakhir</span>
            </div>
            <div class="card-pkk-body">
                <canvas id="chartSaldoPengguna" height="150"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-pkk h-100">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-list-ul text-primary"></i> 5 Transaksi Terakhir</h5>
                <a href="<?= base_url('pengguna/riwayat.php') ?>" class="small text-success text-decoration-none fw-semibold">Lihat Semua</a>
            </div>
            <div class="card-pkk-body p-0">
                <?php if (empty($transaksiTerbaru)): ?>
                    <div class="text-center py-4 text-muted small">Belum ada transaksi pada akun Anda.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($transaksiTerbaru as $t): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-icon" style="width: 38px; height: 38px; border-radius: 8px; font-size: 1rem; background-color: <?= $t['jenis_transaksi'] === 'setoran' ? '#dcfce7' : '#ffe4e6' ?>; color: <?= $t['jenis_transaksi'] === 'setoran' ? '#15803d' : '#e11d48' ?>; display:flex; align-items:center; justify-content:center;">
                                        <i class="fa-solid <?= $t['jenis_transaksi'] === 'setoran' ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark small mb-0"><?= htmlspecialchars($t['keterangan'] ?: ($t['jenis_transaksi'] === 'setoran' ? 'Setoran Tabungan' : 'Penarikan Tabungan')) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;">
                                            <?= format_tanggal($t['tanggal']) ?> • <?= htmlspecialchars($t['kode_transaksi']) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold <?= $t['jenis_transaksi'] === 'setoran' ? 'text-success' : 'text-danger' ?>">
                                        <?= $t['jenis_transaksi'] === 'setoran' ? '+' : '-' ?><?= format_rupiah($t['nominal']) ?>
                                    </div>
                                    <a href="<?= base_url('cetak_kuitansi.php?kode=' . urlencode($t['kode_transaksi'])) ?>" class="small text-muted text-decoration-none" title="Cetak Kwitansi">
                                        <i class="fa-solid fa-print"></i> Kwitansi
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('chartSaldoPengguna').getContext('2d');
    const chartData = <?= json_encode($grafikData) ?>;

    const labels = chartData.map(i => i.bulan);
    const dataSaldo = chartData.map(i => i.saldo);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Saldo Tabungan (Rp)',
                data: dataSaldo,
                borderColor: '#15803d',
                backgroundColor: 'rgba(21, 128, 61, 0.15)',
                borderWidth: 3,
                fill: true,
                tension: 0.3,
                pointRadius: 5,
                pointBackgroundColor: '#15803d',
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(v) {
                            return 'Rp ' + (v / 1000).toLocaleString('id-ID') + 'k';
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
