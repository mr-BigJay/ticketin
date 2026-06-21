<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/jalali.php'; // اگر دارید برای تبدیل تاریخ

// دریافت شناسه خبر
$announcement_id = (int)($_GET['id'] ?? 0);
if(!$announcement_id){
    die("خبر یافت نشد");
}

// اطلاعات خبر
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE id=?");
$stmt->execute([$announcement_id]);
$announcement = $stmt->fetch();

if(!$announcement){
    die("خبر یافت نشد");
}

// دسته بندی‌های خبر
$cat_stmt = $pdo->prepare("
    SELECT c.name
    FROM announcement_category_rel r
    LEFT JOIN announcement_categories c ON r.category_id = c.id
    WHERE r.announcement_id=?
");
$cat_stmt->execute([$announcement_id]);
$categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// عنوان صفحه و بازگشت
$page_title = $announcement['title'];
$back_url = $_SERVER['HTTP_REFERER'] ?? 'announcements.php';
require 'includes/header.php';
?>

<style>
.announcement-box{
    max-width:900px;
    margin:auto;
}
.announcement-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}
.announcement-title-box{
    background:#f3f4f6;
    border-radius:18px;
    padding:16px;
    margin-bottom:16px;
}
.announcement-title{
    font-size:24px;
    font-weight:900;
    margin-bottom:6px;
}
.announcement-meta{
    font-size:13px;
    color:#64748b;
}
.announcement-image{
    width:100%;
    border-radius:12px;
    margin-bottom:12px;
}
.announcement-summary{
    background:#f8fafc;
    padding:14px;
    border-radius:12px;
    margin-bottom:14px;
}
.announcement-content{
    padding:14px;
    border-radius:12px;
    background:white;
    border:1px solid #e5e7eb;
    line-height:1.7;
}
.back-btn-top{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 16px;
    background:#ffffff;
    border:1px solid #e2e8f0;
    border-radius:12px;
    text-decoration:none;
    font-weight:700;
    color:#0f172a;
    transition:.2s;
}
.back-btn-top:hover{
    background:#f8fafc;
}
</style>

<div class="announcement-box">

    <a href="<?= $back_url ?>" class="back-btn-top">← بازگشت</a>

    <div class="announcement-title-box">
        <div class="announcement-title"><?= htmlspecialchars($announcement['title']) ?></div>
        <div class="announcement-meta">
            🕒 <?= fa_datetime($announcement['created_at']) ?>
            <?php if(!empty($categories)): ?>
            | 📂 <?= implode(', ', $categories) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if(!empty($announcement['image'])): ?>
        <img src="<?= htmlspecialchars($announcement['image']) ?>" class="announcement-image" alt="خبر">
    <?php endif; ?>

    <?php if(!empty($announcement['summary'])): ?>
        <div class="announcement-summary"><?= nl2br(htmlspecialchars($announcement['summary'])) ?></div>
    <?php endif; ?>

    <div class="announcement-content"><?= nl2br(htmlspecialchars($announcement['content'])) ?></div>

</div>

<?php require 'includes/footer.php'; ?>