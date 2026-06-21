<?php
require '../includes/jalali.php';
require '../includes/auth.php';
require '../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

if(isset($_GET['archive'])){

    $id =
    (int)$_GET['archive'];

    $stmt = $pdo->prepare("
        UPDATE announcements
        SET is_archived=1
        WHERE id=?
    ");

    $stmt->execute([$id]);

    header(
        "Location: /admin/announcement-list.php"
    );

    exit;

}

if(isset($_GET['delete'])){

    $id =
    (int)$_GET['delete'];

    $stmt = $pdo->prepare("
        DELETE FROM announcements
        WHERE id=?
    ");

    $stmt->execute([$id]);

    header(
        "Location: /admin/announcement-list.php"
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

$items =
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

    overflow:visible;

    margin-bottom:18px;

    display:flex;

    gap:18px;

    align-items:flex-start;

    position:relative;

}

.news-image{

    width:220px;

    height:180px;

    object-fit:cover;

    flex-shrink:0;

    border-radius:18px;

}

.news-content{

    padding:18px;

    flex:1;

}

.news-title{

    font-size:20px;

    font-weight:bold;

    margin-bottom:10px;

}

.news-summary{

    color:#64748b;

    line-height:34px;

    font-size:14px;

}

.news-date{

    margin-top:12px;

    font-size:13px;

    color:#94a3b8;

}

.dropdown{

    position:absolute;

    top:14px;

    left:14px;

    z-index:9999;

}

.dropdown-btn{

    width:42px;

    height:42px;

    border:none;

    border-radius:14px;

    background:white;

    cursor:pointer;

    font-size:20px;

    box-shadow:0 0 10px rgba(0,0,0,0.08);

}

.dropdown-menu{

    position:absolute;

    top:50px;

    left:0;

    background:white;

    border-radius:14px;

    min-width:140px;

    overflow:hidden;

    box-shadow:0 0 20px rgba(0,0,0,0.08);

    display:none;

    z-index:99999;

}

.dropdown-menu a{

    display:block;

    padding:12px;

    text-decoration:none;

    color:#333;

    font-size:13px;

    border-bottom:1px solid #f1f5f9;

}

.dropdown-menu a:last-child{

    border-bottom:none;

}

.dropdown-menu a:hover{

    background:#f8fafc;

}

.show-dropdown{

    display:block;

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

@media(max-width:768px){

    .news-card{

        flex-direction:column;

    }

    .news-image{

        width:100%;

        height:220px;

    }

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

📰 لیست اطلاعیه ها

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

<div class="card">

<?php if(count($items)): ?>

<?php foreach($items as $item): ?>

<div class="news-card">

<?php if(!empty($item['image'])): ?>

<img
src="/uploads/<?= htmlspecialchars($item['image']) ?>"
class="news-image">

<?php endif; ?>

<div class="news-content">

<div class="news-title">

<?= htmlspecialchars($item['title']) ?>

</div>

<div class="news-summary">

<?= mb_substr(
strip_tags(
$item['summary']
),
0,
180
) ?>

...

</div>

<div class="news-date">

🕒

<?= jalali_date(
$item['created_at']
) ?>

</div>

</div>

<div class="dropdown">

<button
type="button"
class="dropdown-btn">

⋮

</button>

<div class="dropdown-menu">

<a
href="/admin/announcement-create.php?edit=<?= $item['id'] ?>">

✏️ ویرایش

</a>

<a
href="/admin/announcement-list.php?archive=<?= $item['id'] ?>">

🗃 بایگانی

</a>

<a
href="/admin/announcement-list.php?delete=<?= $item['id'] ?>"
onclick="return confirm('حذف شود؟')">

🗑 حذف

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

اطلاعیه ای ثبت نشده

</div>

<?php endif; ?>

</div>

</div>

<script>

window.addEventListener(
    'DOMContentLoaded',
    function(){

        document
        .querySelectorAll(
            '.dropdown-btn'
        )
        .forEach(btn => {

            btn.addEventListener(
                'click',
                function(e){

                    e.stopPropagation();

                    document
                    .querySelectorAll(
                        '.dropdown-menu'
                    )
                    .forEach(menu => {

                        if(
                            menu !==
                            this.nextElementSibling
                        ){

                            menu.classList.remove(
                                'show-dropdown'
                            );

                        }

                    });

                    this
                    .nextElementSibling
                    .classList
                    .toggle(
                        'show-dropdown'
                    );

                }
            );

        });

        document.addEventListener(
            'click',
            function(){

                document
                .querySelectorAll(
                    '.dropdown-menu'
                )
                .forEach(menu => {

                    menu.classList.remove(
                        'show-dropdown'
                    );

                });

            }
        );

    }
);

</script>

<?php include '../includes/footer.php'; ?>
