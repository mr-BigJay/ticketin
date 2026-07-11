<?php

require '../includes/admin_auth.php';
require '../includes/user_helpers.php';

user_ensure_schema($pdo);

$message = '';
$error = '';
$activeModal = '';
$passwordModalUserId = 0;
$passwordModalName = '';

if(isset($_POST['change_user_password'])){
    if(admin_users_readonly()){
        die('دسترسی غیر مجاز');
    }

    $activeModal = 'password';
    $passwordModalUserId = (int)($_POST['user_id'] ?? 0);
    $password = trim($_POST['password'] ?? '');

    $targetUser = null;

    if($passwordModalUserId > 0){
        $stmt = $pdo->prepare("
            SELECT id, fullname
            FROM users
            WHERE id=? AND role='user'
        ");
        $stmt->execute([$passwordModalUserId]);
        $targetUser = $stmt->fetch();
    }

    if(!$targetUser){
        $error = 'کاربر یافت نشد';
    }elseif($password === ''){
        $error = 'رمز عبور را وارد کنید';
        $passwordModalName = (string)$targetUser['fullname'];
    }elseif($msg = user_update_password($pdo, $passwordModalUserId, $password)){
        $error = $msg;
        $passwordModalName = (string)$targetUser['fullname'];
    }else{
        $message = 'رمز عبور ' . $targetUser['fullname'] . ' با موفقیت تغییر کرد';
        $activeModal = '';
        $passwordModalUserId = 0;
        $passwordModalName = '';
    }
}

function users_redirect(){
    $params = $_GET;
    unset($params['deactivate'], $params['activate'], $params['delete']);
    $qs = http_build_query($params);
    header('Location: users.php' . ($qs ? '?' . $qs : ''));
    exit;
}

if(isset($_POST['approve_user_id'])){
    if(admin_users_readonly()){
        die('دسترسی غیر مجاز');
    }

    $userId =
    (int)$_POST['approve_user_id'];

    $jobTitleId =
    (int)$_POST['job_title_id'];

    $stmt = $pdo->prepare("
        SELECT title
        FROM job_titles
        WHERE id=?
    ");

    $stmt->execute([
        $jobTitleId
    ]);

    $job =
    $stmt->fetch();

    if($job){

        $stmt = $pdo->prepare("
            UPDATE users
            SET
            status='active',
            job_title_id=?,
            job_title=?
            WHERE id=?
        ");

        $stmt->execute([

            $jobTitleId,
            $job['title'],
            $userId

        ]);

        require_once '../includes/sms_helpers.php';
        sms_dispatch_user_event($pdo, 'user_approved', $userId, [
            'job_title' => (string)($job['title'] ?? ''),
        ]);

    }

    header("Location: users.php");

    exit;

}

if(isset($_GET['deactivate'])){
    if(admin_users_readonly()){
        die('دسترسی غیر مجاز');
    }

    $id = (int)$_GET['deactivate'];

    $stmt = $pdo->prepare("
        UPDATE users
        SET status='inactive'
        WHERE id=?
    ");

    $stmt->execute([$id]);

    users_redirect();

}

if(isset($_GET['activate'])){
    if(admin_users_readonly()){
        die('دسترسی غیر مجاز');
    }

    $id = (int)$_GET['activate'];

    $stmt = $pdo->prepare("
        UPDATE users
        SET status='active'
        WHERE id=?
    ");

    $stmt->execute([$id]);

    users_redirect();

}

if(isset($_GET['delete'])){
    if(admin_users_readonly()){
        die('دسترسی غیر مجاز');
    }

    $id = (int)$_GET['delete'];

    if(!user_delete_account($pdo, $id)){
        die('حذف کاربر انجام نشد. ممکن است کاربر وابستگی داشته باشد.');
    }

    users_redirect();

}

$where = [];

$params = [];

$where[] = "u.role='user'";
$where[] = "u.status!='pending'";

if(!empty($_GET['status'])){

    $where[] = "u.status=?";

    $params[] = $_GET['status'];

}

if(!empty($_GET['search'])){

    $where[] = "(
        u.fullname LIKE ?
        OR u.job_title LIKE ?
    )";

    $search =
    "%" . $_GET['search'] . "%";

    $params[] = $search;
    $params[] = $search;

}

if(!empty($_GET['job_title_id'])){

    $where[] = "u.job_title_id=?";

    $params[] = (int)$_GET['job_title_id'];

}

$center_id = (int)($_GET['center_id'] ?? 0);
$sub_type = trim($_GET['sub_type'] ?? '');
$node_id = (int)($_GET['node_id'] ?? 0);

$joins = "";

if($center_id || $node_id || $sub_type){

    $joins = "
        INNER JOIN user_organization_rel uor
        ON u.id = uor.user_id
    ";

    if($node_id){

        $where[] = "uor.node_id=?";
        $params[] = $node_id;

    }elseif($center_id && $sub_type){

        $joins .= "
            INNER JOIN organization_nodes org_node
            ON uor.node_id = org_node.id
        ";

        $where[] = "uor.center_id=?";
        $where[] = "org_node.type=?";

        $params[] = $center_id;
        $params[] = $sub_type;

    }elseif($center_id){

        $where[] = "uor.center_id=?";
        $params[] = $center_id;

    }

}

$whereSql =
"WHERE " . implode(" AND ",$where);

$stmt = $pdo->prepare("
    SELECT DISTINCT u.*
    FROM users u
    $joins
    $whereSql
    ORDER BY u.id DESC
");

$stmt->execute($params);

$users = $stmt->fetchAll();

$jobTitles =
$pdo->query("
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

$filterQuery = $_GET;
unset($filterQuery['deactivate'], $filterQuery['activate'], $filterQuery['delete']);
$filterQs = http_build_query($filterQuery);
$filterPrefix = $filterQs ? '?' . $filterQs . '&' : '?';

$back_url = 'index.php';
$page_title = '👥 مدیریت کاربران';
$page_header_menu_type = 'action-menu';
$page_header_menu_label = 'منوی مدیریت کاربران';
$page_header_menu_items = [
    [
        'label' => 'جستجو کاربران',
        'onclick' => 'openUsersSearchModal()',
    ],
];

require '../includes/header.php';

?>

<div class="page-box">

<style>

.page-box{

    max-width:1100px;

    margin:auto;

}

.page-title{

    font-size:24px;

    font-weight:bold;

    margin-bottom:20px;

}

.card{

    background:white;

    border-radius:22px;

    padding:16px;

    margin-bottom:20px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

    overflow:visible;

}

.list-search-modal-overlay{
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.45);
    backdrop-filter:blur(8px);
    z-index:100000;
    display:none;
    align-items:center;
    justify-content:center;
    padding:20px;
}

.list-search-modal-overlay.show{
    display:flex;
}

.list-search-modal{
    width:100%;
    max-width:520px;
    max-height:90vh;
    overflow-y:auto;
    background:#ffffff;
    border-radius:24px;
    padding:24px 22px;
    box-shadow:0 20px 50px rgba(15,23,42,.18);
    position:relative;
}

.list-search-modal-title{
    font-size:20px;
    font-weight:800;
    color:#0f172a;
    margin-bottom:18px;
    padding-left:36px;
}

.list-search-modal-close{
    position:absolute;
    left:16px;
    top:16px;
    width:34px;
    height:34px;
    border:none;
    border-radius:12px;
    background:#f1f5f9;
    color:#64748b;
    font-size:22px;
    line-height:1;
    cursor:pointer;
}

.search-field-label{
    display:block;
    font-size:13px;
    font-weight:800;
    color:#334155;
    margin-bottom:8px;
}

.search-field-group{
    margin-bottom:12px;
}

.users-table-wrap{

    overflow:visible;

    position:relative;

}

.user-row{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:10px;

    background:#f8fafc;

    border-radius:14px;

    padding:8px 10px;

    margin-bottom:6px;

    position:relative;

    overflow:visible;

    z-index:1;

}

.user-row.menu-open{

    z-index:100;

}

.user-main{

    flex:1;

    min-width:0;

    display:flex;

    align-items:center;

    gap:12px;

}

.row-actions{

    display:flex;

    align-items:center;

    gap:8px;

    flex-shrink:0;

}

.status{

    display:inline-block;

    padding:4px 10px;

    border-radius:20px;

    font-size:11px;

    color:white;

    text-align:center;

    white-space:nowrap;

}

.pending{

    background:#f59e0b;

}

.active{

    background:#10b981;

}

.inactive{

    background:#ef4444;

}

.user-cell{

    font-size:13px;

    color:#334155;

    overflow:hidden;

    text-overflow:ellipsis;

    white-space:nowrap;

}

.user-cell.name{

    font-weight:700;

    color:#0f172a;

    flex:1;

    min-width:0;

}

.user-cell.job{

    flex:1;

    min-width:0;

    color:#64748b;

    font-size:12px;

}

.job-menu{

    position:relative;

}

.menu-btn{

    width:34px;

    height:34px;

    border:none;

    border-radius:10px;

    background:#f1f5f9;

    color:#334155;

    font-size:20px;

    line-height:1;

    cursor:pointer;

    transition:.2s;

}

.menu-btn:hover{

    background:#e2e8f0;

}

.dropdown-menu{

    position:absolute;

    top:45px;

    left:0;

    min-width:160px;

    background:#fff;

    border-radius:16px;

    border:1px solid #eef2f7;

    box-shadow:0 12px 35px rgba(15,23,42,.15);

    display:none;

    overflow:hidden;

    z-index:9999;

}

.dropdown-menu.show{

    display:block;

}

.dropdown-menu a{

    display:flex;

    align-items:center;

    gap:8px;

    padding:10px 14px;

    text-decoration:none;

    color:#334155;

    font-size:13px;

    font-weight:700;

    transition:.2s;

}

.dropdown-menu button{

    display:flex;

    align-items:center;

    gap:8px;

    width:100%;

    padding:10px 14px;

    border:none;

    background:none;

    color:#334155;

    font-size:13px;

    font-weight:700;

    font-family:'Vazirmatn',sans-serif;

    cursor:pointer;

    text-align:right;

    transition:.2s;

}

.dropdown-menu a:hover{

    background:#f8fafc;

}

.dropdown-menu button:hover{

    background:#f8fafc;

}

.dropdown-menu a.danger{

    color:#ef4444;

}

.modal-overlay{

    position:fixed;

    inset:0;

    background:rgba(15,23,42,.35);

    backdrop-filter:blur(8px);

    display:none;

    justify-content:center;

    align-items:center;

    z-index:100001;

    padding:20px;

}

.modal-overlay.show{

    display:flex;

}

.modal-box{

    width:100%;

    max-width:500px;

    max-height:90vh;

    overflow-y:auto;

    background:#fff;

    border-radius:24px;

    padding:24px;

    box-shadow:0 20px 60px rgba(0,0,0,.15);

    animation:modalIn .2s ease;

}

@keyframes modalIn{

    from{opacity:0;transform:translateY(15px);}

    to{opacity:1;transform:none;}

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

    color:#94a3b8;

    user-select:none;

    display:flex;

    align-items:center;

    justify-content:center;

    width:24px;

    height:24px;

}

.toggle-password svg{

    width:20px;

    height:20px;

}

.password-hint{

    font-size:13px;

    color:#64748b;

    line-height:1.8;

    margin-bottom:14px;

}

.empty-box{

    text-align:center;

    color:#777;

    padding:25px;

}

@media(max-width:768px){

    .user-main{

        flex-direction:column;

        align-items:flex-start;

        gap:2px;

    }

    .user-cell.job{

        font-size:11px;

    }

}

</style>

<?php if($message): ?>

<div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>

<?php endif; ?>

<?php if($error): ?>

<div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>

<?php endif; ?>

<div class="card">

<?php if(count($users)): ?>

<div class="users-table-wrap">

<?php foreach($users as $user): ?>

<div class="user-row" id="row-<?= $user['id'] ?>">

<div class="user-main">

<div class="user-cell name">

<?= htmlspecialchars($user['fullname'] ?: '-') ?>

</div>

<div class="user-cell job">

<?= htmlspecialchars($user['job_title'] ?: '-') ?>

</div>

</div>

<div class="row-actions">

<span class="status <?= $user['status'] ?>">

<?php

if($user['status'] == 'pending'){

    echo 'در انتظار تایید';

}elseif($user['status'] == 'active'){

    echo 'فعال';

}else{

    echo 'غیرفعال';

}

?>

</span>

<div class="job-menu">

<button
class="menu-btn"
type="button"
onclick="toggleMenu(event, <?= $user['id'] ?>)">

⋮

</button>

<div
id="menu-<?= $user['id'] ?>"
class="dropdown-menu">

<a href="user-view.php?id=<?= $user['id'] ?>">

👤 مشاهده پروفایل

</a>

<?php if(!admin_users_readonly()): ?>

<a href="user-edit.php?id=<?= $user['id'] ?>">

✏️ ویرایش

</a>

<button
type="button"
class="js-open-user-password"
data-user-id="<?= (int)$user['id'] ?>"
data-user-name="<?= htmlspecialchars($user['fullname'] ?: '-', ENT_QUOTES, 'UTF-8') ?>">

🔐 تغییر رمز عبور

</button>

<?php if($user['status'] == 'active'): ?>

<a href="<?= $filterPrefix ?>deactivate=<?= $user['id'] ?>">

⏸ غیرفعال

</a>

<?php endif; ?>

<?php if($user['status'] == 'inactive'): ?>

<a href="<?= $filterPrefix ?>activate=<?= $user['id'] ?>">

▶️ فعال سازی

</a>

<?php endif; ?>

<a
href="<?= $filterPrefix ?>delete=<?= $user['id'] ?>"
class="danger"
onclick="return confirm('کاربر حذف شود؟')">

🗑 حذف

</a>

<?php endif; ?>

</div>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

<?php else: ?>

<div class="empty-box">

کاربری یافت نشد

</div>

<?php endif; ?>

</div>

</div>

<div
class="list-search-modal-overlay"
id="usersSearchModalOverlay"
aria-hidden="true">

<div class="list-search-modal" role="dialog" aria-modal="true">

<button
type="button"
class="list-search-modal-close"
onclick="closeUsersSearchModal()"
aria-label="بستن">

×

</button>

<h2 class="list-search-modal-title">جستجوی کاربران</h2>

<form method="GET" id="usersSearchForm">

<div class="search-field-group">

<label class="search-field-label" for="usersSearchInput">نام یا پست سازمانی</label>

<input
type="text"
id="usersSearchInput"
name="search"
class="form-control"
placeholder="جستجو نام یا پست سازمانی"
value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

</div>

<div class="search-field-group">

<label class="search-field-label" for="usersStatusSelect">وضعیت</label>

<select
name="status"
id="usersStatusSelect"
class="form-control">

<option value="">همه وضعیت‌ها</option>

<option value="pending" <?= ($_GET['status'] ?? '') == 'pending' ? 'selected' : '' ?>>در انتظار تایید</option>

<option value="active" <?= ($_GET['status'] ?? '') == 'active' ? 'selected' : '' ?>>فعال</option>

<option value="inactive" <?= ($_GET['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>غیرفعال</option>

</select>

</div>

<div class="search-field-group">

<label class="search-field-label" for="usersJobTitleSelect">پست سازمانی</label>

<select
name="job_title_id"
id="usersJobTitleSelect"
class="form-control">

<option value="">همه پست‌های سازمانی</option>

<?php foreach($jobTitles as $job): ?>

<option
value="<?= $job['id'] ?>"
<?= (int)($_GET['job_title_id'] ?? 0) === (int)$job['id'] ? 'selected' : '' ?>>

<?= htmlspecialchars($job['title']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="search-field-group">

<label class="search-field-label" for="centerSelect">مرکز</label>

<select
name="center_id"
id="centerSelect"
class="form-control">

<option value="">همه مراکز</option>

<?php foreach($centers as $center): ?>

<option
value="<?= $center['id'] ?>"
<?= $center_id === (int)$center['id'] ? 'selected' : '' ?>>

<?= htmlspecialchars($center['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="search-field-group">

<label class="search-field-label" for="subTypeSelect">نوع محل خدمت</label>

<select
name="sub_type"
id="subTypeSelect"
class="form-control">

<option value="">همه انواع</option>

<option value="health_house" <?= $sub_type === 'health_house' ? 'selected' : '' ?>>خانه بهداشت</option>

<option value="unit" <?= $sub_type === 'unit' ? 'selected' : '' ?>>واحد مستقر در مرکز</option>

</select>

</div>

<div class="search-field-group">

<label class="search-field-label" for="nodeSelect">واحد</label>

<select
name="node_id"
id="nodeSelect"
class="form-control">

<option value="">همه واحدها</option>

</select>

</div>

<button type="submit" class="btn-custom">جستجو کاربران</button>

</form>

</div>

</div>

<div
id="userPasswordModal"
class="modal-overlay<?= $activeModal === 'password' ? ' show' : '' ?>"
aria-hidden="<?= $activeModal === 'password' ? 'false' : 'true' ?>">

<div class="modal-box" role="dialog" aria-modal="true" onclick="event.stopPropagation()">

<div class="modal-title" id="userPasswordModalTitle">
<?= $passwordModalName !== ''
    ? 'تغییر رمز عبور — ' . htmlspecialchars($passwordModalName, ENT_QUOTES, 'UTF-8')
    : 'تغییر رمز عبور' ?>
</div>

<form method="POST">

<input
type="hidden"
name="user_id"
id="passwordUserId"
value="<?= $passwordModalUserId ?>">

<div class="password-box">

<input
type="password"
name="password"
id="userPasswordField"
class="form-control"
placeholder="رمز عبور جدید"
required
minlength="8"
autocomplete="new-password"
value="">

<span
class="toggle-password"
id="toggleUserPassword"
role="button"
tabindex="0"
aria-label="نمایش رمز عبور"
title="نمایش رمز عبور">

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>

</span>

</div>

<div class="password-hint">رمز عبور باید حداقل ۸ کاراکتر باشد.</div>

<div class="modal-actions">

<button type="submit" name="change_user_password" class="modal-btn save-btn">ثبت</button>

<button type="button" onclick="closeUserPasswordModal()" class="modal-btn cancel-btn">انصراف</button>

</div>

</form>

</div>

</div>

<script>

const usersSearchModalOverlay =
document.getElementById('usersSearchModalOverlay');

const userPasswordModal =
document.getElementById('userPasswordModal');

const eyeOpenSvg =
'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';

const eyeClosedSvg =
'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>';

function closeAllUserMenus(){

    document.querySelectorAll('.dropdown-menu').forEach(function(menu){

        menu.classList.remove('show');
        menu.style.top = '45px';
        menu.style.bottom = 'auto';

    });

    document.querySelectorAll('.user-row').forEach(function(row){

        row.classList.remove('menu-open');

    });

}

function openUserPasswordModal(userId, fullname){

    const passwordField = document.getElementById('userPasswordField');
    const title = document.getElementById('userPasswordModalTitle');
    const userIdField = document.getElementById('passwordUserId');
    const toggleBtn = document.getElementById('toggleUserPassword');

    if(userIdField){
        userIdField.value = userId;
    }

    if(title){
        title.textContent = 'تغییر رمز عبور — ' + (fullname || '');
    }

    if(passwordField){
        passwordField.value = '';
        passwordField.type = 'password';
    }

    if(toggleBtn){
        toggleBtn.innerHTML = eyeOpenSvg;
        toggleBtn.title = 'نمایش رمز عبور';
        toggleBtn.setAttribute('aria-label', 'نمایش رمز عبور');
    }

    if(userPasswordModal){
        userPasswordModal.classList.add('show');
        userPasswordModal.setAttribute('aria-hidden', 'false');
    }

    closeAllUserMenus();
    closePageHeaderDropdown();

    if(passwordField){
        passwordField.focus();
    }

}

function closeUserPasswordModal(){

    if(!userPasswordModal){
        return;
    }

    userPasswordModal.classList.remove('show');
    userPasswordModal.setAttribute('aria-hidden', 'true');
}

function setupUserPasswordToggle(){

    const toggleBtn = document.getElementById('toggleUserPassword');
    const passwordField = document.getElementById('userPasswordField');

    if(!toggleBtn || !passwordField){
        return;
    }

    function toggleVisibility(){
        if(passwordField.type === 'password'){
            passwordField.type = 'text';
            toggleBtn.innerHTML = eyeClosedSvg;
            toggleBtn.title = 'مخفی کردن رمز عبور';
            toggleBtn.setAttribute('aria-label', 'مخفی کردن رمز عبور');
        }else{
            passwordField.type = 'password';
            toggleBtn.innerHTML = eyeOpenSvg;
            toggleBtn.title = 'نمایش رمز عبور';
            toggleBtn.setAttribute('aria-label', 'نمایش رمز عبور');
        }
    }

    toggleBtn.addEventListener('click', toggleVisibility);

    toggleBtn.addEventListener('keydown', function(event){
        if(event.key === 'Enter' || event.key === ' '){
            event.preventDefault();
            toggleVisibility();
        }
    });
}

setupUserPasswordToggle();

document.querySelectorAll('.js-open-user-password').forEach(function(button){

    button.addEventListener('click', function(event){

        event.preventDefault();
        event.stopPropagation();

        openUserPasswordModal(
            this.dataset.userId,
            this.dataset.userName || ''
        );

    });

});

userPasswordModal?.addEventListener('click', function(event){

    if(event.target === userPasswordModal){
        closeUserPasswordModal();
    }

});

function closePageHeaderDropdown(){

    const dropdown =
    document.getElementById('pageHeaderDropdown');

    const menuBtn =
    document.getElementById('pageHeaderMenuBtn');

    if(dropdown){
        dropdown.classList.remove('show');
    }

    if(menuBtn){
        menuBtn.setAttribute('aria-expanded', 'false');
    }

}

function openUsersSearchModal(){

    if(!usersSearchModalOverlay){
        return;
    }

    usersSearchModalOverlay.classList.add('show');
    usersSearchModalOverlay.setAttribute('aria-hidden', 'false');
    closePageHeaderDropdown();

}

function closeUsersSearchModal(){

    if(!usersSearchModalOverlay){
        return;
    }

    usersSearchModalOverlay.classList.remove('show');
    usersSearchModalOverlay.setAttribute('aria-hidden', 'true');
    closePageHeaderDropdown();

}

usersSearchModalOverlay?.addEventListener('click', function(event){

    if(event.target === usersSearchModalOverlay){
        closeUsersSearchModal();
    }

});

function toggleMenu(event, id){

    event.stopPropagation();

    document.querySelectorAll('.dropdown-menu').forEach(menu => {

        if(menu.id !== 'menu-' + id){

            menu.classList.remove('show');

        }

    });

    document.querySelectorAll('.user-row').forEach(row => {

        row.classList.remove('menu-open');

    });

    const menu = document.getElementById('menu-' + id);
    const row = document.getElementById('row-' + id);

    menu.classList.toggle('show');

    if(menu.classList.contains('show')){

        row.classList.add('menu-open');

        const rect = menu.getBoundingClientRect();

        if(rect.bottom > window.innerHeight){

            menu.style.top = 'auto';

            menu.style.bottom = '45px';

        }else{

            menu.style.top = '45px';

            menu.style.bottom = 'auto';

        }

    }

}

document.addEventListener('click', function(e){

    if(!e.target.closest('.job-menu')){

        closeAllUserMenus();

    }

});

const centerSelect = document.getElementById('centerSelect');
const subTypeSelect = document.getElementById('subTypeSelect');
const nodeSelect = document.getElementById('nodeSelect');
const selectedNodeId = <?= (int)$node_id ?>;

function loadOrgNodes(){

    const centerId = centerSelect.value;
    const type = subTypeSelect.value;

    nodeSelect.innerHTML = '<option value="">همه واحدها</option>';

    if(!centerId || !type){
        return;
    }

    fetch('../tickets.php?action=subs&center_id=' + centerId + '&type=' + type)
    .then(response => response.json())
    .then(data => {

        data.forEach(item => {

            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;

            if(parseInt(item.id, 10) === selectedNodeId){
                option.selected = true;
            }

            nodeSelect.appendChild(option);

        });

    });

}

centerSelect.addEventListener('change', loadOrgNodes);
subTypeSelect.addEventListener('change', loadOrgNodes);

if(centerSelect.value && subTypeSelect.value){
    loadOrgNodes();
}

</script>

<?php include '../includes/footer.php'; ?>
