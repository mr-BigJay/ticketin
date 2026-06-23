<?php

require '../includes/admin_auth.php';

admin_require_super();

$message = '';
$error = '';

if(isset($_GET['deactivate'])){
    $id = (int)$_GET['deactivate'];

    $stmt = $pdo->prepare("
        UPDATE users
        SET status='inactive'
        WHERE id=? AND role='admin' AND admin_type='support'
    ");
    $stmt->execute([$id]);

    header('Location: admins.php');
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

if($_SERVER['REQUEST_METHOD'] === 'POST'){

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

require '../includes/header.php';

?>

<style>
.page-box{max-width:950px;margin:auto;}
.card{background:white;border-radius:22px;padding:22px;margin-bottom:20px;box-shadow:0 0 20px rgba(0,0,0,0.05);}
.admin-row{background:#f8fafc;border-radius:18px;padding:16px;margin-bottom:12px;display:grid;grid-template-columns:1.2fr 1fr 1fr auto;gap:12px;align-items:center;}
.admin-name{font-weight:800;color:#0f172a;}
.admin-meta{font-size:13px;color:#64748b;line-height:26px;}
.status{display:inline-block;padding:6px 12px;border-radius:999px;font-size:12px;color:#fff;font-weight:700;}
.active{background:#10b981;}
.inactive{background:#ef4444;}
.btn-sm{padding:8px 12px;border-radius:10px;text-decoration:none;color:#fff;font-size:12px;font-weight:700;}
.btn-off{background:#f59e0b;}
.btn-on{background:#2563eb;}
.hint{font-size:13px;color:#64748b;line-height:28px;margin-top:10px;}
@media(max-width:768px){.admin-row{grid-template-columns:1fr;}}
</style>

<div class="page-box">

<?php if($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
<form method="POST">
<input type="text" name="fullname" class="form-control" placeholder="نام و نام خانوادگی" required value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>">
<input type="text" name="support_department" class="form-control" placeholder="بخش پشتیبانی (مثلاً کارشناس IT)" required value="<?= htmlspecialchars($_POST['support_department'] ?? '') ?>">
<input type="text" name="mobile" class="form-control" placeholder="شماره موبایل" required maxlength="11" pattern="09[0-9]{9}" value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">
<input type="text" name="username" class="form-control" placeholder="نام کاربری (لاتین ۶ تا ۱۶ کاراکتر)" required minlength="6" maxlength="16" pattern="[a-zA-Z0-9._-]{6,16}" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
<div class="hint">رمز عبور پیش‌فرض: <strong>1</strong> — در اولین ورود باید تغییر داده شود.</div>
<button type="submit" class="btn-custom">ایجاد ادمین پشتیبانی</button>
</form>
</div>

<div class="card">
<?php if(count($admins)): ?>
<?php foreach($admins as $admin): ?>
<div class="admin-row">
<div>
<div class="admin-name"><?= htmlspecialchars($admin['fullname']) ?></div>
<div class="admin-meta">@<?= htmlspecialchars($admin['username']) ?></div>
</div>
<div class="admin-meta">
<?= htmlspecialchars($admin['support_department']) ?><br>
📱 <?= htmlspecialchars($admin['mobile']) ?>
</div>
<div>
<span class="status <?= $admin['status'] ?>"><?= $admin['status'] === 'active' ? 'فعال' : 'غیرفعال' ?></span>
</div>
<div>
<?php if($admin['status'] === 'active'): ?>
<a href="?deactivate=<?= $admin['id'] ?>" class="btn-sm btn-off">غیرفعال</a>
<?php else: ?>
<a href="?activate=<?= $admin['id'] ?>" class="btn-sm btn-on">فعال</a>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="hint">هنوز ادمین پشتیبانی ثبت نشده است.</div>
<?php endif; ?>
</div>

</div>

<?php include '../includes/footer.php'; ?>
