<?php
/**
 * =========================================================================================
 * APLIKASI TABUNGAN DIGITAL PKK (TabunganPKK)
 * File: admin/pengguna.php
 * -----------------------------------------------------------------------------------------
 * PENJELASAN MATERI UNTUK PRESENTASI KE GURU:
 * 1. Fungsi Modul (CRUD Pengguna):
 *    Modul ini mengimplementasikan siklus lengkap CRUD (Create, Read, Update, Delete) akun:
 *    - CREATE: Admin mendaftarkan pengguna baru dengan role (Admin, Bendahara, Pengguna).
 *              Jika role adalah 'Pengguna', sistem otomatis membuatkan profil anggota dan nomor anggota.
 *    - READ: Menampilkan tabel pengguna dengan filter pencarian dan filter hak akses.
 *    - UPDATE: Mengedit biodata pengguna, mengubah role, serta mengubah password baru.
 *    - DELETE: Menghapus akun pengguna yang tidak aktif dengan proteksi konfirmasi JavaScript.
 *
 * 2. Aspek Keamanan Penting:
 *    - Anti Self-Destruction: Admin dicegah secara logika agar tidak dapat menghapus atau
 *      menonaktifkan akunnya sendiri yang sedang aktif digunakan login.
 *    - Pengecekan Duplikasi: Username dan Email diuji keunikannya sebelum disimpan.
 *    - BCRYPT Re-Hashing: Password hanya dienkripsi ulang jika admin mengisi kolom password baru.
 * =========================================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

require_role(['admin']);

$pdo = getDBConnection();
$pageTitle = 'Kelola Pengguna';
$pageHeading = 'Manajemen Pengguna';
$pageSubheading = 'Kelola akun Admin, Bendahara, dan Anggota sistem PKK';

// Handle Action: Tambah Pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'pengguna';
    $status = $_POST['status'] ?? 'aktif';
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    if (empty($nama) || empty($username) || empty($email) || empty($password)) {
        set_flash('danger', 'Semua kolom wajib (Nama, Username, Email, Password) harus diisi.');
    } else {
        // Cek duplikasi username / email
        $cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $cek->execute([$username, $email]);
        if ($cek->fetchColumn() > 0) {
            set_flash('danger', 'Username atau Email sudah terdaftar dalam sistem.');
        } else {
            $hashedPass = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                INSERT INTO users (nama, username, email, password, role, status, no_hp, alamat)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nama, $username, $email, $hashedPass, $role, $status, $no_hp, $alamat]);
            $newUserId = $pdo->lastInsertId();

            // Jika role adalah pengguna, buatkan juga data anggota PKK otomatis jika diminta
            if ($role === 'pengguna' && isset($_POST['buat_anggota']) && $_POST['buat_anggota'] == '1') {
                $noAnggota = 'PKK-' . date('Y') . '-' . sprintf('%03d', rand(100, 999));
                $stmtAnggota = $pdo->prepare("
                    INSERT INTO anggota (user_id, nomor_anggota, nama, nomor_hp, alamat, tanggal_gabung, status)
                    VALUES (?, ?, ?, ?, ?, CURDATE(), 'aktif')
                ");
                $stmtAnggota->execute([$newUserId, $noAnggota, $nama, $no_hp, $alamat]);
            }

            set_flash('success', "Pengguna '$nama' berhasil ditambahkan.");
            header("Location: " . base_url('admin/pengguna.php'));
            exit;
        }
    }
}

// Handle Action: Edit Pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'pengguna';
    $status = $_POST['status'] ?? 'aktif';
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    if ($userId <= 0 || empty($nama) || empty($username) || empty($email)) {
        set_flash('danger', 'Data pengguna tidak valid.');
    } else {
        // Cek duplikasi dengan user lain
        $cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $cek->execute([$username, $email, $userId]);
        if ($cek->fetchColumn() > 0) {
            set_flash('danger', 'Username atau Email sudah digunakan pengguna lain.');
        } else {
            if (!empty($password)) {
                $hashedPass = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    UPDATE users SET nama = ?, username = ?, email = ?, password = ?, role = ?, status = ?, no_hp = ?, alamat = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nama, $username, $email, $hashedPass, $role, $status, $no_hp, $alamat, $userId]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users SET nama = ?, username = ?, email = ?, role = ?, status = ?, no_hp = ?, alamat = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nama, $username, $email, $role, $status, $no_hp, $alamat, $userId]);
            }

            // Sync ke tabel anggota jika ada
            $syncAnggota = $pdo->prepare("UPDATE anggota SET nama = ?, nomor_hp = ?, alamat = ? WHERE user_id = ?");
            $syncAnggota->execute([$nama, $no_hp, $alamat, $userId]);

            set_flash('success', "Data pengguna '$nama' berhasil diperbarui.");
            header("Location: " . base_url('admin/pengguna.php'));
            exit;
        }
    }
}

// Handle Action: Toggle Status
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    if ($userId == $_SESSION['user_id']) {
        set_flash('warning', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
    } else {
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currStatus = $stmt->fetchColumn();
        $newStatus = ($currStatus === 'aktif') ? 'nonaktif' : 'aktif';

        $stmtUp = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmtUp->execute([$newStatus, $userId]);
        set_flash('success', "Status akun berhasil diubah menjadi: $newStatus");
    }
    header("Location: " . base_url('admin/pengguna.php'));
    exit;
}

// Handle Action: Hapus Pengguna
if (isset($_GET['hapus']) && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    if ($userId == $_SESSION['user_id']) {
        set_flash('danger', 'Anda tidak dapat menghapus akun Anda sendiri.');
    } else {
        $stmtNama = $pdo->prepare("SELECT nama FROM users WHERE id = ?");
        $stmtNama->execute([$userId]);
        $namaUser = $stmtNama->fetchColumn();

        $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmtDel->execute([$userId]);
        set_flash('success', "Pengguna '$namaUser' berhasil dihapus dari sistem.");
    }
    header("Location: " . base_url('admin/pengguna.php'));
    exit;
}

// Ambil list pengguna
$search = trim($_GET['q'] ?? '');
$filterRole = trim($_GET['role'] ?? '');

$sql = "
    SELECT u.*, a.nomor_anggota 
    FROM users u
    LEFT JOIN anggota a ON a.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.nama LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR a.nomor_anggota LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($filterRole)) {
    $sql .= " AND u.role = ?";
    $params[] = $filterRole;
}

$sql .= " ORDER BY u.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarPengguna = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1">Daftar Pengguna Sistem</h4>
        <p class="text-muted small mb-0">Total <?= count($daftarPengguna) ?> akun terdaftar pada sistem TabunganPKK</p>
    </div>
    <div>
        <button class="btn btn-pkk-primary" data-bs-toggle="modal" data-bs-target="#modalTambahPengguna">
            <i class="fa-solid fa-user-plus me-1"></i> Tambah Pengguna Baru
        </button>
    </div>
</div>

<!-- Filter & Search Bar -->
<div class="card-pkk mb-4">
    <div class="card-pkk-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari nama, username, email, no anggota..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="">Semua Role (Hak Akses)</option>
                    <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="bendahara" <?= $filterRole === 'bendahara' ? 'selected' : '' ?>>Bendahara</option>
                    <option value="pengguna" <?= $filterRole === 'pengguna' ? 'selected' : '' ?>>Pengguna (Anggota)</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-pkk-primary">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <a href="<?= base_url('admin/pengguna.php') ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Pengguna -->
<div class="card-pkk">
    <div class="table-responsive">
        <table class="table-pkk">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama & No. Anggota</th>
                    <th>Username / Email</th>
                    <th>No. HP & Alamat</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Tgl Bergabung</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarPengguna)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fa-regular fa-user-slash fs-3 d-block mb-2"></i>
                            Tidak ada data pengguna yang sesuai dengan kriteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarPengguna as $u): ?>
                        <tr>
                            <td class="text-muted fw-bold">#<?= $u['id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($u['nama']) ?></div>
                                <?php if ($u['nomor_anggota']): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle small font-monospace">
                                        <?= htmlspecialchars($u['nomor_anggota']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small fw-semibold text-secondary">@<?= htmlspecialchars($u['username']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td>
                                <div class="small"><?= htmlspecialchars($u['no_hp'] ?: '-') ?></div>
                                <div class="text-muted small text-truncate" style="max-width: 180px;"><?= htmlspecialchars($u['alamat'] ?: '-') ?></div>
                            </td>
                            <td>
                                <span class="badge badge-role-<?= $u['role'] ?> text-uppercase">
                                    <?= $u['role'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-status-<?= $u['status'] ?> text-uppercase">
                                    <?= $u['status'] ?>
                                </span>
                            </td>
                            <td class="small text-muted">
                                <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <!-- Edit Button (Modal) -->
                                    <button class="btn btn-light border text-primary" data-bs-toggle="modal" data-bs-target="#modalEditPengguna<?= $u['id'] ?>" title="Edit Pengguna">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    
                                    <!-- Toggle Status Button -->
                                    <a href="<?= base_url('admin/pengguna.php?toggle_status=1&id=' . $u['id']) ?>" class="btn btn-light border <?= $u['status'] === 'aktif' ? 'text-warning' : 'text-success' ?>" title="<?= $u['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                        <i class="fa-solid <?= $u['status'] === 'aktif' ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                    </a>

                                    <!-- Delete Button -->
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <a href="<?= base_url('admin/pengguna.php?hapus=1&id=' . $u['id']) ?>" class="btn btn-light border text-danger btn-confirm-delete" data-name="<?= htmlspecialchars($u['nama']) ?>" title="Hapus Pengguna">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <!-- Modal Edit Pengguna -->
                        <div class="modal fade" id="modalEditPengguna<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content" style="border-radius: 14px;">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">

                                        <div class="modal-header border-bottom">
                                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-pen me-2 text-primary"></i> Edit Pengguna</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Nama Lengkap *</label>
                                                <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($u['nama']) ?>" required>
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold">Username *</label>
                                                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($u['username']) ?>" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold">Email *</label>
                                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Ganti Password (Kosongkan jika tidak diubah)</label>
                                                <input type="password" name="password" class="form-control" placeholder="Ketik kata sandi baru">
                                            </div>
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold">Role Pengguna *</label>
                                                    <select name="role" class="form-select">
                                                        <option value="pengguna" <?= $u['role'] === 'pengguna' ? 'selected' : '' ?>>Pengguna (Anggota)</option>
                                                        <option value="bendahara" <?= $u['role'] === 'bendahara' ? 'selected' : '' ?>>Bendahara</option>
                                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-semibold">Status Akun *</label>
                                                    <select name="status" class="form-select">
                                                        <option value="aktif" <?= $u['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                                        <option value="nonaktif" <?= $u['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Nomor WhatsApp / HP</label>
                                                <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($u['no_hp'] ?: '') ?>">
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small fw-semibold">Alamat Rumah</label>
                                                <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($u['alamat'] ?: '') ?></textarea>
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

<!-- Modal Tambah Pengguna Baru -->
<div class="modal fade" id="modalTambahPengguna" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px;">
            <form method="POST" action="">
                <input type="hidden" name="action" value="tambah">

                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-2 text-success"></i> Tambah Pengguna Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap *</label>
                        <input type="text" name="nama" class="form-control" placeholder="Contoh: Ibu Siti Rahmawati" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Username *</label>
                            <input type="text" name="username" class="form-control" placeholder="ibu_siti" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Email *</label>
                            <input type="email" name="email" class="form-control" placeholder="siti@email.com" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password Awal *</label>
                        <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Role Pengguna *</label>
                            <select name="role" class="form-select" id="roleSelector">
                                <option value="pengguna" selected>Pengguna (Anggota)</option>
                                <option value="bendahara">Bendahara</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Status Akun *</label>
                            <select name="status" class="form-select">
                                <option value="aktif" selected>Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-check mb-3" id="wrapBuatAnggota">
                        <input class="form-check-input" type="checkbox" name="buat_anggota" value="1" id="buatAnggotaCheck" checked>
                        <label class="form-check-label small text-muted" for="buatAnggotaCheck">
                            Otomatis buatkan profil Anggota PKK dengan Nomor Anggota baru
                        </label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nomor WhatsApp / HP</label>
                        <input type="text" name="no_hp" class="form-control" placeholder="0812xxxxxxx">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Alamat Rumah</label>
                        <textarea name="alamat" class="form-control" rows="2" placeholder="Jl. Melati RT 01/05"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-pkk-primary btn-sm">Simpan Pengguna</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('roleSelector').addEventListener('change', function() {
    const wrap = document.getElementById('wrapBuatAnggota');
    if (this.value === 'pengguna') {
        wrap.style.display = 'block';
    } else {
        wrap.style.display = 'none';
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
