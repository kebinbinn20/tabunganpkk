<?php
/**
 * TabunganPKK - Laporan Keuangan Bendahara
 *
 * Menyediakan laporan mutasi pada rentang tanggal atau rekap saldo anggota.
 * Parameter GET mempertahankan filter saat menampilkan halaman maupun
 * mengunduh hasil sebagai file spreadsheet yang dapat dibuka di Excel.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

require_role(['bendahara', 'admin']);

$pdo = getDBConnection();
$pengaturan = get_pengaturan($pdo);

$pageTitle = 'Laporan Keuangan Bendahara';
$pageHeading = 'Laporan Keuangan Tabungan';
$pageSubheading = 'Rekapitulasi pemasukan, pengeluaran kas, dan saldo simpanan anggota';

$jenisLaporan = $_GET['tipe'] ?? 'mutasi'; // 'mutasi' atau 'rekap_anggota'
$anggotaId = (int)($_GET['anggota_id'] ?? 0);
$tglAwal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tglAkhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$isExportExcel = isset($_GET['export']) && $_GET['export'] === 'excel';

// Saldo kas keseluruhan tidak dibatasi tanggal; kartu ini menunjukkan posisi saat ini.
$totalSaldoKas = hitung_total_saldo_semua($pdo);

// Query Data Mutasi
$sqlMutasi = "
    SELECT t.*, a.nama AS nama_anggota, a.nomor_anggota
    FROM transaksi t
    JOIN anggota a ON a.id = t.anggota_id
    WHERE t.status = 'berhasil' AND t.tanggal BETWEEN ? AND ?
";
$paramsMutasi = [$tglAwal, $tglAkhir];

if ($anggotaId > 0) {
    $sqlMutasi .= " AND t.anggota_id = ?";
    $paramsMutasi[] = $anggotaId;
}

$sqlMutasi .= " ORDER BY t.tanggal DESC, t.id DESC";
$stmtM = $pdo->prepare($sqlMutasi);
$stmtM->execute($paramsMutasi);
$dataMutasi = $stmtM->fetchAll();

// Hitung total setoran & penarikan pada rentang
$sumSetoran = 0;
$sumPenarikan = 0;
foreach ($dataMutasi as $m) {
    if ($m['jenis_transaksi'] === 'setoran') $sumSetoran += (float)$m['nominal'];
    else $sumPenarikan += (float)$m['nominal'];
}

// Query Rekap Saldo Per Anggota
$sqlRekap = "
    SELECT a.*,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'setoran' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) AS total_setor,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'penarikan' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) AS total_tarik,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'setoran' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) -
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'penarikan' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) AS saldo_anggota
    FROM anggota a
    LEFT JOIN transaksi t ON t.anggota_id = a.id
    WHERE a.status = 'aktif'
";
$paramsRekap = [];
if ($anggotaId > 0) {
    $sqlRekap .= " AND a.id = ?";
    $paramsRekap[] = $anggotaId;
}
$sqlRekap .= " GROUP BY a.id ORDER BY a.nama ASC";
$stmtR = $pdo->prepare($sqlRekap);
$stmtR->execute($paramsRekap);
$dataRekapAnggota = $stmtR->fetchAll();

// Export Excel Handler
if ($isExportExcel) {
    $fileName = 'Laporan_Bendahara_' . date('Ymd_His') . '.xls';
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$fileName");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr><th colspan='6' style='font-size:16px; font-weight:bold;'>LAPORAN KEUANGAN BENDAHARA - TABUNGAN PKK</th></tr>";
    echo "<tr><th colspan='6'>" . htmlspecialchars($pengaturan['nama_organisasi']) . " (" . $tglAwal . " s/d " . $tglAkhir . ")</th></tr>";
    
    if ($jenisLaporan === 'rekap_anggota') {
        echo "<tr><th>No</th><th>No Anggota</th><th>Nama</th><th>Total Setoran</th><th>Total Penarikan</th><th>Saldo</th></tr>";
        $no = 1;
        foreach ($dataRekapAnggota as $r) {
            echo "<tr><td>" . $no++ . "</td><td>" . $r['nomor_anggota'] . "</td><td>" . $r['nama'] . "</td><td>" . $r['total_setor'] . "</td><td>" . $r['total_tarik'] . "</td><td>" . $r['saldo_anggota'] . "</td></tr>";
        }
    } else {
        echo "<tr><th>No</th><th>Kode Trx</th><th>Tanggal</th><th>No Anggota</th><th>Nama Anggota</th><th>Jenis</th><th>Nominal</th></tr>";
        $no = 1;
        foreach ($dataMutasi as $m) {
            echo "<tr><td>" . $no++ . "</td><td>" . $m['kode_transaksi'] . "</td><td>" . $m['tanggal'] . "</td><td>" . $m['nomor_anggota'] . "</td><td>" . $m['nama_anggota'] . "</td><td>" . strtoupper($m['jenis_transaksi']) . "</td><td>" . $m['nominal'] . "</td></tr>";
        }
        echo "<tr><td colspan='6'>TOTAL SETORAN</td><td>$sumSetoran</td></tr>";
        echo "<tr><td colspan='6'>TOTAL PENARIKAN</td><td>$sumPenarikan</td></tr>";
    }
    echo "</table>";
    exit;
}

$semuaAnggota = $pdo->query("SELECT id, nomor_anggota, nama FROM anggota ORDER BY nama ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Tab Pilihan & Tombol Export -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">
    <div class="btn-group" role="group">
        <a href="<?= base_url('bendahara/laporan.php?tipe=mutasi') ?>" class="btn <?= $jenisLaporan === 'mutasi' ? 'btn-pkk-primary' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-list-check me-1"></i> Rekap Mutasi Transaksi
        </a>
        <a href="<?= base_url('bendahara/laporan.php?tipe=rekap_anggota') ?>" class="btn <?= $jenisLaporan === 'rekap_anggota' ? 'btn-pkk-primary' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-users-viewfinder me-1"></i> Rekap Saldo Per Anggota
        </a>
    </div>

    <div class="d-flex gap-2">
        <?php
        $exportQuery = $_GET;
        $exportQuery['export'] = 'excel';
        $exportUrl = '?' . http_build_query($exportQuery);
        ?>
        <a href="<?= $exportUrl ?>" class="btn btn-success">
            <i class="fa-solid fa-file-excel me-1"></i> Export Excel
        </a>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fa-solid fa-print me-1"></i> Cetak Laporan
        </button>
    </div>
</div>

<!-- Form Filter (Hidden on Print) -->
<div class="card-pkk mb-4 no-print">
    <div class="card-pkk-body py-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <input type="hidden" name="tipe" value="<?= htmlspecialchars($jenisLaporan) ?>">

            <div class="col-md-4">
                <label class="form-label small fw-semibold">Pilih Anggota</label>
                <select name="anggota_id" class="form-select form-select-sm">
                    <option value="0">-- Semua Anggota PKK --</option>
                    <?php foreach ($semuaAnggota as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= $anggotaId == $ag['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ag['nama']) ?> (<?= htmlspecialchars($ag['nomor_anggota']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold">Dari Tanggal</label>
                <input type="date" name="tgl_awal" class="form-control form-control-sm" value="<?= htmlspecialchars($tglAwal) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold">Sampai Tanggal</label>
                <input type="date" name="tgl_akhir" class="form-control form-control-sm" value="<?= htmlspecialchars($tglAkhir) ?>">
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-pkk-primary btn-sm flex-grow-1">Filter</button>
                <a href="<?= base_url('bendahara/laporan.php?tipe=' . $jenisLaporan) ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Ringkasan Statistik Laporan -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card stat-green">
            <div>
                <div class="stat-title">Total Uang Tabungan (Kas)</div>
                <div class="stat-value text-success"><?= format_rupiah($totalSaldoKas) ?></div>
            </div>
            <div class="stat-icon"><i class="fa-solid fa-vault"></i></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-blue">
            <div>
                <div class="stat-title">Pemasukan Setoran Periode Ini</div>
                <div class="stat-value text-primary"><?= format_rupiah($sumSetoran) ?></div>
            </div>
            <div class="stat-icon"><i class="fa-solid fa-arrow-down"></i></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-rose">
            <div>
                <div class="stat-title">Pengeluaran Penarikan Periode Ini</div>
                <div class="stat-value text-danger"><?= format_rupiah($sumPenarikan) ?></div>
            </div>
            <div class="stat-icon"><i class="fa-solid fa-arrow-up"></i></div>
        </div>
    </div>
</div>

<?php if ($jenisLaporan === 'rekap_anggota'): ?>
    <!-- TABEL REKAP ANGGOTA -->
    <div class="card-pkk">
        <div class="card-pkk-header">
            <h5><i class="fa-solid fa-users text-success"></i> Rekap Saldo Seluruh Anggota</h5>
            <span class="badge bg-light text-secondary border"><?= count($dataRekapAnggota) ?> Anggota</span>
        </div>
        <div class="table-responsive">
            <table class="table-pkk">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>No. Anggota</th>
                        <th>Nama Anggota</th>
                        <th>No. HP</th>
                        <th class="text-end">Total Disetor</th>
                        <th class="text-end">Total Ditarik</th>
                        <th class="text-end">Saldo Tabungan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    $grandSetor = 0; $grandTarik = 0; $grandSaldo = 0;
                    foreach ($dataRekapAnggota as $r): 
                        $grandSetor += (float)$r['total_setor'];
                        $grandTarik += (float)$r['total_tarik'];
                        $grandSaldo += (float)$r['saldo_anggota'];
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="font-monospace fw-bold text-dark"><?= htmlspecialchars($r['nomor_anggota']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($r['nama']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($r['nomor_hp'] ?: '-') ?></td>
                            <td class="text-end text-success fw-bold"><?= format_rupiah($r['total_setor']) ?></td>
                            <td class="text-end text-danger fw-bold"><?= format_rupiah($r['total_tarik']) ?></td>
                            <td class="text-end fw-extrabold fs-6 <?= (float)$r['saldo_anggota'] > 0 ? 'text-primary' : 'text-muted' ?>">
                                <?= format_rupiah($r['saldo_anggota']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-light fw-bold">
                        <td colspan="4" class="text-center text-uppercase">TOTAL KESELURUHAN</td>
                        <td class="text-end text-success"><?= format_rupiah($grandSetor) ?></td>
                        <td class="text-end text-danger"><?= format_rupiah($grandTarik) ?></td>
                        <td class="text-end text-primary fs-6"><?= format_rupiah($grandSaldo) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- TABEL MUTASI -->
    <div class="card-pkk">
        <div class="card-pkk-header">
            <h5><i class="fa-solid fa-file-invoice-dollar text-success"></i> Rincian Mutasi Kas (<?= format_tanggal($tglAwal) ?> s/d <?= format_tanggal($tglAkhir) ?>)</h5>
            <span class="badge bg-light text-secondary border"><?= count($dataMutasi) ?> Transaksi</span>
        </div>
        <div class="table-responsive">
            <table class="table-pkk">
                <thead>
                    <tr>
                        <th>No. Transaksi</th>
                        <th>Tanggal</th>
                        <th>Anggota</th>
                        <th>Jenis</th>
                        <th>Nominal</th>
                        <th>Metode</th>
                        <th>Keterangan</th>
                        <th class="text-center no-print">Kwitansi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataMutasi)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada transaksi pada rentang tanggal ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dataMutasi as $m): ?>
                            <tr>
                                <td class="font-monospace fw-bold text-dark small"><?= htmlspecialchars($m['kode_transaksi']) ?></td>
                                <td class="small"><?= format_tanggal($m['tanggal']) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($m['nama_anggota']) ?></div>
                                    <small class="text-muted font-monospace"><?= htmlspecialchars($m['nomor_anggota']) ?></small>
                                </td>
                                <td><span class="badge badge-trx-<?= $m['jenis_transaksi'] ?> text-uppercase"><?= $m['jenis_transaksi'] ?></span></td>
                                <td>
                                    <span class="fw-bold <?= $m['jenis_transaksi'] === 'setoran' ? 'text-success' : 'text-danger' ?>">
                                        <?= $m['jenis_transaksi'] === 'setoran' ? '+' : '-' ?><?= format_rupiah($m['nominal']) ?>
                                    </span>
                                </td>
                                <td class="small"><?= htmlspecialchars($m['metode_pembayaran'] ?: 'Tunai') ?></td>
                                <td class="small text-muted text-truncate" style="max-width: 180px;"><?= htmlspecialchars($m['keterangan'] ?: '-') ?></td>
                                <td class="text-center no-print">
                                    <a href="<?= base_url('cetak_kuitansi.php?kode=' . urlencode($m['kode_transaksi'])) ?>" class="btn btn-sm btn-light border" title="Cetak Bukti">
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
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
