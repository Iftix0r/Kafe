<?php
// Test webhook with POST request

$testData = [
    'message' => [
        'message_id' => 123,
        'from' => [
            'id' => 2114098498,
            'first_name' => 'Test',
            'last_name' => 'User',
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
                        'price' => 35000,
                        'quantity' => 1
                    ]
                ],
                'total' => 35000,
                'timestamp' => date('c')
            ])
        ]
    ]
];

echo "Testing webhook with POST request...\n";
echo "URL: https://olmazorgo.bigsaver.ru/bot/webhook_test.php\n";
echo "Data: " . json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

// Use file_get_contents with POST context
$postData = json_encode($testData);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $postData,
        'timeout' => 30
    ]
]);

$response = file_get_contents('https://olmazorgo.bigsaver.ru/bot/webhook_test.php', false, $context);

echo "Response: " . ($response ?: 'No response') . "\n";

// Check if debug log was created
sleep(2); // Wait a bit for file to be written

if (file_exists('bot/test_debug.log')) {
    echo "\n=== Debug Log ===\n";
    echo file_get_contents('bot/test_debug.log');
} else {
    echo "\nNo debug log found.\n";
}
?>