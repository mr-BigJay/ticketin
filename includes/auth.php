<?php

if(session_status() === PHP_SESSION_NONE){

    session_start();

}

if(!isset($_SESSION['user_id'])){

    $requestUri = $_SERVER['REQUEST_URI'] ?? '';

    if(strpos($requestUri, '/admin') !== false){

        header("Location: /jay_controller.php");

    }else{

        header("Location: /login.php");

    }

    exit;

}
