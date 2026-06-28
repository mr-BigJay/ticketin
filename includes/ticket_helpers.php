<?php

require_once __DIR__ . '/upload_storage.php';

function ticket_delete_attachments_from_field(?string $attachmentField): void
{
    if($attachmentField === null || $attachmentField === ''){
        return;
    }

    foreach(explode(',', $attachmentField) as $part){
        $part = trim($part);

        if($part === ''){
            continue;
        }

        if(upload_storage_normalize_relative_path($part) !== null){
            upload_storage_delete_file($part);
            continue;
        }

        $path = upload_storage_base_dir() . '/' . basename($part);

        if(is_file($path)){
            @unlink($path);
        }
    }
}

function ticket_delete_reply_attachment(?string $filename): void
{
    if($filename === null || $filename === ''){
        return;
    }

    $filename = basename($filename);
    $path = __DIR__ . '/../uploads/tickets/' . $filename;

    if(is_file($path)){
        @unlink($path);
    }
}

function ticket_delete(PDO $pdo, int $ticketId): bool
{
    try{
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, attachment FROM tickets WHERE id=?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();

        if(!$ticket){
            $pdo->rollBack();
            return false;
        }

        $replies = $pdo->prepare("SELECT attachment FROM ticket_replies WHERE ticket_id=?");
        $replies->execute([$ticketId]);

        foreach($replies->fetchAll() as $reply){
            ticket_delete_reply_attachment($reply['attachment'] ?? null);
        }

        ticket_delete_attachments_from_field($ticket['attachment'] ?? null);

        $pdo->prepare("DELETE FROM ticket_replies WHERE ticket_id=?")->execute([$ticketId]);

        $delete = $pdo->prepare("DELETE FROM tickets WHERE id=?");
        $delete->execute([$ticketId]);

        if($delete->rowCount() < 1){
            $pdo->rollBack();
            return false;
        }

        $pdo->commit();
        return true;

    }catch(PDOException $e){
        if($pdo->inTransaction()){
            $pdo->rollBack();
        }

        return false;
    }
}
