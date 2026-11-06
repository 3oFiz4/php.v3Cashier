<?php

namespace Config;

// definisikan rute mvc aplikasi ramstore pos untuk admin dan kasir
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('AuthController');
$routes->setDefaultMethod('loginPage');
$routes->setTranslateURIDashes(false);
$routes->set404Override();

// rute halaman antarmuka pengguna (views)
$routes->get('/', 'KasirPosController::index', ['filter' => 'auth:kasir,admin']);
$routes->get('login', 'AuthController::loginPage');
$routes->get('pos', 'KasirPosController::index', ['filter' => 'auth:kasir,admin']);
$routes->get('admin', 'AdminDashboardController::index', ['filter' => 'auth:admin']);

// rute api autentikasi dan otorisasi pin supervisor
$routes->group('api/auth', static function ($routes) {
    $routes->post('login', 'AuthController::login');
    $routes->post('logout', 'AuthController::logout');
    $routes->get('check', 'AuthController::check');
    $routes->post('verify-supervisor', 'AuthController::verifySupervisorPin');
});

// rute api modul transaksi kasir (pos)
$routes->group('api/pos', ['filter' => 'auth:kasir,admin'], static function ($routes) {
    $routes->get('catalog', 'KasirPosController::catalog');
    $routes->get('barcode/(:segment)', 'KasirPosController::scanBarcode/$1');
    $routes->post('calculate-cart', 'KasirPosController::calculateCart');
    $routes->post('qris-generate', 'KasirPosController::generateDynamicQris');
    $routes->post('checkout', 'KasirPosController::checkout');
    $routes->get('history', 'KasirPosController::myTransactions');
    $routes->get('receipt/(:num)', 'KasirPosController::receipt/$1');

    // rute transaksi tertunda (parked / held carts)
    $routes->get('held', 'KasirPosController::listHeldCarts');
    $routes->post('held/park', 'KasirPosController::parkCart');
    $routes->post('held/resume/(:num)', 'KasirPosController::resumeCart/$1');
    $routes->post('held/discard/(:num)', 'KasirPosController::discardHeldCart/$1');

    // rute rekonsiliasi kas shift kasir
    $routes->get('shift/active', 'KasirPosController::activeShift');
    $routes->post('shift/open', 'KasirPosController::openShift');
    $routes->post('shift/close', 'KasirPosController::closeShift');
});

// rute api modul manajemen admin
$routes->group('api/admin', ['filter' => 'auth:admin'], static function ($routes) {
    $routes->get('dashboard', 'AdminDashboardController::summary');
    $routes->get('analytics/margin', 'AdminDashboardController::grossProfitAnalytics');
    $routes->get('alerts/low-stock', 'AdminDashboardController::lowStockAlerts');
    $routes->get('alerts/expiry', 'AdminDashboardController::expiryAlerts');

    $routes->get('products', 'MasterBarangController::listProducts');
    $routes->post('products/create', 'MasterBarangController::createProduct');
    $routes->post('products/update', 'MasterBarangController::updateProduct');
    $routes->post('products/delete', 'MasterBarangController::deleteProduct');

    $routes->get('categories', 'MasterBarangController::listCategories');
    $routes->post('categories/create', 'MasterBarangController::createCategory');
    $routes->post('categories/update', 'MasterBarangController::updateCategory');
    $routes->post('categories/delete', 'MasterBarangController::deleteCategory');

    $routes->get('batches', 'MasterBarangController::listBatches');
    $routes->post('batches/create', 'MasterBarangController::createBatch');

    $routes->get('promotions', 'MasterBarangController::listPromotions');
    $routes->post('promotions/create', 'MasterBarangController::createPromotion');
    $routes->post('promotions/toggle', 'MasterBarangController::togglePromotion');

    $routes->get('users', 'MasterUserController::listUsers');
    $routes->post('users/admin/save', 'MasterUserController::saveAdmin');
    $routes->post('users/admin/delete', 'MasterUserController::deleteAdmin');
    $routes->post('users/kasir/save', 'MasterUserController::saveKasir');
    $routes->post('users/kasir/delete', 'MasterUserController::deleteKasir');

    $routes->get('suppliers', 'OperasionalController::listSuppliers');
    $routes->post('suppliers/create', 'OperasionalController::createSupplier');
    $routes->get('purchase-orders', 'OperasionalController::listPurchaseOrders');
    $routes->post('purchase-orders/create', 'OperasionalController::createPurchaseOrder');

    $routes->get('opname', 'OperasionalController::listStockOpname');
    $routes->post('opname/create', 'OperasionalController::createStockOpname');

    $routes->get('transactions', 'OperasionalController::listAllTransactions');
    $routes->post('transactions/refund', 'OperasionalController::refundTransaction');
    $routes->get('shifts', 'OperasionalController::listShifts');

    $routes->get('audit-logs', 'OperasionalController::listAuditLogs');
    $routes->get('backups', 'OperasionalController::listBackups');
    $routes->post('backups/run', 'OperasionalController::triggerBackup');
});
