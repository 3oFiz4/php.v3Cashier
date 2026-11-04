<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

// kelola klasifikasi kategori produk minimarket pada tabel jenis_barang
class JenisBarangModel extends Model
{
    protected $table         = 'jenis_barang';
    protected $primaryKey    = 'id_jenis';
    protected $returnType    = 'array';
    protected $allowedFields = ['nama_jenis', 'deskripsi', 'created_at', 'updated_at'];

    // ambil seluruh kategori beserta jumlah produk terkait
    public function getCategoriesWithCount(): array
    {
        $db   = Database::connectPdo();
        $stmt = $db->query(
            "SELECT j.*, COUNT(b.id_barang) AS product_count
             FROM jenis_barang j
             LEFT JOIN barang b ON j.id_jenis = b.id_jenis
             GROUP BY j.id_jenis
             ORDER BY j.nama_jenis ASC"
        );
        return $stmt->fetchAll();
    }
}
