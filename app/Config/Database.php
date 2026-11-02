<?php

namespace Config;

use CodeIgniter\Database\Config;
use PDO;
use PDOException;

// kelola koneksi database relasional ci4 dan pdo fallback
class Database extends Config
{
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;
    public string $defaultGroup = 'default';

    public array $default = [
        'DSN'          => '',
        'hostname'     => 'localhost',
        'username'     => 'root',
        'password'     => '',
        'database'     => 'ramstore_pos',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_unicode_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
    ];

    // bangun koneksi pdo murni dengan prepared statement aman
    public static function connectPdo(): PDO
    {
        static $pdo = null;
        if ($pdo === null) {
            $host = getenv('database.default.hostname') ?: 'localhost';
            $db   = getenv('database.default.database') ?: 'ramstore_pos';
            $user = getenv('database.default.username') ?: 'root';
            $pass = getenv('database.default.password') ?: '';
            $port = getenv('database.default.port') ?: '3306';

            try {
                $pdo = new PDO(
                    "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'koneksi database gagal: ' . $e->getMessage()]);
                exit();
            }
        }
        return $pdo;
    }
}
