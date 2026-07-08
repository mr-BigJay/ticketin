<?php

session_start();

$hideBackButton = true;

$page_title = '🏠 داشبورد ادمین';

require '../includes/admin_auth.php';
require_once '../includes/jalali.php';
require_once '../includes/push_helpers.php';

$totalUsers =
$pdo->query("
SELECT COUNT(*) FROM users
")->fetchColumn();

$todayUsers =
$pdo->query("
SELECT COUNT(*) FROM users
WHERE DATE(created_at)=CURDATE()
")->fetchColumn();

$openTickets =
$pdo->query("
SELECT COUNT(*) FROM tickets
WHERE status='open'
")->fetchColumn();

$progressTickets =
$pdo->query("
SELECT COUNT(*) FROM tickets
WHERE status='progress'
")->fetchColumn();

$closedTickets =
$pdo->query("
SELECT COUNT(*) FROM tickets
WHERE status='closed'
")->fetchColumn();

$todayJalali = push_today_jalali_date();

$reminderRows = $pdo->query("
SELECT *
FROM reminders
ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$todayReminders = array_values(array_filter(
    $reminderRows,
    static function(array $row) use ($todayJalali): bool {
        return toEnglishNumbers((string)($row['reminder_date'] ?? '')) === $todayJalali;
    }
));

require '../includes/header.php';

?>

<style>

.dashboard{

    max-width:1100px;

    margin:auto;

}

.today-reminder-box{

    background:
    linear-gradient(
        135deg,
        #eff6ff,
        #dbeafe
    );

    border:
    1px solid #bfdbfe;

    border-radius:24px;

    padding:18px;

    margin-bottom:18px;

    cursor:pointer;

    overflow:hidden;

    position:relative;

    transition:.2s;

}

.today-reminder-box:hover{

    transform:translateY(-2px);

}

.today-reminder-title{

    font-weight:800;

    color:#1e3a8a;

    margin-bottom:10px;

    font-size:16px;

}

.today-reminder-text{

    color:#0f172a;

    line-height:30px;

    font-weight:700;

    min-height:60px;

    max-height:60px;

    overflow:hidden;

    display:-webkit-box;

    -webkit-line-clamp:2;

    -webkit-box-orient:vertical;

    transition:.4s;

    font-size:14px;

}

.stats-grid{

    display:grid;

    grid-template-columns:
    repeat(6,1fr);

    gap:8px;

    margin-bottom:18px;

}
.stat-card{

    background:
    linear-gradient(
        180deg,
        #ffffff,
        #f8fbff
    );

    border-radius:14px;

    padding:10px 6px;

    text-align:center;

    box-shadow:
    0 2px 10px rgba(15,23,42,.03);

    border:
    1px solid #edf4fb;

    transition:.2s;

    min-height:82px;

    display:flex;

    flex-direction:column;

    justify-content:center;

}

.stat-card:hover{

    transform:translateY(-2px);

}

.stat-icon{

    font-size:18px;

    margin-bottom:4px;

}

.stat-number{

    font-size:19px;

    font-weight:800;

    color:#0284c7;

    line-height:24px;

}

.stat-label{

    margin-top:2px;

    color:#64748b;

    font-size:11px;

    font-weight:700;

    line-height:16px;

}

.grid-menu{

    display:grid;

    grid-template-columns:
    repeat(auto-fit,minmax(210px,1fr));

    gap:18px;

}

.menu-card{

    background:white;

    border-radius:24px;

    padding:24px 18px;

    text-align:center;

    text-decoration:none;

    color:#222;

    box-shadow:
    0 10px 30px rgba(15,23,42,.05);

    border:
    1px solid #eef2f7;

    transition:.2s;

}

.menu-card:hover{

    transform:translateY(-3px);

    border-color:#bae6fd;

}

.menu-icon{

    font-size:38px;

    margin-bottom:14px;

}

.menu-title{

    font-size:15px;

    font-weight:800;

    line-height:28px;

}

.logout-btn{

    display:block;

    background:
    linear-gradient(
        135deg,
        #ef4444,
        #dc2626
    );

    color:white;

    text-align:center;

    padding:18px;

    border-radius:20px;

    text-decoration:none;

    margin-top:24px;

    font-weight:800;

    box-shadow:
    0 10px 25px rgba(239,68,68,.2);

    transition:.2s;

}

.logout-btn:hover{

    transform:translateY(-2px);

}

.reminder-modal-overlay{

    position:fixed;

    inset:0;

    background:
    rgba(15,23,42,.45);

    backdrop-filter:blur(8px);

    display:none;

    align-items:center;

    justify-content:center;

    z-index:999999;

    padding:20px;

}

.reminder-modal{

    width:100%;

    max-width:650px;

    background:white;

    border-radius:30px;

    padding:26px;

    animation:modalShow .25s ease;

    max-height:85vh;

    overflow:auto;

}

@keyframes modalShow{

    from{

        opacity:0;

        transform:
        translateY(20px)
        scale(.97);

    }

    to{

        opacity:1;

        transform:
        translateY(0)
        scale(1);

    }

}

.modal-title{

    font-size:24px;

    font-weight:800;

    margin-bottom:22px;

}

.modal-reminder-item{

    background:#f8fafc;

    border:
    1px solid #e2e8f0;

    border-radius:20px;

    padding:16px;

    margin-bottom:14px;

}

.modal-reminder-date{

    color:#0284c7;

    font-weight:800;

    margin-bottom:8px;

    font-size:14px;

}

.modal-reminder-text{

    line-height:32px;

    color:#0f172a;

    font-weight:700;

}

.close-modal-btn{

    width:100%;

    margin-top:18px;

    border:none;

    background:
    linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    color:white;

    border-radius:18px;

    padding:16px;

    font-weight:800;

    cursor:pointer;

}

@media(max-width:768px){

    .stats-grid{

        grid-template-columns:
        repeat(3,1fr);

        gap:6px;

    }

    .grid-menu{

        grid-template-columns:
        repeat(2,1fr);

    }

    .stat-card{

        min-height:72px;

        padding:8px 4px;

    }

    .stat-icon{

        font-size:16px;

        margin-bottom:2px;

    }

    .stat-number{

        font-size:16px;

        line-height:20px;

    }

    .stat-label{

        font-size:10px;

        line-height:14px;

    }

}

</style>

<div class="dashboard">

<?php if(count($todayReminders)): ?>

<div
class="today-reminder-box"
onclick="openReminderModal()">

<div class="today-reminder-title">

⏰ یادآوری های امروز

</div>

<div
id="rotatingReminder"
class="today-reminder-text">

<?= htmlspecialchars(
$todayReminders[0]['title']
) ?>

</div>

</div>

<?php endif; ?>

<div class="stats-grid">

<div class="stat-card">

<div class="stat-icon">
📨
</div>

<div class="stat-number">

<?= $openTickets ?>

</div>

<div class="stat-label">

تیکت باز

</div>

</div>

<div class="stat-card">

<div class="stat-icon">
⏳
</div>

<div class="stat-number">

<?= $progressTickets ?>

</div>

<div class="stat-label">

درحال بررسی

</div>

</div>

<div class="stat-card">

<div class="stat-icon">
✅
</div>

<div class="stat-number">

<?= $closedTickets ?>

</div>

<div class="stat-label">

تیکت بسته

</div>

</div>

<div class="stat-card">

<div class="stat-icon">
👥
</div>

<div class="stat-number">

<?= $totalUsers ?>

</div>

<div class="stat-label">

تعداد کل کاربر

</div>

</div>

<div class="stat-card">

<div class="stat-icon">
🆕
</div>

<div class="stat-number">

<?= $todayUsers ?>

</div>

<div class="stat-label">

ثبت نام امروز

</div>

</div>

</div>

<div class="grid-menu">

<a href="tickets.php" class="menu-card"><div class="menu-icon">🎫</div><div class="menu-title">تیکت های باز</div></a>
<a href="closed-tickets.php" class="menu-card"><div class="menu-icon">✅</div><div class="menu-title">تیکت های بسته</div></a>

<?php if(admin_is_super()): ?>
<a href="pending-users.php" class="menu-card"><div class="menu-icon">📝</div><div class="menu-title">تایید کاربران</div></a>
<a href="departments.php" class="menu-card"><div class="menu-icon">📂</div><div class="menu-title">دسته بندی ها</div></a>
<a href="organization" class="menu-card"><div class="menu-icon">🏥</div><div class="menu-title">ساختار سازمانی</div></a>
<a href="job-titles.php" class="menu-card"><div class="menu-icon">🏷️</div><div class="menu-title">پست سازمانی</div></a>
<?php endif; ?>

<a href="announcements.php" class="menu-card"><div class="menu-icon">📢</div><div class="menu-title">اطلاعیه ها</div></a>
<a href="trainings.php" class="menu-card"><div class="menu-icon">🎓</div><div class="menu-title">آموزش</div></a>
<a href="reminders.php" class="menu-card"><div class="menu-icon">⏰</div><div class="menu-title">یادآوری ها</div></a>

<?php if(admin_is_super()): ?>
<a href="upload-settings.php" class="menu-card"><div class="menu-icon">📤</div><div class="menu-title">مدیریت آپلود</div></a>
<a href="sms-settings.php" class="menu-card"><div class="menu-icon">📱</div><div class="menu-title">مدیریت پیامک</div></a>
<a href="admins.php" class="menu-card"><div class="menu-icon">👑</div><div class="menu-title">مدیریت کاربران ادمین</div></a>
<a href="push-test.php" class="menu-card"><div class="menu-icon">🔔</div><div class="menu-title">تست اعلان‌ها</div></a>
<?php endif; ?>

<a href="users.php" class="menu-card"><div class="menu-icon">👥</div><div class="menu-title">مدیریت کاربران</div></a>

</div>

<a
href="/logout.php"
class="logout-btn">

خروج از سامانه

</a>

</div>

<div
class="reminder-modal-overlay"
id="reminderModal">

<div class="reminder-modal">

<div class="modal-title">

⏰ یادآوری های امروز

</div>

<div class="modal-list">

<?php foreach($todayReminders as $item): ?>

<div class="modal-reminder-item">

<div class="modal-reminder-date">

<?= format_stored_jalali_date($item['reminder_date']) ?>

</div>

<div class="modal-reminder-text">

<?= htmlspecialchars(
$item['title']
) ?>

</div>

</div>

<?php endforeach; ?>

</div>

<button
class="close-modal-btn"
onclick="closeReminderModal()">

بستن

</button>

</div>

</div>

<script>

let reminders = [

<?php foreach($todayReminders as $item): ?>

`<?= addslashes(
$item['title']
) ?>`,

<?php endforeach; ?>

];

let reminderIndex = 0;

if(reminders.length > 1){

    setInterval(() => {

        reminderIndex++;

        if(
            reminderIndex >=
            reminders.length
        ){

            reminderIndex = 0;

        }

        let box =
        document.getElementById(
            'rotatingReminder'
        );

        box.style.opacity = 0;

        box.style.transform =
        'translateY(10px)';

        setTimeout(() => {

            box.innerHTML =
            reminders[
                reminderIndex
            ];

            box.style.opacity = 1;

            box.style.transform =
            'translateY(0px)';

        },250);

    },6000);

}

function openReminderModal(){

    document.getElementById(
        'reminderModal'
    ).style.display = 'flex';

}

function closeReminderModal(){

    document.getElementById(
        'reminderModal'
    ).style.display = 'none';

}

</script>

<?php include '../includes/footer.php'; ?>