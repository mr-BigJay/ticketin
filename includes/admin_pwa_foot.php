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
            bodyEl.textContent = 'اعلان پاسخ کاربر و یادآوری برای همه ادمین‌ها؛ ثبت‌نام جدید برای سوپرادمین.';
            primaryBtn.textContent = 'فعال‌سازی';
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

    async function enablePushNotifications(){
        if(!publicKey || !('serviceWorker' in navigator) || !('PushManager' in window)){
            alert('مرورگر شما از اعلان‌ها پشتیبانی نمی‌کند.');
            return false;
        }

        if(!swRegistration){
            swRegistration = await navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' });
        }

        const permission = await Notification.requestPermission();

        if(permission !== 'granted'){
            return false;
        }

        let subscription = await swRegistration.pushManager.getSubscription();

        if(!subscription){
            subscription = await swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey)
            });
        }

        const result = await saveSubscription(subscription);
        return !!result.ok;
    }

    async function handlePrimaryAction(){
        if(deferredInstallPrompt){
            deferredInstallPrompt.prompt();
            await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            banner.classList.remove('show');
            await enablePushNotifications();
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

    primaryBtn.addEventListener('click', handlePrimaryAction);
    dismissBtn.addEventListener('click', function(){
        dismissBanner(7);
    });

    if('serviceWorker' in navigator){
        navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' })
            .then(function(registration){
                swRegistration = registration;

                if(window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true){
                    return registration.pushManager.getSubscription();
                }

                return null;
            })
            .then(function(subscription){
                if(subscription || Notification.permission === 'granted'){
                    banner.classList.remove('show');
                    return;
                }

                if(!deferredInstallPrompt && Notification.permission === 'default'){
                    showBanner('notify');
                }
            })
            .catch(function(){
            });
    }
})();
</script>
