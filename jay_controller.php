<?php

session_start();

require 'includes/db.php';
require 'includes/security.php';
require 'includes/admin_helpers.php';

admin_ensure_schema($pdo);

if(
    isset($_SESSION['user_id'])
    &&
    ($_SESSION['role'] ?? '') === 'admin'
    &&
    empty($_SESSION['must_change_password'])
){
    header('Location: /admin/');
    exit;
}

$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if(security_is_login_locked($username)){
        $minutes = security_get_lock_remaining_minutes($username);
        $error = "به دلیل تلاش‌های ناموفق، ورود برای {$minutes} دقیقه مسدود شده است";
    }elseif($msg = admin_validate_username($username)){
        $error = $msg;
    }else{
        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE username=?
            AND role='admin'
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if($user && password_verify($password, $user['password'])){
            if($user['status'] != 'active'){
                $error = 'حساب مدیریت غیرفعال است';
                security_record_failed_login($username);
            }else{
                security_clear_login_attempts($username);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['admin_type'] = $user['admin_type'] ?: 'super';
                $_SESSION['support_department'] = $user['support_department'] ?? '';
                $_SESSION['username'] = $user['username'] ?? '';
                $_SESSION['must_change_password'] = (int)$user['must_change_password'];

                if(!empty($user['must_change_password'])){
                    header('Location: /admin/change-password.php');
                }else{
                    header('Location: /admin/');
                }
                exit;
            }
        }else{
            security_record_failed_login($username);

            if(security_is_login_locked($username)){
                $error = 'تعداد تلاش‌های ناموفق بیش از حد مجاز است. ورود برای ۳۰ دقیقه مسدود شد';
            }else{
                $error = 'نام کاربری یا رمز عبور اشتباه است';
            }
        }
    }
}

require 'includes/header.php';

?>

<style>
.auth-box{max-width:520px;margin:40px auto;}
.auth-card{background:white;border-radius:30px;padding:35px;box-shadow:0 0 35px rgba(0,0,0,0.06);}
.auth-title{text-align:center;font-size:32px;font-weight:bold;margin-bottom:10px;color:#0f172a;}
.auth-subtitle{text-align:center;color:#64748b;line-height:34px;font-size:15px;margin-bottom:28px;}
.password-box{position:relative;margin-bottom:15px;}
.password-box .form-control{margin-bottom:0;padding-left:52px;}
.toggle-password{position:absolute;left:18px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:16px;color:#94a3b8;user-select:none;}
</style>

<div class="auth-box">
<div class="auth-card">
<div class="auth-title">🛠 ورود مدیریت</div>
<div class="auth-subtitle">پنل مدیریت سامانه پشتیبانی و ثبت تیکت<br>شبکه بهداشت و درمان رودسر</div>

<?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST">
<input type="text" name="username" class="form-control" placeholder="نام کاربری" required minlength="6" maxlength="16" pattern="[a-zA-Z0-9._-]{6,16}" autocomplete="username">
<div class="password-box">
<input type="password" name="password" id="passwordField" class="form-control" placeholder="رمز عبور" required autocomplete="current-password">
<span class="toggle-password" id="togglePassword">◉</span>
</div>
<button type="submit" class="btn-custom">ورود به مدیریت</button>
</form>
</div>
</div>

<script>
const toggleBtn = document.getElementById('togglePassword');
const passwordField = document.getElementById('passwordField');
if(toggleBtn && passwordField){
    toggleBtn.addEventListener('click', function(){
        if(passwordField.type === 'password'){
            passwordField.type = 'text';
            toggleBtn.innerHTML = '○';
        }else{
            passwordField.type = 'password';
            toggleBtn.innerHTML = '◉';
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
