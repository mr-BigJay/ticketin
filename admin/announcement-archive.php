<?php

require '../includes/auth.php';
require '../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

if(isset($_GET['restore'])){

    $id = (int)$_GET['restore'];

    $stmt = $pdo->prepare("
        UPDATE announcements
        SET is_archived=0
        WHERE id=?
    ");

    $stmt->execute([$id]);

    header(
        "Location: announcement-archive.php"
    );

    exit;

}

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
"is_archived=1";

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

require '../includes/header.php';

?>

<style>

.page-box{

    max-width:1100px;

    margin:auto;

}

.page-title{

    font-size:26px;

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

    background:#f8fafc;

    border-radius:22px;

    overflow:hidden;

    margin-bottom:18px;

}

.news-image{

    width:100%;

    height:220px;

    object-fit:cover;

}

.news-content{

    padding:20px;

}

.news-title{

    font-size:20px;

    font-weight:bold;

    margin-bottom:12px;

}

.news-summary{

    color:#64748b;

    line-height:34px;

    font-size:14px;

}

.news-meta{

    margin-top:14px;

    color:#94a3b8;

    font-size:13px;

}

.news-actions{

    margin-top:18px;

}

.btn{

    display:inline-block;

    text-decoration:none;

    padding:10px 14px;

    border-radius:12px;

    color:white;

    font-size:13px;

    font-weight:bold;

}

.btn-restore{

    background:#10b981;

}

.pagination{

    margin-top:25px;

    text-align:center;

}

.page-link{

    display:inline-block;

    background:white;

    padding:10px 14px;

    border-radius:12px;

    margin:4px;

    text-decoration:none;

    color:#333;

    box-shadow:0 0 10px rgba(0,0,0,0.05);

}

.active-page{

    background:#2563eb;

    color:white;

}

.empty-box{

    text-align:center;

    padding:35px;

    color:#777;

}

</style>

<div class="page-box">

<div style="margin-bottom:20px;">

<a
href="javascript:history.back()"
class="back-btn-top">

← بازگشت

</a>

</div>

<div class="page-title">

🗃 بایگانی اطلاعیه ها

</div>

<div class="card">

<form method="GET">

<input
type="text"
name="search"
class="form-control"
placeholder="جستجوی اطلاعیه بایگانی شده"
value="<?= $_GET['search'] ?? '' ?>">

<button
type="submit"
class="btn-custom">

جستجو

</button>

</form>

</div>

<div class="card">

<?php if(count($announcements)): ?>

<?php foreach($announcements as $item): ?>

<div class="news-card">

<?php if($item['image']): ?>

<img
src="../uploads/<?= $item['image'] ?>"
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

🕒 <?= htmlspecialchars($item['created_at']) ?>

</div>

<div class="news-actions">

<a
href="?restore=<?= $item['id'] ?>"
class="btn btn-restore">

بازگردانی اطلاعیه

</a>

</div>

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

اطلاعیه بایگانی شده ای وجود ندارد

</div>

<?php endif; ?>

</div>

</div>

<?php include '../includes/footer.php'; ?>
