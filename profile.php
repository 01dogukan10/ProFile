<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] == 0) {
    header('Location: auth.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = isset($_GET['success']) ? 'Profil başarıyla güncellendi!' : '';
$error = '';

// Bilgileri güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta giriniz.';
    } else {
        $stmt = $db->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?');
        $stmt->execute([$full_name, $email, $user_id]);
        header('Location: profile.php?success=1');
        exit;
    }
}

// Profil fotoğrafı yükleme
if (isset($_POST['upload_photo']) && isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $filename = 'profile_' . $user_id . '_' . uniqid() . '.' . $ext;
            $uploadPath = 'uploads/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Eski fotoğrafı sil
                $stmt = $db->prepare('SELECT profile_image FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $old = $stmt->fetchColumn();
                if ($old && file_exists('uploads/' . $old)) {
                    unlink('uploads/' . $old);
                }
                $stmt = $db->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
                $stmt->execute([$filename, $user_id]);
                header('Location: profile.php?success=1');
                exit;
            } else {
                $error = 'Fotoğraf yüklenemedi.';
            }
        } else {
            $error = 'Sadece jpg, jpeg, png, gif, webp dosyaları yüklenebilir.';
        }
    } else {
        $error = 'Fotoğraf yüklenirken hata oluştu.';
    }
}

// Kullanıcı bilgilerini çek
$stmt = $db->prepare('SELECT username, email, full_name, created_at, profile_image FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die('Kullanıcı bulunamadı.');
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body[data-theme='dark'], html[data-theme='dark'] { background: #181a20 !important; }
        body, html { background: var(--bg-main) !important; min-height: 100vh; }
        .profile-card {max-width: 500px; margin: 40px auto; background: var(--bg-card); border-radius: 18px; box-shadow: 0 4px 24px 0 rgba(60,72,88,0.08); border: none;}
        .profile-title {font-weight:700; color:var(--text-main); letter-spacing:1px;}
        .profile-avatar, .profile-avatar img, .profile-avatar .avatar-initial {
            width:96px; height:96px; object-fit:cover; border-radius:50%; box-shadow:0 2px 8px rgba(0,0,0,0.08);
        }
        .profile-avatar .avatar-initial {
            background: var(--text-secondary); color: #fff; font-size:2.5rem; display:flex; align-items:center; justify-content:center;
        }
        .profile-photo-upload input[type="file"] {max-width:220px; display:inline-block;}
        @media (max-width:600px) {
            .profile-card {margin: 16px auto;}
            .profile-title {font-size:1.1rem;}
            .profile-avatar, .profile-avatar img, .profile-avatar .avatar-initial {width:72px;height:72px;font-size:1.5rem;}
        }
    </style>
</head>
<body>
    <button class="theme-toggle" id="themeToggle" title="Tema Değiştir">🌙</button>
    <div class="container">
        <div class="card profile-card p-4 mt-5">
            <h2 class="text-center mb-4 profile-title">Profilim</h2>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <div class="text-center mb-4">
                <div class="profile-avatar mx-auto mb-2">
                <?php if ($user['profile_image'] && file_exists('uploads/' . $user['profile_image'])): ?>
                    <img src="uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profil Fotoğrafı">
                <?php else: ?>
                    <div class="avatar-initial"><?php echo strtoupper(mb_substr($user['full_name'] ?: $user['username'],0,1,'UTF-8')); ?></div>
                <?php endif; ?>
                </div>
                <form method="post" enctype="multipart/form-data" class="mt-2 profile-photo-upload">
                    <input type="file" name="profile_image" accept="image/*" class="form-control mb-2">
                    <button type="submit" name="upload_photo" class="btn btn-outline-primary btn-sm">Fotoğrafı Yükle</button>
                </form>
            </div>
            <form method="post" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-posta</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="full_name" class="form-label">Ad Soyad</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Kayıt Tarihi</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['created_at']); ?>" disabled>
                </div>
                <button type="submit" class="btn btn-primary w-100">Güncelle</button>
            </form>
            <div class="mt-3 text-center">
                <a href="index.php" class="btn btn-outline-secondary">← Ana Sayfa</a>
            </div>
        </div>
    </div>
    <script src="main.js"></script>
    <script>
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
    </script>
</body>
</html> 