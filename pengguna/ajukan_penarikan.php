<?php
/**
 * TabunganPKK - Form & Riwayat Pengajuan Penarikan Tabungan (Pengguna)
 *
 * Anggota mengirim nominal dan alasan penarikan. Server membandingkan nominal
 * dengan saldo terkini sebelum menyimpan pengajuan berstatus menunggu, lalu
 * halaman menampilkan riwayat keputusan bendahara.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

require_role(['pengguna', 'admin', 'bendahara']);

$pdo = getDBConnection();
$pengaturan = get_pengaturan($pdo);

// Ambil ID akun dari sesi sebagai identitas anggota yang sedang mengajukan.
$userId = $_SESSION['user_id'];
// Cari profil anggota yang terhubung ke akun tersebut.
$anggota = get_anggota_by_user_id($pdo, $userId);
// Simpan ID anggota untuk query saldo dan riwayat pengajuan.
$anggotaId = $anggota['id'];

// Hitung saldo saat ini
// Saldo bersumber dari mutasi berhasil, bukan dari input yang dikirim browser.
$saldoData = hitung_saldo_anggota($pdo, $anggotaId);
$saldoSaatIni = $saldoData['saldo'];

// Handle Submit Pengajuan Penarikan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ubah nominal dari form menjadi angka dan ambil alasan pengajuan.
    $nominal = (float)str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $tanggal_pengajuan = date('Y-m-d');

    // Tolak nominal nol atau negatif.
    if ($nominal <= 0) {
        set_flash('danger', 'Nominal penarikan harus lebih dari Rp 0.');
    // Pastikan permohonan tidak melebihi saldo saat halaman diproses.
    } elseif ($nominal > $saldoSaatIni) {
        set_flash('danger', 'Nominal penarikan melebihi saldo tabungan Anda saat ini (' . format_rupiah($saldoSaatIni) . ').');
    } else {
        // Buat permohonan baru dalam status menunggu verifikasi bendahara.
        $stmt = $pdo->prepare("
            INSERT INTO pengajuan_penarikan (anggota_id, nominal, tanggal_pengajuan, keterangan, status)
            VALUES (?, ?, ?, ?, 'menunggu')
        ");
        // Simpan anggota, nominal, tanggal, dan alasan dengan parameter SQL terpisah.
        $stmt->execute([$anggotaId, $nominal, $tanggal_pengajuan, $keterangan]);

        // Berikan notifikasi ke pengguna
        // Beri tanda kepada anggota bahwa pengajuan berhasil diterima sistem.
        tambah_notifikasi(
            $pdo,
            $userId,
            'Pengajuan Penarikan Dikirim',
            "Pengajuan penarikan sebesar " . format_rupiah($nominal) . " telah dikirimkan dan sedang menunggu verifikasi bendahara."
        );

        set_flash('success', 'Pengajuan penarikan tabungan sebesar ' . format_rupiah($nominal) . ' berhasil diajukan. Mohon menunggu persetujuan dari bendahara.');
        // Redirect mencegah pengajuan yang sama terkirim ulang saat halaman di-refresh.
        header("Location: " . base_url('pengguna/ajukan_penarikan.php'));
        exit;
    }
}

// Ambil riwayat pengajuan penarikan milik anggota ini
// Ambil seluruh pengajuan anggota ini, termasuk status dan catatan petugas.
$stmtRiwayat = $pdo->prepare("
    SELECT p.*, u.nama AS nama_petugas
    FROM pengajuan_penarikan p
    LEFT JOIN users u ON u.id = p.diproses_oleh
    WHERE p.anggota_id = ?
    ORDER BY p.tanggal_pengajuan DESC, p.id DESC
");
$stmtRiwayat->execute([$anggotaId]);
$daftarPengajuan = $stmtRiwayat->fetchAll();

$pageTitle = 'Ajukan Penarikan Tabungan';
$pageHeading = 'Pengajuan Penarikan Tabungan';
$pageSubheading = 'Form permohonan penarikan dana tabungan mandiri dan pantau statusnya';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="row g-4 mb-4">
    <!-- Form Pengajuan -->
    <div class="col-lg-6">
        <div class="card-pkk h-100">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-hand-holding-dollar text-success"></i> Formulir Pengajuan Penarikan</h5>
                <span class="badge bg-light text-secondary border">Online PKK</span>
            </div>
            <div class="card-pkk-body">
                <!-- Info Saldo -->
                <div class="p-3 bg-light rounded-3 border mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted text-uppercase fw-semibold d-block">Saldo Tersedia Saat Ini:</small>
                            <span class="fs-4 fw-bold text-success"><?= format_rupiah($saldoSaatIni) ?></span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="tarikSemua()">Tarik Semua</button>
                    </div>
                </div>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nominal Penarikan (Rp) *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold text-success">Rp</span>
                            <input type="number" name="nominal" id="nominalInput" class="form-control fs-5 fw-bold" placeholder="Contoh: 100000" max="<?= $saldoSaatIni ?>" min="1000" step="1000" required>
                        </div>
                        <small class="text-muted">Maksimal penarikan: <?= format_rupiah($saldoSaatIni) ?></small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Alasan / Keperluan Penarikan *</label>
                        <textarea name="keterangan" class="form-control" rows="3" placeholder="Contoh: Keperluan biaya sekolah anak atau belanja keluarga..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-pkk-primary w-100 py-2 fw-bold" <?= $saldoSaatIni <= 0 ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-paper-plane me-1"></i> Kirim Pengajuan Penarikan
                    </button>
                    <?php if ($saldoSaatIni <= 0): ?>
                        <small class="text-danger d-block text-center mt-2">Saldo tabungan Anda saat ini Rp 0. Tidak dapat mengajukan penarikan.</small>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Informasi Petunjuk & Ketentuan -->
    <div class="col-lg-6">
        <div class="card-pkk h-100 bg-light border">
            <div class="card-pkk-header bg-transparent">
                <h5><i class="fa-solid fa-circle-info text-primary"></i> Ketentuan Penarikan Tabungan</h5>
            </div>
            <div class="card-pkk-body small">
                <div class="d-flex gap-3 mb-3">
                    <i class="fa-solid fa-shield-check text-success fs-4"></i>
                    <div>
                        <strong class="text-dark d-block">Pemeriksaan Saldo Otomatis</strong>
                        <span class="text-secondary">Sistem memastikan Anda hanya dapat menarik sejumlah saldo yang benar-benar tersedia di buku tabungan Anda.</span>
                    </div>
                </div>

                <div class="d-flex gap-3 mb-3">
                    <i class="fa-solid fa-user-clock text-warning fs-4"></i>
                    <div>
                        <strong class="text-dark d-block">Verifikasi Oleh Bendahara</strong>
                        <span class="text-secondary">Setiap pengajuan akan diperiksa terlebih dahulu oleh bendahara PKK. Anda akan menerima notifikasi saat disetujui.</span>
                    </div>
                </div>

                <div class="d-flex gap-3 mb-3">
                    <i class="fa-solid fa-receipt text-info fs-4"></i>
                    <div>
                        <strong class="text-dark d-block">Pencatatan Buku Tabungan</strong>
                        <span class="text-secondary">Setelah disetujui dan dicairkan, saldo tabungan Anda akan langsung berkurang dan kwitansi resmi otomatis diterbitkan.</span>
                    </div>
                </div>

                <div class="alert alert-white border mt-4 mb-0 text-muted">
                    <strong>Aturan Resmi Pengurus:</strong><br>
                    <?= htmlspecialchars($pengaturan['aturan_penarikan']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Riwayat Pengajuan Penarikan -->
<div class="card-pkk">
    <div class="card-pkk-header">
        <h5><i class="fa-solid fa-clock-rotate-left text-success"></i> Riwayat Pengajuan Penarikan Anda</h5>
        <span class="badge bg-light text-secondary border"><?= count($daftarPengajuan) ?> Pengajuan</span>
    </div>
    <div class="table-responsive">
        <table class="table-pkk">
            <thead>
                <tr>
                    <th>Tgl Pengajuan</th>
                    <th>Nominal</th>
                    <th>Keperluan</th>
                    <th>Status</th>
                    <th>Catatan Petugas</th>
                    <th>Tgl Diproses</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarPengajuan)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            Belum pernah membuat pengajuan penarikan tabungan.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarPengajuan as $p): ?>
                        <tr>
                            <td class="small"><?= format_tanggal($p['tanggal_pengajuan']) ?></td>
                            <td class="fw-bold fs-6 text-danger">
                                <?= format_rupiah($p['nominal']) ?>
                            </td>
                            <td class="small text-muted text-truncate" style="max-width: 250px;">
                                <?= htmlspecialchars($p['keterangan'] ?: '-') ?>
                            </td>
                            <td>
                                <span class="badge badge-status-<?= $p['status'] ?> text-uppercase">
                                    <?= $p['status'] ?>
                                </span>
                            </td>
                            <td class="small text-muted">
                                <?= htmlspecialchars($p['catatan_petugas'] ?: '-') ?>
                            </td>
                            <td class="small text-muted">
                                <?= $p['tanggal_proses'] ? date('d/m/Y H:i', strtotime($p['tanggal_proses'])) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function tarikSemua() {
    const maxSaldo = <?= (float)$saldoSaatIni ?>;
    if (maxSaldo > 0) {
        document.getElementById('nominalInput').value = maxSaldo;
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
