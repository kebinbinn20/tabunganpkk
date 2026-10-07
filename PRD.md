# Product Requirements Document (PRD)

## Aplikasi Tabungan Digital PKK

| Informasi Dokumen | Keterangan |
|---|---|
| Versi | 1.0 |
| Tanggal | 3 Oktober 2026 |
| Status | Draft untuk tugas/laporan |
| Disusun oleh | [Nama siswa] |
| Kelas / Program | [Kelas atau program studi] |
| Produk | TabunganPKK |

> Dokumen ini disusun berdasarkan fitur dan struktur aplikasi yang tersedia di repository. Bagian kebutuhan yang belum sepenuhnya didukung implementasi saat ini ditandai sebagai rekomendasi atau pengembangan berikutnya.

## 1. Ringkasan Produk

TabunganPKK adalah aplikasi web untuk membantu pengurus PKK mencatat dan memantau tabungan anggota. Aplikasi menyediakan pengelolaan akun serta data anggota, pencatatan setoran dan penarikan, pengajuan penarikan anggota, notifikasi dalam aplikasi, laporan keuangan, dan kuitansi transaksi.

Aplikasi ditujukan untuk menggantikan pencatatan yang tersebar atau dilakukan sepenuhnya secara manual dengan catatan transaksi yang dapat ditelusuri. Saldo anggota dihitung dari penjumlahan transaksi berhasil, sehingga setiap perubahan saldo memiliki riwayat transaksi.

## 2. Latar Belakang dan Masalah

Pencatatan tabungan secara manual berisiko menimbulkan kesalahan perhitungan, kesulitan mencari riwayat transaksi, dan keterlambatan penyampaian informasi saldo kepada anggota. Bendahara juga membutuhkan cara yang lebih teratur untuk memproses setoran, penarikan, dan laporan periodik.

TabunganPKK menyediakan satu aplikasi bersama yang memisahkan akses berdasarkan peran. Pengurus dapat mengelola data dan transaksi, sedangkan anggota dapat memantau saldo serta mengajukan penarikan.

## 3. Tujuan Produk

1. Mencatat setoran dan penarikan anggota secara terpusat.
2. Menampilkan saldo berdasarkan transaksi berhasil yang tercatat.
3. Membantu bendahara memeriksa dan memproses pengajuan penarikan.
4. Memberikan akses informasi transaksi yang sesuai dengan peran pengguna.
5. Menyediakan laporan dan bukti transaksi yang dapat dicetak atau diekspor.
6. Mengurangi risiko akses data keuangan oleh pihak yang tidak berwenang.

## 4. Pengguna dan Pemangku Kepentingan

| Pengguna | Kebutuhan utama | Hak akses utama |
|---|---|---|
| Administrator | Mengatur aplikasi, akun, data anggota, dan laporan | Dashboard admin, pengguna, anggota, pengaturan sistem, laporan |
| Bendahara | Mencatat arus kas dan memproses permohonan anggota | Dashboard bendahara, setoran, penarikan, pengajuan, anggota, riwayat, laporan |
| Anggota / Pengguna | Memantau tabungan dan mengajukan penarikan | Dashboard pribadi, saldo, riwayat, pengajuan, notifikasi, profil |
| Pengurus PKK | Memastikan kegiatan tabungan tercatat dan dapat dilaporkan | Menggunakan informasi agregat dan laporan sesuai kewenangan |

## 5. Ruang Lingkup

### 5.1 Termasuk dalam ruang lingkup

- Login menggunakan username atau email dan kata sandi.
- Hak akses berbasis peran dan status akun.
- Pengelolaan akun, profil anggota, dan pengaturan organisasi.
- Pencatatan transaksi setoran serta penarikan.
- Pengajuan penarikan secara mandiri dan pemrosesan oleh bendahara.
- Perhitungan saldo dari transaksi berstatus berhasil.
- Riwayat transaksi, notifikasi dalam aplikasi, laporan, ekspor, dan kuitansi.
- Penggunaan aplikasi melalui browser pada lingkungan PHP dan MySQL.

### 5.2 Di luar ruang lingkup versi ini

- Integrasi langsung dengan bank, payment gateway, QRIS, atau layanan pencairan dana.
- Pengiriman notifikasi melalui SMS, WhatsApp API, atau email otomatis.
- Pemulihan kata sandi mandiri melalui tautan email.
- Aplikasi native Android/iOS.
- Pembukuan akuntansi penuh, rekonsiliasi bank otomatis, atau pengelolaan bunga.

## 6. Alur Pengguna Utama

### 6.1 Setoran oleh bendahara

1. Bendahara memilih anggota, tanggal, metode pembayaran, nominal, dan keterangan.
2. Sistem memeriksa bahwa anggota tersedia dan nominal memenuhi batas minimal setoran.
3. Bendahara dapat melampirkan bukti pembayaran.
4. Sistem membuat kode transaksi, menyimpan transaksi berhasil, dan menghitung saldo baru dari riwayat transaksi.
5. Jika anggota memiliki akun, sistem membuat notifikasi dalam aplikasi.
6. Bendahara dapat membuka dan mencetak kuitansi.

### 6.2 Penarikan langsung oleh bendahara

1. Bendahara memilih anggota dan memasukkan nominal penarikan.
2. Sistem menampilkan saldo dan memeriksa ulang saldo di server.
3. Jika saldo mencukupi, sistem mencatat transaksi penarikan berhasil dan mengirim notifikasi bila akun anggota tersedia.
4. Jika saldo tidak mencukupi, transaksi ditolak dan saldo tidak berubah.

### 6.3 Pengajuan penarikan oleh anggota

1. Anggota memasukkan nominal dan alasan penarikan.
2. Sistem menolak nominal yang tidak positif atau melebihi saldo saat pengajuan diproses.
3. Sistem menyimpan permohonan dengan status menunggu.
4. Bendahara menyetujui atau menolak permohonan dengan catatan.
5. Jika disetujui, sistem mencatat transaksi penarikan dan mengurangi saldo secara otomatis.
6. Anggota melihat status permohonan dan notifikasi hasilnya.

## 7. Kebutuhan Fungsional

| ID | Kebutuhan | Kriteria penerimaan ringkas |
|---|---|---|
| FR-01 | Autentikasi pengguna | Pengguna dapat masuk dengan username/email dan kata sandi yang valid; akun nonaktif tidak dapat masuk. |
| FR-02 | Pengalihan berdasarkan peran | Setelah login, admin, bendahara, dan anggota diarahkan ke dashboard sesuai perannya. |
| FR-03 | Pengelolaan akun | Admin dapat menambah, melihat, mengubah, mengaktifkan/nonaktifkan, dan menghapus akun sesuai aturan aplikasi. |
| FR-04 | Pengelolaan anggota | Admin dapat mengelola identitas, nomor anggota, status, serta melihat saldo dan mutasi anggota. |
| FR-05 | Pengaturan organisasi | Admin dapat mengubah nama organisasi/aplikasi, periode, minimal setoran, aturan penarikan, dan kontak. |
| FR-06 | Pencatatan setoran | Bendahara dapat mencatat setoran dengan anggota, nominal, tanggal, metode, dan keterangan; nominal di bawah batas minimum ditolak. |
| FR-07 | Lampiran setoran | Form setoran menyediakan unggahan bukti transfer dengan format gambar/PDF yang didukung. |
| FR-08 | Pencatatan penarikan langsung | Bendahara dapat mencatat penarikan hanya jika saldo anggota mencukupi. |
| FR-09 | Pengajuan penarikan | Anggota dapat mengirim permohonan dengan nominal dan alasan serta melihat status permohonannya. |
| FR-10 | Pemrosesan pengajuan | Bendahara dapat menyetujui atau menolak permohonan menunggu dan menyimpan catatan proses. Persetujuan membuat transaksi penarikan. |
| FR-11 | Perhitungan saldo | Sistem menghitung saldo sebagai total setoran berhasil dikurangi total penarikan berhasil. |
| FR-12 | Riwayat transaksi | Bendahara dapat memfilter riwayat; anggota dapat melihat riwayat miliknya beserta saldo setelah transaksi. |
| FR-13 | Notifikasi | Sistem membuat notifikasi dalam aplikasi untuk peristiwa transaksi dan keputusan pengajuan yang relevan. |
| FR-14 | Laporan | Admin dan bendahara dapat melihat laporan transaksi atau rekap saldo sesuai filter yang tersedia. |
| FR-15 | Ekspor dan cetak laporan | Laporan dapat diekspor sebagai file spreadsheet `.xls` dan dicetak melalui fitur cetak browser. |
| FR-16 | Kuitansi transaksi | Pengguna berwenang dapat membuka kuitansi berisi identitas transaksi, nominal angka dan terbilang, serta informasi anggota/petugas. |
| FR-17 | Profil anggota | Pengguna dapat memperbarui data kontak dan mengganti kata sandi dengan verifikasi kata sandi lama. |
| FR-18 | Bantuan pemulihan akun | Pengguna dapat mencari akun menggunakan username, email, atau nomor anggota dan memperoleh informasi kontak pengurus untuk verifikasi. |

## 8. Aturan Bisnis

1. Saldo tidak menjadi sumber data tersendiri; saldo berasal dari transaksi berhasil.
2. Setoran menambah saldo, sedangkan penarikan mengurangi saldo.
3. Transaksi dengan status selain berhasil tidak memengaruhi saldo.
4. Nominal transaksi setoran dan penarikan harus lebih besar dari nol.
5. Nominal setoran tidak boleh kurang dari nilai minimal yang ditetapkan admin.
6. Penarikan tidak boleh melebihi saldo anggota yang dihitung dari database.
7. Pengajuan baru dimulai dengan status menunggu dan hanya dapat diproses satu kali.
8. Kode transaksi setoran menggunakan awalan `TRX`; penarikan menggunakan awalan `WD`, tanggal, dan nomor urut.
9. Penghapusan atau penonaktifan akun admin yang sedang digunakan sendiri harus dicegah.
10. Data dan tindakan yang tersedia harus mengikuti peran pengguna yang sedang login.

## 9. Kebutuhan Data

| Entitas | Data utama | Fungsi |
|---|---|---|
| `users` | Nama, username, email, hash kata sandi, role, status, kontak | Identitas dan autentikasi akun |
| `anggota` | Nomor anggota, nama, NIK, kontak, alamat, tanggal bergabung, status, user ID | Profil keanggotaan PKK |
| `transaksi` | Kode, anggota, jenis, nominal, metode, bukti, tanggal, status, petugas | Buku mutasi setoran dan penarikan |
| `pengajuan_penarikan` | Anggota, nominal, alasan, status, pemroses, waktu, catatan | Alur permohonan penarikan anggota |
| `notifikasi` | Penerima, judul, pesan, status baca, waktu | Pemberitahuan di dalam aplikasi |
| `pengaturan` | Identitas organisasi, minimal setoran, aturan, periode, kontak | Konfigurasi aplikasi dan organisasi |

## 10. Kebutuhan Nonfungsional

### Keamanan

- Query database menggunakan PDO prepared statements.
- Kata sandi disimpan sebagai hash bcrypt.
- Halaman dan tindakan dibatasi melalui pemeriksaan role.
- Anggota hanya boleh melihat transaksi miliknya sendiri.
- Data yang ditampilkan dari input pengguna harus di-escape untuk mengurangi risiko XSS.

### Integritas dan keandalan

- Saldo harus konsisten dengan transaksi berhasil.
- Setiap kode transaksi harus unik.
- Kesalahan koneksi database perlu ditampilkan sebagai informasi yang dapat ditindaklanjuti, tanpa membocorkan rahasia konfigurasi pada sistem produksi.
- Proses persetujuan dan pencatatan transaksi harus mencegah pemrosesan ganda.

### Kemudahan penggunaan

- Antarmuka menggunakan Bahasa Indonesia dan format Rupiah/tanggal yang mudah dibaca.
- Halaman utama perlu menyesuaikan ukuran desktop dan perangkat seluler.
- Form harus memberi umpan balik yang jelas untuk kesalahan dan keberhasilan.
- Kuitansi dan laporan harus tetap terbaca saat dicetak.

### Teknologi dan lingkungan

- Aplikasi berbasis PHP native dan MySQL.
- Koneksi database menggunakan PDO.
- Lingkungan pengembangan lokal yang didokumentasikan adalah XAMPP.
- Antarmuka menggunakan Bootstrap, Font Awesome, dan Chart.js melalui CDN.

## 11. Indikator Keberhasilan

- Bendahara dapat mencatat setoran dan penarikan tanpa menghitung saldo secara manual.
- Saldo yang ditampilkan sesuai dengan hasil agregasi transaksi berhasil.
- Pengajuan penarikan dapat dilacak dari status menunggu sampai keputusan bendahara.
- Anggota dapat menemukan riwayat dan kuitansi transaksi miliknya.
- Pengurus dapat menghasilkan laporan sesuai periode atau anggota yang dipilih.
- Pengguna tanpa hak akses tidak dapat melakukan tindakan di luar perannya.

## 12. Batasan dan Risiko Implementasi Saat Ini

Bagian ini mencatat batasan yang terlihat dari implementasi saat dokumen dibuat, bukan menyatakan seluruhnya sebagai fitur yang sudah selesai.

1. Pemulihan kata sandi masih berupa instruksi menghubungi pengurus, belum berupa reset mandiri.
2. Notifikasi tersedia di dalam aplikasi; integrasi WhatsApp, SMS, dan email belum termasuk.
3. Ekspor laporan `.xls` menggunakan tabel HTML, sedangkan ekspor PDF dilakukan melalui dialog cetak browser, bukan pembangkit PDF khusus.
4. Form unggah menyebut batas ukuran dan jenis file. Validasi server sebaiknya ditingkatkan dengan pemeriksaan MIME, ukuran file, nama acak yang aman, dan lokasi penyimpanan yang tidak dapat mengeksekusi skrip.
5. Pembentukan kode transaksi dan persetujuan penarikan perlu perlindungan transaksi database/locking agar tetap konsisten jika ada permintaan bersamaan.
6. Token CSRF dan kebijakan keamanan sesi perlu ditambahkan untuk perlindungan formulir dan sesi yang lebih kuat.
7. Akun demo menggunakan kata sandi bawaan untuk pengembangan; kredensial wajib diganti sebelum penggunaan nyata.
8. Kebijakan pencadangan, retensi, dan pemulihan database perlu ditetapkan sebelum aplikasi digunakan untuk data operasional.

## 13. Rekomendasi Pengembangan Berikutnya

1. Menambahkan proteksi CSRF, pembatasan percobaan login, dan regenerasi ID sesi setelah login.
2. Memvalidasi unggahan berdasarkan MIME dan ukuran di sisi server serta menyimpan file dengan izin minimum.
3. Memproses transaksi keuangan dalam database transaction dan mengunci saldo saat persetujuan/penarikan.
4. Menambahkan pagination dan pencarian yang efisien untuk data transaksi berukuran besar.
5. Menambahkan pengujian otomatis untuk autentikasi, hak akses, saldo, pengajuan, dan laporan.
6. Membuat proses backup database dan panduan pemulihan.
7. Menambahkan notifikasi eksternal hanya jika organisasi menyetujui kanal dan kebijakan privasinya.

## 14. Glosarium

| Istilah | Arti |
|---|---|
| PKK | Pemberdayaan dan Kesejahteraan Keluarga |
| Admin | Pengelola akun, data anggota, konfigurasi, dan laporan sistem |
| Bendahara | Petugas yang mencatat transaksi dan memproses pengajuan penarikan |
| Anggota/Pengguna | Pemilik tabungan yang dapat memantau saldo dan mengirim pengajuan |
| Saldo berjalan | Nilai saldo setelah transaksi dihitung secara berurutan |
| RBAC | Role-Based Access Control, pembatasan akses berdasarkan peran |
| Prepared statement | Cara menjalankan query dengan parameter terpisah dari sintaks SQL |
| Hash kata sandi | Bentuk satu arah kata sandi yang dipakai untuk verifikasi tanpa menyimpan teks asli |

## 15. Referensi Implementasi

- Skema tabel dan data awal: `database.sql`
- Koneksi PDO dan inisialisasi database: `config/database.php`
- Helper saldo, autentikasi, role, tanggal, dan Rupiah: `config/helpers.php`
- Fitur administrator: `admin/`
- Fitur bendahara: `bendahara/`
- Fitur anggota: `pengguna/`
- Komponen antarmuka: `includes/` dan `assets/`
- Ringkasan fitur dan petunjuk penggunaan: `README.md`
