<?php

session_start();

require '../includes/auth.php';
require '../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

if(isset($_GET['delete'])){

    $id = (int)$_GET['delete'];

    $stmt = $pdo->prepare("
        DELETE FROM reminders
        WHERE id=?
    ");

    $stmt->execute([$id]);

    header("Location: reminders.php");

    exit;

}

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $title =
    trim($_POST['title']);

    $date =
    trim($_POST['reminder_date']);

    if(!empty($_POST['edit_id'])){

        $stmt = $pdo->prepare("
            UPDATE reminders
            SET
            title=?,
            reminder_date=?
            WHERE id=?
        ");

        $stmt->execute([

            $title,
            $date,
            (int)$_POST['edit_id']

        ]);

    }else{

        $stmt = $pdo->prepare("
            INSERT INTO reminders
            (
                title,
                reminder_date,
                created_by
            )
            VALUES
            (?,?,?)
        ");

        $stmt->execute([

            $title,
            $date,
            $_SESSION['user_id']

        ]);

    }

    header("Location: reminders.php");

    exit;

}

$editMode = false;

$editItem = null;

if(isset($_GET['edit'])){

    $editMode = true;

    $id = (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM reminders
        WHERE id=?
    ");

    $stmt->execute([$id]);

    $editItem = $stmt->fetch();

}

$reminders =
$pdo->query("
SELECT *
FROM reminders
ORDER BY reminder_date ASC,id DESC
")->fetchAll();

$back_url = 'index.php';

require '../includes/header.php';

?>

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css"/>

<style>

.reminder-page{

    max-width:1000px;

    margin:auto;

}

.reminder-card{

    background:white;

    border-radius:28px;

    padding:26px;

    margin-bottom:24px;

    box-shadow:
    0 10px 35px rgba(15,23,42,.05);

    border:
    1px solid #eef2f7;

    overflow:visible;

}

.page-title{

    font-size:28px;

    font-weight:800;

    margin-bottom:24px;

    color:#0f172a;

}

.reminder-form input,
.reminder-form textarea{

    width:100%;

    border:
    1px solid #dbeafe;

    border-radius:18px;

    padding:16px;

    margin-bottom:14px;

    font-family:inherit;

    outline:none;

    transition:.2s;

    font-size:14px;

    background:white;

}

.reminder-form input:focus,
.reminder-form textarea:focus{

    border-color:#0284c7;

    box-shadow:
    0 0 0 4px rgba(2,132,199,.08);

}

.reminder-form textarea{

    min-height:130px;

    resize:vertical;

}

.save-btn{

    background:
    linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    border:none;

    color:white;

    padding:15px 24px;

    border-radius:18px;

    font-weight:800;

    cursor:pointer;

    transition:.2s;

}

.save-btn:hover{

    transform:translateY(-2px);

}

.reminder-item{

    background:#f8fafc;

    border:
    1px solid #e2e8f0;

    border-radius:22px;

    padding:18px;

    margin-bottom:14px;

    transition:.2s;

}

.reminder-item:hover{

    border-color:#bfdbfe;

}

.reminder-date{

    color:#0284c7;

    font-weight:800;

    margin-bottom:10px;

    font-size:14px;

}

.reminder-text{

    line-height:34px;

    color:#0f172a;

    font-weight:600;

    margin-bottom:14px;

}

.reminder-actions{

    display:flex;

    gap:10px;

}

.action-btn{

    padding:10px 16px;

    border-radius:14px;

    text-decoration:none;

    font-size:13px;

    font-weight:700;

    transition:.2s;

}

.edit-btn{

    background:#dbeafe;

    color:#1d4ed8;

}

.delete-btn{

    background:#fee2e2;

    color:#dc2626;

}

.action-btn:hover{

    transform:translateY(-2px);

}

.pwt-datepicker-container{

    z-index:999999999 !important;

    position:fixed !important;

}

.pwt-datepicker{

    font-family:inherit !important;

    border-radius:22px !important;

    overflow:hidden !important;

    box-shadow:
    0 20px 50px rgba(15,23,42,.18) !important;

}

.pwt-btn{

    border-radius:12px !important;

}

.pwt-calendar{

    direction:rtl !important;

}

@media(max-width:768px){

    .reminder-actions{

        flex-direction:column;

    }

}

</style>

<div class="reminder-page">

<div class="reminder-card">

<div class="page-title">

⏰ مدیریت یادآوری ها

</div>

<form
method="POST"
class="reminder-form">

<input
type="text"
name="title"
placeholder="متن یادآوری..."
required
value="<?= $editMode ? htmlspecialchars($editItem['title']) : '' ?>">

<input
type="text"
id="reminder_date"
name="reminder_date"
placeholder="انتخاب تاریخ"
required
autocomplete="off"
value="<?= $editMode ? htmlspecialchars($editItem['reminder_date']) : '' ?>">

<?php if($editMode): ?>

<input
type="hidden"
name="edit_id"
value="<?= $editItem['id'] ?>">

<?php endif; ?>

<button
type="submit"
class="save-btn">

<?= $editMode
? 'ذخیره تغییرات'
: 'ثبت یادآوری' ?>

</button>

</form>

</div>

<div class="reminder-card">

<div class="page-title">

📝 لیست یادآوری ها

</div>

<?php if(count($reminders)): ?>

<?php foreach($reminders as $item): ?>

<div class="reminder-item">

<div class="reminder-date">

<?= jalali_date(
$item['reminder_date']
) ?>

</div>

<div class="reminder-text">

<?= nl2br(
htmlspecialchars(
$item['title']
)
) ?>

</div>

<div class="reminder-actions">

<a
href="?edit=<?= $item['id'] ?>"
class="action-btn edit-btn">

✏️ ویرایش

</a>

<a
href="?delete=<?= $item['id'] ?>"
class="action-btn delete-btn"
onclick="return confirm('حذف شود؟')">

🗑 حذف

</a>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div style="
text-align:center;
padding:40px;
color:#94a3b8;
font-weight:700;
">

یادآوری ثبت نشده

</div>

<?php endif; ?>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/jquery/dist/jquery.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>

<script>

$(function(){

    $("#reminder_date").persianDatepicker({

        format: 'YYYY/MM/DD',

        autoClose: true,

        initialValue: false,

        initialValueType: 'persian',

        calendar: {

            persian: {

                locale: 'fa'

            }

        },

        navigator: {

            scroll: {

                enabled: false

            }

        },

        toolbox: {

            calendarSwitch: {

                enabled: false

            }

        }

    });

});

</script>

<?php include '../includes/footer.php'; ?>