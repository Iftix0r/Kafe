<?php
session_start();
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['login'] === ADMIN_LOGIN && password_verify($_POST['password'], ADMIN_PASSWORD)) {
        $_SESSION['admin'] = true;
        header('Location: /admin/index.php');
        exit;
    }
    $error = 'Login yoki parol noto\'g\'ri';
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login - Olmazor Go</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="logo"><i class="fas fa-utensils"></i></div>
        <h2>Admin Panel</h2>
        <?php if (!empty($error)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <input type="text" name="login" placeholder="Login" required autofocus>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Parol" required>
            </div>
            <button type="submit">
                <i class="fas fa-sign-in-alt"></i>
                Kirish
            </button>
        </form>
    </div>
</body>
</html>
