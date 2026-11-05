<?php

namespace App\Controllers;

// sediakan utilitas dasar input json, respons json, dan render view mvc ci4
abstract class BaseController
{
    // kirim respons berformat json beserta kode status http
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ambil payload body json dari permintaan http post atau put
    protected function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return $_POST ?: [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : ($_POST ?: []);
    }

    // muat file tampilan (view) dari direktori app/Views
    protected function renderView(string $viewName, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = dirname(__DIR__) . '/Views/' . ltrim($viewName, '/') . '.php';
        if (!is_file($viewFile)) {
            http_response_code(404);
            echo "View tidak ditemukan: " . htmlspecialchars($viewName);
            exit();
        }
        require $viewFile;
        exit();
    }
}
