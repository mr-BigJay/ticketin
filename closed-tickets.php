<?php
require 'includes/auth.php';
require 'includes/db.php';

// عنوان صفحه
$page_title = '📦 تیکت های بسته شده';
$back_url = 'dashboard.php';

// تعیین آدرس بازگشت
$back_url = $_GET['back'] ?? $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';

// ادامه کد برای جستجو و گرفتن تیکت‌ها
$user_id = $_SESSION['user_id'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page-1)*$limit;

$search = trim($_GET['search'] ?? '');
$where = "WHERE user_id=? AND status='closed' AND closed_at IS NOT NULL";
$params = [$user_id];

if($search){
    $where .= " AND (title LIKE ? OR message LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM tickets $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['total'];
$totalPages = ceil($total / $limit);

// Tickets
$stmt = $pdo->prepare("
    SELECT * FROM tickets
    $where
    ORDER BY closed_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$tickets = $stmt->fetchAll();

require 'includes/header.php';
?>



<!-- ادامه کارت‌ها و جستجو مثل قبل -->

<style>

.page-box{

    max-width:1000px;

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

.ticket-card{

    background:#f8fafc;

    border-radius:20px;

    padding:18px;

    margin-bottom:16px;

}

.ticket-card{

    background:#fff;

    border-radius:24px;

    padding:22px;

    margin-bottom:18px;

    border:1px solid #eef2f7;

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

    justify-content:flex-start;

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

}

.ticket-statuses{

    display:flex;

    gap:8px;

}

.status{

    display:inline-block;

    padding:8px 14px;

    border-radius:999px;

    color:#fff;

    font-size:12px;

    font-weight:700;

}

.closed-ticket{

    background:#374151;

}

.ticket-btn{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:6px;

    background:#f8fafc;

    color:#334155;

    text-decoration:none;

    padding:11px 18px;

    border-radius:14px;

    border:1px solid #e2e8f0;

    font-size:13px;

    font-weight:700;

    transition:.2s;

}

.ticket-btn:hover{

    background:#eff6ff;

    border-color:#bfdbfe;

    color:#0369a1;

}

.pagination{

    margin-top:30px;

    text-align:center;

}

.page-link{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    width:42px;

    height:42px;

    margin:0 4px;

    border-radius:14px;

    text-decoration:none;

    font-weight:700;

    background:#ffffff;

    color:#0284c7;

    border:1px solid #dbeafe;

    box-shadow:0 4px 12px rgba(2,132,199,.08);

    transition:.2s;

}

.page-link:hover{

    background:#eff6ff;

    border-color:#93c5fd;

    transform:translateY(-2px);

}

.active-page{

    background:linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    color:white;

    border-color:transparent;

    box-shadow:0 8px 20px rgba(2,132,199,.25);

}

.empty-box{

    text-align:center;

    padding:35px;

    color:#777;

}

</style>

<div class="page-box">

<div class="card">

<form method="GET">

<input
type="text"
name="search"
class="form-control"
placeholder="جستجوی تیکت"
value="<?= $_GET['search'] ?? '' ?>">

<button
type="submit"
class="btn-custom">

جستجو

</button>

</form>

</div>

<div class="card">

<?php if(count($tickets)): ?>


<?php foreach($tickets as $ticket): ?>

<div class="ticket-card">

    <div class="ticket-top">

        <span class="tracking-code">

            <?= $ticket['tracking_code'] ?>

        </span>

        <span>

            📂 <?= htmlspecialchars($ticket['category']) ?>

        </span>

        <span>

            🕒 <?= fa_datetime($ticket['closed_at']) ?>

        </span>

    </div>

    <div class="ticket-title-box">

        <?= htmlspecialchars($ticket['title']) ?>

    </div>

    <div class="ticket-bottom">

        <div class="ticket-statuses">

            <span class="status closed-ticket">

                بسته شده

            </span>

        </div>

        <a
        href="view-ticket.php?id=<?= $ticket['id'] ?>"
        class="ticket-btn">

            مشاهده و تغییر وضعیت

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

تیکت بسته شده ای وجود ندارد

</div>

<?php endif; ?>

</div>

</div>

<?php include 'includes/footer.php'; ?>
