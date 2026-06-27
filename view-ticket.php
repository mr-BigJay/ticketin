<?php

require 'includes/auth.php';
require 'includes/db.php';

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
    trim($_POST['message']);

    if($message){

        $stmt = $pdo->prepare("
            INSERT INTO ticket_replies
            (
                ticket_id,
                user_id,
                message,
                sender
            )
            VALUES
            (
                ?,?,?,?
            )
        ");

        $stmt->execute([

            $ticket_id,
            $user_id,
            $message,
            'user'

        ]);

        $stmt = $pdo->prepare("
            UPDATE tickets
            SET last_reply_by='user_reply'
            WHERE id=?
        ");

        $stmt->execute([$ticket_id]);

        header(
            "Location: view-ticket.php?id=" .
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

require 'includes/header.php';

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
    background:#111827;
}

.admin_reply{
    background:#16a34a;
}

.user_reply{
    background:#dc2626;
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

    border:none;

    background:linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    color:white;

    padding:15px 20px;

    border-radius:18px;

    font-size:14px;

    font-weight:bold;

    cursor:pointer;

    font-family:'Vazirmatn',sans-serif;

    transition:.2s;

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

    justify-content:center;

    align-items:center;

    flex-wrap:wrap;

    gap:14px;

    margin-bottom:18px;

    color:#64748b;

    font-size:13px;

}

.tracking-code{

    background:#eff6ff;

    color:#1d4ed8;

    padding:8px 14px;

    border-radius:999px;

    font-size:14px;

    font-weight:800;

    border:1px solid #bfdbfe;

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

.ticket-statuses{

    display:flex;

    gap:8px;

    flex-wrap:wrap;

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

.ticket-statuses{

    display:flex;

    gap:8px;

    flex-wrap:wrap;

}
</style>

<div class="ticket-box">

<div class="card">

<?php

$statusText = [

    'open' => 'باز',

    'pending' => 'درحال بررسی',

    'progress' => 'درحال بررسی',

    'closed' => 'بسته'

];

$replyText = [

    'admin_reply' => 'پاسخ ادمین',

    'user_reply'  => 'پاسخ شما'

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
    type="button"
    onclick="openCloseModal()"
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

</div>

</div>

<?php endforeach; ?>

</div>

<?php if($ticket['status'] != 'closed'): ?>

<div class="card">

<form method="POST">

<textarea
name="message"
class="form-control"
placeholder="پاسخ خود را بنویسید"
required
style="min-height:140px;"></textarea>

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
            <strong>«تیکت‌های جاری»</strong>
            نمایش داده خواهد شد و پس از آن به بخش
            <strong>«تیکت‌های رفع شده»</strong>
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
</script>

<?php include 'includes/footer.php'; ?>