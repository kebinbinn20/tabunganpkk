<?php
/**
 * =========================================================================================
 * APLIKASI TABUNGAN DIGITAL PKK (TabunganPKK)
 * File: admin/sistem.php
 * -----------------------------------------------------------------------------------------
 * PENJELASAN MATERI UNTUK PRESENTASI KE GURU:
 * 1. Fungsi Modul (Dynamic System Configuration):
 *    Modul ini mengimplementasikan konsep 'Dynamic App Configuration'. Informasi organisasi
 *    seperti nama kelompok PKK, aturan penarikan, batas minimal setoran, dan kontak resmi
 *    tidak di-hardcode di kode program, melainkan disimpan di tabel 'pengaturan' database.
 *    Dengan cara ini, pengurus PKK dapat memperbarui informasi kapan saja tanpa perlu menyentuh kode.
 *
 * 2. Keamanan Pembaruan Password Administrator:
 *    Untuk mengganti password akun admin, sistem mewajibkan verifikasi 'Password Saat Ini'
 *    menggunakan 'password_verify()' untuk mencegah pembajakan akun jika komputer ditinggal dalam keadaan login.
 * =========================================================================================
 */

// Muat koneksi PDO dan helper autentikasi sebelum memproses halaman admin.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

// Batasi halaman ini untuk akun administrator.
require_role(['admin']);

// Buka koneksi dan ambil konfigurasi organisasi yang akan diedit.
$pdo = getDBConnection();
$pengaturan = get_pengaturan($pdo);

$pageTitle = 'Kelola Sistem';
$pageHeading = 'Pengaturan Sistem';
$pageSubheading = 'Konfigurasi identitas organisasi, aturan tabungan, dan informasi aplikasi';

// Handle Action: Update Pengaturan Sistem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_sistem') {
    // Ambil identitas organisasi dan hilangkan spasi tepi dari input teks.
    $nama_organisasi = trim($_POST['nama_organisasi'] ?? '');
    $nama_aplikasi = trim($_POST['nama_aplikasi'] ?? '');
    // Normalisasi format angka sebelum diubah menjadi nilai numerik untuk database.
    $nominal_minimal_setor = (float)str_replace(['.', ','], ['', '.'], $_POST['nominal_minimal_setor'] ?? '0');
    $aturan_penarikan = trim($_POST['aturan_penarikan'] ?? '');
    $periode_aktif = trim($_POST['periode_aktif'] ?? '');
    $kontak_hp = trim($_POST['kontak_hp'] ?? '');
    $kontak_email = trim($_POST['kontak_email'] ?? '');
    $alamat_kantor = trim($_POST['alamat_kantor'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    // Siapkan query update dengan placeholder agar data form tidak dicampur ke SQL.
    $stmt = $pdo->prepare("
        UPDATE pengaturan SET
            nama_organisasi = ?,
            nama_aplikasi = ?,
            nominal_minimal_setor = ?,
            aturan_penarikan = ?,
            periode_aktif = ?,
            kontak_hp = ?,
            kontak_email = ?,
            alamat_kantor = ?,
            deskripsi = ?
        WHERE id = ?
    ");
    // Kirim setiap nilai konfigurasi sebagai parameter terpisah ke database.
    $stmt->execute([
        $nama_organisasi,
        $nama_aplikasi,
        $nominal_minimal_setor,
        $aturan_penarikan,
        $periode_aktif,
        $kontak_hp,
        $kontak_email,
        $alamat_kantor,
        $deskripsi,
        $pengaturan['id']
    ]);

    // Simpan pesan sukses untuk halaman setelah redirect.
    set_flash('success', 'Pengaturan sistem TabunganPKK berhasil diperbarui.');
    // Redirect mencegah form terkirim ulang ketika pengguna me-refresh halaman.
    header("Location: " . base_url('admin/sistem.php'));
    exit;
}

// Handle Action: Update Akun Admin Sendiri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_akun_admin') {
    // Ambil nama/email baru serta password lama dan password pengganti dari form.
    $adminNama = trim($_POST['admin_nama'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPassBaru = $_POST['admin_password_baru'] ?? '';
    $adminPassLama = $_POST['admin_password_lama'] ?? '';

    // Ambil hash password admin yang tersimpan untuk proses verifikasi.
    $stmtCek = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmtCek->execute([$_SESSION['user_id']]);
    $currHash = $stmtCek->fetchColumn();

    // Hanya ubah hash password jika kolom password baru memang diisi.
    if (!empty($adminPassBaru)) {
        // Tolak perubahan jika password lama yang dimasukkan tidak cocok.
        if (!password_verify($adminPassLama, $currHash)) {
            set_flash('danger', 'Password saat ini salah. Perubahan akun gagal.');
            header("Location: " . base_url('admin/sistem.php'));
            exit;
        }
        // Hash password baru sebelum menyimpannya ke tabel users.
        $newHash = password_hash($adminPassBaru, PASSWORD_BCRYPT);
        $stmtUp = $pdo->prepare("UPDATE users SET nama = ?, email = ?, password = ? WHERE id = ?");
        $stmtUp->execute([$adminNama, $adminEmail, $newHash, $_SESSION['user_id']]);
    // Jika password baru kosong, perbarui nama dan email saja.
    } else {
        $stmtUp = $pdo->prepare("UPDATE users SET nama = ?, email = ? WHERE id = ?");
        $stmtUp->execute([$adminNama, $adminEmail, $_SESSION['user_id']]);
    }

    // Sinkronkan nama dan email sesi supaya topbar langsung menampilkan data terbaru.
    $_SESSION['nama'] = $adminNama;
    $_SESSION['email'] = $adminEmail;
    set_flash('success', 'Profil dan akun Administrator berhasil diperbarui.');
    // Kembali ke halaman pengaturan setelah penyimpanan berhasil.
    header("Location: " . base_url('admin/sistem.php'));
    exit;
}

$currentUser = get_current_user_data($pdo);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div class="row g-4">
    <!-- Form Pengaturan Sistem Organisasi -->
    <div class="col-lg-8">
        <div class="card-pkk">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-sliders text-success"></i> Konfigurasi Aplikasi & Organisasi PKK</h5>
            </div>
            <div class="card-pkk-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_sistem">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nama Aplikasi</label>
                            <input type="text" name="nama_aplikasi" class="form-control" value="<?= htmlspecialchars($pengaturan['nama_aplikasi']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nama Organisasi / Kelompok PKK</label>
                            <input type="text" name="nama_organisasi" class="form-control" value="<?= htmlspecialchars($pengaturan['nama_organisasi']) ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nominal Minimal Setoran (Rp)</label>
                            <input type="number" name="nominal_minimal_setor" class="form-control" value="<?= (int)$pengaturan['nominal_minimal_setor'] ?>" required>
                            <small class="text-muted">Batas minimal anggota saat menyetor tabungan</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Periode Tabungan Aktif</label>
                            <input type="text" name="periode_aktif" class="form-control" value="<?= htmlspecialchars($pengaturan['periode_aktif']) ?>" placeholder="Contoh: 2026 / 2027">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nomor Kontak WhatsApp / HP</label>
                            <input type="text" name="kontak_hp" class="form-control" value="<?= htmlspecialchars($pengaturan['kontak_hp']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Pengurus</label>
                            <input type="email" name="kontak_email" class="form-control" value="<?= htmlspecialchars($pengaturan['kontak_email']) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alamat Kantor / Sekretariat PKK</label>
                        <textarea name="alamat_kantor" class="form-control" rows="2"><?= htmlspecialchars($pengaturan['alamat_kantor']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Aturan & Ketentuan Penarikan Tabungan</label>
                        <textarea name="aturan_penarikan" class="form-control" rows="3"><?= htmlspecialchars($pengaturan['aturan_penarikan']) ?></textarea>
                        <small class="text-muted">Informasi ini akan muncul pada menu pengajuan penarikan anggota</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Deskripsi Singkat Aplikasi</label>
                        <textarea name="deskripsi" class="form-control" rows="2"><?= htmlspecialchars($pengaturan['deskripsi']) ?></textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-pkk-primary px-4">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Pengaturan Sistem
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Form Pengaturan Akun Admin -->
    <div class="col-lg-4">
        <div class="card-pkk mb-4">
            <div class="card-pkk-header">
                <h5><i class="fa-solid fa-user-lock text-primary"></i> Akun Administrator</h5>
            </div>
            <div class="card-pkk-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_akun_admin">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap</label>
                        <input type="text" name="admin_nama" class="form-control" value="<?= htmlspecialchars($currentUser['nama']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email Admin</label>
                        <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password Saat Ini</label>
                        <input type="password" name="admin_password_lama" class="form-control" placeholder="Wajib jika ingin ganti password">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password Baru</label>
                        <input type="password" name="admin_password_baru" class="form-control" placeholder="Kosongkan jika tidak diubah">
                    </div>

                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="fa-solid fa-key me-1"></i> Perbarui Akun Saya
                    </button>
                </form>
            </div>
        </div>

        <div class="card-pkk bg-light border">
            <div class="card-pkk-body small">
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-circle-question text-info me-1"></i> Informasi Database</h6>
                <div class="text-muted mb-1">Host: <strong>localhost</strong></div>
                <div class="text-muted mb-1">Database: <strong>tabungan_pkk</strong></div>
                <div class="text-muted mb-2">Driver: <strong>PHP Data Objects (PDO)</strong></div>
                <span class="badge bg-success-subtle text-success">Koneksi Aktif & Stabil</span>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
