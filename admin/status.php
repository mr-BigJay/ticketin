<?php

require '../includes/auth.php';
require '../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

$id = (int)($_GET['id'] ?? 0);

$status = $_GET['status'] ?? '';

$allowedStatuses = ['open','pending','closed'];

if(!$id || !in_array($status, $allowedStatuses, true)){

    die("درخواست نامعتبر است");

}

if($status === 'closed'){

    $stmt = $pdo->prepare("
        UPDATE tickets
        SET status=?, closed_at=NOW()
        WHERE id=?
    ");

}else{

    $stmt = $pdo->prepare("
        UPDATE tickets
        SET status=?, closed_at=NULL
        WHERE id=?
    ");

}

$stmt->execute([
    $status,
    $id
]);

header("Location: tickets.php");
