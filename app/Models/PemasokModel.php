<?php

namespace App\Models;

use CodeIgniter\Model;

// kelola data master pemasok distributor barang minimarket
class PemasokModel extends Model
{
    protected $table         = 'pemasok';
    protected $primaryKey    = 'id_pemasok';
    protected $returnType    = 'array';
    protected $allowedFields = ['kode_pemasok', 'nama_pemasok', 'kontak_person', 'no_telepon', 'alamat', 'created_at'];
}
