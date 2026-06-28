<?php

$host = 'localhost';
$dbname = 'ticketin';
$username = 'ticketuser';
$password = 'StrongPass123!';

$localConfig = __DIR__ . '/db.local.php';

if(is_file($localConfig)){
    require $localConfig;
}

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e) {

    die('Database Error: ' . $e->getMessage());

}
