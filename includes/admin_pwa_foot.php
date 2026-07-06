<?php

require_once __DIR__ . '/push_helpers.php';

$adminPwaPublicKey = push_get_vapid_public_key();

?>
<style>
.admin-pwa-banner{
    position:fixed;
    left:16px;
    right:16px;
    bottom:16px;
    z-index:100001;
    display:none;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:14px 16px;
    border-radius:18px;
    background:#0f172a;
    color:#fff;
    box-shadow:0 18px 40px rgba(15,23,42,.28);
}
.admin-pwa-banner.show{
    display:flex;
}
.admin-pwa-banner__text{
    font-size:13px;
    line-height:1.8;
    font-weight:600;
}
.admin-pwa-banner__title{
    display:block;
    font-size:14px;
    font-weight:800;
    margin-bottom:2px;
}
.admin-pwa-banner__actions{
    display:flex;
    gap:8px;
    flex-shrink:0;
}
.admin-pwa-btn{
    border:none;
    border-radius:12px;
    padding:10px 14px;
    font-family:'Vazirmatn',sans-serif;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
}
.admin-pwa-btn--primary{
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    color:#fff;
}
.admin-pwa-btn--ghost{
    background:rgba(255,255,255,.08);
    color:#fff;
}
@media(max-width:720px){
    .admin-pwa-banner{
        flex-direction:column;
        align-items:stretch;
    }
    .admin-pwa-banner__actions{
        justify-content:stretch;
    }
    .admin-pwa-btn{
        flex:1;
    }
}
</style>

<div class="admin-pwa-banner" id="adminPwaBanner" aria-live="polite">
<div class="admin-pwa-banner__text">
<span class="admin-pwa-banner__title" id="adminPwaBannerTitle">نصب نسخه اپلیکیشن ادمین</span>
<span id="adminPwaBannerBody">پاسخ کاربر و یادآوری برای همه ادمین‌ها؛ ثبت‌نام جدید فقط برای سوپرادمین.</span>
</div>
<div class="admin-pwa-banner__actions">
<button type="button" class="admin-pwa-btn admin-pwa-btn--primary" id="adminPwaPrimaryBtn">نصب</button>
<button type="button" class="admin-pwa-btn admin-pwa-btn--ghost" id="adminPwaDismissBtn">بعداً</button>
</div>
</div>

<script>
(function(){
    const publicKey = <?= json_encode($adminPwaPublicKey, JSON_UNESCAPED_UNICODE) ?>;
    const banner = document.getElementById('adminPwaBanner');
    const titleEl = document.getElementById('adminPwaBannerTitle');
    const bodyEl = document.getElementById('adminPwaBannerBody');
    const primaryBtn = document.getElementById('adminPwaPrimaryBtn');
    const dismissBtn = document.getElementById('adminPwaDismissBtn');
    const dismissKey = 'ticketin_admin_pwa_dismissed_until';
    let deferredInstallPrompt = null;
    let swRegistration = null;

    function isDismissed(){
        const until = parseInt(localStorage.getItem(dismissKey) || '0', 10);
        return until > Date.now();
    }

    function dismissBanner(days){
        localStorage.setItem(
            dismissKey,
            String(Date.now() + (days * 24 * 60 * 60 * 1000))
        );
        banner.classList.remove('show');
    }

    function showBanner(mode){
        if(isDismissed()){
            return;
        }

        if(mode === 'install'){
            titleEl.textContent = 'نصب نسخه اپلیکیشن ادمین';
            bodyEl.textContent = 'پاسخ کاربر و یادآوری برای همه ادمین‌ها؛ ثبت‌نام جدید فقط برای سوپرادمین.';
            primaryBtn.textContent = 'نصب';
        }else{
            titleEl.textContent = 'فعال‌سازی اعلان‌ها';
            bodyEl.textContent = 'بعد از نصب اپ، این دکمه را بزنید و اجازه اعلان را بدهید.';
            primaryBtn.textContent = 'فعال‌سازی اعلان';
        }

        banner.classList.add('show');
    }

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

    async function getServiceWorkerRegistration(){
        if(!('serviceWorker' in navigator)){
            return null;
        }

        if(swRegistration){
            return swRegistration;
        }

        await navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' });
        swRegistration = await navigator.serviceWorker.ready;

        return swRegistration;
    }

    async function saveSubscription(subscription){
        const response = await fetch('/admin/push-subscribe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'subscribe',
                subscription: subscription.toJSON()
            })
        });

        return response.json();
    }

    function pushSupportError(){
        if(!publicKey){
            return 'کلید اعلان روی سرور ساخته نشده. یک‌بار از پنل خارج و دوباره وارد شوید. اگر ادامه داشت، افزونه openssl سرور را بررسی کنید.';
        }

        if(!('serviceWorker' in navigator)){
            return 'این مرورگر Service Worker را پشتیبانی نمی‌کند. از Chrome یا Edge استفاده کنید.';
        }

        if(!('Notification' in window)){
            return 'این مرورگر اعلان را پشتیبانی نمی‌کند. از Chrome اندروید یا اپ نصب‌شده استفاده کنید.';
        }

        return 'اعلان Push در این مرورگر فعال نیست. اپ را نصب کنید و از Chrome اندروید استفاده کنید.';
    }

    async function resolvePublicKey(){
        if(publicKey){
            return publicKey;
        }

        try{
            const response = await fetch('/admin/push-vapid-public.php', {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            const data = await response.json();

            if(data.publicKey){
                return data.publicKey;
            }

            if(data.error){
                throw new Error(data.error);
            }
        }catch(error){
            if(error && error.message){
                throw error;
            }
        }

        throw new Error(pushSupportError());
    }

    async function enablePushNotifications(){
        let activePublicKey = '';

        try{
            activePublicKey = await resolvePublicKey();
        }catch(error){
            alert(error.message || pushSupportError());
            return false;
        }

        if(!('serviceWorker' in navigator) || !('Notification' in window)){
            alert(pushSupportError());
            return false;
        }

        const registration = await getServiceWorkerRegistration();

        if(!registration || !registration.pushManager){
            alert(pushSupportError());
            return false;
        }

        const permission = await Notification.requestPermission();

        if(permission !== 'granted'){
            alert('اجازه اعلان داده نشد. از تنظیمات مرورگر برای ticketin.ir اعلان را فعال کنید.');
            return false;
        }

        let subscription = await registration.pushManager.getSubscription();

        if(!subscription){
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(activePublicKey)
            });
        }

        const result = await saveSubscription(subscription);

        if(!result.ok){
            alert(result.message || 'ثبت اعلان ناموفق بود. صفحه را رفرش کنید و دوباره تلاش کنید.');
            return false;
        }

        alert('اعلان‌ها با موفقیت فعال شد.');
        return true;
    }

    async function handlePrimaryAction(){
        if(deferredInstallPrompt){
            deferredInstallPrompt.prompt();
            const choice = await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;

            if(choice.outcome === 'accepted'){
                banner.classList.remove('show');
                setTimeout(function(){
                    showBanner('notify');
                }, 1200);
            }

            return;
        }

        const enabled = await enablePushNotifications();

        if(enabled){
            banner.classList.remove('show');
        }
    }

    window.addEventListener('beforeinstallprompt', function(event){
        event.preventDefault();
        deferredInstallPrompt = event;
        showBanner('install');
    });

    window.addEventListener('appinstalled', function(){
        setTimeout(function(){
            showBanner('notify');
        }, 1000);
    });

    primaryBtn.addEventListener('click', handlePrimaryAction);
    dismissBtn.addEventListener('click', function(){
        dismissBanner(7);
    });

    if('serviceWorker' in navigator){
        getServiceWorkerRegistration()
            .then(function(registration){
                if(!registration){
                    return null;
                }

                return registration.pushManager.getSubscription();
            })
            .then(function(subscription){
                if(subscription || Notification.permission === 'granted'){
                    banner.classList.remove('show');
                    return;
                }

                const isStandalone =
                    window.matchMedia('(display-mode: standalone)').matches
                    || window.navigator.standalone === true;

                if(isStandalone || !deferredInstallPrompt){
                    showBanner('notify');
                }
            })
            .catch(function(){
            });
    }
})();
</script>
