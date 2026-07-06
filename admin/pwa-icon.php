<?php

$size = (int)($_GET['size'] ?? 192);
$allowed = [32, 192, 512];

if(!in_array($size, $allowed, true)){
    $size = 192;
}

$iconPath = __DIR__ . '/icons/icon-' . $size . '.png';

if(!is_file($iconPath)){
    http_response_code(404);
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');
readfile($iconPath);
