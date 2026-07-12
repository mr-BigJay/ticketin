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
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $fullname = trim($firstname . ' ' . $lastname);
    $national_code_raw = trim($_POST['national_code'] ?? '');
    $mobile_raw = trim($_POST['mobile'] ?? '');
    $national_code = user_normalize_national_code($national_code_raw);
    $mobile = user_normalize_mobile($mobile_raw);
    $password = (string)($_POST['password'] ?? '');
    $captcha = strtoupper(trim($_POST['captcha'] ?? ''));

    if($firstname === '' || $lastname === ''){
        $error = 'نام و نام خانوادگی الزامی است';
    }
    elseif($msg = user_validate_persian_name($firstname, 'نام')){
        $error = $msg;
    }
    elseif($msg = user_validate_persian_name($lastname, 'نام خانوادگی')){
        $error = $msg;
    }
    elseif($national_code === null){
        $error = user_validate_national_code($national_code_raw) ?? 'کد ملی معتبر نیست';
    }
    elseif($mobile === null){
        $error = user_validate_mobile($mobile_raw) ?? 'شماره موبایل معتبر نیست';
    }
    elseif($msg = user_validate_password($password)){
        $error = $msg;
    }
    elseif($captcha === '' || !isset($_SESSION['captcha']) || $captcha !== $_SESSION['captcha']){
        $error = 'کد امنیتی اشتباه است';
        unset($_SESSION['captcha']);
    }
    elseif(!security_can_register_today($pdo)){
        $error = 'ظرفیت ثبت نام روزانه تکمیل شده است. لطفاً فردا دوباره تلاش کنید';
    }
    else{
        $existingUser = user_registration_lookup($pdo, $mobile, $national_code);

        if($existingUser){
            $error = user_registration_conflict_message($existingUser)
                ?? 'کاربری با این اطلاعات وجود دارد';
        }else{
            try{
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO users (fullname, national_code, mobile, password, role, status, created_at)
                    VALUES (?, ?, ?, ?, 'user', 'pending', NOW())
                ");
                $stmt->execute([$fullname, $national_code, $mobile, $hashedPassword]);

                unset($_SESSION['captcha']);
                $success = true;

                try{
                    require_once __DIR__ . '/includes/push_helpers.php';
                    push_notify_new_registration($pdo, $fullname);
                }catch(Throwable $e){
                }
            }catch(PDOException $e){
                $error = 'خطا در ثبت نام. اگر قبلاً ثبت نام کرده‌اید، منتظر تایید ادمین بمانید یا با پشتیبانی تماس بگیرید.';
            }
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
.auth-avatar-wrap{
    position:relative;
    width:74px;
    height:74px;
    margin:-66px auto 14px;
    z-index:1;
}
.auth-avatar{
    position:relative;
    width:74px;
    height:74px;
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
.auth-avatar-plus{
    position:absolute;
    top:-2px;
    right:-2px;
    width:22px;
    height:22px;
    border-radius:50%;
    background:#ffffff;
    border:2px solid #e0f2fe;
    box-shadow:0 4px 10px rgba(2,132,199,.18);
    display:flex;
    align-items:center;
    justify-content:center;
    color:#0284c7;
    user-select:none;
}
.auth-avatar-plus svg{
    width:12px;
    height:12px;
    display:block;
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
.auth-hint{
    margin-top:10px;
    font-size:12px;
    line-height:1.8;
    color:#64748b;
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
    background:#eff6ff;
    border:1px solid #93c5fd;
    color:#1d4ed8;
    text-decoration:none;
    font-size:13px;
    font-weight:700;
    font-family:'Vazirmatn',sans-serif;
    transition:.2s;
}
.auth-register-btn:hover{
    background:#dbeafe;
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
    .auth-card{
        padding-top:48px;
    }
    .auth-avatar-wrap::before{
        inset:-8px;
    }
    .auth-avatar-wrap::after{
        inset:-15px;
    }
    .auth-avatar-wrap{
        width:68px;
        height:68px;
        margin:-60px auto 12px;
    }
    .auth-avatar{
        width:68px;
        height:68px;
    }
    .auth-avatar svg{
        width:32px;
        height:32px;
    }
    .auth-avatar-plus{
        top:-1px;
        right:-1px;
        width:20px;
        height:20px;
    }
    .auth-avatar-plus svg{
        width:11px;
        height:11px;
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
        <div class="auth-avatar-wrap" aria-hidden="true">
            <div class="auth-avatar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm0 2c-3.33 0-10 1.67-10 5v2h20v-2c0-3.33-6.67-5-10-5z"/></svg>
            </div>
            <span class="auth-avatar-plus" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="square"><path d="M12 5v14M5 12h14"/></svg>
            </span>
        </div>
        <div class="auth-title">ثبت نام</div>
        <div class="auth-system-line">سامانه پشتیبانی IT</div>
        <div class="auth-org-line">شبکه بهداشت و درمان <strong class="auth-place">رودسر</strong></div>
        <div class="auth-hint">پس از ثبت نام، حساب شما پس از تایید ادمین فعال می‌شود.</div>
    </div>

    <?php if($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if(!$success): // فرم فقط وقتی موفقیت نباشد نمایش داده شود ?>
    <form method="POST" id="registerForm" novalidate>
        <div class="name-row">
            <input type="text" name="firstname" class="form-control" placeholder="نام" required autocomplete="given-name" value="<?= htmlspecialchars($_POST['firstname'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="text" name="lastname" class="form-control" placeholder="نام خانوادگی" required autocomplete="family-name" value="<?= htmlspecialchars($_POST['lastname'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="split-row">
            <input type="text" name="mobile" id="mobileField" class="form-control" placeholder="شماره موبایل (مثلاً 09123456789)" required inputmode="numeric" autocomplete="tel" maxlength="11" value="<?= htmlspecialchars($_POST['mobile'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="text" name="national_code" id="nationalCodeField" class="form-control" placeholder="کد ملی" required inputmode="numeric" autocomplete="username" maxlength="10" value="<?= htmlspecialchars($_POST['national_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
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

        <input type="text" name="captcha" class="form-control" placeholder="کد امنیتی را وارد کنید" required maxlength="5" autocomplete="off" value="<?= htmlspecialchars($_POST['captcha'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <button type="submit" class="btn-custom">ثبت نام</button>
    </form>

    <div class="auth-footer">
        <span>حساب کاربری دارید؟</span>
        <a href="/login.php" class="auth-register-btn">ورود</a>
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
function registerToEnglishDigits(value){
    const persian = '۰۱۲۳۴۵۶۷۸۹';
    const arabic = '٠١٢٣٤٥٦٧٨٩';

    return String(value).replace(/[۰-۹٠-٩]/g, function(ch){
        const persianIndex = persian.indexOf(ch);

        if(persianIndex !== -1){
            return String(persianIndex);
        }

        const arabicIndex = arabic.indexOf(ch);

        return arabicIndex !== -1 ? String(arabicIndex) : ch;
    }).replace(/[^\d]/g, '');
}

function registerNormalizeMobileField(){
    const field = document.getElementById('mobileField');

    if(!field){
        return;
    }

    let digits = registerToEnglishDigits(field.value);

    if(digits.startsWith('98') && digits.length === 12){
        digits = '0' + digits.slice(2);
    }else if(digits.startsWith('9') && digits.length === 10){
        digits = '0' + digits;
    }

    field.value = digits.slice(0, 11);
}

function registerNormalizeNationalCodeField(){
    const field = document.getElementById('nationalCodeField');

    if(!field){
        return;
    }

    field.value = registerToEnglishDigits(field.value).slice(0, 10);
}

const registerForm = document.getElementById('registerForm');
const mobileField = document.getElementById('mobileField');
const nationalCodeField = document.getElementById('nationalCodeField');

if(mobileField){
    mobileField.addEventListener('input', registerNormalizeMobileField);
    mobileField.addEventListener('blur', registerNormalizeMobileField);
}

if(nationalCodeField){
    nationalCodeField.addEventListener('input', registerNormalizeNationalCodeField);
    nationalCodeField.addEventListener('blur', registerNormalizeNationalCodeField);
}

if(registerForm){
    registerForm.addEventListener('submit', function(event){
        registerNormalizeMobileField();
        registerNormalizeNationalCodeField();

        const firstname = registerForm.querySelector('[name="firstname"]');
        const lastname = registerForm.querySelector('[name="lastname"]');
        const passwordField = document.getElementById('passwordField');
        const captchaField = registerForm.querySelector('[name="captcha"]');
        const mobile = mobileField ? mobileField.value.trim() : '';
        const nationalCode = nationalCodeField ? nationalCodeField.value.trim() : '';

        if(!firstname || !firstname.value.trim()){
            event.preventDefault();
            alert('لطفاً نام را وارد کنید');
            firstname?.focus();
            return;
        }

        if(!lastname || !lastname.value.trim()){
            event.preventDefault();
            alert('لطفاً نام خانوادگی را وارد کنید');
            lastname?.focus();
            return;
        }

        if(!/^09\d{9}$/.test(mobile)){
            event.preventDefault();
            alert('شماره موبایل باید ۱۱ رقم و با 09 شروع شود');
            mobileField?.focus();
            return;
        }

        if(!/^\d{10}$/.test(nationalCode)){
            event.preventDefault();
            alert('کد ملی باید ۱۰ رقم باشد');
            nationalCodeField?.focus();
            return;
        }

        if(!passwordField || passwordField.value.length < 8){
            event.preventDefault();
            alert('رمز عبور باید حداقل ۸ کاراکتر باشد');
            passwordField?.focus();
            return;
        }

        if(!captchaField || captchaField.value.trim().length < 1){
            event.preventDefault();
            alert('کد امنیتی را وارد کنید');
            captchaField?.focus();
        }
    });
}

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

<?php include 'includes/footer.php'; ?>