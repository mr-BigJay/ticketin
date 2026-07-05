<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_helpers.php';
require_once __DIR__ . '/push_helpers.php';

admin_ensure_schema($pdo);
push_ensure_schema($pdo);

if(($_SESSION['role'] ?? '') !== 'admin'){
    die('دسترسی غیر مجاز');
}

admin_load_session_user($pdo);

$adminCurrentPage = basename($_SERVER['PHP_SELF'] ?? '');

admin_require_page_access($adminCurrentPage);

$admin_pwa_enabled = true;

if(
    !empty($_SESSION['must_change_password'])
    &&
    $adminCurrentPage !== 'change-password.php'
){
    header('Location: /admin/change-password.php');
    exit;
}
