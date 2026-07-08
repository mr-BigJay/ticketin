<?php

session_start();

require '../includes/admin_auth.php';

$headerPath = realpath(__DIR__ . '/../includes/header.php');
$headerMtime = is_file($headerPath) ? filemtime($headerPath) : 0;
$headerContent = is_file($headerPath) ? file_get_contents($headerPath) : '';
$hasDesignB = str_contains($headerContent, 'TICKETIN_HEADER_VERSION=design-b-unified');
$hasOldOverride = str_contains($headerContent, 'body:not(.user-portal) .topbar');

$page_title = '🔍 بررسی هدر';
$hideBackButton = true;

require '../includes/header.php';

?>

<div class="card" style="max-width:720px;margin:0 auto;">

<h2 style="margin-bottom:16px;">وضعیت هدر سایت</h2>

<table style="width:100%;border-collapse:collapse;">
<tr>
<th style="text-align:right;padding:10px;border-bottom:1px solid #e2e8f0;">مورد</th>
<th style="text-align:right;padding:10px;border-bottom:1px solid #e2e8f0;">مقدار</th>
</tr>
<tr>
<td style="padding:10px;border-bottom:1px solid #f1f5f9;">مسیر فایل</td>
<td style="padding:10px;border-bottom:1px solid #f1f5f9;direction:ltr;text-align:left;"><?= htmlspecialchars($headerPath ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
</tr>
<tr>
<td style="padding:10px;border-bottom:1px solid #f1f5f9;">آخرین تغییر فایل</td>
<td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?= $headerMtime ? date('Y-m-d H:i:s', $headerMtime) : '—' ?></td>
</tr>
<tr>
<td style="padding:10px;border-bottom:1px solid #f1f5f9;">نسخه Design B</td>
<td style="padding:10px;border-bottom:1px solid #f1f5f9;">
<?= $hasDesignB ? '✅ بله' : '❌ خیر — فایل قدیمی است' ?>
</td>
</tr>
<tr>
<td style="padding:10px;border-bottom:1px solid #f1f5f9;">استایل قدیمی ادمین</td>
<td style="padding:10px;border-bottom:1px solid #f1f5f9;">
<?= $hasOldOverride ? '❌ هنوز وجود دارد' : '✅ حذف شده' ?>
</td>
</tr>
<tr>
<td style="padding:10px;">کلاس body</td>
<td style="padding:10px;"><?= htmlspecialchars(implode(' ', $body_classes ?? []), ENT_QUOTES, 'UTF-8') ?: '(بدون کلاس — ادمین)' ?></td>
</tr>
</table>

<p style="margin-top:18px;color:#64748b;font-size:14px;line-height:1.7;">
اگر «نسخه Design B» خیر است، اسکریپت دیپلوی را اجرا کنید و سپس با <strong>Ctrl+Shift+R</strong> رفرش سخت بزنید.
</p>

</div>

<?php require '../includes/footer.php'; ?>
