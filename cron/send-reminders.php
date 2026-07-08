<?php

require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/push_helpers.php';

date_default_timezone_set('Asia/Tehran');

try{
    push_send_today_reminders($pdo);
    $result = ['ok' => true, 'time' => date('Y-m-d H:i:s')];
}catch(Throwable $e){
    $result = [
        'ok' => false,
        'error' => $e->getMessage(),
        'time' => date('Y-m-d H:i:s'),
    ];
}

if(php_sapi_name() === 'cli'){
    echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
