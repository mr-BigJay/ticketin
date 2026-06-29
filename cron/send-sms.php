<?php

require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/sms_helpers.php';

$result = sms_process_queue($pdo, 50);

if(php_sapi_name() === 'cli'){
    echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
