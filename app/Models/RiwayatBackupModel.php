<?php

namespace App\Models;

use CodeIgniter\Model;

// kelola riwayat file arsip cadangan database (.sql.gz)
class RiwayatBackupModel extends Model
{
    protected $table         = 'riwayat_backup';
    protected $primaryKey    = 'id_backup';
    protected $returnType    = 'array';
    protected $allowedFields = ['nama_file', 'ukuran_bytes', 'lokasi_simpan', 'tipe_pemicu', 'id_admin', 'status_backup', 'waktu_backup'];
}
