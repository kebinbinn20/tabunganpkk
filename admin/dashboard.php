<?php
/**
 * =========================================================================================
 * APLIKASI TABUNGAN DIGITAL PKK (TabunganPKK)
 * File: admin/dashboard.php
 * -----------------------------------------------------------------------------------------
 * PENJELASAN MATERI UNTUK PRESENTASI KE GURU:
 * 1. Fungsi Halaman:
 *    Dashboard Administrator menyajikan ringkasan eksekutif (Executive Summary) seluruh
 *    operasional kas tabungan PKK. Admin dapat memantau indikator kunci (KPI):
 *    - Total saldo tabungan seluruh warga/anggota.
 *    - Jumlah anggota terdaftar dan yang berstatus aktif.
 *    - Total akumulasi setoran (uang masuk) dan penarikan (uang keluar).
 *    - Grafik batang perbandingan pemasukan vs pengeluaran 6 bulan terakhir.
 *    - 5 transaksi mutasi terkini.
 *
 * 2. Teknik Pemrograman SQL yang Digunakan:
 *    - Agregasi Bersyarat (SUM CASE WHEN): Menghitung total nominal berdasarkan jenis transaksi
 *      dan status 'berhasil' dalam satu kali query efisien.
 *    - Subquery & Date Formatting: Fungsi DATE_FORMAT(tanggal, '%Y-%m') digunakan untuk
 *      mengelompokkan data transaksi per bulan demi kebutuhan visualisasi grafik Chart.js.
 *    - Keamanan RBAC: Menggunakan fungsi require_role(['admin']) sehingga selain administrator
 *      tidak ada pihak yang dapat mengakses halaman ini.
 * =========================================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

// Memastikan hanya role 'admin' yang dapat membuka halaman ini
require_role(['admin']);

$pdo = getDBConnection();
$pengaturan = get_pengaturan($pdo);

$pageTitle = 'Dashboard Admin';
$pageHeading = 'Dashboard Administrator';
$pageSubheading = 'Ringkasan operasional dan pengelolaan tabungan PKK';

// 1. STATISTIK KEUANGAN & ANGGOTA (QUERY DATABASE)
// Menghitung total semua anggota
$totalAnggota = $pdo->query("SELECT COUNT(*) FROM anggota")->fetchColumn();

// Menghitung anggota yang berstatus 'aktif'
$anggotaAktif = $pdo->query("SELECT COUNT(*) FROM anggota WHERE status = 'aktif'")->fetchColumn();

// Menghitung jumlah akun petugas bendahara
$totalBendahara = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'bendahara'")->fetchColumn();

// Menghitung total seluruh akun pengguna yang terdaftar di sistem
$totalPengguna = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Menghitung jumlah transaksi yang berstatus 'berhasil'
$totalTransaksi = $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status = 'berhasil'")->fetchColumn();

// Menghitung total saldo kas tabungan yang tersedia
$totalSaldo = hitung_total_saldo_semua($pdo);

// Mengambil total setoran dan penarikan yang sah (status = 'berhasil')
$statTrx = $pdo->query("
    SELECT 
        COALESCE(SUM(CASE WHEN jenis_transaksi = 'setoran' AND status = 'berhasil' THEN nominal ELSE 0 END), 0) AS total_setoran,
        COALESCE(SUM(CASE WHEN jenis_transaksi = 'penarikan' AND status = 'berhasil' THEN nominal ELSE 0 END), 0) AS total_penarikan
    FROM transaksi
")->fetch();
$totalSetoran = (float)$statTrx['total_setoran'];
$totalPenarikan = (float)$statTrx['total_penarikan'];

// 2. MENGAMBIL 5 SETORAN TERAKHIR (RELASI TABLE TRANSAKSI DENGAN ANGGOTA)
$stmtSetoranTerbaru = $pdo->query("
    SELECT t.*, a.nama AS nama_anggota, a.nomor_anggota 
    FROM transaksi t
    JOIN anggota a ON a.id = t.anggota_id
    WHERE t.jenis_transaksi = 'setoran' AND t.status = 'berhasil'
    ORDER BY t.tanggal DESC, t.id DESC LIMIT 5
");
$setoranTerbaru = $stmtSetoranTerbaru->fetchAll();

// 3. MENGAMBIL 5 PENARIKAN TERAKHIR
$stmtPenarikanTerbaru = $pdo->query("
    SELECT t.*, a.nama AS nama_anggota, a.nomor_anggota 
    FROM transaksi t
    JOIN anggota a ON a.id = t.anggota_id
    WHERE t.jenis_transaksi = 'penarikan' AND t.status = 'berhasil'
    ORDER BY t.tanggal DESC, t.id DESC LIMIT 5
");
$penarikanTerbaru = $stmtPenarikanTerbaru->fetchAll();

// 4. MEMPERSIAPKAN DATA GRAFIK 6 BULAN TERAKHIR UNTUK CHART.JS
$grafikData = [];
for ($i = 5; $i >= 0; $i--) {
    $bulanTahun = date('Y-m', strtotime("-$i months"));
    $namaBulan = date('M Y', strtotime("-$i months"));

    $stmtG = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN jenis_transaksi = 'setoran' AND status = 'berhasil' THEN nominal ELSE 0 END), 0) AS setoran,
            COALESCE(SUM(CASE WHEN jenis_transaksi = 'penarikan' AND status = 'berhasil' THEN nominal ELSE 0 END), 0) AS penarikan
        FROM transaksi
        WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?
    ");
    $stmtG->execute([$bulanTahun]);
    $gRow = $stmtG->fetch();

    $grafikData[] = [
        'bulan' => $namaBulan,
        'setoran' => (float)$gRow['setoran'],
        'penarikan' => (float)$gRow['penarikan']
    ];
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Banner Sambutan PKK (Warna Hijau Gelap Berkontras Tinggi) -->
<div class="card border-0 mb-4 p-4 text-white" style="background: linear-gradient(135deg, #072e18, #0b4626); border-radius: 18px; box-shadow: 0 12px 28px -4px rgba(7, 46, 24, 0.45); border: 1px solid rgba(255, 255, 255, 0.15);">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <span class="badge bg-white text-dark px-3 py-1 mb-2 fw-bold rounded-pill" style="color: #072e18 !important;">Sistem Digital PKK</span>
            <h3 class="fw-bold mb-1">Selamat Datang di <?= htmlspecialchars($pengaturan['nama_aplikasi']) ?></h3>
            <p class="mb-0 text-white-50"><?= htmlspecialchars($pengaturan['nama_organisasi']) ?> • Periode Aktif: <strong><?= htmlspecialchars($pengaturan['periode_aktif']) ?></strong></p>
        </div>
        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
            <a href="<?= base_url('admin/anggota.php') ?>" class="btn btn-light fw-bold me-2 rounded-pill px-3 shadow-sm" style="color: #072e18;">
                <i class="fa-solid fa-user-plus me-1"></i> Anggota
            </a>
            <a href="<?= base_url('admin/laporan.php') ?>" class="btn btn-outline-light rounded-pill px-3 fw-semibold">
                <i class="fa-solid fa-file-export me-1"></i> Laporan
            </a>
        </div>
    </div>
</div>

<!-- 4 Kartu Statistik Utama -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-green">
            <div>
                <div class="stat-title">Total Saldo Tabungan</div>
                <div class="stat-value text-success"><?= format_rupiah($totalSaldo) ?></div>
                <div class="stat-sub text-muted">Akumulasi seluruh anggota</div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-wallet"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-blue">
            <div>
                <div class="stat-title">Total Anggota PKK</div>
                <div class="stat-value"><?= number_format($totalAnggota) ?> <small class="fs-6 text-muted">Orang</small></div>
                <div class="stat-sub text-success"><i class="fa-solid fa-check me-1"></i><?= $anggotaAktif ?> Anggota Aktif</div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-amber">
            <div>
                <div class="stat-title">Total Setoran Masuk</div>
                <div class="stat-value text-success"><?= format_rupiah($totalSetoran) ?></div>
                <div class="stat-sub text-muted">Pemasukan kas tabungan</div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-circle-arrow-down"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card stat-rose">
            <div>
                <div class="stat-title">Total Penarikan</div>
                <div class="stat-value text-danger"><?= format_rupiah($totalPenarikan) ?></div>
                <div class="stat-sub text-muted"><?= $totalTransaksi ?> Transaksi berhasil</div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-circle-arrow-up"></i>
            </div>
        </div>
    </div>
</div>

<!-- Grafik & Informasi Petugas -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card-pkk h-100">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-chart-line text-success"></i> Perkembangan Tabungan (6 Bulan Terakhir)</h5>
                <span class="badge bg-light text-secondary border">Realtime</span>
            </div>
            <div class="card-pkk-body">
                <canvas id="chartPerkembangan" height="120"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-pkk h-100">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-circle-info text-primary"></i> Ringkasan Sistem</h5>
            </div>
            <div class="card-pkk-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <span class="text-secondary"><i class="fa-solid fa-user-shield me-2 text-danger"></i> Total Admin</span>
                        <span class="badge bg-danger-subtle text-danger rounded-pill fw-bold">1 Akun</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <span class="text-secondary"><i class="fa-solid fa-money-check-dollar me-2 text-warning"></i> Total Bendahara</span>
                        <span class="badge bg-warning-subtle text-warning rounded-pill fw-bold"><?= $totalBendahara ?> Akun</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <span class="text-secondary"><i class="fa-solid fa-user-group me-2 text-success"></i> Total Pengguna Akun</span>
                        <span class="badge bg-success-subtle text-success rounded-pill fw-bold"><?= $totalPengguna ?> User</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <span class="text-secondary"><i class="fa-solid fa-coins me-2 text-info"></i> Minimal Setor</span>
                        <span class="fw-bold text-dark"><?= format_rupiah($pengaturan['nominal_minimal_setor']) ?></span>
                    </li>
                </ul>
                <div class="p-3 bg-light text-center">
                    <a href="<?= base_url('admin/sistem.php') ?>" class="btn btn-sm btn-outline-success fw-semibold">
                        <i class="fa-solid fa-gear me-1"></i> Ubah Pengaturan Sistem
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Aktivitas Transaksi Terbaru: Setoran vs Penarikan -->
<div class="row g-4">
    <!-- Setoran Terbaru -->
    <div class="col-lg-6">
        <div class="card-pkk">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-arrow-trend-up text-success"></i> 5 Setoran Terakhir</h5>
                <a href="<?= base_url('admin/laporan.php?jenis=setoran') ?>" class="small text-success text-decoration-none fw-semibold">Semua Setoran <i class="fa-solid fa-angle-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table-pkk">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Anggota</th>
                            <th>Nominal</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($setoranTerbaru)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Belum ada setoran tercatat.</td></tr>
                        <?php else: ?>
                            <?php foreach ($setoranTerbaru as $trx): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= format_tanggal($trx['tanggal']) ?></div>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($trx['kode_transaksi']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($trx['nama_anggota']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($trx['nomor_anggota']) ?></small>
                                    </td>
                                    <td class="fw-bold text-success">
                                        +<?= format_rupiah($trx['nominal']) ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= base_url('cetak_kuitansi.php?kode=' . urlencode($trx['kode_transaksi'])) ?>" class="btn btn-sm btn-light border" title="Cetak Bukti">
                                            <i class="fa-solid fa-print text-secondary"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Penarikan Terbaru -->
    <div class="col-lg-6">
        <div class="card-pkk">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-arrow-trend-down text-danger"></i> 5 Penarikan Terakhir</h5>
                <a href="<?= base_url('admin/laporan.php?jenis=penarikan') ?>" class="small text-danger text-decoration-none fw-semibold">Semua Penarikan <i class="fa-solid fa-angle-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table-pkk">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Anggota</th>
                            <th>Nominal</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($penarikanTerbaru)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Belum ada transaksi penarikan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($penarikanTerbaru as $trx): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= format_tanggal($trx['tanggal']) ?></div>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($trx['kode_transaksi']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($trx['nama_anggota']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($trx['nomor_anggota']) ?></small>
                                    </td>
                                    <td class="fw-bold text-danger">
                                        -<?= format_rupiah($trx['nominal']) ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= base_url('cetak_kuitansi.php?kode=' . urlencode($trx['kode_transaksi'])) ?>" class="btn btn-sm btn-light border" title="Cetak Bukti">
                                            <i class="fa-solid fa-print text-secondary"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('chartPerkembangan').getContext('2d');
    const chartData = <?= json_encode($grafikData) ?>;

    const labels = chartData.map(item => item.bulan);
    const setoranData = chartData.map(item => item.setoran);
    const penarikanData = chartData.map(item => item.penarikan);

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Setoran (Masuk)',
                    data: setoranData,
                    backgroundColor: 'rgba(21, 128, 61, 0.75)',
                    borderColor: '#15803d',
                    borderWidth: 1,
                    borderRadius: 6
                },
                {
                    label: 'Penarikan (Keluar)',
                    data: penarikanData,
                    backgroundColor: 'rgba(225, 29, 72, 0.75)',
                    borderColor: '#e11d48',
                    borderWidth: 1,
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 14,
                        font: { family: "'Plus Jakarta Sans', sans-serif", size: 12 }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + (value / 1000).toLocaleString('id-ID') + 'k';
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
