<?php
/**
 * Dashboard API
 * Summary statistics for admin
 */

session_start();
require_once __DIR__ . '/db.php';

// Check admin auth
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? 'summary';

match($action) {
    'summary' => getSummary(),
    'recent' => getRecentTransactions(),
    'bestsellers' => getBestsellers(),
    'inventory' => getInventory(),
    default => jsonResponse(['error' => 'Invalid action'], 400)
};

function getSummary(): void {
    $db = getDB();
    $today = date('Y-m-d');

    // Today's revenue
    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as today_revenue, COUNT(*) as today_txns 
                          FROM transactions WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $todayData = $stmt->fetch();

    // Total revenue
    $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total_revenue, 
                        COUNT(*) as total_transactions,
                        COALESCE(SUM(total_items), 0) as total_items_sold 
                        FROM transactions");
    $totals = $stmt->fetch();

    // Products count
    $stmt = $db->query("SELECT COUNT(*) as total FROM products");
    $totalProducts = $stmt->fetch()['total'];

    // Low stock (<=5)
    $stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE stock > 0 AND stock <= 5");
    $lowStock = $stmt->fetch()['total'];

    // Out of stock
    $stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE stock = 0");
    $outOfStock = $stmt->fetch()['total'];

    jsonResponse([
        'summary' => [
            'today_revenue' => (float)$todayData['today_revenue'],
            'today_transactions' => (int)$todayData['today_txns'],
            'total_revenue' => (float)$totals['total_revenue'],
            'total_transactions' => (int)$totals['total_transactions'],
            'total_items_sold' => (int)$totals['total_items_sold'],
            'total_products' => (int)$totalProducts,
            'low_stock' => (int)$lowStock,
            'out_of_stock' => (int)$outOfStock
        ]
    ]);
}

function getRecentTransactions(): void {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM transactions ORDER BY created_at DESC LIMIT 10");
    jsonResponse(['transactions' => $stmt->fetchAll()]);
}

function getBestsellers(): void {
    $db = getDB();
    $stmt = $db->query("SELECT product_name, SUM(quantity) as total_sold, SUM(subtotal) as total_revenue 
                        FROM transaction_items 
                        GROUP BY product_name 
                        ORDER BY total_sold DESC LIMIT 10");
    jsonResponse(['bestsellers' => $stmt->fetchAll()]);
}

function getInventory(): void {
    $db = getDB();
    $stmt = $db->query("SELECT p.*, c.name as category_name FROM products p 
                        JOIN categories c ON p.category_id = c.id 
                        ORDER BY p.stock ASC, p.name ASC");
    jsonResponse(['inventory' => $stmt->fetchAll()]);
}
?>
