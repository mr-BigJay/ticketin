<?php

require '../includes/admin_auth.php';
require_once '../includes/sms_helpers.php';

admin_require_super();

if(!function_exists('sms_normalize_event_patterns')){
    http_response_code(503);
    $page_title = 'مدیریت پیامک';
    require '../includes/header.php';
    echo '<div class="page-box"><div class="card"><div class="alert alert-danger">';
    echo 'فایل <code>includes/sms_helpers.php</code> روی سرور قدیمی است. ';
    echo 'آخرین نسخه را deploy کنید:<br><code>';
    echo 'curl -fsSL https://raw.githubusercontent.com/mr-BigJay/ticketin/cursor/sms-infrastructure-a1f4/includes/sms_helpers.php -o /var/www/ticketin/includes/sms_helpers.php';
    echo '</code></div></div></div>';
    require '../includes/footer.php';
    exit;
}

$message = '';
$error = '';
$settings = sms_settings_get($pdo);
$apiConfig = sms_api_config_for_form($pdo);
$events = sms_event_catalog();
$logs = sms_recent_logs($pdo, 20);
$bulkApprovalStats = sms_count_bulk_user_approved_candidates($pdo);
$smsDiagnostics = sms_queue_diagnostics($pdo);

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

    }elseif($action === 'bulk_user_approved'){

        $bulkResult = sms_queue_bulk_user_approved($pdo);

        if(!$bulkResult['ok']){
            $error = $bulkResult['error'] ?: 'ارسال گروهی ناموفق بود';
        }else{
            $message = 'برای ' . (int)$bulkResult['queued'] . ' کاربر در صف ثبت شد'
                . ' (' . (int)$bulkResult['skipped'] . ' نفر رد شد)';

            if((int)($bulkResult['sent'] ?? 0) > 0){
                $message .= ' — ' . (int)$bulkResult['sent'] . ' پیامک ارسال شد';
            }elseif(!empty($bulkResult['flush_skipped'])){
                $error = (string)($bulkResult['flush_reason'] ?: 'پیامک در صف ماند؛ فعال‌سازی کلی را روشن کنید');
            }

            $bulkApprovalStats = sms_count_bulk_user_approved_candidates($pdo);
        }

    }elseif($action === 'retry_failed'){

        $retried = sms_retry_failed_queue(
            $pdo,
            trim($_POST['retry_event'] ?? '') ?: null
        );

        if($retried < 1){
            $message = 'پیامک failed برای تلاش مجدد وجود نداشت';
        }else{
            $message = $retried . ' پیامک failed به صف pending برگشت — «ارسال صف الان» را بزنید';

            if(!empty($_POST['retry_and_send'])){
                if(!empty($_POST['enable_master']) && empty($settings['master_enabled'])){
                    sms_settings_save(
                        $pdo,
                        true,
                        $settings['event_flags'],
                        $settings['admin_notify_mobiles']
                    );
                    $settings = sms_settings_get($pdo);
                }

                $queueResult = sms_process_queue($pdo, 5, ['web' => true]);

                if((int)($queueResult['sent'] ?? 0) > 0){
                    $message .= ' — ' . (int)$queueResult['sent'] . ' پیامک ارسال شد';
                }

                if((int)($queueResult['remaining'] ?? 0) > 0){
                    $message .= ' — هنوز ' . (int)$queueResult['remaining'] . ' پیامک در صف است';
                }

                if(!empty($queueResult['skipped'])){
                    $error = (string)($queueResult['reason'] ?: 'ارسال صف انجام نشد');
                }
            }
        }

    }elseif($action === 'process_queue'){

        if(!empty($_POST['enable_master']) && empty($settings['master_enabled'])){
            sms_settings_save(
                $pdo,
                true,
                $settings['event_flags'],
                $settings['admin_notify_mobiles']
            );
            $settings = sms_settings_get($pdo);
            $smsDiagnostics = sms_queue_diagnostics($pdo);
        }

        $queueResult = sms_process_queue($pdo, 5, ['web' => true]);

        if(!empty($queueResult['skipped'])){
            $error = (string)($queueResult['reason'] ?: 'ارسال صف انجام نشد');
        }elseif((int)($queueResult['sent'] ?? 0) > 0){
            $message = (int)$queueResult['sent'] . ' پیامک ارسال شد';

            if((int)($queueResult['failed'] ?? 0) > 0){
                $message .= ' — ' . (int)$queueResult['failed'] . ' مورد ناموفق';
            }

            if((int)($queueResult['remaining'] ?? 0) > 0){
                $message .= ' — هنوز ' . (int)$queueResult['remaining'] . ' پیامک در صف است؛ دوباره «ارسال صف الان» را بزنید';
            }
        }elseif((int)($queueResult['processed'] ?? 0) < 1){
            $message = 'پیامکی در صف ارسال نبود';
        }else{
            $error = 'هیچ پیامکی ارسال نشد — لاگ آخرین پیامک‌ها را بررسی کنید';
        }

    }elseif($action === 'test'){

        $testResult = sms_send_test(
            $pdo,
            trim($_POST['test_mobile'] ?? ''),
            trim($_POST['test_args'] ?? '') ?: null
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
    $smsDiagnostics = sms_queue_diagnostics($pdo);
}

$back_url = 'index.php';
$page_title = '📱 مدیریت پیامک';

require '../includes/header.php';

$provider = (string)($apiConfig['provider'] ?? 'melipayamak_console');
$mode = (string)($apiConfig['mode'] ?? 'shared');
$eventPatterns = sms_normalize_event_patterns($apiConfig['event_patterns'] ?? []);

$smsPrimaryEvents = ['user_approved', 'ticket_reply_admin'];
$smsOtherEvents = array_values(array_diff(array_keys($events), $smsPrimaryEvents));

$smsPatternTips = [
    'user_approved' => [
        'when' => 'وقتی ادمین حساب کاربر را تایید می‌کند، این پیامک برای او ارسال می‌شود.',
        'body' => 'کد الگوی «تایید کاربر» در ملی‌پیامک. مثال: 485205',
        'args' => 'نام داخل پیامک. معمولاً {fullname} — اگر نام لاتین باشد خودکار «همکار گرامی» می‌شود.',
    ],
    'ticket_reply_admin' => [
        'when' => 'وقتی پشتیبان به تیکت پاسخ می‌دهد، این پیامک به کاربر می‌رسد.',
        'body' => 'کد الگوی «پاسخ تیکت» در ملی‌پیامک. مثال: 485236',
        'args' => 'شماره پیگیری تیکت داخل پیامک. معمولاً {tracking_code}',
    ],
];

$smsPatternDefaults = [
    'user_approved' => ['body_id' => 485205, 'args' => ['{fullname}']],
    'ticket_reply_admin' => ['body_id' => 485236, 'args' => ['{tracking_code}']],
];

function sms_render_help(string $tip): string
{
    return '<span class="sms-help" tabindex="0" role="button" aria-label="راهنما">'
        . '<span class="sms-help-icon">!</span>'
        . '<span class="sms-help-pop">' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '</span>'
        . '</span>';
}

function sms_render_label(string $text, string $helpTip = ''): string
{
    $html = '<span class="field-label-text">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';

    if($helpTip !== ''){
        $html .= sms_render_help($helpTip);
    }

    return $html;
}

function sms_render_pattern_card(
    string $eventKey,
    array $eventMeta,
    array $pattern,
    array $tips,
    bool $primary = false
): void {
    $patternArgs = $pattern['args'] ?? ['{tracking_code}'];

    if(is_string($patternArgs)){
        $patternArgs = array_values(array_filter(array_map(
            'trim',
            preg_split('/\s*,\s*/', $patternArgs) ?: []
        )));
    }

    if(!is_array($patternArgs) || $patternArgs === []){
        $patternArgs = ['{tracking_code}'];
    }

    $cardClass = $primary ? 'sms-pattern-card event-item-primary' : 'sms-pattern-card';
    ?>
<div class="<?= $cardClass ?>">
<?php if($primary): ?>
<span class="sms-primary-badge">پیشنهادی — حتماً پر کنید</span>
<?php endif; ?>
<div class="sms-pattern-title"><?= htmlspecialchars($eventMeta['label'], ENT_QUOTES, 'UTF-8') ?></div>
<div class="sms-pattern-desc"><?= htmlspecialchars($tips['when'] ?? $eventMeta['description'], ENT_QUOTES, 'UTF-8') ?></div>
<div class="form-grid">
<div>
<label class="field-label"><?= sms_render_label('کد الگو (bodyId)', $tips['body'] ?? 'عدد الگو از پنل ملی‌پیامک.') ?></label>
<input type="number" class="form-control" name="event_patterns[<?= htmlspecialchars($eventKey, ENT_QUOTES, 'UTF-8') ?>][body_id]" min="0" placeholder="مثلاً 485205" value="<?= (int)($pattern['body_id'] ?? 0) ?>">
</div>
<div>
<label class="field-label"><?= sms_render_label('متغیرهای داخل پیامک', $tips['args'] ?? 'مثلاً {tracking_code} یا {fullname} — با ویرگول جدا کنید.') ?></label>
<input type="text" class="form-control" name="event_patterns[<?= htmlspecialchars($eventKey, ENT_QUOTES, 'UTF-8') ?>][args]" placeholder="{tracking_code}" value="<?= htmlspecialchars(implode(',', $patternArgs), ENT_QUOTES, 'UTF-8') ?>">
</div>
</div>
</div>
<?php
}

?>

<style>

.page-box{max-width:920px;margin:auto;}
.card{background:#fff;border-radius:24px;padding:24px;margin-bottom:18px;box-shadow:0 10px 30px rgba(15,23,42,.05);border:1px solid #eef2f7;}
.page-title{font-size:24px;font-weight:800;color:#0f172a;margin-bottom:8px;}
.page-title-sm{font-size:18px;font-weight:800;color:#0f172a;margin:0;}
.page-sub{color:#64748b;font-size:14px;line-height:28px;margin-bottom:16px;}
.field-label{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:14px;font-weight:800;color:#0f172a;margin-bottom:10px;}
.field-label-text{line-height:1.5;}
.field-hint{display:block;font-size:12px;color:#64748b;line-height:24px;margin-top:6px;}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;}
.form-grid .full{grid-column:1 / -1;}
.toggle-row{display:flex;align-items:flex-start;gap:10px;margin-bottom:14px;}
.toggle-row input{width:18px;height:18px;margin-top:4px;flex-shrink:0;}
.status-box{border-radius:18px;padding:16px;font-size:13px;line-height:28px;font-weight:700;margin-bottom:16px;}
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
.test-row .form-control{max-width:240px;margin:0;}
.cron-box{background:#eff6ff;border:1px solid #bfdbfe;border-radius:16px;padding:14px 16px;font-size:12px;line-height:26px;color:#1e3a8a;direction:ltr;text-align:left;}
.provider-panel{display:none;}
.provider-panel.active{display:block;}
.sms-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:18px;}
.sms-step{background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:12px;font-size:12px;line-height:24px;color:#475569;}
.sms-step strong{display:block;color:#0f172a;font-size:13px;margin-bottom:4px;}
.sms-section-head{display:flex;align-items:center;gap:12px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #eef2f7;}
.sms-section-num{width:34px;height:34px;border-radius:12px;background:linear-gradient(135deg,#0284c7,#06b6d4);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;flex-shrink:0;}
.sms-section-body{margin-top:4px;}
.sms-pattern-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;padding:16px;margin-bottom:12px;}
.sms-pattern-title{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:15px;font-weight:800;color:#0f172a;margin-bottom:4px;}
.sms-pattern-desc{font-size:12px;color:#64748b;line-height:24px;margin-bottom:12px;}
.sms-details{border:1px solid #e2e8f0;border-radius:16px;padding:12px 14px;background:#fafbfc;}
.sms-details{margin-top:14px;}
.sms-details summary{cursor:pointer;font-weight:800;color:#334155;list-style:none;padding:4px 0;}
.sms-details summary::-webkit-details-marker{display:none;}
.sms-details-body{margin-top:12px;display:flex;flex-direction:column;gap:12px;}
.sms-primary-badge{display:inline-block;font-size:11px;font-weight:800;color:#0369a1;background:#e0f2fe;border-radius:999px;padding:3px 10px;margin-bottom:8px;}
.event-item-primary{border-color:#7dd3fc;background:#f0f9ff;}
.sms-help{position:relative;display:inline-flex;align-items:center;justify-content:center;cursor:help;}
.sms-help-icon{width:22px;height:22px;border-radius:999px;background:#0f172a;color:#fff;font-size:13px;font-weight:800;line-height:22px;text-align:center;flex-shrink:0;}
.sms-help-pop{
    position:absolute;left:0;top:calc(100% + 8px);z-index:20;
    width:min(280px,calc(100vw - 40px));background:#0f172a;color:#fff;
    font-size:12px;font-weight:600;line-height:24px;padding:12px 14px;border-radius:14px;
    box-shadow:0 12px 30px rgba(15,23,42,.25);opacity:0;visibility:hidden;pointer-events:none;
    transition:opacity .15s ease,visibility .15s ease;
}
.sms-help:hover .sms-help-pop,
.sms-help:focus .sms-help-pop,
.sms-help:focus-within .sms-help-pop{opacity:1;visibility:visible;}
.btn-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;}
@media (max-width:720px){
    .form-grid{grid-template-columns:1fr;}
    .sms-steps{grid-template-columns:1fr 1fr;}
}

</style>

<div class="page-box">

<div class="card">

<div class="page-title">مدیریت پیامک</div>
<div class="page-sub">
در چهار مرحله ساده پیامک تیکتین را وصل کنید. کنار فیلدهای مهم علامت <strong>!</strong> را بزنید تا توضیح ببینید.
</div>

<div class="sms-steps">
<div class="sms-step"><strong>۱. اتصال</strong>توکن و نوع ارسال را ذخیره کنید.</div>
<div class="sms-step"><strong>۲. الگوها</strong>bodyId تایید کاربر و پاسخ تیکت را وارد کنید.</div>
<div class="sms-step"><strong>۳. روشن کردن</strong>فعال‌سازی کلی و رویدادها را بزنید.</div>
<div class="sms-step"><strong>۴. تست و ارسال</strong>پیامک آزمایشی بفرستید و صف را خالی کنید.</div>
</div>

<?php if($message): ?>
<div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if(!empty($smsDiagnostics['issues'])): ?>
<div class="status-box status-warn">
<strong>توجه — ارسال پیامک ممکن است انجام نشود:</strong>
<ul style="margin:10px 0 0;padding-right:18px;font-weight:600;">
<?php foreach($smsDiagnostics['issues'] as $issue): ?>
<li><?= htmlspecialchars($issue, ENT_QUOTES, 'UTF-8') ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php elseif($smsDiagnostics['is_ready'] ?? false): ?>
<div class="status-box status-ok">
ارسال پیامک فعال است. در صف: <?= (int)($smsDiagnostics['pending'] ?? 0) ?> — ناموفق: <?= (int)($smsDiagnostics['failed'] ?? 0) ?>
</div>
<?php endif; ?>

</div>

<div class="card">

<div class="sms-section-head">
<div class="sms-section-num">۱</div>
<div>
<div class="page-title-sm">اتصال به ملی‌پیامک</div>
<div class="page-sub" style="margin:0;">اول توکن API را وارد کنید و نوع ارسال را روی «خط خدماتی» بگذارید.</div>
</div>
</div>

<div class="sms-section-body">

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
<label class="field-label" for="provider"><?= sms_render_label('شرکت پیامک', 'معمولاً همان ملی‌پیامک کنسول را انتخاب کنید.') ?></label>
<select class="form-control" id="provider" name="provider">
<option value="melipayamak_console" <?= $provider === 'melipayamak_console' ? 'selected' : '' ?>>ملی‌پیامک کنسول</option>
<option value="generic" <?= $provider === 'generic' ? 'selected' : '' ?>>سرویس دیگر (پیشرفته)</option>
</select>
</div>

<div id="panelMelipayamak" class="provider-panel full <?= $provider === 'melipayamak_console' ? 'active' : '' ?>">

<div class="form-grid">

<div>
<label class="field-label" for="mode"><?= sms_render_label('نوع ارسال', 'برای تیکتین معمولاً «خط خدماتی» درست است. simple برای متن آزاد است. otp فقط برای کد ورود است و اینجا لازم نیست.') ?></label>
<select class="form-control" id="mode" name="mode">
<option value="shared" <?= $mode === 'shared' ? 'selected' : '' ?>>خط خدماتی — الگوی آماده (پیشنهادی)</option>
<option value="simple" <?= $mode === 'simple' ? 'selected' : '' ?>>خط اختصاصی — متن دلخواه</option>
<option value="otp" <?= $mode === 'otp' ? 'selected' : '' ?>>فقط کد یکبارمصرف (ورود)</option>
</select>
</div>

<div>
<label class="field-label" for="apiToken"><?= sms_render_label('توکن API', 'کلید ۳۲ کاراکتری از پنل ملی‌پیامک → وب‌سرویس. اگر قبلاً ذخیره شده، برای تغییر ندادن خالی بگذارید.') ?></label>
<input
type="password"
class="form-control"
id="apiToken"
name="api_token"
placeholder="<?= $apiConfig['has_saved_token'] ? 'توکن ذخیره‌شده: ' . htmlspecialchars($apiConfig['api_token_masked'], ENT_QUOTES, 'UTF-8') : 'توکن از پنل ملی‌پیامک' ?>"
autocomplete="new-password">
</div>

<div id="senderField">
<label class="field-label" for="sender"><?= sms_render_label('شماره خط فرستنده', 'فقط وقتی نوع ارسال «خط اختصاصی» است لازم است. مثل 50004001482880') ?></label>
<input
type="text"
class="form-control"
id="sender"
name="sender"
placeholder="5000xxxx"
value="<?= htmlspecialchars((string)($apiConfig['sender'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
</div>

<div>
<label class="field-label" for="timeout"><?= sms_render_label('زمان انتظار اتصال', 'اگر اینترنت کند است کمی بیشتر کنید. معمولاً ۱۵ ثانیه کافی است.') ?></label>
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

<div class="sms-section-head" style="margin-top:20px;border-top:1px solid #eef2f7;padding-top:20px;">
<div class="sms-section-num">۲</div>
<div>
<div class="page-title-sm">کد الگو برای هر نوع پیامک</div>
<div class="page-sub" style="margin:0;">کد الگو را فقط همین‌جا وارد کنید — برای هر نوع پیامک جداگانه. جای دیگری لازم نیست.</div>
</div>
</div>

<?php foreach($smsPrimaryEvents as $eventKey): ?>
<?php
$eventMeta = $events[$eventKey] ?? ['label' => $eventKey, 'description' => ''];
$pattern = $eventPatterns[$eventKey] ?? ($smsPatternDefaults[$eventKey] ?? ['body_id' => 0, 'args' => ['{tracking_code}']]);
$tips = $smsPatternTips[$eventKey] ?? [];
sms_render_pattern_card($eventKey, $eventMeta, $pattern, $tips, true);
?>
<?php endforeach; ?>

<details class="sms-details">
<summary>سایر انواع پیامک (اختیاری) — <?= count($smsOtherEvents) ?> مورد</summary>
<div class="sms-details-body">
<div class="page-sub" style="margin:0 0 4px;">
اگر فعلاً فقط تایید کاربر و پاسخ تیکت را می‌خواهید، این بخش را خالی بگذارید.
</div>
<?php foreach($smsOtherEvents as $eventKey): ?>
<?php
$eventMeta = $events[$eventKey] ?? ['label' => $eventKey, 'description' => ''];
$pattern = $eventPatterns[$eventKey] ?? ['body_id' => 0, 'args' => ['{tracking_code}']];
$tips = [
    'when' => $eventMeta['description'],
    'body' => 'کد الگوی این رویداد در ملی‌پیامک. اگر خالی باشد از کد پیش‌فرض بالا استفاده می‌شود.',
    'args' => 'متغیرهای الگو — مثلاً {tracking_code} ، {category} ، {title}',
];
sms_render_pattern_card($eventKey, $eventMeta, $pattern, $tips, false);
?>
<?php endforeach; ?>
</div>
</details>

</div>

<div class="btn-row">
<button type="submit" class="btn-custom">ذخیره اتصال و الگوها</button>
</div>

</form>

</div>

</div>

<div class="card">

<div class="sms-section-head">
<div class="sms-section-num">۳</div>
<div>
<div class="page-title-sm">روشن کردن ارسال پیامک</div>
<div class="page-sub" style="margin:0;">اول کل سیستم را روشن کنید، بعد مشخص کنید برای چه اتفاقی پیامک برود.</div>
</div>
</div>

<div class="sms-section-body">

<form method="POST">

<input type="hidden" name="action" value="save_events">

<label class="toggle-row event-item-primary" style="padding:14px 16px;border-radius:16px;">
<input type="checkbox" name="master_enabled" value="1" <?= $settings['master_enabled'] ? 'checked' : '' ?>>
<div>
<strong style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
<?= sms_render_label('فعال‌سازی کلی ارسال پیامک', 'تا این تیک نخورَد، هیچ پیامکی — حتی تایید کاربر — ارسال نمی‌شود.') ?>
</strong>
<span class="field-hint" style="margin-top:4px;">کلید اصلی روشن/خاموش کردن پیامک در تیکتین</span>
</div>
</label>

<?php if(!$settings['master_enabled']): ?>
<div class="status-box status-warn">ارسال کلی خاموش است — هیچ پیامکی ارسال نمی‌شود تا این گزینه را فعال کنید.</div>
<?php endif; ?>

<?php if($settings['master_enabled'] && !$settings['api_configured']): ?>
<div class="status-box status-warn">ارسال کلی روشن است ولی اتصال ملی‌پیامک تنظیم نشده؛ ابتدا مرحله ۱ را کامل کنید.</div>
<?php endif; ?>

<label class="field-label" for="adminNotifyMobiles">
<?= sms_render_label('شماره موبایل پشتیبان‌ها', 'وقتی تیکت جدید ثبت شود یا کاربر پاسخ بدهد، پیامک به این شماره‌ها می‌رود. با ویرگول جدا کنید.') ?>
</label>

<input
type="text"
class="form-control"
id="adminNotifyMobiles"
name="admin_notify_mobiles"
placeholder="0912xxxxxxx,0913xxxxxxx"
value="<?= htmlspecialchars($settings['admin_notify_mobiles'], ENT_QUOTES, 'UTF-8') ?>">

<div style="height:22px"></div>

<div class="field-label"><?= sms_render_label('برای چه اتفاقی پیامک برود؟', 'هر کدام را که لازم دارید تیک بزنید. حداقل «تایید کاربر» را روشن کنید.') ?></div>

<?php if(empty($settings['event_flags']['user_approved'])): ?>
<div class="status-box status-warn" style="margin-bottom:12px;">«تایید کاربر» خاموش است — بعد از تایید حساب، پیامکی ارسال نمی‌شود.</div>
<?php endif; ?>

<div class="event-list">

<?php foreach($smsPrimaryEvents as $eventKey): ?>
<?php $eventMeta = $events[$eventKey]; ?>
<label class="event-item event-item-primary">
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

<details class="sms-details">
<summary>سایر رویدادها — <?= count($smsOtherEvents) ?> مورد</summary>
<div class="sms-details-body">
<?php foreach($smsOtherEvents as $eventKey): ?>
<?php $eventMeta = $events[$eventKey]; ?>
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
</details>

</div>

<div class="btn-row">
<button type="submit" class="btn-custom">ذخیره رویدادها</button>
</div>

</form>

</div>

</div>

<div class="card">

<div class="sms-section-head">
<div class="sms-section-num">۴</div>
<div>
<div class="page-title-sm">تست، ارسال صف و کاربران قبلی</div>
<div class="page-sub" style="margin:0;">اول یک پیامک آزمایشی بفرستید. اگر درست رسید، صف را خالی کنید.</div>
</div>
</div>

<div class="sms-section-body">

<div class="sms-pattern-card">
<div class="sms-pattern-title"><?= sms_render_label('ارسال آزمایشی', 'یک پیامک تست به موبایل خودتان می‌فرستد. از همان کد الگوی «تایید کاربر» در مرحله ۲ استفاده می‌کند.') ?></div>
<form method="POST" class="test-row" style="margin-top:12px;flex-wrap:wrap;">
<input type="hidden" name="action" value="test">
<input type="text" name="test_mobile" class="form-control" placeholder="09xxxxxxxxx" required>
<input type="text" name="test_args" class="form-control" placeholder="متن داخل پیامک تست (مثلاً: علی)" value="<?= htmlspecialchars((string)($apiConfig['test_args'] ?? 'تست'), ENT_QUOTES, 'UTF-8') ?>">
<button type="submit" class="btn-custom">ارسال تست</button>
</form>
<span class="field-hint">اگر مرحله ۲ را پر کرده‌اید، همین کافی است — نیازی به فیلد جداگانهٔ «پیش‌فرض» نیست.</span>
</div>

<div class="sms-pattern-card">
<div class="sms-pattern-title"><?= sms_render_label('کاربران تاییدشده قبلی', 'کاربرانی که قبل از فعال شدن پیامک تایید شده‌اند، خودکار پیامک نگرفته‌اند. یک‌بار این دکمه را بزنید.') ?></div>
<div class="status-box status-info" style="margin:12px 0;">
واجد شرایط: <?= (int)$bulkApprovalStats['eligible'] ?> نفر
— قبلاً پیامک گرفته‌اند: <?= (int)$bulkApprovalStats['already_notified'] ?>
— بدون موبایل: <?= (int)$bulkApprovalStats['missing_mobile'] ?>
</div>
<form method="POST" onsubmit="return confirm('پیامک تایید برای کاربران واجد شرایط در صف قرار بگیرد؟');">
<input type="hidden" name="action" value="bulk_user_approved">
<button type="submit" class="btn-custom" <?= (int)$bulkApprovalStats['eligible'] < 1 ? 'disabled' : '' ?>>
ارسال یک‌باره پیامک تایید به کاربران قبلی
</button>
</form>
</div>

<div class="sms-pattern-card">
<div class="sms-pattern-title"><?= sms_render_label('صف ارسال', 'پیامک‌های در انتظار اینجا جمع می‌شوند. هر بار حداکثر ۵ پیامک ارسال می‌شود تا صفحه گیر نکند.') ?></div>

<div class="status-box status-info" style="margin:12px 0;">
در صف: <?= (int)($smsDiagnostics['pending'] ?? 0) ?>
— ناموفق: <?= (int)($smsDiagnostics['failed'] ?? 0) ?>
— نوع ارسال: <?= htmlspecialchars($mode === 'shared' ? 'خط خدماتی' : $mode, ENT_QUOTES, 'UTF-8') ?>
</div>

<?php if(!$settings['master_enabled']): ?>
<div class="status-box status-warn">
برای ارسال صف، «فعال‌سازی کلی» باید روشن باشد. می‌توانید هنگام ارسال خودکار روشن شود.
</div>
<?php endif; ?>

<form method="POST" style="margin-top:10px;">
<input type="hidden" name="action" value="process_queue">
<?php if(!$settings['master_enabled']): ?>
<label class="toggle-row" style="margin-bottom:12px;">
<input type="checkbox" name="enable_master" value="1" checked>
<span>فعال‌سازی کلی و سپس ارسال صف</span>
</label>
<?php endif; ?>
<button type="submit" class="btn-custom" <?= (int)($smsDiagnostics['pending'] ?? 0) < 1 ? 'disabled' : '' ?>>
ارسال صف الان (<?= (int)($smsDiagnostics['pending'] ?? 0) ?>)
</button>
</form>

<?php if((int)($smsDiagnostics['failed'] ?? 0) > 0): ?>
<form method="POST" style="margin-top:10px;" onsubmit="return confirm('پیامک‌های ناموفق دوباره ارسال شوند؟');">
<input type="hidden" name="action" value="retry_failed">
<input type="hidden" name="retry_event" value="user_approved">
<input type="hidden" name="retry_and_send" value="1">
<?php if(!$settings['master_enabled']): ?>
<input type="hidden" name="enable_master" value="1">
<?php endif; ?>
<button type="submit" class="btn-custom" style="background:#f59e0b;">
تلاش مجدد ناموفق‌ها (<?= (int)($smsDiagnostics['failed'] ?? 0) ?>) و ارسال
</button>
</form>
<?php endif; ?>

<details class="sms-details" style="margin-top:14px;">
<summary>راه‌اندازی ارسال خودکار روی سرور (cron)</summary>
<div class="cron-box" style="margin-top:10px;">* * * * * php /var/www/ticketin/cron/send-sms.php >> /var/log/ticketin-sms.log 2>&1</div>
<span class="field-hint">اگر این دستور روی سرور فعال باشد، نیازی به زدن «ارسال صف الان» نیست.</span>
</details>

</div>

</div>

</div>

<div class="card">

<div class="sms-section-head">
<div class="sms-section-num" style="background:linear-gradient(135deg,#64748b,#94a3b8);">📋</div>
<div>
<div class="page-title-sm">آخرین پیامک‌ها</div>
<div class="page-sub" style="margin:0;">اگر وضعیت «failed» است، معمولاً کد الگو اشتباه است یا نام کاربر لاتین/خالی بوده.</div>
</div>
</div>

<?php if($logs): ?>

<table class="log-table">

<thead>
<tr>
<th>زمان</th>
<th>رویداد</th>
<th>موبایل</th>
<th>وضعیت</th>
<th>توضیح خطا</th>
</tr>
</thead>

<tbody>

<?php foreach($logs as $log): ?>
<?php
$logEventKey = (string)$log['event_key'];
$logEventLabel = $events[$logEventKey]['label'] ?? $logEventKey;
$logStatus = (string)$log['status'];
$logStatusFa = ['sent' => 'ارسال شد', 'failed' => 'ناموفق', 'pending' => 'در صف'][$logStatus] ?? $logStatus;
?>

<tr>
<td><?= htmlspecialchars((string)$log['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars($logEventLabel, ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars(sms_mask_mobile((string)$log['mobile']), ENT_QUOTES, 'UTF-8') ?></td>
<td>
<span class="log-badge log-<?= htmlspecialchars($logStatus, ENT_QUOTES, 'UTF-8') ?>">
<?= htmlspecialchars($logStatusFa, ENT_QUOTES, 'UTF-8') ?>
</span>
</td>
<td><?= htmlspecialchars((string)($log['last_error'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
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
    var sharedPatternBox = document.getElementById('sharedPatternBox');
    var senderField = document.getElementById('senderField');

    function syncModeFields(){

        var value = mode ? mode.value : 'shared';
        var isShared = value === 'shared';
        var isSimple = value === 'simple';

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
