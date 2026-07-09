<?php

require '../includes/admin_auth.php';
require '../includes/upload_storage.php';

admin_require_super();

$message = '';
$error = '';
$formatCatalog = upload_settings_format_catalog();
$settings = upload_settings_get($pdo);

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $maxSizeMb = (int)($_POST['max_size_mb'] ?? 0);
    $selectedExtensions = $_POST['extensions'] ?? [];

    if(!is_array($selectedExtensions)){
        $selectedExtensions = [];
    }

    $saveError = upload_settings_save(
        $pdo,
        $maxSizeMb,
        $selectedExtensions
    );

    if($saveError){
        $error = $saveError;
    }else{

        try{
            upload_storage_ensure_base_dir();
            upload_storage_ensure_date_dir(
                upload_storage_jalali_date_folder()
            );
        }catch(Throwable $e){
            $error = $e->getMessage();
        }

        if(!$error){
            $message = 'تنظیمات آپلود ذخیره شد';
            $settings = upload_settings_get($pdo);
        }

    }

}

$back_url = 'index.php';
$page_title = '⚙️ مدیریت آپلود';

require '../includes/header.php';

?>

<style>

.page-box{

    max-width:920px;

    margin:auto;

}

.card{

    background:white;

    border-radius:24px;

    padding:24px;

    margin-bottom:18px;

    box-shadow:0 10px 30px rgba(15,23,42,.05);

    border:1px solid #eef2f7;

}

.page-title{

    font-size:24px;

    font-weight:800;

    color:#0f172a;

    margin-bottom:8px;

}

.page-sub{

    color:#64748b;

    font-size:14px;

    line-height:28px;

    margin-bottom:20px;

}

.field-label{

    display:block;

    font-size:14px;

    font-weight:800;

    color:#0f172a;

    margin-bottom:10px;

}

.size-row{

    display:flex;

    align-items:center;

    gap:14px;

    flex-wrap:wrap;

}

.size-input{

    width:120px;

    padding:12px 14px;

    border:1px solid #dbe3ee;

    border-radius:14px;

    font-family:'Vazirmatn',sans-serif;

    font-size:15px;

    font-weight:700;

}

.size-hint{

    color:#64748b;

    font-size:13px;

}

.format-group{

    margin-bottom:18px;

}

.format-group-title{

    font-size:15px;

    font-weight:800;

    color:#0369a1;

    margin-bottom:10px;

}

.format-grid{

    display:grid;

    grid-template-columns:repeat(auto-fit,minmax(120px,1fr));

    gap:10px;

}

.format-item{

    display:flex;

    align-items:center;

    gap:8px;

    background:#f8fafc;

    border:1px solid #e2e8f0;

    border-radius:14px;

    padding:10px 12px;

    cursor:pointer;

}

.format-item input{

    width:16px;

    height:16px;

}

.format-item span{

    font-size:13px;

    font-weight:700;

    color:#0f172a;

}

.summary-box{

    background:#eff6ff;

    border:1px solid #bfdbfe;

    border-radius:18px;

    padding:16px;

    color:#1e3a8a;

    font-size:13px;

    line-height:28px;

    font-weight:700;

}

</style>

<div class="page-box">

<div class="card">

<div class="page-title">مدیریت آپلود</div>

<div class="page-sub">
فرمت‌های مجاز و حداکثر حجم فایل‌های پیوست تیکت را از اینجا تنظیم کنید.
</div>

<?php if($message): ?>
<div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

<label class="field-label" for="maxSizeMb">
حداکثر حجم فایل (مگابایت)
</label>

<div class="size-row">

<input
type="number"
class="size-input"
id="maxSizeMb"
name="max_size_mb"
min="0"
max="100"
step="1"
value="<?= (int)$settings['max_size_mb'] ?>"
required>

<div class="size-hint">
۰ یعنی قطع کامل آپلود — از ۱ تا ۱۰۰ مگابایت
</div>

</div>

<div style="height:22px"></div>

<div class="field-label">
فرمت‌های مجاز
</div>

<?php foreach($formatCatalog as $groupTitle => $formats): ?>

<div class="format-group">

<div class="format-group-title">
<?= htmlspecialchars($groupTitle) ?>
</div>

<div class="format-grid">

<?php foreach($formats as $ext => $label): ?>

<label class="format-item">

<input
type="checkbox"
name="extensions[]"
value="<?= htmlspecialchars($ext) ?>"
<?= in_array($ext, $settings['allowed_extensions'], true) ? 'checked' : '' ?>>

<span><?= htmlspecialchars($label) ?></span>

</label>

<?php endforeach; ?>

</div>

</div>

<?php endforeach; ?>

<div style="height:18px"></div>

<div class="summary-box">
<?php if($settings['uploads_enabled']): ?>
الان <?= count($settings['allowed_extensions']) ?> فرمت فعال است و سقف آپلود
<?= (int)$settings['max_size_mb'] ?> مگابایت است.
<?php else: ?>
آپلود فایل در حال حاضر <strong>غیرفعال</strong> است (حجم = ۰).
<?php endif; ?>
</div>

<div style="height:18px"></div>

<button type="submit" class="btn-custom">
ذخیره تنظیمات
</button>

</form>

</div>

</div>

<?php require '../includes/footer.php'; ?>
