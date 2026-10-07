<?php
/**
 * TabunganPKK - Riwayat Transaksi Anggota (Pribadi)
 *
 * Membuat saldo berjalan dari transaksi berhasil dalam urutan waktu naik,
 * kemudian menampilkan mutasi terbaru. Peta saldo memakai ID transaksi agar
 * nilai saldo akhir tetap sesuai walau daftar tampilan difilter.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

require_role(['pengguna', 'admin', 'bendahara']);

$pdo = getDBConnection();
// Ambil akun aktif dari sesi dan temukan profil anggota yang terkait.
$userId = $_SESSION['user_id'];
$anggota = get_anggota_by_user_id($pdo, $userId);
$anggotaId = $anggota['id'];

// Baca filter jenis transaksi dari URL; nilai kosong berarti tampilkan semua jenis.
$filterJenis = trim($_GET['jenis'] ?? ''); // 'setoran', 'penarikan', atau ''

// 1. Ambil seluruh transaksi berurutan ASC untuk menghitung running balance (saldo setelah transaksi)
// Ambil transaksi berhasil dari yang paling lama untuk menghitung saldo kumulatif.
$stmtAll = $pdo->prepare("
    SELECT id, kode_transaksi, jenis_transaksi, nominal, tanggal, keterangan, status, metode_pembayaran
    FROM transaksi
    WHERE anggota_id = ? AND status = 'berhasil'
    ORDER BY tanggal ASC, id ASC
");
$stmtAll->execute([$anggotaId]);
$semuaTransaksi = $stmtAll->fetchAll();

// Peta ini menghubungkan ID transaksi dengan saldo setelah transaksi tersebut.
$runningBalanceMap = [];
// Saldo awal sebelum mutasi pertama adalah nol.
$saldoBerjalan = 0;
foreach ($semuaTransaksi as $trx) {
    // Setoran menambah saldo, sedangkan penarikan menguranginya.
    if ($trx['jenis_transaksi'] === 'setoran') {
        $saldoBerjalan += (float)$trx['nominal'];
    } else {
        $saldoBerjalan -= (float)$trx['nominal'];
    }
    // Simpan hasil saldo pada ID transaksi agar bisa ditampilkan setelah diurutkan terbalik.
    $runningBalanceMap[$trx['id']] = $saldoBerjalan;
}

// 2. Query transaksi untuk ditampilkan sesuai filter
// Siapkan query daftar mutasi dan batasi awalnya pada anggota yang sedang login.
$sql = "
    SELECT t.*, u.nama AS nama_petugas
    FROM transaksi t
    LEFT JOIN users u ON u.id = t.petugas_id
    WHERE t.anggota_id = ?
";
$params = [$anggotaId];

if (!empty($filterJenis)) {
    // Tambahkan kondisi jenis hanya jika pengguna memilih filter.
    $sql .= " AND t.jenis_transaksi = ?";
    $params[] = $filterJenis;
}

$sql .= " ORDER BY t.tanggal DESC, t.id DESC";

// Jalankan query dengan parameter yang sudah dikumpulkan dari filter.
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarTrx = $stmt->fetchAll();

$pageTitle = 'Riwayat Transaksi Saya';
$pageHeading = 'Riwayat Mutasi Tabungan';
$pageSubheading = 'Catatan lengkap seluruh setoran dan penarikan tabungan Anda';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Filter Kategori Transaksi -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div class="btn-group" role="group">
        <a href="<?= base_url('pengguna/riwayat.php') ?>" class="btn <?= empty($filterJenis) ? 'btn-pkk-primary' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-list-ul me-1"></i> Semua Transaksi
        </a>
        <a href="<?= base_url('pengguna/riwayat.php?jenis=setoran') ?>" class="btn <?= $filterJenis === 'setoran' ? 'btn-success fw-bold' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-arrow-down me-1"></i> Setoran Saja
        </a>
        <a href="<?= base_url('pengguna/riwayat.php?jenis=penarikan') ?>" class="btn <?= $filterJenis === 'penarikan' ? 'btn-danger fw-bold' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-arrow-up me-1"></i> Penarikan Saja
        </a>
    </div>

    <div>
        <a href="<?= base_url('pengguna/ajukan_penarikan.php') ?>" class="btn btn-outline-danger">
            <i class="fa-solid fa-hand-holding-dollar me-1"></i> Ajukan Penarikan
        </a>
    </div>
</div>

<div class="card-pkk">
    <div class="card-pkk-header">
        <h5><i class="fa-solid fa-receipt text-success"></i> Buku Tabungan Digital Anda</h5>
        <span class="badge bg-light text-secondary border"><?= count($daftarTrx) ?> Catatan</span>
    </div>
    <div class="table-responsive">
        <table class="table-pkk">
            <thead>
                <tr>
                    <th>No. Transaksi</th>
                    <th>Tanggal</th>
                    <th>Jenis Transaksi</th>
                    <th>Nominal</th>
                    <th>Keterangan</th>
                    <th>Saldo Setelah Transaksi</th>
                    <th>Status</th>
                    <th class="text-center">Kwitansi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarTrx)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-folder-open fs-3 d-block mb-2"></i>
                            Belum ada transaksi tabungan yang tercatat untuk filter ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarTrx as $t): 
                        $saldoSesudah = $runningBalanceMap[$t['id']] ?? null;
                    ?>
                        <tr>
                            <td class="font-monospace fw-bold text-dark small">
                                <?= htmlspecialchars($t['kode_transaksi']) ?>
                            </td>
                            <td class="small"><?= format_tanggal($t['tanggal']) ?></td>
                            <td>
                                <span class="badge badge-trx-<?= $t['jenis_transaksi'] ?> text-uppercase">
                                    <?= $t['jenis_transaksi'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold fs-6 <?= $t['jenis_transaksi'] === 'setoran' ? 'text-success' : 'text-danger' ?>">
                                    <?= $t['jenis_transaksi'] === 'setoran' ? '+' : '-' ?><?= format_rupiah($t['nominal']) ?>
                                </span>
                            </td>
                            <td class="small text-muted text-truncate" style="max-width: 220px;">
                                <?= htmlspecialchars($t['keterangan'] ?: '-') ?>
                            </td>
                            <td class="fw-bold text-dark">
                                <?= $saldoSesudah !== null ? format_rupiah($saldoSesudah) : '-' ?>
                            </td>
                            <td>
                                <span class="badge badge-status-<?= $t['status'] ?>">
                                    <?= strtoupper($t['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="<?= base_url('cetak_kuitansi.php?kode=' . urlencode($t['kode_transaksi'])) ?>" class="btn btn-sm btn-light border text-primary" title="Cetak Bukti Transaksi">
                                    <i class="fa-solid fa-print"></i> Cetak
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
