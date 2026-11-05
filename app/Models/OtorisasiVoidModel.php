<?php

namespace App\Models;

use CodeIgniter\Model;

// kelola pencatatan otorisasi pin supervisor untuk pembatalan item dan refund
class OtorisasiVoidModel extends Model
{
    protected $table         = 'otorisasi_void';
    protected $primaryKey    = 'id_void';
    protected $returnType    = 'array';
    protected $allowedFields = ['id_kasir', 'id_admin_supervisor', 'id_transaksi', 'id_barang', 'jenis_void', 'nominal_terdampak', 'alasan_void', 'waktu_otorisasi'];
}
