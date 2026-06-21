<?php

require 'includes/auth.php';
require 'includes/db.php';

$page_title = '📰 اطلاعیه ها';
$back_url = 'dashboard.php';

$page =
isset($_GET['page'])
? (int)$_GET['page']
: 1;

if($page < 1){

    $page = 1;

}

$limit = 10;

$offset =
($page - 1) * $limit;

$where = [];

$params = [];

$where[] =
"is_archived=0";

if(!empty($_GET['search'])){

    $where[] =
    "(title LIKE ? OR summary LIKE ?)";

    $search =
    "%" . $_GET['search'] . "%";

    $params[] = $search;
    $params[] = $search;

}

$whereSql =
"WHERE " .
implode(" AND ",$where);

$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM announcements
    $whereSql
");

$countStmt->execute($params);

$total =
$countStmt->fetch()['total'];

$totalPages =
ceil($total / $limit);

$stmt = $pdo->prepare("
    SELECT *
    FROM announcements

    $whereSql

    ORDER BY id DESC

    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);

$announcements =
$stmt->fetchAll();

require 'includes/header.php';

?>

<style>

.page-box{

    max-width:1100px;

    margin:auto;

}

.page-title{

    font-size:28px;

    font-weight:bold;

    margin-bottom:20px;

}

.card{

    background:white;

    border-radius:24px;

    padding:22px;

    margin-bottom:20px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

}

.news-card{

    background:white;

    border-radius:24px;

    overflow:hidden;

    margin-bottom:22px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

}

.news-image{

    width:100%;

    height:240px;

    object-fit:cover;

}

.news-content{

    padding:22px;

}

.news-title{

    font-size:22px;

    font-weight:bold;

    margin-bottom:14px;

    line-height:42px;

}

.news-summary{

    color:#64748b;

    line-height:34px;

    font-size:15px;

}

.news-meta{

    margin-top:14px;

    color:#94a3b8;

    font-size:13px;

}

.read-more{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    min-width:120px;

    padding:12px 20px;

    border-radius:16px;

    background:linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    color:#fff;

    text-decoration:none;

    font-size:13px;

    font-weight:800;

    box-shadow:0 8px 20px rgba(2,132,199,.18);

    transition:.2s;

}

.read-more:hover{

    transform:translateY(-2px);

    box-shadow:0 12px 24px rgba(2,132,199,.25);

}

.read-more:active{

    transform:translateY(0);

}

.pagination{

    margin-top:25px;

    text-align:center;

}

/* Pagination */
.pagination {
    margin-top: 25px;
    text-align: center;
}

.page-link {
    display: inline-block;
    background: #e0f2fe; /* رنگ هماهنگ با تم سایت */
    padding: 10px 14px;
    border-radius: 12px;
    margin: 4px;
    text-decoration: none;
    color: #0284c7;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: 0.2s;
}

.page-link:hover {
    background: #0284c7;
    color: white;
    transform: translateY(-2px);
}

.active-page {
    background: #0284c7;
    color: white;
    font-weight: 800;
}

/* دکمه اطلاعیه ها و تیکت های بسته شده */
.btn-custom, .ticket-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 20px;
    border-radius: 18px;
    font-weight: 700;
    font-family: 'Vazirmatn', sans-serif;
    transition: 0.2s;
}

.ticket-btn {
    background: #0284c7;
    color: white;
    text-decoration: none;
}

.ticket-btn:hover, .btn-custom:hover {
    opacity: 0.95;
    transform: translateY(-2px);
}

/* مخصوص متن کامل اطلاعیه */
.announcement-btn {
    background: #0284c7;
    color: white;
    padding: 12px 20px;
    border-radius: 18px;
    font-weight: 700;
    text-decoration: none;
    display: inline-block;
    transition: 0.2s;
}

.announcement-btn:hover {
    opacity: 0.95;
    transform: translateY(-2px);
}
}

.empty-box{

    text-align:center;

    padding:35px;

    color:#777;

}
.announcement-meta{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:12px;

    margin-top:10px;

    color:#64748b;

    font-size:13px;

}

</style>

<div class="page-box">

<div style="margin-bottom:20px;">

</div>


<div class="card">

<form method="GET">

<input
type="text"
name="search"
class="form-control"
placeholder="جستجوی اطلاعیه"
value="<?= $_GET['search'] ?? '' ?>">

<button
type="submit"
class="btn-custom">

جستجو

</button>

</form>

</div>

<?php if(count($announcements)): ?>

<?php foreach($announcements as $item): ?>

<div class="news-card">

<?php if(!empty($item['image'])): ?>

<img
src="uploads/<?= htmlspecialchars($item['image']) ?>"
class="news-image">

<?php endif; ?>

<div class="news-content">

<div class="news-title">

<?= htmlspecialchars($item['title']) ?>

</div>

<div class="news-summary">

<?= nl2br(
htmlspecialchars(
$item['summary']
)
) ?>

</div>

<div class="news-meta">

🕒 <?= fa_datetime($item['created_at']) ?>

</div>

<a
href="announcement-view.php?id=<?= $item['id'] ?>"
class="read-more">

متن کامل

</a>

</div>

</div>

<?php endforeach; ?>

<div class="pagination">

<?php for($i=1;$i<=$totalPages;$i++): ?>

<a
href="?page=<?= $i ?>"
class="page-link <?= $page==$i ? 'active-page' : '' ?>">

<?= $i ?>

</a>

<?php endfor; ?>

</div>

<?php else: ?>

<div class="empty-box">

اطلاعیه ای وجود ندارد

</div>

<?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
