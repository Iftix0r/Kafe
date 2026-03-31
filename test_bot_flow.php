<?php
// Test the complete bot flow

echo "=== Testing Bot Flow ===\n\n";

// Test 1: /start command
echo "1. Testing /start command...\n";
$startData = [
    'message' => [
        'message_id' => 1,
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
        'text' => '/start'
    ]
];

file_put_contents('test_webhook_input.json', json_encode($startData));
echo "Saved start command data to test_webhook_input.json\n";

// Test 2: WebApp data
echo "\n2. Testing WebApp data...\n";
$webappData = [
    'message' => [
        'message_id' => 2,
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
                        'menu_item_id' => 2,
                        'name' => 'Osh',
                        'price' => 35000,
                        'quantity' => 1
                    ],
                    [
                        'menu_item_id' => 4,
                        'name' => 'Choy',
                        'price' => 5000,
                        'quantity' => 2
                    ]
                ],
                'total' => 45000,
                'timestamp' => date('c')
            ])
        ]
    ]
];

file_put_contents('test_webapp_data.json', json_encode($webappData));
echo "Saved WebApp data to test_webapp_data.json\n";

// Test 3: Phone number
echo "\n3. Testing phone number...\n";
$phoneData = [
    'message' => [
        'message_id' => 3,
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
        'text' => '+998901234567'
    ]
];

file_put_contents('test_phone_data.json', json_encode($phoneData));
echo "Saved phone data to test_phone_data.json\n";

echo "\nTest data files created. You can now test the webhook manually.\n";
echo "To test, you would need to POST these JSON files to the webhook URL.\n";

// Check if debug log exists
if (file_exists('bot/simple_debug.log')) {
    echo "\n=== Current Debug Log ===\n";
    echo file_get_contents('bot/simple_debug.log');
} else {
    echo "\nNo debug log found yet.\n";
}
?>