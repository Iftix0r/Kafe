<?php
require_once __DIR__ . '/bot/db/Database.php';

try {
    $db = Database::get();
    echo "Connection successful!\n";

    $tables = ['orders', 'order_items', 'users', 'menu_items'];
    foreach ($tables as $table) {
        echo "\nTable: $table\n";
        $stmt = $db->query("DESCRIBE $table");
        while ($row = $stmt->fetch()) {
            echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Key']}\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
