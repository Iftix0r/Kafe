<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=olmazorgo;charset=utf8mb4', 'olmazorgo', 'Iftixor2006', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("ALTER TABLE orders MODIFY COLUMN status ENUM('new','confirmed','preparing','on_way','delivered','cancelled') DEFAULT 'new'");
    // add tracking_link if not exists
    $cols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_ASSOC);
    $hasTracking = false;
    foreach ($cols as $col) { if ($col['Field'] == 'tracking_link') $hasTracking = true; }
    if (!$hasTracking) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN tracking_link VARCHAR(255) DEFAULT NULL AFTER status");
    }
    echo "Success!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
