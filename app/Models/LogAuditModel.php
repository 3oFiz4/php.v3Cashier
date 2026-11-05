<?php

namespace App\Models;

use CodeIgniter\Model;

// kelola pembacaan log audit aktivitas pengguna dan perubahan data sistem
class LogAuditModel extends Model
{
    protected $table         = 'log_audit';
    protected $primaryKey    = 'id_log';
    protected $returnType    = 'array';
    protected $allowedFields = ['peran_pelaku', 'id_pelaku', 'username_pelaku', 'aksi', 'modul', 'id_entitas', 'data_lama', 'data_baru', 'ip_address', 'user_agent', 'waktu_kejadian'];
}
