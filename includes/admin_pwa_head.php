<?php

require_once __DIR__ . '/push_helpers.php';

$adminPwaPublicKey = push_get_vapid_public_key();

?>
<link rel="manifest" href="/admin/manifest.webmanifest">
<meta name="theme-color" content="#0284c7">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Ticketin Admin">
<link rel="apple-touch-icon" href="/admin/icons/icon-192.png">
