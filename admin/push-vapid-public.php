<?php

require '../includes/admin_auth.php';
require_once '../includes/push_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$ready = push_ensure_vapid_keys();
$publicKey = push_get_vapid_public_key();

echo json_encode([
    'ok' => $publicKey !== '',
    'publicKey' => $publicKey,
    'ready' => $ready,
    'error' => $publicKey === '' ? push_vapid_last_error() : '',
], JSON_UNESCAPED_UNICODE);
