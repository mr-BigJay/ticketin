<?php

require '../includes/admin_auth.php';
require_once '../includes/push_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if(!is_array($data)){
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
}

$action = (string)($data['action'] ?? 'subscribe');

if($action === 'unsubscribe'){
    $endpoint = trim((string)($data['endpoint'] ?? ''));

    if($endpoint !== ''){
        push_remove_subscription($pdo, $endpoint);
    }

    echo json_encode(['ok' => true]);
    exit;
}

$subscription = $data['subscription'] ?? $data;

if(!is_array($subscription)){
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_subscription']);
    exit;
}

$publicKey = push_get_vapid_public_key();

if($publicKey === ''){
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'vapid_not_ready']);
    exit;
}

$saved = push_save_subscription($pdo, (int)$_SESSION['user_id'], $subscription);

echo json_encode([
    'ok' => $saved,
    'publicKey' => $publicKey,
]);
