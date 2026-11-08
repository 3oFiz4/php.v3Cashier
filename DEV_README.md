# 🛠️ Ramstore POS (`php.v3Cashier`) — Dokumentasi Arsitektur Pengembang (Developer Guide)

Dokumen ini menjelaskan arsitektur internal **CodeIgniter 4 MVC**, skema relasional 15 tabel basis data, mekanisme pustaka layanan bisnis, serta daftar parameter konfigurasi yang dapat dimodifikasi oleh pengembang.

## 1. Arsitektur MVC & Komponen Sistem

- **Front Controller & Routing (`public/index.php` & `app/Config/Routes.php`)**: Menangani inisialisasi lingkungan CodeIgniter 4, pembacaan `.env`, autoloading PSR-4 untuk namespace `App\` dan `Config\`, serta pemetaan rute HTTP ke Controller terkait.
- **Security Filters (`app/Filters/AuthFilter.php` & `AuditFilter.php`)**: Memvalidasi sesi login dan membatasi akses endpoint antara peran `admin` dan `kasir`.
- **Business Libraries (`app/Libraries/`)**:
  - `PromoEngine.php`: Mengevaluasi item keranjang terhadap aturan aktif di tabel `promosi_bundling` (`Bundle_Pair`, `Min_Qty_Cut`, `Percent_Discount`).
  - `FefoAllocator.php`: Mengurangi stok batch pada tabel `batch_barang` berdasarkan urutan `tanggal_kadaluarsa ASC` dengan penguncian baris `FOR UPDATE` serta memperbarui status batch (`Active`, `Near_Expiry`, `Expired`, `Depleted`).
  - `QrisGenerator.php`: Menghasilkan string payload **EMVCo Dynamic QRIS** beserta perhitungan checksum heksadesimal **CRC16-CCITT (`0x1021`)**.
  - `AuditLogger.php`: Mencatat setiap mutasi data kritis ke tabel `log_audit` lengkap dengan snapshot JSON `data_lama`, `data_baru`, alamat IP, dan User-Agent.
  - `BackupService.php`: Mengekspor DDL dan DML seluruh tabel database ke dalam arsip terkompresi Zlib (`.sql.gz`) di `writable/backups/` dan mencatat metadatanya ke `riwayat_backup`.

## 2. Daftar Parameter Konfigurasi yang Dapat Dimodifikasi

Anda dapat memodifikasi konfigurasi sistem melalui file **`.env`** maupun **`app/Config/PosConfig.php`**:

| Parameter Konfigurasi | Lokasi File | Nilai Default | Fungsi & Keterangan |
| :--- | :--- | :--- | :--- |
| `CI_ENVIRONMENT` | `.env` | `development` | Mode eksekusi aplikasi (`development` atau `production`). |
| `app.baseURL` | `.env` / `App.php` | `'http://localhost:8080/'` | URL dasar aplikasi web. |
| `app.appTimezone` | `.env` / `App.php` | `'Asia/Makassar'` | Zona waktu pencatatan transaksi dan log. |
| `database.default.hostname` | `.env` / `Database.php` | `localhost` | Nama host server MySQL/MariaDB. |
| `database.default.database` | `.env` / `Database.php` | `ramstore_pos` | Nama database relasional POS. |
| `database.default.username` | `.env` / `Database.php` | `root` | Username database MySQL/MariaDB. |
| `database.default.password` | `.env` / `Database.php` | `""` | Password database MySQL/MariaDB. |
| `database.default.port` | `.env` / `Database.php` | `3306` | Port koneksi TCP MySQL/MariaDB. |
| `$storeName` (`pos.storeName`) | `PosConfig.php` / `.env` | `'Ramstore Minimarket'` | Nama minimarket pada header aplikasi dan cetakan struk. |
| `$storeAddress` (`pos.storeAddress`) | `PosConfig.php` / `.env` | `'Jl. Raya Denpasar No. 88, Bali'` | Alamat minimarket pada cetakan struk thermal. |
| `$receiptPrefix` (`pos.receiptPrefix`) | `PosConfig.php` / `.env` | `'RMS'` | Prefiks kode unik nomor struk transaksi (`RMS-YYYYMMDD-XXXX`). |
| `$lowStockDefault` (`pos.lowStockDefault`) | `PosConfig.php` / `.env` | `15` | Batas stok minimum default untuk memicu peringatan stok rendah. |
| `$expiryWarningDays` (`pos.expiryWarningDays`) | `PosConfig.php` / `.env` | `3` | Ambang hari sebelum tanggal kadaluarsa untuk peringatan batch FEFO. |
| `$qrisMerchantName` | `PosConfig.php` / `.env` | `'RAMSTORE MINIMARKET BALI'` | Nama merchant pada payload QRIS Dinamis Tag 59. |
| `$qrisMerchantNmid` | `PosConfig.php` / `.env` | `'ID1026008899001'` | Nomor NMID merchant pada payload QRIS Dinamis Tag 26. |
| `scannerThresholdMs` | `barcode_shortcuts.js` | `45` | Ambang jeda milidetik antar-ketikan untuk deteksi scanner barcode USB. |
