<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/Database.php';
require_once __DIR__ . '/../db/MenuRepo.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$menuRepo = new MenuRepo();
$categories = $menuRepo->getCategoriesWithItems();

// Add default images for items without images
foreach ($categories as &$category) {
    foreach ($category['items'] as &$item) {
        if (empty($item['image_url'])) {
            // Default placeholder image based on category
            $defaultImages = [
                1 => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=400&h=300&fit=crop', // Salads
                2 => 'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=400&h=300&fit=crop', // Main dishes
                3 => 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=400&h=300&fit=crop', // Drinks
                4 => 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400&h=300&fit=crop'  // Desserts
            ];
            $item['image_url'] = $defaultImages[$item['category_id']] ?? 'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=400&h=300&fit=crop';
        }
    }
}

echo json_encode($categories);
