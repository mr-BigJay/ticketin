<?php

require 'includes/auth.php';
require 'includes/db.php';

$page_title = '🎫 تیکت های جاری';
$back_url = 'dashboard.php';

$search = trim($_GET['search'] ?? '');
$user_id = (int)$_SESSION['user_id'];

if(isset($_GET['action']) && $_GET['action'] === 'subs'){

    $center_id = (int)$_GET['center_id'];
    $type = trim($_GET['type']);

    $stmt = $pdo->prepare("
        SELECT id, name
        FROM organization_nodes
        WHERE parent_id=? AND type=?
        ORDER BY sort_order ASC, id ASC
    ");

    $stmt->execute([$center_id, $type]);
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;

}

$where = "WHERE user_id=? AND status != 'closed'";
$params = [$user_id];

if($search){
    $where .= " AND (title LIKE ? OR tracking_code LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$stmt = $pdo->prepare("
    SELECT *
    FROM tickets
    $where
    ORDER BY id DESC
");
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$statusText = [
    'open' => 'باز',
    'pending' => 'درحال بررسی',
    'progress' => 'درحال بررسی',
    'closed' => 'بسته',
];

$replyText = [
    'admin_reply' => 'پاسخ ادمین',
    'user_reply' => 'پاسخ شما',
];

require 'includes/header.php';

?>

<style>

.ticket-page{

    max-width:950px;

    margin:auto;

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

    flex-wrap:wrap;

}

.ticket-status-badge{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    padding:8px 14px;

    border-radius:999px;

    color:#fff;

    font-size:12px;

    font-weight:700;

    line-height:1.2;

}

.ticket-status-badge.open{

    background:#2563eb;

}

.ticket-status-badge.pending,
.ticket-status-badge.progress{

    background:#f59e0b;

}

.ticket-status-badge.closed{

    background:#111827;

}

.ticket-status-badge.admin_reply{

    background:#16a34a;

}

.ticket-status-badge.user_reply{

    background:#dc2626;

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

    flex-shrink:0;

}

.ticket-btn:hover{

    background:#eff6ff;

    border-color:#bfdbfe;

    color:#0369a1;

    transform:translateY(-1px);

}

.search-box{

    display:flex;

    gap:10px;

    align-items:center;

    margin-bottom:20px;

}

.search-box .form-control{

    margin-bottom:0;

}

.search-box .btn-custom{

    width:auto;

    min-width:120px;

}

.empty-box{

    background:#fff;

    border-radius:24px;

    padding:30px;

    text-align:center;

    color:#64748b;

    border:1px solid #eef2f7;

    box-shadow:0 8px 30px rgba(15,23,42,.05);

}

@media(max-width:768px){

    .ticket-bottom{

        flex-direction:column;

        align-items:stretch;

    }

    .ticket-btn{

        width:100%;

    }

    .ticket-statuses{

        justify-content:center;

    }

    .search-box{

        flex-direction:column;

    }

    .search-box .btn-custom{

        width:100%;

    }

}

</style>

<div class="ticket-page">

<div class="card">

<form method="GET" class="search-box">

<input
type="text"
name="search"
value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
class="form-control"
placeholder="جستجو بر اساس شماره پیگیری یا عنوان">

<button type="submit" class="btn-custom">
جستجو
</button>

</form>

<?php if(count($tickets)): ?>

<?php foreach($tickets as $ticket): ?>

<?php
$statusKey = $ticket['status'] ?? '';
$statusClass = $statusKey === 'progress' ? 'pending' : $statusKey;
$replyKey = $ticket['last_reply_by'] ?? '';
?>

<div class="ticket-card">

<div class="ticket-top">

<span class="tracking-code">
#<?= htmlspecialchars((string)$ticket['tracking_code'], ENT_QUOTES, 'UTF-8') ?>
</span>

<span>
📂 <?= htmlspecialchars((string)$ticket['category'], ENT_QUOTES, 'UTF-8') ?>
</span>

<span>
🕒 <?= fa_datetime($ticket['created_at']) ?>
</span>

</div>

<div class="ticket-title-box">
<?= htmlspecialchars((string)$ticket['title'], ENT_QUOTES, 'UTF-8') ?>
</div>

<div class="ticket-bottom">

<div class="ticket-statuses">

<?php if(isset($statusText[$statusKey])): ?>
<span class="ticket-status-badge <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
<?= htmlspecialchars($statusText[$statusKey], ENT_QUOTES, 'UTF-8') ?>
</span>
<?php endif; ?>

<?php if($replyKey && isset($replyText[$replyKey])): ?>
<span class="ticket-status-badge <?= htmlspecialchars($replyKey, ENT_QUOTES, 'UTF-8') ?>">
<?= htmlspecialchars($replyText[$replyKey], ENT_QUOTES, 'UTF-8') ?>
</span>
<?php endif; ?>

</div>

<a
href="view-ticket.php?id=<?= (int)$ticket['id'] ?>"
class="ticket-btn">

مشاهده و پاسخ

</a>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="empty-box">
تیکت جاری وجود ندارد
</div>

<?php endif; ?>

</div>

</div>

<?php include 'includes/footer.php'; ?>
