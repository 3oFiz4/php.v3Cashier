<?php
/**
 * Transactions API
 * Handles checkout and transaction history
 */

session_start();
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

match($action) {
    'list' => listTransactions(),
    'checkout' => handleCheckout(),
    'get' => getTransaction(),
    default => jsonResponse(['error' => 'Invalid action'], 400)
};

function listTransactions(): void {
    $db = getDB();
    $limit = (int)($_GET['limit'] ?? 50);
    $stmt = $db->prepare("SELECT * FROM transactions ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    jsonResponse(['transactions' => $stmt->fetchAll()]);
}

function getTransaction(): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'Invalid ID'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$id]);
    $transaction = $stmt->fetch();

    if (!$transaction) jsonResponse(['error' => 'Transaction not found'], 404);

    $stmt2 = $db->prepare("SELECT * FROM transaction_items WHERE transaction_id = ?");
    $stmt2->execute([$id]);
    $transaction['items'] = $stmt2->fetchAll();

    jsonResponse(['transaction' => $transaction]);
}

function handleCheckout(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $data = getJsonInput();
    $items = $data['items'] ?? [];
    $paymentAmount = (float)($data['payment_amount'] ?? 0);

    if (empty($items)) {
        jsonResponse(['error' => 'Cart is empty'], 400);
    }

    $db = getDB();

    try {
        $db->beginTransaction();

        $totalAmount = 0;
        $totalItems = 0;
        $validatedItems = [];

        // See if the stock is validated and then calculate totals
        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['quantity'] ?? 0);

            if ($productId <= 0 || $qty <= 0) {
                throw new Exception("Invalid item data");
            }

            // Lock row for update
            $stmt = $db->prepare("SELECT id, name, price, stock FROM products WHERE id = ? FOR UPDATE");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) {
                throw new Exception("Product ID {$productId} not found");
            }

            if ($product['stock'] < $qty) {
                throw new Exception("Insufficient stock for '{$product['name']}'. Available: {$product['stock']}");
            }

            $subtotal = $product['price'] * $qty;
            $totalAmount += $subtotal;
            $totalItems += $qty;

            $validatedItems[] = [
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'product_price' => $product['price'],
                'quantity' => $qty,
                'subtotal' => $subtotal
            ];
        }

        if ($paymentAmount < $totalAmount) {
            throw new Exception("Payment amount ({$paymentAmount}) is less than total ({$totalAmount})");
        }

        $changeAmount = $paymentAmount - $totalAmount;

        // Unique transaction code, YOR- for right now
        $txnCode = 'YOR-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Insert the transaction thing
        $stmt = $db->prepare("INSERT INTO transactions (transaction_code, total_amount, total_items, payment_amount, change_amount) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$txnCode, $totalAmount, $totalItems, $paymentAmount, $changeAmount]);
        $txnId = (int)$db->lastInsertId();

        // Insert the nitems and then update stock
        $insertItem = $db->prepare("INSERT INTO transaction_items (transaction_id, product_id, product_name, product_price, quantity, subtotal) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
        $updateStock = $db->prepare("UPDATE products SET stock = stock - ?, updated_at = NOW() WHERE id = ?");

        foreach ($validatedItems as $vi) {
            $insertItem->execute([$txnId, $vi['product_id'], $vi['product_name'], $vi['product_price'], $vi['quantity'], $vi['subtotal']]);
            $updateStock->execute([$vi['quantity'], $vi['product_id']]);
        }

        $db->commit();

        jsonResponse([
            'success' => true,
            'transaction' => [
                'id' => $txnId,
                'transaction_code' => $txnCode,
                'total_amount' => $totalAmount,
                'total_items' => $totalItems,
                'payment_amount' => $paymentAmount,
                'change_amount' => $changeAmount,
                'items' => $validatedItems,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => $e->getMessage()], 400);
    }
}
?>
