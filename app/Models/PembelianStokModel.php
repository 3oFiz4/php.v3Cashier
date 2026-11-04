<?php

namespace App\Models;

use CodeIgniter\Model;

// kelola faktur pembelian stok masuk (purchase order) dari pemasok
class PembelianStokModel extends Model
{
    protected $table         = 'pembelian_stok';
    protected $primaryKey    = 'id_pembelian';
    protected $returnType    = 'array';
    protected $allowedFields = ['no_faktur_masuk', 'id_pemasok', 'id_admin', 'tanggal_pembelian', 'total_unit_masuk', 'total_biaya', 'catatan'];
}
