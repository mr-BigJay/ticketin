<?php

require '../includes/admin_auth.php';

$back_url = 'index.php';
$page_title = '🎓 آموزش‌ها';

require '../includes/header.php';

?>

<style>
.page-box{max-width:900px;margin:auto;}
.card{background:white;border-radius:24px;padding:28px;box-shadow:0 0 20px rgba(0,0,0,.05);line-height:34px;color:#334155;}
</style>

<div class="page-box">
<div class="card">
<div style="font-size:18px;font-weight:800;margin-bottom:12px;color:#0f172a;">بخش آموزش</div>
<p>محتوای آموزشی سامانه از این بخش در دسترس است. در صورت نیاز، فایل‌ها و ویدیوهای آموزشی توسط ادمین اصلی در این قسمت قرار می‌گیرند.</p>
</div>
</div>

<?php include '../includes/footer.php'; ?>
