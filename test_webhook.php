<?php
// Test webhook with sample WebApp data
$testData = [
    'message' => [
        'message_id' => 123,
        'from' => [
            'id' => 2114098498,
            'first_name' => 'Test',
            'username' => 'testuser'
        ],
        'chat' => [
            'id' => 2114098498,
            'type' => 'private'
        ],
        'date' => time(),
        'web_app_data' => [
            'data' => json_encode([
                'items' => [
                    [
                        'menu_item_id' => 1,
                        'name' => 'Test Osh',
                        'price' => 25000,
                        'quantity' => 2
                    ]
                ],
                'total' => 50000,
                'timestamp' => date('c')
            ])
        ]
    ]
];

echo "Testing webhook with sample data...\n";
echo "Data: " . json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init('https://olmazorgo.bigsaver.ru/bot/webhook_simple.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($testData),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
if ($error) {
    echo "cURL Error: $error\n";
}

// Check if debug log was created
if (file_exists('bot/simple_debug.log')) {
    echo "\n=== Debug Log ===\n";
    echo file_get_contents('bot/simple_debug.log');
} else {
    echo "\nNo debug log found.\n";
}
?>