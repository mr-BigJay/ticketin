<?php
require 'includes/auth.php';
require 'includes/db.php';

$announcement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$announcement_id) die("شناسه اطلاعیه نامعتبر است");

$stmt = $pdo->prepare("SELECT * FROM announcements WHERE id=?");
$stmt->execute([$announcement_id]);
$announcement = $stmt->fetch();
if(!$announcement) die("اطلاعیه یافت نشد");

$cat_stmt = $pdo->prepare("
    SELECT c.name
    FROM announcement_category_rel r
    JOIN announcement_categories c ON r.category_id = c.id
    WHERE r.announcement_id = ?
");
$cat_stmt->execute([$announcement_id]);
$categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

$published_datetime = $announcement['created_at'];
$published_jalali = fa_datetime($published_datetime);
$back_url = 'announcements.php';

require 'includes/header.php';
?>

<style>
.announcement-box{
    max-width:1000px;
    margin:auto;
    padding:20px;
}

.announcement-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}
.announcement-header-right{ font-weight:bold; font-size:14px; }
.announcement-header-left{ font-weight:bold; font-size:14px; }

.announcement-main{

    display:grid;

    grid-template-columns:1fr 1.4fr;

    gap:20px;

    margin-bottom:20px;

}
.announcement-image{
    width:100%;
    max-width:450px;
    border-radius:16px;
    object-fit:cover;
}
.announcement-info{
    flex:1;
    display:flex;
    flex-direction:column;
    gap:12px;
}
.announcement-title{
    font-size:22px;
    font-weight:800;
}
.announcement-summary{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:16px;
    line-height:28px;
}
.announcement-content{
    margin-top:20px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:16px;
    line-height:28px;
    font-size:15px;
    background:#fff;
}
.announcement-header {
    text-align: center;
    margin-bottom: 20px;
}

.announcement-categories {
    font-weight: bold;
    font-size: 14px;
    color: #1f2937;
    margin-bottom: 5px;
}

.announcement-datetime {
    font-size: 13px;
    color: #64748b;
}
</style>

<div class="announcement-box">

<div class="announcement-header" style="text-align:center; margin-bottom:20px;">
    <div class="announcement-categories" style="font-weight:bold; font-size:14px; color:#1f2937;">
        📂 <?= implode(' ، ', $categories) ?>
    </div>
    <div class="announcement-datetime" style="font-size:13px; color:#64748b;">
        🕒 <?= fa_datetime($announcement['created_at']) ?>
    </div>
</div>

<div class="announcement-main">

    <div class="announcement-info">

        <div class="announcement-title">

            <?= htmlspecialchars($announcement['title']) ?>

        </div>

        <div class="announcement-summary">

            <?= nl2br(
                htmlspecialchars(
                    $announcement['summary']
                )
            ) ?>

        </div>

    </div>

    <div class="announcement-image-box">

        <img
        src="uploads/<?= htmlspecialchars($announcement['image']) ?>"
        class="announcement-image">

    </div>

</div>

<div class="announcement-content">
    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
</div>

</div>

<?php include 'includes/footer.php'; ?>