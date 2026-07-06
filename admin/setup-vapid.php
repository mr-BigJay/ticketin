<?php

require '../includes/admin_auth.php';

admin_require_super();

header('Content-Type: text/plain; charset=utf-8');

$pemFile = push_vapid_private_pem_path();
$includesDir = dirname($pemFile);

echo "Ticketin VAPID setup\n";
echo "===================\n\n";

echo "OpenSSL extension: " . (extension_loaded('openssl') ? 'yes' : 'no') . "\n";
echo "Includes dir: {$includesDir}\n";
echo "Includes writable: " . (is_writable($includesDir) ? 'yes' : 'no') . "\n\n";

push_ensure_vapid_keys();

if(!file_exists($pemFile)){
    echo "FAILED: could not create push_vapid_private.pem\n";
    echo "Run on server as root:\n";
    echo "chown www-data:www-data {$includesDir}\n";
    echo "chmod 755 {$includesDir}\n";
    exit(1);
}

$publicKey = push_get_vapid_public_key();

echo "OK: push_vapid_private.pem created\n";
echo "Public key length: " . strlen($publicKey) . "\n";
echo "Public key ready: " . ($publicKey !== '' ? 'yes' : 'no') . "\n";
