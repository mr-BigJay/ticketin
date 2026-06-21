<?php

require '../includes/auth.php';
require '../includes/db.php';

if(!isset($_GET['id'])){

    die("شناسه تیکت نامعتبر است");

}

$ticket_id =
(int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM tickets
    WHERE id=?
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

$replies = $pdo->prepare("
    SELECT *
    FROM ticket_replies
    WHERE ticket_id=?
    ORDER BY id ASC
");

$replies->execute([$ticket_id]);

$replies =
$replies->fetchAll();

require '../includes/header.php';

?>

<style>

.page-box{

    max-width:950px;

    margin:auto;

}

.page-title{

    font-size:26px;

    font-weight:bold;

    margin-bottom:20px;

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

.status{

    display:inline-block;

    margin-top:15px;

    padding:8px 14px;

    border-radius:30px;

    color:white;

    font-size:12px;

}

.open{

    background:#2563eb;

}

.closed{

    background:#ef4444;

}

.pending{

    background:#f59e0b;

}

.user_reply{

    background:#7c3aed;

}

.admin_reply{

    background:#0f766e;

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

.actions{

    margin-top:20px;

    display:flex;

    gap:10px;

    flex-wrap:wrap;

}

.btn-action{

    border:none;

    color:white;

    padding:12px 16px;

    border-radius:14px;

    cursor:pointer;

    font-size:14px;

    font-family:'Vazirmatn',sans-serif;

}

.close-btn{

    background:linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    color:white;

    border:none;

    box-shadow:0 8px 20px rgba(2,132,199,.18);

}
.close-btn:hover{

    transform:translateY(-2px);

    opacity:.95;

}

.open-btn{

    background:#10b981;

}
.ticket-top{

    display:flex;

    justify-content:flex-start;

    align-items:center;

    gap:12px;

    margin-bottom:18px;

    color:#64748b;

    font-size:13px;

}

.tracking-code{

    background:#eff6ff;

    color:#2563eb;

    border:1px solid #bfdbfe;

    border-radius:999px;

    padding:7px 16px;

    font-weight:800;

}

.ticket-title-box{

    background:#f8fafc;

    border:1px solid #e2e8f0;

    border-radius:18px;

    padding:16px;

    min-height:72px;

    line-height:32px;

    font-size:18px;

    font-weight:700;

    margin-bottom:15px;

}

.ticket-bottom{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-top:18px;

}

.ticket-statuses{

    display:flex;

    gap:8px;

    flex-wrap:wrap;

}

.closed{

    background:#111827;

}

.admin_reply{

    background:#16a34a;

}

.user_reply{

    background:#dc2626;

}

</style>

<div class="page-box">

<div style="margin-bottom:20px;">

<a
href="javascript:history.back()"
class="back-btn-top">

← بازگشت

</a>

</div>

<div class="page-title">

🎫 مشاهده تیکت

</div>

<div class="card">

<?php

$statusText = [

    'open'    => 'باز',
    'pending' => 'درحال بررسی',
    'closed'  => 'بسته'

];

$replyText = [

    'admin_reply' => 'پاسخ ادمین',
    'user_reply'  => 'پاسخ کاربر'

];

?>

<div class="ticket-top">

    <span class="tracking-code">

        <?= $ticket['tracking_code'] ?>

    </span>

    <span>

        📂 <?= htmlspecialchars($ticket['category']) ?>

    </span>

    <span>

        🕒 <?= fa_datetime($ticket['created_at']) ?>

    </span>

</div>

<div class="ticket-title-box">

    <?= htmlspecialchars($ticket['title']) ?>

</div>

<div class="ticket-bottom">

    <div class="ticket-statuses">

        <span
        class="status <?= $ticket['status'] ?>">

            <?= $statusText[$ticket['status']] ?? '-' ?>

        </span>

        <span
        class="status <?= $ticket['last_reply_by'] ?>">

            <?= $replyText[$ticket['last_reply_by']] ?? '-' ?>

        </span>

    </div>

    <?php if($ticket['status'] != 'closed'): ?>

    <button
    type="submit"
    form="closeTicketForm"
    class="btn-action close-btn">

        بستن تیکت

    </button>

    <?php else: ?>

    <form method="POST">

        <button
        type="submit"
        name="reopen_ticket"
        class="btn-action open-btn">

            بازگشایی مجدد

        </button>

    </form>

    <?php endif; ?>

</div>

<form
id="closeTicketForm"
method="POST"
style="display:none">

    <input
    type="hidden"
    name="close_ticket"
    value="1">

</form>
<h3
style="
margin-top:35px;
margin-bottom:20px;
padding-top:10px;
border-top:1px solid #eef2f7;
">

💬 پاسخ ها

</h3>

<div class="reply-box reply-user">

<div class="reply-meta">

    درخواست اولیه کاربر

    -

    <?= fa_datetime($ticket['created_at']) ?>

</div>

<div class="reply-message">

    <?= nl2br(
        htmlspecialchars(
            $ticket['message']
        )
    ) ?>

</div>

</div>

<?php foreach($replies as $reply): ?>

<div
class="reply-box <?= $reply['sender']=='admin' ? 'reply-admin' : 'reply-user' ?>">

<div class="reply-meta">

    <?= $reply['sender']=='admin'
    ? 'پاسخ ادمین'
    : 'پاسخ کاربر' ?>

    -

    <?= fa_datetime($reply['created_at']) ?>

</div>

<div class="reply-message">

    <?= nl2br(
        htmlspecialchars(
            $reply['message']
        )
    ) ?>

    <?php if(!empty($reply['attachment'])): ?>

    <div style="margin-top:10px">

        <a
        href="../uploads/tickets/<?= htmlspecialchars($reply['attachment']) ?>"
        target="_blank">

            📎 مشاهده ضمیمه

        </a>

    </div>

    <?php endif; ?>

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
style="min-height:140px;"></textarea>

<input
type="file"
name="attachment"
class="form-control">

<button
type="submit"
name="reply"
class="btn-custom">

ارسال پاسخ

</button>

</form>

</div>

<?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>

