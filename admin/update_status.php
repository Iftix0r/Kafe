<?php
require_once __DIR__ . '/auth.php';
requireAuth();

$allowed = ['new', 'confirmed', 'preparing', 'on_way', 'delivered', 'cancelled'];
$id = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$tracking_link = $_POST['tracking_link'] ?? '';

if ($id && in_array($status, $allowed)) {
    db()->prepare('UPDATE orders SET status = ?, tracking_link = ? WHERE id = ?')->execute([$status, $tracking_link, $id]);
    
    // Notify customer
    $stmt = db()->prepare("SELECT o.id, u.telegram_id, o.status FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    
    if ($order && $order['telegram_id']) {
        $statusTexts = [
            'confirmed' => "✅ Sizning #{$id} raqamli buyurtmangiz tasdiqlandi!",
            'preparing' => "👨‍🍳 Sizning #{$id} raqamli buyurtmangiz tayyorlanmoqda!",
            'on_way'    => "🚚 Sizning #{$id} raqamli buyurtmangiz yo'lda!\nKuryer yo'lga chiqdi va tez orada buyurtmangizni yetkazib beradi.",
            'delivered' => "🚀 Sizning #{$id} raqamli buyurtmangiz muvaffaqiyatli yetkazildi! Yoqimli ishtaha!",
            'cancelled' => "❌ Sizning #{$id} raqamli buyurtmangiz bekor qilindi."
        ];
        
        if (isset($statusTexts[$status])) {
            $msg = $statusTexts[$status];
            if ($status === 'on_way' && !empty($tracking_link)) {
                $msg .= "\n\📍 Kuryer qayerda ekanligini ushbu havola orqali kuzatib borishingiz mumkin:\n" . $tracking_link;
            }
            
            $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
            $postData = http_build_query([
                'chat_id' => $order['telegram_id'],
                'text' => $msg,
                'parse_mode' => 'HTML'
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}

$redirect = $_POST['redirect'] ?? 'index.php';
header('Location: ' . $redirect);
