<?php

require '../includes/admin_auth.php';
require '../includes/user_helpers.php';

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
$activeModal = $_GET['modal'] ?? '';

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

    header("Location: user-edit.php?id=" . $user_id . "&msg=deleted&modal=service");

    exit;

}

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    if(isset($_POST['save_profile'])){

        $job_title_id = (int)$_POST['job_title_id'];
        $status = $_POST['status'] ?? 'active';
        $profileError = '';
        $activeModal = 'profile';

        if(!in_array($status, ['active', 'inactive', 'pending'], true)){

            $status = 'active';

        }

        $fullname = trim((string)($user['fullname'] ?? ''));
        $mobile = trim((string)($user['mobile'] ?? ''));
        $nationalCode = trim((string)($user['national_code'] ?? ''));

        if(admin_is_super()){

            $firstname = trim($_POST['firstname'] ?? '');
            $lastname = trim($_POST['lastname'] ?? '');
            $mobileRaw = trim($_POST['mobile'] ?? '');
            $nationalCodeRaw = trim($_POST['national_code'] ?? '');

            if($msg = user_validate_persian_name($firstname, 'نام')){
                $profileError = $msg;
            }elseif($msg = user_validate_persian_name($lastname, 'نام خانوادگی')){
                $profileError = $msg;
            }elseif($msg = user_validate_mobile($mobileRaw)){
                $profileError = $msg;
            }elseif($msg = user_validate_national_code($nationalCodeRaw)){
                $profileError = $msg;
            }else{
                $fullname = user_build_fullname($firstname, $lastname);
                $mobile = user_normalize_mobile($mobileRaw);
                $nationalCode = user_normalize_national_code($nationalCodeRaw);

                $dupMobile = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE mobile=? AND role='user' AND id!=?
                    LIMIT 1
                ");
                $dupMobile->execute([$mobile, $user_id]);

                if($dupMobile->fetch()){
                    $profileError = 'این شماره موبایل قبلاً ثبت شده است';
                }else{
                    $dupNational = $pdo->prepare("
                        SELECT id
                        FROM users
                        WHERE national_code=? AND role='user' AND id!=?
                        LIMIT 1
                    ");
                    $dupNational->execute([$nationalCode, $user_id]);

                    if($dupNational->fetch()){
                        $profileError = 'این کد ملی قبلاً ثبت شده است';
                    }
                }
            }

        }

        if($profileError === ''){

            $jobStmt = $pdo->prepare("
                SELECT title
                FROM job_titles
                WHERE id=?
            ");

            $jobStmt->execute([$job_title_id]);

            $job = $jobStmt->fetch();

            if($job){

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
                        $fullname,
                        $mobile,
                        $nationalCode,
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
                $activeModal = '';

                $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

            }

        }else{

            $message = $profileError;

        }

    }

    if(isset($_POST['change_password'])){

        $activeModal = 'password';
        $password = trim($_POST['password'] ?? '');
        $confirm = trim($_POST['password_confirm'] ?? '');

        if($password !== $confirm){

            $message = 'تکرار رمز عبور یکسان نیست';

        }elseif($error = user_update_password($pdo, $user_id, $password)){

            $message = $error;

        }else{

            $message = 'رمز عبور کاربر با موفقیت تغییر کرد';
            $activeModal = '';

        }

    }

    if(isset($_POST['add_service'])){

        $activeModal = 'service';
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

$userNameParts = user_parse_fullname((string)($user['fullname'] ?? ''));

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

$statusLabels = [
    'active' => 'فعال',
    'inactive' => 'غیرفعال',
    'pending' => 'در انتظار تایید',
];

$back_url = 'users.php';
$page_title = '✏️ ویرایش کاربر';
$page_header_menu_type = 'action-menu';
$page_header_menu_label = 'منوی ویرایش کاربر';
$page_header_menu_items = [
    [
        'label' => 'ویرایش پروفایل',
        'onclick' => 'openProfileModal()',
    ],
    [
        'label' => 'تغییر رمز عبور',
        'onclick' => 'openPasswordModal()',
    ],
    [
        'label' => 'ویرایش محل خدمت',
        'onclick' => 'openServiceModal()',
    ],
];

require '../includes/header.php';

?>

<style>

.page-box{
    max-width:850px;
    margin:auto;
}

.card{
    background:white;
    border-radius:24px;
    padding:24px;
    margin-bottom:20px;
    box-shadow:0 0 20px rgba(0,0,0,0.05);
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
    color:#111827;
}

.status{
    display:inline-block;
    padding:7px 14px;
    border-radius:30px;
    font-size:12px;
    color:white;
}

.status.active{background:#10b981;}
.status.inactive{background:#ef4444;}
.status.pending{background:#f59e0b;}

.unit-card{
    background:linear-gradient(135deg,#eff6ff,#dbeafe);
    border-radius:18px;
    padding:16px;
    margin-bottom:12px;
}

.unit-name{
    font-size:15px;
    font-weight:bold;
    color:#1e3a8a;
}

.unit-meta{
    margin-top:6px;
    font-size:13px;
    color:#64748b;
}

.modal-overlay{
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.35);
    backdrop-filter:blur(8px);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:9999;
    padding:20px;
}

.modal-overlay.show{
    display:flex;
}

.modal-box{
    width:100%;
    max-width:520px;
    max-height:90vh;
    overflow-y:auto;
    background:#fff;
    border-radius:24px;
    padding:24px;
    box-shadow:0 20px 60px rgba(0,0,0,.15);
    animation:modalIn .2s ease;
}

@keyframes modalIn{
    from{opacity:0; transform:translateY(15px);}
    to{opacity:1; transform:none;}
}

.modal-title{
    font-size:20px;
    font-weight:800;
    margin-bottom:18px;
    color:#0f172a;
}

.modal-actions{
    display:flex;
    gap:10px;
    margin-top:20px;
}

.modal-btn{
    flex:1;
    border:none;
    padding:14px;
    border-radius:16px;
    cursor:pointer;
    font-family:'Vazirmatn',sans-serif;
    font-weight:700;
}

.save-btn{
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    color:white;
}

.cancel-btn{
    background:#f1f5f9;
    color:#334155;
}

.hidden{display:none;}

.password-box{
    position:relative;
    margin-bottom:14px;
}

.password-box .form-control{
    margin-bottom:0;
    padding-left:52px;
}

.toggle-password{
    position:absolute;
    left:18px;
    top:50%;
    transform:translateY(-50%);
    cursor:pointer;
    font-size:16px;
    color:#94a3b8;
    user-select:none;
}

.password-hint{
    font-size:13px;
    color:#64748b;
    margin-bottom:14px;
    line-height:28px;
}

.name-row{
    display:flex;
    gap:8px;
    margin-bottom:14px;
}

.name-row .form-control{
    margin-bottom:0;
}

.name-row .form-control:first-child{
    flex:0 0 35%;
    max-width:35%;
    min-width:0;
}

.name-row .form-control:last-child{
    flex:1 1 65%;
    min-width:0;
}

.field-label{
    display:block;
    font-size:13px;
    font-weight:700;
    color:#334155;
    margin-bottom:8px;
}

.field-group{
    margin-bottom:14px;
}

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

.service-modal-card{
    background:linear-gradient(135deg,#eff6ff,#dbeafe);
    border-radius:18px;
    padding:16px;
    margin-bottom:12px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
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

.plus-btn{
    width:100%;
    border:none;
    background:#2563eb;
    color:white;
    padding:14px;
    border-radius:18px;
    font-size:15px;
    cursor:pointer;
    margin-top:8px;
    margin-bottom:16px;
    font-family:'Vazirmatn',sans-serif;
}

.empty-service{
    background:#f8fafc;
    border-radius:18px;
    padding:16px;
    color:#64748b;
    margin-bottom:12px;
}

@media(max-width:768px){
    .info-grid{grid-template-columns:1fr;}
}

</style>

<div class="page-box">

<?php if($message): ?>

<div class="alert"><?= htmlspecialchars($message) ?></div>

<?php endif; ?>

<div class="card">

<div class="section-title">اطلاعات کاربر</div>

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

<div class="info-item">

<div class="info-label">پست سازمانی</div>

<div class="info-value"><?= htmlspecialchars($user['job_title'] ?: '-') ?></div>

</div>

<div class="info-item">

<div class="info-label">وضعیت</div>

<div class="info-value">

<span class="status <?= htmlspecialchars($user['status']) ?>">

<?= htmlspecialchars($statusLabels[$user['status']] ?? $user['status']) ?>

</span>

</div>

</div>

</div>

</div>

<div class="card">

<div class="section-title">🏢 محل‌های خدمت</div>

<?php if(count($currentNodes)): ?>

<?php foreach($currentNodes as $node): ?>

<div class="unit-card">

<div class="unit-name">

<?= htmlspecialchars($node['center_name']) ?>

-

<?= htmlspecialchars($node['child_name']) ?>

</div>

<div class="unit-meta">

<?= $node['child_type'] == 'unit' ? 'واحد مستقر در مرکز' : 'خانه بهداشت' ?>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="empty-service">هیچ محل خدمتی ثبت نشده</div>

<?php endif; ?>

</div>

</div>

<div id="profileModal" class="modal-overlay<?= $activeModal === 'profile' ? ' show' : '' ?>">

<div class="modal-box" role="dialog" aria-modal="true">

<div class="modal-title">ویرایش پروفایل</div>

<form method="POST">

<input type="hidden" name="user_id" value="<?= $user_id ?>">

<?php if(admin_is_super()): ?>

<label class="field-label">نام و نام خانوادگی</label>

<div class="name-row">

<input
type="text"
name="firstname"
class="form-control"
placeholder="نام"
required
value="<?= htmlspecialchars($userNameParts['firstname'], ENT_QUOTES, 'UTF-8') ?>">

<input
type="text"
name="lastname"
class="form-control"
placeholder="نام خانوادگی"
required
value="<?= htmlspecialchars($userNameParts['lastname'], ENT_QUOTES, 'UTF-8') ?>">

</div>

<div class="field-group">

<label class="field-label">شماره موبایل</label>

<input
type="text"
name="mobile"
class="form-control"
placeholder="09xxxxxxxxx"
required
maxlength="11"
inputmode="numeric"
value="<?= htmlspecialchars($user['mobile'], ENT_QUOTES, 'UTF-8') ?>">

</div>

<div class="field-group">

<label class="field-label">کد ملی</label>

<input
type="text"
name="national_code"
class="form-control"
placeholder="کد ملی"
required
maxlength="10"
inputmode="numeric"
autocomplete="off"
value="<?= htmlspecialchars($user['national_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

</div>

<?php endif; ?>

<div class="field-group">

<label class="field-label">پست سازمانی</label>

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

</div>

<div class="field-group">

<label class="field-label">وضعیت</label>

<select name="status" class="form-control">

<option value="active" <?= $user['status'] == 'active' ? 'selected' : '' ?>>فعال</option>

<option value="inactive" <?= $user['status'] == 'inactive' ? 'selected' : '' ?>>غیرفعال</option>

<option value="pending" <?= $user['status'] == 'pending' ? 'selected' : '' ?>>در انتظار تایید</option>

</select>

</div>

<div class="modal-actions">

<button type="submit" name="save_profile" class="modal-btn save-btn">ذخیره اطلاعات</button>

<button type="button" onclick="closeProfileModal()" class="modal-btn cancel-btn">انصراف</button>

</div>

</form>

</div>

</div>

<div id="passwordModal" class="modal-overlay<?= $activeModal === 'password' ? ' show' : '' ?>">

<div class="modal-box" role="dialog" aria-modal="true">

<div class="modal-title">تغییر رمز عبور</div>

<div class="password-hint">رمز عبور جدید برای ورود کاربر به سامانه تنظیم می‌شود. حداقل ۸ کاراکتر.</div>

<form method="POST">

<input type="hidden" name="user_id" value="<?= $user_id ?>">

<div class="password-box">
<input type="password" name="password" id="newPasswordField" class="form-control" placeholder="رمز عبور جدید" required minlength="8" autocomplete="new-password">
<span class="toggle-password" id="toggleNewPassword">◉</span>
</div>

<div class="password-box">
<input type="password" name="password_confirm" id="confirmPasswordField" class="form-control" placeholder="تکرار رمز عبور جدید" required minlength="8" autocomplete="new-password">
<span class="toggle-password" id="toggleConfirmPassword">◉</span>
</div>

<div class="modal-actions">

<button type="submit" name="change_password" class="modal-btn save-btn">تغییر رمز عبور</button>

<button type="button" onclick="closePasswordModal()" class="modal-btn cancel-btn">انصراف</button>

</div>

</form>

</div>

</div>

<div id="serviceModal" class="modal-overlay<?= $activeModal === 'service' ? ' show' : '' ?>">

<div class="modal-box" role="dialog" aria-modal="true">

<div class="modal-title">ویرایش محل خدمت</div>

<?php if(count($currentNodes)): ?>

<?php foreach($currentNodes as $node): ?>

<div class="service-modal-card">

<div>

<div class="unit-name">

<?= htmlspecialchars($node['center_name']) ?>

-

<?= htmlspecialchars($node['child_name']) ?>

</div>

<div class="unit-meta">

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

<div class="empty-service">هیچ محل خدمتی ثبت نشده</div>

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

<div class="modal-actions">

<button type="submit" name="add_service" class="modal-btn save-btn">افزودن محل خدمت</button>

<button type="button" onclick="closeServiceModal()" class="modal-btn cancel-btn">بستن</button>

</div>

</form>

</div>

</div>

<script>

function setupPasswordToggle(toggleId, fieldId){
    const toggleBtn = document.getElementById(toggleId);
    const passwordField = document.getElementById(fieldId);

    if(toggleBtn && passwordField){
        toggleBtn.addEventListener('click', function(){
            if(passwordField.type === 'password'){
                passwordField.type = 'text';
                toggleBtn.textContent = '○';
            }else{
                passwordField.type = 'password';
                toggleBtn.textContent = '◉';
            }
        });
    }
}

setupPasswordToggle('toggleNewPassword', 'newPasswordField');
setupPasswordToggle('toggleConfirmPassword', 'confirmPasswordField');

function openProfileModal(){
    document.getElementById('profileModal').classList.add('show');
}

function closeProfileModal(){
    document.getElementById('profileModal').classList.remove('show');
}

function openPasswordModal(){
    document.getElementById('passwordModal').classList.add('show');
}

function closePasswordModal(){
    document.getElementById('passwordModal').classList.remove('show');
}

function openServiceModal(){
    document.getElementById('serviceModal').classList.add('show');
}

function closeServiceModal(){
    document.getElementById('serviceModal').classList.remove('show');
}

const showFormBtn = document.getElementById('showFormBtn');
const serviceForm = document.getElementById('serviceForm');

if(showFormBtn && serviceForm){
    showFormBtn.addEventListener('click', function(){
        serviceForm.classList.toggle('hidden');
    });
}

const centerSelect = document.getElementById('centerSelect');
const subTypeSelect = document.getElementById('subTypeSelect');
const subItemsBox = document.getElementById('subItemsBox');
const subItemsSelect = document.getElementById('subItemsSelect');

if(subTypeSelect){
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
}

window.addEventListener('click', function(e){

    if(e.target.classList.contains('modal-overlay')){
        e.target.classList.remove('show');
    }

});

</script>

<?php include '../includes/footer.php'; ?>
