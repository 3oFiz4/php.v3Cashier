<?php

namespace App\Models;

use CodeIgniter\Model;

// kelola aturan promosi paket bundling dan diskon kuantitas produk
class PromosiBundlingModel extends Model
{
    protected $table         = 'promosi_bundling';
    protected $primaryKey    = 'id_promo';
    protected $returnType    = 'array';
    protected $allowedFields = ['kode_promo', 'nama_promo', 'id_barang', 'tipe_promo', 'min_kuantitas', 'harga_paket', 'potongan_rupiah', 'persen_diskon', 'tanggal_mulai', 'tanggal_akhir', 'status_aktif'];
}
