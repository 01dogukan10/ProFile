<?php
require_once 'config.php';
session_start();

// Giriş yaptıysa ana sayfaya yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Hata ve başarı mesajları
$login_error = '';
$register_error = '';
$register_success = '';
$password_error = '';
$password_success = '';

// Otomatik giriş (çerezden)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_user_id']) && isset($_COOKIE['remember_username'])) {
    $_SESSION['user_id'] = $_COOKIE['remember_user_id'];
    $_SESSION['username'] = $_COOKIE['remember_username'];
}

// Giriş işlemi
if (isset($_POST['login'])) {
    $username = $_POST['login_username'] ?? '';
    $password = $_POST['login_password'] ?? '';
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        // Beni hatırla seçiliyse çerez ayarla
        if (!empty($_POST['remember_me'])) {
            setcookie('remember_user_id', $user['id'], time() + 60*60*24*30, '/');
            setcookie('remember_username', $user['username'], time() + 60*60*24*30, '/');
        }
        header('Location: index.php');
        exit;
    } else {
        $login_error = 'Kullanıcı adı veya şifre hatalı!';
    }
}

// Misafir girişi işlemi
if (isset($_POST['guest_login'])) {
    $_SESSION['user_id'] = 0;
    $_SESSION['username'] = 'Misafir';
    header('Location: index.php');
    exit;
}

// Kayıt işlemi
if (isset($_POST['register'])) {
    $username = trim($_POST['register_username'] ?? '');
    $email = trim($_POST['register_email'] ?? '');
    $password = $_POST['register_password'] ?? '';
    $password2 = $_POST['register_password2'] ?? '';
    $full_name = trim($_POST['register_full_name'] ?? '');
    if ($password !== $password2) {
        $register_error = 'Şifreler uyuşmuyor!';
    } elseif (strlen($username) < 3 || strlen($password) < 6) {
        $register_error = 'Kullanıcı adı en az 3, şifre en az 6 karakter olmalı!';
    } else {
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $register_error = 'Bu kullanıcı adı veya e-posta zaten kayıtlı!';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username, password, email, full_name) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, $hash, $email, $full_name]);
            $register_success = 'Kayıt başarılı! Giriş yapabilirsiniz.';
        }
    }
}

// Şifre değiştirme işlemi
if (isset($_POST['change_password'])) {
    $username = trim($_POST['change_username'] ?? '');
    $email = trim($_POST['change_email'] ?? '');
    $new_password = $_POST['change_new_password'] ?? '';
    $new_password2 = $_POST['change_new_password2'] ?? '';
    if ($new_password !== $new_password2) {
        $password_error = 'Şifreler uyuşmuyor!';
    } elseif (strlen($new_password) < 6) {
        $password_error = 'Yeni şifre en az 6 karakter olmalı!';
    } else {
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ? AND email = ?');
        $stmt->execute([$username, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$hash, $user['id']]);
            $password_success = 'Şifre başarıyla değiştirildi!';
        } else {
            $password_error = 'Kullanıcı adı ve e-posta eşleşmiyor!';
        }
    }
}

// Çıkışta çerezleri de sil
if (basename($_SERVER['PHP_SELF']) === 'logout.php') {
    setcookie('remember_user_id', '', time() - 3600, '/');
    setcookie('remember_username', '', time() - 3600, '/');
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Giriş / Kayıt / Şifre Değiştir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --bg-main: #f8fafc;
            --bg-card: #fff;
            --text-main: #374151;
            --text-secondary: #6366f1;
            --border-main: #e0e7ef;
            --tab-bg: #f1f5f9;
            --tab-active: linear-gradient(90deg, #6366f1 0%, #60a5fa 100%);
            --btn-primary: linear-gradient(90deg, #6366f1 0%, #60a5fa 100%);
            --btn-primary-hover: linear-gradient(90deg, #4f46e5 0%, #2563eb 100%);
            --btn-success: linear-gradient(90deg, #22d3ee 0%, #38bdf8 100%);
            --btn-success-hover: linear-gradient(90deg, #0ea5e9 0%, #0284c7 100%);
            --btn-warning: linear-gradient(90deg, #fbbf24 0%, #f59e42 100%);
            --btn-warning-hover: linear-gradient(90deg, #f59e42 0%, #fbbf24 100%);
            --alert-success-bg: linear-gradient(90deg, #d1fae5 0%, #a7f3d0 100%);
            --alert-success-text: #065f46;
            --alert-danger-bg: linear-gradient(90deg, #fee2e2 0%, #fecaca 100%);
            --alert-danger-text: #991b1b;
        }
        body[data-theme='dark'] {
            --bg-main: #181a20;
            --bg-card: #23272f;
            --text-main: #f3f4f6;
            --text-secondary: #a5b4fc;
            --border-main: #23272f;
            --tab-bg: #23272f;
            --tab-active: linear-gradient(90deg, #6366f1 0%, #60a5fa 100%);
            --btn-primary: linear-gradient(90deg, #6366f1 0%, #60a5fa 100%);
            --btn-primary-hover: linear-gradient(90deg, #4f46e5 0%, #2563eb 100%);
            --btn-success: linear-gradient(90deg, #22d3ee 0%, #38bdf8 100%);
            --btn-success-hover: linear-gradient(90deg, #0ea5e9 0%, #0284c7 100%);
            --btn-warning: linear-gradient(90deg, #fbbf24 0%, #f59e42 100%);
            --btn-warning-hover: linear-gradient(90deg, #f59e42 0%, #fbbf24 100%);
            --alert-success-bg: linear-gradient(90deg, #134e4a 0%, #0f766e 100%);
            --alert-success-text: #a7f3d0;
            --alert-danger-bg: linear-gradient(90deg, #7f1d1d 0%, #991b1b 100%);
            --alert-danger-text: #fecaca;
        }
        body {
            min-height: 100vh;
            background: var(--bg-main);
        }
        .auth-card {
            border-radius: 18px;
            box-shadow: 0 4px 24px 0 rgba(60,72,88,0.08);
            border: none;
            background: var(--bg-card);
            padding: 2.5rem 2rem 2rem 2rem;
        }
        .nav-tabs .nav-link {
            border-radius: 8px 8px 0 0;
            font-weight: 500;
            color: var(--text-secondary);
            background: var(--tab-bg);
            border: none;
            margin-right: 2px;
        }
        .nav-tabs .nav-link.active {
            background: var(--tab-active);
            color: #fff;
        }
        .btn-primary, .btn-success, .btn-warning {
            border-radius: 8px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .btn-primary {
            background: var(--btn-primary);
            border: none;
        }
        .btn-primary:hover {
            background: var(--btn-primary-hover);
        }
        .btn-success {
            background: var(--btn-success);
            border: none;
        }
        .btn-success:hover {
            background: var(--btn-success-hover);
        }
        .btn-warning {
            background: var(--btn-warning);
            border: none;
            color: #fff;
        }
        .btn-warning:hover {
            background: var(--btn-warning-hover);
        }
        .alert-success {
            background: var(--alert-success-bg);
            color: var(--alert-success-text);
            border: none;
        }
        .alert-danger {
            background: var(--alert-danger-bg);
            color: var(--alert-danger-text);
            border: none;
        }
        .form-control, .form-select {
            border-radius: 8px;
            background: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border-main);
        }
        .form-label {
            font-weight: 500;
            color: var(--text-main);
        }
        .show-password-btn {
            position: absolute;
            top: 60%;
            right: 8px;
            transform: translateY(-40%);
            height: 20px;
            width: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .eye {
            font-size: 1.2em;
        }
        .auth-title {
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: 1px;
        }
        .theme-toggle {
            position: absolute;
            top: 24px;
            right: 32px;
            background: none;
            border: none;
            font-size: 1.6rem;
            color: var(--text-secondary);
            cursor: pointer;
            z-index: 10;
        }
        body[data-theme='dark'] .form-label,
        body[data-theme='dark'] .form-check-label,
        body[data-theme='dark'] .nav-link,
        body[data-theme='dark'] .form-text,
        body[data-theme='dark'] .form-check-input + .form-check-label {
            color: #f3f4f6 !important;
        }
        body[data-theme='dark'] .form-control::placeholder {
            color: #cbd5e1 !important;
            opacity: 1;
        }
        body[data-theme='dark'] .nav-tabs .nav-link.active {
            color: #fff !important;
            background: var(--tab-active) !important;
            border-color: var(--tab-active) var(--tab-active) #23272f !important;
            font-weight: bold;
        }
        body[data-theme='dark'] .nav-tabs .nav-link {
            color: #a5b4fc !important;
        }
        body[data-theme='dark'] .nav-tabs {
            border-bottom: 2px solid #6366f1 !important;
        }
        body[data-theme='dark'] .form-check-input:checked {
            background-color: #6366f1 !important;
            border-color: #6366f1 !important;
        }
    </style>
</head>
<body>
<div class="container d-flex align-items-center justify-content-center" style="min-height:100vh; position:relative;">
    <button class="theme-toggle" id="themeToggle" title="Tema Değiştir">🌙</button>
    <div class="auth-card" style="width:100%; max-width:500px;">
        <h2 class="mb-4 text-center auth-title">Profile</h2>
        <ul class="nav nav-tabs mb-3" id="authTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab">Giriş</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">Kayıt Ol</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="change-tab" data-bs-toggle="tab" data-bs-target="#change" type="button" role="tab">Şifre Değiştir</button>
            </li>
        </ul>
        <div class="tab-content" id="authTabContent">
            <!-- Giriş -->
            <div class="tab-pane fade show active" id="login" role="tabpanel">
                <?php if ($login_error): ?>
                    <div class="alert alert-danger mt-2"><?php echo $login_error; ?></div>
                <?php endif; ?>
                <form method="post" class="mt-3 needs-validation" novalidate id="loginForm">
                    <div class="mb-3">
                        <label for="login_username" class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="login_username" name="login_username" required>
                        <div class="invalid-feedback">Kullanıcı adı gereklidir.</div>
                    </div>
                    <div class="mb-3 position-relative">
                        <label for="login_password" class="form-label">Şifre</label>
                        <input type="password" class="form-control password-toggle" id="login_password" name="login_password" required>
                        <button type="button" class="btn btn-outline-secondary btn-sm position-absolute top-50 end-0 translate-middle-y me-2 show-password-btn" tabindex="-1" onclick="togglePassword('login_password', this)"><span class="eye">👁️</span></button>
                        <div class="invalid-feedback">Şifre gereklidir.</div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                        <label class="form-check-label" for="remember_me">Beni hatırla</label>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary w-100">Giriş Yap</button>
                </form>
                <form method="post" class="mt-2">
                    <button type="submit" name="guest_login" class="btn btn-secondary w-100">Misafir Girişi</button>
                </form>
                <div class="text-center mt-3">
                    <a href="reset_request.php">Şifremi Unuttum?</a>
                </div>
            </div>
            <!-- Kayıt -->
            <div class="tab-pane fade" id="register" role="tabpanel">
                <?php if ($register_error): ?>
                    <div class="alert alert-danger mt-2"><?php echo $register_error; ?></div>
                <?php elseif ($register_success): ?>
                    <div class="alert alert-success mt-2"><?php echo $register_success; ?></div>
                <?php endif; ?>
                <form method="post" class="mt-3 needs-validation" novalidate id="registerForm">
                    <div class="mb-3">
                        <label for="register_username" class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="register_username" name="register_username" required>
                        <div class="invalid-feedback">Kullanıcı adı gereklidir.</div>
                    </div>
                    <div class="mb-3">
                        <label for="register_email" class="form-label">E-posta</label>
                        <input type="email" class="form-control" id="register_email" name="register_email" required>
                        <div class="invalid-feedback">Geçerli bir e-posta giriniz.</div>
                    </div>
                    <div class="mb-3">
                        <label for="register_full_name" class="form-label">Ad Soyad</label>
                        <input type="text" class="form-control" id="register_full_name" name="register_full_name">
                    </div>
                    <div class="mb-3 position-relative">
                        <label for="register_password" class="form-label">Şifre</label>
                        <input type="password" class="form-control password-toggle" id="register_password" name="register_password" required oninput="checkPasswordStrength(this, 'register_strength')">
                        <button type="button" class="btn btn-outline-secondary btn-sm position-absolute top-50 end-0 translate-middle-y me-2 show-password-btn" tabindex="-1" onclick="togglePassword('register_password', this)"><span class="eye">👁️</span></button>
                        <div id="register_strength" class="form-text mt-1"></div>
                        <div class="invalid-feedback">Şifre gereklidir.</div>
                    </div>
                    <div class="mb-3 position-relative">
                        <label for="register_password2" class="form-label">Şifre (Tekrar)</label>
                        <input type="password" class="form-control password-toggle" id="register_password2" name="register_password2" required>
                        <button type="button" class="btn btn-outline-secondary btn-sm position-absolute top-50 end-0 translate-middle-y me-2 show-password-btn" tabindex="-1" onclick="togglePassword('register_password2', this)"><span class="eye">👁️</span></button>
                        <div class="invalid-feedback">Şifre tekrar gereklidir.</div>
                    </div>
                    <button type="submit" name="register" class="btn btn-success w-100">Kayıt Ol</button>
                </form>
            </div>
            <!-- Şifre Değiştir -->
            <div class="tab-pane fade" id="change" role="tabpanel">
                <?php if ($password_error): ?>
                    <div class="alert alert-danger mt-2"><?php echo $password_error; ?></div>
                <?php elseif ($password_success): ?>
                    <div class="alert alert-success mt-2"><?php echo $password_success; ?></div>
                <?php endif; ?>
                <form method="post" class="mt-3 needs-validation" novalidate id="changeForm">
                    <div class="mb-3">
                        <label for="change_username" class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="change_username" name="change_username" required>
                        <div class="invalid-feedback">Kullanıcı adı gereklidir.</div>
                    </div>
                    <div class="mb-3">
                        <label for="change_email" class="form-label">E-posta</label>
                        <input type="email" class="form-control" id="change_email" name="change_email" required>
                        <div class="invalid-feedback">Geçerli bir e-posta giriniz.</div>
                    </div>
                    <div class="mb-3 position-relative">
                        <label for="change_new_password" class="form-label">Yeni Şifre</label>
                        <input type="password" class="form-control password-toggle" id="change_new_password" name="change_new_password" required oninput="checkPasswordStrength(this, 'change_strength')">
                        <button type="button" class="btn btn-outline-secondary btn-sm position-absolute top-50 end-0 translate-middle-y me-2 show-password-btn" tabindex="-1" onclick="togglePassword('change_new_password', this)"><span class="eye">👁️</span></button>
                        <div id="change_strength" class="form-text mt-1"></div>
                        <div class="invalid-feedback">Yeni şifre gereklidir.</div>
                    </div>
                    <div class="mb-3 position-relative">
                        <label for="change_new_password2" class="form-label">Yeni Şifre (Tekrar)</label>
                        <input type="password" class="form-control password-toggle" id="change_new_password2" name="change_new_password2" required>
                        <button type="button" class="btn btn-outline-secondary btn-sm position-absolute top-50 end-0 translate-middle-y me-2 show-password-btn" tabindex="-1" onclick="togglePassword('change_new_password2', this)"><span class="eye">👁️</span></button>
                        <div class="invalid-feedback">Yeni şifre tekrar gereklidir.</div>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-warning w-100">Şifreyi Değiştir</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
// Aktif sekmeyi URL hash ile koru
const hash = window.location.hash;
if (hash) {
    const tabTrigger = document.querySelector(`button[data-bs-target='${hash}']`);
    if (tabTrigger) {
        new bootstrap.Tab(tabTrigger).show();
    }
}
// Şifre göster/gizle fonksiyonu
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.querySelector('.eye').textContent = '🙈';
    } else {
        input.type = 'password';
        btn.querySelector('.eye').textContent = '👁️';
    }
}
// Şifre gücü kontrolü
function checkPasswordStrength(input, targetId) {
    const val = input.value;
    let strength = 0;
    if (val.length >= 8) strength++;
    if (/[A-Z]/.test(val)) strength++;
    if (/[a-z]/.test(val)) strength++;
    if (/[0-9]/.test(val)) strength++;
    if (/[^A-Za-z0-9]/.test(val)) strength++;
    let msg = '';
    let color = '';
    if (val.length === 0) {
        msg = '';
    } else if (strength <= 2) {
        msg = 'Zayıf şifre';
        color = 'text-danger';
    } else if (strength === 3 || strength === 4) {
        msg = 'Orta seviye şifre';
        color = 'text-warning';
    } else if (strength === 5) {
        msg = 'Güçlü şifre';
        color = 'text-success';
    }
    const target = document.getElementById(targetId);
    target.textContent = msg;
    target.className = 'form-text mt-1 ' + color;
}
// Bootstrap form validation
(function () {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
// Tema geçişi ve localStorage
const themeToggle = document.getElementById('themeToggle');
function setTheme(theme) {
    document.body.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    themeToggle.textContent = theme === 'dark' ? '☀️' : '🌙';
}
// İlk yüklemede tema uygula
const savedTheme = localStorage.getItem('theme') || 'light';
setTheme(savedTheme);
themeToggle.addEventListener('click', function() {
    const current = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    setTheme(current);
});
</script>
</body>
</html> 