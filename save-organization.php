<?php
require 'includes/auth.php';
require 'includes/db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$node_ids_raw = $_POST['organization_nodes'] ?? '';

if(is_array($node_ids_raw)) {
    $node_ids_raw = implode(',', $node_ids_raw);
}

// تبدیل رشته کاما جدا به آرایه اعداد یکتا
$node_ids = array_values(array_unique(array_filter(
    array_map('intval', explode(',', (string)$node_ids_raw)),
    function($id){
        return $id > 0;
    }
)));

if(empty($node_ids)) {
    http_response_code(400);
    die('خطا: حداقل یک محل خدمت باید انتخاب شود');
}

// اولین واحد انتخاب شده به عنوان primary
$primary_node_id = $node_ids[0];

$placeholders = implode(',', array_fill(0, count($node_ids), '?'));
$checkStmt = $pdo->prepare("
    SELECT id, parent_id
    FROM organization_nodes
    WHERE id IN ($placeholders)
");
$checkStmt->execute($node_ids);
$nodes = [];

foreach($checkStmt->fetchAll(PDO::FETCH_ASSOC) as $node) {
    $nodes[(int)$node['id']] = $node;
}

if(count($nodes) !== count($node_ids)) {
    http_response_code(400);
    die('خطا: محل خدمت انتخاب شده معتبر نیست');
}

$pdo->beginTransaction();

try {
    // آپدیت users برای primary node
    $stmt = $pdo->prepare("UPDATE users SET organization_node_id = ? WHERE id = ?");
    $stmt->execute([$primary_node_id, $user_id]);

    // حذف رکوردهای قبلی
    $pdo->prepare("DELETE FROM user_organization_rel WHERE user_id = ?")->execute([$user_id]);

    $inserted = 0;
    foreach($node_ids as $node_id) {

        $row = $nodes[$node_id];

        // پیدا کردن center_id از parent_id
        $center_id = !empty($row['parent_id']) ? (int)$row['parent_id'] : $node_id;

        $insert = $pdo->prepare("INSERT INTO user_organization_rel (user_id, center_id, node_id) VALUES (?, ?, ?)");
        $insert->execute([$user_id, $center_id, $node_id]);
        $inserted++;
    }

    $pdo->commit();

} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    die('خطا در ثبت محل خدمت');
}

// ذخیره primary node در سشن
$_SESSION['organization_node_id'] = $primary_node_id;

// هدایت به داشبورد با پیام موفقیت
header("Location: dashboard.php?success=1");
exit;
?>