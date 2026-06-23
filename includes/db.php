<?php

$config = [
    'host' => 'localhost',
    'dbname' => 'ticketin',
    'username' => 'ticketuser',
    'password' => 'StrongPass123!',
    'charset' => 'utf8mb4',
];

$localConfig = __DIR__ . '/config.local.php';

if(file_exists($localConfig)){
    $config = array_merge($config, require $localConfig);
}

try {

    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

} catch(PDOException $e) {

    die("Database Error: " . $e->getMessage());

}
