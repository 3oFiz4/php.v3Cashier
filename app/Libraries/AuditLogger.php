<?php

namespace App\Libraries;

use Config\Database;
use Throwable;

// rekam jejak audit imutabel atas setiap perubahan data kritis sistem
class AuditLogger
{
    // simpan entri aktivitas pengguna beserta alamat ip dan perubahan nilai json
    public static function log(
        string $modul,
        string $aksi,
        ?string $idEntitas = null,
        ?array $dataLama = null,
        ?array $dataBaru = null
    ): void {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $peran    = $_SESSION['role'] ?? 'system';
            $idPelaku = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
            $username = $_SESSION['username'] ?? 'system';
            $ip       = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $agent    = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/RamstorePOS', 0, 250);

            if (!in_array($peran, ['admin', 'kasir', 'system'], true)) {
                $peran = 'system';
            }

            $db   = Database::connectPdo();
            $stmt = $db->prepare(
                "INSERT INTO log_audit (peran_pelaku, id_pelaku, username_pelaku, aksi, modul, id_entitas, data_lama, data_baru, ip_address, user_agent, waktu_kejadian)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );

            $stmt->execute([
                $peran,
                $idPelaku,
                $username,
                strtoupper($aksi),
                strtolower($modul),
                $idEntitas,
                $dataLama !== null ? json_encode($dataLama, JSON_UNESCAPED_UNICODE) : null,
                $dataBaru !== null ? json_encode($dataBaru, JSON_UNESCAPED_UNICODE) : null,
                $ip,
                $agent,
            ]);
        } catch (Throwable $e) {
            // abaikan kegagalan penulisan log sekunder agar transaksi utama tidak terputus
        }
    }
}
