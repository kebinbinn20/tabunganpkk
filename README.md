# TabunganPKK - Aplikasi Digital Pengelolaan Tabungan PKK

Aplikasi web modern berbasis **PHP Native** dan **MySQL** yang dirancang khusus untuk mengelola dan memonitor tabungan anggota PKK secara terstruktur, aman, transparan, dan mudah digunakan.

---

## 🌟 Fitur Utama Berdasarkan Role

### 1. 🛡️ Role Admin
* **Dashboard Admin**: Ringkasan total anggota, bendahara, total kas tabungan, total transaksi, grafik bar 6 bulan terakhir, aktivitas setoran & penarikan terbaru.
* **Kelola Pengguna**: CRUD akun (Admin, Bendahara, Pengguna/Anggota), aktivasi/nonaktifkan akun, ganti password, filter role & pencarian.
* **Kelola Anggota**: Manajemen data identitas anggota (Nama, Nomor Anggota, NIK, No. HP, Alamat, Tanggal Gabung, Status), pemantauan saldo simpanan real-time per anggota, detail & mutasi lengkap.
* **Kelola Sistem**: Konfigurasi identitas organisasi PKK, nama aplikasi, nominal minimal setoran, periode aktif, aturan penarikan, kontak pengurus, dan keamanan akun administrator.
* **Laporan Transaksi**: Laporan komprehensif transaksi dan rekap saldo seluruh anggota dengan filter (Hari, Bulan, Tahun, Rentang Tanggal, Nama Anggota), fitur **Export Excel (.xls)** dan **Cetak / Export PDF**.

### 2. 💼 Role Bendahara
* **Dashboard Bendahara**: Pantauan kas tabungan, total setoran hari ini, total penarikan hari ini, jumlah anggota, grafik garis arus kas, transaksi terkini.
* **Data Anggota**: Melihat seluruh anggota PKK beserta saldo berjalan dan riwayat mutasi.
* **Pencatatan Setoran Tabungan**: Form setoran dengan pemilihan anggota, nomor transaksi otomatis (`TRX-YYYYMMDD-XXXX`), metode pembayaran (Tunai, Transfer, QRIS), upload bukti transfer, penambahan saldo otomatis, dan pencetakan kwitansi resmi.
* **Pencatatan Penarikan Tabungan**: Form penarikan dengan pengecekan saldo langsung (*live balance check*), validasi saldo mencukupi, pengurangan saldo otomatis, dan pencetakan kwitansi penarikan.
* **Pengajuan Penarikan Anggota**: Verifikasi permohonan penarikan dana dari anggota, opsi persetujuan (cairkan & potong saldo otomatis) atau penolakan dengan catatan resmi.
* **Riwayat Transaksi**: Tabel mutasi lengkap dengan filter pencarian dan tombol cetak bukti kuitansi.
* **Laporan Keuangan**: Laporan mutasi kas dan rekapitulasi simpanan per anggota, filter tanggal & anggota, serta Export Excel dan Cetak Laporan.

### 3. 👤 Role Pengguna / Anggota
* **Dashboard Anggota**: Tampilan kartu saldo besar (**Rp XXX.XXX**), total setoran, total penarikan, jumlah transaksi, setoran terakhir, penarikan terakhir, serta grafik pertumbuhan saldo simpanan.
* **Saldo Tabungan**: Rincian akumulasi tabungan, kartu nomor anggota digital, dan panduan aturan penarikan dana.
* **Riwayat Transaksi Pribadi**: Buku tabungan digital yang menampilkan riwayat mutasi pribadi, kolom *Saldo Setelah Transaksi (Running Balance)*, status transaksi, filter setoran/penarikan, dan cetak kuitansi.
* **Ajukan Penarikan Dana**: Formulir pengajuan penarikan tabungan mandiri dengan validasi saldo maksimum dan pemantauan status persetujuan bendahara (*Menunggu*, *Disetujui*, *Ditolak*).
* **Notifikasi Sistem**: Notifikasi instan saat setoran berhasil dicatat, penarikan disetujui/ditolak oleh bendahara.
* **Profil Saya**: Rincian profil keanggotaan, formulir pembaruan nomor WhatsApp/alamat, dan pembaruan kata sandi.

### 4. 🖨️ Bukti Transaksi Resmi (Kwitansi)
* Format kwitansi standar PKK lengkap dengan kop organisasi, nomor transaksi unik, tanggal, rincian nominal angka, **ejaan terbilang bahasa Indonesia secara otomatis**, tanda tangan penyetor & bendahara, serta mode cetak ramah printer / PDF.

---

## 🔑 Akun Uji Coba Bawaan (Default Credentials)

Semua akun default menggunakan password: `password123`

| Role | Username | Password | Keterangan |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `password123` | Administrator Sistem PKK RW 05 |
| **Bendahara** | `bendahara` | `password123` | Ibu Siti Nurhaliza (Bendahara Kas) |
| **Pengguna** | `anggota` | `password123` | Ibu Ani Suryani (Anggota Aktif) |
| **Pengguna** | `ibu_dewi` | `password123` | Ibu Dewi Lestari (Anggota Aktif) |
| **Pengguna** | `ibu_rina` | `password123` | Ibu Rina Kartika (Anggota Aktif) |

> 💡 *Catatan:* Pada halaman login (`login.php`), telah disediakan tombol **Demo Login 1-Klik** untuk memudahkan pengujian.

---

## 🚀 Cara Menjalankan Aplikasi di XAMPP

1. Pastikan folder aplikasi berada di direktori XAMPP:
   ```
   C:\xampp\htdocs\tabunganPKK
   ```
2. Buka **XAMPP Control Panel**, lalu klik **Start** pada modul **Apache** dan **MySQL**.
3. Buka browser (Google Chrome, Microsoft Edge, Mozilla Firefox) dan akses:
   ```
   http://localhost/tabunganPKK/
   ```
4. **Auto-Setup Otomatis:**
   * Aplikasi memiliki sistem deteksi otomatis. Saat pertama kali dibuka di browser, sistem akan membuat database `tabungan_pkk`, seluruh tabel, dan data demo awal secara otomatis tanpa perlu import manual!
   * Atau, Anda juga dapat membuka `http://localhost/tabunganPKK/install.php` untuk melihat status tabel database.
   * File skema SQL mandiri juga tersedia di `database.sql` jika ingin diimpor melalui phpMyAdmin.

---

## 📁 Struktur Direktori Proyek

```
tabunganPKK/
├── config/
│   ├── database.php          # Koneksi PDO & Auto-setup tabel/seeder
│   └── helpers.php           # Helper format rupiah, saldo, auth role, notifikasi
├── includes/
│   ├── header.php            # HTML head, Bootstrap 5, FontAwesome 6, Chart.js
│   ├── sidebar.php           # Navigasi sidebar sesuai role (Admin, Bendahara, Pengguna)
│   ├── topbar.php            # Topbar, bell notifikasi, profile menu, alert
│   └── footer.php            # Mobile bottom navigation bar & scripts
├── assets/
│   ├── css/
│   │   └── style.css         # Tema hijau PKK, kartu statistik, responsif, print
│   └── js/
│       └── app.js            # Sidebar mobile drawer, format rupiah, konfirmasi hapus
├── admin/
│   ├── dashboard.php         # Dashboard statistik & grafik admin
│   ├── pengguna.php          # Kelola pengguna, role, aktivasi akun
│   ├── anggota.php           # Kelola data anggota PKK & saldo berjalan
│   ├── anggota_detail.php    # Detail profil & mutasi riwayat anggota
│   ├── sistem.php            # Pengaturan identitas organisasi & sistem
│   └── laporan.php           # Laporan transaksi & rekap saldo (Excel & PDF)
├── bendahara/
│   ├── dashboard.php         # Dashboard kas & arus transaksi harian bendahara
│   ├── anggota.php           # Pantau saldo anggota & mutasi
│   ├── setoran.php           # Pencatatan setoran tabungan & upload bukti
│   ├── penarikan.php         # Pencatatan penarikan dengan validasi saldo
│   ├── pengajuan.php         # Verifikasi & persetujuan penarikan anggota
│   ├── riwayat.php           # Riwayat transaksi lengkap
│   └── laporan.php           # Laporan keuangan bendahara (Excel & Cetak)
├── pengguna/
│   ├── dashboard.php         # Dashboard anggota dengan kartu saldo besar
│   ├── saldo.php             # Rincian tabungan & kartu simpanan
│   ├── riwayat.php           # Buku tabungan digital & running balance
│   ├── ajukan_penarikan.php  # Form pengajuan penarikan dana mandiri
│   ├── notifikasi.php        # Daftar pemberitahuan anggota
│   └── profil.php            # Edit nomor WA, alamat, dan ganti password
├── cetak_kuitansi.php        # Kwitansi resmi bukti setoran/penarikan siap cetak
├── database.sql              # Skema SQL & data awal (MySQL)
├── install.php               # Web installer & pemeriksa database
├── login.php                 # Halaman login modern dengan demo credentials
├── logout.php                # Script logout aman
├── lupa-password.php         # Bantuan pemulihan akun & kontak pengurus
└── index.php                 # Router utama berdasarkan status login & role
```

---

## 🔒 Keamanan & Integritas Data
* **Prepared Statements (PDO)** pada setiap query SQL untuk mencegah SQL Injection.
* **Password Hashing** menggunakan algoritma `BCRYPT`.
* **Role-Based Access Control (RBAC)**: Pengguna hanya dapat mengakses halaman dan data yang menjadi haknya.
* **Perhitungan Saldo Konsisten**: Saldo dihitung dinamis dari akumulasi transaksi setoran dan penarikan yang berstatus `berhasil` sehingga tidak dapat dimanipulasi manual.
* **Validasi Penarikan**: Mencegah penarikan melebihi saldo tabungan aktual anggota.
