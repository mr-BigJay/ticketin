<?php

session_start();

require 'includes/db.php';
require 'includes/security.php';

if(
    isset($_SESSION['user_id'])
    &&
    ($_SESSION['role'] ?? '') === 'admin'
){
    header("Location: /admin/");
    exit;
}

$error = "";

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $mobile = trim($_POST['mobile'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if(security_is_login_locked($mobile)){

        $minutes = security_get_lock_remaining_minutes($mobile);

        $error =
        "به دلیل تلاش‌های ناموفق، ورود برای {$minutes} دقیقه مسدود شده است";

    }elseif(!preg_match('/^09[0-9]{9}$/', $mobile)){

        $error = "شماره موبایل معتبر نیست";

    }else{

        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE mobile=?
            AND role='admin'
        ");

        $stmt->execute([$mobile]);

        $user = $stmt->fetch();

        if(
            $user
            &&
            password_verify($password, $user['password'])
        ){

            if($user['status'] != 'active'){

                $error = "حساب مدیریت غیرفعال است";

                security_record_failed_login($mobile);

            }else{

                security_clear_login_attempts($mobile);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];

                header("Location: /admin/");
                exit;

            }

        }else{

            security_record_failed_login($mobile);

            if(security_is_login_locked($mobile)){

                $error =
                "تعداد تلاش‌های ناموفق بیش از حد مجاز است. ورود برای ۳۰ دقیقه مسدود شد";

            }else{

                $error = "اطلاعات ورود ادمین اشتباه است";

            }

        }

    }

}

require 'includes/header.php';

?>

<style>

.auth-box{

    max-width:520px;

    margin:40px auto;

}

.auth-card{

    background:white;

    border-radius:30px;

    padding:35px;

    box-shadow:0 0 35px rgba(0,0,0,0.06);

}

.auth-title{

    text-align:center;

    font-size:32px;

    font-weight:bold;

    margin-bottom:10px;

    color:#0f172a;

}

.auth-subtitle{

    text-align:center;

    color:#64748b;

    line-height:34px;

    font-size:15px;

    margin-bottom:28px;

}

.password-box{

    position:relative;

    margin-bottom:15px;

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

</style>

<div class="auth-box">

<div class="auth-card">

<div class="auth-title">

🛠 ورود مدیریت

</div>

<div class="auth-subtitle">

پنل مدیریت سامانه پشتیبانی و ثبت تیکت
<br>
شبکه بهداشت و درمان رودسر

</div>

<?php if($error): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<form method="POST">

<input
type="text"
name="mobile"
class="form-control"
placeholder="شماره موبایل"
required
maxlength="11"
pattern="09[0-9]{9}">

<div class="password-box">

<input
type="password"
name="password"
id="passwordField"
class="form-control"
placeholder="رمز عبور"
required>

<span
class="toggle-password"
id="togglePassword">

◉

</span>

</div>

<button
type="submit"
class="btn-custom">

ورود به مدیریت

</button>

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
