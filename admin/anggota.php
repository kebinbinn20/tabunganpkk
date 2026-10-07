<?php
/**
 * =========================================================================================
 * APLIKASI TABUNGAN DIGITAL PKK (TabunganPKK)
 * File: admin/anggota.php
 * -----------------------------------------------------------------------------------------
 * PENJELASAN MATERI UNTUK PRESENTASI KE GURU:
 * 1. Manajemen Keanggotaan PKK:
 *    Halaman ini mengelola identitas anggota resmi PKK (Nomor Anggota unik, Nama, NIK, No. HP,
 *    Alamat, Tanggal Bergabung, dan Status Aktif/Nonaktif).
 *
 * 2. Teknik Query SQL Canggih (Live Aggregation Balance):
 *    Di halaman ini, sistem menggabungkan 3 tabel sekaligus (anggota, users, dan transaksi)
 *    menggunakan klausa 'LEFT JOIN' dan 'GROUP BY a.id'.
 *    - Saldo setiap anggota dihitung detik itu juga dari seluruh riwayat setoran dan penarikan.
 *    - Nilai saldo yang tampil adalah 100% akurat dan tidak ada risiko selisih hitung kasir.
 *
 * 3. Fitur Filter & Pencarian Multikolom:
 *    Pengguna dapat mencari anggota secara fleksibel berdasarkan Nama, Nomor Anggota, NIK KTP,
 *    Nomor Telepon, maupun Alamat menggunakan operator 'LIKE %...%'.
 * =========================================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

require_role(['admin']);

$pdo = getDBConnection();
$pageTitle = 'Kelola Anggota PKK';
$pageHeading = 'Manajemen Anggota PKK';
$pageSubheading = 'Kelola data identitas anggota, status kepesertaan, dan saldo tabungan';

// Handle Action: Tambah Anggota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    $nomor_anggota = trim($_POST['nomor_anggota'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $nomor_hp = trim($_POST['nomor_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $tanggal_gabung = $_POST['tanggal_gabung'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'aktif';

    if (empty($nama)) {
        set_flash('danger', 'Nama anggota wajib diisi.');
    } else {
        // Generate nomor anggota jika kosong
        if (empty($nomor_anggota)) {
            $nomor_anggota = 'PKK-' . date('Y') . '-' . sprintf('%03d', rand(100, 999));
        }

        // Cek duplikasi nomor anggota
        $cek = $pdo->prepare("SELECT COUNT(*) FROM anggota WHERE nomor_anggota = ?");
        $cek->execute([$nomor_anggota]);
        if ($cek->fetchColumn() > 0) {
            set_flash('danger', 'Nomor anggota ' . htmlspecialchars($nomor_anggota) . ' sudah terdaftar.');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO anggota (nomor_anggota, nama, nik, nomor_hp, alamat, tanggal_gabung, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nomor_anggota, $nama, $nik, $nomor_hp, $alamat, $tanggal_gabung, $status]);
            set_flash('success', "Anggota '$nama' ($nomor_anggota) berhasil ditambahkan.");
            header("Location: " . base_url('admin/anggota.php'));
            exit;
        }
    }
}

// Handle Action: Edit Anggota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $anggotaId = (int)($_POST['anggota_id'] ?? 0);
    $nomor_anggota = trim($_POST['nomor_anggota'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $nomor_hp = trim($_POST['nomor_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $tanggal_gabung = $_POST['tanggal_gabung'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'aktif';

    if ($anggotaId <= 0 || empty($nama) || empty($nomor_anggota)) {
        set_flash('danger', 'Data anggota tidak lengkap.');
    } else {
        // Cek duplikasi nomor anggota dengan anggota lain
        $cek = $pdo->prepare("SELECT COUNT(*) FROM anggota WHERE nomor_anggota = ? AND id != ?");
        $cek->execute([$nomor_anggota, $anggotaId]);
        if ($cek->fetchColumn() > 0) {
            set_flash('danger', 'Nomor anggota sudah digunakan oleh anggota lain.');
        } else {
            $stmt = $pdo->prepare("
                UPDATE anggota SET nomor_anggota = ?, nama = ?, nik = ?, nomor_hp = ?, alamat = ?, tanggal_gabung = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$nomor_anggota, $nama, $nik, $nomor_hp, $alamat, $tanggal_gabung, $status, $anggotaId]);
            set_flash('success', "Data anggota '$nama' berhasil diperbarui.");
            header("Location: " . base_url('admin/anggota.php'));
            exit;
        }
    }
}

// Handle Action: Hapus Anggota
if (isset($_GET['hapus']) && isset($_GET['id'])) {
    $anggotaId = (int)$_GET['id'];
    $stmtNama = $pdo->prepare("SELECT nama FROM anggota WHERE id = ?");
    $stmtNama->execute([$anggotaId]);
    $namaAnggota = $stmtNama->fetchColumn();

    $stmtDel = $pdo->prepare("DELETE FROM anggota WHERE id = ?");
    $stmtDel->execute([$anggotaId]);
    set_flash('success', "Anggota '$namaAnggota' beserta riwayat tabungannya telah dihapus.");
    header("Location: " . base_url('admin/anggota.php'));
    exit;
}

// Query Filter & Search
$search = trim($_GET['q'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$sql = "
    SELECT a.*, u.username,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'setoran' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) -
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'penarikan' AND t.status = 'berhasil' THEN t.nominal ELSE 0 END), 0) AS saldo_tabungan
    FROM anggota a
    LEFT JOIN users u ON u.id = a.user_id
    LEFT JOIN transaksi t ON t.anggota_id = a.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (a.nama LIKE ? OR a.nomor_anggota LIKE ? OR a.nik LIKE ? OR a.nomor_hp LIKE ? OR a.alamat LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if (!empty($filterStatus)) {
    $sql .= " AND a.status = ?";
    $params[] = $filterStatus;
}

$sql .= " GROUP BY a.id ORDER BY a.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarAnggota = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1">Daftar Anggota PKK</h4>
        <p class="text-muted small mb-0">Total <?= count($daftarAnggota) ?> anggota terdaftar dengan saldo tabungan masing-masing</p>
    </div>
    <div>
        <button class="btn btn-pkk-primary" data-bs-toggle="modal" data-bs-target="#modalTambahAnggota">
            <i class="fa-solid fa-user-plus me-1"></i> Tambah Anggota PKK
        </button>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card-pkk mb-4">
    <div class="card-pkk-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari nama, no. anggota, NIK, alamat..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status Keanggotaan</option>
                    <option value="aktif" <?= $filterStatus === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= $filterStatus === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-pkk-primary">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <a href="<?= base_url('admin/anggota.php') ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Anggota -->
<div class="card-pkk">
    <div class="table-responsive">
        <table class="table-pkk">
            <thead>
                <tr>
                    <th>No. Anggota</th>
                    <th>Nama Anggota & NIK</th>
                    <th>No. HP</th>
                    <th>Alamat</th>
                    <th>Tanggal Gabung</th>
                    <th>Saldo Tabungan</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarAnggota)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-users-slash fs-3 d-block mb-2"></i>
                            Tidak ada anggota PKK yang ditemukan.
                        </td>
                    </tr>
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
                                <small class="text-muted">NIK: <?= htmlspecialchars($ag['nik'] ?: '-') ?></small>
                            </td>
                            <td><?= htmlspecialchars($ag['nomor_hp'] ?: '-') ?></td>
                            <td>
                                <div class="text-truncate text-muted small" style="max-width: 200px;">
                                    <?= htmlspecialchars($ag['alamat'] ?: '-') ?>
                                </div>
                            </td>
                            <td class="small text-muted"><?= format_tanggal($ag['tanggal_gabung']) ?></td>
                            <td>
                                <span class="fw-bold fs-6 <?= (float)$ag['saldo_tabungan'] > 0 ? 'text-success' : 'text-muted' ?>">
                                    <?= format_rupiah($ag['saldo_tabungan']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-status-<?= $ag['status'] ?> text-uppercase">
                                    <?= $ag['status'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <!-- Detail Anggota & Riwayat -->
                                    <a href="<?= base_url('admin/anggota_detail.php?id=' . $ag['id']) ?>" class="btn btn-light border text-info" title="Lihat Detail & Riwayat Tabungan">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <!-- Edit Anggota -->
                                    <button class="btn btn-light border text-primary" data-bs-toggle="modal" data-bs-target="#modalEditAnggota<?= $ag['id'] ?>" title="Edit Anggota">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <!-- Hapus Anggota -->
                                    <a href="<?= base_url('admin/anggota.php?hapus=1&id=' . $ag['id']) ?>" class="btn btn-light border text-danger btn-confirm-delete" data-name="<?= htmlspecialchars($ag['nama']) ?>" title="Hapus Anggota">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>

                        <!-- Modal Edit Anggota -->
                        <div class="modal fade" id="modalEditAnggota<?= $ag['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content" style="border-radius: 14px;">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="anggota_id" value="<?= $ag['id'] ?>">

                                        <div class="modal-header border-bottom">
                                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-pen me-2 text-primary"></i> Edit Data Anggota</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold">Nomor Anggota *</label>
                                                    <input type="text" name="nomor_anggota" class="form-control" value="<?= htmlspecialchars($ag['nomor_anggota']) ?>" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold">Tanggal Gabung</label>
                                                    <input type="date" name="tanggal_gabung" class="form-control" value="<?= htmlspecialchars($ag['tanggal_gabung']) ?>" required>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Nama Lengkap *</label>
                                                <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($ag['nama']) ?>" required>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold">NIK (KTP)</label>
                                                    <input type="text" name="nik" class="form-control" value="<?= htmlspecialchars($ag['nik'] ?: '') ?>" placeholder="16 digit NIK">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold">Nomor WhatsApp / HP</label>
                                                    <input type="text" name="nomor_hp" class="form-control" value="<?= htmlspecialchars($ag['nomor_hp'] ?: '') ?>">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Status Keanggotaan</label>
                                                <select name="status" class="form-select">
                                                    <option value="aktif" <?= $ag['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                                    <option value="nonaktif" <?= $ag['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                                </select>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small fw-semibold">Alamat Rumah</label>
                                                <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($ag['alamat'] ?: '') ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-top bg-light">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-pkk-primary btn-sm">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Anggota -->
<div class="modal fade" id="modalTambahAnggota" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px;">
            <form method="POST" action="">
                <input type="hidden" name="action" value="tambah">

                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-2 text-success"></i> Tambah Anggota PKK Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Nomor Anggota</label>
                            <input type="text" name="nomor_anggota" class="form-control" placeholder="Kosongkan untuk otomatis">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Tanggal Gabung</label>
                            <input type="date" name="tanggal_gabung" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap *</label>
                        <input type="text" name="nama" class="form-control" placeholder="Contoh: Ibu Siti Fatimah" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">NIK (KTP)</label>
                            <input type="text" name="nik" class="form-control" placeholder="3201xxxxxxxxxxxx">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Nomor WhatsApp / HP</label>
                            <input type="text" name="nomor_hp" class="form-control" placeholder="0812xxxxxxxx">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status Keanggotaan</label>
                        <select name="status" class="form-select">
                            <option value="aktif" selected>Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Alamat Rumah</label>
                        <textarea name="alamat" class="form-control" rows="2" placeholder="Jl. Anggrek No. 12 RT 03/05"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-pkk-primary btn-sm">Simpan Anggota</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
