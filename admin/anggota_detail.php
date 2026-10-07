<?php
/**
 * TabunganPKK - Detail Anggota & Riwayat Mutasi (Admin)
 *
 * ID anggota diambil dari URL, lalu dipakai untuk membaca profil dan semua
 * transaksi anggota tersebut. Saldo tidak disimpan terpisah, tetapi dihitung
 * dari transaksi berhasil melalui helper hitung_saldo_anggota().
 */

// Muat PDO dan helper aplikasi untuk hak akses, URL, saldo, serta format tampilan.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

// Pastikan hanya administrator dapat melihat data anggota lengkap.
require_role(['admin']);

// Buka koneksi database untuk mengambil profil dan mutasi anggota.
$pdo = getDBConnection();

// Baca ID dari URL sebagai integer agar hanya ID numerik yang diproses.
$id = (int)($_GET['id'] ?? 0);
// Kembalikan ke daftar jika parameter ID tidak valid.
if ($id <= 0) {
    header("Location: " . base_url('admin/anggota.php'));
    exit;
}

// Ambil data anggota
// Ambil profil beserta username/email akun yang mungkin tertaut.
$stmt = $pdo->prepare("
    SELECT a.*, u.username, u.email 
    FROM anggota a
    LEFT JOIN users u ON u.id = a.user_id
    WHERE a.id = ?
");
// Isi placeholder query dengan ID anggota yang sudah divalidasi sebagai integer.
$stmt->execute([$id]);
// Simpan hasil profil untuk dipakai pada kartu identitas.
$anggota = $stmt->fetch();

// Hentikan alur dengan pesan bila ID tidak menunjuk anggota yang tersimpan.
if (!$anggota) {
    set_flash('danger', 'Data anggota tidak ditemukan.');
    header("Location: " . base_url('admin/anggota.php'));
    exit;
}

// Hitung setoran, penarikan, dan saldo bersih berdasarkan transaksi berhasil.
$saldoData = hitung_saldo_anggota($pdo, $id);

// Ambil mutasi transaksi anggota beserta nama petugas untuk tabel riwayat.
$stmtMutasi = $pdo->prepare("
    SELECT t.*, u.nama AS nama_petugas
    FROM transaksi t
    LEFT JOIN users u ON u.id = t.petugas_id
    WHERE t.anggota_id = ?
    ORDER BY t.tanggal DESC, t.id DESC
");
// Batasi mutasi pada satu anggota, lalu simpan seluruh hasil query.
$stmtMutasi->execute([$id]);
$riwayatTrx = $stmtMutasi->fetchAll();

$pageTitle = 'Detail Anggota - ' . $anggota['nama'];
$pageHeading = 'Detail Data Anggota';
$pageSubheading = 'Profil lengkap dan rekap riwayat tabungan ' . $anggota['nama'];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="mb-4">
    <a href="<?= base_url('admin/anggota.php') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar Anggota
    </a>
</div>

<div class="row g-4 mb-4">
    <!-- Profil Anggota -->
    <div class="col-lg-5">
        <div class="card-pkk h-100">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-id-badge text-success"></i> Identitas Anggota</h5>
                <span class="badge badge-status-<?= $anggota['status'] ?> text-uppercase"><?= $anggota['status'] ?></span>
            </div>
            <div class="card-pkk-body">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div style="width: 58px; height: 58px; border-radius: 50%; background: #dcfce7; color: #15803d; font-weight: 800; font-size: 1.5rem; display: flex; align-items: center; justify-content: center;">
                        <?= strtoupper(substr($anggota['nama'], 0, 1)) ?>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($anggota['nama']) ?></h5>
                        <span class="badge bg-light text-secondary border font-monospace"><?= htmlspecialchars($anggota['nomor_anggota']) ?></span>
                    </div>
                </div>

                <table class="table table-sm table-borderless small mb-0">
                    <tr>
                        <td class="text-muted" style="width: 140px;">NIK KTP</td>
                        <td class="fw-semibold">: <?= htmlspecialchars($anggota['nik'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Nomor WhatsApp</td>
                        <td class="fw-semibold">: <?= htmlspecialchars($anggota['nomor_hp'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Alamat</td>
                        <td>: <?= htmlspecialchars($anggota['alamat'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Tanggal Gabung</td>
                        <td>: <?= format_tanggal($anggota['tanggal_gabung']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Akun Login</td>
                        <td>: 
                            <?php if ($anggota['username']): ?>
                                <span class="badge bg-primary-subtle text-primary">@<?= htmlspecialchars($anggota['username']) ?> (<?= htmlspecialchars($anggota['email']) ?>)</span>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Belum memiliki akun login web</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Saldo Hero Card -->
    <div class="col-lg-7">
        <div class="saldo-hero-card h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="hero-label"><i class="fa-solid fa-piggy-bank me-1"></i> Saldo Tabungan Tersedia</div>
                    <span class="badge bg-white text-success fw-bold px-3 py-1">Aktif</span>
                </div>
                <div class="hero-nominal"><?= format_rupiah($saldoData['saldo']) ?></div>
            </div>

            <div class="row g-2 mt-2">
                <div class="col-sm-6">
                    <div class="saldo-hero-stat">
                        <small class="d-block opacity-75"><i class="fa-solid fa-arrow-down me-1"></i> Total Setoran Masuk</small>
                        <strong class="fs-6"><?= format_rupiah($saldoData['total_setoran']) ?></strong>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="saldo-hero-stat">
                        <small class="d-block opacity-75"><i class="fa-solid fa-arrow-up me-1"></i> Total Penarikan Keluar</small>
                        <strong class="fs-6"><?= format_rupiah($saldoData['total_penarikan']) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Riwayat Transaksi Anggota -->
<div class="card-pkk">
    <div class="card-pkk-header">
        <h5><i class="fa-solid fa-list-check text-success"></i> Riwayat Transaksi Anggota</h5>
        <span class="badge bg-light text-secondary border"><?= count($riwayatTrx) ?> Transaksi</span>
    </div>
    <div class="table-responsive">
        <table class="table-pkk">
            <thead>
                <tr>
                    <th>No. Transaksi</th>
                    <th>Tanggal</th>
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
                <?php if (empty($riwayatTrx)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            Belum ada riwayat transaksi tabungan untuk anggota ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($riwayatTrx as $trx): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-dark small">
                                <?= htmlspecialchars($trx['kode_transaksi']) ?>
                            </td>
                            <td class="small"><?= format_tanggal($trx['tanggal']) ?></td>
                            <td>
                                <span class="badge badge-trx-<?= $trx['jenis_transaksi'] ?> text-uppercase">
                                    <?= $trx['jenis_transaksi'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold <?= $trx['jenis_transaksi'] === 'setoran' ? 'text-success' : 'text-danger' ?>">
                                    <?= $trx['jenis_transaksi'] === 'setoran' ? '+' : '-' ?><?= format_rupiah($trx['nominal']) ?>
                                </span>
                            </td>
                            <td class="small"><?= htmlspecialchars($trx['metode_pembayaran'] ?: 'Tunai') ?></td>
                            <td class="small text-muted text-truncate" style="max-width: 200px;">
                                <?= htmlspecialchars($trx['keterangan'] ?: '-') ?>
                            </td>
                            <td class="small"><?= htmlspecialchars($trx['nama_petugas'] ?: '-') ?></td>
                            <td>
                                <span class="badge badge-status-<?= $trx['status'] ?>">
                                    <?= strtoupper($trx['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                        <a href="<?= base_url('cetak_kuitansi.php?kode=' . urlencode($trx['kode_transaksi'])) ?>" class="btn btn-sm btn-light border" title="Cetak Kuitansi">
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
