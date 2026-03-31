<?php
require_once 'bot/db/Database.php';
require_once 'bot/db/UserRepo.php';

echo "=== Testing Database Connection ===\n\n";

try {
    $db = Database::get();
    echo "✅ Database connection successful\n";
    
    // Test UserRepo
    $userRepo = new UserRepo();
    echo "✅ UserRepo created successfully\n";
    
    // Test finding a user
    $user = $userRepo->findByTelegramId(2114098498);
    if ($user) {
        echo "✅ User found: " . json_encode($user) . "\n";
    } else {
        echo "ℹ️ User not found (this is expected for new users)\n";
        
        // Test creating a user
        echo "Creating test user...\n";
        $userData = [
            'telegram_id' => 2114098498,
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'phone_number' => null
        ];
        
        $userId = $userRepo->create($userData);
        echo "✅ User created with ID: $userId\n";
        
        // Try to find the user again
        $user = $userRepo->findByTelegramId(2114098498);
        if ($user) {
            echo "✅ User found after creation: " . json_encode($user) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>