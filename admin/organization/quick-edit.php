<?php

require '../../includes/auth.php';
require '../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if(($_SESSION['role'] ?? '') !== 'admin'){
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'دسترسی غیر مجاز']);
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'متد نامعتبر']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');

if(!$id || !$name){
    echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص است']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, type
    FROM organization_nodes
    WHERE id=?
");
$stmt->execute([$id]);
$node = $stmt->fetch();

if(!$node || !in_array($node['type'], ['unit', 'health_house'], true)){
    echo json_encode(['success' => false, 'error' => 'فقط زیرمجموعه قابل ویرایش است']);
    exit;
}

$update = $pdo->prepare("
    UPDATE organization_nodes
    SET name=?
    WHERE id=?
");
$update->execute([$name, $id]);

echo json_encode([
    'success' => true,
    'id' => $id,
    'name' => $name,
]);
