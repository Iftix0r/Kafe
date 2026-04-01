<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");

$userId = $_GET['id'] ?? '';
if (!$userId) {
    echo json_encode(['ok' => false, 'error' => 'Missing tg_id']);
    exit;
}

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
    $stmt->execute([$userId]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$userRow) {
        echo json_encode(['ok' => true, 'orders' => []]);
        exit;
    }
    
    $uid = $userRow['id'];
    
    // Fetch orders
    $ordersStmt = $pdo->prepare("SELECT id, total_price, status, created_at, tracking_link FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $ordersStmt->execute([$uid]);
    $dbOrders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $orders = [];
    foreach ($dbOrders as $o) {
        $orderId = $o['id'];
        
        // Fetch items
        $itemsStmt = $pdo->prepare("SELECT oi.quantity, oi.price, m.name FROM order_items oi JOIN menu_items m ON m.id = oi.menu_item_id WHERE oi.order_id = ?");
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $orders[] = [
            'id' => $o['id'],
            'date' => date('Y-m-d H:i', strtotime($o['created_at'])),
            'status' => $o['status'],
            'total' => $o['total_price'],
            'tracking_link' => $o['tracking_link'],
            'items' => array_map(function($i) {
                return [
                    'name' => $i['name'],
                    'quantity' => $i['quantity'],
                    'price' => $i['price']
                ];
            }, $items)
        ];
    }
    
    echo json_encode(['ok' => true, 'orders' => $orders]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
