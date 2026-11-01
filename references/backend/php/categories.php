<?php
/**
 * Categories API
 */

session_start();
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

match($action) {
    'list' => listCategories(),
    'create' => createCategory(),
    default => jsonResponse(['error' => 'Invalid action'], 400)
};

function listCategories(): void {
    $db = getDB();
    $stmt = $db->query("SELECT c.*, COUNT(p.id) as product_count 
                        FROM categories c LEFT JOIN products p ON c.id = p.category_id 
                        GROUP BY c.id ORDER BY c.name ASC");
    jsonResponse(['categories' => $stmt->fetchAll()]);
}

function createCategory(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $data = getJsonInput();
    $name = trim($data['name'] ?? '');

    if (empty($name)) jsonResponse(['error' => 'Category name is required'], 400);

    $db = getDB();
    try {
        $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$name]);
        jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId()], 201);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Category already exists'], 400);
    }
}
?>
