<?php

require '../includes/admin_auth.php';
require_once '../includes/ticket_helpers.php';
require_once '../includes/pagination_helpers.php';
require_once '../includes/ticket_status_helpers.php';

if(
isset($_GET['action'])
&&
isset($_GET['id'])
){

    $id = (int)$_GET['id'];

    if($_GET['action'] === 'delete'){

        admin_require_super();

        ticket_delete($pdo, $id);

        header('Location: tickets.php');
        exit;

    }

    if($_GET['action']=='close'){

        ticket_mark_closed($pdo, $id, 'admin');

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

$pagination = pagination_parse_request();
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];

$where = [];
$params = [];

$where[] = ticket_sql_current_scope('t');

if(!empty($_GET['status'])){
    $where[] = "t.status=?";
    $params[] = $_GET['status'];
}

if(!empty($_GET['category'])){
    $where[] = "t.category=?";
    $params[] = $_GET['category'];
}

$searchQuery = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';

if($searchQuery !== ''){
    $where[] = "(t.title LIKE ? OR u.fullname LIKE ? OR t.tracking_code LIKE ?)";
    $searchPattern = '%' . $searchQuery . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

$whereSql = "WHERE " . implode(" AND ",$where);

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
    ORDER BY t.id DESC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);
$tickets = $stmt->fetchAll();

$categoryOptions = $pdo->query("
    SELECT DISTINCT category
    FROM tickets
    WHERE category IS NOT NULL
    ORDER BY category ASC
")->fetchAll();

$ticketsFilterQuery = [];

if($searchQuery !== ''){
    $ticketsFilterQuery['search'] = $searchQuery;
}

if($categoryFilter !== ''){
    $ticketsFilterQuery['category'] = $categoryFilter;
}

if($statusFilter !== ''){
    $ticketsFilterQuery['status'] = $statusFilter;
}

$back_url = 'index.php';
$page_title = '🎫 تیکت‌های باز';
$page_header_menu_type = 'ticket-search';

require '../includes/header.php';

ticket_list_print_layout_styles();
?>

<style>

.ticket-page{

    max-width:950px;

    margin:auto;

}

.card{

    background:white;

    border-radius:24px;

    padding:22px;

    margin-bottom:20px;

    box-shadow:0 0 20px rgba(0,0,0,.05);

}

.ticket-card{

    background:#fff;

    border-radius:24px;

    padding:22px;

    margin-bottom:18px;

    border:1px solid #eef2f7;

    box-shadow:0 8px 30px rgba(15,23,42,.05);

}

.ticket-title-box{

    background:#f8fafc;

    border:1px solid #e2e8f0;

    border-radius:18px;

    padding:16px;

    min-height:68px;

    display:flex;

    align-items:center;

    justify-content:flex-start;

    font-size:14px;

    font-weight:700;

    color:#0f172a;

    line-height:28px;

    margin-bottom:18px;

}

a.ticket-title-box{
    text-decoration:none;
    cursor:pointer;
    transition:background .2s,border-color .2s;
}

a.ticket-title-box:hover{
    background:#eff6ff;
    border-color:#bfdbfe;
}

.ticket-bottom{

    margin-top:2px;

}

.ticket-bottom-meta{
    display:flex;
    flex-wrap:nowrap;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    width:100%;
    min-width:0;
}

.ticket-bottom-meta .ticket-statuses{
    flex-wrap:nowrap;
    flex-shrink:0;
    gap:6px;
}

.ticket-bottom-meta .ticket-badge{
    padding:6px 10px;
    font-size:11px;
    gap:5px;
    flex-shrink:0;
}

.ticket-bottom-meta .ticket-badge__icon{
    font-size:10px;
}

.ticket-bottom-meta .ticket-badge--user{
    min-width:0;
    max-width:46%;
    flex-shrink:1;
    margin-left:0;
    margin-right:0;
}

.ticket-bottom-meta .ticket-badge--user .ticket-badge__text{
    white-space:nowrap;
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

.ticket-search-modal-overlay{
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.45);
    backdrop-filter:blur(8px);
    z-index:100000;
    display:none;
    align-items:center;
    justify-content:center;
    padding:20px;
}

.ticket-search-modal-overlay.show{
    display:flex;
}

.ticket-search-modal{
    width:100%;
    max-width:460px;
    background:#ffffff;
    border-radius:24px;
    padding:24px 22px;
    box-shadow:0 20px 50px rgba(15,23,42,.18);
    position:relative;
}

.ticket-search-modal-title{
    font-size:20px;
    font-weight:800;
    color:#0f172a;
    margin-bottom:18px;
    padding-left:36px;
}

.ticket-search-modal-close{
    position:absolute;
    left:16px;
    top:16px;
    width:34px;
    height:34px;
    border:none;
    border-radius:12px;
    background:#f1f5f9;
    color:#64748b;
    font-size:22px;
    line-height:1;
    cursor:pointer;
}

.search-field-label{
    display:block;
    font-size:13px;
    font-weight:800;
    color:#334155;
    margin-bottom:8px;
}

.search-field-group{
    margin-bottom:14px;
}

@media(max-width:768px){

    .ticket-bottom-meta .ticket-badge{
        padding:5px 8px;
        font-size:10px;
    }

    .ticket-bottom-meta .ticket-badge--user{
        max-width:38%;
    }

}

</style>

<div class="ticket-page ticket-list-page">

<div class="ticket-list-shell">

<?php if(count($tickets)): ?>

<?php foreach($tickets as $ticket): ?>

<div class="ticket-card">

<?php ticket_render_top_bar($ticket, ['menu' => 'admin_list', 'is_super' => admin_is_super()]); ?>

<a
href="view-ticket.php?id=<?= (int)$ticket['id'] ?>"
class="ticket-title-box">

<?= htmlspecialchars((string)$ticket['title'], ENT_QUOTES, 'UTF-8') ?>

</a>

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
    $ticketsFilterQuery
);
?>

<?php else: ?>

<div class="empty-box">
تیکت باز وجود ندارد
</div>

<?php endif; ?>

</div>
</div>

<div
class="ticket-search-modal-overlay"
id="ticketSearchModalOverlay"
aria-hidden="true">

<div class="ticket-search-modal" role="dialog" aria-modal="true">

<button
type="button"
class="ticket-search-modal-close"
onclick="closeTicketSearchModal()"
aria-label="بستن">

×

</button>

<h2 class="ticket-search-modal-title">جستجوی تیکت‌های باز</h2>

<form method="GET" id="ticketSearchForm">

<div class="search-field-group">
<label class="search-field-label" for="ticketSearchInput">عنوان، کاربر یا کد پیگیری</label>
<input
type="text"
id="ticketSearchInput"
name="search"
class="form-control"
placeholder="جستجوی عنوان، کاربر یا کد پیگیری"
value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
</div>

<div class="search-field-group">
<label class="search-field-label" for="ticketCategoryFilter">دسته‌بندی</label>
<select name="category" id="ticketCategoryFilter" class="form-control">
<option value="">همه دسته‌بندی‌ها</option>
<?php foreach($categoryOptions as $cat): ?>
<option
value="<?= htmlspecialchars($cat['category'], ENT_QUOTES, 'UTF-8') ?>"
<?= $categoryFilter === $cat['category'] ? 'selected' : '' ?>>
<?= htmlspecialchars(ticket_category_plain_label((string)$cat['category']), ENT_QUOTES, 'UTF-8') ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="search-field-group">
<label class="search-field-label" for="ticketStatusFilter">وضعیت</label>
<select name="status" id="ticketStatusFilter" class="form-control">
<option value="">همه وضعیت‌ها</option>
<option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>باز</option>
<option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>درحال بررسی</option>
<option value="admin_reply" <?= $statusFilter === 'admin_reply' ? 'selected' : '' ?>>پاسخ ادمین</option>
<option value="user_reply" <?= $statusFilter === 'user_reply' ? 'selected' : '' ?>>پاسخ کاربر</option>
</select>
</div>

<button type="submit" class="btn-custom">جستجو</button>

</form>

</div>

</div>

<script>

const ticketSearchModalOverlay =
document.getElementById('ticketSearchModalOverlay');

function closeTicketSearchModal(){

    if(!ticketSearchModalOverlay){
        return;
    }

    ticketSearchModalOverlay.classList.remove('show');
    ticketSearchModalOverlay.setAttribute('aria-hidden', 'true');

    const dropdown =
    document.getElementById('pageHeaderDropdown');

    const menuBtn =
    document.getElementById('pageHeaderMenuBtn');

    if(dropdown){
        dropdown.classList.remove('show');
    }

    if(menuBtn){
        menuBtn.setAttribute('aria-expanded', 'false');
    }

}

function openTicketSearchModal(){

    if(!ticketSearchModalOverlay){
        return;
    }

    ticketSearchModalOverlay.classList.add('show');
    ticketSearchModalOverlay.setAttribute('aria-hidden', 'false');

    const searchInput =
    document.getElementById('ticketSearchInput');

    if(searchInput){
        searchInput.focus();
    }

}

if(ticketSearchModalOverlay){

    ticketSearchModalOverlay.addEventListener('click', function(event){

        if(event.target === ticketSearchModalOverlay){
            closeTicketSearchModal();
        }

    });

}

document.addEventListener('keydown', function(event){

    if(
        event.key === 'Escape' &&
        ticketSearchModalOverlay &&
        ticketSearchModalOverlay.classList.contains('show')
    ){
        closeTicketSearchModal();
    }

});

</script>
<?php include '../includes/footer.php'; ?>
