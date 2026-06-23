<?php

require '../includes/admin_auth.php';

$error = '';
$message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if($msg = admin_validate_password($password)){
        $error = $msg;
    }elseif($password !== $confirm){
        $error = 'تکرار رمز عبور یکسان نیست';
    }elseif($password === '1'){
        $error = 'رمز عبور جدید نمی‌تواند همان رمز پیش‌فرض باشد';
    }else{
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$_SESSION['user_id']]);
        $current = $stmt->fetch();

        if($current && password_verify($password, $current['password'])){
            $error = 'رمز عبور جدید باید با رمز فعلی متفاوت باشد';
        }else{
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                UPDATE users
                SET password=?, must_change_password=0
                WHERE id=?
            ");
            $stmt->execute([$hash, $_SESSION['user_id']]);

            $_SESSION['must_change_password'] = 0;

            header('Location: /admin/');
            exit;
        }
    }
}

$hideBackButton = true;
$page_title = '🔐 تغییر رمز عبور';

require '../includes/header.php';

?>

<style>
.auth-box{max-width:520px;margin:40px auto;}
.auth-card{background:white;border-radius:24px;padding:28px;box-shadow:0 0 20px rgba(0,0,0,.05);}
.auth-title{font-size:24px;font-weight:800;margin-bottom:10px;text-align:center;}
.auth-subtitle{color:#64748b;line-height:30px;font-size:14px;text-align:center;margin-bottom:20px;}
</style>

<div class="auth-box">
<div class="auth-card">
<div class="auth-title">تغییر رمز عبور</div>
<div class="auth-subtitle">
برای ادامه، رمز عبور جدید خود را تنظیم کنید.<br>
حداقل ۸ کاراکتر، شامل حرف لاتین و عدد.
</div>

<?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST">
<input type="password" name="password" class="form-control" placeholder="رمز عبور جدید" required minlength="8">
<input type="password" name="password_confirm" class="form-control" placeholder="تکرار رمز عبور جدید" required minlength="8">
<button type="submit" class="btn-custom">ذخیره و ورود به پنل</button>
</form>
</div>
</div>

<?php include '../includes/footer.php'; ?>
