<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use App\Filters\AuthFilter;
use App\Filters\AuditFilter;

// daftarkan middleware filter autentikasi peran dan jejak audit
class Filters extends BaseConfig
{
    public array $aliases = [
        'auth'  => AuthFilter::class,
        'audit' => AuditFilter::class,
    ];

    public array $globals = [
        'before' => [],
        'after'  => ['audit'],
    ];

    public array $methods = [];

    public array $filters = [
        'auth:admin' => ['before' => ['admin/*', 'api/admin/*']],
        'auth:kasir' => ['before' => ['pos/*', 'api/pos/*']],
    ];
}
