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
    <title>Admin Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <h2>🍽 Admin Panel</h2>
        <?php if (!empty($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="login" placeholder="Login" required autofocus>
            <input type="password" name="password" placeholder="Parol" required>
            <button type="submit">Kirish</button>
        </form>
    </div>
</body>
</html>
