<?php
// Demo menyu ma'lumotlarini yuklash

require_once __DIR__ . '/bot/config.php';
require_once __DIR__ . '/bot/db/Database.php';

try {
    $db = Database::get();
    
    // Kategoriyalarni yangilash
    $db->exec("DELETE FROM categories");
    $db->exec("INSERT INTO categories (id, name, sort_order) VALUES 
        (1, 'Salatlar', 1),
        (2, 'Asosiy taomlar', 2), 
        (3, 'Ichimliklar', 3),
        (4, 'Shirinliklar', 4)");
    
    // Menyu mahsulotlarini demo rasmlar bilan yangilash
    $db->exec("DELETE FROM menu_items");
    
    $items = [
        // Salatlar
        [1, 'Toshkent salati', 'Yangi sabzavotlar, pomidor, bodring, piyoz', 15000, 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=400&h=300&fit=crop'],
        [1, 'Sezar salati', 'Tovuq go\'shti, salat bargi, parmesan pishloqi', 22000, 'https://images.unsplash.com/photo-1546793665-c74683f339c1?w=400&h=300&fit=crop'],
        [1, 'Yunon salati', 'Pomidor, bodring, zaytun, feta pishloqi', 18000, 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=400&h=300&fit=crop'],
        
        // Asosiy taomlar  
        [2, 'Osh', 'An\'anaviy o\'zbek oshi, qo\'y go\'shti bilan', 35000, 'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=400&h=300&fit=crop'],
        [2, 'Shashlik', 'Qo\'y go\'shtidan tayyorlangan shashlik', 45000, 'https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?w=400&h=300&fit=crop'],
        [2, 'Manti', 'Bug\'da pishirilgan manti, 8 dona', 30000, 'https://images.unsplash.com/photo-1496116218417-1a781b1c416c?w=400&h=300&fit=crop'],
        [2, 'Lag\'mon', 'Qo\'l tortma lag\'mon, go\'sht va sabzavotlar bilan', 28000, 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=400&h=300&fit=crop'],
        [2, 'Somsa', 'Tandir somsasi, go\'sht va piyoz bilan', 8000, 'https://images.unsplash.com/photo-1601050690597-df0568f70950?w=400&h=300&fit=crop'],
        
        // Ichimliklar
        [3, 'Ko\'k choy', 'An\'anaviy o\'zbek choyi', 5000, 'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=400&h=300&fit=crop'],
        [3, 'Qora choy', 'Hindiston choyi', 5000, 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&h=300&fit=crop'],
        [3, 'Coca-Cola', 'Gazlangan ichimlik 0.5L', 8000, 'https://images.unsplash.com/photo-1561758033-d89a9ad46330?w=400&h=300&fit=crop'],
        [3, 'Fanta', 'Apelsinli gazlangan ichimlik 0.5L', 8000, 'https://images.unsplash.com/photo-1624517452488-04869289c4ca?w=400&h=300&fit=crop'],
        [3, 'Suv', 'Toza ichimlik suvi 0.5L', 3000, 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=400&h=300&fit=crop'],
        [3, 'Kompot', 'Mevali kompot', 7000, 'https://images.unsplash.com/photo-1571934811356-5cc061b6821f?w=400&h=300&fit=crop'],
        
        // Shirinliklar
        [4, 'Tiramisu', 'Italyan tiramisu torti', 25000, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400&h=300&fit=crop'],
        [4, 'Cheesecake', 'Klassik cheesecake', 20000, 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=400&h=300&fit=crop'],
        [4, 'Muzqaymoq', 'Vanilli muzqaymoq', 12000, 'https://images.unsplash.com/photo-1563805042-7684c019e1cb?w=400&h=300&fit=crop'],
        [4, 'Shokoladli tort', 'Qatlamli shokoladli tort', 22000, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=400&h=300&fit=crop']
    ];
    
    $stmt = $db->prepare("INSERT INTO menu_items (category_id, name, description, price, image_url, is_available) VALUES (?, ?, ?, ?, ?, 1)");
    
    foreach ($items as $item) {
        $stmt->execute($item);
    }
    
    echo "✅ Demo menyu ma'lumotlari muvaffaqiyatli yuklandi!\n";
    echo "📊 Jami " . count($items) . " ta mahsulot qo'shildi.\n";
    echo "🔗 Menyu API: /bot/api/menu.php\n";
    echo "⚙️ Admin panel: /admin/\n";
    
} catch (Exception $e) {
    echo "❌ Xatolik: " . $e->getMessage() . "\n";
}
?>