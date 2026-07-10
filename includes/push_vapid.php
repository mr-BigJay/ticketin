<?php

function push_vapid_subject(): string
{
    return 'mailto:admin@ticketin.ir';
}

function push_vapid_candidate_paths(): array
{
    return [
        dirname(__DIR__) . '/storage/push_vapid_private.pem',
        __DIR__ . '/push_vapid_private.pem',
    ];
}

function push_vapid_existing_paths(): array
{
    $paths = [];

    foreach(push_vapid_candidate_paths() as $path){
        if(is_file($path)){
            $paths[] = $path;
        }
    }

    return $paths;
}

function push_vapid_set_last_error(string $message): void
{
    $GLOBALS['push_vapid_last_error'] = $message;
}

function push_vapid_last_error(): string
{
    return (string)($GLOBALS['push_vapid_last_error'] ?? '');
}

function push_vapid_private_pem_path(): string
{
    $existing = push_vapid_existing_paths();

    if(count($existing) > 1){
        error_log('[ticketin-push] multiple VAPID PEM files found; using storage copy');
    }

    if($existing){
        return $existing[0];
    }

    $writablePath = push_vapid_writable_pem_path();

    return $writablePath ?? push_vapid_candidate_paths()[0];
}

function push_vapid_duplicate_warning(): string
{
    $existing = push_vapid_existing_paths();

    if(count($existing) < 2){
        return '';
    }

    $fingerprints = [];

    foreach($existing as $path){
        $contents = @file_get_contents($path);

        if($contents === false){
            continue;
        }

        $fingerprints[$path] = substr(hash('sha256', $contents), 0, 12);
    }

    $unique = array_unique(array_values($fingerprints));

    if(count($unique) < 2){
        return 'دو فایل VAPID تکراری پیدا شد؛ از storage استفاده می‌شود.';
    }

    return 'دو کلید VAPID متفاوت روی سرور هست — احتمالاً علت خطا. «بازنشانی کامل» را بزنید.';
}

function push_vapid_clear_cached_key(): void
{
    unset($GLOBALS['push_vapid_private_key_cache']);
}

function push_vapid_delete_all_pem_files(): void
{
    foreach(push_vapid_candidate_paths() as $path){
        if(is_file($path)){
            @unlink($path);
        }
    }

    push_vapid_clear_cached_key();
}

function push_vapid_writable_pem_path(): ?string
{
    foreach(push_vapid_candidate_paths() as $path){
        $dir = dirname($path);

        if(is_file($path)){
            return $path;
        }

        if(!is_dir($dir)){
            @mkdir($dir, 0755, true);
        }

        if(is_dir($dir) && is_writable($dir)){
            return $path;
        }
    }

    push_vapid_set_last_error(
        'پوشه includes یا storage روی سرور قابل نوشتن نیست.'
    );

    return null;
}

function push_vapid_openssl_config_path(): ?string
{
    static $cached = null;

    if($cached !== null){
        return $cached ?: null;
    }

    $candidates = [
        '/etc/ssl/openssl.cnf',
        '/etc/pki/tls/openssl.cnf',
        '/usr/lib/ssl/openssl.cnf',
    ];

    foreach($candidates as $candidate){
        if(is_readable($candidate)){
            $cached = $candidate;
            return $candidate;
        }
    }

    $tmpConfig = sys_get_temp_dir() . '/ticketin-openssl.cnf';
    $contents = <<<INI
[ req ]
distinguished_name = req_distinguished_name
[ req_distinguished_name ]
INI;

    if(@file_put_contents($tmpConfig, $contents) !== false){
        $cached = $tmpConfig;
        return $tmpConfig;
    }

    $cached = false;

    return null;
}

function push_vapid_generate_pem_via_php(): string
{
    if(!function_exists('openssl_pkey_new')){
        push_vapid_set_last_error('افزونه openssl در PHP فعال نیست.');
        return '';
    }

    $options = [
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ];

    $configPath = push_vapid_openssl_config_path();

    if($configPath){
        $options['config'] = $configPath;
    }

    $key = openssl_pkey_new($options);

    if(!$key){
        $error = '';

        while($message = openssl_error_string()){
            $error = $message;
        }

        push_vapid_set_last_error(
            $error !== ''
                ? 'ساخت کلید با OpenSSL ناموفق بود: ' . $error
                : 'ساخت کلید با OpenSSL ناموفق بود.'
        );

        return '';
    }

    $pem = '';

    if(!openssl_pkey_export($key, $pem, null, $configPath ? ['config' => $configPath] : [])){
        push_vapid_set_last_error('خروجی گرفتن از کلید خصوصی OpenSSL ناموفق بود.');
        return '';
    }

    return $pem;
}

function push_vapid_generate_pem_via_shell(): string
{
    if(!function_exists('proc_open')){
        return '';
    }

    $commands = [
        ['openssl', 'ecparam', '-genkey', '-name', 'prime256v1', '-noout'],
        ['openssl', 'ecparam', '-name', 'prime256v1', '-genkey', '-noout'],
    ];

    foreach($commands as $command){
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);

        if(!is_resource($process)){
            continue;
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if($exitCode === 0 && is_string($output) && str_contains($output, 'BEGIN EC PRIVATE KEY')){
            return $output;
        }

        if($errorOutput){
            push_vapid_set_last_error(trim($errorOutput));
        }
    }

    return '';
}

function push_vapid_write_pem(string $pem): bool
{
    $pem = trim($pem);

    if($pem === '' || !str_contains($pem, 'BEGIN')){
        push_vapid_set_last_error('کلید ساخته‌شده معتبر نیست.');
        return false;
    }

    $targetPath = push_vapid_writable_pem_path();

    if(!$targetPath){
        return false;
    }

    foreach(push_vapid_candidate_paths() as $path){
        if($path !== $targetPath && is_file($path)){
            @unlink($path);
        }
    }

    $written = @file_put_contents($targetPath, $pem . PHP_EOL, LOCK_EX);

    if($written === false){
        push_vapid_set_last_error('نوشتن فایل کلید روی سرور ناموفق بود.');
        return false;
    }

    @chmod($targetPath, 0600);
    push_vapid_clear_cached_key();

    return true;
}
