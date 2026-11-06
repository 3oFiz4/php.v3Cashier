<?php

// inisialisasi front controller codeigniter 4 dan dispatcher rute mvc ramstore pos
declare(strict_types=1);

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
define('ROOTPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'app' . DIRECTORY_SEPARATOR);
define('WRITEPATH', ROOTPATH . 'writable' . DIRECTORY_SEPARATOR);

date_default_timezone_set('Asia/Makassar');

// muat autoloader psr-4 untuk namespace App dan Config
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'App\\'    => APPPATH,
        'Config\\' => APPPATH . 'Config' . DIRECTORY_SEPARATOR,
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $len);
        $file          = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

// sediakan kelas dasar kompatibilitas codeigniter 4 jika vendor belum diinstal
if (!class_exists('CodeIgniter\Config\BaseConfig')) {
    eval('namespace CodeIgniter\Config; class BaseConfig {}');
}
if (!class_exists('CodeIgniter\Database\Config')) {
    eval('namespace CodeIgniter\Database; class Config extends \CodeIgniter\Config\BaseConfig {}');
}
if (!class_exists('CodeIgniter\Model')) {
    eval('namespace CodeIgniter; class Model {}');
}

// baca variabel konfigurasi dari file .env
$envFile = ROOTPATH . '.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $trimmed, 2));
        $v       = trim($v, "'\"");
        putenv("{$k}={$v}");
    }
}

// petakan permintaan uri http ke controller dan method mvc yang sesuai
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri    = '/' . trim($uri, '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$routesMap = [
    'GET:/'                             => [\App\Controllers\KasirPosController::class, 'index'],
    'GET:/login'                        => [\App\Controllers\AuthController::class, 'loginPage'],
    'GET:/pos'                          => [\App\Controllers\KasirPosController::class, 'index'],
    'GET:/admin'                        => [\App\Controllers\AdminDashboardController::class, 'index'],

    'POST:/api/auth/login'              => [\App\Controllers\AuthController::class, 'login'],
    'POST:/api/auth/logout'             => [\App\Controllers\AuthController::class, 'logout'],
    'GET:/api/auth/check'               => [\App\Controllers\AuthController::class, 'check'],
    'POST:/api/auth/verify-supervisor'  => [\App\Controllers\AuthController::class, 'verifySupervisorPin'],

    'GET:/api/pos/catalog'              => [\App\Controllers\KasirPosController::class, 'catalog'],
    'POST:/api/pos/calculate-cart'      => [\App\Controllers\KasirPosController::class, 'calculateCart'],
    'POST:/api/pos/qris-generate'       => [\App\Controllers\KasirPosController::class, 'generateDynamicQris'],
    'POST:/api/pos/checkout'            => [\App\Controllers\KasirPosController::class, 'checkout'],
    'GET:/api/pos/history'              => [\App\Controllers\KasirPosController::class, 'myTransactions'],
    'GET:/api/pos/held'                 => [\App\Controllers\KasirPosController::class, 'listHeldCarts'],
    'POST:/api/pos/held/park'           => [\App\Controllers\KasirPosController::class, 'parkCart'],
    'GET:/api/pos/shift/active'         => [\App\Controllers\KasirPosController::class, 'activeShift'],
    'POST:/api/pos/shift/open'          => [\App\Controllers\KasirPosController::class, 'openShift'],
    'POST:/api/pos/shift/close'         => [\App\Controllers\KasirPosController::class, 'closeShift'],

    'GET:/api/admin/dashboard'          => [\App\Controllers\AdminDashboardController::class, 'summary'],
    'GET:/api/admin/analytics/margin'   => [\App\Controllers\AdminDashboardController::class, 'grossProfitAnalytics'],
    'GET:/api/admin/alerts/low-stock'   => [\App\Controllers\AdminDashboardController::class, 'lowStockAlerts'],
    'GET:/api/admin/alerts/expiry'      => [\App\Controllers\AdminDashboardController::class, 'expiryAlerts'],

    'GET:/api/admin/products'           => [\App\Controllers\MasterBarangController::class, 'listProducts'],
    'POST:/api/admin/products/create'   => [\App\Controllers\MasterBarangController::class, 'createProduct'],
    'POST:/api/admin/products/update'   => [\App\Controllers\MasterBarangController::class, 'updateProduct'],
    'POST:/api/admin/products/delete'   => [\App\Controllers\MasterBarangController::class, 'deleteProduct'],

    'GET:/api/admin/categories'         => [\App\Controllers\MasterBarangController::class, 'listCategories'],
    'POST:/api/admin/categories/create' => [\App\Controllers\MasterBarangController::class, 'createCategory'],
    'POST:/api/admin/categories/update' => [\App\Controllers\MasterBarangController::class, 'updateCategory'],
    'POST:/api/admin/categories/delete' => [\App\Controllers\MasterBarangController::class, 'deleteCategory'],

    'GET:/api/admin/batches'            => [\App\Controllers\MasterBarangController::class, 'listBatches'],
    'POST:/api/admin/batches/create'    => [\App\Controllers\MasterBarangController::class, 'createBatch'],

    'GET:/api/admin/promotions'         => [\App\Controllers\MasterBarangController::class, 'listPromotions'],
    'POST:/api/admin/promotions/create' => [\App\Controllers\MasterBarangController::class, 'createPromotion'],
    'POST:/api/admin/promotions/toggle' => [\App\Controllers\MasterBarangController::class, 'togglePromotion'],

    'GET:/api/admin/users'              => [\App\Controllers\MasterUserController::class, 'listUsers'],
    'POST:/api/admin/users/admin/save'  => [\App\Controllers\MasterUserController::class, 'saveAdmin'],
    'POST:/api/admin/users/admin/delete'=> [\App\Controllers\MasterUserController::class, 'deleteAdmin'],
    'POST:/api/admin/users/kasir/save'  => [\App\Controllers\MasterUserController::class, 'saveKasir'],
    'POST:/api/admin/users/kasir/delete'=> [\App\Controllers\MasterUserController::class, 'deleteKasir'],

    'GET:/api/admin/suppliers'          => [\App\Controllers\OperasionalController::class, 'listSuppliers'],
    'POST:/api/admin/suppliers/create'  => [\App\Controllers\OperasionalController::class, 'createSupplier'],
    'GET:/api/admin/purchase-orders'    => [\App\Controllers\OperasionalController::class, 'listPurchaseOrders'],
    'POST:/api/admin/purchase-orders/create' => [\App\Controllers\OperasionalController::class, 'createPurchaseOrder'],

    'GET:/api/admin/opname'             => [\App\Controllers\OperasionalController::class, 'listStockOpname'],
    'POST:/api/admin/opname/create'     => [\App\Controllers\OperasionalController::class, 'createStockOpname'],

    'GET:/api/admin/transactions'       => [\App\Controllers\OperasionalController::class, 'listAllTransactions'],
    'POST:/api/admin/transactions/refund' => [\App\Controllers\OperasionalController::class, 'refundTransaction'],
    'GET:/api/admin/shifts'             => [\App\Controllers\OperasionalController::class, 'listShifts'],
    'GET:/api/admin/audit-logs'         => [\App\Controllers\OperasionalController::class, 'listAuditLogs'],
    'GET:/api/admin/backups'            => [\App\Controllers\OperasionalController::class, 'listBackups'],
    'POST:/api/admin/backups/run'       => [\App\Controllers\OperasionalController::class, 'triggerBackup'],
];

$routeKey = "{$method}:{$uri}";
if (isset($routesMap[$routeKey])) {
    [$controllerClass, $action] = $routesMap[$routeKey];
    $controller = new $controllerClass();
    $controller->$action();
    exit();
}

if ($method === 'GET' && preg_match('#^/api/pos/barcode/([^/]+)$#', $uri, $m)) {
    (new \App\Controllers\KasirPosController())->scanBarcode(urldecode($m[1]));
    exit();
}
if ($method === 'GET' && preg_match('#^/api/pos/receipt/(\d+)$#', $uri, $m)) {
    (new \App\Controllers\KasirPosController())->receipt((int) $m[1]);
    exit();
}
if ($method === 'POST' && preg_match('#^/api/pos/held/resume/(\d+)$#', $uri, $m)) {
    (new \App\Controllers\KasirPosController())->resumeCart((int) $m[1]);
    exit();
}
if ($method === 'POST' && preg_match('#^/api/pos/held/discard/(\d+)$#', $uri, $m)) {
    (new \App\Controllers\KasirPosController())->discardHeldCart((int) $m[1]);
    exit();
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'rute endpoint tidak ditemukan: ' . $uri]);
