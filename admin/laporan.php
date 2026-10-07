<?php
/**
 * =========================================================================================
 * APLIKASI TABUNGAN DIGITAL PKK (TabunganPKK)
 * File: admin/laporan.php
 * -----------------------------------------------------------------------------------------
 * PENJELASAN MATERI UNTUK PRESENTASI KE GURU:
 * 1. Modul Pelaporan Keuangan (Financial Reporting):
 *    Menyediakan dua mode laporan akuntansi:
 *    a) Laporan Mutasi Transaksi: Memantau arus kas masuk (setoran) dan kas keluar (penarikan)
 *       lengkap dengan filter harian, bulanan, tahunan, maupun rentang tanggal kustom.
 *    b) Laporan Rekap Saldo Seluruh Anggota: Memantau total simpanan bersih masing-masing warga PKK.
 *
 * 2. Teknik Export Excel Native (Tanpa Library Berat):
 *    Aplikasi memanfaatkan manipulasi Header HTTP resmi:
 *    - header("Content-Type: application/vnd.ms-excel; charset=utf-8")
 *    - header("Content-Disposition: attachment; filename=...")
 *    Ketika browser menerima header ini, browser otomatis mengunduhnya sebagai file .xls
 *    yang langsung dapat dibuka di Microsoft Excel atau spreadsheet lainnya.
 *
 * 3. Teknik Cetak / Export PDF:
 *    Mengintegrasikan fungsi JavaScript 'window.print()' dipadukan dengan stylesheet cetak
 *    '@media print' di style.css untuk menghasilkan dokumen laporan rapi dengan kop organisasi.
 * =========================================================================================
 */

// Muat koneksi dan helper untuk query, hak akses, tanggal, serta mata uang.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

// Batasi laporan sistem agar hanya dapat dibuka administrator.
require_role(['admin']);

// Ambil PDO dan identitas organisasi untuk laporan layar maupun file ekspor.
$pdo = getDBConnection();
$pengaturan = get_pengaturan($pdo);

// Parameter Filter
$jenisLaporan = $_GET['jenis_laporan'] ?? 'transaksi'; // 'transaksi' atau 'saldo_anggota'
$jenisTrx = $_GET['jenis'] ?? ''; // 'setoran', 'penarikan', atau '' (semua)
// Ubah filter ID anggota menjadi integer agar nilai query konsisten.
$anggotaId = (int)($_GET['anggota_id'] ?? 0);
$periodeFilter = $_GET['periode'] ?? 'semua'; // 'hari', 'bulan', 'tahun', 'custom', 'semua'
$tanggalSpesifik = $_GET['tanggal'] ?? date('Y-m-d');
$bulanSpesifik = $_GET['bulan'] ?? date('m');
$tahunSpesifik = $_GET['tahun'] ?? date('Y');
$tanggalAwal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tanggalAkhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

// Export Excel Handler
// Tandai request ekspor hanya bila parameter meminta format Excel.
$isExportExcel = isset($_GET['export']) && $_GET['export'] === 'excel';

// Query data transaksi
$sqlTrx = "
    SELECT t.*, a.nama AS nama_anggota, a.nomor_anggota, u.nama AS nama_petugas
    FROM transaksi t
    JOIN anggota a ON a.id = t.anggota_id
    LEFT JOIN users u ON u.id = t.petugas_id
    WHERE t.status = 'berhasil'
";
// Kumpulkan nilai filter terpisah dari teks SQL untuk prepared statement.
$paramsTrx = [];

if (!empty($jenisTrx)) {
    // Tambahkan jenis transaksi hanya jika pengguna memilih satu jenis tertentu.
    $sqlTrx .= " AND t.jenis_transaksi = ?";
    $paramsTrx[] = $jenisTrx;
}

if ($anggotaId > 0) {
    // Batasi laporan ke anggota terpilih bila ID-nya lebih besar dari nol.
    $sqlTrx .= " AND t.anggota_id = ?";
    $paramsTrx[] = $anggotaId;
}

if ($periodeFilter === 'hari') {
    // Filter satu tanggal kalender.
    $sqlTrx .= " AND t.tanggal = ?";
    $paramsTrx[] = $tanggalSpesifik;
} elseif ($periodeFilter === 'bulan') {
    // Filter bulan dan tahun agar bulan yang sama pada tahun berbeda tidak tercampur.
    $sqlTrx .= " AND MONTH(t.tanggal) = ? AND YEAR(t.tanggal) = ?";
    $paramsTrx[] = (int)$bulanSpesifik;
    $paramsTrx[] = (int)$tahunSpesifik;
} elseif ($periodeFilter === 'tahun') {
    // Filter seluruh transaksi pada satu tahun.
    $sqlTrx .= " AND YEAR(t.tanggal) = ?";
    $paramsTrx[] = (int)$tahunSpesifik;
} elseif ($periodeFilter === 'custom') {
    // Filter periode khusus dengan tanggal awal dan akhir.
    $sqlTrx .= " AND t.tanggal BETWEEN ? AND ?";
    $paramsTrx[] = $tanggalAwal;
    $paramsTrx[] = $tanggalAkhir;
}

$sqlTrx .= " ORDER BY t.tanggal DESC, t.id DESC";

// Siapkan dan jalankan query mutasi beserta nilai filter yang sudah terkumpul.
$stmtTrx = $pdo->prepare($sqlTrx);
$stmtTrx->execute($paramsTrx);
$dataTransaksi = $stmtTrx->fetchAll();

// Total Pemasukan & Pengeluaran dari filter
$totalSetoran = 0;
$totalPenarikan = 0;
// Akumulasikan pemasukan dan pengeluaran dari data transaksi terfilter.
foreach ($dataTransaksi as $row) {
    if ($row['jenis_transaksi'] === 'setoran') {
        $totalSetoran += (float)$row['nominal'];
    } else {
        $totalPenarikan += (float)$row['nominal'];
    }
}
// Selisih bersih adalah setoran dikurangi penarikan pada periode laporan.
$selisihSaldo = $totalSetoran - $totalPenarikan;

// Query Laporan Saldo Anggota
$sqlSaldo = "
    SELECT a.*,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'setoran' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) AS total_setor,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'penarikan' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) AS total_tarik,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'setoran' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) -
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'penarikan' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) AS saldo_akhir
    FROM anggota a
    LEFT JOIN transaksi t ON t.anggota_id = a.id
    WHERE 1=1
";
// Siapkan parameter terpisah untuk query rekap saldo anggota.
$paramsSaldo = [];
if ($anggotaId > 0) {
    $sqlSaldo .= " AND a.id = ?";
    $paramsSaldo[] = $anggotaId;
}
$sqlSaldo .= " GROUP BY a.id ORDER BY a.nama ASC";
// Jalankan agregasi saldo per anggota untuk tabel rekap.
$stmtSaldo = $pdo->prepare($sqlSaldo);
$stmtSaldo->execute($paramsSaldo);
$dataSaldoAnggota = $stmtSaldo->fetchAll();

// Ambil list semua anggota untuk dropdown filter
$semuaAnggota = $pdo->query("SELECT id, nomor_anggota, nama FROM anggota ORDER BY nama ASC")->fetchAll();

// JIKA EXPORT EXCEL
// Bila diminta ekspor, kirim header unduhan dan hentikan render halaman HTML biasa.
if ($isExportExcel) {
    $fileName = 'Laporan_TabunganPKK_' . date('Ymd_His') . '.xls';
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$fileName");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    // Pilih bentuk lembar kerja sesuai tipe laporan yang sedang dibuka.
    if ($jenisLaporan === 'saldo_anggota') {
        echo "<tr><th colspan='6' style='font-size:16px; font-weight:bold;'>LAPORAN REKAP SALDO ANGGOTA PKK</th></tr>";
        echo "<tr><th colspan='6'>" . htmlspecialchars($pengaturan['nama_organisasi']) . " - Periode " . htmlspecialchars($pengaturan['periode_aktif']) . "</th></tr>";
        echo "<tr><th>No</th><th>No Anggota</th><th>Nama Anggota</th><th>Total Setoran</th><th>Total Penarikan</th><th>Saldo Akhir</th></tr>";
        $no = 1;
        $tSetor = 0; $tTarik = 0; $tSaldo = 0;
        // Tulis satu baris per anggota sekaligus hitung total pada bagian akhir.
        foreach ($dataSaldoAnggota as $row) {
            echo "<tr>";
            echo "<td>" . $no++ . "</td>";
            echo "<td>" . $row['nomor_anggota'] . "</td>";
            echo "<td>" . $row['nama'] . "</td>";
            echo "<td>" . $row['total_setor'] . "</td>";
            echo "<td>" . $row['total_tarik'] . "</td>";
            echo "<td>" . $row['saldo_akhir'] . "</td>";
            echo "</tr>";
            $tSetor += $row['total_setor'];
            $tTarik += $row['total_tarik'];
            $tSaldo += $row['saldo_akhir'];
        }
        echo "<tr style='font-weight:bold;'><td colspan='3'>TOTAL</td><td>$tSetor</td><td>$tTarik</td><td>$tSaldo</td></tr>";
    } else {
        echo "<tr><th colspan='8' style='font-size:16px; font-weight:bold;'>LAPORAN TRANSAKSI TABUNGAN PKK</th></tr>";
        echo "<tr><th colspan='8'>" . htmlspecialchars($pengaturan['nama_organisasi']) . "</th></tr>";
        echo "<tr><th>No</th><th>Kode Transaksi</th><th>Tanggal</th><th>No Anggota</th><th>Nama Anggota</th><th>Jenis</th><th>Nominal</th><th>Metode</th></tr>";
        $no = 1;
        // Tulis setiap mutasi yang lolos filter ke lembar kerja.
        foreach ($dataTransaksi as $row) {
            echo "<tr>";
            echo "<td>" . $no++ . "</td>";
            echo "<td>" . $row['kode_transaksi'] . "</td>";
            echo "<td>" . $row['tanggal'] . "</td>";
            echo "<td>" . $row['nomor_anggota'] . "</td>";
            echo "<td>" . $row['nama_anggota'] . "</td>";
            echo "<td>" . strtoupper($row['jenis_transaksi']) . "</td>";
            echo "<td>" . $row['nominal'] . "</td>";
            echo "<td>" . $row['metode_pembayaran'] . "</td>";
            echo "</tr>";
        }
        echo "<tr style='font-weight:bold;'><td colspan='6'>TOTAL SETORAN</td><td colspan='2'>$totalSetoran</td></tr>";
        echo "<tr style='font-weight:bold;'><td colspan='6'>TOTAL PENARIKAN</td><td colspan='2'>$totalPenarikan</td></tr>";
        echo "<tr style='font-weight:bold;'><td colspan='6'>SELISIH KAS MASUK</td><td colspan='2'>$selisihSaldo</td></tr>";
    }
    echo "</table>";
    exit;
}

$pageTitle = 'Laporan Tabungan';
$pageHeading = 'Laporan Keuangan Tabungan';
$pageSubheading = 'Rekapitulasi transaksi, setoran, penarikan, dan saldo anggota';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Tab Pilihan Laporan -->
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div class="btn-group" role="group">
        <a href="<?= base_url('admin/laporan.php?jenis_laporan=transaksi') ?>" class="btn <?= $jenisLaporan === 'transaksi' ? 'btn-pkk-primary' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-list-check me-1"></i> Laporan Transaksi
        </a>
        <a href="<?= base_url('admin/laporan.php?jenis_laporan=saldo_anggota') ?>" class="btn <?= $jenisLaporan === 'saldo_anggota' ? 'btn-pkk-primary' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-users-viewfinder me-1"></i> Rekap Saldo Seluruh Anggota
        </a>
    </div>

    <!-- Tombol Export & Print -->
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
            <i class="fa-solid fa-file-pdf me-1"></i> Cetak / Export PDF
        </button>
    </div>
</div>

<!-- Filter Box (Hidden on Print) -->
<div class="card-pkk mb-4 no-print">
    <div class="card-pkk-header py-2">
        <span class="small fw-bold text-muted"><i class="fa-solid fa-filter me-1"></i> Filter Data Laporan</span>
    </div>
    <div class="card-pkk-body py-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <input type="hidden" name="jenis_laporan" value="<?= htmlspecialchars($jenisLaporan) ?>">

            <?php if ($jenisLaporan === 'transaksi'): ?>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Jenis Transaksi</label>
                    <select name="jenis" class="form-select form-select-sm">
                        <option value="">Semua (Setor & Tarik)</option>
                        <option value="setoran" <?= $jenisTrx === 'setoran' ? 'selected' : '' ?>>Hanya Setoran</option>
                        <option value="penarikan" <?= $jenisTrx === 'penarikan' ? 'selected' : '' ?>>Hanya Penarikan</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="col-md-3">
                <label class="form-label small fw-semibold">Pilih Anggota</label>
                <select name="anggota_id" class="form-select form-select-sm">
                    <option value="0">-- Seluruh Anggota PKK --</option>
                    <?php foreach ($semuaAnggota as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= $anggotaId == $ag['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ag['nama']) ?> (<?= htmlspecialchars($ag['nomor_anggota']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold">Periode Waktu</label>
                <select name="periode" id="periodeSelector" class="form-select form-select-sm" onchange="togglePeriodeInputs()">
                    <option value="semua" <?= $periodeFilter === 'semua' ? 'selected' : '' ?>>Semua Waktu</option>
                    <option value="hari" <?= $periodeFilter === 'hari' ? 'selected' : '' ?>>Hari Ini / Tanggal</option>
                    <option value="bulan" <?= $periodeFilter === 'bulan' ? 'selected' : '' ?>>Bulan Tertentu</option>
                    <option value="tahun" <?= $periodeFilter === 'tahun' ? 'selected' : '' ?>>Tahun Tertentu</option>
                    <option value="custom" <?= $periodeFilter === 'custom' ? 'selected' : '' ?>>Rentang Tanggal</option>
                </select>
            </div>

            <div class="col-md-3" id="periodeExtraWrap">
                <!-- Dynamic input via JS -->
                <div id="wrapHari" style="<?= $periodeFilter === 'hari' ? '' : 'display:none;' ?>">
                    <label class="form-label small fw-semibold">Tanggal</label>
                    <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= htmlspecialchars($tanggalSpesifik) ?>">
                </div>
                <div id="wrapBulan" class="row g-1" style="<?= $periodeFilter === 'bulan' ? '' : 'display:none;' ?>">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Bulan</label>
                        <select name="bulan" class="form-select form-select-sm">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= (int)$bulanSpesifik === $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Tahun</label>
                        <input type="number" name="tahun" class="form-control form-control-sm" value="<?= htmlspecialchars($tahunSpesifik) ?>">
                    </div>
                </div>
                <div id="wrapTahun" style="<?= $periodeFilter === 'tahun' ? '' : 'display:none;' ?>">
                    <label class="form-label small fw-semibold">Tahun</label>
                    <input type="number" name="tahun" class="form-control form-control-sm" value="<?= htmlspecialchars($tahunSpesifik) ?>">
                </div>
                <div id="wrapCustom" class="row g-1" style="<?= $periodeFilter === 'custom' ? '' : 'display:none;' ?>">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Dari</label>
                        <input type="date" name="tgl_awal" class="form-control form-control-sm" value="<?= htmlspecialchars($tanggalAwal) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Sampai</label>
                        <input type="date" name="tgl_akhir" class="form-control form-control-sm" value="<?= htmlspecialchars($tanggalAkhir) ?>">
                    </div>
                </div>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-pkk-primary btn-sm flex-grow-1">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Tampilkan
                </button>
                <a href="<?= base_url('admin/laporan.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Header Laporan Cetak (Kop Laporan) -->
<div class="d-none d-print-block text-center mb-4">
    <h3 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($pengaturan['nama_aplikasi']) ?></h3>
    <p class="mb-0 fs-5"><?= htmlspecialchars($pengaturan['nama_organisasi']) ?></p>
    <p class="small text-muted mb-2"><?= htmlspecialchars($pengaturan['alamat_kantor']) ?> | Kontak: <?= htmlspecialchars($pengaturan['kontak_hp']) ?></p>
    <hr class="border-2 border-dark my-2">
    <h5 class="fw-bold text-uppercase mt-2"><?= $jenisLaporan === 'saldo_anggota' ? 'LAPORAN REKAPITULASI SALDO ANGGOTA' : 'LAPORAN MUTASI TRANSAKSI TABUNGAN' ?></h5>
    <div class="small text-muted">Dicetak pada: <?= date('d/m/Y H:i') ?> WIB</div>
</div>

<?php if ($jenisLaporan === 'saldo_anggota'): ?>
    <!-- LAPORAN REKAP SALDO SELURUH ANGGOTA -->
    <div class="card-pkk">
        <div class="card-pkk-header">
            <h5><i class="fa-solid fa-users text-success"></i> Rekapitulasi Saldo Seluruh Anggota</h5>
            <span class="badge bg-light text-secondary border"><?= count($dataSaldoAnggota) ?> Anggota</span>
        </div>
        <div class="table-responsive">
            <table class="table-pkk">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>No. Anggota</th>
                        <th>Nama Lengkap</th>
                        <th>No. WhatsApp</th>
                        <th class="text-end">Total Setoran</th>
                        <th class="text-end">Total Penarikan</th>
                        <th class="text-end">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataSaldoAnggota)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Tidak ada data anggota ditemukan.</td></tr>
                    <?php else: ?>
                        <?php 
                        $no = 1; 
                        $sumSetor = 0; $sumTarik = 0; $sumSaldo = 0;
                        foreach ($dataSaldoAnggota as $ag): 
                            $sumSetor += (float)$ag['total_setor'];
                            $sumTarik += (float)$ag['total_tarik'];
                            $sumSaldo += (float)$ag['saldo_akhir'];
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="font-monospace fw-bold text-dark"><?= htmlspecialchars($ag['nomor_anggota']) ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($ag['nama']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($ag['nomor_hp'] ?: '-') ?></td>
                                <td class="text-end text-success fw-bold"><?= format_rupiah($ag['total_setor']) ?></td>
                                <td class="text-end text-danger fw-bold"><?= format_rupiah($ag['total_tarik']) ?></td>
                                <td class="text-end fw-extrabold fs-6 <?= (float)$ag['saldo_akhir'] > 0 ? 'text-primary' : 'text-muted' ?>">
                                    <?= format_rupiah($ag['saldo_akhir']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="4" class="text-center text-uppercase">TOTAL KESELURUHAN</td>
                            <td class="text-end text-success"><?= format_rupiah($sumSetor) ?></td>
                            <td class="text-end text-danger"><?= format_rupiah($sumTarik) ?></td>
                            <td class="text-end text-primary fs-6"><?= format_rupiah($sumSaldo) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- LAPORAN TRANSAKSI -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card stat-green">
                <div>
                    <div class="stat-title">Total Setoran</div>
                    <div class="stat-value text-success"><?= format_rupiah($totalSetoran) ?></div>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-circle-arrow-down"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card stat-rose">
                <div>
                    <div class="stat-title">Total Penarikan</div>
                    <div class="stat-value text-danger"><?= format_rupiah($totalPenarikan) ?></div>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-circle-arrow-up"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card stat-blue">
                <div>
                    <div class="stat-title">Selisih Kas Bersih</div>
                    <div class="stat-value text-primary"><?= format_rupiah($selisihSaldo) ?></div>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-scale-balanced"></i></div>
            </div>
        </div>
    </div>

    <div class="card-pkk">
        <div class="card-pkk-header">
            <h5><i class="fa-solid fa-receipt text-success"></i> Rincian Data Transaksi</h5>
            <span class="badge bg-light text-secondary border"><?= count($dataTransaksi) ?> Data Ditemukan</span>
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
                        <th>Petugas</th>
                        <th class="text-center no-print">Kwitansi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataTransaksi)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">Tidak ada transaksi pada periode atau filter yang dipilih.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dataTransaksi as $t): ?>
                            <tr>
                                <td class="font-monospace fw-bold text-dark small"><?= htmlspecialchars($t['kode_transaksi']) ?></td>
                                <td class="small"><?= format_tanggal($t['tanggal']) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($t['nama_anggota']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($t['nomor_anggota']) ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-trx-<?= $t['jenis_transaksi'] ?> text-uppercase">
                                        <?= $t['jenis_transaksi'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold <?= $t['jenis_transaksi'] === 'setoran' ? 'text-success' : 'text-danger' ?>">
                                        <?= $t['jenis_transaksi'] === 'setoran' ? '+' : '-' ?><?= format_rupiah($t['nominal']) ?>
                                    </span>
                                </td>
                                <td class="small"><?= htmlspecialchars($t['metode_pembayaran'] ?: 'Tunai') ?></td>
                                <td class="small text-muted text-truncate" style="max-width: 180px;"><?= htmlspecialchars($t['keterangan'] ?: '-') ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($t['nama_petugas'] ?: '-') ?></td>
                                <td class="text-center no-print">
                                    <a href="<?= base_url('cetak_kuitansi.php?kode=' . urlencode($t['kode_transaksi'])) ?>" class="btn btn-sm btn-light border" title="Cetak Kwitansi">
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
<?php endif; ?>

<script>
function togglePeriodeInputs() {
    const selector = document.getElementById('periodeSelector').value;
    document.getElementById('wrapHari').style.display = (selector === 'hari') ? 'block' : 'none';
    document.getElementById('wrapBulan').style.display = (selector === 'bulan') ? 'flex' : 'none';
    document.getElementById('wrapTahun').style.display = (selector === 'tahun') ? 'block' : 'none';
    document.getElementById('wrapCustom').style.display = (selector === 'custom') ? 'flex' : 'none';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
