<?php

/**
 * صفحه تشخیص مشکل نصب — بعد از رفع مشکل حذف کنید.
 * آدرس: https://your-domain/setup-check.php
 */

header('Content-Type: text/html; charset=utf-8');

$checks = [];
$hasError = false;

function add_check(string $title, bool $ok, string $detail = ''): void
{
    global $checks, $hasError;

    if(!$ok){
        $hasError = true;
    }

    $checks[] = [
        'title' => $title,
        'ok' => $ok,
        'detail' => $detail,
    ];
}

add_check(
    'نسخه PHP',
    version_compare(PHP_VERSION, '7.4.0', '>='),
    'نسخه فعلی: ' . PHP_VERSION . ' (حداقل 7.4 لازم است)'
);

add_check(
    'افزونه PDO MySQL',
    extension_loaded('pdo_mysql'),
    extension_loaded('pdo_mysql') ? 'فعال است' : 'pdo_mysql نصب نیست'
);

add_check(
    'مسیر نصب',
    is_dir(__DIR__),
    __DIR__
);

$configFile = __DIR__ . '/includes/config.local.php';
$configExample = __DIR__ . '/includes/config.local.php.example';

if(file_exists($configFile)){
    add_check('فایل تنظیمات دیتابیس', true, 'includes/config.local.php پیدا شد');
}else{
    add_check(
        'فایل تنظیمات دیتابیس',
        false,
        'فایل includes/config.local.php وجود ندارد. از روی config.local.php.example بسازید و رمز دیتابیس را وارد کنید.'
    );
}

$dbOk = false;
$dbMessage = '';

if(file_exists(__DIR__ . '/includes/db.php')){
    try{
        require __DIR__ . '/includes/db.php';
        $pdo->query('SELECT 1');
        $dbOk = true;
        $dbMessage = 'اتصال به دیتابیس برقرار است';
    }catch(Throwable $e){
        $dbMessage = $e->getMessage();
    }
}

add_check('اتصال دیتابیس', $dbOk, $dbMessage);

$requiredTables = [
    'users',
    'tickets',
    'ticket_replies',
    'announcements',
    'reminders',
    'organization_nodes',
    'job_titles',
];

if($dbOk){
    foreach($requiredTables as $table){
        try{
            $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
            add_check("جدول {$table}", true, 'موجود است');
        }catch(Throwable $e){
            add_check("جدول {$table}", false, 'جدول پیدا نشد یا دسترسی ندارید');
        }
    }

    try{
        require __DIR__ . '/includes/admin_helpers.php';
        admin_ensure_schema($pdo);
        add_check('به‌روزرسانی ساختار دیتابیس', true, 'ستون‌های ادمین بررسی/اضافه شد');
    }catch(Throwable $e){
        add_check('به‌روزرسانی ساختار دیتابیس', false, $e->getMessage());
    }
}

require_once __DIR__ . '/includes/paths.php';
app_ensure_upload_dirs();

$uploadsDir = app_uploads_dir();
$ticketsDir = app_uploads_tickets_dir();

add_check(
    'پوشه uploads',
    is_dir($uploadsDir) && is_writable($uploadsDir),
    $uploadsDir . (is_writable($uploadsDir) ? ' — قابل نوشتن' : ' — قابل نوشتن نیست (chmod 755 یا 775)')
);

add_check(
    'پوشه uploads/tickets',
    is_dir($ticketsDir) && is_writable($ticketsDir),
    $ticketsDir
);

$sessionPath = session_save_path() ?: sys_get_temp_dir();
add_check(
    'مسیر session',
    is_writable($sessionPath),
    $sessionPath
);

$importantFiles = [
    'index.php',
    'login.php',
    'jay_controller.php',
    'includes/auth.php',
    'includes/db.php',
    'includes/admin_auth.php',
    'admin/index.php',
];

foreach($importantFiles as $file){
    add_check(
        "فایل {$file}",
        file_exists(__DIR__ . '/' . $file),
        file_exists(__DIR__ . '/' . $file) ? 'موجود' : 'فایل گم شده'
    );
}

$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$expectedRoot = realpath(__DIR__);

add_check(
    'DocumentRoot سرور',
    $docRoot && realpath($docRoot) === $expectedRoot,
    "DocumentRoot فعلی: {$docRoot}\nمسیر پروژه: {$expectedRoot}\nاگر متفاوت است، در Apache/Nginx مسیر را /var/www/ticketin تنظیم کنید."
);

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>بررسی نصب ticketin</title>
<style>
body{font-family:Tahoma,sans-serif;background:#f8fafc;color:#0f172a;line-height:1.8;padding:24px;}
.box{max-width:900px;margin:auto;background:#fff;border-radius:18px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,.06);}
h1{margin-top:0;}
.item{border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;margin-bottom:12px;}
.ok{border-color:#86efac;background:#f0fdf4;}
.bad{border-color:#fecaca;background:#fef2f2;}
.title{font-weight:800;margin-bottom:6px;}
.detail{white-space:pre-wrap;font-size:13px;color:#475569;}
.summary{padding:14px 16px;border-radius:14px;margin-bottom:18px;font-weight:700;}
.summary.ok{background:#dcfce7;color:#166534;}
.summary.bad{background:#fee2e2;color:#991b1b;}
code{background:#f1f5f9;padding:2px 6px;border-radius:6px;}
.steps{background:#eff6ff;border:1px solid #bfdbfe;border-radius:14px;padding:16px;margin-top:20px;}
</style>
</head>
<body>
<div class="box">
<h1>بررسی نصب سامانه ticketin</h1>

<div class="summary <?= $hasError ? 'bad' : 'ok' ?>">
<?= $hasError ? 'مشکلی پیدا شد — موارد قرمز را بررسی کنید.' : 'همه بررسی‌ها موفق بود.' ?>
</div>

<?php foreach($checks as $check): ?>
<div class="item <?= $check['ok'] ? 'ok' : 'bad' ?>">
<div class="title"><?= $check['ok'] ? '✅' : '❌' ?> <?= htmlspecialchars($check['title']) ?></div>
<?php if($check['detail']): ?>
<div class="detail"><?= htmlspecialchars($check['detail']) ?></div>
<?php endif; ?>
</div>
<?php endforeach; ?>

<div class="steps">
<strong>مراحل رایج بعد از جایگزینی پوشه:</strong><br><br>
1. فایل <code>includes/config.local.php</code> را از روی <code>config.local.php.example</code> بسازید و اطلاعات دیتابیس قبلی را وارد کنید.<br>
2. دسترسی پوشه‌ها: <code>chown -R www-data:www-data /var/www/ticketin</code><br>
3. پوشه آپلود: <code>chmod -R 775 /var/www/ticketin/uploads</code><br>
4. DocumentRoot باید دقیقاً <code>/var/www/ticketin</code> باشد.<br>
5. ورود ادمین: <code>/jay_controller.php</code> — نام کاربری ادمین قدیمی معمولاً <code>admin001</code> است.<br>
6. بعد از رفع مشکل، این فایل را حذف کنید: <code>setup-check.php</code>
</div>
</div>
</body>
</html>
