<?php

session_start();

require 'includes/db.php';
require 'includes/security.php';

if(isset($_SESSION['user_id'])){

    if(($_SESSION['role'] ?? '') == 'admin'){

        header("Location: /jay_controller.php");

    }else{

        header("Location: /dashboard.php");

    }

    exit;

}

if(isset($_GET['refresh_captcha'])){

    unset($_SESSION['captcha']);

    header("Location: login.php");

    exit;

}

if(!isset($_SESSION['captcha'])){

    $chars =
    'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    $captcha = '';

    for($i=0;$i<5;$i++){

        $captcha .=
        $chars[rand(
            0,
            strlen($chars)-1
        )];

    }

    $_SESSION['captcha'] =
    $captcha;

}

$error = "";

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $mobile =
    trim($_POST['mobile']);

    $password =
    trim($_POST['password']);

    $captcha =
    strtoupper(
        trim($_POST['captcha'])
    );

    if(

        !preg_match(
            '/^09[0-9]{9}$/',
            $mobile
        )

    ){

        $error =
        "شماره موبایل معتبر نیست";

    }

    elseif(security_is_login_locked($mobile)){

        $minutes = security_get_lock_remaining_minutes($mobile);

        $error =
        "به دلیل تلاش‌های ناموفق، ورود برای {$minutes} دقیقه مسدود شده است";

    }

    elseif(
        $captcha !=
        $_SESSION['captcha']

    ){

        $error =
        "کد امنیتی اشتباه است";

        unset($_SESSION['captcha']);

    }

    else{

        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE mobile=?
            AND role='user'
        ");

        $stmt->execute([$mobile]);

        $user =
        $stmt->fetch();

        if(
            $user &&
            password_verify(
                $password,
                $user['password']
            )
        ){

            if($user['status'] != 'active'){

                $error =
                "حساب کاربری هنوز تایید نشده است";

            }else{

                security_clear_login_attempts($mobile);

                $_SESSION['user_id'] =
                $user['id'];

                $_SESSION['role'] =
                $user['role'];

                $_SESSION['fullname'] =
                $user['fullname'];

                unset($_SESSION['captcha']);

                header(
                    "Location: /dashboard.php"
                );

                exit;

            }

        }else{

            security_record_failed_login($mobile);

            if(security_is_login_locked($mobile)){

                $error =
                "تعداد تلاش‌های ناموفق بیش از حد مجاز است. ورود برای ۳۰ دقیقه مسدود شد";

            }else{

                $error =
                "شماره موبایل یا رمز عبور اشتباه است";

            }

            unset($_SESSION['captcha']);

        }

    }

}

$auth_page = true;

require 'includes/header.php';

?>

<style>

.auth-box{

    max-width:460px;

    margin:0 auto;

    width:100%;

    flex:1;

    display:flex;

    align-items:center;

}

.auth-card{

    background:white;

    border-radius:22px;

    padding:22px 20px 18px;

    box-shadow:0 0 28px rgba(0,0,0,0.06);

    width:100%;

}

.auth-title{

    text-align:center;

    font-size:24px;

    font-weight:800;

    margin-bottom:6px;

    color:#0f172a;

}

.auth-subtitle{

    text-align:center;

    color:#64748b;

    line-height:24px;

    font-size:13px;

    margin-bottom:16px;

}

.captcha-wrapper{

    display:flex;

    align-items:center;

    gap:8px;

    margin-bottom:10px;

}

.captcha-box{

    background:#eff6ff;

    border:2px dashed #2563eb;

    border-radius:12px;

    padding:10px 14px;

    text-align:center;

    font-size:18px;

    font-weight:bold;

    letter-spacing:4px;

    color:#1d4ed8;

    min-width:120px;

    flex:1;

}

.refresh-captcha{

    width:42px;

    height:42px;

    border:none;

    border-radius:12px;

    background:#2563eb;

    color:white;

    font-size:20px;

    cursor:pointer;

    transition:.2s;

    flex-shrink:0;

}

.refresh-captcha:hover{

    background:#1d4ed8;

}

.password-box{

    position:relative;

    margin-bottom:10px;

}

.password-box .form-control{

    margin-bottom:0;

    padding-left:48px;

}

.toggle-password{

    position:absolute;

    left:14px;

    top:50%;

    transform:translateY(-50%);

    cursor:pointer;

    font-size:15px;

    color:#94a3b8;

    user-select:none;

}

.auth-footer{

    text-align:center;

    margin-top:14px;

    color:#64748b;

    font-size:13px;

}

.auth-footer a{

    color:#2563eb;

    text-decoration:none;

    font-weight:bold;

}

.login-logo-footer{

    margin-top:14px;

    text-align:center;

}

.login-logo-footer img{

    width:170px;

    max-width:70%;

    opacity:.94;

}

@media (max-height: 760px){

    .auth-card{

        padding:16px 16px 14px;

        border-radius:18px;

    }

    .auth-title{

        font-size:21px;

        margin-bottom:4px;

    }

    .auth-subtitle{

        font-size:12px;

        line-height:22px;

        margin-bottom:12px;

    }

    .login-logo-footer{

        margin-top:10px;

    }

    .login-logo-footer img{

        width:140px;

    }

}

@media (max-width: 420px){

    .auth-title{

        font-size:20px;

    }

    .captcha-box{

        font-size:16px;

        letter-spacing:3px;

        padding:8px 10px;

    }

}

</style>

<div class="auth-box">

<div class="auth-card">

<div class="auth-title">

ورود کاربران

</div>

<div class="auth-subtitle">

سامانه پشتیبانی و ثبت تیکت IT
<br>
شبکه بهداشت و درمان رودسر

</div>

<?php if($error): ?>

<div class="alert alert-danger">

<?= $error ?>

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

<div class="captcha-wrapper">

<div class="captcha-box">

<?= $_SESSION['captcha'] ?>

</div>

<button
type="button"
class="refresh-captcha"
onclick="window.location='?refresh_captcha=1';">

↻

</button>

</div>

<input
type="text"
name="captcha"
class="form-control"
placeholder="کد امنیتی را وارد کنید"
required
maxlength="5">

<button
type="submit"
class="btn-custom">

ورود

</button>

</form>

<div class="auth-footer">

حساب کاربری ندارید؟

<a href="/register.php">

ثبت نام کنید

</a>

</div>

<div class="login-logo-footer">

<img
src="/assets/gums-logo.png"
alt="Guilan University of Medical Sciences">

</div>

</div>

</div>

<script>

const toggleBtn =
document.getElementById(
    'togglePassword'
);

const passwordField =
document.getElementById(
    'passwordField'
);

toggleBtn.addEventListener(
    'click',
    function(){

        if(
            passwordField.type === 'password'
        ){

            passwordField.type = 'text';

            toggleBtn.innerHTML = '○';

        }else{

            passwordField.type = 'password';

            toggleBtn.innerHTML = '◉';

        }

    }
);

</script>

</div>

</body>

</html>
