<?php

function sms_event_catalog(): array
{
    return [
        'ticket_created_user' => [
            'label' => 'ثبت تیکت — پیامک به کاربر',
            'description' => 'بعد از ثبت موفق تیکت جدید',
            'audience' => 'user',
        ],
        'ticket_reply_admin' => [
            'label' => 'پاسخ پشتیبان — پیامک به کاربر',
            'description' => 'وقتی ادمین به تیکت پاسخ می‌دهد',
            'audience' => 'user',
        ],
        'ticket_reply_user' => [
            'label' => 'پاسخ کاربر — پیامک به پشتیبان',
            'description' => 'وقتی کاربر به تیکت پاسخ می‌دهد',
            'audience' => 'admin',
        ],
        'ticket_closed_user' => [
            'label' => 'بستن توسط کاربر — پیامک به کاربر',
            'description' => 'وقتی کاربر تیکت را می‌بندد',
            'audience' => 'user',
        ],
        'ticket_closed_admin' => [
            'label' => 'بستن توسط پشتیبان — پیامک به کاربر',
            'description' => 'وقتی ادمین تیکت را می‌بندد',
            'audience' => 'user',
        ],
        'ticket_reopened' => [
            'label' => 'بازگشایی تیکت — پیامک به کاربر',
            'description' => 'وقتی تیکت دوباره باز می‌شود',
            'audience' => 'user',
        ],
        'ticket_new_admin' => [
            'label' => 'تیکت جدید — پیامک به پشتیبان',
            'description' => 'وقتی کاربر تیکت جدید ثبت می‌کند',
            'audience' => 'admin',
        ],
        'user_approved' => [
            'label' => 'تایید کاربر — پیامک به کاربر',
            'description' => 'وقتی ادمین حساب کاربر را تایید می‌کند',
            'audience' => 'user',
        ],
    ];
}

function sms_message_templates(): array
{
    return [
        'ticket_created_user' => 'تیکتین: تیکت شما با کد {tracking_code} ثبت شد.',
        'ticket_reply_admin' => 'همکار گرامی ، به تیکت شما با شماره پیگیری {tracking_code} پاسخ داده شد.',
        'ticket_reply_user' => 'تیکتین: پاسخ جدید کاربر در تیکت {tracking_code}.',
        'ticket_closed_user' => 'تیکتین: تیکت {tracking_code} توسط شما بسته شد.',
        'ticket_closed_admin' => 'تیکتین: تیکت {tracking_code} توسط پشتیبان بسته شد.',
        'ticket_reopened' => 'تیکتین: تیکت {tracking_code} دوباره باز شد.',
        'ticket_new_admin' => 'تیکتین: تیکت جدید {tracking_code} در {category}.',
        'user_approved' => 'همکار گرامی ، حساب کاربری شما در تیکتین تایید شد.',
    ];
}

function sms_default_api_config(): array
{
    return [
        'provider' => 'melipayamak_console',
        'mode' => 'shared',
        'api_url' => '',
        'api_token' => '',
        'method' => 'POST',
        'timeout' => 15,
        'sender' => '',
        'body_id' => 0,
        'test_args' => 'تست',
        'event_patterns' => sms_default_event_patterns(),
        'username' => '',
        'password' => '',
        'headers' => [],
        'json' => true,
        'verify_ssl' => true,
        'fields' => [
            'mobile' => 'mobile',
            'message' => 'message',
            'sender' => 'sender',
        ],
    ];
}

function sms_default_event_patterns(): array
{
    $patterns = [];

    foreach(sms_shared_default_args_map() as $eventKey => $args){
        $patterns[$eventKey] = [
            'body_id' => 0,
            'args' => $args,
        ];
    }

    return $patterns;
}

function sms_shared_default_args_map(): array
{
    return [
        'ticket_created_user' => ['{tracking_code}'],
        'ticket_reply_admin' => ['{tracking_code}'],
        'ticket_reply_user' => ['{tracking_code}'],
        'ticket_closed_user' => ['{tracking_code}'],
        'ticket_closed_admin' => ['{tracking_code}'],
        'ticket_reopened' => ['{tracking_code}'],
        'ticket_new_admin' => ['{tracking_code}', '{category}'],
        'user_approved' => ['عزیز'],
    ];
}

function sms_invalidate_config_cache(): void
{
    $GLOBALS['__sms_config_cache'] = null;
}

function sms_local_config(): array
{
    if(
        array_key_exists('__sms_config_cache', $GLOBALS)
        &&
        is_array($GLOBALS['__sms_config_cache'])
    ){
        return $GLOBALS['__sms_config_cache'];
    }

    $config = sms_default_api_config();
    $localFile = __DIR__ . '/sms.local.php';

    if(is_file($localFile)){
        $loaded = require $localFile;

        if(is_array($loaded)){
            $config = array_replace_recursive($config, $loaded);
        }
    }

    global $pdo;

    if(isset($pdo) && $pdo instanceof PDO){
        $dbConfig = sms_api_config_from_db($pdo);

        if($dbConfig !== []){
            $config = array_replace_recursive($config, $dbConfig);
        }
    }

    $GLOBALS['__sms_config_cache'] = $config;

    return $config;
}

function sms_api_config_from_db(PDO $pdo): array
{
    sms_ensure_schema($pdo);

    $stmt = $pdo->query("
        SELECT api_config_json
        FROM sms_settings
        WHERE id=1
        LIMIT 1
    ");

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $raw = trim((string)($row['api_config_json'] ?? ''));

    if($raw === ''){
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function sms_api_config_for_form(PDO $pdo): array
{
    $config = array_replace(
        sms_default_api_config(),
        sms_api_config_from_db($pdo)
    );
    $token = trim((string)($config['api_token'] ?? ''));

    $config['api_token_masked'] = $token !== ''
        ? sms_mask_secret($token)
        : '';
    $config['api_token'] = '';
    $config['has_saved_token'] = $token !== '';
    $password = trim((string)($config['password'] ?? ''));
    $config['password'] = '';
    $config['has_saved_password'] = $password !== '';
    $config['source'] = sms_api_config_from_db($pdo) !== []
        ? 'database'
        : (is_file(__DIR__ . '/sms.local.php') ? 'file' : 'none');

    return $config;
}

function sms_normalize_event_patterns($raw): array
{
    $defaults = sms_default_event_patterns();
    $normalized = $defaults;

    if(!is_array($raw)){
        return $normalized;
    }

    foreach($defaults as $eventKey => $defaultPattern){
        $item = $raw[$eventKey] ?? [];

        if(!is_array($item)){
            continue;
        }

        $bodyId = max(0, (int)($item['body_id'] ?? 0));
        $args = $item['args'] ?? $defaultPattern['args'];

        if(is_string($args)){
            $args = array_values(array_filter(array_map(
                'trim',
                preg_split('/\s*,\s*/', $args) ?: []
            )));
        }

        if(!is_array($args) || $args === []){
            $args = $defaultPattern['args'];
        }

        $normalized[$eventKey] = [
            'body_id' => $bodyId,
            'args' => array_values($args),
        ];
    }

    return $normalized;
}

function sms_event_pattern(string $eventKey, ?array $config = null): array
{
    $config = $config ?? sms_local_config();
    $patterns = sms_normalize_event_patterns($config['event_patterns'] ?? []);
    $pattern = $patterns[$eventKey] ?? [
        'body_id' => 0,
        'args' => ['{tracking_code}'],
    ];
    $bodyId = (int)($pattern['body_id'] ?? 0);

    if($bodyId < 1){
        $bodyId = max(0, (int)($config['body_id'] ?? 0));
    }

    return [
        'body_id' => $bodyId,
        'args' => $pattern['args'] ?? ['{tracking_code}'],
    ];
}

function sms_render_args(array $templates, array $context): array
{
    $replacements = [
        '{tracking_code}' => (string)($context['tracking_code'] ?? ''),
        '{title}' => (string)($context['title'] ?? ''),
        '{category}' => (string)($context['category'] ?? ''),
        '{status}' => (string)($context['status'] ?? ''),
        '{fullname}' => (string)($context['fullname'] ?? ''),
        '{job_title}' => (string)($context['job_title'] ?? ''),
    ];
    $args = [];

    foreach($templates as $template){
        $args[] = strtr((string)$template, $replacements);
    }

    return $args;
}

function sms_uses_shared_mode(?array $config = null): bool
{
    $config = $config ?? sms_local_config();

    if((string)($config['provider'] ?? '') !== 'melipayamak_console'){
        return false;
    }

    $resolved = sms_melipayamak_resolve_config($config);

    return ($resolved['mode'] ?? '') === 'shared';
}

function sms_build_queue_message(string $eventKey, array $context): string
{
    $config = sms_local_config();

    if(sms_uses_shared_mode($config)){
        $pattern = sms_event_pattern($eventKey, $config);
        $args = sms_render_args($pattern['args'], $context);

        return json_encode([
            'type' => 'shared',
            'bodyId' => (int)$pattern['body_id'],
            'args' => $args,
            'label' => sms_render_message($eventKey, $context),
        ], JSON_UNESCAPED_UNICODE);
    }

    return sms_render_message($eventKey, $context);
}

function sms_decode_shared_payload(string $message, array $config): ?array
{
    $decoded = json_decode($message, true);

    if(
        is_array($decoded)
        &&
        ($decoded['type'] ?? '') === 'shared'
        &&
        (int)($decoded['bodyId'] ?? 0) > 0
    ){
        return [
            'bodyId' => (int)$decoded['bodyId'],
            'args' => array_values(array_map('strval', $decoded['args'] ?? [])),
        ];
    }

    $bodyId = max(0, (int)($config['body_id'] ?? 0));

    if($bodyId < 1){
        return null;
    }

    $testArgs = trim((string)($config['test_args'] ?? 'تست'));

    return [
        'bodyId' => $bodyId,
        'args' => $testArgs !== ''
            ? array_values(array_filter(array_map(
                'trim',
                preg_split('/\s*,\s*/', $testArgs) ?: []
            )))
            : ['تست'],
    ];
}

function sms_mask_secret(string $value): string
{
    $length = strlen($value);

    if($length <= 4){
        return str_repeat('*', $length);
    }

    return substr($value, 0, 4) . str_repeat('*', max(4, $length - 8)) . substr($value, -4);
}

function sms_api_config_save(PDO $pdo, array $input): ?string
{
    sms_ensure_schema($pdo);

    $current = array_replace(
        sms_default_api_config(),
        sms_api_config_from_db($pdo)
    );
    $provider = trim((string)($input['provider'] ?? $current['provider']));

    if(!in_array($provider, ['melipayamak_console', 'generic'], true)){
        return 'سرویس‌دهنده پیامک نامعتبر است';
    }

    $config = [
        'provider' => $provider,
        'mode' => in_array(
            trim((string)($input['mode'] ?? 'shared')),
            ['simple', 'otp', 'shared'],
            true
        ) ? trim((string)$input['mode']) : 'shared',
        'api_url' => trim((string)($input['api_url'] ?? '')),
        'api_token' => trim((string)($input['api_token'] ?? '')),
        'method' => strtoupper(trim((string)($input['method'] ?? 'POST'))) ?: 'POST',
        'timeout' => max(5, (int)($input['timeout'] ?? 15)),
        'sender' => trim((string)($input['sender'] ?? '')),
        'body_id' => max(0, (int)($input['body_id'] ?? 0)),
        'test_args' => trim((string)($input['test_args'] ?? 'تست')),
        'username' => trim((string)($input['username'] ?? '')),
        'password' => trim((string)($input['password'] ?? '')),
        'json' => !empty($input['json']),
        'verify_ssl' => !isset($input['verify_ssl']) || !empty($input['verify_ssl']),
        'fields' => $current['fields'],
        'event_patterns' => sms_normalize_event_patterns(
            $input['event_patterns'] ?? ($current['event_patterns'] ?? [])
        ),
    ];

    if($config['api_token'] === '' && trim((string)($current['api_token'] ?? '')) !== ''){
        $config['api_token'] = trim((string)$current['api_token']);
    }

    if($config['password'] === '' && trim((string)($current['password'] ?? '')) !== ''){
        $config['password'] = trim((string)$current['password']);
    }

    if($provider === 'melipayamak_console'){
        $resolved = sms_melipayamak_resolve_config($config);

        if($resolved['token'] === ''){
            return 'توکن API ملی‌پیامک را وارد کنید';
        }

        if($config['mode'] === 'shared'){
            if($config['body_id'] < 1){
                return 'کد الگوی خط خدماتی (bodyId) را وارد کنید';
            }
        }elseif($config['mode'] !== 'otp' && $config['sender'] === ''){
            return 'شماره خط فرستنده را وارد کنید';
        }
    }elseif($config['api_url'] === ''){
        return 'آدرس API را وارد کنید';
    }

    $stmt = $pdo->prepare("
        UPDATE sms_settings
        SET api_config_json=?
        WHERE id=1
    ");

    $stmt->execute([
        json_encode($config, JSON_UNESCAPED_UNICODE),
    ]);

    sms_invalidate_config_cache();

    return null;
}

function sms_ensure_schema(PDO $pdo): void
{
    static $done = false;

    if($done){
        return;
    }

    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sms_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            master_enabled TINYINT(1) NOT NULL DEFAULT 0,
            event_flags TEXT NOT NULL,
            admin_notify_mobiles TEXT NULL,
            api_config_json TEXT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columnStmt = $pdo->query("
        SHOW COLUMNS FROM sms_settings LIKE 'api_config_json'
    ");

    if(!$columnStmt->fetch(PDO::FETCH_ASSOC)){
        $pdo->exec("
            ALTER TABLE sms_settings
            ADD COLUMN api_config_json TEXT NULL
            AFTER admin_notify_mobiles
        ");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sms_queue (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            event_key VARCHAR(60) NOT NULL,
            mobile VARCHAR(11) NOT NULL,
            message TEXT NOT NULL,
            user_id INT NULL,
            ticket_id INT NULL,
            status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            provider_response TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at DATETIME NULL,
            INDEX idx_sms_queue_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $count = (int)$pdo
        ->query('SELECT COUNT(*) FROM sms_settings')
        ->fetchColumn();

    if($count < 1){
        $flags = [];

        foreach(sms_event_catalog() as $eventKey => $meta){
            $flags[$eventKey] = false;
        }

        $stmt = $pdo->prepare("
            INSERT INTO sms_settings
            (id, master_enabled, event_flags, admin_notify_mobiles)
            VALUES
            (1, 0, ?, '')
        ");

        $stmt->execute([
            json_encode($flags, JSON_UNESCAPED_UNICODE),
        ]);
    }
}

function sms_default_event_flags(): array
{
    $flags = [];

    foreach(sms_event_catalog() as $eventKey => $meta){
        $flags[$eventKey] = false;
    }

    return $flags;
}

function sms_settings_get(PDO $pdo): array
{
    sms_ensure_schema($pdo);

    $stmt = $pdo->query("
        SELECT master_enabled, event_flags, admin_notify_mobiles
        FROM sms_settings
        WHERE id=1
        LIMIT 1
    ");

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $flags = sms_default_event_flags();

    if(!empty($row['event_flags'])){
        $decoded = json_decode((string)$row['event_flags'], true);

        if(is_array($decoded)){
            foreach($flags as $eventKey => $enabled){
                $flags[$eventKey] = !empty($decoded[$eventKey]);
            }
        }
    }

    return [
        'master_enabled' => !empty($row['master_enabled']),
        'event_flags' => $flags,
        'admin_notify_mobiles' => trim((string)($row['admin_notify_mobiles'] ?? '')),
        'api_configured' => sms_api_configured(),
        'api_source' => sms_api_config_from_db($pdo) !== []
            ? 'database'
            : (is_file(__DIR__ . '/sms.local.php') ? 'file' : 'none'),
        'local_config_exists' => is_file(__DIR__ . '/sms.local.php'),
    ];
}

function sms_settings_save(
    PDO $pdo,
    bool $masterEnabled,
    array $eventFlags,
    string $adminNotifyMobiles
): ?string
{
    sms_ensure_schema($pdo);

    $normalizedFlags = sms_default_event_flags();

    foreach($normalizedFlags as $eventKey => $enabled){
        $normalizedFlags[$eventKey] = !empty($eventFlags[$eventKey]);
    }

    $mobiles = [];

    foreach(preg_split('/[\s,;]+/', $adminNotifyMobiles) ?: [] as $part){
        $mobile = sms_normalize_mobile($part);

        if($mobile){
            $mobiles[] = $mobile;
        }
    }

    $stmt = $pdo->prepare("
        UPDATE sms_settings
        SET
            master_enabled=?,
            event_flags=?,
            admin_notify_mobiles=?
        WHERE id=1
    ");

    $stmt->execute([
        $masterEnabled ? 1 : 0,
        json_encode($normalizedFlags, JSON_UNESCAPED_UNICODE),
        implode(',', array_values(array_unique($mobiles))),
    ]);

    return null;
}

function sms_api_configured(): bool
{
    $config = sms_local_config();
    $provider = (string)($config['provider'] ?? 'generic');

    if($provider === 'melipayamak_console'){
        $resolved = sms_melipayamak_resolve_config($config);

        return $resolved['token'] !== '';
    }

    return trim((string)($config['api_url'] ?? '')) !== '';
}

function sms_melipayamak_resolve_config(array $config): array
{
    $mode = (string)($config['mode'] ?? 'simple');
    $token = trim((string)($config['api_token'] ?? ''));
    $apiUrl = trim((string)($config['api_url'] ?? ''));

    if(
        $apiUrl !== ''
        &&
        preg_match(
            '#/api/send/(otp|simple|shared)/([a-f0-9]{32})#i',
            $apiUrl,
            $matches
        )
    ){
        $mode = strtolower($matches[1]);
        $token = $matches[2];
    }elseif(
        $token !== ''
        &&
        preg_match(
            '#/api/send/(otp|simple|shared)/([a-f0-9]{32})#i',
            $token,
            $matches
        )
    ){
        // اگر آدرس کامل در فیلد توکن paste شده، فقط توکن را بگیر؛ mode از تنظیمات پنل
        $token = $matches[2];
    }

    if(!in_array($mode, ['simple', 'otp', 'shared'], true)){
        $mode = 'shared';
    }

    $url = $token !== '' && preg_match('/^[a-f0-9]{32}$/i', $token)
        ? 'https://console.melipayamak.com/api/send/' . $mode . '/' . $token
        : '';

    return [
        'mode' => $mode,
        'token' => $token,
        'url' => $url,
    ];
}

function sms_http_post_json(
    string $url,
    array $payload,
    int $timeout,
    bool $verifySsl = true
): array
{
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json; charset=utf-8',
    ];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => array_merge($headers, [
            'Content-Length: ' . strlen($body),
        ]),
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if($response === false){
        return [
            'ok' => false,
            'error' => $curlError !== '' ? $curlError : 'خطا در ارتباط با API پیامک',
            'response' => '',
            'http_code' => $httpCode,
        ];
    }

    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'error' => $httpCode >= 200 && $httpCode < 300 ? '' : 'کد HTTP ' . $httpCode,
        'response' => (string)$response,
        'http_code' => $httpCode,
    ];
}

function sms_melipayamak_parse_success(string $mode, string $responseBody): array
{
    $decoded = json_decode($responseBody, true);

    if(!is_array($decoded)){
        return [
            'ok' => false,
            'error' => 'پاسخ نامعتبر از ملی‌پیامک',
        ];
    }

    $recId = trim((string)($decoded['recId'] ?? ''));
    $status = trim((string)($decoded['status'] ?? ''));

    if($mode === 'otp'){
        $code = trim((string)($decoded['code'] ?? ''));

        if($code === ''){
            return [
                'ok' => false,
                'error' => $status !== '' ? $status : 'کد OTP از سرویس دریافت نشد',
            ];
        }

        return ['ok' => true, 'error' => ''];
    }

    if($recId !== '' && $recId !== '0'){
        return ['ok' => true, 'error' => ''];
    }

    if($status !== ''){
        return [
            'ok' => false,
            'error' => $status,
        ];
    }

    return [
        'ok' => false,
        'error' => 'شناسه ارسال از ملی‌پیامک دریافت نشد',
    ];
}

function sms_send_melipayamak_console(
    string $mobile,
    string $message,
    array $config
): array
{
    $resolved = sms_melipayamak_resolve_config($config);

    if($resolved['url'] === ''){
        return [
            'ok' => false,
            'error' => 'توکن API ملی‌پیامک تنظیم نشده است',
            'response' => '',
        ];
    }

    if($resolved['mode'] === 'otp'){
        $payload = [
            'to' => $mobile,
        ];
    }elseif($resolved['mode'] === 'shared'){
        $shared = sms_decode_shared_payload($message, $config);

        if(!$shared){
            return [
                'ok' => false,
                'error' => 'کد الگوی خط خدماتی (bodyId) تنظیم نشده است',
                'response' => '',
            ];
        }

        $payload = [
            'bodyId' => $shared['bodyId'],
            'to' => $mobile,
            'args' => $shared['args'],
        ];
    }else{
        $sender = trim((string)($config['sender'] ?? ''));

        if($sender === ''){
            return [
                'ok' => false,
                'error' => 'شماره خط فرستنده در تنظیمات API تنظیم نشده است',
                'response' => '',
            ];
        }

        $payload = [
            'to' => $mobile,
            'from' => $sender,
            'text' => $message,
        ];
    }

    $timeout = max(5, (int)($config['timeout'] ?? 15));
    $verifySsl = !isset($config['verify_ssl']) || !empty($config['verify_ssl']);
    $result = sms_http_post_json(
        $resolved['url'],
        $payload,
        $timeout,
        $verifySsl
    );

    if(!$result['ok']){
        $hint = $resolved['mode'] === 'otp'
            ? ' (برای تیکت باید simple یا shared باشد، نه otp)'
            : '';

        return [
            'ok' => false,
            'error' => ($result['error'] ?: 'خطا در ارسال به ملی‌پیامک') . $hint,
            'response' => $result['response'],
        ];
    }

    $parsed = sms_melipayamak_parse_success(
        $resolved['mode'],
        $result['response']
    );

    return [
        'ok' => $parsed['ok'],
        'error' => $parsed['error'],
        'response' => $result['response'],
    ];
}

function sms_send_generic(
    string $mobile,
    string $message,
    array $config
): array
{
    $apiUrl = trim((string)($config['api_url'] ?? ''));

    if($apiUrl === ''){
        return [
            'ok' => false,
            'error' => 'آدرس API پیامک تنظیم نشده است',
            'response' => '',
        ];
    }

    $fields = $config['fields'] ?? [];
    $payload = [];

    if(!empty($fields['mobile'])){
        $payload[$fields['mobile']] = $mobile;
    }

    if(!empty($fields['message'])){
        $payload[$fields['message']] = $message;
    }

    if(
        !empty($fields['sender'])
        &&
        trim((string)($config['sender'] ?? '')) !== ''
    ){
        $payload[$fields['sender']] = $config['sender'];
    }

    $method = strtoupper((string)($config['method'] ?? 'POST'));
    $timeout = max(5, (int)($config['timeout'] ?? 15));
    $headers = ['Accept: application/json'];

    foreach(($config['headers'] ?? []) as $headerName => $headerValue){
        $headers[] = $headerName . ': ' . $headerValue;
    }

    if(
        trim((string)($config['username'] ?? '')) !== ''
        &&
        trim((string)($config['password'] ?? '')) !== ''
    ){
        $headers[] = 'Authorization: Basic ' . base64_encode(
            $config['username'] . ':' . $config['password']
        );
    }

    $body = '';

    if(!empty($config['json'])){
        $headers[] = 'Content-Type: application/json; charset=utf-8';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }else{
        $headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
        $body = http_build_query($payload);
    }

    $verifySsl = !isset($config['verify_ssl']) || !empty($config['verify_ssl']);
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ]);

    if($method !== 'GET'){
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if($response === false){
        return [
            'ok' => false,
            'error' => $curlError !== '' ? $curlError : 'خطا در ارتباط با API پیامک',
            'response' => '',
        ];
    }

    $ok = $httpCode >= 200 && $httpCode < 300;

    return [
        'ok' => $ok,
        'error' => $ok ? '' : 'کد HTTP ' . $httpCode,
        'response' => (string)$response,
    ];
}

function sms_is_ready(PDO $pdo): bool
{
    $settings = sms_settings_get($pdo);

    return $settings['master_enabled'] && sms_api_configured();
}

function sms_is_event_enabled(PDO $pdo, string $eventKey): bool
{
    if(!sms_is_ready($pdo)){
        return false;
    }

    $settings = sms_settings_get($pdo);

    return !empty($settings['event_flags'][$eventKey]);
}

function sms_normalize_mobile(?string $mobile): ?string
{
    $digits = preg_replace('/\D+/', '', (string)$mobile);

    if(
        strpos($digits, '98') === 0
        &&
        strlen($digits) === 12
    ){
        $digits = '0' . substr($digits, 2);
    }

    if(
        strlen($digits) === 10
        &&
        ($digits[0] ?? '') === '9'
    ){
        $digits = '0' . $digits;
    }

    if(preg_match('/^09\d{9}$/', $digits)){
        return $digits;
    }

    return null;
}

function sms_mask_mobile(string $mobile): string
{
    if(strlen($mobile) !== 11){
        return $mobile;
    }

    return substr($mobile, 0, 4) . '***' . substr($mobile, -4);
}

function sms_render_message(string $eventKey, array $context): string
{
    $templates = sms_message_templates();
    $template = $templates[$eventKey] ?? 'تیکتین: اطلاع‌رسانی تیکت {tracking_code}.';

    $replacements = [
        '{tracking_code}' => (string)($context['tracking_code'] ?? ''),
        '{title}' => (string)($context['title'] ?? ''),
        '{category}' => (string)($context['category'] ?? ''),
        '{status}' => (string)($context['status'] ?? ''),
        '{fullname}' => (string)($context['fullname'] ?? ''),
        '{job_title}' => (string)($context['job_title'] ?? ''),
    ];

    return strtr($template, $replacements);
}

function sms_queue_add(
    PDO $pdo,
    string $eventKey,
    string $mobile,
    string $message,
    ?int $userId = null,
    ?int $ticketId = null
): bool
{
    sms_ensure_schema($pdo);

    $mobile = sms_normalize_mobile($mobile) ?? '';

    if($mobile === '' || trim($message) === ''){
        return false;
    }

    $stmt = $pdo->prepare("
        INSERT INTO sms_queue
        (
            event_key,
            mobile,
            message,
            user_id,
            ticket_id,
            status
        )
        VALUES
        (?, ?, ?, ?, ?, 'pending')
    ");

    $stmt->execute([
        $eventKey,
        $mobile,
        trim($message),
        $userId,
        $ticketId,
    ]);

    return true;
}

function sms_send_via_api(string $mobile, string $message): array
{
    $config = sms_local_config();
    $provider = (string)($config['provider'] ?? 'generic');

    if($provider === 'melipayamak_console'){
        return sms_send_melipayamak_console($mobile, $message, $config);
    }

    return sms_send_generic($mobile, $message, $config);
}

function sms_process_queue(PDO $pdo, int $limit = 30): array
{
    sms_ensure_schema($pdo);

    if(!sms_is_ready($pdo)){
        return ['processed' => 0, 'sent' => 0, 'failed' => 0];
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM sms_queue
        WHERE status='pending'
        AND attempts < 3
        ORDER BY id ASC
        LIMIT ?
    ");

    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sent = 0;
    $failed = 0;

    foreach($rows as $row){
        $result = sms_send_via_api(
            (string)$row['mobile'],
            (string)$row['message']
        );

        $update = $pdo->prepare("
            UPDATE sms_queue
            SET
                attempts = attempts + 1,
                status = ?,
                last_error = ?,
                provider_response = ?,
                sent_at = CASE WHEN ? = 'sent' THEN NOW() ELSE sent_at END
            WHERE id = ?
        ");

        if($result['ok']){
            $sent++;
            $update->execute([
                'sent',
                null,
                mb_substr((string)$result['response'], 0, 2000),
                'sent',
                $row['id'],
            ]);
        }else{
            $failed++;
            $status = ((int)$row['attempts'] + 1) >= 3 ? 'failed' : 'pending';
            $update->execute([
                $status,
                mb_substr((string)$result['error'], 0, 1000),
                mb_substr((string)$result['response'], 0, 2000),
                'failed',
                $row['id'],
            ]);
        }
    }

    return [
        'processed' => count($rows),
        'sent' => $sent,
        'failed' => $failed,
    ];
}

function sms_ticket_context(PDO $pdo, int $ticketId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            t.*,
            u.mobile AS user_mobile,
            u.fullname AS user_fullname
        FROM tickets t
        INNER JOIN users u ON t.user_id = u.id
        WHERE t.id=?
        LIMIT 1
    ");

    $stmt->execute([$ticketId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function sms_admin_notify_mobiles(PDO $pdo): array
{
    $settings = sms_settings_get($pdo);
    $mobiles = [];

    foreach(explode(',', $settings['admin_notify_mobiles']) as $part){
        $mobile = sms_normalize_mobile($part);

        if($mobile){
            $mobiles[] = $mobile;
        }
    }

    return array_values(array_unique($mobiles));
}

function sms_dispatch_ticket_event(
    PDO $pdo,
    string $eventKey,
    int $ticketId,
    array $extra = []
): void
{
    if(!sms_is_event_enabled($pdo, $eventKey)){
        return;
    }

    $ticket = sms_ticket_context($pdo, $ticketId);

    if(!$ticket){
        return;
    }

    $context = array_merge($ticket, $extra);
    $message = sms_build_queue_message($eventKey, $context);
    $catalog = sms_event_catalog();
    $audience = $catalog[$eventKey]['audience'] ?? 'user';

    if($audience === 'admin'){
        foreach(sms_admin_notify_mobiles($pdo) as $mobile){
            sms_queue_add(
                $pdo,
                $eventKey,
                $mobile,
                $message,
                null,
                $ticketId
            );
        }

        return;
    }

    $mobile = sms_normalize_mobile($ticket['user_mobile'] ?? null);

    if(!$mobile){
        return;
    }

    sms_queue_add(
        $pdo,
        $eventKey,
        $mobile,
        $message,
        (int)($ticket['user_id'] ?? 0),
        $ticketId
    );
}

function sms_dispatch_user_event(
    PDO $pdo,
    string $eventKey,
    int $userId,
    array $extra = []
): void
{
    if(!sms_is_event_enabled($pdo, $eventKey)){
        return;
    }

    $stmt = $pdo->prepare("
        SELECT id, fullname, mobile, job_title
        FROM users
        WHERE id=?
        LIMIT 1
    ");

    $stmt->execute([$userId]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user){
        return;
    }

    $mobile = sms_normalize_mobile($user['mobile'] ?? null);

    if(!$mobile){
        return;
    }

    $context = array_merge($user, $extra);
    $message = sms_build_queue_message($eventKey, $context);

    sms_queue_add(
        $pdo,
        $eventKey,
        $mobile,
        $message,
        (int)$user['id'],
        null
    );
}

function sms_count_bulk_user_approved_candidates(PDO $pdo): array
{
    sms_ensure_schema($pdo);

    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total_active,
            SUM(
                CASE
                    WHEN u.mobile IS NULL OR TRIM(u.mobile) = '' THEN 1
                    ELSE 0
                END
            ) AS missing_mobile,
            SUM(
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM sms_queue q
                        WHERE q.event_key = 'user_approved'
                        AND q.user_id = u.id
                        AND q.status IN ('sent', 'pending')
                    ) THEN 1
                    ELSE 0
                END
            ) AS already_notified
        FROM users u
        WHERE u.role = 'user'
        AND u.status = 'active'
    ");

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $total = (int)($row['total_active'] ?? 0);
    $missingMobile = (int)($row['missing_mobile'] ?? 0);
    $alreadyNotified = (int)($row['already_notified'] ?? 0);
    $eligible = max(0, $total - $missingMobile - $alreadyNotified);

    return [
        'total_active' => $total,
        'missing_mobile' => $missingMobile,
        'already_notified' => $alreadyNotified,
        'eligible' => $eligible,
    ];
}

function sms_queue_bulk_user_approved(PDO $pdo): array
{
    sms_ensure_schema($pdo);

    if(!sms_api_configured()){
        return [
            'ok' => false,
            'error' => 'اتصال API پیامک تنظیم نشده است',
            'queued' => 0,
            'skipped' => 0,
        ];
    }

    $stmt = $pdo->query("
        SELECT u.id, u.fullname, u.mobile, u.job_title
        FROM users u
        WHERE u.role = 'user'
        AND u.status = 'active'
        ORDER BY u.id ASC
    ");

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $queued = 0;
    $skipped = 0;

    foreach($users as $user){
        $userId = (int)($user['id'] ?? 0);

        if($userId < 1){
            $skipped++;
            continue;
        }

        $check = $pdo->prepare("
            SELECT id
            FROM sms_queue
            WHERE event_key='user_approved'
            AND user_id=?
            AND status IN ('sent', 'pending')
            LIMIT 1
        ");

        $check->execute([$userId]);

        if($check->fetch()){
            $skipped++;
            continue;
        }

        $mobile = sms_normalize_mobile($user['mobile'] ?? null);

        if(!$mobile){
            $skipped++;
            continue;
        }

        $message = sms_build_queue_message('user_approved', $user);

        if(sms_queue_add($pdo, 'user_approved', $mobile, $message, $userId, null)){
            $queued++;
        }else{
            $skipped++;
        }
    }

    return [
        'ok' => true,
        'error' => '',
        'queued' => $queued,
        'skipped' => $skipped,
    ];
}

function sms_recent_logs(PDO $pdo, int $limit = 25): array
{
    sms_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT *
        FROM sms_queue
        ORDER BY id DESC
        LIMIT ?
    ");

    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sms_send_test(PDO $pdo, string $mobile): array
{
    if(!sms_api_configured()){
        return [
            'ok' => false,
            'error' => 'اتصال API پیامک تنظیم نشده است',
        ];
    }

    $mobile = sms_normalize_mobile($mobile);

    if(!$mobile){
        return [
            'ok' => false,
            'error' => 'شماره موبایل معتبر نیست',
        ];
    }

    $config = sms_local_config();
    $testMessage = sms_uses_shared_mode($config)
        ? json_encode([
            'type' => 'shared',
            'bodyId' => max(1, (int)($config['body_id'] ?? 0)),
            'args' => sms_render_args(
                array_values(array_filter(array_map(
                    'trim',
                    preg_split('/\s*,\s*/', (string)($config['test_args'] ?? 'تست')) ?: []
                ))),
                ['tracking_code' => 'TEST']
            ),
            'label' => 'تیکتین: پیامک آزمایشی',
        ], JSON_UNESCAPED_UNICODE)
        : 'تیکتین: این یک پیامک آزمایشی است.';

    $result = sms_send_via_api(
        $mobile,
        $testMessage
    );

    sms_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO sms_queue
        (event_key, mobile, message, status, attempts, last_error, provider_response, sent_at)
        VALUES
        ('test', ?, ?, ?, 1, ?, ?, CASE WHEN ? = 'sent' THEN NOW() ELSE NULL END)
    ");

    $logMessage = sms_uses_shared_mode($config)
        ? 'تیکتین: پیامک آزمایشی (shared)'
        : 'تیکتین: این یک پیامک آزمایشی است.';

    $stmt->execute([
        $mobile,
        $logMessage,
        $result['ok'] ? 'sent' : 'failed',
        $result['ok'] ? null : $result['error'],
        mb_substr((string)$result['response'], 0, 2000),
        $result['ok'] ? 'sent' : 'failed',
    ]);

    return $result;
}
