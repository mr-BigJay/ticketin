<?php
session_start();
require 'includes/db.php';
require 'includes/security.php';
require 'includes/user_helpers.php';

user_ensure_schema($pdo);

if(isset($_SESSION['user_id'])){
    header("Location: /dashboard.php");
    exit;
}

if(isset($_GET['refresh_captcha'])){
    unset($_SESSION['captcha']);
    header("Location: register.php");
    exit;
}

if(!isset($_SESSION['captcha'])){
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $captcha = '';
    for($i=0;$i<5;$i++){
        $captcha .= $chars[rand(0, strlen($chars)-1)];
    }
    $_SESSION['captcha'] = $captcha;
}

$error = "";
$success = false; // تغییر به boolean برای تشخیص بهتر

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $fullname = $firstname . ' ' . $lastname;
    $national_code = trim($_POST['national_code']);
    $mobile = trim($_POST['mobile']);
    $password = trim($_POST['password']);
    $captcha = strtoupper(trim($_POST['captcha']));

    if(!$firstname || !$lastname || !$national_code || !$mobile || !$password){
        $error = "تمام فیلدها الزامی هستند";
    }
    elseif(!preg_match('/^[0-9]{10}$/', $national_code)){
        $error = "کد ملی معتبر نیست";
    }
    elseif(!preg_match('/^09[0-9]{9}$/', $mobile)){
        $error = "شماره موبایل معتبر نیست";
    }
    elseif(strlen($password) < 8){  // ← تغییر مهم: فقط ۸ کاراکتر
        $error = "رمز عبور باید حداقل ۸ کاراکتر باشد";
    }
    elseif($captcha != $_SESSION['captcha']){
        $error = "کد امنیتی اشتباه است";
        unset($_SESSION['captcha']);
    }
    elseif(!security_can_register_today($pdo)){
        $error = "ظرفیت ثبت نام روزانه تکمیل شده است. لطفاً فردا دوباره تلاش کنید";
    }
    else{
        if(user_registration_exists($pdo, $mobile, $national_code)){
            $error = "کاربری با این اطلاعات وجود دارد";
        }else{
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (fullname, national_code, mobile, password, role, status, created_at)
                VALUES (?, ?, ?, ?, 'user', 'pending', NOW())
            ");
            $stmt->execute([$fullname, $national_code, $mobile, $hashedPassword]);

            unset($_SESSION['captcha']);
            $success = true;
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
.name-row,
.split-row{
    display:flex;
    gap:8px;
}
.name-row .form-control:first-child{
    flex:0 0 35%;
    max-width:35%;
    min-width:0;
    margin-bottom:10px;
}
.name-row .form-control:last-child{
    flex:1 1 65%;
    min-width:0;
    margin-bottom:10px;
}
.split-row .form-control{
    flex:1;
    width:auto;
    min-width:0;
    margin-bottom:10px;
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
.auth-logo-footer{
    margin-top:14px;
    text-align:center;
}
.auth-logo-footer img{
    width:170px;
    max-width:70%;
    opacity:.94;
}
.success-modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.6);
    z-index:1050;
    align-items:center;
    justify-content:center;
    padding:16px;
}
.success-content{
    background:white;
    max-width:420px;
    width:100%;
    border-radius:20px;
    padding:28px 22px;
    text-align:center;
    box-shadow:0 10px 40px rgba(0,0,0,0.15);
}
.success-content h2{
    color:#10b981;
    margin-bottom:12px;
    font-size:22px;
}
.success-content p{
    color:#334155;
    line-height:1.7;
    margin-bottom:22px;
    font-size:14px;
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
    .auth-logo-footer{
        margin-top:10px;
    }
    .auth-logo-footer img{
        width:140px;
    }
}
@media (max-width: 420px){
    .auth-title{
        font-size:20px;
    }
    .name-row .form-control:first-child,
    .name-row .form-control:last-child,
    .split-row .form-control{
        padding:12px 10px;
        font-size:13px;
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
        <div class="auth-eyebrow">سامانه پشتیبانی IT</div>
        <div class="auth-title">عضویت در تیکتین</div>
        <div class="auth-subtitle">شبکه بهداشت و درمان رودسر</div>
    </div>

    <?php if($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if(!$success): // فرم فقط وقتی موفقیت نباشد نمایش داده شود ?>
    <form method="POST">
        <div class="name-row">
            <input type="text" name="firstname" class="form-control" placeholder="نام" required>
            <input type="text" name="lastname" class="form-control" placeholder="نام خانوادگی" required>
        </div>
        <div class="split-row">
            <input type="text" name="mobile" class="form-control" placeholder="شماره موبایل" required maxlength="11" pattern="09[0-9]{9}">
            <input type="text" name="national_code" class="form-control" placeholder="کد ملی" required maxlength="10" pattern="[0-9]{10}">
        </div>
        
        <div class="password-box">
            <input type="password" name="password" id="passwordField" class="form-control" placeholder="رمز عبور" required>
            <span class="toggle-password" id="togglePassword" title="نمایش رمز عبور" aria-label="نمایش رمز عبور">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            </span>
        </div>

        <div class="captcha-wrapper">
            <div class="captcha-box"><?= $_SESSION['captcha'] ?></div>
            <button type="button" class="refresh-captcha" onclick="window.location='?refresh_captcha=1';">↻</button>
        </div>

        <input type="text" name="captcha" class="form-control" placeholder="کد امنیتی را وارد کنید" required maxlength="5">

        <button type="submit" class="btn-custom">ثبت نام</button>
    </form>

    <div class="auth-switch">
        حساب کاربری دارید؟
        <br>
        <a href="/login.php">ورود به سامانه</a>
    </div>

    <div class="auth-logo-footer">
        <img src="/assets/gums-logo.png" alt="Guilan University of Medical Sciences">
    </div>
    <?php endif; ?>
</div>
</div>

<!-- مودال موفقیت -->
<?php if($success): ?>
<div class="success-modal" id="successModal" style="display:flex;">
    <div class="success-content">
        <h2>ثبت نام با موفقیت انجام شد</h2>
        <p>ثبت نام شما موفقیت آمیز بوده.<br>
        بعد از بررسی توسط ادمین تایید خواهد شد.<br>
        از طریق پیامک به شماره شما اطلاع‌رسانی می‌شود.</p>
        <button onclick="window.location='/login.php'" class="btn-custom" style="width:100%; max-width:280px; margin:0 auto; display:block;">
            برگشت به صفحه ورود
        </button>
    </div>
</div>
<?php endif; ?>

<script>
const eyeOpen =
'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';

const eyeClosed =
'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>';

const toggleBtn = document.getElementById('togglePassword');
const passwordField = document.getElementById('passwordField');

if(toggleBtn && passwordField){
    toggleBtn.addEventListener('click', function(){
        if(passwordField.type === 'password'){
            passwordField.type = 'text';
            toggleBtn.innerHTML = eyeClosed;
            toggleBtn.title = 'مخفی کردن رمز عبور';
        }else{
            passwordField.type = 'password';
            toggleBtn.innerHTML = eyeOpen;
            toggleBtn.title = 'نمایش رمز عبور';
        }
    });
}
</script>

</div>

</div>

</body>

</html>