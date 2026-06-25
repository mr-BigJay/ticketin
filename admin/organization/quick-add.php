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

$name = trim($_POST['name'] ?? '');
$parent_id = (int)($_POST['parent_id'] ?? 0);
$type = $_POST['type'] ?? '';

if(!$name || !$parent_id || !in_array($type, ['unit', 'health_house'], true)){
    echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص است']);
    exit;
}

$parentStmt = $pdo->prepare("
    SELECT id, type, center_category
    FROM organization_nodes
    WHERE id=?
");
$parentStmt->execute([$parent_id]);
$parent = $parentStmt->fetch();

if(!$parent || $parent['type'] !== 'center'){
    echo json_encode(['success' => false, 'error' => 'مرکز معتبر نیست']);
    exit;
}

if(($parent['center_category'] ?? '') === 'administrative' && $type !== 'unit'){
    echo json_encode(['success' => false, 'error' => 'مرکز ستادی فقط واحد می‌پذیرد']);
    exit;
}

$sortStmt = $pdo->prepare("
    SELECT COALESCE(MAX(sort_order), 0) + 1
    FROM organization_nodes
    WHERE parent_id=? AND type=?
");
$sortStmt->execute([$parent_id, $type]);
$sort_order = (int)$sortStmt->fetchColumn();

$insert = $pdo->prepare("
    INSERT INTO organization_nodes
    (parent_id, type, center_category, name, sort_order)
    VALUES (?, ?, NULL, ?, ?)
");
$insert->execute([$parent_id, $type, $name, $sort_order]);

echo json_encode([
    'success' => true,
    'id' => (int)$pdo->lastInsertId(),
    'name' => $name,
    'sort_order' => $sort_order,
]);
