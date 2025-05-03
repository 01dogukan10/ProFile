<?php
require_once 'config.php';
session_start();

// Çıkış işlemi
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    setcookie('remember_user_id', '', time() - 3600, '/');
    setcookie('remember_username', '', time() - 3600, '/');
    header('Location: auth.php');
    exit;
}

// Kullanıcı giriş yapmamışsa login sayfasına yönlendir
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

// CSRF token üretimi
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Kullanıcı bilgisi ve admin kontrolü (her işlemden önce tanımlı olmalı)
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare('SELECT full_name, profile_image, username, is_admin FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$nav_user = $stmt->fetch(PDO::FETCH_ASSOC);
$is_admin = !empty($nav_user['is_admin']);

// Dosya indirme işlemi
if (isset($_GET['download'])) {
    $id = intval($_GET['download']);
    $stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($file) {
        // Erişim kontrolü
        if (!$is_admin && $file['user_id'] != $user_id) {
            die("Bu dosyaya erişim izniniz yok.");
        }
        $filepath = 'uploads/' . $file['user_id'] . '/' . $file['filename'];
        if (file_exists($filepath)) {
            if (ob_get_level()) ob_end_clean();
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            flush();
            if (readfile($filepath) !== false) {
                $stmt = $db->prepare("UPDATE files SET download_count = download_count + 1 WHERE id = ?");
                $stmt->execute([$id]);
            }
            exit;
        } else {
            die("Dosya bulunamadı.");
        }
    } else {
        die("Geçersiz dosya ID'si.");
    }
}

// Dosya silme işlemi
if (isset($_GET['delete'])) {
    // CSRF kontrolü
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Geçersiz CSRF token!');
    }
    $id = intval($_GET['delete']);
    $stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($file) {
        // Erişim kontrolü
        if (!$is_admin && $file['user_id'] != $user_id) {
            die("Bu dosyayı silme izniniz yok.");
        }
        $filepath = 'uploads/' . $file['user_id'] . '/' . $file['filename'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        $stmt = $db->prepare("DELETE FROM files WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php?deleted=1");
        exit;
    } else {
        die("Dosya bulunamadı.");
    }
}

// Kategori listesi
$categories = [
    'Belge', 'Resim', 'Video', 'Müzik', 'Arşiv', 'Diğer'
];

// Arama ve filtreleme
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';

// Dosya yükleme işlemi (kategori olmadan)
$upload_error = '';
$upload_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    // CSRF kontrolü
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Geçersiz CSRF token!');
    }
    $files = $_FILES['file'];
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml',
        'video/mp4', 'video/x-msvideo', 'video/x-matroska', 'video/quicktime', 'video/x-flv', 'video/mpeg', 'video/webm',
        'text/plain', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip', 'application/x-7z-compressed', 'application/x-rar-compressed', 'application/x-tar', 'application/gzip', 'application/x-zip-compressed'
    ];
    $success_count = 0;
    $error_msgs = [];
    $user_upload_dir = 'uploads/' . $user_id . '/';
    if (!file_exists($user_upload_dir)) {
        if (!mkdir($user_upload_dir, 0777, true)) {
            $error_msgs[] = 'Kullanıcı klasörü oluşturulamadı.';
        }
    }
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $error_msgs[] = 'Dosya yükleme hatası: ' . upload_error_message($files['error'][$i]) . ' (' . htmlspecialchars($files['name'][$i]) . ')';
            continue;
        }
        if (!in_array($files['type'][$i], $allowedTypes)) {
            $error_msgs[] = 'Bu dosya türü desteklenmiyor: ' . htmlspecialchars($files['name'][$i]);
            continue;
        }
        $originalName = $files['name'][$i];
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $uploadPath = $user_upload_dir . $filename;
        if (move_uploaded_file($files['tmp_name'][$i], $uploadPath)) {
            $stmt = $db->prepare("INSERT INTO files (filename, original_name, file_size, user_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$filename, $originalName, $files['size'][$i], $user_id]);
            $success_count++;
        } else {
            $error_msgs[] = 'Dosya yüklenirken bir hata oluştu: ' . htmlspecialchars($originalName);
        }
    }
    if ($success_count > 0) {
        $upload_success = $success_count . ' dosya başarıyla yüklendi!';
    }
    if ($error_msgs) {
        $upload_error = implode('<br>', $error_msgs);
    }
    if ($upload_success && !$upload_error) {
        header("Location: index.php?success=1");
        exit;
    }
}

$displayName = $nav_user['full_name'] ?? $nav_user['username'] ?? 'M';
$firstLetter = strtoupper(mb_substr((string)$displayName, 0, 1, 'UTF-8'));

function upload_error_message($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'Yüklenen dosya, sunucu ayarlarında belirtilen maksimum boyutu aşıyor.';
        case UPLOAD_ERR_FORM_SIZE:
            return 'Yüklenen dosya, formda belirtilen maksimum boyutu aşıyor.';
        case UPLOAD_ERR_PARTIAL:
            return 'Dosya sadece kısmen yüklendi.';
        case UPLOAD_ERR_NO_FILE:
            return 'Hiçbir dosya seçilmedi.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Geçici klasör eksik.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Dosya diske yazılamadı.';
        case UPLOAD_ERR_EXTENSION:
            return 'Bir PHP eklentisi dosya yüklemeyi durdurdu.';
        default:
            return 'Bilinmeyen bir hata oluştu.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Dosya Paylaşım Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            --btn-danger: linear-gradient(90deg, #f87171 0%, #ef4444 100%);
            --btn-danger-hover: linear-gradient(90deg, #dc2626 0%, #b91c1c 100%);
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
            --btn-danger: linear-gradient(90deg, #991b1b 0%, #7f1d1d 100%);
            --btn-danger-hover: linear-gradient(90deg, #7f1d1d 0%, #991b1b 100%);
            --alert-success-bg: linear-gradient(90deg, #134e4a 0%, #0f766e 100%);
            --alert-success-text: #a7f3d0;
            --alert-danger-bg: linear-gradient(90deg, #7f1d1d 0%, #991b1b 100%);
            --alert-danger-text: #fecaca;
        }
        body {
            min-height: 100vh;
            background: var(--bg-main);
        }
        .navbar {
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            background: var(--bg-card) !important;
        }
        .card {
            border-radius: 18px;
            box-shadow: 0 4px 24px 0 rgba(60,72,88,0.08);
            border: none;
            background: var(--bg-card);
        }
        .card-title {
            font-weight: 600;
            color: var(--text-main);
        }
        .btn-primary, .btn-success, .btn-danger {
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
        .btn-danger {
            background: var(--btn-danger);
            border: none;
        }
        .btn-danger:hover {
            background: var(--btn-danger-hover);
        }
        .table {
            border-radius: 12px;
            overflow: hidden;
            background: var(--bg-card);
        }
        .table th {
            background: var(--tab-bg);
            color: var(--text-main);
            font-weight: 600;
        }
        .table td, .table th {
            vertical-align: middle;
        }
        .spinner-border {
            width: 2rem; height: 2rem;
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
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
            letter-spacing: 1px;
            color: var(--text-main) !important;
        }
        .btn-outline-danger {
            border-radius: 8px;
        }
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #f8fafc;
        }
        body[data-theme='dark'] .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #23272f;
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
        .theme-toggle {
            position: fixed;
            top: 64px;
            right: 32px;
            background: none;
            border: none;
            font-size: 1.6rem;
            color: var(--text-secondary);
            cursor: pointer;
            z-index: 1000;
        }
        body[data-theme='dark'] .card,
        body[data-theme='dark'] .table,
        body[data-theme='dark'] .table th,
        body[data-theme='dark'] .table td {
            background: #23272f !important;
            color: #f3f4f6 !important;
            border-color: #23272f !important;
        }
        body[data-theme='dark'] .card-title,
        body[data-theme='dark'] .form-label,
        body[data-theme='dark'] .navbar-brand {
            color: #f3f4f6 !important;
        }
        body[data-theme='dark'] .btn-primary,
        body[data-theme='dark'] .btn-success,
        body[data-theme='dark'] .btn-danger,
        body[data-theme='dark'] .btn-outline-danger {
            color: #fff !important;
            border: none !important;
        }
        body[data-theme='dark'] .btn-primary {
            background: linear-gradient(90deg, #6366f1 0%, #60a5fa 100%) !important;
        }
        body[data-theme='dark'] .btn-success {
            background: linear-gradient(90deg, #22d3ee 0%, #38bdf8 100%) !important;
        }
        body[data-theme='dark'] .btn-danger,
        body[data-theme='dark'] .btn-outline-danger {
            background: linear-gradient(90deg, #991b1b 0%, #7f1d1d 100%) !important;
        }
        body[data-theme='dark'] .btn-primary:hover {
            background: linear-gradient(90deg, #4f46e5 0%, #2563eb 100%) !important;
        }
        body[data-theme='dark'] .btn-success:hover {
            background: linear-gradient(90deg, #0ea5e9 0%, #0284c7 100%) !important;
        }
        body[data-theme='dark'] .btn-danger:hover,
        body[data-theme='dark'] .btn-outline-danger:hover {
            background: linear-gradient(90deg, #7f1d1d 0%, #991b1b 100%) !important;
        }
        body[data-theme='dark'] .form-control,
        body[data-theme='dark'] .form-select {
            background: #181a20 !important;
            color: #f3f4f6 !important;
            border: 1px solid #23272f !important;
        }
        body[data-theme='dark'] .form-control:focus {
            background: #23272f !important;
            color: #f3f4f6 !important;
        }
        body[data-theme='dark'] .invalid-feedback {
            color: #fecaca !important;
        }
        body[data-theme='dark'] .alert-danger {
            background: linear-gradient(90deg, #7f1d1d 0%, #991b1b 100%) !important;
            color: #fecaca !important;
        }
        body[data-theme='dark'] .alert-success {
            background: linear-gradient(90deg, #134e4a 0%, #0f766e 100%) !important;
            color: #a7f3d0 !important;
        }
        .container {
            max-width: 900px;
            padding-left: 16px;
            padding-right: 16px;
        }
        .card {
            margin-bottom: 1.5rem;
        }
        .card-body {
            padding: 1.5rem 1rem;
        }
        .card-title {
            font-size: 1.2rem;
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        .btn, .form-control {
            font-size: 1rem;
        }
        h1 {
            font-size: 2rem;
        }
        @media (max-width: 600px) {
            .container {
                max-width: 100%;
                padding-left: 4px;
                padding-right: 4px;
            }
            .card-body {
                padding: 1rem 0.5rem;
            }
            .card-title {
                font-size: 1rem;
            }
            h1 {
                font-size: 1.2rem;
            }
            .btn, .form-control {
                font-size: 0.95rem;
            }
            .theme-toggle {
                top: 56px;
                right: 12px;
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <button class="theme-toggle" id="themeToggle" title="Tema Değiştir">🌙</button>
    <!-- Toast Container -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
        <div id="mainToast" class="toast align-items-center text-bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMsg">
                    <!-- Mesaj buraya gelecek -->
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Kapat"></button>
            </div>
        </div>
    </div>
    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4" style="min-height:64px;">
        <div class="container d-flex justify-content-center align-items-center position-relative" style="min-height:64px;">
            <a class="navbar-brand mx-auto" href="index.php" style="position: absolute; left: 0; right: 0; margin: auto; text-align: center;">Profile</a>
            <div class="d-flex align-items-center position-absolute end-0" style="padding-top:8px;">
                <?php if (!empty($nav_user['is_admin'])): ?>
                    <a href="admin.php" class="btn btn-warning btn-sm me-2">Admin Paneli</a>
                <?php endif; ?>
                <a href="profile.php" class="d-flex align-items-center text-decoration-none">
                    <?php if (!empty($nav_user['profile_image']) && file_exists('uploads/' . $nav_user['profile_image'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($nav_user['profile_image']); ?>" alt="Profil" class="rounded-circle me-2" style="width:38px;height:38px;object-fit:cover;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                    <?php else: ?>
                        <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center me-2" style="width:38px;height:38px;font-size:1.2rem;color:#fff;">
                            <?php echo $firstLetter; ?>
                        </div>
                    <?php endif; ?>
                </a>
                <a href="?logout=1" class="btn btn-outline-danger btn-sm ms-2">Çıkış</a>
            </div>
        </div>
    </nav>
    <div class="container" style="max-width: 900px;">
        <h1 class="text-center mb-5" style="font-weight:700; color:#374151;">Profile - Dosya Paylaşım Sistemi</h1>
        <!-- Arama ve Filtreleme Alanı -->
        <div class="card mb-3 p-2">
            <div class="card-body d-flex flex-wrap align-items-center gap-2">
                <form class="d-flex flex-wrap gap-2 w-100" method="get" action="">
                    <input type="text" class="form-control" name="search" placeholder="Dosya adı ile ara..." value="<?php echo htmlspecialchars($search); ?>" style="max-width:220px;">
                    <select name="category" class="form-select" style="max-width:180px;">
                        <option value="">Tüm Kategoriler</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php if ($filter_category === $cat) echo 'selected'; ?>><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-primary">Filtrele</button>
                </form>
            </div>
        </div>
        <!-- Dosya Yükleme Formu -->
        <div class="card mb-4 p-2">
            <div class="card-body">
                <h5 class="card-title">Dosya Yükle</h5>
                <?php if ($upload_error): ?>
                    <div class="alert alert-danger"><?php echo $upload_error; ?></div>
                <?php endif; ?>
                <form id="uploadForm" class="needs-validation" action="" method="post" enctype="multipart/form-data" novalidate>
                    <div class="mb-3">
                        <input type="file" class="form-control" name="file[]" id="fileInput" required multiple>
                        <div class="invalid-feedback">Lütfen bir veya birden fazla dosya seçin.</div>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="submit" class="btn btn-primary">Yükle</button>
                    <div id="uploadSpinner" class="mt-3" style="display:none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Yükleniyor...</span>
                        </div>
                        <span class="ms-2">Yükleniyor...</span>
                    </div>
                </form>
            </div>
        </div>
        <!-- Yüklenen Dosyalar Listesi -->
        <div class="card p-2">
            <div class="card-body">
                <h5 class="card-title">Yüklenen Dosyalar</h5>
                <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Dosya Adı</th>
                            <th>Kategori</th>
                            <th>Boyut</th>
                            <th>Yükleme Tarihi</th>
                            <th>İndirme Sayısı</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Dosya listeleme sorgusu
                        if ($is_admin) {
                            $query = "SELECT * FROM files WHERE 1";
                            $params = [];
                        } else {
                            $query = "SELECT * FROM files WHERE user_id = ?";
                            $params = [$user_id];
                        }
                        if ($search) {
                            $query .= " AND original_name LIKE ?";
                            $params[] = "%$search%";
                        }
                        if ($filter_category) {
                            $query .= " AND category = ?";
                            $params[] = $filter_category;
                        }
                        $query .= " ORDER BY upload_date DESC";
                        $stmt = $db->prepare($query);
                        $stmt->execute($params);
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['original_name']);
                            $ext = strtolower(pathinfo($row['original_name'], PATHINFO_EXTENSION));
                            $video_exts = ['mp4', 'webm', 'ogg', 'mov', 'mkv', 'avi', 'flv', 'mpeg'];
                            $image_exts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
                            $text_exts = ['txt', 'csv', 'log', 'md'];
                            $pdf_exts = ['pdf'];
                            $archive_exts = ['zip', 'rar', '7z', 'tar', 'gz'];
                            if (in_array($ext, $video_exts)) {
                                echo '<br><video src="uploads/' . htmlspecialchars($row['user_id']) . '/' . htmlspecialchars($row['filename']) . '" controls style="max-width:320px; max-height:180px; margin-top:4px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08);"></video>';
                            } elseif (in_array($ext, $image_exts)) {
                                echo '<br><img src="uploads/' . htmlspecialchars($row['user_id']) . '/' . htmlspecialchars($row['filename']) . '" style="max-width:120px; max-height:80px; margin-top:4px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08);" alt="Önizleme">';
                            } elseif (in_array($ext, $text_exts)) {
                                $file_path = 'uploads/' . htmlspecialchars($row['user_id']) . '/' . $row['filename'];
                                if (file_exists($file_path)) {
                                    $lines = file($file_path);
                                    $preview = htmlspecialchars(implode("", array_slice($lines, 0, 5)));
                                    echo '<br><pre style="max-width:200px; max-height:80px; overflow:auto; margin-top:4px; background:#f8f9fa; border:1px solid #ddd; padding:4px; font-size:12px; border-radius:8px;">' . $preview . '</pre>';
                                }
                            } elseif (in_array($ext, $pdf_exts)) {
                                echo '<br><img src="https://cdn.jsdelivr.net/gh/walkxcode/dashboard-icons/svg/pdf.svg" alt="PDF" style="width:24px;vertical-align:middle;">';
                            } elseif (in_array($ext, $archive_exts)) {
                                echo '<br><span class="text-muted" style="font-size:12px;">Önizleme yok</span>';
                            }
                            echo "</td>";
                            echo "<td>" . htmlspecialchars($row['category'] ?? '') . "</td>";
                            echo "<td>" . number_format($row['file_size'] / 1024, 2) . " KB</td>";
                            echo "<td>" . $row['upload_date'] . "</td>";
                            echo "<td>" . $row['download_count'] . "</td>";
                            echo "<td><a href='?download=" . $row['id'] . "' class='btn btn-success btn-sm me-2'>İndir</a> ";
                            echo "<a href='?delete=" . $row['id'] . "&csrf_token=" . $_SESSION['csrf_token'] . "' class='btn btn-danger btn-sm' onclick='return confirm(\"Dosyayı silmek istediğinize emin misiniz?\");'>Sil</a></td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
    <script>
    // Yükleme sırasında spinner göster
    const uploadForm = document.getElementById('uploadForm');
    const fileInput = document.getElementById('fileInput');
    const categoryFilter = document.querySelector('select[name="category"]');

    // Kategoriye göre dosya formatlarını ayarla
    if (categoryFilter && fileInput) {
        const acceptMap = {
            'Resim': 'image/*',
            'Video': 'video/*',
            'Belge': '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt',
            'Müzik': 'audio/*',
            'Arşiv': '.zip,.rar,.7z,.tar,.gz',
            'Diğer': ''
        };
        function updateAccept() {
            const val = categoryFilter.value;
            fileInput.accept = acceptMap[val] || '';
        }
        categoryFilter.addEventListener('change', updateAccept);
        updateAccept(); // ilk yüklemede de uygula
    }
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
<?php if (isset($_GET['success'])): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    showToast('<?php echo $upload_success; ?>', 'success');
});
</script>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    showToast('Dosya başarıyla silindi!', 'success');
});
</script>
<?php endif; ?>
<?php if ($upload_error): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    showToast(<?php echo json_encode($upload_error); ?>, 'danger');
});
</script>
<?php endif; ?>
<script>
// Toast fonksiyonu
function showToast(msg, type) {
    var toastEl = document.getElementById('mainToast');
    var toastMsg = document.getElementById('toastMsg');
    toastMsg.textContent = msg;
    toastEl.classList.remove('text-bg-primary', 'text-bg-success', 'text-bg-danger');
    if (type === 'success') toastEl.classList.add('text-bg-success');
    else if (type === 'danger') toastEl.classList.add('text-bg-danger');
    else toastEl.classList.add('text-bg-primary');
    var toast = new bootstrap.Toast(toastEl, { delay: 3500 });
    toast.show();
}
</script>
</body>
</html> 