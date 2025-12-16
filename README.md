# 🚀 Panduan Singkat Mini ERP Bengkel

## Apa yang Sudah Jadi?

Sistem untuk mengelola data bengkel lewat web browser. Sudah bisa:

### ✅ Yang Sudah Selesai:

1. **Login** - Masuk ke sistem pakai username & password
2. **Dashboard** - Halaman utama, lihat ringkasan data
3. **Kelola User** - Tambah/edit/hapus pengguna sistem
4. **Kelola Customer** - Daftar pelanggan bengkel
5. **Kelola Kendaraan** - Mobil/motor pelanggan
6. **Kelola Sparepart** - Stok onderdil + alert kalau mau habis

---

## 📱 Cara Pakai

### Login:
1. Buka browser → ketik: `http://localhost:8000/login.php`
2. Username: `admin`
3. Password: `admin123` (atau `password`)
4. Klik Login

### Menu-menu:
- **Users** = Kelola pengguna sistem
- **Customers** = Kelola data pelanggan
- **Vehicles** = Kelola kendaraan pelanggan
- **Spareparts** = Kelola stok onderdil

### Tambah Data Baru:
1. Klik menu yang mau diisi
2. Klik tombol "Tambah ..." (warna biru)
3. Isi form yang muncul
4. Klik "Simpan"
5. Data langsung muncul di tabel

### Edit Data:
1. Klik tombol kuning (icon pensil) di baris data
2. Ubah datanya
3. Klik "Simpan"

### Hapus Data:
1. Klik tombol merah (icon tempat sampah)
2. Konfirmasi hapus
3. Data hilang

### Cari Data:
- Ketik kata kunci di kotak pencarian
- Data otomatis terfilter

---

## ⚠️ Hal Penting

1. **Kalau Tidak Bisa Login:**
   - Buka: `http://localhost:8000/debug_password.php`
   - Klik tombol "Auto Update Password"
   - Login lagi

2. **Data Yang Tidak Bisa Dihapus:**
   - Customer yang punya kendaraan
   - Kendaraan yang punya SPK
   - Sparepart yang ada transaksi
   - User sendiri yang lagi login

3. **Alert Stok:**
   - Dashboard kasih tahu kalau ada sparepart yang stoknya tinggal sedikit
   - Warna merah = stok di bawah minimum

---

## 🎯 Yang Bisa Dikerjakan Sekarang

### Test 1: Kelola Customer
1. Login
2. Klik "Customers"
3. Tambah customer baru (nama, telp, alamat)
4. Coba search customer
5. Edit datanya
6. Hapus (kalau belum punya kendaraan)

### Test 2: Kelola Kendaraan
1. Klik "Vehicles"
2. Tambah kendaraan
3. Pilih customer pemilik
4. Isi nomor polisi (contoh: B1234XYZ)
5. Isi merk, model, tahun
6. Simpan
7. Coba cari pakai nomor polisi

### Test 3: Kelola Sparepart
1. Klik "Spareparts"
2. Tambah sparepart (contoh: Oli Mesin)
3. Isi harga beli & jual
4. Isi stok saat ini & minimum stok
5. Simpan
6. Coba centang "Tampilkan hanya stok menipis"

---

## 📊 Progress

**Sudah Jadi (4 dari 10):**
- ✅ Login/Logout
- ✅ Dashboard
- ✅ User Management
- ✅ Customer Management
- ✅ Vehicle Management
- ✅ Sparepart Management

**Belum Dibuat:**
- ⏳ Purchases (Pembelian)
- ⏳ SPK (Surat Kerja)
- ⏳ Warehouse Out (Keluar Barang)
- ⏳ Invoice (Tagihan)
- ⏳ Payment (Pembayaran)
- ⏳ Audit Log (Riwayat)

---

## 💻 Teknologi

- **Bahasa:** PHP + MySQL
- **Tampilan:** Bootstrap 5 (responsive)
- **Interaksi:** jQuery + AJAX (tanpa reload)
- **Keamanan:** Password terenkripsi

---

**Tanggal:** 16 Desember 2025  
**Status:** ✅ Siap Testing Phase 1

---

### 📞 Butuh Bantuan?

**Kalau error:**
1. Screenshot errornya
2. Cek terminal PHP (kotak hitam)
3. Catat lagi ngapain sebelum error

**File bantuan:**
- `debug_password.php` - Cek/reset password
- `setup_admin.php` - Bikin ulang user admin
- `DOKUMENTASI.md` - Dokumentasi lengkap

---

🎉 **Selamat Mencoba!**
