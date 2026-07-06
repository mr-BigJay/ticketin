<?php

require '../includes/admin_auth.php';

admin_require_super();

header('Content-Type: text/plain; charset=utf-8');

$pemFile = push_vapid_private_pem_path();
$storageDir = dirname(__DIR__) . '/storage';
$includesDir = __DIR__ . '/../includes';

echo "Ticketin VAPID setup\n";
echo "===================\n\n";

echo "OpenSSL extension: " . (extension_loaded('openssl') ? 'yes' : 'no') . "\n";
echo "proc_open available: " . (function_exists('proc_open') ? 'yes' : 'no') . "\n";
echo "Includes dir: {$includesDir}\n";
echo "Includes writable: " . (is_writable($includesDir) ? 'yes' : 'no') . "\n";
echo "Storage dir: {$storageDir}\n";
echo "Storage writable: " . (is_dir($storageDir) && is_writable($storageDir) ? 'yes' : (is_writable(dirname($storageDir)) ? 'parent writable' : 'no')) . "\n";
echo "OpenSSL config: " . (push_vapid_openssl_config_path() ?: 'not found') . "\n\n";

$ready = push_ensure_vapid_keys();
$pemFile = push_vapid_private_pem_path();
$publicKey = push_get_vapid_public_key();

if(!$ready || !is_file($pemFile) || $publicKey === ''){
    echo "FAILED: could not create VAPID key\n";
    echo "Reason: " . (push_vapid_last_error() ?: 'unknown') . "\n\n";
    echo "Run on server as root:\n";
    echo "mkdir -p {$storageDir}\n";
    echo "chown -R www-data:www-data {$storageDir} {$includesDir}\n";
    echo "chmod 755 {$storageDir} {$includesDir}\n";
    exit(1);
}

echo "OK: VAPID key created\n";
echo "PEM file: {$pemFile}\n";
echo "Public key length: " . strlen($publicKey) . "\n";
echo "Public key ready: yes\n";
