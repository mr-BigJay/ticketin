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

    if(!empty($_POST['uploaded_attachments'])){

        $uploaded_files = json_decode(
            (string)$_POST['uploaded_attachments'],
            true
        );

        if(is_array($uploaded_files)){

            $stored_names = [];

            foreach($uploaded_files as $uploaded_file){

                $stored = basename((string)$uploaded_file);

                if(
                    $stored &&
                    is_file(__DIR__ . '/uploads/' . $stored)
                ){
                    $stored_names[] = $stored;
                }

            }

            if($stored_names){
                $attachment = implode(',', $stored_names);
            }

        }

    }

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

            if($attachment === ''){
                $attachment = $file;
            }

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

        unset($_SESSION['pending_ticket_attachments']);

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

    padding:16px;

    margin-bottom:18px;

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

.upload-progress{

    margin-top:14px;

}

.upload-progress.hidden{

    display:none;

}

.upload-progress-bar{

    width:100%;

    height:10px;

    background:#e2e8f0;

    border-radius:999px;

    overflow:hidden;

}

.upload-progress-fill{

    width:0;

    height:100%;

    background:linear-gradient(90deg,#0284c7,#06b6d4);

    border-radius:999px;

    transition:width .15s ease;

}

.upload-progress-text{

    margin-top:8px;

    font-size:12px;

    font-weight:700;

    color:#0369a1;

    text-align:left;

}

.uploaded-files-list{

    margin-top:14px;

    display:flex;

    flex-direction:column;

    gap:8px;

}

.uploaded-files-list:empty{

    display:none;

}

.uploaded-files-title{

    font-size:13px;

    font-weight:800;

    color:#334155;

    margin-bottom:2px;

    text-align:right;

}

.uploaded-file-item{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:10px;

    padding:10px 12px;

    background:white;

    border:1px solid #e2e8f0;

    border-radius:14px;

}

.uploaded-file-meta{

    min-width:0;

    flex:1;

    text-align:right;

}

.uploaded-file-name{

    display:block;

    font-size:13px;

    font-weight:700;

    color:#0f172a;

    white-space:nowrap;

    overflow:hidden;

    text-overflow:ellipsis;

}

.uploaded-file-status{

    display:block;

    margin-top:2px;

    font-size:12px;

    color:#16a34a;

    font-weight:700;

}

.uploaded-file-actions{

    display:flex;

    align-items:center;

    gap:6px;

    flex-shrink:0;

}

.uploaded-file-link{

    font-size:12px;

    font-weight:700;

    color:#0284c7;

    text-decoration:none;

}

.uploaded-file-remove{

    width:30px;

    height:30px;

    border:none;

    border-radius:10px;

    background:#fee2e2;

    color:#b91c1c;

    font-size:16px;

    line-height:1;

    cursor:pointer;

}

.upload-file-input{

    display:none !important;
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
method="POST">

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

<div class="upload-box-header">

<div class="upload-box-title">
ضمیمه درخواست
</div>

<div class="upload-icon-actions">

<button
type="button"
class="upload-icon-btn"
id="pickFileBtn"
aria-label="انتخاب فایل"
title="انتخاب فایل">

📎

</button>

<button
type="button"
class="upload-icon-btn upload-icon-camera"
id="openCameraBtn"
aria-label="عکس با دوربین"
title="عکس با دوربین">

📷

</button>

</div>

</div>

<div
class="upload-progress hidden"
id="uploadProgressWrap">

<div class="upload-progress-bar">

<div
class="upload-progress-fill"
id="uploadProgressFill"></div>

</div>

<div
class="upload-progress-text"
id="uploadProgressText">

۰٪

</div>

</div>

<div class="uploaded-files-title hidden" id="uploadedFilesTitle">
فایل‌های ارسال‌شده
</div>

<div
class="uploaded-files-list"
id="uploadedFilesList"></div>

<input
type="file"
id="galleryInput"
class="upload-file-input"
accept="image/*,video/*">

<input
type="file"
id="cameraInput"
class="upload-file-input"
accept="image/*"
capture="environment">

<input
type="hidden"
name="uploaded_attachments"
id="uploadedAttachmentsField"
value="">

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

let galleryInput =
document.getElementById('galleryInput');

let cameraInput =
document.getElementById('cameraInput');

let uploadProgressWrap =
document.getElementById('uploadProgressWrap');

let uploadProgressFill =
document.getElementById('uploadProgressFill');

let uploadProgressText =
document.getElementById('uploadProgressText');

let uploadedFilesList =
document.getElementById('uploadedFilesList');

let uploadedFilesTitle =
document.getElementById('uploadedFilesTitle');

let uploadedAttachmentsField =
document.getElementById('uploadedAttachmentsField');

let uploadedFiles = [];

function toPersianDigits(value){

    return String(value).replace(
        /\d/g,
        d => '۰۱۲۳۴۵۶۷۸۹'[d]
    );

}

function syncUploadedField(){

    uploadedAttachmentsField.value =
    JSON.stringify(
        uploadedFiles.map(
            file => file.stored
        )
    );

    if(uploadedFiles.length){

        uploadedFilesTitle
        .classList
        .remove('hidden');

    }else{

        uploadedFilesTitle
        .classList
        .add('hidden');

    }

}

function renderUploadedFiles(){

    uploadedFilesList.innerHTML = '';

    uploadedFiles.forEach(file => {

        let item =
        document.createElement('div');

        item.className =
        'uploaded-file-item';

        item.innerHTML =
        '<div class="uploaded-file-meta">' +
        '<span class="uploaded-file-name">' +
        file.original +
        '</span>' +
        '<span class="uploaded-file-status">' +
        '✓ روی سرور ذخیره شد' +
        '</span>' +
        '</div>' +
        '<div class="uploaded-file-actions">' +
        '<a class="uploaded-file-link" href="' +
        file.url +
        '" target="_blank" rel="noopener">مشاهده</a>' +
        '<button type="button" class="uploaded-file-remove" data-stored="' +
        file.stored +
        '" aria-label="حذف فایل">×</button>' +
        '</div>';

        uploadedFilesList.appendChild(item);

    });

    syncUploadedField();

}

function setUploadProgress(percent){

    uploadProgressWrap
    .classList
    .remove('hidden');

    uploadProgressFill.style.width =
    percent + '%';

    uploadProgressText.textContent =
    toPersianDigits(percent) + '٪';

}

function hideUploadProgress(){

    uploadProgressWrap
    .classList
    .add('hidden');

    uploadProgressFill.style.width = '0%';

    uploadProgressText.textContent = '۰٪';

}

function uploadSelectedFile(file){

    let formData = new FormData();

    formData.append('file', file);

    let xhr = new XMLHttpRequest();

    xhr.open('POST', 'ticket-upload.php');

    xhr.upload.addEventListener(
        'progress',
        function(event){

            if(!event.lengthComputable){
                return;
            }

            let percent = Math.round(
                (event.loaded / event.total) * 100
            );

            setUploadProgress(percent);

        }
    );

    xhr.addEventListener('load', function(){

        hideUploadProgress();

        let response = null;

        try{
            response = JSON.parse(xhr.responseText);
        }catch(error){
            alert('خطا در پاسخ سرور');
            return;
        }

        if(
            xhr.status >= 200 &&
            xhr.status < 300 &&
            response.ok
        ){

            uploadedFiles = response.files || [];
            renderUploadedFiles();

        }else{

            alert(
                response.error ||
                'آپلود فایل انجام نشد'
            );

        }

    });

    xhr.addEventListener('error', function(){

        hideUploadProgress();
        alert('خطا در ارتباط با سرور');

    });

    setUploadProgress(0);
    xhr.send(formData);

}

function handleFileInputChange(input){

    if(
        !input.files ||
        !input.files[0]
    ){
        return;
    }

    uploadSelectedFile(input.files[0]);
    input.value = '';

}

document
.getElementById('pickFileBtn')
.addEventListener('click', function(){
    galleryInput.click();
});

document
.getElementById('openCameraBtn')
.addEventListener('click', function(){
    cameraInput.click();
});

galleryInput.addEventListener(
    'change',
    function(){
        handleFileInputChange(this);
    }
);

cameraInput.addEventListener(
    'change',
    function(){
        handleFileInputChange(this);
    }
);

uploadedFilesList.addEventListener(
    'click',
    function(event){

        let removeBtn =
        event.target.closest(
            '.uploaded-file-remove'
        );

        if(!removeBtn){
            return;
        }

        let stored =
        removeBtn.getAttribute('data-stored');

        let formData = new FormData();

        formData.append('action', 'remove');
        formData.append('stored', stored);

        fetch('ticket-upload.php', {
            method: 'POST',
            body: formData
        })

        .then(response => response.json())

        .then(data => {

            if(data.ok){

                uploadedFiles = data.files || [];
                renderUploadedFiles();

            }else{

                alert(
                    data.error ||
                    'حذف فایل انجام نشد'
                );

            }

        })

        .catch(function(){
            alert('خطا در حذف فایل');
        });

    }
);

fetch('ticket-upload.php?action=list')

.then(response => response.json())

.then(data => {

    if(data.ok){

        uploadedFiles = data.files || [];
        renderUploadedFiles();

    }

})

.catch(function(){});

</script>

<?php include 'includes/footer.php'; ?>