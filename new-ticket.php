<?php

require 'includes/auth.php';
require 'includes/db.php';
require_once 'includes/ticket_helpers.php';
require_once 'includes/user_helpers.php';

user_ensure_schema($pdo);
ticket_category_ensure_schema($pdo);

$page_title = '🎫 ثبت تیکت جدید';
$back_url = 'dashboard.php';

if(!isset($_SESSION['user_id'])){
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$message = '';
$tracking_code = '';
$ticket_id = '';

$categories = $pdo->query("
    SELECT *
    FROM categories
    ORDER BY sort_order ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$categoryChildrenMap = ticket_category_children_map($categories);
$rootCategories = $categoryChildrenMap[0] ?? [];
$categoryIndex = [];

foreach($categories as $categoryRow){
    $categoryIndex[(int)$categoryRow['id']] = $categoryRow;
}

$userLocations = ticket_fetch_user_work_locations($pdo, $user_id);
$hasMultipleLocations = count($userLocations) > 1;
$singleLocation = count($userLocations) === 1 ? $userLocations[0] : null;

$categoryChildrenJson = [];

foreach($categoryChildrenMap as $parentKey => $children){
    if($parentKey === 0){
        continue;
    }

    $categoryChildrenJson[(string)$parentKey] = array_map(
        static function(array $child): array{
            return [
                'id' => (int)$child['id'],
                'name' => (string)$child['name'],
            ];
        },
        $children
    );
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $title = trim((string)($_POST['title'] ?? ''));
    $mainCategoryId = (int)($_POST['main_category_id'] ?? 0);
    $subCategoryId = (int)($_POST['sub_category_id'] ?? 0);
    $locationKey = trim((string)($_POST['location_key'] ?? ''));
    $message_text = trim((string)($_POST['message'] ?? ''));

    $mainCategory = $categoryIndex[$mainCategoryId] ?? null;
    $subCategory = $subCategoryId > 0 ? ($categoryIndex[$subCategoryId] ?? null) : null;
    $mainChildren = $categoryChildrenMap[$mainCategoryId] ?? [];
    $requiresSubCategory = count($mainChildren) > 0;

    $lengthError =
        ticket_text_length_error('موضوع درخواست', $title, ticket_title_max_length())
        ?? ticket_text_length_error('شرح درخواست', $message_text, ticket_message_max_length());

    $parsedLocation = ticket_parse_location_key($locationKey);
    $locationAllowed = false;

    if($parsedLocation){
        foreach($userLocations as $userLocation){
            if($userLocation['location_key'] === $locationKey){
                $locationAllowed = true;
                break;
            }
        }
    }

    if($lengthError){
        $message = $lengthError;
    }elseif(
        !$title ||
        !$mainCategory ||
        ($requiresSubCategory && !$subCategory) ||
        !$parsedLocation ||
        !$locationAllowed ||
        !$message_text
    ){
        $message = 'تمام موارد الزامی را تکمیل کنید';
    }elseif(
        $subCategory &&
        ticket_category_parent_id($subCategory['parent_id'] ?? null) !== $mainCategoryId
    ){
        $message = 'دسته‌بندی انتخاب‌شده معتبر نیست';
    }else{

        $category = ticket_category_format_display(
            (string)$mainCategory['name'],
            $subCategory ? (string)$subCategory['name'] : null
        );

        $center_id = (int)$parsedLocation['center_id'];
        $sub_type = (string)$parsedLocation['child_type'];
        $sub_id = (int)$parsedLocation['node_id'];

        $attachment = '';

        if(
            isset($_FILES['attachment']) &&
            ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK &&
            !empty($_FILES['attachment']['name'])
        ){
            $file = time() . '_' . basename((string)$_FILES['attachment']['name']);
            $target = 'uploads/' . $file;

            if(move_uploaded_file($_FILES['attachment']['tmp_name'], $target)){
                $attachment = $file;
            }
        }

        do{
            $tracking_code = (string)rand(10000, 999999);

            $check = $pdo->prepare("
                SELECT id
                FROM tickets
                WHERE tracking_code=?
                LIMIT 1
            ");

            $check->execute([$tracking_code]);
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
            $user_id,
            $title,
            $category,
            'medium',
            $message_text,
            $attachment,
            $center_id,
        ]);

        $ticket_id = (string)$pdo->lastInsertId();
        $message = 'success';
    }
}

require 'includes/header.php';

ticket_view_print_styles();

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
    box-shadow:0 10px 35px rgba(15,23,42,.05);
    border:1px solid #eef2f7;
}

.form-section{
    margin-bottom:14px;
}

.form-section-label{
    display:block;
    text-align:center;
    font-size:13px;
    font-weight:800;
    color:#334155;
    margin-bottom:8px;
}

.form-row-duo{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
}

.form-control-compact{
    font-size:13px;
    padding:10px 12px;
    min-height:44px;
}

.location-readonly{
    background:#f1f5f9;
    color:#475569;
    cursor:default;
}

.field-hint{
    margin-top:6px;
    font-size:11px;
    color:#94a3b8;
    text-align:left;
}

.hidden{
    display:none;
}

.upload-box{
    margin-bottom:18px;
}

textarea{
    min-height:160px;
    resize:none;
}

.success-overlay{
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.45);
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
    background:rgba(14,165,233,.06);
}

.success-icon{
    position:relative;
    z-index:2;
    width:95px;
    height:95px;
    border-radius:50%;
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    display:flex;
    align-items:center;
    justify-content:center;
    color:white;
    font-size:42px;
    margin:auto;
    margin-bottom:22px;
    box-shadow:0 15px 35px rgba(2,132,199,.18);
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
    background:linear-gradient(135deg,#0284c7,#06b6d4);
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

@media(max-width:640px){
    .form-row-duo{
        grid-template-columns:1fr 1fr;
        gap:8px;
    }
}

</style>

<div class="ticket-box">

<div class="card">

<?php if($message && $message !== 'success'): ?>

<div class="alert alert-danger">
<?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
</div>

<?php endif; ?>

<?php if(!$userLocations): ?>

<div class="alert alert-danger">
محل فعالیت شما ثبت نشده است. لطفاً با پشتیبانی تماس بگیرید.
</div>

<?php else: ?>

<form method="POST" enctype="multipart/form-data" id="newTicketForm">

<input
type="text"
name="title"
class="form-control"
placeholder="موضوع درخواست"
required
maxlength="<?= ticket_title_max_length() ?>">

<div class="form-section">
<label class="form-section-label" for="mainCategorySelect">دسته بندی</label>
<div class="form-row-duo">
<select
name="main_category_id"
id="mainCategorySelect"
class="form-control form-control-compact"
required>

<option value="">دسته بندی اصلی</option>

<?php foreach($rootCategories as $rootCategory): ?>

<option value="<?= (int)$rootCategory['id'] ?>">
<?= htmlspecialchars((string)$rootCategory['name'], ENT_QUOTES, 'UTF-8') ?>
</option>

<?php endforeach; ?>

</select>

<select
name="sub_category_id"
id="subCategorySelect"
class="form-control form-control-compact"
disabled>

<option value="">زیر مجموعه</option>

</select>
</div>
</div>

<div class="form-section">
<label class="form-section-label" for="locationSelect">محل فعالیت</label>

<?php if($hasMultipleLocations): ?>

<select
name="location_key"
id="locationSelect"
class="form-control form-control-compact"
required>

<option value="">انتخاب محل فعالیت</option>

<?php
$groupedLocations = [];

foreach($userLocations as $userLocation){
    $groupedLocations[$userLocation['center_name']][] = $userLocation;
}

foreach($groupedLocations as $centerName => $centerLocations):
?>

<optgroup label="<?= htmlspecialchars((string)$centerName, ENT_QUOTES, 'UTF-8') ?>">

<?php foreach($centerLocations as $userLocation): ?>

<option value="<?= htmlspecialchars($userLocation['location_key'], ENT_QUOTES, 'UTF-8') ?>">
<?= htmlspecialchars($userLocation['label'], ENT_QUOTES, 'UTF-8') ?>
</option>

<?php endforeach; ?>

</optgroup>

<?php endforeach; ?>

</select>

<?php else: ?>

<input
type="hidden"
name="location_key"
value="<?= htmlspecialchars((string)$singleLocation['location_key'], ENT_QUOTES, 'UTF-8') ?>">

<input
type="text"
id="locationSelect"
class="form-control form-control-compact location-readonly"
value="<?= htmlspecialchars((string)$singleLocation['label'], ENT_QUOTES, 'UTF-8') ?>"
readonly
tabindex="-1"
aria-readonly="true">

<?php endif; ?>

</div>

<textarea
name="message"
class="form-control"
placeholder="شرح مشکل یا درخواست"
required
maxlength="<?= ticket_message_max_length() ?>"></textarea>

<div class="upload-box">

<div class="upload-box-header">

<div class="upload-box-title">پیوست درخواست</div>

<div class="upload-icon-actions">

<button
type="button"
class="upload-icon-btn"
id="pickTicketFileBtn"
aria-label="انتخاب فایل">

📎

</button>

<button
type="button"
class="upload-icon-btn upload-icon-camera"
id="openTicketCameraBtn"
aria-label="گرفتن عکس">

📷

</button>

</div>

</div>

<div class="upload-file-name" id="ticketAttachmentFileName">فایلی انتخاب نشده</div>

<input
type="file"
id="ticketAttachmentInput"
name="attachment"
class="upload-file-input"
accept="image/*,video/*"
tabindex="-1"
aria-hidden="true">

</div>

<button type="submit" class="btn-custom">ارسال درخواست</button>

</form>

<?php endif; ?>

</div>

</div>

<?php if($message === 'success'): ?>

<div class="success-overlay">

<div class="success-modal">

<div class="success-icon">✓</div>

<div class="success-title">درخواست شما ثبت شد</div>

<div class="success-sub">
درخواست شما با موفقیت در سامانه ثبت گردید.
<br>
لطفاً شماره پیگیری زیر را نگهداری کنید.
</div>

<div class="track-box">

<div class="track-label">شماره پیگیری درخواست</div>

<div class="track-number"><?= htmlspecialchars($tracking_code, ENT_QUOTES, 'UTF-8') ?></div>

<div class="track-id">
شماره داخلی تیکت:
<?= htmlspecialchars($ticket_id, ENT_QUOTES, 'UTF-8') ?>
</div>

</div>

<button class="close-modal-btn" onclick="window.location='tickets.php';">
مشاهده تیکت‌های باز
</button>

</div>

</div>

<?php endif; ?>

<script>

const categoryChildren = <?= json_encode($categoryChildrenJson, JSON_UNESCAPED_UNICODE) ?>;

const mainCategorySelect = document.getElementById('mainCategorySelect');
const subCategorySelect = document.getElementById('subCategorySelect');

function resetSubCategorySelect(disabled, placeholder){

    if(!subCategorySelect){
        return;
    }

    subCategorySelect.innerHTML =
        '<option value="">' + placeholder + '</option>';

    subCategorySelect.disabled = disabled;
    subCategorySelect.required = !disabled;
}

if(mainCategorySelect && subCategorySelect){

    mainCategorySelect.addEventListener('change', function(){

        const mainId = this.value;
        const children = categoryChildren[mainId] || [];

        if(!mainId){
            resetSubCategorySelect(true, 'زیر مجموعه');
            return;
        }

        if(!children.length){
            resetSubCategorySelect(true, 'زیرمجموعه ندارد');
            return;
        }

        resetSubCategorySelect(false, 'زیر مجموعه');

        children.forEach(function(child){
            const option = document.createElement('option');
            option.value = String(child.id);
            option.textContent = child.name;
            subCategorySelect.appendChild(option);
        });

    });

}

</script>

<?php
ticket_view_print_upload_scripts('ticketAttachmentInput', 'ticketAttachmentFileName', 'pickTicketFileBtn', 'openTicketCameraBtn');
include 'includes/footer.php'; ?>
