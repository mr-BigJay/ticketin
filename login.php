<?php

session_start();

require 'includes/db.php';
require 'includes/security.php';
require 'includes/user_helpers.php';

user_ensure_schema($pdo);

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

    $national_code_raw = trim($_POST['national_code'] ?? '');
    $national_code = user_normalize_national_code($national_code_raw);

    $password = trim($_POST['password'] ?? '');

    $captcha = strtoupper(trim($_POST['captcha'] ?? ''));

    if($national_code === null){

        $error = 'کد ملی معتبر نیست';

    }
    elseif(security_is_login_locked($national_code)){

        $minutes = security_get_lock_remaining_minutes($national_code);

        $error =
        "به دلیل تلاش‌های ناموفق، ورود برای {$minutes} دقیقه مسدود شده است";

    }
    elseif($captcha != $_SESSION['captcha']){

        $error =
        "کد امنیتی اشتباه است";

        unset($_SESSION['captcha']);

    }
    else{

        $user = user_find_by_national_code($pdo, $national_code_raw);

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

                security_clear_login_attempts($national_code);

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

            security_record_failed_login($national_code);

            if(security_is_login_locked($national_code)){

                $error =
                "تعداد تلاش‌های ناموفق بیش از حد مجاز است. ورود برای ۳۰ دقیقه مسدود شد";

            }else{

                $error =
                "کد ملی یا رمز عبور اشتباه است";

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

    position:relative;

    background:white;

    border-radius:22px;

    padding:52px 20px 18px;

    box-shadow:0 0 28px rgba(0,0,0,0.06);

    width:100%;

    overflow:visible;

}

.auth-card-head{

    text-align:center;

    margin-bottom:18px;

}

.auth-avatar{

    width:74px;

    height:74px;

    margin:-66px auto 14px;

    border-radius:50%;

    background:linear-gradient(
        135deg,
        #dbeafe 0%,
        #eff6ff 55%,
        #ffffff 100%
    );

    border:4px solid #ffffff;

    box-shadow:0 10px 24px rgba(2,132,199,.16);

    display:flex;

    align-items:center;

    justify-content:center;

}

.auth-avatar svg{

    width:38px;

    height:38px;

    color:#0284c7;

}

.auth-title{

    text-align:center;

    font-size:34px;

    font-weight:700;

    font-family:'Digi Lalezar Plus','Vazirmatn',sans-serif;

    margin-bottom:8px;

    color:#0369a1;

    line-height:1.2;

}

.auth-system-line{

    display:block;

    margin-bottom:6px;

    color:#0284c7;

    font-size:15px;

    font-weight:700;

    line-height:1.5;

}

.auth-org-line{

    display:block;

    color:#475569;

    font-size:13px;

    font-weight:500;

    line-height:1.7;

}

.auth-place{

    font-weight:800;

    font-size:17px;

    color:#1e293b;

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

.input-icon-box{

    position:relative;

    margin-bottom:10px;

}

.password-box{

    position:relative;

    margin-bottom:10px;

}

.input-icon-box .form-control,
.password-box .form-control{

    position:relative;

    z-index:1;

    background:#ffffff;

}

.toggle-password{

    position:absolute;

    left:14px;

    top:50%;

    transform:translateY(-50%);

    z-index:2;

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

    display:block;

}

.auth-footer{

    display:flex;

    align-items:center;

    justify-content:center;

    gap:8px;

    flex-wrap:wrap;

    margin-top:14px;

    color:#64748b;

    font-size:13px;

}

.auth-register-btn{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    padding:8px 14px;

    border-radius:12px;

    background:#f0fdf4;

    border:1px solid #86efac;

    color:#15803d;

    text-decoration:none;

    font-size:13px;

    font-weight:700;

    font-family:'Vazirmatn',sans-serif;

    transition:.2s;

}

.auth-register-btn:hover{

    background:#dcfce7;

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

    .auth-card{

        padding-top:48px;

    }

    .auth-avatar{

        width:68px;

        height:68px;

        margin:-60px auto 12px;

    }

    .auth-avatar svg{

        width:34px;

        height:34px;

    }

    .auth-title{

        font-size:28px;

    }

    .auth-system-line{

        font-size:14px;

    }

    .auth-place{

        font-size:15px;

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

<div class="auth-card-head">

<div class="auth-avatar" aria-hidden="true">

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm0 2c-3.33 0-10 1.67-10 5v2h20v-2c0-3.33-6.67-5-10-5z"/></svg>

</div>

<div class="auth-title">ورود</div>

<div class="auth-system-line">سامانه پشتیبانی IT</div>

<div class="auth-org-line">شبکه بهداشت و درمان <strong class="auth-place">رودسر</strong></div>

</div>

<?php if($error): ?>

<div class="alert alert-danger">

<?= $error ?>

</div>

<?php endif; ?>

<form method="POST">

<div class="input-icon-box">

<input
type="text"
name="national_code"
class="form-control"
placeholder="کد ملی (نام کاربری)"
required
maxlength="10"
inputmode="numeric"
pattern="[0-9]{10}"
autocomplete="username">

<span class="input-field-icon" aria-hidden="true">

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>

</span>

</div>

<div class="password-box">

<input
type="password"
name="password"
id="passwordField"
class="form-control"
placeholder="رمز عبور"
required
autocomplete="current-password">

<span class="input-field-icon" aria-hidden="true">

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>

</span>

<span
class="toggle-password"
id="togglePassword"
title="نمایش رمز عبور"
aria-label="نمایش رمز عبور">

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>

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

<span>حساب کاربری ندارید؟</span>

<a href="/register.php" class="auth-register-btn">ثبت نام کنید</a>

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

const eyeOpen =
'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';

const eyeClosed =
'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>';

toggleBtn.addEventListener(
    'click',
    function(){

        if(
            passwordField.type === 'password'
        ){

            passwordField.type = 'text';

            toggleBtn.innerHTML = eyeClosed;
            toggleBtn.title = 'مخفی کردن رمز عبور';

        }else{

            passwordField.type = 'password';

            toggleBtn.innerHTML = eyeOpen;
            toggleBtn.title = 'نمایش رمز عبور';

        }

    }
);

</script>

<?php include 'includes/footer.php'; ?>
