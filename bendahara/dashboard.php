<?php
/**
 * =========================================================================================
 * APLIKASI TABUNGAN DIGITAL PKK (TabunganPKK)
 * File: bendahara/dashboard.php
 * -----------------------------------------------------------------------------------------
 * PENJELASAN MATERI UNTUK PRESENTASI KE GURU:
 * 1. Peran Bendahara (Treasurer Role):
 *    Bendahara bertanggung jawab penuh atas operasional arus kas tabungan harian:
 *    - Menghitung setoran masuk dan penarikan tunai yang terjadi pada 'hari ini'.
 *    - Memantau notifikasi jika ada anggota yang mengajukan penarikan dana mandiri.
 *    - Mengamati grafik arus kas (Pemasukan vs Pengeluaran) untuk memprediksi likuiditas dana.
 *
 * 2. Real-Time Tracking Tanggal Berjalan (Current Day Metrics):
 *    Menggunakan parameter tanggal hari ini 'date("Y-m-d")' untuk mengunci perhitungan transaksi
 *    harian secara otomatis tanpa harus memilih tanggal manual.
 *
 * 3. Keamanan Otorisasi:
 *    Halaman dibatasi menggunakan require_role(['bendahara', 'admin']) sehingga anggota biasa
 *    tidak dapat mengintip kondisi brankas kas PKK.
 * =========================================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

require_role(['bendahara', 'admin']);

$pdo = getDBConnection();
$pengaturan = get_pengaturan($pdo);

$pageTitle = 'Dashboard Bendahara';
$pageHeading = 'Dashboard Bendahara';
$pageSubheading = 'Pengelolaan pencatatan transaksi tabungan dan arus kas PKK';

$today = date('Y-m-d');

// 1. Total Uang Tabungan Keseluruhan
$totalUangTabungan = hitung_total_saldo_semua($pdo);

// 2. Setoran Hari Ini
$stmtSetorToday = $pdo->prepare("
    SELECT COALESCE(SUM(nominal), 0) AS total_setor_hari_ini, COUNT(*) AS count_setor_hari_ini
    FROM transaksi
    WHERE jenis_transaksi = 'setoran' AND status = 'berhasil' AND tanggal = ?
");
$stmtSetorToday->execute([$today]);
$setorHariIni = $stmtSetorToday->fetch();
$totalSetorHariIni = (float)$setorHariIni['total_setor_hari_ini'];
$countSetorHariIni = (int)$setorHariIni['count_setor_hari_ini'];

// 3. Penarikan Hari Ini
$stmtTarikToday = $pdo->prepare("
    SELECT COALESCE(SUM(nominal), 0) AS total_tarik_hari_ini, COUNT(*) AS count_tarik_hari_ini
    FROM transaksi
    WHERE jenis_transaksi = 'penarikan' AND status = 'berhasil' AND tanggal = ?
");
$stmtTarikToday->execute([$today]);
$tarikHariIni = $stmtTarikToday->fetch();
$totalTarikHariIni = (float)$tarikHariIni['total_tarik_hari_ini'];
$countTarikHariIni = (int)$tarikHariIni['count_tarik_hari_ini'];

// 4. Jumlah Anggota Aktif
$totalAnggota = $pdo->query("SELECT COUNT(*) FROM anggota WHERE status = 'aktif'")->fetchColumn();

// 5. Pengajuan Penarikan Menunggu Persetujuan
$pengajuanPending = $pdo->query("SELECT COUNT(*) FROM pengajuan_penarikan WHERE status = 'menunggu'")->fetchColumn();

// 6. Transaksi Terbaru (Campuran Setoran & Penarikan)
$stmtTrxTerbaru = $pdo->query("
    SELECT t.*, a.nama AS nama_anggota, a.nomor_anggota
    FROM transaksi t
    JOIN anggota a ON a.id = t.anggota_id
    WHERE t.status = 'berhasil'
    ORDER BY t.tanggal DESC, t.id DESC
    LIMIT 6
");
$transaksiTerbaru = $stmtTrxTerbaru->fetchAll();

// 7. Data Grafik Arus Kas 6 Bulan Terakhir
$grafikData = [];
for ($i = 5; $i >= 0; $i--) {
    $bulanTahun = date('Y-m', strtotime("-$i months"));
    $namaBulan = date('M Y', strtotime("-$i months"));

    $stmtG = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN jenis_transaksi = 'setoran' AND status = 'berhasil' THEN nominal ELSE 0 END), 0) AS pemasukan,
            COALESCE(SUM(CASE WHEN jenis_transaksi = 'penarikan' AND status = 'berhasil' THEN nominal ELSE 0 END), 0) AS pengeluaran
        FROM transaksi
        WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?
    ");
    $stmtG->execute([$bulanTahun]);
    $gRow = $stmtG->fetch();

    $grafikData[] = [
        'bulan' => $namaBulan,
        'pemasukan' => (float)$gRow['pemasukan'],
        'pengeluaran' => (float)$gRow['pengeluaran']
    ];
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Quick Action Alert jika ada pengajuan penarikan baru -->
<?php if ($pengajuanPending > 0): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center mb-4 shadow-sm" style="border-radius: 12px; border: 1.5px solid #f59e0b;">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-bell fs-4 me-3 text-warning"></i>
            <div>
                <strong>Perhatian Bendahara:</strong> Ada <strong><?= $pengajuanPending ?> pengajuan penarikan tabungan</strong> dari anggota yang menunggu verifikasi Anda.
            </div>
        </div>
        <a href="<?= base_url('bendahara/pengajuan.php') ?>" class="btn btn-warning btn-sm fw-bold">
            Proses Sekarang <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>
<?php endif; ?>

<!-- Quick Actions Banner (Hijau Gelap Berkontras Tinggi) -->
<div class="card border-0 mb-4 p-4 text-white" style="background: linear-gradient(135deg, #072e18, #0b4626); border-radius: 18px; box-shadow: 0 12px 28px -4px rgba(7, 46, 24, 0.45); border: 1px solid rgba(255, 255, 255, 0.15);">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <span class="badge bg-white text-dark px-3 py-1 mb-2 fw-bold rounded-pill" style="color: #072e18 !important;">Bendahara Keuangan</span>
            <h3 class="fw-bold mb-1">Pencatatan Cepat Transaksi Tabungan</h3>
            <p class="mb-0 text-white-50">Catat setoran masuk atau proses penarikan tabungan anggota PKK secara langsung dan akurat.</p>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <a href="<?= base_url('bendahara/setoran.php') ?>" class="btn btn-light fw-bold me-2 rounded-pill px-4 shadow-sm" style="color: #072e18;">
                <i class="fa-solid fa-circle-arrow-down text-success me-1"></i> + Setor Tabungan
            </a>
            <a href="<?= base_url('bendahara/penarikan.php') ?>" class="btn btn-danger fw-bold rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-circle-arrow-up text-white me-1"></i> - Tarik Tabungan
            </a>
        </div>
    </div>
</div>

<!-- 4 Kartu Statistik Bendahara -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-green">
            <div>
                <div class="stat-title">Total Uang Tabungan</div>
                <div class="stat-value text-success"><?= format_rupiah($totalUangTabungan) ?></div>
                <div class="stat-sub text-muted">Kas bersih saat ini</div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-vault"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-blue">
            <div>
                <div class="stat-title">Setoran Hari Ini</div>
                <div class="stat-value text-primary"><?= format_rupiah($totalSetorHariIni) ?></div>
                <div class="stat-sub text-muted"><?= $countSetorHariIni ?> transaksi tercatat hari ini</div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-arrow-down-long"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-rose">
            <div>
                <div class="stat-title">Penarikan Hari Ini</div>
                <div class="stat-value text-danger"><?= format_rupiah($totalTarikHariIni) ?></div>
                <div class="stat-sub text-muted"><?= $countTarikHariIni ?> penarikan hari ini</div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-arrow-up-long"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-amber">
            <div>
                <div class="stat-title">Jumlah Anggota Aktif</div>
                <div class="stat-value"><?= number_format($totalAnggota) ?> <small class="fs-6 text-muted">Orang</small></div>
                <div class="stat-sub text-muted">Terdaftar di TabunganPKK</div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
    </div>
</div>

<!-- Grafik Pemasukan vs Pengeluaran & Transaksi Terbaru -->
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card-pkk h-100">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-chart-simple text-success"></i> Grafik Pemasukan dan Pengeluaran (6 Bulan)</h5>
                <span class="badge bg-light text-secondary border">Kas PKK</span>
            </div>
            <div class="card-pkk-body">
                <canvas id="chartBendahara" height="150"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-pkk h-100">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-clock-rotate-left text-primary"></i> Transaksi Tabungan Terbaru</h5>
                <a href="<?= base_url('bendahara/riwayat.php') ?>" class="small text-decoration-none text-success fw-semibold">Lihat Semua</a>
            </div>
            <div class="card-pkk-body p-0">
                <?php if (empty($transaksiTerbaru)): ?>
                    <div class="text-center py-4 text-muted small">Belum ada transaksi tercatat.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($transaksiTerbaru as $t): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-icon" style="width: 40px; height: 40px; border-radius: 10px; font-size: 1.1rem; background-color: <?= $t['jenis_transaksi'] === 'setoran' ? '#dcfce7' : '#ffe4e6' ?>; color: <?= $t['jenis_transaksi'] === 'setoran' ? '#15803d' : '#e11d48' ?>; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa-solid <?= $t['jenis_transaksi'] === 'setoran' ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark small mb-0"><?= htmlspecialchars($t['nama_anggota']) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;">
                                            <?= format_tanggal($t['tanggal']) ?> • <span class="font-monospace"><?= htmlspecialchars($t['kode_transaksi']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold <?= $t['jenis_transaksi'] === 'setoran' ? 'text-success' : 'text-danger' ?>">
                                        <?= $t['jenis_transaksi'] === 'setoran' ? '+' : '-' ?><?= format_rupiah($t['nominal']) ?>
                                    </div>
                                    <a href="<?= base_url('cetak_kuitansi.php?kode=' . urlencode($t['kode_transaksi'])) ?>" class="small text-muted text-decoration-none" title="Cetak Kuitansi">
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
    const ctx = document.getElementById('chartBendahara').getContext('2d');
    const chartData = <?= json_encode($grafikData) ?>;

    const labels = chartData.map(item => item.bulan);
    const pemasukan = chartData.map(item => item.pemasukan);
    const pengeluaran = chartData.map(item => item.pengeluaran);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Pemasukan (Setoran)',
                    data: pemasukan,
                    borderColor: '#15803d',
                    backgroundColor: 'rgba(21, 128, 61, 0.1)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointBackgroundColor: '#15803d'
                },
                {
                    label: 'Pengeluaran (Penarikan)',
                    data: pengeluaran,
                    borderColor: '#e11d48',
                    backgroundColor: 'rgba(225, 29, 72, 0.08)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointBackgroundColor: '#e11d48'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { font: { family: "'Plus Jakarta Sans', sans-serif" } }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(val) {
                            return 'Rp ' + (val / 1000).toLocaleString('id-ID') + 'k';
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
