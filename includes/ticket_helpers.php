<?php

require_once __DIR__ . '/upload_storage.php';

function ticket_grace_period_days(): int
{
    return 7;
}

function ticket_is_closed(array $ticket): bool
{
    return (string)($ticket['status'] ?? '') === 'closed';
}

function ticket_is_in_grace_period(array $ticket): bool
{
    if(!ticket_is_closed($ticket)){
        return false;
    }

    $closedAt = $ticket['closed_at'] ?? null;

    if($closedAt === null || $closedAt === ''){
        return false;
    }

    $closedTimestamp = strtotime((string)$closedAt);

    if($closedTimestamp === false){
        return false;
    }

    $graceStart = strtotime(
        '-' . ticket_grace_period_days() . ' days'
    );

    return $closedTimestamp >= $graceStart;
}

function ticket_can_reopen(array $ticket): bool
{
    return ticket_is_in_grace_period($ticket);
}

function ticket_sql_current_scope(string $tableAlias = ''): string
{
    $prefix = $tableAlias !== '' ? $tableAlias . '.' : '';
    $days = ticket_grace_period_days();

    return '('
        . $prefix . "status != 'closed'"
        . ' OR ('
        . $prefix . "status = 'closed'"
        . ' AND ' . $prefix . 'closed_at IS NOT NULL'
        . ' AND ' . $prefix . "closed_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"
        . ')'
        . ')';
}

function ticket_sql_closed_scope(string $tableAlias = ''): string
{
    $prefix = $tableAlias !== '' ? $tableAlias . '.' : '';

    return $prefix . "status = 'closed' AND " . $prefix . 'closed_at IS NOT NULL';
}

function ticket_attachment_url(string $stored): string
{
    $stored = trim($stored);

    if($stored === ''){
        return '';
    }

    $url = upload_storage_public_url($stored);

    if($url !== ''){
        return $url;
    }

    $normalized = str_replace('\\', '/', $stored);

    if(strpos($normalized, 'tickets/') === 0){
        return '/uploads/' . implode(
            '/',
            array_map('rawurlencode', explode('/', $normalized))
        );
    }

    $basename = basename($normalized);

    if(preg_match('/^\d+_/', $basename)){
        return '/uploads/tickets/' . rawurlencode($basename);
    }

    return '/uploads/' . rawurlencode($basename);
}

function ticket_render_attachments(?string $attachmentField): void
{
    if($attachmentField === null || $attachmentField === ''){
        return;
    }

    $attachments = array_values(array_filter(array_map(
        static function(string $part): string{
            return trim($part);
        },
        explode(',', $attachmentField)
    )));

    if(!$attachments){
        return;
    }

    echo '<div class="reply-attachments">';

    foreach($attachments as $index => $stored){
        $url = ticket_attachment_url($stored);

        if($url === ''){
            continue;
        }

        $label = count($attachments) === 1
            ? 'مشاهده ضمیمه'
            : 'ضمیمه ' . ($index + 1);

        echo '<a class="reply-attachment-link" href="'
            . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            . '" target="_blank" rel="noopener">';

        echo '📎 ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        echo '</a>';
    }

    echo '</div>';
}

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
