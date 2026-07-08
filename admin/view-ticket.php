<?php

require '../includes/admin_auth.php';
require_once '../includes/ticket_helpers.php';
require_once '../includes/ticket_status_helpers.php';

if(!isset($_GET['id'])){

    die("شناسه تیکت نامعتبر است");

}

$ticket_id =
(int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT t.*, u.fullname
    FROM tickets t
    LEFT JOIN users u ON u.id = t.user_id
    WHERE t.id=?
");

$stmt->execute([
    $ticket_id
]);

$ticket =
$stmt->fetch();

if(!$ticket){

    die("تیکت یافت نشد");

}

if(isset($_POST['reply'])){

    $message = trim($_POST['message']);

    $attachment = null;

    if(
        isset($_FILES['attachment'])
        &&
        $_FILES['attachment']['error'] == 0
    ){

        $uploadDir = '../uploads/tickets/';

        if(!is_dir($uploadDir)){
            mkdir($uploadDir,0777,true);
        }

        $filename =
        time().'_'.
        basename($_FILES['attachment']['name']);

        if(
            move_uploaded_file(
                $_FILES['attachment']['tmp_name'],
                $uploadDir.$filename
            )
        ){
            $attachment = $filename;
        }

    }

    if($message){

        $stmt = $pdo->prepare("
            INSERT INTO ticket_replies
            (
                ticket_id,
                user_id,
                message,
                sender,
                attachment
            )
            VALUES
            (
                ?,?,?,?,?
            )
        ");

        $stmt->execute([

            $ticket_id,

            $_SESSION['user_id'],

            $message,

            'admin',

            $attachment

        ]);

        $stmt = $pdo->prepare("
            UPDATE tickets
            SET last_reply_by='admin_reply'
            WHERE id=?
        ");

        $stmt->execute([$ticket_id]);

        header(
            'Location: view-ticket.php?id=' .
            $ticket_id
        );

        exit;

    }

}

if(isset($_POST['close_ticket'])){

    $stmt = $pdo->prepare("
        UPDATE tickets
        SET
        status='closed',
        closed_at=NOW()
        WHERE id=?
    ");

    $stmt->execute([$ticket_id]);

    header(
        "Location: view-ticket.php?id=" .
        $ticket_id
    );

    exit;

}

if(isset($_POST['reopen_ticket'])){

    $stmt = $pdo->prepare("
        UPDATE tickets
        SET
        status='open',
        closed_at=NULL
        WHERE id=?
    ");

    $stmt->execute([$ticket_id]);

    header(
        "Location: view-ticket.php?id=" .
        $ticket_id
    );

    exit;

}

if(isset($_POST['delete_ticket'])){

    admin_require_super();

    $redirect = ($ticket['status'] ?? '') === 'closed'
        ? 'closed-tickets.php'
        : 'tickets.php';

    if(ticket_delete($pdo, $ticket_id)){
        header('Location: ' . $redirect);
        exit;
    }

    die('خطا در حذف تیکت');

}

$replies = $pdo->prepare("
    SELECT *
    FROM ticket_replies
    WHERE ticket_id=?
    ORDER BY id ASC
");

$replies->execute([$ticket_id]);

$replies =
$replies->fetchAll();

$back_url = 'tickets.php';
$page_title = '🎫 مشاهده تیکت';
$page_header_menu_type = 'action-menu';
$page_header_menu_label = 'عملیات تیکت';
$page_header_menu_items = [];

if(($ticket['status'] ?? '') != 'closed'){

    $page_header_menu_items[] = [
        'label' => 'بستن تیکت',
        'onclick' => 'submitCloseTicket()',
    ];

}elseif(ticket_can_reopen($ticket)){

    $page_header_menu_items[] = [
        'label' => 'بازگشایی مجدد',
        'onclick' => 'submitReopenTicket()',
    ];

}

if(admin_is_super()){

    $page_header_menu_items[] = [
        'label' => 'حذف تیکت',
        'onclick' => 'confirmDeleteTicket()',
    ];

}

require '../includes/header.php';

ticket_view_print_styles();

?>

<div class="ticket-box">

<div class="ticket-view-card">

<?php ticket_render_top_bar($ticket, ['menu' => 'none']); ?>

<div class="ticket-title-box">

<?= htmlspecialchars((string)$ticket['title'], ENT_QUOTES, 'UTF-8') ?>

</div>

<div class="ticket-bottom">

<div class="ticket-bottom-meta">

<?php ticket_status_render_ticket_badges($ticket, 'admin'); ?>

<?php if(!empty($ticket['fullname'])): ?>

<span class="ticket-badge ticket-badge--user">
<span class="ticket-badge__icon" aria-hidden="true">👤</span>
<span class="ticket-badge__text"><?= htmlspecialchars((string)$ticket['fullname'], ENT_QUOTES, 'UTF-8') ?></span>
</span>

<?php endif; ?>

</div>

</div>

<form id="closeTicketForm" method="POST" class="hidden-form">
<input type="hidden" name="close_ticket" value="1">
</form>

<form id="reopenTicketForm" method="POST" class="hidden-form">
<input type="hidden" name="reopen_ticket" value="1">
</form>

<form id="deleteTicketForm" method="POST" class="hidden-form">
<input type="hidden" name="delete_ticket" value="1">
</form>

</div>

<div class="ticket-view-card">

<h3>💬 پاسخ ها</h3>

<?php if(!empty($ticket['message'])): ?>

<div class="reply-box reply-user">

<div class="reply-meta">

درخواست اولیه کاربر

-

<?= fa_datetime($ticket['created_at']) ?>

</div>

<div class="reply-message">

<?= nl2br(htmlspecialchars((string)$ticket['message'], ENT_QUOTES, 'UTF-8')) ?>

<?php ticket_render_attachments($ticket['attachment'] ?? null); ?>

</div>

</div>

<?php endif; ?>

<?php foreach($replies as $reply): ?>

<div class="reply-box <?= $reply['sender'] === 'admin' ? 'reply-admin' : 'reply-user' ?>">

<div class="reply-meta">

<?= $reply['sender'] === 'admin' ? 'پاسخ ادمین' : 'پاسخ کاربر' ?>

-

<?= fa_datetime($reply['created_at']) ?>

</div>

<div class="reply-message">

<?= nl2br(htmlspecialchars((string)$reply['message'], ENT_QUOTES, 'UTF-8')) ?>

<?php ticket_render_attachments($reply['attachment'] ?? null); ?>

</div>

</div>

<?php endforeach; ?>

</div>

<?php if(($ticket['status'] ?? '') !== 'closed'): ?>

<div class="ticket-view-card">

<form method="POST" enctype="multipart/form-data">

<textarea
name="message"
class="form-control"
placeholder="پاسخ خود را بنویسید"
required
style="min-height:140px;"></textarea>

<button type="submit" name="reply" class="btn-custom">ارسال پاسخ</button>

<div class="upload-box">

<div class="upload-box-header">

<div class="upload-box-title">پیوست پاسخ</div>

<div class="upload-icon-actions">

<button
type="button"
class="upload-icon-btn"
id="pickReplyFileBtn"
aria-label="انتخاب فایل">

📎

</button>

<button
type="button"
class="upload-icon-btn upload-icon-camera"
id="openReplyCameraBtn"
aria-label="گرفتن عکس">

📷

</button>

</div>

</div>

<div class="upload-file-name" id="replyAttachmentFileName">فایلی انتخاب نشده</div>

<input
type="file"
id="replyAttachmentInput"
name="attachment"
class="upload-file-input"
accept="image/*,video/*"
tabindex="-1"
aria-hidden="true">

</div>

</form>

</div>

<?php endif; ?>

</div>

<script>

function submitCloseTicket(){

    if(confirm('تیکت بسته شود؟')){
        document.getElementById('closeTicketForm').submit();
    }

}

function submitReopenTicket(){

    if(confirm('تیکت دوباره باز شود؟')){
        document.getElementById('reopenTicketForm').submit();
    }

}

function confirmDeleteTicket(){

    if(confirm('آیا از حذف این تیکت اطمینان دارید؟ این عمل غیرقابل بازگشت است.')){
        document.getElementById('deleteTicketForm').submit();
    }

}

</script>

<?php
ticket_view_print_upload_scripts();
include '../includes/footer.php'; ?>

