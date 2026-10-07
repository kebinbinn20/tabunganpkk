<?php
/**
 * =========================================================================================
 * APLIKASI TABUNGAN DIGITAL PKK (TabunganPKK)
 * File: bendahara/pengajuan.php
 * -----------------------------------------------------------------------------------------
 * PENJELASAN MATERI UNTUK PRESENTASI KE GURU:
 * 1. Alur Persetujuan Penarikan Dana (Approval Workflow):
 *    Fitur ini mendigitalkan proses birokrasi permohonan uang kas PKK:
 *    a) Tahap Pengajuan: Anggota meminta pencairan tabungan melalui dashboard mereka (status = 'menunggu').
 *    b) Tahap Verifikasi: Bendahara memeriksa sisa saldo aktif dan alasan keperluan dana.
 *    c) Tahap Eksekusi:
 *       - Jika DISETUJUI: Sistem secara otomatis membuat rekaman transaksi 'penarikan',
 *         memotong saldo kas, mencatat nama bendahara verifikator, dan mengirim notifikasi sukses.
 *       - Jika DITOLAK: Status berubah menjadi 'ditolak' dengan alasan resmi tanpa mengurangi saldo.
 *
 * 2. Integritas Transaksi Terhubung:
 *    Pengajuan penarikan terhubung langsung dengan ID anggota dan ID petugas (Foreign Key).
 * =========================================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

require_role(['bendahara', 'admin']);

$pdo = getDBConnection();
$pageTitle = 'Pengajuan Penarikan Anggota';
$pageHeading = 'Pengajuan Penarikan Dana';
$pageSubheading = 'Verifikasi dan proses pencairan penarikan yang diajukan oleh anggota';

// Handle Action: Setujui Pengajuan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'setujui') {
    // Ambil ID pengajuan, catatan bendahara, dan metode pencairan dari form.
    $pengajuanId = (int)($_POST['pengajuan_id'] ?? 0);
    $catatan = trim($_POST['catatan_petugas'] ?? 'Disetujui dan dicairkan');
    $metode = trim($_POST['metode_pembayaran'] ?? 'Tunai');

    // Ambil hanya pengajuan yang masih menunggu agar tidak diproses dua kali.
    $stmtP = $pdo->prepare("
        SELECT p.*, a.nama AS nama_anggota, a.user_id 
        FROM pengajuan_penarikan p
        JOIN anggota a ON a.id = p.anggota_id
        WHERE p.id = ? AND p.status = 'menunggu'
    ");
    $stmtP->execute([$pengajuanId]);
    $p = $stmtP->fetch();

    // Berhenti jika pengajuan tidak ada atau statusnya sudah berubah.
    if (!$p) {
        set_flash('danger', 'Pengajuan penarikan tidak ditemukan atau sudah diproses.');
    } else {
        // Cek saldo anggota saat ini
        // Hitung saldo terbaru sebelum bendahara membuat pencairan.
        $saldoData = hitung_saldo_anggota($pdo, $p['anggota_id']);
        if ($saldoData['saldo'] < (float)$p['nominal']) {
            set_flash('danger', 'Saldo anggota tidak mencukupi untuk penarikan ini. Saldo saat ini: ' . format_rupiah($saldoData['saldo']));
        } else {
            // 1. Generate Kode Transaksi
            $kodeTrx = generate_kode_transaksi($pdo, 'penarikan');

            // 2. Buat Transaksi Penarikan Otomatis
            // Simpan transaksi penarikan agar saldo dan laporan ikut diperbarui.
            $stmtTrx = $pdo->prepare("
                INSERT INTO transaksi (kode_transaksi, anggota_id, jenis_transaksi, nominal, metode_pembayaran, tanggal, keterangan, status, petugas_id)
                VALUES (?, ?, 'penarikan', ?, ?, CURDATE(), ?, 'berhasil', ?)
            ");
            // Kaitkan mutasi dengan anggota, pengajuan, metode, dan petugas pemroses.
            $stmtTrx->execute([
                $kodeTrx,
                $p['anggota_id'],
                $p['nominal'],
                $metode,
                'Pencairan Pengajuan: ' . ($p['keterangan'] ?: 'Penarikan mandiri anggota'),
                $_SESSION['user_id']
            ]);

            // 3. Update Status Pengajuan
            // Tandai permohonan disetujui dan catat siapa serta kapan memprosesnya.
            $stmtUp = $pdo->prepare("
                UPDATE pengajuan_penarikan SET
                    status = 'disetujui',
                    diproses_oleh = ?,
                    tanggal_proses = NOW(),
                    catatan_petugas = ?
                WHERE id = ?
            ");
            $stmtUp->execute([$_SESSION['user_id'], $catatan, $pengajuanId]);

            // 4. Notifikasi Anggota
            // Kirim hasil persetujuan ke akun anggota bila profilnya terhubung.
            if (!empty($p['user_id'])) {
                tambah_notifikasi(
                    $pdo,
                    $p['user_id'],
                    'Pengajuan Penarikan Disetujui',
                    "Pengajuan penarikan sebesar " . format_rupiah($p['nominal']) . " telah DISETUJUI dan dicairkan. Nomor transaksi: $kodeTrx. Catatan: $catatan."
                );
            }

            set_flash('success', "Pengajuan penarikan $kodeTrx untuk " . htmlspecialchars($p['nama_anggota']) . " berhasil disetujui dan dicairkan.");
            header("Location: " . base_url('bendahara/pengajuan.php'));
            exit;
        }
    }
}

// Handle Action: Tolak Pengajuan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tolak') {
    // Ambil identitas pengajuan dan alasan resmi penolakan.
    $pengajuanId = (int)($_POST['pengajuan_id'] ?? 0);
    $alasan = trim($_POST['alasan_penolakan'] ?? 'Pengajuan penarikan ditolak.');

    // Cari pengajuan yang masih berstatus menunggu untuk mencegah keputusan ganda.
    $stmtP = $pdo->prepare("
        SELECT p.*, a.nama AS nama_anggota, a.user_id 
        FROM pengajuan_penarikan p
        JOIN anggota a ON a.id = p.anggota_id
        WHERE p.id = ? AND p.status = 'menunggu'
    ");
    $stmtP->execute([$pengajuanId]);
    $p = $stmtP->fetch();

    // Tampilkan kesalahan bila pengajuan sudah tidak dapat diproses.
    if (!$p) {
        set_flash('danger', 'Pengajuan penarikan tidak ditemukan atau sudah diproses.');
    } else {
        // Simpan status ditolak beserta petugas, waktu proses, dan alasan.
        $stmtUp = $pdo->prepare("
            UPDATE pengajuan_penarikan SET
                status = 'ditolak',
                diproses_oleh = ?,
                tanggal_proses = NOW(),
                catatan_petugas = ?
            WHERE id = ?
        ");
        $stmtUp->execute([$_SESSION['user_id'], $alasan, $pengajuanId]);

        // Notifikasi ke anggota
        // Beri tahu anggota tentang hasil penolakan jika ia memiliki akun.
        if (!empty($p['user_id'])) {
            tambah_notifikasi(
                $pdo,
                $p['user_id'],
                'Pengajuan Penarikan Ditolak',
                "Pengajuan penarikan sebesar " . format_rupiah($p['nominal']) . " ditolak oleh bendahara. Alasan: $alasan."
            );
        }

        set_flash('warning', "Pengajuan penarikan untuk " . htmlspecialchars($p['nama_anggota']) . " telah ditolak.");
        header("Location: " . base_url('bendahara/pengajuan.php'));
        exit;
    }
}

// Filter Status
// Tampilkan pengajuan menunggu sebagai pilihan awal jika filter belum dipilih.
$filterStatus = $_GET['status'] ?? 'menunggu';

$sql = "
    SELECT p.*, a.nama AS nama_anggota, a.nomor_anggota, a.nomor_hp, u.nama AS nama_petugas
    FROM pengajuan_penarikan p
    JOIN anggota a ON a.id = p.anggota_id
    LEFT JOIN users u ON u.id = p.diproses_oleh
    WHERE 1=1
";
$params = [];

if ($filterStatus !== 'semua' && !empty($filterStatus)) {
    $sql .= " AND p.status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY p.tanggal_pengajuan DESC, p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarPengajuan = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Filter Status Tabs -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div class="btn-group" role="group">
        <a href="<?= base_url('bendahara/pengajuan.php?status=menunggu') ?>" class="btn <?= $filterStatus === 'menunggu' ? 'btn-warning fw-bold' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-clock me-1"></i> Menunggu Persetujuan
        </a>
        <a href="<?= base_url('bendahara/pengajuan.php?status=disetujui') ?>" class="btn <?= $filterStatus === 'disetujui' ? 'btn-success fw-bold' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-check me-1"></i> Telah Disetujui
        </a>
        <a href="<?= base_url('bendahara/pengajuan.php?status=ditolak') ?>" class="btn <?= $filterStatus === 'ditolak' ? 'btn-danger fw-bold' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-xmark me-1"></i> Ditolak
        </a>
        <a href="<?= base_url('bendahara/pengajuan.php?status=semua') ?>" class="btn <?= $filterStatus === 'semua' ? 'btn-dark fw-bold' : 'btn-outline-secondary' ?>">
            Semua
        </a>
    </div>

    <div>
        <a href="<?= base_url('bendahara/penarikan.php') ?>" class="btn btn-outline-danger">
            <i class="fa-solid fa-circle-arrow-up me-1"></i> Catat Penarikan Langsung
        </a>
    </div>
</div>

<div class="card-pkk">
    <div class="card-pkk-header">
        <h5><i class="fa-solid fa-hand-holding-dollar text-warning"></i> Daftar Pengajuan Penarikan Anggota</h5>
        <span class="badge bg-light text-secondary border"><?= count($daftarPengajuan) ?> Data</span>
    </div>
    <div class="table-responsive">
        <table class="table-pkk">
            <thead>
                <tr>
                    <th>Tgl Pengajuan</th>
                    <th>Anggota</th>
                    <th>Nominal Diajukan</th>
                    <th>Saldo Saat Ini</th>
                    <th>Keterangan</th>
                    <th>Status</th>
                    <th>Diproses Oleh</th>
                    <th class="text-center">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarPengajuan)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fa-regular fa-clipboard-check fs-3 d-block mb-2"></i>
                            Tidak ada pengajuan penarikan dengan status "<?= htmlspecialchars($filterStatus) ?>".
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarPengajuan as $p): 
                        $saldoAnggota = hitung_saldo_anggota($pdo, $p['anggota_id'])['saldo'];
                        $isCukup = $saldoAnggota >= (float)$p['nominal'];
                    ?>
                        <tr>
                            <td class="small"><?= format_tanggal($p['tanggal_pengajuan']) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['nama_anggota']) ?></div>
                                <span class="badge bg-light text-secondary border font-monospace small"><?= htmlspecialchars($p['nomor_anggota']) ?></span>
                            </td>
                            <td>
                                <span class="fw-bold fs-6 text-danger"><?= format_rupiah($p['nominal']) ?></span>
                            </td>
                            <td>
                                <span class="fw-bold <?= $isCukup ? 'text-success' : 'text-danger' ?>">
                                    <?= format_rupiah($saldoAnggota) ?>
                                </span>
                                <?php if (!$isCukup): ?>
                                    <small class="badge bg-danger-subtle text-danger d-block mt-1">Saldo Kurang</small>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted text-truncate" style="max-width: 180px;">
                                <?= htmlspecialchars($p['keterangan'] ?: '-') ?>
                            </td>
                            <td>
                                <span class="badge badge-status-<?= $p['status'] ?> text-uppercase">
                                    <?= $p['status'] ?>
                                </span>
                            </td>
                            <td class="small">
                                <?php if ($p['diproses_oleh']): ?>
                                    <div><?= htmlspecialchars($p['nama_petugas']) ?></div>
                                    <small class="text-muted"><?= date('d/m/y H:i', strtotime($p['tanggal_proses'])) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($p['status'] === 'menunggu'): ?>
                                    <div class="btn-group btn-group-sm">
                                        <!-- Tombol Setujui -->
                                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalSetujui<?= $p['id'] ?>" <?= !$isCukup ? 'disabled' : '' ?> title="Setujui & Cairkan">
                                            <i class="fa-solid fa-check"></i> Cairkan
                                        </button>
                                        <!-- Tombol Tolak -->
                                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalTolak<?= $p['id'] ?>" title="Tolak">
                                            <i class="fa-solid fa-xmark"></i> Tolak
                                        </button>
                                    </div>

                                    <!-- Modal Setujui -->
                                    <div class="modal fade" id="modalSetujui<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content" style="border-radius: 14px;">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="setujui">
                                                    <input type="hidden" name="pengajuan_id" value="<?= $p['id'] ?>">

                                                    <div class="modal-header border-bottom">
                                                        <h5 class="modal-title fw-bold text-success"><i class="fa-solid fa-circle-check me-2"></i> Setujui Penarikan Tabungan</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4 text-start">
                                                        <p class="mb-3">Konfirmasi pencairan penarikan tabungan untuk <strong><?= htmlspecialchars($p['nama_anggota']) ?></strong>:</p>
                                                        
                                                        <div class="p-3 bg-light rounded-3 mb-3 border">
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Nominal Diajukan:</span>
                                                                <strong class="text-danger fs-6"><?= format_rupiah($p['nominal']) ?></strong>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Saldo Tersedia:</span>
                                                                <strong class="text-success"><?= format_rupiah($saldoAnggota) ?></strong>
                                                            </div>
                                                            <div class="d-flex justify-content-between border-top pt-1">
                                                                <span class="text-muted">Sisa Saldo Setelah Penarikan:</span>
                                                                <strong><?= format_rupiah($saldoAnggota - $p['nominal']) ?></strong>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Metode Penyerahan Dana</label>
                                                            <select name="metode_pembayaran" class="form-select">
                                                                <option value="Tunai">Tunai Langsung</option>
                                                                <option value="Transfer Bank">Transfer Bank</option>
                                                            </select>
                                                        </div>

                                                        <div class="mb-2">
                                                            <label class="form-label small fw-semibold">Catatan Petugas (Opsional)</label>
                                                            <input type="text" name="catatan_petugas" class="form-control" value="Disetujui dan diserahkan tunai.">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-top bg-light">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-success btn-sm fw-bold">Ya, Setujui & Potong Saldo</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal Tolak -->
                                    <div class="modal fade" id="modalTolak<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content" style="border-radius: 14px;">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="tolak">
                                                    <input type="hidden" name="pengajuan_id" value="<?= $p['id'] ?>">

                                                    <div class="modal-header border-bottom">
                                                        <h5 class="modal-title fw-bold text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> Tolak Pengajuan Penarikan</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4 text-start">
                                                        <p class="mb-3 text-secondary">Tolak pengajuan penarikan dari <strong><?= htmlspecialchars($p['nama_anggota']) ?></strong> sebesar <strong><?= format_rupiah($p['nominal']) ?></strong>?</p>
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Alasan Penolakan *</label>
                                                            <textarea name="alasan_penolakan" class="form-control" rows="3" placeholder="Contoh: Saldo tabungan tidak mencukupi atau batas penarikan periode ini belum dibuka" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-top bg-light">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-danger btn-sm">Tolak Pengajuan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                <?php else: ?>
                                    <span class="small text-muted"><?= htmlspecialchars($p['catatan_petugas'] ?: '-') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
