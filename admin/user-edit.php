<?php

require '../includes/admin_auth.php';
require_once '../includes/user_helpers.php';

user_ensure_schema($pdo);

$user_id = (int)($_GET['id'] ?? $_POST['user_id'] ?? 0);

if(!$user_id){

    header("Location: users.php");

    exit;

}

$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id=? AND role='user'
");

$stmt->execute([$user_id]);

$user = $stmt->fetch();

if(!$user){

    header("Location: users.php");

    exit;

}

$message = "";
[$userFirstname, $userLastname] = user_split_fullname((string)($user['fullname'] ?? ''));

if(isset($_GET['delete_rel'])){

    $rel_id = (int)$_GET['delete_rel'];

    $stmt = $pdo->prepare("
        DELETE FROM user_organization_rel
        WHERE id=? AND user_id=?
    ");

    $stmt->execute([$rel_id, $user_id]);

    $remaining = $pdo->prepare("
        SELECT node_id
        FROM user_organization_rel
        WHERE user_id=?
        ORDER BY id ASC
        LIMIT 1
    ");

    $remaining->execute([$user_id]);

    $first = $remaining->fetch();

    $primary = $first ? $first['node_id'] : null;

    $pdo->prepare("
        UPDATE users
        SET organization_node_id=?
        WHERE id=?
    ")->execute([$primary, $user_id]);

    header("Location: user-edit.php?id=" . $user_id . "&msg=deleted");

    exit;

}

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    if(isset($_POST['save_profile'])){

        $job_title_id = (int)$_POST['job_title_id'];
        $status = $_POST['status'] ?? 'active';
        $profileFullname = (string)$user['fullname'];
        $profileMobile = (string)$user['mobile'];
        $profileNationalCode = (string)$user['national_code'];

        if(!in_array($status, ['active', 'inactive', 'pending'], true)){

            $status = 'active';

        }

        if(admin_is_super()){

            $identity = user_validate_identity_fields(
                (string)($_POST['firstname'] ?? ''),
                (string)($_POST['lastname'] ?? ''),
                (string)($_POST['mobile'] ?? ''),
                (string)($_POST['national_code'] ?? '')
            );

            if($identity['error']){
                $message = $identity['error'];
            }else{
                $existingIdentity = user_identity_lookup_excluding(
                    $pdo,
                    $identity['mobile'],
                    $identity['national_code'],
                    $user_id
                );

                if($existingIdentity){
                    $message = user_identity_conflict_message($existingIdentity)
                        ?? 'کاربر دیگری با این اطلاعات وجود دارد';
                }else{
                    $profileFullname = $identity['fullname'];
                    $profileMobile = $identity['mobile'];
                    $profileNationalCode = $identity['national_code'];
                }
            }
        }

        if($message === ''){

            $jobStmt = $pdo->prepare("
                SELECT title
                FROM job_titles
                WHERE id=?
            ");

            $jobStmt->execute([$job_title_id]);

            $job = $jobStmt->fetch();

            if(!$job_title_id){

                $message = "پست سازمانی را انتخاب کنید";

            }elseif($job){

                if(admin_is_super()){

                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET
                        fullname=?,
                        mobile=?,
                        national_code=?,
                        job_title_id=?,
                        job_title=?,
                        status=?
                        WHERE id=?
                    ");

                    $stmt->execute([
                        $profileFullname,
                        $profileMobile,
                        $profileNationalCode,
                        $job_title_id,
                        $job['title'],
                        $status,
                        $user_id,
                    ]);

                }else{

                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET
                        job_title_id=?,
                        job_title=?,
                        status=?
                        WHERE id=?
                    ");

                    $stmt->execute([
                        $job_title_id,
                        $job['title'],
                        $status,
                        $user_id,
                    ]);

                }

                $message = "اطلاعات کاربر بروزرسانی شد";

                $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");

                $stmt->execute([$user_id]);

                $user = $stmt->fetch();
                [$userFirstname, $userLastname] = user_split_fullname((string)($user['fullname'] ?? ''));

            }else{

                $message = "پست سازمانی انتخاب شده معتبر نیست";

            }

        }

    }

    if(isset($_POST['add_service'])){

        $center_id = (int)$_POST['center_id'];
        $sub_items = $_POST['sub_items'] ?? [];

        if(!$center_id || empty($sub_items)){

            $message = "مرکز و حداقل یک محل خدمت را انتخاب کنید";

        }else{

            $added = 0;

            foreach($sub_items as $node_id){

                $node_id = (int)$node_id;

                if($node_id <= 0) continue;

                $check = $pdo->prepare("
                    SELECT id
                    FROM user_organization_rel
                    WHERE user_id=? AND node_id=?
                ");

                $check->execute([$user_id, $node_id]);

                if(!$check->fetch()){

                    $insert = $pdo->prepare("
                        INSERT INTO user_organization_rel
                        (user_id, center_id, node_id)
                        VALUES (?, ?, ?)
                    ");

                    $insert->execute([$user_id, $center_id, $node_id]);

                    $added++;

                }

            }

            if($added > 0 && empty($user['organization_node_id'])){

                $pdo->prepare("
                    UPDATE users
                    SET organization_node_id=?
                    WHERE id=?
                ")->execute([(int)$sub_items[0], $user_id]);

            }

            if($added > 0){

                $message = "محل خدمت اضافه شد";

            }else{

                $message = "محل خدمت انتخاب شده قبلاً ثبت شده";

            }

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");

            $stmt->execute([$user_id]);

            $user = $stmt->fetch();

        }

    }

}

if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'){

    $message = "محل خدمت حذف شد";

}

$jobTitles = $pdo->query("
    SELECT *
    FROM job_titles
    ORDER BY title ASC
")->fetchAll();

$centers = $pdo->query("
    SELECT *
    FROM organization_nodes
    WHERE type='center'
    ORDER BY sort_order ASC, id ASC
")->fetchAll();

$userNodes = $pdo->prepare("
    SELECT

    user_organization_rel.id as rel_id,

    child.name as child_name,

    center.name as center_name,

    child.type as child_type

    FROM user_organization_rel

    LEFT JOIN organization_nodes child
    ON user_organization_rel.node_id = child.id

    LEFT JOIN organization_nodes center
    ON user_organization_rel.center_id = center.id

    WHERE user_organization_rel.user_id=?

    ORDER BY user_organization_rel.id DESC
");

$userNodes->execute([$user_id]);

$currentNodes = $userNodes->fetchAll();

$back_url = 'users.php';
$page_title = '✏️ ویرایش کاربر';

require '../includes/header.php';

?>

<style>

.page-box{

    max-width:850px;

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

    padding:24px;

    margin-bottom:20px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

    overflow:visible;

}

.section-title{

    font-size:18px;

    font-weight:bold;

    margin-bottom:16px;

}

.info-grid{

    display:grid;

    grid-template-columns:1fr 1fr;

    gap:14px;

    margin-bottom:16px;

}

.info-item{

    background:#f8fafc;

    border-radius:18px;

    padding:16px;

    line-height:34px;

}

.info-label{

    font-size:13px;

    color:#64748b;

}

.info-value{

    font-size:15px;

    font-weight:bold;

}

.plus-btn{

    width:100%;

    border:none;

    background:#2563eb;

    color:white;

    padding:14px;

    border-radius:18px;

    font-size:15px;

    cursor:pointer;

    margin-bottom:16px;

    font-family:'Vazirmatn',sans-serif;

}

.hidden{display:none;}

.checkbox-wrapper{

    background:#f8fafc;

    border:1px solid #dbeafe;

    border-radius:16px;

    padding:14px;

    margin-bottom:15px;

}

.checkbox-item{

    display:block;

    padding:10px;

    border-bottom:1px solid #e2e8f0;

    font-size:14px;

}

.checkbox-item:last-child{border-bottom:none;}

.unit-card{

    background:linear-gradient(135deg,#eff6ff,#dbeafe);

    border-radius:18px;

    padding:16px;

    margin-bottom:12px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:10px;

    position:relative;

    overflow:visible;

}

.unit-name{

    font-size:15px;

    font-weight:bold;

    color:#1e3a8a;

}

.btn-delete-rel{

    background:#ef4444;

    color:white;

    border:none;

    padding:8px 14px;

    border-radius:12px;

    font-size:13px;

    cursor:pointer;

    text-decoration:none;

    font-family:'Vazirmatn',sans-serif;

    white-space:nowrap;

}

@media(max-width:768px){

    .info-grid{grid-template-columns:1fr;}

    .name-row,
    .split-row{
        flex-direction:column;
    }

    .name-row .form-control:first-child{
        flex:1;
        max-width:none;
    }

}

.name-row,
.split-row{
    display:flex;
    gap:8px;
    margin-bottom:10px;
}

.name-row .form-control:first-child{
    flex:0 0 35%;
    max-width:35%;
    min-width:0;
    margin-bottom:0;
}

.name-row .form-control:last-child,
.split-row .form-control{
    flex:1;
    min-width:0;
    margin-bottom:0;
}

</style>

<div class="page-box">

<?php if($message): ?>

<div class="alert"><?= htmlspecialchars($message) ?></div>

<?php endif; ?>

<div class="card">

<div class="section-title">اطلاعات کاربر</div>

<form method="POST" id="userEditForm">

<input type="hidden" name="user_id" value="<?= $user_id ?>">

<?php if(admin_is_super()): ?>

<label class="info-label">نام و نام خانوادگی</label>

<div class="name-row">

<input
type="text"
name="firstname"
class="form-control"
placeholder="نام"
required
value="<?= htmlspecialchars($userFirstname, ENT_QUOTES, 'UTF-8') ?>">

<input
type="text"
name="lastname"
class="form-control"
placeholder="نام خانوادگی"
required
value="<?= htmlspecialchars($userLastname, ENT_QUOTES, 'UTF-8') ?>">

</div>

<label class="info-label" style="display:block;margin-top:4px;">شماره موبایل و کد ملی</label>

<div class="split-row">

<input
type="text"
name="mobile"
id="mobileField"
class="form-control"
placeholder="شماره موبایل"
required
inputmode="numeric"
maxlength="11"
value="<?= htmlspecialchars((string)$user['mobile'], ENT_QUOTES, 'UTF-8') ?>">

<input
type="text"
name="national_code"
id="nationalCodeField"
class="form-control"
placeholder="کد ملی"
required
inputmode="numeric"
maxlength="10"
value="<?= htmlspecialchars((string)$user['national_code'], ENT_QUOTES, 'UTF-8') ?>">

</div>

<?php else: ?>

<div class="info-grid">

<div class="info-item">

<div class="info-label">نام و نام خانوادگی</div>

<div class="info-value"><?= htmlspecialchars($user['fullname']) ?></div>

</div>

<div class="info-item">

<div class="info-label">شماره موبایل</div>

<div class="info-value"><?= htmlspecialchars($user['mobile']) ?></div>

</div>

<div class="info-item">

<div class="info-label">کد ملی</div>

<div class="info-value"><?= htmlspecialchars($user['national_code'] ?: '-') ?></div>

</div>

</div>

<?php endif; ?>

<label class="info-label" style="display:block;margin-top:14px;">پست سازمانی</label>

<select name="job_title_id" class="form-control" required>

<option value="">انتخاب پست سازمانی</option>

<?php foreach($jobTitles as $job): ?>

<option
value="<?= $job['id'] ?>"
<?= ($user['job_title_id'] ?? 0) == $job['id'] ? 'selected' : '' ?>>

<?= htmlspecialchars($job['title']) ?>

</option>

<?php endforeach; ?>

</select>

<label class="info-label" style="display:block;margin-top:14px;">وضعیت</label>

<select name="status" class="form-control">

<option value="active" <?= $user['status'] == 'active' ? 'selected' : '' ?>>فعال</option>

<option value="inactive" <?= $user['status'] == 'inactive' ? 'selected' : '' ?>>غیرفعال</option>

<option value="pending" <?= $user['status'] == 'pending' ? 'selected' : '' ?>>در انتظار تایید</option>

</select>

<button type="submit" name="save_profile" class="btn-custom" style="margin-top:16px;">

ذخیره اطلاعات

</button>

</form>

</div>

<div class="card">

<div class="section-title">🏢 محل‌های خدمت</div>

<?php if(count($currentNodes)): ?>

<?php foreach($currentNodes as $node): ?>

<div class="unit-card">

<div>

<div class="unit-name">

<?= htmlspecialchars($node['center_name']) ?>

-

<?= htmlspecialchars($node['child_name']) ?>

</div>

<div style="margin-top:6px;font-size:13px;color:#64748b;">

<?= $node['child_type'] == 'unit' ? 'واحد مستقر در مرکز' : 'خانه بهداشت' ?>

</div>

</div>

<a
href="?id=<?= $user_id ?>&delete_rel=<?= $node['rel_id'] ?>"
class="btn-delete-rel"
onclick="return confirm('این محل خدمت حذف شود؟')">

🗑 حذف

</a>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="info-item" style="margin-bottom:16px;">هیچ محل خدمتی ثبت نشده</div>

<?php endif; ?>

<button type="button" class="plus-btn" id="showFormBtn">

➕ افزودن محل خدمت

</button>

<form method="POST" id="serviceForm" class="hidden">

<input type="hidden" name="user_id" value="<?= $user_id ?>">

<select name="center_id" id="centerSelect" class="form-control" required>

<option value="">انتخاب مرکز</option>

<?php foreach($centers as $center): ?>

<option value="<?= $center['id'] ?>"><?= htmlspecialchars($center['name']) ?></option>

<?php endforeach; ?>

</select>

<select name="sub_type" id="subTypeSelect" class="form-control">

<option value="">انتخاب نوع زیر مجموعه</option>

<option value="health_house">خانه بهداشت</option>

<option value="unit">واحد مستقر در مرکز</option>

</select>

<div id="subItemsBox" class="hidden">

<div id="subItemsSelect" class="checkbox-wrapper"></div>

</div>

<button type="submit" name="add_service" class="btn-custom">

افزودن محل خدمت

</button>

</form>

</div>

</div>

<script>

document.getElementById('showFormBtn').addEventListener('click', function(){

    document.getElementById('serviceForm').classList.toggle('hidden');

});

const centerSelect = document.getElementById('centerSelect');
const subTypeSelect = document.getElementById('subTypeSelect');
const subItemsBox = document.getElementById('subItemsBox');
const subItemsSelect = document.getElementById('subItemsSelect');

subTypeSelect.addEventListener('change', function(){

    const centerId = centerSelect.value;
    const type = this.value;

    if(!centerId || !type) return;

    fetch('../tickets.php?action=subs&center_id=' + centerId + '&type=' + type)

    .then(response => response.json())

    .then(data => {

        subItemsSelect.innerHTML = '';

        data.forEach(item => {

            subItemsSelect.innerHTML +=

            '<label class="checkbox-item">' +

            '<input type="checkbox" name="sub_items[]" value="' + item.id + '"> ' +

            item.name +

            '</label>';

        });

        subItemsBox.classList.remove('hidden');

    });

});

function userEditToEnglishDigits(value){
    const persian = '۰۱۲۳۴۵۶۷۸۹';
    const arabic = '٠١٢٣٤٥٦٧٨٩';

    return String(value).replace(/[۰-۹٠-٩]/g, function(ch){
        const persianIndex = persian.indexOf(ch);

        if(persianIndex !== -1){
            return String(persianIndex);
        }

        const arabicIndex = arabic.indexOf(ch);

        return arabicIndex !== -1 ? String(arabicIndex) : ch;
    }).replace(/[^\d]/g, '');
}

function userEditPadNationalCode(digits){
    if(digits === ''){
        return '';
    }

    if(digits.length > 10){
        return digits.slice(0, 10);
    }

    return digits.padStart(10, '0');
}

function userEditNormalizeMobileField(){
    const field = document.getElementById('mobileField');

    if(!field){
        return;
    }

    let digits = userEditToEnglishDigits(field.value);

    if(digits.startsWith('98') && digits.length === 12){
        digits = '0' + digits.slice(2);
    }else if(digits.startsWith('9') && digits.length === 10){
        digits = '0' + digits;
    }

    field.value = digits.slice(0, 11);
}

function userEditNormalizeNationalCodeField(padNow){
    const field = document.getElementById('nationalCodeField');

    if(!field){
        return '';
    }

    let digits = userEditToEnglishDigits(field.value).slice(0, 10);

    if(padNow && digits !== ''){
        digits = userEditPadNationalCode(digits);
    }

    field.value = digits;

    return digits;
}

const userEditForm = document.getElementById('userEditForm');
const mobileField = document.getElementById('mobileField');
const nationalCodeField = document.getElementById('nationalCodeField');

if(mobileField){
    mobileField.addEventListener('input', userEditNormalizeMobileField);
    mobileField.addEventListener('blur', userEditNormalizeMobileField);
}

if(nationalCodeField){
    nationalCodeField.addEventListener('input', function(){
        userEditNormalizeNationalCodeField(false);
    });
    nationalCodeField.addEventListener('blur', function(){
        userEditNormalizeNationalCodeField(true);
    });
}

if(userEditForm){
    userEditForm.addEventListener('submit', function(event){
        if(!mobileField || !nationalCodeField){
            return;
        }

        userEditNormalizeMobileField();
        const nationalCode = userEditNormalizeNationalCodeField(true);
        const mobile = mobileField.value.trim();

        if(!/^09\d{9}$/.test(mobile)){
            event.preventDefault();
            alert('شماره موبایل باید ۱۱ رقم و با 09 شروع شود');
            mobileField.focus();
            return;
        }

        if(!/^\d{10}$/.test(nationalCode)){
            event.preventDefault();
            alert('کد ملی باید عدد و حداکثر ۱۰ رقم باشد');
            nationalCodeField.focus();
        }
    });
}

</script>

<?php include '../includes/footer.php'; ?>
