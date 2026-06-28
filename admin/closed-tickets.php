<?php

require '../includes/admin_auth.php';
require_once '../includes/ticket_helpers.php';

if(
    isset($_GET['action'], $_GET['id'])
    &&
    $_GET['action'] === 'delete'
){
    admin_require_super();

    ticket_delete($pdo, (int)$_GET['id']);

    header('Location: closed-tickets.php');
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

$where = ["t.status='closed'"];
$params = [];

if($search){
    $where[] = "(t.title LIKE ? OR u.fullname LIKE ? OR t.tracking_code LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    $whereSql
");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['total'];
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

$back_url = 'index.php';
$page_title = '✅ تیکت‌های رفع شده';

require '../includes/header.php';

?>

<style>
.page-box{max-width:1100px;margin:auto;}
.card{background:white;border-radius:24px;padding:22px;margin-bottom:20px;box-shadow:0 0 20px rgba(0,0,0,.05);}
.ticket-row{background:#fff;border-radius:24px;padding:22px;margin-bottom:16px;border:1px solid #eef2f7;box-shadow:0 8px 30px rgba(15,23,42,.05);}
.ticket-top{display:flex;justify-content:center;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:16px;color:#64748b;font-size:13px;}
.tracking-code{background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;border-radius:999px;padding:6px 14px;font-weight:800;}
.ticket-title-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;padding:16px;min-height:60px;margin-bottom:16px;font-weight:700;line-height:30px;}
.ticket-btn{display:block;width:100%;text-align:center;background:linear-gradient(135deg,#0284c7,#06b6d4);color:white;text-decoration:none;padding:12px;border-radius:14px;font-size:13px;font-weight:700;}
.empty-box{text-align:center;padding:35px;color:#777;}
.pagination{display:flex;justify-content:center;gap:8px;margin-top:20px;}
.page-link{min-width:42px;height:42px;display:flex;align-items:center;justify-content:center;text-decoration:none;border-radius:14px;background:#f8fafc;color:#334155;border:1px solid #e2e8f0;}
.active-page{background:linear-gradient(135deg,#0284c7,#06b6d4);color:white;border:none;}
.ticket-actions{display:flex;gap:10px;margin-top:16px;flex-wrap:wrap;}
.ticket-actions .ticket-btn{flex:1;width:auto;}
.ticket-btn-secondary{display:block;flex:1;text-align:center;background:#fef2f2;color:#dc2626;text-decoration:none;padding:12px;border-radius:14px;font-size:13px;font-weight:700;border:1px solid #fecaca;}
</style>

<div class="page-box">

<div class="card">
<form method="GET">
<input type="text" name="search" class="form-control" placeholder="جستجوی عنوان، کاربر یا کد پیگیری" value="<?= htmlspecialchars($search) ?>">
<button type="submit" class="btn-custom">جستجو</button>
</form>
</div>

<div class="card">
<?php if(count($tickets)): ?>
<?php foreach($tickets as $ticket): ?>
<div class="ticket-row">
<div class="ticket-top">
<span class="tracking-code"><?= htmlspecialchars($ticket['tracking_code']) ?></span>
<span>👤 <?= htmlspecialchars($ticket['fullname']) ?></span>
<span>🕒 <?= fa_datetime($ticket['closed_at'] ?: $ticket['created_at']) ?></span>
</div>
<div class="ticket-title-box"><?= htmlspecialchars($ticket['title']) ?></div>
<div class="ticket-actions">
<a href="view-ticket.php?id=<?= $ticket['id'] ?>" class="ticket-btn">مشاهده تیکت</a>
<?php if(admin_is_super()): ?>
<a
href="?action=delete&id=<?= (int)$ticket['id'] ?>"
class="ticket-btn-secondary"
onclick="return confirm('آیا از حذف این تیکت اطمینان دارید؟ این عمل غیرقابل بازگشت است.');">
حذف تیکت
</a>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>

<div class="pagination">
<?php for($i = 1; $i <= $totalPages; $i++): ?>
<a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="page-link <?= $page === $i ? 'active-page' : '' ?>"><?= $i ?></a>
<?php endfor; ?>
</div>

<?php else: ?>
<div class="empty-box">تیکت رفع‌شده‌ای یافت نشد</div>
<?php endif; ?>
</div>

</div>

<?php include '../includes/footer.php'; ?>
