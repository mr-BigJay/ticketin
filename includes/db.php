<?php

$host = "localhost";
$dbname = "ticketin";
$username = "ticketuser";
$password = "StrongPass123!";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e) {

    die("Database Error: " . $e->getMessage());

}
