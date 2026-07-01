<?php

require '../includes/admin_auth.php';

admin_require_super();

$message = '';
$error = trim((string)($_GET['err'] ?? ''));
$activeModal = $_GET['modal'] ?? '';

if(isset($_GET['msg'])){
    if($_GET['msg'] === 'deactivated'){
        $message = 'ادمین غیرفعال شد';
    }elseif($_GET['msg'] === 'deleted'){
        $message = 'ادمین حذف شد';
    }
}

if(isset($_GET['deactivate'])){
    $id = (int)$_GET['deactivate'];

    if($id === (int)$_SESSION['user_id']){
        header('Location: admins.php?err=' . urlencode('نمی‌توانید حساب خود را غیرفعال کنید'));
    }else{
        $stmt = $pdo->prepare("
            UPDATE users
            SET status='inactive'
            WHERE id=? AND role='admin' AND admin_type='support'
        ");
        $stmt->execute([$id]);
        header('Location: admins.php?msg=deactivated');
    }

    exit;
}

if(isset($_GET['activate'])){
    $id = (int)$_GET['activate'];

    $stmt = $pdo->prepare("
        UPDATE users
        SET status='active'
        WHERE id=? AND role='admin' AND admin_type='support'
    ");
    $stmt->execute([$id]);

    header('Location: admins.php');
    exit;
}

if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];

    if($id === (int)$_SESSION['user_id']){
        header('Location: admins.php?err=' . urlencode('نمی‌توانید حساب خود را حذف کنید'));
    }else{
        $stmt = $pdo->prepare("
            DELETE FROM users
            WHERE id=? AND role='admin' AND admin_type='support'
        ");
        $stmt->execute([$id]);

        if($stmt->rowCount() > 0){
            header('Location: admins.php?msg=deleted');
        }else{
            header('Location: admins.php?err=' . urlencode('حذف ادمین انجام نشد'));
        }
    }

    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    if(isset($_POST['create_admin'])){

        $activeModal = 'create';
        $fullname = trim($_POST['fullname'] ?? '');
        $support_department = trim($_POST['support_department'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if(!$fullname || !$support_department || !$mobile || !$username){
            $error = 'تمام فیلدها الزامی هستند';
        }elseif(!preg_match('/^09[0-9]{9}$/', $mobile)){
            $error = 'شماره موبایل معتبر نیست';
        }elseif($msg = admin_validate_username($username)){
            $error = $msg;
        }else{
            $check = $pdo->prepare("SELECT id FROM users WHERE username=?");
            $check->execute([$username]);

            if($check->fetch()){
                $error = 'این نام کاربری قبلاً ثبت شده است';
            }else{
                $nationalCode = '99' . str_pad((string)time(), 8, '0', STR_PAD_LEFT);
                $passwordHash = password_hash('1', PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO users
                    (
                        fullname,
                        national_code,
                        mobile,
                        username,
                        support_department,
                        password,
                        role,
                        admin_type,
                        status,
                        must_change_password,
                        created_at
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?, 'admin', 'support', 'active', 1, NOW())
                ");

                $stmt->execute([
                    $fullname,
                    $nationalCode,
                    $mobile,
                    $username,
                    $support_department,
                    $passwordHash,
                ]);

                $message = 'ادمین پشتیبانی با موفقیت ایجاد شد. رمز اولیه: 1';
                $activeModal = '';
            }
        }

    }

    if(isset($_POST['change_admin_password'])){

        $activeModal = 'password';
        $adminId = (int)($_POST['admin_id'] ?? 0);
        $password = trim($_POST['password'] ?? '');
        $confirm = trim($_POST['password_confirm'] ?? '');

        $target = $pdo->prepare("
            SELECT id, fullname
            FROM users
            WHERE id=? AND role='admin' AND admin_type='support'
        ");
        $target->execute([$adminId]);
        $targetAdmin = $target->fetch();

        if(!$targetAdmin){
            $error = 'ادمین یافت نشد';
        }elseif($password !== $confirm){
            $error = 'تکرار رمز عبور یکسان نیست';
        }elseif($msg = admin_validate_password($password)){
            $error = $msg;
        }else{
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                UPDATE users
                SET password=?, must_change_password=1
                WHERE id=? AND role='admin' AND admin_type='support'
            ");
            $stmt->execute([$hash, $adminId]);

            $message = 'رمز عبور ' . $targetAdmin['fullname'] . ' با موفقیت تغییر کرد';
            $activeModal = '';
        }

    }

}

$admins = $pdo->query("
    SELECT id, fullname, support_department, mobile, username, status, created_at
    FROM users
    WHERE role='admin' AND admin_type='support'
    ORDER BY id DESC
")->fetchAll();

$back_url = 'index.php';
$page_title = '👑 مدیریت کاربران ادمین';
$page_header_menu_type = 'action-menu';
$page_header_menu_label = 'منوی مدیریت ادمین‌ها';
$page_header_menu_items = [
    [
        'label' => 'ایجاد ادمین جدید',
        'onclick' => 'openCreateModal()',
    ],
];

require '../includes/header.php';

?>

<style>
.page-box{max-width:950px;margin:auto;}
.card{background:white;border-radius:22px;padding:16px;margin-bottom:20px;box-shadow:0 0 20px rgba(0,0,0,0.05);overflow:visible;}
.admin-row{
    background:#f8fafc;
    border-radius:14px;
    padding:8px 10px;
    margin-bottom:6px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    position:relative;
    overflow:visible;
    z-index:1;
}
.admin-row.menu-open{z-index:100;}
.admin-main{flex:1;min-width:0;display:flex;align-items:center;gap:12px;}
.admin-name{font-weight:800;color:#0f172a;font-size:14px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.admin-meta{font-size:12px;color:#64748b;line-height:22px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.admin-username{font-size:11px;color:#94a3b8;margin-top:2px;}
.row-actions{display:flex;align-items:center;gap:8px;flex-shrink:0;}
.status{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11px;color:#fff;font-weight:700;white-space:nowrap;}
.active{background:#10b981;}
.inactive{background:#ef4444;}
.job-menu{position:relative;}
.menu-btn{
    width:34px;height:34px;border:none;border-radius:10px;
    background:#f1f5f9;color:#334155;font-size:20px;line-height:1;cursor:pointer;
}
.menu-btn:hover{background:#e2e8f0;}
.dropdown-menu{
    position:absolute;top:40px;left:0;min-width:170px;background:#fff;
    border-radius:16px;border:1px solid #eef2f7;
    box-shadow:0 12px 35px rgba(15,23,42,.15);
    display:none;overflow:hidden;z-index:9999;
}
.dropdown-menu.show{display:block;}
.dropdown-menu button,
.dropdown-menu a{
    display:flex;align-items:center;gap:8px;width:100%;
    padding:10px 14px;text-decoration:none;color:#334155;
    font-size:13px;font-weight:700;transition:.2s;border:none;background:none;
    font-family:'Vazirmatn',sans-serif;cursor:pointer;text-align:right;
}
.dropdown-menu button:hover,
.dropdown-menu a:hover{background:#f8fafc;}
.dropdown-menu .danger{color:#ef4444;}
.hint{font-size:13px;color:#64748b;line-height:28px;margin-top:4px;margin-bottom:14px;}
.modal-overlay{
    position:fixed;inset:0;background:rgba(15,23,42,.35);
    backdrop-filter:blur(8px);display:none;justify-content:center;
    align-items:center;z-index:9999;padding:20px;
}
.modal-overlay.show{display:flex;}
.modal-box{
    width:100%;max-width:500px;max-height:90vh;overflow-y:auto;
    background:#fff;border-radius:24px;padding:24px;
    box-shadow:0 20px 60px rgba(0,0,0,.15);animation:modalIn .2s ease;
}
@keyframes modalIn{from{opacity:0;transform:translateY(15px);}to{opacity:1;transform:none;}}
.modal-title{font-size:20px;font-weight:800;margin-bottom:18px;color:#0f172a;}
.modal-actions{display:flex;gap:10px;margin-top:20px;}
.modal-btn{
    flex:1;border:none;padding:14px;border-radius:16px;cursor:pointer;
    font-family:'Vazirmatn',sans-serif;font-weight:700;
}
.save-btn{background:linear-gradient(135deg,#0284c7,#06b6d4);color:white;}
.cancel-btn{background:#f1f5f9;color:#334155;}
.field-label{display:block;font-size:13px;font-weight:700;color:#334155;margin-bottom:8px;}
.field-group{margin-bottom:14px;}
.password-box{position:relative;margin-bottom:14px;}
.password-box .form-control{margin-bottom:0;padding-left:52px;}
.toggle-password{
    position:absolute;left:18px;top:50%;transform:translateY(-50%);
    cursor:pointer;font-size:16px;color:#94a3b8;user-select:none;
}
.empty-box{text-align:center;color:#777;padding:20px;}
@media(max-width:768px){
    .admin-main{flex-direction:column;align-items:flex-start;gap:2px;}
    .admin-meta{white-space:normal;}
}
</style>

<div class="page-box">

<?php if($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
<?php if(count($admins)): ?>
<?php foreach($admins as $admin): ?>
<div class="admin-row" id="row-<?= $admin['id'] ?>">
<div class="admin-main">
<div>
<div class="admin-name"><?= htmlspecialchars($admin['fullname']) ?></div>
<div class="admin-username">@<?= htmlspecialchars($admin['username']) ?></div>
</div>
<div class="admin-meta">
<?= htmlspecialchars($admin['support_department']) ?> · 📱 <?= htmlspecialchars($admin['mobile']) ?>
</div>
</div>
<div class="row-actions">
<span class="status <?= $admin['status'] ?>"><?= $admin['status'] === 'active' ? 'فعال' : 'غیرفعال' ?></span>
<div class="job-menu">
<button class="menu-btn" type="button" onclick="toggleMenu(event, <?= $admin['id'] ?>)">⋮</button>
<div id="menu-<?= $admin['id'] ?>" class="dropdown-menu">
<button
type="button"
onclick="openPasswordModal(<?= $admin['id'] ?>, <?= json_encode($admin['fullname'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)">
🔐 تغییر رمز عبور
</button>
<?php if($admin['status'] === 'active'): ?>
<a href="?deactivate=<?= $admin['id'] ?>" onclick="return confirm('این ادمین غیرفعال شود؟')">⏸ غیرفعال‌سازی</a>
<?php else: ?>
<a href="?activate=<?= $admin['id'] ?>">▶️ فعال‌سازی</a>
<?php endif; ?>
<?php if((int)$admin['id'] !== (int)$_SESSION['user_id']): ?>
<a href="?delete=<?= $admin['id'] ?>" class="danger" onclick="return confirm('این ادمین حذف شود؟')">🗑 حذف</a>
<?php endif; ?>
</div>
</div>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="empty-box">هنوز ادمین پشتیبانی ثبت نشده است</div>
<?php endif; ?>
</div>

</div>

<div id="createModal" class="modal-overlay<?= $activeModal === 'create' ? ' show' : '' ?>">
<div class="modal-box" role="dialog" aria-modal="true">
<div class="modal-title">ایجاد ادمین پشتیبانی</div>
<form method="POST">
<div class="field-group">
<label class="field-label">نام و نام خانوادگی</label>
<input type="text" name="fullname" class="form-control" placeholder="نام و نام خانوادگی" required value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>">
</div>
<div class="field-group">
<label class="field-label">بخش پشتیبانی</label>
<input type="text" name="support_department" class="form-control" placeholder="مثلاً کارشناس IT" required value="<?= htmlspecialchars($_POST['support_department'] ?? '') ?>">
</div>
<div class="field-group">
<label class="field-label">شماره موبایل</label>
<input type="text" name="mobile" class="form-control" placeholder="09xxxxxxxxx" required maxlength="11" pattern="09[0-9]{9}" value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">
</div>
<div class="field-group">
<label class="field-label">نام کاربری</label>
<input type="text" name="username" class="form-control" placeholder="لاتین ۶ تا ۱۶ کاراکتر" required minlength="6" maxlength="16" pattern="[a-zA-Z0-9._-]{6,16}" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
</div>
<div class="hint">رمز عبور پیش‌فرض: <strong>1</strong> — در اولین ورود باید تغییر داده شود.</div>
<div class="modal-actions">
<button type="submit" name="create_admin" class="modal-btn save-btn">ایجاد ادمین</button>
<button type="button" onclick="closeCreateModal()" class="modal-btn cancel-btn">انصراف</button>
</div>
</form>
</div>
</div>

<div id="passwordModal" class="modal-overlay<?= $activeModal === 'password' ? ' show' : '' ?>">
<div class="modal-box" role="dialog" aria-modal="true">
<div class="modal-title" id="passwordModalTitle">تغییر رمز عبور</div>
<form method="POST">
<input type="hidden" name="admin_id" id="passwordAdminId" value="<?= (int)($_POST['admin_id'] ?? 0) ?>">
<div class="password-box">
<input type="password" name="password" id="adminPasswordField" class="form-control" placeholder="رمز عبور جدید" required minlength="8" autocomplete="new-password">
<span class="toggle-password" id="toggleAdminPassword">◉</span>
</div>
<div class="password-box">
<input type="password" name="password_confirm" id="adminConfirmPasswordField" class="form-control" placeholder="تکرار رمز عبور جدید" required minlength="8" autocomplete="new-password">
<span class="toggle-password" id="toggleAdminConfirmPassword">◉</span>
</div>
<div class="hint">رمز باید حداقل ۸ کاراکتر، شامل حرف لاتین و عدد باشد.</div>
<div class="modal-actions">
<button type="submit" name="change_admin_password" class="modal-btn save-btn">ذخیره رمز عبور</button>
<button type="button" onclick="closePasswordModal()" class="modal-btn cancel-btn">انصراف</button>
</div>
</form>
</div>
</div>

<script>
function closePageHeaderDropdown(){
    const dropdown = document.getElementById('pageHeaderDropdown');
    const menuBtn = document.getElementById('pageHeaderMenuBtn');
    if(dropdown){ dropdown.classList.remove('show'); }
    if(menuBtn){ menuBtn.setAttribute('aria-expanded', 'false'); }
}

function openCreateModal(){
    document.getElementById('createModal').classList.add('show');
    closePageHeaderDropdown();
}

function closeCreateModal(){
    document.getElementById('createModal').classList.remove('show');
}

function openPasswordModal(id, fullname){
    document.getElementById('passwordAdminId').value = id;
    document.getElementById('passwordModalTitle').textContent = 'تغییر رمز عبور — ' + fullname;
    document.getElementById('passwordModal').classList.add('show');
    document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.remove('show'));
    document.querySelectorAll('.admin-row').forEach(row => row.classList.remove('menu-open'));
}

function closePasswordModal(){
    document.getElementById('passwordModal').classList.remove('show');
}

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

setupPasswordToggle('toggleAdminPassword', 'adminPasswordField');
setupPasswordToggle('toggleAdminConfirmPassword', 'adminConfirmPasswordField');

function toggleMenu(event, id){
    event.stopPropagation();
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        if(menu.id !== 'menu-' + id){
            menu.classList.remove('show');
        }
    });
    document.querySelectorAll('.admin-row').forEach(row => {
        row.classList.remove('menu-open');
    });
    const menu = document.getElementById('menu-' + id);
    const row = document.getElementById('row-' + id);
    menu.classList.toggle('show');
    if(menu.classList.contains('show')){
        row.classList.add('menu-open');
    }
}

document.addEventListener('click', function(e){
    if(!e.target.closest('.job-menu')){
        document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.remove('show'));
        document.querySelectorAll('.admin-row').forEach(row => row.classList.remove('menu-open'));
    }
});

window.addEventListener('click', function(e){
    if(e.target.classList.contains('modal-overlay')){
        e.target.classList.remove('show');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
