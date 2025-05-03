<?php
require_once 'config.php';
session_start();

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$show_form = true;

if (!$token) {
    $error = 'Geçersiz veya eksik bağlantı.';
    $show_form = false;
} else {
    $stmt = $db->prepare('SELECT id, reset_token_expiry FROM users WHERE reset_token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $error = 'Geçersiz veya süresi dolmuş bağlantı.';
        $show_form = false;
    } elseif (strtotime($user['reset_token_expiry']) < time()) {
        $error = 'Bu sıfırlama bağlantısının süresi dolmuş.';
        $show_form = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    $new_password = $_POST['new_password'] ?? '';
    $new_password2 = $_POST['new_password2'] ?? '';
    if ($new_password !== $new_password2) {
        $error = 'Şifreler uyuşmuyor!';
    } elseif (strlen($new_password) < 6) {
        $error = 'Şifre en az 6 karakter olmalı!';
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?');
        $stmt->execute([$hash, $user['id']]);
        $success = 'Şifreniz başarıyla güncellendi! Giriş yapabilirsiniz.';
        $show_form = false;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Şifre Belirle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <div class="card p-4" style="max-width:400px; width:100%;">
        <h3 class="mb-3 text-center">Yeni Şifre Belirle</h3>
        <?php if ($error): ?>
            <div class="alert alert-danger"> <?php echo $error; ?> </div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"> <?php echo $success; ?> </div>
        <?php endif; ?>
        <?php if ($show_form): ?>
        <form method="post">
            <div class="mb-3">
                <label for="new_password" class="form-label">Yeni Şifre</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
            </div>
            <div class="mb-3">
                <label for="new_password2" class="form-label">Yeni Şifre (Tekrar)</label>
                <input type="password" class="form-control" id="new_password2" name="new_password2" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Şifreyi Güncelle</button>
        </form>
        <?php endif; ?>
        <div class="text-center mt-3">
            <a href="auth.php">Girişe Dön</a>
        </div>
    </div>
</div>
</body>
</html> 