<?php
/**
 * Products API
 * CRUD operations for products
 */

session_start();
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

match($method) {
    'GET' => match($action) {
        'list' => listProducts(),
        'get' => getProduct(),
        'search' => searchProducts(),
        default => jsonResponse(['error' => 'Invalid action'], 400)
    },
    'POST' => match($action) {
        'create' => createProduct(),
        'update' => updateProduct(),
        'delete' => deleteProduct(),
        default => jsonResponse(['error' => 'Invalid action'], 400)
    },
    default => jsonResponse(['error' => 'Method not allowed'], 405)
};

function listProducts(): void {
    $db = getDB();
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';

    $sql = "SELECT p.*, c.name as category_name FROM products p 
            JOIN categories c ON p.category_id = c.id WHERE 1=1";
    $params = [];

    if (!empty($category) && $category !== 'all') {
        $sql .= " AND p.category_id = ?";
        $params[] = (int)$category;
    }

    if (!empty($search)) {
        $sql .= " AND p.name LIKE ?";
        $params[] = "%{$search}%";
    }

    $sql .= " ORDER BY p.name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    jsonResponse(['products' => $products]);
}

function getProduct(): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'Invalid ID'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p 
                          JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) jsonResponse(['error' => 'Product not found'], 404);
    jsonResponse(['product' => $product]);
}

function searchProducts(): void {
    $query = $_GET['q'] ?? '';
    $db = getDB();
    $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p 
                          JOIN categories c ON p.category_id = c.id 
                          WHERE p.name LIKE ? ORDER BY p.name ASC LIMIT 20");
    $stmt->execute(["%{$query}%"]);
    jsonResponse(['products' => $stmt->fetchAll()]);
}

function requireAdmin(): void {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
}

function createProduct(): void {
    requireAdmin();
    $data = getJsonInput();

    $name = trim($data['name'] ?? '');
    $categoryId = (int)($data['category_id'] ?? 0);
    $price = (float)($data['price'] ?? 0);
    $stock = (int)($data['stock'] ?? 0);
    $image = trim($data['image'] ?? '');

    if (empty($name) || $categoryId <= 0 || $price < 0) {
        jsonResponse(['error' => 'Name, category, and valid price are required'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO products (name, category_id, price, stock, image) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $categoryId, $price, $stock, $image ?: null]);

    jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId(), 'message' => 'Product created'], 201);
}

function updateProduct(): void {
    requireAdmin();
    $data = getJsonInput();
    $id = (int)($data['id'] ?? 0);

    if ($id <= 0) jsonResponse(['error' => 'Invalid ID'], 400);

    $name = trim($data['name'] ?? '');
    $categoryId = (int)($data['category_id'] ?? 0);
    $price = (float)($data['price'] ?? 0);
    $stock = (int)($data['stock'] ?? 0);
    $image = trim($data['image'] ?? '');

    if (empty($name) || $categoryId <= 0 || $price < 0) {
        jsonResponse(['error' => 'Name, category, and valid price are required'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("UPDATE products SET name=?, category_id=?, price=?, stock=?, image=?, updated_at=NOW() WHERE id=?");
    $stmt->execute([$name, $categoryId, $price, $stock, $image ?: null, $id]);

    jsonResponse(['success' => true, 'message' => 'Product updated']);
}

function deleteProduct(): void {
    requireAdmin();
    $data = getJsonInput();
    $id = (int)($data['id'] ?? 0);

    if ($id <= 0) jsonResponse(['error' => 'Invalid ID'], 400);

    $db = getDB();
    // Set NULL in transaction_items and NOT deleting history, because then the history might be used sometimes!
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    
    try {
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Product deleted']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Cannot delete product with existing transaction history. Consider setting stock to 0.'], 400);
    }
}
?>
