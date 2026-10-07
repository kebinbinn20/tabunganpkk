<?php
/**
 * =========================================================================================
 * APLIKASI TABUNGAN DIGITAL PKK (TabunganPKK)
 * File: bendahara/setoran.php
 * -----------------------------------------------------------------------------------------
 * PENJELASAN MATERI UNTUK PRESENTASI KE GURU:
 * 1. Alur Transaksi Kas Masuk (Deposit Workflow):
 *    - Bendahara memilih nama anggota yang menyetor tabungan.
 *    - Memasukkan nominal uang (diverifikasi harus >= batas minimal setoran di pengaturan).
 *    - Memilih metode transaksi (Tunai langsung, Transfer Bank, atau QRIS).
 *    - Opsional: Mengunggah foto bukti struk transfer jika non-tunai.
 *
 * 2. Keamanan Unggah Berkas (File Upload Security):
 *    - Whitelist Ekstensi: Hanya mengizinkan berkas gambar (JPG, PNG) atau dokumen PDF.
 *    - File Renaming: Nama berkas di-generate ulang dengan timestamp acak agar tidak menimpa
 *      berkas lain dan mencegah eksekusi file berbahaya (.php.jpg).
 *
 * 3. Otomatisasi Terintegrasi:
 *    - Saldo anggota langsung bertambah otomatis pada detik yang sama.
 *    - Menerbitkan Kode Transaksi unik berurutan: TRX-YYYYMMDD-XXXX.
 *    - Mengirim notifikasi otomatis ke smartphone/akun anggota yang bersangkutan.
 *    - Menyediakan tombol langsung Cetak Bukti Kwitansi Sah.
 * =========================================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

require_role(['bendahara', 'admin']);

$pdo = getDBConnection();
$pengaturan = get_pengaturan($pdo);

$pageTitle = 'Catat Setoran Tabungan';
$pageHeading = 'Pencatatan Setoran Tabungan';
$pageSubheading = 'Input setoran tunai atau transfer anggota PKK';

$selectedAnggotaId = (int)($_GET['anggota_id'] ?? 0);
$successKodeTrx = '';

// Handle Submit Setoran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil ID anggota dan tanggal transaksi dari formulir.
    $anggota_id = (int)($_POST['anggota_id'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    // Normalisasi nominal rupiah menjadi angka sebelum divalidasi.
    $nominal = (float)str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
    // Ambil metode, keterangan, dan ID bendahara yang sedang login.
    $metode_pembayaran = trim($_POST['metode_pembayaran'] ?? 'Tunai');
    $keterangan = trim($_POST['keterangan'] ?? 'Setoran tabungan PKK');
    $petugas_id = $_SESSION['user_id'];

    // Tolak data tanpa anggota yang valid.
    if ($anggota_id <= 0) {
        set_flash('danger', 'Silakan pilih anggota PKK yang melakukan setoran.');
    // Nominal setoran harus bernilai positif.
    } elseif ($nominal <= 0) {
        set_flash('danger', 'Nominal setoran harus lebih besar dari Rp 0.');
    // Bandingkan nominal dengan batas minimum yang disetel admin.
    } elseif ($nominal < (float)$pengaturan['nominal_minimal_setor']) {
        set_flash('danger', 'Nominal setoran minimal adalah ' . format_rupiah($pengaturan['nominal_minimal_setor']));
    } else {
        // Ambil info anggota
        // Pastikan anggota benar-benar ada sebelum mencatat uang masuk.
        $stmtA = $pdo->prepare("SELECT * FROM anggota WHERE id = ?");
        $stmtA->execute([$anggota_id]);
        $anggota = $stmtA->fetch();

        if (!$anggota) {
            set_flash('danger', 'Data anggota tidak valid.');
        } else {
            // Handle upload bukti transaksi jika ada
            // Nilai awal NULL berarti transaksi tidak memiliki lampiran bukti.
            $buktiPath = null;
            if (isset($_FILES['bukti_transaksi']) && $_FILES['bukti_transaksi']['error'] === UPLOAD_ERR_OK) {
                // Ambil lokasi sementara dan nama asli file yang diunggah.
                $fileTmp = $_FILES['bukti_transaksi']['tmp_name'];
                $fileName = $_FILES['bukti_transaksi']['name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

                // Terima hanya ekstensi gambar dan PDF yang sudah diizinkan.
                if (in_array($ext, $allowed)) {
                    $uploadDir = __DIR__ . '/../uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    // Buat nama baru agar nama asli tidak membuka informasi dan tidak bertabrakan.
                    $newFileName = 'bukti_setor_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                        $buktiPath = 'uploads/' . $newFileName;
                    }
                }
            }

            // Generate Kode Transaksi Unik
            $kodeTransaksi = generate_kode_transaksi($pdo, 'setoran');

            // Simpan Transaksi ke Database
            // Siapkan insert setoran berstatus berhasil agar masuk hitungan saldo.
            $stmt = $pdo->prepare("
                INSERT INTO transaksi (kode_transaksi, anggota_id, jenis_transaksi, nominal, metode_pembayaran, bukti_transaksi, tanggal, keterangan, status, petugas_id)
                VALUES (?, ?, 'setoran', ?, ?, ?, ?, ?, 'berhasil', ?)
            ");
            // Kirim data transaksi sebagai parameter prepared statement.
            $stmt->execute([
                $kodeTransaksi,
                $anggota_id,
                $nominal,
                $metode_pembayaran,
                $buktiPath,
                $tanggal,
                $keterangan,
                $petugas_id
            ]);

            // Kirim notifikasi jika anggota tertaut ke akun user
            // Kirim notifikasi hanya jika profil anggota terhubung ke akun login.
            if (!empty($anggota['user_id'])) {
                tambah_notifikasi(
                    $pdo,
                    $anggota['user_id'],
                    'Setoran Tabungan Berhasil',
                    "Setoran sebesar " . format_rupiah($nominal) . " telah berhasil dicatat pada tanggal " . format_tanggal($tanggal) . " dengan nomor transaksi " . $kodeTransaksi . "."
                );
            }

            set_flash('success', "Setoran sebesar " . format_rupiah($nominal) . " untuk " . htmlspecialchars($anggota['nama']) . " berhasil disimpan dengan kode $kodeTransaksi.");
            $successKodeTrx = $kodeTransaksi;
        }
    }
}

// Ambil list semua anggota aktif
$semuaAnggota = $pdo->query("SELECT id, nomor_anggota, nama, nomor_hp FROM anggota WHERE status = 'aktif' ORDER BY nama ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<!-- Alert Success & Print Receipt Box -->
<?php if (!empty($successKodeTrx)): ?>
    <div class="card bg-success-subtle border-success mb-4 p-3 shadow-sm">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold text-success mb-1"><i class="fa-solid fa-circle-check me-1"></i> Transaksi Berhasil Dicatat!</h6>
                <div class="small text-secondary">Nomor Transaksi: <strong class="font-monospace text-dark"><?= htmlspecialchars($successKodeTrx) ?></strong></div>
            </div>
            <div>
                <a href="<?= base_url('cetak_kuitansi.php?kode=' . urlencode($successKodeTrx)) ?>" class="btn btn-success fw-bold">
                    <i class="fa-solid fa-print me-1"></i> Cetak Kwitansi Transaksi
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card-pkk">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-circle-arrow-down text-success"></i> Form Pencatatan Setoran Tabungan</h5>
                <span class="badge bg-success-subtle text-success">Transaksi Masuk</span>
            </div>
            <div class="card-pkk-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih Anggota PKK *</label>
                        <select name="anggota_id" id="anggotaSelect" class="form-select" required>
                            <option value="">-- Pilih Anggota PKK --</option>
                            <?php foreach ($semuaAnggota as $ag): ?>
                                <option value="<?= $ag['id'] ?>" <?= $selectedAnggotaId == $ag['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ag['nama']) ?> — [<?= htmlspecialchars($ag['nomor_anggota']) ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Pilih nama anggota yang menyerahkan uang tabungan</small>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tanggal Transaksi *</label>
                            <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Metode Pembayaran *</label>
                            <select name="metode_pembayaran" class="form-select" required>
                                <option value="Tunai" selected>Tunai (Cash di Pertemuan)</option>
                                <option value="Transfer Bank">Transfer Bank</option>
                                <option value="QRIS">QRIS / Dompet Digital</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nominal Setoran (Rp) *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold text-success">Rp</span>
                            <input type="number" name="nominal" class="form-control fs-5 fw-bold" placeholder="Contoh: 100000" min="<?= (int)$pengaturan['nominal_minimal_setor'] ?>" step="1000" required>
                        </div>
                        <small class="text-muted">Minimal setoran: <strong><?= format_rupiah($pengaturan['nominal_minimal_setor']) ?></strong></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Keterangan / Keperluan</label>
                        <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Setoran rutin PKK bulan ini" value="Setoran rutin tabungan PKK">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Bukti Pembayaran (Opsional jika Transfer)</label>
                        <input type="file" name="bukti_transaksi" class="form-control" accept="image/*,.pdf">
                        <small class="text-muted">Format: JPG, PNG, atau PDF (maks 5MB)</small>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= base_url('bendahara/dashboard.php') ?>" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-arrow-left me-1"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-pkk-primary px-4">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Setoran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Petunjuk Pencatatan -->
    <div class="col-lg-4">
        <div class="card-pkk mb-4 bg-light border">
            <div class="card-pkk-body small">
                <h6 class="fw-bold text-success mb-2"><i class="fa-solid fa-shield-halved me-1"></i> Standar Operasional Bendahara</h6>
                <ul class="ps-3 text-secondary mb-0">
                    <li class="mb-2">Hitung uang tunai secara teliti di hadapan penyetor sebelum menekan <strong>Simpan Setoran</strong>.</li>
                    <li class="mb-2">Saldo anggota akan langsung bertambah secara otomatis pada detik yang sama transaksi disimpan.</li>
                    <li class="mb-2">Sistem langsung menerbitkan nomor transaksi unik dan mencatat nama bendahara yang bertugas.</li>
                    <li>Segera berikan atau cetak <strong>Bukti Kwitansi Transaksi</strong> kepada anggota sebagai tanda terima sah.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
