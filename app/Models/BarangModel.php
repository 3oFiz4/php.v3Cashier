<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

// kelola katalog produk minimarket, pencarian barcode, dan validasi stok
class BarangModel extends Model
{
    protected $table         = 'barang';
    protected $primaryKey    = 'id_barang';
    protected $returnType    = 'array';
    protected $allowedFields = ['id_jenis', 'barcode', 'nama_barang', 'harga_beli', 'harga_jual', 'stok', 'stok_minimum', 'gambar', 'created_at', 'updated_at'];

    // ambil daftar katalog produk dengan filter kategori dan kata kunci pencarian
    public function getCatalog(?string $categoryId = null, ?string $search = null): array
    {
        $db     = Database::connectPdo();
        $sql    = "SELECT b.*, j.nama_jenis AS category_name,
                          (SELECT MIN(tanggal_kadaluarsa) FROM batch_barang bb WHERE bb.id_barang = b.id_barang AND bb.stok_tersedia > 0) AS nearest_expiry
                   FROM barang b
                   JOIN jenis_barang j ON b.id_jenis = j.id_jenis
                   WHERE 1=1";
        $params = [];

        if (!empty($categoryId) && $categoryId !== 'all') {
            $sql .= " AND b.id_jenis = ?";
            $params[] = (int) $categoryId;
        }

        if (!empty($search)) {
            $sql .= " AND (b.nama_barang LIKE ? OR b.barcode LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $sql .= " ORDER BY b.nama_barang ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // cari satu barang secara instan berdasarkan kode barcode scanner
    public function findByBarcode(string $barcode): ?array
    {
        $db   = Database::connectPdo();
        $stmt = $db->prepare(
            "SELECT b.*, j.nama_jenis AS category_name
             FROM barang b
             JOIN jenis_barang j ON b.id_jenis = j.id_jenis
             WHERE b.barcode = ? LIMIT 1"
        );
        $stmt->execute([trim($barcode)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ambil daftar barang yang menyentuh atau berada di bawah ambang stok minimum
    public function getLowStockItems(): array
    {
        $db   = Database::connectPdo();
        $stmt = $db->query(
            "SELECT b.*, j.nama_jenis AS category_name
             FROM barang b
             JOIN jenis_barang j ON b.id_jenis = j.id_jenis
             WHERE b.stok <= b.stok_minimum
             ORDER BY b.stok ASC, b.nama_barang ASC"
        );
        return $stmt->fetchAll();
    }
}
