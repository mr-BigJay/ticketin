<?php

$config = [
    'host' => 'localhost',
    'dbname' => 'ticketin',
    'username' => 'ticketuser',
    'password' => 'StrongPass123!',
    'charset' => 'utf8mb4',
    'socket' => '',
];

$localConfig = __DIR__ . '/config.local.php';
$legacyConfig = __DIR__ . '/db.local.php';

if(file_exists($localConfig)){
    $loaded = require $localConfig;

    if(is_array($loaded)){
        $config = array_merge($config, $loaded);
    }
}elseif(file_exists($legacyConfig)){
    $host = $config['host'];
    $dbname = $config['dbname'];
    $username = $config['username'];
    $password = $config['password'];

    require $legacyConfig;

    $config['host'] = $host;
    $config['dbname'] = $dbname;
    $config['username'] = $username;
    $config['password'] = $password;
}

function db_build_dsn(array $config): string
{
    if(!empty($config['socket'])){
        return sprintf(
            'mysql:unix_socket=%s;dbname=%s;charset=%s',
            $config['socket'],
            $config['dbname'],
            $config['charset']
        );
    }

    $host = $config['host'] ?? 'localhost';

    if($host === 'localhost'){
        $defaultSockets = [
            '/var/run/mysqld/mysqld.sock',
            '/tmp/mysql.sock',
            '/var/lib/mysql/mysql.sock',
        ];

        foreach($defaultSockets as $socket){
            if(is_readable($socket)){
                return sprintf(
                    'mysql:unix_socket=%s;dbname=%s;charset=%s',
                    $socket,
                    $config['dbname'],
                    $config['charset']
                );
            }
        }
    }

    return sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $host,
        $config['dbname'],
        $config['charset']
    );
}

try {

    $pdo = new PDO(
        db_build_dsn($config),
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

} catch(PDOException $e) {

    die('Database Error: ' . $e->getMessage());

}
