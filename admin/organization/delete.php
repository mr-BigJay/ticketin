<?php

require '../../includes/auth.php';
require '../../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    DELETE FROM organization_nodes
    WHERE parent_id=?
");

$stmt->execute([$id]);

$stmt = $pdo->prepare("
    DELETE FROM organization_nodes
    WHERE id=?
");

$stmt->execute([$id]);

header("Location: index.php");

exit;
