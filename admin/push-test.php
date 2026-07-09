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

$myStmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM admin_push_subscriptions
    WHERE user_id = ?
");
$myStmt->execute([(int)$_SESSION['user_id']]);
$mySubscriptionCount = (int)$myStmt->fetchColumn();

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
        $report = push_notify_super_admins(
            $pdo,
            'تست اعلان Ticketin',
            'اگر این پیام را می‌بینید، اعلان‌ها درست کار می‌کنند.',
            '/admin/push-test.php',
            'ticketin-push-test'
        );

        $flash = [
            'report' => $report,
            'result' => null,
            'error' => null,
        ];

        if(($report['sent'] ?? 0) > 0){
            $flash['result'] = 'ارسال موفق برای ' . (int)$report['sent'] . ' اشتراک.';
        }elseif($subscriptionCount === 0){
            $flash['error'] = 'هیچ اشتراک اعلانی ثبت نشده. ابتدا «فعال‌سازی اعلان» را بزنید.';
        }else{
            $flash['error'] = 'ارسال به همه اشتراک‌ها ناموفق بود. گزارش پایین را ببینید.';
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
<li class="push-test-item"><span>موفق</span><span class="push-test-ok"><?= (int)($testReport['sent'] ?? 0) ?></span></li>
<li class="push-test-item"><span>ناموفق</span><span class="<?= ($testReport['failed'] ?? 0) ? 'push-test-bad' : 'push-test-ok' ?>"><?= (int)($testReport['failed'] ?? 0) ?></span></li>
<?php foreach(($testReport['results'] ?? []) as $row): ?>
<li class="push-test-item">
<span>ادمین #<?= (int)($row['user_id'] ?? 0) ?></span>
<span class="<?= ((int)($row['status'] ?? 0) >= 200 && (int)($row['status'] ?? 0) < 300) ? 'push-test-ok' : 'push-test-bad' ?>">
HTTP <?= (int)($row['status'] ?? 0) ?>
</span>
</li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<div class="push-test-card">
<div class="push-test-title">وضعیت سرور</div>
<ul class="push-test-list">
<li class="push-test-item"><span>کلید VAPID</span><span class="<?= $status['vapid'] ? 'push-test-ok' : 'push-test-bad' ?>"><?= $status['vapid'] ? 'آماده' : 'ساخته نشده' ?></span></li>
<li class="push-test-item"><span>افزونه openssl</span><span class="<?= $status['openssl'] ? 'push-test-ok' : 'push-test-bad' ?>"><?= $status['openssl'] ? 'فعال' : 'غیرفعال' ?></span></li>
<li class="push-test-item"><span>افزونه curl</span><span class="<?= $status['curl'] ? 'push-test-ok' : 'push-test-bad' ?>"><?= $status['curl'] ? 'فعال' : 'غیرفعال' ?></span></li>
<li class="push-test-item"><span>اشتراک‌های ثبت‌شده</span><span><?= $subscriptionCount ?> مورد</span></li>
<li class="push-test-item"><span>اشتراک شما</span><span><?= $mySubscriptionCount > 0 ? 'ثبت شده' : 'ثبت نشده' ?></span></li>
</ul>
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

</div>

<script>
(function(){
    const publicKey = <?= json_encode(push_get_vapid_public_key(), JSON_UNESCAPED_UNICODE) ?>;
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

        let subscription = await readyRegistration.pushManager.getSubscription();

        if(subscription){
            try{
                const existing = await saveSubscription(subscription);

                if(existing.ok){
                    alert('اعلان در این مرورگر فعال شد.');
                    await refreshStatus();
                    location.reload();
                    return;
                }
            }catch(error){
            }

            try{
                await subscription.unsubscribe();
            }catch(error){
            }

            subscription = null;
        }

        if(!subscription){
            subscription = await readyRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey)
            });
        }

        const result = await saveSubscription(subscription);
        if(result.ok){
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
