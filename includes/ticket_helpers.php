<?php

require_once __DIR__ . '/upload_storage.php';
require_once __DIR__ . '/jalali.php';

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

function ticket_ensure_schema(PDO $pdo): void
{
    static $ensured = false;

    if($ensured){
        return;
    }

    $ensured = true;

    try{
        $pdo->exec("
            ALTER TABLE tickets
            ADD COLUMN closed_by VARCHAR(20) NULL DEFAULT NULL
        ");
    }catch(PDOException $e){
    }
}

function ticket_mark_closed(PDO $pdo, int $ticketId, string $closedBy): void
{
    ticket_ensure_schema($pdo);

    if(!in_array($closedBy, ['user', 'admin'], true)){
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE tickets
        SET
            status='closed',
            closed_at=NOW(),
            closed_by=?
        WHERE id=?
    ");

    $stmt->execute([$closedBy, $ticketId]);

    require_once __DIR__ . '/sms_helpers.php';

    $eventKey = $closedBy === 'user'
        ? 'ticket_closed_user'
        : 'ticket_closed_admin';

    sms_dispatch_ticket_event($pdo, $eventKey, $ticketId);
}

function ticket_mark_reopened(PDO $pdo, int $ticketId): void
{
    ticket_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        UPDATE tickets
        SET
            status='open',
            closed_at=NULL,
            closed_by=NULL
        WHERE id=?
    ");

    $stmt->execute([$ticketId]);

    require_once __DIR__ . '/sms_helpers.php';

    sms_dispatch_ticket_event($pdo, 'ticket_reopened', $ticketId);
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
            ? 'مشاهده پیوست'
            : 'پیوست ' . ($index + 1);

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

function ticket_list_print_layout_styles(): void
{
    static $done = false;

    if($done){
        return;
    }

    $done = true;

    echo <<<'CSS'
<style>
.ticket-list-page{
    max-width:950px;
    margin:0 auto;
}
.ticket-list-shell{
    padding:0;
    margin:0;
    background:transparent;
    border:none;
    box-shadow:none;
}
.ticket-list-shell > .ticket-card,
.ticket-list-shell > .ticket-row{
    margin-bottom:18px;
}
.ticket-list-shell .list-pagination-bar{
    margin-top:18px;
}
@media(max-width:768px){
    body.user-portal .ticket-list-page{
        margin-left:-12px;
        margin-right:-12px;
        width:calc(100% + 24px);
    }
    body:not(.user-portal) .ticket-list-page{
        margin-left:-14px;
        margin-right:-14px;
        width:calc(100% + 28px);
    }
    .ticket-list-shell > .ticket-card,
    .ticket-list-shell > .ticket-row{
        border-radius:0;
        border-left:none;
        border-right:none;
        box-shadow:0 4px 16px rgba(15,23,42,.04);
    }
}
</style>
CSS;
}

function ticket_top_bar_datetime_value(array $ticket, string $field = 'created_at'): string
{
    if($field === 'closed_at'){
        $value = (string)($ticket['closed_at'] ?? '');

        if($value !== ''){
            return $value;
        }
    }

    return (string)($ticket['created_at'] ?? '');
}

function ticket_render_top_bar(array $ticket, array $options = []): void
{
    $menu = (string)($options['menu'] ?? 'none');
    $datetimeField = (string)($options['datetime_at'] ?? 'created_at');
    $ticketId = (int)($options['ticket_id'] ?? $ticket['id'] ?? 0);
    $isSuper = !empty($options['is_super']);
    $datetimeValue = ticket_top_bar_datetime_value($ticket, $datetimeField);

    ticket_top_bar_print_styles();

    $code = htmlspecialchars((string)($ticket['tracking_code'] ?? ''), ENT_QUOTES, 'UTF-8');
    $category = htmlspecialchars((string)($ticket['category'] ?? ''), ENT_QUOTES, 'UTF-8');
    $time = fa_time($datetimeValue);
    $date = fa_date($datetimeValue);
    ?>
<div class="ticket-top-bar">

<div class="ticket-top-accent" aria-hidden="true"></div>

<span class="tracking-code-gradient"><?= $code ?></span>

<div class="ticket-top-category">
<span class="ticket-folder-icon" aria-hidden="true">📁</span>
<span class="ticket-category-text"><?= $category ?></span>
</div>

<div class="ticket-top-datetime">
<span class="ticket-clock-icon" aria-hidden="true">🕒</span>
<div class="ticket-datetime-stack">
<span class="ticket-time"><?= $time ?></span>
<span class="ticket-date"><?= $date ?></span>
</div>
</div>

<?php if($menu !== 'none'): ?>

<div class="ticket-top-sep" aria-hidden="true"></div>

<div class="ticket-menu dropdown ticket-menu-inline">

<button
type="button"
class="menu-btn menu-btn-inline"
onclick="toggleTicketMenu(this)"
aria-label="عملیات تیکت">

⋮

</button>

<div class="dropdown-menu">

<?php if($menu === 'admin_list'): ?>

<a href="?action=close&id=<?= $ticketId ?>">بستن تیکت</a>

<?php if($isSuper): ?>

<a
href="?action=delete&id=<?= $ticketId ?>"
class="delete-link"
onclick="return confirm('آیا از حذف این تیکت اطمینان دارید؟ این عمل غیرقابل بازگشت است.');">

حذف تیکت

</a>

<?php endif; ?>

<?php elseif($menu === 'admin_list_closed' && $isSuper): ?>

<a
href="?action=delete&id=<?= $ticketId ?>"
class="delete-link"
onclick="return confirm('آیا از حذف این تیکت اطمینان دارید؟ این عمل غیرقابل بازگشت است.');">

حذف تیکت

</a>

<?php elseif($menu === 'admin_view'): ?>

<?php if(($ticket['status'] ?? '') != 'closed'): ?>

<button type="button" onclick="submitCloseTicket()">بستن تیکت</button>

<?php elseif(function_exists('ticket_can_reopen') && ticket_can_reopen($ticket)): ?>

<button type="button" onclick="submitReopenTicket()">بازگشایی مجدد</button>

<?php endif; ?>

<?php if($isSuper): ?>

<button type="button" class="menu-danger" onclick="confirmDeleteTicket()">حذف تیکت</button>

<?php endif; ?>

<?php elseif($menu === 'user_view' && (($ticket['status'] ?? '') != 'closed' || (function_exists('ticket_can_reopen') && ticket_can_reopen($ticket)))): ?>

<?php if(($ticket['status'] ?? '') != 'closed'): ?>

<button type="button" class="menu-danger" onclick="openCloseModalFromMenu()">بستن تیکت</button>

<?php elseif(function_exists('ticket_can_reopen') && ticket_can_reopen($ticket)): ?>

<form method="POST">
<button type="submit" name="reopen_ticket">بازگشایی مجدد</button>
</form>

<?php endif; ?>

<?php endif; ?>

</div>

</div>

<?php endif; ?>

</div>
    <?php

    ticket_top_bar_print_scripts();
}

function ticket_top_bar_print_styles(): void
{
    static $done = false;

    if($done){
        return;
    }

    $done = true;

    echo <<<'CSS'
<style>
.ticket-top-bar{
    display:flex;
    align-items:center;
    gap:8px;
    background:#fff;
    border:1px solid #eef2f7;
    border-radius:16px;
    padding:9px 10px 9px 8px;
    margin-bottom:16px;
    box-shadow:0 4px 14px rgba(15,23,42,.04);
    position:relative;
    overflow:hidden;
    min-height:48px;
}
.ticket-top-accent{
    position:absolute;
    right:0;
    top:0;
    bottom:0;
    width:4px;
    background:linear-gradient(180deg,#0284c7,#06b6d4);
}
.tracking-code-gradient{
    flex-shrink:0;
    padding:7px 13px;
    border-radius:999px;
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    color:#fff;
    font-size:14px;
    font-weight:800;
    line-height:1.2;
    box-shadow:0 4px 12px rgba(2,132,199,.18);
    margin-right:2px;
    z-index:1;
}
.ticket-top-category{
    flex:1 1 auto;
    min-width:0;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:5px;
    padding:0 8px;
}
.ticket-folder-icon{
    font-size:13px;
    line-height:1;
    flex-shrink:0;
}
.ticket-category-text{
    font-size:11px;
    font-weight:700;
    color:#334155;
    line-height:1.4;
    text-align:center;
    overflow:hidden;
    display:-webkit-box;
    -webkit-line-clamp:2;
    -webkit-box-orient:vertical;
}
.ticket-top-datetime{
    flex-shrink:0;
    display:flex;
    align-items:center;
    gap:4px;
    padding:0 4px;
}
.ticket-clock-icon{
    font-size:12px;
    line-height:1;
    flex-shrink:0;
}
.ticket-datetime-stack{
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:1px;
    line-height:1.2;
    text-align:center;
}
.ticket-top-bar .ticket-time{
    font-size:11px;
    font-weight:800;
    color:#0f172a;
    white-space:nowrap;
}
.ticket-top-bar .ticket-date{
    font-size:10px;
    font-weight:600;
    color:#94a3b8;
    white-space:nowrap;
}
.ticket-top-sep{
    width:1px;
    height:28px;
    background:#e2e8f0;
    flex-shrink:0;
}
.ticket-menu-inline{
    position:relative;
    flex-shrink:0;
}
.menu-btn-inline{
    width:22px;
    height:22px;
    border:none;
    border-radius:6px;
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    color:#fff;
    font-size:13px;
    font-weight:800;
    line-height:1;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:0;
    box-shadow:0 3px 8px rgba(2,132,199,.18);
}
.menu-btn-inline:hover{
    opacity:.92;
    transform:translateY(-1px);
}
.ticket-menu{
    position:relative;
}
.ticket-top-bar .dropdown-menu{
    display:none;
    position:absolute;
    left:0;
    top:calc(100% + 6px);
    background:#fff;
    min-width:170px;
    border-radius:14px;
    box-shadow:0 10px 30px rgba(15,23,42,.15);
    border:1px solid #e2e8f0;
    overflow:hidden;
    z-index:30;
}
.ticket-top-bar .dropdown-menu.show{
    display:block;
}
.ticket-top-bar .dropdown-menu a,
.ticket-top-bar .dropdown-menu button{
    display:block;
    width:100%;
    padding:12px 14px;
    border:none;
    background:transparent;
    color:#334155;
    text-decoration:none;
    text-align:right;
    font-size:13px;
    font-weight:700;
    font-family:'Vazirmatn',sans-serif;
    cursor:pointer;
}
.ticket-top-bar .dropdown-menu a:hover,
.ticket-top-bar .dropdown-menu button:hover{
    background:#f8fafc;
}
.ticket-top-bar .dropdown-menu .delete-link,
.ticket-top-bar .dropdown-menu .menu-danger{
    color:#dc2626;
}
.ticket-top-bar .dropdown-menu .delete-link:hover,
.ticket-top-bar .dropdown-menu .menu-danger:hover{
    background:#fef2f2;
}
@media(max-width:720px){
    .ticket-top-bar{
        gap:6px;
        padding:8px 8px 8px 6px;
    }
    .tracking-code-gradient{
        font-size:12px;
        padding:5px 10px;
    }
    .ticket-category-text{
        font-size:11px;
    }
}
</style>
CSS;
}

function ticket_top_bar_print_scripts(): void
{
    static $done = false;

    if($done){
        return;
    }

    $done = true;

    echo <<<'JS'
<script>
function toggleTicketMenu(button){
    const menu = button.parentElement.querySelector('.dropdown-menu');

    if(!menu){
        return;
    }

    document.querySelectorAll('.ticket-top-bar .dropdown-menu.show').forEach(function(item){
        if(item !== menu){
            item.classList.remove('show');
        }
    });

    menu.classList.toggle('show');
}

document.addEventListener('click', function(event){
    if(!event.target.closest('.ticket-menu-inline')){
        document.querySelectorAll('.ticket-top-bar .dropdown-menu.show').forEach(function(item){
            item.classList.remove('show');
        });
    }
});
</script>
JS;
}
