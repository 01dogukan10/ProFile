<?php
require_once 'config.php';
session_start();

// Admin kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$is_admin = $stmt->fetchColumn();
if (!$is_admin) {
    header('Location: index.php');
    exit;
}

// Kullanıcı silme
if (isset($_GET['delete_user'])) {
    $del_id = intval($_GET['delete_user']);
    if ($del_id !== $user_id) { // Kendi hesabını silemesin
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$del_id]);
    }
    header('Location: admin.php');
    exit;
}
// Dosya silme
if (isset($_GET['delete_file'])) {
    $del_id = intval($_GET['delete_file']);
    $stmt = $db->prepare('SELECT filename FROM files WHERE id = ?');
    $stmt->execute([$del_id]);
    $file = $stmt->fetchColumn();
    if ($file && file_exists('uploads/' . $file)) {
        unlink('uploads/' . $file);
    }
    $db->prepare('DELETE FROM files WHERE id = ?')->execute([$del_id]);
    header('Location: admin.php');
    exit;
}
// Kullanıcılar
$users = $db->query('SELECT id, username, email, full_name, created_at, is_admin FROM users ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
// Dosyalar
$files = $db->query('SELECT * FROM files ORDER BY upload_date DESC')->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    setcookie('remember_user_id', '', time() - 3600, '/');
    setcookie('remember_username', '', time() - 3600, '/');
    header('Location: auth.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body[data-theme='dark'], html[data-theme='dark'] { background: #181a20 !important; }
        body, html { background: var(--bg-main) !important; min-height: 100vh; }
        .container { background: transparent !important; box-shadow: none !important; }
        .admin-title {font-weight:700; color:var(--text-main); letter-spacing:1px;}
        .card { background: var(--bg-card); border-radius: 18px; box-shadow: 0 4px 24px 0 rgba(60,72,88,0.08); border: none; }
        .table th, .table td {vertical-align:middle;}
        .table th {background: var(--tab-bg); color: var(--text-main);}
        .table-striped > tbody > tr:nth-of-type(odd) {background-color: #f8fafc;}
        body[data-theme='dark'] .table-striped > tbody > tr:nth-of-type(odd) {background-color: #23272f;}
        .btn-xs {padding:2px 8px;font-size:0.95rem;}
        .btn-warning {background:linear-gradient(90deg,#fbbf24 0%,#f59e42 100%)!important;color:#23272f!important;border:none!important;}
        .btn-warning:hover {background:linear-gradient(90deg,#f59e42 0%,#fbbf24 100%)!important;}
        .btn-danger, .btn-danger.btn-xs {background:linear-gradient(90deg,#991b1b 0%,#7f1d1d 100%)!important;color:#fff!important;border:none!important;}
        .btn-danger:hover {background:linear-gradient(90deg,#7f1d1d 0%,#991b1b 100%)!important;}
        .btn-success, .btn-success.btn-xs {background:linear-gradient(90deg,#22d3ee 0%,#38bdf8 100%)!important;color:#fff!important;border:none!important;}
        .btn-success:hover {background:linear-gradient(90deg,#0ea5e9 0%,#0284c7 100%)!important;}
        .badge.bg-success {background:linear-gradient(90deg,#134e4a 0%,#0f766e 100%)!important;color:#a7f3d0!important;}
        .badge.bg-secondary {background:linear-gradient(90deg,#374151 0%,#23272f 100%)!important;color:#f3f4f6!important;}
        .badge.bg-primary {background:linear-gradient(90deg,#6366f1 0%,#60a5fa 100%)!important;color:#fff!important;}
        .btn-outline-secondary {color:var(--text-main)!important;border-color:var(--border-main)!important;}
        .btn-outline-secondary:hover {background:var(--tab-bg)!important;}
        .btn-outline-danger {color:#fff!important;background:linear-gradient(90deg,#991b1b 0%,#7f1d1d 100%)!important;border:none!important;}
        .btn-outline-danger:hover {background:linear-gradient(90deg,#7f1d1d 0%,#991b1b 100%)!important;}
    </style>
</head>
<body>
    <button class="theme-toggle" id="themeToggle" title="Tema Değiştir">🌙</button>
    <div class="container" style="max-width:1100px;">
        <h2 class="text-center my-4 admin-title">Admin Paneli</h2>
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <a href="index.php" class="btn btn-outline-secondary">← Ana Sayfa</a>
            <span class="badge bg-primary">Admin: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="?logout=1" class="btn btn-outline-danger">Çıkış</a>
        </div>
        <div class="card mb-4 p-2">
            <div class="card-body">
                <h5 class="card-title">Kullanıcılar</h5>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kullanıcı Adı</th>
                                <th>Ad Soyad</th>
                                <th>E-posta</th>
                                <th>Kayıt Tarihi</th>
                                <th>Yetki</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo $u['id']; ?></td>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><?php echo $u['created_at']; ?></td>
                                <td><?php echo $u['is_admin'] ? '<span class="badge bg-success">Admin</span>' : '<span class="badge bg-secondary">Kullanıcı</span>'; ?></td>
                                <td>
                                    <?php if ($u['id'] != $user_id): ?>
                                    <a href="?delete_user=<?php echo $u['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('Kullanıcıyı silmek istediğinize emin misiniz?');">Sil</a>
                                    <?php else: ?>
                                    <span class="text-muted">(Siz)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card p-2">
            <div class="card-body">
                <h5 class="card-title">Tüm Dosyalar</h5>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Dosya Adı</th>
                                <th>Kategori</th>
                                <th>Boyut</th>
                                <th>Yükleme Tarihi</th>
                                <th>İndirme</th>
                                <th>İndirme Sayısı</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($files as $f): ?>
                            <tr>
                                <td><?php echo $f['id']; ?></td>
                                <td><?php echo htmlspecialchars($f['original_name']); ?></td>
                                <td><?php echo htmlspecialchars($f['category'] ?? ''); ?></td>
                                <td><?php echo number_format($f['file_size'] / 1024, 2); ?> KB</td>
                                <td><?php echo $f['upload_date']; ?></td>
                                <td><a href="uploads/<?php echo htmlspecialchars($f['filename']); ?>" class="btn btn-success btn-xs" download>İndir</a></td>
                                <td><?php echo $f['download_count']; ?></td>
                                <td><a href="?delete_file=<?php echo $f['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('Dosyayı silmek istediğinize emin misiniz?');">Sil</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="main.js"></script>
</body>
</html> 