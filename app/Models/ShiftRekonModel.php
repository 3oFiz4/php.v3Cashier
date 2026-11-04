<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

// kelola pembukaan, penutupan, dan rekonsiliasi selisih kas shift kasir
class ShiftRekonModel extends Model
{
    protected $table         = 'rekonsiliasi_shift';
    protected $primaryKey    = 'id_rekon';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'id_kasir', 'shift', 'waktu_buka', 'waktu_tutup', 'modal_awal',
        'penjualan_tunai_sistem', 'penjualan_non_tunai_sistem',
        'kas_fisik_akhir', 'selisih_kas', 'catatan', 'status'
    ];

    // ambil sesi shift yang sedang aktif milik kasir
    public function getActiveShift(int $idKasir): ?array
    {
        $db   = Database::connectPdo();
        $stmt = $db->prepare("SELECT * FROM rekonsiliasi_shift WHERE id_kasir = ? AND status = 'Open' ORDER BY id_rekon DESC LIMIT 1");
        $stmt->execute([$idKasir]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ambil riwayat lengkap rekonsiliasi kas seluruh shift
    public function getAllShifts(): array
    {
        $db   = Database::connectPdo();
        $stmt = $db->query(
            "SELECT r.*, k.nama_lengkap AS nama_kasir, k.username
             FROM rekonsiliasi_shift r
             JOIN kasir k ON r.id_kasir = k.id_kasir
             ORDER BY r.waktu_buka DESC LIMIT 100"
        );
        return $stmt->fetchAll();
    }
}
