<?php
/**
 * TabunganPKK - Web Installer & Status Database
 *
 * Halaman ini menguji koneksi MySQL, menampilkan jumlah baris pada setiap
 * tabel, dan menyediakan aksi untuk menjalankan inisialisasi ulang yang aman.
 * CREATE TABLE IF NOT EXISTS tidak menghapus data yang sudah tersimpan.
 */

// Muat fungsi koneksi dan helper tampilan URL sebelum memeriksa database.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Inisialisasi status tampilan agar template dapat dirender walau koneksi gagal.
$message = '';
$messageType = 'info';
$dbStatus = false;
$tableStats = [];

// Coba hubungkan aplikasi ke MySQL dan tangani setiap kegagalan dengan pesan.
try {
    // Ambil koneksi; panggilan ini juga memastikan tabel dan data awal tersedia.
    $pdo = getDBConnection();
    $dbStatus = true;

    // Aksi ini dijalankan hanya dari tombol form installer.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'seed') {
        // Buat tabel yang belum ada dan isi data demo hanya jika database kosong.
        initDatabaseTables($pdo);
        $message = "Database dan tabel berhasil diinisialisasi beserta data demo!";
        $messageType = "success";
    }

    // Hitung isi tabel satu per satu agar status instalasi mudah diperiksa.
    $tables = ['users', 'anggota', 'transaksi', 'pengajuan_penarikan', 'notifikasi', 'pengaturan'];
    foreach ($tables as $t) {
        try {
            // Hitung baris sebagai indikator bahwa tabel tersedia.
            $c = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            $tableStats[$t] = $c;
        } catch (Exception $e) {
            // Simpan status yang mudah dibaca jika tabel belum dapat diperiksa.
            $tableStats[$t] = 'Belum dibuat';
        }
    }
} catch (Exception $e) {
    // Tampilkan kesalahan koneksi pada halaman, bukan menghentikan template.
    $message = "Error Koneksi Database: " . $e->getMessage();
    $messageType = "danger";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalasi & Status Database - TabunganPKK</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            padding: 2.5rem 1rem;
        }
        .setup-card {
            max-width: 680px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            padding: 2.25rem;
        }
        .table th {
            background-color: #f8fafc;
        }
    </style>
</head>
<body>

<div class="setup-card">
    <div class="text-center mb-4">
        <div style="width: 56px; height: 56px; background: #15803d; color: #ffffff; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 0.75rem;">
            <i class="fa-solid fa-hand-holding-dollar"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">Status Sistem & Database TabunganPKK</h4>
        <p class="text-muted small mb-0">Pemeriksaan koneksi MySQL XAMPP dan struktur data</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> small mb-4">
            <i class="fa-solid fa-circle-info me-2"></i><?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card bg-light border-0 mb-4 p-3 rounded-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small fw-semibold text-secondary">Koneksi MySQL XAMPP:</span>
            <?php if ($dbStatus): ?>
                <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> Terhubung (Database: tabungan_pkk)</span>
            <?php else: ?>
                <span class="badge bg-danger"><i class="fa-solid fa-circle-xmark me-1"></i> Gagal Terhubung</span>
            <?php endif; ?>
        </div>
        <div class="small text-muted">
            Host: <strong>localhost:3306</strong> | User: <strong>root</strong> | Password: <strong>(kosong)</strong>
        </div>
    </div>

    <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-table me-2 text-success"></i> Status Tabel Database</h6>
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-sm small">
            <thead>
                <tr>
                    <th>Nama Tabel</th>
                    <th>Fungsi</th>
                    <th class="text-center">Jumlah Baris</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $desc = [
                    'users' => 'Data Akun Login (Admin, Bendahara, Pengguna)',
                    'anggota' => 'Profil Anggota PKK & Nomor Anggota',
                    'transaksi' => 'Catatan Setoran & Penarikan Tabungan',
                    'pengajuan_penarikan' => 'Permohonan Tarik Mandiri Anggota',
                    'notifikasi' => 'Notifikasi Mutasi & Status Akun',
                    'pengaturan' => 'Konfigurasi Organisasi PKK & Sistem'
                ];
                foreach ($tableStats as $tb => $cnt):
                ?>
                    <tr>
                        <td class="font-monospace fw-bold text-dark"><?= $tb ?></td>
                        <td><?= $desc[$tb] ?? '-' ?></td>
                        <td class="text-center fw-bold"><?= is_numeric($cnt) ? $cnt : 0 ?></td>
                        <td class="text-center">
                            <?php if (is_numeric($cnt)): ?>
                                <span class="badge bg-success-subtle text-success">Siap Digunakan</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger">Belum Dibuat</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Akun Demo -->
    <div class="bg-light p-3 rounded-3 border mb-4">
        <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-users me-1 text-primary"></i> Akun Login Bawaan (Password: password123):</h6>
        <div class="row g-2 small">
            <div class="col-md-4">
                <div class="p-2 border rounded bg-white">
                    <strong class="text-danger d-block">Admin:</strong>
                    Username: <code>admin</code>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-2 border rounded bg-white">
                    <strong class="text-warning d-block">Bendahara:</strong>
                    Username: <code>bendahara</code>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-2 border rounded bg-white">
                    <strong class="text-success d-block">Anggota:</strong>
                    Username: <code>anggota</code>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <form method="POST" action="">
            <input type="hidden" name="action" value="seed">
            <button type="submit" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Inisialisasi tabel dan data awal? Data yang sudah ada tidak akan dihapus.')">
                <i class="fa-solid fa-rotate me-1"></i> Inisialisasi Ulang Tabel
            </button>
        </form>

        <a href="<?= base_url('login.php') ?>" class="btn btn-success fw-bold px-4">
            Buka Halaman Login <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>
</div>

</body>
</html>
