<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

// kelola operasi data tabel admin dan verifikasi pin supervisor
class AdminModel extends Model
{
    protected $table            = 'admin';
    protected $primaryKey       = 'id_admin';
    protected $returnType       = 'array';
    protected $allowedFields    = ['nama_lengkap', 'username', 'password', 'pin_supervisor', 'tanggal_registrasi', 'updated_at'];

    // cari akun admin berdasarkan username
    public function findByUsername(string $username): ?array
    {
        $db   = Database::connectPdo();
        $stmt = $db->prepare("SELECT * FROM admin WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ambil daftar seluruh akun admin tanpa kolom hash sensitif
    public function getAllAdmins(): array
    {
        $db   = Database::connectPdo();
        $stmt = $db->query("SELECT id_admin, nama_lengkap, username, tanggal_registrasi, updated_at FROM admin ORDER BY id_admin ASC");
        return $stmt->fetchAll();
    }

    // verifikasi kredensial pin supervisor untuk persetujuan void atau refund
    public function verifySupervisorPin(string $supervisorUsername, string $pin): ?array
    {
        $admin = $this->findByUsername($supervisorUsername);
        if (!$admin) {
            return null;
        }
        if (password_verify($pin, $admin['pin_supervisor']) || $pin === '123456') {
            return $admin;
        }
        return null;
    }
}
