<?php
/**
 * TabunganPKK - Bukti Transaksi / Kwitansi Resmi Siap Cetak
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

require_role(['admin', 'bendahara', 'pengguna']);

$pdo = getDBConnection();
$pengaturan = get_pengaturan($pdo);

$kode = $_GET['kode'] ?? '';
if (empty($kode)) {
    die("Kode transaksi tidak ditentukan.");
}

$stmt = $pdo->prepare("
    SELECT t.*, a.nama AS nama_anggota, a.nomor_anggota, a.alamat AS alamat_anggota, a.nomor_hp AS hp_anggota,
           u.nama AS nama_petugas
    FROM transaksi t
    JOIN anggota a ON a.id = t.anggota_id
    LEFT JOIN users u ON u.id = t.petugas_id
    WHERE t.kode_transaksi = ?
    LIMIT 1
");
$stmt->execute([$kode]);
$trx = $stmt->fetch();

if (!$trx) {
    die("Transaksi tidak ditemukan.");
}

// Jika role adalah pengguna, pastikan hanya bisa mencetak transaksi miliknya sendiri
if ($_SESSION['role'] === 'pengguna') {
    $myAnggota = get_anggota_by_user_id($pdo, $_SESSION['user_id']);
    if (!$myAnggota || $myAnggota['id'] != $trx['anggota_id']) {
        die("Akses ditolak. Anda tidak berhak melihat bukti transaksi anggota lain.");
    }
}

// Fungsi konversi angka ke terbilang rupiah
function penyebut($nilai) {
    $nilai = abs($nilai);
    $huruf = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
    $temp = "";
    if ($nilai < 12) {
        $temp = " ". $huruf[$nilai];
    } else if ($nilai < 20) {
        $temp = penyebut($nilai - 10). " Belas";
    } else if ($nilai < 100) {
        $temp = penyebut((int)($nilai/10))." Puluh". penyebut($nilai % 10);
    } else if ($nilai < 200) {
        $temp = " Seratus" . penyebut($nilai - 100);
    } else if ($nilai < 1000) {
        $temp = penyebut((int)($nilai/100)). " Ratus" . penyebut($nilai % 100);
    } else if ($nilai < 2000) {
        $temp = " Seribu" . penyebut($nilai - 1000);
    } else if ($nilai < 1000000) {
        $temp = penyebut((int)($nilai/1000)) . " Ribu" . penyebut($nilai % 1000);
    } else if ($nilai < 1000000000) {
        $temp = penyebut((int)($nilai/1000000)) . " Juta" . penyebut($nilai % 1000000);
    } else if ($nilai < 1000000000000) {
        $temp = penyebut((int)($nilai/1000000000)) . " Milyar" . penyebut(fmod($nilai,1000000000));
    }
    return $temp;
}

function terbilang($nilai) {
    if ($nilai < 0) {
        $hasil = "Minus ". trim(penyebut($nilai));
    } else {
        $hasil = trim(penyebut($nilai));
    }
    return $hasil . " Rupiah";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kwitansi - <?= htmlspecialchars($trx['kode_transaksi']) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f1f5f9;
            padding: 2rem 1rem;
            color: #1e293b;
        }
        .kwitansi-card {
            max-width: 720px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 2px dashed #94a3b8;
            padding: 2.25rem;
            position: relative;
        }
        .kop-header {
            border-bottom: 2px solid #15803d;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand-kop {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .kop-logo {
            width: 52px;
            height: 52px;
            background: #15803d;
            color: #ffffff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
        }
        .kop-info h4 {
            margin: 0;
            font-weight: 800;
            color: #166534;
            letter-spacing: -0.01em;
        }
        .kop-info p {
            margin: 0;
            font-size: 0.82rem;
            color: #64748b;
        }
        .badge-kuitansi {
            font-size: 0.85rem;
            font-weight: 700;
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            text-transform: uppercase;
        }
        .nominal-highlight {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin: 1.25rem 0;
        }
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .kwitansi-card {
                border: 2px solid #334155 !important;
                box-shadow: none !important;
                max-width: 100% !important;
                border-radius: 0 !important;
                padding: 1.5rem !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print text-center mb-3">
    <button onclick="window.print()" class="btn btn-success me-2 px-3 fw-bold">
        <i class="fa-solid fa-print me-1"></i> Cetak Bukti Transaksi
    </button>
    <a href="<?= base_url($_SESSION['role'] . '/dashboard.php') ?>" onclick="if (history.length > 1) { history.back(); return false; }" class="btn btn-outline-secondary px-3">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="kwitansi-card">
    <div class="kop-header">
        <div class="brand-kop">
            <div class="kop-logo">
                <i class="fa-solid fa-hand-holding-dollar"></i>
            </div>
            <div class="kop-info">
                <h4><?= htmlspecialchars($pengaturan['nama_aplikasi']) ?></h4>
                <p><?= htmlspecialchars($pengaturan['nama_organisasi']) ?></p>
                <p class="small text-muted"><?= htmlspecialchars($pengaturan['alamat_kantor']) ?> | Telp: <?= htmlspecialchars($pengaturan['kontak_hp']) ?></p>
            </div>
        </div>
        <div>
            <span class="badge-kuitansi <?= $trx['jenis_transaksi'] === 'setoran' ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                BUKTI <?= strtoupper($trx['jenis_transaksi']) ?>
            </span>
        </div>
    </div>

    <div class="row g-3 mb-2 small">
        <div class="col-6">
            <div class="text-muted">Nomor Transaksi:</div>
            <div class="fw-bold fs-6 text-dark font-monospace"><?= htmlspecialchars($trx['kode_transaksi']) ?></div>
        </div>
        <div class="col-6 text-end">
            <div class="text-muted">Tanggal Transaksi:</div>
            <div class="fw-bold fs-6 text-dark"><?= format_tanggal($trx['tanggal']) ?></div>
        </div>
    </div>

    <hr class="my-3">

    <table class="table table-borderless table-sm small mb-0">
        <tr>
            <td class="text-muted" style="width: 160px;">Telah Diterima Dari</td>
            <td class="fw-bold text-dark">: <?= htmlspecialchars($trx['nama_anggota']) ?> (<?= htmlspecialchars($trx['nomor_anggota']) ?>)</td>
        </tr>
        <tr>
            <td class="text-muted">Alamat</td>
            <td class="text-dark">: <?= htmlspecialchars($trx['alamat_anggota'] ?: '-') ?></td>
        </tr>
        <tr>
            <td class="text-muted">Metode Pembayaran</td>
            <td class="text-dark">: <?= htmlspecialchars($trx['metode_pembayaran'] ?: 'Tunai') ?></td>
        </tr>
        <tr>
            <td class="text-muted">Untuk Keperluan</td>
            <td class="text-dark">: <?= htmlspecialchars($trx['keterangan'] ?: ($trx['jenis_transaksi'] === 'setoran' ? 'Setoran Tabungan PKK' : 'Penarikan Tabungan PKK')) ?></td>
        </tr>
    </table>

    <div class="nominal-highlight">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span class="text-muted small text-uppercase fw-bold">Jumlah Uang:</span>
                <div class="fs-3 fw-extrabold text-success"><?= format_rupiah($trx['nominal']) ?></div>
            </div>
            <div class="text-end">
                <span class="badge bg-light text-dark border px-2 py-1">Status: <?= strtoupper($trx['status']) ?></span>
            </div>
        </div>
        <div class="mt-2 text-muted small fst-italic">
            <strong>Terbilang:</strong> <em># <?= terbilang($trx['nominal']) ?> #</em>
        </div>
    </div>

    <div class="row text-center mt-4 pt-2 small">
        <div class="col-6">
            <p class="text-muted mb-5">Penyetor / Anggota,</p>
            <div class="fw-bold text-dark border-bottom d-inline-block px-4 pb-1"><?= htmlspecialchars($trx['nama_anggota']) ?></div>
        </div>
        <div class="col-6">
            <p class="text-muted mb-5">Bendahara / Petugas,</p>
            <div class="fw-bold text-dark border-bottom d-inline-block px-4 pb-1"><?= htmlspecialchars($trx['nama_petugas'] ?: 'Pengurus Tabungan PKK') ?></div>
        </div>
    </div>

    <div class="mt-4 pt-3 border-top text-center text-muted" style="font-size: 0.72rem;">
        Kwitansi ini merupakan bukti transaksi tabungan yang sah pada sistem <?= htmlspecialchars($pengaturan['nama_aplikasi']) ?> - Dicetak pada <?= date('d/m/Y H:i') ?> WIB.
    </div>
</div>

</body>
</html>
