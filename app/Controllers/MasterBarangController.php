<?php

namespace App\Controllers;

use App\Filters\AuthFilter;
use App\Models\BarangModel;
use App\Models\JenisBarangModel;
use App\Models\BatchBarangModel;
use App\Libraries\AuditLogger;
use Config\Database;
use PDOException;

// kelola operasi crud master barang, kategori jenis_barang, batch fefo, dan promosi
class MasterBarangController extends BaseController
{
    // tampilkan daftar seluruh barang beserta kategori
    public function listProducts(): void
    {
        AuthFilter::enforce(['admin'], true);
        $barangModel = new BarangModel();
        $this->jsonResponse(['products' => $barangModel->getCatalog()]);
    }

    // tambahkan produk baru ke tabel barang dan catat ke log audit
    public function createProduct(): void
    {
        AuthFilter::enforce(['admin'], true);
        $in = $this->getJsonInput();

        $barcode     = trim((string) ($in['barcode'] ?? ''));
        $namaBarang  = trim((string) ($in['nama_barang'] ?? $in['name'] ?? ''));
        $idJenis     = (int) ($in['id_jenis'] ?? $in['category_id'] ?? 0);
        $hargaBeli   = (float) ($in['harga_beli'] ?? 0);
        $hargaJual   = (float) ($in['harga_jual'] ?? $in['price'] ?? 0);
        $stok        = (int) ($in['stok'] ?? $in['stock'] ?? 0);
        $stokMinimum = (int) ($in['stok_minimum'] ?? 15);
        $gambar      = trim((string) ($in['gambar'] ?? $in['image'] ?? ''));

        if ($barcode === '') {
            $barcode = '899' . date('ymd') . random_int(100, 999);
        }

        if ($namaBarang === '' || $idJenis <= 0 || $hargaJual < 0) {
            $this->jsonResponse(['error' => 'nama barang, kategori, dan harga jual wajib valid'], 400);
        }

        $db   = Database::connectPdo();
        $stmt = $db->prepare(
            "INSERT INTO barang (id_jenis, barcode, nama_barang, harga_beli, harga_jual, stok, stok_minimum, gambar, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([$idJenis, $barcode, $namaBarang, $hargaBeli, $hargaJual, $stok, $stokMinimum, $gambar ?: null]);
        $newId = (int) $db->lastInsertId();

        AuditLogger::log('barang', 'CREATE_PRODUCT', (string) $newId, null, [
            'barcode'     => $barcode,
            'nama_barang' => $namaBarang,
            'harga_beli'  => $hargaBeli,
            'harga_jual'  => $hargaJual,
            'stok'        => $stok,
        ]);

        $this->jsonResponse(['success' => true, 'id_barang' => $newId, 'message' => 'produk berhasil ditambahkan'], 201);
    }

    // perbarui data produk di tabel barang dan rekam perubahan harga atau stok ke log audit
    public function updateProduct(): void
    {
        AuthFilter::enforce(['admin'], true);
        $in       = $this->getJsonInput();
        $idBarang = (int) ($in['id_barang'] ?? $in['id'] ?? 0);

        if ($idBarang <= 0) {
            $this->jsonResponse(['error' => 'id barang tidak valid'], 400);
        }

        $db      = Database::connectPdo();
        $oldStmt = $db->prepare("SELECT * FROM barang WHERE id_barang = ?");
        $oldStmt->execute([$idBarang]);
        $oldData = $oldStmt->fetch();

        if (!$oldData) {
            $this->jsonResponse(['error' => 'produk tidak ditemukan'], 404);
        }

        $barcode     = trim((string) ($in['barcode'] ?? $oldData['barcode']));
        $namaBarang  = trim((string) ($in['nama_barang'] ?? $in['name'] ?? $oldData['nama_barang']));
        $idJenis     = (int) ($in['id_jenis'] ?? $in['category_id'] ?? $oldData['id_jenis']);
        $hargaBeli   = (float) ($in['harga_beli'] ?? $oldData['harga_beli']);
        $hargaJual   = (float) ($in['harga_jual'] ?? $in['price'] ?? $oldData['harga_jual']);
        $stok        = (int) ($in['stok'] ?? $in['stock'] ?? $oldData['stok']);
        $stokMinimum = (int) ($in['stok_minimum'] ?? $oldData['stok_minimum']);
        $gambar      = trim((string) ($in['gambar'] ?? $in['image'] ?? ($oldData['gambar'] ?? '')));

        $stmt = $db->prepare(
            "UPDATE barang
             SET id_jenis = ?, barcode = ?, nama_barang = ?, harga_beli = ?, harga_jual = ?,
                 stok = ?, stok_minimum = ?, gambar = ?, updated_at = NOW()
             WHERE id_barang = ?"
        );
        $stmt->execute([$idJenis, $barcode, $namaBarang, $hargaBeli, $hargaJual, $stok, $stokMinimum, $gambar ?: null, $idBarang]);

        AuditLogger::log('barang', 'UPDATE_PRODUCT', (string) $idBarang, [
            'nama_barang' => $oldData['nama_barang'],
            'harga_beli'  => $oldData['harga_beli'],
            'harga_jual'  => $oldData['harga_jual'],
            'stok'        => $oldData['stok'],
        ], [
            'nama_barang' => $namaBarang,
            'harga_beli'  => $hargaBeli,
            'harga_jual'  => $hargaJual,
            'stok'        => $stok,
        ]);

        $this->jsonResponse(['success' => true, 'message' => 'produk berhasil diperbarui']);
    }

    // hapus produk dari tabel barang jika belum memiliki riwayat transaksi
    public function deleteProduct(): void
    {
        AuthFilter::enforce(['admin'], true);
        $in       = $this->getJsonInput();
        $idBarang = (int) ($in['id_barang'] ?? $in['id'] ?? 0);

        $db      = Database::connectPdo();
        $oldStmt = $db->prepare("SELECT * FROM barang WHERE id_barang = ?");
        $oldStmt->execute([$idBarang]);
        $oldData = $oldStmt->fetch();

        try {
            $stmt = $db->prepare("DELETE FROM barang WHERE id_barang = ?");
            $stmt->execute([$idBarang]);
            AuditLogger::log('barang', 'DELETE_PRODUCT', (string) $idBarang, $oldData ?: null, null);
            $this->jsonResponse(['success' => true, 'message' => 'produk berhasil dihapus']);
        } catch (PDOException $e) {
            $this->jsonResponse(['error' => 'produk memiliki riwayat transaksi, ubah stok menjadi 0 sebagai gantinya'], 400);
        }
    }

    // tampilkan daftar seluruh kategori jenis_barang
    public function listCategories(): void
    {
        AuthFilter::enforce(['admin'], true);
        $jenisModel = new JenisBarangModel();
        $this->jsonResponse(['categories' => $jenisModel->getCategoriesWithCount()]);
    }

    // buat kategori jenis_barang baru
    public function createCategory(): void
    {
        AuthFilter::enforce(['admin'], true);
        $in        = $this->getJsonInput();
        $namaJenis = trim((string) ($in['nama_jenis'] ?? $in['name'] ?? ''));
        $deskripsi = trim((string) ($in['deskripsi'] ?? ''));

        if ($namaJenis === '') {
            $this->jsonResponse(['error' => 'nama kategori wajib diisi'], 400);
        }

        $db   = Database::connectPdo();
        $stmt = $db->prepare("INSERT INTO jenis_barang (nama_jenis, deskripsi, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->execute([$namaJenis, $deskripsi ?: null]);
        $newId = (int) $db->lastInsertId();

        AuditLogger::log('jenis_barang', 'CREATE_CATEGORY', (string) $newId, null, ['nama_jenis' => $namaJenis]);
        $this->jsonResponse(['success' => true, 'id_jenis' => $newId, 'message' => 'kategori berhasil dibuat'], 201);
    }

    // perbarui kategori jenis_barang
    public function updateCategory(): void
    {
        AuthFilter::enforce(['admin'], true);
        $in        = $this->getJsonInput();
        $idJenis   = (int) ($in['id_jenis'] ?? $in['id'] ?? 0);
        $namaJenis = trim((string) ($in['nama_jenis'] ?? $in['name'] ?? ''));
        $deskripsi = trim((string) ($in['deskripsi'] ?? ''));

        $db   = Database::connectPdo();
        $stmt = $db->prepare("UPDATE jenis_barang SET nama_jenis = ?, deskripsi = ?, updated_at = NOW() WHERE id_jenis = ?");
        $stmt->execute([$namaJenis, $deskripsi ?: null, $idJenis]);

        AuditLogger::log('jenis_barang', 'UPDATE_CATEGORY', (string) $idJenis, null, ['nama_jenis' => $namaJenis]);
        $this->jsonResponse(['success' => true, 'message' => 'kategori berhasil diperbarui']);
    }

    // hapus kategori jenis_barang yang kosong
    public function deleteCategory(): void
    {
        AuthFilter::enforce(['admin'], true);
        $in      = $this->getJsonInput();
        $idJenis = (int) ($in['id_jenis'] ?? $in['id'] ?? 0);

        $db = Database::connectPdo();
        try {
            $stmt = $db->prepare("DELETE FROM jenis_barang WHERE id_jenis = ?");
            $stmt->execute([$idJenis]);
            AuditLogger::log('jenis_barang', 'DELETE_CATEGORY', (string) $idJenis);
            $this->jsonResponse(['success' => true, 'message' => 'kategori berhasil dihapus']);
        } catch (PDOException $e) {
            $this->jsonResponse(['error' => 'kategori masih digunakan oleh produk aktif'], 400);
        }
    }

    // tampilkan daftar seluruh batch barang beserta status kadaluarsa fefo
    public function listBatches(): void
    {
        AuthFilter::enforce(['admin'], true);
        $batchModel = new BatchBarangModel();
        $this->jsonResponse(['batches' => $batchModel->getAllBatches(3)]);
    }

    // tambahkan batch kadaluarsa baru dan tambahkan stok ke barang utama
    public function createBatch(): void
    {
        AuthFilter::enforce(['admin'], true);
        $in         = $this->getJsonInput();
        $idBarang   = (int) ($in['id_barang'] ?? 0);
        $nomorBatch = trim((string) ($in['nomor_batch'] ?? ''));
        $tglExp     = trim((string) ($in['tanggal_kadaluarsa'] ?? ''));
        $qty        = (int) ($in['stok_awal'] ?? 0);
        $hargaBeli  = (float) ($in['harga_beli_batch'] ?? 0);

        if ($idBarang <= 0 || $nomorBatch === '' || $tglExp === '' || $qty <= 0) {
            $this->jsonResponse(['error' => 'data batch dan tanggal kadaluarsa wajib lengkap'], 400);
        }

        $db = Database::connectPdo();
        $db->beginTransaction();
        $stmt = $db->prepare(
            "INSERT INTO batch_barang (id_barang, nomor_batch, tanggal_masuk, tanggal_kadaluarsa, stok_awal, stok_tersedia, harga_beli_batch, status_batch, created_at)
             VALUES (?, ?, CURDATE(), ?, ?, ?, ?, 'Active', NOW())"
        );
        $stmt->execute([$idBarang, $nomorBatch, $tglExp, $qty, $qty, $hargaBeli]);

        $upd = $db->prepare("UPDATE barang SET stok = stok + ?, updated_at = NOW() WHERE id_barang = ?");
        $upd->execute([$qty, $idBarang]);
        $db->commit();

        AuditLogger::log('batch_barang', 'CREATE_BATCH', $nomorBatch, null, [
            'id_barang'          => $idBarang,
            'stok_awal'          => $qty,
            'tanggal_kadaluarsa' => $tglExp,
        ]);

        $this->jsonResponse(['success' => true, 'message' => 'batch baru berhasil ditambahkan ke stok fefo']);
    }

    // tampilkan daftar aturan promosi dan bundling harga
    public function listPromotions(): void
    {
        AuthFilter::enforce(['admin'], true);
        $db   = Database::connectPdo();
        $stmt = $db->query(
            "SELECT p.*, b.nama_barang, b.harga_jual
             FROM promosi_bundling p
             JOIN barang b ON p.id_barang = b.id_barang
             ORDER BY p.id_promo DESC"
        );
        $this->jsonResponse(['promotions' => $stmt->fetchAll()]);
    }

    // tambahkan aturan promosi bundling baru
    public function createPromotion(): void
    {
        AuthFilter::enforce(['admin'], true);
        $in          = $this->getJsonInput();
        $kodePromo   = trim((string) ($in['kode_promo'] ?? ('PRM-' . random_int(100, 999))));
        $namaPromo   = trim((string) ($in['nama_promo'] ?? ''));
        $idBarang    = (int) ($in['id_barang'] ?? 0);
        $tipePromo   = (string) ($in['tipe_promo'] ?? 'Bundle_Pair');
        $minQty      = max(1, (int) ($in['min_kuantitas'] ?? 2));
        $hargaPaket  = isset($in['harga_paket']) && $in['harga_paket'] !== '' ? (float) $in['harga_paket'] : null;
        $potonganRp  = (float) ($in['potongan_rupiah'] ?? 0);
        $persenDisk  = (float) ($in['persen_diskon'] ?? 0);
        $tglMulai    = (string) ($in['tanggal_mulai'] ?? date('Y-m-d'));
        $tglAkhir    = (string) ($in['tanggal_akhir'] ?? date('Y-m-d', strtotime('+30 days')));

        $db   = Database::connectPdo();
        $stmt = $db->prepare(
            "INSERT INTO promosi_bundling
             (kode_promo, nama_promo, id_barang, tipe_promo, min_kuantitas, harga_paket, potongan_rupiah, persen_diskon, tanggal_mulai, tanggal_akhir, status_aktif)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->execute([$kodePromo, $namaPromo, $idBarang, $tipePromo, $minQty, $hargaPaket, $potonganRp, $persenDisk, $tglMulai, $tglAkhir]);

        AuditLogger::log('promosi_bundling', 'CREATE_PROMO', $kodePromo, null, ['nama_promo' => $namaPromo, 'tipe' => $tipePromo]);
        $this->jsonResponse(['success' => true, 'message' => 'promosi bundling berhasil ditambahkan']);
    }

    // aktifkan atau nonaktifkan aturan promosi bundling
    public function togglePromotion(): void
    {
        AuthFilter::enforce(['admin'], true);
        $in      = $this->getJsonInput();
        $idPromo = (int) ($in['id_promo'] ?? 0);

        $db   = Database::connectPdo();
        $stmt = $db->prepare("UPDATE promosi_bundling SET status_aktif = IF(status_aktif = 1, 0, 1) WHERE id_promo = ?");
        $stmt->execute([$idPromo]);

        AuditLogger::log('promosi_bundling', 'TOGGLE_PROMO', (string) $idPromo);
        $this->jsonResponse(['success' => true, 'message' => 'status promosi diperbarui']);
    }
}
