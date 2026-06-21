<?php
require 'includes/auth.php';
require 'includes/db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$node_ids_raw = $_POST['organization_nodes'] ?? '';

// تبدیل رشته کاما جدا به آرایه اعداد
$node_ids = array_filter(array_map('intval', explode(',', $node_ids_raw)));

if(empty($node_ids)) {
    die('خطا: حداقل یک محل خدمت باید انتخاب شود');
}

// اولین واحد انتخاب شده به عنوان primary
$primary_node_id = $node_ids[0];

// آپدیت users برای primary node
$stmt = $pdo->prepare("UPDATE users SET organization_node_id = ? WHERE id = ?");
$stmt->execute([$primary_node_id, $user_id]);

// حذف رکوردهای قبلی
$pdo->prepare("DELETE FROM user_organization_rel WHERE user_id = ?")->execute([$user_id]);

$inserted = 0;
foreach($node_ids as $node_id) {
    if($node_id <= 0) continue;

    // پیدا کردن center_id از parent_id
    $stmt = $pdo->prepare("SELECT parent_id FROM organization_nodes WHERE id = ?");
    $stmt->execute([$node_id]);
    $row = $stmt->fetch();

    $center_id = $row && !empty($row['parent_id']) ? (int)$row['parent_id'] : $node_id;

    $insert = $pdo->prepare("INSERT INTO user_organization_rel (user_id, center_id, node_id) VALUES (?, ?, ?)");
    $insert->execute([$user_id, $center_id, $node_id]);
    $inserted++;
}

// ذخیره primary node در سشن
$_SESSION['organization_node_id'] = $primary_node_id;

// هدایت به داشبورد با پیام موفقیت
header("Location: dashboard.php?success=1");
exit;
?>