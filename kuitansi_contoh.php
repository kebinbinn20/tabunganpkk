<?php
$receipts = [
    [
        'code' => 'CONTOH-SETOR-001',
        'type' => 'Setoran',
        'date' => '01 Oktober 2026',
        'name' => 'Ani Suryani',
        'member' => 'PKK-001',
        'address' => 'RW 05 Harapan Jaya',
        'amount' => 150000,
        'method' => 'Tunai',
        'purpose' => 'Setoran tabungan bulanan',
    ],
    [
        'code' => 'CONTOH-SETOR-002',
        'type' => 'Setoran',
        'date' => '03 Oktober 2026',
        'name' => 'Dewi Lestari',
        'member' => 'PKK-002',
        'address' => 'RW 05 Harapan Jaya',
        'amount' => 275000,
        'method' => 'Transfer',
        'purpose' => 'Setoran tabungan dan iuran',
    ],
    [
        'code' => 'CONTOH-TARIK-001',
        'type' => 'Penarikan',
        'date' => '05 Oktober 2026',
        'name' => 'Rina Kartika',
        'member' => 'PKK-003',
        'address' => 'RW 05 Harapan Jaya',
        'amount' => 100000,
        'method' => 'Tunai',
        'purpose' => 'Penarikan tabungan',
    ],
];

function contohPenyebut($number) {
    $words = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
    if ($number < 12) return $words[$number];
    if ($number < 20) return contohPenyebut($number - 10) . ' Belas';
    if ($number < 100) return contohPenyebut((int) ($number / 10)) . ' Puluh ' . contohPenyebut($number % 10);
    if ($number < 200) return 'Seratus ' . contohPenyebut($number - 100);
    if ($number < 1000) return contohPenyebut((int) ($number / 100)) . ' Ratus ' . contohPenyebut($number % 100);
    if ($number < 2000) return 'Seribu ' . contohPenyebut($number - 1000);
    if ($number < 1000000) return contohPenyebut((int) ($number / 1000)) . ' Ribu ' . contohPenyebut($number % 1000);
    if ($number < 1000000000) return contohPenyebut((int) ($number / 1000000)) . ' Juta ' . contohPenyebut($number % 1000000);
    return contohPenyebut((int) ($number / 1000000000)) . ' Miliar ' . contohPenyebut($number % 1000000000);
}

function contohRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuitansi Contoh Presentasi - TabunganPKK</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px 16px; background: #eef2ef; color: #17251d; font-family: Arial, sans-serif; }
        .toolbar { max-width: 780px; margin: 0 auto 18px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .toolbar p { margin: 0; color: #43534a; font-size: 14px; }
        button { border: 0; border-radius: 5px; padding: 12px 18px; background: #176b43; color: white; font-weight: 700; cursor: pointer; }
        .receipt { position: relative; width: min(100%, 780px); min-height: 510px; margin: 0 auto 22px; padding: 34px 38px; background: white; border: 1px solid #b8c5bc; box-shadow: 0 5px 22px #14281b12; page-break-after: always; break-after: page; overflow: hidden; }
        .receipt:last-child { page-break-after: auto; break-after: auto; }
        .sample-stamp { margin: 0 0 18px; padding: 8px 10px; border: 2px solid #b42318; color: #b42318; text-align: center; font-size: 13px; font-weight: 800; letter-spacing: 1px; }
        .heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 2px solid #176b43; padding-bottom: 16px; }
        .heading h1 { margin: 0 0 5px; color: #145b39; font-size: 22px; }
        .heading p { margin: 3px 0; color: #536157; font-size: 12px; }
        .type { flex: 0 0 auto; padding: 8px 10px; border-radius: 4px; background: #e9f5ed; color: #145b39; font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .meta { display: flex; justify-content: space-between; gap: 16px; padding: 20px 0 14px; font-size: 13px; }
        .meta div:last-child { text-align: right; }
        .label { display: block; margin-bottom: 5px; color: #65736a; }
        .value { font-weight: 700; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        td { padding: 7px 0; vertical-align: top; }
        td:first-child { width: 165px; color: #65736a; }
        .amount { margin: 18px 0; padding: 15px 17px; border: 1px solid #b9d8c3; background: #f1f8f3; }
        .amount-label { color: #536157; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .amount-value { margin: 5px 0 8px; color: #145b39; font-size: 27px; font-weight: 800; }
        .spelled { color: #536157; font-size: 12px; font-style: italic; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 34px; text-align: center; font-size: 12px; }
        .signatures p { margin: 0 0 58px; color: #65736a; }
        .signatures strong { display: inline-block; min-width: 145px; padding-bottom: 5px; border-bottom: 1px solid #536157; }
        .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #d6dfd9; color: #b42318; text-align: center; font-size: 10px; font-weight: 700; }
        @media (max-width: 560px) {
            body { padding: 14px 8px; }
            .toolbar { align-items: flex-start; flex-direction: column; }
            .receipt { min-height: auto; padding: 22px 18px; }
            .heading h1 { font-size: 18px; }
            .type { font-size: 10px; }
            td:first-child { width: 125px; }
        }
        @media print {
            @page { size: A4; margin: 14mm; }
            body { padding: 0; background: white; }
            .toolbar { display: none; }
            .receipt { width: 100%; min-height: 0; margin: 0; padding: 12mm 10mm; border: 1px solid #8b9890; box-shadow: none; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <p>Tiga kuitansi dummy untuk presentasi. Tidak ada transaksi atau data database yang dibuat.</p>
    <button type="button" onclick="window.print()">Cetak 3 Kuitansi</button>
</div>

<?php foreach ($receipts as $receipt): ?>
<main class="receipt">
    <div class="sample-stamp">CONTOH PRESENTASI - BUKAN BUKTI TRANSAKSI ASLI</div>
    <header class="heading">
        <div>
            <h1>TabunganPKK</h1>
            <p>PKK RW 05 Harapan Jaya</p>
            <p>Balai Warga RW 05, Jl. Harmoni No. 01</p>
        </div>
        <span class="type"><?= htmlspecialchars($receipt['type'], ENT_QUOTES, 'UTF-8') ?></span>
    </header>

    <section class="meta">
        <div><span class="label">Nomor Transaksi</span><span class="value"><?= htmlspecialchars($receipt['code'], ENT_QUOTES, 'UTF-8') ?></span></div>
        <div><span class="label">Tanggal</span><span class="value"><?= htmlspecialchars($receipt['date'], ENT_QUOTES, 'UTF-8') ?></span></div>
    </section>

    <table>
        <tr><td>Telah Diterima Dari</td><td><strong><?= htmlspecialchars($receipt['name'], ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars($receipt['member'], ENT_QUOTES, 'UTF-8') ?>)</td></tr>
        <tr><td>Alamat</td><td><?= htmlspecialchars($receipt['address'], ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><td>Metode Pembayaran</td><td><?= htmlspecialchars($receipt['method'], ENT_QUOTES, 'UTF-8') ?></td></tr>
        <tr><td>Untuk Keperluan</td><td><?= htmlspecialchars($receipt['purpose'], ENT_QUOTES, 'UTF-8') ?></td></tr>
    </table>

    <section class="amount">
        <div class="amount-label">Jumlah Uang</div>
        <div class="amount-value"><?= contohRupiah($receipt['amount']) ?></div>
        <div class="spelled"><strong>Terbilang:</strong> # <?= trim(contohPenyebut($receipt['amount'])) ?> Rupiah #</div>
    </section>

    <section class="signatures">
        <div><p>Penyetor / Anggota,</p><strong><?= htmlspecialchars($receipt['name'], ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div><p>Bendahara / Petugas,</p><strong>Pengurus Tabungan PKK</strong></div>
    </section>
    <footer class="footer">DATA FIKTIF UNTUK PRESENTASI - TIDAK BERLAKU SEBAGAI BUKTI KEUANGAN</footer>
</main>
<?php endforeach; ?>
</body>
</html>