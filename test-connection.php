<?php

/**
 * تست اتصال — بعد از رفع مشکل حذف کنید.
 * php /var/www/ticketin/test-connection.php
 */

$configFile = __DIR__ . '/includes/config.local.php';

if(!file_exists($configFile)){
    die("config.local.php not found\n");
}

$config = require $configFile;
$user = $config['username'] ?? '';
$pass = $config['password'] ?? '';
$db = $config['dbname'] ?? 'ticketin';

$attempts = [];

$sockets = [
    $config['socket'] ?? '',
    '/var/run/mysqld/mysqld.sock',
    '/tmp/mysql.sock',
];

foreach(array_unique(array_filter($sockets)) as $socket){
    if(!is_readable($socket)){
        $attempts[] = "socket {$socket}: file not readable";
        continue;
    }

    try{
        $pdo = new PDO(
            "mysql:unix_socket={$socket};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->query('SELECT 1');
        echo "OK via unix_socket: {$socket}\n";
        exit(0);
    }catch(PDOException $e){
        $attempts[] = "socket {$socket}: " . $e->getMessage();
    }
}

foreach(['127.0.0.1', 'localhost'] as $host){
    try{
        $pdo = new PDO(
            "mysql:host={$host};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->query('SELECT 1');
        echo "OK via host: {$host}\n";
        exit(0);
    }catch(PDOException $e){
        $attempts[] = "host {$host}: " . $e->getMessage();
    }
}

echo "All attempts failed:\n";
foreach($attempts as $line){
    echo " - {$line}\n";
}
exit(1);
