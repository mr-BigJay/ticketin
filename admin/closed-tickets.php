<?php

require '../includes/admin_auth.php';
require_once '../includes/ticket_helpers.php';
require_once '../includes/pagination_helpers.php';
require_once '../includes/ticket_status_helpers.php';

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

$pagination = pagination_parse_request();
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];
$search = trim($_GET['search'] ?? '');

$where = [ticket_sql_closed_scope('t')];
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
$totalPages = pagination_total_pages($total, $limit);
$page = pagination_clamp_page($page, $totalPages);

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

$closedTicketsFilterQuery = [];

if($search !== ''){
    $closedTicketsFilterQuery['search'] = $search;
}

$back_url = 'index.php';
$page_title = '✅ تیکت‌های بسته';

require '../includes/header.php';

ticket_list_print_layout_styles();

?>

<style>
.ticket-page{max-width:950px;margin:auto;}
.ticket-list-shell{padding:0;margin:0;background:transparent;border:none;box-shadow:none;}
.ticket-list-search{background:#fff;border-radius:24px;padding:22px;margin-bottom:18px;box-shadow:0 0 20px rgba(0,0,0,.05);}
.ticket-card{background:#fff;border-radius:24px;padding:22px;margin-bottom:18px;border:1px solid #eef2f7;box-shadow:0 8px 30px rgba(15,23,42,.05);}
.ticket-title-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;padding:16px;min-height:68px;display:flex;align-items:center;justify-content:flex-start;font-size:14px;font-weight:700;color:#0f172a;line-height:28px;margin-bottom:18px;}
a.ticket-title-box{text-decoration:none;cursor:pointer;transition:background .2s,border-color .2s;}
a.ticket-title-box:hover{background:#eff6ff;border-color:#bfdbfe;}
.ticket-bottom{margin-top:2px;}
.ticket-bottom-meta{display:flex;flex-wrap:nowrap;align-items:center;justify-content:space-between;gap:8px;width:100%;min-width:0;}
.ticket-bottom-meta .ticket-statuses{flex-wrap:nowrap;flex-shrink:0;gap:6px;}
.ticket-bottom-meta .ticket-badge{padding:6px 10px;font-size:11px;gap:5px;flex-shrink:0;}
.ticket-bottom-meta .ticket-badge__icon{font-size:10px;}
.ticket-bottom-meta .ticket-badge--user{min-width:0;max-width:46%;flex-shrink:1;margin-left:0;margin-right:0;}
.ticket-bottom-meta .ticket-badge--user .ticket-badge__text{white-space:nowrap;}
.empty-box{background:#fff;border-radius:24px;padding:30px;text-align:center;color:#64748b;border:1px solid #eef2f7;box-shadow:0 8px 30px rgba(15,23,42,.05);}
@media(max-width:720px){
    .ticket-bottom-meta .ticket-badge--user{max-width:38%;}
}
</style>

<div class="ticket-page ticket-list-page">

<div class="card ticket-list-search">
<form method="GET">
<input type="text" name="search" class="form-control" placeholder="جستجوی عنوان، کاربر یا کد پیگیری" value="<?= htmlspecialchars($search) ?>">
<button type="submit" class="btn-custom">جستجو</button>
</form>
</div>

<div class="ticket-list-shell">
<?php if(count($tickets)): ?>
<?php foreach($tickets as $ticket): ?>
<div class="ticket-card">
<?php ticket_render_top_bar($ticket, ['menu' => 'admin_list_closed', 'is_super' => admin_is_super(), 'datetime_at' => 'closed_at']); ?>
<a href="view-ticket.php?id=<?= (int)$ticket['id'] ?>" class="ticket-title-box"><?= htmlspecialchars($ticket['title']) ?></a>
<div class="ticket-bottom">
<div class="ticket-bottom-meta">
<?php ticket_status_render_ticket_badges($ticket, 'admin'); ?>
<span class="ticket-badge ticket-badge--user">
<span class="ticket-badge__icon" aria-hidden="true">👤</span>
<span class="ticket-badge__text"><?= htmlspecialchars((string)$ticket['fullname'], ENT_QUOTES, 'UTF-8') ?></span>
</span>
</div>
</div>
</div>
<?php endforeach; ?>

<?php
pagination_render_bar(
    $page,
    $limit,
    $total,
    $totalPages,
    $closedTicketsFilterQuery
);
?>

<?php else: ?>
<div class="empty-box">تیکت بسته‌ای یافت نشد</div>
<?php endif; ?>
</div>

</div>

<?php include '../includes/footer.php'; ?>
