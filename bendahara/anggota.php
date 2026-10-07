<?php
/**
 * TabunganPKK - Data Anggota & Riwayat Saldo (Bendahara)
 *
 * Bendahara dapat mencari anggota, melihat rekap saldo yang dihitung dari
 * transaksi berhasil, membuka mutasi per anggota, dan menuju form setoran
 * atau penarikan dengan anggota sudah dipilih.
 */

// Muat PDO dan helper umum sebelum pemeriksaan role dan query saldo.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

// Bendahara dan admin diizinkan melihat daftar serta saldo anggota.
require_role(['bendahara', 'admin']);

// Buka koneksi database yang dipakai untuk pencarian dan riwayat.
$pdo = getDBConnection();
$pageTitle = 'Data Anggota Tabungan';
$pageHeading = 'Data Anggota Tabungan';
$pageSubheading = 'Daftar anggota PKK, saldo simpanan berjalan, dan riwayat transaksi';

// Bersihkan kata pencarian dan baca ID anggota untuk panel detail transaksi.
$search = trim($_GET['q'] ?? '');
$detailAnggotaId = (int)($_GET['detail_id'] ?? 0);

// Gabungkan data anggota dengan transaksi untuk menghitung saldo dan total mutasi.
$sql = "
    SELECT a.*,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'setoran' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) -
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'penarikan' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) AS saldo_tabungan,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'setoran' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) AS total_setoran,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'penarikan' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) AS total_penarikan
    FROM anggota a
    LEFT JOIN transaksi t ON t.anggota_id = a.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    // Cari anggota berdasarkan nama, nomor anggota, telepon, atau alamat.
    $sql .= " AND (a.nama LIKE ? OR a.nomor_anggota LIKE ? OR a.nomor_hp LIKE ? OR a.alamat LIKE ?)";
    $term = "%$search%";
    $params = [$term, $term, $term, $term];
}

$sql .= " GROUP BY a.id ORDER BY a.nama ASC";
// Jalankan daftar anggota memakai parameter pencarian yang sudah dikumpulkan.
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarAnggota = $stmt->fetchAll();

// Jika ada permintaan melihat riwayat mutasi anggota spesifik
$detailAnggota = null;
$riwayatAnggota = [];
if ($detailAnggotaId > 0) {
    // Ambil profil anggota yang dipilih untuk panel riwayat.
    $stmtD = $pdo->prepare("SELECT * FROM anggota WHERE id = ?");
    $stmtD->execute([$detailAnggotaId]);
    $detailAnggota = $stmtD->fetch();

    // Query mutasi hanya dilakukan bila profil anggota ditemukan.
    if ($detailAnggota) {
        // Ambil mutasi dan nama petugas yang menangani setiap transaksi.
        $stmtR = $pdo->prepare("
            SELECT t.*, u.nama AS nama_petugas
            FROM transaksi t
            LEFT JOIN users u ON u.id = t.petugas_id
            WHERE t.anggota_id = ?
            ORDER BY t.tanggal DESC, t.id DESC
        ");
        $stmtR->execute([$detailAnggotaId]);
        $riwayatAnggota = $stmtR->fetchAll();
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Search Bar & Setor Cepat Button -->
<div class="row g-3 mb-4 align-items-center">
    <div class="col-md-7">
        <form method="GET" action="" class="input-group">
            <span class="input-group-text bg-white text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" name="q" class="form-control" placeholder="Cari nama anggota, no. anggota, no. HP..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-pkk-primary">Cari</button>
            <?php if (!empty($search)): ?>
                <a href="<?= base_url('bendahara/anggota.php') ?>" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="col-md-5 text-md-end">
        <a href="<?= base_url('bendahara/setoran.php') ?>" class="btn btn-pkk-primary me-2">
            <i class="fa-solid fa-circle-arrow-down me-1"></i> Catat Setoran Baru
        </a>
        <a href="<?= base_url('bendahara/penarikan.php') ?>" class="btn btn-outline-danger">
            <i class="fa-solid fa-circle-arrow-up me-1"></i> Catat Penarikan
        </a>
    </div>
</div>

<!-- Modal Detail Riwayat Transaksi Anggota (jika detail_id diklik) -->
<?php if ($detailAnggota): ?>
    <div class="card-pkk mb-4 border-success">
        <div class="card-pkk-header bg-success-subtle">
            <h5 class="text-success"><i class="fa-solid fa-clock-rotate-left me-1"></i> Riwayat Transaksi: <?= htmlspecialchars($detailAnggota['nama']) ?> (<?= htmlspecialchars($detailAnggota['nomor_anggota']) ?>)</h5>
            <a href="<?= base_url('bendahara/anggota.php') ?>" class="btn btn-sm btn-outline-secondary">Tutup Riwayat</a>
        </div>
        <div class="card-pkk-body pb-0">
            <?php $saldoAnggotaIni = hitung_saldo_anggota($pdo, $detailAnggota['id']); ?>
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <div class="p-2 border rounded bg-light">
                        <small class="text-muted d-block">Saldo Tabungan Saat Ini</small>
                        <span class="fs-5 fw-bold text-success"><?= format_rupiah($saldoAnggotaIni['saldo']) ?></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 border rounded bg-light">
                        <small class="text-muted d-block">Total Setoran</small>
                        <span class="fs-6 fw-bold text-dark"><?= format_rupiah($saldoAnggotaIni['total_setoran']) ?></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-2 border rounded bg-light">
                        <small class="text-muted d-block">Total Penarikan</small>
                        <span class="fs-6 fw-bold text-danger"><?= format_rupiah($saldoAnggotaIni['total_penarikan']) ?></span>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-pkk mb-3">
                    <thead>
                        <tr>
                            <th>No. Transaksi</th>
                            <th>Tanggal</th>
                            <th>Jenis</th>
                            <th>Nominal</th>
                            <th>Metode</th>
                            <th>Keterangan</th>
                            <th>Petugas</th>
                            <th>Kwitansi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayatAnggota)): ?>
                            <tr><td colspan="8" class="text-center py-3 text-muted">Belum ada transaksi pada anggota ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($riwayatAnggota as $trx): ?>
                                <tr>
                                    <td class="font-monospace fw-bold text-dark small"><?= htmlspecialchars($trx['kode_transaksi']) ?></td>
                                    <td class="small"><?= format_tanggal($trx['tanggal']) ?></td>
                                    <td><span class="badge badge-trx-<?= $trx['jenis_transaksi'] ?> text-uppercase"><?= $trx['jenis_transaksi'] ?></span></td>
                                    <td class="fw-bold <?= $trx['jenis_transaksi'] === 'setoran' ? 'text-success' : 'text-danger' ?>">
                                        <?= $trx['jenis_transaksi'] === 'setoran' ? '+' : '-' ?><?= format_rupiah($trx['nominal']) ?>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($trx['metode_pembayaran'] ?: 'Tunai') ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($trx['keterangan'] ?: '-') ?></td>
                                    <td class="small"><?= htmlspecialchars($trx['nama_petugas'] ?: '-') ?></td>
                                    <td>
                                        <a href="<?= base_url('cetak_kuitansi.php?kode=' . urlencode($trx['kode_transaksi'])) ?>" class="btn btn-sm btn-light border py-0 px-2" title="Cetak Kwitansi">
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
    </div>
<?php endif; ?>

<!-- Tabel Seluruh Anggota PKK -->
<div class="card-pkk">
    <div class="card-pkk-header">
        <h5><i class="fa-solid fa-address-book text-success"></i> Data Anggota & Rekap Saldo</h5>
        <span class="badge bg-light text-secondary border"><?= count($daftarAnggota) ?> Anggota Terdata</span>
    </div>
    <div class="table-responsive">
        <table class="table-pkk">
            <thead>
                <tr>
                    <th>No. Anggota</th>
                    <th>Nama Anggota</th>
                    <th>No. WhatsApp</th>
                    <th>Alamat</th>
                    <th class="text-end">Total Setor</th>
                    <th class="text-end">Total Tarik</th>
                    <th class="text-end">Saldo Tabungan</th>
                    <th class="text-center">Aksi Cepat</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarAnggota)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada data anggota yang ditemukan.</td></tr>
                <?php else: ?>
                    <?php foreach ($daftarAnggota as $ag): ?>
                        <tr>
                            <td>
                                <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace fw-bold">
                                    <?= htmlspecialchars($ag['nomor_anggota']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($ag['nama']) ?></div>
                                <small class="text-muted">Bergabung: <?= format_tanggal($ag['tanggal_gabung']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($ag['nomor_hp'] ?: '-') ?></td>
                            <td>
                                <div class="text-truncate text-muted small" style="max-width: 180px;">
                                    <?= htmlspecialchars($ag['alamat'] ?: '-') ?>
                                </div>
                            </td>
                            <td class="text-end text-success fw-bold small"><?= format_rupiah($ag['total_setoran']) ?></td>
                            <td class="text-end text-danger fw-bold small"><?= format_rupiah($ag['total_penarikan']) ?></td>
                            <td class="text-end fw-extrabold fs-6 <?= (float)$ag['saldo_tabungan'] > 0 ? 'text-primary' : 'text-muted' ?>">
                                <?= format_rupiah($ag['saldo_tabungan']) ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <!-- Riwayat -->
                                    <a href="<?= base_url('bendahara/anggota.php?detail_id=' . $ag['id']) ?>" class="btn btn-light border text-info" title="Lihat Riwayat Transaksi">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                    </a>
                                    <!-- Setor ke anggota ini -->
                                    <a href="<?= base_url('bendahara/setoran.php?anggota_id=' . $ag['id']) ?>" class="btn btn-light border text-success" title="Tambah Setoran">
                                        <i class="fa-solid fa-plus"></i> Setor
                                    </a>
                                    <!-- Tarik tabungan anggota ini -->
                                    <a href="<?= base_url('bendahara/penarikan.php?anggota_id=' . $ag['id']) ?>" class="btn btn-light border text-danger" title="Catat Penarikan">
                                        <i class="fa-solid fa-minus"></i> Tarik
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
