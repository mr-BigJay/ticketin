<?php

require '../includes/auth.php';
require '../includes/db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

$page_title = '✅ تیکت های رفع شده';
$back_url = 'index.php';

if(isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'reopen'){

    $id = (int)$_GET['id'];

    if($id){

        $stmt = $pdo->prepare("
            UPDATE tickets
            SET status='open', closed_at=NULL
            WHERE id=?
        ");

        $stmt->execute([$id]);

    }

    header("Location: closed-tickets.php");
    exit;

}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1){ $page = 1; }

$limit = 20;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

$where = ["t.status='closed'"];
$params = [];

if($search){

    $where[] = "(t.title LIKE ? OR t.tracking_code LIKE ? OR u.fullname LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

}

if($category){

    $where[] = "t.category=?";
    $params[] = $category;

}

$whereSql = "WHERE " . implode(" AND ", $where);

$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    $whereSql
");

$countStmt->execute($params);
$total = $countStmt->fetch()['total'];
$totalPages = max(1, (int)ceil($total / $limit));

$stmt = $pdo->prepare("
    SELECT t.*, u.fullname
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    $whereSql
    ORDER BY t.closed_at DESC, t.id DESC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);
$tickets = $stmt->fetchAll();

$categories = $pdo->query("
    SELECT DISTINCT category
    FROM tickets
    WHERE category IS NOT NULL AND category != ''
    ORDER BY category ASC
")->fetchAll();

require '../includes/header.php';

?>

<style>
.page-box{max-width:1100px;margin:auto;}
.page-title{font-size:26px;font-weight:800;margin-bottom:20px;}
.card{background:white;border-radius:24px;padding:22px;margin-bottom:20px;box-shadow:0 0 20px rgba(0,0,0,.05);}
.filter-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;}
.ticket-row{
    background:#fff;
    border:1px solid #eef2f7;
    border-radius:24px;
    padding:20px;
    margin-bottom:16px;
    box-shadow:0 8px 30px rgba(15,23,42,.05);
}
.ticket-top{
    display:flex;
    justify-content:center;
    align-items:center;
    flex-wrap:wrap;
    gap:14px;
    margin-bottom:18px;
    color:#64748b;
    font-size:13px;
}
.tracking-code{
    background:#eff6ff;
    color:#1d4ed8;
    padding:8px 14px;
    border-radius:999px;
    font-size:14px;
    font-weight:800;
    border:1px solid #bfdbfe;
}
.ticket-title-box{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:18px;
    padding:16px;
    min-height:72px;
    display:flex;
    align-items:center;
    font-size:15px;
    font-weight:700;
    color:#0f172a;
    line-height:32px;
    margin-bottom:18px;
}
.ticket-bottom{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}
.status-ticket{
    display:inline-block;
    padding:8px 14px;
    border-radius:999px;
    background:#111827;
    color:white;
    font-size:12px;
    font-weight:700;
}
.user-badge{
    display:inline-flex;
    align-items:center;
    gap:8px;
    background:#f1f5f9;
    color:#334155;
    padding:9px 14px;
    border-radius:999px;
    font-size:13px;
    font-weight:800;
}
.ticket-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.ticket-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    color:white;
    padding:10px 16px;
    border-radius:14px;
    font-size:13px;
    font-weight:700;
}
.reopen-btn{background:#10b981;}
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
    .ticket-bottom{align-items:stretch;flex-direction:column;}
    .ticket-actions{flex-direction:column;}
    .ticket-btn{width:100%;}
}
</style>

<div class="page-box">

<div class="page-title">✅ تیکت های رفع شده</div>

<div class="card">
<form method="GET">
    <div class="filter-grid">
        <input
        type="text"
        name="search"
        class="form-control"
        placeholder="جستجوی عنوان، شماره پیگیری یا کاربر"
        value="<?= htmlspecialchars($search) ?>">

        <select name="category" class="form-control">
            <option value="">همه دسته بندی ها</option>
            <?php foreach($categories as $cat): ?>
            <option
            value="<?= htmlspecialchars($cat['category']) ?>"
            <?= $category === $cat['category'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['category']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn-custom">فیلتر تیکت ها</button>
</form>
</div>

<div class="card">

<?php if(count($tickets)): ?>

<?php foreach($tickets as $ticket): ?>

<div class="ticket-row">
    <div class="ticket-top">
        <span class="tracking-code">
            <?= htmlspecialchars($ticket['tracking_code']) ?>
        </span>
        <span>
            🕒 ثبت: <?= fa_datetime($ticket['created_at']) ?>
        </span>
        <span>
            ✅ بسته شده: <?= fa_datetime($ticket['closed_at']) ?>
        </span>
        <span>
            📂 <?= htmlspecialchars($ticket['category']) ?>
        </span>
    </div>

    <div class="ticket-title-box">
        <?= htmlspecialchars($ticket['title']) ?>
    </div>

    <div class="ticket-bottom">
        <div>
            <span class="status-ticket">بسته</span>
            <span class="user-badge">
                👤 <?= htmlspecialchars($ticket['fullname'] ?? '-') ?>
            </span>
        </div>

        <div class="ticket-actions">
            <a href="view-ticket.php?id=<?= $ticket['id'] ?>" class="ticket-btn">مشاهده</a>
            <a href="?action=reopen&id=<?= $ticket['id'] ?>" class="ticket-btn reopen-btn">بازگشایی</a>
        </div>
    </div>
</div>

<?php endforeach; ?>

<?php if($totalPages > 1): ?>
<div class="pagination">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<?php
$query = $_GET;
$query['page'] = $i;
?>
<a
href="?<?= htmlspecialchars(http_build_query($query)) ?>"
class="page-link <?= $page==$i ? 'active-page' : '' ?>">
<?= $i ?>
</a>
<?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>

<div class="empty-box">تیکت بسته شده ای وجود ندارد</div>

<?php endif; ?>

</div>
</div>

<?php include '../includes/footer.php'; ?>
