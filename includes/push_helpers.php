<?php

require_once __DIR__ . '/push_vapid.php';

function push_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function push_base64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;

    if($remainder){
        $data .= str_repeat('=', 4 - $remainder);
    }

    $decoded = base64_decode(strtr($data, '-_', '+/'), true);

    return $decoded === false ? '' : $decoded;
}

function push_ensure_schema(PDO $pdo): void
{
    static $done = false;

    if($done){
        return;
    }

    $done = true;

    push_ensure_vapid_keys();

    try{
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_push_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                endpoint VARCHAR(768) NOT NULL,
                p256dh VARCHAR(255) NOT NULL,
                auth_key VARCHAR(255) NOT NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_endpoint (endpoint(191)),
                KEY idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }catch(PDOException $e){
    }

    try{
        $pdo->exec("ALTER TABLE reminders ADD COLUMN push_sent_date DATE NULL");
    }catch(PDOException $e){
    }

    try{
        $pdo->exec("ALTER TABLE reminders ADD COLUMN push_sent_slots VARCHAR(31) NULL");
    }catch(PDOException $e){
    }
}

function push_ensure_vapid_keys(): bool
{
    $pemFile = push_vapid_private_pem_path();

    if(is_file($pemFile)){
        return true;
    }

    push_vapid_set_last_error('');

    $pem = push_vapid_generate_pem_via_php();

    if($pem === ''){
        $pem = push_vapid_generate_pem_via_shell();
    }

    if($pem === ''){
        if(push_vapid_last_error() === ''){
            push_vapid_set_last_error(
                'کلید VAPID ساخته نشد. دسترسی نوشتن پوشه storage یا openssl سرور را بررسی کنید.'
            );
        }

        return false;
    }

    return push_vapid_write_pem($pem);
}

function push_get_vapid_public_key(): string
{
    push_ensure_vapid_keys();

    $pemFile = push_vapid_private_pem_path();

    if(!file_exists($pemFile)){
        return '';
    }

    $privateKey = openssl_pkey_get_private(file_get_contents($pemFile));

    if(!$privateKey){
        return '';
    }

    $details = openssl_pkey_get_details($privateKey);
    $publicKey = "\x04"
        . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
        . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

    return push_base64url_encode($publicKey);
}

function push_save_subscription(PDO $pdo, int $userId, array $subscription): bool
{
    push_ensure_schema($pdo);

    $endpoint = trim((string)($subscription['endpoint'] ?? ''));

    if($endpoint === ''){
        return false;
    }

    $keys = $subscription['keys'] ?? [];
    $p256dh = trim((string)($keys['p256dh'] ?? ''));
    $auth = trim((string)($keys['auth'] ?? ''));

    if($p256dh === '' || $auth === ''){
        return false;
    }

    $stmt = $pdo->prepare("
        INSERT INTO admin_push_subscriptions
        (user_id, endpoint, p256dh, auth_key, user_agent)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            p256dh = VALUES(p256dh),
            auth_key = VALUES(auth_key),
            user_agent = VALUES(user_agent)
    ");

    return $stmt->execute([
        $userId,
        $endpoint,
        $p256dh,
        $auth,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
}

function push_remove_subscription(PDO $pdo, string $endpoint): void
{
    push_ensure_schema($pdo);

    $stmt = $pdo->prepare("DELETE FROM admin_push_subscriptions WHERE endpoint = ?");
    $stmt->execute([$endpoint]);
}

function push_get_subscriptions(PDO $pdo, ?string $adminType = null): array
{
    push_ensure_schema($pdo);

    $sql = "
        SELECT s.*, u.admin_type
        FROM admin_push_subscriptions s
        INNER JOIN users u ON u.id = s.user_id
        WHERE u.role = 'admin'
    ";

    $params = [];

    if($adminType === 'super'){
        $sql .= " AND (u.admin_type = 'super' OR u.admin_type IS NULL OR u.admin_type = '')";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function push_today_jalali_date(): string
{
    require_once __DIR__ . '/jalali.php';

    return jalali_today_for_db();
}

function push_notify_all_admins(
    PDO $pdo,
    string $title,
    string $body,
    string $url,
    string $tag = 'ticketin-admin'
): array {
    return push_notify_admins($pdo, $title, $body, $url, $tag, null);
}

function push_notify_super_admins(
    PDO $pdo,
    string $title,
    string $body,
    string $url,
    string $tag = 'ticketin-admin'
): array {
    return push_notify_admins($pdo, $title, $body, $url, $tag, 'super');
}

function push_notify_user(
    PDO $pdo,
    int $userId,
    string $title,
    string $body,
    string $url,
    string $tag = 'ticketin-admin'
): array {
    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'url' => $url,
        'tag' => $tag,
    ], JSON_UNESCAPED_UNICODE);

    if($payload === false){
        return [
            'sent' => 0,
            'failed' => 0,
            'results' => [],
            'targeted' => 0,
        ];
    }

    push_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT s.*, u.admin_type
        FROM admin_push_subscriptions s
        INNER JOIN users u ON u.id = s.user_id
        WHERE s.user_id = ?
        AND u.role = 'admin'
    ");
    $stmt->execute([$userId]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sendResults = push_send_subscriptions_parallel($pdo, $subscriptions, $payload);
    $sent = 0;
    $failed = 0;

    foreach($sendResults as $row){
        if(($row['status'] ?? 0) >= 200 && ($row['status'] ?? 0) < 300){
            $sent++;
            continue;
        }

        $failed++;
    }

    return [
        'sent' => $sent,
        'failed' => $failed,
        'results' => $sendResults,
        'targeted' => count($subscriptions),
    ];
}

function push_diagnose_subscription(array $subscription, string $payload = '{"title":"test","body":"test"}'): array
{
    $endpoint = trim((string)($subscription['endpoint'] ?? ''));
    $p256dh = trim((string)($subscription['p256dh'] ?? ''));
    $auth = trim((string)($subscription['auth_key'] ?? ''));

    if($endpoint === '' || $p256dh === '' || $auth === ''){
        return [
            'ok' => false,
            'step' => 'subscription',
            'error' => 'اطلاعات اشتراک ناقص است',
        ];
    }

    $privateKey = push_load_vapid_private_key();

    if(!$privateKey){
        return [
            'ok' => false,
            'step' => 'vapid',
            'error' => 'کلید VAPID روی سرور آماده نیست',
        ];
    }

    try{
        $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
        $port = parse_url($endpoint, PHP_URL_PORT);

        if($port){
            $audience .= ':' . $port;
        }

        push_create_vapid_jwt($audience, push_vapid_subject(), $privateKey);
    }catch(Throwable $e){
        return [
            'ok' => false,
            'step' => 'jwt',
            'error' => 'ساخت توکن VAPID ناموفق: ' . $e->getMessage(),
        ];
    }

    try{
        push_encrypt_payload($payload, $p256dh, $auth);
    }catch(Throwable $e){
        return [
            'ok' => false,
            'step' => 'encrypt',
            'error' => $e->getMessage(),
        ];
    }

    return [
        'ok' => true,
        'step' => 'ready',
        'error' => '',
    ];
}

function push_curl_timeout_seconds(): int
{
    return 8;
}

function push_curl_connect_timeout_seconds(): int
{
    return 4;
}

function push_set_last_send_error(string $message): void
{
    $GLOBALS['push_last_send_error'] = $message;
}

function push_last_send_error(): string
{
    return (string)($GLOBALS['push_last_send_error'] ?? '');
}

function push_create_local_ec_key()
{
    $options = [
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ];

    $configPath = push_vapid_openssl_config_path();

    if($configPath){
        $options['config'] = $configPath;
    }

    return openssl_pkey_new($options);
}

function push_human_error_for_status(int $status, string $detail = ''): string
{
    if($status >= 200 && $status < 300){
        return 'موفق';
    }

    if($status === 0){
        return $detail !== ''
            ? 'اتصال برقرار نشد: ' . $detail
            : 'اتصال به سرور اعلان برقرار نشد';
    }

    if(in_array($status, [401, 403], true)){
        return 'کلید VAPID یا اشتراک نامعتبر است — دوباره «فعال‌سازی اعلان» را بزنید';
    }

    if(in_array($status, [404, 410], true)){
        return 'اشتراک منقضی شده — دوباره «فعال‌سازی اعلان» را بزنید';
    }

    return 'خطای HTTP ' . $status;
}

function push_load_vapid_private_key()
{
    if(array_key_exists('push_vapid_private_key_cache', $GLOBALS)){
        return $GLOBALS['push_vapid_private_key_cache'] ?: false;
    }

    $pemFile = push_vapid_private_pem_path();

    if(!is_file($pemFile)){
        $GLOBALS['push_vapid_private_key_cache'] = false;
        return false;
    }

    $GLOBALS['push_vapid_private_key_cache'] = openssl_pkey_get_private(file_get_contents($pemFile)) ?: false;

    return $GLOBALS['push_vapid_private_key_cache'];
}

function push_normalize_ecdh_secret(string $sharedSecret): string
{
    if(strlen($sharedSecret) > 32){
        $sharedSecret = substr($sharedSecret, -32);
    }

    return str_pad($sharedSecret, 32, "\x00", STR_PAD_LEFT);
}

function push_ca_bundle_paths(): array
{
    return [
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/ca-bundle.pem',
        '/usr/local/share/certs/ca-root-nss.crt',
    ];
}

function push_curl_apply_ssl_options($ch): void
{
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    foreach(push_ca_bundle_paths() as $bundle){
        if(is_readable($bundle)){
            curl_setopt($ch, CURLOPT_CAINFO, $bundle);
            return;
        }
    }
}

function push_endpoint_host(string $endpoint): string
{
    $host = (string)parse_url($endpoint, PHP_URL_HOST);

    return $host !== '' ? $host : 'نامشخص';
}

function push_reset_vapid_and_subscriptions(PDO $pdo): bool
{
    push_ensure_schema($pdo);
    push_vapid_delete_all_pem_files();

    try{
        $pdo->exec('DELETE FROM admin_push_subscriptions');
    }catch(PDOException $e){
        return false;
    }

    return push_ensure_vapid_keys();
}

function push_server_environment(): array
{
    $pemPath = push_vapid_private_pem_path();
    $pemFiles = push_vapid_existing_paths();
    $caBundle = '';

    foreach(push_ca_bundle_paths() as $bundle){
        if(is_readable($bundle)){
            $caBundle = $bundle;
            break;
        }
    }

    return [
        'php_version' => PHP_VERSION,
        'openssl_loaded' => extension_loaded('openssl'),
        'curl_loaded' => extension_loaded('curl'),
        'openssl_pkey_derive' => function_exists('openssl_pkey_derive'),
        'curl_multi_init' => function_exists('curl_multi_init'),
        'aes_128_gcm' => in_array('aes-128-gcm', openssl_get_cipher_methods(), true),
        'prime256v1' => in_array('prime256v1', openssl_get_curve_names() ?: [], true),
        'pem_path' => is_file($pemPath) ? $pemPath : '',
        'pem_count' => count($pemFiles),
        'pem_duplicate_warning' => push_vapid_duplicate_warning(),
        'ca_bundle' => $caBundle,
        'storage_writable' => is_dir(dirname(__DIR__) . '/storage') && is_writable(dirname(__DIR__) . '/storage'),
    ];
}

function push_create_subscription_curl_handle(array $subscription, string $payload, $privateKey)
{
    $details = openssl_pkey_get_details($privateKey);
    $vapidPublicKey = "\x04"
        . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
        . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

    $endpoint = (string)($subscription['endpoint'] ?? '');

    if($endpoint === ''){
        return null;
    }

    try{
        $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
        $port = parse_url($endpoint, PHP_URL_PORT);

        if($port){
            $audience .= ':' . $port;
        }

        $jwt = push_create_vapid_jwt($audience, push_vapid_subject(), $privateKey);
        $body = push_encrypt_payload($payload, (string)$subscription['p256dh'], (string)$subscription['auth_key']);
        push_set_last_send_error('');
    }catch(Throwable $e){
        $message = $e->getMessage();
        push_set_last_send_error($message);
        error_log('[ticketin-push] encrypt error: ' . $message);
        return null;
    }

    $headers = [
        'TTL: 86400',
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'Authorization: vapid t=' . $jwt . ', k=' . push_base64url_encode($vapidPublicKey),
        'Urgency: normal',
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => push_curl_timeout_seconds(),
        CURLOPT_CONNECTTIMEOUT => push_curl_connect_timeout_seconds(),
        CURLOPT_NOSIGNAL => true,
    ]);
    push_curl_apply_ssl_options($ch);

    if(defined('CURL_HTTP_VERSION_2TLS')){
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
    }

    return $ch;
}

function push_send_subscriptions_parallel(PDO $pdo, array $subscriptions, string $payload): array
{
    $results = [];

    if(!$subscriptions){
        return $results;
    }

    $privateKey = push_load_vapid_private_key();

    if(!$privateKey){
        foreach($subscriptions as $subscription){
            $results[] = [
                'user_id' => (int)($subscription['user_id'] ?? 0),
                'status' => 0,
                'endpoint' => substr((string)($subscription['endpoint'] ?? ''), 0, 72),
                'error' => 'کلید VAPID روی سرور آماده نیست',
            ];
        }

        return $results;
    }

    if(!function_exists('curl_multi_init')){
        foreach($subscriptions as $subscription){
            $results[] = [
                'user_id' => (int)($subscription['user_id'] ?? 0),
                'status' => 0,
                'endpoint' => substr((string)($subscription['endpoint'] ?? ''), 0, 72),
                'error' => 'افزونه curl در PHP فعال نیست',
            ];
        }

        return $results;
    }

    $handles = [];
    $meta = [];

    foreach($subscriptions as $subscription){
        $ch = push_create_subscription_curl_handle($subscription, $payload, $privateKey);

        if(!$ch){
            $results[] = [
                'user_id' => (int)($subscription['user_id'] ?? 0),
                'status' => 0,
                'endpoint' => substr((string)($subscription['endpoint'] ?? ''), 0, 72),
                'error' => push_last_send_error() !== ''
                    ? push_last_send_error()
                    : 'آماده‌سازی ارسال ناموفق بود',
            ];
            continue;
        }

        $id = (int)$ch;
        $handles[$id] = $ch;
        $meta[$id] = $subscription;
    }

    if(!$handles){
        return $results;
    }

    $multi = curl_multi_init();

    foreach($handles as $ch){
        curl_multi_add_handle($multi, $ch);
    }

    $running = null;

    do{
        $status = curl_multi_exec($multi, $running);

        if($running > 0){
            curl_multi_select($multi, 0.2);
        }
    }while($running > 0 && $status === CURLM_OK);

    foreach($handles as $id => $ch){
        $subscription = $meta[$id];
        $endpoint = (string)($subscription['endpoint'] ?? '');
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $responseBody = (string)curl_multi_getcontent($ch);
        $detail = $curlError;

        if($detail === '' && $responseBody !== '' && $httpStatus >= 400){
            $detail = trim(preg_replace('/\s+/', ' ', $responseBody));
            $detail = substr($detail, 0, 180);
        }

        $row = [
            'user_id' => (int)($subscription['user_id'] ?? 0),
            'status' => $httpStatus,
            'endpoint' => substr($endpoint, 0, 72),
            'endpoint_host' => push_endpoint_host($endpoint),
            'error' => push_human_error_for_status($httpStatus, $detail),
            'detail' => $detail,
        ];

        $results[] = $row;

        if($httpStatus === 0 && $curlError !== ''){
            error_log('[ticketin-push] curl error: ' . $curlError);
        }elseif($httpStatus > 0 && ($httpStatus < 200 || $httpStatus >= 300)){
            error_log('[ticketin-push] push HTTP ' . $httpStatus . ' for ' . substr($endpoint, 0, 80) . ' body=' . substr($responseBody, 0, 200));
        }

        if(in_array($httpStatus, [401, 403, 404, 410], true)){
            push_remove_subscription($pdo, $endpoint);
        }

        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }

    curl_multi_close($multi);

    return $results;
}

function push_notify_admins(
    PDO $pdo,
    string $title,
    string $body,
    string $url,
    string $tag = 'ticketin-admin',
    ?string $adminType = null
): array {
    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'url' => $url,
        'tag' => $tag,
    ], JSON_UNESCAPED_UNICODE);

    if($payload === false){
        return [
            'sent' => 0,
            'failed' => 0,
            'results' => [],
        ];
    }

    $subscriptions = push_get_subscriptions($pdo, $adminType);
    $sendResults = push_send_subscriptions_parallel($pdo, $subscriptions, $payload);
    $sent = 0;
    $failed = 0;

    foreach($sendResults as $row){
        if(($row['status'] ?? 0) >= 200 && ($row['status'] ?? 0) < 300){
            $sent++;
            continue;
        }

        $failed++;
    }

    if(!$subscriptions){
        error_log('[ticketin-push] no admin subscriptions registered');
    }elseif($sent === 0 && $failed > 0){
        error_log('[ticketin-push] all sends failed: ' . json_encode($sendResults, JSON_UNESCAPED_UNICODE));
    }

    return [
        'sent' => $sent,
        'failed' => $failed,
        'results' => $sendResults,
        'targeted' => count($subscriptions),
    ];
}

function push_notify_ticket_user_reply(PDO $pdo, int $ticketId, array $ticket): void
{
    $code = (string)($ticket['tracking_code'] ?? $ticketId);
    $title = 'پاسخ جدید کاربر';
    $body = 'کاربر به تیکت ' . $code . ' پاسخ داد.';
    $url = '/admin/view-ticket.php?id=' . $ticketId;

    push_notify_all_admins($pdo, $title, $body, $url, 'ticket-reply-' . $ticketId);
}

function push_notify_new_registration(PDO $pdo, string $fullname): void
{
    $title = 'ثبت‌نام جدید';
    $body = trim($fullname) !== '' ? $fullname . ' در انتظار تایید است.' : 'کاربر جدید در انتظار تایید است.';
    $url = '/admin/pending-users.php';

    push_notify_super_admins($pdo, $title, $body, $url, 'new-registration');
}

function push_tehran_today_date(): string
{
    return push_tehran_now()->format('Y-m-d');
}

function push_reminder_notification_hours(): array
{
    return [8, 10, 12];
}

function push_tehran_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran'));
}

function push_is_reminder_business_hours(): bool
{
    $now = push_tehran_now();
    $minutes = ((int)$now->format('H') * 60) + (int)$now->format('i');

    return $minutes >= 450 && $minutes <= 870;
}

function push_due_reminder_hours(): array
{
    if(!push_is_reminder_business_hours()){
        return [];
    }

    $currentHour = (int)push_tehran_now()->format('G');
    $due = [];

    foreach(push_reminder_notification_hours() as $hour){
        if($currentHour >= $hour){
            $due[] = $hour;
        }
    }

    return $due;
}

function push_parse_sent_slots(?string $slots): array
{
    if($slots === null || trim($slots) === ''){
        return [];
    }

    $parsed = [];

    foreach(explode(',', $slots) as $slot){
        $hour = (int)trim($slot);

        if(in_array($hour, push_reminder_notification_hours(), true)){
            $parsed[] = $hour;
        }
    }

    return array_values(array_unique($parsed));
}

function push_slots_sent_today(?string $sentDate, ?string $slots): array
{
    $today = push_tehran_today_date();
    $normalizedSentDate = $sentDate;

    if($sentDate !== null && $sentDate !== ''){
        $normalizedSentDate = substr((string)$sentDate, 0, 10);
    }

    if($normalizedSentDate !== $today){
        return [];
    }

    return push_parse_sent_slots($slots);
}

function push_notify_reminder(
    PDO $pdo,
    int $reminderId,
    string $title,
    int $slotHour
): void {
    push_notify_all_admins(
        $pdo,
        'یادآوری امروز',
        $title,
        '/admin/reminders.php',
        'reminder-' . $reminderId . '-' . $slotHour
    );
}

function push_mark_reminder_slot_sent(PDO $pdo, int $reminderId, array $sentSlots): void
{
    $slots = array_values(array_unique($sentSlots));
    sort($slots, SORT_NUMERIC);

    $update = $pdo->prepare("
        UPDATE reminders
        SET
            push_sent_date = ?,
            push_sent_slots = ?
        WHERE id = ?
    ");

    $update->execute([
        push_tehran_today_date(),
        implode(',', $slots),
        $reminderId,
    ]);
}

function push_send_today_reminders(PDO $pdo): void
{
    push_ensure_schema($pdo);
    require_once __DIR__ . '/jalali.php';

    $dueHours = push_due_reminder_hours();

    if(!$dueHours){
        return;
    }

    $todayJalali = push_today_jalali_date();

    $rows = $pdo->query("
        SELECT id, title, reminder_date, push_sent_date, push_sent_slots
        FROM reminders
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $reminders = array_values(array_filter(
        $rows,
        static function(array $row) use ($todayJalali): bool {
            return normalize_jalali_date_for_db((string)($row['reminder_date'] ?? '')) === $todayJalali;
        }
    ));

    if(!$reminders){
        return;
    }

    foreach($reminders as $reminder){
        $reminderId = (int)$reminder['id'];
        $sentSlots = push_slots_sent_today(
            $reminder['push_sent_date'] ?? null,
            $reminder['push_sent_slots'] ?? null
        );

        foreach($dueHours as $hour){
            if(in_array($hour, $sentSlots, true)){
                continue;
            }

            push_notify_reminder(
                $pdo,
                $reminderId,
                (string)$reminder['title'],
                $hour
            );

            $sentSlots[] = $hour;
            push_mark_reminder_slot_sent($pdo, $reminderId, $sentSlots);
        }
    }
}

function push_hkdf(string $salt, string $ikm, string $info, int $length): string
{
    $prk = hash_hmac('sha256', $ikm, $salt, true);

    return substr(hash_hmac('sha256', $info . chr(1), $prk, true), 0, $length);
}

function push_der_ecdsa_to_raw(string $der): string
{
    $pos = 0;

    if(($der[$pos++] ?? '') !== "\x30"){
        throw new RuntimeException('Invalid DER signature');
    }

    $len = ord($der[$pos++] ?? "\x00");

    if($len & 0x80){
        $bytes = $len & 0x7f;
        $len = 0;

        for($i = 0; $i < $bytes; $i++){
            $len = ($len << 8) | ord($der[$pos++] ?? "\x00");
        }
    }

    if(($der[$pos++] ?? '') !== "\x02"){
        throw new RuntimeException('Invalid DER R marker');
    }

    $rLen = ord($der[$pos++] ?? "\x00");
    $r = substr($der, $pos, $rLen);
    $pos += $rLen;

    if(($der[$pos++] ?? '') !== "\x02"){
        throw new RuntimeException('Invalid DER S marker');
    }

    $sLen = ord($der[$pos++] ?? "\x00");
    $s = substr($der, $pos, $sLen);
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");

    return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
}

function push_ec_public_key_to_pem(string $publicKey): string
{
    $der = push_build_ec_public_key_der($publicKey);

    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

function push_build_ec_public_key_der(string $publicKey): string
{
    $algoOid = hex2bin('301306072a8648ce3d020106082a8648ce3d030107');
    $bitString = "\x03" . chr(strlen($publicKey) + 1) . "\x00" . $publicKey;

    $sequence = $algoOid . $bitString;
    $length = strlen($sequence);

    if($length < 128){
        return "\x30" . chr($length) . $sequence;
    }

    return "\x30\x81" . chr($length) . $sequence;
}

function push_create_vapid_jwt(string $audience, string $subject, $privateKey): string
{
    $header = push_base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $claims = push_base64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200,
        'sub' => $subject,
    ], JSON_UNESCAPED_SLASHES));

    $input = $header . '.' . $claims;
    $derSignature = '';

    if(!openssl_sign($input, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)){
        throw new RuntimeException('Unable to sign VAPID JWT');
    }

    return $input . '.' . push_base64url_encode(push_der_ecdsa_to_raw($derSignature));
}

function push_encrypt_payload(string $payload, string $p256dh, string $auth): string
{
    if(!function_exists('openssl_pkey_derive')){
        throw new RuntimeException('PHP شما openssl_pkey_derive ندارد — نسخه PHP را به 7.3+ ارتقا دهید');
    }

    $userPublicKey = push_base64url_decode($p256dh);
    $userAuthToken = push_base64url_decode($auth);

    if(strlen($userPublicKey) !== 65 || ($userPublicKey[0] ?? '') !== "\x04"){
        throw new RuntimeException('کلید عمومی اشتراک نامعتبر است');
    }

    $localKey = push_create_local_ec_key();

    if(!$localKey){
        $opensslError = '';

        while($message = openssl_error_string()){
            $opensslError = $message;
        }

        throw new RuntimeException(
            $opensslError !== ''
                ? 'ساخت کلید موقت ناموفق: ' . $opensslError
                : 'ساخت کلید موقت ناموفق'
        );
    }

    $localDetails = openssl_pkey_get_details($localKey);
    $localPublicKey = "\x04"
        . str_pad($localDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT)
        . str_pad($localDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);

    $userPublicPem = push_ec_public_key_to_pem($userPublicKey);
    $sharedSecret = openssl_pkey_derive(openssl_pkey_get_public($userPublicPem), $localKey);

    if($sharedSecret === false){
        throw new RuntimeException('محاسبه shared secret ناموفق بود');
    }

    $sharedSecret = push_normalize_ecdh_secret($sharedSecret);
    $salt = random_bytes(16);
    $ikm = push_hkdf(
        $userAuthToken,
        $sharedSecret,
        'WebPush: info' . chr(0) . $userPublicKey . $localPublicKey,
        32
    );
    $contentEncryptionKey = push_hkdf($salt, $ikm, 'Content-Encoding: aes128gcm' . chr(0), 16);
    $nonce = push_hkdf($salt, $ikm, 'Content-Encoding: nonce' . chr(0), 12);
    $paddedPayload = $payload . chr(2);
    $tag = '';
    $cipherText = openssl_encrypt(
        $paddedPayload,
        'aes-128-gcm',
        $contentEncryptionKey,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag
    );

    if($cipherText === false){
        throw new RuntimeException('Unable to encrypt push payload');
    }

    return $salt
        . pack('N', 4096)
        . chr(strlen($localPublicKey))
        . $localPublicKey
        . $cipherText
        . $tag;
}

function push_send_to_subscription(array $subscription, string $payload): int
{
    $privateKey = push_load_vapid_private_key();

    if(!$privateKey){
        return 0;
    }

    $ch = push_create_subscription_curl_handle($subscription, $payload, $privateKey);

    if(!$ch){
        return 0;
    }

    curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if($status === 0 && $curlError !== ''){
        error_log('[ticketin-push] curl error: ' . $curlError);
    }

    return $status;
}

function push_maybe_send_today_reminders(PDO $pdo): void
{
    $stateFile = sys_get_temp_dir() . '/ticketin-push-reminders-web.state';

    if(is_file($stateFile)){
        $lastRun = (int)@file_get_contents($stateFile);

        if($lastRun > 0 && (time() - $lastRun) < 300){
            return;
        }
    }

    $lockFile = sys_get_temp_dir() . '/ticketin-push-reminders-web.lock';
    $lockHandle = @fopen($lockFile, 'c+');

    if(!$lockHandle){
        return;
    }

    if(!flock($lockHandle, LOCK_EX | LOCK_NB)){
        fclose($lockHandle);
        return;
    }

    try{
        push_send_today_reminders($pdo);
        @file_put_contents($stateFile, (string)time());
    }catch(Throwable $e){
        error_log('[ticketin-push] reminder dispatch failed: ' . $e->getMessage());
    }finally{
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}
