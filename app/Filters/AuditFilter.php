<?php

namespace App\Filters;

use App\Libraries\AuditLogger;

// catat aktivitas permintaan mutasi secara otomatis ke tabel log_audit
class AuditFilter
{
    // rekam jejak aksi post put delete setelah pemrosesan selesai
    public static function recordRequest(string $modul, string $aksi, ?string $idEntitas = null, ?array $oldData = null, ?array $newData = null): void
    {
        AuditLogger::log($modul, $aksi, $idEntitas, $oldData, $newData);
    }
}
