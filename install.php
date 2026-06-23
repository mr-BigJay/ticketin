<?php

/**
 * نصب دیتابیس جدید — فقط یک‌بار اجرا کنید و بعد حذف کنید.
 * آدرس: https://your-domain/install.php
 */

header('Content-Type: text/html; charset=utf-8');

$lockFile = __DIR__ . '/.installed';
$schemaFile = __DIR__ . '/database/schema.sql';
$configFile = __DIR__ . '/includes/config.local.php';

$error = '';
$success = '';
$step = 'check';

if(file_exists($lockFile)){
    $step = 'done';
}

if($step !== 'done' && $_SERVER['REQUEST_METHOD'] === 'POST'){

    $fullname = trim($_POST['fullname'] ?? 'مدیر سیستم');
    $username = trim($_POST['username'] ?? 'superadmin');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if(!file_exists($configFile)){
        $error = 'ابتدا فایل includes/config.local.php را بسازید';
    }elseif(!file_exists($schemaFile)){
        $error = 'فایل database/schema.sql پیدا نشد';
    }elseif($msg = validate_username($username)){
        $error = $msg;
    }elseif(strlen($password) < 8){
        $error = 'رمز عبور باید حداقل ۸ کاراکتر باشد';
    }elseif($password !== $passwordConfirm){
        $error = 'تکرار رمز عبور یکسان نیست';
    }else{
        try{
            require __DIR__ . '/includes/db.php';

            $sql = file_get_contents($schemaFile);
            $statements = split_sql_statements($sql);

            foreach($statements as $statement){
                $statement = trim($statement);
                if($statement !== ''){
                    $pdo->exec($statement);
                }
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $nationalCode = '1000000001';

            $check = $pdo->prepare("SELECT id FROM users WHERE username=? OR role='admin' LIMIT 1");
            $check->execute([$username]);

            if($check->fetch()){
                $error = 'ادمین قبلاً وجود دارد. اگر دیتابیس خالی نیست، فایل .installed را بررسی کنید.';
            }else{
                $stmt = $pdo->prepare("
                    INSERT INTO users
                    (
                        fullname,
                        national_code,
                        mobile,
                        username,
                        password,
                        role,
                        admin_type,
                        status,
                        must_change_password,
                        created_at
                    )
                    VALUES
                    (?, ?, '09000000000', ?, ?, 'admin', 'super', 'active', 0, NOW())
                ");
                $stmt->execute([$fullname, $nationalCode, $username, $hash]);

                file_put_contents($lockFile, date('c') . PHP_EOL);
                $success = 'دیتابیس با موفقیت نصب شد.';
                $step = 'done';
            }
        }catch(Throwable $e){
            $error = $e->getMessage();
        }
    }
}

function validate_username(string $username): ?string
{
    if(!preg_match('/^[a-zA-Z0-9._-]{6,16}$/', $username)){
        return 'نام کاربری باید ۶ تا ۱۶ کاراکتر لاتین باشد';
    }

    return null;
}

function split_sql_statements(string $sql): array
{
    $lines = preg_split('/\R/', $sql);
    $buffer = '';
    $statements = [];

    foreach($lines as $line){
        $trimmed = trim($line);

        if($trimmed === '' || strpos($trimmed, '--') === 0){
            continue;
        }

        $buffer .= $line . "\n";

        if(substr(rtrim($line), -1) === ';'){
            $statements[] = $buffer;
            $buffer = '';
        }
    }

    if(trim($buffer) !== ''){
        $statements[] = $buffer;
    }

    return $statements;
}

$dbStatus = 'نامشخص';
$configExists = file_exists($configFile);

if($configExists && $step !== 'done'){
    try{
        require __DIR__ . '/includes/db.php';
        $pdo->query('SELECT 1');
        $dbStatus = 'اتصال برقرار است';
    }catch(Throwable $e){
        $dbStatus = 'خطا: ' . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>نصب دیتابیس ticketin</title>
<style>
body{font-family:Tahoma,sans-serif;background:#f8fafc;color:#0f172a;line-height:1.9;padding:24px;}
.box{max-width:720px;margin:auto;background:#fff;border-radius:18px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.06);}
h1{margin-top:0;font-size:24px;}
.alert{padding:14px 16px;border-radius:12px;margin-bottom:16px;}
.ok{background:#dcfce7;color:#166534;}
.bad{background:#fee2e2;color:#991b1b;}
.info{background:#eff6ff;color:#1e3a8a;}
label{display:block;font-weight:700;margin:12px 0 6px;}
input{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px;font-size:15px;box-sizing:border-box;}
button{margin-top:18px;width:100%;padding:14px;border:none;border-radius:12px;background:#2563eb;color:#fff;font-size:16px;font-weight:700;cursor:pointer;}
code{background:#f1f5f9;padding:2px 6px;border-radius:6px;}
.steps{font-size:14px;color:#475569;margin-top:20px;line-height:2;}
</style>
</head>
<body>
<div class="box">
<h1>نصب دیتابیس جدید ticketin</h1>

<?php if($step === 'done'): ?>
<div class="alert ok">
✅ <?= htmlspecialchars($success ?: 'نصب قبلاً انجام شده است.') ?><br><br>
ورود ادمین: <code>/jay_controller.php</code><br>
بعد از ورود، این فایل‌ها را حذف کنید:<br>
<code>install.php</code> و <code>setup-check.php</code>
</div>
<?php else: ?>

<div class="alert info">
وضعیت config: <?= $configExists ? '✅ config.local.php موجود' : '❌ config.local.php نیست' ?><br>
وضعیت دیتابیس: <?= htmlspecialchars($dbStatus) ?>
</div>

<?php if($error): ?><div class="alert bad"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="steps">
<strong>قبل از نصب:</strong><br>
1. در MySQL دیتابیس خالی بسازید<br>
2. فایل <code>includes/config.local.php</code> را تنظیم کنید<br>
3. فرم زیر را پر کنید تا جداول ساخته و ادمین اصلی ایجاد شود
</div>

<form method="POST">
<label>نام و نام خانوادگی ادمین اصلی</label>
<input type="text" name="fullname" value="<?= htmlspecialchars($_POST['fullname'] ?? 'مدیر سیستم') ?>" required>

<label>نام کاربری (لاتین ۶–۱۶ کاراکتر)</label>
<input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? 'superadmin') ?>" required minlength="6" maxlength="16" pattern="[a-zA-Z0-9._-]{6,16}">

<label>رمز عبور (حداقل ۸ کاراکتر)</label>
<input type="password" name="password" required minlength="8">

<label>تکرار رمز عبور</label>
<input type="password" name="password_confirm" required minlength="8">

<button type="submit">نصب دیتابیس و ساخت ادمین اصلی</button>
</form>

<?php endif; ?>
</div>
</body>
</html>
