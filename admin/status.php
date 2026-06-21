<?php

require '../includes/auth.php';
require '../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

$id = (int)$_GET['id'];

$status = $_GET['status'];

$stmt = $pdo->prepare("
    UPDATE tickets
    SET status=?
    WHERE id=?
");

$stmt->execute([
    $status,
    $id
]);

header("Location: tickets.php");
