<?php

require 'includes/auth.php';
require 'includes/db.php';

$page_title = '📰 اطلاعیه ها';
$back_url = 'dashboard.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

$where = ["a.is_archived=0"];
$params = [];

if($search){

    $where[] = "(
        a.title LIKE ?
        OR a.summary LIKE ?
        OR a.content LIKE ?
    )";

    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

}

$whereSql = "WHERE " . implode(" AND ", $where);

$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM announcements a
    $whereSql
");

$countStmt->execute($params);
$total = (int)$countStmt->fetch()['total'];
$totalPages = max(1, (int)ceil($total / $limit));

$stmt = $pdo->prepare("
    SELECT a.*
    FROM announcements a
    $whereSql
    ORDER BY a.id DESC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);
$announcements = $stmt->fetchAll();

$categoryMap = [];
$announcementIds = array_column($announcements, 'id');

if($announcementIds){

    $placeholders = implode(',', array_fill(0, count($announcementIds), '?'));

    $catStmt = $pdo->prepare("
        SELECT
            r.announcement_id,
            c.name
        FROM announcement_category_rel r
        INNER JOIN announcement_categories c ON r.category_id = c.id
        WHERE r.announcement_id IN ($placeholders)
        ORDER BY c.name ASC
    ");

    $catStmt->execute($announcementIds);

    foreach($catStmt->fetchAll() as $row){

        $categoryMap[$row['announcement_id']][] = $row['name'];

    }

}

require 'includes/header.php';

?>

<style>
.page-box{max-width:1100px;margin:auto;}
.page-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    flex-wrap:wrap;
    margin-bottom:20px;
}
.page-title{font-size:28px;font-weight:900;color:#0f172a;}
.page-subtitle{color:#64748b;font-size:14px;margin-top:8px;}
.card{
    background:white;
    border-radius:24px;
    padding:22px;
    margin-bottom:20px;
    box-shadow:0 10px 35px rgba(15,23,42,.05);
    border:1px solid #eef2f7;
}
.search-form{
    display:grid;
    grid-template-columns:1fr auto;
    gap:10px;
    align-items:center;
}
.announcement-list{
    display:grid;
    gap:18px;
}
.announcement-card{
    display:grid;
    grid-template-columns:260px 1fr;
    gap:20px;
    background:white;
    border-radius:26px;
    overflow:hidden;
    border:1px solid #eef2f7;
    box-shadow:0 10px 35px rgba(15,23,42,.05);
}
.announcement-image-wrap{
    background:#eff6ff;
    min-height:210px;
}
.announcement-image{
    width:100%;
    height:100%;
    min-height:210px;
    object-fit:cover;
    display:block;
}
.announcement-placeholder{
    height:100%;
    min-height:210px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:52px;
    color:#0284c7;
}
.announcement-content{
    padding:22px 22px 22px 0;
    display:flex;
    flex-direction:column;
    gap:14px;
}
.announcement-title{
    font-size:21px;
    font-weight:900;
    color:#0f172a;
    line-height:36px;
}
.announcement-summary{
    color:#475569;
    line-height:32px;
    font-size:14px;
}
.announcement-meta{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
    color:#64748b;
    font-size:13px;
}
.category-badge{
    display:inline-flex;
    padding:6px 12px;
    border-radius:999px;
    background:#eff6ff;
    color:#0369a1;
    font-size:12px;
    font-weight:800;
}
.read-more{
    align-self:flex-start;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:125px;
    padding:12px 18px;
    border-radius:16px;
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    color:#fff;
    text-decoration:none;
    font-size:13px;
    font-weight:900;
}
.pagination{
    display:flex;
    justify-content:center;
    gap:8px;
    flex-wrap:wrap;
    margin-top:24px;
}
.page-link{
    min-width:42px;
    height:42px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:14px;
    background:white;
    border:1px solid #dbeafe;
    color:#0284c7;
    text-decoration:none;
    font-weight:900;
}
.active-page{
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    color:white;
    border:none;
}
.empty-box{
    text-align:center;
    padding:42px;
    color:#64748b;
    font-weight:800;
}
@media(max-width:768px){
    .search-form{grid-template-columns:1fr;}
    .announcement-card{grid-template-columns:1fr;}
    .announcement-content{padding:20px;}
    .announcement-image-wrap,
    .announcement-image,
    .announcement-placeholder{min-height:180px;}
}
</style>

<div class="page-box">

<div class="page-head">
    <div>
        <div class="page-title">📰 اطلاعیه ها</div>
        <div class="page-subtitle">آخرین اطلاعیه‌های منتشر شده سامانه</div>
    </div>
</div>

<div class="card">
<form method="GET" class="search-form">
    <input
    type="text"
    name="search"
    class="form-control"
    placeholder="جستجو در عنوان، خلاصه یا متن اطلاعیه"
    value="<?= htmlspecialchars($search) ?>">

    <button type="submit" class="btn-custom">جستجو</button>
</form>
</div>

<?php if(count($announcements)): ?>

<div class="announcement-list">
<?php foreach($announcements as $item): ?>
<?php
$categories = $categoryMap[$item['id']] ?? [];
?>
<article class="announcement-card">
    <div class="announcement-image-wrap">
        <?php if(!empty($item['image'])): ?>
        <img
        src="uploads/<?= htmlspecialchars($item['image']) ?>"
        class="announcement-image"
        alt="<?= htmlspecialchars($item['title']) ?>">
        <?php else: ?>
        <div class="announcement-placeholder">📢</div>
        <?php endif; ?>
    </div>

    <div class="announcement-content">
        <div class="announcement-meta">
            <span>🕒 <?= fa_datetime($item['created_at']) ?></span>
            <?php foreach($categories as $category): ?>
            <span class="category-badge"><?= htmlspecialchars($category) ?></span>
            <?php endforeach; ?>
        </div>

        <h2 class="announcement-title">
            <?= htmlspecialchars($item['title']) ?>
        </h2>

        <?php if(!empty($item['summary'])): ?>
        <div class="announcement-summary">
            <?= nl2br(htmlspecialchars($item['summary'])) ?>
        </div>
        <?php endif; ?>

        <a
        href="announcement-view.php?id=<?= $item['id'] ?>"
        class="read-more">
            متن کامل
        </a>
    </div>
</article>
<?php endforeach; ?>
</div>

<?php if($totalPages > 1): ?>
<div class="pagination">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<?php
$pageQuery = $_GET;
$pageQuery['page'] = $i;
?>
<a
href="?<?= htmlspecialchars(http_build_query($pageQuery)) ?>"
class="page-link <?= $page==$i ? 'active-page' : '' ?>">
<?= $i ?>
</a>
<?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>

<div class="card empty-box">اطلاعیه ای وجود ندارد</div>

<?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
