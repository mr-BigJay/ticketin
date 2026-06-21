<?php
session_start();
require 'includes/db.php';
require 'includes/security.php';

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
        $check = $pdo->prepare("SELECT id FROM users WHERE mobile=? OR national_code=?");
        $check->execute([$mobile, $national_code]);

        if($check->fetch()){
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

require 'includes/header.php';
?>

<style>
/* استایل‌های قبلی بدون تغییر */
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
.name-row{
    display:flex;
    gap:10px;
}
.name-row .form-control{
    width:50%;
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

/* استایل مودال جدید */
.success-modal {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 1050;
    align-items: center;
    justify-content: center;
}
.success-content {
    background: white;
    max-width: 480px;
    width: 90%;
    border-radius: 24px;
    padding: 40px 30px;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}
.success-content h2 {
    color: #10b981;
    margin-bottom: 16px;
    font-size: 24px;
}
.success-content p {
    color: #334155;
    line-height: 1.6;
    margin-bottom: 30px;
}
</style>

<div class="auth-box">
<div class="auth-card">
    <div class="auth-title">ثبت نام کاربران</div>
    <div class="auth-subtitle">
        سامانه پشتیبانی و ثبت تیکت IT<br>
        شبکه بهداشت و درمان رودسر
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
        <input type="text" name="national_code" class="form-control" placeholder="کد ملی" required maxlength="10" pattern="[0-9]{10}">
        <input type="text" name="mobile" class="form-control" placeholder="شماره موبایل" required maxlength="11" pattern="09[0-9]{9}">
        
        <div class="password-box">
            <input type="password" name="password" id="passwordField" class="form-control" placeholder="رمز عبور" required>
            <span class="toggle-password" id="togglePassword">◉</span>
        </div>

        <div class="captcha-wrapper">
            <div class="captcha-box"><?= $_SESSION['captcha'] ?></div>
            <button type="button" class="refresh-captcha" onclick="window.location='?refresh_captcha=1';">↻</button>
        </div>

        <input type="text" name="captcha" class="form-control" placeholder="کد امنیتی را وارد کنید" required maxlength="5">

        <button type="submit" class="btn-custom">ثبت نام</button>
    </form>

    <div class="auth-footer">
        حساب کاربری دارید؟ <a href="/login.php">ورود</a>
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