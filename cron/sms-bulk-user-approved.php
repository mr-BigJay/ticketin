<?php

require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/sms_helpers.php';

$stats = sms_count_bulk_user_approved_candidates($pdo);
$queued = sms_queue_bulk_user_approved($pdo);
$sent = sms_process_queue($pdo, 50);

echo json_encode([
    'stats' => $stats,
    'queue' => $queued,
    'process' => $sent,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
