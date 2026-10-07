<?php
/**
 * TabunganPKK - Riwayat Transaksi Tabungan (Bendahara)
 *
 * Halaman ini menggabungkan transaksi dengan nama anggota dan petugas, lalu
 * menerapkan filter pencarian, jenis, tanggal, dan anggota sebelum hasil
 * ditampilkan dalam urutan terbaru.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

require_role(['bendahara', 'admin']);

$pdo = getDBConnection();
$pageTitle = 'Riwayat Transaksi';
$pageHeading = 'Riwayat Transaksi Tabungan';
$pageSubheading = 'Seluruh mutasi setoran dan penarikan yang telah dicatat';

// Nilai filter berasal dari URL dan dipasang ke prepared statement sebagai parameter.
$search = trim($_GET['q'] ?? '');
$filterJenis = trim($_GET['jenis'] ?? '');
$filterTanggal = trim($_GET['tanggal'] ?? '');
$filterAnggota = (int)($_GET['anggota_id'] ?? 0);

$sql = "
    SELECT t.*, a.nama AS nama_anggota, a.nomor_anggota, u.nama AS nama_petugas
    FROM transaksi t
    JOIN anggota a ON a.id = t.anggota_id
    LEFT JOIN users u ON u.id = t.petugas_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    // Cari kode transaksi, nama/no anggota, atau keterangan transaksi.
    $sql .= " AND (t.kode_transaksi LIKE ? OR a.nama LIKE ? OR a.nomor_anggota LIKE ? OR t.keterangan LIKE ?)";
    $term = "%$search%";
    $params = [$term, $term, $term, $term];
}

if (!empty($filterJenis)) {
    // Tambahkan filter jenis bila hanya setoran atau penarikan yang dipilih.
    $sql .= " AND t.jenis_transaksi = ?";
    $params[] = $filterJenis;
}

if (!empty($filterTanggal)) {
    // Batasi hasil ke satu tanggal bila tanggal diisi.
    $sql .= " AND t.tanggal = ?";
    $params[] = $filterTanggal;
}

if ($filterAnggota > 0) {
    // Batasi hasil ke anggota tertentu bila dropdown bukan pilihan semua.
    $sql .= " AND t.anggota_id = ?";
    $params[] = $filterAnggota;
}

$sql .= " ORDER BY t.tanggal DESC, t.id DESC";

// Jalankan query dengan parameter filter agar nilainya tidak menjadi sintaks SQL.
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarTransaksi = $stmt->fetchAll();

$semuaAnggota = $pdo->query("SELECT id, nomor_anggota, nama FROM anggota ORDER BY nama ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Search & Filter Bar -->
<div class="card-pkk mb-4">
    <div class="card-pkk-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="No. TRX, Nama, Keterangan..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="jenis" class="form-select form-select-sm">
                    <option value="">Semua Jenis</option>
                    <option value="setoran" <?= $filterJenis === 'setoran' ? 'selected' : '' ?>>Setoran</option>
                    <option value="penarikan" <?= $filterJenis === 'penarikan' ? 'selected' : '' ?>>Penarikan</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="anggota_id" class="form-select form-select-sm">
                    <option value="0">-- Semua Anggota --</option>
                    <?php foreach ($semuaAnggota as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= $filterAnggota == $ag['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ag['nama']) ?> (<?= htmlspecialchars($ag['nomor_anggota']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= htmlspecialchars($filterTanggal) ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-pkk-primary btn-sm flex-grow-1">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <a href="<?= base_url('bendahara/riwayat.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Riwayat Transaksi -->
<div class="card-pkk">
    <div class="card-pkk-header">
        <h5><i class="fa-solid fa-clock-rotate-left text-success"></i> Riwayat Transaksi</h5>
        <span class="badge bg-light text-secondary border"><?= count($daftarTransaksi) ?> Transaksi</span>
    </div>
    <div class="table-responsive">
        <table class="table-pkk">
            <thead>
                <tr>
                    <th>No. Transaksi</th>
                    <th>Tanggal</th>
                    <th>Nama Anggota</th>
                    <th>Jenis</th>
                    <th>Nominal</th>
                    <th>Metode</th>
                    <th>Keterangan</th>
                    <th>Petugas</th>
                    <th>Status</th>
                    <th class="text-center">Kwitansi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarTransaksi)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">
                            Tidak ada transaksi yang cocok dengan kriteria filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarTransaksi as $trx): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-dark small">
                                <?= htmlspecialchars($trx['kode_transaksi']) ?>
                            </td>
                            <td class="small"><?= format_tanggal($trx['tanggal']) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($trx['nama_anggota']) ?></div>
                                <span class="badge bg-light text-secondary border font-monospace small"><?= htmlspecialchars($trx['nomor_anggota']) ?></span>
                            </td>
                            <td>
                                <span class="badge badge-trx-<?= $trx['jenis_transaksi'] ?> text-uppercase">
                                    <?= $trx['jenis_transaksi'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold fs-6 <?= $trx['jenis_transaksi'] === 'setoran' ? 'text-success' : 'text-danger' ?>">
                                    <?= $trx['jenis_transaksi'] === 'setoran' ? '+' : '-' ?><?= format_rupiah($trx['nominal']) ?>
                                </span>
                            </td>
                            <td class="small"><?= htmlspecialchars($trx['metode_pembayaran'] ?: 'Tunai') ?></td>
                            <td class="small text-muted text-truncate" style="max-width: 180px;">
                                <?= htmlspecialchars($trx['keterangan'] ?: '-') ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($trx['nama_petugas'] ?: '-') ?></td>
                            <td>
                                <span class="badge badge-status-<?= $trx['status'] ?>">
                                    <?= strtoupper($trx['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="<?= base_url('cetak_kuitansi.php?kode=' . urlencode($trx['kode_transaksi'])) ?>" class="btn btn-sm btn-light border text-primary" title="Cetak Kwitansi">
                                    <i class="fa-solid fa-print"></i>
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
