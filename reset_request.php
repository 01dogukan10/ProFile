<?php
require_once 'config.php';
session_start();

// PHPMailer Sınıfı
class PHPMailer {
    private $smtp_host = 'smtp.gmail.com';
    private $smtp_port = 465; // SSL port
    private $smtp_username = 'berrak.dogukan2@gmail.com'; // Gmail adresiniz
    private $smtp_password = 'xjvw wbhg xtlm aaaa'; // Gmail uygulama şifreniz (örnek)
    private $charset = 'UTF-8';
    private $from_email;
    private $from_name;
    private $error;

    public function __construct() {
        $this->from_email = $this->smtp_username;
        $this->from_name = 'Profile System';
    }

    public function send($to, $subject, $message) {
        $headers = array();
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-type: text/html; charset={$this->charset}";
        $headers[] = "From: {$this->from_name} <{$this->from_email}>";
        $headers[] = "Reply-To: {$this->from_email}";
        $headers[] = "X-Mailer: PHP/" . phpversion();

        // SMTP ayarları
        ini_set("SMTP", $this->smtp_host);
        ini_set("smtp_port", $this->smtp_port);
        ini_set("sendmail_from", $this->smtp_username);

        // SSL bağlantısı
        $smtp = fsockopen("ssl://{$this->smtp_host}", $this->smtp_port, $errno, $errstr, 30);
        if (!$smtp) {
            $this->error = "SMTP bağlantı hatası: $errstr ($errno)";
            return false;
        }

        // SMTP komutları
        $this->getResponse($smtp);
        fwrite($smtp, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $this->getResponse($smtp);
        fwrite($smtp, "AUTH LOGIN\r\n");
        $this->getResponse($smtp);
        fwrite($smtp, base64_encode($this->smtp_username) . "\r\n");
        $this->getResponse($smtp);
        fwrite($smtp, base64_encode($this->smtp_password) . "\r\n");
        $this->getResponse($smtp);
        fwrite($smtp, "MAIL FROM: <{$this->from_email}>\r\n");
        $this->getResponse($smtp);
        fwrite($smtp, "RCPT TO: <$to>\r\n");
        $this->getResponse($smtp);
        fwrite($smtp, "DATA\r\n");
        $this->getResponse($smtp);
        
        // E-posta içeriği
        $email = implode("\r\n", $headers) . "\r\n\r\n" . $message . "\r\n.\r\n";
        fwrite($smtp, $email);
        $this->getResponse($smtp);
        fwrite($smtp, "QUIT\r\n");
        fclose($smtp);

        return true;
    }

    private function getResponse($smtp) {
        $response = '';
        while ($str = fgets($smtp, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == ' ') break;
        }
        return $response;
    }

    public function getError() {
        return $this->error;
    }
}

// Mesajlar
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta giriniz.';
    } else {
        $stmt = $db->prepare('SELECT id, username FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 saat geçerli
            $stmt = $db->prepare('UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?');
            $stmt->execute([$token, $expiry, $user['id']]);
            $reset_link = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;
            
            // HTML formatında e-posta
            $subject = 'Şifre Sıfırlama Talebi';
            $message = '
            <html>
            <head>
                <title>Şifre Sıfırlama</title>
            </head>
            <body>
                <div style="max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;">
                    <h2 style="color: #333;">Şifre Sıfırlama Talebi</h2>
                    <p>Merhaba ' . htmlspecialchars($user['username']) . ',</p>
                    <p>Şifrenizi sıfırlamak için aşağıdaki bağlantıya tıklayın:</p>
                    <p style="margin: 20px 0;">
                        <a href="' . $reset_link . '" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                            Şifremi Sıfırla
                        </a>
                    </p>
                    <p style="color: #666; font-size: 14px;">Bu bağlantı 1 saat geçerlidir.</p>
                    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="color: #999; font-size: 12px;">
                        Bu e-posta, hesabınız için şifre sıfırlama talebi üzerine gönderilmiştir.<br>
                        Eğer bu talebi siz yapmadıysanız, bu e-postayı görmezden gelebilirsiniz.
                    </p>
                </div>
            </body>
            </html>';

            // E-posta gönderimi
            $mailer = new PHPMailer();
            if ($mailer->send($email, $subject, $message)) {
                $success = 'Şifre sıfırlama bağlantısı e-posta adresinize gönderildi!';
            } else {
                $error = 'E-posta gönderilemedi: ' . $mailer->getError();
            }
        } else {
            $error = 'Bu e-posta ile kayıtlı bir kullanıcı bulunamadı.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırlama Talebi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-main: #f8fafc;
            --bg-card: #fff;
            --text-main: #374151;
            --text-secondary: #6366f1;
            --border-main: #e0e7ef;
        }
        body[data-theme='dark'] {
            --bg-main: #181a20;
            --bg-card: #23272f;
            --text-main: #f3f4f6;
            --text-secondary: #a5b4fc;
            --border-main: #23272f;
        }
        body {
            min-height: 100vh;
            background: var(--bg-main);
        }
        .card {
            background: var(--bg-card);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card h3 {
            color: var(--text-main);
        }
        .form-control {
            background: var(--bg-card);
            border-color: var(--border-main);
            color: var(--text-main);
        }
        .form-label {
            color: var(--text-main);
        }
        .btn-primary {
            background: linear-gradient(to right, #6366f1, #60a5fa);
            border: none;
            padding: 10px 20px;
        }
        .btn-primary:hover {
            background: linear-gradient(to right, #4f46e5, #3b82f6);
        }
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            z-index: 1000;
        }
    </style>
</head>
<body>
<button class="theme-toggle" id="themeToggle" title="Tema Değiştir">🌙</button>
<div class="container d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <div class="card p-4" style="max-width:400px; width:100%;">
        <h3 class="mb-4 text-center">Şifre Sıfırlama</h3>
        <?php if ($success): ?>
            <div class="alert alert-success"> <?php echo $success; ?> </div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"> <?php echo $error; ?> </div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label for="email" class="form-label">E-posta adresiniz</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Sıfırlama Bağlantısı Gönder</button>
        </form>
        <div class="text-center mt-3">
            <a href="auth.php" style="color: var(--text-secondary); text-decoration: none;">Girişe Dön</a>
        </div>
    </div>
</div>
<script>
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