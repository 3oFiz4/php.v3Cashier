<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

// kelola pencatatan header struk transaksi penjualan dan laporan riwayat
class TransaksiModel extends Model
{
    protected $table         = 'transaksi';
    protected $primaryKey    = 'id_transaksi';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'no_struk', 'tanggal_transaksi', 'id_kasir', 'id_rekon', 'total_item',
        'subtotal_awal', 'total_diskon', 'total_harga', 'metode_pembayaran',
        'bayar_tunai', 'bayar_non_tunai', 'referensi_pembayaran',
        'jumlah_bayar', 'kembalian', 'status_transaksi'
    ];

    // ambil daftar transaksi beserta nama kasir dan rincian item
    public function getTransactions(?int $idKasir = null, int $limit = 100): array
    {
        $db     = Database::connectPdo();
        $sql    = "SELECT t.*, k.nama_lengkap AS nama_kasir, k.shift AS shift_kasir
                   FROM transaksi t
                   JOIN kasir k ON t.id_kasir = k.id_kasir";
        $params = [];

        if ($idKasir !== null && $idKasir > 0) {
            $sql .= " WHERE t.id_kasir = ?";
            $params[] = $idKasir;
        }

        $sql .= " ORDER BY t.tanggal_transaksi DESC LIMIT " . max(1, min(500, $limit));
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ambil satu transaksi lengkap dengan baris item untuk cetak struk
    public function getTransactionWithDetails(int $idTransaksi): ?array
    {
        $db   = Database::connectPdo();
        $stmt = $db->prepare(
            "SELECT t.*, k.nama_lengkap AS nama_kasir, k.shift AS shift_kasir
             FROM transaksi t
             JOIN kasir k ON t.id_kasir = k.id_kasir
             WHERE t.id_transaksi = ? LIMIT 1"
        );
        $stmt->execute([$idTransaksi]);
        $txn = $stmt->fetch();
        if (!$txn) {
            return null;
        }

        $stmtItems = $db->prepare("SELECT * FROM detail_transaksi WHERE id_transaksi = ? ORDER BY id_detail ASC");
        $stmtItems->execute([$idTransaksi]);
        $txn['items'] = $stmtItems->fetchAll();

        return $txn;
    }
}
