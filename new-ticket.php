<?php

require 'includes/auth.php';
require 'includes/db.php';

$page_title = '🎫 ثبت تیکت جدید';
$back_url = 'dashboard.php';

if(!isset($_SESSION['user_id'])){

    header("Location: login.php");

    exit;

}

$message = "";

$tracking_code = "";

$ticket_id = "";

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $title = trim($_POST['title']);

    $category = trim($_POST['category']);

    $center_id = (int)$_POST['center_id'];

    $sub_type = trim($_POST['sub_type']);

    $sub_id = (int)$_POST['sub_id'];

    $message_text = trim($_POST['message']);

    $priority = 'medium';

    $attachment = "";

    if(
        !$title ||
        !$category ||
        !$center_id ||
        !$sub_type ||
        !$sub_id ||
        !$message_text
    ){

        $message =
        "تمام موارد الزامی را تکمیل کنید";

    }else{

        if(
            isset($_FILES['attachment']) &&
            $_FILES['attachment']['name']
        ){

            $file =
            time() . "_" .
            basename(
                $_FILES['attachment']['name']
            );

            $target =
            "uploads/" . $file;

            move_uploaded_file(
                $_FILES['attachment']['tmp_name'],
                $target
            );

            $attachment = $file;

        }

        $tracking_code =
        rand(100000,999999);

$tracking_code = rand(100000,999999);

do{

    $tracking_code = rand(10000,999999);

    $check = $pdo->prepare("
        SELECT id
        FROM tickets
        WHERE tracking_code=?
        LIMIT 1
    ");

    $check->execute([
        $tracking_code
    ]);

}while($check->fetch());

$stmt = $pdo->prepare("
    INSERT INTO tickets
    (
        tracking_code,
        user_id,
        title,
        category,
        priority,
        message,
        attachment,
        status,
        center_id,
        created_at
    )
    VALUES
    (
        ?,?,?,?,?,?,?, 'open', ?, NOW()
    )
");

$stmt->execute([

    $tracking_code,

    $_SESSION['user_id'],

    $title,

    $category,

    $priority,

    $message_text,

    $attachment,

    $center_id

]);

        $ticket_id =
        $pdo->lastInsertId();

        $message =
        "success";

    }

}

$categories = $pdo->query("
    SELECT *
    FROM categories
    ORDER BY sort_order ASC,id ASC
")->fetchAll();

$centers = $pdo->query("
    SELECT *
    FROM organization_nodes
    WHERE type='center'
    ORDER BY sort_order ASC,id ASC
")->fetchAll();

require 'includes/header.php';

?>

<style>

.ticket-box{

    max-width:850px;

    margin:auto;

}

.card{

    background:white;

    border-radius:28px;

    padding:28px;

    box-shadow:
    0 10px 35px rgba(15,23,42,.05);

    border:1px solid #eef2f7;

}

.section-title{

    margin-bottom:22px;

    font-size:26px;

    font-weight:800;

    color:#0f172a;

}

.hidden{

    display:none;

}

.upload-box{

    background:#f8fafc;

    border:2px dashed #cbd5e1;

    border-radius:20px;

    padding:20px 18px;

    text-align:center;

    margin-bottom:18px;

}

.upload-box-title{

    font-size:15px;

    font-weight:800;

    color:#0f172a;

    margin-bottom:14px;

}

.upload-actions{

    display:flex;

    align-items:stretch;

    justify-content:center;

    gap:10px;

    flex-wrap:wrap;

}

.upload-action-btn{

    flex:1 1 130px;

    max-width:180px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:8px;

    min-height:48px;

    padding:10px 14px;

    border:1px solid #dbeafe;

    border-radius:16px;

    background:white;

    color:#0369a1;

    font-family:'Vazirmatn',sans-serif;

    font-size:14px;

    font-weight:700;

    cursor:pointer;

    transition:.2s;

    box-shadow:0 4px 12px rgba(2,132,199,.08);

}

.upload-action-btn:hover{

    transform:translateY(-1px);

    border-color:#7dd3fc;

    background:#f0f9ff;

}

.upload-action-camera{

    background:linear-gradient(135deg,#0284c7,#06b6d4);

    border-color:transparent;

    color:white;

    box-shadow:0 6px 16px rgba(2,132,199,.22);

}

.upload-action-camera:hover{

    background:linear-gradient(135deg,#0369a1,#0891b2);

    border-color:transparent;

    color:white;

}

.upload-action-icon{

    font-size:20px;

    line-height:1;

}

.upload-file-name{

    margin-top:12px;

    font-size:13px;

    color:#64748b;

    line-height:1.6;

    word-break:break-word;

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

textarea{

    min-height:160px;

    resize:none;

}

.success-overlay{

    position:fixed;

    inset:0;

    background:
    rgba(15,23,42,.45);

    backdrop-filter:blur(8px);

    z-index:999999;

    display:flex;

    align-items:center;

    justify-content:center;

    padding:20px;

}

.success-modal{

    width:100%;

    max-width:480px;

    background:white;

    border-radius:32px;

    padding:34px 28px;

    text-align:center;

    position:relative;

    overflow:hidden;

    animation:popup .25s ease;

}

@keyframes popup{

    from{

        transform:scale(.9);

        opacity:0;

    }

    to{

        transform:scale(1);

        opacity:1;

    }

}

.success-modal::before{

    content:'';

    position:absolute;

    top:-80px;

    left:-80px;

    width:220px;

    height:220px;

    border-radius:50%;

    background:
    rgba(14,165,233,.06);

}

.success-icon{

    position:relative;

    z-index:2;

    width:95px;

    height:95px;

    border-radius:50%;

    background:
    linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    display:flex;

    align-items:center;

    justify-content:center;

    color:white;

    font-size:42px;

    margin:auto;

    margin-bottom:22px;

    box-shadow:
    0 15px 35px rgba(2,132,199,.18);

}

.success-title{

    position:relative;

    z-index:2;

    font-size:28px;

    font-weight:800;

    color:#0f172a;

    margin-bottom:14px;

}

.success-sub{

    position:relative;

    z-index:2;

    font-size:14px;

    color:#64748b;

    line-height:32px;

    margin-bottom:24px;

}

.track-box{

    position:relative;

    z-index:2;

    background:#f8fafc;

    border:1px solid #e2e8f0;

    border-radius:24px;

    padding:22px;

    margin-bottom:22px;

}

.track-label{

    font-size:13px;

    color:#64748b;

    margin-bottom:10px;

}

.track-number{

    font-size:38px;

    font-weight:900;

    color:#0284c7;

    letter-spacing:4px;

}

.track-id{

    margin-top:12px;

    font-size:13px;

    color:#64748b;

}

.close-modal-btn{

    position:relative;

    z-index:2;

    width:100%;

    border:none;

    background:
    linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    color:white;

    padding:16px;

    border-radius:20px;

    font-size:15px;

    font-weight:800;

    cursor:pointer;

    font-family:'Vazirmatn',sans-serif;

    transition:.2s;

}

.close-modal-btn:hover{

    transform:translateY(-2px);

}

</style>

<div class="ticket-box">

<div class="card">


<?php if(
$message &&
$message != 'success'
): ?>

<div class="alert alert-danger">

<?= $message ?>

</div>

<?php endif; ?>

<form
method="POST"
enctype="multipart/form-data">

<input
type="text"
name="title"
class="form-control"
placeholder="موضوع درخواست"
required>

<select
name="category"
class="form-control"
required>

<option value="">
انتخاب دسته بندی
</option>

<?php foreach($categories as $category): ?>

<option
value="<?= htmlspecialchars($category['name']) ?>">

<?= htmlspecialchars($category['name']) ?>

</option>

<?php endforeach; ?>

</select>

<select
name="center_id"
id="centerSelect"
class="form-control"
required>

<option value="">
انتخاب مرکز
</option>

<?php foreach($centers as $center): ?>

<option
value="<?= $center['id'] ?>">

<?= htmlspecialchars($center['name']) ?>

</option>

<?php endforeach; ?>

</select>

<div
id="subTypeBox"
class="hidden">

<select
name="sub_type"
id="subTypeSelect"
class="form-control">

<option value="">
انتخاب نوع زیر مجموعه
</option>

<option value="health_house">

خانه بهداشت

</option>

<option value="unit">

واحد مستقر در مرکز

</option>

</select>

</div>

<div
id="subItemBox"
class="hidden">

<select
name="sub_id"
id="subItemSelect"
class="form-control">

<option value="">
انتخاب مورد
</option>

</select>

</div>

<textarea
name="message"
class="form-control"
placeholder="شرح مشکل یا درخواست"
required></textarea>

<div class="upload-box">

<div class="upload-box-title">
ضمیمه درخواست
</div>

<div class="upload-actions">

<button
type="button"
class="upload-action-btn"
id="pickFileBtn"
aria-label="انتخاب فایل از گالری">

<span class="upload-action-icon" aria-hidden="true">📎</span>
<span>انتخاب فایل</span>

</button>

<button
type="button"
class="upload-action-btn upload-action-camera"
id="openCameraBtn"
aria-label="گرفتن عکس با دوربین">

<span class="upload-action-icon" aria-hidden="true">📷</span>
<span>عکس با دوربین</span>

</button>

</div>

<div
class="upload-file-name"
id="attachmentFileName">

فایلی انتخاب نشده

</div>

<input
type="file"
id="attachmentInput"
name="attachment"
class="upload-file-input"
accept="image/*,video/*"
tabindex="-1"
aria-hidden="true">

</div>

<button
type="submit"
class="btn-custom">

ارسال درخواست

</button>

</form>

</div>

</div>

<?php if($message == 'success'): ?>

<div class="success-overlay">

<div class="success-modal">

<div class="success-icon">

✓

</div>

<div class="success-title">

درخواست شما ثبت شد

</div>

<div class="success-sub">

درخواست شما با موفقیت
در سامانه ثبت گردید.
<br>
لطفاً شماره پیگیری زیر را نگهداری کنید.

</div>

<div class="track-box">

<div class="track-label">

شماره پیگیری درخواست

</div>

<div class="track-number">

<?= $tracking_code ?>

</div>

<div class="track-id">

شماره داخلی تیکت:
<?= $ticket_id ?>

</div>

</div>

<button
class="close-modal-btn"
onclick="window.location='tickets.php';">

مشاهده درخواست‌های جاری

</button>

</div>

</div>

<?php endif; ?>

<script>

let centerSelect =
document.getElementById(
    'centerSelect'
);

let subTypeBox =
document.getElementById(
    'subTypeBox'
);

let subTypeSelect =
document.getElementById(
    'subTypeSelect'
);

let subItemBox =
document.getElementById(
    'subItemBox'
);

let subItemSelect =
document.getElementById(
    'subItemSelect'
);

centerSelect.addEventListener(
    'change',
    function(){

        if(this.value){

            subTypeBox
            .classList
            .remove('hidden');

        }else{

            subTypeBox
            .classList
            .add('hidden');

            subItemBox
            .classList
            .add('hidden');

        }

    }
);

subTypeSelect.addEventListener(
    'change',
    function(){

        let centerId =
        centerSelect.value;

        let type =
        this.value;

        if(!centerId || !type){

            return;

        }

        fetch(
            'tickets.php?action=subs'
            + '&center_id=' + centerId
            + '&type=' + type
        )

        .then(response =>
            response.json()
        )

        .then(data => {

            subItemSelect.innerHTML =
            '<option value="">انتخاب مورد</option>';

            data.forEach(item => {

                subItemSelect.innerHTML +=
                '<option value="' +
                item.id +
                '">' +
                item.name +
                '</option>';

            });

            subItemBox
            .classList
            .remove('hidden');

        });

    }
);

let attachmentInput =
document.getElementById(
    'attachmentInput'
);

let attachmentFileName =
document.getElementById(
    'attachmentFileName'
);

document
.getElementById('pickFileBtn')
.addEventListener('click', function(){

    attachmentInput.removeAttribute('capture');
    attachmentInput.setAttribute(
        'accept',
        'image/*,video/*'
    );
    attachmentInput.click();

});

document
.getElementById('openCameraBtn')
.addEventListener('click', function(){

    attachmentInput.setAttribute(
        'accept',
        'image/*'
    );
    attachmentInput.setAttribute(
        'capture',
        'environment'
    );
    attachmentInput.click();

});

attachmentInput.addEventListener(
    'change',
    function(){

        if(
            this.files &&
            this.files[0]
        ){

            attachmentFileName.textContent =
            this.files[0].name;

            attachmentFileName
            .classList
            .add('has-file');

        }else{

            attachmentFileName.textContent =
            'فایلی انتخاب نشده';

            attachmentFileName
            .classList
            .remove('has-file');

        }

    }
);

</script>

<?php include 'includes/footer.php'; ?>