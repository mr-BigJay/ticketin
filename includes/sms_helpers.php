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
    ];
}

function sms_message_templates(): array
{
    return [
        'ticket_created_user' => 'تیکتین: تیکت شما با کد {tracking_code} ثبت شد.',
        'ticket_reply_admin' => 'تیکتین: پاسخ جدید برای تیکت {tracking_code}.',
        'ticket_reply_user' => 'تیکتین: پاسخ جدید کاربر در تیکت {tracking_code}.',
        'ticket_closed_user' => 'تیکتین: تیکت {tracking_code} توسط شما بسته شد.',
        'ticket_closed_admin' => 'تیکتین: تیکت {tracking_code} توسط پشتیبان بسته شد.',
        'ticket_reopened' => 'تیکتین: تیکت {tracking_code} دوباره باز شد.',
        'ticket_new_admin' => 'تیکتین: تیکت جدید {tracking_code} در {category}.',
    ];
}

function sms_local_config(): array
{
    static $config = null;

    if($config !== null){
        return $config;
    }

    $defaults = [
        'provider' => 'generic',
        'mode' => 'simple',
        'api_url' => '',
        'api_token' => '',
        'method' => 'POST',
        'timeout' => 15,
        'sender' => '',
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

    $localFile = __DIR__ . '/sms.local.php';

    if(is_file($localFile)){
        $loaded = require $localFile;

        if(is_array($loaded)){
            $defaults = array_replace_recursive($defaults, $loaded);
        }
    }

    $config = $defaults;

    return $config;
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
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

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
            '#/api/send/(otp|simple)/([a-f0-9]+)#i',
            $apiUrl,
            $matches
        )
    ){
        $mode = strtolower($matches[1]);
        $token = $matches[2];
    }

    if(!in_array($mode, ['simple', 'otp'], true)){
        $mode = 'simple';
    }

    $url = $token !== ''
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

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
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

    $status = trim((string)($decoded['status'] ?? ''));

    if($status !== ''){
        return [
            'ok' => false,
            'error' => $status,
        ];
    }

    if($mode === 'otp'){
        $code = trim((string)($decoded['code'] ?? ''));

        if($code === ''){
            return [
                'ok' => false,
                'error' => 'کد OTP از سرویس دریافت نشد',
            ];
        }

        return ['ok' => true, 'error' => ''];
    }

    $recId = trim((string)($decoded['recId'] ?? ''));

    if($recId === '' || $recId === '0'){
        return [
            'ok' => false,
            'error' => 'شناسه ارسال از ملی‌پیامک دریافت نشد',
        ];
    }

    return ['ok' => true, 'error' => ''];
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
    }else{
        $sender = trim((string)($config['sender'] ?? ''));

        if($sender === ''){
            return [
                'ok' => false,
                'error' => 'شماره خط فرستنده در sms.local.php تنظیم نشده است',
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
        return [
            'ok' => false,
            'error' => $result['error'] ?: 'خطا در ارسال به ملی‌پیامک',
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
    $message = sms_render_message($eventKey, $context);
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
    if(!sms_is_ready($pdo)){
        return [
            'ok' => false,
            'error' => 'پیامک سراسری غیرفعال است یا API تنظیم نشده',
        ];
    }

    $mobile = sms_normalize_mobile($mobile);

    if(!$mobile){
        return [
            'ok' => false,
            'error' => 'شماره موبایل معتبر نیست',
        ];
    }

    $result = sms_send_via_api(
        $mobile,
        'تیکتین: این یک پیامک آزمایشی است.'
    );

    sms_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO sms_queue
        (event_key, mobile, message, status, attempts, last_error, provider_response, sent_at)
        VALUES
        ('test', ?, 'تیکتین: این یک پیامک آزمایشی است.', ?, 1, ?, ?, CASE WHEN ? = 'sent' THEN NOW() ELSE NULL END)
    ");

    $stmt->execute([
        $mobile,
        $result['ok'] ? 'sent' : 'failed',
        $result['ok'] ? null : $result['error'],
        mb_substr((string)$result['response'], 0, 2000),
        $result['ok'] ? 'sent' : 'failed',
    ]);

    return $result;
}
