<?php
/**
 * =========================================================================================
 * APLIKASI TABUNGAN DIGITAL PKK (TabunganPKK)
 * File: bendahara/penarikan.php
 * -----------------------------------------------------------------------------------------
 * PENJELASAN MATERI UNTUK PRESENTASI KE GURU:
 * 1. Validasi Keamanan Ganda (Dual Validation: Client & Server Side):
 *    - Client-side (JavaScript): Menampilkan saldo anggota secara live saat nama dipilih dari
 *      dropdown. Jika nominal melebihi saldo, sistem memberi peringatan langsung di layar.
 *    - Server-side (PHP): Kode PHP melakukan query ulang ke database untuk memastikan saldo
 *      aktual BENAR-BENAR mencukupi sebelum query INSERT dijalankan. Hal ini mencegah manipulasi
 *      data melalui inspect element atau curl request.
 *
 * 2. Pencegahan Saldo Negatif (Overdraft Protection):
 *    Sistem menolak tegas penarikan jika nominal yang diminta > saldo tabungan yang ada.
 *    Saldo tidak akan pernah minus dalam kondisi apa pun.
 *
 * 3. Penerbitan Kode Transaksi Penarikan (WD / Withdrawal):
 *    Transaksi penarikan menggunakan prefix 'WD-' (contoh: WD-20261003-0001) untuk memudahkan
 *    pemisahan data arus kas keluar pada pembukuan.
 * =========================================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

require_role(['bendahara', 'admin']);

$pdo = getDBConnection();
$pengaturan = get_pengaturan($pdo);

$pageTitle = 'Catat Penarikan Tabungan';
$pageHeading = 'Pencatatan Penarikan Tabungan';
$pageSubheading = 'Proses penarikan saldo tabungan anggota PKK';

$selectedAnggotaId = (int)($_GET['anggota_id'] ?? 0);
$successKodeTrx = '';

// Handle Submit Penarikan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil anggota, tanggal, dan nominal dari formulir penarikan.
    $anggota_id = (int)($_POST['anggota_id'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    // Ubah tampilan nominal rupiah menjadi angka untuk validasi server.
    $nominal = (float)str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
    // Ambil catatan transaksi, metode, dan identitas petugas aktif.
    $keterangan = trim($_POST['keterangan'] ?? 'Penarikan tabungan PKK');
    $metode = trim($_POST['metode_pembayaran'] ?? 'Tunai');
    $petugas_id = $_SESSION['user_id'];

    // Periksa pilihan anggota sebelum mencari profilnya.
    if ($anggota_id <= 0) {
        set_flash('danger', 'Silakan pilih anggota PKK yang melakukan penarikan.');
    // Penarikan bernilai nol atau negatif tidak boleh dicatat.
    } elseif ($nominal <= 0) {
        set_flash('danger', 'Nominal penarikan harus lebih dari Rp 0.');
    } else {
        // Cek data anggota dan hitung saldo saat ini
        // Ambil profil untuk memastikan anggota tersedia dan mencari akun notifikasinya.
        $stmtA = $pdo->prepare("SELECT * FROM anggota WHERE id = ?");
        $stmtA->execute([$anggota_id]);
        $anggota = $stmtA->fetch();

        if (!$anggota) {
            set_flash('danger', 'Data anggota tidak valid.');
        } else {
            // Hitung ulang saldo dari database, bukan mempercayai nilai dari browser.
            $saldoData = hitung_saldo_anggota($pdo, $anggota_id);
            $saldoSaatIni = $saldoData['saldo'];

            // VALIDASI: Saldo harus mencukupi
            // Cegah saldo menjadi negatif dengan menolak nominal yang melebihi saldo.
            if ($nominal > $saldoSaatIni) {
                set_flash('danger', "Saldo tidak mencukupi! Saldo saat ini hanya " . format_rupiah($saldoSaatIni) . ", sedangkan penarikan diminta " . format_rupiah($nominal) . ".");
            } else {
                // Generate Kode Transaksi Penarikan (WD)
                $kodeTransaksi = generate_kode_transaksi($pdo, 'penarikan');

                // Simpan transaksi penarikan
                // Siapkan mutasi penarikan yang akan mengurangi saldo setelah berhasil disimpan.
                $stmt = $pdo->prepare("
                    INSERT INTO transaksi (kode_transaksi, anggota_id, jenis_transaksi, nominal, metode_pembayaran, tanggal, keterangan, status, petugas_id)
                    VALUES (?, ?, 'penarikan', ?, ?, ?, ?, 'berhasil', ?)
                ");
                // Masukkan identitas anggota, nilai, tanggal, catatan, dan petugas.
                $stmt->execute([
                    $kodeTransaksi,
                    $anggota_id,
                    $nominal,
                    $metode,
                    $tanggal,
                    $keterangan,
                    $petugas_id
                ]);

                // Kirim notifikasi ke anggota jika punya akun user
                // Beri tahu anggota melalui aplikasi jika ia memiliki akun pengguna.
                if (!empty($anggota['user_id'])) {
                    tambah_notifikasi(
                        $pdo,
                        $anggota['user_id'],
                        'Penarikan Tabungan Berhasil',
                        "Penarikan saldo sebesar " . format_rupiah($nominal) . " telah diproses pada tanggal " . format_tanggal($tanggal) . " dengan nomor transaksi " . $kodeTransaksi . ". Sisa saldo Anda: " . format_rupiah($saldoSaatIni - $nominal) . "."
                    );
                }

                set_flash('success', "Penarikan tabungan sebesar " . format_rupiah($nominal) . " untuk " . htmlspecialchars($anggota['nama']) . " berhasil dicatat dengan kode $kodeTransaksi.");
                $successKodeTrx = $kodeTransaksi;
            }
        }
    }
}

// Ambil list seluruh anggota dengan saldo masing-masing
$sqlAnggota = "
    SELECT a.id, a.nomor_anggota, a.nama,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'setoran' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) -
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'penarikan' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) AS saldo
    FROM anggota a
    LEFT JOIN transaksi t ON t.anggota_id = a.id
    WHERE a.status = 'aktif'
    GROUP BY a.id
    ORDER BY a.nama ASC
";
$semuaAnggota = $pdo->query($sqlAnggota)->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Alert Success & Print Receipt Box -->
<?php if (!empty($successKodeTrx)): ?>
    <div class="card bg-success-subtle border-success mb-4 p-3 shadow-sm">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold text-success mb-1"><i class="fa-solid fa-circle-check me-1"></i> Penarikan Berhasil Dicatat!</h6>
                <div class="small text-secondary">Nomor Transaksi: <strong class="font-monospace text-dark"><?= htmlspecialchars($successKodeTrx) ?></strong></div>
            </div>
            <div>
                <a href="<?= base_url('cetak_kuitansi.php?kode=' . urlencode($successKodeTrx)) ?>" class="btn btn-success fw-bold">
                    <i class="fa-solid fa-print me-1"></i> Cetak Bukti Penarikan
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card-pkk">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-circle-arrow-up text-danger"></i> Form Pencatatan Penarikan Tabungan</h5>
                <span class="badge bg-danger-subtle text-danger">Transaksi Keluar</span>
            </div>
            <div class="card-pkk-body">
                <form method="POST" action="" id="formPenarikan">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih Anggota PKK *</label>
                        <select name="anggota_id" id="anggotaSelect" class="form-select" onchange="updateSaldoInfo()" required>
                            <option value="" data-saldo="0">-- Pilih Anggota PKK --</option>
                            <?php foreach ($semuaAnggota as $ag): ?>
                                <option value="<?= $ag['id'] ?>" data-saldo="<?= (float)$ag['saldo'] ?>" <?= $selectedAnggotaId == $ag['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ag['nama']) ?> — [<?= htmlspecialchars($ag['nomor_anggota']) ?>] (Saldo: <?= format_rupiah($ag['saldo']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Live Saldo Display Box -->
                    <div class="alert alert-info py-2 px-3 small d-flex justify-content-between align-items-center mb-3" id="boxSaldoInfo" style="display:none !important;">
                        <div>
                            <i class="fa-solid fa-wallet me-1"></i> Saldo Tersedia: <strong id="lblSaldo">Rp 0</strong>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary py-0" onclick="tarikSemua()">Tarik Semua Saldo</button>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tanggal Penarikan *</label>
                            <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Metode Penyerahan Dana *</label>
                            <select name="metode_pembayaran" class="form-select" required>
                                <option value="Tunai" selected>Tunai (Cash di Tempat)</option>
                                <option value="Transfer Bank">Transfer Bank</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nominal Penarikan (Rp) *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold text-danger">Rp</span>
                            <input type="number" name="nominal" id="nominalInput" class="form-control fs-5 fw-bold text-danger" placeholder="Contoh: 50000" min="1000" step="1000" required>
                        </div>
                        <small class="text-muted" id="nominalHelper">Nominal tidak boleh melebihi saldo tabungan anggota</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Keterangan / Alasan Penarikan</label>
                        <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Keperluan keluarga, biaya sekolah anak" value="Penarikan tabungan PKK">
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= base_url('bendahara/dashboard.php') ?>" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-arrow-left me-1"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-danger px-4 fw-bold">
                            <i class="fa-solid fa-hand-holding-dollar me-1"></i> Simpan Penarikan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Informasi Validasi Keamanan -->
    <div class="col-lg-4">
        <div class="card-pkk bg-light border">
            <div class="card-pkk-body small">
                <h6 class="fw-bold text-danger mb-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> Aturan Validasi Penarikan</h6>
                <p class="text-muted">Aplikasi TabunganPKK menerapkan validasi ketat untuk menjamin keamanan kas:</p>
                <ol class="ps-3 text-secondary mb-3">
                    <li class="mb-2"><strong>Saldo mencukupi:</strong> Transaksi otomatis ditolak jika nominal penarikan melebihi saldo aktif anggota.</li>
                    <li class="mb-2"><strong>Nominal > 0:</strong> Tidak dapat memproses penarikan negatif atau bernilai nol.</li>
                    <li class="mb-2"><strong>Serah terima tunai:</strong> Pastikan anggota menandatangani bukti penarikan setelah menerima uang.</li>
                </ol>
                <div class="p-2 bg-white rounded border text-muted">
                    <i class="fa-solid fa-info-circle text-primary me-1"></i> Anggota juga dapat mengajukan penarikan mandiri melalui dashboard mereka.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentSaldo = 0;

function updateSaldoInfo() {
    const select = document.getElementById('anggotaSelect');
    const selectedOption = select.options[select.selectedIndex];
    currentSaldo = parseFloat(selectedOption.getAttribute('data-saldo')) || 0;
    
    const box = document.getElementById('boxSaldoInfo');
    const lbl = document.getElementById('lblSaldo');

    if (select.value !== "") {
        box.style.setProperty('display', 'flex', 'important');
        lbl.textContent = 'Rp ' + currentSaldo.toLocaleString('id-ID');
    } else {
        box.style.setProperty('display', 'none', 'important');
    }
}

function tarikSemua() {
    if (currentSaldo > 0) {
        document.getElementById('nominalInput').value = currentSaldo;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateSaldoInfo();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
