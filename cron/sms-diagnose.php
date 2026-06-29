<?php

require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/sms_helpers.php';

sms_ensure_schema($pdo);

$settings = sms_settings_get($pdo);
$config = sms_local_config();
$resolved = sms_melipayamak_resolve_config($config);
$queue = $pdo->query("
    SELECT id, event_key, mobile, status, attempts, last_error, created_at
    FROM sms_queue
    ORDER BY id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$report = [
    'api_configured' => sms_api_configured(),
    'master_enabled' => $settings['master_enabled'],
    'is_ready' => sms_is_ready($pdo),
    'api_source' => $settings['api_source'] ?? 'unknown',
    'provider' => (string)($config['provider'] ?? ''),
    'mode' => $resolved['mode'] ?? '',
    'sender' => (string)($config['sender'] ?? ''),
    'token_set' => ($resolved['token'] ?? '') !== '',
    'url_mode' => $resolved['mode'] ?? '',
    'pending_count' => (int)$pdo->query("SELECT COUNT(*) FROM sms_queue WHERE status='pending'")->fetchColumn(),
    'failed_count' => (int)$pdo->query("SELECT COUNT(*) FROM sms_queue WHERE status='failed'")->fetchColumn(),
    'last_queue' => array_map(static function (array $row): array {
        $row['mobile'] = sms_mask_mobile((string)$row['mobile']);

        return $row;
    }, $queue),
    'hints' => [],
];

if(!$report['api_configured']){
    $report['hints'][] = 'API تنظیم نشده — پنل سوپرادمین → مدیریت پیامک → اتصال API';
}

if(!$report['master_enabled']){
    $report['hints'][] = 'ارسال کلی غیرفعال است — در بخش رویدادها فعال کنید';
}

if(($resolved['mode'] ?? '') === 'otp'){
    $report['hints'][] = 'حالت otp است؛ برای تیکت باید simple باشد';
}

if(($config['provider'] ?? '') === 'melipayamak_console' && trim((string)($config['sender'] ?? '')) === ''){
    $report['hints'][] = 'شماره خط فرستنده (from) خالی است';
}

if($report['pending_count'] > 0 && !$report['is_ready']){
    $report['hints'][] = 'پیامک در صف است ولی ارسال کلی یا API آماده نیست';
}

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
