<?php

session_start();

require 'includes/db.php';

if(isset($_SESSION['user_id'])){

    if($_SESSION['role'] == 'admin'){

        header("Location: /admin/");

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

                $_SESSION['user_id'] =
                $user['id'];

                $_SESSION['role'] =
                $user['role'];

                $_SESSION['fullname'] =
                $user['fullname'];

                unset($_SESSION['captcha']);

                if($user['role'] == 'admin'){

                    header(
                        "Location: /admin/"
                    );

                }else{

                    header(
                        "Location: /dashboard.php"
                    );

                }

                exit;

            }

        }else{

            $error =
            "شماره موبایل یا رمز عبور اشتباه است";

            unset($_SESSION['captcha']);

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

.captcha-wrapper{

    display:flex;

    align-items:center;

    gap:10px;

    margin-bottom:15px;

}

.captcha-box{

    background:#eff6ff;

    border:2px dashed #2563eb;

    border-radius:14px;

    padding:12px 18px;

    text-align:center;

    font-size:22px;

    font-weight:bold;

    letter-spacing:5px;

    color:#1d4ed8;

    min-width:150px;

}

.refresh-captcha{

    width:48px;

    height:48px;

    border:none;

    border-radius:14px;

    background:#2563eb;

    color:white;

    font-size:22px;

    cursor:pointer;

    transition:.2s;

}

.refresh-captcha:hover{

    background:#1d4ed8;

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

.auth-footer{

    text-align:center;

    margin-top:22px;

    color:#64748b;

    font-size:15px;

}

.auth-footer a{

    color:#2563eb;

    text-decoration:none;

    font-weight:bold;

}

.login-logo-footer{

    margin-top:38px;

    text-align:center;

}

.login-logo-footer img{

    width:260px;

    max-width:82%;

    opacity:.96;

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
