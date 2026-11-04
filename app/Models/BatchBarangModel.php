<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;
use App\Libraries\FefoAllocator;

// kelola nomor batch dan peringatan tanggal kadaluarsa produk (fefo)
class BatchBarangModel extends Model
{
    protected $table         = 'batch_barang';
    protected $primaryKey    = 'id_batch';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'id_barang', 'nomor_batch', 'tanggal_masuk', 'tanggal_kadaluarsa',
        'stok_awal', 'stok_tersedia', 'harga_beli_batch', 'status_batch', 'created_at'
    ];

    // ambil seluruh batch aktif beserta sisa hari menuju tanggal kadaluarsa
    public function getAllBatches(int $warningDays = 3): array
    {
        $db = Database::connectPdo();
        FefoAllocator::refreshExpiryStatuses($db, $warningDays);

        $stmt = $db->query(
            "SELECT bb.*, b.nama_barang, b.barcode,
                    DATEDIFF(bb.tanggal_kadaluarsa, CURDATE()) AS sisa_hari
             FROM batch_barang bb
             JOIN barang b ON bb.id_barang = b.id_barang
             ORDER BY bb.tanggal_kadaluarsa ASC"
        );
        return $stmt->fetchAll();
    }

    // ambil daftar batch yang mendekati kadaluarsa (<= 3 hari) atau sudah kadaluarsa
    public function getExpiryAlerts(int $warningDays = 3): array
    {
        $db = Database::connectPdo();
        FefoAllocator::refreshExpiryStatuses($db, $warningDays);

        $stmt = $db->prepare(
            "SELECT bb.*, b.nama_barang, b.barcode,
                    DATEDIFF(bb.tanggal_kadaluarsa, CURDATE()) AS sisa_hari
             FROM batch_barang bb
             JOIN barang b ON bb.id_barang = b.id_barang
             WHERE bb.stok_tersedia > 0
               AND bb.tanggal_kadaluarsa <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY bb.tanggal_kadaluarsa ASC"
        );
        $stmt->execute([$warningDays]);
        return $stmt->fetchAll();
    }
}
