<?php

require '../includes/auth.php';
require '../includes/db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    die("دسترسی غیر مجاز");
}
if(
isset($_GET['action'])
&&
isset($_GET['id'])
){

    $id = (int)$_GET['id'];

    if($_GET['action']=='close'){

        $stmt = $pdo->prepare("
            UPDATE tickets
            SET
            status='closed',
            closed_at=NOW()
            WHERE id=?
        ");

        $stmt->execute([$id]);

    }

    if($_GET['action']=='pending'){

        $stmt = $pdo->prepare("
            UPDATE tickets
            SET status='pending'
            WHERE id=?
        ");

        $stmt->execute([$id]);

    }

    header("Location: tickets.php");

    exit;

}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1){ $page = 1; }

$limit = 20;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

$where[] = "t.status != 'closed'";

if(!empty($_GET['status'])){
    if(in_array($_GET['status'], ['admin_reply','user_reply'], true)){
        $where[] = "t.last_reply_by=?";
    }else{
        $where[] = "t.status=?";
    }
    $params[] = $_GET['status'];
}

if(!empty($_GET['category'])){
    $where[] = "t.category=?";
    $params[] = $_GET['category'];
}

if(!empty($_GET['search'])){
    $where[] = "(t.title LIKE ? OR t.tracking_code LIKE ? OR u.fullname LIKE ?)";
    $search = "%" . $_GET['search'] . "%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$whereSql = "WHERE " . implode(" AND ",$where);

$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    $whereSql
");

$countStmt->execute($params);
$total = $countStmt->fetch()['total'];
$totalPages = ceil($total / $limit);

$stmt = $pdo->prepare("
    SELECT t.*, u.fullname
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    $whereSql
    ORDER BY t.id DESC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);
$tickets = $stmt->fetchAll();

require '../includes/header.php';
?>

<style>
.page-box{max-width:1100px;margin:auto;}
.page-title{font-size:26px;font-weight:800;margin-bottom:20px;}
.card{background:white;border-radius:24px;padding:22px;margin-bottom:20px;box-shadow:0 0 20px rgba(0,0,0,.05);}
.filter-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}


.ticket-title{
    font-size:15px;
    font-weight:800;
    line-height:28px;
    color:#0f172a;
    margin-bottom:10px;
}

.ticket-meta{
    color:#64748b;
    line-height:28px;
    font-size:13px;
}

.status{
    display:inline-block;
    padding:8px 14px;
    border-radius:30px;
    color:white;
    font-size:12px;
    margin-top:14px;
    font-weight:700;
}

.open{background:#2563eb;}
.pending{background:#f59e0b;}
.admin_reply{background:#0f766e;}
.user_reply{background:#7c3aed;}

.ticket-actions{margin-top:16px;}

.ticket-btn{
    display:block;
    width:100%;
    text-align:center;
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    color:white;
    text-decoration:none;
    padding:12px;
    border-radius:14px;
    font-size:13px;
    font-weight:700;
}

.pagination{text-align:center;margin-top:25px;}
.page-link{
    display:inline-block;
    background:white;
    padding:10px 14px;
    border-radius:12px;
    margin:4px;
    text-decoration:none;
    color:#333;
    box-shadow:0 0 10px rgba(0,0,0,.05);
}
.active-page{background:#2563eb;color:white;}
.empty-box{text-align:center;padding:35px;color:#777;}

@media(max-width:768px){
    .filter-grid{grid-template-columns:1fr;}
    .ticket-card{padding:14px;}
    .ticket-title{font-size:14px;line-height:26px;}
    .ticket-meta{font-size:12px;line-height:24px;}
    .ticket-btn{padding:11px;}
}


.menu-btn{

    position:relative;

    z-index:10001;

    border:none;

    background:none;

    font-size:24px;

    cursor:pointer;

}

.dropdown{

    position:relative;

}

.dropdown-menu{

    display:none;

    position:absolute;

    left:0;

    top:35px;

    background:white;

    min-width:180px;

    border-radius:12px;

    box-shadow:0 8px 30px rgba(0,0,0,.15);

    overflow:hidden;

    z-index:9999;

}

.dropdown-menu.show{

    display:block;

}

.dropdown-menu.show{

    display:block;

}

.dropdown-menu a{

    display:block;

    padding:12px;

    color:#333;

    text-decoration:none;

}

.dropdown-menu a:hover{

    background:#f1f5f9;

}

.status-ticket,
.status-reply{

    display:inline-block;

    padding:6px 12px;

    border-radius:999px;

    color:#fff;

    font-size:12px;

    font-weight:700;

    margin-left:6px;

}

.open{
    background:#2563eb;
}

.pending{
    background:#f59e0b;
}

.closed{
    background:#111827;
}

.admin_reply{
    background:#16a34a;
}

.user_reply{
    background:#dc2626;
}
.ticket-row{

    position:relative;

    background:#fff;

    border-radius:24px;

    padding:22px;

    margin-bottom:16px;

    border:1px solid #eef2f7;

    box-shadow:0 8px 30px rgba(15,23,42,.05);

}

.ticket-menu{

    position:absolute;

    top:12px;

    left:12px;

    z-index:10000;

}

.ticket-top{

    display:flex;

    justify-content:center;

    align-items:center;

    gap:14px;

    flex-wrap:wrap;

    margin-bottom:16px;

    color:#64748b;

    font-size:13px;

}

.tracking-code{

    background:#eff6ff;

    color:#2563eb;

    border:1px solid #bfdbfe;

    border-radius:999px;

    padding:6px 14px;

    font-weight:800;

}

.ticket-title-box{

    background:#f8fafc;

    border:1px solid #e2e8f0;

    border-radius:18px;

    padding:16px;

    min-height:72px;

    margin-bottom:16px;

    font-weight:700;

    line-height:30px;

}

.ticket-bottom{

    display:flex;

    justify-content:space-between;

    align-items:center;

}

.ticket-statuses{

    display:flex;

    gap:8px;

    flex-wrap:wrap;

}

.ticket-user{

    font-weight:700;

    color:#334155;

}

.pagination{

    display:flex;

    justify-content:center;

    gap:8px;

    margin-top:20px;

}

.page-link{

    min-width:42px;

    height:42px;

    display:flex;

    align-items:center;

    justify-content:center;

    text-decoration:none;

    border-radius:14px;

    background:#f8fafc;

    color:#334155;

    border:1px solid #e2e8f0;

}

.active-page{

    background:linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    color:white;

    border:none;

}
.user-badge{

    display:inline-flex;

    align-items:center;

    gap:8px;

    padding:10px 18px;

    border-radius:999px;

    background:linear-gradient(
        180deg,
        #f8fafc,
        #eef6ff
    );

    border:1px solid #bfdbfe;

    box-shadow:
    inset 0 1px 0 rgba(255,255,255,.8),
    0 4px 12px rgba(37,99,235,.08);

    color:#111827;

    font-size:13px;

    font-weight:800;

    white-space:nowrap;

}

</style>

<div class="page-box">

<div style="margin-bottom:20px;">
<a href="javascript:history.back()" class="back-btn-top">← بازگشت</a>
</div>

<div class="page-title">🎫 تیکت های جاری</div>

<div class="card">
<form method="GET">

<div class="filter-grid">

<input type="text" name="search" class="form-control" placeholder="جستجوی عنوان، شماره پیگیری یا کاربر" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

<select name="category" class="form-control">
<option value="">همه دسته بندی ها</option>
<?php
$cats = $pdo->query("SELECT DISTINCT category FROM tickets WHERE category IS NOT NULL")->fetchAll();
foreach($cats as $cat):
?>
<option
value="<?= htmlspecialchars($cat['category']) ?>"
<?= (($_GET['category'] ?? '') === $cat['category']) ? 'selected' : '' ?>>
<?= htmlspecialchars($cat['category']) ?>
</option>
<?php endforeach; ?>
</select>

<select name="status" class="form-control">
<option value="">همه وضعیت ها</option>
<option value="open" <?= (($_GET['status'] ?? '') === 'open') ? 'selected' : '' ?>>باز</option>
<option value="pending" <?= (($_GET['status'] ?? '') === 'pending') ? 'selected' : '' ?>>درحال بررسی</option>
<option value="admin_reply" <?= (($_GET['status'] ?? '') === 'admin_reply') ? 'selected' : '' ?>>پاسخ ادمین</option>
<option value="user_reply" <?= (($_GET['status'] ?? '') === 'user_reply') ? 'selected' : '' ?>>پاسخ کاربر</option>
</select>

</div>

<button type="submit" class="btn-custom">فیلتر تیکت ها</button>
</form>
</div>

<div class="card">

<?php if(count($tickets)): ?>

<?php foreach($tickets as $ticket): ?>

<div class="ticket-row">

    <div class="ticket-menu">

        <button
        type="button"
        class="menu-btn"
        onclick="toggleMenu(this)">

            ⋮

        </button>

        <div class="dropdown-menu">

            <a
            href="view-ticket.php?id=<?= $ticket['id'] ?>">

                پاسخ

            </a>

            <a
            href="?action=pending&id=<?= $ticket['id'] ?>">

                درحال بررسی

            </a>

            <a
            href="?action=close&id=<?= $ticket['id'] ?>">

                بستن تیکت

            </a>

        </div>

    </div>

    <div class="ticket-top">

        <span class="tracking-code">

            <?= $ticket['tracking_code'] ?>

        </span>

        <span>

            🕒 <?= fa_datetime($ticket['created_at']) ?>

        </span>

        <span>

            📂 <?= htmlspecialchars($ticket['category']) ?>

        </span>

    </div>

    <div class="ticket-title-box">

        <?= htmlspecialchars($ticket['title']) ?>

    </div>

    <?php

    $statusText = [

        'open'    => 'باز',
        'pending' => 'درحال بررسی',
        'closed'  => 'بسته'

    ];

    $replyText = [

        'admin_reply' => 'پاسخ ادمین',
        'user_reply'  => 'پاسخ کاربر'

    ];

    ?>

    <div class="ticket-bottom">

        <div class="ticket-statuses">

            <span
            class="status-ticket <?= $ticket['status'] ?>">

                <?= $statusText[$ticket['status']] ?? '-' ?>

            </span>

            <span
            class="status-reply <?= $ticket['last_reply_by'] ?>">

                <?= $replyText[$ticket['last_reply_by']] ?? '-' ?>

            </span>

        </div>

<div class="user-badge">

    👤

    <?= htmlspecialchars($ticket['fullname']) ?>

</div>

    </div>

</div>
<?php endforeach; ?>

<div class="pagination">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<a href="?page=<?= $i ?>" class="page-link <?= $page==$i ? 'active-page' : '' ?>">
<?= $i ?>
</a>
<?php endfor; ?>
</div>

<?php else: ?>

<div class="empty-box">
تیکت جاری وجود ندارد
</div>

<?php endif; ?>

</div>
</div>
<script>

function toggleMenu(btn){

    const menu = btn.nextElementSibling;

    document
    .querySelectorAll('.dropdown-menu')
    .forEach(item => {

        if(item !== menu){

            item.classList.remove('show');

        }

    });

    menu.classList.toggle('show');

}

document.addEventListener('click', function(e){

    if(!e.target.closest('.dropdown-menu') &&
       !e.target.closest('.menu-btn')){

        document
        .querySelectorAll('.dropdown-menu')
        .forEach(menu => {

            menu.classList.remove('show');

        });

    }

});

</script>
<?php include '../includes/footer.php'; ?>
