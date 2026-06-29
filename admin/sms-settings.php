<?php

require '../includes/admin_auth.php';
require_once '../includes/sms_helpers.php';

admin_require_super();

$message = '';
$error = '';
$settings = sms_settings_get($pdo);
$events = sms_event_catalog();
$logs = sms_recent_logs($pdo, 20);

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $action = trim($_POST['action'] ?? 'save');

    if($action === 'test'){

        $testResult = sms_send_test(
            $pdo,
            trim($_POST['test_mobile'] ?? '')
        );

        if($testResult['ok']){
            $message = 'پیامک آزمایشی ارسال شد';
        }else{
            $error = $testResult['error'] ?: 'ارسال پیامک آزمایشی ناموفق بود';
        }

    }else{

        $eventFlags = $_POST['events'] ?? [];

        if(!is_array($eventFlags)){
            $eventFlags = [];
        }

        sms_settings_save(
            $pdo,
            !empty($_POST['master_enabled']),
            $eventFlags,
            trim($_POST['admin_notify_mobiles'] ?? '')
        );

        $message = 'تنظیمات پیامک ذخیره شد';
        $settings = sms_settings_get($pdo);
    }

    $logs = sms_recent_logs($pdo, 20);
}

$back_url = 'index.php';
$page_title = '📱 مدیریت پیامک';

require '../includes/header.php';

?>

<style>

.page-box{max-width:980px;margin:auto;}
.card{background:#fff;border-radius:24px;padding:24px;margin-bottom:18px;box-shadow:0 10px 30px rgba(15,23,42,.05);border:1px solid #eef2f7;}
.page-title{font-size:24px;font-weight:800;color:#0f172a;margin-bottom:8px;}
.page-sub{color:#64748b;font-size:14px;line-height:28px;margin-bottom:20px;}
.field-label{display:block;font-size:14px;font-weight:800;color:#0f172a;margin-bottom:10px;}
.toggle-row{display:flex;align-items:center;gap:10px;margin-bottom:18px;}
.toggle-row input{width:18px;height:18px;}
.status-box{border-radius:18px;padding:16px;font-size:13px;line-height:28px;font-weight:700;margin-bottom:18px;}
.status-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;}
.status-warn{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;}
.event-list{display:flex;flex-direction:column;gap:12px;}
.event-item{display:flex;gap:12px;align-items:flex-start;background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px 16px;}
.event-item input{margin-top:4px;width:18px;height:18px;flex-shrink:0;}
.event-item strong{display:block;font-size:14px;color:#0f172a;margin-bottom:4px;}
.event-item span{display:block;font-size:12px;color:#64748b;line-height:24px;}
.log-table{width:100%;border-collapse:collapse;font-size:12px;}
.log-table th,.log-table td{padding:10px 8px;border-bottom:1px solid #eef2f7;text-align:right;vertical-align:top;}
.log-badge{display:inline-block;padding:4px 10px;border-radius:999px;font-weight:800;}
.log-sent{background:#ecfdf5;color:#047857;}
.log-failed{background:#fef2f2;color:#b91c1c;}
.log-pending{background:#fffbeb;color:#b45309;}
.test-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.test-row .form-control{max-width:220px;margin:0;}
.cron-box{background:#eff6ff;border:1px solid #bfdbfe;border-radius:16px;padding:14px 16px;font-size:12px;line-height:26px;color:#1e3a8a;direction:ltr;text-align:left;}

</style>

<div class="page-box">

<div class="card">

<div class="page-title">مدیریت پیامک</div>
<div class="page-sub">
رویدادهای اطلاع‌رسانی را از اینجا فعال کنید. همه گزینه‌ها پیش‌فرض خاموش هستند تا مرحله‌به‌مرحله فعال شوند.
</div>

<?php if($message): ?>
<div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if($settings['api_configured'] && $settings['local_config_exists']): ?>
<div class="status-box status-ok">
فایل <code>includes/sms.local.php</code> پیدا شد و آدرس API تنظیم شده است.
</div>
<?php else: ?>
<div class="status-box status-warn">
ابتدا فایل <code>includes/sms.local.php</code> را از روی <code>sms.local.php.example</code> بسازید.<br>
برای اطلاع‌رسانی تیکت از بخش <strong>ارسال ساده (simple)</strong> کنسول ملی‌پیامک استفاده کنید، نه OTP.<br>
OTP فقط برای کد یکبارمصرف است و متن دلخواه تیکت را نمی‌فرستد.
</div>
<?php endif; ?>

<form method="POST">

<input type="hidden" name="action" value="save">

<label class="toggle-row">
<input type="checkbox" name="master_enabled" value="1" <?= $settings['master_enabled'] ? 'checked' : '' ?>>
<span>فعال‌سازی کلی ارسال پیامک</span>
</label>

<label class="field-label" for="adminNotifyMobiles">
شماره‌های پشتیبان (برای رویدادهای اطلاع‌رسانی ادمین)
</label>

<input
type="text"
class="form-control"
id="adminNotifyMobiles"
name="admin_notify_mobiles"
placeholder="0912xxxxxxx,0913xxxxxxx"
value="<?= htmlspecialchars($settings['admin_notify_mobiles'], ENT_QUOTES, 'UTF-8') ?>">

<div style="height:22px"></div>

<div class="field-label">رویدادهای قابل فعال‌سازی</div>

<div class="event-list">

<?php foreach($events as $eventKey => $eventMeta): ?>

<label class="event-item">
<input
type="checkbox"
name="events[<?= htmlspecialchars($eventKey, ENT_QUOTES, 'UTF-8') ?>]"
value="1"
<?= !empty($settings['event_flags'][$eventKey]) ? 'checked' : '' ?>>
<div>
<strong><?= htmlspecialchars($eventMeta['label'], ENT_QUOTES, 'UTF-8') ?></strong>
<span><?= htmlspecialchars($eventMeta['description'], ENT_QUOTES, 'UTF-8') ?></span>
</div>
</label>

<?php endforeach; ?>

</div>

<div style="height:22px"></div>

<button type="submit" class="btn-custom">ذخیره تنظیمات</button>

</form>

</div>

<div class="card">

<div class="page-title" style="font-size:20px;">ارسال آزمایشی</div>

<form method="POST" class="test-row">

<input type="hidden" name="action" value="test">

<input
type="text"
name="test_mobile"
class="form-control"
placeholder="09xxxxxxxxx"
required>

<button type="submit" class="btn-custom">ارسال تست</button>

</form>

</div>

<div class="card">

<div class="page-title" style="font-size:20px;">صف ارسال (cron)</div>
<div class="page-sub">برای ارسال خودکار پیامک‌ها این دستور را روی سرور فعال کنید:</div>

<div class="cron-box">* * * * * php /var/www/ticketin/cron/send-sms.php</div>

</div>

<div class="card">

<div class="page-title" style="font-size:20px;">آخرین پیامک‌ها</div>

<?php if($logs): ?>

<table class="log-table">

<thead>
<tr>
<th>زمان</th>
<th>رویداد</th>
<th>موبایل</th>
<th>وضعیت</th>
</tr>
</thead>

<tbody>

<?php foreach($logs as $log): ?>

<tr>
<td><?= htmlspecialchars((string)$log['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars((string)$log['event_key'], ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars(sms_mask_mobile((string)$log['mobile']), ENT_QUOTES, 'UTF-8') ?></td>
<td>
<span class="log-badge log-<?= htmlspecialchars((string)$log['status'], ENT_QUOTES, 'UTF-8') ?>">
<?= htmlspecialchars((string)$log['status'], ENT_QUOTES, 'UTF-8') ?>
</span>
</td>
</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php else: ?>

<div class="page-sub">هنوز پیامکی ثبت نشده است.</div>

<?php endif; ?>

</div>

</div>

<?php include '../includes/footer.php'; ?>
