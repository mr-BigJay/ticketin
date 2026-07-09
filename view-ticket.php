<?php

require 'includes/auth.php';
require 'includes/db.php';
require_once 'includes/ticket_helpers.php';
require_once 'includes/ticket_status_helpers.php';

$page_title = '📦 مشاهده تیکت';
$back_url = 'tickets.php';
if(!isset($_GET['id'])){

    die("شناسه تیکت نامعتبر است");

}

$ticket_id =
(int)$_GET['id'];

$user_id =
$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM tickets
    WHERE
    id=?
    AND user_id=?
");

$stmt->execute([

    $ticket_id,
    $user_id

]);

$ticket =
$stmt->fetch();

if(!$ticket){

    die("تیکت یافت نشد");

}

if(isset($_POST['reply'])){

    $message =
    trim($_POST['message'] ?? '');

    $attachment = null;

    if(
        isset($_FILES['attachment'])
        &&
        ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        &&
        !empty($_FILES['attachment']['name'])
    ){

        $uploadDir = __DIR__ . '/uploads/tickets/';

        if(!is_dir($uploadDir)){
            mkdir($uploadDir, 0755, true);
        }

        $filename =
        time() . '_' .
        preg_replace(
            '/[^a-zA-Z0-9._-]/',
            '_',
            basename((string)$_FILES['attachment']['name'])
        );

        if(
            move_uploaded_file(
                $_FILES['attachment']['tmp_name'],
                $uploadDir . $filename
            )
        ){
            $attachment = $filename;
        }

    }

    if(
        $message
        &&
        !ticket_text_length_error('متن پاسخ', $message, ticket_reply_max_length())
    ){

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
            $user_id,
            $message,
            'user',
            $attachment

        ]);

        $stmt = $pdo->prepare("
            UPDATE tickets
            SET last_reply_by='user_reply'
            WHERE id=?
        ");

        $stmt->execute([$ticket_id]);

        try{
            require_once 'includes/push_helpers.php';
            push_notify_ticket_user_reply($pdo, $ticket_id, $ticket);
        }catch(Throwable $e){
        }

        try{
            if(is_file(__DIR__ . '/includes/sms_helpers.php')){
                require_once 'includes/sms_helpers.php';
                sms_dispatch_ticket_event(
                    $pdo,
                    'ticket_reply_user',
                    $ticket_id
                );
            }
        }catch(Throwable $e){
        }

        header(
            "Location: view-ticket.php?id=" .
            $ticket_id
        );

        exit;

    }

}

if(isset($_POST['close_ticket'])){

    ticket_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        UPDATE tickets
        SET
            status='closed',
            closed_at=NOW(),
            closed_by='user'
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

    if(!ticket_can_reopen($ticket)){

        header(
            "Location: view-ticket.php?id=" .
            $ticket_id
        );

        exit;

    }

    ticket_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        UPDATE tickets
        SET
            status='open',
            closed_at=NULL,
            closed_by=NULL
        WHERE id=?
    ");

    $stmt->execute([$ticket_id]);

    header(
        "Location: view-ticket.php?id=" .
        $ticket_id
    );

    exit;

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

$page_header_menu_items = [];

if(($ticket['status'] ?? '') !== 'closed'){

    $page_header_menu_items[] = [
        'label' => 'بستن تیکت',
        'onclick' => 'openCloseModalFromMenu()',
    ];

}elseif(ticket_can_reopen($ticket)){

    $page_header_menu_items[] = [
        'label' => 'بازگشایی مجدد',
        'onclick' => 'submitReopenTicketFromMenu()',
    ];

}

if($page_header_menu_items){

    $page_header_menu_type = 'action-menu';
    $page_header_menu_label = 'عملیات تیکت';

}

require 'includes/header.php';

?>

<style>

.ticket-box{

    max-width:950px;

    margin:auto;

}

.card{

    background:white;

    border-radius:24px;

    padding:22px;

    margin-bottom:20px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

}

.ticket-title{

    font-size:22px;

    font-weight:bold;

    margin-bottom:12px;

}

.ticket-meta{

    color:#64748b;

    line-height:34px;

    font-size:14px;

}

.reply-box{

    background:#f8fafc;

    border-radius:18px;

    padding:16px;

    margin-bottom:14px;

}

.reply-user{

    background:#eff6ff;

}

.reply-admin{

    background:#ecfeff;

}

.reply-meta{

    font-size:13px;

    color:#64748b;

    margin-bottom:10px;

}

.reply-message{

    line-height:34px;

    color:#111827;

}

.reply-attachments{

    display:flex;

    flex-wrap:wrap;

    gap:6px;

    margin-top:8px;

}

.reply-attachment-link{

    display:inline-flex;

    align-items:center;

    gap:4px;

    padding:4px 8px;

    border-radius:8px;

    background:#fff;

    border:1px solid #dbeafe;

    color:#0369a1;

    text-decoration:none;

    font-size:11px;

    font-weight:600;

    line-height:1.4;

}

.reply-attachment-link:hover{

    background:#eff6ff;

}

.ticket-title-box{

    background:#f8fafc;

    border:1px solid #e2e8f0;

    border-radius:18px;

    padding:16px;

    min-height:72px;

    display:flex;

    align-items:center;

    font-size:15px;

    font-weight:700;

    line-height:32px;

    margin-bottom:18px;

}

.modal-overlay{

    display:none;

    position:fixed;

    inset:0;

    background:rgba(15,23,42,.25);

    backdrop-filter:blur(8px);

    z-index:9999;

    align-items:center;

    justify-content:center;

}

.modal-overlay.show{

    display:flex;

}

.modal-box{

    width:520px;

    max-width:92%;

    background:#fff;

    border-radius:28px;

    padding:30px;

    box-shadow:0 25px 60px rgba(15,23,42,.18);

    text-align:center;

    border:1px solid #eef2f7;

}

.modal-icon{

    width:70px;

    height:70px;

    margin:0 auto 18px;

    border-radius:50%;

    background:linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    display:flex;

    align-items:center;

    justify-content:center;

    color:#fff;

    font-size:30px;

}

.modal-title{

    font-size:24px;

    font-weight:800;

    color:#0f172a;

    margin-bottom:15px;

}

.modal-text{

    color:#475569;

    line-height:34px;

    font-size:14px;

}

.modal-text strong{

    color:#0f172a;

    font-weight:800;

}

.modal-actions{

    margin-top:25px;

    display:flex;

    justify-content:center;

    gap:12px;

}

.modal-cancel{

    border:none;

    background:#f1f5f9;

    color:#334155;

    padding:12px 20px;

    border-radius:16px;

    font-family:'Vazirmatn',sans-serif;

    cursor:pointer;

}

.modal-confirm{

    border:none;

    background:linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    color:white;

    padding:12px 22px;

    border-radius:16px;

    font-weight:bold;

    font-family:'Vazirmatn',sans-serif;

    cursor:pointer;

}
.ticket-bottom{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-top:15px;

}

.upload-box{

    background:#f8fafc;

    border:2px dashed #cbd5e1;

    border-radius:20px;

    padding:16px;

    margin-top:18px;

}

.upload-box-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:12px;

}

.upload-box-title{

    font-size:15px;

    font-weight:800;

    color:#0f172a;

}

.upload-icon-actions{

    display:flex;

    align-items:center;

    gap:8px;

}

.upload-icon-btn{

    width:44px;

    height:44px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    border:1px solid #dbeafe;

    border-radius:14px;

    background:white;

    font-size:22px;

    line-height:1;

    cursor:pointer;

    transition:.2s;

    box-shadow:0 4px 12px rgba(2,132,199,.08);

    padding:0;

}

.upload-icon-btn:hover{

    transform:translateY(-1px);

    border-color:#7dd3fc;

    background:#f0f9ff;

}

.upload-icon-camera{

    background:linear-gradient(135deg,#0284c7,#06b6d4);

    border-color:transparent;

    box-shadow:0 6px 16px rgba(2,132,199,.22);

}

.upload-icon-camera:hover{

    background:linear-gradient(135deg,#0369a1,#0891b2);

}

.upload-file-name{

    margin-top:12px;

    font-size:13px;

    color:#64748b;

    line-height:1.6;

    word-break:break-word;

    text-align:right;

}

.upload-file-name.has-file{

    color:#0284c7;

    font-weight:700;

}

.upload-file-input{

    position:absolute;

    width:1px;

    height:1px;

    padding:0;

    margin:-1px;

    overflow:hidden;

    clip:rect(0,0,0,0);

    white-space:nowrap;

    border:0;

}

.hidden-form{

    display:none;

}

</style>

<div class="ticket-box">

<div class="card">

<?php ticket_render_top_bar($ticket, ['menu' => 'none']); ?>

<div class="ticket-title-box">

    <?= htmlspecialchars($ticket['title']) ?>

</div>

<div class="ticket-bottom">

    <?php ticket_status_render_ticket_badges($ticket, 'user'); ?>

</div>

</div>

<div class="card">

<h3 style="margin-bottom:18px;">

💬 پاسخ ها

</h3>

<?php if(!empty($ticket['message'])): ?>

<div class="reply-box reply-user">

    <div class="reply-meta">

        درخواست اولیه شما

        -

        <?= fa_datetime($ticket['created_at']) ?>

    </div>

    <div class="reply-message">

        <?= nl2br(
            htmlspecialchars(
                $ticket['message']
            )
        ) ?>

        <?php ticket_render_attachments($ticket['attachment'] ?? null); ?>

    </div>

</div>

<?php endif; ?>


<?php foreach($replies as $reply): ?>

<div
class="reply-box <?= $reply['sender']=='admin' ? 'reply-admin' : 'reply-user' ?>">

<div class="reply-meta">

<?= $reply['sender']=='admin'
? 'پاسخ ادمین'
: 'پاسخ شما' ?>

-

<?= fa_datetime($reply['created_at']) ?>

</div>

<div class="reply-message">

<?= nl2br(
htmlspecialchars(
$reply['message']
)
) ?>

<?php ticket_render_attachments($reply['attachment'] ?? null); ?>

</div>

</div>

<?php endforeach; ?>

</div>

<?php if($ticket['status'] != 'closed'): ?>

<div class="card">

<form method="POST" enctype="multipart/form-data">

<textarea
name="message"
class="form-control"
placeholder="پاسخ خود را بنویسید"
required
maxlength="<?= ticket_reply_max_length() ?>"
style="min-height:140px;"></textarea>

<button
type="submit"
name="reply"
class="btn-custom">

ارسال پاسخ

</button>

<div class="upload-box">

<div class="upload-box-header">

<div class="upload-box-title">
پیوست پاسخ
</div>

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

<div
class="upload-file-name"
id="replyAttachmentFileName">

فایلی انتخاب نشده

</div>

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

<form method="POST" id="reopenTicketForm" class="hidden-form">
<input type="hidden" name="reopen_ticket" value="1">
</form>

<div id="closeModal" class="modal-overlay">

    <div class="modal-box">

        <div class="modal-icon">
            ✓
        </div>

        <div class="modal-title">
            بستن تیکت
        </div>

        <div class="modal-text">
            با بستن این تیکت، درخواست شما رفع شده تلقی می‌شود.
            <br><br>
            این تیکت تا <strong>یک هفته</strong> آینده در بخش
            <strong>«تیکت‌های باز»</strong>
            نمایش داده خواهد شد و پس از آن به بخش
            <strong>«تیکت‌های بسته»</strong>
            منتقل می‌شود.
        </div>

        <div class="modal-actions">

            <button
                type="button"
                onclick="closeModal()"
                class="modal-cancel">
                انصراف
            </button>

            <form method="POST">
                <button
                    type="submit"
                    name="close_ticket"
                    class="modal-confirm">
                    تایید و بستن
                </button>
            </form>

        </div>

    </div> <!-- بسته شدن modal-box -->

</div> <!-- این خط بسته شدن modal-overlay اضافه شد -->

<script>

function openCloseModalFromMenu(){

    document.querySelectorAll('.page-header-dropdown.show').forEach(function(item){
        item.classList.remove('show');
    });

    const menuBtn = document.getElementById('pageHeaderMenuBtn');

    if(menuBtn){
        menuBtn.setAttribute('aria-expanded', 'false');
    }

    openCloseModal();

}

function submitReopenTicketFromMenu(){

    document.querySelectorAll('.page-header-dropdown.show').forEach(function(item){
        item.classList.remove('show');
    });

    const form = document.getElementById('reopenTicketForm');

    if(form){
        form.submit();
    }

}

function openCloseModal(){
    document
        .getElementById('closeModal')
        .classList
        .add('show');
}

function closeModal(){
    document
        .getElementById('closeModal')
        .classList
        .remove('show');
}

document.addEventListener('keydown', function(event){

    if(event.key === 'Escape'){
        closeModal();
    }

});

const replyAttachmentInput =
document.getElementById('replyAttachmentInput');

const replyAttachmentFileName =
document.getElementById('replyAttachmentFileName');

const pickReplyFileBtn =
document.getElementById('pickReplyFileBtn');

const openReplyCameraBtn =
document.getElementById('openReplyCameraBtn');

if(pickReplyFileBtn && replyAttachmentInput){

    pickReplyFileBtn.addEventListener('click', function(){

        replyAttachmentInput.removeAttribute('capture');
        replyAttachmentInput.setAttribute(
            'accept',
            'image/*,video/*'
        );
        replyAttachmentInput.click();

    });

}

if(openReplyCameraBtn && replyAttachmentInput){

    openReplyCameraBtn.addEventListener('click', function(){

        replyAttachmentInput.setAttribute(
            'accept',
            'image/*'
        );
        replyAttachmentInput.setAttribute(
            'capture',
            'environment'
        );
        replyAttachmentInput.click();

    });

}

if(replyAttachmentInput && replyAttachmentFileName){

    replyAttachmentInput.addEventListener('change', function(){

        if(this.files && this.files[0]){

            replyAttachmentFileName.textContent =
            this.files[0].name;

            replyAttachmentFileName.classList.add('has-file');

        }else{

            replyAttachmentFileName.textContent =
            'فایلی انتخاب نشده';

            replyAttachmentFileName.classList.remove('has-file');

        }

    });

}

</script>

<?php include 'includes/footer.php'; ?>