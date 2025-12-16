# 📋 Dokumentasi Mini ERP Bengkel

**Tanggal:** 16 Desember 2025  
**Versi:** 1.0  
**Status:** ✅ CRUD 4 Tabel Pertama Selesai

---

## 🎯 Apa yang Sudah Dibuat?

Sistem manajemen bengkel berbasis web yang bisa diakses melalui browser. Saat ini sudah selesai dibuat **4 modul utama** untuk mengelola data bengkel.

---

## 🗂️ Modul yang Sudah Selesai

### 1. 👥 **Manajemen USER** (Pengguna Sistem)
**Lokasi:** `users/index.php`

**Fungsi:**
- ➕ Tambah pengguna baru (admin, warehouse, owner)
- ✏️ Edit data pengguna
- 🗑️ Hapus pengguna
- 👁️ Lihat daftar semua pengguna
- 🔐 Password dienkripsi otomatis (aman)

**Contoh Penggunaan:**
> Ketika ada karyawan baru, bisa dibuatkan akun di sini dengan role sesuai jabatannya (Admin/Warehouse/Owner).

---

### 2. 👔 **Manajemen CUSTOMER** (Pelanggan)
**Lokasi:** `customers/index.php`

**Fungsi:**
- ➕ Tambah customer baru (nama, telepon, alamat)
- ✏️ Edit data customer
- 🗑️ Hapus customer (jika belum punya kendaraan)
- 👁️ Lihat daftar semua customer
- 🔍 Cari customer berdasarkan nama atau telepon

**Contoh Penggunaan:**
> Saat ada pelanggan baru datang ke bengkel, data mereka bisa langsung didaftarkan di sini.

---

### 3. 🚗 **Manajemen KENDARAAN**
**Lokasi:** `vehicles/index.php`

**Fungsi:**
- ➕ Tambah kendaraan baru (nomor polisi, merk, model, tahun)
- 🔗 Kendaraan otomatis terhubung dengan customer pemiliknya
- ✏️ Edit data kendaraan
- 🗑️ Hapus kendaraan (jika belum ada SPK)
- 👁️ Lihat semua kendaraan yang terdaftar
- 🔍 Cari berdasarkan nomor polisi, merk, atau nama customer

**Contoh Penggunaan:**
> Setelah customer terdaftar, kendaraan mereka (motor/mobil) bisa didaftarkan dengan nomor polisi sebagai identitas utama.

---

### 4. ⚙️ **Manajemen SPAREPART**
**Lokasi:** `spareparts/index.php`

**Fungsi:**
- ➕ Tambah sparepart baru (nama, barcode, harga, stok)
- ✏️ Edit data sparepart
- 🗑️ Hapus sparepart (jika belum ada transaksi)
- 👁️ Lihat semua sparepart
- 📊 Tracking stok (jumlah tersedia vs minimum stok)
- ⚠️ Alert otomatis jika stok menipis
- 🔍 Cari sparepart berdasarkan nama atau barcode
- 💰 Harga beli & harga jual

**Contoh Penggunaan:**
> Semua onderdil/suku cadang yang dijual di bengkel didaftarkan di sini. Sistem otomatis kasih peringatan jika stok sudah mau habis.

---

## 🔐 Cara Login

**URL:** `http://localhost:8000/login.php`

**Kredensial Default:**
- Username: `admin`
- Password: `admin123` atau `password`

> ⚠️ **Jika tidak bisa login**, buka: `http://localhost:8000/debug_password.php` lalu klik tombol "Auto Update Password"

---

## 📱 Tampilan & Fitur Umum

### ✨ Fitur yang Sudah Ada:

1. **Dashboard** - Halaman utama dengan statistik
   - Total user, customer, kendaraan, sparepart
   - Peringatan stok menipis
   - Quick links ke semua modul

2. **Sidebar Menu** - Navigasi mudah ke semua modul

3. **Form Modal** - Tambah/Edit data tanpa pindah halaman

4. **Search/Filter** - Cari data dengan cepat

5. **Validasi** - Sistem otomatis cek data:
   - Username harus unik
   - Nomor polisi tidak boleh sama
   - Barcode tidak boleh duplikat
   - Data yang punya relasi tidak bisa dihapus sembarangan

6. **Responsive Design** - Bisa diakses dari komputer/tablet/HP

---

## 🗄️ Database

**Nama Database:** `minierp`

**Tabel yang Sudah Digunakan:**
1. ✅ `users` - Data pengguna sistem
2. ✅ `customers` - Data pelanggan
3. ✅ `vehicles` - Data kendaraan
4. ✅ `spareparts` - Data sparepart/onderdil

**Tabel yang Belum Dibuat (Next Phase):**
5. ⏳ `purchases` - Pembelian sparepart
6. ⏳ `spk` - Surat Perintah Kerja
7. ⏳ `warehouse_out` - Barang keluar gudang
8. ⏳ `invoices` - Invoice/tagihan
9. ⏳ `payments` - Pembayaran/cicilan
10. ⏳ `audit_logs` - Log aktivitas

---

## 📂 Struktur File

```
minierp/
├── config.php              # Konfigurasi database
├── login.php               # Halaman login
├── logout.php              # Logout
├── dashboard.php           # Dashboard utama
├── header.php              # Template header
├── footer.php              # Template footer
├── 
├── users/
│   ├── index.php          # Tampilan CRUD user
│   └── backend.php        # Proses CRUD user
├── 
├── customers/
│   ├── index.php          # Tampilan CRUD customer
│   └── backend.php        # Proses CRUD customer
├── 
├── vehicles/
│   ├── index.php          # Tampilan CRUD kendaraan
│   └── backend.php        # Proses CRUD kendaraan
├── 
└── spareparts/
    ├── index.php          # Tampilan CRUD sparepart
    └── backend.php        # Proses CRUD sparepart
```

---

## 🚀 Cara Testing

### 1️⃣ Persiapan
- ✅ Database `minierp` sudah dibuat
- ✅ Semua tabel sudah diimport
- ✅ User admin sudah ada
- ✅ Server PHP sudah jalan (port 8000)

### 2️⃣ Login
1. Buka browser
2. Ketik: `http://localhost:8000/login.php`
3. Masukkan username & password
4. Klik Login

### 3️⃣ Test Setiap Modul

**A. Test User Management**
1. Klik menu "Users" di sidebar
2. Klik tombol "Tambah User"
3. Isi form (username, password, nama, role)
4. Klik "Simpan"
5. Coba Edit & Hapus

**B. Test Customer Management**
1. Klik menu "Customers"
2. Klik "Tambah Customer"
3. Isi nama, telepon, alamat
4. Simpan → Lihat muncul di tabel
5. Coba fitur Search
6. Coba Edit & Hapus

**C. Test Vehicle Management**
1. Klik menu "Vehicles"
2. Klik "Tambah Kendaraan"
3. Pilih customer dari dropdown
4. Isi nomor polisi, merk, model, tahun
5. Simpan
6. Coba cari nomor polisi
7. Coba Edit & Hapus

**D. Test Sparepart Management**
1. Klik menu "Spareparts"
2. Klik "Tambah Sparepart"
3. Isi nama, barcode (optional), satuan
4. Isi harga beli & jual
5. Isi stok saat ini & minimum stok
6. Simpan
7. Coba filter "Stok Menipis"
8. Coba Edit & Hapus

---

## ✅ Checklist Testing

### User Management
- [ ] Bisa tambah user baru
- [ ] Password terenkripsi
- [ ] Bisa edit user
- [ ] Tidak bisa hapus user sendiri
- [ ] Username harus unik

### Customer Management
- [ ] Bisa tambah customer
- [ ] Bisa edit customer
- [ ] Search berfungsi
- [ ] Tidak bisa hapus customer yang punya kendaraan

### Vehicle Management
- [ ] Bisa tambah kendaraan
- [ ] Dropdown customer muncul
- [ ] Nomor polisi auto uppercase
- [ ] Nomor polisi harus unik
- [ ] Tidak bisa hapus kendaraan yang punya SPK

### Sparepart Management
- [ ] Bisa tambah sparepart
- [ ] Barcode optional
- [ ] Alert stok menipis muncul
- [ ] Filter stok menipis berfungsi
- [ ] Tidak bisa hapus sparepart yang ada transaksi

---

## 🐛 Troubleshooting

### ❌ Tidak bisa login
**Solusi:**
1. Buka: `http://localhost:8000/debug_password.php`
2. Klik "Auto Update Password"
3. Login lagi

### ❌ Error "Connection failed"
**Penyebab:** Database belum dibuat atau config salah

**Solusi:**
1. Buka phpMyAdmin
2. Buat database `minierp`
3. Import SQL tabel
4. Cek `config.php` (host, user, password)

### ❌ Halaman blank/error
**Solusi:**
1. Cek error di terminal PHP
2. Pastikan semua file ada
3. Restart PHP server

---

## 📊 Progress Proyek

**Phase 1 (✅ SELESAI):**
- [x] Setup database
- [x] Login/Logout system
- [x] Dashboard
- [x] CRUD Users
- [x] CRUD Customers
- [x] CRUD Vehicles
- [x] CRUD Spareparts

**Phase 2 (⏳ PENDING):**
- [ ] CRUD Purchases
- [ ] CRUD SPK
- [ ] CRUD Warehouse Out
- [ ] CRUD Invoices
- [ ] Payment Management
- [ ] Audit Log

---

## 💡 Catatan Penting

1. **Keamanan:**
   - Password dienkripsi dengan algoritma bcrypt
   - Session management untuk login
   - Proteksi hapus data yang berelasi

2. **Validasi:**
   - Semua input divalidasi (frontend & backend)
   - Escape SQL injection
   - Unique constraint untuk data unik

3. **User Experience:**
   - AJAX - tidak perlu reload halaman
   - Bootstrap 5 - tampilan modern & responsive
   - Font Awesome - icon menarik
   - Alert otomatis hilang setelah 3 detik

4. **Performance:**
   - Search dengan delay 500ms
   - Indexed database columns
   - Efficient queries

---

## 📞 Bantuan

**Jika ada error:**
1. Screenshot error yang muncul
2. Catat apa yang dilakukan sebelum error
3. Cek terminal PHP untuk error log

**File untuk debug:**
- `debug_password.php` - Cek password
- `setup_admin.php` - Reset user admin

---

**Dibuat oleh:** AI Assistant  
**Terakhir Update:** 16 Desember 2025  
**Status:** ✅ Phase 1 Complete - Siap Testing

---

## 🎉 Summary

Sistem Mini ERP Bengkel sudah memiliki **4 modul CRUD lengkap** yang siap digunakan:
1. ✅ User Management
2. ✅ Customer Management  
3. ✅ Vehicle Management
4. ✅ Sparepart Management

Semua modul sudah dilengkapi dengan:
- Form tambah/edit yang mudah
- Tabel data yang rapi
- Fitur search/filter
- Validasi data
- Alert dan notifikasi
- Tampilan responsive

**Next Step:** Testing semua fitur dan lanjut ke Phase 2 (Purchases, SPK, dll)
