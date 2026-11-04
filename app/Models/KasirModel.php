<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

// kelola data akun kasir dan jadwal shift kerja
class KasirModel extends Model
{
    protected $table         = 'kasir';
    protected $primaryKey    = 'id_kasir';
    protected $returnType    = 'array';
    protected $allowedFields = ['nama_lengkap', 'username', 'password', 'no_telepon', 'shift', 'status_aktif', 'created_at', 'updated_at'];

    // cari akun kasir aktif berdasarkan username
    public function findByUsername(string $username): ?array
    {
        $db   = Database::connectPdo();
        $stmt = $db->prepare("SELECT * FROM kasir WHERE username = ? AND status_aktif = 1 LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ambil daftar seluruh petugas kasir beserta shift kerja
    public function getAllKasir(): array
    {
        $db   = Database::connectPdo();
        $stmt = $db->query("SELECT id_kasir, nama_lengkap, username, no_telepon, shift, status_aktif, created_at FROM kasir ORDER BY id_kasir ASC");
        return $stmt->fetchAll();
    }
}
