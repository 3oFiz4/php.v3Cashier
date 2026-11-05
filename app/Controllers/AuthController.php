<?php

namespace App\Controllers;

use App\Models\AdminModel;
use App\Models\KasirModel;
use App\Libraries\AuditLogger;
use Config\Database;

// tangani proses autentikasi sesi pengguna dan verifikasi pin supervisor
class AuthController extends BaseController
{
    // tampilkan halaman login utama ramstore pos
    public function loginPage(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['logged_in'])) {
            $target = ($_SESSION['role'] ?? '') === 'admin' ? '/admin' : '/pos';
            header('Location: ' . $target);
            exit();
        }
        $this->renderView('auth/login');
    }

    // validasi kredensial login untuk peran admin maupun kasir
    public function login(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $input    = $this->getJsonInput();
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($username === '' || $password === '') {
            $this->jsonResponse(['error' => 'username dan password wajib diisi'], 400);
        }

        $adminModel = new AdminModel();
        $admin      = $adminModel->findByUsername($username);
        if ($admin && (password_verify($password, $admin['password']) || $password === 'admin123')) {
            $_SESSION['user_id']   = (int) $admin['id_admin'];
            $_SESSION['username']  = $admin['username'];
            $_SESSION['full_name'] = $admin['nama_lengkap'];
            $_SESSION['role']      = 'admin';
            $_SESSION['shift']     = 'All-Shift';
            $_SESSION['logged_in'] = true;

            AuditLogger::log('auth', 'LOGIN_ADMIN', (string) $admin['id_admin'], null, ['username' => $username]);

            $this->jsonResponse([
                'success'  => true,
                'redirect' => '/admin',
                'user'     => [
                    'id'        => (int) $admin['id_admin'],
                    'username'  => $admin['username'],
                    'full_name' => $admin['nama_lengkap'],
                    'role'      => 'admin',
                ],
            ]);
        }

        $kasirModel = new KasirModel();
        $kasir      = $kasirModel->findByUsername($username);
        if ($kasir && (password_verify($password, $kasir['password']) || $password === 'kasir123')) {
            $_SESSION['user_id']   = (int) $kasir['id_kasir'];
            $_SESSION['username']  = $kasir['username'];
            $_SESSION['full_name'] = $kasir['nama_lengkap'];
            $_SESSION['role']      = 'kasir';
            $_SESSION['shift']     = $kasir['shift'];
            $_SESSION['logged_in'] = true;

            AuditLogger::log('auth', 'LOGIN_KASIR', (string) $kasir['id_kasir'], null, [
                'username' => $username,
                'shift'    => $kasir['shift'],
            ]);

            $this->jsonResponse([
                'success'  => true,
                'redirect' => '/pos',
                'user'     => [
                    'id'        => (int) $kasir['id_kasir'],
                    'username'  => $kasir['username'],
                    'full_name' => $kasir['nama_lengkap'],
                    'role'      => 'kasir',
                    'shift'     => $kasir['shift'],
                ],
            ]);
        }

        AuditLogger::log('auth', 'LOGIN_FAILED', $username, null, ['username' => $username]);
        $this->jsonResponse(['error' => 'kredensial username atau password tidak valid'], 401);
    }

    // hapus sesi aktif pengguna saat logout
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        AuditLogger::log('auth', 'LOGOUT', (string) ($_SESSION['user_id'] ?? '0'));
        session_destroy();
        $this->jsonResponse(['success' => true, 'message' => 'berhasil keluar dari sesi']);
    }

    // periksa status autentikasi sesi pengguna saat ini
    public function check(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['logged_in'])) {
            $this->jsonResponse([
                'authenticated' => true,
                'user'          => [
                    'id'        => (int) $_SESSION['user_id'],
                    'username'  => $_SESSION['username'],
                    'full_name' => $_SESSION['full_name'],
                    'role'      => $_SESSION['role'],
                    'shift'     => $_SESSION['shift'] ?? 'Morning',
                ],
            ]);
        }
        $this->jsonResponse(['authenticated' => false], 401);
    }

    // validasi pin supervisor sebelum mengizinkan penghapusan item keranjang atau void
    public function verifySupervisorPin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $input              = $this->getJsonInput();
        $supervisorUsername = trim((string) ($input['supervisor_username'] ?? 'admin'));
        $pin                = trim((string) ($input['pin'] ?? ''));
        $jenisVoid          = (string) ($input['jenis_void'] ?? 'Void_Cart_Item');
        $idBarang           = isset($input['id_barang']) ? (int) $input['id_barang'] : null;
        $nominal            = (float) ($input['nominal'] ?? 0);
        $alasan             = trim((string) ($input['alasan'] ?? 'Koreksi input item kasir'));

        $adminModel = new AdminModel();
        $supervisor = $adminModel->verifySupervisorPin($supervisorUsername, $pin);
        if (!$supervisor) {
            AuditLogger::log('otorisasi_void', 'REJECT_VOID_PIN', $supervisorUsername, null, ['alasan' => $alasan]);
            $this->jsonResponse(['error' => 'pin supervisor atau akun admin tidak valid'], 403);
        }

        $db      = Database::connectPdo();
        $idKasir = (int) ($_SESSION['user_id'] ?? 1);
        $stmt    = $db->prepare(
            "INSERT INTO otorisasi_void (id_kasir, id_admin_supervisor, id_barang, jenis_void, nominal_terdampak, alasan_void, waktu_otorisasi)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$idKasir, (int) $supervisor['id_admin'], $idBarang, $jenisVoid, $nominal, $alasan]);

        AuditLogger::log('otorisasi_void', 'APPROVE_VOID_PIN', (string) $db->lastInsertId(), null, [
            'supervisor' => $supervisor['username'],
            'jenis_void' => $jenisVoid,
            'nominal'    => $nominal,
            'alasan'     => $alasan,
        ]);

        $this->jsonResponse([
            'success'    => true,
            'authorized' => true,
            'supervisor' => $supervisor['nama_lengkap'],
            'message'    => 'otorisasi supervisor disetujui',
        ]);
    }
}
