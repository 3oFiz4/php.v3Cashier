<?php

namespace App\Models;

use CodeIgniter\Model;

// kelola pencatatan hasil stok opname fisik dan laporan selisih penyusutan
class StokOpnameModel extends Model
{
    protected $table         = 'stok_opname';
    protected $primaryKey    = 'id_opname';
    protected $returnType    = 'array';
    protected $allowedFields = ['kode_opname', 'id_admin', 'id_barang', 'stok_sistem', 'stok_fisik', 'selisih_unit', 'nominal_selisih', 'alasan_selisih', 'disesuaikan_ke_stok', 'tanggal_opname'];
}
