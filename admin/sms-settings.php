<?php

require '../includes/admin_auth.php';
require_once '../includes/sms_helpers.php';

admin_require_super();

$message = '';
$error = '';
$settings = sms_settings_get($pdo);
$apiConfig = sms_api_config_for_form($pdo);
$events = sms_event_catalog();
$logs = sms_recent_logs($pdo, 20);

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $action = trim($_POST['action'] ?? 'save_events');

    if($action === 'save_api'){

        $saveError = sms_api_config_save($pdo, [
            'provider' => $_POST['provider'] ?? '',
            'mode' => $_POST['mode'] ?? 'shared',
            'api_token' => trim($_POST['api_token'] ?? ''),
            'api_url' => trim($_POST['api_url'] ?? ''),
            'sender' => trim($_POST['sender'] ?? ''),
            'body_id' => (int)($_POST['body_id'] ?? 0),
            'test_args' => trim($_POST['test_args'] ?? 'تست'),
            'method' => $_POST['method'] ?? 'POST',
            'timeout' => (int)($_POST['timeout'] ?? 15),
            'username' => trim($_POST['username'] ?? ''),
            'password' => trim($_POST['password'] ?? ''),
            'json' => !empty($_POST['json']),
            'verify_ssl' => !empty($_POST['verify_ssl']),
            'event_patterns' => $_POST['event_patterns'] ?? [],
        ]);

        if($saveError){
            $error = $saveError;
        }else{
            $message = 'تنظیمات اتصال API ذخیره شد';
            $settings = sms_settings_get($pdo);
            $apiConfig = sms_api_config_for_form($pdo);
        }

    }elseif($action === 'test'){

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

        $message = 'تنظیمات رویدادها ذخیره شد';
        $settings = sms_settings_get($pdo);
    }

    $logs = sms_recent_logs($pdo, 20);
}

$back_url = 'index.php';
$page_title = '📱 مدیریت پیامک';

require '../includes/header.php';

$provider = (string)($apiConfig['provider'] ?? 'melipayamak_console');
$mode = (string)($apiConfig['mode'] ?? 'shared');
$eventPatterns = sms_normalize_event_patterns($apiConfig['event_patterns'] ?? []);

?>

<style>

.page-box{max-width:980px;margin:auto;}
.card{background:#fff;border-radius:24px;padding:24px;margin-bottom:18px;box-shadow:0 10px 30px rgba(15,23,42,.05);border:1px solid #eef2f7;}
.page-title{font-size:24px;font-weight:800;color:#0f172a;margin-bottom:8px;}
.page-title-sm{font-size:20px;font-weight:800;color:#0f172a;margin-bottom:8px;}
.page-sub{color:#64748b;font-size:14px;line-height:28px;margin-bottom:20px;}
.field-label{display:block;font-size:14px;font-weight:800;color:#0f172a;margin-bottom:10px;}
.field-hint{display:block;font-size:12px;color:#64748b;line-height:24px;margin-top:6px;}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;}
.form-grid .full{grid-column:1 / -1;}
.toggle-row{display:flex;align-items:center;gap:10px;margin-bottom:18px;}
.toggle-row input{width:18px;height:18px;}
.status-box{border-radius:18px;padding:16px;font-size:13px;line-height:28px;font-weight:700;margin-bottom:18px;}
.status-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;}
.status-warn{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;}
.status-info{background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;}
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
.provider-panel{display:none;}
.provider-panel.active{display:block;}
@media (max-width:720px){.form-grid{grid-template-columns:1fr;}}

</style>

<div class="page-box">

<div class="card">

<div class="page-title">مدیریت پیامک</div>
<div class="page-sub">
اتصال API و رویدادهای اطلاع‌رسانی را از اینجا مدیریت کنید. همه رویدادها پیش‌فرض خاموش هستند.
</div>

<?php if($message): ?>
<div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

</div>

<div class="card">

<div class="page-title-sm">اتصال API</div>
<div class="page-sub">
تنظیمات سرویس‌دهنده پیامک. اگر پشتیبانی ملی‌پیامک گفت از <strong>خط خدماتی</strong> استفاده کنید، حالت <strong>shared</strong> را انتخاب کنید.
</div>

<?php if($settings['api_configured']): ?>
<div class="status-box status-ok">
اتصال API تنظیم شده است
<?php if($settings['api_source'] === 'database'): ?>
(ذخیره در پنل)
<?php elseif($settings['api_source'] === 'file'): ?>
(از فایل <code>includes/sms.local.php</code>)
<?php endif; ?>
</div>
<?php else: ?>
<div class="status-box status-warn">
اتصال API هنوز تنظیم نشده است. فرم زیر را پر کنید یا فایل <code>includes/sms.local.php</code> را بسازید.
</div>
<?php endif; ?>

<?php if($settings['local_config_exists'] && $settings['api_source'] !== 'database'): ?>
<div class="status-box status-info">
تنظیمات ذخیره‌شده در این پنل، فایل <code>sms.local.php</code> را بازنویسی می‌کند.
</div>
<?php endif; ?>

<form method="POST" id="apiForm">

<input type="hidden" name="action" value="save_api">

<div class="form-grid">

<div class="full">
<label class="field-label" for="provider">سرویس‌دهنده</label>
<select class="form-control" id="provider" name="provider">
<option value="melipayamak_console" <?= $provider === 'melipayamak_console' ? 'selected' : '' ?>>ملی‌پیامک کنسول</option>
<option value="generic" <?= $provider === 'generic' ? 'selected' : '' ?>>API سفارشی (generic)</option>
</select>
</div>

<div id="panelMelipayamak" class="provider-panel full <?= $provider === 'melipayamak_console' ? 'active' : '' ?>">

<div class="form-grid">

<div>
<label class="field-label" for="mode">نوع ارسال</label>
<select class="form-control" id="mode" name="mode">
<option value="shared" <?= $mode === 'shared' ? 'selected' : '' ?>>shared — خط خدماتی (bodyId)</option>
<option value="simple" <?= $mode === 'simple' ? 'selected' : '' ?>>simple — متن دلخواه + خط اختصاصی</option>
<option value="otp" <?= $mode === 'otp' ? 'selected' : '' ?>>otp — فقط کد یکبارمصرف</option>
</select>
<span class="field-hint">پشتیبانی ملی‌پیامک معمولاً shared را برای تیکتین پیشنهاد می‌دهد.</span>
</div>

<div id="sharedFields" class="form-grid full" style="display:none;">

<div>
<label class="field-label" for="bodyId">کد الگو (bodyId)</label>
<input
type="number"
class="form-control"
id="bodyId"
name="body_id"
min="1"
placeholder="524"
value="<?= (int)($apiConfig['body_id'] ?? 0) ?>">
<span class="field-hint">از کنسول ملی‌پیامک → خط خدماتی</span>
</div>

<div>
<label class="field-label" for="testArgs">آرگومان‌های تست</label>
<input
type="text"
class="form-control"
id="testArgs"
name="test_args"
placeholder="تست یا arg1,arg2"
value="<?= htmlspecialchars((string)($apiConfig['test_args'] ?? 'تست'), ENT_QUOTES, 'UTF-8') ?>">
<span class="field-hint">مقادیر جایگزین متغیرهای الگو در ارسال آزمایشی</span>
</div>

</div>

<div>
<label class="field-label" for="apiToken">توکن API</label>
<input
type="password"
class="form-control"
id="apiToken"
name="api_token"
placeholder="<?= $apiConfig['has_saved_token'] ? 'توکن ذخیره‌شده: ' . htmlspecialchars($apiConfig['api_token_masked'], ENT_QUOTES, 'UTF-8') : 'توکن از پنل ملی‌پیامک' ?>"
autocomplete="new-password">
<span class="field-hint">برای تغییر ندادن توکن، این فیلد را خالی بگذارید.</span>
</div>

<div id="senderField">
<label class="field-label" for="sender">شماره خط فرستنده (from)</label>
<input
type="text"
class="form-control"
id="sender"
name="sender"
placeholder="5000xxxx"
value="<?= htmlspecialchars((string)($apiConfig['sender'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
</div>

<div>
<label class="field-label" for="timeout">مهلت اتصال (ثانیه)</label>
<input
type="number"
class="form-control"
id="timeout"
name="timeout"
min="5"
max="60"
value="<?= (int)($apiConfig['timeout'] ?? 15) ?>">
</div>

</div>

</div>

<div id="panelGeneric" class="provider-panel full <?= $provider === 'generic' ? 'active' : '' ?>">

<div class="form-grid">

<div class="full">
<label class="field-label" for="apiUrl">آدرس API</label>
<input
type="url"
class="form-control"
id="apiUrl"
name="api_url"
placeholder="https://example.com/api/send"
value="<?= htmlspecialchars((string)($apiConfig['api_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
</div>

<div>
<label class="field-label" for="genericSender">شماره فرستنده</label>
<input
type="text"
class="form-control"
id="genericSender"
name="sender"
placeholder="اختیاری"
value="<?= htmlspecialchars((string)($apiConfig['sender'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
</div>

<div>
<label class="field-label" for="method">متد HTTP</label>
<select class="form-control" id="method" name="method">
<option value="POST" <?= strtoupper((string)($apiConfig['method'] ?? 'POST')) === 'POST' ? 'selected' : '' ?>>POST</option>
<option value="GET" <?= strtoupper((string)($apiConfig['method'] ?? '')) === 'GET' ? 'selected' : '' ?>>GET</option>
</select>
</div>

<div>
<label class="field-label" for="username">نام کاربری (اختیاری)</label>
<input
type="text"
class="form-control"
id="username"
name="username"
value="<?= htmlspecialchars((string)($apiConfig['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
</div>

<div>
<label class="field-label" for="password">رمز عبور (اختیاری)</label>
<input
type="password"
class="form-control"
id="password"
name="password"
placeholder="<?= !empty($apiConfig['has_saved_password']) ? 'رمز ذخیره‌شده — برای تغییر وارد کنید' : '' ?>"
autocomplete="new-password">
</div>

<div>
<label class="toggle-row" style="margin:0;">
<input type="checkbox" name="json" value="1" <?= !empty($apiConfig['json']) ? 'checked' : '' ?>>
<span>ارسال JSON</span>
</label>
</div>

<div>
<label class="toggle-row" style="margin:0;">
<input type="checkbox" name="verify_ssl" value="1" <?= !isset($apiConfig['verify_ssl']) || !empty($apiConfig['verify_ssl']) ? 'checked' : '' ?>>
<span>بررسی SSL</span>
</label>
</div>

</div>

</div>

</div>

<div id="sharedPatternBox" class="full" style="display:none;">
<div class="field-label">متغیرهای الگو برای هر رویداد (shared)</div>
<div class="page-sub" style="margin-bottom:12px;">
اگر bodyId جداگانه نگذارید، از bodyId اصلی استفاده می‌شود. متغیرها: <code>{tracking_code}</code> ، <code>{category}</code>
</div>
<div class="event-list">
<?php foreach($events as $eventKey => $eventMeta): ?>
<?php $pattern = $eventPatterns[$eventKey] ?? ['body_id' => 0, 'args' => ['{tracking_code}']]; ?>
<div class="event-item" style="display:block;">
<strong><?= htmlspecialchars($eventMeta['label'], ENT_QUOTES, 'UTF-8') ?></strong>
<div class="form-grid" style="margin-top:10px;">
<div>
<label class="field-label">bodyId (اختیاری)</label>
<input type="number" class="form-control" name="event_patterns[<?= htmlspecialchars($eventKey, ENT_QUOTES, 'UTF-8') ?>][body_id]" min="0" value="<?= (int)($pattern['body_id'] ?? 0) ?>">
</div>
<div>
<label class="field-label">args</label>
<input type="text" class="form-control" name="event_patterns[<?= htmlspecialchars($eventKey, ENT_QUOTES, 'UTF-8') ?>][args]" value="<?= htmlspecialchars(implode(',', $pattern['args'] ?? []), ENT_QUOTES, 'UTF-8') ?>">
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</div>

<div style="height:18px"></div>

<button type="submit" class="btn-custom">ذخیره اتصال API</button>

</form>

</div>

<div class="card">

<div class="page-title-sm">ارسال آزمایشی</div>
<div class="page-sub">برای تست اتصال API، بدون نیاز به فعال بودن رویدادها، یک پیامک آزمایشی بفرستید.</div>

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

<div class="page-title-sm">رویدادها و فعال‌سازی</div>

<form method="POST">

<input type="hidden" name="action" value="save_events">

<label class="toggle-row">
<input type="checkbox" name="master_enabled" value="1" <?= $settings['master_enabled'] ? 'checked' : '' ?>>
<span>فعال‌سازی کلی ارسال پیامک</span>
</label>

<?php if($settings['master_enabled'] && !$settings['api_configured']): ?>
<div class="status-box status-warn">ارسال کلی فعال است ولی API تنظیم نشده؛ پیامکی ارسال نمی‌شود.</div>
<?php endif; ?>

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

<button type="submit" class="btn-custom">ذخیره رویدادها</button>

</form>

</div>

<div class="card">

<div class="page-title-sm">صف ارسال (cron)</div>
<div class="page-sub">برای ارسال خودکار پیامک‌ها این دستور را روی سرور فعال کنید:</div>

<div class="cron-box">* * * * * php /var/www/ticketin/cron/send-sms.php</div>

</div>

<div class="card">

<div class="page-title-sm">آخرین پیامک‌ها</div>

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

<script>

(function(){

    var provider = document.getElementById('provider');
    var mode = document.getElementById('mode');
    var panelMelipayamak = document.getElementById('panelMelipayamak');
    var panelGeneric = document.getElementById('panelGeneric');
    var sharedFields = document.getElementById('sharedFields');
    var sharedPatternBox = document.getElementById('sharedPatternBox');
    var senderField = document.getElementById('senderField');

    function syncModeFields(){

        var value = mode ? mode.value : 'shared';
        var isShared = value === 'shared';
        var isSimple = value === 'simple';

        if(sharedFields){
            sharedFields.style.display = isShared ? 'grid' : 'none';
        }

        if(sharedPatternBox){
            sharedPatternBox.style.display = isShared ? 'block' : 'none';
        }

        if(senderField){
            senderField.style.display = isSimple ? 'block' : 'none';
        }

    }

    function syncPanels(){

        var value = provider.value;

        panelMelipayamak.classList.toggle('active', value === 'melipayamak_console');
        panelGeneric.classList.toggle('active', value === 'generic');
        syncModeFields();

    }

    provider.addEventListener('change', syncPanels);

    if(mode){
        mode.addEventListener('change', syncModeFields);
    }

    syncPanels();

    document.getElementById('apiForm').addEventListener('submit', function(){

        var active = provider.value === 'melipayamak_console'
            ? panelMelipayamak
            : panelGeneric;
        var inactive = active === panelMelipayamak
            ? panelGeneric
            : panelMelipayamak;

        inactive.querySelectorAll('input,select,textarea').forEach(function(el){
            el.disabled = true;
        });

    });

})();

</script>

<?php include '../includes/footer.php'; ?>
