<?php

session_start();

$role = $_SESSION['role'] ?? 'user';

session_destroy();

if($role === 'admin'){

    header("Location: /jay_controller.php");

}else{

    header("Location: /login.php");

}

exit;
