/**
 * TabunganPKK - Application Scripts
 */

document.addEventListener('DOMContentLoaded', function () {
    // Tunggu HTML selesai dibaca sebelum mencari elemen halaman.
    // 1. Mobile Sidebar Toggle
    // Ambil tombol, sidebar, dan lapisan gelap yang dipakai pada navigasi mobile.
    const sidebarToggle = document.getElementById('sidebarToggle');
    const appSidebar = document.querySelector('.app-sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    // Pasang aksi buka/tutup hanya jika tombol dan sidebar tersedia di halaman.
    if (sidebarToggle && appSidebar) {
        // Klik tombol akan mengubah kelas CSS untuk menampilkan atau menyembunyikan sidebar.
        sidebarToggle.addEventListener('click', function () {
            appSidebar.classList.toggle('show');
            // Overlay dapat tidak ada pada layout tertentu, jadi periksa sebelum dipakai.
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('show');
            }
        });
    }

    // Klik area gelap menutup menu agar navigasi mobile terasa alami.
    if (sidebarOverlay && appSidebar) {
        sidebarOverlay.addEventListener('click', function () {
            // Hapus kelas tampil dari kedua elemen.
            appSidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });
    }

    // 2. Format Rupiah Input Helper
    // Cari seluruh input yang meminta pemformatan angka menjadi rupiah.
    const rupiahInputs = document.querySelectorAll('.format-rupiah-input');
    // Terapkan listener satu per satu karena halaman bisa punya lebih dari satu input.
    rupiahInputs.forEach(input => {
        // Format ulang isi input setelah pengguna mengetik.
        input.addEventListener('keyup', function (e) {
            this.value = formatRupiah(this.value);
        });
    });

    // 3. Konfirmasi Hapus Data
    // Cari tombol hapus yang meminta konfirmasi sebelum tautan dijalankan.
    const deleteButtons = document.querySelectorAll('.btn-confirm-delete');
    // Pasang handler pada setiap tombol hapus.
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            // Gunakan nama item dari atribut HTML pada dialog konfirmasi.
            const itemName = this.getAttribute('data-name') || 'data ini';
            // Batalkan navigasi jika pengguna memilih batal.
            if (!confirm(`Apakah Anda yakin ingin menghapus ${itemName}? Tindakan ini tidak dapat dibatalkan.`)) {
                e.preventDefault();
            }
        });
    });

    // 4. Auto-hide Alert Flash Messages after 5s
    // Cari pesan sementara yang boleh ditutup otomatis.
    const flashAlerts = document.querySelectorAll('.alert-dismissible');
    // Jadwalkan penutupan setiap pesan lima detik setelah halaman dimuat.
    flashAlerts.forEach(alert => {
        setTimeout(() => {
            // Gunakan API Bootstrap agar transisi alert ditangani library.
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            // Pastikan objek alert tersedia sebelum meminta Bootstrap menutupnya.
            if (bsAlert) {
                bsAlert.close();
            }
        }, 5000);
    });
});

/**
 * Format string to Rupiah
 */
function formatRupiah(angka) {
    // Hapus karakter selain angka dan koma agar input konsisten.
    let number_string = angka.replace(/[^,\d]/g, '').toString();
    // Pisahkan bagian bilangan bulat dan pecahan berdasarkan koma.
    let split = number_string.split(',');
    // Tentukan jumlah digit awal yang tidak membentuk kelompok ribuan penuh.
    let sisa = split[0].length % 3;
    // Simpan digit awal sebelum kelompok berisi tiga angka.
    let rupiah = split[0].substr(0, sisa);
    // Pecah digit lainnya menjadi kelompok ribuan.
    let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

    // Gabungkan kelompok ribuan dengan titik sebagai pemisah.
    if (ribuan) {
        // Kelompok pertama memerlukan titik hanya bila ada digit awal.
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }

    // Pertahankan bagian desimal jika pengguna memasukkan koma.
    rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    // Kembalikan teks yang sudah siap ditampilkan pada input.
    return rupiah;
}
