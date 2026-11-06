<?php

namespace App\Controllers;

use App\Filters\AuthFilter;
use App\Models\AdminModel;
use App\Models\KasirModel;
use App\Libraries\AuditLogger;
use Config\Database;

// kelola operasi crud akun administrator dan kasir beserta shift kerja
class MasterUserController extends BaseController
{
    // ambil daftar seluruh akun admin dan kasir
    public function listUsers(): void
    {
        AuthFilter::enforce(['admin'], true);
        $adminModel = new AdminModel();
        $kasirModel = new KasirModel();

        $this->jsonResponse([
            'admins'   => $adminModel->getAllAdmins(),
            'cashiers' => $kasirModel->getAllKasir(),
        ]);
    }

    // tambah atau perbarui data akun admin beserta enkripsi password dan pin supervisor
    public function saveAdmin(): void
    {
        AuthFilter::enforce(['admin'], true);
        $in          = $this->getJsonInput();
        $idAdmin     = (int) ($in['id_admin'] ?? 0);
        $namaLengkap = trim((string) ($in['nama_lengkap'] ?? ''));
        $username    = trim((string) ($in['username'] ?? ''));
        $password    = (string) ($in['password'] ?? '');
        $pin         = (string) ($in['pin_supervisor'] ?? '123456');

        if ($namaLengkap === '' || $username === '') {
            $this->jsonResponse(['error' => 'nama lengkap dan username admin wajib diisi'], 400);
        }

        $db = Database::connectPdo();
        if ($idAdmin > 0) {
            if ($password !== '') {
                $stmt = $db->prepare("UPDATE admin SET nama_lengkap = ?, username = ?, password = ?, pin_supervisor = ?, updated_at = NOW() WHERE id_admin = ?");
                $stmt->execute([$namaLengkap, $username, password_hash($password, PASSWORD_BCRYPT), password_hash($pin, PASSWORD_BCRYPT), $idAdmin]);
            } else {
                $stmt = $db->prepare("UPDATE admin SET nama_lengkap = ?, username = ?, updated_at = NOW() WHERE id_admin = ?");
                $stmt->execute([$namaLengkap, $username, $idAdmin]);
            }
            AuditLogger::log('admin', 'UPDATE_ADMIN', (string) $idAdmin, null, ['username' => $username]);
            $this->jsonResponse(['success' => true, 'message' => 'akun admin berhasil diperbarui']);
        }

        $hashPass = password_hash($password ?: 'admin123', PASSWORD_BCRYPT);
        $hashPin  = password_hash($pin ?: '123456', PASSWORD_BCRYPT);
        $stmt     = $db->prepare("INSERT INTO admin (nama_lengkap, username, password, pin_supervisor, tanggal_registrasi, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$namaLengkap, $username, $hashPass, $hashPin]);

        AuditLogger::log('admin', 'CREATE_ADMIN', (string) $db->lastInsertId(), null, ['username' => $username]);
        $this->jsonResponse(['success' => true, 'message' => 'akun admin baru berhasil ditambahkan']);
    }

    // hapus akun admin
    public function deleteAdmin(): void
    {
        $user    = AuthFilter::enforce(['admin'], true);
        $in      = $this->getJsonInput();
        $idAdmin = (int) ($in['id_admin'] ?? 0);

        if ($idAdmin === $user['id']) {
            $this->jsonResponse(['error' => 'tidak dapat menghapus akun admin yang sedang digunakan'], 400);
        }

        $db   = Database::connectPdo();
        $stmt = $db->prepare("DELETE FROM admin WHERE id_admin = ?");
        $stmt->execute([$idAdmin]);

        AuditLogger::log('admin', 'DELETE_ADMIN', (string) $idAdmin);
        $this->jsonResponse(['success' => true, 'message' => 'akun admin dihapus']);
    }

    // tambah atau perbarui data akun kasir beserta enumerasi shift kerja
    public function saveKasir(): void
    {
        AuthFilter::enforce(['admin'], true);
        $in          = $this->getJsonInput();
        $idKasir     = (int) ($in['id_kasir'] ?? 0);
        $namaLengkap = trim((string) ($in['nama_lengkap'] ?? ''));
        $username    = trim((string) ($in['username'] ?? ''));
        $password    = (string) ($in['password'] ?? '');
        $noTelepon   = trim((string) ($in['no_telepon'] ?? ''));
        $shift       = (string) ($in['shift'] ?? 'Morning');

        if (!in_array($shift, ['Morning', 'Afternoon', 'Night'], true)) {
            $shift = 'Morning';
        }

        if ($namaLengkap === '' || $username === '') {
            $this->jsonResponse(['error' => 'nama lengkap dan username kasir wajib diisi'], 400);
        }

        $db = Database::connectPdo();
        if ($idKasir > 0) {
            if ($password !== '') {
                $stmt = $db->prepare("UPDATE kasir SET nama_lengkap = ?, username = ?, password = ?, no_telepon = ?, shift = ?, updated_at = NOW() WHERE id_kasir = ?");
                $stmt->execute([$namaLengkap, $username, password_hash($password, PASSWORD_BCRYPT), $noTelepon, $shift, $idKasir]);
            } else {
                $stmt = $db->prepare("UPDATE kasir SET nama_lengkap = ?, username = ?, no_telepon = ?, shift = ?, updated_at = NOW() WHERE id_kasir = ?");
                $stmt->execute([$namaLengkap, $username, $noTelepon, $shift, $idKasir]);
            }
            AuditLogger::log('kasir', 'UPDATE_KASIR', (string) $idKasir, null, ['username' => $username, 'shift' => $shift]);
            $this->jsonResponse(['success' => true, 'message' => 'data kasir berhasil diperbarui']);
        }

        $hashPass = password_hash($password ?: 'kasir123', PASSWORD_BCRYPT);
        $stmt     = $db->prepare(
            "INSERT INTO kasir (nama_lengkap, username, password, no_telepon, shift, status_aktif, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())"
        );
        $stmt->execute([$namaLengkap, $username, $hashPass, $noTelepon, $shift]);

        AuditLogger::log('kasir', 'CREATE_KASIR', (string) $db->lastInsertId(), null, ['username' => $username, 'shift' => $shift]);
        $this->jsonResponse(['success' => true, 'message' => 'akun kasir baru berhasil ditambahkan']);
    }

    // hapus atau nonaktifkan akun kasir
    public function deleteKasir(): void
    {
        AuthFilter::enforce(['admin'], true);
        $in      = $this->getJsonInput();
        $idKasir = (int) ($in['id_kasir'] ?? 0);

        $db   = Database::connectPdo();
        $stmt = $db->prepare("UPDATE kasir SET status_aktif = 0, updated_at = NOW() WHERE id_kasir = ?");
        $stmt->execute([$idKasir]);

        AuditLogger::log('kasir', 'DEACTIVATE_KASIR', (string) $idKasir);
        $this->jsonResponse(['success' => true, 'message' => 'akun kasir telah dinonaktifkan']);
    }
}
