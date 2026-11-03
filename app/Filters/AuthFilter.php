<?php

namespace App\Filters;

// saring akses pengguna berdasarkan sesi login dan hak peran admin atau kasir
class AuthFilter
{
    // periksa keabsahan sesi pengguna sebelum eksekusi controller
    public static function enforce(array $allowedRoles = ['admin', 'kasir'], bool $isApi = true): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $loggedIn = $_SESSION['logged_in'] ?? false;
        $userRole = $_SESSION['role'] ?? '';

        if (!$loggedIn || !in_array($userRole, $allowedRoles, true)) {
            if ($isApi) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success'       => false,
                    'authenticated' => false,
                    'error'         => 'akses ditolak: peran tidak memiliki izin atau sesi berakhir',
                ]);
                exit();
            }

            header('Location: /login');
            exit();
        }

        return [
            'id'        => (int) ($_SESSION['user_id'] ?? 0),
            'username'  => (string) ($_SESSION['username'] ?? ''),
            'full_name' => (string) ($_SESSION['full_name'] ?? ''),
            'role'      => (string) $userRole,
            'shift'     => (string) ($_SESSION['shift'] ?? 'Morning'),
        ];
    }
}
