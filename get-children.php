<?php
require 'includes/db.php';
header('Content-Type: application/json');

$center_id = (int)($_GET['center_id'] ?? 0);
$type = $_GET['type'] ?? '';

if($center_id && $type) {
    $stmt = $pdo->prepare("SELECT id, name FROM organization_nodes WHERE parent_id = ? AND type = ? ORDER BY name ASC");
    $stmt->execute([$center_id, $type]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    echo json_encode([]);
}
?>