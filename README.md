# PESUT - Sistem Informasi Pengajuan Cuti & Izin Pegawai

**PESUT** (Pengelolaan Elektronik Surat Cuti & Izin Terpadu) adalah sistem informasi berbasis web yang dikembangkan khusus untuk mengelola, mengajukan, dan memonitor data cuti serta izin pegawai di lingkungan instansi pemerintahan (khususnya disesuaikan untuk struktur PTUN). Sistem ini hadir dengan antarmuka modern (Glassmorphism & Dark/Light Mode) dan alur persetujuan berjenjang.

---

## 🌟 Fitur Utama

### 1. Manajemen Cuti & Izin Terintegrasi
Pegawai dapat mengajukan berbagai jenis ketidakhadiran secara mandiri melalui sistem:
- **Cuti Tahunan** (Sistem memisahkan perhitungan sisa cuti tahun berjalan dan sisa cuti tahun lalu).
- **Cuti Sakit**
- **Cuti Alasan Penting**
- **Cuti Melahirkan** (Form secara otomatis hanya muncul untuk pegawai berjenis kelamin perempuan).
- **Izin Keluar Kantor** (Pulang Cepat / Terlambat) lengkap dengan kalkulasi durasi otomatis.

### 2. Logika Aturan Kepegawaian Otomatis (Hakim, PNS, PPPK)
Sistem ini dengan cerdas membedakan hak dan validasi input berdasarkan status kepegawaian:
- **Hakim & PNS**: Memiliki hak akses penuh terhadap Cuti Tahunan, Cuti Tahun Lalu, Cuti Sakit, Cuti Alasan Penting, dan Melahirkan.
- **PPPK**: Sistem secara otomatis membatasi (menghilangkan/men-strip) hak atas "Cuti Tahun Lalu" dan "Cuti Alasan Penting" dari antarmuka pengajuan dan laporan karena PPPK tidak menerima fasilitas tersebut.

### 3. Kontrol Cuti (Khusus Kepegawaian / Admin)
Halaman sentral bagi Subbagian Kepegawaian (Kasubag / Staf KPOT) untuk memonitor sisa saldo:
- **Dashboard Sisa Cuti**: Menampilkan seluruh kuota saldo (Tahunan, Tahun Lalu, Sakit, Melahirkan, Penting) untuk setiap pegawai dalam satu tabel rapi.
- **Penyesuaian Manual**: Memungkinkan admin merubah jatah dan sisa cuti secara manual jika terjadi anomali, dengan tampilan yang beradaptasi sesuai status kepegawaian yang di-edit.
- **Export to Excel**: Laporan akhir dapat diunduh dalam format `.xls` dengan _conditional formatting_ yang rapi, terpisah untuk daftar PNS, Hakim, dan PPPK sesuai aturan baku.

### 4. Alur Persetujuan (Approval) Berjenjang
Proses birokrasi difasilitasi dengan *multi-level approval*:
- **Tahap 1:** Atasan Langsung (Mengetahui).
- **Tahap 2:** Kepegawaian (Memverifikasi saldo dan aturan administrasi).
- **Tahap 3:** Pimpinan (Ketua / Panitera / Sekretaris) sebagai pemberi TTD Final.
*Setiap pejabat memiliki antarmuka "Review & TTD" tersendiri (menggunakan Canvas Tanda Tangan Digital).*

### 5. Penomoran SK Otomatis
Sistem dilengkapi dengan *generator* nomor Surat Keputusan (SK) ketika dokumen mencapai tahap finalisasi.
- **PNS/Hakim**: Menggunakan format `[Urut]/KPTUN.W6-TUN3/KP5.3/[Romawi]/[Tahun]`.
- **PPPK**: Secara otomatis menambahkan kode awalan `SEK.` pada format nomor SK.

### 6. Cetak Dokumen Instan (Word & PDF)
Setelah cuti/izin selesai disetujui, pegawai tidak perlu lagi mengetik manual. Sistem menyediakan tombol:
- **Cetak Word (.rtf)**: Dokumen mentah yang bisa langsung di-edit jika perlu penyesuaian khusus.
- **Cetak PDF**: Dokumen _read-only_ siap arsip, lengkap dengan tanda tangan digital para pejabat yang bersangkutan.

### 7. Kelola Pegawai (Multi-Add User)
Memudahkan tugas Admin IT:
- Menambahkan banyak pegawai sekaligus dalam satu layar.
- Mengatur *role* (Hak Akses) spesifik seperti Panitera, Kasubag KPOT, dll.
- Auto-assign *Tanggal Mulai Kerja* PNS dari deret angka NIP mereka.
- Reset password ke *default* dan pengaturan akun aktif/nonaktif.

### 8. UI/UX Modern & Responsif
- **Tema Glassmorphism**: Elemen melayang transparan (Kaca) yang elegan.
- **Dark Mode & Light Mode**: Dapat dialihkan (*toggle*) kapan saja untuk kenyamanan mata pengguna.
- **Mobile Friendly**: Layout tabel, formulir, dan navigasi (*burger menu*) dapat dioperasikan secara sempurna dari layar _smartphone_.

---

## 🛠️ Teknologi yang Digunakan
- **Frontend**: HTML5, CSS3 (Vanilla + Variabel), JavaScript (Vanilla).
- **Backend**: PHP 8+ (PDO for Database).
- **Database**: MySQL / MariaDB.
- **Library Tambahan**: 
  - *TomSelect* (Dropdown Searchable)
  - *Signature Pad* (Tanda Tangan Canvas HTML5)
  - *SweetAlert2* (Notifikasi Popup Interaktif)

---

## 📝 Akses & Role Default
Sistem memiliki pengaturan berbasis role (Hak Akses) untuk menu-menu spesifik:
- `admin`: Memiliki akses kelola Data User dan konfigurasi utama.
- `kepegawaian` / `staf_kpot`: Mengakses Kontrol Cuti, Cetak Excel Laporan, Review pengajuan seluruh staf.
- `ketua` / `panitera` / `sekretaris`: Menerima notifikasi untuk melakukan persetujuan (approval) tingkat akhir.
- Pegawai / Staf: Hanya bisa mengajukan dan melihat riwayat cuti miliknya sendiri, serta melakukan approval jika ia ditunjuk sebagai "Atasan Langsung" dari pegawai lain.

---
_Dokumentasi ini dibuat untuk memandu admin atau developer baru memahami cakupan dan ekosistem dari aplikasi PESUT._
