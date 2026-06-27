<?php

require 'includes/auth.php';
require 'includes/db.php';
require 'includes/upload_storage.php';

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

if($action === 'list'){

    $settings = upload_settings_get($pdo);

    ticket_upload_response([
        'ok' => true,
        'files' => array_values($_SESSION['pending_ticket_attachments']),
        'settings' => [
            'max_size_mb' => $settings['max_size_mb'],
            'max_size_bytes' => $settings['max_size_bytes'],
        ],
    ]);

}

if($action === 'remove'){

    $stored = (string)($_POST['stored'] ?? '');

    if($stored === ''){
        ticket_upload_response([
            'ok' => false,
            'error' => 'فایل نامعتبر است'
        ]);
    }

    $_SESSION['pending_ticket_attachments'] = array_values(array_filter(
        $_SESSION['pending_ticket_attachments'],
        function($item) use ($stored){

            if(($item['stored'] ?? '') !== $stored){
                return true;
            }

            upload_storage_delete_file($stored);

            return false;
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

try{

    $pendingNames = array_map(
        static function($item){
            return $item['stored'] ?? '';
        },
        $_SESSION['pending_ticket_attachments']
    );

    $entry = upload_storage_store_uploaded_file(
        $pdo,
        (int)$_SESSION['user_id'],
        $_FILES['file'],
        $pendingNames
    );

    $_SESSION['pending_ticket_attachments'][] = $entry;

    ticket_upload_response([
        'ok' => true,
        'file' => $entry,
        'files' => array_values($_SESSION['pending_ticket_attachments'])
    ]);

}catch(Throwable $e){

    ticket_upload_response([
        'ok' => false,
        'error' => $e->getMessage()
    ]);

}
