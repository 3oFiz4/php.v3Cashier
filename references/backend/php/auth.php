<?php
/**
 * Authentication Handler
 * Simple session-based auth for admin
 */

session_start();
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

match($action) {
    'login' => handleLogin(),
    'logout' => handleLogout(),
    'check' => checkAuth(),
    default => jsonResponse(['error' => 'Invalid action'], 400)
};

function handleLogin(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $data = getJsonInput();
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        jsonResponse(['error' => 'Username and password are required'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // password_verify for hashed password, also allow plain 'admin123' for demo
    if ($user && (password_verify($password, $user['password']) || $password === 'admin123')) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;

        jsonResponse([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ]
        ]);
    } else {
        jsonResponse(['error' => 'Invalid credentials'], 401);
    }
}

function handleLogout(): void {
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
}

function checkAuth(): void {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        jsonResponse([
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'full_name' => $_SESSION['full_name'],
                'role' => $_SESSION['role']
            ]
        ]);
    } else {
        jsonResponse(['authenticated' => false], 401);
    }
}
?>
