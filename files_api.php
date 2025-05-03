<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Giriş gerekli']);
    exit;
}

$user_id = $_SESSION['user_id'];
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';

$query = "SELECT * FROM files WHERE user_id = ?";
$params = [$user_id];
if ($search) {
    $query .= " AND original_name LIKE ?";
    $params[] = "%$search%";
}
if ($filter_category) {
    $query .= " AND category = ?";
    $params[] = $filter_category;
}
$query .= " ORDER BY upload_date DESC LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Toplam dosya sayısı (daha fazla var mı kontrolü için)
$count_query = "SELECT COUNT(*) FROM files WHERE user_id = ?";
$count_params = [$user_id];
if ($search) {
    $count_query .= " AND original_name LIKE ?";
    $count_params[] = "%$search%";
}
if ($filter_category) {
    $count_query .= " AND category = ?";
    $count_params[] = $filter_category;
}
$total = $db->prepare($count_query);
$total->execute($count_params);
$total_count = $total->fetchColumn();

// Dosya uzantısı ekle
foreach ($files as &$f) {
    $f['ext'] = strtolower(pathinfo($f['original_name'], PATHINFO_EXTENSION));
}

echo json_encode([
    'files' => $files,
    'has_more' => ($offset + $per_page) < $total_count
]); 