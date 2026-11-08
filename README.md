# 🏪 Ramstore POS (`php.v3Cashier`) — Panduan Pengguna & Manual Operasional Lengkap

Selamat datang di dokumentasi pengguna **Ramstore POS (`php.v3Cashier`)**, aplikasi **Point of Sale (POS) Minimarket Real-Time** berbasis kerangka kerja **CodeIgniter 4 (MVC)** dengan antarmuka Neo-Minimalist yang mendukung **Light Mode & Dark Mode**, **Emulasi Barcode Scanner**, **Multi-Payment & QRIS Dinamis**, **FEFO Batch Expiry Tracking**, serta **Manajemen Inventaris & Analitik Margin Laba Kotor**.

## 1. Gambaran Umum Sistem & Hak Akses Peran

| Peran Pengguna | Hak Akses & Tanggung Jawab Utama |
| :--- | :--- |
| **A. Admin (`admin`)** | Memegang kendali penuh atas manajemen basis data pengguna (CRUD Admin & Kasir), pengelolaan katalog master barang, kategori (`jenis_barang`), pelacakan batch kadaluarsa FEFO, penerimaan stok Purchase Order (PO) dari pemasok, pelaksanaan Stok Opname, pengaturan promo bundling, pemberian otorisasi PIN Supervisor untuk Void/Refund, pemantauan laporan riwayat transaksi & analitik margin laba kotor, pemeriksaan Audit Trail, serta eksekusi backup database `.sql.gz`. |
| **B. Kasir (`kasir`)** | Mengoperasikan modul terminal transaksi penjualan real-time (`/pos`), memindai barcode barang, mengelola keranjang belanja multi-item, menunda/memanggil transaksi (`Hold/Resume`), memproses pembayaran tunai maupun non-tunai/QRIS dinamis, mencetak struk thermal, melakukan rekonsiliasi kas awal/akhir shift (`Morning`, `Afternoon`, `Night`), serta melihat daftar riwayat transaksi yang diproses selama bertugas. |

## 2. Persyaratan Sistem & Cara Instalasi Cepat

1. **Persyaratan Server**: PHP `8.1+` / `8.2+` (dengan ekstensi `pdo_mysql`, `mysqli`, `mbstring`, `intl`, `json`, `zlib`) dan MySQL `5.7+` / `8.0+` atau MariaDB `10.4+` (XAMPP / Laragon / LAMP).
2. **Impor Skema & Data Awal Database**:
   Jalankan MySQL dan impor skrip lengkap dari `sql/ramstore_schema_seed.sql`:
   ```sql
   SOURCE sql/ramstore_schema_seed.sql;
   ```
3. **Konfigurasi Lingkungan (`.env`)**:
   Sesuaikan kredensial MySQL Anda pada file `.env` jika diperlukan (`database.default.database = ramstore_pos`).
4. **Jalankan Server Aplikasi**:
   ```bash
   php spark serve
   # atau:
   php -S localhost:8080 -t public
   ```
5. Buka peramban Anda pada alamat **`http://localhost:8080/login`**.

## 3. Akun Demo & Kredensial Default

| Peran | Username | Password | Shift Kerja | PIN Supervisor Void |
| :--- | :--- | :--- | :--- | :--- |
| **Super Admin** | `admin` | `admin123` | All-Shift | `123456` |
| **Supervisor** | `david` | `admin123` | All-Shift | `123456` |
| **Kasir Pagi** | `kasir_pagi` | `kasir123` | `Morning` | *(Meminta PIN Admin)* |
| **Kasir Siang** | `kasir_siang` | `kasir123` | `Afternoon` | *(Meminta PIN Admin)* |
| **Kasir Malam** | `kasir_malam` | `kasir123` | `Night` | *(Meminta PIN Admin)* |

## 4. Panduan Operasional Kasir (Terminal POS `/pos`)

- **Rekonsiliasi Kas Shift (`F6`)**: Buka shift kerja di awal tugas dengan memasukkan modal kas awal laci (misal `Rp 500.000`). Di akhir shift, masukkan hasil hitung fisik uang tunai di laci untuk mendeteksi selisih kas otomatis terhadap catatan sistem.
- **Emulasi Barcode Scanner**: Mendukung pemindai barcode USB/Wireless secara otomatis (`< 45ms burst`) maupun input manual di kolom barcode lalu tekan `Enter`.
- **Transaksi Multi-Item & Kalkulasi Bundling Otomatis**: Tambahkan berbagai produk ke dalam satu keranjang. Apabila item memenuhi aturan promo (misal beli 2 kaleng Kopi Latte dari harga normal `Rp 16.000` menjadi `Rp 14.000`), potongan promo dihitung secara otomatis.
- **Validasi & Pengurangan Stok FEFO**: Setiap transaksi yang berhasil langsung mengurangi stok barang di tabel `barang` dan mengalokasikan pengurangan pada `batch_barang` dari tanggal kadaluarsa terdekat (**First-Expired, First-Out**). Jika jumlah beli melebihi stok tersedia, transaksi ditolak beserta pesan peringatan.
- **Transaksi Tertunda / Parked Carts (`F4`)**: Simpan sementara keranjang pelanggan yang meninggalkan kasir sejenak dan panggil kembali (`Resume`) kapan saja.
- **Void & Supervisor Override (`F9` / `✕`)**: Menghapus item dari keranjang aktif atau mengosongkan keranjang mewajibkan input **PIN Supervisor (`123456`)** dari akun Admin/Supervisor.
- **Multi-Payment & Dynamic QRIS (`F8`)**: Mendukung pembayaran `Cash`, `QRIS` (dengan generator payload EMVCo & CRC16), `Debit`, `E-Wallet`, maupun `Split Payment` (kombinasi Tunai + QRIS).
- **Cetak Struk Thermal (`Enter`) & Riwayat Kasir**: Cetak struk pembayaran langsung setelah checkout atau melalui menu **Riwayat Transaksi**.

### Daftar Pintasan Keyboard Kasir (POS Hotkeys)
- **`F2`**: Ubah kuantitas item terakhir di keranjang belanja
- **`F4`**: Parkir/tunda keranjang aktif atau buka daftar keranjang tertunda
- **`F6`**: Buka modal Rekonsiliasi Kas Shift
- **`F8`**: Buka jendela Pembayaran (Tender Checkout)
- **`F9`**: Minta otorisasi PIN Supervisor untuk Void keranjang
- **`Enter`**: Cetak struk pembayaran saat modal struk terbuka

## 5. Panduan Operasional Administrator (Admin Dashboard `/admin`)

1. **Dashboard & Peringatan (`📊`)**: Pantau pendapatan harian, laba kotor kumulatif, daftar produk **Low-Stock** (`stok <= stok_minimum`), dan peringatan batch produk **Near-Expiry** (`<= 3 hari` sebelum kadaluarsa).
2. **Produk & Batch FEFO (`📦`)**: Kelola CRUD katalog barang ber-barcode beserta registrasi batch tanggal kadaluarsa baru.
3. **Kategori Barang (`🏷️`)**: Kelola CRUD klasifikasi produk minimarket (`jenis_barang`).
4. **Akun Admin & Kasir (`👥`)**: Kelola akun Admin (beserta PIN Void) dan Kasir beserta pembagian shift (`Morning`, `Afternoon`, `Night`).
5. **Analitik Laba & Margin (`📈`)**: Lihat laporan laba kotor (`harga_jual - harga_beli`) dan persentase margin keuntungan per kategori maupun per produk.
6. **PO & Pemasok (`🚚`)**: Catat pengiriman stok masuk dari distributor (misal 50 krat @ 24 botol otomatis menambah 1.200 botol ke stok aktif dan batch FEFO).
7. **Stok Opname & Selisih (`📋`)**: Bandingkan jumlah hitung fisik rak dengan stok sistem untuk merekam laporan selisih penyusutan (*shrinkage/spoilage*).
8. **Promosi & Bundling (`🎁`)**: Buat dan aktifkan/nonaktifkan aturan promo `Bundle_Pair`, `Min_Qty_Cut`, atau `Percent_Discount`.
9. **Transaksi, Shift & Void (`🧾`)**: Pantau seluruh transaksi kasir, proses **Refund** struk (mengembalikan stok otomatis), lihat laporan rekonsiliasi shift, dan audit log otorisasi Void.
10. **Audit Trail & Backup Database (`🛡️`)**: Periksa jejak audit imutabel (`data_lama → data_baru` + IP Address) dan buat cadangan database terkompresi **`.sql.gz`** secara manual atau terjadwal (`php spark db:backup`).
