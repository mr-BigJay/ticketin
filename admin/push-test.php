<?php

require '../includes/admin_auth.php';

admin_require_super();

$status = [
    'vapid' => push_get_vapid_public_key() !== '',
    'openssl' => extension_loaded('openssl'),
    'curl' => extension_loaded('curl'),
    'gd' => extension_loaded('gd'),
];

$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM admin_push_subscriptions s
    INNER JOIN users u ON u.id = s.user_id
    WHERE u.role = 'admin'
");
$subscriptionCount = (int)$stmt->fetchColumn();

$myCountStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_push_subscriptions WHERE user_id = ?");
$myCountStmt->execute([(int)$_SESSION['user_id']]);
$mySubscriptionCount = (int)$myCountStmt->fetchColumn();

$myStmt = $pdo->prepare("
    SELECT s.*
    FROM admin_push_subscriptions s
    WHERE s.user_id = ?
    ORDER BY s.id DESC
    LIMIT 1
");
$myStmt->execute([(int)$_SESSION['user_id']]);
$mySubscription = $myStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$vapidPublicKey = push_get_vapid_public_key();
$vapidPreview = $vapidPublicKey !== ''
    ? substr($vapidPublicKey, 0, 8) . '…' . substr($vapidPublicKey, -8)
    : '—';

$diagnosis = null;
$serverEnv = push_server_environment();
$vapidDuplicateWarning = (string)($serverEnv['pem_duplicate_warning'] ?? '');
$outboundWarning = (string)($serverEnv['outbound_warning'] ?? '');
$outboundProbes = is_array($serverEnv['outbound'] ?? null) ? $serverEnv['outbound'] : [];

if($mySubscription){
    $diagnosis = push_diagnose_subscription($mySubscription);
}

$testResult = null;
$testError = null;
$testReport = [];

if(isset($_SESSION['push_test_flash'])){
    $flash = $_SESSION['push_test_flash'];
    unset($_SESSION['push_test_flash']);
    $testResult = $flash['result'] ?? null;
    $testError = $flash['error'] ?? null;
    $testReport = $flash['report'] ?? [];
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_test'){
    try{
        $userId = (int)$_SESSION['user_id'];
        $report = push_notify_user(
            $pdo,
            $userId,
            'تست اعلان Ticketin',
            'اگر این پیام را می‌بینید، اعلان‌ها درست کار می‌کنند.',
            '/admin/push-test.php',
            'ticketin-push-test'
        );

        if(($report['targeted'] ?? 0) === 0){
            $report = push_notify_super_admins(
                $pdo,
                'تست اعلان Ticketin',
                'اگر این پیام را می‌بینید، اعلان‌ها درست کار می‌کنند.',
                '/admin/push-test.php',
                'ticketin-push-test'
            );
        }

        $flash = [
            'report' => $report,
            'result' => null,
            'error' => null,
        ];

        $targeted = (int)($report['targeted'] ?? 0);

        if(($report['sent'] ?? 0) > 0){
            $flash['result'] = 'ارسال موفق برای ' . (int)$report['sent'] . ' اشتراک.';
        }elseif($targeted === 0){
            $flash['error'] = 'هیچ اشتراک اعلانی برای ارسال پیدا نشد. ابتدا «فعال‌سازی اعلان» را بزنید.';
        }else{
            $firstError = '';

            foreach(($report['results'] ?? []) as $row){
                if(!empty($row['error'])){
                    $firstError = (string)$row['error'];
                    break;
                }
            }

            $flash['error'] = $firstError !== ''
                ? 'ارسال ناموفق بود: ' . $firstError
                : 'ارسال به همه اشتراک‌ها ناموفق بود. گزارش پایین را ببینید.';

            $outboundHint = push_outbound_connectivity_summary(push_probe_outbound_connectivity());

            if($outboundHint !== '' && ($report['sent'] ?? 0) === 0){
                $flash['error'] = 'ارسال ناموفق بود: ' . $outboundHint;
            }
        }

        $_SESSION['push_test_flash'] = $flash;
    }catch(Throwable $e){
        $_SESSION['push_test_flash'] = [
            'report' => [],
            'result' => null,
            'error' => 'خطا در ارسال: ' . $e->getMessage(),
        ];
    }

    header('Location: push-test.php');
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_push'){
    $resetOk = push_reset_vapid_and_subscriptions($pdo);

    $_SESSION['push_test_flash'] = [
        'report' => [],
        'result' => $resetOk
            ? 'کلید VAPID و همه اشتراک‌ها پاک شد. حالا «فعال‌سازی اعلان» را بزنید.'
            : null,
        'error' => $resetOk
            ? null
            : 'بازنشانی ناموفق بود. دسترسی پوشه storage را بررسی کنید.',
    ];

    header('Location: push-test.php');
    exit;
}

$back_url = 'index.php';
$page_title = 'تست اعلان‌ها';

require '../includes/header.php';

?>

<style>
.push-test-page{max-width:760px;margin:0 auto;}
.push-test-card{background:#fff;border-radius:24px;padding:22px;margin-bottom:18px;border:1px solid #eef2f7;box-shadow:0 8px 30px rgba(15,23,42,.05);}
.push-test-title{font-size:18px;font-weight:800;color:#0f172a;margin-bottom:14px;}
.push-test-list{display:grid;gap:10px;margin:0;padding:0;list-style:none;}
.push-test-item{display:flex;justify-content:space-between;gap:12px;padding:12px 14px;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0;font-size:13px;}
.push-test-ok{color:#047857;font-weight:800;}
.push-test-bad{color:#dc2626;font-weight:800;}
.push-test-note{color:#64748b;line-height:2;font-size:13px;}
.push-test-steps{padding-right:18px;line-height:2.1;color:#334155;font-size:13px;}
.push-test-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;}
.push-test-btn{border:none;border-radius:14px;padding:12px 18px;font-family:'Vazirmatn',sans-serif;font-size:13px;font-weight:800;cursor:pointer;}
.push-test-btn--primary{background:linear-gradient(135deg,#0284c7,#06b6d4);color:#fff;}
.push-test-btn--ghost{background:#f8fafc;color:#334155;border:1px solid #e2e8f0;}
.push-test-alert{padding:14px 16px;border-radius:14px;margin-bottom:16px;font-size:13px;line-height:1.9;}
.push-test-alert--ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;}
.push-test-alert--error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}
.push-test-status{font-size:13px;color:#334155;line-height:2;}
.push-test-item--error{align-items:flex-start;flex-direction:column;gap:6px;}
.push-test-error{font-size:12px;color:#991b1b;line-height:1.8;}
.push-test-btn--danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;}
.push-test-btn[disabled]{opacity:.7;cursor:wait;}
</style>

<div class="push-test-page">

<?php if($testResult): ?>
<div class="push-test-alert push-test-alert--ok"><?= htmlspecialchars($testResult, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if($testError): ?>
<div class="push-test-alert push-test-alert--error"><?= htmlspecialchars($testError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if($testReport): ?>
<div class="push-test-card">
<div class="push-test-title">گزارش آخرین ارسال</div>
<ul class="push-test-list">
<li class="push-test-item"><span>هدف‌گیری‌شده</span><span><?= (int)($testReport['targeted'] ?? 0) ?></span></li>
<li class="push-test-item"><span>موفق</span><span class="push-test-ok"><?= (int)($testReport['sent'] ?? 0) ?></span></li>
<li class="push-test-item"><span>ناموفق</span><span class="<?= ($testReport['failed'] ?? 0) ? 'push-test-bad' : 'push-test-ok' ?>"><?= (int)($testReport['failed'] ?? 0) ?></span></li>
<?php foreach(($testReport['results'] ?? []) as $row): ?>
<?php $ok = ((int)($row['status'] ?? 0) >= 200 && (int)($row['status'] ?? 0) < 300); ?>
<li class="push-test-item<?= $ok ? '' : ' push-test-item--error' ?>">
<span>
ادمین #<?= (int)($row['user_id'] ?? 0) ?>
<?php if(!empty($row['endpoint_host'])): ?>
 — <?= htmlspecialchars((string)$row['endpoint_host'], ENT_QUOTES, 'UTF-8') ?>
<?php endif; ?>
 — HTTP <?= (int)($row['status'] ?? 0) ?>
</span>
<?php if(!$ok && !empty($row['error'])): ?>
<span class="push-test-error"><?= htmlspecialchars((string)$row['error'], ENT_QUOTES, 'UTF-8') ?></span>
<?php endif; ?>
<?php if(!$ok && !empty($row['detail'])): ?>
<span class="push-test-error" style="direction:ltr;font-family:monospace;font-size:11px;"><?= htmlspecialchars((string)$row['detail'], ENT_QUOTES, 'UTF-8') ?></span>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<div class="push-test-card">
<div class="push-test-title">وضعیت سرور</div>
<ul class="push-test-list">
<li class="push-test-item"><span>کلید VAPID</span><span class="<?= $status['vapid'] ? 'push-test-ok' : 'push-test-bad' ?>"><?= $status['vapid'] ? 'آماده' : 'ساخته نشده' ?></span></li>
<li class="push-test-item"><span>اثر انگشت VAPID</span><span style="direction:ltr;font-family:monospace;"><?= htmlspecialchars($vapidPreview, ENT_QUOTES, 'UTF-8') ?></span></li>
<li class="push-test-item"><span>افزونه openssl</span><span class="<?= $status['openssl'] ? 'push-test-ok' : 'push-test-bad' ?>"><?= $status['openssl'] ? 'فعال' : 'غیرفعال' ?></span></li>
<li class="push-test-item"><span>افزونه curl</span><span class="<?= $status['curl'] ? 'push-test-ok' : 'push-test-bad' ?>"><?= $status['curl'] ? 'فعال' : 'غیرفعال' ?></span></li>
<li class="push-test-item"><span>PHP</span><span style="direction:ltr;"><?= htmlspecialchars((string)$serverEnv['php_version'], ENT_QUOTES, 'UTF-8') ?></span></li>
<li class="push-test-item"><span>openssl_pkey_derive</span><span class="<?= !empty($serverEnv['openssl_pkey_derive']) ? 'push-test-ok' : 'push-test-bad' ?>"><?= !empty($serverEnv['openssl_pkey_derive']) ? 'دارد' : 'ندارد' ?></span></li>
<li class="push-test-item"><span>aes-128-gcm</span><span class="<?= !empty($serverEnv['aes_128_gcm']) ? 'push-test-ok' : 'push-test-bad' ?>"><?= !empty($serverEnv['aes_128_gcm']) ? 'دارد' : 'ندارد' ?></span></li>
<li class="push-test-item"><span>مسیر کلید VAPID</span><span style="direction:ltr;font-size:11px;"><?= htmlspecialchars((string)($serverEnv['pem_path'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></span></li>
<li class="push-test-item"><span>CA bundle</span><span class="<?= !empty($serverEnv['ca_bundle']) ? 'push-test-ok' : 'push-test-bad' ?>"><?= !empty($serverEnv['ca_bundle']) ? 'پیدا شد' : 'پیدا نشد' ?></span></li>
<li class="push-test-item"><span>پوشه storage</span><span class="<?= !empty($serverEnv['storage_writable']) ? 'push-test-ok' : 'push-test-bad' ?>"><?= !empty($serverEnv['storage_writable']) ? 'قابل نوشتن' : 'غیرقابل نوشتن' ?></span></li>
<?php if(!empty($serverEnv['proxy'])): ?>
<li class="push-test-item"><span>پروکسی خروجی</span><span class="push-test-ok">فعال</span></li>
<?php endif; ?>
<?php foreach($outboundProbes as $label => $probe): ?>
<li class="push-test-item">
<span>اتصال <?= htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') ?></span>
<span class="<?= !empty($probe['ok']) ? 'push-test-ok' : 'push-test-bad' ?>">
<?= !empty($probe['ok']) ? 'برقرار (HTTP ' . (int)($probe['status'] ?? 0) . ')' : htmlspecialchars((string)($probe['error'] ?: 'ناموفق'), ENT_QUOTES, 'UTF-8') ?>
</span>
</li>
<?php endforeach; ?>
<li class="push-test-item"><span>اشتراک‌های ثبت‌شده</span><span><?= $subscriptionCount ?> مورد</span></li>
<li class="push-test-item"><span>اشتراک شما</span><span class="<?= $mySubscriptionCount > 0 ? 'push-test-ok' : 'push-test-bad' ?>"><?= $mySubscriptionCount > 0 ? 'ثبت شده' : 'ثبت نشده' ?></span></li>
<?php if($diagnosis): ?>
<li class="push-test-item<?= $diagnosis['ok'] ? '' : ' push-test-item--error' ?>">
<span>آزمایش رمزنگاری</span>
<span class="<?= $diagnosis['ok'] ? 'push-test-ok' : 'push-test-bad' ?>">
<?= $diagnosis['ok'] ? 'موفق' : htmlspecialchars((string)$diagnosis['error'], ENT_QUOTES, 'UTF-8') ?>
</span>
</li>
<?php endif; ?>
</ul>
<?php if($outboundWarning !== ''): ?>
<p class="push-test-note push-test-bad"><?= htmlspecialchars($outboundWarning, ENT_QUOTES, 'UTF-8') ?></p>
<p class="push-test-note">روی سرور تست کنید: <code style="direction:ltr;">curl -I --max-time 10 https://fcm.googleapis.com/</code><br>اگر timeout شد، فایروال باید خروجی به Google را باز کند، یا در <code>storage/push_proxy.txt</code> آدرس پروکسی HTTPS بگذارید.</p>
<?php endif; ?>
<?php if($vapidDuplicateWarning !== ''): ?>
<p class="push-test-note push-test-bad"><?= htmlspecialchars($vapidDuplicateWarning, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if($diagnosis && !$diagnosis['ok'] && in_array($diagnosis['step'] ?? '', ['encrypt', 'jwt', 'vapid'], true)): ?>
<p class="push-test-note">اگر خطا مربوط به VAPID است، یک‌بار «فعال‌سازی اعلان» را بزنید تا اشتراک با کلید جدید ثبت شود.</p>
<?php endif; ?>
</div>

<div class="push-test-card">
<div class="push-test-title">وضعیت مرورگر</div>
<div class="push-test-status" id="browserPushStatus">در حال بررسی...</div>
<div class="push-test-actions">
<button type="button" class="push-test-btn push-test-btn--primary" id="enablePushBtn">فعال‌سازی اعلان در این مرورگر</button>
</div>
</div>

<div class="push-test-card">
<div class="push-test-title">راهنمای تست سریع</div>
<ol class="push-test-steps">
<li>این صفحه را با <strong>HTTPS</strong> باز کنید (یا روی localhost تست کنید).</li>
<li>روی «فعال‌سازی اعلان» بزنید و اجازه Allow را بدهید.</li>
<li>در صورت نمایش بنر پایین صفحه، اپ ادمین را نصب کنید.</li>
<li>روی «ارسال اعلان تست» بزنید؛ باید نوتیفیکیشن بیاید.</li>
<li>برای تست واقعی: با یک کاربر به تیکت پاسخ دهید، ثبت‌نام جدید بزنید، یا یادآوری امروز ثبت کنید.</li>
</ol>
<p class="push-test-note">تب مرورگر را می‌توانید ببندید؛ اعلان باید همچنان نمایش داده شود.</p>
</div>

<div class="push-test-card">
<div class="push-test-title">ارسال تست</div>
<form method="POST" id="pushTestSendForm">
<input type="hidden" name="action" value="send_test">
<button type="submit" class="push-test-btn push-test-btn--primary" id="pushTestSendBtn">ارسال اعلان تست</button>
</form>
<p class="push-test-note" id="pushTestSendHint" style="display:none;margin-top:10px;">در حال ارسال… حداکثر چند ثانیه طول می‌کشد.</p>
</div>

<div class="push-test-card">
<div class="push-test-title">بازنشانی کامل (اگر هنوز کار نمی‌کند)</div>
<p class="push-test-note">کلید VAPID و همه اشتراک‌های ذخیره‌شده پاک می‌شود. بعد باید دوباره «فعال‌سازی اعلان» را بزنید.</p>
<form method="POST" onsubmit="return confirm('کلید VAPID و همه اشتراک‌ها پاک شود؟');">
<input type="hidden" name="action" value="reset_push">
<button type="submit" class="push-test-btn push-test-btn--danger">بازنشانی کامل اعلان‌ها</button>
</form>
</div>

</div>

<script>
(function(){
    const publicKey = <?= json_encode($vapidPublicKey, JSON_UNESCAPED_UNICODE) ?>;
    const vapidStorageKey = 'ticketin_admin_vapid_public_key';
    const statusEl = document.getElementById('browserPushStatus');
    const enableBtn = document.getElementById('enablePushBtn');

    function urlBase64ToUint8Array(base64String){
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for(let i = 0; i < rawData.length; i++){
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    async function ensureFreshSubscription(registration, activePublicKey){
        let subscription = await registration.pushManager.getSubscription();
        const storedKey = localStorage.getItem(vapidStorageKey) || '';

        if(subscription && storedKey && storedKey !== activePublicKey){
            try{
                await subscription.unsubscribe();
            }catch(error){
            }

            subscription = null;
        }

        if(!subscription){
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(activePublicKey)
            });
        }

        return subscription;
    }

    async function saveSubscription(subscription){
        const response = await fetch('/admin/push-subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'subscribe',
                subscription: subscription.toJSON()
            })
        });
        return response.json();
    }

    async function refreshStatus(){
        const lines = [];
        lines.push('کلید VAPID سرور: ' + (publicKey ? 'آماده' : 'ساخته نشده'));
        if(publicKey){
            lines.push('اثر انگشت VAPID: ' + publicKey.slice(0, 8) + '…' + publicKey.slice(-8));
            const storedKey = localStorage.getItem(vapidStorageKey) || '';
            lines.push('کلید ذخیره‌شده مرورگر: ' + (storedKey ? (storedKey === publicKey ? 'هم‌خوان' : 'قدیمی — دوباره فعال‌سازی لازم است') : 'ثبت نشده'));
        }
        lines.push('Service Worker: ' + ('serviceWorker' in navigator ? 'پشتیبانی می‌شود' : 'پشتیبانی نمی‌شود'));
        lines.push('Notification API: ' + ('Notification' in window ? 'پشتیبانی می‌شود' : 'پشتیبانی نمی‌شود'));
        lines.push('HTTPS: ' + (location.protocol === 'https:' || location.hostname === 'localhost' ? 'مناسب' : 'نیاز به HTTPS'));
        lines.push('اجازه اعلان: ' + (Notification.permission || 'نامشخص'));

        if('serviceWorker' in navigator){
            try{
                const registration = await navigator.serviceWorker.getRegistration('/admin/');
                lines.push('ثبت SW: ' + (registration ? 'بله' : 'خیر'));
                if(registration){
                    const subscription = await registration.pushManager.getSubscription();
                    lines.push('اشتراک Push: ' + (subscription ? 'بله' : 'خیر'));
                }
            }catch(error){
                lines.push('خطا در بررسی SW: ' + error.message);
            }
        }

        statusEl.innerHTML = lines.join('<br>');
    }

    enableBtn.addEventListener('click', async function(){
        if(!publicKey){
            alert('کلید VAPID هنوز روی سرور ساخته نشده است.');
            return;
        }

        const registration = await navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' });
        const readyRegistration = await navigator.serviceWorker.ready;

        if(!readyRegistration.pushManager){
            alert('Push Manager در این مرورگر در دسترس نیست.');
            await refreshStatus();
            return;
        }

        const permission = await Notification.requestPermission();
        if(permission !== 'granted'){
            alert('اجازه اعلان داده نشد.');
            await refreshStatus();
            return;
        }

        const subscription = await ensureFreshSubscription(readyRegistration, publicKey);
        const result = await saveSubscription(subscription);

        if(result.ok){
            localStorage.setItem(vapidStorageKey, publicKey);
            localStorage.removeItem('ticketin_admin_pwa_dismissed_until');
            alert('اعلان در این مرورگر فعال شد.');
        }else{
            alert('ثبت اشتراک ناموفق بود.');
        }

        await refreshStatus();
        location.reload();
    });

    refreshStatus();

    const pushTestSendForm = document.getElementById('pushTestSendForm');
    const pushTestSendBtn = document.getElementById('pushTestSendBtn');
    const pushTestSendHint = document.getElementById('pushTestSendHint');

    if(pushTestSendForm && pushTestSendBtn){
        pushTestSendForm.addEventListener('submit', function(){
            pushTestSendBtn.disabled = true;
            pushTestSendBtn.textContent = 'در حال ارسال…';

            if(pushTestSendHint){
                pushTestSendHint.style.display = 'block';
            }
        });
    }
})();
</script>

<?php include '../includes/footer.php'; ?>
