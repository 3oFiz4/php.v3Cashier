<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

// kelola rincian item per struk dan perhitungan analitik laba kotor
class DetailTransaksiModel extends Model
{
    protected $table         = 'detail_transaksi';
    protected $primaryKey    = 'id_detail';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'id_transaksi', 'id_barang', 'id_batch', 'nama_barang_snapshot',
        'harga_beli_satuan', 'harga_satuan', 'jumlah', 'diskon_item', 'subtotal'
    ];

    // hitung analitik laba kotor dan persentase margin per kategori serta produk
    public function getGrossProfitAnalytics(): array
    {
        $db = Database::connectPdo();

        $byCategory = $db->query(
            "SELECT j.nama_jenis AS kategori,
                    SUM(d.jumlah) AS unit_terjual,
                    SUM(d.subtotal) AS pendapatan_bersih,
                    SUM(d.harga_beli_satuan * d.jumlah) AS total_hpp,
                    SUM(d.subtotal - (d.harga_beli_satuan * d.jumlah)) AS laba_kotor,
                    ROUND(
                        CASE WHEN SUM(d.subtotal) > 0
                             THEN ((SUM(d.subtotal - (d.harga_beli_satuan * d.jumlah))) / SUM(d.subtotal)) * 100
                             ELSE 0 END,
                        2
                    ) AS margin_persen
             FROM detail_transaksi d
             JOIN transaksi t ON d.id_transaksi = t.id_transaksi
             JOIN barang b ON d.id_barang = b.id_barang
             JOIN jenis_barang j ON b.id_jenis = j.id_jenis
             WHERE t.status_transaksi = 'Completed'
             GROUP BY j.id_jenis, j.nama_jenis
             ORDER BY laba_kotor DESC"
        )->fetchAll();

        $byProduct = $db->query(
            "SELECT d.nama_barang_snapshot AS nama_barang,
                    SUM(d.jumlah) AS unit_terjual,
                    SUM(d.subtotal) AS pendapatan_bersih,
                    SUM(d.harga_beli_satuan * d.jumlah) AS total_hpp,
                    SUM(d.subtotal - (d.harga_beli_satuan * d.jumlah)) AS laba_kotor,
                    ROUND(
                        CASE WHEN SUM(d.subtotal) > 0
                             THEN ((SUM(d.subtotal - (d.harga_beli_satuan * d.jumlah))) / SUM(d.subtotal)) * 100
                             ELSE 0 END,
                        2
                    ) AS margin_persen
             FROM detail_transaksi d
             JOIN transaksi t ON d.id_transaksi = t.id_transaksi
             WHERE t.status_transaksi = 'Completed'
             GROUP BY d.id_barang, d.nama_barang_snapshot
             ORDER BY laba_kotor DESC"
        )->fetchAll();

        return [
            'by_category' => $byCategory,
            'by_product'  => $byProduct,
        ];
    }
}
