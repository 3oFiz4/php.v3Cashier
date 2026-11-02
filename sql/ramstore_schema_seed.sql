-- skema database relasional aplikasi pos minimarket ramstore (codeigniter 4)
CREATE DATABASE IF NOT EXISTS ramstore_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ramstore_pos;

-- a. tabel admin: simpan informasi administrator sistem dan pin supervisor
CREATE TABLE IF NOT EXISTS admin (
    id_admin INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(120) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    pin_supervisor VARCHAR(255) NOT NULL,
    tanggal_registrasi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- b. tabel kasir: simpan data petugas kasir beserta jadwal shift kerja
CREATE TABLE IF NOT EXISTS kasir (
    id_kasir INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(120) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    no_telepon VARCHAR(25) NOT NULL,
    shift ENUM('Morning', 'Afternoon', 'Night') NOT NULL DEFAULT 'Morning',
    status_aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- c. tabel jenis_barang: simpan klasifikasi kategori produk minimarket
CREATE TABLE IF NOT EXISTS jenis_barang (
    id_jenis INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_jenis VARCHAR(100) NOT NULL UNIQUE,
    deskripsi VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- d. tabel barang: simpan katalog produk minimarket terhubung ke jenis_barang
CREATE TABLE IF NOT EXISTS barang (
    id_barang INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_jenis INT UNSIGNED NOT NULL,
    barcode VARCHAR(64) NOT NULL UNIQUE,
    nama_barang VARCHAR(160) NOT NULL,
    harga_beli DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    harga_jual DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    stok INT NOT NULL DEFAULT 0,
    stok_minimum INT NOT NULL DEFAULT 15,
    gambar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_barang_jenis FOREIGN KEY (id_jenis) REFERENCES jenis_barang(id_jenis) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_barang_barcode (barcode),
    INDEX idx_barang_nama (nama_barang)
) ENGINE=InnoDB;

-- fitur 2: tabel rekonsiliasi_shift untuk lacak kas awal, penjualan sistem, dan selisih kas fisik
CREATE TABLE IF NOT EXISTS rekonsiliasi_shift (
    id_rekon INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_kasir INT UNSIGNED NOT NULL,
    shift ENUM('Morning', 'Afternoon', 'Night') NOT NULL,
    waktu_buka DATETIME NOT NULL,
    waktu_tutup DATETIME DEFAULT NULL,
    modal_awal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    penjualan_tunai_sistem DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    penjualan_non_tunai_sistem DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    kas_fisik_akhir DECIMAL(14,2) DEFAULT NULL,
    selisih_kas DECIMAL(14,2) DEFAULT NULL,
    catatan VARCHAR(255) DEFAULT NULL,
    status ENUM('Open', 'Closed') NOT NULL DEFAULT 'Open',
    CONSTRAINT fk_rekon_kasir FOREIGN KEY (id_kasir) REFERENCES kasir(id_kasir) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- e. tabel transaksi: simpan header struk penjualan real-time
CREATE TABLE IF NOT EXISTS transaksi (
    id_transaksi INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    no_struk VARCHAR(60) NOT NULL UNIQUE,
    tanggal_transaksi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_kasir INT UNSIGNED NOT NULL,
    id_rekon INT UNSIGNED DEFAULT NULL,
    total_item INT NOT NULL DEFAULT 0,
    subtotal_awal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_diskon DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_harga DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    metode_pembayaran ENUM('Cash', 'Debit', 'E-Wallet', 'QRIS', 'Split') NOT NULL DEFAULT 'Cash',
    bayar_tunai DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    bayar_non_tunai DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    referensi_pembayaran VARCHAR(100) DEFAULT NULL,
    jumlah_bayar DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    kembalian DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status_transaksi ENUM('Completed', 'Voided', 'Refunded') NOT NULL DEFAULT 'Completed',
    CONSTRAINT fk_transaksi_kasir FOREIGN KEY (id_kasir) REFERENCES kasir(id_kasir) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_transaksi_rekon FOREIGN KEY (id_rekon) REFERENCES rekonsiliasi_shift(id_rekon) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_transaksi_tanggal (tanggal_transaksi)
) ENGINE=InnoDB;

-- fitur 10: tabel batch_barang untuk pelacakan nomor batch dan tanggal kadaluarsa (fefo)
CREATE TABLE IF NOT EXISTS batch_barang (
    id_batch INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_barang INT UNSIGNED NOT NULL,
    nomor_batch VARCHAR(80) NOT NULL,
    tanggal_masuk DATE NOT NULL,
    tanggal_kadaluarsa DATE NOT NULL,
    stok_awal INT NOT NULL DEFAULT 0,
    stok_tersedia INT NOT NULL DEFAULT 0,
    harga_beli_batch DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status_batch ENUM('Active', 'Near_Expiry', 'Expired', 'Depleted') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_batch_barang FOREIGN KEY (id_barang) REFERENCES barang(id_barang) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_batch_fefo (id_barang, tanggal_kadaluarsa, stok_tersedia)
) ENGINE=InnoDB;

-- f. tabel detail_transaksi: simpan rincian item terbeli per nomor struk
CREATE TABLE IF NOT EXISTS detail_transaksi (
    id_detail INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT UNSIGNED NOT NULL,
    id_barang INT UNSIGNED NOT NULL,
    id_batch INT UNSIGNED DEFAULT NULL,
    nama_barang_snapshot VARCHAR(160) NOT NULL,
    harga_beli_satuan DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    harga_satuan DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    jumlah INT NOT NULL DEFAULT 1,
    diskon_item DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_detail_transaksi FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_detail_barang FOREIGN KEY (id_barang) REFERENCES barang(id_barang) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_detail_batch FOREIGN KEY (id_batch) REFERENCES batch_barang(id_batch) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- fitur 4: tabel pemasok dan pembelian_stok untuk manajemen purchase order distributor
CREATE TABLE IF NOT EXISTS pemasok (
    id_pemasok INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_pemasok VARCHAR(40) NOT NULL UNIQUE,
    nama_pemasok VARCHAR(150) NOT NULL,
    kontak_person VARCHAR(100) NOT NULL,
    no_telepon VARCHAR(30) NOT NULL,
    alamat TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pembelian_stok (
    id_pembelian INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    no_faktur_masuk VARCHAR(80) NOT NULL UNIQUE,
    id_pemasok INT UNSIGNED NOT NULL,
    id_admin INT UNSIGNED NOT NULL,
    tanggal_pembelian DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_unit_masuk INT NOT NULL DEFAULT 0,
    total_biaya DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    catatan VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_po_pemasok FOREIGN KEY (id_pemasok) REFERENCES pemasok(id_pemasok) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_po_admin FOREIGN KEY (id_admin) REFERENCES admin(id_admin) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detail_pembelian_stok (
    id_detail_beli INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pembelian INT UNSIGNED NOT NULL,
    id_barang INT UNSIGNED NOT NULL,
    jumlah_kemasan INT NOT NULL DEFAULT 1,
    unit_per_kemasan INT NOT NULL DEFAULT 1,
    total_unit INT NOT NULL DEFAULT 1,
    harga_beli_satuan DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    subtotal_biaya DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    nomor_batch VARCHAR(80) NOT NULL,
    tanggal_kadaluarsa DATE NOT NULL,
    CONSTRAINT fk_detail_po FOREIGN KEY (id_pembelian) REFERENCES pembelian_stok(id_pembelian) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_detail_po_barang FOREIGN KEY (id_barang) REFERENCES barang(id_barang) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- fitur 6: tabel transaksi_tertunda untuk simpan keranjang belanja yang di-parkir sementara
CREATE TABLE IF NOT EXISTS transaksi_tertunda (
    id_tunda INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_tunda VARCHAR(50) NOT NULL UNIQUE,
    id_kasir INT UNSIGNED NOT NULL,
    label_pelanggan VARCHAR(120) NOT NULL,
    payload_keranjang JSON NOT NULL,
    subtotal_sementara DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    waktu_tunda DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status_tunda ENUM('Held', 'Resumed', 'Discarded') NOT NULL DEFAULT 'Held',
    CONSTRAINT fk_tunda_kasir FOREIGN KEY (id_kasir) REFERENCES kasir(id_kasir) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- fitur 7: tabel otorisasi_void untuk catat persetujuan pin supervisor saat hapus item atau refund
CREATE TABLE IF NOT EXISTS otorisasi_void (
    id_void INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_kasir INT UNSIGNED NOT NULL,
    id_admin_supervisor INT UNSIGNED NOT NULL,
    id_transaksi INT UNSIGNED DEFAULT NULL,
    id_barang INT UNSIGNED DEFAULT NULL,
    jenis_void ENUM('Void_Cart_Item', 'Clear_Active_Cart', 'Refund_Receipt') NOT NULL,
    nominal_terdampak DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    alasan_void VARCHAR(255) NOT NULL,
    waktu_otorisasi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_void_kasir FOREIGN KEY (id_kasir) REFERENCES kasir(id_kasir) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_void_admin FOREIGN KEY (id_admin_supervisor) REFERENCES admin(id_admin) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- fitur 8: tabel stok_opname untuk pencocokan stok fisik rak terhadap saldo sistem
CREATE TABLE IF NOT EXISTS stok_opname (
    id_opname INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_opname VARCHAR(60) NOT NULL UNIQUE,
    id_admin INT UNSIGNED NOT NULL,
    id_barang INT UNSIGNED NOT NULL,
    stok_sistem INT NOT NULL,
    stok_fisik INT NOT NULL,
    selisih_unit INT NOT NULL,
    nominal_selisih DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    alasan_selisih VARCHAR(255) NOT NULL,
    disesuaikan_ke_stok TINYINT(1) NOT NULL DEFAULT 1,
    tanggal_opname DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_opname_admin FOREIGN KEY (id_admin) REFERENCES admin(id_admin) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_opname_barang FOREIGN KEY (id_barang) REFERENCES barang(id_barang) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- fitur 12: tabel promosi_bundling untuk aturan diskon paket dan potongan harga otomatis
CREATE TABLE IF NOT EXISTS promosi_bundling (
    id_promo INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_promo VARCHAR(50) NOT NULL UNIQUE,
    nama_promo VARCHAR(140) NOT NULL,
    id_barang INT UNSIGNED NOT NULL,
    tipe_promo ENUM('Bundle_Pair', 'Min_Qty_Cut', 'Percent_Discount') NOT NULL DEFAULT 'Bundle_Pair',
    min_kuantitas INT NOT NULL DEFAULT 2,
    harga_paket DECIMAL(14,2) DEFAULT NULL,
    potongan_rupiah DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    persen_diskon DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    tanggal_mulai DATE NOT NULL,
    tanggal_akhir DATE NOT NULL,
    status_aktif TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_promo_barang FOREIGN KEY (id_barang) REFERENCES barang(id_barang) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- fitur 13: tabel log_audit untuk rekam jejak perubahan data kritis secara imutabel
CREATE TABLE IF NOT EXISTS log_audit (
    id_log BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    peran_pelaku ENUM('admin', 'kasir', 'system') NOT NULL,
    id_pelaku INT UNSIGNED DEFAULT NULL,
    username_pelaku VARCHAR(60) NOT NULL,
    aksi VARCHAR(80) NOT NULL,
    modul VARCHAR(80) NOT NULL,
    id_entitas VARCHAR(60) DEFAULT NULL,
    data_lama JSON DEFAULT NULL,
    data_baru JSON DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    waktu_kejadian DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_waktu (waktu_kejadian),
    INDEX idx_audit_modul (modul, aksi)
) ENGINE=InnoDB;

-- fitur 20: tabel riwayat_backup untuk pencatatan arsip cadangan database (.sql.gz)
CREATE TABLE IF NOT EXISTS riwayat_backup (
    id_backup INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_file VARCHAR(180) NOT NULL,
    ukuran_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    lokasi_simpan VARCHAR(255) NOT NULL,
    tipe_pemicu ENUM('Scheduled_Cron', 'Manual_Admin') NOT NULL DEFAULT 'Scheduled_Cron',
    id_admin INT UNSIGNED DEFAULT NULL,
    status_backup ENUM('Success', 'Failed') NOT NULL DEFAULT 'Success',
    waktu_backup DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- data awal (seed) untuk operasional langsung minimarket ramstore
-- password default: admin123 (hash bcrypt) dan pin supervisor: 123456
INSERT INTO admin (nama_lengkap, username, password, pin_supervisor) VALUES
('Rama Wijaya (Super Admin)', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('David Pratama (Supervisor)', 'david', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- data awal kasir tiga shift (password default: kasir123)
INSERT INTO kasir (nama_lengkap, username, password, no_telepon, shift, status_aktif) VALUES
('Merta Sari', 'kasir_pagi', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567801', 'Morning', 1),
('Budi Santoso', 'kasir_siang', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567802', 'Afternoon', 1),
('Ayu Lestari', 'kasir_malam', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '081234567803', 'Night', 1);

-- data awal kategori produk minimarket
INSERT INTO jenis_barang (nama_jenis, deskripsi) VALUES
('Beverages', 'Minuman ringan, air mineral, kopi kaleng, teh botol, dan jus kemasan'),
('Snacks', 'Makanan ringan, keripik, biskuit, cokelat, dan kacang kemasan'),
('Personal Care', 'Produk perawatan tubuh, sabun, sampo, pasta gigi, dan kebutuhan bayi'),
('Household/Kitchen Essentials', 'Kebutuhan dapur, mie instan, minyak goreng, deterjen, dan pembersih rumah'),
('Dairy & Fresh', 'Produk susu segar, keju, yogurt, dan roti harian dengan pengawasan kadaluarsa ketat');

-- data awal katalog barang beserta barcode, harga beli, harga jual, dan batas stok minimum
INSERT INTO barang (id_jenis, barcode, nama_barang, harga_beli, harga_jual, stok, stok_minimum, gambar) VALUES
(1, '899100100001', 'Air Mineral Ramstore 600ml', 2200.00, 3500.00, 140, 24, 'assets/product_img/IMG20210201152252.avif'),
(1, '899100100002', 'Kopi Kaleng Latte Espresso 240ml', 5800.00, 8000.00, 65, 15, NULL),
(1, '899100100003', 'Teh Hijau Melati Botol 500ml', 4500.00, 6500.00, 80, 20, NULL),
(1, '899100100004', 'Minuman Soda Cola Dingin 330ml', 4800.00, 7000.00, 95, 20, NULL),
(2, '899100200001', 'Keripik Kentang Original 100g', 8200.00, 11500.00, 95, 15, NULL),
(2, '899100200002', 'Cokelat Batang Almond 65g', 9500.00, 13500.00, 50, 15, NULL),
(2, '899100200003', 'Kacang Panggang Pedas Manis 150g', 11000.00, 15000.00, 42, 12, NULL),
(3, '899100300001', 'Sabun Mandi Cair Antibakteri 450ml', 17500.00, 24500.00, 38, 10, NULL),
(3, '899100300002', 'Susu Formula Bayi Gold Tin 800g', 115000.00, 150000.00, 18, 8, NULL),
(3, '899100300003', 'Sampo Herbal Anti Ketombe 340ml', 21000.00, 28500.00, 30, 10, NULL),
(4, '899100400001', 'Mie Instan Goreng Spesial 85g', 2400.00, 3200.00, 12, 15, NULL),
(4, '899100400002', 'Minyak Goreng Sawit Pouch 2L', 29500.00, 35000.00, 28, 15, NULL),
(4, '899100400003', 'Deterjen Bubuk Konsentrat 1kg', 16500.00, 22000.00, 8, 15, NULL),
(5, '899100500001', 'Susu UHT Full Cream 1 Liter', 14800.00, 19500.00, 25, 10, NULL),
(5, '899100500002', 'Yogurt Greek Stroberi Cup 120g', 7500.00, 11000.00, 14, 10, NULL);

-- data awal batch barang (fefo & peringatan mendekati kadaluarsa 3 hari)
INSERT INTO batch_barang (id_barang, nomor_batch, tanggal_masuk, tanggal_kadaluarsa, stok_awal, stok_tersedia, harga_beli_batch, status_batch) VALUES
(1, 'BATCH-AIR-2610A', '2026-10-15', '2027-10-15', 140, 140, 2200.00, 'Active'),
(2, 'BATCH-KOP-2610A', '2026-10-20', '2027-04-20', 65, 65, 5800.00, 'Active'),
(5, 'BATCH-SNK-2610A', '2026-10-18', '2027-02-18', 95, 95, 8200.00, 'Active'),
(9, 'BATCH-FRM-2610A', '2026-10-25', '2027-08-25', 18, 18, 115000.00, 'Active'),
(11, 'BATCH-MIE-2610A', '2026-10-10', '2027-03-10', 50, 12, 2400.00, 'Active'),
(14, 'BATCH-UHT-2611A', '2026-11-01', '2026-11-10', 10, 10, 14800.00, 'Near_Expiry'),
(14, 'BATCH-UHT-2611B', '2026-11-04', '2027-01-15', 15, 15, 14800.00, 'Active'),
(15, 'BATCH-YOG-2611A', '2026-11-01', '2026-11-09', 14, 14, 7500.00, 'Near_Expiry');

-- data awal pemasok distributor
INSERT INTO pemasok (kode_pemasok, nama_pemasok, kontak_person, no_telepon, alamat) VALUES
('SUP-001', 'PT Tirta Nusantara Distribusi', 'Hendra Kusuma', '0361-445566', 'Kawasan Industri Cargo Permai, Denpasar'),
('SUP-002', 'CV Pangan Makmur Sejahtera', 'Ni Luh Putu Ari', '0361-778899', 'Jl. Bypass Ngurah Rai No. 112, Badung'),
('SUP-003', 'PT Kosmetik & Husada Bali', 'Bagus Pratama', '0361-332211', 'Jl. Gatot Subroto Timur No. 45, Denpasar');

-- data awal promosi bundling (contoh: beli 2 kopi kaleng dari Rp 16.000 jadi Rp 14.000)
INSERT INTO promosi_bundling (kode_promo, nama_promo, id_barang, tipe_promo, min_kuantitas, harga_paket, potongan_rupiah, persen_diskon, tanggal_mulai, tanggal_akhir, status_aktif) VALUES
('PRM-KOPIPAIR', 'Paket Hemat 2 Kopi Kaleng Rp 14.000', 2, 'Bundle_Pair', 2, 14000.00, 2000.00, 0.00, '2026-11-01', '2026-12-31', 1),
('PRM-SNACK3', 'Potongan Rp 3.000 Pembelian 3 Keripik Kentang', 5, 'Min_Qty_Cut', 3, NULL, 3000.00, 0.00, '2026-11-01', '2026-12-31', 1),
('PRM-DAIRY10', 'Diskon 10% Susu UHT Near Expiry', 14, 'Percent_Discount', 1, NULL, 0.00, 10.00, '2026-11-01', '2026-11-30', 1);

-- data awal rekonsiliasi shift (contoh shift siang selisih Rp 20.000)
INSERT INTO rekonsiliasi_shift (id_kasir, shift, waktu_buka, waktu_tutup, modal_awal, penjualan_tunai_sistem, penjualan_non_tunai_sistem, kas_fisik_akhir, selisih_kas, catatan, status) VALUES
(2, 'Afternoon', '2026-11-07 14:00:00', '2026-11-07 22:00:00', 500000.00, 1020000.00, 350000.00, 1500000.00, -20000.00, 'Selisih kurang Rp 20.000 pada laci kasir shift siang', 'Closed'),
(1, 'Morning', '2026-11-08 07:00:00', NULL, 500000.00, 75000.00, 25000.00, NULL, NULL, 'Shift pagi sedang berjalan', 'Open');

-- data awal transaksi dan detail transaksi
INSERT INTO transaksi (no_struk, tanggal_transaksi, id_kasir, id_rekon, total_item, subtotal_awal, total_diskon, total_harga, metode_pembayaran, bayar_tunai, bayar_non_tunai, referensi_pembayaran, jumlah_bayar, kembalian, status_transaksi) VALUES
('RMS-20261108-0001', '2026-11-08 08:30:15', 1, 2, 5, 77000.00, 2000.00, 75000.00, 'Split', 50000.00, 25000.00, 'QRIS-DYN-882910', 75000.00, 0.00, 'Completed'),
('RMS-20261108-0002', '2026-11-08 09:15:40', 1, 2, 2, 25000.00, 0.00, 25000.00, 'Cash', 30000.00, 0.00, NULL, 30000.00, 5000.00, 'Completed');

INSERT INTO detail_transaksi (id_transaksi, id_barang, id_batch, nama_barang_snapshot, harga_beli_satuan, harga_satuan, jumlah, diskon_item, subtotal) VALUES
(1, 2, 2, 'Kopi Kaleng Latte Espresso 240ml', 5800.00, 8000.00, 2, 2000.00, 14000.00),
(1, 8, NULL, 'Sabun Mandi Cair Antibakteri 450ml', 17500.00, 24500.00, 2, 0.00, 49000.00),
(1, 1, 1, 'Air Mineral Ramstore 600ml', 2200.00, 3500.00, 1, 0.00, 3500.00),
(2, 6, NULL, 'Cokelat Batang Almond 65g', 9500.00, 13500.00, 1, 0.00, 13500.00),
(2, 5, 3, 'Keripik Kentang Original 100g', 8200.00, 11500.00, 1, 0.00, 11500.00);

-- data awal stok opname (contoh: 95 keripik di rak vs 100 di database, selisih 5 unit)
INSERT INTO stok_opname (kode_opname, id_admin, id_barang, stok_sistem, stok_fisik, selisih_unit, nominal_selisih, alasan_selisih, disesuaikan_ke_stok, tanggal_opname) VALUES
('OPN-20261107-001', 1, 5, 100, 95, -5, -41000.00, 'Penyusutan fisik 5 bungkus keripik kentang rusak kemasan di rak B2', 1, '2026-11-07 17:30:00');

-- data awal log audit jejak sistem
INSERT INTO log_audit (peran_pelaku, id_pelaku, username_pelaku, aksi, modul, id_entitas, data_lama, data_baru, ip_address, user_agent, waktu_kejadian) VALUES
('admin', 1, 'admin', 'UPDATE_PRICE', 'barang', '6', '{"harga_jual": 12000}', '{"harga_jual": 13500}', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64)', '2026-11-06 11:20:00'),
('admin', 1, 'admin', 'CREATE_OPNAME', 'stok_opname', 'OPN-20261107-001', '{"stok_sistem": 100}', '{"stok_fisik": 95, "selisih": -5}', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64)', '2026-11-07 17:30:00'),
('admin', 2, 'david', 'APPROVE_VOID_PIN', 'otorisasi_void', '9', '{"item": "Susu Formula Bayi Gold Tin 800g", "harga": 150000}', '{"status": "Void_Cart_Item"}', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64)', '2026-11-08 08:45:00');
