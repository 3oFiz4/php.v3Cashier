<?php

namespace App\Models;

use CodeIgniter\Model;

// kelola penyimpanan keranjang belanja yang ditunda (parked / held transactions)
class TransaksiTertundaModel extends Model
{
    protected $table         = 'transaksi_tertunda';
    protected $primaryKey    = 'id_tunda';
    protected $returnType    = 'array';
    protected $allowedFields = ['kode_tunda', 'id_kasir', 'label_pelanggan', 'payload_keranjang', 'subtotal_sementara', 'waktu_tunda', 'status_tunda'];
}
