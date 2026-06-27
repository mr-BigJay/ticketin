<?php

require 'includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if(empty($_SESSION['user_id'])){

    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'لطفاً وارد شوید'
    ]);
    exit;

}

if(!isset($_SESSION['pending_ticket_attachments'])){
    $_SESSION['pending_ticket_attachments'] = [];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function ticket_upload_response(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ticket_upload_allowed_mime(string $mime): bool
{
    $allowed = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/heic',
        'image/heif',
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/3gpp',
    ];

    return in_array($mime, $allowed, true);
}

if($action === 'list'){

    ticket_upload_response([
        'ok' => true,
        'files' => array_values($_SESSION['pending_ticket_attachments'])
    ]);

}

if($action === 'remove'){

    $stored = basename((string)($_POST['stored'] ?? ''));

    if($stored === ''){
        ticket_upload_response([
            'ok' => false,
            'error' => 'فایل نامعتبر است'
        ]);
    }

    $_SESSION['pending_ticket_attachments'] = array_values(array_filter(
        $_SESSION['pending_ticket_attachments'],
        function($item) use ($stored){

            if(($item['stored'] ?? '') === $stored){

                $path = __DIR__ . '/uploads/' . $stored;

                if(is_file($path)){
                    @unlink($path);
                }

                return false;
            }

            return true;
        }
    ));

    ticket_upload_response([
        'ok' => true,
        'files' => array_values($_SESSION['pending_ticket_attachments'])
    ]);

}

if(
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    empty($_FILES['file'])
){

    http_response_code(400);
    ticket_upload_response([
        'ok' => false,
        'error' => 'درخواست نامعتبر است'
    ]);

}

$file = $_FILES['file'];

if($file['error'] !== UPLOAD_ERR_OK){

    ticket_upload_response([
        'ok' => false,
        'error' => 'خطا در آپلود فایل'
    ]);

}

$maxSize = 20 * 1024 * 1024;

if($file['size'] > $maxSize){

    ticket_upload_response([
        'ok' => false,
        'error' => 'حداکثر حجم فایل ۲۰ مگابایت است'
    ]);

}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']) ?: '';

if(!ticket_upload_allowed_mime($mime)){

    ticket_upload_response([
        'ok' => false,
        'error' => 'فرمت فایل مجاز نیست'
    ]);

}

$originalName = basename($file['name']);
$safeName = preg_replace('/[^a-zA-Z0-9._\x{0600}-\x{06FF}\-]+/u', '_', $originalName);
$stored = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
$target = __DIR__ . '/uploads/' . $stored;

if(!is_dir(__DIR__ . '/uploads')){
    mkdir(__DIR__ . '/uploads', 0755, true);
}

if(!move_uploaded_file($file['tmp_name'], $target)){

    ticket_upload_response([
        'ok' => false,
        'error' => 'ذخیره فایل انجام نشد'
    ]);

}

$entry = [
    'stored' => $stored,
    'original' => $originalName,
    'size' => (int)$file['size'],
    'url' => '/uploads/' . rawurlencode($stored)
];

$_SESSION['pending_ticket_attachments'][] = $entry;

ticket_upload_response([
    'ok' => true,
    'file' => $entry,
    'files' => array_values($_SESSION['pending_ticket_attachments'])
]);
